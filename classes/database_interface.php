<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Database Interface
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

use core_course_category;
use stdClass;
use context_system;

defined('MOODLE_INTERNAL') || die();

require_once "$CFG->dirroot/local/mentor_core/classes/model/session.php";
require_once "$CFG->dirroot/local/mentor_core/classes/utils/datatables.php";

class database_interface {

    /**
     * @var \moodle_database
     */
    protected $db;

    protected $entities;

    protected $mainentity;

    protected $mainentities;

    protected $courses;

    protected $sessions;

    protected $courseshortnames;

    protected $roles;

    protected $users;

    protected $courseformatoptions;

    protected $cohorts;

    /**
     * @var self
     */
    protected static $instance;

    public function __construct() {

        global $DB;

        $this->db = $DB;

        $this->entities = $this->get_all_entities(false);
        $this->mainentity = $this->get_all_main_categories(false);
    }

    /**
     * Create a singleton
     *
     * @return database_interface
     */
    public static function get_instance() {

        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;

    }

    /**
     * Updates a record in a given database table.
     *
     * This method extends the basic update_record function of Moodle's $DB global object.
     * It allows updating records by encapsulating the global database access, providing 
     * a single point of control within the application. It expects an object containing 
     * the updated record data, where the object must include an ID that matches an existing 
     * record in the specified table.
     *
     * @param string $table The name of the database table, without prefix (e.g., 'user', 'course').
     * @param stdClass $record An object containing the updated data for the record. Must include an 'id' property.
     * @return bool True if the record is successfully updated, false otherwise.
     * @throws \dml_exception Throws exception if update fails or if the record with the given ID does not exist.
    */
    public function update_record($table, $record) {
        return $this->db->update_record($table, $record);
    }
    

    /*****************************FILES****************************/

    /**
     * Get a file record from database
     *
     * @param int $contextid
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @return mixed
     * @throws \dml_exception
     */
    public function get_file_from_database($contextid, $component, $filearea, $itemid) {
        return $this->db->get_record_sql("
            SELECT *
            FROM {files}
            WHERE
                filename != '.'
                AND
                contextid = :contextid
                AND
                component = :component
                AND
                filearea = :filearea
                AND
                itemid = :itemid
        ", ['contextid' => $contextid, 'component' => $component, 'filearea' => $filearea, 'itemid' => $itemid]);
    }

    /*****************************USER*****************************/

    /**
     * Get user by email
     *
     * @param string $useremail
     * @return bool|\stdClass
     * @throws \dml_exception
     */
    public function get_user_by_email($useremail) {
        return \core_user::get_user_by_email($useremail);
    }

    /**
     * Get user by id
     *
     * @param int $userid
     * @param bool $forcerefresh
     * @return \stdClass
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_user_by_id($userid, $forcerefresh = false) {

        // Fetch the user in database and cache it.
        if ($forcerefresh || !isset($this->users[$userid])) {

            // Check if user exists.
            if (!$user = \core_user::get_user($userid)) {
                throw new \moodle_exception('unknownusererror', 'local_user', '', $userid);
            }

            $this->users[$userid] = $user;
        }

        return $this->users[$userid];
    }

    /**
     * Get user by username
     *
     * @return \stdClass|bool
     * @throws \dml_exception
     */
    public function get_user_by_username($username) {
        return $this->db->get_record_sql('
            SELECT *
            FROM {user}
            WHERE username = ?',
            [$username]
        );
    }

    /**
     * Search among users
     *
     * @param string $searchtext
     * @param array $exceptions
     * @return array
     */
    public function search_users($searchtext, $exceptions) {
        return search_users(0, 0, $searchtext, '', $exceptions);
    }

    /*****************************ENTITY***************************/

    /**
     * Update entity
     *
     * @param entity $entity
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function update_entity($entity) {
        if (!isset($entity->id)) {
            throw new \moodle_exception('missingid');
        }

        if (isset($entity->shortname)) {
            $entity->idnumber = $entity->shortname;
        }

        unset($this->entities[$entity->id]);
        $coursecat = core_course_category::get($entity->id);
        $coursecat->update(get_object_vars($entity));
        return true;
    }

    /**
     * Search among main entities
     *
     * @param string $searchtext
     * @param bool $includehidden
     * @return array
     * @throws \dml_exception
     */
    public function search_main_entities($searchtext, $includehidden = true) {

        $and = '';

        // Exclude hidden entities.
        if (!$includehidden) {
            $and = " AND cc.id NOT IN (SELECT categoryid FROM {category_options} WHERE value = '1' AND name='hidden')";
        }

        return $this->db->get_records_sql('
            SELECT cc.*, cc.idnumber as shortname
            FROM {course_categories} cc
            WHERE
                parent = 0
                AND '
                                          . $this->db->sql_like('cc.idnumber', ':shortname', false, false) .
                                          ' ' . $and . '
            ORDER BY shortname ASC',
            ['shortname' => '%' . $this->db->sql_like_escape($searchtext) . '%']
        );
    }


    /**
     * Search among main entities user managed
     *
     * @param string $searchtext
     * @param int $userid
     * @param string $roleshortname
     * @param bool $includehidden
     * @return array
     * @throws \dml_exception
     */
    public function search_main_entities_user_managed($searchtext, $userid, $roleshortname, $includehidden = true) {

        $and = '';

        // Exclude hidden entities.
        if (!$includehidden) {
            $and = " AND cc.id NOT IN (SELECT categoryid FROM {category_options} WHERE value = '1' AND name='hidden')";
        }

        return $this->db->get_records_sql('
            SELECT cc.*, cc.idnumber as shortname
            FROM {course_categories} cc
            JOIN {context} c ON c.instanceid = cc.id
            JOIN {role_assignments} ra ON ra.contextid = c.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE cc.parent = 0 AND
                  ra.userid = :userid AND
                  r.shortname = :roleshortname AND
                  c.contextlevel = :contextlevel
                ' . $and . '
            AND cc.name LIKE \'%' . $searchtext . '%\'',
            [
                'userid' => $userid,
                'roleshortname' => $roleshortname,
                'contextlevel' => CONTEXT_COURSECAT,
            ]
        );
    }

    /**
     * Get all users by mainentity
     *
     * @param string $mainentity
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_users_by_mainentity($mainentity) {

        return $this->db->get_records_sql('
            SELECT u.*
            FROM {user} u
            JOIN {user_info_data} uid ON u.id = uid.userid
            JOIN {user_info_field} uif ON uif.id = uid.fieldid
            WHERE
                uif.shortname = :fieldname
                AND
                uid.data = :data
        ', ['fieldname' => 'mainentity', 'data' => $mainentity]);
    }

    /**
     * Get all users by mainentity
     *
     * @param string $secondaryentity
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_users_by_secondaryentity($secondaryentity) {

        $users = [];

        $usersdata = $this->db->get_records_sql('
            SELECT u.id, u.*, uid.data
            FROM {user} u
            JOIN {user_info_data} uid ON u.id = uid.userid
            JOIN {user_info_field} uif ON uif.id = uid.fieldid
            WHERE uif.shortname = :fieldname
            AND (' . $this->db->sql_like('uid.data', ':data', false, false) . '
            OR uid.data = :data2)
        ', [
            'fieldname' => 'secondaryentities',
            'data' => '%' . $this->db->sql_like_escape($secondaryentity) . '%',
            'data2' => $secondaryentity,
        ]);

        $existingnames = $this->get_similary_secondaryentity_names($secondaryentity);
        
        foreach ($usersdata as $userdata) {
            if(is_value_existing_in_string($userdata->data, $secondaryentity, $existingnames)) {
                unset($userdata->data);
                $users[$userdata->id] = $userdata;
            }
        }

        return $users;
    }

    /**
     * Get array of secondary entity names from list in string (filtering similary values unexpected)
     *
     * @param string $secondaryentitynames
     * @return string[]
     * @throws \dml_exception
     */
    public function get_secondaryentity_names_array(string $secondaryentitynames): array {
        return array_filter_values_existing_in_string(
            $this->get_similary_secondaryentity_names($secondaryentitynames),
            $secondaryentitynames
        );
    }

    /**
     * Get array of secondary entity names from list in string without taking care on existing similary values
     *
     * @param string $secondaryentitynames
     * @return string[]
     * @throws \dml_exception
     */
    public function get_similary_secondaryentity_names(string $secondaryentitynames): array {
        if(!isset($secondaryentitynames) || empty($secondaryentitynames)) return [];

        $secondaryentitynamesresult = $this->db->get_records_sql('
            SELECT name
            FROM {course_categories}
            WHERE :data ILIKE \'%\'  || name || \'%\'
            AND parent = 0;
        ', [
            'data' => $secondaryentitynames
        ]);

        return array_values(array_map(fn($secondaryentityname) => $secondaryentityname->name, $secondaryentitynamesresult));
    }

