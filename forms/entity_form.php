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

namespace local_mentor_core;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * entity form
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Nabil Hamdi <nabil.hamdi@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entity_form extends \moodleform {

    /**
     * Define entity form fields
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function definition() {
        global $CFG;
        $mform = $this->_form;

        // Check if elements must be disabled.
        $disabled = [];

        // Only the main admin can rename the entity.
        if (!is_siteadmin()) {
            $disabled = ['disabled' => 'disabled'];
        }

        $mform->addElement('text', 'namecategory', get_string('renameentity', 'local_entities'), $disabled);
        if (is_siteadmin()) {
            $mform->addRule('namecategory', get_string('required'), 'required');
        }
        $mform->setType('namecategory', PARAM_NOTAGS);

        $acceptedtypes = preg_split('/\s*,\s*/', trim($CFG->courseoverviewfilesext), -1, PREG_SPLIT_NO_EMPTY);

        $mform->addElement('filepicker', 'logo', 'Logo', null,
            ['accepted_types' => $acceptedtypes, 'subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 1024000]);

        $mform->addElement('hidden', 'idcategory');
        $mform->setType('idcategory', PARAM_INT);

        $this->add_action_buttons();
    }
}
