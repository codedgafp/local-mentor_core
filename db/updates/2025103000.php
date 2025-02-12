<?php

defined('MOODLE_INTERNAL') || die();

global $DB;

$table = new xmldb_table('course_categories_domains');

// Adding fields to table course_categories_domains.
$table->add_field('domain_name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
$table->add_field('course_categories_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
$table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
$table->add_field('disabled_at',XMLDB_TYPE_INTEGER, '10', null, null, null, null);

// Adding keys to table course_categories_domains.
$table->add_key('primary', XMLDB_KEY_PRIMARY, ['domain_name', 'course_categories_id']);
$table->add_key('fk_course_categories', XMLDB_KEY_FOREIGN, ['course_categories_id'], 'course_categories', ['id']);

// Conditionally launch create table for course_categories_domains.
if (!$DB->get_manager()->table_exists($table)) {
    $DB->get_manager()->create_table($table);
}
