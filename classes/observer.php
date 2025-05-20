<?php

use local_categories_domains\utils\categories_domains_service;
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
 * Plugin observers
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');
require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');

class local_mentor_core_observer {
    /**
     * Sync user main entity to the corresponding email
     *
     * @param \core\event\user_updated $event
     * @throws Exception
     */
    public static function sync_user_main_entity(\core\event\user_updated $event) {
        global $DB;
        $cds = new categories_domains_service();
        $user = $DB->get_record('user', ['id' => $event->objectid]);
        $cds->link_categories_to_users([$user]);
        return;
    }

    /**
     * Sync entities into user profile field
     *
     * @param \core\event\course_category_deleted $event
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function sync_mainentity(\core\event\course_category_deleted $event) {
        global $CFG;
        require_once($CFG->dirroot . '/local/mentor_core/lib.php');
        local_mentor_core_update_entities_list();
    }

    /**
     *
     * Sync user main entity to the corresponding email
     *
     * @param \core\event\user_created $event
     * @throws Exception
     */
    public static function sync_user_main_entity_on_create(\core\event\user_created $event) {
        global $DB;
        $cds = new categories_domains_service();
        $user = $DB->get_record('user', ['id' => $event->objectid]);
        
        if(empty($user->email)) $user->email = $user->username;
        $cds->link_categories_to_users([$user]);
        return;
    }

}
