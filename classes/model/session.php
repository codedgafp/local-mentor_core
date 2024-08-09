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
 * Class session
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

use coding_exception;
use context_course;
use dml_exception;
use Exception;
use gradereport_singleview\local\screen\select;
use function local_autogroup\plugin_is_enabled;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/model.php');
require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');
require_once($CFG->dirroot . '/local/mentor_core/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/backup_controller_edu.class.php');

class session extends model {

    public const STATUS_IN_PREPARATION = 'inpreparation';
    public const STATUS_OPENED_REGISTRATION = 'openedregistration';
    public const STATUS_IN_PROGRESS = 'inprogress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_REPORTED = 'reported';
    public const STATUS_CANCELLED = 'cancelled';

    public const OPEN_TO_CURRENT_ENTITY = 'current_entity';
    public const OPEN_TO_CURRENT_MAIN_ENTITY = 'current_main_entity';
    public const OPEN_TO_ALL = 'all';
    public const OPEN_TO_OTHER_ENTITY = 'other_entities';
    public const OPEN_TO_NOT_VISIBLE = 'not_visible';

    public const FAVOURITE = 'favourite_session';

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $courseshortname;

    public $courseid;

    public $contextid;

    /**
     * @var int
     */
    public $trainingid;

    /**
     * @var string
     */
    public $fullname;

    /**
     * @var string
     */
    public $shortname;

    /**
     * @var string
     */
    public $status;

    /**
     * @var string
     */
    public $opento;

    /** @var string */
    public $opentolist;

    /** @var int */
    public $sessionstartdate;

    /** @var int */
    public $sessionenddate;

    /** @var string */
    public $maxparticipants;

    /** @var string */
    public $placesavailable;

    /** @var string */
    public $numberparticipants;

    // Cache the sessions participants.
    protected $participants;

    // Cache the sessions tutors.
    protected $tutors;

    // Cache the sessions formateurs.
    protected $formateurs;

    // Cache the sessions demonstrateurs.
    protected $demonstrateurs;

    /**
     * db session
     *
     * @var stdClass
     */
    protected $session;

    /**
     * Caching
     */
    protected $course;
    protected $context;
    protected $training;
    protected $template;

    protected $courseusers;

    /**
     * Session constructor.
     *
     * @param int|stdClass $sessionidorinstance - id or stdclass from session table
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function __construct($sessionidorinstance) {

        parent::__construct();

        $requiredfields = [
            'id',
            'courseshortname',
            'courseid',
            'contextid',
            'trainingid',
            'fullname',
            'shortname',
            'status',
            'opento',
            'sessionstartdate',
            'sessionenddate',
            'maxparticipants',
        ];

        $this->session = is_object($sessionidorinstance) ? $sessionidorinstance : $this->dbinterface->get_session_by_id
        ($sessionidorinstance);

        foreach ($requiredfields as $requiredfield) {
            if (!property_exists($this->session, $requiredfield)) {
                throw new \Exception('Missing field: ' . $requiredfield);
            }

            $this->{$requiredfield} = $this->session->{$requiredfield};
        }

        $this->numberparticipants = null;
        $this->opentolist = ($this->opento == 'other_entities') ? $this->get_opento_list() : '';
    }

    /**
     * Get the context of the linked course
     *
     * @return context_course
     * @throws dml_exception
     */
    public function get_context() {
        if (empty($this->context)) {
            $this->context = \context_course::instance($this->courseid);
        }
        return $this->context;
    }

    /**
     * Get the course linked to the session
     *
     * @return stdClass
     * @throws dml_exception
     */
    public function get_course($refresh = false) {
        if (empty($this->course) || $refresh) {
            $this->course = $this->dbinterface->get_course_by_shortname($this->shortname);
        }

        return $this->course;
    }

    /**
     * Get course session url
     *
     * @return moodle_url
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_url() {
        return local_mentor_core_get_course_url($this->get_course());
    }

    /**
     * Check the user's ability to manage a session course
     *
     * @param int|stdClass $user
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public function is_manager($userid = null) {
        global $USER;

        if (!$userid) {
            $userid = $USER->id;
        }

        $context = $this->get_context();
        return has_capability('local/session:manage', $context, $userid);
    }

    /**
     * Check the user's ability to create a session course
     *
     * @param integer|stdClass $user
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public function is_creator($user) {
        $context = $this->get_context();
        return has_capability('local/session:create', $context, $user);
    }

    /**
     * Check the user's ability to update a session course
     *
     * @param integer|stdClass $user
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public function is_updater($user) {
        $context = $this->get_context();
        return has_capability('moodle/course:viewhiddenactivities', $context, $user);
    }

    /**
     * Check the user's ability to delete a session course
     *
     * @param integer|stdClass $user
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public function is_deleter($user) {
        $context = $this->get_context();
        return has_capability('local/session:delete', $context, $user);
    }

    /**
     * Check the user is session course trainer
     *
     * @param integer|stdClass $user
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public function is_trainer($user) {
        return $this->is_updater($user);
    }

    /**
     * Check the user is session course tutor
     *
     * @param integer|stdClass $user
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public function is_tutor($user) {
        return !$this->is_updater($user) && has_capability('moodle/course:bulkmessaging', $this->get_context(), $user);
    }

    /**
     * Get sheet session url
     *
     * @param string $returnto
     * @return moodle_url
     * @throws moodle_exception
     */
    public function get_sheet_url(string $returnto = '') {
        $params = [
            'sessionid' => $this->id,
        ];

        if (strlen($returnto) > 0) {
            $params['returnto'] = $returnto;
        }

        return new moodle_url('/local/session/pages/update_session.php', $params);
    }

