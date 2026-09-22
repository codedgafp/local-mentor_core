<?php

namespace local_mentor_core\helper;

defined('MOODLE_INTERNAL') || die();

class coursereminder_helper {

    /**
     * Duplicate course reminders rules from source course to target course.
     *
     * @param int $sourcecourseid The ID of the source course.
     * @param int $targetcourseid The ID of the target course.
     * @return void
     * @throws \dml_exception
     */
    public static function duplicate_rules(int $sourcecourseid, int $targetcourseid): void {
        global $DB;

        $rules = $DB->get_records(
            'local_coursereminders_rule',
            ['courseid' => $sourcecourseid]
        );

        foreach ($rules as $rule) {
            unset($rule->id);

            $rule->courseid = $targetcourseid;
            $rule->timemodified = time();

            $DB->insert_record(
                'local_coursereminders_rule',
                $rule
            );
        }
    }
}