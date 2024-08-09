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
 * Ad hoc task for duplicating a training
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core\task;

class duplicate_training_task extends \core\task\adhoc_task {

    /**
     * Execute the task
     *
     * @return \local_mentor_core\training
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \restore_controller_exception
     */
    public function execute() {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/local/mentor_core/api/training.php');

        $USER = get_admin();

        $data = $this->get_custom_data();

        // Define all required custom data fields.
        $requiredfields = ['trainingid', 'trainingshortname'];

        // Check all required fields.
        foreach ($requiredfields as $requiredfield) {
            if (!isset($data->{$requiredfield})) {
                throw new \coding_exception('Field ' . $requiredfield . ' is missing in custom data');
            }
        }

        // Trim the training name.
        $data->trainingshortname = trim($data->trainingshortname);

        // Get the training.
        $oldtraining = \local_mentor_core\training_api::get_training($data->trainingid);

        $destinationentity = isset($data->destinationentity) ? $data->destinationentity : null;

        // Duplicate the training.
        $newtraining = $oldtraining->duplicate($data->trainingshortname, $destinationentity);

        // Get recipient and sender.
        $creator = \core_user::get_user($this->get_userid());
        $supportuser = \core_user::get_support_user();

        // Get the content of the email.
        $content = get_string('duplicate_training_email', 'local_mentor_core', [
            'newtrainingurlsheet' => $newtraining->get_sheet_url($newtraining->get_url()->out())->out(),
            'newtrainingfullname' => $newtraining->name,
            'newtrainingshortname' => $newtraining->shortname,
            'oldtrainingurlsheet' => $oldtraining->get_sheet_url($oldtraining->get_url()->out())->out(),
            'oldtrainingfullname' => $oldtraining->name,
            'oldtrainingshortname' => $oldtraining->shortname,
        ]);
        $contenthtml = text_to_html($content, false, false, true);

        // Send the email.
        email_to_user($creator, $supportuser,
            get_string('duplicate_training_object_email', 'local_mentor_core', $newtraining->name), $content,
            $contenthtml);

        return $newtraining;
    }
}

