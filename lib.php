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
 * PLugin library
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mentor_core\entity;
use local_mentor_core\profile_api;
use local_categories_domains\utils\categories_domains_service;
use local_categories_domains\model\domain_name;
use local_categories_domains\repository\categories_domains_repository;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/admin/tool/uploaduser/locallib.php');
require_once($CFG->dirroot . '/login/lib.php');
require_once($CFG->dirroot . '/lib/csvlib.class.php');
require_once($CFG->dirroot . '/local/mentor_core/api/session.php');
require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');
require_once($CFG->dirroot . '/lib/completionlib.php');
require_once($CFG->dirroot . '/local/mentor_core/forms/importcsv_form.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/helper/form_checker.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/helper/entity_helper.php');

/**
 * Set a moodle config
 *
 * @param $name
 * @param $value
 * @param $plugin
 */
function local_mentor_core_set_moodle_config($name, $value, $plugin = null)
{
    mtrace('Set config ' . $name . ' to ' . $value);
    set_config($name, $value, $plugin);
}

/**
 * Unset a moodle config
 *
 * @param $name
 * @param $plugin
 */
function local_mentor_core_unset_moodle_config($name, $plugin)
{
    mtrace('Unset config ' . $name . ' to plugin ' . $plugin);
    unset_config($name, $plugin);
}

/**
 * Remove a role capability
 *
 * @param $role
 * @param $capability
 * @throws dml_exception
 */
function local_mentor_core_remove_capability($role, $capability)
{
    global $DB;

    // Remove capabilities if exist.
    if (!$DB->record_exists('role_capabilities', ['roleid' => $role->id, 'capability' => $capability])) {
        return;
    }

    mtrace('Remove capability ' . $capability . ' from role ' . $role->name);

    $DB->delete_records('role_capabilities', ['roleid' => $role->id, 'capability' => $capability]);
}

/**
 * Remove role capabilities
 *
 * @param stdClass $role
 * @param array $capabilities
 * @throws dml_exception
 */
function local_mentor_core_remove_capabilities($role, $capabilities)
{
    foreach ($capabilities as $capability) {
        local_mentor_core_remove_capability($role, $capability);
    }
}

/**
 * Remove capability for all role.
 *
 * @param string $capability
 * @return void
 * @throws dml_exception
 */
function local_mentor_core_remove_capability_for_all($capability)
{
    global $DB;

    if ($DB->record_exists('role_capabilities', ['capability' => $capability])) {
        mtrace('Remove ' . $capability . '  to all role.');
        $DB->delete_records('role_capabilities', ['capability' => $capability]);
    }
}

/**
 * Add a role capability
 *
 * @param $role
 * @param $capability
 * @param $capability
 * @return bool|int
 * @throws dml_exception
 */
function local_mentor_core_add_capability($role, $capability, $permission = CAP_ALLOW)
{
    global $DB;

    mtrace('Add capability ' . $capability . ' to role ' . $role->name);

    // Capability already exists.
    if (!$cap = $DB->get_record('role_capabilities', ['roleid' => $role->id, 'capability' => $capability])) {
        $cap = new stdClass();
        $cap->roleid = $role->id;
        $cap->capability = $capability;
        $cap->contextid = 1;
        $cap->permission = $permission;
        $cap->timemodified = time();
        $cap->modifierid = 0;

        return $DB->insert_record('role_capabilities', $cap);
    }

    $cap->permission = $permission;
    $cap->timemodified = time();
    $cap->modifierid = 0;

    return $DB->update_record('role_capabilities', $cap);
}

/**
 * Add role capabilities
 *
 * @param stdClass $role
 * @param array $capabilities
 * @throws dml_exception
 */
function local_mentor_core_add_capabilities($role, $capabilities, $permission = CAP_ALLOW)
{
    foreach ($capabilities as $capability) {
        local_mentor_core_add_capability($role, $capability, $permission);
    }
}

/**
 * Prevent a role capability
 *
 * @param $role
 * @param $capability
 * @return bool|int
 * @throws dml_exception
 */
function local_mentor_core_prevent_capability($role, $capability)
{
    global $DB;

    mtrace('Prevent capability ' . $capability . ' to role ' . $role->name);

    // Capability already exists.
    if (!$cap = $DB->get_record('role_capabilities', ['roleid' => $role->id, 'capability' => $capability])) {
        $cap = new stdClass();
        $cap->roleid = $role->id;
        $cap->capability = $capability;
        $cap->contextid = 1;
        $cap->permission = -1;
        $cap->timemodified = time();
        $cap->modifierid = 0;

        return $DB->insert_record('role_capabilities', $cap);
    }

    $cap->permission = -1;
    $cap->timemodified = time();
    $cap->modifierid = 0;

    return $DB->update_record('role_capabilities', $cap);
}

/**
 * Create new role
 *
 * @param string $name
 * @param string $shortname
 * @param array $contextlevels
 * @return int
 * @throws dml_exception
 */
function local_mentor_core_create_role($name, $shortname, $contextlevels = [])
{
    global $DB;

    // Check if role exist.
    if (!$newroleid = $DB->get_field('role', 'id', ['name' => $name, 'shortname' => $shortname])) {
        // Create new role.
        mtrace('Add new role : ' . $name . ' ');
        $newroleid = create_role($name, $shortname, '');
    }

    if (!empty($contextlevels)) {
        // Add context level to role.
        local_mentor_core_add_context_levels($newroleid, $contextlevels);
    }

    return $newroleid;
}

/**
 * Add a context levels to role
 *
 * @param int $roleid
 * @param array $contextlevels
 * @return void
 * @throws dml_exception
 */
function local_mentor_core_add_context_levels($roleid, $contextlevels)
{
    global $DB;

    mtrace('Add context level to role ' . $roleid . '(');
    foreach ($contextlevels as $contextlevel) {
        // Check if the role does not already have the context level.
        if (!$DB->record_exists('role_context_levels', ['roleid' => $roleid, 'contextlevel' => $contextlevel])) {
            // Add context level to role.
            mtrace($contextlevel . ' ');
            $DB->insert_record(
                'role_context_levels',
                [
                    'roleid' => $roleid,
                    'contextlevel' => $contextlevel,
                ]
            );
        }
    }
    mtrace(')');
}

/**
 * This function extends the course navigation settings
 *
 * @param navigation_node $parentnode
 * @param stdClass $course
 * @param context_course $context
 * @return navigation_node
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_mentor_core_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context)
{

    // Remove the "Copy course" entry from course menu.
    if ($copycoursenode = $parentnode->get('copy')) {
        $copycoursenode->hide();
    }

    return $parentnode;
}

/**
 * Validate the users import csv.
 * Builds the preview and errors tables, if provided.
 *
 * @param array $content CSV content as array
 * @param string $delimitername
 * @param int|null $courseid
 * @param array $preview
 * @param array $errors
 * @param array $warnings
 * @param array $errors
 * @return bool Returns true if it has fatal errors. Otherwise returns false.
 * @throws coding_exception
 * @throws dml_exception
 */
