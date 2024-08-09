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
 * Specialization management singleton
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

class specialization {

    /**
     * @var \moodle_database
     */
    private $specializations;

    /**
     * @var self for singleton
     */
    private static $instance;

    public function __construct() {
        global $CFG;

        $this->specializations = [];

        if (!isset($CFG->mentor_specializations)) {
            return;
        }

        // Create a new instance of each specialization classes definied in config.php file.
        foreach ($CFG->mentor_specializations as $classname => $classfile) {
            require_once($CFG->dirroot . '/' . $classfile);
            $this->specializations[] = new $classname;
        }

    }

    /**
     * Create the singleton
     *
     * @return specialization
     */
    public static function get_instance() {

        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Call the specialization of a given action
     *
     * @param string $action
     * @param mixed $obj
     * @return mixed
     */
    public function get_specialization($action, $obj = null, $params = null) {

        foreach ($this->specializations as $spec) {
            $obj = $spec->get_specialization($action, $obj, $params);
        }

        return $obj;
    }

}
