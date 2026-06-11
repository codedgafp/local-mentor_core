<?php

defined('MOODLE_INTERNAL') || die();

global $DB;

$dbman = $DB->get_manager();

$table = new xmldb_table('user_completion');
$newfield = new xmldb_field('processed', XMLDB_TYPE_INTEGER, '1', null, false, false, 0);

if (!$dbman->field_exists($table, $newfield)) {
    $dbman->add_field($table, $newfield);
}
