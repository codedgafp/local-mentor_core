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
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config.php.
require_once('../../../config.php');
require_once($CFG->dirroot . '/local/mentor_core/lib.php');

// Require login.
require_login();

if (!is_siteadmin()) {
    throw new \moodle_exception('Permission denied');
}

$trainingid = required_param('id', PARAM_INT);

try {
    $training = \local_mentor_core\training_api::get_training($trainingid);
} catch (Exception $e) {
    exit('Training not found');
}

$picture = $training->get_training_picture();

if (!$picture) {
    $fs = get_file_storage();

    $filename = 'default_thumbnail.jpg';

    $filerecord = [
        'contextid' => $training->get_context()->id,
        'component' => 'local_trainings',
        'filearea' => 'thumbnail',
        'itemid' => $training->id,
        'filepath' => '/',
        'filename' => $filename,
    ];
    $pathname = $CFG->dirroot . '/local/mentor_core/pix/' . $filename;

    $fs->create_file_from_pathname($filerecord, $pathname);
}

$data = new stdClass();
$data->id = $training->id;
$data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
$data->content = ['text' => 'content', 'format' => 1];
$data->traininggoal = ['text' => 'traininggoal', 'format' => 1];
$data->collection = [0 => 'achat'];
$data->trainingmodalities = 'p';

\local_mentor_core\training_api::update_training($data);

echo 'success';

