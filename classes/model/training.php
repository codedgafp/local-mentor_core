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
 * Class training
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     nabil <nabil.hamdi@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

use core_course\search\course;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/mentor_core/classes/model/model.php');
require_once($CFG->dirroot . '/local/mentor_core/api/library.php');
require_once($CFG->dirroot . '/course/format/edadmin/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/backup_controller_edu.class.php');

class training extends model {

    public const STATUS_DRAFT = 'draft';
    public const STATUS_TEMPLATE = 'template';
    public const STATUS_ELABORATION_COMPLETED = 'elaboration_completed';
    public const STATUS_ARCHIVED = 'archived';

    public const FAVOURITE_DESIGNER = 'favourite_training';

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $shortname;

    /**
     * @var string
     */
    public $content;

    /**
     * @var array
     */
    protected $training;

    /**
     * @var string
     */
    public $status;

    /**
     * @var string[]
     */
    protected $_allowedarea
        = [
            'thumbnail',
        ];

    /**
     * @var string
     */
    public $courseshortname;

    public $courseid;

    public $courseformat;

    protected $course;

    protected $context;

    protected $entity;

    public $entityname;

    public $traininggoal;

    public $thumbnail;

    protected $template;

    public $contextid;

    protected $pictures = [];

    public $lastupdate;

    /**
     * training constructor.
     *
     * @param int $trainingid
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function __construct($trainingid) {
        parent::__construct();

        $this->training = $this->dbinterface->get_training_by_id($trainingid);

        // Convert db result into object.
        $this->id = $this->training->id;
        $this->courseshortname = $this->training->courseshortname;
        $this->courseid = $this->training->courseid;
        $this->courseformat = $this->training->courseformat;
        $this->name = $this->training->name;
        $this->shortname = $this->training->shortname;
        $this->content = $this->training->content;
        $this->status = $this->training->status;
        $this->traininggoal = $this->training->traininggoal;
        $this->thumbnail = $this->training->thumbnail;
        $this->contextid = $this->training->contextid;
        $this->entityname = $this->get_entity(false)->name;
    }

    /**
     * Create course for training
     *
     * @param $course course de create
     * @return array|courses
     * @throws \moodle_exception
     */
    public static function create_course_training($course) {
        global $CFG;

        require_once($CFG->dirroot . '/course/externallib.php');

        $courses = [];
        // Create new courses.
        if (!empty($course)) {
            $courses = \core_course_external::create_courses([$course]);
        }

        return $courses;
    }