function local_mentor_core_validate_users_csv($content, $delimitername, $courseid = null, &$preview = [], &$errors = [], &$warnings = [])
{
    global $DB, $USER;

    // CSV headers.
    $headers = ['email', 'lastname', 'firstname'];

    if (!isset($preview['list'])) {
        $preview['list'] = [];
    }

    $defaultrole = 'Participant';

    // Add group column for course import.
    if (!is_null($courseid)) {
        $headers[] = 'role';
        $headers[] = 'group';

        $allowedroles = \local_mentor_core\session_api::get_allowed_roles($courseid);
        $defaultrole = $allowedroles['participant']->localname;
    }

    // Fatal errors that stops processing the content.
    $hasfatalerrors = false;

    // Fields pattern.
    $pattern = '/[\/~`\!@#\$%\^&\*\(\)_\+=\{\}\[\]\|;:"\<\>,\.\?\\\]/';
    $emailpattern = '/[\(\)<>";:\\,\[\]]/';

    // No more than 200 entries.
    if (count($content) > 5001) {
        \core\notification::error(get_string('error_too_many_lines', 'local_mentor_core'));
        return true;
    }

    $usersExistData = fetch_users_by_emails_from_content($content, $delimitername);
    if (count($usersExistData)>0) {
        $usernames = array_map(fn($userData) => $userData->username, $usersExistData);
        $usersExistUsernameEmail = get_users_by_usernames($usernames, 'id, suspended, email');
    }
    // Check entries.
    foreach ($content as $index => $line) {

        $line = trim($line ?? '');

        // Skip empty lines.
        if (empty($line)) {
            continue;
        }

        $groupname = null;
        $rolename = $defaultrole;
        $linenumber = $index + 1;
        $columnscsv = str_getcsv(trim($line), csv_import_reader::get_delimiter($delimitername));

        $columns = [];
        foreach ($columnscsv as $column) {

            // Remove whitespaces.
            $column = trim($column);

            // Remove hidden caracters.
            $column = preg_replace('/\p{C}+/u', "", $column);

            $columns[] = $column;
        }

        // Some errors are not fatal errors, so we just ignore the current line.
        $ignoreline = false;

        // Count columns.
        $columnscount = count($columns);

        // Check if CSV header is valid.
        if ($index === 0) {

            // Check for missing headers.
            if (!in_array('email', $columns, true)
                || !in_array('lastname', $columns, true)
                || !in_array('firstname', $columns, true)
            ) {
                $error = is_null($courseid) ? get_string('missing_headers', 'local_user') :
                    get_string('missing_headers', 'local_mentor_core');

                \core\notification::error($error);
                return true;
            }

            // Check if there are data.
            if (count($content) === 1) {
                \core\notification::error(get_string('missing_data', 'local_mentor_core'));
                return true;
            }

            // Init csv columns indexes.
            $emailkey = array_search('email', $columns, true);
            $lastnamekey = array_search('lastname', $columns, true);
            $firstnamekey = array_search('firstname', $columns, true);
            $groupkey = (in_array('group', $columns, true)) ? array_search('group', $columns, true) : null;
            $rolekey = (in_array('role', $columns, true)) ? array_search('role', $columns, true) : null;

            continue;
        }

        // Check if line is empty.
        if ($columnscount === 1 && null === $columns[0]) {
            continue;
        }

        // Check if each lines has at least 3 fields.
        if ($columnscount < 3) {
            $hasfatalerrors = true;
            $errors['list'][] = [
                $linenumber,
                get_string('error_missing_field', 'local_mentor_core'),
            ];

            continue;
        }

        // Check if firstname, lastname and email are missing.
        // Else, check if there is any special chars in firstname or lastname.
        if (in_array('', [$columns[$lastnamekey], $columns[$firstnamekey], $columns[$emailkey]], true)) {
            $errors['list'][] = [
                $linenumber,
                get_string('error_missing_field', 'local_mentor_core'),
            ];

            $ignoreline = true;
        } else if (1 ===
            preg_match($pattern, implode('', [$columns[$lastnamekey], $columns[$firstnamekey] ?? ''])
            ) || 1 === preg_match($emailpattern, $columns[$emailkey])) {
            $errors['list'][] = [
                $linenumber,
                get_string('error_specials_chars', 'local_mentor_core'),
            ];

            $ignoreline = true;
        }

        // Check if email field is valid.
        if (isset($columns[$emailkey]) && !$ignoreline
            && (1 === preg_match($emailpattern, $columns[$emailkey]) ||
                false === filter_var($columns[$emailkey], FILTER_VALIDATE_EMAIL))
        ) {

            $errors['list'][] = [
                $linenumber,
                get_string('invalid_email', 'local_mentor_core'),
            ];

            $ignoreline = true;
        }

        // Check if group exists, if provided.
        if (isset($columns[$groupkey]) && '' !== $columns[$groupkey] && null !== $courseid) {
            $groupid = groups_get_group_by_name($courseid, $columns[$groupkey]);

            if (false === $groupid && !isset($warnings['groupsnotfound'][$columns[$groupkey]])) {
                $warnings['groupsnotfound'][$columns[$groupkey]] = $columns[$groupkey];

                $warnings['list'][] = [
                    $linenumber,
                    get_string('invalid_groupname', 'local_mentor_core', $columns[$groupkey]),
                ];
            }

            $groupname = $columns[$groupkey];
        }

        $definedrole = null;

        // Check if role exists, if provided.
        if (isset($columns[$rolekey]) && '' !== $columns[$rolekey] && null !== $courseid) {

            $rolename = $columns[$rolekey];

            $rolefound = false;

            // Check if the role exists.
            foreach ($allowedroles as $allowedrole) {
                if (
                    (strtolower($allowedrole->localname) == strtolower($rolename)) ||
                    (strtolower($allowedrole->name) == strtolower($rolename))
                ) {
                    $rolefound = true;
                    $definedrole = $allowedrole;
                }
            }
            if (!$rolefound) {
                $errors['rolenotfound'][$rolename] = $rolename;

                $errors['list'][] = [
                    $linenumber,
                    get_string('invalid_role', 'local_mentor_core', $rolename),
                ];

                $ignoreline = true;
            }
        }

        // Check if user exists.
        if (false === $ignoreline && isset($columns[$emailkey], $preview['validforcreation'])) {
            if (isset($preview["useridentified"])) {
                $preview["useridentified"]++;
            }

            $email = $columns[$emailkey];

            $countMatchingEmails = 0;
            $countMatchingEmails = count(array_filter($usersExistData, fn($userData) => $userData->email === $email));

            if ($countMatchingEmails > 1) {
                $warnings['list'][] = [
                    $linenumber,
                    get_string(
                        is_null($courseid) ? 'user_already_exists' : 'email_already_used',
                        'local_mentor_core',
                        $email
                    ),
                ];

                $ignoreline = true;
            }

            // If the user exists, check if an other user as an email equals to the username.
            if ($countMatchingEmails == 1) {
                $u = (array_values(array_filter($usersExistData, fn($userData) => $userData->email === $email))[0] ?? null);

                $users = array_filter($usersExistUsernameEmail, fn($userData) => $userData->email === $email);

                if(count($users) >= 2) {
                    $warnings['list'][] = [
                        $linenumber,
                        get_string('email_already_used', 'local_mentor_core'),
                    ];

                    $ignoreline = true;
                }

                // The user must be reactivated.
                if (!$ignoreline && $u->suspended == 1) {
                    $preview['validforreactivation'][$email] = $u;

                    $warnings['list'][] = [
                        $linenumber,
                        get_string('warning_user_suspended', 'local_mentor_core'),
                    ];
                }

                // The user exists, now check if he's enrolled.
                if (!is_null($definedrole) && false !== $definedrole) {

                    $user = current($users);
                    $oldroles = profile_api::get_course_roles($user->id, $courseid);

                    // User is enrolled.
                    if (!empty($oldroles) && !isset($oldroles[$definedrole->id])) {

                        $strparams = new stdClass();
                        $strparams->newrole = $definedrole->localname;
                        $strparams->oldroles = '';
                        foreach ($oldroles as $oldrole) {
                            $strparams->oldroles .= $allowedroles[$oldrole->shortname]->localname . ',';
                        }
                        $strparams->oldroles = substr($strparams->oldroles, 0, -1);

                        // If the local user is a trainer.
                        // he/she cannot lower his/her privileges as a participant.
                        // Else, the role can be changed for him and the other users.
                        if (($USER->id === $user->id) &&
                            $strparams->newrole === $allowedroles['participant']->localname &&
                            $strparams->oldroles === $allowedroles['formateur']->localname) {

                            $errors['list'][] = [
                                $linenumber,
                                get_string('loseprivilege', 'local_mentor_core'),
                            ];

                            $ignoreline = true;
                        } else {
                            $warnings['newrole'][$columns[$rolekey]] = $columns[$rolekey];

                            $warnings['list'][] = [
                                $linenumber,
                                get_string('newrole', 'local_mentor_core', $strparams),
                            ];
                        }
                    }
                }
            }

            // User doesn't exists or is suspended.
            if (false === $ignoreline && $countMatchingEmails === 0 && !isset($preview['validforreactivation'][$email])) {
                $preview['validforcreation']++;
                    $warnings['list'][] = [
                        $linenumber,
                        get_string('usercreatandenrol', 'local_mentor_core', $email),
                    ];
            }
        }

        // Add the valid lines to the preview list.
        if (false === $ignoreline) {
            if (isset($preview['validlines'])) {
                $preview['validlines']++;
            }

            $newline = [
                'linenumber' => $linenumber,
                'lastname' => $columns[$lastnamekey],
                'firstname' => $columns[$firstnamekey],
                'email' => strtolower($columns[$emailkey]),
            ];

            // Add extras fields for session import.
            if (!is_null($courseid)) {
                $newline['role'] = $rolename;
                $newline['groupname'] = $groupname;
            }

            $preview['list'][] = $newline;
        }
  
    }

    if (count($preview['list']) === 0 && (!isset($preview['validforreactivation']) || count($preview['validforreactivation']) === 0)) {
        $hasfatalerrors = true;
    }

    return $hasfatalerrors;
}

 /**
     * Fetch user count for each email in the content.
     *
     * @param array $content CSV content as array
     * @param array $columns CSV header columns
     * @return array Returns an array with email as key and user object as value
     */
 function fetch_users_by_emails_from_content($content, $delimitername) {
        $emails = [];
        

        foreach ($content as $index => $line) {

            $line = trim($line ?? '');
    
            // Skip empty lines.
            if (empty($line)) {
                continue;
            }
    
 
            $columnscsv = str_getcsv(trim($line), csv_import_reader::get_delimiter($delimitername));
    
            $columns = [];
            foreach ($columnscsv as $column) {
    
                // Remove whitespaces.
                $column = trim($column);
    
                // Remove hidden caracters.
                $column = preg_replace('/\p{C}+/u', "", $column);
    
                $columns[] = $column;
            }
    
            // Check if CSV header is valid.
            if ($index === 0) {
                // Check for missing headers.
                if (in_array('email', $columns, true) && count($content) > 1) {                   
                // Init csv columns indexes.
                $emailkey = array_search('email', $columns, true);
                }
                continue;
            }

            if(isset($emailkey) && isset($columns[$emailkey])) {
                $email = $columns[$emailkey];
                $emails[] = $email;          
            }           
           
        }       

        if (count($emails) > 0) {
            return get_users_by_emails($emails, '', 'id, email, username, suspended');
        }
        return [];
    }

