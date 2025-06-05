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
 * Create/update accounts and enrol a list of users for the given course
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     mounir <mounir.ganem@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config.php.
require_once('../../../config.php');

// Includes.
require_once($CFG->dirroot . '/local/mentor_core/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/forms/importcsv_form.php');
require_once($CFG->dirroot . '/local/mentor_core/forms/importusers_form.php');

// Raise time and memory limit.
core_php_time_limit::raise(60 * 60);
raise_memory_limit(MEMORY_HUGE);

// Require login.
require_login();

// System context.
$systemcontext = context_system::instance();

// Require course id.
$courseid = required_param('courseid', PARAM_INT);

// Check if course is session.
if (!$session = \local_mentor_core\session_api::get_session_by_course_id($courseid)) {
    throw new \moodle_exception('Permission denied');
}

// Check session status.
if ($session->status === \local_mentor_core\session::STATUS_CANCELLED ||
    $session->status === \local_mentor_core\session::STATUS_ARCHIVED ||
    $session->status == \local_mentor_core\session::STATUS_COMPLETED) {
    throw new \moodle_exception('notacessimportcsv', 'local_entities');
}

// Course context.
$course = get_course($courseid);
$coursecontext = context_course::instance($courseid);

// Check capabilities.
require_capability('local/mentor_core:importusers', $coursecontext);

// Settings first element page.
$PAGE->set_url('/local/mentor_core/pages/importcsv.php', ['courseid' => $courseid]);
$PAGE->set_context($coursecontext);
$PAGE->set_course($course);
$PAGE->set_title(get_string('enrolusers', 'local_mentor_core'));
$PAGE->set_heading($course->fullname);

$params = [
    'modaltitle' => get_string('import_modal_title', 'local_mentor_core'),
    'modalcontent' => get_string('import_modal_content', 'local_mentor_core'),
];

// Require JS.
$PAGE->requires->js_call_amd('local_mentor_core/importcsv', 'init', $params);

// Output content.
$out = '';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('import_and_enrol_heading', 'local_mentor_core'));

// Anchor directly to the import report.
$anchorurl = new moodle_url('/local/mentor_core/pages/importcsv.php', ['courseid' => $courseid], 'import-reports');

// Import CSV form.
$csvmform = new importcsv_form($anchorurl->out(), []);
$csvformdata = $csvmform->get_data();

// Import users form.
$importusersform = new importusers_form([],
    new moodle_url('/local/mentor_core/pages/importcsv.php', ['courseid' => $courseid]));
$importusersformdata = $importusersform->get_data();

