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
 * Automatically cleanup orphans trainings and sessions
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core\task;

class cleanup_trainings_and_sessions extends \core\task\scheduled_task {

    public function get_name() {
        // Shown in admin screens.
        return get_string('task_cleanup_trainings_and_sessions', 'local_mentor_core');
    }

    public function execute() {
        global $DB;

        $trainings = $DB->get_records_sql('
            SELECT t.*
            FROM {training} t
            WHERE
                t.courseshortname NOT IN (
                    SELECT DISTINCT shortname
                    FROM {course} c
                )
                AND
                t.courseshortname NOT IN (
                    SELECT DISTINCT shortname
                    FROM {tool_recyclebin_category}
                )
        ');

        foreach ($trainings as $training) {
            $DB->delete_records('training', ['id' => $training->id]);
            mtrace('Delete training id : ' . $training->id . ' and shortname : ' . $training->courseshortname);
        }

        $sessions = $DB->get_records_sql('
            SELECT s.*
            FROM {session} s
            WHERE
                s.courseshortname NOT IN (
                    SELECT DISTINCT shortname
                    FROM {course} c
                )
                AND
                s.courseshortname NOT IN (
                    SELECT DISTINCT shortname
                    FROM {tool_recyclebin_category}
                )
        ');

        foreach ($sessions as $session) {
            $DB->delete_records('session', ['id' => $session->id]);
            mtrace('Delete session id : ' . $session->id . ' and shortname : ' . $session->courseshortname);
        }

    }
}