/**
 * Enrol users to the session. Create and enrol if a user doesn't exist.
 *
 * @param int $courseid
 * @param array $userslist
 * @param array $userstoreactivate
 * @param bool $areexternals
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function local_mentor_core_enrol_users_csv($courseid, $userslist = [], $userstoreactivate = [])
{
    global $DB, $CFG;

    require_once($CFG->dirroot . '/group/lib.php');

    $session = local_mentor_core\session_api::get_session_by_course_id($courseid);

    $allowedroles = \local_mentor_core\session_api::get_allowed_roles($courseid);

    // Reactivate user accounts.
    foreach ($userstoreactivate as $usertoreactivate) {

        $email = $usertoreactivate['email'];
        $user = get_suspended_user_by_email($email);

        // Check if user exists.
        if ($user === false) {
            continue;
        }

        $profile = profile_api::get_profile($user, true);
        $profile->reactivate();
    }

    $db = \local_mentor_core\database_interface::get_instance();
    $entity = $db->get_main_course_category_data_by_course_id($courseid);

    $entityobject = new \stdClass();
    $entityobject->id = $entity->id;
    $entityobject->name = $entity->name;

    foreach ($userslist as $index => $line) {
        $user = get_user_by_email($line['email'], 'id');

        // User not found : account creation.
        if (false === $user) {
            $user = new stdClass();
            $user->lastname = $line['lastname'];
            $user->firstname = $line['firstname'];
            $user->email = $line['email'];
            $user->username = local_mentor_core_mail_to_username($line['email']);
            $user->password = 'to be generated';
            $user->mnethostid = 1;
            $user->confirmed = 1;
            if (isset($line['auth'])) {
                $user->auth = $line['auth'];
            }

            $otherdata = json_encode(['entity' => $entityobject]);

            try {
                $user->id = local_mentor_core\profile_api::create_user($user, $otherdata);
            } catch (moodle_exception $e) {

                \core\notification::error(
                    get_string('error_line', 'local_mentor_core', $index + 1)
                    . ' : ' . $e->getMessage() . '. '
                    . get_string('error_ignore_line', 'local_mentor_core')
                );

                continue;
            }

            $user = $DB->get_record('user', ['id' => $user->id]);
        }

        // Define the file role.
        // Set default role.
        $role = 'participant';

        // Get the role shortname from the role defined in the csv file.
        if (
            isset($line['role']) &&
            null !== $line['role']
        ) {

            $lowerrole = strtolower($line['role']);

            foreach ($allowedroles as $allowedrole) {
                if (
                    (strtolower($allowedrole->localname) == $lowerrole) ||
                    (strtolower($allowedrole->name) == $lowerrole)
                ) {
                    $role = $allowedrole->shortname;
                }
            }

        }

        // If user is not already enrolled, enrol him.
        if (true !== $session->user_is_enrolled($user->id)) {

            $dbrole = $DB->get_record('role', ['shortname' => $role]);

            $enrolmentresult = enrol_try_internal_enrol($courseid, $user->id, $dbrole->id);

            // Set user role.
            profile_api::role_assign($role, $user->id, context_course::instance($courseid)->id);
        } else if (
            isset($line['role']) &&
            null !== $line['role'] &&
            $dbrole = $DB->get_record('role', ['shortname' => $role])
        ) {
            // If the user is already enrolled, update his role if necessary.

            $oldroles = profile_api::get_course_roles($user->id, $courseid);
            // THe CSV file define a new role, so unassign all other roles and assign the new role.
            if (!isset($oldroles[$dbrole->id])) {

                $params = ['userid' => $user->id, 'contextid' => context_course::instance($courseid)->id];
                role_unassign_all($params);

                enrol_try_internal_enrol($courseid, $user->id, $dbrole->id);

                // Set user role.
                profile_api::role_assign($role, $user->id, context_course::instance($courseid)->id);
            }

        }

        // Add user to group, if given.
        if (null !== $line['groupname']) {

            // Create the group if it does not exist.
            if (!$groupid = groups_get_group_by_name($courseid, $line['groupname'])) {

                $data = new stdClass();
                $data->name = $line['groupname'];
                $data->timecreated = time();
                $data->courseid = $courseid;
                $groupid = groups_create_group($data);
            }

            // Add the user into the group.
            groups_add_member($groupid, $user->id);
        }
    }

    return true;
}

/**
 * Create users by csv content
 *
 * @param array $userslist users that must be created
 * @param array $userstoreactivate users that must be reactivated
 * @param null $entityid
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_mentor_core_create_users_csv($userslist = [], $userstoreactivate = [], $entityid = null)
{
    global $DB, $CFG, $PAGE;
    $entity = null;
    $cds = new categories_domains_service();       
    // Reactivate user accounts.
    foreach ($userstoreactivate as $usertoreactivate) {

        $email = $usertoreactivate['email'];
        $user = get_suspended_user_by_email($email);

        // Check if user exists.
        if ($user === false) {
            continue;
        }

        $profile = profile_api::get_profile($user, true);
        $profile->reactivate();
    }

    $entityObject = null;
    if ($entityid !== null) {
        $entity = \local_mentor_core\entity_api::get_entity($entityid);
        if ($entityid !== null) {
            $entityObject = new \stdClass();
            $entityObject->id = $entityid;
            $entityObject->name = $entity->name;
        }
    }

    foreach ($userslist as $index => $line) {
        $email = $line['email'];
        $user = get_user_by_email($email, 'id');

        // User not found : account creation.
        if (false === $user) {
            $user = new stdClass();
            $user->lastname = $line['lastname'];
            $user->firstname = $line['firstname'];
            $user->email = $email;
            $user->username = local_mentor_core_mail_to_username($email);
            $user->password = 'to be generated';
            $user->mnethostid = 1;
            $user->confirmed = 1;

            if (isset($line['auth'])) {
                $user->auth = $line['auth'];
            }

            //set user second entity
            $user->profile_field_secondaryentities = local_mentor_core_set_secondary_entities($entity, $email);          

            $otherdata = $entityObject !== null ? json_encode(['entity' => $entityObject]) : null;

            try {  
                $user->id = local_mentor_core\profile_api::create_user($user, $otherdata);
            } catch (moodle_exception $e) {
                \core\notification::error(
                    get_string('error_line', 'local_mentor_core', $index + 1)
                    . ' : ' . $e->getMessage() . '. '
                    . get_string('error_ignore_line', 'local_mentor_core')
                );

                continue;
            }
        } else if ($entityid !== null) {
            // User update.
            $dbinterface = \local_mentor_core\database_interface::get_instance();

            // Get main and secondary entity user.
            $usersecondaryentities = $dbinterface->get_profile_field_value($user->id, 'secondaryentities');
            // Create old user data object for the update event.
            $olduserdata = new \stdClass();
            $olduserdata->id = $user->id;
            $olduserdata->profile_field_secondaryentities = $dbinterface->get_secondaryentity_names_array($usersecondaryentities);

            // Create new user data object for the update event.
            $newuserdata = new \stdClass();
            $newuserdata->id = $user->id;
            $newuserdata->profile_field_secondaryentities = local_mentor_core_set_secondary_entities($entity, $email);
            $profile = profile_api::get_profile($user->id);
            $profile->set_profile_field('secondaryentities', implode(',', $newuserdata->profile_field_secondaryentities));

            // Create data for user_updated event.
            // WARNING : other event data must be compatible with json encoding.
            $otherdata = json_encode(
                [
                    'old' => $olduserdata,
                    'new' => $newuserdata,
                    'entity' => $entityObject
                ]
            );
            $data = [
                'objectid' => $newuserdata->id,
                'relateduserid' => $newuserdata->id,
                'context' => \context_user::instance($newuserdata->id),
                'other' => $otherdata,
            ];

            // Create and trigger event.
            \core\event\user_updated::create($data)->trigger();
        }
    }

    return true;
}
/**
 * Set secondary entities for a user based on the entity and email.
 *
 * @param \local_mentor_core\entity $entity
 * @param string $email
 * @param int $entityid
 * @return array
 */
