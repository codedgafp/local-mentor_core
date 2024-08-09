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
 * Duplicate session form
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     Adrien Jamot <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class duplicate_session_form extends \moodleform {

    private $sessionid;
    private $entitieslist;
    private $entitiesjs;

    /**
     * duplicate_session_form constructor.
     *
     * @param null $action
     * @param null $customdata
     * @param string $method
     * @param string $target
     * @param null $attributes
     * @param bool $editable
     * @param null $ajaxformdata
     */
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '', $attributes = null,
        $editable = true, $ajaxformdata = null) {

        $this->sessionid = $customdata['sessionid'];
        $this->entitieslist = $customdata['entitieslist'];
        $this->entitiesjs = $customdata['entitiesjs'];

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $ajaxformdata);
    }

    /**
     * Define entity form fields
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function definition() {
        global $CFG, $USER;
        $mform = $this->_form;

        $mform->addElement('select', 'duplicationtype', get_string('chooseduplicationtype', 'local_mentor_core'), [
            0 => get_string('createnewtraining', 'local_mentor_core'),
            1 => get_string('erasetraining', 'local_mentor_core'),
        ]);
        $mform->addRule('duplicationtype', get_string('required'), 'required', null, 'client');

        $strrequired = get_string("required");
        $requiredicon = '<i class="icon fa fa-exclamation-circle text-danger fa-fw" title="' . $strrequired . '" aria-label="' .
                        $strrequired . '"></i>';

        // New training.
        $mform->addElement('text', 'fullname', get_string('trainingfullname', 'local_mentor_core') . $requiredicon, [
            'size' => '45',
        ]);
        $mform->setType('fullname', PARAM_NOTAGS);
        $mform->hideIf('fullname', 'duplicationtype', 'eq', "1");

        $mform->addElement('text', 'shortname', get_string('trainingshortname', 'local_mentor_core') . $requiredicon, [
            'size' => '45',
        ]);
        $mform->setType('shortname', PARAM_NOTAGS);
        $mform->hideIf('shortname', 'duplicationtype', 'eq', "1");

        $session = session_api::get_session($this->sessionid);

        $sessionentity = $session->get_entity();

        $countentity = count($this->entitieslist);

        // Only one main entity.
        if ($countentity == 1) {
            $onlyentityid = array_keys($this->entitieslist)[0];

            // No subentities.
            if (isset($this->entitiesjs[$onlyentityid]) &&
                count($this->entitiesjs[$onlyentityid]) == 0) {
                $mform->addElement('hidden', 'entityid', $onlyentityid);
                $mform->setType('entityid', PARAM_INT);
            } else if (isset($this->entitiesjs[$onlyentityid]) &&
                       count($this->entitiesjs[$onlyentityid]) == 1) {
                // Only one subentity.
                $mform->addElement('hidden', 'subentityid', $this->entitiesjs[$onlyentityid][0]['id']);
                $mform->setType('subentityid', PARAM_INT);
            } else {
                // More than one subentity.
                $mform->addElement('select', 'entityid', get_string('destinationentity', 'local_mentor_core'), $this->entitieslist);
                $mform->hideIf('entityid', 'duplicationtype', 'eq', "1");
                $mform->setDefault('entityid', $sessionentity->id);

                $mform->addElement('select', 'subentityid', get_string('destinationsubentity', 'local_mentor_core'), []);
                $mform->hideIf('subentityid', 'duplicationtype', 'eq', "1");
            }
        } else {
            // More than one main entity.
            $mform->addElement('select', 'entityid', get_string('destinationentity', 'local_mentor_core'), $this->entitieslist);
            $mform->hideIf('entityid', 'duplicationtype', 'eq', "1");
            $mform->setDefault('entityid', $sessionentity->id);

            $mform->addElement('select', 'subentityid', get_string('destinationsubentity', 'local_mentor_core'), []);
            $mform->hideIf('subentityid', 'duplicationtype', 'eq', "1");
        }

        $mform->addElement('hidden', 'sessionid');
        $mform->setType('sessionid', PARAM_INT);

        $this->add_action_buttons(true, get_string('confirm', 'moodle'));
    }

    /**
     * Validate the form data
     *
     * @param array $data
     * @param array $files
     * @return array errors
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Set required fields for duplication as a new training.
        if ($data['duplicationtype'] == 0) {
            if (empty($data['fullname'])) {
                $errors['fullname'] = get_string('emptyfield', 'local_mentor_core');
            }

            if (empty($data['shortname'])) {
                $errors['shortname'] = get_string('emptyfield', 'local_mentor_core');
            }

            $dbinterface = database_interface::get_instance();

            // Check if training name is not already in use.
            if ($dbinterface->course_shortname_exists($data['shortname'])) {
                $errors['shortname'] = get_string('trainingshortnameexists', 'local_mentor_core');
            }
        }

        return $errors;
    }

    /**
     * Get form data
     *
     * @return object|void
     */
    public function get_data() {
        $data = parent::get_data();

        $hassubentityid = isset($_POST['subentityid']);

        if (isset($data->subentityid) || !$hassubentityid) {
            return $data;
        }

        // Add missing subentityid field value when the form does not retrieve it.
        $data->subentityid = $_POST['subentityid'];
        return $data;
    }

    /**
     * Set an element value
     *
     * @param string $elementname
     * @param mixed $value
     */
    public function set_value($elementname, $value) {
        $mform = $this->_form;
        $element = $mform->getElement($elementname);
        $element->setValue($value);
    }
}
