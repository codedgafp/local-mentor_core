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
 * Database install for the mentor_core local.
 *
 * @return bool
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author    Rémi Colet <remi.colet@edunao.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL
 *
 * /**
 * Database install
 *
 * @package   local_mentor_core
 */
function xmldb_local_mentor_core_install() {
    global $CFG;

    require_once($CFG->dirroot . '/local/mentor_core/lib.php');

    // Save specialisations config.
    $lcfgspecializations = isset($CFG->mentor_specializations) ? $CFG->mentor_specializations : [];

    // Reset specializations config to call the generic functions during mentor_core installation.
    $CFG->mentor_specializations = [];

    // Redefined the configuration of the specializations with the basic configuration.
    $CFG->mentor_specializations = $lcfgspecializations;

    return true;
}
