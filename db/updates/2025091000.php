<?php

defined('MOODLE_INTERNAL') || die();

mtrace("Mise en attente de la tâche de reprise de données pour les liens course_sections");

$task = new \local_mentor_core\task\data_recovery_course_section_links();
\core\task\manager::queue_adhoc_task($task);

mtrace("Suppression du plugin 'adhoctaskqueue' s'il est installé");

$plugin = 'tool_adhoctasksqueue';
list($type, $name) = explode('_', $plugin, 2);

$plugininfo = \core_plugin_manager::instance()->get_plugin_info($plugin);
if ($plugininfo) {
    mtrace("Désinstallation automatique du plugin : $plugin");
    uninstall_plugin($type, $name);
} else {
    mtrace("Plugin $plugin non installé, rien à faire.");
}