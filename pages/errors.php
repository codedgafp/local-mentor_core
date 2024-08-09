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
 * Display database errors
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config.php.
require_once('../../../config.php');

// Require login.
require_login();

if (!is_siteadmin()) {
    throw new \moodle_exception('Permission denied');
}

// Delete session.
$deletesession = optional_param('deletesession', null, PARAM_INT);

if (!is_null($deletesession)) {
    $DB->delete_records('session', ['id' => $deletesession]);
}

// Delete training.
$deletetraining = optional_param('deletetraining', null, PARAM_INT);

if (!is_null($deletetraining)) {
    $DB->delete_records('training', ['id' => $deletetraining]);
}

// Delete course.
$deletecourse = optional_param('deletecourse', null, PARAM_INT);

if (!is_null($deletecourse)) {
    delete_course($deletecourse, false);
}

$url = new moodle_url('/local/mentor_core/pages/errors.php');

// Sessions sans formation.

$orphansessions = $DB->get_records_sql('
    SELECT s.*
    FROM {session} s
    LEFT OUTER JOIN {training} t ON s.trainingid = t.id
    WHERE t.id is null
');

echo '<h2>Sessions sans formation (' . count($orphansessions) . ')</h2>';

if (!empty($orphansessions)) {
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>id</th>';
    echo '<th>courseshortname</th>';
    echo '<th>trainingid</th>';
    echo '<th>trainingname</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($orphansessions as $orphansession) {
        echo '<tr>';
        echo '<td>' . $orphansession->id . '</td>';
        echo '<td>' . $orphansession->courseshortname . '</td>';
        echo '<td>' . $orphansession->trainingid . '</td>';
        echo '<td>' . $orphansession->trainingname . '</td>';
        echo '<td><a href="' . $url . '?deletesession=' . $orphansession->id . '">Supprimer</a></td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}

// Session sans cours.

$orphansessioncourses = $DB->get_records_sql('
    SELECT s.*
    FROM {session} s
    LEFT OUTER JOIN {course} c ON s.courseshortname = c.shortname
    WHERE c.id is null
');

echo '<h2>Sessions sans cours (' . count($orphansessioncourses) . ')</h2>';

if (!empty($orphansessioncourses)) {
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>id</th>';
    echo '<th>courseshortname</th>';
    echo '<th>trainingid</th>';
    echo '<th>trainingname</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($orphansessioncourses as $orphansession) {
        echo '<tr>';
        echo '<td>' . $orphansession->id . '</td>';
        echo '<td>' . $orphansession->courseshortname . '</td>';
        echo '<td>' . $orphansession->trainingid . '</td>';
        echo '<td>' . $orphansession->trainingname . '</td>';
        echo '<td><a href="' . $url . '?deletesession=' . $orphansession->id . '">Supprimer</a></td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}

// Formation sans cours.

$orphantrainingcourses = $DB->get_records_sql('
    SELECT t.*
    FROM {training} t
    LEFT OUTER JOIN {course} c ON t.courseshortname = c.shortname
    WHERE c.id is null
');

echo '<h2>Formations sans cours (' . count($orphantrainingcourses) . ')</h2>';

if (!empty($orphantrainingcourses)) {
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>id</th>';
    echo '<th>courseshortname</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($orphantrainingcourses as $orphantraining) {
        echo '<tr>';
        echo '<td>' . $orphantraining->id . '</td>';
        echo '<td>' . $orphantraining->courseshortname . '</td>';
        echo '<td><a href="' . $url . '?deletetraining=' . $orphantraining->id . '">Supprimer</a></td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}

// Cours orphelins.

$orphancourses = $DB->get_records_sql("
    SELECT c.*
    FROM {course} c
    LEFT OUTER JOIN {session} s ON s.courseshortname = c.shortname
    LEFT OUTER JOIN {training} t ON t.courseshortname = c.shortname
    WHERE
        s.id is null AND
        t.id is NULL AND
        c.format != 'singleactivity' AND
        c.format != 'edadmin' AND
        c.format != 'site'");

echo '<h2>Cours orphelins (' . count($orphancourses) . ')</h2>';

if (!empty($orphancourses)) {
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>id</th>';
    echo '<th>shortname</th>';
    echo '<th>fullname</th>';
    echo '<th>format</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    foreach ($orphancourses as $orphancourse) {
        echo '<tr>';
        echo '<td><a href="' . $CFG->wwwroot . '/course/view.php?id=' . $orphancourse->id . '" target="_blank" rel="opener">' .
             $orphancourse->id . '</a></td>';
        echo '<td>' . $orphancourse->shortname . '</td>';
        echo '<td>' . $orphancourse->fullname . '</td>';
        echo '<td>' . $orphancourse->format . '</td>';
        echo '<td><a href="' . $url . '?deletecourse=' . $orphancourse->id . '">Supprimer</a></td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}
