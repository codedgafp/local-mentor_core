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
        global $DB;

        $taskdata = \core\task\manager::get_scheduled_task(self::class);
        $tasklastruntime = $taskdata->get_last_run_time();

        $mcdatabaseinterface = new \local_mentor_core\database_interface();

        $coursemodulescompleted = $mcdatabaseinterface->get_last_course_modules_completions($tasklastruntime);        
        $coursearray = [];

        foreach ($coursemodulescompleted as $coursemodule) {
            if (!isset($coursearray[$coursemodule->course])) {
                $coursearray[$coursemodule->course] = $DB->get_record('course', ['id' => $coursemodule->course]);
            }

            $userid = $coursemodule->userid;
            $course = $coursearray[$coursemodule->course];
            $courseid = $course->id;

            $newusercompletion = local_mentor_core_calculate_completion_get_progress_percentage($course, $userid);

            $usercompletion = $DB->get_record('user_completion', ['userid' => $userid, 'courseid' => $courseid]);

            if ($usercompletion->completion != $newusercompletion) {
                $this->log("Le cours [id: $courseid] voit sa complétion mise à jour : $usercompletion->completion => $newusercompletion");
                $mcdatabaseinterface->set_user_course_completion($userid, $courseid, $newusercompletion);
            }
        }
    }
}