    /**
     * Get the list of user session actions
     *
     * @param null|int $userid
     * @param bool $refresh - true to fresh move action - optional default false
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_actions($userid = null, $refresh = false) {
        global $USER, $CFG;

        $actions = [];

        if (!$userid) {
            $userid = $USER->id;
        }

        $entity = $this->get_entity();
        $mainentity = $entity->get_main_entity();

        // Check if the user can access the training sheet.
        if ($this->is_updater($userid)) {
            $sessioncourse = $mainentity->get_edadmin_courses('session');
            $url = $CFG->wwwroot . '/course/view.php?id=' . $sessioncourse['id'];
            $actions['sessionSheet'] = [
                'url' => $this->get_sheet_url()->out() . '&returnto=' . $url,
                'tooltip' => get_string('gotosessionsheet', 'local_mentor_core'),
            ];
        }

        $profile = profile_api::get_profile($userid, $refresh);

        // Move session.
        if (has_capability('local/session:create', $entity->get_context()) && $profile->can_move_session($mainentity)) {
            $actions['moveSession'] = [
                'url' => '',
                'tooltip' => get_string('movesession', 'local_mentor_core'),
            ];
        }

        // Manage users buttons.
        if ($this->status != self::STATUS_CANCELLED && $this->status != self::STATUS_ARCHIVED &&
            $this->status != self::STATUS_COMPLETED) {
            // It can assign users because he is manager.
            $actions['manageUser'] = [
                'url' => $CFG->wwwroot . '/user/index.php?id=' . $this->courseid,
                'tooltip' => get_string('manageusers', 'local_mentor_core'),
            ];

            $actions['importUsers'] = [
                'url' => $CFG->wwwroot . '/local/mentor_core/pages/importcsv.php?courseid=' . $this->courseid,
                'tooltip' => get_string('enrolusers', 'local_mentor_core'),
            ];

        }

        // Cancel session button.
        $cancelstates = [
            self::STATUS_OPENED_REGISTRATION,
            self::STATUS_IN_PROGRESS,
            self::STATUS_REPORTED,
        ];

        if (in_array($this->status, $cancelstates)) {
            $actions['cancelSession'] = [
                'url' => '',
                'tooltip' => get_string('cancelsession', 'local_mentor_core'),
            ];
        }

        // Delete session button.
        if (has_capability('local/session:delete', $this->get_context()) && count($this->get_course_users()) == 0) {
            $actions['deleteSession'] = [
                'url' => '',
                'tooltip' => get_string('deletesession', 'local_mentor_core'),
            ];
        }

        return $actions;
    }

    /**
     * Update the session
     *
     * @param stdClass $data
     * @param session_form|null $mform
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function update($data, $mform = null) {

        // If is not modified by a designer.
        if (!isset($data->shortname)) {
            $data->shortname = $this->shortname;
        }
        $data->shortname = trim($data->shortname);

        // Check if the course shortname already exists.
        if (($data->shortname != $this->shortname) && $this->dbinterface->course_shortname_exists($data->shortname)) {
            throw new Exception(get_string('shortnameexist', 'local_trainings'));
        }

        $oldstatus = $this->status;
        $oldmaxparticipants = $this->maxparticipants;

        // If form is modified by a designer.
        if (!isset($data->fullname)) {
            $data->fullname = $this->fullname;
        }
        $data->fullname = trim($data->fullname);

        if (!isset($data->status)) {
            $data->status = $this->status;
        }

        // If form is modified by a designer.
        if (isset($data->sessionstartdate)) {
            $this->sessionstartdate = $data->sessionstartdate;
        }
        if (isset($data->maxparticipants)) {
            $this->maxparticipants = $data->maxparticipants;
        }

        if (isset($data->sessionenddate)) {
            $this->sessionenddate = $data->sessionenddate !== 0 ? $data->sessionenddate : null;
        } else {
            $this->sessionenddate = null;
        }

        $oldcourseshortname = $this->courseshortname;
        $this->courseshortname = $data->shortname;
        $this->status = $data->status;

        // If we want to modify opento field.
        // User need to have specific capability to share session to other spaces.
        if (isset($data->opento) && (
                $data->opento == 'not_visible' ||
                $data->opento == 'current_entity' ||
                has_capability('local/mentor_core:changesessionopentoexternal', $this->get_context())
            )
        ) {
            $this->opento = $data->opento;

            if ($data->opento == 'other_entities' && isset($data->opentolist)) {
                $this->opentolist = implode(',', $data->opentolist);
            } else {
                $this->opentolist = '';
            }

            // Update the session sharing between entities.
            if ($this->opentolist && !$this->update_session_sharing($data->opentolist)) {
                throw new Exception(get_string('sessionsharingfailed'));
            }

            if ($data->opento != 'other_entities') {
                // Remove sharing.
                $this->update_session_sharing([]);
            }
        } else {
            $this->opento = 'current_entity';
        }

        // Update the session in database.
        if (!$this->dbinterface->update_session($this)) {
            throw new \moodle_exception('sessionupdatefailed', 'local_mentor_core');
        }

        // Update session status.
        if (isset($data->status) && $oldstatus != $data->status) {
            $this->courseshortname = $oldcourseshortname;
            $this->update_status($data->status, $oldstatus);
            $this->courseshortname = $data->shortname;
        }

        // Update the session course with form data.
        if (isset($data->fullname)) {

            // Excpetion when update course if set end date without start date.
            if (is_null($this->sessionenddate)) {
                $startdate = is_null($this->sessionstartdate) ? 0 : $this->sessionstartdate;
                $enddate = 0;
            } else {
                $startdate = is_null($this->sessionstartdate) ? $this->get_course()->timecreated : $this->sessionstartdate;
                $enddate = $this->sessionenddate;
            }

            // Update course.
            $course = [
                'id' => $this->courseid,
                'fullname' => $data->fullname ?: $this->get_training()->name,
                'shortname' => $data->shortname,
                'startdate' => $startdate,
                'enddate' => $enddate,
            ];

            $result = self::update_session_course($course);

            if (!empty($result['warnings'])) {
                if (isset($result['warnings']['message'])) {
                    throw new Exception('Error :' . $result['warnings']['message']);
                }

                if ($result['warnings'][0] && $result['warnings'][0]['message']) {
                    throw new Exception('Error :' . $result['warnings'][0]['message']);
                }
            }
        }

        // Update session enrolment instances.
        if ($enrolselfinstance = $this->get_enrolment_instances_by_type('self')) {

            // Change max participants self enrol if max participants session change.
            if (isset($data->maxparticipants) && $oldmaxparticipants != $data->maxparticipants) {
                $enrolselfinstance->customint3 = $data->maxparticipants;
            }

            $this->dbinterface->update_enrolment($enrolselfinstance);
        }

        // Create files only from the training form.
        if (!empty($mform)) {
            $this->create_files_by_session_form(['thumbnail'], $mform, $data);
        }
    }



     /**
     * Create files only from the training form.
     *
     * @param array $pictures
     * @param training_form $mform
     * @param $data
     * @return bool|void
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_files_by_session_form($pictures, $mform, $data) {

        $fs = get_file_storage();

        $maxfilewidth = 400;

        foreach ($pictures as $picture) {
            if(!method_exists($mform, 'get_new_filename')){
                continue;
            }
            $data->{$picture} = $mform->get_new_filename($picture);

            // No file provided, continue.;
            if (empty($data->{$picture})) {
                continue;
            }

            // Delete the old file.
            $fs->delete_area_files($this->contextid, 'local_session', $picture, $this->id);

            $deletefield = 'delete' . $picture;

            // Check if the delete picture is checked or not.
            if (!isset($data->{$deletefield}) || $data->{$deletefield} != 1) {
                // Store the new file.
                $file = $mform->save_stored_file($picture, $this->contextid, 'local_session',
                    $picture, $this->id);

                // Resize the file.
                local_mentor_core_resize_picture($file, $maxfilewidth);
            }
        }
    }

    /**
     * Update course for training
     *
     * @param array $course
     * @return array
     */
    public static function update_session_course($course) {
        global $CFG;

        require_once($CFG->dirroot . '/course/externallib.php');

        $courses = [];

        // Update course.
        if (!empty($course)) {
            $courses = \core_course_external::update_courses([$course]);
        }

        return $courses;
    }

    /**
     * Get the parent session of the training
     *
     * @return entity
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_entity() {
        $course = $this->get_course();
        $entityid = $this->dbinterface->get_course_main_category_id($course->id);
        return entity_api::get_entity($entityid, false);
    }

    /**
     * Get a training object for the edit form
     *
     * @return session
     */
    public function prepare_edit_form() {
        return clone($this);
    }

