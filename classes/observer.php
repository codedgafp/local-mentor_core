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
     * Add user to entities cohorts corresponding to his profile
     *
     * @param \core\event\user_created $event
     * @throws Exception
     */
    public static function add_user_to_cohorts(\core\event\user_created $event) {
        global $CFG;

        require_once($CFG->dirroot . '/local/profile/lib.php');

        $userid = $event->objectid;

        $profile = \local_mentor_core\profile_api::get_profile($userid);

        $profile->sync_entities(true);
    }

    /**
     *
     * Sync user to entities cohorts corresponding to his profile
     *
     * @param \core\event\user_updated $event
     * @throws Exception
     */
    public static function sync_user_cohorts(\core\event\user_updated $event) {
        global $CFG;

        require_once($CFG->dirroot . '/local/profile/lib.php');

        $userid = $event->objectid;

        $profile = \local_mentor_core\profile_api::get_profile($userid);

        $profile->sync_entities();
    }

    /**
     *
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

}
