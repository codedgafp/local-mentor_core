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

use local_trainings\database_interface;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

/**
 * training form
 *
 * @package    local_trainings
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Nabil Hamdi <nabil.hamdi@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class training_form extends \moodleform {

    /**
     * @var entity
     */
    public $entity;

    public $returnto;

    /**
     * training_form constructor.
     *
     * @param string $action
     * @param \stdClass $data
     */
    public function __construct($action, $data) {

        // Init entity object.
        $this->entity = $data->entity;
        $this->returnto = isset($data->returnto) ? $data->returnto : '';

        parent::__construct($data->actionurl);
    }

    /**
     * init training form
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function definition() {
        global $CFG;

        $mform = $this->_form;

        $acceptedtypes = preg_split('/\s*,\s*/', trim($CFG->courseoverviewfilesext), -1, PREG_SPLIT_NO_EMPTY);

        $context = $this->entity->get_context();

        // Statut.
        $mform->addElement('select', 'status', get_string('status', 'local_trainings'), array_map(function($status) {
            return get_string($status, 'local_trainings');
        }, training_api::get_status_list()), ['style' => 'width : 405px']);
        $mform->addRule('status', get_string('required'), 'required');

        // Libellé de la formation.
        $mform->addElement('text', 'name', get_string('name', 'local_trainings'), ['size' => 40]);
        $mform->setType('name', PARAM_RAW);
        if (!has_capability('local/mentor_core:changefullname', $context)) {
            $mform->disabledIf('name', '');
        } else {
            $mform->addRule('name', get_string('required'), 'required');
        }

        // Nom abrégé du cours.
        $mform->addElement('text', 'shortname', get_string('shortname', 'local_trainings'), ['size' => 40]);
        $mform->setType('shortname', PARAM_NOTAGS);
        if (!has_capability('local/mentor_core:changeshortname', $context)) {
            $mform->disabledIf('shortname', '');
        } else {
            $mform->addRule('shortname', get_string('required'), 'required');
        }

        // Vignette.
        $mform->addElement('filepicker', 'thumbnail',
            get_string('thumbnail', 'local_trainings') . ' ' . get_string('recommandedratio', 'local_mentor_core', '3:2'), null,
            ['accepted_types' => $acceptedtypes, 'subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 1024000]);

        // Contenu de la formation.
        $mform->addElement('editor', 'content', get_string('content', 'local_trainings'), [
            'rows' => 8, 'cols' => 60,
        ]);
        $mform->setType('content', PARAM_RAW);
        if (!has_capability('local/mentor_core:changecontent', $context)) {
            $mform->disabledIf('content', '');
        }

        // Objectifs de la formation.
        $mform->addElement('editor', 'traininggoal', get_string('traininggoal', 'local_trainings'), [
            'rows' => 8, 'cols' => 60,
        ]);
        $mform->setType('traininggoal', PARAM_RAW);
        if (!has_capability('local/mentor_core:changetraininggoal', $context)) {
            $mform->disabledIf('traininggoal', '');
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'categoryid', $this->entity->id);
        $mform->setType('categoryid', PARAM_INT);

        $mform->addElement('hidden', 'categorychildid', $this->entity->get_entity_formation_category());
        $mform->setType('categorychildid', PARAM_INT);

        $mform->addElement('hidden', 'returnto', $this->returnto);
        $mform->setType('returnto', PARAM_LOCALURL);

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
        global $USER;
        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $errors = parent::validation($data, $files);

        // Check thumbnail.
        if ($data['status'] != training::STATUS_DRAFT) {
            $fs = get_file_storage();
            $context = \context_user::instance($USER->id);

            if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $data['thumbnail'], 'id DESC', false)) {
                $errors['thumbnail'] = get_string('errorthumbnail', 'local_mentor_specialization');
            }
        }

        // Check required fields when status is "Elaboration Completed".
        if ($data['status'] == training::STATUS_ELABORATION_COMPLETED) {
            // Check content.
            if ($data['content']['text'] == '') {
                $errors['content'] = get_string('errorelaborationcompleted', 'local_mentor_specialization');
            }

            // Check traininggoal.
            if ($data['traininggoal']['text'] == '') {
                $errors['traininggoal'] = get_string('errorelaborationcompleted', 'local_mentor_specialization');
            }
        }

        // Check if the fullname is not too long.
        if (isset($data['name']) && mb_strlen($data['name']) > 254) {
            $errors['name'] = get_string('fieldtoolong', 'local_mentor_core', 254);
        }

        // Check if the shortname already exists and if is not too long.
        if (isset($data['shortname'])) {
            $shortname = $data['shortname'];

            // Check shortname length.
            if (mb_strlen($shortname) > 255) {
                $errors['shortname'] = get_string('fieldtoolong', 'local_mentor_core', 255);
            } else {
                // Check if the shortname already exists.
                if (isset($this->training) && ($this->training->shortname != $shortname) &&
                    ($dbinterface->course_shortname_exists($shortname) ||
                     $dbinterface->training_exists($shortname))) {
                    $errors['shortname'] = get_string('shortnameexist', 'local_trainings');
                }
            }
        }

        return $errors;
    }
}
