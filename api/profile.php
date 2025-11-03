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
 * Class profile_api
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

use context;
use context_system;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/mentor_core/classes/database_interface.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/entity.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/profile.php');
require_once($CFG->dirroot . '/local/mentor_core/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/helper/form_checker.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/helper/entity_helper.php');

/**
 * User profile API
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_api {

    public const EMAIL_USED = -1;
    public const EMAIL_NOT_ALLOWED = -2;

    private static $profiles = [];

    /**
     * Get a profile by user or userid
     *
     * @param int|\stdClass $userorid
     * @param bool $refresh - true to refresh the user profile, false to use a cached version.
     * @return profile|null
     * @throws \moodle_exception
     */
    public static function get_profile($userorid, $refresh = true) {

        $userid = is_object($userorid) ? $userorid->id : $userorid;

        if (!$userid) {
            return null;
        }

        if ($refresh || !isset(self::$profiles[$userid])) {
            $specialization = specialization::get_instance();

            // Return the profile specialization if it has one.
            $profile = $specialization->get_specialization('get_profile', $userorid);

            // Default behaviour if there is no specialization.
            if (is_numeric($profile) ||
                is_a($profile, 'stdClass')
            ) {
                $profile = new profile($userorid);
            }

            self::$profiles[$userid] = $profile;
        }

        return self::$profiles[$userid];
    }

    /**
     * Search among users
     *
     * @param string $searchtext
     * @return array
     * @throws \dml_exception
     */
    public static function search_users($searchtext) {
        $db = database_interface::get_instance();

        // Ignore the guest user.
        $guestuser = $db->get_user_by_username('guest');
        $excludedIds = isset($guestuser) ? [$guestuser->id] : [];

        return array_values($db->search_users(($searchtext), $excludedIds));
    }

    /**
     * Assign role to user
     *
     * @param string $rolename
     * @param int $userid
     * @param int|context $context
     * @return int new/existing id of the assignment
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function role_assign($rolename, $userid, $context) {
        return role_assign(self::get_role_by_name($rolename)->id, $userid, $context);
    }
    
    /**
     * Unassign role to user
     *
     * @param string $rolename
     * @param int $userid
     * @param int $contextid
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function role_unassign($rolename, $userid, $contextid) {
        return role_unassign(self::get_role_by_name($rolename)->id, $userid, $contextid);
    }

    /**
     * Get a role by his name
     *
     * @param string $roleshortname
     * @return stdClass role corresponding to the shortname
     * @throws \moodle_exception
     */
    public static function get_role_by_name($roleshortname)
    {
        $db = database_interface::get_instance();

        if (!$role = $db->get_role_by_name($roleshortname))
            throw new \moodle_exception('rolenotexisterror', 'local_mentor_core', '', $roleshortname);

        return $role;
    }

    /**
     * Get users by main entity
     *
     * @param string $mainentity
     * @return \stdClass[] users
     * @throws \dml_exception
     */
    public static function get_users_by_mainentity($mainentity) {
        $db = database_interface::get_instance();
        return $db->get_users_by_mainentity($mainentity);
    }

    /**
     * Get users by secondary entity
     *
     * @param string $secondaryentity
     * @return \stdClass[] users
     * @throws \dml_exception
     */
    public static function get_users_by_secondaryentity($secondaryentity) {
        $db = database_interface::get_instance();
        return $db->get_users_by_secondaryentity($secondaryentity);
    }

    /**
     * Get user main entity
     *
     * @param int $userid optional default null for the current user id
     * @return entity|false
     * @throws \moodle_exception
     */
    public static function get_user_main_entity($userid = null) {

        // Use the current user if the userid is not set.
        if (empty($userid)) {
            global $USER;
            $userid = $USER->id;
        }

        $profile = self::get_profile($userid);

        return $profile->get_main_entity();
    }

    /**
     * Get user logo from user main entity
     *
     * @param int $userid
     * @return \stored_file
     * @throws \moodle_exception
     */
    public static function get_user_logo($userid) {

        // Check if the user is loggedin.
        if (!isloggedin() || empty($userid)) {
            return false;
        }

        // If the user has no main entity yet.
        if (!$mainentity = self::get_user_main_entity($userid)) {
            return false;
        }

        return $mainentity->get_logo();
    }

    /**
     * Get the specialization of the users template
     *
     * @param string $defaulttemplate
     * @return mixed
     */
    public static function get_user_template($defaulttemplate) {
        $specialization = specialization::get_instance();
        return $specialization->get_specialization('get_user_template', $defaulttemplate);
    }

    /**
     * Get the specialization of the users template params
     *
     * @param array $params
     * @return mixed
     */
    public static function get_user_template_params($params = []) {
        $specialization = specialization::get_instance();
        return $specialization->get_specialization('get_user_template_params', $params);
    }

    /**
     * Get the specialization of the users javascript
     *
     * @param string $defaultjs
     * @return mixed
     */
    public static function get_user_javascript($defaultjs) {
        $specialization = specialization::get_instance();
        return $specialization->get_specialization('get_user_javascript', $defaultjs);
    }

    /**
     * Get the specialization of the users manager role name
     *
     * @return string
     */
    public static function get_user_manager_role_name() {
        $specialization = specialization::get_instance();

        $defaulshortname = 'manager';
        return $specialization->get_specialization('get_user_manager_role_name', $defaulshortname);
    }

    /**
     * Create and add new user with minimum information
     * The new user must fill in the rest of the information in his profile
     *
     * @param string $lastname
     * @param string $firstname
     * @param string $email
     * @param string|int|entity $entity
     * @param array $secondaryentities
     * @param string $region
     * @param string $auth
     * @param bool $isexternal
     * @param int $courseid
     * @return bool|int
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create_and_add_user(string $lastname, string $firstname, string $email, string|int|entity $entity = null, string $region = null, string $auth = null, bool $isexternal = false, int $courseid = null): int|bool
    {
        global $DB;
        $dbi = database_interface::get_instance();

        // Clear lastname and firstname.
        $lastname = str_replace(['<', '>'], '', $lastname);
        $firstname = str_replace(['<', '>'], '', $firstname);

        // Check if lastname is empty.
        if (empty($lastname)) {
            throw new \moodle_exception('emptylastname', 'local_mentor_core', '');
        }

        // Check if firstname is empty.
        if (empty($firstname)) {
            throw new \moodle_exception('emptyfirstname', 'local_mentor_core', '');
        }

        // Check if email is empty.
        if (empty($email)) {
            throw new \moodle_exception('emptyemail', 'local_mentor_core', '');
        }

        // Check if email is not used.
        if (check_users_by_email($email)) {
            return self::EMAIL_USED;
        }

        // Check email form.
        if (!validate_email($email)) {
            return self::EMAIL_NOT_ALLOWED;
        }

        // Create user object for create user function.
        $user = new \stdClass();
        $user->lastname = $lastname;
        $user->firstname = $firstname;
        $user->email = $email;
        $user->username = local_mentor_core_mail_to_username($email);
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        if ($auth !== null) {
            $user->auth = $auth;
        }

        $entityobject = null;
        // Set user main entity.
        if (!is_null($entity) && $entity !== 0 && $entity !== '') {
            if (is_number($entity)) {
                $entityobject = entity_api::get_entity($entity);
                $entityname = $entityobject->name;
            } else if (is_object($entity)) {
                $entityobject = $entity;
                $entityname = $entity->name;
            } else if (is_string($entity)) {
                $entityobject = entity_api::get_entity_by_name($entity);
                $entityname = $entityobject->name;
            }

            $user->profile_field_mainentity = $entityname;
        }


        // Set user region.
        if (!is_null($region) && $region !== get_string('none', 'local_mentor_core')) {
            $user->profile_field_region = $region;
        }

        if($isexternal && $courseid) {
            $currententity =  $dbi->get_course_category_by_course_id($courseid);
            $objentity = $currententity ? entity_api::get_entity($currententity->id) : false;
            if($objentity && $objentity->can_be_main_entity()) {
                $user->profile_field_mainentity = $objentity->name;
            } else {
                $defaultentity = \local_mentor_specialization\mentor_entity::get_default_entity();
                if($defaultentity && $defaultentity->name != $entityname) {
                    $user->profile_field_mainentity = $defaultentity->name;
                }
            }
        }

        $otherdata = json_encode(['entity' => $entityobject]);

        // Create new user.
        $user->id = self::create_user($user, $otherdata);

        if($isexternal) {
            self::role_assign('utilisateurexterne', $user->id, context_system::instance());
        }

        return true;
    }

    /**
     * Create a user in database. CRON will generate the password and send email.
     * NOTE : user fields must be validated before using this function.
     *
     * @param \stdClass $user
     * @param string|null $otherdata
     * @return int
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function create_user($user, string|null $otherdata = null): int
    {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $user->email = strtolower($user->email);
        $user->username = local_mentor_core_mail_to_username($user->email);

        if (!isset($user->auth)) {
            $user->auth = isset($CFG->defaultauth) ? $CFG->defaultauth : '';
        }

        if (empty($user->auth)) $user->auth = 'ldap_syncplus';

        // Create a Moodle user.
        $user->id = user_create_user($user, false, false);
        
        // Use LDAP Sync Plus auth by default.
        if ($user->auth === 'ldap_syncplus') {
            // Create user into LDAP.
            $auth = get_auth_plugin($user->auth);

            // Check if the user has been created into the ldap.
            if (!$auth->user_create($user, $user->password)) {
                throw new \moodle_exception('cannotupdateuseronexauth', '', '', $user->auth);
            }
        }
        // Update user password into LDAP.
        if (isset($auth) && !$auth->user_update_password($user, $user->password)) {
            throw new \moodle_exception('cannotupdateuseronexauth', '', '', $user->auth);
        }

        // Pre-process custom profile menu fields data.
        $user = uu_pre_process_custom_profile_data($user);
        // Save custom profile fields data.
        profile_save_data($user);

        // Set required fields for the fullname() function.
        $user->firstnamephonetic = '';
        $user->lastnamephonetic = '';
        $user->middlename = '';
        $user->alternatename = '';

        // Manage password.
        setnew_password_and_mail($user);
        unset_user_preference('create_password', $user);
        set_user_preference('auth_forcepasswordchange', 1, $user);

        // Prepare event data
        $data = [
            'objectid' => $user->id,
            'relateduserid' => $user->id,
            'context' => \context_user::instance($user->id)
        ];

        if ($otherdata !== null) {
            $data['other'] = $otherdata;
        }

        // Create user_created event.
        \core\event\user_created::create($data)->trigger();

        // Make sure user context exists.
        \context_user::instance($user->id);

        return $user->id;
    }

    /**
     * Return user highest role on the platform
     *
     * @param int $userid
     * @return \stdClass
     * @throws \moodle_exception
     */
    public static function get_highest_role_by_user($userid) {
        $profile = self::get_profile($userid);
        return $profile->get_highest_role();
    }

    /**
     * Get all users categories roles
     *
     * @param \stdClass $data containing search, order, length
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_all_users_roles($data) {
        global $CFG;

        $db = database_interface::get_instance();

        // Get all admins and category users.
        $mainadmins = $db->get_all_admins($data);
        $categoryadmins = $db->get_all_category_users($data);

        // Merge admin and category admins.
        $alladmins = array_merge($mainadmins, $categoryadmins);

        $formattedroles = [];

        $currenttime = time();

        $mainentities = [];

        // Format the result.
        foreach ($alladmins as $role) {

            if (isset($role->roleshortname) && $role->roleshortname === 'reflocalnonediteur') {
                continue;
            }

            $formattedrole = new \stdClass();

            // Check entity name for category roles.
            if (is_numeric($role->categoryid)) {
                // Check if user has capability to see user role.
                if (!has_capability('local/entities:manageentity', \context_coursecat::instance($role->categoryid))) {
                    continue;
                }

                $entity = entity_api::get_entity($role->categoryid, false);
                $formattedrole->entityname = $entity->get_entity_path();
            } else {
                // Admin case.
                $formattedrole->entityname = '-';
            }

            $formattedrole->categoryid = $role->categoryid;
            $formattedrole->parentid = $role->parentid;
            $formattedrole->rolename
                = isset($role->roleshortname) && $role->roleshortname === 'coursecreator' ?
                get_string('coursecreators') :
                $role->rolename;
            $formattedrole->firstname = $role->firstname;
            $formattedrole->lastname = $role->lastname;
            $formattedrole->email = $role->email;
            $formattedrole->lastaccess = $role->lastaccess;
            $formattedrole->lastaccessstr = $role->lastaccess == 0 ? get_string('neverconnected', 'local_user') : format_time
            ($currenttime - $role->lastaccess);
            $formattedrole->timemodifiednumeric = $role->timemodified;
            $formattedrole->timemodified = "-";

            if (is_numeric($role->timemodified)) {
                $dtz = new \DateTimeZone('Europe/Paris');
                $timemodifieddate = new \DateTime("@$role->timemodified");
                $timemodifieddate->setTimezone($dtz);
                $formattedrole->timemodified = $timemodifieddate->format('d/m/Y');
            }

            $formattedrole->email = $role->email;
            $formattedrole->userid = $role->userid;
            $formattedrole->profilelink = $CFG->wwwroot . '/user/profile.php?id=' . $role->userid;
            $formattedrole->mainentity = $role->mainentity;

            if (!isset($mainentities[$role->mainentity])) {
                $mainentities[$role->mainentity] = entity_api::get_entity_by_name($role->mainentity);
            }

            $formattedrole->entityshortname
                = $mainentities[$role->mainentity] ?
                $mainentities[$role->mainentity]->shortname :
                '';

            $formattedroles[] = $formattedrole;
        }

        // Manage sort order.
        if (isset($data->order) && !empty($data->order)) {
            $columnsorder = [
                'firstname',
                'email',
                'entityshortname',
                'rolename',
                'entityname',
                'timemodifiednumeric',
                'lastaccess',
            ];

            // Select the column to be sorted.
            $column = $columnsorder[$data->order['column']];

            // Selects how the column will be sorted.
            $order = $data->order['dir'];

            // Apply sort order.
            local_mentor_core_sort_array($formattedroles, $column, $order);
        }

        $length = $data->length == 0 ? count($formattedroles) : $data->length;

        // Return paginated values.
        return array_slice($formattedroles, $data->start, $length);
    }

    /**
     * Get user roles in a course
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     * @throws \dml_exception
     */
    public static function get_course_roles($userid, $courseid) {
        $db = database_interface::get_instance();
        return $db->get_user_course_roles($userid, $courseid);
    }

    /**
     * Check if user has capability to update user profile.
     * The current user can configure his own profile.
     *
     * @param int $profileid
     * @return bool
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function has_profile_config_access($profileid) {
        global $USER;

        $profile = self::get_profile($profileid);
        $mainentity = $profile->get_main_entity();

        if (!$mainentity) {
            return false;
        }

        return $USER->id === $profileid || has_capability('local/entities:manageentity', $mainentity->get_context());
    }

    /**
     * Set user preference
     *
     * @param int $userid
     * @param string $preferencename
     * @param mixed $value
     * @return mixed
     * @throws \moodle_exception
     */
    public static function set_user_preference($userid, $preferencename, $value) {
        $profile = self::get_profile($userid);

        return $profile->set_preference($preferencename, $value);
    }

    /**
     * get user preference
     *
     * @param int $userid
     * @param string $preferencename
     * @return mixed
     * @throws \moodle_exception
     */
    public static function get_user_preference($userid, $preferencename) {
        $profile = self::get_profile($userid);

        return $profile->get_preference($preferencename);
    }

    /**
     * Gives the name of the capability that allows access to the edadmin course of the user format.
     *
     * @return string
     */
    public static function get_edadmin_course_view_capability() {
        return 'local/entities:manageentity';
    }
}
