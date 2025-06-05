<?php

defined('MOODLE_INTERNAL') || die();

$pluginname = 'filter_mathjaxloader';

$pluginman = core_plugin_manager::instance();
$pluginfo = $pluginman->get_plugin_info($pluginname);

if (!$pluginfo) exit;

$progress = new null_progress_trace();
core_plugin_manager::instance()->uninstall_plugin($pluginname, $progress);