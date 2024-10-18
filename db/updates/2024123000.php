<?php

defined('MOODLE_INTERNAL') || die();

global $DB;
$dbman = $DB->get_manager();
 $DB->execute("CREATE EXTENSION IF NOT EXISTS unaccent");