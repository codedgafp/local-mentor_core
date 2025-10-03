<?php

defined('MOODLE_INTERNAL') || die();

global $DB;

$DB->execute("CREATE EXTENSION IF NOT EXISTS unaccent");