function local_mentor_core_set_secondary_entities($entity, $email) : array
{
    if ($entity) {
        $domain = new domain_name();
        $domain->set_user_domain($email);
        $domain->course_categories_id = $entity->id;

        return (($entity->can_be_main_entity() && !$domain->is_exist() ) || 
                                                (!$entity->can_be_main_entity())) 
                                                ? [$entity->name] 
                                                : [];
    }
    return [];
}

/**
 * Toggle user externale role.
 *
 * @param int $userid
 * @param bool $areexternals
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function toggle_external_user_role($userid, $areexternals = false)
{
    $method = $areexternals ? 'role_assign' : 'role_unassign';
    \local_mentor_core\profile_api::$method('utilisateurexterne', $userid, context_system::instance()->id);
}

/**
 * List of old training status name changes
 *  key   => old name
 *  value => new name
 *
 * @return array
 */
function local_mentor_core_get_list_status_name_changes()
{
    return [
        'dr' => 'draft',
        'tp' => 'template',
        'ec' => 'elaboration_completed',
        'ar' => 'archived',
    ];
}

/**
 * Get the specialized HTML to be displayed in the footer
 *
 * @param string $html the default footer html
 * @return mixed
 */
function local_mentor_core_get_footer_specialization($html)
{
    global $CFG;

    require_once($CFG->dirroot . '/local/mentor_core/classes/specialization.php');

    // The footer content can be specialized by specialization plugins.
    $specialization = \local_mentor_core\specialization::get_instance();

    return $specialization->get_specialization('get_footer', $html);
}

/**
 * List of profile fields
 *
 * @return array[]
 */
function local_mentor_core_get_profile_fields_values()
{
    // Colonnes: shortname , name, datatype, description, descriptionformat, categoryid,
    // sortorder, required, locked, visible, forceunique, signup, defaultdata, defaultdataformat, param1.
    return [
        [
            'mainentity', 'Entité de rattachement', 'menu', '', 1, 1, 2, 1, 0, 2, 0, 0, '', 0,
            'local_mentor_core_list_entities',
        ],
    ];
}

/**
 * Create object from array row
 *
 * @param $values
 * @return stdClass
 */
function local_mentor_core_create_field_object_to_use($values)
{
    $field = new stdClass();
    $field->shortname = array_key_exists(0, $values) ? $values[0] : null;
    $field->name = array_key_exists(1, $values) ? $values[1] : null;
    $field->datatype = array_key_exists(2, $values) ? $values[2] : null;
    $field->description = array_key_exists(3, $values) ? $values[3] : null;
    $field->descriptionformat = array_key_exists(4, $values) ? $values[4] : null;
    $field->categoryid = array_key_exists(5, $values) ? $values[5] : null;
    $field->sortorder = array_key_exists(6, $values) ? $values[6] : null;
    $field->required = array_key_exists(7, $values) ? $values[7] : null;
    $field->locked = array_key_exists(8, $values) ? $values[8] : null;
    $field->visible = array_key_exists(9, $values) ? $values[9] : null;
    $field->forceunique = array_key_exists(10, $values) ? $values[10] : null;
    $field->signup = array_key_exists(11, $values) ? $values[11] : null;
    $field->defaultdata = array_key_exists(12, $values) ? $values[12] : null;
    $field->defaultdataformat = array_key_exists(13, $values) ? $values[13] : null;

    // If it begin with "list_", excute associated funtion.
    // Else insert value.
    if (array_key_exists(14, $values)) {
        preg_match('/^local_mentor_core_list_/i', $values[14]) ? $field->param1 = call_user_func($values[14]) :
            $values[14];
    } else {
        $field->param1 = null;
    }

    $field->param2 = array_key_exists(15, $values) ? $values[15] : null;
    $field->param3 = array_key_exists(16, $values) ? $values[16] : null;
    $field->param4 = array_key_exists(17, $values) ? $values[17] : null;
    $field->param5 = array_key_exists(18, $values) ? $values[18] : null;

    return $field;
}

function local_mentor_core_generate_user_fields()
{
    global $DB;

    $fields = local_mentor_core_get_profile_fields_values();

    foreach ($fields as $value) {
        $field = local_mentor_core_create_field_object_to_use($value);

        if ($dbfield = $DB->get_record('user_info_field', ['shortname' => $field->shortname], 'id')) {
            $field->id = $dbfield->id;
            $field->id = $DB->update_record('user_info_field', $field);
        } else {
            $field->id = $DB->insert_record('user_info_field', $field);
        }
    }
}

/**
 * Update the main and secondary entities profile fields with the current list of entities
 *
 * @return bool
 * @throws dml_exception
 * @throws moodle_exception
 */
function local_mentor_core_update_entities_list()
{
    global $CFG, $DB;

    require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');

    // Main entity profile fields.
    if (!$field = $DB->get_record('user_info_field', ['shortname' => 'mainentity'])) {
        throw new \moodle_exception('shortnamedoesnotexist', 'local_profile', '', 'mainentity');
    }

    $field->param1 = \local_mentor_core\entity_api::get_entities_list(true, true, false, false);
    $mainentityupdate = $DB->update_record('user_info_field', $field);

    // Secondary entity profile fields.
    if (!$field = $DB->get_record('user_info_field', ['shortname' => 'secondaryentities'])) {
        throw new \moodle_exception('shortnamedoesnotexist', 'local_profile', '', 'secondaryentities');
    }

    $field->param1 = \local_mentor_core\entity_api::get_entities_list(true, true, false);
    $secondaryentitiesupdate = $DB->update_record('user_info_field', $field);

    return $mainentityupdate && $secondaryentitiesupdate;
}

/**
 * Get list of entities
 *
 * @return string
 * @throws dml_exception
 * @throws moodle_exception
 */
function local_mentor_core_list_entities()
{
    global $CFG;
    require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');

    // Get entities list.
    return \local_mentor_core\entity_api::get_entities_list(false, true);
}

// Completion tracking is disabled for this activity.
// This is a completion tracking option per-activity  (course_modules/completion).
defined('COMPLETION_TRACKING_NONE') || define('COMPLETION_TRACKING_NONE', 0);

// The user has not completed this activity.
// This is a completion state value (course_modules_completion/completionstate).
defined('COMPLETION_INCOMPLETE') || define('COMPLETION_INCOMPLETE', 0);

// The user has completed this activity but their grade is less than the pass mark.
// This is a completion state value (course_modules_completion/completionstate).
defined('COMPLETION_COMPLETE_FAIL') || define('COMPLETION_COMPLETE_FAIL', 3);

// The user has completed this activity. It is not specified whether they have passed or failed it.
// This is a completion state value (course_modules_completion/completionstate).
defined('COMPLETION_COMPLETE') || define('COMPLETION_COMPLETE', 1);

