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
 * Class profile
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

use Matrix\Exception;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/model.php');

class profile extends model {

    /**
     * @var string
     */
    public $username;

    /**
     * @var string
     */
    public $firstname;

    /**
     * @var string
     */
    public $lastname;

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $profileurl;

    /**
     * @var bool
     */
    public $suspended;

    /**
     * @var int
     */
    public $lastconnection;

    /**
     * @var string
     */
    public $mainentity;

    /**
     * @var string
     */
    public $region;

    /**
     * @var string
     */
    public $auth;

    public $entityshortname;

    public $hasconfigaccess;

    public $secondaryentities;

    public $department;

    public $category;

    public $status;

    /**
     * @var array
     */
    protected $canmovesessions = [];

    /**
     * @var array
     */
    protected $canmovetrainings = [];

    /**
     * user constructor.
     *
     * @param int $userid
     * @throws \moodle_exception
     */
    public function __construct($userorid) {

        parent::__construct();

        if (is_object($userorid)) {
            $user = $userorid;
        } else {
            // Fetch the user id database.
            $user = $this->dbinterface->get_user_by_id($userorid);
        }

        $this->id = $user->id;
        $this->username = $user->username;
        $this->firstname = $user->firstname;
        $this->lastname = $user->lastname;
        $this->email = $user->email;
        $this->profileurl = $this->get_url();
        $this->suspended = $user->suspended;
        $this->auth = $user->auth;

        if (!isset($user->lastaccess) || $user->lastaccess == 0) {
            $this->lastconnection = [
                'display' => get_string('never', 'local_mentor_core'),
                'timestamp' => 0,
            ];
        } else {
            // Create date format (example: 02/05/2021 15:16).

            // When day number not have two digit, add "0" first.
            $dateformat = strlen(userdate($user->lastaccess, '%d')) === 1 ?
                '0%d/%m/%Y %R' : '%d/%m/%Y %R';
            $this->lastconnection = [
                'display' => userdate($user->lastaccess, $dateformat),
                'timestamp' => (int) $user->lastaccess,
            ];
        }

        // Check if the mainentity has already been set.
        if (property_exists($user, 'mainentity')) {
            $this->mainentity = $user->mainentity;
        } else {
            $profileuserrecord = profile_user_record($this->id);
            $this->mainentity = property_exists($profileuserrecord, 'mainentity') ? $profileuserrecord->mainentity : '';
        }

    }

    /**
     * Get user url profile
     *
     * @return string
     * @throws \moodle_exception
     */
    public function get_url() {
        global $CFG;

        return $CFG->wwwroot . '/local/profile/pages/editadvanced.php?id=' . $this->id;
    }

    /**
     * Checks if the user is a member of a cohort
     *
     * @return bool
     * @throws \dml_exception
     */
    public function is_cohort_member() {
        return !empty($this->get_entities_cohorts());
    }

    /**
     * Checks if the user is suspended
     *
     * @return bool
     */
    public function is_suspended() {
        return $this->suspended;
    }

    /**
     * Get user entities cohorts
     *
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_entities_cohorts() {
        $db = database_interface::get_instance();
        return $db->get_user_cohorts($this->id);
    }

    /**
     * Check if the current user can edit this profile
     *
     * @return bool
     */
    public function can_edit_profile() {
        global $USER;

        $mainentity = $this->get_main_entity();

        if (!$mainentity) {
            return false;
        }

        return $USER->id === $this->id || has_capability('local/entities:manageentity', $mainentity->get_context());
    }

    /**
     * Get user main entity
     *
     * @return bool|entity
     * @throws \moodle_exception
     */
    public function get_main_entity() {
        global $CFG;
        require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');

        // The user has no main entity.
        if (empty($this->mainentity)) {
            return false;
        }

        // Return the main entity of the user.
        return entity_api::get_entity_by_name($this->mainentity, true, false);
    }

