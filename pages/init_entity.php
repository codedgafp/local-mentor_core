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
 * Admin page to set training status to elaboration_completed
 * IMPORTANT :Use this page only for perf testing
 *
 * @package    local_mentor_core
 * @copyright  2023 Edunao SAS (contact@edunao.com)
 * @author     rémi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config.php.
require_once('../../../config.php');
require_once($CFG->dirroot . '/local/mentor_core/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/page/mod_form.php');

// Require login.
require_login();

if (!is_siteadmin()) {
    throw new \moodle_exception('Permission denied');
}

$entityname = required_param('entityname', PARAM_RAW);

try {
    $entity = \local_mentor_core\entity_api::get_entity_by_name($entityname);
} catch (Exception $e) {
    exit('Training not found');
}

// Init presentation page.
if (!$entity->get_presentation_page_course()) {
    mtrace("Init presentation page : Start");
    if ($entity->create_presentation_page()) {
        mtrace("Init presentation page : Ok");
    } else {
        mtrace("Init presentation page : Not Ok");
    }
    mtrace("Init presentation page : End");
}

// Init contact page.
if (!$entity->contact_page_is_initialized()) {
    mtrace("Init presentation page : Start");

    $contacpage = $entity->get_contact_page_course();
    require_login($contacpage);

    list($module, $context, $cw, $cm, $data) = prepare_new_moduleinfo_data($contacpage, 'page', 0);
    $module->course = $contacpage->id;
    $data->name = $entityname . ' - Page de contacte';
    $data->type = 'text/html';
    $data->content = '<p dir="ltr" style="text-align: left;">' . $entityname . ' - Page de contacte</p>';
    $data->modulename = 'page';
    $data->availabilityconditionsjson = '{"op":"&","c":[],"showc":[]}';
    $data->printlastmodified = 1;
    $data->display = 5;
    $data->printintro = 0;
    add_moduleinfo($data, $contacpage);

    mtrace("Init presentation page : End");
}

// Init logo.
$picture = $entity->get_logo();
if (!$picture) {
    mtrace("Init logo : Start");
    $fs = get_file_storage();

    $filename = 'default_thumbnail.jpg';

    $filerecord = [
        'contextid' => $entity->get_context()->id,
        'component' => 'local_entities',
        'filearea' => 'logo',
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $filename,
    ];
    $pathname = $CFG->dirroot . '/local/mentor_core/pix/' . $filename;

    $fs->create_file_from_pathname($filerecord, $pathname);
    mtrace("Init logo : End");
}

mtrace('success');