// The user has completed this activity with a grade above the pass mark.
// This is a completion state value (course_modules_completion/completionstate).
defined('COMPLETION_COMPLETE_PASS') || define('COMPLETION_COMPLETE_PASS', 2);

/**
 * Finds gradebook exclusions for students in a course
 *
 * @param int $courseid The ID of the course containing grade items
 * @param int $userid The ID of the user whos grade items are being retrieved
 * @return array of exclusions as activity-user pairs
 */
function local_mentor_core_completion_find_exclusions($courseid, $userid = null)
{
    global $DB;

    // Get gradebook exclusions for students in a course.
    $query = "SELECT g.id, " . $DB->sql_concat('i.itemmodule', "'-'", 'i.iteminstance', "'-'", 'g.userid') . " as exclusion
              FROM {grade_grades} g, {grade_items} i
              WHERE i.courseid = :courseid
                AND i.id = g.itemid
                AND g.excluded <> 0";

    $params = ['courseid' => $courseid];
    if (!is_null($userid)) {
        $query .= " AND g.userid = :userid";
        $params['userid'] = $userid;
    }
    $results = $DB->get_records_sql($query, $params);

    // Create exclusions list.
    $exclusions = [];
    foreach ($results as $value) {
        $exclusions[] = $value->exclusion;
    }

    return $exclusions;
}

/**
 * Returns the activities with completion set in current course
 *
 * @param int $courseid ID of the course
 * @return array Activities with completion settings in the course
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_mentor_core_completion_get_activities($courseid)
{
    $modinfo = get_fast_modinfo($courseid, -1);
    $sections = $modinfo->get_sections();
    $activities = [];

    // Create activities list with completion set.
    foreach ($modinfo->instances as $module => $instances) {
        $modulename = get_string('pluginname', $module);
        foreach ($instances as $cm) {
            if ($cm->completion != COMPLETION_TRACKING_NONE) {
                $activities[] = [
                    'type' => $module,
                    'modulename' => $modulename,
                    'id' => $cm->id,
                    'instance' => $cm->instance,
                    'name' => format_string($cm->name),
                    'expected' => $cm->completionexpected,
                    'section' => $cm->sectionnum,
                    'position' => array_search($cm->id, $sections[$cm->sectionnum]),
                    'url' => !is_null($cm->url) && method_exists($cm->url, 'out') ? $cm->url->out() : '',
                    'context' => $cm->context,
                    'icon' => $cm->get_icon_url(),
                    'available' => $cm->available,
                ];
            }
        }
    }

    return $activities;
}

/**
 * Filters activities that a user cannot see due to grouping constraints
 *
 * @param array $activities The possible activities that can occur for modules
 * @param array $userid The user's id
 * @param int $courseid the course for filtering visibility
 * @param array $exclusions Assignment exemptions for students in the course
 * @return array The array with restricted activities removed
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_mentor_core_completion_filter_activities($activities, $userid, $courseid, $exclusions)
{
    global $CFG;
    $filteredactivities = [];
    $modinfo = get_fast_modinfo($courseid, $userid);
    $coursecontext = CONTEXT_COURSE::instance($courseid);

    // Keep only activities that are visible.
    foreach ($activities as $activity) {

        $coursemodule = $modinfo->cms[$activity['id']];

        // Check visibility in course.
        if (!$coursemodule->visible && !has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
            continue;
        }

        // Check availability, allowing for visible, but not accessible items.
        if (!empty($CFG->enableavailability)) {
            if (has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
                $activity['available'] = true;
            } else {
                if (isset($coursemodule->available) && !$coursemodule->available && empty($coursemodule->availableinfo)) {
                    continue;
                }
                $activity['available'] = $coursemodule->available;
            }
        }

        // Check for exclusions.
        if (in_array($activity['type'] . '-' . $activity['instance'] . '-' . $userid, $exclusions)) {
            continue;
        }

        // Save the visible event.
        $filteredactivities[] = $activity;
    }
    return $filteredactivities;
}

/**
 * Finds submissions for a user in a course
 * This code is a copy of block_completion_progress
 *
 * @param int $courseid ID of the course
 * @param int $userid ID of user in the course, or 0 for all
 * @return array Course module IDs submissions
 * @throws dml_exception
 */
function local_mentor_core_completion_get_user_course_submissions($courseid, $userid = 0)
{
    global $DB, $CFG;

    require_once($CFG->dirroot . '/mod/quiz/lib.php');

    $submissions = [];

    // Set courseid in query for different activities.
    $params = [
        'courseid' => $courseid,
    ];

    // Set userid in query for different activities.
    if ($userid) {
        $assignwhere = 'AND s.userid = :userid';
        $workshopwhere = 'AND s.authorid = :userid';
        $quizwhere = 'AND qa.userid = :userid';

        $params += [
            'userid' => $userid,
        ];
    } else {
        $assignwhere = '';
        $workshopwhere = '';
        $quizwhere = '';
    }

    // Queries to deliver instance IDs of activities with submissions by user.
    $queries = [
        [
            /* Assignments with individual submission, or groups requiring a submission per user,
            or ungrouped users in a group submission situation. */
            'module' => 'assign',
            'query' => "SELECT " . $DB->sql_concat('s.userid', "'-'", 'c.id') . " AS id,
                         s.userid, c.id AS cmid,
                         MAX(CASE WHEN ag.grade IS NULL OR ag.grade = -1 THEN 0 ELSE 1 END) AS graded
                      FROM {assign_submission} s
                        INNER JOIN {assign} a ON s.assignment = a.id
                        INNER JOIN {course_modules} c ON c.instance = a.id
                        INNER JOIN {modules} m ON m.name = 'assign' AND m.id = c.module
                        LEFT JOIN {assign_grades} ag ON ag.assignment = s.assignment
                              AND ag.attemptnumber = s.attemptnumber
                              AND ag.userid = s.userid
                      WHERE s.latest = 1
                        AND s.status = 'submitted'
                        AND a.course = :courseid
                        AND (
                            a.teamsubmission = 0 OR
                            (a.teamsubmission <> 0 AND a.requireallteammemberssubmit <> 0 AND s.groupid = 0) OR
                            (a.teamsubmission <> 0 AND a.preventsubmissionnotingroup = 0 AND s.groupid = 0)
                        )
                        $assignwhere
                    GROUP BY s.userid, c.id",
            'params' => [],
        ],
        [
            // Assignments with groups requiring only one submission per group.
            'module' => 'assign',
            'query' => "SELECT " . $DB->sql_concat('s.userid', "'-'", 'c.id') . " AS id,
                         s.userid, c.id AS cmid,
                         MAX(CASE WHEN ag.grade IS NULL OR ag.grade = -1 THEN 0 ELSE 1 END) AS graded
                      FROM {assign_submission} gs
                        INNER JOIN {assign} a ON gs.assignment = a.id
                        INNER JOIN {course_modules} c ON c.instance = a.id
                        INNER JOIN {modules} m ON m.name = 'assign' AND m.id = c.module
                        INNER JOIN {groups_members} s ON s.groupid = gs.groupid
                        LEFT JOIN {assign_grades} ag ON ag.assignment = gs.assignment
                              AND ag.attemptnumber = gs.attemptnumber
                              AND ag.userid = s.userid
                      WHERE gs.latest = 1
                        AND gs.status = 'submitted'
                        AND gs.userid = 0
                        AND a.course = :courseid
                        AND (a.teamsubmission <> 0 AND a.requireallteammemberssubmit = 0)
                        $assignwhere
                    GROUP BY s.userid, c.id",
            'params' => [],
        ],
        [
            'module' => 'workshop',
            'query' => "SELECT " . $DB->sql_concat('s.authorid', "'-'", 'c.id') . " AS id,
                           s.authorid AS userid, c.id AS cmid,
                           1 AS graded
                         FROM {workshop_submissions} s, {workshop} w, {modules} m, {course_modules} c
                        WHERE s.workshopid = w.id
                          AND w.course = :courseid
                          AND m.name = 'workshop'
                          AND m.id = c.module
                          AND c.instance = w.id
                          $workshopwhere
                      GROUP BY s.authorid, c.id",
            'params' => [],
        ],
        [
            // Quizzes with 'first' and 'last attempt' grading methods.
            'module' => 'quiz',
            'query' => "SELECT " . $DB->sql_concat('qa.userid', "'-'", 'c.id') . " AS id,
                       qa.userid, c.id AS cmid,
                       (CASE WHEN qa.sumgrades IS NULL THEN 0 ELSE 1 END) AS graded
                     FROM {quiz_attempts} qa
                       INNER JOIN {quiz} q ON q.id = qa.quiz
                       INNER JOIN {course_modules} c ON c.instance = q.id
                       INNER JOIN {modules} m ON m.name = 'quiz' AND m.id = c.module
                    WHERE qa.state = 'finished'
                      AND q.course = :courseid
                      AND qa.attempt = (
                        SELECT CASE WHEN q.grademethod = :gmfirst THEN MIN(qa1.attempt)
                                    WHEN q.grademethod = :gmlast THEN MAX(qa1.attempt) END
                        FROM {quiz_attempts} qa1
                        WHERE qa1.quiz = qa.quiz
                          AND qa1.userid = qa.userid
                          AND qa1.state = 'finished'
                      )
                      $quizwhere",
            'params' => [
                'gmfirst' => 3,
                'gmlast' => 4,
            ],
        ],
        [
            // Quizzes with 'maximum' and 'average' grading methods.
            'module' => 'quiz',
            'query' => "SELECT " . $DB->sql_concat('qa.userid', "'-'", 'c.id') . " AS id,
                       qa.userid, c.id AS cmid,
                       MIN(CASE WHEN qa.sumgrades IS NULL THEN 0 ELSE 1 END) AS graded
                     FROM {quiz_attempts} qa
                       INNER JOIN {quiz} q ON q.id = qa.quiz
                       INNER JOIN {course_modules} c ON c.instance = q.id
                       INNER JOIN {modules} m ON m.name = 'quiz' AND m.id = c.module
                    WHERE (q.grademethod = :gmmax OR q.grademethod = :gmavg)
                      AND qa.state = 'finished'
                      AND q.course = :courseid
                      $quizwhere
                   GROUP BY qa.userid, c.id",
            'params' => [
                'gmmax' => 1,
                'gmavg' => 2,
            ],
        ],
    ];

    // Create user's submissions list in a course.
    foreach ($queries as $spec) {
        $results = $DB->get_records_sql($spec['query'], $params + $spec['params']);
        foreach ($results as $id => $obj) {
            $submissions[$id] = $obj;
        }
    }

    ksort($submissions);

    return $submissions;
}