    /**
     * Get parent training
     *
     * @return training
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_training() {
        if (empty($this->training)) {
            $this->training = training_api::get_training($this->trainingid);
        }
        return $this->training;
    }

    /**
     * Create a self enrolment instance for this session
     * if not exist
     * Enable instance if exist
     *
     * @return bool true if is create, false if already exists
     * @throws coding_exception
     * @throws dml_exception
     */
    public function create_self_enrolment_instance() {
        $course = $this->get_course();
        $type = 'self';

        if (!$this->get_enrolment_instances_by_type($type)) {
            // Create new self enrol instance.
            $plugin = enrol_get_plugin($type);

            $instance = (object) $plugin->get_instance_defaults();
            $instance->status = 0;
            $instance->id = '';
            $instance->courseid = $course->id;
            $instance->customint1 = 0;
            $instance->customint2 = 0;
            $instance->customint3 = 0; // Max participants.
            $instance->customint4 = 1;
            $instance->customint5 = 0;
            $instance->customint6 = 1; // Enable.
            $instance->name = '';
            $instance->password = '';
            $instance->customtext1 = '';
            $instance->returnurl = '';
            $instance->expirythreshold = 0;
            $instance->enrolstartdate = $this->sessionstartdate ?? 0;
            $instance->enrolenddate = $this->sessionenddate ?? 0;

            $fields = (array) $instance;

            return $plugin->add_instance($course, $fields);
        }

        // Enable enrol instance if disable.
        return $this->enable_self_enrolment_instance();
    }

    /**
     * Create a manual enrolment instance for this session
     * if not exist
     * Enable instance if exist
     *
     * @return int instanceid if is create, false if already exists
     * @throws coding_exception
     * @throws dml_exception
     */
    public function create_manual_enrolment_instance() {

        $course = $this->get_course();
        $type = 'manual';

        if (!$this->get_enrolment_instances_by_type($type)) {
            // Create new self enrol instance.
            $plugin = enrol_get_plugin($type);

            $instance = (object) $plugin->get_instance_defaults();
            $instance->status = 0;
            $instance->id = '';
            $instance->courseid = $course->id;
            $instance->expirythreshold = 0;
            $instance->enrolstartdate = 0;
            $instance->enrolenddate = 0;
            $instance->timecreated = time();
            $instance->timemodified = time();

            $fields = (array) $instance;

            return $plugin->add_instance($course, $fields);
        }

        // Enable enrol instance if disable.
        return $this->enable_manual_enrolment_instance();
    }

    /**
     * Get all particpants with no editing rights
     *
     * @param bool $refresh
     * @return stdClass[]
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_participants($refresh = false) {

        if ($refresh || empty($this->participants)) {
            $course = $this->get_course();

            // Get all session users.
            $users = enrol_get_course_users($course->id);

            $this->courseusers = array_keys($users);

            $this->participants = [];
            foreach ($users as $user) {
                // Ignore the user if he is not participant.
                if ($this->is_participant($user)) {
                    $this->participants[] = $user;
                }
            }
        }

        return $this->participants;
    }

    /**
     * Check if user is participant
     *
     * @param int|stdClass $user
     * @return boolean
     * @throws dml_exception
     * @throws coding_exception
     */
    public function is_participant($user) {
        if (is_int($user)) {
            $userid = $user;
            $user = new \stdClass();
            $user->id = $userid;
        }

        $participantrole = $this->dbinterface->get_role_by_name('participant');
        $participantnonediteurrole = $this->dbinterface->get_role_by_name('participantnonediteur');

        // Ignore the user if he can update the course settings.
        if (!user_has_role_assignment($user->id, $participantrole->id, $this->get_context()->id) &&
            !user_has_role_assignment($user->id, $participantnonediteurrole->id, $this->get_context()->id)) {
            return false;
        }

        return true;
    }

    /**
     * Get course users
     *
     * @param bool $refresh
     * @return array
     * @throws coding_exception
     */
    public function get_course_users($refresh = false) {

        if ($refresh || !is_array($this->courseusers)) {

            // Get all session users.
            $users = enrol_get_course_users($this->courseid);

            $this->courseusers = array_column($users, 'id');
        }

        return $this->courseusers;
    }

    /**
     * Get the number of users with no editing rights
     *
     * @param bool $refresh
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_participants_number($refresh = false) {

        if (!$refresh && !is_null($this->numberparticipants)) {
            return $this->numberparticipants;
        }

        $this->numberparticipants = count($this->get_participants($refresh));
        return $this->numberparticipants;
    }

    /**
     * Get all enrol instances
     *
     * @return array
     * @throws dml_exception
     */
    public function get_enrolment_instances() {
        $course = $this->dbinterface->get_course_by_shortname($this->courseshortname);

        return enrol_get_instances($course->id, false);
    }

    /**
     * Get enrol instance by type name
     *
     * @param string $type
     * @return false|mixed
     * @throws dml_exception
     */
    public function get_enrolment_instances_by_type($type) {
        $enrolmentinstances = $this->get_enrolment_instances();

        foreach ($enrolmentinstances as $enrolmentinstance) {
            if ($enrolmentinstance->enrol == $type) {
                return $enrolmentinstance;
            }
        }

        return false;
    }

    /**
     * Update enrol instance data
     *
     * @param $data
     * @throws dml_exception
     */
    public function update_enrolment_instance($data) {
        $this->dbinterface->update_enrolment($data);
    }

