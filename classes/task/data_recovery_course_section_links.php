<?php

namespace local_mentor_core\task;

use local_mentor_core\utils\mentor_core_service;

class data_recovery_course_section_links extends \core\task\adhoc_task {

    /**
     * @var \moodle_database
     */
    private $db;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
    }

    public function execute() {
        $mcservice = new mentor_core_service();

        $datatocheck = $this->datatocheck();

        $pattern = '/view\.php\?id=(\d+)(?:&|&amp;)section=(\d+)/';

        $sectioncache = [];

        foreach ($datatocheck as $table => $columns) {
            $datatoupdate = $this->getdatatoupdate($columns, $table);

            if (!$datatoupdate) {
                continue;
            }

            $mcservice::debugtrace(get_string('info_data_found_in_table', 'local_mentor_core', $table), true);

            foreach ($columns as $column)  {
                $mcservice::debugtrace(get_string('info_start_processing', 'local_mentor_core', ["id" => $datatoupdate->id, "column" => $column]));

                $oldlink = $datatoupdate->$column;
                $urlparams = $this->geturlparams($pattern, $oldlink);

                if ($urlparams === null) {
                    $mcservice::debugtrace(get_string('warn_already_compliant', 'local_mentor_core', ["id" => $datatoupdate->id, "column" => $column, "oldlink" => $oldlink]));
                    continue;
                }

                $cachekey = $urlparams['courseid'] . '_' . $urlparams['section'];
                if (!isset($sectioncache[$cachekey])) {
                    $sectioncache[$cachekey] = $this->db->get_record(
                        'course_sections',
                        ["course" => $urlparams["courseid"], "section" => $urlparams["section"]],
                        "id"
                    );
                }

                $coursesection = $sectioncache[$cachekey];
                if (!$coursesection) {
                    $mcservice::debugtrace(get_string('warn_no_course_section', 'local_mentor_core', ['courseid' => $urlparams['courseid'], 'section' => $urlparams['section']]));
                    continue;
                }

                $replacement = "section.php?id={$coursesection->id}";

                $newlink = preg_replace($pattern, $replacement, $oldlink);

                $updateparams = new \stdClass;
                $updateparams->id = $datatoupdate->id;
                $updateparams->$column = $newlink;

                $this->db->update_record($table, $updateparams);

                $mcservice::debugtrace(get_string('ok_data_update', 'local_mentor_core', ['oldlink' => $oldlink, 'newlink' => $newlink]));
            }
        }
    }

    /**
     * List of table and columns to check
     * 
     * @return array{table: array{0: 'column1', 1: 'column2'}}
     */
    private function datatocheck(): array
    {
        return [
            "assign" => [
                "intro",
                "activity"
            ],
            "bigbluebuttonbn" => [
                "intro",
                "welcome",
                "presentation",
                "participants"
            ],
            "block_instances" => [
                "configdata"
            ],
            "book" => [
                "intro"
            ],
            "book_chapters" => [
                "content"
            ],
            "choice" => [
                "intro"
            ],
            "choicegroup" => [
                "intro"
            ],
            "course" => [
                "summary"
            ],
            "course_sections" => [
                "summary"
            ],
            "customcert" => [
                "intro"
            ],
            "data" => [
                "intro"
            ],
            "data_content" => [
                "content1",
                "content2",
                "content3",
                "content4"
            ],
            "feedback" => [
                "intro"
            ],
            "folder" => [
                "intro"
            ],
            "forum" => [
                "intro"
            ],
            "forum_posts" => [
                "message"
            ],
            "glossary" => [
                "intro"
            ],
            "h5pactivity" => [
                "intro"
            ],
            "label" => [
                "intro"
            ],
            "lesson" => [
                "intro"
            ],
            "page" => [
                "intro",
                "content"
            ],
            "questionnaire" => [
                "intro"
            ],
            "quiz" => [
                "intro"
            ],
            "resource" => [
                "intro"
            ],
            "scorm" => [
                "intro"
            ],
            "url" => [
                "intro",
                "externalurl"
            ],
            "via" => [
                "intro"
            ],
            "wiki" => [
                "intro"
            ],
            "workshop" => [
                "intro"
            ],
        ];
    }

    /**
     * Retrieves data from the column based on a regex
     * 
     * @param array $columns
     * @param string $table
     * @return \stdClass|bool
     */
    private function getdatatoupdate(array $columns, string $table): \stdClass|bool
    {
        $whereparts = [];
        $params = [];

        foreach ($columns as $i => $column) {
            $regexhtml = "phtml$i";
            $regexchar = "pchar$i";
            $whereparts[] = "($column LIKE :$regexhtml OR $column LIKE :$regexchar)";
            $params[$regexhtml] = '%/course/view.php?id=%&section=%';
            $params[$regexchar] = '%/course/view.php?id=%&amp;section=%';
        }

        $where = implode(' OR ', $whereparts);
        $select = implode(', ', $columns);
        $sql = "SELECT id, $select
                FROM {" . $table . "}
                WHERE ($where)
                ";

        return $this->db->get_record_sql($sql, $params);
    }

    /**
     * Return course id and section from given link, return null if the patern doesn't match with the link
     * 
     * @param string $patern
     * @param string $link
     * @return array{courseid: string, section: string}|null
     */
    private function geturlparams(string $patern, string $link): array|null
    {
        if (preg_match($patern, $link, $matches)) {
            $url = html_entity_decode($matches[0]);

            $parts = parse_url($url);

            parse_str($parts['query'], $queryParams);

            return [
                'courseid' => $queryParams['id'],
                'section' => $queryParams['section']
            ];
        }

        return null;
    }
}
