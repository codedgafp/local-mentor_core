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
 * Entity created event.
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core\event;

use core\event\base;

/**
 * Entity created event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - string name: name of entity.
 *      - string managementcourseid: management course id of entity.
 * }
 *
 * @package    local_mentor_core
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entity_create extends base {

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = 'course_categories';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('evententitycreated', 'local_mentor_core');
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     * @throws \moodle_exception
     */
    public function get_url() {
        return new \moodle_url('/course/view.php', ['id' => $this->other['managementcourseid']]);
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' created the entity with course category id '$this->objectid'.";
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return [SITEID, 'category', 'add', 'course/view.php?id=' . $this->other['managementcourseid'], $this->objectid];
    }

    public static function get_objectid_mapping() {
        // Categories are not backed up, so no need to map them on restore.
        return ['db' => 'course_categories', 'restore' => base::NOT_MAPPED];
    }
}
