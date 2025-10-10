<?php

namespace local_mentor_core\task;

use local_mentor_core\utils\mentor_core_service;

class data_recovery_course_section_links extends \core\task\adhoc_task
{

    /**
     * @var \moodle_database
     */
    private $db;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
    }

    public function execute()
    {
        $mcservice = new mentor_core_service();
        $datatocheck = $this->datatocheck();

        $pattern = '/view\.php\?id=(\d+)(?:&|&amp;)section=(\d+)/';

        $sectioncache = [];

        foreach ($datatocheck as $table => $columns) {
            $datatoupdate = $this->getdatatoupdate($columns, $table);
            if (empty($datatoupdate)) {
                continue;
            }

            $mcservice::debugtrace(get_string('info_data_found_in_table', 'local_mentor_core', $table), true);

            foreach ($datatoupdate as $data) {
                foreach ($columns as $column) {
                    $oldlink = $data->$column;

                    if (empty($oldlink)) {
                        continue;
                    }

                    $mcservice::debugtrace(get_string('info_start_processing', 'local_mentor_core', ["id" => $data->id, "column" => $column]));

                    $urlparamsarray = $this->geturlparams($pattern, $oldlink);

                    if (empty($urlparamsarray)) {
                        $mcservice::debugtrace(get_string('warn_already_compliant', 'local_mentor_core', ["id" => $data->id, "column" => $column, "oldlink" => $oldlink]));
                        continue;
                    }

                    foreach ($urlparamsarray as $urlparams) {
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

                        $newlink = preg_replace_callback($pattern, function ($match) use ($urlparams, $replacement) {
                            [$full, $courseid, $section] = $match;

                            return $courseid == $urlparams['courseid'] && $section == $urlparams['section'] ? $replacement : $full;
                        }, $oldlink);

                        if ($newlink !== $oldlink) {
                            $updateparams = new \stdClass;
                            $updateparams->id = $data->id;
                            $updateparams->$column = $newlink;

                            $this->db->update_record($table, $updateparams);

                            $mcservice::debugtrace(get_string('ok_data_update', 'local_mentor_core', ['oldlink' => $oldlink, 'newlink' => $newlink]));

                            $oldlink = $newlink;
                        }
                    }
                }
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
     * @return array
     */
    private function getdatatoupdate(array $columns, string $table): array
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

        return $this->db->get_records_sql($sql, $params);
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
        if (preg_match_all($patern, $link, $matches, PREG_SET_ORDER)) {
            $urlparams = [];

            foreach ($matches as $match) {
                $url = html_entity_decode($match[0]);
                $parts = parse_url($url);
                parse_str($parts['query'], $queryParams);

                $urlparams[] = [
                    'courseid' => $queryParams['id'],
                    'section' => $queryParams['section']
                ];
            }

            return $urlparams;
        }

        return null;
    }
}