    /**
     * Get all user entities
     *
     * @param int $userid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_user_entities($userid) {
        return $this->db->get_records_sql('
            SELECT cc.*, cc.idnumber as shortname
            FROM {course_categories} cc
            JOIN {context} c ON c.instanceid = cc.id
            JOIN {cohort} coh ON coh.contextid = c.id
            JOIN {cohort_members} cm ON cm.cohortid = coh.id
            WHERE
                c.contextlevel = 40
                AND
                cc.depth = 1
                AND
                cm.userid = :userid
        ', ['userid' => $userid]);
    }

    /**
     * Get all sub entities
     */
    public function search_deletable_subentities($userid, $entityid, $searchtext,$is_siteadmin = false) {
        $check_role_join = "";
        $check_role_where = "";
        if(!$is_siteadmin) {
            $check_role_join .=    " JOIN {role_assignments} ra ON ra.contextid = ctx.id
                              JOIN {role} r ON ra.roleid = r.id
                              JOIN {user} u ON ra.userid = u.id ";  
            $check_role_where .= " AND r.shortname = :rolename
                                AND u.id = :userid ";                    
        }
        return $this->db->get_records_sql(
            "SELECT cc.id as id, cc.name as name
                FROM {course_categories} AS cc
                JOIN {course_categories} AS cc2 ON cc.parent = cc2.id
                JOIN {context} ctx ON ctx.instanceid = cc2.parent   "
                . $check_role_join .
                "  WHERE ctx.contextlevel = :contextlevel"
                . $check_role_where .
                    "  AND cc.name NOT IN ('Formations', 'Sessions')
                    AND cc2.parent = :entityid
                    AND ".$this->db->sql_like('cc.name', ':searchtext', false, false, false)."
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM {session} s
                        JOIN {course} c ON s.courseshortname = c.shortname
                        JOIN {course_categories} cc4 ON c.category = cc4.id
                        WHERE cc4.parent = cc.id
                    )
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM {course} c
                        JOIN {course_categories} cc4 ON c.category = cc4.id
                        WHERE cc4.parent = cc.id)
                    ORDER BY cc.name ASC
                ", 
            ['entityid' => $entityid, 'contextlevel' => CONTEXT_COURSECAT, 'rolename' => profile_api::get_user_manager_role_name(), 'userid' => $userid, 'searchtext' => '%' . $this->db->sql_like_escape($searchtext) . '%']);
    }

    /*****************************ROLE*****************************/

    /**
     * Return the role in a stdClass with there name
     *
     * @param string $rolename
     * @return \stdClass|false
     * @throws \dml_exception
     */
    public function get_role_by_name($rolename) {

        if (empty($this->roles[$rolename])) {
            $this->roles[$rolename] = $this->db->get_record('role', ['shortname' => $rolename]);
        }

        return $this->roles[$rolename];
    }

    /**
     * Get user roles in course
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     * @throws \dml_exception
     */
    public function get_user_course_roles($userid, $courseid) {
        return $this->db->get_records_sql('
            SELECT r.id, r.shortname, r.name
            FROM
                {role} r
            JOIN {role_assignments} ra ON r.id = ra.roleid
            JOIN {context} c on ra.contextid = c.id
            WHERE
                c.contextlevel = :contextlevel
                AND
                ra.userid = :userid
                AND
                c.instanceid = :instanceid
        ', ['contextlevel' => CONTEXT_COURSE, 'userid' => $userid, 'instanceid' => $courseid]);
    }

    /***********************COURSE_CATEGORY************************/

    /**
     * Create a course category
     *
     * @param string $entityname name of the category
     * @param int $parent
     * @param string $idnumber - optional default empty
     * @return \core_course_category
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_course_category($entityname, $parent = 0, $idnumber = '') {
        $data = new stdClass();
        $data->name = $entityname;
        $data->idnumber = $idnumber;
        $data->parent = $parent;
        $data->description = '';
        $category = core_course_category::create($data);

        // Refresh entities cache.
        $this->get_all_entities(true);

        return $category;
    }

    /**
     * Get all main categories
     * if $refresh is true, refresh main entities cache
     *
     * @param bool $refresh refresh data from database default false
     * @param bool $includehidden - optional default true
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_all_main_categories($refresh = false, $includehidden = true, $filter = null) {
        if ($refresh || empty($this->mainentities)) {

            $and = '';

            // Do not retrieve hidden categories.
            if (!$includehidden) {
                $and = " AND cc.id NOT IN (SELECT categoryid FROM {category_options} WHERE value = '1' AND name='hidden')";
            }

            $request = '
                SELECT cc.*, cc.idnumber as shortname
                FROM {course_categories} cc
                WHERE depth = 1
                ' . $and;

            if (is_null($filter)) {
                // Default filter.
                $request .= ' ORDER BY shortname ASC, name ASC';
            } else {
                // Check order by filter.
                if (isset($filter->order)) {
                    $request .= ' ORDER BY ' . $filter->order['column'] . ' ' . $filter->order['dir'];
                }
            }

            $this->mainentities = $this->db->get_records_sql($request);
        }

        return $this->mainentities;
    }

    /**
     * Get all entities
     * if $refresh is true, refresh entities cache
     *
     * @param bool $refresh refresh data from database default false
     * @param null|\stdClass $filter
     * @param bool $includehidden - optional default true
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_all_entities($refresh = false, $filter = null, $includehidden = true) {
        if ($refresh || empty($this->entities)) {
            $and = '';

            // Do not retrieve hidden categories.
            if (!$includehidden) {
                $and = " AND cc.id NOT IN (SELECT categoryid FROM {category_options} WHERE value = '1' AND name='hidden')";
            }

            if (is_null($filter)) {
                $this->entities = $this->db->get_records_sql('
                SELECT cc.*, cc2.parent as parentid, cc2.name AS parentname, cc.idnumber as shortname
                FROM {course_categories} cc
                LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                WHERE (cc.depth = 1
                OR cc2.name = :subentitycategory)
                ' . $and . '
                ORDER BY shortname ASC, name ASC'
                    , ['subentitycategory' => \local_mentor_core\entity::SUB_ENTITY_CATEGORY]);
            } else {
                $request = '
            SELECT cc.*, cc2.parent as parentid, cc2.name AS parentname, cc.idnumber as shortname
                FROM {course_categories} cc
                LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                LEFT JOIN {course_categories} cc3 ON cc2.parent = cc3.id
                WHERE (cc.depth = 1
                OR cc2.name = :subentitycategory)
                ' . $and;

                $params = ['subentitycategory' => \local_mentor_core\entity::SUB_ENTITY_CATEGORY];

                if (isset($filter->search) && !is_null($filter->search['value'])) {
                    $request .= 'AND (' .
                                $this->db->sql_like('cc.name', ':search1', false, false) . ' OR ' .
                                $this->db->sql_like('cc3.name', ':search2', false, false) . ' OR ' .
                                $this->db->sql_like('cc.idnumber', ':search3', false, false) . ' OR ' .
                                $this->db->sql_like('cc3.idnumber', ':search4', false, false) .
                                ')';

                    $likeescape = $this->db->sql_like_escape($filter->search['value']);
                    $params += [
                        'search1' => '%' . $likeescape . '%',
                        'search2' => '%' . $likeescape . '%',
                        'search3' => '%' . $likeescape . '%',
                        'search4' => '%' . $likeescape . '%',
                    ];
                }
                if ($filter->order) {
                    $request .= 'ORDER BY CONCAT(COALESCE(cc3.name, \'\'), cc.name) ' . $filter->order['dir'] .
                                ', CONCAT(COALESCE(cc3.idnumber, \'\'), cc.idnumber) ' . $filter->order['dir'];
                }

                $this->entities = $this->db->get_records_sql(
                    $request,
                    $params
                );
            }
        }

        return $this->entities;
    }

    /**
     * Get sub entities of the entity
     *
     * @param int $entityid
     * @return mixed
     * @throws \dml_exception
     */
    public function get_sub_entities($entityid) {
        return $this->entities = $this->db->get_records_sql('
                SELECT cc.*, cc2.parent as parentid, cc2.name AS parentname, cc.idnumber as shortname
                FROM {course_categories} cc
                LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                WHERE cc2.name = :subentitycategory AND
                      cc2.parent = :entityid
                ORDER BY name ASC'
            , [
                'subentitycategory' => \local_mentor_core\entity::SUB_ENTITY_CATEGORY,
                'entityid' => $entityid,
            ]);
    }

    /**
     * Get a course category by name
     *
     * @param string $categoryname
     * @param bool $refresh refresh or not the entities list before the check - default false
     * @param bool $mainonly - include only main entities. optional default false
     * @return \stdClass|false
     * @throws \dml_exception
     */
    public function get_course_category_by_name($categoryname, $refresh = false, $mainonly = false) {

        // Refresh entities cache.
        if ($refresh) {
            $this->get_all_entities(true);
        }

        // Check in class cache.
        foreach ($this->entities as $entity) {

            // We are looking for a main category.
            if ($mainonly && $entity->parentid != 0) {
                continue;
            }

            if (strtolower($entity->name) == strtolower($categoryname)) {
                return $entity;
            }
        }

        // Not found in cache, refresh entities cache again.
        $this->get_all_entities(true);

        foreach ($this->entities as $entity) {

            // We are looking for a main category.
            if ($mainonly && $entity->parentid != 0) {
                continue;
            }

            if (strtolower($entity->name) == strtolower($categoryname)) {
                return $entity;
            }
        }

        // Category not found.
        return false;
    }

    /**
     * Get a main entity by name
     *
     * @param string $categoryname
     * @param bool $refresh refresh or not the entities list before the check
     * @return \stdClass|false
     * @throws \dml_exception
     */
    public function get_main_entity_by_name($categoryname, $refresh = false) {

        // Refresh entities cache.
        if ($refresh) {
            $this->get_all_main_categories(true);
        }

        // Check in class cache.
        foreach ($this->mainentities as $entity) {
            if (strtolower($entity->name) == strtolower($categoryname ?? '')) {
                return $entity;
            }
        }

        // Refresh entities cache again.
        $this->get_all_main_categories(true);

        foreach ($this->mainentities as $entity) {
            if (strtolower($entity->name) == strtolower($categoryname ?? '')) {
                return $entity;
            }
        }

        // Main entity not found.
        return false;
    }

    /**
     * Check if a category shortname exists
     *
     * @param string $shortname
     * @param int $ignorecategoryid default 0
     * @return bool
     * @throws \dml_exception
     */
    public function entity_shortname_exists($shortname, $ignorecategoryid = 0) {
        return $this->db->record_exists_sql('
            SELECT *
            FROM {course_categories}
            WHERE idnumber = :idnumber AND id != :ignorecategoryid
        ', ['idnumber' => $shortname, 'ignorecategoryid' => $ignorecategoryid]);
    }

    /**
     * get library object.
     *
     * @return \stdClass|false
     * @throws \dml_exception
     */
    public function get_library_object() {
        return $this->db->get_record_sql('
            SELECT *
            FROM {course_categories}
            WHERE idnumber = :idnumber AND name = :name
        ', [
            'idnumber' => \local_mentor_core\library::SHORTNAME,
            'name' => \local_mentor_core\library::NAME,
        ]);
    }

    /**
     * Get sub entity by name
     *
     * @param string $entityname
     * @param int $parentid
     * @return bool|mixed
     * @throws \dml_exception
     */
    public function get_sub_entity_by_name($entityname, $parentid) {
        $subentities = $this->get_sub_entities($parentid);

        foreach ($subentities as $subentity) {
            if (strtolower($subentity->name) == strtolower($entityname)) {
                return $subentity;
            }
        }

        return false;
    }

    /**
     * Get a course category by parent id and name
     *
     * @param int $parentid
     * @param string $name
     * @return mixed
     * @throws \dml_exception
     */
    public function get_course_category_by_parent_and_name($parentid, $name) {
        return $this->db->get_record_sql('
            SELECT cc.*, cc.idnumber as shortname
            FROM {course_categories} cc
            WHERE
                parent = :parent
                AND
                name = :name',
            ['parent' => $parentid, 'name' => $name]
        );
    }

    /**
     * Get a course category by id
     * if $refresh is true, refresh entities cache
     *
     * @param int $categoryid
     * @param bool $refresh default false . True to refresh the cached data.
     * @return \stdClass
     * @throws \dml_exception
     */
    public function get_course_category_by_id($categoryid, $refresh = false) {

        // Refresh entities cache.
        if ($refresh) {
            $this->get_all_entities(true);
        }

        // Fetch the data in database if it's not already in cache.
        if (!isset($this->entities[$categoryid])) {
            $this->entities[$categoryid] = $this->db->get_record_sql('
                SELECT cc.*, cc2.parent as parentid, cc2.name AS parentname, cc.idnumber as shortname
                FROM {course_categories} cc
                LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                WHERE cc.id = :id',
                ['id' => $categoryid], MUST_EXIST);
        }

        return $this->entities[$categoryid];
    }



    /**
     * Get a course category by course id
     * 
     * @param int $courseid
     * @return \stdClass
     * @throws \dml_exception
     */
    public function get_course_category_by_course_id($courseid) {

        return $this->db->get_record_sql('
        SELECT cc.* FROM {course_categories} cc LEFT JOIN {course} c ON cc.id = c.category
        WHERE c.id = :courseid',
        ['courseid' => $courseid]);

    }


    /**
     * Get a cohort by context id
     *
     * @param int $contextid
     * @return \stdClass|false
     * @throws \dml_exception
     */
    public function get_cohort_by_context_id($contextid) {
        return $this->db->get_record_sql('
            SELECT id, contextid, name
            FROM {cohort}
            WHERE contextid = :contextid
        ', ['contextid' => $contextid]);
    }

    /*****************************COURSE*****************************/

    /**
     * Get a course by id
     *
     * @param int $courseid
     * @param bool $forcerefresh
     * @return \stdClass
     * @throws \dml_exception
     */
    public function get_course_by_id($courseid, $forcerefresh = false) {

        if ($forcerefresh || !isset($this->courses[$courseid])) {
            $this->courses[$courseid] = get_course($courseid);
        }
        return $this->courses[$courseid];

    }

    /**
     * Get a course by shortname
     *
     * @param string $shortname
     * @param bool $refresh
     * @return \stdClass|false
     * @throws \dml_exception
     */
    public function get_course_by_shortname($shortname, $refresh = false) {

        if ($refresh || !isset($this->courseshortnames[$shortname])) {

            $course = $this->db->get_record('course', ['shortname' => $shortname]);

            if (!$course) {
                return false;
            }
            $this->courses[$course->id] = $course;
            $this->courseshortnames[$shortname] = $course->id;
        }

        return $this->get_course_by_id($this->courseshortnames[$shortname]);

    }

    /**
     * Return edadmin courses linked with the id category
     *
     * @param int $categoryid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_edadmin_courses_by_category($categoryid) {

        return $this->db->get_records_sql('
            SELECT c.id, c.fullname, c.shortname, cfp.value AS formattype
            FROM {course} c
            JOIN {course_format_options} cfp ON cfp.courseid = c.id
            WHERE
                c.category = :category
                AND c.format = :format
                AND cfp.name = :type',
            [
                'category' => $categoryid,
                'format' => 'edadmin',
                'type' => 'formattype',
            ]
        );
    }

    /**
     * Rename a course
     *
     * @param int $courseid
     * @param string $coursename
     * @param string|null $fullname
     * @return bool
     * @throws \dml_exception
     */
    public function update_course_name($courseid, $coursename, $fullname = null) {
        $course = new stdClass();
        $course->id = $courseid;

        if ($fullname) {
            $course->fullname = $fullname;
        } else {
            $course->fullname = $coursename;
        }

        $course->shortname = $coursename;

        // Remove old course from class cache.
        if (isset($this->courses[$courseid])) {
            unset($this->courses[$courseid]);
        }

        return $this->db->update_record('course', $course);
    }

    /**
     * Check if a course exists in recyclebin
     *
     * @param string $shortname
     * @return bool
     * @throws \dml_exception
     */
    public function course_exists_in_recyclebin($shortname) {
        return $this->db->record_exists('tool_recyclebin_category', ['shortname' => $shortname]);
    }

    /**
     * Check if shortname exists for courses.
     *
     * @param string $shortname
     * @return bool
     * @throws \dml_exception
     */
    public function course_shortname_exists($shortname) {

        // Shortname is empty.
        if (empty($shortname)) {
            return false;
        }

        // Check in course table.
        if ($this->course_exists($shortname)) {
            return true;
        }

        // Check in recyclebin.
        if ($this->course_exists_in_recyclebin($shortname)) {
            return true;
        }

        // Check if the session name exists.
        $tasksadhoc = $this->get_tasks_adhoc('\local_mentor_core\task\create_session_task');
        foreach ($tasksadhoc as $taskadhoc) {
            $customdata = json_decode($taskadhoc->customdata);
            if ($customdata->sessionname === $shortname) {
                return true;
            }
        }

        // Check if the training is already in an ad hoc task.
        $tasksadhoc = $this->get_tasks_adhoc('\local_mentor_core\task\duplicate_training_task');
        foreach ($tasksadhoc as $taskadhoc) {
            $customdata = json_decode($taskadhoc->customdata);

            if ($customdata->trainingshortname === $shortname) {
                return true;
            }
        }

        // Check if the training is already in an ad hoc task.
        $tasksadhoc = $this->get_tasks_adhoc('\local_mentor_core\task\duplicate_session_as_new_training_task');
        foreach ($tasksadhoc as $taskadhoc) {
            $customdata = json_decode($taskadhoc->customdata);

            if ($customdata->trainingshortname === $shortname) {
                return true;
            }
        }

        // Check if the training is already in an ad hoc task.
        $tasksadhoc = $this->get_tasks_adhoc('\local_library\task\import_to_entity_task');
        foreach ($tasksadhoc as $taskadhoc) {
            $customdata = json_decode($taskadhoc->customdata);

            if ($customdata->trainingshortname === $shortname) {
                return true;
            }
        }

        // The course shortname does not exists anywhere.
        return false;
    }

    /*********************COURSE_FORMAT_OPTION*********************/

    /**
     * Get edadmin course format options by
     *
     * @param int $courseid
     * @param bool $forcerefresh - true to fetch the result in database
     * @param string $format - course format to retrieve, default edadmin
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_course_format_options_by_course_id($courseid, $forcerefresh = false, $format = 'edadmin') {

        if ($forcerefresh || !isset($this->courseformatoptions[$courseid])) {
            $this->courseformatoptions[$courseid] = $this->db->get_records('course_format_options', [
                'courseid' => $courseid,
                'format' => $format,
            ]);
        }

        return $this->courseformatoptions[$courseid];
    }

    /**
     * Set course format options
     *
     * @param int $courseid
     * @param string $format
     * @param array $options
     * @return void
     */
    public function set_course_format_options($courseid, $format, $options) {
        $this->db->delete_records('course_format_options', ['courseid' => $courseid, 'format' => $format]);

        foreach ($options as $option) {
            $insert = new stdClass();
            $insert->courseid = $courseid;
            $insert->format = $format;
            $insert->sectionid = $option->sectionid;
            $insert->name = $option->name;
            $insert->value = $option->value;

            $this->add_course_format_option($insert);
        }
    }

    /**
     * Insert a new course format option
     *
     * @param \stdClass $formatoption
     * @return int $courseformatoptionid
     * @throws \dml_exception
     */
    public function add_course_format_option($formatoption) {
        $courseformatoptionid = $this->db->insert_record('course_format_options', $formatoption);

        // Remove cached data.
        if (isset($this->courseformatoptions[$formatoption->courseid])) {
            unset($this->courseformatoptions[$formatoption->courseid]);
        }

        return $courseformatoptionid;
    }

    /**
     * Get a course format option
     *
     * @param int $courseid
     * @param string $option
     * @return mixed
     * @throws \dml_exception
     */
    public function get_course_format_option($courseid, $option) {
        return $this->db->get_field('course_format_options', 'value', ['courseid' => $courseid, 'name' => $option]);
    }

    /*****************************COHORT*****************************/

    /**
     * Get cohort by id
     *
     * @param int $cohortid
     * @param bool $forcerefresh
     * @return \stdClass|boolean
     * @throws \dml_exception
     */
    public function get_cohort_by_id($cohortid, $forcerefresh = false) {

        if ($forcerefresh || !isset($this->cohorts[$cohortid])) {
            $this->cohorts[$cohortid] = $this->db->get_record('cohort', ['id' => $cohortid], 'id, name, contextid, visible');
        }

        return $this->cohorts[$cohortid];
    }

    /**
     * Get cohort by name
     *
     * @param int $cohortname
     * @return array
     * @throws \dml_exception
     */
    public function get_cohorts_by_name($cohortname) {
        return $this->db->get_records('cohort', ['name' => $cohortname], 'id, name, contextid, visible');
    }

    /**
     * Get all members cohort by cohort id and data filters
     *
     * @param int $cohortid
     * @param object $data
     * @return array 
     * @throws \dml_exception
     */
    public function get_cohort_members_by_cohort_id($cohortid, $data = new stdClass()) {
        
        $cohort = $this->get_cohort_by_id($cohortid);
        // Filters and params
        $filtersandparams = $this->get_cohort_members_filters_and_params($data);
        $sqlfilters = $filtersandparams->filters;
        $params = $filtersandparams->params;
        $data->start ??= 0;
        $data->length ??= 50;
        $data->order ??= null;
        
        $sqlorderby = order_cohort_members($data->order);

        $sqlrequest = 
            "SELECT u.*, mainentity
                FROM {user} u
                INNER JOIN
                    (SELECT userid, uid.data as mainentity
                        FROM {user_info_data} uid
                        JOIN {user_info_field} uif ON uif.id = uid.fieldid AND uif.shortname = :mainentity
                    ) AS user_info ON user_info.userid = u.id
                INNER JOIN {cohort_members} cohortm ON cohortm.userid = u.id
                INNER JOIN {course_categories} e ON e.name = mainentity
                WHERE
                    cohortm.cohortid = :cohortid
                    AND u.deleted = 0
                    $sqlfilters
                    $sqlorderby
                OFFSET :offset LIMIT :limit ";
        
        $queryParams = array_merge(['mainentity' => 'mainentity' , 'cohortid' => $cohortid, 'offset' => $data->start, 'limit' => $data->length], $params);
        
        $cohort->members = $this->db->get_records_sql($sqlrequest, $queryParams);
        return $cohort->members;
    }

    /**
     * build membrs filters and params via data object
     * @param object $data
     * @return stdClass
     */
    public function get_cohort_members_filters_and_params($data){
        $filtersandparams = new stdClass();
        
        $params = [];
        $sqlfilters = '';
        // Suspended users
        $data->suspendedusers = isset($data->suspendedusers) ? $data->suspendedusers : null;
        if ($data->suspendedusers == 'true') {
            $sqlfilters .= ' AND u.suspended = 1';
        } else if ($data->suspendedusers == 'false') {
            $sqlfilters .= ' AND u.suspended = 0';
        }
        
        // Extern users
        $data->externalusers = isset($data->externalusers) ? $data->externalusers : null;
        $externexistencesql = "(SELECT 1
                FROM {role_assignments} ra
                JOIN {role} r ON ra.roleid = r.id
                WHERE 
                    ra.userid = u.id
                    AND ra.contextid = ".context_system::instance()->id."
                    AND r.shortname = 'utilisateurexterne')";
        if ($data->externalusers == 'true') {
            $sqlfilters .= " AND EXISTS ".$externexistencesql;
        } else if ($data->externalusers == 'false') {
            $sqlfilters .= " AND NOT EXISTS ".$externexistencesql;
        }

        // Search filters
        if (!empty($data->search) && mb_strlen(trim($data->search)) > 0) {
            $searchvalue = $data->search;
            $searchvalue = str_replace("&#39;", "\'", $searchvalue);
            $listsearchvalue = explode(" ", $searchvalue);
            $searchConditions = [];

            foreach ($listsearchvalue as $key => $partsearchvalue) {
                // Limit search length
                $searchvalue = trim($data->search);
                if (mb_strlen($searchvalue) > 100) {
                    $searchvalue = mb_substr($searchvalue, 0, 100);
                }
                // Add a cleaning param layer 
                $searchvalue = clean_param($searchvalue, PARAM_TEXT);

                if (!$partsearchvalue) {
                    continue;
                }
                $userSearchConditions = [
                    $this->db->sql_like('u.firstname', ':firstname' . $key, false, false),
                    $this->db->sql_like('u.lastname', ':lastname' . $key, false, false),
                    $this->db->sql_like('u.email', ':email' . $key, false, false),
                    $this->db->sql_like('u.username', ':username' . $key, false, false)
                ];

                $searchConditions[] = '(' . implode(' OR ', $userSearchConditions) . ')';

                // Add parameters for each search condition
                $params['firstname' . $key] = '%' . $this->db->sql_like_escape($partsearchvalue) . '%';
                $params['lastname' . $key] = '%' . $this->db->sql_like_escape($partsearchvalue) . '%';
                $params['email' . $key] = '%' . $this->db->sql_like_escape($partsearchvalue) . '%';
                $params['username' . $key] = '%' . $this->db->sql_like_escape($partsearchvalue) . '%';
            }

            if (!empty($searchConditions)) {
                $sqlfilters = ' AND (' . implode(' AND ', $searchConditions) . ')';
            }
        }

        $filtersandparams->params = $params;        
        $filtersandparams->filters = $sqlfilters;

        return $filtersandparams;
    }


    /**
     * Return the count of a course members with or without filers
     * @param string $sqlfilter
     * @param string $searchfilter
     * @param array $params
     * @param bool $enablefilters
     * @return int
     * @throws \dml_exception
     */
    public function get_cohort_members_count_by_cohort_id($cohortid, $data, $enablefilters = true){
        $count = 0;
        $sqlfilters = '';
        $params = ['cohortid' => $cohortid];
        $sqlcountrequest = 
            'SELECT count(Distinct u.id) FROM {user} u
                INNER JOIN {cohort_members} cohortm
                    ON cohortm.userid = u.id
                WHERE
                    cohortm.cohortid = :cohortid
                    AND u.deleted = 0';
        try{
            if($enablefilters){
                // Count with filters & search
                $filtersandparams = $this->get_cohort_members_filters_and_params($data);
                $sqlfilters = $filtersandparams->filters;
                $sqlcountrequest .= $sqlfilters;
                $params = array_merge($filtersandparams->params, $params);
            }
            
            $count = $this->db->count_records_sql($sqlcountrequest, $params);
        }catch(\dml_exception $e){
            mtrace('Error sql getting cohort members count: ' . $e->getMessage());
        }
        return $count;
    }

    /**
     * Update cohort
     *
     * @param \stdClass $cohort
     * @return bool
     * @throws \dml_exception
     */
    public function update_cohort($cohort) {
        return $this->db->update_record('cohort', $cohort);
    }

    /**
     * Get cohorts by userid
     *
     * @param int $userid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_user_cohorts($userid) {

        return $this->db->get_records_sql('
            SELECT cm.*
            FROM {cohort_members} cm
            JOIN {cohort} coh ON coh.id = cm.cohortid
            JOIN {context} cnt ON cnt.id = coh.contextid
            JOIN {course_categories} cca ON cca.id = cnt.instanceid
            WHERE
                cnt.contextlevel = :contextlevel
                AND
                cca.depth = :dept
                AND
                cm.userid = :userid
        ', ['contextlevel' => 40, 'dept' => 1, 'userid' => $userid]);
    }

    /**
     * Check if a user is member of a given cohort
     *
     * @param int $userid
     * @param int $cohortid
     * @return bool
     * @throws \dml_exception
     */
    public function check_if_user_is_cohort_member($userid, $cohortid) {
        return $this->db->record_exists('cohort_members',
            [
                'userid' => $userid,
                'cohortid' => $cohortid,
            ]
        );
    }

    /**
     * Add cohort member
     *
     * @param int $cohortid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function add_cohort_member($cohortid, $userid) {

        if (!$this->check_if_user_is_cohort_member($userid, $cohortid)) {
            cohort_add_member($cohortid, $userid);
        }

        return true;
    }

    /**
     * Remove cohort member
     *
     * @param int $cohortid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function remove_cohort_member($cohortid, $userid) {

        if ($this->check_if_user_is_cohort_member($userid, $cohortid)) {
            cohort_remove_member($cohortid, $userid);
        }

        return true;
    }

    /****************************TRAINING**************************/

    /**
     * Add a new training
     *
     * @param \stdClass $training
     * @return bool|int
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function add_training($training) {

        // Check if the courseshortname is not missing.
        if (!isset($training->courseshortname) || empty($training->courseshortname)) {
            throw new \moodle_exception('missingshortname');
        }

        // Check if the training course exists.
        if (!$this->db->record_exists('course', ['shortname' => $training->courseshortname])) {
            throw new \moodle_exception('missingcourse');
        }

        // Insert the training.
        return $this->db->insert_record('training', $training);
    }

    /**
     * Update a training
     *
     * @param \stdClass $training
     * @return bool|int
     * @throws \dml_exception
     */
    public function update_training($training) {
        return $this->db->update_record('training', $training);
    }

    /**
     * Update a session
     *
     * @param \stdClass $session
     * @return bool|int
     * @throws \dml_exception
     */
    public function update_session($session) {
        unset($this->sessions[$session->id]);
        return $this->db->update_record('session', $session);
    }

    /**
     * Delete a session
     *
     * @param session $session
     * @throws \moodle_exception
     */
    public function delete_session($session) {
        if (!delete_course($session->courseid, false)) {
            throw new \moodle_exception('errorremovecourse', 'local_mentor_core');
        }

        unset($this->sessions[$session->id]);
    }

    /**
     * Delete a session sheet by course shortname
     *
     * @param string $shortname
     * @throws \dml_exception
     */
    public function delete_session_sheet($shortname) {
        $this->db->delete_records('session', ['courseshortname' => $shortname]);
    }

    /**
     * Delete a training sheet by course shortname
     *
     * @param string $shortname
     * @throws \dml_exception
     */
    public function delete_training_sheet($shortname) {
        $this->db->delete_records('training', ['courseshortname' => $shortname]);
    }

    /**
     * Get session sharing entities.
     *
     * @param int $sessionid
     * @return stdClass[]
     * @throws \dml_exception
     */
    public function get_opento_list($sessionid) {
        return $this->db->get_records_sql('
            SELECT
                coursecategoryid
            FROM
                {session_sharing} ss
            JOIN
                {course_categories} cc ON cc.id = coursecategoryid
            WHERE
                ss.sessionid = :sessionid
        ', ['sessionid' => $sessionid]);
    }

    /**
     * Update session sharing entities.
     *
     * @param int $sessionid
     * @param array $entitiesid List of entities id
     * @return bool
     * @throws \Exception
     */
    public function update_session_sharing($sessionid, $entitiesid) {
        try {
            $this->db->delete_records('session_sharing', ['sessionid' => $sessionid]);
            foreach ($entitiesid as $entity) {
                $sessionshare = new stdClass();
                $sessionshare->sessionid = $sessionid;
                $sessionshare->coursecategoryid = $entity;
                $this->db->insert_record('session_sharing', $sessionshare);
            }

            return true;

        } catch (\Exception $e) {
            throw new \Exception('updatesessionsharingerror');
        }
    }

    /**
     * Remove sharing session data
     *
     * @param $sessionid
     * @return bool
     * @throws \dml_exception
     */
    public function remove_session_sharing($sessionid) {
        return $this->db->delete_records('session_sharing', ['sessionid' => $sessionid]);
    }

    /**
     * Get training by id
     *
     * @param int $trainingid
     * @return array
     * @throws \dml_exception
     */
    public function get_training_by_id($trainingid) {
        return $this->db->get_record_sql('
            SELECT
                t.*,co.fullname as name,
                co.shortname,
                co.summary as content,
                co.id as courseid, co.format as courseformat,
                con.id as contextid
            FROM
                {training} t
            JOIN
                {course} co ON co.shortname = t.courseshortname
            JOIN
                {context} con ON con.instanceid = co.id
            WHERE
                t.id = :id AND con.contextlevel = :contextlevel
        ', ['id' => $trainingid, 'contextlevel' => CONTEXT_COURSE], MUST_EXIST);
    }

    /**
     * Get a training by course shortname
     *
     * @param int $courseid
     * @return \stdClass
     * @throws \dml_exception
     */
    public function get_training_by_course_id($courseid) {
        return $this->db->get_record_sql('
            SELECT
                t.*, co.fullname as name, co.shortname, co.summary as content
            FROM
                {training} t
            JOIN
                {course} co ON co.shortname = t.courseshortname
            WHERE
                co.id = :courseid
        ', ['courseid' => $courseid]);
    }

    /**
     * Get all trainings by entity id
     *
     * @param int $entityid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_trainings_by_entity_id($entityid) {
        return $this->db->get_records_sql('
                SELECT
                    t.*
                FROM
                    {training} t
                JOIN
                    {course} co ON co.shortname = t.courseshortname
                JOIN
                    {course_categories} cc ON cc.id = co.category
                WHERE
                    cc.parent = :entityid',
            ['entityid' => $entityid]
        );
    }

    /**
     * Count all trainings by entity id
     *
     * @param int $entityid
     * @return int
     * @throws \dml_exception
     */
    public function count_trainings_by_entity_id($entityid) {
        return $this->db->count_records_sql('
                SELECT
                    count(DISTINCT t.id)
                FROM
                    {training} t
                JOIN
                    {course} co ON co.shortname = t.courseshortname
                JOIN
                    {course_categories} cc ON cc.id = co.category
                WHERE
                    cc.parent = :entityid',
            ['entityid' => $entityid]
        );
    }

    /**
     * Get an entity child category by name
     *
     * @param int $entityid
     * @param string $categoryname
     * @return false|\stdClass
     * @throws \dml_exception
     */
    public function get_entity_category_by_name($entityid, $categoryname) {
        return $this->db->get_record_sql('
            SELECT cc.*, cc.idnumber as shortname
            FROM {course_categories} cc
            WHERE
                parent = :parent
                AND
                name = :name
            ', ['parent' => $entityid, 'name' => $categoryname]);
    }

    /**
     * Get a category course by idnumber
     *
     * @param int $categoryid
     * @param string $idnumber
     * @return \stdClass
     * @throws \dml_exception
     */
    public function get_category_course_by_idnumber($categoryid, $idnumber) {
        return $this->db->get_record('course', ['category' => $categoryid, 'idnumber' => $idnumber]);
    }

    /**
     * Update the main entity name in all user profiles
     *
     * @param string $oldname
     * @param string $newname
     * @return bool
     * @throws \dml_exception
     */
    public function update_main_entities_name($oldname, $newname) {
        // Check if the mainentity profile field exists.
        if (!$mainentityfield = $this->db->get_record('user_info_field', ['shortname' => 'mainentity'])) {
            return false;
        }

        // Update all users mainentity fields.
        try {
            $this->db->execute('
            UPDATE
                {user_info_data}
            SET
                data = :newname
            WHERE
                data = :oldname
            AND
                fieldid=' . $mainentityfield->id,
                ['newname' => $newname, 'oldname' => $oldname]);
        } catch (\dml_exception $e) {
            \core\notification::error("ERROR : Update all users mainentity fields!!!\n" . $e->getMessage());
        }

        return true;
    }

    /**
     * Update the secondary entity name in all user profiles
     *
     * @param string $oldname
     * @param string $newname
     * @return bool
     * @throws \dml_exception
     */
    public function update_secondary_entities_name($oldname, $newname) {
        // Check if the secondary profile field exists.
        if (!$secondaryentityfield = $this->db->get_record('user_info_field', ['shortname' => 'secondaryentities'])) {
            return false;
        }

        $usersdatafield = $this->db->get_records_sql('
            SELECT uid.*
            FROM {user_info_data} uid
            WHERE uid.fieldid = :fieldid
            AND (' . $this->db->sql_like('uid.data', ':data', false, false) . '
            OR uid.data = :data2)
        ', [
            'fieldid' => $secondaryentityfield->id,
            'data' => '%' . $this->db->sql_like_escape($oldname) . '%',
            'data2' => $oldname,
        ]);

        foreach ($usersdatafield as $userdatafield) {
            if(str_contains($userdatafield->data, $oldname)) {
                $userdatafield->data = str_replace($oldname, $newname, $userdatafield->data);
                $this->db->update_record('user_info_data', $userdatafield);
            }
        }

        return true;
    }

    /**
     * Get the main category id of a given course
     *
     * @param int $courseid
     * @return int main category id (=entityid)
     * @throws \dml_exception
     */
    public function get_course_main_category_id($courseid) {
        return $this->db->get_field_sql(
            'SELECT
                    cc.parent
                FROM
                    {course_categories} cc
                JOIN
                    {course} c ON cc.id = c.category
                WHERE
                    c.id = :courseid
        ', ['courseid' => $courseid], MUST_EXIST);
    }

    /**
     * Check if a course exists by shortname
     *
     * @param string $courseshortname
     * @return bool
     * @throws \dml_exception
     */
    public function course_exists($courseshortname) {
        return $this->db->record_exists('course', ['shortname' => $courseshortname]);
    }

    /**
     * Check if a course category exists
     *
     * @param int $id
     * @return bool
     * @throws \dml_exception
     */
    public function course_category_exists($id) {
        return $this->db->record_exists('course_categories', ['id' => $id]);
    }

    /****************************SESSION***************************/

    /**
     * Add a new session
     *
     * @param \stdClass $session
     * @return bool|int
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function add_session($session) {

        // Check if the courseshortname is not missing.
        if (!isset($session->courseshortname) || empty($session->courseshortname)) {
            throw new \moodle_exception('missingshortname');
        }

        // Check if the course exists.
        if (!$this->db->record_exists('course', ['shortname' => $session->courseshortname])) {
            throw new \moodle_exception('missingcourse');
        }

        // Check if the trainingid is not missing.
        if (!isset($session->trainingid) || empty($session->trainingid)) {
            throw new \moodle_exception('missingshortname');
        }

        // Check if the course exists.
        if (!$this->db->record_exists('training', ['id' => $session->trainingid])) {
            throw new \moodle_exception('missingtraining');
        }

        // Reset the sessions cache.
        $this->sessions = [];

        return $this->db->insert_record('session', $session);
    }

    /**
     * Update session status
     *
     * @param int $sessionid
     * @param string $newstatus
     * @return bool
     * @throws \dml_exception
     */
    public function update_session_status($sessionid, $newstatus) {
        $session = new stdClass();
        $session->id = $sessionid;
        $session->status = $newstatus;
        return $this->db->update_record('session', $session);
    }

    /**
     * Check if a session exists by shortname
     *
     * @param string $courseshortname
     * @return bool
     * @throws \dml_exception
     */
    public function session_exists($courseshortname) {
        return $this->db->record_exists('session', ['courseshortname' => $courseshortname]);
    }

    /**
     * Get session record by id
     *
     * @param int $sessionid
     * @return false|mixed
     * @throws \dml_exception
     */
    public function get_session_by_id($sessionid) {

        // Get the session if it's not found in class cache.
        if (!isset($this->sessions[$sessionid])) {

            $this->sessions[$sessionid] = $this->db->get_record_sql('
                SELECT
                    s.*, co.fullname, co.shortname, co.timecreated, co.id as courseid, con.id as contextid, s.timecreated as sessiontimecreated
                FROM
                    {session} s
                JOIN
                    {training} t ON t.id = s.trainingid
                JOIN
                    {course} co ON co.shortname = s.courseshortname
                JOIN
                    {context} con ON con.instanceid = co.id
                WHERE
                    s.id = :id AND con.contextlevel = :contextlevel
            ', ['id' => $sessionid, 'contextlevel' => CONTEXT_COURSE], MUST_EXIST);
        }

        return $this->sessions[$sessionid];
    }

    /**
     * Get all sessions by entity id
     *
     * @param \stdClass $data must contain at least : entityid, start, length. Optional fields are : status, dateto, datefrom.
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_sessions_by_entity_id($data) {

        $requiredfields = [
            'entityid',
            'start',
            'length',
        ];

        // Check if data contains all required fields.
        foreach ($requiredfields as $requiredfield) {
            if (!isset($data->{$requiredfield})) {
                throw new \coding_exception('Missing field ' . $requiredfield);
            }
        }

        $request = '
                SELECT s.*
                FROM {session} s
                JOIN {training} t ON t.id = s.trainingid
                JOIN {course} co ON co.shortname = s.courseshortname
                LEFT JOIN {course_categories} cc ON cc.id = co.category
                LEFT JOIN {course} co2 ON co2.shortname = s.courseshortname
                LEFT JOIN {course_categories} cc2 ON cc2.id = co2.category
                LEFT JOIN {course_categories} cc3 ON cc3.id = cc2.parent
                LEFT JOIN {course_categories} cc4 ON cc4.id = cc3.parent
                WHERE cc.parent = :entityid OR cc4.parent = :entityid2';

        $params = [
            'entityid' => $data->entityid,
            'entityid2' => $data->entityid,
        ];

        // Filter on session status.
        if ($data->status) {
            $request .= ' AND s.status = :status';
            $params['status'] = $data->status;
        }

        // Filter on end date.
        if ($data->dateto) {
            $request .= ' AND (co.timecreated > :dateto OR co2.timecreated > :dateto2)';
            $params['dateto'] = $data->dateto;
            $params['dateto2'] = $data->dateto;
        }

        // Filter on start date.
        if ($data->datefrom) {
            $request .= ' AND (co.timecreated < :datefrom OR co2.timecreated < :datefrom2)';
            $params['datefrom'] = $data->datefrom;
            $params['datefrom2'] = $data->datefrom;
        }

        return $this->db->get_records_sql(
            $request,
            $params,
            $data->start,
            $data->length
        );
    }

    /**
     * Count sessions record by entity id
     *
     * @param \stdClass $data - must contain at least an entityid field.
     * @return int
     * @throws \dml_exception
     */
    public function count_sessions_by_entity_id($data) {

        // Check required entityid field.
        if (!isset($data->entityid)) {
            throw new \coding_exception('Missing field entityid');
        }

        $request = '
                SELECT count(s.id)
                FROM {session} s
                JOIN {training} t ON t.id = s.trainingid
                JOIN {course} co ON co.shortname = s.courseshortname
                LEFT JOIN {course_categories} cc ON cc.id = co.category
                LEFT JOIN {course} co2 ON co2.shortname = s.courseshortname
                LEFT JOIN {course_categories} cc2 ON cc2.id = co2.category
                LEFT JOIN {course_categories} cc3 ON cc3.id = cc2.parent
                LEFT JOIN {course_categories} cc4 ON cc4.id = cc3.parent
                WHERE cc.parent = :entityid OR cc4.parent = :entityid2';

        $params = [
            'entityid' => $data->entityid,
            'entityid2' => $data->entityid,
        ];

        if ($data->status) {
            $request .= ' AND s.status = :status';
            $params['status'] = $data->status;
        }

        if ($data->dateto) {
            $request .= ' AND (co.timecreated > :dateto OR co2.timecreated > :dateto2)';
            $params['dateto'] = $data->dateto;
            $params['dateto2'] = $data->dateto;
        }

        if ($data->datefrom) {
            $request .= ' AND (co.timecreated < :datefrom OR co2.timecreated < :datefrom2)';
            $params['datefrom'] = $data->datefrom;
            $params['datefrom2'] = $data->datefrom;
        }

        return $this->db->count_records_sql(
            $request,
            $params
        );
    }

    /**
     * Get a session by course id
     *
     * @param int $courseid
     * @return \stdClass|bool
     * @throws \dml_exception
     */
    public function get_session_by_course_id($courseid) {
        return $this->db->get_record_sql('
            SELECT
                s.*, co.fullname as name, co.shortname, co.summary as content
            FROM
                {session} s
            JOIN
                {training} t ON t.id = s.trainingid
            JOIN
                {course} co ON co.shortname = s.courseshortname
            WHERE
                co.id = :courseid
        ', ['courseid' => $courseid]);
    }

    /**
     * Get a sessions by training id.
     *
     * @param int $trainingid
     * @param string $orderby - optional default empty to skip order by.
     * @return array
     * @throws \dml_exception
     */
    public function get_sessions_by_training_id($trainingid, $orderby = '') {
        $request = 'SELECT
                s.*, co.fullname as name, co.shortname, co.summary as content
            FROM
                {session} s
            JOIN
                {training} t ON t.id = s.trainingid
            JOIN
                {course} co ON co.shortname = s.courseshortname
            WHERE
                t.id = :trainingid';

        if (!empty($orderby)) {
            $request .= ' ORDER BY ' . $orderby;
        }

        return $this->db->get_records_sql($request, ['trainingid' => $trainingid]);
    }

    /**
     * Check if the course is a session course
     *
     * @param int $courseid
     * @return bool
     * @throws \dml_exception
     */
    public function is_session_course($courseid) {
        return $this->db->record_exists_sql('
            SELECT
                s.id
            FROM
                {session} s
            JOIN
                {training} t ON t.id = s.trainingid
            JOIN
                {course} co ON co.shortname = s.courseshortname
            WHERE
                co.id = :courseid
        ', ['courseid' => $courseid]);
    }

    /**
     * Count all session record
     *
     * @param int $entityid
     * @return int
     * @throws \dml_exception
     */
    public function count_session_record($entityid) {
        return $this->db->count_records_sql('
                SELECT count(DISTINCT s.id)
                FROM {session} s
                JOIN {training} t ON s.trainingid = t.id
                JOIN {course} co ON co.shortname = s.courseshortname
                JOIN {course} co2 ON co2.shortname = t.courseshortname
                JOIN {course_categories} cc ON cc.id = co.category
                JOIN {context} con ON con.instanceid = co.id
                JOIN {course} co3 ON co3.shortname = s.courseshortname
                LEFT JOIN {course_categories} cc3 ON cc3.id = co3.category
                LEFT JOIN {course_categories} cc4 ON cc4.id = cc3.parent
                LEFT JOIN {course_categories} cc5 ON cc5.id = cc4.parent
                JOIN {context} con2 ON con2.instanceid = co3.id
                WHERE
                    (cc.parent = :entityid OR cc5.parent = :entityid2)
                    AND (con.contextlevel = :contextlevel OR con2.contextlevel = :contextlevel2)',
            [
                'entityid' => $entityid,
                'entityid2' => $entityid,
                'contextlevel' => CONTEXT_COURSE,
                'contextlevel2' => CONTEXT_COURSE,
            ]);
    }

    /**
     * Get the max sessionnumber from training sessions
     *
     * @param int $trainingid
     * @return mixed
     * @throws \dml_exception
     */
    public function get_max_training_session_index($trainingid) {
        return $this->db->count_records('session', ['trainingid' => $trainingid]);
    }

    /**
     * Get all sessions if the user is an admin.
     * Return false if user is not admin.
     *
     * @param $userid
     * @return array|false
     * @throws \dml_exception
     */
    public function get_all_admin_sessions($userid) {

        // Check if the user is admin.
        if (!is_siteadmin($userid)) {
            return false;
        }

        $results = $this->db->get_records_sql("
                SELECT
                    s.*,
                    c.id as courseid,
                    con.id as contextid,
                    c.fullname,
                    c.shortname,
                    c.timecreated,
                    t.id as trainingid,
                    t.producingorganization as trainingproducingorganization,
                    t.producerorganizationshortname as trainingproducerorganizationshortname,
                    t.catchphrase as trainingcatchphrase,
                    t.collection as trainingcollection,
                    t.typicaljob as trainingtypicaljob,
                    t.skills as trainingskills,
                    c2.summary as trainingcontent,
                    t.idsirh as trainingidsirh,
                    c2.fullname as trainingname,
                    con2.id as trainingcontextid,
                    cc2.parent as trainingentityid
                FROM {session} s
                JOIN {course} c ON s.courseshortname = c.shortname
                JOIN {context} con ON c.id = con.instanceid AND con.contextlevel = :contextlevel
                JOIN {training} t ON t.id = s.trainingid
            JOIN {course} c2 ON t.courseshortname = c2.shortname
            JOIN {course_categories} cc2 ON c2.category = cc2.id
            JOIN {context} con2 ON con2.instanceid = c2.id AND con2.contextlevel = :contextlevel2
                WHERE
                    (s.status = :openedregistration OR s.status = :inprogress)
                    AND
                    s.opento != 'not_visible'
            ", [
            'contextlevel' => CONTEXT_COURSE,
            'contextlevel2' => CONTEXT_COURSE,
            'openedregistration' => session::STATUS_OPENED_REGISTRATION,
            'inprogress' => session::STATUS_IN_PROGRESS,
        ]);

        return $results;
    }

    /**
     * Get sessions shared to all entities.
     *
     * @return array
     * @throws \dml_exception
     */
    public function get_sessions_shared_to_all_entities() {
        return $this->db->get_records_sql("
            SELECT
                s.*,
                c.id as courseid,
                con.id as contextid,
                c.fullname,
                c.shortname,
                c.timecreated,
                t.id as trainingid,
                t.producingorganization as trainingproducingorganization,
                t.producerorganizationshortname as trainingproducerorganizationshortname,
                t.catchphrase as trainingcatchphrase,
                t.collection as trainingcollection,
                t.typicaljob as trainingtypicaljob,
                t.skills as trainingskills,
                c2.summary as trainingcontent,
                t.idsirh as trainingidsirh,
                c2.fullname as trainingname,
                con2.id as trainingcontextid,
                cc2.parent as trainingentityid
            FROM {session} s
            JOIN {course} c ON s.courseshortname = c.shortname
            JOIN {context} con ON con.instanceid = c.id AND con.contextlevel = :contextlevel
            JOIN {training} t ON t.id = s.trainingid
            JOIN {course} c2 ON t.courseshortname = c2.shortname
            JOIN {course_categories} cc2 ON c2.category = cc2.id
            JOIN {context} con2 ON con2.instanceid = c2.id AND con2.contextlevel = :contextlevel2
            WHERE
                s.opento = 'all'
                AND
                (s.status = :openedregistration OR s.status = :inprogress)
        ", [
            'contextlevel' => CONTEXT_COURSE,
            'contextlevel2' => CONTEXT_COURSE,
            'openedregistration' => session::STATUS_OPENED_REGISTRATION,
            'inprogress' => session::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Get entities sessions.
     *
     * @param array $entities - [entityid => entityobject]
     * @param bool $opentoall
     * @return array
     * @throws \dml_exception
     */
    public function get_entities_sessions($entities, $opentoall = true) {

        // Check if the session path contain entity.
        $like = '(';
        foreach ($entities as $entityid => $entity) {
            $like .= ' cc.path LIKE \'%/' . $entityid . '/%\' OR';
        }
        $like = substr($like, 0, -3);
        $like .= ')';

        $request = "
            SELECT DISTINCT s.*, c.id as courseid, con.id as contextid, c.fullname, c.shortname, c.timecreated
            FROM {session} s
            JOIN {training} t ON t.id = s.trainingid
            JOIN {course} c ON s.courseshortname = c.shortname
            JOIN {course_categories} cc ON c.category = cc.id
            JOIN {context} con ON con.instanceid = c.id AND con.contextlevel = :contextlevel
            WHERE
                " . $like . "
                AND
                (s.status = :openedregistration OR s.status = :inprogress)
                AND
                s.opento != 'not_visible' ";

        if (!$opentoall) {
            $request .= "AND s.opento != 'all' ";
        }

        return $this->db->get_records_sql($request, [
            'openedregistration' => session::STATUS_OPENED_REGISTRATION,
            'inprogress' => session::STATUS_IN_PROGRESS,
            'contextlevel' => CONTEXT_COURSE,
        ]);
    }

    /**
     * Get sessions shared to entities.
     *
     * @param array|string $entitiesid - Can be an array or a string of ids separated by commas.
     * @return array
     * @throws \dml_exception
     */
    public function get_sessions_shared_to_entities($entitiesid) {

        if (is_array($entitiesid)) {
            $entitiesid = implode(',', $entitiesid);
        }

        return $this->db->get_records_sql("
            SELECT
                DISTINCT s.*,
                         c.id as courseid,
                         con.id as contextid,
                         c.fullname,
                         c.shortname,
                         c.timecreated,
                         t.id as trainingid,
                         t.producingorganization as trainingproducingorganization,
                         t.producerorganizationshortname as trainingproducerorganizationshortname,
                         t.catchphrase as trainingcatchphrase,
                         t.collection as trainingcollection,
                         t.typicaljob as trainingtypicaljob,
                         t.skills as trainingskills,
                         c2.summary as trainingcontent,
                         t.idsirh as trainingidsirh,
                         c2.fullname as trainingname,
                         con2.id as trainingcontextid,
                         cc2.parent as trainingentityid
            FROM {session} s
            JOIN {course} c ON s.courseshortname = c.shortname
            JOIN {session_sharing} ss ON ss.sessionid = s.id
            JOIN {context} con ON con.instanceid = c.id AND con.contextlevel = :contextlevel
            JOIN {training} t ON t.id = s.trainingid
            JOIN {course} c2 ON t.courseshortname = c2.shortname
            JOIN {course_categories} cc2 ON c2.category = cc2.id
            JOIN {context} con2 ON con2.instanceid = c2.id AND con2.contextlevel = :contextlevel2
            WHERE
                ss.coursecategoryid IN (" . $entitiesid . ")
                AND
                (s.status = :openedregistration OR s.status = :inprogress)
                AND
                s.opento != 'not_visible'
        ", [
            'contextlevel' => CONTEXT_COURSE,
            'contextlevel2' => CONTEXT_COURSE,
            'openedregistration' => session::STATUS_OPENED_REGISTRATION,
            'inprogress' => session::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Get session link to this entity if is main.
     *
     * @param int $entityid
     * @return array
     * @throws \dml_exception
     */
    public function get_sessions_link_to_entities_if_it_main($entityid) {
        return $this->db->get_records_sql('
            SELECT
                DISTINCT s.*,
                         c.id as courseid,
                         con.id as contextid,
                         c.fullname,
                         c.shortname,
                         c.timecreated,
                         t.id as trainingid,
                         t.producingorganization as trainingproducingorganization,
                         t.producerorganizationshortname as trainingproducerorganizationshortname,
                         t.catchphrase as trainingcatchphrase,
                         t.collection as trainingcollection,
                         t.typicaljob as trainingtypicaljob,
                         t.skills as trainingskills,
                         c2.summary as trainingcontent,
                         t.idsirh as trainingidsirh,
                         c2.fullname as trainingname,
                         con2.id as trainingcontextid,
                         cc2.parent as trainingentityid
            FROM {session} s
            JOIN {course} c ON s.courseshortname = c.shortname
            JOIN {course_categories} cc ON c.category = cc.id
            JOIN {context} con ON con.instanceid = c.id AND con.contextlevel = :contextlevel
            JOIN {training} t ON t.id = s.trainingid
            JOIN {course} c2 ON t.courseshortname = c2.shortname
            JOIN {course_categories} cc2 ON c2.category = cc2.id
            JOIN {context} con2 ON con2.instanceid = c2.id AND con2.contextlevel = :contextlevel2
            WHERE
                cc.path LIKE \'%/\'  || :entityid || \'/%\'
                AND
                (s.status = :openedregistration OR s.status = :inprogress)
                AND s.opento = \'current_main_entity\'
        ', [
            'contextlevel' => CONTEXT_COURSE,
            'contextlevel2' => CONTEXT_COURSE,
            'entityid' => $entityid,
            'openedregistration' => session::STATUS_OPENED_REGISTRATION,
            'inprogress' => session::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Get session to entities.
     *
     * @param array $entities
     * @return array
     * @throws \dml_exception
     */
    public function get_sessions_link_to_entities($entities) {

        if (empty($entities)) {
            return [];
        }

        // Check if the session path contain entity.
        $like = '(';
        foreach ($entities as $entityid => $entity) {
            $like .= ' cc.path LIKE \'%/' . $entityid . '/%\' OR';
        }
        $like = substr($like, 0, -3);
        $like .= ')';

        // Main, second and region.
        return $this->db->get_records_sql('
            SELECT
                DISTINCT s.*,
                         c.id as courseid,
                         con.id as contextid,
                         c.fullname,
                         c.shortname,
                         c.timecreated,
                         t.id as trainingid,
                         t.producingorganization as trainingproducingorganization,
                         t.producerorganizationshortname as trainingproducerorganizationshortname,
                         t.catchphrase as trainingcatchphrase,
                         t.collection as trainingcollection,
                         t.typicaljob as trainingtypicaljob,
                         t.skills as trainingskills,
                         c2.summary as trainingcontent,
                         t.idsirh as trainingidsirh,
                         c2.fullname as trainingname,
                         con2.id as trainingcontextid,
                         cc2.parent as trainingentityid
            FROM {session} s
            JOIN {course} c ON s.courseshortname = c.shortname
            JOIN {course_categories} cc ON c.category = cc.id
            JOIN {context} con ON con.instanceid = c.id AND con.contextlevel = :contextlevel
            JOIN {training} t ON t.id = s.trainingid
            JOIN {course} c2 ON t.courseshortname = c2.shortname
            JOIN {course_categories} cc2 ON c2.category = cc2.id
            JOIN {context} con2 ON con2.instanceid = c2.id AND con2.contextlevel = :contextlevel2
            WHERE
                ' . $like . '
                AND
                (s.status = :openedregistration OR s.status = :inprogress)
                AND
                (s.opento = \'current_entity\' OR s.opento = \'other_entities\')
        ', [
            'contextlevel' => CONTEXT_COURSE,
            'contextlevel2' => CONTEXT_COURSE,
            'openedregistration' => session::STATUS_OPENED_REGISTRATION,
            'inprogress' => session::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Get all available sessions for a given user
     *
     * @param int $userid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_user_available_sessions($userid) {

        // Get all sessions if the user is an admin.
        $allsessionsforuseradmin = $this->get_all_admin_sessions($userid);

        // False if user is not admin.
        if ($allsessionsforuseradmin !== false) {
            return $allsessionsforuseradmin;
        }

        $usersessions = [];

        // Get sessions shared to all entities.
        $usersessions += $this->get_sessions_shared_to_all_entities();

        // User is not logged in.
        if (!isloggedin()) {
            return $usersessions;
        }

        $usermainentityname = $this->get_profile_field_value($userid, 'mainentity');
        $usermainentity = \local_mentor_core\entity_api::get_entity_by_name($usermainentityname);

        // Session link to user main entity.
        if ($usermainentity) {
            $usersessions += $this->get_sessions_link_to_entities_if_it_main($usermainentity->id);
        }

        // Get all user entity.
        $userentities = $this->get_user_entities($userid);

        // Session link to user entities.
        $usersessions += $this->get_sessions_link_to_entities($userentities);

        if (!empty($userentities)) {
            // Ex 1,2,10.
            $entitiesid = implode(',', array_keys($userentities));

            // Get sesison shared to user entities.
            $usersessions += $this->get_sessions_shared_to_entities($entitiesid);
        }

        return $usersessions;
    }

    /**
     * Get number of session of a given training.
     *
     * @param int $trainingid
     * @return int
     * @throws \dml_exception
     */
    public function get_session_number($trainingid) {
        return $this->db->count_records_sql('
            SELECT
                COUNT(s.id)
            FROM
                {session} s
            JOIN
                {training} t ON t.id = s.trainingid
            JOIN
                {course} c ON s.courseshortname = c.shortname
            WHERE
                s.trainingid = :trainingid
        ', ['trainingid' => $trainingid]);
    }

    /**
     * Get number of availables sessions of a given training.
     *
     * @param int $trainingid
     * @return false|mixed
     * @throws \dml_exception
     */
    public function get_availables_sessions_number($trainingid) {
        return $this->db->get_record_sql('
            SELECT
                count(s.id) as sessionumber
            FROM
                {session} s
            JOIN
                {training} t ON t.id = s.trainingid
            WHERE
                s.trainingid = :trainingid
            AND
                (
                    s.status = :inpreparation
                    OR
                    s.status = :openedregistration
                    OR
                    s.status = :inprogress
                )
        ',
            [
                'trainingid' => $trainingid,
                'inpreparation' => \local_mentor_core\session::STATUS_IN_PREPARATION,
                'openedregistration' => \local_mentor_core\session::STATUS_OPENED_REGISTRATION,
                'inprogress' => \local_mentor_core\session::STATUS_IN_PROGRESS,
            ],
            MUST_EXIST
        );
    }

    /**
     * Returns list of courses user is enrolled into.
     *
     * @param int $userid
     * @param string|null $sort
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_user_courses($userid, $sort = null) {
        return enrol_get_users_courses($userid, false, null, $sort);
    }

    /*****************************ENROL****************************/

    /**
     * Update enrolment instance record
     *
     * @param \stdClass $data
     * @return bool
     * @throws \dml_exception
     */
    public function update_enrolment($data) {
        return $this->db->update_record('enrol', $data);
    }

    /*****************************BACKUP**************************/

    /**
     * Get a course backup file
     *
     * @param int $contextid
     * @param string $component
     * @param string $filearea
     * @return false|mixed
     * @throws \dml_exception
     */
    public function get_course_backup($contextid, $component, $filearea) {
        return $this->db->get_record_sql('
                        SELECT
                            *
                        FROM
                            {files}
                        WHERE
                            contextid = :contextid
                            AND
                            component = :component
                            AND
                            filearea = :filearea
                            AND
                             filename != :filename
                        ORDER BY id DESC LIMIT 1',
            [
                'contextid' => $contextid,
                'component' => $component,
                'filearea' => $filearea,
                'filename' => '.',
            ]
        );
    }

    /**
     * Check if a training has sessions
     *
     * @param int $trainingid
     * @return bool
     * @throws \dml_exception
     */
    public function training_has_sessions($trainingid) {
        return $this->db->record_exists_sql('
           SELECT s.*
           FROM {session} s
           JOIN {course} c ON c.shortname = s.courseshortname
           WHERE s.trainingid = :trainingid
        ', ['trainingid' => $trainingid]);
    }

    /**
     * Check if a training has sessions in recycle bin
     *
     * @param int $trainingid
     * @return bool
     * @throws \dml_exception
     */
    public function training_has_sessions_in_recycle_bin($trainingid) {
        return $this->db->record_exists_sql('
           SELECT s.*
           FROM {session} s
           JOIN {tool_recyclebin_category} trc ON trc.shortname = s.courseshortname
           WHERE s.trainingid = :trainingid
        ', ['trainingid' => $trainingid]);
    }

    /**
     * Check if a training exists
     *
     * @param string $courseshortname
     * @return bool
     * @throws \dml_exception
     */
    public function training_exists($courseshortname) {
        return $this->db->record_exists('training', ['courseshortname' => $courseshortname]);
    }

    /**
     * Get next available training name
     *
     * @param string $trainingname
     * @return string
     * @throws \dml_exception
     */
    public function get_next_available_training_name($trainingname) {

        $nameok = false;
        $i = 1;

        $createsessiontasks = $this->get_tasks_adhoc('\local_mentor_core\task\create_session_task');
        $duplicatetrainingtasks = $this->get_tasks_adhoc('\local_mentor_core\task\duplicate_training_task');
        $duplicatesessiontasks = $this->get_tasks_adhoc('\local_mentor_core\task\duplicate_session_as_new_training_task');
        $importtoentitytasks = $this->get_tasks_adhoc('\local_library\task\import_to_entity_task');

        while (!$nameok) {

            $nameok = true;

            // Increment the shortname index.
            $shortname = $trainingname . ' ' . $i;

            // Check if the shortname already exists.
            if ($this->db->record_exists('course', ['shortname' => $shortname])) {
                $nameok = false;
            }

            if ($nameok) {
                // Check in create session task.
                foreach ($createsessiontasks as $taskadhoc) {
                    $customdata = json_decode($taskadhoc->customdata);
                    if ($customdata->sessionname === $shortname) {
                        $nameok = false;
                    }
                }
            }

            if ($nameok) {
                // Check in duplicate training task.
                foreach ($duplicatetrainingtasks as $taskadhoc) {
                    $customdata = json_decode($taskadhoc->customdata);
                    if ($customdata->trainingshortname === $shortname) {
                        $nameok = false;
                    }
                }
            }

            if ($nameok) {
                // Check in duplicate session as new training task.
                foreach ($duplicatesessiontasks as $taskadhoc) {
                    $customdata = json_decode($taskadhoc->customdata);
                    if ($customdata->trainingshortname === $shortname) {
                        $nameok = false;
                    }
                }
            }

            if ($nameok) {
                // Check in import to entity as new training task.
                foreach ($importtoentitytasks as $taskadhoc) {
                    $customdata = json_decode($taskadhoc->customdata);
                    if ($customdata->trainingshortname === $shortname) {
                        $nameok = false;
                    }
                }
            }

            $i++;
        }

        return $shortname;
    }

    /**
     * Get next sessionnumber index for a given training
     *
     * @param int $trainingid
     * @return int
     * @throws \dml_exception
     */
    public function get_next_sessionnumber_index($trainingid) {
        return $this->db->get_field_sql('
            SELECT MAX(sessionnumber)
            FROM {session}
            WHERE trainingid = :trainingid',
                ['trainingid' => $trainingid]) + 1;
    }

    /**
     * Get next available training name
     *
     * @param string $trainingname
     * @return string
     * @throws \dml_exception
     */
    public function get_next_training_session_index($trainingname) {

        $nameok = false;
        $i = 1;

        $createsessiontasks = $this->get_tasks_adhoc('\local_mentor_core\task\create_session_task');
        $duplicatetrainingtasks = $this->get_tasks_adhoc('\local_mentor_core\task\duplicate_training_task');
        $duplicatesessiontasks = $this->get_tasks_adhoc('\local_mentor_core\task\duplicate_session_as_new_training_task');

        while (!$nameok) {

            $nameok = true;

            // Increment the shortname index.
            $shortname = $trainingname . ' ' . $i;

            // Check if the shortname already exists.
            if ($this->db->record_exists('course', ['shortname' => $shortname])) {
                $nameok = false;
            }

            if ($nameok) {
                // Check in create session task.
                foreach ($createsessiontasks as $taskadhoc) {
                    $customdata = json_decode($taskadhoc->customdata);
                    if ($customdata->sessionname === $shortname) {
                        $nameok = false;
                    }
                }
            }

            if ($nameok) {
                // Check in duplicate training task.
                foreach ($duplicatetrainingtasks as $taskadhoc) {
                    $customdata = json_decode($taskadhoc->customdata);
                    if ($customdata->trainingshortname === $shortname) {
                        $nameok = false;
                    }
                }
            }

            if ($nameok) {
                // Check in duplicate session as new training task.
                foreach ($duplicatesessiontasks as $taskadhoc) {
                    $customdata = json_decode($taskadhoc->customdata);
                    if ($customdata->trainingshortname === $shortname) {
                        $nameok = false;
                    }
                }
            }

            if ($nameok) {
                // Check if shortname exists in recycle bin.
                if ($this->course_exists_in_recyclebin($shortname)) {
                    $nameok = false;
                }
            }

            $i++;
        }

        return $i - 1;
    }


    /*****************************SESSION_SHARING**************************/

    /**
     * Get all specific entities to which the session is shared
     *
     * @param int $sessionid
     * @return array
     * @throws \dml_exception
     */
    public function get_session_sharing_by_session_id($sessionid) {
        return $this->db->get_records('session_sharing', ['sessionid' => $sessionid]);
    }

    /**
     * Get list tasks adhoc
     *
     * @param $classname - specify an ad hoc class name
     * @return array
     * @throws \dml_exception
     */
    public function get_tasks_adhoc($classname = null) {
        return $classname ?
            $this->db->get_records('task_adhoc', ['classname' => $classname]) :
            $this->db->get_records('task_adhoc');
    }

    /**
     * Get component files ordered by filearea
     *
     * @param int $contextid
     * @param string $component
     * @param int $itemid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_files_by_component_order_by_filearea($contextid, $component, $itemid) {
        return $this->db->get_records_sql("
            SELECT f.filearea, f.*
            FROM {files} f
            WHERE
                filename != '.'
                AND
                contextid = :contextid
                AND
                component = :component
                AND
                itemid = :itemid
            ORDER BY filearea
        ", ['contextid' => $contextid, 'component' => $component, 'itemid' => $itemid]);
    }

    /**
     * Check if a course section exists and is visible
     *
     * @param int $courseid
     * @param int $sectionindex
     * @return bool
     * @throws \dml_exception
     */
    public function is_course_section_visible($courseid, $sectionindex) {
        return $this->db->record_exists('course_sections', ['course' => $courseid, 'section' => $sectionindex, 'visible' => 1]);
    }

    /**
     * Return user highest role object
     *
     * @param int $userid
     * @return \stdClass
     * @throws \dml_exception
     */
    public function get_highest_role_by_user($userid) {

        // Check if user is admin.
        if (is_siteadmin($userid)) {
            return (object) [
                'id' => 0,
                'name' => 'Administrateur pilote',
                'shortname' => 'admin',
            ];
        }

        return $this->db->get_record_sql('
            SELECT DISTINCT r.*
            FROM {role} r
            JOIN {role_assignments} ra ON ra.roleid = r.id
            WHERE ra.userid = :userid
            ORDER BY r.sortorder',
            ['userid' => $userid],
            IGNORE_MULTIPLE
        );
    }

    /**
     * Get all admins
     *
     * @param \stdClass $data
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_all_admins($data) {

        if (!is_siteadmin()) {
            return [];
        }

        // Get all admins.
        $adminsid = get_config('moodle', 'siteadmins');

        // Initial resquest.
        $adminrequest = "
            SELECT
                u.id,
                '-' as categoryid,
                '-' as name,
                '-' as parentid,
                'Administrateur pilote' as rolename,
                u.firstname,
                u.lastname,
                u.id as userid,
                u.email,
                uid.data as mainentity,
                '-' as timemodified,
                u.lastaccess
            FROM
                {user} u
            JOIN
                {user_info_data} uid ON u.id = uid.userid
            JOIN
                {user_info_field} uif ON uid.fieldid = uif.id
            WHERE
                u.deleted = 0
                AND
                uif.shortname = :mainentity
                AND
                u.id IN (" . $adminsid . ")
        ";

        $params = ['mainentity' => 'mainentity'];

        // Manage search in admin roles.
        if (is_array($data->search) && $data->search['value']) {

            // Clean searched value.
            $cleanedsearch = str_replace(
                ["'", '"'],
                [" ", " "],
                $data->search['value']);

            $listsearchvalue = explode(" ", $cleanedsearch);

            // Generate the search part of the request.
            foreach ($listsearchvalue as $key => $searchvalue) {
                if (!$searchvalue) {
                    continue;
                }

                $adminrequest .= ' AND ( ';

                $adminrequest .= $this->db->sql_like('u.firstname', ':firstname' . $key, false, false);
                $params['firstname' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $adminrequest .= ' OR ';

                $adminrequest .= $this->db->sql_like('u.lastname', ':lastname' . $key, false, false);
                $params['lastname' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $adminrequest .= ' OR ';

                $adminrequest .= $this->db->sql_like('u.email', ':email' . $key, false, false);
                $params['email' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $adminrequest .= ' OR ';

                $adminrequest .= $this->db->sql_like('uid.data', ':mainentity' . $key, false, false);
                $params['mainentity' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $adminrequest .= ' OR ';

                $adminrequest .= "position('" . $searchvalue . "' IN 'Administrateur pilote') > 0";

                $adminrequest .= ' ) ';
            }
        }

        // Execute request with conditions and filters.
        return $this->db->get_records_sql(
            $adminrequest,
            $params
        );
    }

    /**
     * Get all users category
     *
     * @param \stdClass $data
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_all_category_users($data) {
        // Initial request.
        $request = '
            SELECT
                ra.id,
                cc.id as categoryid,
                cc.name,
                cc.parent as parentid,
                r.name as rolename,
                r.shortname as roleshortname,
                u.firstname,
                u.lastname,
                u.id as userid,
                u.email,
                uid.data as mainentity,
                ra.timemodified,
                u.lastaccess
            FROM
                {user} u
            JOIN
               {role_assignments} ra ON ra.userid = u.id
            JOIN
                {role} r on ra.roleid = r.id
            JOIN
                {role_context_levels} rcl ON r.id = rcl.roleid
            JOIN
                {context} c ON ra.contextid = c.id
            JOIN
                {course_categories} cc ON c.instanceid = cc.id
            LEFT JOIN
                {user_info_data} uid ON u.id = uid.userid
            INNER JOIN
                {user_info_field} uif ON uid.fieldid = uif.id
            JOIN
                {course_categories} cc2 ON cc2.name = uid.data
            WHERE
                rcl.contextlevel = :contextlevel
                AND
                uif.shortname = :mainentity
                AND
                deleted = 0
        ';

        // Set default params.
        $params = ['contextlevel' => CONTEXT_COURSECAT, 'mainentity' => 'mainentity'];

        // Manage search in category roles.
        if (is_array($data->search) && $data->search['value']) {
            // Replacement of the character encoding of the single coast for the search.
            $searchvalue = str_replace("&#39;", "'", $data->search['value']);
            $listsearchvalue = explode(" ", $searchvalue);

            // Generate the search part of the request.
            foreach ($listsearchvalue as $key => $searchvalue) {
                if (!$searchvalue) {
                    continue;
                }

                $request .= ' AND ( ';

                $request .= $this->db->sql_like('u.firstname', ':firstname' . $key, false, false);
                $params['firstname' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('u.lastname', ':lastname' . $key, false, false);
                $params['lastname' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('u.email', ':email' . $key, false, false);
                $params['email' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('uid.data', ':mainentity' . $key, false, false);
                $params['mainentity' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('cc.idnumber', ':idnumber' . $key, false, false);
                $params['idnumber' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('cc2.idnumber', ':idnumberbis' . $key, false, false);
                $params['idnumberbis' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('r.name', ':rolename' . $key, false, false);
                $params['rolename' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('cc.name', ':name' . $key, false, false);
                $params['name' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';

                $request .= ' ) ';
            }

        }

        // Execute request with conditions and filters.
        $userswithmainentities = $this->db->get_records_sql(
            $request,
            $params
        );

        // Users without main entities.

        // Initial request.
        $request = "
            SELECT
                ra.id,
                cc.id as categoryid,
                cc.name,
                cc.parent as parentid,
                r.name as rolename,
                r.shortname as roleshortname,
                u.firstname,
                u.lastname,
                u.id as userid,
                u.email,
                '-' as mainentity,
                ra.timemodified,
                u.lastaccess
            FROM
                {user} u
            JOIN
               {role_assignments} ra ON ra.userid = u.id
            JOIN
                {role} r on ra.roleid = r.id
            JOIN
                {role_context_levels} rcl ON r.id = rcl.roleid
            JOIN
                {context} c ON ra.contextid = c.id
            JOIN
                {course_categories} cc ON c.instanceid = cc.id
            WHERE
                rcl.contextlevel = :contextlevel
                AND
                deleted = 0
                AND
                u.id NOT IN (
                    SELECT uid2.userid
                    FROM {user_info_data} uid2
                    JOIN
                        {user_info_field} uif2 ON uid2.fieldid = uif2.id
                    WHERE
                        uif2.shortname = :mainentity
                )
        ";

        // Set default params.
        $params = ['contextlevel' => CONTEXT_COURSECAT, 'mainentity' => 'mainentity'];

        // Manage search in category roles.
        if (is_array($data->search) && $data->search['value']) {
            $listsearchvalue = explode(" ", $data->search['value']);

            // Generate the search part of the request.
            foreach ($listsearchvalue as $key => $searchvalue) {
                if (!$searchvalue) {
                    continue;
                }

                $request .= ' AND ( ';

                $request .= $this->db->sql_like('u.firstname', ':firstname' . $key, false, false);
                $params['firstname' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('u.lastname', ':lastname' . $key, false, false);
                $params['lastname' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('u.email', ':email' . $key, false, false);
                $params['email' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('r.name', ':rolename' . $key, false, false);
                $params['rolename' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';
                $request .= ' OR ';

                $request .= $this->db->sql_like('cc.name', ':name' . $key, false, false);
                $params['name' . $key] = '%' . $this->db->sql_like_escape($searchvalue) . '%';

                $request .= ' ) ';
            }

        }

        // Execute request with conditions and filters.
        $userswithoutmainentities = $this->db->get_records_sql(
            $request,
            $params
        );

        return array_merge($userswithmainentities, $userswithoutmainentities);
    }

    /**
     * Get roles of course context
     *
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_course_roles() {
        return $this->db->get_records_sql('
            SELECT r.*
            FROM {role} r
            JOIN {role_context_levels} rcl ON r.id = rcl.roleid
            WHERE
                rcl.contextlevel = :coursecontext
        ', ['coursecontext' => CONTEXT_COURSE]);
    }

    /**
     * Unassign roles from a context
     *
     * @param int $contextid
     * @param array $rolesid
     * @throws \dml_exception
     */
    public function unassign_roles($contextid, $rolesid) {

        $roles = $this->db->get_records_sql('
            SELECT
                id
            FROM
                {role_assignments}
            WHERE
                contextid = :contextid
                AND
                roleid IN (' . implode(',', $rolesid) . ')
        ', ['contextid' => $contextid]);

        foreach ($roles as $role) {
            $this->db->delete_records('role_assignments', ['id' => $role->id]);
        }
    }

    /**
     * Set a profile field value
     *
     * @param int $userid
     * @param string $rolename
     * @param string $value
     * @return bool
     * @throws \dml_exception
     */
    public function set_profile_field_value($userid, $rolename, $value) {

        $profilefieldvalue = $this->db->get_record_sql('
            SELECT uid.*
            FROM {user_info_data} uid
            JOIN {user_info_field} uif ON uif.id = uid.fieldid
            WHERE
                uid.userid = :userid
                AND
                uif.shortname = :shortname
        ', ['userid' => $userid, 'shortname' => $rolename]);

        // Value already exists.
        if ($profilefieldvalue && $profilefieldvalue->data != $value) {
            $profilefieldvalue->data = $value;
            $this->db->update_record('user_info_data', $profilefieldvalue);
        } else if (!$profilefieldvalue) {
            // Value does not exist.
            $profilefield = $this->db->get_record('user_info_field', ['shortname' => $rolename]);
            $profilefielddata = new stdClass();
            $profilefielddata->userid = $userid;
            $profilefielddata->fieldid = $profilefield->id;
            $profilefielddata->data = $value;

            $this->db->insert_record('user_info_data', $profilefielddata);
        }

        return true;
    }

    /**
     * get a profile field value
     *
     * @param int $userid
     * @param string $name
     * @return string|bool
     * @throws \dml_exception
     */
    public function get_profile_field_value($userid, $name) {
        // Get all user data profile.
        $profileuserrecord = profile_user_record($userid);

        // Check if user's field data exist.
        if (!property_exists($profileuserrecord, $name)) {
            return false;
        }

        return $profileuserrecord->$name;
    }

    /**
     * Get role assignments on a context
     *
     * @param int $contextid
     * @return array
     * @throws \dml_exception
     */
    public function get_role_assignments($contextid) {
        return $this->db->get_records('role_assignments', ['contextid' => $contextid]);
    }

    /**
     * Get all training files
     *
     * @param int $contextid
     * @return array
     * @throws \dml_exception
     */
    public function get_all_training_files($contextid) {
        return $this->db->get_records_sql('
            SELECT
                *
            FROM
                {files}
            WHERE
                filename != ?
                AND component = ?
                AND contextid = ?
        ', ['.', 'local_trainings', $contextid]);
    }

    /**
     * Check if session is sharing to the entity.
     *
     * @param int $sessionid
     * @param int $entityid
     * @return bool
     * @throws \dml_exception
     */
    public function is_shared_to_entity_by_session_id($sessionid, $entityid) {
        return $this->db->record_exists('session_sharing', ['sessionid' => $sessionid, 'coursecategoryid' => $entityid]);
    }

    /**
     * Get all available sessions catalog for a given entity
     *
     * @param int $entityid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_available_sessions_to_catalog_by_entity($entityid) {

        // Get sessions shared to all entities.
        $sharedtoall = $this->get_sessions_shared_to_all_entities();

        // Get entities sessions.
        $entitiessessions = $this->get_entities_sessions([$entityid => $entityid]);

        // Get other entities sessions shared to entities.
        $sharedsessions = $this->get_sessions_shared_to_entities([$entityid]);

        return array_merge($sharedtoall, $entitiessessions, $sharedsessions);
    }

    /**
     * Get the type of a singleactivity course
     *
     * @param int $courseid
     * @return mixed
     * @throws \dml_exception
     */
    public function get_course_singleactivity_type($courseid) {
        return $this->db->get_field('course_format_options', 'value', [
            'courseid' => $courseid,
            'format' => 'singleactivity',
            'name' => 'activitytype',
        ]);
    }

    /**
     * Get all session group.
     *
     * @param int $sessionid
     * @return array
     * @throws \dml_exception
     */
    public function get_all_session_group($sessionid) {
        return $this->db->get_records_sql('
            SELECT g.*
            FROM {groups} g
            JOIN {course} c ON g.courseid = c.id
            JOIN {session} s ON s.courseshortname = c.shortname
            WHERE s.id = :sessionid
        ', ['sessionid' => $sessionid]);
    }

    /**
     * Add new user favourite.
     *
     * @param string $component
     * @param string $itemtype
     * @param int $itemid
     * @param int $contextid
     * @param int $userid
     * @return bool|int
     * @throws \dml_exception
     */
    public function add_user_favourite($component, $itemtype, $itemid, $contextid, $userid) {
        $favourite = new stdClass();
        $favourite->component = $component;
        $favourite->itemtype = $itemtype;
        $favourite->itemid = $itemid;
        $favourite->contextid = $contextid;
        $favourite->userid = $userid;
        $favourite->timecreated = time();
        $favourite->timemodified = time();

        return $this->db->insert_record('favourite', $favourite);
    }

    /**
     * Remove user favourite.
     *
     * @param string $component
     * @param string $itemtype
     * @param int $itemid
     * @param int $contextid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     */
    public function remove_user_favourite($component, $itemtype, $itemid, $contextid, $userid) {
        $favourite = [];
        $favourite['component'] = $component;
        $favourite['itemtype'] = $itemtype;
        $favourite['itemid'] = $itemid;
        $favourite['contextid'] = $contextid;
        $favourite['userid'] = $userid;

        return $this->db->delete_records('favourite', $favourite);
    }

    /**
     * Check if favourite exist.
     *
     * @param string $component
     * @param string $itemtype
     * @param int $itemid
     * @param int $contextid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     */
    public function is_user_favourite($component, $itemtype, $itemid, $contextid, $userid) {
        $favourite = [];
        $favourite['component'] = $component;
        $favourite['itemtype'] = $itemtype;
        $favourite['itemid'] = $itemid;
        $favourite['contextid'] = $contextid;
        $favourite['userid'] = $userid;

        return $this->db->record_exists('favourite', $favourite);
    }

    /**
     * Get user favourite data.
     *
     * @param string $component
     * @param string $itemtype
     * @param int $itemid
     * @param int $contextid
     * @param int $userid
     * @return false|\stdClass
     * @throws \dml_exception
     */
    public function get_user_favourite($component, $itemtype, $itemid, $contextid, $userid) {
        $favourite = [];
        $favourite['component'] = $component;
        $favourite['itemtype'] = $itemtype;
        $favourite['itemid'] = $itemid;
        $favourite['contextid'] = $contextid;
        $favourite['userid'] = $userid;

        return $this->db->get_record('favourite', $favourite);
    }

    /**
     * Add a training to the user's preferred designs.
     *
     * @param int $itemid
     * @param int $contextid
     * @param int $userid
     * @return bool|int
     * @throws \dml_exception
     */
    public function add_trainings_user_designer_favourite($itemid, $contextid, $userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        return $this->add_user_favourite(
            'local_trainings',
            \local_mentor_core\training::FAVOURITE_DESIGNER,
            $itemid,
            $contextid,
            $userid
        );
    }

    /**
     * Remove a training to the user's preferred designs.
     *
     * @param int $itemid
     * @param int $contextid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     */
    public function remove_trainings_user_designer_favourite($itemid, $contextid, $userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        return $this->remove_user_favourite(
            'local_trainings',
            \local_mentor_core\training::FAVOURITE_DESIGNER,
            $itemid,
            $contextid,
            $userid
        );
    }

    /**
     * Check if the user has chosen this training in these preferred designs.
     *
     * @param int $itemid
     * @param int $contextid
     * @param int $userid
     * @return bool
     */
    public function is_training_user_favourite_designer($itemid, $contextid, $userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        return $this->is_user_favourite(
            'local_trainings',
            \local_mentor_core\training::FAVOURITE_DESIGNER,
            $itemid,
            $contextid,
            $userid
        );
    }

    /**
     * Get user preferred designs data.
     *
     * @param int $itemid
     * @param int $contextid
     * @param int $userid
     * @return \stdClass|false
     */
    public function get_training_user_favourite_designer_data($itemid, $contextid, $userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        return $this->get_user_favourite(
            'local_trainings',
            \local_mentor_core\training::FAVOURITE_DESIGNER,
            $itemid,
            $contextid,
            $userid
        );
    }

    /**
     * Add session to user's favourite.
     *
     * @param int $itemid
     * @param int $contextid
     * @param null|int $userid
     * @return bool|int
     * @throws \dml_exception
     */
    public function add_user_favourite_session($itemid, $contextid, $userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        return $this->add_user_favourite(
            'local_session',
            \local_mentor_core\session::FAVOURITE,
            $itemid,
            $contextid,
            $userid
        );
    }

    /**
     * Remove session to user's favourite.
     *
     * @param int $itemid
     * @param int $contextid
     * @param null|int $userid
     * @return bool
     * @throws \dml_exception
     */
    public function remove_user_favourite_session($itemid, $contextid, $userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        return $this->remove_user_favourite(
            'local_session',
            \local_mentor_core\session::FAVOURITE,
            $itemid,
            $contextid,
            $userid
        );
    }

    /**
     * Get session to user's favourite data.
     *
     * @param int $itemid
     * @param int $contextid
     * @param null|int $userid
     * @return \stdClass|false
     */
    public function get_user_favourite_session_data($itemid, $contextid, $userid = null) {
        global $USER;

        if (is_null($userid)) {
            $userid = $USER->id;
        }

        return $this->get_user_favourite(
            'local_session',
            \local_mentor_core\session::FAVOURITE,
            $itemid,
            $contextid,
            $userid
        );
    }

    /**
     * Get user preference
     *
     * @param int $userid
     * @param string $preferencename
     * @return false|mixed|\stdClass
     * @throws \dml_exception
     */
    public function get_user_preference($userid, $preferencename) {
        $record = $this->db->get_record('user_preferences', ['userid' => $userid, 'name' => $preferencename]);
        return $record ? $record->value : false;
    }

    /**
     * Set user preference
     *
     * @param int $userid
     * @param string $preferencename
     * @param mixed $value
     * @return bool
     * @throws \dml_exception
     */
    public function set_user_preference($userid, $preferencename, $value) {
        if ($preference = $this->db->get_record('user_preferences', ['userid' => $userid, 'name' => $preferencename])) {
            $preference->value = $value;
            $this->db->update_record('user_preferences', $preference);
        } else {
            $preference = new stdClass();
            $preference->userid = $userid;
            $preference->name = $preferencename;
            $preference->value = $value;
            $this->db->insert_record('user_preferences', $preference);
        }

        return true;
    }

    /**
     * Check if user has a specific context role
     *
     * @param int $userid
     * @param string $rolename
     * @param int $contextid
     * @return bool
     * @throws \dml_exception
     */
    public function user_has_role_in_context($userid, $rolename, $contextid) {
        return $this->db->record_exists_sql('
            SELECT
                ra.id
            FROM
                {role_assignments} ra
            JOIN
                {role} r ON ra.roleid = r.id
            WHERE
                userid = :userid
                AND contextid = :contextid
                AND r.shortname = :rolename
        ', ['userid' => $userid, 'contextid' => $contextid, 'rolename' => $rolename]);
    }

    /**
     * Check if enrol user by course is enabled.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     */
    public function has_enroll_user_enabled($courseid, $userid) {
        return $this->db->record_exists_sql('
            SELECT ue.*
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id =  ue.enrolid
            WHERE
                e.courseid = :courseid
                AND ue.userid = :userid
                AND ue.status = 0
        ', ['courseid' => $courseid, 'userid' => $userid]);
    }

    /**
     * Get library publication object by training id or original training id.
     *
     * @param int $trainingid
     * @return false|\stdClass
     * @throws \dml_exception
     */
    public function get_library_publication($trainingid, $by = 'originaltrainingid') {
        // Accepted column search.
        $acceptedby = [
            'originaltrainingid',
            'trainingid',
        ];

        // Not accepted column.
        if (!in_array($by, $acceptedby)) {
            return false;
        }

        return $this->db->get_record('library', [$by => $trainingid]);
    }

    /**
     * Add or update training/library link.
     *
     * @param int $trainingid
     * @param int $originaltrainingid
     * @param int $userid
     * @return bool|int
     * @throws \dml_exception
     */
    public function publish_to_library($trainingid, $originaltrainingid, $userid) {
        // If link exist.
        if ($traininglibrary = $this->get_library_publication($originaltrainingid)) {
            $traininglibrary->trainingid = $trainingid;
            $traininglibrary->timemodified = time();
            $traininglibrary->userid = $userid;
            $this->db->update_record('library', $traininglibrary);
            return $traininglibrary->id;
        }

        // Add new link.
        $data = new stdClass();
        $data->trainingid = $trainingid;
        $data->originaltrainingid = $originaltrainingid;
        $data->timecreated = time();
        $data->timemodified = time();
        $data->userid = $userid;
        return $this->db->insert_record('library', $data);
    }

    /**
     * Add or update training/library link.
     *
     * @param int $originaltrainingid
     * @return void
     * @throws \dml_exception
     */
    public function unpublish_to_library($originaltrainingid) {
        $this->db->delete_records('library', ['originaltrainingid' => $originaltrainingid]);
    }

    /**
     * Get library task by training id.
     *
     * @param int $trainingid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_library_task_by_training_id(int $trainingid) {
        return $this->db->get_records_sql('
            SELECT ta.*
            FROM {task_adhoc} ta
            WHERE ' . $this->db->sql_like('ta.customdata', ':customdata', false, false) . ' AND
                  (ta.classname = \'\local_library\task\publication_library_task\' OR
                  ta.classname = \'\local_library\task\depublication_library_task\')
        ', [
            'customdata' => '%' . $this->db->sql_like_escape('"trainingid":' . $trainingid) . '%',
        ]);
    }

    /**
     * get recyclebin category item by shortname.s
     *
     * @param string $shortname
     * @return bool|\stdClass
     * @throws \dml_exception
     */
    public function get_recyclebin_category_item($shortname) {
        return $this->db->get_record('tool_recyclebin_category', ['shortname' => $shortname]);
    }

    /**
     * Checks if the user is already enrolled.
     *
     * @param int $instanceid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     */
    public function has_enrol_by_instance_id($instanceid, $userid) {
        global $DB;

        return $DB->record_exists('user_enrolments', ['userid' => $userid, 'enrolid' => $instanceid]);
    }

    /**
     * Get course tutors
     *
     * @param int $contextid
     * @return array
     * @throws \dml_exception
     */
    public function get_course_tutors($contextid) {
        $sql = '
            SELECT DISTINCT(u.id), u.*
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE
                ra.contextid = :contextid
                AND r.shortname = :concepteur
        ';

        return $this->db->get_records_sql($sql, [
            'contextid' => $contextid,
            'concepteur' => \local_mentor_specialization\mentor_profile::ROLE_TUTEUR,
        ]);
    }

    /**
     * Get course formateurs
     *
     * @param int $contextid
     * @return array
     * @throws \dml_exception
     */
    public function get_course_formateurs($contextid) {
        $sql = '
            SELECT DISTINCT(u.id), u.*
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE
                ra.contextid = :contextid
                AND r.shortname = :concepteur
        ';

        return $this->db->get_records_sql($sql, [
            'contextid' => $contextid,
            'concepteur' => \local_mentor_specialization\mentor_profile::ROLE_FORMATEUR,
        ]);
    }

    /**
     * Get course demonstrateurs
     *
     * @param int $contextid
     * @return array
     * @throws \dml_exception
     */
    public function get_course_demonstrateurs($contextid) {
        $sql = '
            SELECT DISTINCT(u.id), u.*
            FROM {user} u
            JOIN {role_assignments} ra ON ra.userid = u.id
            JOIN {role} r ON r.id = ra.roleid
            WHERE
                ra.contextid = :contextid
                AND r.shortname = :concepteur
        ';

        return $this->db->get_records_sql($sql, [
            'contextid' => $contextid,
            'concepteur' => \local_mentor_specialization\mentor_profile::ROLE_PARTICIPANTDEMONSTRATION,
        ]);
    }

    /**
     * Delete all H5P owners in database.
     *
     * @param int $contextid
     * @return void
     * @throws \dml_exception
     */
    public function remove_user_owner_h5p_file($contextid) {
        $this->db->execute('
            UPDATE {files}
            SET userid = null
            WHERE id IN (
                SELECT f.id
                FROM {files} f
                         JOIN {context} c ON c.id = f.contextid
                WHERE (c.id = ' . $contextid . ' OR c.path like \'%/' . $contextid . '/%\') AND
                    f.mimetype like \'application/zip.h5p\'
            )'
        );
    }

    /**
     * Check if block is present to course.
     *
     * @param int $corseid
     * @param string $blockname
     * @return bool
     * @throws \dml_exception
     */
    public function is_block_present_to_course($corseid, $blockname) {
        $coursecontext = \context_course::instance($corseid);
        return $this->db->record_exists(
            'block_instances',
            ['blockname' => $blockname, 'parentcontextid' => $coursecontext->id]
        );
    }

    /**
     * Get list hidden entity id.
     *
     * @return string|false
     * @throws \dml_exception
     */
    public function get_hidden_categories() {
        // Get all hidden entity.
        $hiddencategories = $this->db->get_records_sql('
            SELECT DISTINCT categoryid
            FROM {category_options}
            WHERE
                name=\'hidden\'
                AND
                value = \'1\'
        ');

        if (empty($hiddencategories)) {
            return false;
        }

        // Get list entity id.
        $categorykeys = array_keys($hiddencategories);
        $hiddencategoriesid = implode(',', $categorykeys);

        // Create request to get hiddent entities and their sub-entity.
        $allhiddencategoriessql = '
            SELECT cc.id
            FROM {course_categories} cc
            WHERE
                cc.id IN (' . $hiddencategoriesid . ')
                OR
                (depth > 2 AND (
        ';

        // Create path condition to get sub entity.
        foreach ($categorykeys as $categorykey) {
            $allhiddencategoriessql .= ' (cc.path LIKE \'/' . $categorykey . '/%\') OR ';
        }
        $allhiddencategoriessql = substr($allhiddencategoriessql, 0, -4);
        $allhiddencategoriessql .= '))';

        // Get all hidden entity and their sub-entity.
        $allhiddencategories = $this->db->get_records_sql($allhiddencategoriessql);
        return implode(',', array_keys($allhiddencategories));
    }

    /**
     * Get user sessions where it is enrolled.
     *
     * @param int $userid
     * @param string $searchText
     * @return array
     * @throws \dml_exception
     */
    public function get_user_sessions($userid, $searchText = null) {

        // Get all hidden entity and their sub-entity.
        $hiddencondition = '';
        if ($allhiddencategoriesids = $this->get_hidden_categories()) {
            $hiddencondition .= 'AND cc.parent NOT IN (' . $allhiddencategoriesids . ')';
        }

        $searchConditions = "";
       if(!is_null($searchText))
        {
          $columns = ["t.typicaljob", "t.skills", "t.idsirh", "t.producingorganization", "t.producerorganizationshortname",
                        "c.fullname", "c.summary", "s.courseshortname", "t.traininggoal", "cc.idnumber", "t.catchphrase", "name"];
                        $searchConditions .= "  AND ( (";

                        $searchTextArray = explode(",",$searchText);
                        foreach($columns as $keyColumn => $columnValue)
                        {
                            if ($keyColumn === array_key_last($columns)) {
                                $searchConditions .= " cc.parent IN (SELECT id
                                                    FROM {course_categories}
                                                    WHERE idnumber IS NOT NULL 
                                                     AND ";
                                foreach ($searchTextArray as $keySearchText=>$valueSearchText)
                                { 
                                    if( $keySearchText === 0){
                                        $searchConditions .= "unaccent(lower(".$columnValue .")) like lower('%".$valueSearchText."%') 
                                        OR unaccent(lower(idnumber)) like lower('%".$valueSearchText."%') 
                                        ";
                                    }else{
                                        $searchConditions .= " AND unaccent(lower(".$columnValue .")) like lower('%".$valueSearchText."%') 
                                         OR unaccent(lower(idnumber)) like lower('%".$valueSearchText."%') ";
                                    } 
                                }
                            }else{
                                foreach ($searchTextArray as $keySearchText=>$valueSearchText)
                                { 
                                    if( $keySearchText === 0){
                                        $searchConditions .= "unaccent(lower(".$columnValue .")) like lower('%".$valueSearchText."%') ";
                                    }else{
                                        $searchConditions .= " AND unaccent(lower(".$columnValue .")) like lower('%".$valueSearchText."%') ";
                                    } 
                                } 
                            }
                                 
                            
                            
                            if ($keyColumn != array_key_last($columns)) {
                                $searchConditions .= " ) OR (";
                            }
                        }
                      
                        $searchConditions .= " ))) ";
                                    
        }

        // Check if the programcourse plugin is installed.
        $programcourseInstalled = \core_plugin_manager::instance()->get_plugin_info('programcourse');
        $programcourseJoin = $programcourseInstalled ? 'LEFT JOIN {programcourse} pc ON e.courseid = pc.courseid ' : '';
        $programcourseConditions = $programcourseInstalled ? 'AND ( 
                                    (( pc.course = s.id AND e.enrol != \'program\' ) OR (pc.id IS NULL and e.enrol!= \'program\')) 
                                    OR (e.enrol != \'program\' ))
                                   ' : '';
        // Get user enrolled sessions.
        $usersessions = $this->db->get_records_sql('
            SELECT 
                   DISTINCT ON (s.sessionstartdate, s.id)
                    s.*,
                    c.id as courseid
                   ,c.fullname as fullname,
                   c.format as courseformat,
                   c.enablecompletion as enablecompletion,
                   t.id as trainingid,
                   t.courseshortname as trainingshortname,
                   ct.id as contextid,
                   ct2.id as contexttrainingid
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
           JOIN {course} c ON e.courseid = c.id
           JOIN {session} s ON c.shortname = s.courseshortname
            ' . $programcourseJoin . '
           JOIN {training} t ON t.id = s.trainingid
           JOIN {course} c2 ON c2.shortname = t.courseshortname
           JOIN {context} ct ON ct.instanceid = c.id
           JOIN {context} ct2 ON ct2.instanceid = c2.id
           JOIN {course_categories} cc ON cc.id = c.category
           WHERE
                ue.userid = :userid AND
                ue.status = 0 AND
                ct.contextlevel = :level AND
                ct2.contextlevel = :levelbis
               ' . $hiddencondition . '
                '. $searchConditions .'
                '. $programcourseConditions .'
            GROUP BY s.id, c.id, t.id, contextid, contexttrainingid
            ORDER BY s.sessionstartdate DESC, s.id, c.fullname
        ', [
            'level' => CONTEXT_COURSE,
            'levelbis' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        // Get user favourite sessions.
        $userfavouritesessions = $this->db->get_records('favourite', [
            'userid' => $userid,
            'component' => 'local_session',
            'itemtype' => \local_mentor_core\session::FAVOURITE,
        ]);

        // If session is favourite, add data to session object.
        foreach ($userfavouritesessions as $userfavouritesession) {
            if (
                isset($usersessions[$userfavouritesession->itemid]) &&
                $usersessions[$userfavouritesession->itemid]->contextid === $userfavouritesession->contextid
            ) {
                $usersessions[$userfavouritesession->itemid]->favouritesession = $userfavouritesession->id;
            }
        }

        // Get sessions training thumbnail data.
        $thumbnailtrainings = $this->get_trainings_file();
        $thumbnailsessions = $this->get_sessions_file();
        
        // Create session id list by training id.
        $sessionsbytrainings = [];
        foreach ($usersessions as $usersession) {
            if (!isset($sessionsbytrainings[$usersession->trainingid])) {
                $sessionsbytrainings[$usersession->trainingid] = [];
            }

            $sessionsbytrainings[$usersession->trainingid][] = $usersession->id;
        }
    
        //if thumbnail is attached to session, add data to session object.
        //else add thumbnail data from training.
        foreach ($usersessions as $session) {
            $has_thumbnail = false;
            foreach ($thumbnailsessions as $thumbnailsession) {
                if ($thumbnailsession->contextid == $session->contextid) {
                    $session->thumbnail = [
                        'component' => 'local_session',
                        'contextid' => $thumbnailsession->contextid,
                        'fileid' => $thumbnailsession->itemid,
                        'filepath' => $thumbnailsession->filepath,
                        'filename' => $thumbnailsession->filename,
                    ];
                    $has_thumbnail = true;
                    break;
                }
            }
            if (!$has_thumbnail) {
                foreach ($thumbnailtrainings as $thumbnailtraining) {
                    if (isset($sessionsbytrainings[$thumbnailtraining->itemid])) {
                        foreach ($sessionsbytrainings[$thumbnailtraining->itemid] as $sessionbytraining) {
                            if ($sessionbytraining == $session->id) {
                                $session->thumbnail = [
                                    'component' => 'local_trainings',
                                    'contextid' => $thumbnailtraining->contextid,
                                    'fileid' => $thumbnailtraining->itemid,
                                    'filepath' => $thumbnailtraining->filepath,
                                    'filename' => $thumbnailtraining->filename,
                                ];
                                break 2;
                            }
                        }
                    }
                }
            }
        }
       

        // Get session course display option value.
        $sessioncourseoptions = $this->db->get_records('course_format_options', ['name' => 'coursedisplay']);
        $courseidlist = array_column($usersessions, 'id', 'courseid');

        // If session has course display option data, add value to its object.
        foreach ($sessioncourseoptions as $sessioncourseoption) {
            if (isset($courseidlist[$sessioncourseoption->courseid])) {
                $key = $courseidlist[$sessioncourseoption->courseid];
                $usersessions[$key]->coursedisplay = $sessioncourseoption->value;
            }
        }
        return $usersessions;
    }

    /**
     * Get training file by area.
     *
     * @param string $filearea
     * @return array
     * @throws \dml_exception
     */
    public function get_trainings_file($filearea = 'thumbnail') {
        return $this->db->get_records_sql('
            SELECT f.*
            FROM {files} f
            WHERE
                f.filename != \'.\' AND
                f.component = \'local_trainings\' AND
                f.filearea = \'' . $filearea . '\'
        ');
    }

    /**
     * Get session file by area.
     *
     * @param string $filearea
     * @return array
     * @throws \dml_exception
     */
    public function get_sessions_file($filearea = 'thumbnail') {
        return $this->db->get_records_sql('
            SELECT f.*
            FROM {files} f
            WHERE
                f.filename != \'.\' AND
                f.component = \'local_session\' AND
                f.filearea = \'' . $filearea . '\'
        ');
    }

    /**
     * Get all course where the user has a specific role
     *
     * @param int $userid
     * @param string $rolename
     * @return array
     */
    public function get_courses_with_role($userid, $rolename) {
        // Get role.
        $role = $this->get_role_by_name($rolename);

        // Get all course where the user has role.
        return $this->db->get_records_sql('
            SELECT c.id
            FROM {course} c
            JOIN {context} con ON con.instanceid = c.id
            JOIN {role_assignments} ra ON ra.contextid = con.id
            WHERE ra.userid = :userid AND ra.roleid = :roleid
            GROUP BY c.id
        ', ['userid' => $userid, 'roleid' => $role->id]);
    }

    /**
     * Get all context where the user has a specific capability
     *
     * @param int $contextlevel
     * @param int $userid
     * @param string $capability
     * @return array
     */
    public function get_context_with_capability($contextlevel, $userid, $capability) {
        return $this->db->get_records_sql('
            SELECT
                c.instanceid
            FROM
                {context} c
            JOIN
                {role_assignments} ra ON ra.contextid = c.id
            JOIN
                {role_capabilities} rc ON rc.roleid = ra.roleid
            WHERE
                c.contextlevel = :contextlevel AND
                ra.userid = :userid AND
                rc.capability = :capability
            GROUP BY c.instanceid
        ', ['contextlevel' => $contextlevel, 'userid' => $userid, 'capability' => $capability]);
    }

    /**
     * Get all categories where the user has a specific capability
     *
     * @param int $userid
     * @param string $capability
     * @return array
     */
    public function get_categories_with_capability($userid, $capability) {
        return $this->get_context_with_capability(CONTEXT_COURSECAT, $userid, $capability);
    }

    /**
     * Get all course where the user has a specific capability
     *
     * @param int $userid
     * @param string $capability
     * @return array
     */
    public function get_course_with_capability($userid, $capability) {
        return $this->get_context_with_capability(CONTEXT_COURSE, $userid, $capability);
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @return false|\stdClass
     *
     * @throws \dml_exception
     */
    public function get_user_course_completion($userid, $courseid) {
        return $this->db->get_record('user_completion', ['userid' => $userid, 'courseid' => $courseid]);
    }

    /**
     * Get main category idnumber from a path string
     *
     * @param string $path
     * @return mixed
     * @throws \dml_exception
     */
    public function get_category_idnumber_from_path($path) {
        $categories = explode('/', $path);
        $maincategory = $this->db->get_record('course_categories', ['id' => $categories[1]]);
        return $maincategory->idnumber;
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @param int $completion
     * @return void
     * @throws \dml_exception
     */
    public function set_user_course_completion($userid, $courseid, $completion) {
        if ($completion === false) {
            $completion = null;
        }

        if ($usercompletion = $this->get_user_course_completion($userid, $courseid)) {
            $usercompletion->completion = $completion;
            $usercompletion->lastupdate = time();
            $this->db->update_record('user_completion', $usercompletion);
            return;
        }

        $usercompletion = new stdClass();
        $usercompletion->userid = $userid;
        $usercompletion->courseid = $courseid;
        $usercompletion->completion = $completion;
        $usercompletion->lastupdate = time();
        $this->db->insert_record('user_completion', $usercompletion);
    }

    /**
     * Get courses where completion needs to be refreshed
     *
     * @param int $userid
     * @return array
     * @throws \dml_exception
     */
    public function get_courses_when_completion_refreshed($userid) {
        $sql = "SELECT distinct uc.courseid
                FROM {user_completion} uc
                INNER JOIN {course} c ON c.id = uc.courseid
                INNER JOIN {session} s ON s.courseshortname = c.shortname
                INNER JOIN {user_lastaccess} ul on c.id = ul.courseid
                WHERE uc.lastupdate < ul.timeaccess
                AND uc.userid = :userid
            ";
        $params['userid'] = $userid;

        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Get all user completion.
     *
     * @param int $userid
     * @return array
     * @throws \dml_exception
     */
    public function get_user_completions($userid) {
        return $this->db->get_records_sql('
            SELECT
                uc.courseid, uc.completion
            FROM
                {user_completion} uc
            WHERE
                uc.userid = :userid
        ', ['userid' => $userid]);
    }

    /**
     * Get the latest data from “course_modules_completion” after the last execution of the “update_users_course_completion” task
     * 
     * @param int $tasklastruntime timestamp
     * @param int $lastid
     * @param bool $count
     * @return array|int
     */
    public function get_last_course_modules_completions(int $tasklastruntime, int $lastid, bool $count = false): array|int
    {
        $select = "COUNT(cmc.id)";
        $endsql = "";
        $params = [
            'tasklastruntime' => $tasklastruntime,
        ];

        if (!$count) {
            global $CFG;
            $select = "cmc.id, cmc.userid, cm.course";
            $endsql = " AND cmc.id > :lastid ORDER BY cmc.id asc LIMIT :limit";
            $params['lastid'] = $lastid;
            $params['limit'] = $CFG->completion_limit_result;
        }

        $sql = "SELECT $select
                FROM {course_modules_completion} cmc
                INNER JOIN {course_modules} cm
                    ON cm.id = cmc.coursemoduleid
                WHERE cmc.timemodified > :tasklastruntime
                AND cm.visible = 1
                $endsql
                ";

        return $count ? $this->db->count_records_sql($sql, $params) : $this->db->get_records_sql($sql, $params);
    }

    /**
     * Get users never logged in on a given day
     *
     * @param int $day timestamp
     * @param int|null $limitetime
     * @return array
     * @throws \dml_exception
     */
    public function get_never_logged_user_for_giver_day($day, $limitetime = null) {
        global $DB;

        $request = '
            SELECT *
            FROM {user} u
            WHERE u.firstaccess = 0 AND
                  u.deleted = 0 AND
                  u.suspended = 0 AND
                  u.timecreated < :day
        ';
        $params = ['day' => $day];

        if ($limitetime) {
            $request .= 'AND u.timecreated > :daybefore';
            $params['daybefore'] = $limitetime;
        }

        // Get users.
        return $DB->get_records_sql($request, $params);
    }

    /**
     * Get of users who have not logged for a given number of days
     *
     * @param int $day timestamp
     * @param int|null $limitetime timestamp
     * @return array
     * @throws \dml_exception
     */
    public function get_not_logged_user_for_giver_day($day, $limitetime = null) {
        global $DB;

        $request = '
            SELECT *
            FROM {user} u
            WHERE u.deleted = 0 AND
                  u.suspended = 0 AND
                  u.lastaccess <> 0 AND
                  u.lastaccess < :day
        ';
        $params = ['day' => $day];

        if ($limitetime) {
            $request .= 'AND u.lastaccess > :daybefore';
            $params['daybefore'] = $limitetime;
        }
        // Get users.
        return $DB->get_records_sql($request, $params);
    }

    /**
     * Get user suspended for days given
     *
     * @param int $day timestamp
     * @return array
     * @throws \dml_exception
     */
    public function get_user_suspended_for_days_given($day) {
        return $this->db->get_records_sql('
            SELECT *
            FROM {user} u
            WHERE u.suspended = 1 AND
                u.deleted = 0 AND
                u.timemodified  < :day
        ', ['day' => $day]);
    }

    /**
     * Get recall users data.
     *
     * @param string $recallname
     * @return array
     * @throws \dml_exception
     */
    public function get_recall_users($recallname) {
        return $this->db->get_records('user_recall', ['recallname' => $recallname], '', 'userid');
    }

    /**
     * Insert recall users data.
     *
     * @param array $usersid
     * @param string $recallname
     * @return void
     * @throws \dml_exception
     */
    public function insert_recall_users($usersid, $recallname) {
        if (empty($usersid)) {
            return;
        }

        $time = time();

        $request
            = 'INSERT INTO {user_recall} (userid, recallname, timecreated) VALUES ';

        foreach ($usersid as $userid) {
            $request .= '(' . $userid . ', \'' . $recallname . '\', ' . $time . '), ';
        }

        $request = substr($request, 0, -2);

        $this->db->execute($request);
    }

    /**
     * Delete recall user data.
     *
     * @param $userid
     * @return void
     * @throws \dml_exception
     */
    public function delete_recall_user($userid) {
        $this->db->delete_records('user_recall', ['userid' => $userid]);
    }


     /**
     * Get a main entity by shortname
     *
     * @param string $shortname
     * @param bool $refresh refresh or not the entities list before the check
     * @return \stdClass|false
     * @throws \dml_exception
     */
    public function get_main_entity_by_shortname($shortname, $refresh = false) {

        // Refresh entities cache.
        if ($refresh) {
            $this->get_all_main_categories(true);
        }

        // Check in class cache.
        foreach ($this->mainentities as $entity) {
            if (isset($entity->shortname) && strtolower($entity->shortname) == strtolower($shortname ?? '')) {
                return $entity;
            }
        }

        // Refresh entities cache again.
        $this->get_all_main_categories(true);

        foreach ($this->mainentities as $entity) {
            if (isset($entity->shortname) && strtolower($entity->shortname) == strtolower($shortname ?? '')) {
                return $entity;
            }
        }

        // Main entity not found.
        return false;
    }

    /**
     * Get the parent category id and name by a course id
     * 
     * @param int $courseid
     */
    public function get_main_course_category_data_by_course_id(int $courseid)
    {
        $params['courseid'] = $courseid;

        $maincategory = $this->get_course_category_by_course($params);

        if ($maincategory->parent != 0)
            $maincategory = $this->get_course_category_by_sub_entity_course($params);

        return $maincategory;
    }

    /**
     * Get course_category data from a course id
     * 
     * @param array $params
     */
    public function get_course_category_by_course(array $params)
    {
        $sqlmaincategory = "SELECT
                ccparent.id,
                ccparent.name,
                ccparent.parent
            FROM {course} co
            INNER JOIN {course_categories} ccsession
                ON ccsession.id = co.category
                AND co.id = :courseid
            INNER JOIN {course_categories} ccparent
                ON ccparent.id = ccsession.parent
            ";

        return $this->db->get_record_sql($sqlmaincategory, $params);
    }

    /**
     * Get course_category data from sub category course id
     * 
     * @param array $params
     */
    public function get_course_category_by_sub_entity_course(array $params)
    {
        $sqlsubcategory = "SELECT
                ccmaincat.id,
                ccmaincat.name
            FROM {course} co
            INNER JOIN {course_categories} ccsession
                ON ccsession.id = co.category
                AND co.id = :courseid
            INNER JOIN {course_categories} ccsubcat 
                ON ccsubcat.id = ccsession.parent
            INNER JOIN {course_categories} ccespace
                ON ccespace.id = ccsubcat.parent
            INNER JOIN {course_categories} ccmaincat
                ON ccmaincat.id = ccespace.parent
            ";

        return $this->db->get_record_sql($sqlsubcategory, $params);
    }
}