    /**
     * Sync user entities
     *
     * @param bool $isnewuser
     * @return bool true if success
     * @throws \Exception
     */
    public function sync_entities($isnewuser = false) {
        global $CFG;
        require_once($CFG->dirroot . '/local/entities/lib.php');
        require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');

        // Get user profile fields.
        $userprofilefields = (array) profile_user_record($this->id, false);

        // Get main entity.
        if (!isset($userprofilefields['mainentity']) || !$userprofilefields['mainentity']) {
            return;
        }

        $oldcohorts = [];
        if (!$isnewuser) {
            // Get user cohorts.
            $usercohorts = $this->get_entities_cohorts();

            // Get old Cohorts.
            $oldcohorts = array_map(function($key) {
                return $key->cohortid;
            }, array_values($usercohorts));
        }

        $entity = \local_mentor_core\entity_api::get_entity_by_name($userprofilefields['mainentity']);

        $maincohort = $entity->get_cohort();
        $maincohortid = $maincohort->id;

        $allcohorts = array_unique([$maincohortid]);

        $cohortstoadd = array_diff($allcohorts, $oldcohorts);
        $cohortstoremove = array_diff($oldcohorts, $allcohorts);

        // Add user to cohorts.
        foreach ($cohortstoadd as $toaddcohort) {
            cohort_add_member($toaddcohort, $this->id);
        }

        // Remove user from cohorts.
        foreach ($cohortstoremove as $toremovecohort) {
            cohort_remove_member($toremovecohort, $this->id);
        }

        return true;
    }

    /**
     * Return user highest role object
     *
     * @return \stdClass
     * @throws \dml_exception
     */
    public function get_highest_role() {
        return $this->dbinterface->get_highest_role_by_user($this->id);
    }

    /**
     * Get student role
     *
     * @return string
     * @throws \dml_exception
     */
    public function get_student_role() {
        return 'student';
    }

    /**
     * Suspend the account
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function suspend() {

        $user = $this->dbinterface->get_user_by_id($this->id);

        // Check if the user is already suspended.
        if ($user->suspended == 1) {
            return false;
        }

        $supportuser = \core_user::get_support_user();

        $object = get_string('email_disabled_accounts_object', 'local_mentor_specialization');

        // Get the content of the email.
        $content = get_string('email_disabled_accounts_content', 'local_mentor_specialization');
        $contenthtml = text_to_html($content, false, false, true);

        // Send email.
        email_to_user($user, $supportuser, $object, $content, $contenthtml);

        // Update the user after the email because we cannot notify suspended users.
        $user->suspended = 1;
        $this->suspended = 1;

        // Update user.
        user_update_user($user, false);

        return true;
    }

    /**
     * Reactivate the account
     *
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function reactivate() {
        global $CFG;

        $user = $this->dbinterface->get_user_by_id($this->id, true);

        // Check if the user is already activated.
        if ($user->suspended == 0) {
            return false;
        }

        $supportuser = \core_user::get_support_user();

        $object = get_string('enabledaccountobject', 'local_profile');

        $a = new \stdClass();
        $a->wwwroot = $CFG->wwwroot;
        $a->forgetpasswordurl = $CFG->wwwroot . '/login/forgot_password.php';

        // Get the content of the email.
        $content = get_string('enabledaccountcontent', 'local_profile', $a);
        $contenthtml = text_to_html($content, false, false, true);

        $user->suspended = 0;
        $this->suspended = 0;

        // Send email.
        email_to_user($user, $supportuser, $object, $content, $contenthtml);

        // Update profile.
        user_update_user($user, false, true);

        return true;
    }

    /**
     * Set user highest role into profile
     *
     * @return string
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function set_highestrole_into_profile() {
        global $CFG;

        require_once($CFG->dirroot . '/user/editlib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/lib.php');

        $user = $this->dbinterface->get_user_by_id($this->id);

        // Load user preferences.
        useredit_load_preferences($user);

        // Load custom profile fields data.
        profile_load_data($user);

        $newuser = clone($user);

        // Default student user role.
        $rolename = $this->get_student_role();

        if (is_siteadmin($this->id)) {
            $rolename = 'admin';
        } else if ($highestrole = $this->get_highest_role()) {
            $rolename = $highestrole->shortname;
        }

        $authplugin = get_auth_plugin($this->auth);

        $newuser->profile_field_roleMentor = $rolename;

        try {
            $authplugin->user_update($user, $newuser);
        } catch (\Exception $e) {
            throw new \moodle_exception('cannotupdateuseronexauth', '', '', $this->auth);
        }

        $this->dbinterface->set_profile_field_value($this->id, 'roleMentor', $rolename);

        return $rolename;
    }

    /**
     * Set user main entity from an entity object
     *
     * @param entity $mainentity
     * @return bool
     */
    public function set_main_entity($mainentity) {
        $entityname = $mainentity->get_name();

        return $this->set_profile_field('mainentity', $entityname);
    }

