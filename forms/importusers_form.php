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
 * Import Users form
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     mounir <mounir.ganem@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/user/lib.php');

class importusers_form extends moodleform {

    /**
     * @var $users string List of users to import in CSV format
     */
    private $users;

    /**
     * @var array List users to reactivate.
     */
    private $userstoreactivate;

    /**
     * importusers_form constructor.
     *
     * @param array $users
     * @param array $userstoreactivate
     * @param null $action
     * @param null $customdata
     * @param string $method
     * @param string $target
     * @param null $attributes
     * @param bool $editable
     * @param null $ajaxformdata
     */
    public function __construct($users = [], $userstoreactivate = [], $action = null, $customdata = null, $method = 'post',
        $target = '', $attributes
        = null,
        $editable = true, $ajaxformdata = null) {

        $this->users = $users;
        $this->userstoreactivate = $userstoreactivate;

        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $ajaxformdata);
    }

    /**
     * Define form fields
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'users', 'users');
        $mform->setType('users', PARAM_RAW);
        $mform->setDefault('users', json_encode($this->users));

        $mform->addElement('hidden', 'userstoreactivate', 'userstoreactivate');
        $mform->setType('userstoreactivate', PARAM_RAW);
        $mform->setDefault('userstoreactivate', json_encode($this->userstoreactivate));

        $this->add_action_buttons(false, get_string('import_and_enrol', 'local_mentor_core'));
    }
}
