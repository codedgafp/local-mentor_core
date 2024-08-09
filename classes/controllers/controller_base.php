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
 * Abstract controller class
 * A page is a course section with some other attributes
 *
 * @package    local_user
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

/**
 * Class controller_base
 *
 * @package local_user
 */
abstract class controller_base {

    /**
     * @var array
     */
    protected $params = [];

    /**
     * @var database_interface
     */
    protected $dbinterface;

    /**
     * @var import_interface
     */
    protected $importinterface;

    /**
     * controller_base constructor.
     *
     * @param $params
     */
    public function __construct($params) {

        $this->params = $params;
        $this->dbinterface = database_interface::get_instance();

    }

    /**
     * Get request param
     *
     * @param string $paramname
     * @param string $type default null if the type is not important
     * @param mixed $default default value if the param does not exist
     * @return mixed value of the param (or default value)
     * @throws \moodle_exception
     */
    public function get_param($paramname, $type = null, $default = false) {

        if (isset($this->params[$paramname])) {

            $param = $this->params[$paramname];

            if (!empty($type)) {
                switch ($type) {

                    case PARAM_INT :
                        if (!is_integer($param) && !ctype_digit($param)) {

                            throw new \moodle_exception('param : ' . $paramname . ' must be an integer for the value : ' . $param);

                        }
                        $param = (int) $param;
                        break;

                    // Add cases for new types here.
                    default :
                        break;
                }
            }
            return $param;
        }

        return $default;

    }

    /**
     * Success message former
     *
     * @param string|\stdClass|bool|int $message
     * @return array
     */
    public function success($message = '') {

        return [
            'success' => true,
            'message' => $message,
        ];
    }

    /**
     * Error message former
     *
     * @param string|\stdClass|bool|int $message
     * @return array
     */
    public function error($message = '') {
        return [
            'success' => false,
            'message' => $message,
        ];
    }

    abstract public function execute();

}
