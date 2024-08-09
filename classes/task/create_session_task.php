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
 * Ad hoc task for creating a session
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core\task;

use local_mentor_core;

class create_session_task extends \core\task\adhoc_task {

    /**
     * Execute the task
     *
     * @return local_mentor_core\session|\stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function execute() {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');
        require_once($CFG->dirroot . '/local/mentor_core/api/training.php');
        require_once($CFG->dirroot . '/local/mentor_core/api/session.php');
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');

        $USER = get_admin();

        $data = $this->get_custom_data();

        // Define all required custom data fields.
        $requiredfields = ['trainingid', 'sessionname'];

        // Check all required fields.
        foreach ($requiredfields as $requiredfield) {
            if (!isset($data->{$requiredfield})) {
                throw new \coding_exception('Field ' . $requiredfield . ' is missing in custom data');
            }
        }

        $data->sessionname = str_replace("&#39;", "'", $data->sessionname);

        $entityid = isset($data->entityid) && !is_null($data->entityid) ? $data->entityid : null;

        $db = \local_mentor_core\database_interface::get_instance();

        $training = \local_mentor_core\training_api::get_training($data->trainingid);
        $course = get_course($training->get_course()->id);

        // Get the asked entity of the training entity.
        $entity = (is_null($entityid) || $entityid == 0) ? $training->get_entity() : local_mentor_core\entity_api::get_entity
        ($entityid);

        $context = $entity->get_context();

        // Check user capabilities.
        if (!has_capability('local/session:create', $context, $USER->id)) {
            throw new \moodle_exception('unauthorisedaction', 'local_mentor_core');
        }

        // Check if the destination category exists.
        if (!$db->course_category_exists($course->category)) {
            throw new \moodle_exception('inexistantcategory', 'local_mentor_core', '', $course->category);
        }

        // Generate a backup file of the training course.
        if (!$backupfile = $training->generate_backup()) {
            throw new \moodle_exception('backupnotcreated', 'local_mentor_core');
        }

        $course->shortname = trim($data->sessionname);

        $coursexists = $db->course_exists($course->shortname);
        $linkcoursexists = $db->session_exists($course->shortname);

        $counter = 0;
        $max = 50;

        // Define the course shortname by adding " session" until the shortname is unique.
        while ($coursexists || $linkcoursexists) {
            $course->shortname .= ' session';
            $coursexists = $db->course_exists($course->shortname);
            $linkcoursexists = $db->session_exists($course->shortname);
            if ($counter++ > $max) {
                // If the counter exceeds the max value, remove backup file.
                $backupfile->delete();
                throw new moodle_exception('maxloop', 'local_mentor_core');
            }
        }

        // Get the sessions category.
        $sessionscatname = 'Sessions';
        $sessioncat = $db->get_course_category_by_parent_and_name($entity->id, $sessionscatname);

        // Create a Sessions category if missing.
        if (!$sessioncat) {
            $newcategory = new \stdClass();
            $newcategory->name = $sessionscatname;
            $newcategory->parent = $entity->id;

            $sessioncat = \core_course_category::create($newcategory);
        }

        // Put the new course into the sessions category.
        $course->category = $sessioncat->id;

        // Set the activity type for a singleactivity restoration.
        if ($course->format == "singleactivity") {
            if ($activitytype = $db->get_course_singleactivity_type($course->id)) {
                $course->activitytype = $activitytype;
            }
        }

        // Backup the training course.
        $newcourse = create_course($course);

        $restoretarget = \backup::TARGET_EXISTING_DELETING;
        $fp = get_file_packer('application/vnd.moodle.backup');

        // Get file path.
        $dirname = basename($backupfile->get_filename(), '.mbz');

        $courseid = $newcourse->id;

        // Define a local storage for the backup restore.
        $oldbackuptempdir = $CFG->backuptempdir;
        $CFG->backuptempdir = isset($CFG->mentorbackuproot) ? $CFG->mentorbackuproot : $CFG->dataroot . '/mentor_backup';

        if (!is_dir($CFG->backuptempdir)) {
            mkdir($CFG->backuptempdir, 0775);
        }

        /* Restore the training course as a session course and places the backup
        in the course that will be linked to the new session. */
        if (!$backupfile->extract_to_pathname($fp, $CFG->backuptempdir . '//' . $dirname)) {
            // Delete backup and new course when we can't get to extract backup pathname.
            $backupfile->delete();
            delete_course($courseid, false);
            throw new \Exception('extract error in folder : ' . $CFG->backuptempdir . '/' . $dirname);
        }
        $rc = new \restore_controller($dirname, $courseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id,
            $restoretarget);
        $rc->execute_precheck();
        $rc->execute_plan();

        $CFG->backuptempdir = $oldbackuptempdir;

        // Delete the backup file.
        $backupfile->delete();

        $session = new \stdClass();
        $session->courseshortname = $newcourse->shortname;
        $session->trainingid = $data->trainingid;
        $session->status = \local_mentor_core\session::STATUS_IN_PREPARATION;

        // Create the session in database.
        $sessionid = $db->add_session($session);

        $session = \local_mentor_core\session_api::get_session($sessionid);

        $data = (object) get_object_vars($training);

        // Set training attributes into session.
        $alldatatounset = ['name', 'shortname', 'content', 'status'];

        foreach ($alldatatounset as $datatounset) {
            if (isset($data->{$datatounset})) {
                $attribute = 'training' . $datatounset;

                $data->{$attribute} = $data->{$datatounset};
                unset($data->{$datatounset});
            }
        }

        // Default session visibility.
        $data->opento = 'not_visible';

        // Update session.
        $session->update($data);

        // Trigger a session created event.
        $event = \local_mentor_core\event\session_create::create([
            'objectid' => $session->id,
            'context' => $session->get_context(),
        ]);
        $event->trigger();

        // Put the course into the Sessions category.
        \core_course\management\helper::move_courses_into_category($sessioncat->id, $courseid);

        // Enable manual enrolment.
        $session->create_manual_enrolment_instance();

        // Reset user data.
        $session->reset();

        // Delete all H5P owners in database.
        // If the owner loses his 'moodle/h5p:deploy' capability and the H5P has never opened before, then it is not visible.
        $db->remove_user_owner_h5p_file($session->get_context()->id);

        // Send email to user.
        $creator = \core_user::get_user($this->get_userid());
        $supportuser = \core_user::get_support_user();
        $content = get_string('create_session_email', 'local_mentor_core', [
            'sessionurlsheet' => $session->get_sheet_url()->out(),
            'sessionfullname' => $session->fullname,
            'sessionshortname' => $session->shortname,
            'trainingurlsheet' => $training->get_sheet_url()->out(),
            'trainingfullname' => $training->name,
            'trainingshortname' => $training->shortname,
        ]);
        $contenthtml = text_to_html($content, false, false, true);
        email_to_user($creator, $supportuser, get_string('create_session_object_email', 'local_mentor_core', $training->name),
            $content,
            $contenthtml);

        return $session;
    }
}

