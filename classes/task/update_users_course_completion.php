<?php

/**
 * Ad hoc task for creating a session
 *
 * @package    local_mentor_core
 * @author     Alban <alban.ploquin@cgi.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core\task;

class update_users_course_completion extends \core\task\scheduled_task
{
    use \core\task\logging_trait;

    public function get_name(): string
    {
        return get_string('task_update_users_course_completion', 'local_mentor_core');
    }

    public function execute(): void
    {
        global $DB, $CFG;

        $taskdata = \core\task\manager::get_scheduled_task(self::class);
        $tasklastruntime = $taskdata->get_last_run_time();

        $mcdatabaseinterface = new \local_mentor_core\database_interface();

        $coursearray = [];
        $courseidscache = [];
        $lastrows = 0;
        $totalprocessed = 0;

        $countcoursemodulescompleted = count($mcdatabaseinterface->get_last_course_modules_completions($tasklastruntime, $lastrows, true));

        if ($countcoursemodulescompleted == 0) {
            $this->log("Aucun enregistrement à traiter.");
            return;
        }

        $this->log("Nombre total d'enregistrements à traiter : $countcoursemodulescompleted");

        $iterations = ceil($countcoursemodulescompleted / $CFG->completion_limit_result);

        for ($i = 0; $i < $iterations; $i++) {
            // Get a batch of "course_modules_completions" determined by the limit
            $coursemodulescompleted = $mcdatabaseinterface->get_last_course_modules_completions($tasklastruntime, $lastrows);

            // Get unique ids from courses and users (to avoids making new requests for the same values)
            $courseids = [];
            $userids = [];
            $newcourseids = [];

            foreach ($coursemodulescompleted as $coursemodule) {
                if (!isset($courseidscache[$coursemodule->course])) {
                    $courseidscache[$coursemodule->course] = true;
                    $newcourseids[$coursemodule->course] = $coursemodule->course;
                }

                $courseids[$coursemodule->course] = $coursemodule->course;
                $userids[$coursemodule->userid] = $coursemodule->userid;
            }

            // Get all courses we found in "course_modules_completions"
            if (!empty($newcourseids)) {
                $newcourses = $DB->get_records_list('course', 'id', array_values($newcourseids));
                foreach ($newcourses as $newcourse) {
                    $coursearray[$newcourse->id] = $newcourse;
                }
            }

            // Get all "user_completions" with one request
            [$inuseridssql, $useridsparams] = $DB->get_in_or_equal(array_values($userids), SQL_PARAMS_NAMED);
            [$incourseidssql, $courseidsparams] = $DB->get_in_or_equal(array_values($courseids), SQL_PARAMS_NAMED);
            $params = array_merge($useridsparams, $courseidsparams);

            $sql = "SELECT CONCAT(userid, '_', courseid) as uniquekey, completion
                    FROM {user_completion}
                    WHERE userid $inuseridssql
                    AND courseid $incourseidssql
                    ";

            $usercompletions = $DB->get_records_sql($sql, $params);

            $updates = [];

            foreach ($coursemodulescompleted as $coursemodule) {
                $userid = $coursemodule->userid;
                $courseid = $coursemodule->course;
                $uniquekey = $userid . '_' . $courseid;

                $course = $coursearray[$courseid] ?? null;

                if (!$course || isset($updates[$uniquekey]))
                    continue;

                // Get the new completion
                $newusercompletion = local_mentor_core_calculate_completion_get_progress_percentage($course, $userid);

                $usercompletion = $usercompletions[$uniquekey] ?? null;

                // Check if the completion need to be updated
                if ($usercompletion && $usercompletion->completion != $newusercompletion) {
                    $updates[$uniquekey] = [
                        'userid' => $userid,
                        'courseid' => $courseid,
                        'old' => $usercompletion->completion,
                        'new' => $newusercompletion
                    ];
                }
            }

            // Update all the completions that need it
            if (!empty($updates)) {
                foreach ($updates as $update) {
                    $this->log("Le cours [id: {$update['courseid']}] pour l'utilisateur [id: {$update['userid']}] voit sa complétion mise à jour : {$update['old']} => {$update['new']}");
                    $mcdatabaseinterface->set_user_course_completion(
                        $update['userid'],
                        $update['courseid'],
                        $update['new']
                    );
                }
            }

            $lastrows += $CFG->completion_limit_result;
            $totalprocessed += count($coursemodulescompleted);

            $progress = round(($i + 1) / $iterations * 100, 2);
            $this->log("Progression : $progress% | Itération " . ($i + 1) . "/$iterations | Total traité : $totalprocessed | Complétions mises à jour : " . count($updates));

            // Libération mémoire
            unset($coursemodulescompleted);
            unset($usercompletions);
            unset($updates);
        }

        $this->log("Traitement terminé : $totalprocessed/$countcoursemodulescompleted enregistrements traités");
    }
}
