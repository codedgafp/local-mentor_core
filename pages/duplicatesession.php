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
 * Duplicate session content into training
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config.php.
require_once('../../../config.php');

// Includes.
require_once($CFG->dirroot . '/local/mentor_core/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/forms/duplicate_session_form.php');

// Require login.
require_login();

$sessionid = required_param('sessionid', PARAM_INT);

$session = \local_mentor_core\session_api::get_session($sessionid);

$context = $session->get_context();

require_capability('local/mentor_core:duplicatesessionintotraining', $context);

$url = new moodle_url('/local/mentor_core/pages/duplicatesession.php', ['sessionid' => $sessionid]);
$title = get_string('duplicatesessionintotrainingtitle', 'local_mentor_core', $session->fullname);

// Set page config.
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_url($url);
$PAGE->set_course($session->get_course());

$PAGE->requires->strings_for_js(
    [
        'confirmation',
        'confirmationwarnining',
        'confirmduplication',
        'close',
    ],
    'local_mentor_core'
);

$PAGE->requires->js_call_amd('local_mentor_core/duplicatesession', 'init');

// Get user entities.
$entities = \local_mentor_core\entity_api::get_entities_where_trainings_managed();

$entitiesjs = [];

$entitieslist = [];

$isadmin = is_siteadmin();

// Get available entities.
foreach ($entities as $entity) {
    $entitieslist[$entity->id] = $entity->shortname;

    $entitiesjs[$entity->id] = [];

    $subentities = $entity->get_sub_entities();

    if (count($subentities) > 0 && $entity->is_trainings_manager($USER, false)) {
        $entitiesjs[$entity->id][] = ['id' => 0, 'name' => 'Aucun'];
    }

    foreach ($subentities as $subentity) {
        if ($isadmin || $subentity->is_trainings_manager($USER)) {
            $entitiesjs[$entity->id][] = ['id' => $subentity->id, 'name' => $subentity->name];
        }
    }
}

$form = new \local_mentor_core\duplicate_session_form(null,
    ['sessionid' => $sessionid, 'entitieslist' => $entitieslist, 'entitiesjs' => $entitiesjs]);

// Get next available index.
$nextindex = \local_mentor_core\session_api::get_next_available_shortname_index($session->shortname);
$nextshortname = $session->shortname . ' ' . $nextindex;
$nextfullname = $session->fullname . ' ' . $nextindex;

// Set form data.
$form->set_data(['sessionid' => $sessionid, 'fullname' => $nextfullname, 'shortname' => $nextshortname]);

if ($form->is_cancelled()) {
    redirect($CFG->wwwroot . '/course/view.php?id=' . $session->get_course()->id);
} else if ($form->is_submitted() && $form->is_validated()) {
    // Manage form submission.
    $data = $form->get_data();

    // Duplicate as a new training.
    if ($data->duplicationtype == 0) {
        $entityid = isset($data->subentityid) && !empty($data->subentityid) && $data->subentityid != 0 ? $data->subentityid :
            $data->entityid;

        $training = \local_mentor_core\session_api::duplicate_session_as_new_training($data->sessionid, $data->fullname,
            $data->shortname,
            $entityid);

    } else if ($data->duplicationtype == 1) {
        // Duplicate into the session training.
        \local_mentor_core\session_api::duplicate_session_into_training($sessionid);
    }

    // Get next available index.
    $nextindex = \local_mentor_core\session_api::get_next_available_shortname_index($session->shortname);
    $nextshortname = $session->shortname . ' ' . $nextindex;
    $nextfullname = $session->fullname . ' ' . $nextindex;

    $form->set_value('shortname', $nextshortname);
    $form->set_value('fullname', $nextfullname);

    $PAGE->requires->js_call_amd(
        'local_mentor_core/duplicatesession',
        'duplicateSuccess',
        ['sessionid' => $session->get_course()->id]
    );
}

// Display the form.
echo $OUTPUT->header();

// Display the form.
$form->display();

// Add the entities list into the page to get it in javascript.
echo '<div id="entitieslist" style="display: none;">' . json_encode($entitiesjs) . '</div>';

echo $OUTPUT->footer();


