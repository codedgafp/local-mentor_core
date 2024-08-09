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
 * Redirect the scorm
 *
 * @package    local_mentor_core
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config.php.
require_once('../../../config.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

$cmid = required_param('cmid', PARAM_INT);

$context = context_module::instance($cmid);

$PAGE->set_context($context);

$coursemodule = get_coursemodule_from_id('scorm', $cmid);

require_login($coursemodule->course, false, $coursemodule);

$dbparams = ['id' => $coursemodule->instance];
$fields = 'id, name, intro, introformat, completionstatusrequired, completionscorerequired, completionstatusallscos, ' .
          'timeopen, timeclose';
if (!$scorm = $DB->get_record('scorm', $dbparams, $fields)) {
    return false;
}

$result = new cached_cm_info();

$result->name = $scorm->name;

if ($coursemodule->showdescription) {
    // Convert intro to html. Do not filter cached version, filters run at display time.
    $result->content = format_module_intro('scorm', $scorm, $coursemodule->id, false);
}

// Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
    $result->customdata['customcompletionrules']['completionstatusrequired'] = $scorm->completionstatusrequired;
    $result->customdata['customcompletionrules']['completionscorerequired'] = $scorm->completionscorerequired;
    $result->customdata['customcompletionrules']['completionstatusallscos'] = $scorm->completionstatusallscos;
}
// Populate some other values that can be used in calendar or on dashboard.
if ($scorm->timeopen) {
    $result->customdata['timeopen'] = $scorm->timeopen;
}
if ($scorm->timeclose) {
    $result->customdata['timeclose'] = $scorm->timeclose;
}

// Patch Edunao Mentor => Open the scorm actity into a new tab.
$scorm = $DB->get_record('scorm', ['id' => $scorm->id]);

$context = context_course::instance($coursemodule->course);

if ($scorm->popup == 1 && !has_capability('moodle/course:manageactivities', $context) &&
    !has_capability('mod/scorm:viewreport', $context)) {

    require_once($CFG->dirroot . '/mod/scorm/locallib.php');

    $organization = $scorm->launch;
    $orgidentifier = '';

    if ($sco = scorm_get_sco($organization, SCO_ONLY)) {
        if (($sco->organization == '') && ($sco->launch == '')) {
            $orgidentifier = $sco->identifier;
        } else {
            $orgidentifier = $sco->organization;
        }
    }

    $toc = scorm_get_toc($USER, $scorm, $coursemodule->id, TOCFULLURL, $orgidentifier);

    // Get latest incomplete sco to launch first if force new attempt isn't set to always.
    if (!empty($toc->sco->id) && $scorm->forcenewattempt != SCORM_FORCEATTEMPT_ALWAYS) {
        $launchsco = $toc->sco->id;
    } else {
        // Use launch defined by SCORM package.
        $launchsco = $scorm->launch;
    }

    $url = new moodle_url($CFG->wwwroot . '/mod/scorm/player.php', [
        'a' => $scorm->id,
        'scoid' => $launchsco,
        'sesskey' => sesskey(),
        'display' => 'popup',
        'normal' => 'normal',
    ]);

    redirect($url->out());
} else {
    redirect($CFG->wwwroot . '/mod/scorm/view.php?id=' . $cmid);
}

