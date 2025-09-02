<?php

defined('MOODLE_INTERNAL') || die();

mtrace("Mise en attente de la tâche de reprise de données pour les liens course_sections");

$task = new \local_mentor_core\task\data_recovery_course_section_links();
\core\task\manager::queue_adhoc_task($task);