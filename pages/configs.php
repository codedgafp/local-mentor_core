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
 * Display Mentor config
 *
 * @package    local_mentor_core
 * @copyright  2024 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config.php.
require_once('../../../config.php');

// Require login.
require_login();

if (!is_siteadmin()) {
    throw new \moodle_exception('Permission denied');
}

$moodleconfig = $DB->get_records('config');
$pluginsconfig = $DB->get_records('config_plugins');

$mergeconfig = array_merge($moodleconfig, $pluginsconfig);

header('Content-Type: application/csv');
header('Content-Disposition: attachment; filename="configs.csv";');
$f = fopen('php://output', 'w');

fputcsv($f, ['Nom', 'Plugin', 'Value'], ';');
$i = 1;
foreach ($mergeconfig as $config) {
    $line = [];
    $line[] = $config->name;
    if (isset($config->plugin)) {
        $line[] = $config->plugin;
    } else {
        $line[] = '';
    }
    $line[] = $config->value;
    fputcsv($f, $line, ';');
    $i++;
}
