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
 * Ad hoc task for duplicating a session content into its training
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core\task;

use local_mentor_core\database_interface;
use local_mentor_core\session_api;

class duplicate_session_into_training_task extends \core\task\adhoc_task {

    /**
     * Execute the task
     *
     * @return \local_mentor_core\training|int
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/local/mentor_core/api/training.php');
        require_once($CFG->dirroot . '/local/mentor_core/api/session.php');

        $data = $this->get_custom_data();

        // Check all required fields.
        if (!isset($data->sessionid) || empty($data->sessionid)) {
            throw new \coding_exception('Field sessionid is missing in custom data');
        }

        $dbinterface = database_interface::get_instance();

        // Check if the session exists.
        try {
            $dbinterface->get_session_by_id($data->sessionid);
        } catch (\dml_missing_record_exception $e) {
            return SESSION_NOT_FOUND;
        }

        $session = session_api::get_session($data->sessionid);
        $sessionurlcourse = $session->get_url()->out();

        $training = $session->duplicate_into_training();
        $trainingurlcourse = $training->get_url()->out();

        $creator = \core_user::get_user($this->get_userid());
        $supportuser = \core_user::get_support_user();

        // Get the content of the email.
        $content = get_string('duplicate_session_into_training_email', 'local_mentor_core', [
            'sessionurlsheet' => $sessionurlcourse,
            'trainingurlsheet' => $trainingurlcourse,
            'trainingfullname' => $training->name,
            'sessionfullname' => $session->fullname,
        ]);
        $contenthtml = text_to_html($content, false, false, true);

        // Send the email.
        email_to_user($creator, $supportuser,
            get_string('duplicate_session_new_training_object_email', 'local_mentor_core', $session->fullname), $content,
            $contenthtml);

        mtrace('Session ' . $data->sessionid . ' duplicated into training.');

        return $session->get_training();
    }
}

