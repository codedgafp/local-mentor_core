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
 * Class library
 *
 * @package    local_mentor_core
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/mentor_core/classes/model/model.php');

class library extends entity {

    public const NAME = 'Bibliothèque de formations';
    public const SHORTNAME = 'Biblio formations';
    public const CONFIG_VALUE_ID = 'library_id';

    /**
     * @var self
     */
    protected static $instance;

    /**
     * Create a singleton
     *
     * @return library
     * @throws \moodle_exception
     */
    public static function get_instance() {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        if (!$libraryid = \local_mentor_core\library_api::get_library_id()) {
            $dbi = \local_mentor_core\database_interface::get_instance();
            if (!$libraryobject = $dbi->get_library_object()) {
                throw new \moodle_exception('La bibli n\'a pas pu être créer');
            }
            \local_mentor_core\library_api::set_library_id_to_config($libraryobject->id);
            $libraryid = $libraryobject->id;
        }

        self::$instance = new static($libraryid);
        return self::$instance;
    }
}