    /**
     * Set user custom profile field value
     *
     * @param string $name
     * @param mixed $value
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function set_profile_field($name, $value) {
        $user = $this->dbinterface->get_user_by_id($this->id);

        // Load user preferences.
        \useredit_load_preferences($user);

        // Load custom profile fields data.
        profile_load_data($user);

        $newuser = clone($user);

        $authplugin = get_auth_plugin($this->auth);

        $propertyname = 'profile_field_' . $name;

        $newuser->{$propertyname} = $value;

        try {
            $authplugin->user_update($user, $newuser);
        } catch (\Exception $e) {
            throw new \moodle_exception('cannotupdateuseronexauth', '', '', $this->auth);
        }

        $this->dbinterface->set_profile_field_value($this->id, $name, $value);

        $this->{$name} = $value;

        return true;
    }

    /**
     * Check if a user can move a session in other entities from a main entity.
     *
     * @param entity $mainentity
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function can_move_training($mainentity) {

        if (!isset($this->canmovetrainings[$mainentity->id])) {
            $canmove = false;
            $countentities = 0;

            // Move into entity and subentities.
            if (has_capability('local/mentor_core:movetrainings', $mainentity->get_context())) {

                if (has_capability('local/trainings:create', $mainentity->get_context())) {
                    $countentities++;
                }

                $subentities = $mainentity->get_sub_entities();

                foreach ($subentities as $subentity) {
                    if (has_capability('local/trainings:create', $subentity->get_context())) {
                        $countentities++;

                        if ($countentities > 1) {
                            $canmove = true;
                            break;
                        }
                    }
                }
            }

            // Move into entity or other entities.
            if (!$canmove && has_capability('local/mentor_core:movetrainingsinotherentities', $mainentity->get_context())) {
                if (count(\local_mentor_core\entity_api::get_entities_where_trainings_managed()) > 1) {
                    $canmove = true;
                }
            }
            $this->canmovetrainings[$mainentity->id] = $canmove;
        }

        return $this->canmovetrainings[$mainentity->id];

    }

    /**
     * Check if a user can move a session in other entities from a main entity.
     *
     * @param entity $mainentity
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function can_move_session($mainentity) {

        if (!isset($this->canmovesessions[$mainentity->id])) {
            $canmove = false;
            $countentities = 0;

            // Move into entity and subentities.
            if (has_capability('local/mentor_core:movesessions', $mainentity->get_context())) {

                if (has_capability('local/session:create', $mainentity->get_context())) {
                    $countentities++;
                }

                $subentities = $mainentity->get_sub_entities();

                foreach ($subentities as $subentity) {
                    if (has_capability('local/session:create', $subentity->get_context())) {
                        $countentities++;

                        if ($countentities > 1) {
                            $canmove = true;
                            break;
                        }
                    }
                }
            }

            // Move into entity or other entities.
            if (!$canmove && has_capability('local/mentor_core:movesessionsinotherentities', $mainentity->get_context())) {
                if (count(\local_mentor_core\entity_api::get_entities_where_trainings_managed()) > 1) {
                    $canmove = true;
                }
            }

            $this->canmovesessions[$mainentity->id] = $canmove;
        }

        return $this->canmovesessions[$mainentity->id];
    }

    /**
     * Set user preference
     *
     * @param string $preferencename
     * @param mixed $value
     * @return bool
     */
    public function set_preference($preferencename, $value) {
        return $this->dbinterface->set_user_preference($this->id, $preferencename, $value);
    }

    /**
     * Set user preference
     *
     * @param string $preferencename
     * @return mixed
     */
    public function get_preference($preferencename) {
        return $this->dbinterface->get_user_preference($this->id, $preferencename);
    }
}