/**
 * Checks the progress of the user's activities/resources.
 *
 * @param array $activities The activities with completion in the course
 * @param int $userid The user's id
 * @param stdClass $course The course instance
 * @param array $submissions Submissions information, keyed by 'userid-cmid'
 * @return array   an describing the user's attempts based on module+instance identifiers
 */
function local_mentor_core_completion_get_progress($activities, $userid, $course, $submissions)
{
    $completions = [];
    // Get completion information for a course.
    $completioninfo = new completion_info($course);
    $cm = new stdClass();

    // Creates a list of user's progress for activities/resources.
    foreach ($activities as $activity) {
        $cm->id = $activity['id'];
        $completion = $completioninfo->get_data($cm, true, $userid);
        $submission = $submissions[$userid . '-' . $cm->id] ?? null;

        if ($completion->completionstate == COMPLETION_INCOMPLETE && $submission) {
            // The user has not completed this activity.
            $completions[$cm->id] = 'submitted';
        } else if ($completion->completionstate == COMPLETION_COMPLETE_FAIL && $submission
            && !$submission->graded) {
            // The user has completed this activity but their grade is less than the pass mark.
            $completions[$cm->id] = 'submitted';
        } else {
            // Other completion.
            $completions[$cm->id] = $completion->completionstate;
        }
    }

    return $completions;
}

/**
 * Calculates an overall percentage of progress
 *
 * @param stdClass $course
 * @param int $userid
 * @return false|int  Progress value as a percentage
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function local_mentor_core_completion_get_progress_percentage($course, $userid, $refresh = false)
{
    // Check completion is enabled.
    if (!local_mentor_core_check_if_completion_enabled($course)) {
        return false;
    }

    // Get database interface.
    $db = \local_mentor_core\database_interface::get_instance();

    // Refresh data.
    if ($refresh) {
        // Calculate course user completion.
        $usercompletion = local_mentor_core_calculate_completion_get_progress_percentage($course, $userid);

        // Set course user completion data.
        $db->set_user_course_completion($userid, $course->id, $usercompletion);

        return $usercompletion;
    }

    // Get course user completion data.
    if ($usercompletion = $db->get_user_course_completion($userid, $course->id)) {
        return !is_null($usercompletion->completion) ? $usercompletion->completion : false;
    }

    // Calculate course user completion.
    $usercompletion = local_mentor_core_calculate_completion_get_progress_percentage($course, $userid);

    // Set course user completion data.
    $db->set_user_course_completion($userid, $course->id, $usercompletion);

    return $usercompletion;
}

/**
 * Check course completion is enabled.
 *
 * @param $course
 * @return bool
 */
function local_mentor_core_check_if_completion_enabled($course)
{
    global $CFG;

    // Check completion is enable.
    if (isset($course->enablecompletion)) {
        // First check global completion.
        if (!isset($CFG->enablecompletion) || $CFG->enablecompletion == COMPLETION_DISABLED) {
            return false;
        }

        // Check course completion.
        if ($course->enablecompletion == COMPLETION_DISABLED) {
            return false;
        }
    } else {
        $completion = new completion_info($course);
        if (!$completion->is_enabled()) {
            return false;
        }
    }

    return true;
}

function local_mentor_core_calculate_completion_get_progress_percentage($course, $userid)
{

    // Get gradebook exclusions list for students in a course.
    $exclusions = local_mentor_core_completion_find_exclusions($course->id, $userid);

    // Get activities list with completion set in current course.
    $activities = local_mentor_core_completion_get_activities($course->id);

    // Filters activities that a user cannot see due to grouping constraints.
    $activities = local_mentor_core_completion_filter_activities($activities, $userid, $course->id, $exclusions);
    if (empty($activities)) {
        return false;
    }

    // Finds submissions for a user in a course.
    $submissions = local_mentor_core_completion_get_user_course_submissions($course->id, $userid);

    // Checks the progress of the user's activities/resources.
    $completions = local_mentor_core_completion_get_progress($activities, $userid, $course, $submissions);

    // Calculates an overall percentage of progress.
    $completecount = 0;
    foreach ($activities as $activity) {
        if (
            $completions[$activity['id']] == COMPLETION_COMPLETE ||
            $completions[$activity['id']] == COMPLETION_COMPLETE_PASS
        ) {
            $completecount++;
        }
    }
    $progressvalue = $completecount == 0 ? 0 : $completecount / count($activities);

    return (int)floor($progressvalue * 100);
}

/**
 * Resize a picture
 *
 * @param stored_file $file
 * @param int $maxfilewidth
 * @return bool|stored_file
 * @throws file_exception
 * @throws stored_file_creation_exception
 */
function local_mentor_core_resize_picture($file, $maxfilewidth)
{

    if ($maxfilewidth <= 0) {
        return false;
    }

    $content = $file->get_content();

    // Fetch the image information for this image.
    $imageinfo = @getimagesizefromstring($content);
    if (empty($imageinfo)) {
        return false;
    }

    $originalwidth = $imageinfo[0];

    // Check if the picture needs to be resized.
    if ($originalwidth < $maxfilewidth) {
        return false;
    }

    $originalheight = $imageinfo[1];
    $ratio = $originalheight / $originalwidth;

    $newwidth = $maxfilewidth;
    $newheight = (int)round($newwidth * $ratio);

    // Create a resized file.
    $imagedata = $file->generate_image_thumbnail($newwidth, $newheight);

    if (empty($imagedata)) {
        return false;
    }

    // Store the new file in the place of the old one.
    $explodedextension = explode('.', $file->get_filename());
    $ext = end($explodedextension);

    $fs = get_file_storage();
    $record = [
        'contextid' => $file->get_contextid(),
        'component' => $file->get_component(),
        'filearea' => $file->get_filearea(),
        'itemid' => $file->get_itemid(),
        'filepath' => $file->get_filepath(),
        'filename' => basename($file->get_filename(), '.' . $ext) . '.png',
    ];

    // Delete the uploaded file.
    $file->delete();

    // Create a new file.
    return $fs->create_file_from_string($record, $imagedata);
}

