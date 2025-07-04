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
 * Test helper class.
 *
 */

namespace local_mentor_core\helper;

defined('MOODLE_INTERNAL') || die;

class testhelper
{
    /**
     * Create default entity
     *
     * @param $test current class test
     * @param string $entityname
     * @return int entity id
     */
    public static function create_default_entity($test, string $entityname = 'DefaultEntity'): string
    {
        global $USER, $DB;

        $defaultEntity = $DB->get_record_sql("SELECT * FROM {category_options} WHERE name = 'isdefaultentity' AND value = '1'");
        if ($defaultEntity) return $defaultEntity->id;

        $userid = !empty($USER->id) ? $USER->id : null;

        if (!$userid || !is_siteadmin($userid)) $test::setAdminUser();

        // Create_entity insert default entity if none exists.
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        if ($userid != null && !is_siteadmin($userid)) $test::setUser($userid);

        return (int)($entityid);
    }
}
