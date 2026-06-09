<?php
#MEN-1169 - Script to remove "utilisateurexterne" in mentorRole profile field.

defined('MOODLE_INTERNAL') || die();

mtrace("Mise en attente de la tâche de suppression du rôle 'utilisateurexterne'");

$task = new \local_mentor_core\task\remove_utilisateurexterne_role();
\core\task\manager::queue_adhoc_task($task);
