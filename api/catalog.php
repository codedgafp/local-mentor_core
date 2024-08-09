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
 * Class catalog_api
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Nabil Hamdi <nabil.hamdi@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/mentor_core/classes/database_interface.php');
require_once($CFG->libdir . '/licenselib.php');

/**
 * catalog API
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     Nabil Hamdi <nabil.hamdi@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog_api {

    /**
     * Get parameters used by the catalog renderer
     *
     * @return \stdClass
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_params_renderer() {
        global $USER;

        $specialization = specialization::get_instance();

        // Get the specialization of the params renderer catalog object.
        $paramsrenderer = $specialization->get_specialization('get_params_renderer_catalog', new \stdClass());

        if ($paramsrenderer == new \stdClass()) {
            // Get trainings with its sessions for the current user.
            $trainings = \local_mentor_core\training_api::get_user_available_sessions_by_trainings($USER->id, true);

            // Fill entities and collections list.
            $entities = [];

            foreach ($trainings as $idx => $training) {
                if ('' !== $training->entityname) {
                    $entities[$training->entityname] = [
                        'id' => $training->entityid,
                        'name' => $training->entityname,
                    ];
                }
            }

            // Entities list.
            uksort($entities, 'strcasecmp');
            $paramsrenderer->entities = array_values($entities);

            // Trainings list.
            $paramsrenderer->trainings = array_values($trainings);
            $paramsrenderer->trainingscount = count($trainings);

            // Json encode amd data.
            $paramsrenderer->available_trainings = json_encode($trainings);
        }

        return $paramsrenderer;
    }

    /**
     * Get all training session for the template catalog
     *
     * @param int $trainingid
     * @return \stdClass[]|bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_sessions_template_by_training($trainingid) {
        global $USER;

        if ($userprofile = \local_mentor_core\profile_api::get_profile($USER->id)) {
            $mainentity = $userprofile->get_main_entity();
        }

        // Get all training sessions.
        $sessions = \local_mentor_core\session_api::get_sessions_by_training($trainingid, 'sessionstartdate');

        // Get status condition for available sessions.
        $avaiblestatus = [
            session::STATUS_OPENED_REGISTRATION,
            session::STATUS_IN_PROGRESS,
        ];

        // Init template data.
        $sessionstemplate = [];

        foreach ($sessions as $session) {

            // Check if the session is visible.
            if ($session->opento == session::OPEN_TO_NOT_VISIBLE) {
                continue;
            }

            // Check status and access session for the template.
            if (!in_array($session->status, $avaiblestatus)) {
                continue;
            }

            // Check if the sessions is available for user.
            if ($session->is_available_to_user($USER->id)) {
                $sessionstemplate[] = $session->convert_for_template();
            }

        }

        // User does not have access to the catalog.
        if (!count($sessionstemplate)) {
            return false;
        }

        return $sessionstemplate;
    }

    /**
     * Get the specialization of the catalog template
     *
     * @param string $defaulttemplate
     * @return mixed
     */
    public static function get_catalog_template($defaulttemplate) {
        $specialization = specialization::get_instance();
        return $specialization->get_specialization('get_catalog_template', $defaulttemplate);
    }

    /**
     * Get the specialization of the training catalog template
     *
     * @param string $defaulttemplate
     * @return mixed
     */
    public static function get_training_template($defaulttemplate) {
        $specialization = specialization::get_instance();
        return $specialization->get_specialization('get_training_template', $defaulttemplate);
    }

    /**
     * Get the specialization of the catalog javascript
     *
     * @param string $defaultjs
     * @return mixed
     */
    public static function get_catalog_javascript($defaultjs) {
        $specialization = specialization::get_instance();
        return $specialization->get_specialization('get_catalog_javascript', $defaultjs);
    }
}
