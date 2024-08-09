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
 * Ad hoc task for duplicating a session as a new training
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core\task;

use local_mentor_core\database_interface;
use local_mentor_core\session_api;

class duplicate_session_as_new_training_task extends \core\task\adhoc_task {

    /**
     * Execute the task
     *
     * @return \local_mentor_core\training
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/local/mentor_core/api/training.php');
        require_once($CFG->dirroot . '/local/mentor_core/api/session.php');

        $data = $this->get_custom_data();

        // Define all required custom data fields.
        $requiredfields = ['sessionid', 'trainingfullname', 'trainingshortname', 'entityid'];

        // Check all required fields.
        foreach ($requiredfields as $requiredfield) {
            if (!isset($data->{$requiredfield}) || empty($data->{$requiredfield})) {
                throw new \coding_exception('Field ' . $requiredfield . ' is missing in custom data');
            }
        }

        $dbinterface = database_interface::get_instance();

        // Trim the training name.
        $data->trainingshortname = trim($data->trainingshortname);

        // Check if training name is not already in use.
        if ($dbinterface->training_exists($data->trainingshortname)) {
            return false;
        }

        $session = session_api::get_session($data->sessionid);

        // Duplicate the session.
        $newtraining = $session->duplicate_as_new_training($data->trainingfullname, $data->trainingshortname, $data->entityid);

        mtrace('Training created : ' . $newtraining->id . ' - ' . $newtraining->shortname);

        $creator = \core_user::get_user($this->get_userid());
        $supportuser = \core_user::get_support_user();

        // Get the content of the email.
        $content = get_string('duplicate_session_new_training_email', 'local_mentor_core', [
            'sessionurlsheet' => $session->get_url()->out(),
            'trainingurlsheet' => $newtraining->get_sheet_url()->out(),
            'trainingfullname' => $newtraining->name,
            'sessionfullname' => $session->fullname,
        ]);
        $contenthtml = text_to_html($content, false, false, true);

        // Send the email.
        email_to_user($creator, $supportuser,
            get_string('duplicate_session_new_training_object_email', 'local_mentor_core', $session->fullname), $content,
            $contenthtml);

        return $newtraining;
    }
}