// Import users with validated data.
if (null !== $importusersformdata) {
    // List of users to import.
    $users = json_decode($importusersformdata->users, true);

    // List of users to reactivate.
    $userstoreactivate = json_decode($importusersformdata->userstoreactivate, true);

    // Create enrol users task
    $adhoctask = new \local_mentor_core\task\enrol_users_csv();

    // Set data
    $adhoctask->set_custom_data([
        "courseid" => $courseid,
        "users" => $users,
        "userstoreactivate" => $userstoreactivate,
        "csvcontent" => $_SESSION['import_csv_raw_content_session'] ?? '',
        "delimiter_name" => $_SESSION['import_csv_delimiter_name_session'] ?? '',
        "filename" => $_SESSION['import_csv_filename_session'] ?? '',
        "user_id" => $USER->id
    ]);

        // Cleanup after use
    unset($_SESSION['import_csv_raw_content_session']); 
    unset($_SESSION['import_csv_delimiter_name_session']); 
    unset($_SESSION['import_csv_filename_session']); 

    // Prepare import users task.
    \core\task\manager::queue_adhoc_task($adhoctask);

    redirect(
        $CFG->wwwroot . "/local/mentor_core/pages/importcsv.php?courseid=$courseid",
        get_string('import_inprogress', 'local_mentor_core'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Validate given data from CSV.
if (null !== $csvformdata) {
    $out .= $csvmform->render();

    echo html_writer::div($out);
    $out = '';

    // Convert line breaks into standard line breaks.
    $filecontent = local_mentor_core_decode_csv_content($csvmform->get_file_content('userscsv'));

    // Check if file is valid UTF-8.
    if (false === mb_detect_encoding($filecontent, 'UTF-8', false)) {
        \core\notification::error(get_string('error_encoding', 'local_mentor_core'));
    } else {
        // Convert lines into array.
        $content = str_getcsv($filecontent, "\n");

        $out .= $OUTPUT->heading(get_string('import_and_enrol_heading', 'local_mentor_core'));

        // Get the uploaded file name from the filepicker element.
        $draftitemid = $csvmform->get_data()->userscsv;
        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id DESC', false);
        $filename = '';
        if ($files) {
            $file = reset($files);
            $filename = $file->get_filename();
        }
        if (!empty($content)) {
            $_SESSION['import_csv_raw_content_session'] = $content;
        }
        $_SESSION['import_csv_delimiter_name_session'] = $csvformdata->delimiter_name;
        $_SESSION['import_csv_filename_session'] = $filename;

        // Errors array.
        $errors = [
            'list' => [], // Errors list.
        ];

        // Errors array.
        $warnings = [
            'list' => [], // Errors list.
            'groupsnotfound' => [], // List of not found group name.
            'rolenotfound' => [], // List of not found role name.
            'loseprivilege' => [], // List of users who could have lost their privilege.
        ];

        // Preview array.
        $preview = [
            'list' => [], // Cleaned list of accounts.
            'validlines' => 0, // Number of lines without error.
            'validforcreation' => 0, // Number of lines that will create an account.
            'validforreactivation' => [], // Valid accounts for reactivation.
        ];

        // Build preview and errors array.
        $hasfatalerrors = local_mentor_core_validate_users_csv($content, $csvformdata->delimiter_name, $courseid, $preview,
            $errors, $warnings);

        // No fatal error, so we display the preview.
        if (false === $hasfatalerrors) {
            // Preview table.
            $out .= html_writer::tag('h5', get_string('preview_table', 'local_mentor_core'), ['class' => 'report-title']);

            // Building preview table.
            $previewstable = new html_table();
            $previewstable->head = [
                get_string('csv_line', 'local_mentor_core'),
                get_string('lastname', 'local_mentor_core'),
                get_string('firstname'),
                get_string('email'),
                get_string('role'),
                get_string('group'),
            ];
            $previewstable->data = array_slice($preview['list'], 0, 10);
            $out .= html_writer::table($previewstable);

            // Add validated data into the import users form.
            $importusersform = new importusers_form($preview['list'], $preview['validforreactivation'],
                new moodle_url('/local/mentor_core/pages/importcsv.php', [
                    'courseid' => $courseid,
                ]));
        }

        // Preview report.
        $out .= html_writer::tag('h5', get_string('preview_report', 'local_mentor_core'), ['class' => 'report-title']);

        $nberror = count($errors['list']);

        // Display the report.
        $out .= html_writer::alist([
            get_string('identified_users', 'local_mentor_core', $preview['validlines']),
            get_string('account_creation_number', 'local_mentor_core', $preview['validforcreation']),
            get_string('account_reactivation_number', 'local_mentor_core', count($preview['validforreactivation'])),
            get_string('errors_number', 'local_mentor_core', $nberror),
            get_string('warnings_number', 'local_mentor_core', count($warnings['list'])),
        ]);

        // Display import logs bloc.
        // Errors detected notification.
        if ($nberror !== 0) \core\notification::warning(get_string('errors_detected', 'local_mentor_core'));

        // Building logs report table.
        $logstable = new html_table();
        $logstable->head = ['Ligne', get_string('information', 'local_mentor_core')];
        $logstable->data = $errors['list'];
        $out .= html_writer::table($logstable);

        // Display warnings bloc.
        if (count($warnings['list']) !== 0) {
            // Errors detected notification.
            \core\notification::warning(get_string('warnings_detected', 'local_mentor_core'));

            // Building errors report table.
            $warningstable = new html_table();
            $warningstable->head = ['Ligne', get_string('warning', 'local_mentor_core')];
            $warningstable->data = $warnings['list'];
            $out .= html_writer::table($warningstable);
        }

        // Check available places.
        $session = \local_mentor_core\session_api::get_session_by_course_id($courseid);
        $availableplaces = $session->get_available_places();

        if (is_int($availableplaces)) {

            // Get the potential available places after the import.
            $nextavailableplaces = $session->count_potential_available_places($preview['list']);

            // If the next available places is negative, then display a warning message.
            if ($nextavailableplaces < 0) {
                // Display a warning.
                $out .= html_writer::tag('div', $OUTPUT->pix_icon('i/warning', get_string('warning')) .
                                                get_string(
                                                    'errors_nbusers',
                                                    'local_mentor_core',
                                                    abs($nextavailableplaces)),
                    ['id' => 'errors-nbusers']
                );
            }
        }

        // Display import users form.
        if (false === $hasfatalerrors) {
            $out .= $importusersform->render();
        }
    }
} else {
    $csvmform->set_data($csvformdata);
    $csvmform->display();
}

echo html_writer::div($out, 'local_mentor_core_report', ['id' => 'import-reports']);

echo $OUTPUT->footer();