/**
 * Decode a csv content
 *
 * @param string $filecontent
 * @return false|mixed|string
 */
function local_mentor_core_decode_csv_content($filecontent)
{

    // Convert ANSI files.
    if (mb_detect_encoding($filecontent, 'utf-8', true) === false) {
        $filecontent = mb_convert_encoding($filecontent, 'utf-8', 'iso-8859-1');
    }

    $filecontent = str_replace(["\r\n", "\r"], "\n", $filecontent);

    // Remove the BOM.
    $filecontent = str_replace("\xEF\xBB\xBF", '', $filecontent);

    // Detect UTF-8.
    if (preg_match('#[\x80-\x{1FF}\x{2000}-\x{3FFF}]#u', $filecontent)) {
        return $filecontent;
    }

    // Detect WINDOWS-1250.
    if (preg_match('#[\x7F-\x9F\xBC]#', $filecontent)) {
        $filecontent = iconv('WINDOWS-1250', 'UTF-8', $filecontent);
    }

    // Assume ISO-8859-2.
    return iconv('ISO-8859-2', 'UTF-8', $filecontent);
}

/**
 * Sort an associative array
 *
 * @param $array
 * @param $attribute
 * @param string $order
 */
function local_mentor_core_sort_array(&$array, $attribute, $order = 'asc')
{

    usort($array, function ($a, $b) use ($attribute, $order) {
        if ($order == 'asc') {
            // Ascending sort.
            return strtolower($b->{$attribute}) <=> strtolower($a->{$attribute});
        } else {
            // Descending sort.
            return strtolower($a->{$attribute}) <=> strtolower($b->{$attribute});
        }
    });
}

/**
 * Check if email is allowed
 *
 * @param string $email
 * @return bool
 */
function local_mentor_core_email_is_allowed($email)
{
    return validate_email($email) && !email_is_not_allowed($email);
}

/**
 * Convert mail to username.
 *
 * @param string $email
 * @return bool
 */
function local_mentor_core_mail_to_username($email)
{
    $db = \local_mentor_core\database_interface::get_instance();

    $notallowcaracter = [
        '[', '!', '#', '$', '%', '&', '\'', '*', '+', '-',
        '/', '=', '?', '^', '.', '{', '|', '}', '~', '`', ']',
    ];

    $newusername = str_replace($notallowcaracter, '_', trim(\core_text::strtolower($email)));

    if (!$db->get_user_by_username($newusername)) {
        return $newusername;
    }

    $i = 1;

    while ($db->get_user_by_username($newusername . $i)) {
        $i++;
    }

    return $newusername . $i;
}

/**
 * Get data for CSV export of available sessions
 *
 * @param int $entityid
 * @return array
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function local_mentor_core_get_available_sessions_csv_data($entityid)
{
    $entity = \local_mentor_core\entity_api::get_entity($entityid);
    $mainentity = $entity->get_main_entity();
    $availablesessions = $mainentity->get_available_sessions_to_catalog();

    $csvdata = [];

    // Set csv header.
    $csvdata[] = [
        'Espace dédié de la formation',
        'Intitulé de la formation',
        'Nom abrégé de la formation',
        'Collections',
        'Formation certifiante',
        'Identifiant SIRH d’origine',
        'Espace dédié de la session',
        'Libellé de la session',
        'Nom abrégé de la session',
        'Public cible',
        'Modalités de l\'inscription',
        'Durée en ligne',
        'Durée en présentiel',
        'Session permanente',
        'Date de début de la session de formation',
        'Date de fin de la session de formation',
        'Modalités de la session',
        'Accompagnement',
        'Nombre maximum de participants',
        'Places disponibles',
    ];

    // Set csv data.
    foreach ($availablesessions as $session) {
        $training = $session->get_training();

        // Set Date Time Zone at France.
        $dtz = new \DateTimeZone('Europe/Paris');

        // Set session start and end date.
        $sessionstartdate = '';
        if (!empty($session->sessionstartdate)) {
            $sessionstartdate = $session->sessionstartdate;
            $startdate = new \DateTime("@$sessionstartdate");
            $startdate->setTimezone($dtz);
            $sessionstartdate = $startdate->format('d/m/Y');
        }

        $sessionenddate = '';
        if (!empty($session->sessionenddate)) {
            $sessionenddate = $session->sessionenddate;
            $enddate = new \DateTime("@$sessionenddate");
            $enddate->setTimezone($dtz);
            $sessionenddate = $enddate->format('d/m/Y');
        }

        $places = $session->get_available_places();
        $placesavailable = is_int($places) && $places < 0 ? 0 : $places;

        $csvdata[] = [
            $training->get_entity()->get_main_entity()->name,
            $training->name,
            $training->shortname,
            $training->get_collections(','),
            $training->certifying === '0' ? 'Non' : 'Oui',
            $training->idsirh,
            $session->get_entity()->get_main_entity()->name,
            $session->fullname,
            $session->shortname,
            $session->publiccible,
            !empty($session->termsregistration) ? get_string($session->termsregistration, 'local_mentor_core') : '',
            $session->onlinesessionestimatedtime ? local_mentor_core_minutes_to_hours($session->onlinesessionestimatedtime) :
                '',
            $session->presencesessionestimatedtime ?
                local_mentor_core_minutes_to_hours($session->presencesessionestimatedtime) : '',
            $session->sessionpermanent == 1 ? 'Oui' : 'Non',
            $sessionstartdate,
            $sessionenddate,
            empty($session->sessionmodalities) ? '' : get_string($session->sessionmodalities, 'local_catalog'),
            $session->accompaniment,
            $session->maxparticipants,
            $placesavailable,
        ];
    }

    return $csvdata;
}

/**
 * Convert a timestamp into hours/minutes format
 *
 * @param int $finaltimesaving
 * @return string
 */
function local_mentor_core_minutes_to_hours($finaltimesaving)
{
    $hours = floor($finaltimesaving / 60);
    $minutes = $finaltimesaving % 60;

    if ($hours < 10) {
        $hours = '0' . $hours;
    }

    if ($hours == 0) {
        if ($minutes < 10) {
            $minutes = '0' . $minutes;
        }
        return $minutes . 'min';
    }

    if ($minutes === 0) {
        return $hours . 'h';
    }

    if ($minutes < 10) {
        $minutes = '0' . $minutes;
    }

    return $hours . 'h' . $minutes;
}