    /**
     * Update course for training
     *
     * @param array $course
     * @return array
     */
    public static function update_training_course($course) {
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
     * Get all training files
     *
     * @return \stored_file[]
     * @throws \dml_exception
     */
    public function get_all_training_files() {
        $dbfiles = $this->dbinterface->get_all_training_files($this->contextid);

        $files = [];

        $fs = get_file_storage();

        foreach ($dbfiles as $dbfile) {
            $files[$dbfile->id] = $fs->get_file_by_id($dbfile->id);
        }

        return $files;
    }

    /**
     * Get training pictures (thumbnail or producer organization logo).
     *
     * @param string $filearea
     * @param bool $refresh - True to refresh the picture
     * @return bool|\stored_file
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_training_picture($filearea = 'thumbnail', $refresh = false) {

        // By default, the only readable files are thumbnail.
        if (!in_array($filearea, $this->_allowedarea)) {
            return false;
        }

        if ($refresh || !isset($this->pictures[$filearea])) {
            $fs = get_file_storage();

            // Check if the file exists in database.
            if (!$dbfile = $this->dbinterface->get_file_from_database($this->get_context()->id, 'local_trainings', $filearea,
                $this->id)) {
                return false;
            }

            // Return the stored_file object.
            $file = $fs->get_file($dbfile->contextid, $dbfile->component, $dbfile->filearea, $dbfile->itemid, $dbfile->filepath,
                $dbfile->filename);

            $this->pictures[$filearea] = $file;
        }

        return $this->pictures[$filearea];
    }

    /**
     * Get the context of the linked course
     *
     * @return \context_course
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_context() {
        if (empty($this->context)) {
            $this->context = \context_course::instance($this->courseid);
        }
        return $this->context;
    }

    /**
     * Get the course linked to the training
     *
     * @param bool $refresh
     * @return \stdClass
     * @throws \dml_exception
     */
    public function get_course($refresh = false) {

        if ($refresh || empty($this->course)) {
            $this->course = $this->dbinterface->get_course_by_shortname($this->shortname, $refresh);

            if (!$this->course) {
                throw new \Exception('Course does not exist for shortname: ' . $this->shortname);
            }
        }

        return $this->course;
    }

    /**
     * Delete the training
     *
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \required_capability_exception
     */
    public function delete() {

        // Check capability.
        require_capability('local/trainings:delete', $this->get_context());

        if (!delete_course($this->courseid, false)) {
            throw new \moodle_exception('errorremovecourse', 'local_mentor_core');
        }

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
            \backup::TYPE_1COURSE,
            $this->courseid,
            \backup::FORMAT_MOODLE,
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
        $bc->disable_userinfo_to_all_activities();

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
     * Restore a backup into the training
     *
     * @param \stored_file $backupfile
     * @return bool true if success
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function restore_backup($backupfile) {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');

        $restoretarget = \backup::TARGET_EXISTING_DELETING;
        $fp = get_file_packer('application/vnd.moodle.backup');

        // Get path file.
        $dirname = basename($backupfile->get_filename(), '.mbz');

        $courseid = $this->courseid;

        $oldbackuptempdir = $CFG->backuptempdir;
        $CFG->backuptempdir = isset($CFG->mentorbackuproot) ? $CFG->mentorbackuproot : $CFG->dataroot . '/mentor_backup';

        if (!is_dir($CFG->backuptempdir)) {
            mkdir($CFG->backuptempdir, 0775);
        }

        // Puts the backup in the course related to the training.
        if (!$backupfile->extract_to_pathname($fp, $CFG->backuptempdir . '/' . $dirname)) {
            throw new \Exception('extract error in folder : ' . $CFG->backuptempdir . '/' . $dirname);
        }

        // User used to make the backup.
        $userrestorebackupid = $USER->id;

        // Check if user has all capability to restor all backup in training.
        if (has_capability('local/mentor_core:movesessions', $this->get_context(), $USER)) {
            $userrestorebackupid = get_admin()->id;
        }

        // Restore the course.
        $rc = new \restore_controller($dirname, $courseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $userrestorebackupid,
            $restoretarget);
        $rc->execute_precheck();
        $rc->execute_plan();

        $CFG->backuptempdir = $oldbackuptempdir;

        // Delete the backup file.
        $backupfile->delete();

        // Update the training shortname.
        $training = clone($this);
        $training->courseshortname = get_course($courseid)->shortname;
        $this->dbinterface->update_training($training);

        // Delete all H5P owners in database.
        // If the owner loses his 'moodle/h5p:deploy' capability and the H5P has never opened before, then it is not visible.
        $this->dbinterface->remove_user_owner_h5p_file($this->get_context()->id);

        return true;
    }

    /**
     * Get the parent entity of the training
     *
     * @return entity
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_entity($refresh = true) {

        // Check if the entity has already been retrieved.
        if ($refresh || empty($this->entity)) {
            $entityid = $this->dbinterface->get_course_main_category_id($this->courseid);
            $this->entity = entity_api::get_entity($entityid, $refresh);
        }

        return $this->entity;
    }

    /**
     * Check the user's ability to manage a training course
     *
     * @param integer|\stdClass $user
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function is_manager($user) {
        $context = $this->get_context();
        return has_capability('local/trainings:manage', $context, $user);
    }

    /**
     * Check the user's ability to create a training course
     *
     * @param integer|\stdClass $user
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function is_creator($user) {
        $context = $this->get_context();
        return has_capability('local/trainings:create', $context, $user);
    }

    /**
     * Check the user's ability to update a training course
     *
     * @param integer|\stdClass $user
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function is_updater($user) {
        $context = $this->get_context();
        return has_capability('local/trainings:update', $context, $user);
    }

    /**
     * Check the user's ability to delete a training course
     *
     * @param integer|\stdClass $user
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function is_deleter($user) {
        $context = $this->get_context();
        return has_capability('local/trainings:delete', $context, $user);
    }

    /**
     * Update the training
     *
     * @param \stdClass $data
     * @param training_form|null $mform
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function update($data, $mform = null) {

        // If form is modified by a designer.
        $this->name = isset($data->name) ? trim($data->name) : $this->name;

        // Last update
        $this->lastupdate = isset($data->lastupdate) ? $data->lastupdate : null;

        $this->courseshortname = isset($data->shortname) ? $data->shortname : $this->courseshortname;
        $this->shortname = isset($data->shortname) ? $data->shortname : $this->shortname;

        // Training goal.
        if (isset($data->traininggoal) && is_array($data->traininggoal)) {
            $data->traininggoal = $data->traininggoal['text'];
        }
        $this->traininggoal = isset($data->traininggoal) ? $data->traininggoal : $this->traininggoal;

        $this->status = isset($data->status) ? $data->status : $this->status;

        // Content.
        if (isset($data->content) && is_array($data->content)) {
            $data->content = $data->content['text'];
        }
        $this->content = isset($data->content) ? $data->content : $this->content;

        // Thumbnail.
        if (isset($data->deletethumbnail) && $data->deletethumbnail == 1) {
            $this->thumbnail = '';
        } else {
            $this->thumbnail = isset($data->thumbnail) ? $data->thumbnail : $this->thumbnail;
        }

        // Update the training course.
        $course = [
            'id' => $this->courseid,
            'fullname' => $this->name,
            'shortname' => $this->shortname,
            'categoryid' => isset($data->categorychildid) ? $data->categorychildid : $this->get_course()->category,
            'summary' => $this->content,
        ];

        $oldcourse = $this->get_course();
        $result = self::update_training_course($course);

        // Check if the training course has been updated correctly.
        if (!empty($result['warnings'])) {
            throw new \Exception('Error :' . $result['warnings'][0]['message']);
        }

        // Update the training in database.
        if (!$this->dbinterface->update_training($this)) {
            // Reverse course update.
            self::update_training_course([
                'id' => $this->courseid,
                'fullname' => $oldcourse->fullname,
                'shortname' => $oldcourse->shortname,
                'categoryid' => $oldcourse->category,
                'summary' => $oldcourse->summary,
            ]);
            throw new \Exception(get_string('trainingupdatefailed', 'local_mentor_core'));
        }

        // Create files only from the training form.
        if (!empty($mform)) {
            $this->create_files_by_training_form(['thumbnail'], $mform, $data);
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
    public function create_files_by_training_form($pictures, $mform, $data) {

        $fs = get_file_storage();

        $maxfilewidth = 400;

        foreach ($pictures as $picture) {
            $data->{$picture} = $mform->get_new_filename($picture);

            // No file provided, continue.
            if (empty($data->{$picture})) {
                continue;
            }

            // Delete the old file.
            $fs->delete_area_files($this->contextid, 'local_trainings', $picture, $this->id);

            $deletefield = 'delete' . $picture;

            // Check if the delete picture is checked or not.
            if (!isset($data->{$deletefield}) || $data->{$deletefield} != 1) {
                // Store the new file.
                $file = $mform->save_stored_file($picture, $this->contextid, 'local_trainings',
                    $picture, $this->id);

                // Resize the file.
                local_mentor_core_resize_picture($file, $maxfilewidth);
            }
        }
    }

    /**
     * Get course training url
     *
     * @return \moodle_url
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_url() {
        return local_mentor_core_get_course_url($this->get_course());
    }

    /**
     * Get the list of user training actions
     *
     * @param null|int $userid
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_actions($userid = null, $actiondata = []) {
        global $USER;

        if (!$userid) {
            $userid = $USER->id;
        }

        $actions = [];

        $entity = $this->get_entity(false);

        $context = $entity->get_context();

        // Check if the user can access the training sheet.
        $mainentity = $entity->get_main_entity();
        $trainingcourse = $mainentity->get_edadmin_courses('trainings');
        $url = new \moodle_url('/course/view.php', ['id' => $trainingcourse['id']]);
        $actions['trainingsheet'] = [
            'url' => $this->get_sheet_url()->out() . '&returnto=' . $url,
            'tooltip' => get_string('gototrainingsheet', 'local_mentor_core'),
        ];

        // Move training.
        if (isset($actiondata['move']) && $actiondata['move']) {
            $actions['movetraining'] = '';
        }

        // Add button to subentity if user has capability.
        if (
            isset($actiondata['movetosubentity']) &&
            // Add if it has at least 2 sub entities with capability.
            count($actiondata['movetosubentity']) > 1 &&
            !$entity->is_main_entity() &&
            // If user has capability to this subentity.
            isset($actiondata['movetosubentity'][$entity->id]) &&
            $actiondata['movetosubentity'][$entity->id]
        ) {
            $actions['movetraining'] = '';
        }

        // Manual enrolmments.
        if (has_capability('enrol/manual:enrol', \context_course::instance($this->courseid))) {
            $actions['assignusers'] = (new \moodle_url('/user/index.php', [
                'id' => $this->courseid,
            ]))->out();
        }

        // Check if the user can duplicate a training.
        if ($this->is_creator($userid) ||
            (has_capability('local/trainings:createinsubentity', $context) && $this->status == self::STATUS_TEMPLATE)
        ) {
            $actions['duplicatetraining'] = '';
        }

        // Checks if the user can create a session.
        if (
            (has_capability('local/session:create', $context) ||
             has_capability('local/session:createinsubentity', $context)) &&
            ($this->status == self::STATUS_ELABORATION_COMPLETED)) {
            $actions['createsessions'] = '';
        }

        return $actions;
    }

    /**
     * Duplicate the training
     *
     * @param string $trainingshortname shortname of the created course
     * @param int $destinationentity optional default null move the created training into a new entity
     * @return training the created training
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function duplicate($trainingshortname, $destinationentity = null) {
        global $DB;

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

        $training = clone($this);

        if (!empty($destinationentity)) {
            // Move the course into the new entity.
            $entity = entity_api::get_entity($destinationentity);
            $training->categorychildid = $entity->get_entity_formation_category();
            $training->categoryid = $entity->id;
        } else {
            $training->categorychildid = $course->category;
            $training->categoryid = \core_course_category::get($course->category)->parent;
        }

        // Name and shortname are updated after the restore.
        // This ame and shortname are used so that there is.
        // no error saying that they are used for another course.
        $training->name = $course->fullname;
        $training->shortname = $trainingshortname;
        // Make "draft" default status value.
        $training->status = self::STATUS_DRAFT;

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

        // Clone 'summary' course attribute.
        $newcourse = $newtraining->get_course(true);
        $newcourse->summary = $course->summary;
        $newcourse->format = $course->format;
        $newcourse->showgrades = $course->showgrades;
        $newcourse->newsitems = $course->newsitems;
        $newcourse->enablecompletion = $course->enablecompletion;
        $newcourse->completionnotify = $course->completionnotify;

        // Copy edadmin course format options.
        $formatoptions = $this->dbinterface->get_course_format_options_by_course_id($course->id);
        foreach ($formatoptions as $formatoption) {
            $formatoption->courseid = $newcourse->id;
            $this->dbinterface->add_course_format_option($formatoption);
        }

        // Copy singleactivity course format options.
        $formatoptions = $this->dbinterface->get_course_format_options_by_course_id($course->id, true, 'singleactivity');
        foreach ($formatoptions as $formatoption) {
            $formatoption->courseid = $newcourse->id;
            $this->dbinterface->add_course_format_option($formatoption);
        }

        // Update the new course.
        update_course($newcourse);

        // Copy the pictures.
        $fs = get_file_storage();
        $newpicturedata = ['contextid' => $newtraining->contextid, 'itemid' => $newtraining->id];

        // Copy the thumbnail.
        if ($oldpicture = $this->get_training_picture('thumbnail')) {
            $fs->create_file_from_storedfile($newpicturedata, $oldpicture);
        }

        // Create manual enrolment instance.
        $newtraining->create_manual_enrolment_instance();

        // Refresh data.
        return training_api::get_training($newtraining->id);
    }

    /**
     * Get sheet training url
     *
     * @param string|null $returnto
     * @return \moodle_url
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_sheet_url(string $returnto = '') {
        $params = [
            'trainingid' => $this->id,
            'entityid' => $this->get_entity(false)->id,
        ];

        if (strlen($returnto) > 0) {
            $params['returnto'] = $returnto;
        }

        return new \moodle_url('/local/trainings/pages/update_training.php', $params);
    }

    /**
     * Get a training object for the edit form
     *
     * @return training
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function prepare_edit_form() {
        $training = clone($this);

        // Get training content.
        if (isset($training->content)) {
            $training->content = ['text' => $training->content];
        }

        // Get training thumbnail.
        if ($trainingthumb = $this->get_training_picture()) {

            // Get training picture.
            $draftitemid = file_get_submitted_draft_itemid('thumbnail');
            file_prepare_draft_area($draftitemid, $trainingthumb->get_contextid(), 'local_trainings', 'thumbnail', $training->id);
            $training->thumbnail = $draftitemid;
        } else {
            $training->thumbnail = '';
        }

        // Get training goals.
        if (isset($training->traininggoal)) {
            $training->traininggoal = ['text' => $training->traininggoal];
        }

        return $training;
    }

    /**
     * Check if the training has sessions
     *
     * @return bool
     * @throws \dml_exception
     */
    public function has_sessions() {
        return $this->dbinterface->training_has_sessions($this->id);
    }

    /**
     * Check if the training has sessions in recycle bin
     *
     * @return bool
     * @throws \dml_exception
     */
    public function has_sessions_in_recyclebin() {
        return $this->dbinterface->training_has_sessions_in_recycle_bin($this->id);
    }

    /**
     * Get a lighter version of the current object for an usage on mustache
     *
     * @return \stdClass
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function convert_for_template() {
        global $USER;

        // Initialise the template data.
        $templateobj = new \stdClass();
        $templateobj->id = $this->id;
        $templateobj->name = $this->name;
        $templateobj->courseurl = $this->get_url()->out();
        $templateobj->content = $this->content;
        $templateobj->traininggoal = local_mentor_core_clean_html($this->traininggoal);
        $templateobj->isreviewer = false;

        // Check if the user can review the training.
        if (!has_capability('local/trainings:update', $this->get_context(), $USER)) {
            $templateobj->isreviewer = true;
        }

        // Get the training entity.
        $trainingentity = $this->get_entity()->get_main_entity();

        // Set entity data to the template.
        $templateobj->entityid = $trainingentity->id;
        $templateobj->entityname = $trainingentity->name;
        $templateobj->favouritedesignerdata = $this->get_favourite_designer_data();

        // Check if all enrolments user are enabled.
        $templateobj->hasenrollenabled = $this->has_enroll_user_enabled();

        // Set the training thumbnail.
        if ($thumbnail = $this->get_training_picture()) {
            $templateobj->thumbnail = \moodle_url::make_pluginfile_url(
                $thumbnail->get_contextid(),
                $thumbnail->get_component(),
                $thumbnail->get_filearea(),
                $thumbnail->get_itemid(),
                $thumbnail->get_filepath(),
                $thumbnail->get_filename()
            )->out();
        } else {
            $templateobj->thumbnail = null;
        }

        return $templateobj;
    }

    /**
     * Create a manual enrolment instance for this training
     * if not exist
     * Enable instance if exist
     *
     * @return bool true if is create, false if already exists
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
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
            $instance->courseid = $this->courseid;
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
     * Enable self enrol instance if exist and is disable
     *
     * @return bool
     * @throws \dml_exception
     */
    protected function enable_manual_enrolment_instance() {
        // Check if enrol instance exist.
        if (!$selfenrolmentinstance = $this->get_enrolment_instances_by_type('manual')) {
            return false;
        }

        // Check if self enrolment is enable.
        if (!$selfenrolmentinstance->status) {
            return true;
        }

        // Enable self enrolment instance.
        $selfenrolmentinstance->status = 0; // Enable.
        $this->update_enrolment_instance($selfenrolmentinstance);

        return true;
    }

    /**
     * Update enrol instance data
     *
     * @param $data
     * @throws \dml_exception
     */
    public function update_enrolment_instance($data) {
        $this->dbinterface->update_enrolment($data);
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

    /**mod/scorm:viewreport
     * Get all enrol instances
     *
     * @return array
     * @throws dml_exception
     */
    public function get_enrolment_instances() {
        return enrol_get_instances($this->courseid, false);
    }

    /**
     * Get the number of sessions for this training.
     *
     * @return false|int
     * @throws \dml_exception
     */
    public function get_session_number() {
        return $this->dbinterface->get_session_number($this->id);
    }

    /**
     * Get the number of availables sessions for this training.
     *
     * @return false|int
     * @throws \dml_exception
     */
    public function get_availables_sessions_number() {
        return $this->dbinterface->get_availables_sessions_number($this->id);
    }

    /**
     * Reset all userdata
     *
     * @param bool $removeenroluser Remove all user enrol.
     * @param bool $removeenrolinstance Remove all enrolment instance.
     */
    public function reset($removeenroluser = true, $removeenrolinstance = true) {
        $resetdata = new \stdClass();
        $resetdata->reset_start_date = 0;
        $resetdata->reset_end_date = 0;
        $resetdata->reset_events = 1;
        $resetdata->reset_comments = 1;
        $resetdata->reset_completion = 1;
        $resetdata->delete_blog_associations = 1;
        $resetdata->reset_competency_ratings = 1;

        if ($removeenroluser) {
            // List course roles.
            $resetdata->unenrol_users = array_keys($this->dbinterface->get_course_roles());
        }

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

        if ($removeenroluser) {
            // Remove roles.
            $this->dbinterface->unassign_roles($this->get_context()->id, $resetdata->unenrol_users);
        }

        if ($removeenrolinstance) {
            // Remove all enrol instance.
            enrol_course_delete($this->get_course(true));
        }
    }

    /**
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_all_enrolments() {
        $contextid = $this->get_context()->id;
        return $this->dbinterface->get_role_assignments($contextid);
    }

    /**
     * Set training course format options
     *
     * @param string $format
     * @param array $options
     * @return void
     */
    public function set_course_format_options($format, $options) {
        return $this->dbinterface->set_course_format_options($this->courseid, $format, $options);
    }

    /**
     * Check if the user has chosen this training in these preferred designs
     * if is true, return favourite object data.
     *
     * @param int|null $userid
     * @return \stdClass|false
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_favourite_designer_data($userid = null) {
        return $this->dbinterface->get_training_user_favourite_designer_data($this->id, $this->get_context()->id, $userid);
    }

    /**
     * Check if enrol user for this training is enabled.
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
     * Get training sessions
     *
     * @param string $orderby - optional default ''
     * @return session[]
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_sessions($orderby = '') {
        return session_api::get_sessions_by_training($this->id, $orderby);
    }

    /**
     * Check if the current user can register for one training session.
     *
     * @param int|null $userid
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function is_available_to_user($userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        $sessions = $this->get_sessions();

        foreach ($sessions as $session) {
            // Check if session is available.
            if ($session->is_available_to_user($userid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a self enrolment instance for this training
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
            $instance->customint4 = 0;
            $instance->customint5 = 0;
            $instance->customint6 = 1; // Enable.
            $instance->name = 'Démonstration';
            $instance->password = '';
            $instance->customtext1 = '';
            $instance->returnurl = '';
            $instance->expirythreshold = 0;
            $instance->enrolstartdate = 0;
            $instance->enrolenddate = 0;

            // Demo users are enrolled for 1 day.
            $instance->enrolperiod = 86400;

            // Add seld enrolled users as demo users.
            $demorole = $this->dbinterface->get_role_by_name('participantdemonstration');
            if ($demorole) {
                $instance->roleid = $demorole->id;
            }

            $fields = (array) $instance;

            return $plugin->add_instance($course, $fields);
        }

        // Enable enrol instance if disable.
        return $this->enable_self_enrolment_instance();
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
        if (!$selfenrolmentinstance->status) {
            return false;
        }

        // Enable self enrol instance.
        $selfenrolmentinstance->status = 0; // Enable.
        $this->update_enrolment_instance($selfenrolmentinstance);

        return true;
    }

    /**
     * Check if the training entity is the library
     *
     * @return bool
     */
    public function is_from_library() {
        $libraryid = library_api::get_library_id();
        $maincategoryid = $this->get_entity()->get_main_entity()->id;

        return $maincategoryid == $libraryid;
    }

    /**
     * Enrol the current user by self enrolment
     *
     * @return array enrolment success
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function enrol_current_user() {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/enrol/self/externallib.php');

        // Check if the user can view the library.
        if (!library_api::user_has_access()) {
            return [
                'status' => false,
                'warnings' => ['message' => get_string('librarynotaccessible', 'local_mentor_core')],
            ];
        }

        // Check if the training is a library training.
        if (!$this->is_from_library()) {
            return [
                'status' => false,
                'warnings' => ['message' => get_string('trainingnotinthelibrary', 'local_mentor_core')],
            ];
        }

        // Get or create the self enrolment instance.
        $instance = $this->get_enrolment_instances_by_type('self');
        if (!$instance) {
            $this->create_self_enrolment_instance();
            $instance = $this->get_enrolment_instances_by_type('self');
        }

        // Checks if the user is already enrolled.
        if ($this->has_self_enrol()) {
            return [
                'status' => true,
            ];
        }

        // Try to enrol the user with default enrolment settings.
        try {
            $result = \enrol_self_external::enrol_user($this->courseid, '', $instance->id);
        } catch (Exception $e) {
            return [
                'status' => false,
                'warnings' => ['message' => $e->getMessage()],
            ];
        }

        return $result;
    }

    /**
     * Checks if the user is already self enrolled.
     *
     * @return bool
     * @throws \dml_exception
     */
    public function has_self_enrol() {
        global $USER;

        if (!$instance = $this->get_enrolment_instances_by_type('self')) {
            return false;
        }

        return $this->dbinterface->has_enrol_by_instance_id($instance->id, $USER->id);
    }
}
