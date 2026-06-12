<?php

/**
 * Ad hoc task for creating a session
 *
 * @package    local_mentor_core
 * @author     Alban <alban.ploquin@cgi.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core\task;

use block_completion_monitor\service\completion_activities_service;

class update_users_course_completion extends \core\task\scheduled_task
{
    use \core\task\logging_trait;

    public function get_name(): string
    {
        return get_string('task_update_users_course_completion', 'local_mentor_core');
    }

    public function execute(): void
    {
        global $CFG, $DB;

        $mcdatabaseinterface = new \local_mentor_core\database_interface();

        $lastrows = 0;
        $totalprocessed = 0;

        $countusercompletion = count($mcdatabaseinterface->get_last_users_completions($lastrows, true));

        if ($countusercompletion == 0) {
            $this->log("Aucun enregistrement à traiter.");
            return;
        }

        $this->log("Nombre total d'enregistrements à traiter : $countusercompletion");

        $iterations = ceil($countusercompletion / $CFG->completion_limit_result);

        $updates = [];

        for ($i = 0; $i < $iterations; $i++) {
            $userscompletionstoprocessed = $mcdatabaseinterface->get_last_users_completions($lastrows);

            foreach ($userscompletionstoprocessed as $usercompletion) {
                $userid = $usercompletion->userid;
                $courseid = $usercompletion->courseid;
                $uniquekey = $usercompletion->uniquekey;
                $actualcompletion = $usercompletion->completion ?? 0;

                $course = get_course($courseid);

                if (!$course || isset($updates[$uniquekey]))
                    continue;

                $completionservice = new completion_activities_service($course);

                // Get the new completion
                $newusercompletion = $completionservice->get_course_completion_details($userid)["percentage"];

                $updates[$uniquekey] = [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'oldcompletion' => $actualcompletion,
                    'completion' => $actualcompletion
                ];

                // Check if the completion need to be updated
                if ($actualcompletion != $newusercompletion) {
                    $updates[$uniquekey]['completion'] = $newusercompletion;

                    $this->log("Le cours [id: {$updates[$uniquekey]['courseid']}] pour l'utilisateur [id: {$updates[$uniquekey]['userid']}] voit sa complétion mise à jour : {$updates[$uniquekey]['oldcompletion']} => {$updates[$uniquekey]['completion']}");
                }
            }

            if (!empty($updates)) {
                foreach ($updates as $update) {
                    $mcdatabaseinterface->set_user_course_completion(
                        $update['userid'],
                        $update['courseid'],
                        $update['completion'],
                    );
                }
            }

            $lastrows += $CFG->completion_limit_result;
            $totalprocessed += count($userscompletionstoprocessed);

            $progress = round(($i + 1) / $iterations * 100, 2);
            $this->log("Progression : $progress% | Itération " . ($i + 1) . "/$iterations | Total traité : $totalprocessed | Complétions mises à jour : " . count($updates));

            // Libération mémoire
            unset($userscompletionstoprocessed);
            unset($updates);
        }
    }
}
