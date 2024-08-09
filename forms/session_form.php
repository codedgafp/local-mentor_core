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

require_once($CFG->libdir . "/formslib.php");

/**
 * session form
 *
 * @package    local_session
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Nabil Hamdi <nabil.hamdi@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_form extends \moodleform {

    public $session;
    public $returnto;
    public $entity;
    public $sharedentities;
    public $logourl;

    /**
     * training_form constructor.
     *
     * @param $string action
     * @param $data
     */
    public function __construct($action, $data) {

        // Init entity object.
        $this->entity = $data->entity;
        $this->session = isset($data->session) ? $data->session : null;

        $this->sharedentities = $data->sharedentities;

        $this->logourl = $data->logourl;
        $this->returnto = $data->returnto;

        parent::__construct($data->actionurl);
    }

    /**
     * init training form
     *
     * @throws \coding_exception
     */
    protected function definition() {

        $mform = $this->_form;

        $mform->addElement('static', '', get_string('subentity', 'local_mentor_core'), $this->entity->get_entity_path());

        // Session status.
        $mform->addElement('select', 'status', get_string('status', 'local_mentor_core'), array_map(function($status) {
            return get_string($status, 'local_mentor_core');
        }, session_api::get_status_list()), ['style' => 'width : 405px']);
        $mform->addRule('status', get_string('required'), 'required');

        // Session name.
        $mform->addElement('text', 'fullname', get_string('fullname', 'local_session'), ['size' => 40]);
        $mform->setType('fullname', PARAM_RAW);
        if (!has_capability('local/session:changefullname', $this->session->get_context())) {
            $mform->disabledIf('fullname', '');
        } else {
            $mform->addRule('fullname', get_string('required'), 'required');
        }

        // Course shortname.
        $mform->addElement('text', 'shortname', get_string('shortname', 'local_session'), ['size' => 40]);
        $mform->setType('shortname', PARAM_NOTAGS);
        if (!has_capability('local/session:changeshortname', $this->session->get_context())) {
            $mform->disabledIf('shortname', '');
        } else {
            $mform->addRule('shortname', get_string('required'), 'required');
        }

        // Session start date.
        $mform->addElement('date_selector', 'sessionstartdate', get_string('sessionstartdate', 'local_mentor_core')
            , ['optional' => true]);

        // Session end date.
        $mform->addElement('date_selector', 'sessionenddate', get_string('sessionenddate', 'local_mentor_core'),
            ['optional' => true]);

        // Open session to other entities.
        $opentoarray = [];
        $opentoarray[] = $mform->createElement('radio', 'opento', '',
            get_string('notvisibleincatalog', 'local_mentor_core', $this->entity->name), 'not_visible');
        $opentoarray[] = $mform->createElement('radio', 'opento', '',
            get_string('all_user_current_entity', 'local_mentor_core', $this->entity->name), 'current_entity');
        $opentoarray[] = $mform->createElement('radio', 'opento', '',
            get_string('all_user_all_entity', 'local_mentor_core'), 'all');
        $opentoarray[] = $mform->createElement('radio', 'opento', '',
            get_string('all_user_current_entity_others', 'local_mentor_core', $this->entity->name), 'other_entities');
        $mform->addGroup($opentoarray, 'opentogroup', get_string('opento', 'local_mentor_core'), ['<br>', ''], false);
        $mform->setDefault('opento', 'not_visible');
        $mform->addRule('opentogroup', get_string('required'), 'required');

        if ($this->session->opento != 'other_entities') {
            $display = 'style="display: none;"';
        } else {
            $display = '';
        }
        $mform->addElement('html', '<div id="other_session_content" class="form-group row fitem" ' . $display .
                                   '><div class="col-md-3"></div><div class="col-md-9">');
        $mform->addElement('autocomplete', 'opentolist', '', $this->sharedentities,
            ['multiple' => true]);
        $mform->addElement('html', '</div></div>');

        // Max participants : rfc rlf == TEXT.
        $mform->addElement('text', 'maxparticipants', get_string('maxparticipants', 'local_mentor_core'),
            ['size' => 40]);
        $mform->setType('maxparticipants', PARAM_RAW_TRIMMED);
        $mform->setDefault('maxparticipants', '');

        $mform->addElement('hidden', 'returnto', $this->returnto);
        $mform->setType('returnto', PARAM_LOCALURL);

        $mform->addElement('hidden', 'oldshortname', $this->session->courseshortname);
        $mform->setType('oldshortname', PARAM_RAW);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Form validation
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function validation($data, $files) {
        $dbinterface = database_interface::get_instance();

        $errors = parent::validation($data, $files);

        // Check if shortname is not used.
        if (isset($data['shortname']) && trim($data['shortname']) !== $data['oldshortname']) {
            if ($dbinterface->course_exists(trim($data['shortname']))) {
                $errors['shortname'] = get_string('courseshortnameused', 'local_mentor_core');
            }
        }

        // Check if enddate is valid.
        if (isset($data['sessionenddate']) && $data['sessionenddate'] !== 0) {
            if ($data['sessionstartdate'] > $data['sessionenddate']) {
                $errors['sessionenddate'] = get_string('errorenddate', 'local_mentor_core');
            }
        }

        // Check if the fullname is not too long.
        if (isset($data['fullname']) && mb_strlen($data['fullname']) > 255) {
            $errors['fullname'] = get_string('fieldtoolong', 'local_mentor_core', 255);
        }

        // Check if the shortname already exists and if is not too long.
        if (isset($data['shortname']) && ($data['shortname'] != $data['oldshortname'])) {
            $shortname = $data['shortname'];

            // Check field length.
            if (mb_strlen($shortname) > 255) {
                $errors['shortname'] = get_string('fieldtoolong', 'local_mentor_core', 255);
            } else {
                if ($dbinterface->course_shortname_exists($shortname)) {
                    $errors['shortname'] = get_string('shortnameexist', 'local_trainings');
                }

                // Check if shortname is not used.
                if ($dbinterface->course_exists(trim($shortname))) {
                    $errors['shortname'] = get_string('courseshortnameused', 'local_mentor_core');
                }
            }
        }

        // Check max participants value.
        if (isset($data['maxparticipants'])) {

            if ('' !== $data['maxparticipants'] && (int) $data['maxparticipants'] <= 0) {
                $errors['maxparticipants'] = get_string('errorzero', 'local_mentor_core');
            }

            if (!empty($data['maxparticipants']) && !is_numeric($data['maxparticipants'])) {
                $errors['maxparticipants'] = get_string('errorzero', 'local_mentor_core');
            }
        }

        return $errors;
    }

}
