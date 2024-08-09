<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class training_api
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Nabil Hamdi <nabil.hamdi@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

use dml_missing_record_exception;
use local_mentor_core\event\training_create;
use local_mentor_core\task\duplicate_training_task;

defined('MOODLE_INTERNAL') || die();
define('TRAINING_NAME_USED', -1);

require_once($CFG->dirroot . '/local/mentor_core/classes/database_interface.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/training.php');
require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/licenselib.php');
require_once($CFG->libdir . '/setuplib.php');

/**
 * training API
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Nabil Hamdi <nabil.hamdi@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class training_api {

    private static $trainings = [];

    /**
     * Create a training.
     *
     * @param \stdClass $data
     * @param training_form|null $form
     * @return training
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \Exception
     */
    public static function create_training($data, $form = null) {

        // Check required fields.
        $requiredfields = ['name', 'shortname', 'categorychildid', 'content'];

        foreach ($requiredfields as $requiredfield) {
            if (!isset($data->{$requiredfield})) {
                throw new \Exception('Fields ' . $requiredfield . ' is missing.');
            }
        }

        $context = \context_coursecat::instance($data->categoryid);

        // Check user capabilities.
        if (!has_capability('local/trainings:create', $context)) {
            throw new \Exception(get_string('unauthorisedaction', 'local_mentor_core'), 2020120810);
        }

        $db = database_interface::get_instance();

        // Check if shortname exists.
        if ($db->course_exists($data->shortname) ||
            $db->course_exists_in_recyclebin($data->shortname)) {
            throw new \Exception('Shortname already exists : ' . $data->shortname);
        }

        // Training summary.
        if (is_array($data->content)) {
            $data->content = $data->content['text'];
        }

        // Create the training course.
        $course = [
            'fullname' => $data->name,
            'shortname' => $data->shortname,
            'categoryid' => $data->categorychildid,
            'courseformatoptions' =>
                [
                    [
                        'name' => 'summary',
                        'value' => $data->content,
                    ],
                ],
        ];

        // Create the training course.
        try {
            $courses = training::create_course_training($course);
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

        $data->shortname = current($courses)['shortname'];

        // Check if only one course has been created.
        if (!is_array($courses) || count($courses) != 1) {

            if (is_array($courses)) {

                // Delete all created courses.
                foreach ($courses as $course) {
                    delete_course($course->id);
                }
            }
            // Throw an exception.
            throw new \Exception('erreurcreatecourse');
        }

        // Create an empty training object.
        $trainingobj = new \stdClass();
        $trainingobj->courseshortname = $data->shortname;
        $trainingobj->status = $data->status;

        // Create the training in database.
        $trainingid = $db->add_training($trainingobj);

        // Instantiate the training object.
        $training = self::get_training($trainingid);

        // Update the training object with extra form data.
        $training->update($data, $form);

        // Trigger a training created event.
        $event = training_create::create([
            'objectid' => $training->id,
            'context' => $training->get_context(),
        ]);
        $event->trigger();

        // Refresh the training object.
        $training = self::get_training($trainingid, true);

        // Add training to api cache.
        self::$trainings[$training->id] = $training;

        // Return the final training object.
        return self::$trainings[$training->id];
    }

    /**
     * Move a training into another entity
     *
     * @param int $trainingid
     * @param int $destinationentityid
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function move_training($trainingid, $destinationentityid) {

        // Instantiate the training object.
        $training = self::get_training($trainingid);

        $trainingentity = $training->get_entity();

        // The training is already in the destination entity.
        if ($trainingentity->id == $destinationentityid) {
            return true;
        }

        // Check user capabilities.
        if (!has_capability('local/trainings:manage', $trainingentity->get_context())) {
            throw new \Exception(get_string('unauthorisedaction', 'local_mentor_core'), 2020120810);
        }

        $destinationentity = entity_api::get_entity($destinationentityid, false);

        // Check user capabilities.
        if (!has_capability('local/trainings:create', $destinationentity->get_context())) {
            throw new \Exception(get_string('unauthorisedaction', 'local_mentor_core'), 2020120810);
        }

        // Move the course into the new category.
        return move_courses([$training->courseid], $destinationentity->get_entity_formation_category());
    }

    /**
     * Update a training.
     *
     * @param \stdClass $data
     * @param training_form $form optional. Used to store files uploaded in file pickers.
     * @return training
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function update_training($data, $form = null) {

        // Get the training object.
        $trainingid = $data->id;
        $training = self::get_training($trainingid);
        $context = $training->get_context();

        // Check capabilities.
        if (!has_capability('local/trainings:update', $context)) {
            throw new \Exception(get_string('unauthorisedaction', 'local_mentor_core'), 2020120810);
        }

        // Capture the updated fields for the log data.
        $updatedfields = [];
        foreach (get_object_vars($training) as $field => $value) {

            // Exception data.
            if ($field === 'creativestructure' || $field === 'thumbnail') {
                continue;
            }

            // Collection and skills data.
            // Array to string like data registration.
            if (($field === 'collection' || $field === 'skills') && isset($data->$field) && is_array($data->$field)) {
                $collectiondata = implode(',', $data->$field);
                if ($collectiondata == $value) {
                    continue;
                }
            }

            // Content and trainggoal data.
            // juste text element like data registration.
            if (($field === 'content' || $field === 'traininggoal') && isset($data->$field) && isset($data->{$field}['text'])) {
                if ($value != $data->{$field}['text']) {
                    $updatedfields[$field] = $data->{$field}['text'];
                }
                continue;
            }

            // Other field.
            if (isset($data->$field) && $data->$field != $value) {
                $updatedfields[$field] = $data->$field;
            }
        }

        // Convert presence estimate time form data to minutes.
        if (isset($data->presenceestimatedtimehours) && isset($data->presenceestimatedtimeminutes)) {
            $presenceestimateddata = (intval($data->presenceestimatedtimehours) * 60) + intval($data->presenceestimatedtimeminutes);
            if ($presenceestimateddata != $training->presenceestimatedtime) {
                $updatedfields['presenceestimated'] = $presenceestimateddata;
            }
        }

        // Convert remote estimate time form data to minutes.
        if (isset($data->remoteestimatedtimehours) && isset($data->remoteestimatedtimeminutes)) {
            $remoteestimateddate = (intval($data->remoteestimatedtimehours) * 60) + intval($data->remoteestimatedtimeminutes);
            if ($remoteestimateddate != $training->remoteestimatedtime) {
                $updatedfields['remoteestimatedtime'] = $remoteestimateddate;
            }
        }

        // Update the training.
        $training->update($data, $form);

        // Trigger a training updated event.
        $event = \local_mentor_core\event\training_update::create([
            'objectid' => $trainingid,
            'context' => $training->get_context(),
            'other' => [
                'updatedfields' => $updatedfields,
            ],
        ]);
        $event->set_legacy_logdata([
            $trainingid, 'training', 'update', 'local/trainings/pages/update_training.php?trainingid=' . $trainingid,
            $trainingid,
        ]);
        $event->trigger();

        // Return a refreshed training object.
        return self::get_training($trainingid, true);
    }

    /**
     * Get a training object instance
     *
     * @param int $trainingid
     * @param bool $refresh - true to get a fresh object. Default to false
     * @return false|training
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_training($trainingid, $refresh = false) {

        if ($refresh || !isset(self::$trainings[$trainingid])) {
            $specialization = specialization::get_instance();

            try {
                // Get the specialization of the training object.
                $training = $specialization->get_specialization('get_training', $trainingid);

                // If the training has no specialization, then instantiate a standard training object.
                if (!is_object($training)) {
                    $training = new training($trainingid);
                }

                self::$trainings[$trainingid] = $training;
            } catch (\dml_exception $e) {
                // Can't find data record in database.
                return false;
            }
        }

        return self::$trainings[$trainingid];
    }

    /**
     * Get training course
     *
     * @param int $trainingid
     * @return \stdClass
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_training_course($trainingid) {
        $training = self::get_training($trainingid);
        return $training->get_course();
    }

    /**
     * Get a training by course id
     *
     * @param int $courseid
     * @return false|training
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_training_by_course_id($courseid) {

        $db = database_interface::get_instance();

        if (!$dbtraining = $db->get_training_by_course_id($courseid)) {
            return false;
        }
        return self::get_training($dbtraining->id);
    }

    /**
     * Get all entity trainings
     *
     * @param int|object $data can be an entity id or a search object
     * @return array of trainings. Each training contains : data, url, actions
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_trainings_by_entity($data) {
        global $USER;

        $specialization = specialization::get_instance();

        // Get the specialization of the session list.
        $trainings = $specialization->get_specialization('get_trainings_by_entity', null, [
            'data' => $data,
        ]);

        // Return the specialization data.
        if (!is_null($trainings)) {
            return $trainings;
        }

        if (is_object($data)) {
            $entityid = $data->entityid;
        } else {
            $entityid = $data;
        }

        $entity = entity_api::get_entity($entityid, false);

        // Get all entity trainings objects.
        $trainingsrecord = $entity->get_trainings();

        $trainingsarray = [];

        // Format trainings as array.
        foreach ($trainingsrecord as $key => $training) {
            $training = self::get_training($training->id);
            $trainingentity = $training->get_entity(false);

            $ismainentity = $trainingentity->is_main_entity();

            // The user has access if it is a main entity or if he manages trainings on this entity or its sub-entity.
            if ($ismainentity || $trainingentity->is_trainings_manager($USER)) {
                $trainingsarray[$key] = [];
                $trainingsarray[$key]['data'] = $training;
                $trainingsarray[$key]['data']->subentityname = !$ismainentity ? $trainingentity->get_name() : '';
                $trainingsarray[$key]['data']->entityid = $trainingentity->id;
                $trainingsarray[$key]['url'] = $trainingsarray[$key]['data']->get_url()->out();
                $trainingsarray[$key]['actions'] = $trainingsarray[$key]['data']->get_actions();
            }
        }

        return $trainingsarray;
    }

    /**
     * Count all entity trainings
     *
     * @param int|object $data can be an entity id or a search object
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function count_trainings_by_entity($data) {
        $specialization = specialization::get_instance();

        // Get the specialization of the session list.
        return $specialization->get_specialization('count_trainings_by_entity', null, [
            'data' => $data,
        ]);
    }

    /**
     * Remove a training
     *
     * @param int $trainingid
     * @return bool result of the deletion
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public static function remove_training($trainingid) {
        $training = self::get_training($trainingid);
        return $training->delete();
    }

    /**
     * Duplicate a training
     *
     * @param int $trainingid
     * @param string $trainingshortname
     * @param int $destinationentity optional default null move the created training into a new entity
     * @param bool $executenow - true to execute the duplication now
     * @return training|bool the created training
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public static function duplicate_training($trainingid, $trainingshortname, $destinationentity = null, $executenow = false) {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/local/mentor_core/classes/task/duplicate_training_task.php');
        require_once($CFG->dirroot . '/local/mentor_core/classes/task/duplicate_session_as_new_training_task.php');

        $dbinterface = database_interface::get_instance();

        // Check if session name is not already in use.
        if ($dbinterface->training_exists($trainingshortname)) {
            return TRAINING_NAME_USED;
        }

        // Check if training name is not already in use.
        if ($dbinterface->course_shortname_exists($trainingshortname)) {
            return TRAINING_NAME_USED;
        }

        // Get the training.
        $oldtraining = self::get_training($trainingid);

        $course = get_course($oldtraining->courseid);

        $context = \context_coursecat::instance($course->category);

        // Check user capabilities.
        if (!has_capability('local/trainings:create', $context) &&
            (!has_capability('local/trainings:createinsubentity', $context) &&
             $oldtraining->status != \local_mentor_core\training::STATUS_TEMPLATE)) {
            throw new \required_capability_exception($context, 'local/trainings:create', 'nopermissions', '');
        }

        $adhoctask = new duplicate_training_task();

        $adhoctask->set_custom_data([
            'trainingid' => $trainingid,
            'trainingshortname' => $trainingshortname,
            'destinationentity' => $destinationentity,
        ]);

        // Use the current user id to launch the adhoc task.
        $adhoctask->set_userid($USER->id);

        // Execute the task now.
        if ($executenow) {
            return $adhoctask->execute();
        }

        // Queued the task.
        return \core\task\manager::queue_adhoc_task($adhoctask);
    }

    /**
     * Get all edadmin - trainings courses that a user can manage
     *
     * @param \stdClass|null $user default null
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_user_training_courses($user = null) {

        // Use the current user of the $user parameter is empty.
        if (empty($user)) {
            global $USER;
            $user = $USER;
        }

        // Get all entities.
        $entities = entity_api::get_all_entities(false);

        $courses = [];

        foreach ($entities as $entity) {

            // Check if the user can manage the trainings of the entity.
            if ($entity->is_trainings_manager($user)) {
                $course = $entity->get_main_entity()->get_edadmin_courses('trainings');
                $courses[$course['id']] = $course;
            }
        }

        // Return the list of training courses that the user can manage.
        return array_values($courses);
    }

    /**
     * Get all entities where the user can manage trainings
     *
     * @param \stdClass|null $user optional default null to use the current user
     * @return entity[]
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_entities_training_managed($user = null) {

        // Use the current user of the $user parameter is empty.
        if (empty($user)) {
            global $USER;
            $user = $USER;
        }

        // Get all main entities.
        $entities = entity_api::get_all_entities();

        $managed = [];

        foreach ($entities as $entity) {

            // Check if the user can manage entity trainings.
            if (has_capability('local/trainings:manage', $entity->get_context(), $user)) {
                $managed[$entity->id] = $entity;
                continue;
            }

            $subentities = $entity->get_sub_entities();

            foreach ($subentities as $subentity) {
                // Check if the user manages one of the sub-entity linked to this entity.
                if (has_capability('local/trainings:manage', $subentity->get_context(), $user)) {
                    $managed[$entity->id] = $entity;
                    continue;
                }
            }

        }

        return $managed;
    }

    /**
     * Get training form
     *
     * @param string $action
     * @param $params
     * @return training_form
     */
    public static function get_training_form($action, $params) {
        global $CFG;
        require_once($CFG->dirroot . '/local/mentor_core/forms/training_form.php');

        $form = new training_form($action, $params);
        $specialization = specialization::get_instance();

        return $specialization->get_specialization('get_training_form', $form, $params);
    }

    /**
     * Get the specialization of the training template
     *
     * @param string $defaulttemplate
     * @return mixed
     */
    public static function get_trainings_template($defaulttemplate) {
        $specialization = specialization::get_instance();
        return $specialization->get_specialization('get_trainings_template', $defaulttemplate);
    }

    /**
     * Get the specilization of the training javascript
     *
     * @param string $defaultjs
     * @return mixed
     */
    public static function get_trainings_javascript($defaultjs) {
        $specialization = specialization::get_instance();
        return $specialization->get_specialization('get_trainings_javascript', $defaultjs);
    }

    /**
     * Get next available name for a training
     *
     * @param int $trainingid
     * @return string
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_next_available_training_name($trainingid) {
        $db = database_interface::get_instance();
        $training = self::get_training($trainingid);

        return $db->get_next_available_training_name($training->get_course()->shortname);
    }

    /**
     * Get all available sessions by trainings for a given user
     *
     * @param int $userid
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_user_available_sessions_by_trainings($userid, $onlytrainings = false) {

        $specialization = specialization::get_instance();
        $trainings = $specialization->get_specialization(
            'get_user_available_sessions_by_trainings', [], ['userid' => $userid, 'onlytrainings' => $onlytrainings]
        );

        // Default case if the function has no specialization.
        if (empty($trainings)) {
            $sessions = session_api::get_user_available_sessions($userid);

            foreach ($sessions as $session) {

                // Fetch the training for the first time.
                if (!isset($trainings[$session->trainingid])) {
                    // Get a light version of the training.

                    if (!$training = self::get_training($session->trainingid)) {
                        continue;
                    }

                    // Skip hidden entities.
                    if ($training->get_entity()->get_main_entity()->is_hidden()) {
                        continue;
                    }

                    $trainings[$session->trainingid] = $training->convert_for_template();

                    $trainings[$session->trainingid]->sessions = [];
                    $trainings[$session->trainingid]->hasinprogresssessions = false;
                }

                // Get a light version of the session.
                if (!$onlytrainings) {
                    $trainings[$session->trainingid]->sessions[] = $session->convert_for_template();
                }

                // Check if is session in progress.
                if (false === $trainings[$session->trainingid]->hasinprogresssessions
                    && session::STATUS_IN_PROGRESS === $session->status
                ) {
                    $trainings[$session->trainingid]->hasinprogresssessions = true;
                }
            }
        }

        return $trainings;
    }

    /**
     * Returns the trainings that the user designs
     *
     * @param \stdClass $user
     * @param bool $favouritefirst
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_trainings_user_designer($user, $favouritefirst = false) {

        // Get database interface.
        $db = database_interface::get_instance();

        // Get all user courses.
        $enrolcourses = $db->get_user_courses($user->id, 'timecreated DESC');

        // Initialize the list of trainings that the user can edit.
        $trainingsuserdesigner = [];

        foreach ($enrolcourses as $enrolcourse) {

            // Check if the course is a training.
            if (!$training = self::get_training_by_course_id($enrolcourse->id)) {
                continue;
            }

            // Skip hidden entities.
            if ($training->get_entity()->get_main_entity()->is_hidden()) {
                continue;
            }

            $trainingsuserdesigner[] = $training->convert_for_template();
        }

        // Favourite is first.
        // Favourite : Sort by favourite time created.
        // Not favourite : Sort by training full name and time created.
        if ($favouritefirst) {
            // Sort with the user's preferred design training first.
            usort($trainingsuserdesigner, function($a, $b) {
                // Two element not favourite, same place.
                if (!$b->favouritedesignerdata && !$a->favouritedesignerdata) {
                    return 0;
                }

                // A element not favourite, B is up.
                if (!$a->favouritedesignerdata) {
                    return 1;
                }

                // B element not favourite, A is up.
                if (!$b->favouritedesignerdata) {
                    return -1;
                }

                // Check time created to favourite select user.
                return $b->favouritedesignerdata->timecreated <=> $a->favouritedesignerdata->timecreated;
            });
        }

        return $trainingsuserdesigner;
    }

    /**
     * Add a training to the user's preferred designs.
     *
     * @param int $trainingid
     * @param int $userid
     * @return bool|int
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function add_trainings_user_designer_favourite($trainingid, $userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        $training = self::get_training($trainingid);

        $db = database_interface::get_instance();
        return $db->add_trainings_user_designer_favourite($trainingid, $training->get_context()->id, $userid);
    }

    /**
     * Remove a training to the user's preferred designs.
     *
     * @param int $trainingid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function remove_trainings_user_designer_favourite($trainingid, $userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        $training = self::get_training($trainingid);

        $db = database_interface::get_instance();
        return $db->remove_trainings_user_designer_favourite($trainingid, $training->get_context()->id, $userid);
    }

    /**
     * Get the list of training status
     *
     * @return array
     */
    public static function get_status_list() {
        return [
            training::STATUS_DRAFT => training::STATUS_DRAFT,
            training::STATUS_TEMPLATE => training::STATUS_TEMPLATE,
            training::STATUS_ELABORATION_COMPLETED => training::STATUS_ELABORATION_COMPLETED,
            training::STATUS_ARCHIVED => training::STATUS_ARCHIVED,
        ];
    }

    /**
     * Clear trainings cache
     */
    public static function clear_cache() {
        self::$trainings = [];
    }

    /**
     * Override the default training template params
     *
     * @param \stdClass|null $params
     * @return mixed
     */
    public static function get_training_template_params($params = null) {
        if (is_null($params)) {
            $params = new \stdClass();
        }

        $specialization = specialization::get_instance();
        return $specialization->get_specialization('get_training_template_params', $params);
    }

    /**
     * Restore the deleted course from an entity's training
     *
     * @param int $entityid
     * @param int $itemid
     * @param string|null $urlredirect optional default null
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public static function restore_training($entityid, $itemid, $urlredirect = null) {
        global $PAGE, $CFG, $OUTPUT;

        // Get entity.
        $entity = entity_api::get_entity($entityid);

        // Get the entity's trainings recycle bin.
        $trainingcategoryid = $entity->get_entity_formation_category();
        $contexttrainingcategory = \context_coursecat::instance($trainingcategoryid);
        $recyclebin = new \tool_recyclebin\category_bin($contexttrainingcategory->instanceid);

        // Check if the user can restore items.
        if (!$recyclebin->can_restore()) {
            // No permissions to restore.
            throw new \moodle_exception('nopermissions', 'error');
        }

        // Get training's course item.
        $item = $recyclebin->get_item($itemid);

        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/externallib.php');

        $user = get_admin();

        // Get the backup file.
        $fs = get_file_storage();
        $files = $fs->get_area_files($contexttrainingcategory->id, 'tool_recyclebin', TOOL_RECYCLEBIN_COURSECAT_BIN_FILEAREA,
            $item->id,
            'itemid, filepath, filename', false);

        if (empty($files)) {
            throw new \moodle_exception('Invalid recycle bin item!');
        }

        if (count($files) > 1) {
            throw new \moodle_exception('Too many files found!');
        }

        // Get the backup file.
        $file = reset($files);

        // Get a backup temp directory name and create it.
        $tempdir = \restore_controller::get_tempdir_name($contexttrainingcategory->id, $user->id);
        $fulltempdir = make_backup_temp_directory($tempdir);

        // Extract the backup to tmpdir.
        $fb = get_file_packer('application/vnd.moodle.backup');
        $fb->extract_to_pathname($file, $fulltempdir);

        // Build a course.
        $course = new \stdClass();
        $course->category = $trainingcategoryid;
        $course->shortname = $item->shortname;
        $course->fullname = $item->fullname;
        $course->summary = '';

        // Create a new course.
        $course = create_course($course);

        if (!$course) {
            throw new \moodle_exception("Could not create course to restore into.");
        }

        // Define the import.
        $controller = new \restore_controller(
            $tempdir,
            $course->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $user->id,
            \backup::TARGET_NEW_COURSE
        );

        // Prechecks.
        if (!$controller->execute_precheck()) {
            $results = $controller->get_precheck_results();

            // Check if errors have been found.
            if (!empty($results['errors'])) {
                // Delete the temporary file we created.
                fulldelete($fulltempdir);

                // Delete the course we created.
                delete_course($course, false);

                echo $OUTPUT->header();
                $backuprenderer = $PAGE->get_renderer('core', 'backup');
                echo $backuprenderer->precheck_notices($results);
                echo $OUTPUT->continue_button(new \moodle_url('/course/index.php', [
                    'categoryid' =>
                        $contexttrainingcategory->id,
                ]));
                echo $OUTPUT->footer();
                exit();
            }
        }

        // Run the import.
        $controller->execute_plan();

        // Have finished with the controller, let's destroy it, freeing mem and resources.
        $controller->destroy();

        // Fire event.
        $event = \tool_recyclebin\event\category_bin_item_restored::create([
            'objectid' => $item->id,
            'context' => $contexttrainingcategory,
        ]);
        $event->add_record_snapshot('tool_recyclebin_category', $item);
        $event->trigger();

        // Cleanup.
        fulldelete($fulltempdir);
        $recyclebin->delete_item($item);

        // Check shortname and fullname course.
        $courseaftercontroller = get_course($course->id);
        if ($item->shortname !== $courseaftercontroller->shortname || $item->fullname !== $courseaftercontroller->fullname) {

            // Restore shortname and fullname course to link whith training.
            $courseaftercontroller->shortname = $item->shortname;
            $courseaftercontroller->fullname = $item->fullname;
            update_course($courseaftercontroller);
        }

        if (!is_null($urlredirect)) {
            redirect($urlredirect, get_string('alertrestored', 'local_mentor_core', $item), 2);
        }
    }

    /**
     * Get entity selector to trainings recycle bin page template
     *
     * @param int $entityid
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function entity_selector_trainings_recyclebin_template($entityid) {
        global $USER, $PAGE, $OUTPUT;

        // Get managed entities if user has any.
        $managedentities = entity_api::get_managed_entities($USER);
        $trainingmanagedentities = self::get_entities_training_managed($USER);

        $managedentities = $managedentities + $trainingmanagedentities;

        if (count($managedentities) <= 1) {
            return '';
        }

        // Create an entity selector if it manages several entities.
        $data = new \stdClass();
        $data->switchentities = [];

        foreach ($managedentities as $managedentity) {
            if (!$managedentity->is_main_entity()) {
                continue;
            }
            $entitydata = new \stdClass();
            $entitydata->name = $managedentity->name;
            $entitydata->link = new \moodle_url('/local/trainings/pages/recyclebin_trainings.php',
                ['entityid' => $managedentity->id]);
            $entitydata->selected = $entityid == $managedentity->id;
            $data->switchentities[] = $entitydata;
        }

        // Call template.
        $PAGE->requires->string_for_js('pleaserefresh', 'format_edadmin');
        $PAGE->requires->js_call_amd('format_edadmin/format_edadmin', 'selectEntity');
        return $OUTPUT->render_from_template('format_edadmin/entity_select', $data);
    }

    /**
     * Remove the deleted course from an entity's training
     *
     * @param int $entityid
     * @param int $itemid - id from recyclebin
     * @param string|null $urlredirect
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function remove_training_item($entityid, $itemid, $urlredirect = null) {
        // Get entity.
        $entity = entity_api::get_entity($entityid);

        // Get the entity's trainings recycle bin.
        $trainingcategoryid = $entity->get_entity_formation_category();
        $contexttrainingcategory = \context_coursecat::instance($trainingcategoryid);
        $recyclebin = new \tool_recyclebin\category_bin($contexttrainingcategory->instanceid);

        // Get training's course item.
        $item = $recyclebin->get_item($itemid);

        // Delete training's course item.
        $recyclebin->delete_item($item);

        $dbinterface = database_interface::get_instance();
        $dbinterface->delete_training_sheet($item->shortname);

        // Redirect the user.
        if (!is_null($urlredirect)) {
            redirect($urlredirect, get_string('alertdeleted', 'local_mentor_core', $item), 2,
                \core\output\notification::NOTIFY_SUCCESS);
        }
    }

    /**
     * Gives the name of the capability that allows access to the edadmin course of trainings format.
     *
     * @return string
     */
    public static function get_edadmin_course_view_capability() {
        return 'local/mentor_core:movetrainings';
    }
}