/**
 * Validate the suspend users import csv.
 * Builds the preview and errors tables, if provided.
 *
 * @param array $content CSV content as array
 * @param \local_mentor_core\entity $entity
 * @param array $preview
 * @param array $errors
 * @return bool Returns true if it has fatal errors. Otherwise returns false.
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function local_mentor_core_validate_suspend_users_csv($content, $entity, &$preview = [], &$errors = [])
{
    global $DB;

    // No more than 5000 entries.
    if (count($content) > 5001) {
        \core\notification::error(get_string('error_too_many_lines', 'local_mentor_core'));
        return true;
    }
    // Check if the file header is valid.
    if ($content[0] !== 'email') {
        \core\notification::error('L\'en-tête du fichier est incorrect. L\'en-tête attendu est : "email".');
        return true;
    }
    // Check if there are data.
    if (count($content) === 1) {
        \core\notification::error(get_string('missing_data', 'local_mentor_core'));
        return true;
    }

    if (!isset($preview['list'])) {
        $preview['list'] = [];
    }

    $emailpattern = '/[\(\)<>";:\\,\[\]]/';

    $forbiddenroles = ['participant', 'participantnonediteur', 'concepteur', 'formateur', 'tuteur'];

    // Check entries.
    foreach ($content as $index => $line) {
        $email = strtolower(trim($line));

        $linenumber = $index + 1;

        // Skip the first line.
        if ($index == 0) {
            continue;
        }

        // Skip empty lines.
        if (empty($email)) {
            $errors['list'][] = [
                $linenumber,
                get_string('invalid_email', 'local_mentor_core'),
            ];
            continue;
        }

        // Check if the line contains an email.
        if (1 === preg_match($emailpattern, $email) ||
            false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['list'][] = [
                $linenumber,
                get_string('email_not_valid', 'local_mentor_core'),
            ];

            continue;
        }

        $users = $DB->get_records_sql('
            SELECT *
            FROM {user}
            WHERE email = :email OR username = :username
        ', ['email' => $email, 'username' => local_mentor_core_mail_to_username($email)]);

        // Check the count of users.
        if (count($users) == 0) {
            $errors['list'][] = [
                $linenumber,
                'L\'adresse mél n\'a pas été trouvée. Cette ligne sera ignorée à l\'import.',
            ];

            continue;
        } else if (count($users) > 1) {
            $errors['list'][] = [
                $linenumber,
                get_string('email_already_used', 'local_mentor_core'),
            ];

            continue;
        }

        $user = reset($users);

        // User already suspended.
        if ($user->suspended == 1) {
            $errors['list'][] = [
                $linenumber,
                'Le compte utilisateur est déjà désactivé. Cette ligne sera ignorée à l\'import.',
            ];

            continue;
        }

        $profile = profile_api::get_profile($user);

        $mainentity = $profile->get_main_entity();

        // Check if the user main entity is the same as the selected entity.
        if (!$mainentity || ($entity->id != $mainentity->id)) {

            $errors['list'][] = [
                $linenumber,
                'L\'utilisateur n\'est pas rattaché à l\'espace dédié ' . $entity->get_name() .
                '. Cette ligne sera ignorée à l\'import',
            ];

            continue;
        }

        // Check if the user has an elevated role.
        $highestrole = $profile->get_highest_role();

        if ($highestrole && !in_array($highestrole->shortname, $forbiddenroles)) {
            $errors['list'][] = [
                $linenumber,
                'L\'utilisateur possède un rôle élevé sur la plateforme. Cette ligne sera ignorée à l\'import.',
            ];

            continue;
        }

        // Email is valid, add to preview list.
        $preview['list'][] = ['linenumber' => $linenumber, 'email' => $email];
        if (!isset($preview['validforsuspension'])) {
            $preview['validforsuspension'] = 0;
        }
        $preview['validforsuspension']++;
    }

    // Has fatal error.
    if (count($preview['list']) === 0) {
        return true;
    }

    return false;
}

/**
 * Suspend users
 *
 * @param array $emails
 * @throws dml_exception
 * @throws moodle_exception
 */
function local_mentor_core_suspend_users($emails)
{
    global $DB;

    foreach ($emails as $email) {
        $email = is_array($email) ? $email['email'] : $email;

        // Get user.
        $user = $DB->get_record_sql('SELECT * FROM {user} WHERE email = :email OR username = :username',
            ['email' => $email, 'username' => $email]);

        if (!$user) {
            continue;
        }

        $profile = profile_api::get_profile($user);
        $profile->suspend();
    }

    \core\notification::success(get_string('import_succeeded', 'local_mentor_core'));
}

/**
 * Remove empty tags at the beginning and at the end of an html string
 *
 * @param string $html
 * @return array|string|string[]
 */
function local_mentor_core_clean_html($html)
{

    // Tags to remove.
    $cleanedtags = [
        '<br>',
        '<br/>',
        '<p></p>',
        '<p><br></p>',
        '<p><br/></p>',
        '<p dir="ltr" style="text-align: left;"></p>',
    ];

    // Remove from the beggining.
    $found = true;
    while ($found) {
        $found = false;

        foreach ($cleanedtags as $cleanedtag) {
            // Tag must be replaced.
            if (substr_compare($html, $cleanedtag, 0, strlen($cleanedtag)) === 0) {
                $html = substr_replace($html, '', 0, strlen($cleanedtag));
                $found = true;
            }
        }
    }

    // Remove from the end.
    $found = true;
    while ($found) {
        $found = false;

        foreach ($cleanedtags as $cleanedtag) {
            // Tag must be replaced.
            if (substr_compare($html, $cleanedtag, -strlen($cleanedtag)) === 0) {
                $html = substr_replace($html, '', -strlen($cleanedtag));
                $found = true;
            }
        }
    }

    return $html;
}

/**
 * Check whether a user has particular capabilities in a given context.
 *
 * @param string[] $capabilities
 * @param \context $context
 * @param \stdClass|int $user
 * @return bool
 * @throws coding_exception
 */
function local_mentor_core_has_capabilities($capabilities, $context, $user)
{
    foreach ($capabilities as $capability) {
        if (!has_capability($capability, $context, $user)) {
            return false;
        }
    }

    return true;
}

/**
 * Sanitize a string to compare it with other strings
 *
 * @param string $string
 * @return string
 */
function local_mentor_core_sanitize_string($string)
{
    $string = trim($string ?? '');

    return strtolower(trim(preg_replace('~[^0-9a-z]+~i', '-',
        preg_replace('~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1',
            htmlentities($string, ENT_QUOTES, 'UTF-8'))), ' '));
}

/**
 * Sort session with favourite session first.
 *
 * @param stdClass $a
 * @param stdClass $b
 * @return int
 */
function local_mentor_core_usort_favourite_session_first($a, $b)
{
    if (!isset($a->favouritesession)) {
        $a->favouritesession = null;
    }

    if (!isset($b->favouritesession)) {
        $b->favouritesession = null;
    }

    // Two element not favourite, same place.
    if (!$b->favouritesession && !$a->favouritesession) {
        return 0;
    }

    // A element not favourite, B is up.
    if (!$a->favouritesession) {
        return 1;
    }

    // B element not favourite, A is up.
    if (!$b->favouritesession) {
        return -1;
    }

    // Check time created to favourite select user.
    return $b->favouritesession->timecreated <=> $a->favouritesession->timecreated;
}

/**
 * Sort session to catalog
 *
 * @param \local_mentor_core\session $s1
 * @param \local_mentor_core\session $s2
 * @return int
 */
function local_mentor_core_uasort_session_to_catalog($s1, $s2)
{

    // Sort by entity name.
    $mainentity1name = $s1->get_entity()->get_main_entity()->name;
    $mainentity2name = $s2->get_entity()->get_main_entity()->name;

    if ($mainentity1name != $mainentity2name) {
        return strcmp(local_mentor_core_sanitize_string($mainentity1name), local_mentor_core_sanitize_string
        ($mainentity2name));
    }

    // Sort by training shortname.
    $training1shortname = $s1->get_training()->shortname;
    $training2shortname = $s2->get_training()->shortname;

    if ($training1shortname != $training2shortname) {
        return strcmp(local_mentor_core_sanitize_string($training1shortname), local_mentor_core_sanitize_string
        ($training2shortname));
    }

    // Sort by session shortname.
    return strcmp(local_mentor_core_sanitize_string($s1->shortname), local_mentor_core_sanitize_string($s2->shortname));
}

/**
 * Give the name of the capability that allows access to the edadmin course.
 *
 * @param $formattype
 * @return string
 */
function local_mentor_core_get_edadmin_course_view_capability($formattype = '')
{
    switch ($formattype) {
        case 'trainings':
            return \local_mentor_core\training_api::get_edadmin_course_view_capability();
        case 'session':
            return \local_mentor_core\session_api::get_edadmin_course_view_capability();
        case 'user':
            return \local_mentor_core\profile_api::get_edadmin_course_view_capability();
        case 'entities':
        default:
            return \local_mentor_core\entity_api::get_edadmin_course_view_capability();
    }
}

/**
 * Get course url
 *
 * @param stdClass $courseid
 * @return moodle_url
 * @throws dml_exception
 * @throws moodle_exception
 */
function local_mentor_core_get_course_url($course, $ismoodleurl = true)
{
    global $CFG;

    $dbi = \local_mentor_core\database_interface::get_instance();

    $url = $ismoodleurl ?
        new \moodle_url('/course/view.php', ['id' => $course->id]) :
        $CFG->wwwroot . '/course/view.php?id=' . $course->id;

    // The course hasn't a topics format.
    if ($course->format != 'topics') {
        return $url;
    }

    $coursedisplay = $course->coursedisplay ?? $dbi->get_course_format_option($course->id, 'coursedisplay');

    // The course is configured to display all sections in the same page.
    if ($coursedisplay != 1) {
        return $url;
    }

    $firstsection = 1;

    // Can we view the first section.
    if ($dbi->is_course_section_visible($course->id, $firstsection)) {
        if ($ismoodleurl) {
            $url->param('section', $firstsection);
        } else {
            $url .= '&section=' . $firstsection;
        }
    }

    return $url;
}
