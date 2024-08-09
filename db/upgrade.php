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
 * Database upgrades for the mentor_core local.
 *
 * @package   local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author    Nabil HAMDI <nabil.hamdi@edunao.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

/**
 * Upgrade the local_trainings database.
 *
 * @param int $oldversion The version number of the plugin that was installed.
 * @return boolean
 * @throws ddl_exception
 * @throws ddl_table_missing_exception
 * @throws dml_exception
 */
function xmldb_local_mentor_core_upgrade($oldversion) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/local/mentor_core/lib.php');
    require_once($CFG->libdir . '/db/upgradelib.php'); // Core Upgrade-related functions.

    if ($oldversion < 2021041302) {
        $liststatusnamechanges = local_mentor_core_get_list_status_name_changes();

        $trainings = $DB->get_records('training');

        foreach ($trainings as $training) {
            if (array_key_exists($training->status, $liststatusnamechanges)) {
                $training->status = $liststatusnamechanges[$training->status];
                $DB->update_record('training', $training);
            }
        }
    }

    $dbman = $DB->get_manager();

    if ($oldversion < 2021041900) {
        $trainingtable = new xmldb_table('training');

        // Training table fields.
        $trainingfields = [
            'traininggoal' => [XMLDB_TYPE_TEXT, '255', null, null, null],
            'thumbnail' => [XMLDB_TYPE_CHAR, '255', null, null, null],
        ];

        // Adding fields to database.
        foreach ($trainingfields as $name => $definition) {
            $trainingfield = new xmldb_field($name, $definition[0], $definition[1], $definition[2], $definition[3], $definition[4]);
            if (!$dbman->field_exists($trainingtable, $trainingfield)) {
                $dbman->add_field($trainingtable, $trainingfield);
            }
        }
    }
    if ($oldversion < 2022051100) {
        try {
            $DB->execute("UPDATE {session}
            SET courseshortname = REPLACE(courseshortname,:search,:replace)", [
                'search' => '&#39;',
                'replace' => "'",
            ]);
        } catch (\dml_exception $e) {
            mtrace('WARNING : Replace unicode to shortname in course to session!!!');
        }

        try {
            $DB->execute("UPDATE {course}
            SET shortname = REPLACE(shortname,:search,:replace)", [
                'search' => '&#39;',
                'replace' => "'",
            ]);
        } catch (\dml_exception $e) {
            mtrace('WARNING : Replace unicode to shortname in course!!!');
        }
    }

    if ($oldversion < 2023041900) {
        // Cohort admin native page error if idnumber is not set to cohort table.
        $DB->execute('
            UPDATE {cohort}
            SET idnumber = \'\'
            WHERE idnumber IS null
        ');
    }

    if ($oldversion < 2023101900) {
        // Define table to store user course completion.
        $table = new xmldb_table('user_completion');

        // Adding fields to table user_completion.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('completion', XMLDB_TYPE_INTEGER, '3', null, null, null, 0);
        $table->add_field('lastupdate', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table user_completion.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_mdl_user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('fk_mdl_course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

        // Adding indexes to table user_completion.
        $table->add_index('user-course', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);
        $table->add_index('user-course-completion', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'completion']);

        // Conditionally launch create table for user_completion.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
    }

    if ($oldversion < 2023101901) {
        // Define table to store user course completion.
        $table = new xmldb_table('user_completion');

        // Adding fields to table user_completion.
        $lastupdatefield = new xmldb_field('lastupdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        if (!$dbman->field_exists($table, $lastupdatefield)) {
            $dbman->add_field($table, $lastupdatefield);
        }

        // Adding indexes to table user_completion.
        $usercourseindex = new xmldb_index('user-course', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        if (!$dbman->index_exists($table, $usercourseindex)) {
            $dbman->add_index($table, $usercourseindex);
        }
    }

    return true;
}
