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
 * Tasks tests
 *
 * @package    local_mentor_core
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mentor_core\session_form;
use local_mentor_core\specialization;
use local_mentor_core\training;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/classes/task/cleanup_trainings_and_sessions.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/task/duplicate_session_as_new_training_task.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/task/duplicate_session_into_training_task.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/task/create_session_task.php');
require_once($CFG->dirroot . '/local/mentor_core/api/session.php');
require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');

class local_mentor_core_tasks_testcase extends advanced_testcase {

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
    public function init_session_creation() {
        // Create training.
        $training = $this->init_training_creation();

        $sessionname = 'TESTUNITCREATESESSION';

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
     * Test cleanup_trainings_and_sessions
     *
     * @covers \local_mentor_core\task\cleanup_trainings_and_sessions::get_name
     * @covers \local_mentor_core\task\cleanup_trainings_and_sessions::execute
     */
    public function test_cleanup_trainings_and_sessions() {
        global $DB;

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

        $training = $session->get_training();

        $task = new \local_mentor_core\task\cleanup_trainings_and_sessions();

        // Check task name.
        self::assertIsString($task->get_name());

        $DB->delete_records('course', ['id' => $session->get_course()->id]);
        $DB->delete_records('course', ['id' => $training->get_course()->id]);

        $expected = 'Delete training id : ' . $training->id . ' and shortname : ' . $training->courseshortname . "\n";
        $expected .= 'Delete session id : ' . $session->id . ' and shortname : ' . $session->courseshortname . "\n";

        $this->expectOutputString($expected);
        $task->execute();

        self::resetAllData();
    }

    /**
     * Test duplicate_session_as_new_training
     *
     * @covers \local_mentor_core\task\duplicate_session_as_new_training_task::execute
     */
    public function test_duplicate_session_as_new_training_task() {
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

        $task = new \local_mentor_core\task\duplicate_session_as_new_training_task();

        // Missing session id.
        $customdata = new stdClass();
        try {
            $task->execute();
        } catch (\Exception $e) {
            // Session course has already been deleted.
            self::assertInstanceOf('coding_exception', $e);
        }

        // Missing training name.
        $customdata->sessionid = $session->id;
        $task->set_custom_data($customdata);
        try {
            $task->execute();
        } catch (\Exception $e) {
            // Session course has already been deleted.
            self::assertInstanceOf('coding_exception', $e);
        }

        // Missing training shortname.
        $customdata->trainingfullname = 'trainingname';
        $task->set_custom_data($customdata);
        try {
            $task->execute();
        } catch (\Exception $e) {
            // Session course has already been deleted.
            self::assertInstanceOf('coding_exception', $e);
        }

        // Missing entityid.
        $customdata->entityid = $session->get_entity()->id;
        $task->set_custom_data($customdata);
        try {
            $task->execute();
        } catch (\Exception $e) {
            // Session course has already been deleted.
            self::assertInstanceOf('coding_exception', $e);
        }

        // Training shortname already exists.
        $customdata->trainingshortname = $session->get_training()->shortname;
        $task->set_custom_data($customdata);
        $result = $task->execute();
        self::assertFalse($result);

        // Right case.
        $customdata->trainingshortname = 'trainingshortname';
        $task->set_custom_data($customdata);
        $this->expectOutputRegex('/trainingshortname/');
        $newtraining = $task->execute();

        self::assertIsNumeric($newtraining->id);
        self::assertEquals('trainingshortname', $newtraining->shortname);
        self::assertEquals('trainingname', $newtraining->name);
        self::assertEquals($session->get_entity()->id, $newtraining->get_entity()->id);

        self::resetAllData();
    }

    /**
     * Test duplicate_session_into_training_task
     *
     * @covers \local_mentor_core\task\duplicate_session_into_training_task::execute
     */
    public function test_duplicate_session_into_training_task() {
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

        $customdata = new stdClass();
        $task = new \local_mentor_core\task\duplicate_session_into_training_task();

        // Missing sessionid.
        $task->set_custom_data($customdata);
        try {
            $task->execute();
        } catch (\Exception $e) {
            // Session course has already been deleted.
            self::assertInstanceOf('coding_exception', $e);
        }

        // Empty session id.
        $customdata->sessionid = '';
        $task->set_custom_data($customdata);
        try {
            $task->execute();
        } catch (\Exception $e) {
            // Session course has already been deleted.
            self::assertInstanceOf('coding_exception', $e);
        }

        // Invalid session id.
        $customdata->sessionid = 123456789;
        $task->set_custom_data($customdata);

        $result = $task->execute();
        self::assertEquals(SESSION_NOT_FOUND, $result);

        // Right case.
        $customdata->sessionid = $session->id;
        $task->set_custom_data($customdata);
        $this->expectOutputRegex('/Session ' . $session->id . ' duplicated into training./');
        $training = $task->execute();

        self::assertEquals($training->id, $session->get_training()->id);

        self::resetAllData();
    }

    /**
     * Test create_session task
     *
     * @covers \local_mentor_core\task\create_session_task::execute
     */
    public function test_create_session_task() {
        global $USER;
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $training = $this->init_training_creation();

        $customdata = new stdClass();
        $task = new \local_mentor_core\task\create_session_task();

        $task->set_userid($USER->id);

        // Missing trainingid.
        $task->set_custom_data($customdata);
        try {
            $task->execute();
        } catch (\Exception $e) {
            // Session course has already been deleted.
            self::assertInstanceOf('coding_exception', $e);
        }

        $customdata->trainingid = $training->id;
        $task->set_custom_data($customdata);

        // Missing sessionname.
        try {
            $task->execute();
        } catch (\Exception $e) {
            // Session course has already been deleted.
            self::assertInstanceOf('coding_exception', $e);
        }

        $customdata->sessionname = 'Session name';
        $task->set_custom_data($customdata);

        $result = $task->execute();

        self::assertIsObject($result);
        self::assertIsNumeric($result->id);

        self::resetAllData();
    }

    /**
     * Test duplicate_training_task task not ok
     *
     * @covers \local_mentor_core\task\duplicate_training_task::execute
     */
    public function test_duplicate_training_task_not() {
        global $USER;
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $data = new \stdClass();
        $data->trainingid = 1;

        $taskrmock = $this->getMockBuilder('\local_mentor_core\task\duplicate_training_task')
            ->setMethods(['get_custom_data'])
            ->disableOriginalConstructor()
            ->getMock();

        $taskrmock->expects($this->once())
            ->method('get_custom_data')
            ->will($this->returnValue($data));

        try {
            $taskrmock->execute();
        } catch (\coding_exception $e) {
            self::assertInstanceOf('coding_exception', $e);
        }

        self::resetAllData();
    }
}