    /**
     * Disable all enrol instance
     *
     * @throws dml_exception
     */
    public function disable_enrolment_instance() {
        $enrolmentinstances = $this->get_enrolment_instances();

        foreach ($enrolmentinstances as $enrolmentinstance) {
            switch ($enrolmentinstance->enrol) {
                case 'manual':
                    $this->disable_manual_enrolment_instance();
                    break;
                case 'self':
                    $this->disable_self_enrolment_instance();
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Disable self enrol instance if exist and is enable
     *
     * @return bool
     * @throws dml_exception
     */
    protected function disable_self_enrolment_instance() {
        // Check if enrol instance exist.
        if (!$selfenrolmentinstance = $this->get_enrolment_instances_by_type('self')) {
            return false;
        }

        // Check if enrol is enable.
        if ($selfenrolmentinstance->status) {
            return false;
        }

        // Disable self enrol instance.
        $selfenrolmentinstance->status = 1; // Disable.
        $this->update_enrolment_instance($selfenrolmentinstance);

        return true;
    }

    /**
     * Close self enrol instance opening.
     *
     * @return bool
     * @throws dml_exception
     */
    protected function close_self_enrolment_instance() {
        // Check if enrol instance exist.
        if (!$selfenrolmentinstance = $this->get_enrolment_instances_by_type('self')) {
            return false;
        }

        // Check if enrol is enable.
        if ($selfenrolmentinstance->status) {
            return false;
        }

        // Disable self enrol instance.
        $selfenrolmentinstance->enrolenddate = time(); // Disable.
        $this->update_enrolment_instance($selfenrolmentinstance);

        return true;
    }

    /**
     * Disable manual enrol instance if exist and is enable
     *
     * @return bool
     * @throws dml_exception
     */
    protected function disable_manual_enrolment_instance() {
        // Check if enrol instance exist.
        if (!$selfenrolmentinstance = $this->get_enrolment_instances_by_type('manual')) {
            return false;
        }

        // Check if enrol is disable.
        if ($selfenrolmentinstance->status) {
            return true;
        }

        // Disable manual enrol instance.
        $selfenrolmentinstance->status = 1; // Disable.
        $this->update_enrolment_instance($selfenrolmentinstance);

        return true;
    }

    /**
     * Enable self enrol instance if exist and is disable
     *
     * @return bool
     * @throws dml_exception
     */
    public function enable_self_enrolment_instance() {
        // Check if enrol instance exist.
        if (!$selfenrolmentinstance = $this->get_enrolment_instances_by_type('self')) {
            return false;
        }

        // Check if enrol is disable.
        if (!$selfenrolmentinstance->status && !$selfenrolmentinstance->enrolenddate) {
            return true;
        }

        // Enable self enrol instance.
        $selfenrolmentinstance->status = 0; // Enable.
        $selfenrolmentinstance->enrolenddate = 0; // Open.
        $this->update_enrolment_instance($selfenrolmentinstance);

        return true;
    }

    /**
     * Enable self enrol instance if exist and is disable
     *
     * @return bool
     * @throws dml_exception
     */
    protected function enable_manual_enrolment_instance() {
        // Check if enrol instance exist.
        if (!$selfenrolmentinstance = $this->get_enrolment_instances_by_type('manual')) {
            return false;
        }

        // Check if seld enrolment is enable.
        if (!$selfenrolmentinstance->status) {
            return true;
        }

        // Enable self enrolment instance.
        $selfenrolmentinstance->status = 0; // Enable.
        $this->update_enrolment_instance($selfenrolmentinstance);

        return true;
    }

    /**
     * Hide the course liked to the session
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function hide_course() {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $course = $this->get_course();
        $course->visible = 0;
        \update_course($course);

        $this->course = $course;
    }

    /**
     * Show the course liked to the session
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function show_course() {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $course = $this->get_course();
        $course->visible = 1;
        \update_course($course);

        $this->course = $course;
    }

    /**
     * Check if a user is enrolled into the session
     *
     * @param int $userid - optional
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     */
    public function user_is_enrolled($userid = null) {
        global $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        return \is_enrolled($this->get_context(), $userid);
    }

    /**
     * Enrol the current user by self enrolment
     *
     * @param null|string $enrolmentkey optional default null
     * @return array enrolment success
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function enrol_current_user($enrolmentkey = null) {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/enrol/self/externallib.php');

        // Check if user have not access to session.
        if (!$this->is_available_to_user()) {
            return [
                'status' => false,
                'warnings' => ['message' => get_string('selfenrolmentnotallowed', 'local_mentor_core')],
                'lang' => 'errorauthorizationselfenrolment',
            ];
        }

        $instance = $this->get_enrolment_instances_by_type('self');

        if ($this->status === $this::STATUS_OPENED_REGISTRATION || $this->status === $this::STATUS_IN_PROGRESS) {
            // Checks if the enrollment should exist in relation to the status of the session.
            if (!$instance) {
                $this->create_self_enrolment_instance();
                $instance = $this->get_enrolment_instances_by_type('self');
            } else {
                // If instance is disable.
                $this->enable_self_enrolment_instance();
            }
        }

        // Check if a self enrolment instance exists.
        if (!$instance) {
            return [
                'status' => false,
                'warnings' => ['message' => get_string('selfenrolmentdisabled', 'local_mentor_core')],
                'lang' => 'errorselfenrolment',
            ];
        }

        // Try to enrol the user with default enrolment settings.
        try {
            $result = \enrol_self_external::enrol_user($this->courseid, $enrolmentkey, $instance->id);
        } catch (Exception $e) {
            return [
                'status' => false,
                'warnings' => ['message' => $e->getMessage()],
            ];
        }

        $this->participants = null;
        $this->numberparticipants = null;

        return $result;
    }

    /**
     * Check if the session has a self registration key
     *
     * @return bool
     * @throws dml_exception
     */
    public function has_registration_key() {
        $instance = $this->get_enrolment_instances_by_type('self');
        return !empty($instance->password);
    }

    /**
     * Get a lighter version of the current object for an usage on mustache
     *
     * @return \stdClass
     * @throws Exception
     */
    public function convert_for_template() {
        global $USER;

        $templateobj = new \stdClass();
        $templateobj->id = $this->id;
        $templateobj->fullname = $this->fullname;
        $templateobj->status = $this->status;

        // Available places.
        $places = $this->get_available_places();
        $templateobj->placesavailable = is_int($places) && $places < 0 ? 0 : $places;

        $templateobj->istrainer = $this->is_trainer($USER->id);
        $templateobj->istutor = $this->is_tutor($USER->id);
        $templateobj->isparticipant = $this->is_participant($USER);
        $templateobj->trainingid = $this->trainingid;
        $training = $this->get_training();
        if ($thumbnail = $this->dbinterface->get_file_from_database($training->contextid,
            'local_trainings',
            'thumbnail',
            $training->id)) {
            $templateobj->thumbnail = \moodle_url::make_pluginfile_url(
                $thumbnail->contextid,
                $thumbnail->component,
                $thumbnail->filearea,
                $thumbnail->itemid,
                $thumbnail->filepath,
                $thumbnail->filename
            )->out();
        } else {
            $templateobj->thumbnail = null;
        }

        // Set Date Time Zone at France.
        $dtz = new \DateTimeZone('Europe/Paris');

        // Set session start and end date.
        if (!empty($this->sessionstartdate)) {
            $sessionstartdate = $this->sessionstartdate;
            $startdate = new \DateTime("@$sessionstartdate");
            $startdate->setTimezone($dtz);
            $templateobj->sessionstartdate = $startdate->format('d/m/Y');
            $templateobj->sessionstartdatetimestamp = $sessionstartdate;
        }

        if (!empty($this->sessionenddate)) {
            $sessionenddate = $this->sessionenddate;
            $enddate = new \DateTime("@$sessionenddate");
            $enddate->setTimezone($dtz);
            $templateobj->sessionenddate = $enddate->format('d/m/Y');
            $templateobj->sessionenddatetimestamp = $sessionenddate;
        }

        // Check if there is more than one free seat.
        $templateobj->placesnotlimited = true;

        if (is_numeric($this->maxparticipants)) {
            $templateobj->placesnotlimited = false;
            $templateobj->placesavailablemoreone = (int) $places > 1;
        }

        // Checks if the session lasts only one day.
        $templateobj->sessiononedaydate = false;
        if ($this->sessionstartdate === $this->sessionenddate) {
            $templateobj->sessiononedaydate = true;
        }

        // Set course url.
        $templateobj->courseurl = htmlspecialchars_decode($this->get_url()->out(), ENT_COMPAT);

        // Check if user is enrolled.
        $templateobj->isenrol = $this->user_is_enrolled();

        // Get session to user's favourite data.
        $templateobj->favouritesession = $this->get_user_favourite_data();

        // Check if all enrolments user are enabled.
        $templateobj->hasenrollenabled = $this->has_enroll_user_enabled();

        // Check status session.
        switch ($this->status) {
            case self::STATUS_IN_PREPARATION:
                $templateobj->isinpreparation = true;
                break;
            case self::STATUS_OPENED_REGISTRATION:
                $templateobj->isopenedregistration = true;
                break;
            case self::STATUS_IN_PROGRESS:
                $templateobj->isinprogress = true;
                break;
            case self::STATUS_COMPLETED:
                $templateobj->completed = true;
                break;
            case self::STATUS_ARCHIVED:
                $templateobj->isarchived = true;
                break;
            case self::STATUS_REPORTED:
                $templateobj->isreported = true;
                break;
            case self::STATUS_CANCELLED:
                $templateobj->iscanceled = true;
                break;
        }

        return $templateobj;

    }

    /**
     * Get 'open to' status.
     *
     * @return string
     */
    public function get_open_to_status() {
        return $this->opento;
    }

    /**
     * Check if the session is open to all entities
     *
     * @return bool
     */
    public function is_open_to_all() {
        return $this->opento === self::OPEN_TO_ALL;
    }

    /**
     * Check if the session is shared to other entities
     *
     * @return bool
     * @throws dml_exception
     */
    public function is_shared() {
        // Shared with all entities.
        if ($this->opento == 'all') {
            return true;
        }

        // Not shared at all.
        if (
            ($this->opento == 'current_entity') ||
            ($this->opento == 'not_visible')
        ) {
            return false;
        }

        // Check if the session is shared to any entity.
        return count($this->dbinterface->get_opento_list($this->id)) > 0;
    }

    /**
     * Returns the list of entities where the session is shared
     *
     * @return array
     * @throws dml_exception
     */
    public function get_entities_sharing() {
        return $this->dbinterface->get_session_sharing_by_session_id($this->id);
    }

    /**
     * Check if session is shared with a specific entity
     *
     * @param int $entityid
     * @return bool
     * @throws dml_exception
     */
    public function is_shared_with_entity($entityid) {
        if ($this->opento === 'current_entity' && $entityid !== $this->get_entity()->id) {
            return false;
        }

        $entitiessharing = $this->get_entities_sharing();

        foreach ($entitiessharing as $entitysharing) {
            if ((int) $entitysharing->coursecategoryid === (int) $entityid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the current user can register for the session
     *
     * @param int|null $userid
     * @return bool
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function is_available_to_user($userid = null) {

        if (null === $userid) {
            global $USER;
            $userid = $USER->id;
        }

        // Check if the session is visible.
        if ($this->opento == self::OPEN_TO_NOT_VISIBLE) {
            return false;
        }

        // Check if the user is admin.
        if (is_siteadmin($userid)) {
            return true;
        }

        // Check if the session is open to all users.
        if ($this->is_open_to_all()) {
            return true;
        }

        // Check if the user exists.
        if (is_null($userid) || $userid == 0) {
            return false;
        }

        // Check if user is member to session's main entity.
        if ($this->get_entity()->get_main_entity()->is_member($userid)) {
            return true;
        }

        // Check if the session is shared to a user entity.
        if ($this->opento === self::OPEN_TO_OTHER_ENTITY) {
            $sharing = $this->get_entities_sharing();

            foreach ($sharing as $sharingentity) {
                $entity = entity_api::get_entity($sharingentity->coursecategoryid);
                if ($entity->is_member($userid)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get available places.
     *
     * @return int|string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_available_places() {
        if ($this->maxparticipants && filter_var($this->maxparticipants, FILTER_VALIDATE_INT) !== false) {
            return $this->maxparticipants - $this->get_participants_number();
        }

        return '';
    }

    /**
     * Get the next available participant places in the session
     *
     * @param array $newusers new users to enrol
     * @return bool|int|string|void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function count_potential_available_places($newusers) {

        // Return false if the session has no max participants number.
        if (!$this->maxparticipants || !is_numeric($this->maxparticipants)) {
            return false;
        }

        // Get actual partipants.
        $participants = $this->get_participants();

        $countparticipant = count($participants);

        $emails = [];
        foreach ($participants as $participant) {
            $emails[$participant->email] = $participant;
        }

        // Loop on each new user from csv file.
        foreach ($newusers as $newuser) {

            if (is_object($newuser)) {
                $newuser = (array) $newuser;
            }

            if (strtolower($newuser['role']) != 'participant') {
                // The user was a participant and is now tuteur, concepteur or formateur.
                if (array_key_exists($newuser['email'], $emails)) {
                    $countparticipant--;
                }
                continue;
            }

            // Check if the user is a new participant.
            if (!array_key_exists($newuser['email'], $emails)) {
                $countparticipant++;
            }
        }

        return $this->maxparticipants - $countparticipant;
    }

    /**
     * Get the list of specific shared entities
     *
     * @return string
     * @throws dml_exception
     */
    protected function get_opento_list() {
        $dbinterface = database_interface::get_instance();

        $listentities = $dbinterface->get_opento_list($this->id);

        return implode(',', array_keys($listentities));
    }

    /**
     * Update session shared entities
     *
     * @param int[] $entities list of entities id
     * @return bool
     * @throws Exception
     */
    public function update_session_sharing($entities) {
        return $this->dbinterface->update_session_sharing($this->id, $entities);
    }

    /**
     * Remove sharing session data
     *
     * @return bool
     * @throws dml_exception
     */
    public function remove_session_sharing() {
        return $this->dbinterface->remove_session_sharing($this->id);
    }

    /**
     * Get all users who can edit the session
     *
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_editors() {
        return get_users_by_capability($this->get_context(), 'local/session:update');
    }

    /**
     * Get all course users (participants, managers...)
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_all_users() {
        $admins = $this->get_editors();
        $participants = $this->get_participants();
        $tutors = $this->get_tutors();
        $demonstrateurs = $this->get_demonstrateurs();

        return array_merge($admins, $participants, $tutors, $demonstrateurs);
    }

    /**
     * Mark the session as in preparation
     *
     * @throws dml_exception
     * @throws \moodle_exception
     */
    protected function inpreparation() {
        $dbinterface = database_interface::get_instance();

        // Update status.
        $dbinterface->update_session_status($this->id, self::STATUS_IN_PREPARATION);

        // Hide the course.
        $this->hide_course();

        // Disable self enrol instance if exist.
        $this->disable_self_enrolment_instance();
    }

    /**
     * Mark the session as opened to registration
     *
     * @param string $oldstatus
     * @throws coding_exception
     * @throws dml_exception
     * @throws \moodle_exception
     */
    protected function open_to_registration($oldstatus) {
        $dbinterface = database_interface::get_instance();

        // Update status.
        $dbinterface->update_session_status($this->id, self::STATUS_OPENED_REGISTRATION);

        // Show the course.
        $this->show_course();

        // Create self enrol instance if not exist.
        $this->create_self_enrolment_instance();

        // Change enrol self start date if exists.
        if ($enrolselfinstance = $this->get_enrolment_instances_by_type('self')) {
            $enrolselfinstance->enrolstartdate = time();
            $this->dbinterface->update_enrolment($enrolselfinstance);
        }

        // Send a message if the session was reported.
        if ($oldstatus == self::STATUS_REPORTED) {
            // Data for message.
            $infodata = new stdClass();
            $infodata->fullname = $this->fullname ?: $this->trainingname;
            $infodata->startdate = date('d/m/Y', $this->sessionstartdate);

            // Message text.
            $messagetext = get_string('new_date_email', 'local_mentor_core', $infodata);

            // Message subject.
            $subject = get_string('newsessiondate', 'local_mentor_core') . ' ' . $infodata->fullname;

            // Message HTML.
            $messagehtml = text_to_html($messagetext, false, false, true);

            // Send a report email to participants.
            $this->send_message_to_all($subject, $messagetext, $messagehtml);
        }
    }

    /**
     * Mark the session as opened (inprogress)
     *
     * @param string $oldstatus
     * @throws coding_exception
     * @throws dml_exception
     * @throws \moodle_exception
     */
    protected function open($oldstatus) {
        $dbinterface = database_interface::get_instance();

        // Update status.
        $dbinterface->update_session_status($this->id, self::STATUS_IN_PROGRESS);

        // Show the course.
        $this->show_course();

        // Create self enrol instance if not exist.
        $this->create_self_enrolment_instance();
    }

    /**
     * Mark the session as completed
     *
     * @param string $oldstatus
     * @throws dml_exception
     */
    protected function complete($oldstatus) {
        $dbinterface = database_interface::get_instance();

        // Update status.
        $dbinterface->update_session_status($this->id, self::STATUS_COMPLETED);

        // Disable all enrol instance.
        $this->close_self_enrolment_instance();

        // Send a message if the session was complete.
        if ($oldstatus == self::STATUS_IN_PROGRESS) {
            // Data for message.
            $fullname = $this->fullname ?: $this->trainingname;

            // Message text.
            $messagetext = get_string('email_complete_session_content', 'local_mentor_core', $fullname);

            // Message subject.
            $subject = get_string('email_complete_session_object', 'local_mentor_core', $fullname);

            // Message HTML.
            $messagehtml = text_to_html($messagetext, false, false, true);

            // Send a report email to participants.
            $this->send_message_to_users(
                array_merge($this->get_participants(), $this->get_tutors(), $this->get_formateurs()),
                $subject, $messagetext, $messagehtml
            );
        }
    }

    /**
     * Mark the session as archived
     *
     * @param string $oldstatus
     * @throws dml_exception
     */
    protected function archive($oldstatus) {
        $dbinterface = database_interface::get_instance();

        // Update status.
        $dbinterface->update_session_status($this->id, self::STATUS_ARCHIVED);

        // Disable self enrol instance.
        $this->close_self_enrolment_instance();

        // Send a message if the session was archive.
        if ($oldstatus == self::STATUS_COMPLETED) {
            // Data for message.
            $fullname = $this->fullname ?: $this->trainingname;

            // Message text.
            $messagetext = get_string('email_archive_session_content', 'local_mentor_core', $fullname);

            // Message subject.
            $subject = get_string('email_archive_session_object', 'local_mentor_core', $fullname);

            // Message HTML.
            $messagehtml = text_to_html($messagetext, false, false, true);

            // Send a report email to participants.
            $this->send_message_to_users(
                array_merge($this->get_participants(), $this->get_tutors(), $this->get_formateurs()),
                $subject, $messagetext, $messagehtml
            );
        }
    }

    /**
     * Mark the session as reported
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws \moodle_exception
     */
    protected function report() {
        $dbinterface = database_interface::get_instance();

        // Update status.
        $dbinterface->update_session_status($this->id, self::STATUS_REPORTED);

        // Hide the course.
        $this->hide_course();

        // Data for message.
        $infodata = new stdClass();
        $infodata->fullname = $this->fullname ?: $this->trainingname;
        $infodata->startdate = date('d/m/Y', $this->sessionstartdate);

        // Message text.
        $messagetext = get_string('reported_session_email', 'local_mentor_core', $infodata);

        // Message HTML.
        $messagehtml = text_to_html($messagetext, false, false, true);

        // Message subject.
        $subject = get_string('reported_session', 'local_mentor_core') . ' ' . $infodata->fullname;

        // Send a report email to participants.
        $this->send_message_to_users(
            array_merge($this->get_participants(), $this->get_tutors(), $this->get_formateurs()),
            $subject, $messagetext, $messagehtml
        );

        // Disable self enrol instance.
        $this->disable_self_enrolment_instance();
    }

    /**
     * Mark the session as cancelled
     *
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws \moodle_exception
     */
    protected function cancel() {
        $dbinterface = database_interface::get_instance();

        // Update status.
        $dbinterface->update_session_status($this->id, self::STATUS_CANCELLED);

        // Hide the course.
        $this->hide_course();

        // Data for message.
        $infodata = new stdClass();
        $infodata->fullname = $this->fullname ?: $this->trainingname;
        $infodata->startdate = date('d/m/Y', $this->sessionstartdate);

        // Message text.
        $messagetext = get_string('cancelled_session_email', 'local_mentor_core', $infodata);

        // Message HTML.
        $messagehtml = text_to_html($messagetext, false, false, true);

        // Subject.
        $subject = get_string('cancelled_session', 'local_mentor_core') . ' ' . $infodata->fullname;

        // Send a cancel email to participants.
        $this->send_message_to_users(
            array_merge($this->get_participants(), $this->get_tutors(), $this->get_formateurs()),
            $subject, $messagetext, $messagehtml
        );

        // Disable all enrol instance.
        $this->disable_enrolment_instance();

        return true;
    }

    /**
     * Update the session status
     *
     * @param string $newstatus
     * @param string $oldstatus optional default ''
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function update_status($newstatus, $oldstatus = '') {

        switch ($newstatus) {
            case self::STATUS_IN_PREPARATION :
                $this->inpreparation();
                break;
            case self::STATUS_OPENED_REGISTRATION :
                $this->open_to_registration($oldstatus);
                break;
            case self::STATUS_IN_PROGRESS :
                $this->open($oldstatus);
                break;
            case self::STATUS_COMPLETED :
                $this->complete($oldstatus);
                break;
            case self::STATUS_ARCHIVED :
                $this->archive($oldstatus);
                break;
            case self::STATUS_REPORTED :
                $this->report();
                break;
            case self::STATUS_CANCELLED :
                $this->cancel();
                break;
            default:
                break;
        }

        $this->status = $newstatus;

        // Update session in database.
        $this->dbinterface->update_session($this);

        return true;
    }

    /**
     * Send an email to all session users
     *
     * @param string $subject
     * @param string $messagetext
     * @param string $messagehtml
     * @throws Exception
     */
    public function send_message_to_all($subject, $messagetext, $messagehtml) {
        $allusers = $this->get_all_users();
        $this->send_message_to_users($allusers, $subject, $messagetext, $messagehtml);
    }

    /**
     * Send a message to a list of users
     *
     * @param stdClass[] $users
     * @param string $subject
     * @param string $messagetext
     * @param string $messagehtml
     * @throws Exception
     */
    protected function send_message_to_users($users, $subject, $messagetext, $messagehtml) {
        // Send message as support user.
        $supportuser = \core_user::get_support_user();

        try {
            foreach ($users as $user) {
                email_to_user($user, $supportuser, $subject, $messagetext, $messagehtml);
            }
        } catch (Exception $e) {
            throw new Exception('Error sending mail : ' . $e->getMessage());
        }
    }

    /**
     * Get course progression
     *
     * @param int $userid
     * @return false|int
     * @throws dml_exception
     */
    public function get_progression($userid) {
        return local_mentor_core_completion_get_progress_percentage($this->get_course(), $userid);
    }

    /**
     * Delete the session
     *
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function delete() {

        // Check capability.
        require_capability('local/session:delete', $this->get_context());

        // Delete the session.
        $this->dbinterface->delete_session($this);

        return true;
    }

    /**
     * Generate a backup of the current training course
     *
     * @return bool|\stored_file
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function generate_backup() {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');

        $currentcontext = $this->get_context();

        // Get the local file storage instance.
        $fs = get_file_storage();

        // Delete the existing backup files.
        $fs->delete_area_files($currentcontext->id, 'backup', 'course', 0);

        $oldbackuptempdir = $CFG->backuptempdir;

        // Define a specific backup dir.
        $CFG->backuptempdir = isset($CFG->mentorbackuproot) ? $CFG->mentorbackuproot : $CFG->dataroot . '/mentor_backup';

        // Create the backuptempdir if not exists.
        if (!is_dir($CFG->backuptempdir)) {
            mkdir($CFG->backuptempdir, 0775);
        }

        // Add the courseid to the backup dir.
        $CFG->backuptempdir .= '/' . $this->courseid;

        // Create the course directory into the backuptempdir.
        if (!is_dir($CFG->backuptempdir)) {
            mkdir($CFG->backuptempdir, 0775);
        }

        // Add the current timestamp to the backup dir.
        $CFG->backuptempdir .= '/' . time();

        // Create the final directory to generate the backup. ex : $CFG->dataroot /mentor_backup/<courseid>/<timestamp>.
        if (!is_dir($CFG->backuptempdir)) {
            mkdir($CFG->backuptempdir, 0775);
        }

        // Create a new backup file.
        $bc = new \backup_controller_edu(
            \backup::TYPE_1COURSE, $this->courseid, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            [
                'settings' => [
                    ['name' => 'activities', 'value' => true],
                    ['name' => 'blocks', 'value' => true],
                    ['name' => 'files', 'value' => true],
                    ['name' => 'filters', 'value' => true],
                    ['name' => 'badges', 'value' => true],
                    ['name' => 'questionbank', 'value' => true],
                    ['name' => 'groups', 'value' => true],
                    ['name' => 'customfield', 'value' => true],
                    ['name' => 'contentbankcontent', 'value' => true],
                ],
            ]
        );

        // Disable user info to all quiz activities.
        $bc->disable_userinfo_to_activity('quiz');
        // Disable user info to all questionnaire activities.
        $bc->disable_userinfo_to_activity('questionnaire');
        // Disable user info to all etherpadlite activities.
        $bc->disable_userinfo_to_activity('etherpadlite');
        // Disable user info to all customcert activities.
        $bc->disable_userinfo_to_activity('customcert');

        $bc->execute_plan();

        $CFG->backuptempdir = $oldbackuptempdir;

        // Check if the backup file has been created and return it.
        if ($dbfile = $this->dbinterface->get_course_backup($currentcontext->id, 'backup', 'course')) {
            $file = $fs->get_file_by_id($dbfile->id);

            $filename = 'backup_' . $this->courseid . '_' . time() . '.mbz';

            // Rename the backup file to be unique and return it.
            $file->rename($file->get_filepath(), $filename);
            return $file;
        }

        return false;
    }

    /**
     * Duplicate the session
     *
     * @param string $trainingshortname shortname of the created course
     * @param int $destinationentity optional default null move the created training into a new entity
     * @return training the created training
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function duplicate_as_new_training($trainingfullname, $trainingshortname, $destinationentity) {
        global $DB;

        $sessiontraining = $this->get_training();

        $course = get_course($this->courseid);

        // Generate a backup file to duplicate course.
        if (!$backupfile = $this->generate_backup()) {
            throw new \Exception('Backup file not created');
        }

        // Check if the destination category exists.
        if (!$DB->get_record('course_categories', ['id' => $course->category])) {
            // Remove backup file.
            $backupfile->delete();
            throw new \Exception('Inexistant category : ' . $course->category);
        }

        unset($this->courseshortname);

        $training = clone($sessiontraining);

        $training->categorychildid = $course->category;
        $training->categoryid = \core_course_category::get($course->category)->parent;
        // Name and shortname are updated after the restore.
        // This ame and shortname are used so that there is.
        // no error saying that they are used for another course.
        $training->name = $trainingfullname;
        $training->shortname = $trainingshortname;
        // Make "draft" default status value.
        $training->status = training::STATUS_DRAFT;

        $coursexists = $this->dbinterface->course_exists($training->shortname);
        $linkcoursexists = $this->dbinterface->training_exists($training->shortname);

        $counter = 0;
        $max = 50;

        while ($coursexists || $linkcoursexists) {
            $training->shortname .= ' copie';
            $coursexists = $this->dbinterface->course_exists($training->shortname);
            $linkcoursexists = $this->dbinterface->training_exists($training->shortname);
            if ($counter++ > $max) {
                // Remove backup file.
                $backupfile->delete();
                throw new \Exception('Limit of the number of loops reached!');
            }
        }

        // Create the training.
        $newtraining = training_api::create_training($training);

        // Get the new training.
        $newtraining = training_api::get_training($newtraining->id);

        // Restore the course backup into the new training course.
        if (!$newtraining->restore_backup($backupfile)) {
            // Remove backup file.
            $backupfile->delete();
            throw new \Exception('Restoration failed');
        }

        // Reset user data.
        $newtraining->reset();

        // Remove backup file.
        $backupfile->delete();

        $trainingcourse = $sessiontraining->get_course();

        // Clone 'summary' course attribute.
        $newcourse = $newtraining->get_course();
        $newcourse->summary = $trainingcourse->summary;
        $newcourse->format = $course->format;
        $newcourse->showgrades = $course->showgrades;
        $newcourse->newsitems = $course->newsitems;
        $newcourse->enablecompletion = $course->enablecompletion;
        $newcourse->completionnotify = $course->completionnotify;

        // Copy course format options.
        $formatoptions = $this->dbinterface->get_course_format_options_by_course_id($course->id);
        foreach ($formatoptions as $formatoption) {
            $formatoption->courseid = $newcourse->id;
            $this->dbinterface->add_course_format_option($formatoption);
        }

        // Move the course into the new entity.
        $entity = entity_api::get_entity($destinationentity);
        $newcourse->category = $entity->get_entity_formation_category();

        // Update the new course.
        update_course($newcourse);

        // Copy the pictures.
        $fs = get_file_storage();
        $newpicturedata = ['contextid' => $newtraining->contextid, 'itemid' => $newtraining->id];

        // Copy the thumbnail.
        if ($oldpicture = $sessiontraining->get_training_picture('thumbnail')) {
            $fs->create_file_from_storedfile($newpicturedata, $oldpicture);
        }

        // Create manual enrolment instance.
        $newtraining->create_manual_enrolment_instance();

        // Refresh data.
        return training_api::get_training($newtraining->id);
    }

    /**
     * Duplicate the session content into its training
     *
     * @return training
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function duplicate_into_training() {

        // Generate a backup file to duplicate course.
        if (!$backupfile = $this->generate_backup()) {
            throw new \Exception('Backup file not created');
        }

        $fs = get_file_storage();

        $training = $this->get_training();

        $oldcoursesummary = $training->get_course()->summary;

        // Get enrolled users.
        $oldenrolments = $training->get_all_enrolments();

        // Save old files in a temp dir.
        $oldfiles = $training->get_all_training_files();

        // Save the backup in a temp dir.
        $filerecord = [
            'contextid' => \context_system::instance()->id,
        ];

        $tempfiles = [];

        foreach ($oldfiles as $oldfile) {
            // Delete temp file if already exists.
            if ($oldexistingfile = $fs->get_file(
                $filerecord['contextid'],
                $oldfile->get_component(),
                $oldfile->get_filearea(),
                $oldfile->get_itemid(),
                $oldfile->get_filepath(),
                $oldfile->get_filename()
            )) {
                $oldexistingfile->delete();
            }

            $tempfiles[] = $fs->create_file_from_storedfile($filerecord, $oldfile);
            $oldfile->delete();
        }

        // Save the content of the training.
        $backup = $training->generate_backup();

        // Save the backup in a temp dir.
        $filerecord = [
            'contextid' => \context_system::instance()->id,
            'component' => 'backuptemp',
            'filearea' => $backup->get_filearea(),
        ];

        $tempbackup = $fs->create_file_from_storedfile($filerecord, $backup);

        try {
            // Restore the course backup into the new training course.
            if (!$training->restore_backup($backupfile)) {
                // Remove backup file.
                $backupfile->delete();
                throw new \Exception('Restoration failed');
            }
        } catch (\Exception $e) {
            $tempbackup->delete();
            $backupfile->delete();

            // Restore training files from temp dir.
            $filerecord = [
                'contextid' => $training->get_context()->id,
            ];

            // Restore temp files into the training.
            foreach ($tempfiles as $tempfile) {
                $fs->create_file_from_storedfile($filerecord, $tempfile);
                $tempfile->delete();
            }

            // Update summary field.
            $course = $training->get_course();
            $course->summary = $oldcoursesummary;
            update_course($course);
            $training->create_manual_enrolment_instance();

            // Re enrol users.
            foreach ($oldenrolments as $oldenrolment) {

                // Enrol user.
                enrol_try_internal_enrol($training->courseid, $oldenrolment->userid, $oldenrolment->roleid);

                // Set user role.
                role_assign($oldenrolment->roleid, $oldenrolment->userid, $training->get_context());
            }

            mtrace($e->getMessage());
            throw new \Exception('Restoration failed');
        }

        // Reset user data.
        $training->reset();
        $training->create_manual_enrolment_instance();

        // Re enrol users.
        foreach ($oldenrolments as $oldenrolment) {

            // Enrol user.
            enrol_try_internal_enrol($training->courseid, $oldenrolment->userid, $oldenrolment->roleid);

            // Set user role.
            role_assign($oldenrolment->roleid, $oldenrolment->userid, $training->get_context());
        }

        // Copy session course data into training course data.
        $sessioncourse = $this->get_course(true);
        $trainingcourse = $training->get_course(true);

        $sessioncourse->id = $trainingcourse->id;
        $sessioncourse->shortname = $trainingcourse->shortname;
        $sessioncourse->fullname = $trainingcourse->fullname;
        $sessioncourse->category = $trainingcourse->category;
        $sessioncourse->summary = $oldcoursesummary;

        update_course($sessioncourse);

        // Copy course format options.
        $sessionformatoptions = $this->get_course_format_options(true);
        $training->set_course_format_options($sessioncourse->format, $sessionformatoptions);

        // Restore the old backup as a course file.
        $filerecord = [
            'contextid' => $training->get_context()->id,
            'component' => 'backup',
            'filearea' => $backup->get_filearea(),
        ];

        $fs->create_file_from_storedfile($filerecord, $tempbackup);
        $tempbackup->delete();

        // Restore training files from temp dir.
        $filerecord = [
            'contextid' => $training->get_context()->id,
        ];

        // Restore temp files into the training.
        foreach ($tempfiles as $tempfile) {
            $fs->create_file_from_storedfile($filerecord, $tempfile);
            $tempfile->delete();
        }

        return $training;
    }

    /**
     * Get session course format options
     *
     * @param bool $refresh
     * @return stdClass[]
     * @throws dml_exception
     */
    public function get_course_format_options($refresh = false) {
        return $this->dbinterface->get_course_format_options_by_course_id($this->courseid, $refresh, $this->get_course($refresh)
            ->format);
    }

    /**
     * Get all session group.
     *
     * @return array
     */
    public function get_all_group() {
        return $this->dbinterface->get_all_session_group($this->id);
    }

    /**
     * Get session to user's favourite data.
     *
     * @param int|null $userid
     * @return \stdClass|false
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_user_favourite_data($userid = null) {
        return $this->dbinterface->get_user_favourite_session_data($this->id, $this->get_context()->id, $userid);
    }

    /**
     * Check if enrol user for this session is enabled.
     *
     * @param int|null $userid
     * @return bool
     * @throws \dml_exception
     */
    public function has_enroll_user_enabled($userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        return $this->dbinterface->has_enroll_user_enabled($this->get_course()->id, $userid);
    }

    /**
     * Get all tutors with no editing rights
     *
     * @param bool $refresh
     * @return stdClass[]
     * @throws dml_exception
     */
    public function get_tutors($refresh = false) {

        // Check if the participants list must be reloaded.
        if ($refresh || empty($this->tutors)) {

            $dbinterface = database_interface::get_instance();

            $this->tutors = $dbinterface->get_course_tutors($this->get_context()->id);
        }

        return $this->tutors;
    }

    /**
     * Get all formateurs with no editing rights
     *
     * @param bool $refresh
     * @return stdClass[]
     * @throws dml_exception
     */
    public function get_formateurs($refresh = false) {

        // Check if the participants list must be reloaded.
        if ($refresh || empty($this->formateurs)) {

            $dbinterface = database_interface::get_instance();

            $this->formateurs = $dbinterface->get_course_formateurs($this->get_context()->id);
        }

        return $this->formateurs;
    }

    /**
     * Get all demonstrateurs with no editing rights
     *
     * @param bool $refresh
     * @return stdClass[]
     * @throws dml_exception
     */
    public function get_demonstrateurs($refresh = false) {

        // Check if the participants list must be reloaded.
        if ($refresh || empty($this->demonstrateurs)) {

            $dbinterface = database_interface::get_instance();

            $this->demonstrateurs = $dbinterface->get_course_demonstrateurs($this->get_context()->id);
        }

        return $this->demonstrateurs;
    }

    /**
     * Reset all userdata
     */
    public function reset() {
        $resetdata = new \stdClass();
        $resetdata->reset_start_date = 0;
        $resetdata->reset_end_date = 0;
        $resetdata->reset_events = 1;
        $resetdata->reset_comments = 1;
        $resetdata->reset_completion = 1;
        $resetdata->delete_blog_associations = 1;
        $resetdata->reset_competency_ratings = 1;

        // List course roles.
        $resetdata->unenrol_users = array_keys($this->dbinterface->get_course_roles());

        $resetdata->reset_roles_overrides = 0;
        $resetdata->reset_roles_local = 0;
        $resetdata->reset_gradebook_grades = 1;
        $resetdata->reset_groups_remove = 0;
        $resetdata->reset_groupings_remove = 0;
        $resetdata->reset_groupings_members = 0;
        $resetdata->reset_forum_all = 1;
        $resetdata->reset_forum_types = 1;
        $resetdata->id = $this->courseid;

        reset_course_userdata($resetdata);

        // Remove roles.
        $this->dbinterface->unassign_roles($this->get_context()->id, $resetdata->unenrol_users);

        // Remove all enrol instance.
        enrol_course_delete($this->get_course(true));
    }
}
