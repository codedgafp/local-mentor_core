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
 * Test cases for training API
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mentor_core\session;
use local_mentor_core\training;
use local_mentor_core\session_api;
use local_mentor_core\session_form;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/classes/model/session.php');
require_once($CFG->dirroot . '/local/mentor_core/api/session.php');
require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');

class local_mentor_core_session_testcase extends advanced_testcase {

    public const UNAUTHORISED_CODE = 2020120810;
    public const DEFAULT_USER = 2;

    /**
     * Reset the singletons
     *
     * @throws ReflectionException
     */
    public function reset_singletons() {
        // Reset the mentor core db interface singleton.
        $dbinterface = \local_mentor_core\database_interface::get_instance();
        $reflection = new ReflectionClass($dbinterface);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true); // Now we can modify that :).
        $instance->setValue(null, null); // Instance is gone.
        $instance->setAccessible(false); // Clean up.

        \local_mentor_core\training_api::clear_cache();
        \local_mentor_core\session_api::clear_cache();
    }

    /**
     * Duplicate a role
     *
     * @param $fromshortname
     * @param $shortname
     * @param $fullname
     * @param $modelname
     * @return mixed|void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function duplicate_role($fromshortname, $shortname, $fullname, $modelname) {
        global $DB;

        if (!$fromrole = $DB->get_record('role', ['shortname' => $fromshortname])) {
            mtrace('ERROR : role ' . $fromshortname . 'does not exist');
            return;
        }

        $newid = create_role($fullname, $shortname, '', $modelname);

        // Role allow override.
        $oldoverrides = $DB->get_records('role_allow_override', ['roleid' => $fromrole->id]);
        foreach ($oldoverrides as $oldoverride) {
            $oldoverride->roleid = $newid;
            $DB->insert_record('role_allow_override', $oldoverride);
        }

        // Role allow switch.
        $oldswitches = $DB->get_records('role_allow_switch', ['roleid' => $fromrole->id]);
        foreach ($oldswitches as $oldswitch) {
            $oldswitch->roleid = $newid;
            $DB->insert_record('role_allow_switch', $oldswitch);
        }

        // Role allow view.
        $oldviews = $DB->get_records('role_allow_view', ['roleid' => $fromrole->id]);
        foreach ($oldviews as $oldview) {
            $oldview->roleid = $newid;
            $DB->insert_record('role_allow_view', $oldview);
        }

        // Role allow assign.
        $oldassigns = $DB->get_records('role_allow_assign', ['roleid' => $fromrole->id]);
        foreach ($oldassigns as $oldassign) {
            $oldassign->roleid = $newid;
            $DB->insert_record('role_allow_assign', $oldassign);
        }

        // Role context levels.
        $oldcontexts = $DB->get_records('role_context_levels', ['roleid' => $fromrole->id]);
        foreach ($oldcontexts as $oldcontext) {
            $oldcontext->roleid = $newid;
            $DB->insert_record('role_context_levels', $oldcontext);
        }

        // Role capabilities.
        $oldcapabilities = $DB->get_records('role_capabilities', ['roleid' => $fromrole->id]);
        foreach ($oldcapabilities as $oldcapability) {
            $oldcapability->roleid = $newid;
            $DB->insert_record('role_capabilities', $oldcapability);
        }

        return $DB->get_record('role', ['id' => $newid]);
    }

    /**
     * Init default role if remove by specialization
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function init_role() {
        global $DB;

        $db = \local_mentor_core\database_interface::get_instance();
        $manager = $db->get_role_by_name('manager');

        if (!$manager) {
            $otherrole = $DB->get_record('role', ['archetype' => 'manager'], '*', IGNORE_MULTIPLE);
            $this->duplicate_role($otherrole->shortname, 'manager', 'Manager',
                'manager');
        }
    }

    /**
     * Initialization of the user data
     *
     * @return int
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function init_create_user() {
        global $DB;

        // Create user.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'test@test.com';
        $user->username = 'testusername';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        $field = $DB->get_record('user_info_field', ['shortname' => 'mainentity']);

        $userdata = new stdClass();
        $userdata->fieldid = $field->id;
        $userdata->data = 'New Entity 1';
        $userdata->userid = $userid;

        $DB->insert_record('user_info_data', $userdata);

        return $userid;
    }

    /**
     * Initialization of the session or trainig data
     *
     * @param false $training
     * @param null $sessionid
     * @return stdClass
     */
    public function init_session_data($training = false, $sessionid = null) {
        $data = new stdClass();

        if ($training) {
            $data->name = 'fullname';
            $data->shortname = 'shortname';
            $data->content = 'summary';
            $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        } else {
            $data->trainingname = 'fullname';
            $data->trainingshortname = 'shortname';
            $data->trainingcontent = 'summary';
            $data->trainingstatus = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        }

        // Fields for taining.
        $data->traininggoal = 'TEST TRAINING ';
        $data->thumbnail = '';

        // Specific fields for session (only for update).
        if ($sessionid) {
            $data->id = $sessionid;
            $data->opento = 'all';

            $data->sessionstartdate = 1609801200;
            $data->sessionenddate = 1609801200;
            $data->maxparticipants = 10;
            $data->opentolist = '';
        }

        return $data;
    }

    /**
     * Init training creation
     *
     * @return training
     * @throws moodle_exception
     */
    public function init_training_creation() {
        global $DB;

        // Remove the miscelleanous category.
        $DB->delete_records('course_categories', ['id' => 1]);

        // Init test data.
        $data = $this->init_session_data(true);

        try {
            // Get entity object for default category.
            $entityid = \local_mentor_core\entity_api::create_entity([
                'name' => 'New Entity 1',
                'shortname' => 'New Entity 1',
                'userid' => 2,  // Set the admin user as manager of the entity.
            ]);

            $entity = \local_mentor_core\entity_api::get_entity($entityid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Init data with entity data.
        $data = $this->init_training_entity($data, $entity);

        // Test standard training creation.
        try {
            $training = \local_mentor_core\training_api::create_training($data);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        return $training;
    }

    /**
     * Init session creation
     *
     * @return int
     * @throws moodle_exception
     */
    public function init_session_creation($sessionname = 'TESTUNITCREATESESSION') {
        // Create training.
        $training = $this->init_training_creation();

        // Test standard training creation.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        return $session->id;
    }

    /**
     * Init training categery by entity id
     */
    public function init_training_entity($data, $entity) {
        // Get "Formation" category id (child of entity category).
        $formationid = $entity->get_entity_formation_category();
        $data->categorychildid = $formationid;

        $data->categoryid = $entity->id;
        $data->creativestructure = $entity->id;

        return $data;
    }

    /**
     * Init training category by entity id
     */
    public function init_session_entity($data, $entity) {
        // Get "Formation" category id (child of entity category).
        $formationid = $entity->get_entity_formation_category();
        $data->categorychildid = $formationid;

        $data->categoryid = $entity->id;
        $data->creativestructure = $entity->id;

        return $data;
    }

    /**
     * Test get session
     *
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::__construct
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     */
    public function test_get_session_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::assertIsObject($session);
        self::assertEquals($sessionid, $session->id);

        self::resetAllData();
    }

    /**
     * Test get session fail
     *
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::__construct
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     */
    public function test_get_session_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $falsesessionid = 10;

        // Test session not found.
        try {
            \local_mentor_core\session_api::get_session($falsesessionid);
            self::fail('Not possible exist');
        } catch (\Exception $e) {
            // Session not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test get session by entity ok
     *
     * @covers  \local_mentor_core\session_api::get_sessions_by_entity
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::__construct
     * @covers  \local_mentor_core\session::get_url
     * @covers  \local_mentor_core\session::get_course
     * @covers  \local_mentor_core\session::is_shared
     * @covers  \local_mentor_core\session::get_actions
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_specialization
     * @covers  \local_mentor_core\database_interface::get_sessions_by_entity_id
     */
    public function test_get_session_by_entity_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Set data passed as an argument.
        $datasessionandfilter = new stdClass();
        $datasessionandfilter->entityid = $session->get_entity()->id;
        $datasessionandfilter->status = null;
        $datasessionandfilter->dateto = null;
        $datasessionandfilter->datefrom = null;
        $datasessionandfilter->length = 10;
        $datasessionandfilter->start = 0;

        // Test session not found.
        try {
            $sessions = \local_mentor_core\session_api::get_sessions_by_entity($datasessionandfilter);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::assertCount(1, $sessions);
        self::assertEquals($session->id, $sessions[0]['id']);

        self::resetAllData();
    }

    /**
     * Test get session by entity when user is not manager ok
     *
     * @covers  \local_mentor_core\session_api::get_sessions_by_entity
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::__construct
     * @covers  \local_mentor_core\session::get_url
     * @covers  \local_mentor_core\session::get_course
     * @covers  \local_mentor_core\session::is_shared
     * @covers  \local_mentor_core\session::get_actions
     * @covers  \local_mentor_core\session::is_manager
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_specialization
     * @covers  \local_mentor_core\database_interface::get_sessions_by_entity_id
     */
    public function test_get_session_by_entity_user_not_managed_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Set data passed as an argument.
        $datasessionandfilter = new stdClass();
        $datasessionandfilter->entityid = $session->get_entity()->id;
        $datasessionandfilter->status = null;
        $datasessionandfilter->dateto = null;
        $datasessionandfilter->datefrom = null;
        $datasessionandfilter->length = 10;
        $datasessionandfilter->start = 0;

        $userid = self::init_create_user();
        self::setUser($userid);

        // Test session not found.
        try {
            $sessions = \local_mentor_core\session_api::get_sessions_by_entity($datasessionandfilter);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::assertCount(0, $sessions);

        self::resetAllData();
    }

    /**
     * Test count session record ok
     *
     * @covers  \local_mentor_core\session_api::count_session_record
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     * @covers  \local_mentor_core\database_interface::get_sessions_by_entity_id
     */
    public function test_count_session_record_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Set data passed as an argument.
        $datasessionandfilter = new stdClass();
        $datasessionandfilter->entityid = $session->get_entity()->id;
        $datasessionandfilter->status = null;
        $datasessionandfilter->dateto = null;
        $datasessionandfilter->datefrom = null;
        $datasessionandfilter->length = 10;
        $datasessionandfilter->start = 0;

        // Test session not found.
        try {
            $sessionscount = \local_mentor_core\session_api::count_session_record($datasessionandfilter);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::assertEquals(1, $sessionscount);

        self::resetAllData();
    }

    /**
     * Test count session record when user is not manager ok
     *
     * @covers  \local_mentor_core\session_api::count_session_record
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::is_manager
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     * @covers  \local_mentor_core\database_interface::get_sessions_by_entity_id
     */
    public function test_count_session_record_user_not_managed_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Set data passed as an argument.
        $datasessionandfilter = new stdClass();
        $datasessionandfilter->entityid = $session->get_entity()->id;
        $datasessionandfilter->status = null;
        $datasessionandfilter->dateto = null;
        $datasessionandfilter->datefrom = null;
        $datasessionandfilter->length = 10;
        $datasessionandfilter->start = 0;

        $userid = self::init_create_user();
        self::setUser($userid);

        // Test session not found.
        try {
            $sessionscount = \local_mentor_core\session_api::count_session_record($datasessionandfilter);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::assertEquals(0, $sessionscount);

        self::resetAllData();
    }

    /**
     * Test get count session by entity id ok
     *
     * @covers  \local_mentor_core\session_api::count_sessions_by_entity_id
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     */
    public function test_count_sessions_by_entity_id_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Set data passed as an argument.
        $datasessionandfilter = new stdClass();
        $datasessionandfilter->entityid = $session->get_entity()->id;
        $datasessionandfilter->status = null;
        $datasessionandfilter->dateto = null;
        $datasessionandfilter->datefrom = null;
        $datasessionandfilter->length = 10;
        $datasessionandfilter->start = 0;

        // Test session not found.
        try {
            $sessioncount = \local_mentor_core\session_api::count_sessions_by_entity_id($datasessionandfilter);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::assertEquals(1, $sessioncount);

        self::resetAllData();
    }

    /**
     * Test get count session by entity id ok
     *
     * @covers  \local_mentor_core\session_api::get_next_training_session_index
     */
    public function test_get_next_training_session_index_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $training = $this->init_training_creation();

        // Get next index session.
        $nextsessionindex = \local_mentor_core\session_api::get_next_training_session_index($training->id);

        self::assertEquals(1, $nextsessionindex);

        self::resetAllData();
    }

    /**
     * Test add a session
     *
     * @covers  \local_mentor_core\session_api::create_session
     * @covers  \local_mentor_core\session::__construct
     * @covers  \local_mentor_core\session::get_course
     * @covers  \local_mentor_core\task\create_session_task::execute
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_create_session_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create training.
        $training = $this->init_training_creation();

        $sessionname = 'TESTUNITCREATESESSION';

        // Test standard training creation.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Test if we have received an object.
        self::assertIsObject($session);
        self::assertIsNumeric($session->id);

        $sessionname = 'TESTUNITCREATESESSION2';

        // Create a session by ad hoc task.
        try {
            $result = \local_mentor_core\session_api::create_session($training->id, $sessionname, false);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::assertIsInt($result);

        self::resetAllData();
    }

    /**
     * Test add a session when has not permission
     *
     * @covers  \local_mentor_core\session_api::create_session
     * @covers  \local_mentor_core\task\create_session_task::execute
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_create_session_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $this->setAdminUser();

        // Create training.
        $training = $this->init_training_creation();

        $userid = $this->init_create_user();

        $this->setUser($userid);

        $sessionname = 'TESTUNITCREATESESSION';

        $exceptionthrown = false;

        // Test standard session creation with no permissions.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);
        } catch (\Exception $e) {
            // An exeption must be thrown here.
            self::assertInstanceOf('moodle_exception', $e);
            $exceptionthrown = true;
        }

        self::assertTrue($exceptionthrown);

        self::resetAllData();
    }

    /**
     * Test add a session when name is used
     *
     * @covers  \local_mentor_core\session_api::create_session
     * @covers  \local_mentor_core\task\create_session_task::execute
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_create_session_name_used_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $this->setAdminUser();

        // Create training.
        $training = $this->init_training_creation();

        $sessionname = 'TESTUNITCREATESESSION';

        $exceptionthrown = false;

        // Create Session.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);
        } catch (\Exception $e) {
            // An exeption must be thrown here.
            self::fail($e);
        }

        // Create Session whith name used.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);
        } catch (\Exception $e) {
            // An exeption must be thrown here.
            self::fail($e);
        }

        self::assertEquals(-1, $session);

        self::resetAllData();
    }

    /**
     * Test add a session when name is in adhoc task
     *
     * @covers  \local_mentor_core\session_api::create_session
     * @covers  \local_mentor_core\task\create_session_task::execute
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_create_session_name_in_adhoc_task_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $this->setAdminUser();

        // Create training.
        $training = $this->init_training_creation();

        $sessionname = 'TESTUNITCREATESESSION';

        $exceptionthrown = false;

        // Create Session.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, false);
        } catch (\Exception $e) {
            // An exeption must be thrown here.
            self::fail($e);
        }

        // Create Session whith name used.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, false);
        } catch (\Exception $e) {
            // An exeption must be thrown here.
            self::fail($e);
        }

        self::assertEquals(-1, $session);

        self::resetAllData();
    }

    /**
     * Test update session
     *
     * @covers  \local_mentor_core\session_api::update_session
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::is_updater
     * @covers  \local_mentor_core\session::update
     * @covers  \local_mentor_core\session::get_context
     */
    public function test_update_session_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        $data = new stdClass();

        // New session data.
        $data->id = $sessionid;
        $data->maxparticipants = 12;
        $data->opento = 'other_entities';
        $data->opentolist = [1, 2];

        try {
            $session = \local_mentor_core\session_api::update_session($data);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Check if session is update.
        self::assertIsObject($session);
        self::assertEquals($data->maxparticipants, $session->maxparticipants);

        // New session data.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Set data session form.
        $sessionentity = $session->get_entity();

        $formparams = new stdClass();
        $formparams->session = $session;
        $formparams->returnto = $session->get_url();
        $formparams->session = $session;
        $formparams->entity = $sessionentity;
        $formparams->sharedentities = null;
        $formparams->logourl = null;
        $formparams->actionurl = $session->get_sheet_url()->out();
        $form = session_api::get_session_form($session->get_sheet_url()->out(), $formparams);

        $data->maxparticipants = 13;

        try {
            $session = \local_mentor_core\session_api::update_session($data, $form);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Check if session is update with form.
        self::assertIsObject($session);
        self::assertEquals($data->maxparticipants, $session->maxparticipants);

        self::resetAllData();
    }

    /**
     * Test update session with errors
     *
     * @covers  \local_mentor_core\session_api::update_session
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::is_updater
     * @covers  \local_mentor_core\session::update
     * @covers  \local_mentor_core\session::get_context
     */
    public function test_update_session_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        $data = new stdClass();

        // New session data.
        $data->id = $sessionid;
        $data->maxparticipants = 12;

        // Tty to update the session with a new user with no capabilities.
        $userid = $this->init_create_user();
        $this->setUser($userid);

        $exceptionthrown = false;

        try {
            $session = \local_mentor_core\session_api::update_session($data);
        } catch (\Exception $e) {
            // An exception must be thrown here.
            self::assertInstanceOf('Exception', $e);
            $exceptionthrown = true;
        }

        self::assertTrue($exceptionthrown);

        self::resetAllData();
    }

    /**
     * Test get session form ok
     *
     * @covers  \local_mentor_core\session_api::get_session_form
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session_form::__construct
     * @covers  \local_mentor_core\session_form::definition
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     * @covers  \local_mentor_core\session::__construct
     * @covers  \local_mentor_core\session::get_entity
     * @covers  \local_mentor_core\session::get_url
     * @covers  \local_mentor_core\session::get_sheet_url
     */
    public function test_get_session_form_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Set data session form.
        $sessionentity = $session->get_entity();

        $logo = $sessionentity->get_logo();
        $logourl = '';
        if ($logo) {
            $logourl = moodle_url::make_pluginfile_url(
                $logo->get_contextid(),
                $logo->get_component(),
                $logo->get_filearea(),
                $logo->get_itemid(),
                $logo->get_filepath(),
                $logo->get_filename()
            );
        }

        $sharedentities = [];
        $allentities = \local_mentor_core\entity_api::get_all_entities(true, [$sessionentity->id]);
        foreach ($allentities as $entity) {
            $sharedentities[$entity->id] = $entity->name;
        }

        $formparams = new stdClass();
        $formparams->session = $session;
        $formparams->returnto = $session->get_url();
        $formparams->session = $session;
        $formparams->entity = $sessionentity;
        $formparams->sharedentities = $sharedentities;
        $formparams->logourl = $logourl;
        $formparams->actionurl = $session->get_sheet_url()->out();

        // Get session form.
        try {
            $sessionform = \local_mentor_core\session_api::get_session_form($session->get_sheet_url()->out(), $formparams);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Check instance session form.
        self::assertInstanceOf('local_mentor_core\session_form', $sessionform);

        self::resetAllData();
    }

    /**
     * Test get session javascript ok
     *
     * @covers  \local_mentor_core\session_api::get_session_javascript
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     */
    public function test_get_session_javascript_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Check if return good session javascirpt path string.
        self::assertEquals(
            'local_mentor_core/session',
            \local_mentor_core\session_api::get_session_javascript('local_mentor_core/session')
        );

        self::resetAllData();
    }

    /**
     * Test get session template ok
     *
     * @covers  \local_mentor_core\session_api::get_session_template
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     */
    public function test_get_session_template_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Check if return good session template path string.
        self::assertEquals(
            'local_mentor_core/session',
            \local_mentor_core\session_api::get_session_template('local_mentor_core/session')
        );

        self::resetAllData();
    }

    /**
     * Test get session by course id ok
     *
     * @covers  \local_mentor_core\session_api::get_session_by_course_id
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::get_course
     */
    public function test_get_session_by_course_id_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Get course id session.
        $courseid = $session->get_course()->id;

        // Get session of course.
        try {
            $coursesession = \local_mentor_core\session_api::get_session_by_course_id($courseid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Check if good session.
        self::assertEquals($sessionid, $coursesession->id);

        self::resetAllData();
    }

    /**
     * Test get session by course id not ok
     *
     * @covers  \local_mentor_core\session_api::get_session_by_course_id
     */
    public function test_get_session_by_course_id_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $falsecourseid = '100';

        // Get session of course.
        try {
            $sessionid = \local_mentor_core\session_api::get_session_by_course_id($falsecourseid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Check if fail.
        self::assertFalse($sessionid);

        self::resetAllData();
    }

    /**
     * Test cancel_session
     *
     * @covers  \local_mentor_core\session_api::cancel_session
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::is_updater
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::cancel
     * @covers  \local_mentor_core\session::hide_course
     * @covers  \local_mentor_core\session::send_message_to_all
     * @covers  \local_mentor_core\session::get_all_users
     * @covers  \local_mentor_core\session::get_editors
     * @covers  \local_mentor_core\session::get_participants
     * @covers  \local_mentor_core\session::send_message_to_users
     * @covers  \local_mentor_core\session::disable_enrolment_instance
     * @covers  \local_mentor_core\session::get_enrolment_instances
     */
    public function test_cancel_session_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        try {
            self::assertTrue(\local_mentor_core\session_api::cancel_session($sessionid));
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::assertEquals('cancelled', $session->status);

        self::resetAllData();
    }

    /**
     * Test cancel_session nok
     *
     * @covers  \local_mentor_core\session_api::cancel_session
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::is_updater
     */
    public function test_cancel_session_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Tty to update the session with a new user with no capabilities.
        $userid = $this->init_create_user();
        $this->setUser($userid);

        // The user cannot cancel this session.
        self::assertFalse(\local_mentor_core\session_api::cancel_session($sessionid));

        $session = \local_mentor_core\session_api::get_session($sessionid);

        // The status must not have changed.
        self::assertNotEquals('cancelled', $session->status);

        self::resetAllData();
    }

    /**
     * Test get user session courses
     *
     * @covers  \local_mentor_core\session_api::get_user_session_courses
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session::get_entity
     */
    public function test_get_user_session_courses_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Assign new role.
        \local_mentor_core\profile_api::role_assign('manager', self::DEFAULT_USER, $session->get_entity()->get_context());

        // Check if user has session manager.
        $sessionmanage = \local_mentor_core\session_api::get_user_session_courses();
        self::assertCount(1, $sessionmanage);

        self::resetAllData();
    }

    /**
     * Test get user available sessions
     *
     * @covers \local_mentor_core\session_api::get_user_available_sessions
     * @covers \local_mentor_core\session_api::get_session
     * @covers \local_mentor_core\session_api::update_session
     * @covers \local_mentor_core\session::get_entity
     */
    public function test_get_user_available_sessions_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $userid = self::init_create_user();

        // Check if no session is available.
        $avaiblesessions = \local_mentor_core\session_api::get_user_available_sessions($userid);
        self::assertCount(0, $avaiblesessions);

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Update the status of the session so that it becomes available.
        $session->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
        \local_mentor_core\session_api::update_session($session);

        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        $user = new stdClass();
        $user->id = $userid;
        $session->get_entity()->add_member($user);

        // Check if user has one available session.
        $avaiblesessions = \local_mentor_core\session_api::get_user_available_sessions($userid);
        self::assertCount(1, $avaiblesessions);

        self::resetAllData();
    }

    /**
     * Test get user available sessions
     *
     * @covers \local_mentor_core\session_api::get_user_available_sessions
     * @covers \local_mentor_core\session_api::get_session
     * @covers \local_mentor_core\session_api::update_session
     */
    public function test_not_visible_session() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $userid = self::init_create_user();

        // Check if no session is available.
        $avaiblesessions = \local_mentor_core\session_api::get_user_available_sessions($userid);
        self::assertCount(0, $avaiblesessions);

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Set session not visible.
        $session->opento = 'not_visible';
        \local_mentor_core\session_api::update_session($session);

        // The session must not be available for users.
        $avaiblesessions = \local_mentor_core\session_api::get_user_available_sessions($userid);
        self::assertCount(0, $avaiblesessions);

        self::resetAllData();
    }

    /**
     * Test user is enrolled
     *
     * @covers \local_mentor_core\session_api::user_is_enrolled
     * @covers \local_mentor_core\session_api::get_session
     * @covers \local_mentor_core\session::create_self_enrolment_instance
     * @covers \local_mentor_core\session::get_course
     * @covers \local_mentor_core\session::get_enrolment_instances_by_type
     * @covers \local_mentor_core\session::enable_self_enrolment_instance
     * @covers \local_mentor_core\session::get_enrolment_instances
     * @covers \local_mentor_core\session::user_is_enrolled
     * @covers \local_mentor_core\session::get_context
     */
    public function test_user_is_enrolled_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        $userid = $this->init_create_user();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Update status.
        $session->opento = 'all';
        \local_mentor_core\session_api::update_session($session);

        // Create self enrolment instance.
        $session->create_self_enrolment_instance();

        self::setUser($userid);

        // Check if user user is not enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertFalse($isenrolled);

        // Enrol user.
        $session->enrol_current_user();

        // Check if user user is enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertTrue($isenrolled);

        self::resetAllData();
    }

    /**
     * Test get all sessions where the user is not enrolled
     *
     * @covers  \local_mentor_core\session_api::get_user_sessions
     * @covers  \local_mentor_core\session_api::get_session_by_course_id
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session_api::update_session
     * @covers  \local_mentor_core\session::convert_for_template
     * @covers  \local_mentor_core\session::get_progression
     * @covers  \local_mentor_core\session::is_trainer
     * @covers  \local_mentor_core\session::is_participant
     * @covers  \local_mentor_core\session::get_training
     * @covers  \local_mentor_core\session::get_course
     */
    public function test_get_sessions_user_not_enrolled() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $userid = $this->init_create_user();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
        \local_mentor_core\session_api::update_session($session);

        // User is not enrol.
        self::assertCount(0, \local_mentor_core\session_api::get_user_sessions($userid));

        self::resetAllData();
    }

    /**
     * Test user is enrolled with password
     *
     * @covers \local_mentor_core\session_api::user_is_enrolled
     * @covers \local_mentor_core\session_api::get_session
     * @covers \local_mentor_core\session::create_self_enrolment_instance
     * @covers \local_mentor_core\session::get_enrolment_instances_by_type
     * @covers \local_mentor_core\session::update_enrolment_instance
     * @covers \local_mentor_core\session::enrol_current_user
     * @covers \local_mentor_core\session::user_is_enrolled
     * @covers \local_mentor_core\session::get_context
     * @covers \local_mentor_core\session::enable_self_enrolment_instance
     * @covers \local_mentor_core\session::is_available_to_user
     * @covers \local_mentor_core\session::get_course
     */
    public function test_user_is_enrolled_password_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        $userid = $this->init_create_user();
        $enrolmentpassword = 'testkey';

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Update status.
        $session->status = session::STATUS_OPENED_REGISTRATION;
        $session->opento = 'all';
        \local_mentor_core\session_api::update_session($session);

        // Create self enrolment instance.
        $session->create_self_enrolment_instance();
        $selfenrolmentsession = $session->get_enrolment_instances_by_type('self');
        $selfenrolmentsession->password = $enrolmentpassword;
        $session->update_enrolment_instance($selfenrolmentsession);

        self::setUser($userid);

        // Check if user user is not enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertFalse($isenrolled);

        // Enrol user with password.
        $session->enrol_current_user($enrolmentpassword);

        // Check if user user is enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertTrue($isenrolled);

        self::resetAllData();
    }

    /**
     * Test user is enrolled with false password
     *
     * @covers \local_mentor_core\session_api::user_is_enrolled
     * @covers \local_mentor_core\session_api::get_session
     * @covers \local_mentor_core\session::create_self_enrolment_instance
     * @covers \local_mentor_core\session::get_enrolment_instances_by_type
     * @covers \local_mentor_core\session::update_enrolment_instance
     * @covers \local_mentor_core\session::enrol_current_user
     * @covers \local_mentor_core\session::user_is_enrolled
     * @covers \local_mentor_core\session::get_context
     * @covers \local_mentor_core\session::enable_self_enrolment_instance
     * @covers \local_mentor_core\session::is_available_to_user
     * @covers \local_mentor_core\session::get_course
     */
    public function test_user_is_enrolled_password_nok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        $userid = $this->init_create_user();
        $enrolmentpassword = 'testkey';
        $enrolmentfalsepassword = 'testfalsekey';

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Create self enrolment instance.
        $session->create_self_enrolment_instance();
        $selfenrolmentsession = $session->get_enrolment_instances_by_type('self');
        $selfenrolmentsession->password = $enrolmentpassword;
        $session->update_enrolment_instance($selfenrolmentsession);

        self::setUser($userid);

        // Check if user user is not enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertFalse($isenrolled);

        // Enrol user with password.
        $session->enrol_current_user($enrolmentfalsepassword);

        // Check if user user is enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertFalse($isenrolled);

        self::resetAllData();
    }

    /**
     * Test get all sessions where the user is enrolled
     *
     * @covers  \local_mentor_core\session_api::get_user_sessions
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session_api::update_session
     * @covers  \local_mentor_core\session_api::user_is_enrolled
     * @covers  \local_mentor_core\session_api::get_session_by_course_id
     * @covers  \local_mentor_core\session::create_self_enrolment_instance
     * @covers  \local_mentor_core\session::enrol_current_user
     * @covers  \local_mentor_core\session::is_trainer
     * @covers  \local_mentor_core\session::convert_for_template
     * @covers  \local_mentor_core\session::is_participant
     * @covers  \local_mentor_core\session::get_url
     * @covers  \local_mentor_core\session::get_available_places
     * @covers  \local_mentor_core\session::user_is_enrolled
     * @covers  \local_mentor_core\session::get_training
     * @covers  \local_mentor_core\session::get_progression
     * @covers  \local_mentor_core\session::get_course
     */
    public function test_get_user_sessions_inprogress() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $userid = $this->init_create_user();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session->opento = 'all';
        $session->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
        \local_mentor_core\session_api::update_session($session);

        // Create self enrolment instance.
        $session->create_self_enrolment_instance();

        // Check if user user is not enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertFalse($isenrolled);

        // Enrol user.
        self::setUser($userid);
        $session->enrol_current_user();
        self::setAdminUser();

        // User is enrolled.
        self::assertCount(1, \local_mentor_core\session_api::get_user_sessions($userid));
        // User is not a trainer.
        self::assertFalse($session->is_trainer($userid));

        // Create a random course.
        $course = self::getDataGenerator()->create_course();

        // Enrol the user into the new course which is not a session.
        self::getDataGenerator()->enrol_user($userid, $course->id);

        // The user must still be enrolled in only one session.
        self::assertCount(1, \local_mentor_core\session_api::get_user_sessions($userid));

        self::resetAllData();
    }

    /**
     * Test get all sessions where the user is enrolled
     *
     * @covers  \local_mentor_core\session_api::get_user_sessions
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session_api::update_session
     * @covers  \local_mentor_core\session_api::user_is_enrolled
     * @covers  \local_mentor_core\session_api::get_session_by_course_id
     * @covers  \local_mentor_core\session::create_self_enrolment_instance
     * @covers  \local_mentor_core\session::enrol_current_user
     * @covers  \local_mentor_core\session::is_trainer
     * @covers  \local_mentor_core\session::convert_for_template
     * @covers  \local_mentor_core\session::is_participant
     * @covers  \local_mentor_core\session::get_url
     * @covers  \local_mentor_core\session::get_available_places
     * @covers  \local_mentor_core\session::user_is_enrolled
     * @covers  \local_mentor_core\session::get_training
     * @covers  \local_mentor_core\session::get_progression
     * @covers  \local_mentor_core\session::get_course
     */
    public function test_get_user_sessions_opened_registration() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $userid = $this->init_create_user();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session->opento = 'all';
        $session->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        \local_mentor_core\session_api::update_session($session);

        // Create self enrolment instance.
        $session->create_self_enrolment_instance();

        // Check if user user is not enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertFalse($isenrolled);

        // Enrol user.
        self::setUser($userid);
        $session->enrol_current_user();
        self::setAdminUser();

        // User is enrolled.
        self::assertCount(1, \local_mentor_core\session_api::get_user_sessions($userid));
        // User is not a trainer.
        self::assertFalse($session->is_trainer($userid));

        // Create a random course.
        $course = self::getDataGenerator()->create_course();

        // Enrol the user into the new course which is not a session.
        self::getDataGenerator()->enrol_user($userid, $course->id);

        // The user must still be enrolled in only one session.
        self::assertCount(1, \local_mentor_core\session_api::get_user_sessions($userid));

        self::resetAllData();
    }

    /**
     * Test get all sessions where the user is enrolled
     *
     * @covers  \local_mentor_core\session_api::get_user_sessions
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session_api::update_session
     * @covers  \local_mentor_core\session_api::user_is_enrolled
     * @covers  \local_mentor_core\session_api::get_session_by_course_id
     * @covers  \local_mentor_core\session::create_self_enrolment_instance
     * @covers  \local_mentor_core\session::enrol_current_user
     * @covers  \local_mentor_core\session::is_trainer
     * @covers  \local_mentor_core\session::convert_for_template
     * @covers  \local_mentor_core\session::is_participant
     * @covers  \local_mentor_core\session::get_url
     * @covers  \local_mentor_core\session::get_available_places
     * @covers  \local_mentor_core\session::user_is_enrolled
     * @covers  \local_mentor_core\session::get_training
     * @covers  \local_mentor_core\session::get_progression
     * @covers  \local_mentor_core\session::get_course
     */
    public function test_get_user_sessions_archived() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $userid = $this->init_create_user();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session->opento = 'all';
        $session->status = \local_mentor_core\session::STATUS_ARCHIVED;
        \local_mentor_core\session_api::update_session($session);

        // Create self enrolment instance.
        $session->create_self_enrolment_instance();

        // Check if user user is not enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertFalse($isenrolled);

        // Enrol user.
        self::setUser($userid);
        $session->enrol_current_user();
        self::setAdminUser();

        // User is enrolled.
        self::assertCount(1, \local_mentor_core\session_api::get_user_sessions($userid));
        // User is not a trainer.
        self::assertFalse($session->is_trainer($userid));

        // Create a random course.
        $course = self::getDataGenerator()->create_course();

        // Enrol the user into the new course which is not a session.
        self::getDataGenerator()->enrol_user($userid, $course->id);

        // The user must still be enrolled in only one session.
        self::assertCount(1, \local_mentor_core\session_api::get_user_sessions($userid));

        self::resetAllData();
    }

    /**
     * Test get all sessions where the user is enrolled
     *
     * @covers  \local_mentor_core\session_api::is_session_course
     * @covers  \local_mentor_core\database_interface::get_instance
     * @covers  \local_mentor_core\database_interface::is_session_course
     */
    public function test_is_session_course() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $userid = $this->init_create_user();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::assertTrue(\local_mentor_core\session_api::is_session_course($session->get_course()->id));

        self::resetAllData();
    }

    /**
     * Test get all sessions where the user is trainer
     *
     * @covers  \local_mentor_core\session_api::get_user_sessions
     * @covers  \local_mentor_core\session_api::get_session
     * @covers  \local_mentor_core\session_api::update_session
     * @covers  \local_mentor_core\session_api::user_is_enrolled
     * @covers  \local_mentor_core\session_api::get_session_by_course_id
     * @covers  \local_mentor_core\session::create_self_enrolment_instance
     * @covers  \local_mentor_core\session::enrol_current_user
     * @covers  \local_mentor_core\session::get_context
     * @covers  \local_mentor_core\session::convert_for_template
     * @covers  \local_mentor_core\session::get_progression
     * @covers  \local_mentor_core\session::user_is_enrolled
     */
    public function test_get_sessions_user_trainer() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        // Create simple user.
        $userid = $this->init_create_user();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session->opento = 'all';
        $session->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
        \local_mentor_core\session_api::update_session($session);

        // Create self enrolment instance.
        $session->create_self_enrolment_instance();

        // Check if user user is not enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertFalse($isenrolled);

        // Enrol user.
        self::setUser($userid);
        $session->enrol_current_user();
        self::setAdminUser();

        \local_mentor_core\profile_api::role_assign('editingteacher', $userid, $session->get_context());

        // User is enrol.
        self::assertCount(1, \local_mentor_core\session_api::get_user_sessions($userid));
        // User id trainer.
        self::assertTrue($session->is_trainer($userid));

        self::resetAllData();
    }

    /**
     * Test get all sessions where the user is enrolled
     * favourite first
     *
     * @covers  \local_mentor_core\session_api::get_user_sessions
     * @covers  ::local_mentor_core_usort_favourite_session_first
     */
    public function test_get_user_sessions_favourite_first() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $userid = $this->init_create_user();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session->opento = 'all';
        $session->status = \local_mentor_core\session::STATUS_ARCHIVED;
        \local_mentor_core\session_api::update_session($session);

        // Create self enrolment instance.
        $session->create_self_enrolment_instance();

        // Check if user user is not enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session->id);
        self::assertFalse($isenrolled);

        // Enrol user.
        self::setUser($userid);
        $session->enrol_current_user();

        // User is enrolled.
        self::assertCount(1, \local_mentor_core\session_api::get_user_sessions($userid));
        self::setAdminUser();

        // User is not a trainer.
        self::assertFalse($session->is_trainer($userid));

        $sessionid2 = \local_mentor_core\session_api::create_session($session->get_training()->id, 'TESTUNITCREATESESSION2', true);

        // Get session2.
        try {
            $session2 = \local_mentor_core\session_api::get_session($sessionid2);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session2->opento = 'all';
        $session2->status = \local_mentor_core\session::STATUS_ARCHIVED;
        \local_mentor_core\session_api::update_session($session2);

        // Create self enrolment instance.
        $session2->create_self_enrolment_instance();

        // Check if user user is not enrolled.
        $isenrolled = \local_mentor_core\session_api::user_is_enrolled($userid, $session2->id);
        self::assertFalse($isenrolled);

        // Enrol user.
        self::setUser($userid);
        $session2->enrol_current_user();

        // The user must still be enrolled in only one session.
        \local_mentor_core\session_api::clear_cache();
        $usersession = \local_mentor_core\session_api::get_user_sessions($userid);
        self::assertCount(2, $usersession);
        self::assertEquals($session->id, current($usersession)->id);
        self::setAdminUser();

        // Enrol user.
        self::setUser($userid);
        \local_mentor_core\session_api::add_user_favourite_session($session2->id, $userid);

        // The user must still be enrolled in only one session.
        \local_mentor_core\session_api::clear_cache();
        $usersession = \local_mentor_core\session_api::get_user_sessions($userid);
        usort($usersession, "local_mentor_core_usort_favourite_session_first");
        self::setAdminUser();

        self::assertCount(2, $usersession);
        self::assertEquals($session2->id, current($usersession)->id);

        self::resetAllData();
    }

    /**
     * Test get_status_list
     *
     * @covers  \local_mentor_core\session_api::get_status_list
     */
    public function test_get_status_list() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::assertEquals([
            session::STATUS_IN_PREPARATION => session::STATUS_IN_PREPARATION,
            session::STATUS_OPENED_REGISTRATION => session::STATUS_OPENED_REGISTRATION,
            session::STATUS_IN_PROGRESS => session::STATUS_IN_PROGRESS,
            session::STATUS_COMPLETED => session::STATUS_COMPLETED,
            session::STATUS_ARCHIVED => session::STATUS_ARCHIVED,
            session::STATUS_REPORTED => session::STATUS_REPORTED,
            session::STATUS_CANCELLED => session::STATUS_CANCELLED,
        ], \local_mentor_core\session_api::get_status_list());

        self::resetAllData();
    }

    /**
     * Test prepare update session editor data
     *
     * @covers \local_mentor_core\session_api::prepare_update_session_editor_data
     * @covers \local_mentor_core\specialization::__construct
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     * @covers \local_mentor_core\session::prepare_edit_form
     */
    public function test_prepare_update_session_editor_data() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Prepare initial form data.
        $defaultdata = $session->prepare_edit_form();

        // Prepare editor data.
        $data = \local_mentor_core\session_api::prepare_update_session_editor_data($defaultdata);

        self::assertEquals($defaultdata, $data);

        $this->resetAllData();
    }

    /**
     * Test convert update session editor data
     *
     * @covers \local_mentor_core\session_api::convert_update_session_editor_data
     * @covers \local_mentor_core\specialization::__construct
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     */
    public function test_convert_update_session_editor_data() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $datatest = new stdClass();
        $datatest->test = 'test';

        // Prepare editor data.
        $data = \local_mentor_core\session_api::convert_update_session_editor_data($datatest);

        self::assertEquals($datatest, $data);

        $this->resetAllData();
    }

    /**
     * Test get session enrolment data
     *
     * @covers \local_mentor_core\session_api::get_session_enrolment_data
     * @covers \local_mentor_core\session_api::get_session
     * @covers \local_mentor_core\session::has_registration_key
     * @covers \local_mentor_core\specialization::__construct
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     */
    public function test_get_session_enrolment_data() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $data = \local_mentor_core\session_api::get_session_enrolment_data($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::assertTrue(isset($data->hasselfregistrationkey));

        $this->resetAllData();
    }

    /**
     * Test move session
     *
     * @covers \local_mentor_core\session_api::get_session
     * @covers \local_mentor_core\session_api::move_session
     * @covers \local_mentor_core\entity_api::create_entity
     */
    public function test_move_session_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Get old entity id.
        $oldentityid = $session->get_entity()->id;

        // Create new enrity.
        $newentityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 2',
            'shortname' => 'New Entity 2',
            'userid' => 2,  // Set the admin user as manager of the entity.
        ]);

        // Move session in new entity.
        try {
            \local_mentor_core\session_api::move_session($session->id, $newentityid);
        } catch (\Exception $e) {
            self::fail($e);
        }

        // Check if session entity is not old entity.
        self::assertNotEquals($session->get_entity()->id, $oldentityid);
        // Check if session entity is new entity.
        self::assertEquals($session->get_entity()->id, $newentityid);

        $this->resetAllData();
    }

    /**
     * Test move session in same entity
     *
     * @covers \local_mentor_core\session_api::get_session
     * @covers \local_mentor_core\session_api::move_session
     */
    public function test_move_session_same_entity_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Get old entity id.
        $oldentityid = $session->get_entity()->id;

        // Move session in new entity.
        try {
            $result = \local_mentor_core\session_api::move_session($session->id, $oldentityid);
        } catch (\Exception $e) {
            self::fail($e);
        }

        // Check if session entity is not old entity.
        self::assertTrue($result);
        // Check if session entity is new entity.
        self::assertEquals($session->get_entity()->id, $oldentityid);

        $this->resetAllData();
    }

    /**
     * Test move session when user not
     * has capability in session's entity
     *
     * @covers \local_mentor_core\session_api::get_session
     * @covers \local_mentor_core\session_api::move_session
     * @covers \local_mentor_core\entity_api::create_entity
     */
    public function test_move_session_user_not_session_entity_capability_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Create new enrity.
        $newentityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 2',
            'shortname' => 'New Entity 2',
            'userid' => 2,  // Set the admin user as manager of the entity.
        ]);

        $userid = self::init_create_user();
        self::setUser($userid);

        $exceptionthrown = false;

        // Move session in new entity.
        try {
            \local_mentor_core\session_api::move_session($session->id, $newentityid);
        } catch (\Exception $e) {
            // An exception must be thrown here.
            self::assertInstanceOf('Exception', $e);
            $exceptionthrown = true;
        }

        // Check if session entity is new entity.
        self::assertTrue($exceptionthrown);

        $this->resetAllData();
    }

    /**
     * Test move session when user not
     * has capability in new entity
     *
     * @covers \local_mentor_core\session_api::get_session
     * @covers \local_mentor_core\session_api::move_session
     * @covers \local_mentor_core\entity_api::create_entity
     */
    public function test_move_session_user_not_new_entity_capability_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Create new enrity.
        $newentityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 2',
            'shortname' => 'New Entity 2',
            'userid' => 2,  // Set the admin user as manager of the entity.
        ]);

        $userid = self::init_create_user();
        self::setUser($userid);

        $session->get_entity()->assign_manager($userid);

        $exceptionthrown = false;

        // Move session in new entity.
        try {
            \local_mentor_core\session_api::move_session($session->id, $newentityid);
        } catch (\Exception $e) {
            // An exception must be thrown here.
            self::assertInstanceOf('Exception', $e);
            $exceptionthrown = true;
        }

        // Check if session entity is new entity.
        self::assertTrue($exceptionthrown);

        $this->resetAllData();
    }

    /**
     * Test Override the default session template params
     *
     * @covers \local_mentor_core\session_api::get_session_template_params
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     */
    public function test_get_session_template_params() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $params = \local_mentor_core\session_api::get_session_template_params();

        self::assertIsObject($params);

        $this->resetAllData();
    }

    /**
     * Test restore session
     *
     * @covers  \local_mentor_core\session_api::restore_session
     */
    public function test_restore_session() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $session = \local_mentor_core\session_api::get_session($this->init_session_creation());
        $sessionname = $session->fullname;

        $entity = $session->get_entity();

        // Set data passed as an argument.
        $datasessionandfilter = new stdClass();
        $datasessionandfilter->entityid = $entity->id;
        $datasessionandfilter->status = null;
        $datasessionandfilter->dateto = null;
        $datasessionandfilter->datefrom = null;
        $datasessionandfilter->length = 10;
        $datasessionandfilter->start = 0;

        // One session in entity and zero remove training.
        self::assertCount(1, \local_mentor_core\session_api::get_sessions_by_entity($datasessionandfilter));
        self::assertCount(0, $entity->get_sessions_recyclebin_items());

        $session->delete();

        // Zero session in entity and one remove training.
        $sessionrecycleitems = $entity->get_sessions_recyclebin_items();
        self::assertCount(1, $sessionrecycleitems);
        self::assertEquals($sessionname, $sessionrecycleitems[0]->name);
        self::assertCount(0, \local_mentor_core\session_api::get_sessions_by_entity($datasessionandfilter));

        /*
         * TODO : find why test does not work
         *
        self::setGuestUser();

        try {
            \local_mentor_core\session_api::restore_session($entity->id, $sessionrecycleitems[0]->id);
        }
        catch (Exception $e) {
            self::assertInstanceOf('moodle_exception', $e);
        }
        */

        \local_mentor_core\session_api::restore_session($entity->id, $sessionrecycleitems[0]->id);

        self::setAdminUser();

        // Zero remove training.
        self::assertCount(0, $entity->get_sessions_recyclebin_items());

        self::resetAllData();
    }

    /**
     * Test remove session item
     *
     * @covers  \local_mentor_core\session_api::remove_session_item
     */
    public function test_remove_training_item() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $session = \local_mentor_core\session_api::get_session($this->init_session_creation());
        $sessionname = $session->fullname;

        $entity = $session->get_entity();

        // Set data passed as an argument.
        $datasessionandfilter = new stdClass();
        $datasessionandfilter->entityid = $entity->id;
        $datasessionandfilter->status = null;
        $datasessionandfilter->dateto = null;
        $datasessionandfilter->datefrom = null;
        $datasessionandfilter->length = 10;
        $datasessionandfilter->start = 0;

        // One session in entity and zero remove training.
        self::assertCount(1, \local_mentor_core\session_api::get_sessions_by_entity($datasessionandfilter));
        self::assertCount(0, $entity->get_sessions_recyclebin_items());

        $session->delete();

        // Zero session in entity and one remove training.
        $sessionrecycleitems = $entity->get_sessions_recyclebin_items();
        self::assertCount(1, $sessionrecycleitems);
        self::assertEquals($sessionname, $sessionrecycleitems[0]->name);
        self::assertCount(0, \local_mentor_core\session_api::get_sessions_by_entity($datasessionandfilter));

        \local_mentor_core\session_api::remove_session_item($entity->id, $sessionrecycleitems[0]->id);

        // Zero remove training.
        self::assertCount(0, $entity->get_sessions_recyclebin_items());
        self::assertCount(0, \local_mentor_core\session_api::get_sessions_by_entity($datasessionandfilter));

        self::resetAllData();
    }

    /**
     * Test duplicate_session_as_new_training
     *
     * @covers  \local_mentor_core\session_api::duplicate_session_as_new_training
     */
    public function test_duplicate_session_as_new_training() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        $session = \local_mentor_core\session_api::get_session($sessionid);

        $entityid = $session->get_entity()->id;

        // Wrong sessionid.
        $result = \local_mentor_core\session_api::duplicate_session_as_new_training(9999, 'trainingfullname', 'trainingshortname',
            $entityid);
        self::assertEquals(SESSION_NOT_FOUND, $result);

        // Wrong entityid.
        $result = \local_mentor_core\session_api::duplicate_session_as_new_training($session->id, 'trainingfullname',
            'trainingshortname',
            9999);
        self::assertEquals(SESSION_ENTITY_NOT_FOUND, $result);

        // Wrong training fullname.
        $result = \local_mentor_core\session_api::duplicate_session_as_new_training($session->id, '',
            'trainingshortname', $entityid);
        self::assertEquals(SESSION_TRAINING_NAME_EMPTY, $result);

        // Wrong training shortname.
        $result = \local_mentor_core\session_api::duplicate_session_as_new_training($session->id, 'trainingfullname',
            '', $entityid);
        self::assertEquals(SESSION_TRAINING_NAME_EMPTY, $result);

        // Wrong training shortname.
        $result = \local_mentor_core\session_api::duplicate_session_as_new_training($session->id, 'trainingfullname',
            $session->shortname, $entityid);
        self::assertEquals(SESSION_TRAINING_NAME_USED, $result);

        // Right case.
        $result = \local_mentor_core\session_api::duplicate_session_as_new_training($session->id, 'trainingfullname',
            'trainingshortname', $entityid);
        self::assertIsInt($result);

        // Bad permissions.
        $newuser = $this->getDataGenerator()->create_user();
        $this->setUser($newuser);

        try {
            $result = \local_mentor_core\session_api::duplicate_session_as_new_training($session->id, 'trainingfullname2',
                'trainingshortname2', $entityid);
        } catch (Exception $e) {
            self::assertInstanceOf('required_capability_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test duplicate_session_into_training
     *
     * @covers  \local_mentor_core\session_api::duplicate_session_into_training
     */
    public function test_duplicate_session_into_training() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        $session = \local_mentor_core\session_api::get_session($sessionid);

        // Wrong session id.
        $result = \local_mentor_core\session_api::duplicate_session_into_training(9999);
        self::assertEquals(SESSION_NOT_FOUND, $result);

        // Right case.
        $result = \local_mentor_core\session_api::duplicate_session_into_training($sessionid);
        self::assertIsInt($result);

        // Bad permissions.
        $newuser = $this->getDataGenerator()->create_user();
        $this->setUser($newuser);

        try {
            $result = \local_mentor_core\session_api::duplicate_session_into_training($sessionid);
        } catch (Exception $e) {
            self::assertInstanceOf('required_capability_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test duplicate_session_into_training
     *
     * @covers  \local_mentor_core\session_api::get_next_available_shortname_index
     */
    public function test_get_next_available_shortname_index() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        // No other session with the same short name.
        self::assertEquals(1, \local_mentor_core\session_api::get_next_available_shortname_index($session->shortname));

        // Create other session with same shortname and last next available shortname index.
        \local_mentor_core\session_api::create_session($session->get_training()->id, $session->shortname . ' ' . 1, true);

        // One other session with the same short name.
        self::assertEquals(2, \local_mentor_core\session_api::get_next_available_shortname_index($session->shortname));

        self::resetAllData();
    }

    /**
     * Test is_session_in_recycle_bin ok
     *
     * @covers  \local_mentor_core\session_api::is_session_in_recycle_bin
     */
    public function test_is_session_in_recycle_bin_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create entity.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $entity = $session->get_entity();
        $session->delete();

        // Return true value because one item have the same session shortname.
        self::assertTrue(\local_mentor_core\session_api::is_session_in_recycle_bin($session->shortname, $entity->id));

        self::resetAllData();
    }

    /**
     * Test is_session_in_recycle_bin not ok
     *
     * @covers  \local_mentor_core\session_api::is_session_in_recycle_bin
     */
    public function test_is_session_in_recycle_bin_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create entity.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $entity = $session->get_entity();

        // Disable recycle bin.
        set_config('categorybinenable', '0', 'tool_recyclebin');

        // Return false value because recycle bin is disable.
        self::assertFalse(\local_mentor_core\session_api::is_session_in_recycle_bin('shortname', $entity->id));

        // Enable recycle bin.
        set_config('categorybinenable', '1', 'tool_recyclebin');

        // Create user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        // Return false value because user does not have view capability.
        self::assertFalse(\local_mentor_core\session_api::is_session_in_recycle_bin('shortname', $entity->id));

        self::setAdminUser();

        // Disable autohide.
        set_config('autohide', '0', 'tool_recyclebin');

        // Return false value because user does not have view capability.
        self::assertFalse(\local_mentor_core\session_api::is_session_in_recycle_bin('shortname', $entity->id));

        // Eenable autohide.
        set_config('autohide', '0', 'tool_recyclebin');

        self::resetAllData();
    }

    /**
     * Test get_allowed_roles ok
     *
     * @covers  \local_mentor_core\session_api::get_allowed_roles
     */
    public function test_get_allowed_roles_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create entity.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $allowedrolesdata = [
            'participant' => [
                'name' => 'Participant',
                'shortname' => 'participant',
            ],
            'tuteur' => [
                'name' => 'Tuteur',
                'shortname' => 'tuteur',
            ],
            'formateur' => [
                'name' => 'Formateur',
                'shortname' => 'formateur',
            ],
        ];

        $allowsroles = \local_mentor_core\session_api::get_allowed_roles($session->courseid);

        self::assertCount(3, $allowsroles);

        foreach ($allowsroles as $rolename => $role) {
            self::assertTrue(array_key_exists($rolename, $allowsroles));
            self::assertEquals($role->name, $allowedrolesdata[$rolename]['name']);
            self::assertEquals($role->shortname, $allowedrolesdata[$rolename]['shortname']);
        }

        self::resetAllData();
    }

    /**
     * Test get_sessions_by_training
     *
     * @covers  \local_mentor_core\session_api::get_sessions_by_training
     * @covers  \local_mentor_core\database_interface::get_sessions_by_training_id
     */
    public function test_get_sessions_by_training() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $training = $this->init_training_creation();
        self::assertCount(0, \local_mentor_core\session_api::get_sessions_by_training($training->id));

        $session1 = \local_mentor_core\session_api::create_session($training->id, 'session 1', true);
        $sessions = \local_mentor_core\session_api::get_sessions_by_training($training->id);
        self::assertCount(1, $sessions);
        self::assertEquals($session1->id, $sessions[0]->id);

        $session2 = \local_mentor_core\session_api::create_session($training->id, 'session 3', true);
        $sessions = \local_mentor_core\session_api::get_sessions_by_training($training->id);
        self::assertCount(2, $sessions);
        self::assertEquals($session1->id, $sessions[0]->id);
        self::assertEquals($session2->id, $sessions[1]->id);

        $session3 = \local_mentor_core\session_api::create_session($training->id, 'session 2', true);
        $sessions = \local_mentor_core\session_api::get_sessions_by_training($training->id, 'id');
        self::assertCount(3, $sessions);
        self::assertEquals($session1->id, $sessions[0]->id);
        self::assertEquals($session2->id, $sessions[1]->id);
        self::assertEquals($session3->id, $sessions[2]->id);

        $sessions = \local_mentor_core\session_api::get_sessions_by_training($training->id, 'courseshortname');
        self::assertCount(3, $sessions);
        self::assertEquals($session1->id, $sessions[0]->id);
        self::assertEquals($session2->id, $sessions[2]->id);
        self::assertEquals($session3->id, $sessions[1]->id);

        self::resetAllData();
    }

    /**
     * Test entity_selector_sessions_recyclebin_template
     *
     * @covers  \local_mentor_core\session_api::entity_selector_sessions_recyclebin
     */
    public function test_entity_selector_sessions_recyclebin() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();
        $user1 = self::getDataGenerator()->create_user();

        // Create entity.
        $entity1id = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity1 = \local_mentor_core\entity_api::get_entity($entity1id);

        self::setUser($user1);

        // Not assigned as a manager.
        self::assertEmpty(\local_mentor_core\session_api::entity_selector_sessions_recyclebin($entity1id));

        self::setAdminUser();

        $entity1->assign_manager($user1->id);

        self::setUser($user1);

        // Is not a manager of more than one entity.
        self::assertEmpty(\local_mentor_core\session_api::entity_selector_sessions_recyclebin($entity1id));

        self::setAdminUser();

        // Create entity.
        $entity2id = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 2',
            'shortname' => 'New Entity 2',
        ]);
        $entity2 = \local_mentor_core\entity_api::get_entity($entity2id);
        $entity2->assign_manager($user1->id);

        self::setUser($user1);

        $entityselector = \local_mentor_core\session_api::entity_selector_sessions_recyclebin($entity1id);

        // Is manager of two entities.
        self::assertIsObject($entityselector);
        self::assertObjectHasAttribute('switchentities', $entityselector);
        self::assertIsArray($entityselector->switchentities);
        self::assertCount(2, $entityselector->switchentities);

        self::assertEquals('New Entity 1', $entityselector->switchentities[0]->name);
        self::assertEquals(
            $CFG->wwwroot . "/local/session/pages/recyclebin_sessions.php?entityid=" . $entity1id,
            $entityselector->switchentities[0]->link->out()
        );
        self::assertTrue($entityselector->switchentities[0]->selected);

        self::assertEquals('New Entity 2', $entityselector->switchentities[1]->name);
        self::assertEquals(
            $CFG->wwwroot . "/local/session/pages/recyclebin_sessions.php?entityid=" . $entity2id,
            $entityselector->switchentities[1]->link->out()
        );
        self::assertFalse($entityselector->switchentities[1]->selected);

        self::resetAllData();
    }

    /**
     * Test add_user_favourite_session function
     *
     * @covers \local_mentor_core\session_api::add_user_favourite_session
     */
    public function test_add_user_favourite_session() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $DB->delete_records('user_info_field');
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $DB->delete_records('favourite');

        // Not favourite.
        self::assertFalse($DB->record_exists('favourite', ['userid' => $user->id]));

        \local_mentor_core\session_api::add_user_favourite_session($session->id);

        // New favourite.
        self::assertTrue($DB->record_exists('favourite', ['userid' => $user->id]));

        // Get favourite.
        $userfavourite = $DB->get_records('favourite', ['userid' => $user->id]);

        // Check data favourite.
        self::assertCount(1, $userfavourite);
        $userfavouriteid = current($userfavourite)->id;
        self::assertEquals($userfavourite[$userfavouriteid]->component, 'local_session');
        self::assertEquals($userfavourite[$userfavouriteid]->itemtype, 'favourite_session');
        self::assertEquals($userfavourite[$userfavouriteid]->itemid, $session->id);
        self::assertEquals($userfavourite[$userfavouriteid]->contextid, $session->get_context()->id);
        self::assertEquals($userfavourite[$userfavouriteid]->userid, $user->id);

        self::resetAllData();
    }

    /**
     * Test remove_user_favourite_session function
     *
     * @covers \local_mentor_core\session_api::remove_user_favourite_session
     */
    public function test_remove_user_favourite_session() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $DB->delete_records('user_info_field');
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $DB->delete_records('favourite');

        // Not favourite.
        self::assertFalse($DB->record_exists('favourite', ['userid' => $user->id]));

        // Add favourite with defined user.
        $favourite = new \stdClass();
        $favourite->component = 'local_session';
        $favourite->itemtype = 'favourite_session';
        $favourite->itemid = $session->id;
        $favourite->contextid = $session->get_context()->id;
        $favourite->userid = $user->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $favouriteid1 = $DB->insert_record('favourite', $favourite);

        $favourites = $DB->get_records('favourite');
        self::assertCount(1, $favourites);
        self::assertArrayHasKey($favouriteid1, $favourites);

        \local_mentor_core\session_api::remove_user_favourite_session($session->id);

        $favourites = $DB->get_records('favourite');
        self::assertEmpty($favourites);

        self::resetAllData();
    }

    /**
     * Test get_all_roles function
     *
     * @covers \local_mentor_core\session_api::get_all_roles
     */
    public function test_get_all_roles() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $allrolecourse = \local_mentor_core\session_api::get_all_roles($session->get_course()->id);
        $allrole = $DB->get_records('role', null, '', 'shortname, *');

        self::assertCount(count($allrole), $allrolecourse);

        foreach ($allrolecourse as $key => $role) {
            self::assertEquals($role->id, $allrole[$key]->id);
        }

        self::resetAllData();
    }

    /**
     * Test get edadmin course view capability
     *
     * @covers \local_mentor_core\session_api::get_edadmin_course_view_capability
     */
    public function test_get_edadmin_course_view_capability_session() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        self::assertEquals(
            'local/mentor_core:movesessions',
            \local_mentor_core\session_api::get_edadmin_course_view_capability()
        );

        self::resetAllData();
    }


    /**
     * Test retrieving user sessions that are part of a program course
     *
     * @covers \local_mentor_core\session_api::get_user_sessions
     */
    public function test_get_user_sessions_with_program_course() {
         global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $userid = $this->init_create_user();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation("Session Programcourse");

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session->opento = 'all';
        $session->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
        \local_mentor_core\session_api::update_session($session);



        // Create 2 sessions.
        $session1Id = \local_mentor_core\session_api::create_session($session->get_training()->id, 'Session 1', true);
        $session2Id = \local_mentor_core\session_api::create_session($session->get_training()->id, 'Session 2', true);

         // Get session.
         try {
            $session1 = \local_mentor_core\session_api::get_session($session1Id);
            $session2 = \local_mentor_core\session_api::get_session($session2Id);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session1->opento = 'all';
        $session1->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
        \local_mentor_core\session_api::update_session($session1);
         // Updating the status session to have return sessions.
         $session2->opento = 'all';
         $session2->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
         \local_mentor_core\session_api::update_session($session2);

        // Add sessions A and B as modules to the program session.
        $DB->insert_record('programcourse', ['course' => $session->get_course()->id, 'courseid' => $session1->get_course()->id,'intro' => '','timemodified' => time()]);
        $DB->insert_record('programcourse', ['course' => $session->get_course()->id, 'courseid' => $session2->get_course()->id,'intro' => '','timemodified' => time()]);


        // Create self enrolment instance.
        $session->create_self_enrolment_instance();
        $dbi = \enrol_program\database_interface::get_instance();

        //Add session 1 as a module to "session programcourse"
        $instance = new \stdClass();
        $instance->courseid = $session1->get_course()->id;
        $instance->enrol = 'program';
        $instance->status = ENROL_INSTANCE_ENABLED;
        $instance->customint1 = $session->get_course()->id; // Id of the "parent" course (the program).
        $instance->timecreated = time();
        $instance->timemodified = $instance->timecreated;
        $dbi->insert_enrol_instance($instance);
        //Add session 2 as a module to "session programcourse"
        $instance1 = new \stdClass();
        $instance1->courseid = $session2->get_course()->id;
        $instance1->enrol = 'program';
        $instance1->status = ENROL_INSTANCE_ENABLED;
        $instance1->customint1 = $session->get_course()->id; // Id of the "parent" course (the program).
        $instance1->timecreated = time();
        $instance1->timemodified = $instance1->timecreated;
        $dbi->insert_enrol_instance($instance1);
        
        // Enrol user.
        self::setUser($userid);
        $session->enrol_current_user();
       
     
        // User is enrolled.
        self::assertCount(1, \local_mentor_core\session_api::get_user_sessions($userid)); 

        //Get user sessions
        $userSessions = \local_mentor_core\session_api::get_user_sessions($userid);

        self::assertCount(1, $userSessions);       
        self::assertEquals($session->id, $userSessions[0]->id);
        self::resetAllData();
    }

    /**
     * Test retrieving user archived sessions that are part of a program course
     *
     * @covers \local_mentor_core\session_api::get_user_sessions
     */
    public function test_get_user_archived_sessions_with_program_course() {
       
        global $DB; 
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();
 
        self::setAdminUser();

        $userid = $this->init_create_user();
              
        // Create session.
        $sessionid = $this->init_session_creation("Session Programcourse");

        // Get session.
        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session->opento = 'all';
        $session->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
        \local_mentor_core\session_api::update_session($session);



        // Create 2 sessions.
        $session1Id = \local_mentor_core\session_api::create_session($session->get_training()->id, 'Session 1', true);
        $session2Id = \local_mentor_core\session_api::create_session($session->get_training()->id, 'Session 2', true);

         // Get session.
         try {
            $session1 = \local_mentor_core\session_api::get_session($session1Id);
            $session2 = \local_mentor_core\session_api::get_session($session2Id);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Updating the status session to have return sessions.
        $session1->opento = 'all';
        $session1->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
        \local_mentor_core\session_api::update_session($session1);
         // Updating the status session to have return sessions.
         $session2->opento = 'all';
         $session2->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
         \local_mentor_core\session_api::update_session($session2);

        // Add sessions A and B as modules to the program session.
        $DB->insert_record('programcourse', ['course' => $session->get_course()->id, 'courseid' => $session1->get_course()->id,'intro' => '','timemodified' => time()]);
        $DB->insert_record('programcourse', ['course' => $session->get_course()->id, 'courseid' => $session2->get_course()->id,'intro' => '','timemodified' => time()]);

        self::setAdminUser();
        // Create self enrolment instance.
        $session->create_self_enrolment_instance();
        $dbi = \enrol_program\database_interface::get_instance();

        //Add session 1 as a module to "session programcourse"
        $instance = new \stdClass();
        $instance->courseid = $session1->get_course()->id;
        $instance->enrol = 'program';
        $instance->status = ENROL_INSTANCE_ENABLED;
        $instance->customint1 = $session->get_course()->id; // Id of the "parent" course (the program).
        $instance->timecreated = time();
        $instance->timemodified = $instance->timecreated;
        $dbi->insert_enrol_instance($instance);
        //Add session 2 as a module to "session programcourse"
        $instance1 = new \stdClass();
        $instance1->courseid = $session2->get_course()->id;
        $instance1->enrol = 'program';
        $instance1->status = ENROL_INSTANCE_ENABLED;
        $instance1->customint1 = $session->get_course()->id; // Id of the "parent" course (the program).
        $instance1->timecreated = time();
        $instance1->timemodified = $instance1->timecreated;
        $dbi->insert_enrol_instance($instance1);
        
        // Enrol user.
        self::setUser($userid);
        $session->enrol_current_user();
       
     
        // User is enrolled.
        self::assertCount(1, \local_mentor_core\session_api::get_user_sessions($userid)); 

        //Get user sessions
        $userSessions = \local_mentor_core\session_api::get_user_sessions($userid);

        self::assertCount(1, $userSessions);       
        self::assertEquals($session->id, $userSessions[0]->id);
        self::setAdminUser();
        //Update session status to archived
        $session->status = \local_mentor_core\session::STATUS_ARCHIVED;
        \local_mentor_core\session_api::update_session($session);
        self::setUser($userid);
        //Get user sessions
        $userSessions = \local_mentor_core\session_api::get_user_sessions($userid);

        self::assertCount(1, $userSessions);       
        self::assertEquals($session->id, $userSessions[0]->id);

        self::resetAllData();
    }
}

