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
use local_mentor_core\entity_api;
use local_mentor_core\session_api;
use local_mentor_core\training_api;
use local_mentor_core\training;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/api/training.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/session.php');

class local_mentor_core_training_testcase extends advanced_testcase {

    public const UNAUTHORISED_CODE = 2020120810;

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
        global $CFG;
        $CFG->defaultauth = 'manual';

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
        return $userid;
    }

    /**
     * Initialization of the database for the tests
     */
    public function init_database() {
        global $DB;

        // Delete Miscellaneous category.
        $DB->delete_records('course_categories', ['id' => 1]);
    }

    /**
     * Init entities data
     *
     * @return array
     */
    public function get_entities_data() {
        $this->init_database();

        return [
            [
                'name' => 'New Entity 1',
                'shortname' => 'New Entity 1',
                'regionid' => 5, // Corse.
                'userid' => 2,  // Set the admin user as manager of the entity.
            ],
            [
                'name' => 'New Entity 2',
                'shortname' => 'New Entity 2',
                'regionid' => 5, // Corse.
                'userid' => 2,  // Set the admin user as manager of the entity.
            ],
        ];
    }

    /**
     * Init training object
     *
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function get_training_data($entitydata = null, $shortname = 'shortname') {

        if ($entitydata === null) {
            $entitydata = $this->get_entities_data()[0];
        }

        // Init test data.
        $trainingdata = new stdClass();

        $trainingdata->name = 'fullname';
        $trainingdata->shortname = $shortname;
        $trainingdata->content = 'summary';

        // Create training object.
        $trainingdata->traininggoal = 'TEST TRAINING';
        $trainingdata->thumbnail = '';
        $trainingdata->status = \local_mentor_core\training::STATUS_DRAFT;

        try {
            // Get entity object for default category.
            $entityid = \local_mentor_core\entity_api::create_entity($entitydata);

            $entity = \local_mentor_core\entity_api::get_entity($entityid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Fill with entity data.
        $formationid = $entity->get_entity_formation_category();
        $trainingdata->categorychildid = $formationid;
        $trainingdata->categoryid = $entity->id;
        $trainingdata->creativestructure = $entity->id;

        return $trainingdata;
    }

    /**
     * Test create training
     *
     * @throws moodle_exception
     * @covers \local_mentor_core\training_api::create_training
     * @covers \local_mentor_core\training_api::get_training
     * @covers \local_mentor_core\training::update_training_course
     * @covers \local_mentor_core\training::update
     * @covers \local_mentor_core\training::get_context
     * @covers \local_mentor_core\training::get_course
     * @covers \local_mentor_core\training::create_files_by_training_form
     * @covers \local_mentor_core\training::create_course_training
     */
    public function test_create_training_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Init training data.
        $trainingdata = $this->get_training_data();

        /** @var training $training */
        $training = training_api::create_training($trainingdata);

        // Test if we have received an object.
        self::assertIsObject($training);
        self::assertInstanceOf(local_mentor_core\training::class, $training);

        self::resetAllData();
    }

    /**
     * Test create training nok
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\training_api::create_training
     * @covers \local_mentor_core\training_api::get_training
     * @covers \local_mentor_core\training::update_training_course
     * @covers \local_mentor_core\training::update
     * @covers \local_mentor_core\training::get_context
     * @covers \local_mentor_core\training::get_course
     * @covers \local_mentor_core\training::create_files_by_training_form
     * @covers \local_mentor_core\training::create_course_training
     */
    public function test_create_training_nok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Init training data.
        $trainingdata = $this->get_training_data();

        /** @var training $training */
        $training = training_api::create_training($trainingdata);

        // Test if we have received an object.
        self::assertIsObject($training);
        self::assertInstanceOf(local_mentor_core\training::class, $training);

        // Try to create the same training course.
        try {
            $duplicatedtraining = training_api::create_training($trainingdata);

        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test create training in sub entity
     *
     * @throws moodle_exception
     * @covers \local_mentor_core\training_api::create_training
     * @covers \local_mentor_core\training_api::get_training
     * @covers \local_mentor_core\training::update_training_course
     * @covers \local_mentor_core\training::update
     * @covers \local_mentor_core\training::get_context
     * @covers \local_mentor_core\training::get_course
     * @covers \local_mentor_core\training::create_files_by_training_form
     * @covers \local_mentor_core\training::create_course_training
     */
    public function test_create_training_in_sub_entity_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = \local_mentor_core\entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Init training data.
        $trainingdata = $this->get_training_data($subentitydata);

        /** @var training $training */
        $training = training_api::create_training($trainingdata);

        // Test if we have received an object.
        self::assertIsObject($training);
        self::assertInstanceOf(local_mentor_core\training::class, $training);

        // Test if training entity is not main entity.
        self::assertFalse($training->get_entity()->is_main_entity());

        // Check id main entity of training entity.
        self::assertEquals($entityid, $training->get_entity()->get_main_entity()->id);

        self::resetAllData();
    }

    /**
     * Test update training
     *
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\training_api::update_training
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training::get_context
     * @covers  \local_mentor_core\training::get_course
     * @covers  \local_mentor_core\training::update
     */
    public function test_update_training_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Init training data.
        $trainingdata = $this->get_training_data();

        /** @var training $training */
        $training = training_api::create_training($trainingdata);

        // Init test data.
        $trainingdata->id = $training->id;
        $trainingdata->traininggoal = 'TEST NEW TRAININGGOAL';
        $trainingdata->status = \local_mentor_core\training::STATUS_ARCHIVED;

        // Check with not updater user.
        self::setUser(self::init_create_user());
        try {
            $training = training_api::update_training($trainingdata);
        } catch (\Exception $e) {
            // User is not updater.
            self::assertInstanceOf('exception', $e);
        }

        self::setAdminUser();

        $training = training_api::update_training($trainingdata);

        // Test if we have received an object.
        self::assertIsObject($training);
        self::assertInstanceOf(local_mentor_core\training::class, $training);
        self::assertEquals($trainingdata->traininggoal, $training->traininggoal);
        self::assertEquals(\local_mentor_core\training::STATUS_ARCHIVED, $training->status);

        self::resetAllData();
    }

    /**
     * Test update training with error database update
     *
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\training_api::update_training
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training::get_context
     * @covers  \local_mentor_core\training::get_course
     * @covers  \local_mentor_core\training::update
     */
    public function test_update_training_nok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Init training data.
        $trainingdata = $this->get_training_data();

        /** @var training $training */
        $training = training_api::create_training($trainingdata);

        // Init test data.
        $trainingdata->id = $training->id;
        $trainingdata->traininggoal = 'TEST NEW TRAININGGOAL';
        $trainingdata->status = \local_mentor_core\training::STATUS_ARCHIVED;

        // Check with not updater user.
        self::setUser(self::init_create_user());
        try {
            $training = training_api::update_training($trainingdata);
        } catch (\Exception $e) {
            // User is not updater.
            self::assertInstanceOf('exception', $e);
        }

        self::setAdminUser();

        // Create database interface Mock.
        $dbinterfacemock = $this->getMockBuilder('\local_mentor_core\database_interface')
            ->setMethods(['update_training', 'get_course'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return one time false when call update_training function.
        $dbinterfacemock->expects($this->once())
            ->method('update_training')
            ->will($this->returnValue(false));

        // Return course value when get_course function call.
        $dbinterfacemock->expects($this->any())
            ->method('get_course')
            ->will($this->returnValue($training->get_course(true)));

        // Replace dbinterface data to training object with mock.
        $reflection = new ReflectionClass($training);
        $reflectionproperty = $reflection->getProperty('dbinterface');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($training, $dbinterfacemock);

        try {
            training_api::update_training($trainingdata);
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
            self::assertEquals($e->getMessage(), get_string('trainingupdatefailed', 'local_mentor_core'));
        }

        self::resetAllData();
    }

    /**
     * Test get training
     *
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     * @covers  \local_mentor_core\training::__construct
     */
    public function test_get_training_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        /** @var training $training */
        $training = training_api::create_training($this->get_training_data());

        $trainingobj = training_api::get_training($training->id);

        self::assertIsObject($trainingobj);
        self::assertInstanceOf(local_mentor_core\training::class, $trainingobj);
        self::assertEquals($training->id, $trainingobj->id);

        self::resetAllData();
    }

    /**
     * Test get training course
     *
     * @covers  \local_mentor_core\training_api::get_training_course
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training::get_course
     */
    public function test_get_training_course_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        /** @var training $training */
        $training = training_api::create_training($this->get_training_data());

        $course = training_api::get_training_course($training->id);

        self::assertIsObject($course);
        self::assertEquals($course->shortname, $training->courseshortname);

        self::resetAllData();
    }

    /**
     * Test get training by course id
     *
     * @covers  \local_mentor_core\training_api::get_training_by_course_id
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training_course
     * @covers  \local_mentor_core\training_api::get_training
     */
    public function test_get_training_by_course_id_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        /** @var training $training */
        $training = training_api::create_training($this->get_training_data());

        $course = training_api::get_training_course($training->id);
        $courseshortname = $course->shortname;

        $trainingobj = training_api::get_training_by_course_id($course->id);

        self::assertIsObject($trainingobj);
        self::assertEquals($courseshortname, $trainingobj->courseshortname);

        self::resetAllData();
    }

    /**
     * Test get training by course id not ok
     *
     * @covers  \local_mentor_core\training_api::get_training_by_course_id
     * @covers  \local_mentor_core\training_api::get_training
     */
    public function test_get_training_by_course_id_nok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $falsetrainingcourseid = 14214471;

        self::assertFalse(training_api::get_training_by_course_id($falsetrainingcourseid));

        self::resetAllData();
    }

    /**
     * Test get trainings by entity
     *
     * @covers  \local_mentor_core\training_api::get_trainings_by_entity
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training::get_entity
     * @covers  \local_mentor_core\training::get_url
     * @covers  \local_mentor_core\training::get_actions
     * @covers  \local_mentor_core\training::get_course
     */
    public function test_get_trainings_by_entity() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        /** @var training $training */
        $training = training_api::create_training($this->get_training_data());

        $trainings = training_api::get_trainings_by_entity($training->get_entity()->id);

        // Check if the entity has a training.
        self::assertCount(1, $trainings);

        // Try to call the api with a stdclass instead of an integer.
        $data = new stdClass();
        $data->entityid = $training->get_entity()->id;
        $trainings = training_api::get_trainings_by_entity($data);

        // Check if the entity has a training.
        self::assertCount(1, $trainings);

        // Check if the current training exists within the entity.
        self::assertArrayHasKey($training->id, $trainings);

        self::resetAllData();
    }

    /**
     * Test the duplication of a training
     *
     * @covers  \local_mentor_core\training_api::duplicate_training
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training::get_course
     * @covers  \local_mentor_core\training::duplicate
     * @covers  \local_mentor_core\task\duplicate_training_task::execute
     */
    public function test_duplicate_training_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        /** @var training $training */
        $training = training_api::create_training($this->get_training_data());

        $newtraining = training_api::duplicate_training($training->id, 'newtrainingshortname', null, true);

        // Check if an object has been created by the duplication.
        self::assertIsObject($newtraining);
        self::assertInstanceOf(local_mentor_core\training::class, $training);

        self::resetAllData();
    }

    /**
     * Test the duplication failures of a training
     *
     * @covers  \local_mentor_core\training_api::duplicate_training
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training::get_course
     * @covers  \local_mentor_core\training::duplicate
     */
    public function test_duplicate_training_nok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        /** @var training $training */
        $training = training_api::create_training($this->get_training_data());

        // Try to duplicate the training with an existing shortname.
        $id = training_api::duplicate_training($training->id, $training->shortname, null, true);
        self::assertEquals(-1, $id);

        // Defer the duplication.
        $result = training_api::duplicate_training($training->id, 'newtrainingshortname', null, false);
        self::assertIsInt($result);

        // Try to create the same training before the execution of the previous ad hoc task.
        $result = training_api::duplicate_training($training->id, 'newtrainingshortname', null, false);
        self::assertEquals(-1, $result);

        self::resetAllData();
    }

    /**
     * Test remove training
     *
     * @covers  \local_mentor_core\training_api::remove_training
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training::delete
     * @covers  \local_mentor_core\training::get_context
     * @covers  \local_mentor_core\training::get_course
     */
    public function test_remove_training_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        /** @var training $training */
        $training = training_api::create_training($this->get_training_data());

        $removeresult = training_api::remove_training($training->id);

        self::assertTrue($removeresult);

        self::resetAllData();
    }

    /**
     * Test get user training courses
     *
     * @covers  \local_mentor_core\training_api::get_user_training_courses
     */
    public function test_get_user_training_courses() {
        $this->resetAfterTest();

        global $DB;
        self::setAdminUser();

        $DB->delete_records('course_categories');

        $this->reset_singletons();
        $this->init_role();

        self::assertCount(count(entity_api::get_all_entities(true, [], true)), training_api::get_user_training_courses());

        // Create entity.
        $entityid = entity_api::create_entity(['name' => 'test', 'shortname' => 'test']);
        $entity = entity_api::get_entity($entityid);

        // Adding user to entity.
        $newuser = self::getDataGenerator()->create_user();
        $entity->assign_manager($newuser->id);

        self::assertCount(1, training_api::get_user_training_courses($newuser));

        self::resetAllData();
    }

    /**
     * Test get entities training managed
     *
     * @covers  \local_mentor_core\training_api::get_entities_training_managed
     */
    public function test_get_entities_training_managed_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        // Create entities.
        $entityid = entity_api::create_entity(['name' => 'test', 'shortname' => 'test']);
        $entity = entity_api::get_entity($entityid);

        $entityid2 = entity_api::create_entity(['name' => 'test2', 'shortname' => 'test2']);
        $entity2 = entity_api::get_entity($entityid2);

        // Adding user to entity.
        $newuser = self::getDataGenerator()->create_user();
        $entity->assign_manager($newuser->id);
        $entity2->assign_manager($newuser->id);

        self::setUser($newuser);

        self::assertCount(2, training_api::get_entities_training_managed());

        self::resetAllData();
    }

    /**
     * Test get entities training managed nok
     *
     * @covers  \local_mentor_core\training_api::get_entities_training_managed
     */
    public function test_get_entities_training_managed_nok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        // Create entities.
        $entityid = entity_api::create_entity(['name' => 'test', 'shortname' => 'test']);
        $subentityid = entity_api::create_sub_entity(['name' => 'sub', 'parentid' => $entityid, 'userid' => 2]);
        $subentity = entity_api::get_entity($subentityid);

        $newuser = self::getDataGenerator()->create_user();
        self::setUser($newuser);

        self::assertCount(0, training_api::get_entities_training_managed());

        // Assign user to the sub entity only.
        $subentity->assign_manager($newuser->id);

        self::assertCount(1, training_api::get_entities_training_managed());

        self::resetAllData();
    }

    /**
     * Test get next available training name
     *
     * @covers  \local_mentor_core\training_api::get_next_available_training_name
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training::get_course
     */
    public function test_get_next_available_training_name() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        $trainingdata = $this->get_training_data();

        /** @var training $training1 */
        $training1 = training_api::create_training($trainingdata);

        $trainingdata->shortname = 'shortname 1';

        // Create second training.
        training_api::create_training($trainingdata);

        self::assertEquals('shortname 2', training_api::get_next_available_training_name($training1->id));

        self::resetAllData();
    }

    /**
     * Test get user available sessions by trainings
     *
     * @covers  \local_mentor_core\training_api::get_user_available_sessions_by_trainings
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     * @covers  \local_mentor_core\training::get_entity
     * @covers  \local_mentor_core\training::get_course
     * @covers  \local_mentor_core\training::convert_for_template
     */
    public function test_get_user_available_sessions_by_trainings() {
        global $DB;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $newuser = self::getDataGenerator()->create_user();

        try {
            $training = training_api::create_training($this->get_training_data());

            $entity = $training->get_entity();

            // Add user to the default entity.
            $entity->add_member($newuser);

            // Session creation.
            $session = session_api::create_session($training->id, 'session 1', true);

        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Update status.
        $sessionupdate = new stdClass();
        $sessionupdate->id = $session->id;
        $sessionupdate->status = session::STATUS_OPENED_REGISTRATION;
        $session = \local_mentor_core\session_api::update_session($sessionupdate);

        // Session creation.
        $session2 = session_api::create_session($training->id, 'session 2', true);

        // Update status.
        $updatedata = new stdClass();
        $updatedata->id = $session2->id;
        $updatedata->status = session::STATUS_IN_PROGRESS;
        $updatedata->opento = 'all';
        $session2 = session_api::update_session($updatedata);

        $trainings = training_api::get_user_available_sessions_by_trainings($newuser->id);

        self::assertCount(2, $trainings[$training->id]->sessions);
        self::assertEquals($session->id, $trainings[$training->id]->sessions[1]->id);
        self::assertEquals($session2->id, $trainings[$training->id]->sessions[0]->id);

        // With course remove for training.
        $DB->delete_records('course', ['id' => $training->get_course()->id]);
        training_api::clear_cache();

        $trainings = training_api::get_user_available_sessions_by_trainings($newuser->id);
        self::assertCount(0, $trainings);

        self::resetAllData();
    }

    /**
     * Test get the trainings that the user designs
     *
     * @covers  \local_mentor_core\training_api::get_trainings_user_designer
     * @covers  \local_mentor_core\training_api::get_training_by_course_id
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training::get_course
     * @covers  \local_mentor_core\training::get_context
     * @covers  \local_mentor_core\training::convert_for_template
     */
    public function test_get_trainings_user_designer() {
        global $CFG;
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/enrol/self/externallib.php');

        // Create simple user.
        $user = new stdClass();
        $user->id = $this->init_create_user();

        /** @var training $training */
        $training = training_api::create_training($this->get_training_data());

        // Create self enrol instance.
        $plugin = enrol_get_plugin('self');
        $instance = (object) $plugin->get_instance_defaults();
        $instance->status = 0;
        $instance->id = '';
        $instance->courseid = $training->get_course()->id;
        $instance->customint1 = 0;
        $instance->customint2 = 0;
        $instance->customint3 = 0; // Max participants.
        $instance->customint4 = 1;
        $instance->customint5 = 0;
        $instance->customint6 = 1; // Enable.
        $instance->name = '';
        $instance->password = '';
        $instance->customtext1 = '';
        $instance->returnurl = '';
        $instance->expirythreshold = 0;
        $instance->enrolstartdate = 0;
        $instance->enrolenddate = 0;
        $fields = (array) $instance;
        $instanceid = $plugin->add_instance($training->get_course(), $fields);

        // Enrol user.
        self::setUser($user->id);
        \enrol_self_external::enrol_user($training->get_course()->id, null, $instanceid);
        self::setAdminUser();

        // Enrol the user into a random course (not a training course!).
        $randomcourse = self::getDataGenerator()->create_course();
        self::getDataGenerator()->enrol_user($user->id, $randomcourse->id);

        // Check user trainings enrolments.
        \local_mentor_core\profile_api::role_assign('editingteacher', $user->id, $training->get_context());
        $trainingsdesigner = \local_mentor_core\training_api::get_trainings_user_designer($user);
        self::assertCount(1, $trainingsdesigner);
        self::assertEquals($training->id, $trainingsdesigner[0]->id);

        // User is designer.
        self::assertTrue(has_capability('local/trainings:update', $training->get_context(), $user));

        self::resetAllData();
    }

    /**
     * Test get the trainings that the user designs with favourite
     *
     * @covers  \local_mentor_core\training_api::get_trainings_user_designer
     */
    public function test_get_trainings_user_designer_with_favourite() {
        global $DB;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create entity.
        $entityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Setting user data.
        $lastname = 'lastname';
        $firstname = 'firstname';
        $email = 'user@mail.fr';
        $auth = 'manual';
        \local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, $entity,
            null, $auth);
        $user = \core_user::get_user_by_email($email);

        // Create 5 training with draft status.
        for ($i = 1; $i <= 5; $i++) {
            $trainingdata = new \stdClass();
            $trainingdata->name = 'training' . $i;
            $trainingdata->shortname = 'training' . $i;
            $trainingdata->content = 'summary';
            $trainingdata->traininggoal = 'TEST TRAINING';
            $trainingdata->thumbnail = '';
            $trainingdata->status = \local_mentor_core\training::STATUS_DRAFT;
            $trainingdata->categorychildid = $entity->get_entity_formation_category();
            $trainingdata->categoryid = $entity->id;
            $trainingdata->creativestructure = $entity->id;
            $traningvarname = 'training' . $i;
            $$traningvarname = \local_mentor_core\training_api::create_training($trainingdata);
            $trainingcourse = $$traningvarname->get_course();

            // Enrol user.
            self::getDataGenerator()->enrol_user($user->id, $trainingcourse->id, 'concepteur');

            // Change time created course for the sort request.
            $trainingcourse->timecreated = intval($trainingcourse->timecreated) + (100 * $i);
            $DB->update_record('course', $trainingcourse);
        }

        self::setUser($user);

        $trainingsdesigner = \local_mentor_core\training_api::get_trainings_user_designer($user, true);

        self::assertCount(5, $trainingsdesigner);
        self::assertEquals($trainingsdesigner[0]->id, $training5->id);
        self::assertEquals($trainingsdesigner[1]->id, $training4->id);
        self::assertEquals($trainingsdesigner[2]->id, $training3->id);
        self::assertEquals($trainingsdesigner[3]->id, $training2->id);
        self::assertEquals($trainingsdesigner[4]->id, $training1->id);

        // Add training3 to favourite.
        $favourite = new \stdClass();
        $favourite->component = 'local_trainings';
        $favourite->itemtype = 'favourite_training';
        $favourite->itemid = $training3->id;
        $favourite->contextid = $training3->get_context()->id;
        $favourite->userid = $user->id;
        $favourite->timecreated = time() + 1000;
        $favourite->timemodified = time();
        $DB->insert_record('favourite', $favourite);

        $trainingsdesigner2 = \local_mentor_core\training_api::get_trainings_user_designer($user, true);

        self::assertCount(5, $trainingsdesigner2);
        self::assertEquals($trainingsdesigner2[0]->id, $training3->id);
        self::assertEquals($trainingsdesigner2[1]->id, $training5->id);
        self::assertEquals($trainingsdesigner2[2]->id, $training4->id);
        self::assertEquals($trainingsdesigner2[3]->id, $training2->id);
        self::assertEquals($trainingsdesigner2[4]->id, $training1->id);

        // Add training1 to favourite.
        $favourite = new \stdClass();
        $favourite->component = 'local_trainings';
        $favourite->itemtype = 'favourite_training';
        $favourite->itemid = $training1->id;
        $favourite->contextid = $training1->get_context()->id;
        $favourite->userid = $user->id;
        $favourite->timecreated = time() + 2000;
        $favourite->timemodified = time();
        $DB->insert_record('favourite', $favourite);

        $trainingsdesigner3 = \local_mentor_core\training_api::get_trainings_user_designer($user, true);

        self::assertCount(5, $trainingsdesigner3);
        self::assertEquals($trainingsdesigner3[0]->id, $training1->id);
        self::assertEquals($trainingsdesigner3[1]->id, $training3->id);
        self::assertEquals($trainingsdesigner3[2]->id, $training5->id);
        self::assertEquals($trainingsdesigner3[3]->id, $training4->id);
        self::assertEquals($trainingsdesigner3[4]->id, $training2->id);

        self::resetAllData();
    }

    /**
     * Test get training form
     *
     * @covers  \local_mentor_core\training_api::get_training_form
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     * @covers  \local_mentor_core\training_form::__construct
     * @covers  \local_mentor_core\training_form::definition
     */
    public function test_get_training_form() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        $training = $this->get_training_data();
        $entity = \local_mentor_core\entity_api::get_entity($training->categoryid);

        $forminfos = new stdClass();
        $forminfos->entity = $entity;
        $forminfos->logourl = 'mentor.fr';
        $forminfos->actionurl = 'mentor.fr';
        $url = 'mentor.fr';

        $form = \local_mentor_core\training_api::get_training_form($url, $forminfos);

        self::assertEquals('local_mentor_core\training_form', get_class($form));

        self::resetAllData();
    }

    /**
     * Test get trainings template
     *
     * @covers  \local_mentor_core\training_api::get_trainings_template
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     */
    public function test_get_trainings_template() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        $defaulttemplate = 'local_trainings/local_trainings';

        $template = training_api::get_trainings_template($defaulttemplate);
        self::assertEquals($defaulttemplate, $template);

        self::resetAllData();
    }

    /**
     * Test get trainings template javascript
     *
     * @covers  \local_mentor_core\training_api::get_trainings_javascript
     * @covers  \local_mentor_core\specialization::__construct
     * @covers  \local_mentor_core\specialization::get_instance
     * @covers  \local_mentor_core\specialization::get_specialization
     */
    public function test_get_trainings_javascript() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        $defaultjavascript = 'local_trainings/local_trainings';

        $javascript = training_api::get_trainings_javascript($defaultjavascript);
        self::assertEquals($defaultjavascript, $javascript);

        self::resetAllData();
    }

    /**
     * Test get status list
     *
     * @covers  \local_mentor_core\training_api::get_status_list
     */
    public function test_get_status_list() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        self::assertEquals([
            training::STATUS_DRAFT => training::STATUS_DRAFT,
            training::STATUS_TEMPLATE => training::STATUS_TEMPLATE,
            training::STATUS_ELABORATION_COMPLETED => training::STATUS_ELABORATION_COMPLETED,
            training::STATUS_ARCHIVED => training::STATUS_ARCHIVED,
        ], \local_mentor_core\training_api::get_status_list());

        self::resetAllData();
    }

    /**
     * Test move training
     *
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training_api::move_training
     */
    public function test_move_training_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        /** @var training $training */
        $training = training_api::create_training($this->get_training_data());

        $otherentitydata = $this->get_entities_data()[1];

        try {
            // Get entity object for default category.
            $otherentityid = \local_mentor_core\entity_api::create_entity($otherentitydata);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Move training.
        $result = \local_mentor_core\training_api::move_training($training->id, $otherentityid);

        self::assertTrue($result);

        // Refresh training data.
        $refreshtraining = training_api::get_training($training->id, true);

        // Check if new training entity is entity used to move training.
        self::assertEquals($refreshtraining->get_entity()->id, $otherentityid);

        // Move the training into the same entity.
        $result = \local_mentor_core\training_api::move_training($refreshtraining->id, $refreshtraining->get_entity()->id);
        self::assertTrue($result);

        self::resetAllData();
    }

    /**
     * Test move training not ok
     *
     * @covers  \local_mentor_core\training_api::create_training
     * @covers  \local_mentor_core\training_api::get_training
     * @covers  \local_mentor_core\training_api::move_training
     */
    public function test_move_training_nok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        /** @var training $training */
        $training = training_api::create_training($this->get_training_data());

        $otherentitydata = $this->get_entities_data()[1];

        try {
            // Get entity object for default category.
            $otherentityid = \local_mentor_core\entity_api::create_entity($otherentitydata);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Create a new user.
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        // Try to move training without any rights on the training entity.
        try {
            \local_mentor_core\training_api::move_training($training->id, $otherentityid);
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
        }

        // Set the new user as manager of the training entity.
        $trainingentity = $training->get_entity();
        $trainingentity->assign_manager($user->id);

        // Try to move training without any rights on the destination entity.
        try {
            \local_mentor_core\training_api::move_training($training->id, $otherentityid);
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test get_training_template_params
     *
     * @covers  \local_mentor_core\training_api::get_training_template_params
     */
    public function test_get_training_template_params() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        try {
            training_api::get_training_template_params();

            $params = new stdClass();
            $params->test = 1;
            training_api::get_training_template_params($params);

        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test restore training
     *
     * @covers  \local_mentor_core\training_api::restore_training
     */
    public function test_restore_training() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        $training = training_api::create_training($this->get_training_data());
        $trainingname = $training->name;

        $entity = $training->get_entity();

        // One training in entity and zero remove training.
        self::assertCount(1, $entity->get_trainings());
        self::assertCount(0, $entity->get_training_recyclebin_items());

        local_mentor_core\training_api::remove_training($training->id);

        // Zero training in entity and one remove training.
        $trainingrecycleitems = $entity->get_training_recyclebin_items();
        self::assertCount(1, $trainingrecycleitems);
        self::assertEquals($trainingname, $trainingrecycleitems[0]->name);
        self::assertCount(0, $entity->get_trainings());

        \local_mentor_core\training_api::restore_training($entity->id, $trainingrecycleitems[0]->id);

        // One training in entity and zero remove training.
        self::assertCount(0, $entity->get_training_recyclebin_items());
        self::assertCount(1, $entity->get_trainings());

        self::resetAllData();
    }

    /**
     * Test remove training item
     *
     * @covers  \local_mentor_core\training_api::remove_training_item
     */
    public function test_remove_training_item() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();
        $training = training_api::create_training($this->get_training_data());

        $trainingname = $training->name;

        $entity = $training->get_entity();

        // One training in entity and zero remove training.
        self::assertCount(1, $entity->get_trainings());
        self::assertCount(0, $entity->get_training_recyclebin_items());

        local_mentor_core\training_api::remove_training($training->id);

        // Zero training in entity and one remove training.
        $trainingrecycleitems = $entity->get_training_recyclebin_items();
        self::assertCount(1, $trainingrecycleitems);
        self::assertEquals($trainingname, $trainingrecycleitems[0]->name);
        self::assertCount(0, $entity->get_trainings());

        try {
            \local_mentor_core\training_api::remove_training_item($entity->id, $trainingrecycleitems[0]->id, 'url');
        } catch (moodle_exception $e) {
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Zero training in entity and zero remove training.
        self::assertCount(0, $entity->get_training_recyclebin_items());
        self::assertCount(0, $entity->get_trainings());

        self::resetAllData();
    }

    /**
     * Test entity selector trainings recyclebin template
     *
     * @covers  \local_mentor_core\training_api::entity_selector_trainings_recyclebin_template
     */
    public function test_entity_selector_trainings_recyclebin_template() {
        global $DB;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        $training = training_api::create_training($this->get_training_data());

        $entity = $training->get_entity();

        self::assertEmpty(\local_mentor_core\training_api::entity_selector_trainings_recyclebin_template($entity->id));

        \local_mentor_core\entity_api::create_entity([
            'name' => 'New Sub Entity 1',
            'shortname' => 'New Sub Entity 1',
            'parentid' => $entity->id,
        ]);

        \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 2',
            'shortname' => 'New Entity 2',
        ]);

        self::assertNotEmpty(\local_mentor_core\training_api::entity_selector_trainings_recyclebin_template($entity->id));

        self::resetAllData();
    }

    /**
     * Test add trainings user designer favourite
     *
     * @covers  \local_mentor_core\training_api::add_trainings_user_designer_favourite
     */
    public function test_add_trainings_user_designer_favourite() {
        global $DB;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        $training = training_api::create_training($this->get_training_data());
        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        $favourite = [];
        $favourite['component'] = 'local_trainings';
        $favourite['itemtype'] = 'favourite_training';
        $favourite['itemid'] = $training->id;
        $favourite['contextid'] = $training->get_context()->id;
        $favourite['userid'] = $user->id;

        self::assertFalse($DB->record_exists('favourite', $favourite));

        \local_mentor_core\training_api::add_trainings_user_designer_favourite($training->id);

        self::assertTrue($DB->record_exists('favourite', $favourite));

        self::resetAllData();
    }

    /**
     * Test remove trainings user designer favourite
     *
     * @covers  \local_mentor_core\training_api::remove_trainings_user_designer_favourite
     */
    public function test_remove_trainings_user_designer_favourite() {
        global $DB;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        $training = training_api::create_training($this->get_training_data());
        $user = self::getDataGenerator()->create_user();

        $favourite = new \stdClass();
        $favourite->component = 'local_trainings';
        $favourite->itemtype = 'favourite_training';
        $favourite->itemid = $training->id;
        $favourite->contextid = $training->get_context()->id;
        $favourite->userid = $user->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $DB->insert_record('favourite', $favourite);

        $favourite = [];
        $favourite['component'] = 'local_trainings';
        $favourite['itemtype'] = 'favourite_training';
        $favourite['itemid'] = $training->id;
        $favourite['contextid'] = $training->get_context()->id;
        $favourite['userid'] = $user->id;
        self::assertTrue($DB->record_exists('favourite', $favourite));

        self::setUser($user);

        \local_mentor_core\training_api::remove_trainings_user_designer_favourite($training->id);

        self::assertFalse($DB->record_exists('favourite', $favourite));

        self::resetAllData();
    }

    /**
     * Test get edadmin course view capability
     *
     * @covers  \local_mentor_core\training_api::get_edadmin_course_view_capability
     */
    public function test_get_edadmin_course_view_capability_training() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();
        self::setAdminUser();

        self::assertEquals(
            'local/mentor_core:movetrainings',
            \local_mentor_core\training_api::get_edadmin_course_view_capability()
        );

        self::resetAllData();
    }
}
