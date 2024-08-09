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
class sub_entity_form extends \moodleform {

    /**
     * training_form constructor.
     *
     * @param stdClass $entity
     * @throws \moodle_exception
     */
    public function __construct($action, $data) {

        // Init entity object.
        $this->entity = $data->entity;

        parent::__construct($action, $data);
    }

    /**
     * Define entity form fields
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    protected function definition() {
        global $CFG;
        $mform = $this->_form;

        // Check if elements must be disabled.
        $attributes = [];

        // Only the main admin can rename the entity.
        if (!has_capability('local/entities:renamesubentity', $this->entity->get_context())) {
            $attributes = ['disabled' => 'disabled', 'size' => '50'];
        }

        $mform->addElement('text', 'namecategory', get_string('renamesubentity', 'local_entities'), $attributes);
        if (has_capability('local/entities:renamesubentity', $this->entity->get_context())) {
            $mform->addRule('namecategory', get_string('required'), 'required');
        }
        $mform->setType('namecategory', PARAM_NOTAGS);
        $mform->addRule('namecategory', get_string('entitynamelimit', 'local_mentor_core', 70), 'maxlength', 70, 'client');

        // Parent entity.
        $structurehtml = '<span>' . $this->entity->get_main_entity()->name . '</span>';
        $mform->addElement('static', 'parententity', get_string('parententity', 'local_mentor_core'), $structurehtml);

        $mform->addElement('hidden', 'idcategory');
        $mform->setType('idcategory', PARAM_INT);

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
     * @throws \moodle_exception
     */
    public function validation($data, $files) {

        $errors = parent::validation($data, $files);

        if (empty(trim($data['namecategory']))) {
            $errors['namecategory'] = get_string('required');
        }

        $data['namecategory'] = trim($data['namecategory']);

        $mainentity = $this->entity->get_main_entity();

        // Check if sub entity name is used.
        if (!empty($data['namecategory']) && \local_mentor_core\entity_api::sub_entity_exists($data['namecategory'],
                $mainentity->id, true) &&
            $data['namecategory'] !== $this->entity->name) {
            $errors['namecategory'] = get_string('errorentityexist', 'local_mentor_core', $data['namecategory']);
        }

        return $errors;
    }
}
