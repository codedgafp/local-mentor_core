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
 * Tests for dbinterface class
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mentor_core\session;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/database_interface.php');
require_once($CFG->dirroot . '/local/mentor_core/api/training.php');

class local_mentor_core_dbinterface_testcase extends advanced_testcase {

    const ENTITY_NAMES = ['New Entity 1', 'New Entity 2', 'New, Entity 3'];

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
        $user->profile_field_mainentity = 'New Entity 1';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        return $userid;
    }

    /**
     * Initialization of the entity data
     *
     * @param string $entityname
     * @return int
     */
    public function init_create_entity($entityname = 'New Entity 1') {

        $entitydata = [
            'name' => $entityname,
            'shortname' => $entityname,
        ];

        // Test standard Entity creation.
        try {
            $entityid = \local_mentor_core\entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        return $entityid;
    }

    /**
     * Create a training
     *
     * @param $name
     * @param $shortname
     * @param $entityid
     * @return \local_mentor_core\training
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function init_create_training($name, $shortname, $entityid) {
        // Init test data.
        $trainingdata = new stdClass();

        $trainingdata->name = $name;
        $trainingdata->shortname = $shortname;
        $trainingdata->content = 'summary';

        // Create training object.
        $trainingdata->teaser = 'http://www.edunao.com/';
        $trainingdata->teaserpicture = '';
        $trainingdata->prerequisite = 'TEST';
        $trainingdata->collection = 'accompagnement';
        $trainingdata->traininggoal = 'TEST TRAINING ';
        $trainingdata->idsirh = 'TEST ID SIRH';
        $trainingdata->licenseterms = 'cc-sa';
        $trainingdata->typicaljob = 'TEST';
        $trainingdata->skills = [1, 3];
        $trainingdata->certifying = '1';
        $trainingdata->presenceestimatedtimehours = '12';
        $trainingdata->presenceestimatedtimeminutes = '10';
        $trainingdata->remoteestimatedtimehours = '15';
        $trainingdata->remoteestimatedtimeminutes = '30';
        $trainingdata->trainingmodalities = 'd';
        $trainingdata->producingorganization = 'TEST';
        $trainingdata->producerorganizationlogo = '';
        $trainingdata->designers = 'TEST';
        $trainingdata->contactproducerorganization = 'TEST';
        $trainingdata->thumbnail = '';
        $trainingdata->status = \local_mentor_core\training::STATUS_DRAFT;

        try {
            $entity = \local_mentor_core\entity_api::get_entity($entityid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Fill with entity data.
        $formationid = $entity->get_entity_formation_category();
        $trainingdata->categorychildid = $formationid;
        $trainingdata->categoryid = $entity->id;
        $trainingdata->creativestructure = $entity->id;

        return \local_mentor_core\training_api::create_training($trainingdata);
    }

    /**
     * Initalize a session
     *
     * @return session
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function init_create_session() {
        $entityid = $this->init_create_entity();
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);

        $sessionname = 'sessionname';

        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        return $session;
    }

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

    public function access_protected($obj, $prop) {
        $reflection = new ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    /**
     * Test get user by email
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_user_by_email
     */
    public function test_get_user_by_email() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create user.
        self::getDataGenerator()->create_user(['email' => 'test@test.com']);

        // Test with a valid email.
        $user = $dbinterface->get_user_by_email('test@test.com');
        self::assertIsObject($user);

        // Test with a wrong email.
        $wronguser = $dbinterface->get_user_by_email('wrong@test.com');
        self::assertFalse($wronguser);

        self::resetAllData();
    }

    /**
     * Test get user by id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_user_by_id
     */
    public function test_get_user_by_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create user.
        $user = self::getDataGenerator()->create_user();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Test with a valid email.
        $userbyid = $dbinterface->get_user_by_id($user->id);
        self::assertIsObject($userbyid);
        self::assertEquals($user->username, $userbyid->username);

        // Test the user caching.
        $userbyid = $dbinterface->get_user_by_id($user->id);
        self::assertIsObject($user);
        self::assertEquals($user->username, $userbyid->username);

        // Test with a wrong email.
        try {
            $wronguser = $dbinterface->get_user_by_id(123456789);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test get user by username
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_user_by_username
     */
    public function test_get_user_by_username() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $user = self::getDataGenerator()->create_user(['username' => 'testusername']);

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Test with a valid email.
        $userbyusername = $dbinterface->get_user_by_username('testusername');
        self::assertIsObject($userbyusername);
        self::assertEquals($userbyusername->id, $user->id);

        // Test with a wrong user.
        $wronguser = $dbinterface->get_user_by_username('wrongusername');
        self::assertFalse($wronguser);

        self::resetAllData();
    }

    /**
     * Test search users
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::search_users
     */
    public function test_search_users() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $user = self::getDataGenerator()->create_user(['firstname' => 'firstname', 'lastname' => 'lastname']);

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $firstname = 'firstname';
        $lastname = 'lastname';

        // Search for a valid user.
        $search = $dbinterface->search_users($firstname . ' ' . $lastname, []);
        self::assertCount(1, $search);

        self::assertEquals($search[$user->id]->lastname, $lastname);
        self::assertEquals($search[$user->id]->firstname, $firstname);

        // Search for a wrong user.
        $search = $dbinterface->search_users('wronguser', []);
        self::assertCount(0, $search);

        self::resetAllData();
    }

    /**
     * Test update entity
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::update_entity
     */
    public function test_update_entity() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $falseentity = new stdClass();
        $falseentity->name = "New name";

        // Try to update an entity without entity id.
        try {
            $dbinterface->update_entity($falseentity);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Update the entity with the right data.
        $category = self::getDataGenerator()->create_category();
        $entity = $DB->get_record('course_categories', ['id' => $category->id]);
        $oldname = $entity->name;
        $entity->name = "new name";
        self::assertTrue($dbinterface->update_entity($entity));

        $entityupdate = $DB->get_record('course_categories', ['id' => $category->id]);
        self::assertNotEquals($entity->name, $oldname);
        self::assertEquals($entityupdate->name, "new name");

        self::resetAllData();
    }

    /**
     * Test get file from database
     *
     * @covers \local_mentor_core\database_interface::get_file_from_database
     */
    public function test_get_file_from_database() {
        global $CFG;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $fs = get_file_storage();

        $contextid = context_system::instance()->id;
        $component = 'local_mentor_core';
        $filearea = 'test';
        $itemid = 0;

        $filerecord = new stdClass();
        $filerecord->contextid = $contextid;
        $filerecord->component = $component;
        $filerecord->filearea = $filearea;
        $filerecord->itemid = $itemid;
        $filerecord->filepath = '/';
        $filerecord->filename = 'logo.png';

        $filepath = $CFG->dirroot . '/local/mentor_core/pix/logo.png';

        // Create file.
        $fs->create_file_from_pathname($filerecord, $filepath);

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Get file.
        $file = $dbinterface->get_file_from_database($contextid, $component, $filearea, $itemid);

        self::assertIsObject($file);

        self::resetAllData();
    }

    /**
     * Test create_course_category
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::create_course_category
     */
    public function test_create_course_category() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Try to create a category without category name.
        try {
            $dbinterface->create_course_category('');
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Try to create a category with an invalid parent category id.
        try {
            $dbinterface->create_course_category('Entity name', 12345);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Create the category with valid data.
        $category = $dbinterface->create_course_category('Entity name');
        self::assertIsObject($category);

        self::resetAllData();
    }

    /**
     * Test get course category by name
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception&#39
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_course_category_by_name
     */
    public function test_get_course_category_by_name() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $categoryname = 'Entity name';

        // Create the category with valid data.
        $createdcategoryid = \local_mentor_core\entity_api::create_entity(['name' => $categoryname, 'shortname' => $categoryname]);
        $createdcategory = \local_mentor_core\entity_api::get_entity($createdcategoryid);

        // Get a valid category.
        $category = $dbinterface->get_course_category_by_name($categoryname);
        self::assertIsObject($category);
        self::assertEquals($createdcategory->id, $category->id);

        // Try again to test the caching.
        $category = $dbinterface->get_course_category_by_name($categoryname);
        self::assertIsObject($category);
        self::assertEquals($createdcategory->id, $category->id);

        // Try the refresh option.
        $category = $dbinterface->get_course_category_by_name($categoryname, true);
        self::assertIsObject($category);
        self::assertEquals($createdcategory->id, $category->id);

        // Get a wrong category.
        $category = $dbinterface->get_course_category_by_name('Wrong name');
        self::assertFalse($category);

        // Create a subcategory.
        $data = ['name' => 'Sous catégorie', 'parentid' => $createdcategory->id];

        $subcategoryid = \local_mentor_core\entity_api::create_sub_entity($data);

        // Get a wrong category.
        $category = $dbinterface->get_course_category_by_name('Sous catégorie', false, true);
        self::assertFalse($category);

        // Get a right category.
        $category = $dbinterface->get_course_category_by_name('Sous catégorie', true, false);
        self::assertIsObject($category);
        self::assertEquals($subcategoryid, $category->id);

        // Get empty result.
        $category = $dbinterface->get_course_category_by_name('Sous catégorie', true, true);
        self::assertFalse($category);

        self::resetAllData();
    }

    /**
     * Test get main entity by name
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_main_entity_by_name
     */
    public function test_get_main_entity_by_name() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $categoryname = 'Entity name';

        // Create the category with valid data.
        $createdcategoryid = \local_mentor_core\entity_api::create_entity(['name' => $categoryname, 'shortname' => $categoryname]);
        $createdcategory = \local_mentor_core\entity_api::get_entity($createdcategoryid);

        // Get a valid category.
        $category = $dbinterface->get_main_entity_by_name($categoryname);
        self::assertIsObject($category);
        self::assertEquals($createdcategory->id, $category->id);

        // Try again to test the refresh.
        $category = $dbinterface->get_main_entity_by_name($categoryname, true);
        self::assertIsObject($category);
        self::assertEquals($createdcategory->id, $category->id);

        // Try again to not existing category.
        $category = $dbinterface->get_main_entity_by_name('falsecategory');
        self::assertFalse($category);

        self::resetAllData();
    }

    /**
     * Test get course category by id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_course_category_by_id
     */
    public function test_get_course_category_by_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $categoryname = 'Entity name';

        // Create the category with valid data.
        $createdcategory = $dbinterface->create_course_category($categoryname);

        // Get a valid category.
        $category = $dbinterface->get_course_category_by_id($createdcategory->id);
        self::assertIsObject($category);
        self::assertEquals($createdcategory->id, $category->id);

        // Try again to test the caching.
        $category = $dbinterface->get_course_category_by_id($createdcategory->id);
        self::assertIsObject($category);
        self::assertEquals($createdcategory->id, $category->id);

        // Try the refresh option.
        $category = $dbinterface->get_course_category_by_id($createdcategory->id, true);
        self::assertIsObject($category);
        self::assertEquals($createdcategory->id, $category->id);

        // Get a wrong category.
        try {
            $category = $dbinterface->get_course_category_by_id(123456789);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('dml_missing_record_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test get_next_available_training_name
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_next_available_training_name
     */
    public function test_get_next_available_training_name_test() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();

        $trainingname = 'Training name';
        $trainingshortname = 'Training shortname';

        $training = $this->init_create_training($trainingname, $trainingshortname, $entityid);
        $next = $dbinterface->get_next_available_training_name($trainingshortname);
        self::assertEquals($trainingshortname . " 1", $next);

        $training = $this->init_create_training($trainingshortname . " 1", $next, $entityid);
        $next = $dbinterface->get_next_available_training_name($trainingshortname);
        self::assertEquals($trainingshortname . " 2", $next);

        // Create task create session with next index into name.
        \local_mentor_core\session_api::create_session(
            $training->id,
            $trainingshortname . " 2"
        );

        // Get next training index.
        $nextindex = $dbinterface->get_next_available_training_name($trainingshortname);
        self::assertEquals('Training shortname 3', $nextindex);

        // Create task duplicate training with next index into name.
        \local_mentor_core\training_api::duplicate_training(
            $training->id,
            'Training shortname 3'
        );

        // Get next training index.
        $nextindex = $dbinterface->get_next_available_training_name($trainingshortname);
        self::assertEquals('Training shortname 4', $nextindex);

        // Create task create session with next index into name.
        $session = \local_mentor_core\session_api::create_session(
            $training->id,
            "sessionname",
            true
        );

        // Create task duplicate training with next index into name.
        \local_mentor_core\session_api::duplicate_session_as_new_training(
            $session->id,
            'Training shortname 4',
            'Training shortname 4',
            $session->get_entity()->id
        );

        // Get next training index.
        $nextindex = $dbinterface->get_next_available_training_name($trainingshortname);
        self::assertEquals('Training shortname 5', $nextindex);

        self::resetAllData();
    }

    /**
     * Test get_course_main_category_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_course_main_category_id
     */
    public function test_get_course_main_category_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();

        $trainingname = 'Training name';
        $trainingshortname = 'Training shortname';

        $training = $this->init_create_training($trainingname, $trainingshortname, $entityid);

        $course = $training->get_course();

        $dbentityid = $dbinterface->get_course_main_category_id($course->id);

        // Check if the entity has been created in the right main category.
        self::assertEquals($entityid, $dbentityid);

        self::resetAllData();
    }

    /**
     * Test get_trainings_by_entity_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_trainings_by_entity_id
     */
    public function test_get_trainings_by_entity_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();

        $trainings = $dbinterface->get_trainings_by_entity_id($entityid);

        // The entity must not have any training.
        self::assertCount(0, $trainings);

        $trainingname1 = 'Training name 1';
        $trainingname2 = 'Training name 2';

        $training1 = $this->init_create_training($trainingname1, $trainingname1, $entityid);
        $training2 = $this->init_create_training($trainingname2, $trainingname2, $entityid);

        $trainings = $dbinterface->get_trainings_by_entity_id($entityid);

        // The entity must have 2 trainings.
        self::assertCount(2, $trainings);

        self::resetAllData();
    }

    /**
     * Test get_training_by_course_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_training_by_course_id
     */
    public function test_get_training_by_course_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();

        // Create a training.
        $trainingname1 = 'Training name 1';
        $training1 = $this->init_create_training($trainingname1, $trainingname1, $entityid);
        $course = $training1->get_course();

        $dbtraining = $dbinterface->get_training_by_course_id($course->id);

        // Check if the db training is the good one.
        self::assertEquals($dbtraining->id, $training1->id);
        self::assertEquals($dbtraining->courseshortname, $training1->courseshortname);

        self::resetAllData();
    }

    /**
     * Test get_training_by_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_training_by_id
     */
    public function test_get_training_by_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();

        // Create a training.
        $trainingname1 = 'Training name 1';
        $training1 = $this->init_create_training($trainingname1, $trainingname1, $entityid);

        $dbtraining = $dbinterface->get_training_by_id($training1->id);

        // Check if the db training is the good one.
        self::assertEquals($dbtraining->id, $training1->id);
        self::assertEquals($dbtraining->courseshortname, $training1->courseshortname);

        // Get a wrong training.
        try {
            $dbtraining = $dbinterface->get_training_by_id(123456789);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('dml_missing_record_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test add_training
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::add_training
     */
    public function test_add_training() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();

        // Create a course.
        $coursedata = new stdClass();
        $coursedata->fullname = "New course";
        $coursedata->shortname = "New course";
        $coursedata->category = $entityid;
        $course = create_course($coursedata);

        $training = new stdClass();
        try {
            $dbinterface->add_training($training);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        $training->courseshortname = 'toto';
        try {
            $dbinterface->add_training($training);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e, 'missingcourse');
        }

        $training->courseshortname = $course->shortname;

        $training->id = $dbinterface->add_training($training);

        self::assertIsInt($training->id);

        self::resetAllData();
    }

    /**
     * Test add_training
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::add_session
     */
    public function test_add_session() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();

        // Create a training course.
        $coursedata = new stdClass();
        $coursedata->fullname = "Training course";
        $coursedata->shortname = "Training course";
        $coursedata->category = $entityid;
        $trainingcourse = create_course($coursedata);

        $training = new stdClass();
        $training->courseshortname = $trainingcourse->shortname;
        try {
            $training->id = $dbinterface->add_training($training);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Create a session course.
        $coursedata = new stdClass();
        $coursedata->fullname = "Session course";
        $coursedata->shortname = "Session course";
        $coursedata->category = $entityid;
        $sessioncourse = create_course($coursedata);

        $session = new stdClass();
        // Try without courseshortname and trainingid.
        try {
            $dbinterface->add_session($session);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Send a wrong courseshortname.
        $session->courseshortname = 'toto';
        try {
            $dbinterface->add_session($session);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Send a right shortname and no trainingid.
        $session->courseshortname = $coursedata->shortname;
        try {
            $dbinterface->add_session($session);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Send a wrong training id.
        $session->trainingid = 123456789;
        try {
            $dbinterface->add_session($session);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Send all required data.
        $session->trainingid = $training->id;

        $session->id = $dbinterface->add_session($session);

        self::assertIsInt($session->id);

        self::resetAllData();
    }

    /**
     * Test get_session_by_course_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_session_by_course_id
     */
    public function test_get_session_by_course_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create a session.
        $session = $this->init_create_session();

        // Get the session course.
        $course = $session->get_course();

        // Fetch the session in database by course id.
        $dbsession = $dbinterface->get_session_by_course_id($course->id);

        // Check if the db training is the good one.
        self::assertEquals($dbsession->id, $session->id);
        self::assertEquals($dbsession->courseshortname, $session->courseshortname);

        // Test with a wrong id.
        $dbsession = $dbinterface->get_session_by_course_id(123456789);
        self::assertFalse($dbsession);

        self::resetAllData();
    }

    /**
     * Test get_course_format_options_by_course_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_course_format_options_by_course_id
     */
    public function test_get_course_format_options_by_course_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();

        try {
            $entity = \local_mentor_core\entity_api::get_entity($entityid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Get edadmin courses of the entity.
        $edadmincourses = $entity->get_edadmin_courses();

        self::assertNotEmpty($edadmincourses);

        // Check if each eadmin course has data in course_format_options table.
        foreach ($edadmincourses as $edadmincourse) {
            $options = $dbinterface->get_course_format_options_by_course_id($edadmincourse['id']);
            self::assertNotEmpty($options);

            // Fetch data from class cache.
            $options = $dbinterface->get_course_format_options_by_course_id($edadmincourse['id']);
            self::assertNotEmpty($options);
        }

        self::resetAllData();
    }

    /**
     * Test get_user_cohorts
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_user_cohorts
     */
    public function test_get_user_cohorts() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        // Create an user.
        $newuser = self::getDataGenerator()->create_user();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create an entity.
        $entityid = $this->init_create_entity();

        try {
            $entity = \local_mentor_core\entity_api::get_entity($entityid);

            // Add user to entity cohort.
            $entity->add_member($newuser);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        $cohorts = $dbinterface->get_user_cohorts($newuser->id);

        self::assertCount(1, $cohorts);

        self::resetAllData();
    }

    /**
     * Test get_user_cohorts
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_user_entities
     */
    public function test_get_user_entities() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create an user.
        $newuser = self::getDataGenerator()->create_user();

        // Create an Entity.
        $entityid = $this->init_create_entity();
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Add user to entity cohort.
        $entity->add_member($newuser);

        $userentities = $dbinterface->get_user_entities($newuser->id);

        self::assertCount(1, $userentities);
        self::assertTrue(isset($userentities[$entityid]));

        self::resetAllData();
    }

    /**
     * Test get_course_category_by_parent_and_name
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_course_category_by_parent_and_name
     */
    public function test_get_course_category_by_parent_and_name() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create an Entity.
        $entityid = $this->init_create_entity();

        // Creates a training so that it is a generation of the category "Formations" as a child of the category of the entity.
        $trainingname = 'Training';
        $this->init_create_training($trainingname, $trainingname, $entityid);

        $childcategoryentity = $dbinterface->get_course_category_by_parent_and_name($entityid, 'Formations');

        self::assertEquals($entityid, $childcategoryentity->parent);

        self::resetAllData();
    }

    /**
     * Test get_course_by_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_course_by_id
     */
    public function test_get_course_by_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create a course.
        $coursedata = [
            'fullname' => 'CourseTest',
            'shortname' => 'CT',
            'categoryid' => 1,
            'summary' => 'Cours de test',
        ];
        $course = current(\core_course_external::create_courses([$coursedata]));

        $requestresult = $dbinterface->get_course_by_id($course['id']);

        self::assertEquals($requestresult->id, $course['id']);
        self::assertEquals($requestresult->shortname, $course['shortname']);

        self::resetAllData();
    }

    /**
     * Test get_course_by_shortname
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_course_by_shortname
     */
    public function test_get_course_by_shortname() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Get false course.
        self::assertFalse($dbinterface->get_course_by_shortname('falsecourse'));

        // Create a course.
        $coursedata = [
            'fullname' => 'CourseTest',
            'shortname' => 'CT',
            'categoryid' => 1,
            'summary' => 'Cours de test',
        ];
        $course = current(\core_course_external::create_courses([$coursedata]));

        // Get course.
        $requestresult = $dbinterface->get_course_by_shortname($course['shortname']);

        // Check if is good informations.
        self::assertEquals($requestresult->id, $course['id']);
        self::assertEquals($requestresult->shortname, $course['shortname']);

        self::resetAllData();
    }

    /**
     * Test update_course_name
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::update_course_name
     */
    public function test_update_course_name() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Empty course shortname.
        self::assertFalse($dbinterface->course_shortname_exists(''));

        // Create a course.
        $coursedata = [
            'fullname' => 'CourseTest',
            'shortname' => 'CT',
            'categoryid' => 1,
        ];
        $course = current(\core_course_external::create_courses([$coursedata]));

        // Check if course exist.
        self::assertTrue($dbinterface->course_shortname_exists($course['shortname']));

        // Update cours shortname.
        self::assertTrue($dbinterface->update_course_name($course['id'], 'newcourseshortname'));

        // Check if course exist with new shortname.
        self::assertTrue($dbinterface->course_shortname_exists('newcourseshortname'));

        // Create course cache to database interface.
        $dbinterface->get_course_by_id($course['id']);

        // Update course shortname with cache remove.
        self::assertTrue($dbinterface->update_course_name($course['id'], 'newcourseshortnamebis'));

        // Check if course exist with new shortname.
        self::assertTrue($dbinterface->course_shortname_exists('newcourseshortnamebis'));

        // Update course shortname with cache remove.
        self::assertTrue($dbinterface->update_course_name($course['id'], 'newcourseshortname3', 'new course fullname'));

        // Check if course exist with new shortname.
        self::assertTrue($dbinterface->course_shortname_exists('newcourseshortname3'));

        // Refresh course data.
        $course = $dbinterface->get_course_by_id($course['id'], true);

        self::assertEquals('new course fullname', $course->fullname);

        self::resetAllData();
    }

    /**
     * Test course_shortname_exists ok
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::course_shortname_exists
     */
    public function test_course_shortname_exists_ok() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Empty course shortname.
        self::assertFalse($dbinterface->course_shortname_exists(''));

        // Create a course.
        $coursedata = [
            'fullname' => 'CourseTest',
            'shortname' => 'CT',
            'categoryid' => 1,
            'summary' => 'Cours de test',
        ];
        $course = current(\core_course_external::create_courses([$coursedata]));

        self::assertTrue($dbinterface->course_shortname_exists($course['shortname']));

        $entity1id = $this->init_create_entity('Entity 1');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);
        $session->delete();

        // Session name exists in recyclebin.
        self::assertTrue($dbinterface->course_shortname_exists('Session course'));

        // Clean recyclebin.
        \local_mentor_core\entity_api::cleanup_session_recyblebin($entity1id);

        // Session name does not exist anymore..
        self::assertFalse($dbinterface->course_shortname_exists('Session course'));

        // Create a session as ad hoc task.
        $session = local_mentor_core\session_api::create_session($training->id, "Session adhoc");

        // Session name exists in ad hoc task.
        self::assertTrue($dbinterface->course_shortname_exists('Session adhoc'));

        // Duplicate a training as ad hoc task.
        \local_mentor_core\training_api::duplicate_training($training->id, 'Duplicated training');

        // Shortname exists in ad hoc tasks.
        self::assertTrue($dbinterface->course_shortname_exists('Duplicated training'));

        // Create a new session.
        $sessiontoduplicate = local_mentor_core\session_api::create_session($training->id, "Session to duplicate", true);

        // Duplicate the session as a new training.
        \local_mentor_core\session_api::duplicate_session_as_new_training($sessiontoduplicate->id, 'trainingfullnameduplicated',
            'trainingshortnameduplicated', $entity1id);

        // Shortname exists in ad hoc tasks.
        self::assertTrue($dbinterface->course_shortname_exists('trainingshortnameduplicated'));

        // Create a training course.
        $training2 = $this->init_create_training('Training course 2', 'Training course 2', $entity1id);

        // Import to entitya training library.
        $traininglibrary = \local_mentor_core\library_api::publish_to_library($training2->id, true);
        \local_mentor_core\library_api::import_to_entity($traininglibrary->id, 'trainingshortnameimporttolibrary', $entity1id);

        // Shortname exists in ad hoc tasks.
        self::assertTrue($dbinterface->course_shortname_exists('trainingshortnameimporttolibrary'));

        self::resetAllData();
    }

    /**
     * Test course_shortname_exists not ok
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::course_shortname_exists
     */
    public function test_course_shortname_exists_nok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::assertFalse($dbinterface->course_shortname_exists("falsecourseshortname"));

        self::resetAllData();
    }

    /**
     * Test set_course_format_options
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::set_course_format_options
     */
    public function test_set_course_format_options() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create a course format option.
        $courseid = 10;
        $format = 'Test';
        $courseformatoption = [];
        $courseformatoption[0] = new \stdClass();
        $courseformatoption[0]->sectionid = 0;
        $courseformatoption[0]->name = 'Test';
        $courseformatoption[0]->value = 1;
        $dbinterface->set_course_format_options($courseid, $format, $courseformatoption);

        // Get a course format option.
        $resultrequest = $DB->get_records('course_format_options', ['courseid' => $courseid]);

        // Check values.
        self::assertCount(1, $resultrequest);
        $resultrequest = array_values($resultrequest);
        self::assertEquals($resultrequest[0]->courseid, $courseid);
        self::assertEquals($resultrequest[0]->format, $format);
        self::assertEquals($resultrequest[0]->sectionid, $courseformatoption[0]->sectionid);
        self::assertEquals($resultrequest[0]->name, $courseformatoption[0]->name);
        self::assertEquals($resultrequest[0]->value, $courseformatoption[0]->value);

        // Create a course format option.
        $courseformatoption = [];
        $courseformatoption[0] = new \stdClass();
        $courseformatoption[0]->sectionid = 1;
        $courseformatoption[0]->name = 'Test2';
        $courseformatoption[0]->value = 2;
        $courseformatoption[1] = new \stdClass();
        $courseformatoption[1]->sectionid = 3;
        $courseformatoption[1]->name = 'Test3';
        $courseformatoption[1]->value = 4;
        $dbinterface->set_course_format_options($courseid, $format, $courseformatoption);

        // Get a course format option.
        $resultrequest = $DB->get_records('course_format_options', ['courseid' => $courseid]);

        // Check values.
        self::assertCount(2, $resultrequest);
        $resultrequest = array_values($resultrequest);
        self::assertEquals($resultrequest[0]->courseid, $courseid);
        self::assertEquals($resultrequest[0]->format, $format);
        self::assertEquals($resultrequest[0]->sectionid, $courseformatoption[0]->sectionid);
        self::assertEquals($resultrequest[0]->name, $courseformatoption[0]->name);
        self::assertEquals($resultrequest[0]->value, $courseformatoption[0]->value);
        self::assertEquals($resultrequest[1]->courseid, $courseid);
        self::assertEquals($resultrequest[1]->format, $format);
        self::assertEquals($resultrequest[1]->sectionid, $courseformatoption[1]->sectionid);
        self::assertEquals($resultrequest[1]->name, $courseformatoption[1]->name);
        self::assertEquals($resultrequest[1]->value, $courseformatoption[1]->value);

        self::resetAllData();
    }

    /**
     * Test add_course_format_option
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::add_course_format_option
     */
    public function test_add_course_format_option() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create a course format option.
        $courseformatoption = new stdClass();
        $courseformatoption->courseid = 1;
        $courseformatoption->format = 'Test';
        $courseformatoption->sectionid = 0;
        $courseformatoption->name = 'Test';
        $courseformatoption->value = 1;
        $courseformatoptionid = $dbinterface->add_course_format_option($courseformatoption);

        // Get a course format option.
        $resultrequest = $DB->get_record('course_format_options', ['id' => $courseformatoptionid]);

        // Check values.
        self::assertEquals($resultrequest->id, $courseformatoptionid);
        self::assertEquals($resultrequest->courseid, $courseformatoption->courseid);
        self::assertEquals($resultrequest->format, $courseformatoption->format);
        self::assertEquals($resultrequest->sectionid, $courseformatoption->sectionid);
        self::assertEquals($resultrequest->name, $courseformatoption->name);
        self::assertEquals($resultrequest->value, $courseformatoption->value);

        // Set values in cache.
        $options = $dbinterface->get_course_format_options_by_course_id(1);

        // Create a course format option.
        $courseformatoption = new stdClass();
        $courseformatoption->courseid = 1;
        $courseformatoption->format = 'Test';
        $courseformatoption->sectionid = 0;
        $courseformatoption->name = 'Test2';
        $courseformatoption->value = 1;
        $courseformatoptionid = $dbinterface->add_course_format_option($courseformatoption);

        // Get a course format option.
        $resultrequest = $DB->get_record('course_format_options', ['id' => $courseformatoptionid]);

        // Check values.
        self::assertEquals($resultrequest->id, $courseformatoptionid);
        self::assertEquals($resultrequest->courseid, $courseformatoption->courseid);
        self::assertEquals($resultrequest->format, $courseformatoption->format);
        self::assertEquals($resultrequest->sectionid, $courseformatoption->sectionid);
        self::assertEquals($resultrequest->name, $courseformatoption->name);
        self::assertEquals($resultrequest->value, $courseformatoption->value);

        self::resetAllData();
    }

    /**
     * Test get_cohort_by_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_cohort_by_id
     */
    public function test_get_cohort_by_id() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create cohort.
        $cohort = new \stdClass();
        $cohort->name = 'Testcohort';
        $cohort->contextid = 10;// False context.
        $cohort->id = cohort_add_cohort($cohort);

        // Get cohort with username.
        $resultrequest = $dbinterface->get_cohort_by_id($cohort->id);

        // Check if are equals values.
        self::assertEquals($resultrequest->id, $cohort->id);
        self::assertEquals($resultrequest->contextid, $cohort->contextid);

        self::resetAllData();
    }

    /**
     * Test get_cohorts_by_name
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_cohorts_by_name
     */
    public function test_get_cohorts_by_name() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create cohort.
        $cohort = new \stdClass();
        $cohort->name = 'Testcohort';
        $cohort->contextid = 10;// False context.
        $cohort->id = cohort_add_cohort($cohort);

        // Get cohort with username.
        $resultrequest = current($dbinterface->get_cohorts_by_name($cohort->name));

        // Check if are equals values.
        self::assertEquals($resultrequest->id, $cohort->id);
        self::assertEquals($resultrequest->name, $cohort->name);
        self::assertEquals($resultrequest->contextid, $cohort->contextid);

        self::resetAllData();
    }

    /**
     * Test get_cohort_members_by_cohort_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_cohort_members_by_cohort_id
     */
    public function test_get_cohort_members_by_cohort_id() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create cohort.
        $cohort = new \stdClass();
        $cohort->name = 'Testcohort';
        $cohort->contextid = 10;// False context.
        $cohort->id = cohort_add_cohort($cohort);

        $this->init_create_entity();

        // Create user.
        $newuserid = $this->init_create_user();

        // Add user to cohort.
        cohort_add_member($cohort->id, $newuserid);

        // Get user to cohort.
        $resultrequest = $dbinterface->get_cohort_members_by_cohort_id($cohort->id, 'all');

        // Check if are equals values.
        self::assertCount(1, $resultrequest);
        self::assertEquals($newuserid, current($resultrequest)->id);

        // Get user active to cohort.
        $resultrequest = $dbinterface->get_cohort_members_by_cohort_id($cohort->id, 'false');

        // Check if are equals values.
        self::assertCount(1, $resultrequest);
        self::assertEquals($newuserid, current($resultrequest)->id);

        // Get user suspended to cohort.
        $resultrequest = $dbinterface->get_cohort_members_by_cohort_id($cohort->id, 'true');

        // Check if are equals values.
        self::assertCount(0, $resultrequest);

        $user = new \stdClass();
        $user->id = $newuserid;
        $user->suspended = 1;
        $DB->update_record('user', $user);

        // Get user active to cohort.
        $resultrequest = $dbinterface->get_cohort_members_by_cohort_id($cohort->id, 'false');

        // Check if are equals values.
        self::assertCount(0, $resultrequest);

        // Get user suspended to cohort.
        $resultrequest = $dbinterface->get_cohort_members_by_cohort_id($cohort->id, 'true');
        self::assertCount(1, $resultrequest);
        self::assertEquals($newuserid, current($resultrequest)->id);

        self::resetAllData();
    }

    /**
     * Test check_if_user_is_cohort_member
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::check_if_user_is_cohort_member
     */
    public function test_check_if_user_is_cohort_member() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create cohort.
        $cohort = new \stdClass();
        $cohort->name = 'Testcohort';
        $cohort->contextid = 10;// False context.
        $cohort->id = cohort_add_cohort($cohort);

        // Create user.
        $newuserid = $this->init_create_user();

        // Add user to cohort.
        cohort_add_member($cohort->id, $newuserid);

        // Check if user is cohort member.
        self::assertTrue($dbinterface->check_if_user_is_cohort_member($newuserid, $cohort->id));

        self::resetAllData();
    }

    /**
     * Test add_cohort_member
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::add_cohort_member
     */
    public function test_add_cohort_member() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create cohort.
        $cohort = new \stdClass();
        $cohort->name = 'Testcohort';
        $cohort->contextid = 10;// False context.
        $cohort->id = cohort_add_cohort($cohort);

        // Create user.
        $newuserid = $this->init_create_user();

        // Add user to cohort.
        self::assertTrue($dbinterface->add_cohort_member($cohort->id, $newuserid));

        // Check if user is cohort member.
        self::assertTrue($dbinterface->check_if_user_is_cohort_member($newuserid, $cohort->id));

        self::resetAllData();
    }

    /**
     * Test remove_cohort_member
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::remove_cohort_member
     */
    public function test_remove_cohort_member() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create cohort.
        $cohort = new \stdClass();
        $cohort->name = 'Testcohort';
        $cohort->contextid = 10;// False context.
        $cohort->id = cohort_add_cohort($cohort);

        // Create user.
        $newuserid = $this->init_create_user();

        // Add user to cohort.
        self::assertTrue($dbinterface->add_cohort_member($cohort->id, $newuserid));

        // Check if user is cohort member.
        self::assertTrue($dbinterface->check_if_user_is_cohort_member($newuserid, $cohort->id));

        // Remove user to cohort.
        self::assertTrue($dbinterface->remove_cohort_member($cohort->id, $newuserid));

        // Check if user is not cohort member.
        self::assertFalse($dbinterface->check_if_user_is_cohort_member($newuserid, $cohort->id));

        self::resetAllData();
    }

    /**
     * Test update_training
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::update_training
     */
    public function test_update_training() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();

        $oldcourseshortname = 'toto';
        $newcourseshortname = 'toto_update';

        // Create a course.
        $coursedata = new stdClass();
        $coursedata->fullname = "New course";
        $coursedata->shortname = $oldcourseshortname;
        $coursedata->category = $entityid;
        $course = create_course($coursedata);

        // Create training.
        $training = new stdClass();
        $training->courseshortname = $course->shortname;
        $training->id = $dbinterface->add_training($training);

        // Change training course shortname.
        $training->courseshortname = $newcourseshortname;

        // Update training.
        $dbinterface->update_training($training);

        // Get training.
        $resultrequest = $DB->get_record('training', ['id' => $training->id]);

        // Check if are not equals values.
        self::assertNotEquals($oldcourseshortname, $resultrequest->courseshortname);

        // Check if are equals values.
        self::assertEquals($newcourseshortname, $resultrequest->courseshortname);

        self::resetAllData();
    }

    /**
     * Test update_session
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::update_session
     */
    public function test_update_session() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();

        $training = $this->init_create_training('Training course', 'Training course', $entityid);

        $oldsessioncourseshortname = "Session course";
        $newsessioncourseshortname = "Session course update";

        // Create a session course.
        $coursedata = new stdClass();
        $coursedata->fullname = "Session course";
        $coursedata->shortname = $oldsessioncourseshortname;
        $coursedata->category = $entityid;
        create_course($coursedata);
        $session = new stdClass();
        $session->courseshortname = $coursedata->shortname;
        $session->trainingid = $training->id;
        $session->id = $dbinterface->add_session($session);

        // Change session course shortname.
        $session->courseshortname = $newsessioncourseshortname;

        // Update session.
        $dbinterface->update_session($session);

        // Get session.
        $resultrequest = $DB->get_record('session', ['id' => $session->id]);

        // Check if are not equals values.
        self::assertNotEquals($oldsessioncourseshortname, $resultrequest->courseshortname);

        // Check if are equals values.
        self::assertEquals($newsessioncourseshortname, $resultrequest->courseshortname);

        self::resetAllData();
    }

    /**
     * Test get_opento_list
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_opento_list
     */
    public function test_get_opento_list() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity1');
        $entity2id = $this->init_create_entity('Entity2');

        // Create a training.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);
        $data = new stdClass();

        // Update a session course with sharing.
        $data->id = $session->id;
        $data->opento = 'other_entities';
        $data->opentolist = [$entity2id];
        $session->update($data);

        // Check if sharing is ok.
        $resultrequest = $dbinterface->get_opento_list($session->id);
        self::assertEquals($entity2id, current($resultrequest)->coursecategoryid);

        self::resetAllData();
    }

    /**
     * Test update_session_sharing
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws Exception
     * @covers \local_mentor_core\database_interface::update_session_sharing
     */
    public function test_update_session_sharing() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity1');
        $entity2id = $this->init_create_entity('Entity2');

        // Create a training.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);

        // Update a session sharing.
        $dbinterface->update_session_sharing($session->id, [$entity2id]);

        // Check if sharing is ok.
        $resultrequest = $dbinterface->get_opento_list($session->id);
        self::assertEquals($entity2id, current($resultrequest)->coursecategoryid);

        // Create DB Mock.
        $DB = $this->createMock(get_class($DB));

        // Return exception when delete_records function call one time.
        // With 'session_sharing' and array('sessionid' => $session->id) arguments.
        $DB->expects($this->once())
            ->method('delete_records')
            ->with('session_sharing', ['sessionid' => $session->id])
            ->will($this->throwException(new \Exception()));

        // Replace dbinterface data with database interface Mock in training Mock.
        $reflection = new ReflectionClass($dbinterface);
        $reflectionproperty = $reflection->getProperty('db');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($dbinterface, $DB);

        try {
            // Update a session sharing.
            $dbinterface->update_session_sharing($session->id, [$entity2id]);
        } catch (\Exception $e) {
            // Error to use DB delete function.
            self::assertInstanceOf('exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test remove_session_sharing
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::remove_session_sharing
     */
    public function test_remove_session_sharing() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity1');
        $entity2id = $this->init_create_entity('Entity2');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);
        $data = new stdClass();

        // Update a session course with sharing.
        $data->id = $session->id;
        $data->opento = 'other_entities';
        $data->opentolist = [$entity2id];
        $session->update($data);

        // Check if sharing is ok.
        $resultrequest = $dbinterface->get_opento_list($session->id);
        self::assertCount(1, $resultrequest);

        // Check if sharing is remove.
        $dbinterface->remove_session_sharing($session->id);
        $resultrequest = $dbinterface->get_opento_list($session->id);
        self::assertCount(0, $resultrequest);

        self::resetAllData();
    }

    /**
     * Test get_entity_category_by_name
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_entity_category_by_name
     */
    public function test_get_entity_category_by_name() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create an Entity.
        $entityid = $this->init_create_entity();

        // Creates a training so that it is a generation of the category "Formations" as a child of the category of the entity.
        $trainingname = 'Training';
        $this->init_create_training($trainingname, $trainingname, $entityid);

        $childcategoryentity = $dbinterface->get_entity_category_by_name($entityid, 'Formations');

        self::assertEquals($entityid, $childcategoryentity->parent);

        self::resetAllData();
    }

    /**
     * Test get_category_course_by_idnumber
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_category_course_by_idnumber
     */
    public function test_get_category_course_by_idnumber() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create a course.
        $coursedata = new stdClass();
        $coursedata->fullname = "New course";
        $coursedata->shortname = "New course";
        $coursedata->category = 1;
        $coursedata->idnumber = 1;
        $course = create_course($coursedata);

        $resutrequest = $dbinterface->get_category_course_by_idnumber($coursedata->category, $coursedata->idnumber);

        self::assertEquals($resutrequest->id, $course->id);
        self::assertEquals($resutrequest->fullname, $course->fullname);
        self::assertEquals($resutrequest->shortname, $course->shortname);

        self::resetAllData();
    }

    /**
     * Test get_category_course_by_idnumber ok
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::update_main_entities_name
     */
    public function test_update_main_entities_name_ok() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $oldentityname = 'New Entity 1';
        $newentityname = 'New Entity 2';

        $field = $DB->get_record('user_info_field', ['shortname' => 'mainentity']);

        // Create user.
        $user1 = new stdClass();
        $user1->lastname = 'lastname1';
        $user1->firstname = 'User1';
        $user1->email = 'user1@test.com';
        $user1->username = 'testuser1';
        $user1->password = 'user1';
        $user1->mnethostid = 1;
        $user1->confirmed = 1;
        $user1->auth = 'manual';

        $user1id = local_mentor_core\profile_api::create_user($user1);
        set_user_preference('auth_forcepasswordchange', 0, $user1);

        $user1data = new stdClass();
        $user1data->fieldid = $field->id;
        $user1data->data = $oldentityname;
        $user1data->userid = $user1id;

        $user1infodata = $DB->insert_record('user_info_data', $user1data);

        // Create user.
        $user2 = new stdClass();
        $user2->lastname = 'lastname2';
        $user2->firstname = 'User2';
        $user2->email = 'user2@test.com';
        $user2->username = 'testuser2';
        $user2->password = 'user2';
        $user2->mnethostid = 1;
        $user2->confirmed = 1;
        $user2->auth = 'manual';

        $user2id = local_mentor_core\profile_api::create_user($user2);
        set_user_preference('auth_forcepasswordchange', 0, $user2);

        $user2data = new stdClass();
        $user2data->fieldid = $field->id;
        $user2data->data = $oldentityname;
        $user2data->userid = $user2id;

        $user2infodata = $DB->insert_record('user_info_data', $user2data);

        $resultrequest = $DB->get_records_sql('
            SELECT *
            FROM {user_info_data}
            WHERE ' . $DB->sql_compare_text('data') . ' = ' . $DB->sql_compare_text(':data') .
                                              'AND fieldid = :fieldid', ['data' => $oldentityname, 'fieldid' => $field->id]);

        self::assertEquals($resultrequest[$user1infodata]->userid, $user1id);
        self::assertEquals($resultrequest[$user1infodata]->data, $oldentityname);
        self::assertEquals($resultrequest[$user2infodata]->userid, $user2id);
        self::assertEquals($resultrequest[$user2infodata]->data, $oldentityname);

        $dbinterface->update_main_entities_name($oldentityname, $newentityname);

        $resultrequest = $DB->get_records_sql('
            SELECT *
            FROM {user_info_data}
            WHERE ' . $DB->sql_compare_text('data') . ' = ' . $DB->sql_compare_text(':data') .
                                              'AND fieldid = :fieldid', ['data' => $newentityname, 'fieldid' => $field->id]);

        self::assertEquals($resultrequest[$user1infodata]->userid, $user1id);
        self::assertEquals($resultrequest[$user1infodata]->data, $newentityname);
        self::assertEquals($resultrequest[$user2infodata]->userid, $user2id);
        self::assertEquals($resultrequest[$user2infodata]->data, $newentityname);

        self::resetAllData();
    }

    /**
     * Test get_category_course_by_idnumber not ok
     * User info field does not exist
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::update_main_entities_name
     */
    public function test_update_main_entities_name_nok_not_user_info_field() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $DB->delete_records('user_info_field', ['shortname' => 'mainentity']);

        self::assertFalse($dbinterface->update_main_entities_name('oldname', 'newname'));

        self::resetAllData();
    }

    /**
     * Test get_category_course_by_idnumber not ok
     * Database request exception
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::update_main_entities_name
     */
    public function test_update_main_entities_name_nok_db_request_exception() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        // Clear notification.
        \core\notification::fetch();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create DB Mock.
        $DB = $this->getMockBuilder(get_class($DB))
            ->setMethods(['get_record', 'execute'])
            ->disableOriginalConstructor()
            ->getMock();

        $mainentityfield = new \stdClass();
        $mainentityfield->id = 0;

        $DB->expects($this->once())
            ->method('get_record')
            ->will($this->returnValue($mainentityfield));

        $DB->expects($this->once())
            ->method('execute')
            ->will($this->throwException(new \dml_exception('DB Error!!!')));

        // Replace dbinterface data with DB Mock.
        $reflection = new ReflectionClass($dbinterface);
        $reflectionproperty = $reflection->getProperty('db');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($dbinterface, $DB);

        $dbinterface->update_main_entities_name('oldname', 'newname');

        $notification = \core\notification::fetch();

        self::assertCount(1, $notification);
        self::assertEquals($notification[0]->get_message_type(), 'error');
        self::assertEquals(
            $notification[0]->get_message(),
            "ERROR : Update all users mainentity fields!!!\nerror/DB Error!!!\n\$a contents: "
        );

        self::resetAllData();
    }

    /**
     * Test course_exists
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::course_exists
     */
    public function test_course_exists() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $courseshrortname = "New course fullname";

        // Course not exist.
        self::assertFalse($dbinterface->course_exists($courseshrortname));

        // Create a course.
        $coursedata = new stdClass();
        $coursedata->fullname = "New course fullname";
        $coursedata->shortname = $courseshrortname;
        $coursedata->category = 1;
        create_course($coursedata);

        // Course exist.
        self::assertTrue($dbinterface->course_exists($courseshrortname));

        self::resetAllData();
    }

    /**
     * Test course_category_exists
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::course_category_exists
     */
    public function test_course_category_exists() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Course exist.
        self::assertTrue($dbinterface->course_category_exists($entity->id));

        self::resetAllData();
    }

    /**
     * Test update_session_status
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::update_session_status
     */
    public function test_update_session_status() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create session.
        $session = $this->init_create_session();
        $oldsessionstatus = $session->status;

        // Update session.
        $newtstatus = 'statustest';
        $dbinterface->update_session_status($session->id, $newtstatus);

        // Get session update.
        $sessionupdate = \local_mentor_core\session_api::get_session($session->id);

        // Check session status.
        self::assertNotEquals($oldsessionstatus, $sessionupdate->status);
        self::assertEquals($newtstatus, $sessionupdate->status);

        self::resetAllData();
    }

    /**
     * Test session_exists
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::session_exists
     */
    public function test_session_exists() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create session.
        $session = $this->init_create_session();

        // Check if session exist.
        self::assertTrue($dbinterface->session_exists($session->shortname));

        self::resetAllData();
    }

    /**
     * Test get_session_by_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_session_by_id
     */
    public function test_get_session_by_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create session.
        $session = $this->init_create_session();

        // Get session.
        $resultrequest = $dbinterface->get_session_by_id($session->id);

        // Check if are equals values.
        self::assertEquals($session->id, $resultrequest->id);
        self::assertEquals($session->courseshortname, $resultrequest->courseshortname);
        self::assertEquals($session->status, $resultrequest->status);

        self::resetAllData();
    }

    /**
     * Test get_sessions_by_entity_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_sessions_by_entity_id
     */
    public function test_get_sessions_by_entity_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create session.
        $session = $this->init_create_session();

        $data = new stdClass();
        $data->entityid = $session->get_entity()->id;
        $data->status = $session->status;
        $date = new DateTime();
        $data->dateto = $date->getTimestamp() - 1000;
        $data->datefrom = $date->getTimestamp() + 1000;
        $data->start = 0;
        $data->length = 10;

        // Get sessions by entity.
        $resultrequest = $dbinterface->get_sessions_by_entity_id($data);

        // Check if are equals values.
        self::assertCount(1, $resultrequest);
        self::assertEquals($session->id, current($resultrequest)->id);
        self::assertEquals($session->courseshortname, current($resultrequest)->courseshortname);
        self::assertEquals($session->status, current($resultrequest)->status);

        self::resetAllData();
    }

    /**
     * Test count_sessions_by_entity_id
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::count_sessions_by_entity_id
     */
    public function test_count_sessions_by_entity_id() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create session.
        $session = $this->init_create_session();

        $data = new stdClass();
        $data->entityid = $session->get_entity()->id;
        $data->status = $session->status;
        $date = new DateTime();
        $data->dateto = $date->getTimestamp() - 1000;
        $data->datefrom = $date->getTimestamp() + 1000;

        // Count sessions by entity.
        self::assertEquals(1, $dbinterface->count_sessions_by_entity_id($data));

        self::resetAllData();
    }

    /**
     * Test count_session_record
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::count_session_record
     */
    public function test_count_session_record() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create session.
        $session = $this->init_create_session();

        // Count sessions by entity.
        self::assertEquals(1, $dbinterface->count_session_record($session->get_entity()->id));

        self::resetAllData();
    }

    /**
     * Test get_next_training_session_index
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_next_training_session_index
     */
    public function test_get_next_training_session_index() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create session.
        $session = $this->init_create_session();

        // Get next session index.
        $nextindex = $dbinterface->get_next_training_session_index($session->courseshortname);
        self::assertEquals(1, $nextindex);

        // Create session with next index into name.
        \local_mentor_core\session_api::create_session(
            $session->get_training()->id,
            $session->courseshortname . ' ' . $nextindex,
            true
        );

        // Get next session index.
        $nextindex = $dbinterface->get_next_training_session_index($session->courseshortname);
        self::assertEquals(2, $nextindex);

        // Create task create session with next index into name.
        \local_mentor_core\session_api::create_session(
            $session->get_training()->id,
            $session->courseshortname . ' ' . $nextindex
        );

        // Get next session index.
        $nextindex = $dbinterface->get_next_training_session_index($session->courseshortname);
        self::assertEquals(3, $nextindex);

        // Create task duplicate training with next index into name.
        \local_mentor_core\training_api::duplicate_training(
            $session->get_training()->id,
            $session->courseshortname . ' ' . $nextindex
        );

        // Get next session index.
        $nextindex = $dbinterface->get_next_training_session_index($session->courseshortname);
        self::assertEquals(4, $nextindex);

        // Create task duplicate training with next index into name.
        \local_mentor_core\session_api::duplicate_session_as_new_training(
            $session->id,
            $session->courseshortname . ' ' . $nextindex,
            $session->courseshortname . ' ' . $nextindex,
            $session->get_entity()->id
        );

        // Get next session index.
        $nextindex = $dbinterface->get_next_training_session_index($session->courseshortname);
        self::assertEquals(5, $nextindex);

        // Create course training in recyclebin with next index into name.
        $training = \local_mentor_core\training_api::duplicate_training(
            $session->get_training()->id,
            $session->courseshortname . ' ' . $nextindex,
            null,
            true
        );
        $training->delete();

        // Get next session index.
        $nextindex = $dbinterface->get_next_training_session_index($session->courseshortname);
        self::assertEquals(6, $nextindex);

        self::resetAllData();
    }

    /**
     * Test get_user_available_sessions
     *
     * @covers \local_mentor_core\database_interface::get_user_available_sessions
     * @covers \local_mentor_core\database_interface::get_sessions_shared_to_all_entities
     */
    public function test_get_user_available_sessions() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::setAdminUser();

        $userid = self::init_create_user();

        // Check if not session is available.
        self::assertCount(0, $dbinterface->get_user_available_sessions($userid));

        // Create session.
        $session = $this->init_create_session();

        // Update the status of the session so that it becomes available.
        $session->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
        \local_mentor_core\session_api::update_session($session);

        $user = new stdClass();
        $user->id = $userid;
        $session->get_entity()->add_member($user);

        // Check if user has one available session.
        self::assertCount(1, $dbinterface->get_user_available_sessions($userid));

        self::resetAllData();
    }

    /**
     * Test get_user_available_sessions
     *
     * @covers \local_mentor_core\database_interface::get_availables_sessions_number
     */
    public function test_get_availables_sessions_number() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::setAdminUser();

        // Create session.
        $session = $this->init_create_session();

        self::assertEquals(1, $dbinterface->get_availables_sessions_number($session->get_training()->id)->sessionumber);

        // Update the status of the session so that it becomes available.
        $session->status = \local_mentor_core\session::STATUS_CANCELLED;
        \local_mentor_core\session_api::update_session($session);

        self::assertEquals(0, $dbinterface->get_availables_sessions_number($session->get_training()->id)->sessionumber);

        self::resetAllData();
    }

    /**
     * Test get_session_number
     *
     * @covers \local_mentor_core\database_interface::get_session_number
     */
    public function test_get_session_number() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::setAdminUser();

        // Create session.
        $session = $this->init_create_session();

        // Check session number.
        self::assertEquals(1, $dbinterface->get_session_number($session->trainingid));

        self::resetAllData();
    }

    /**
     * Test get_user_courses
     *
     * @covers \local_mentor_core\database_interface::get_user_courses
     */
    public function test_get_user_courses() {
        global $DB;

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::setAdminUser();

        // Create session.
        $session = $this->init_create_session();

        // Enable self enrol to session.
        $session->create_self_enrolment_instance();
        $session->enable_self_enrolment_instance();

        // Create user.
        $userid = self::init_create_user();

        // Set current user.
        self::setUser($userid);

        // Enrol user to course session.
        $resultenrol = $session->enrol_current_user();
        self::assertTrue($resultenrol['status']);

        // Get user course.
        $resultrequest = $dbinterface->get_user_courses($userid);

        // Check if are equals values.
        // (Enrol two course because enrol to contact page).
        self::assertCount(2, $resultrequest);
        self::assertArrayHasKey(
            $session->get_entity()->get_main_entity()->get_contact_page_course()->id,
            $resultrequest
        );
        self::assertArrayHasKey($session->get_course()->id, $resultrequest);

        self::resetAllData();
    }

    /**
     * Test update_enrolment
     *
     * @covers \local_mentor_core\database_interface::update_enrolment
     */
    public function test_update_enrolment() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::setAdminUser();

        // Create session.
        $session = $this->init_create_session();
        $session->create_manual_enrolment_instance();

        // Get enrol instance.
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('manual');

        // Check if status equals 0.
        self::assertEquals(0, $selfenrolmentinstance->status);

        // Update enrolment instance.
        $selfenrolmentinstance->status = 1;
        $dbinterface->update_enrolment($selfenrolmentinstance);

        // Get enrol instance update.
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('manual');

        // Check if status is update.
        self::assertEquals(1, $selfenrolmentinstance->status);

        self::resetAllData();
    }

    /**
     * Test training_has_sessions
     *
     * @covers \local_mentor_core\database_interface::training_has_sessions
     */
    public function test_training_has_sessions() {

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::setAdminUser();

        // Create entity.
        $entityid = $this->init_create_entity();

        // Cerate session.
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);

        // Check if not session exist in training.
        self::assertFalse($dbinterface->training_has_sessions($training->id));

        // Create session in training.
        \local_mentor_core\session_api::create_session($training->id, 'sessionname', true);

        // Check if session exist in training.
        self::assertTrue($dbinterface->training_has_sessions($training->id));

        self::resetAllData();
    }

    /**
     * Test training_exists
     *
     * @covers \local_mentor_core\database_interface::training_exists
     */
    public function test_training_exists() {

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::setAdminUser();

        $trainingshortname = 'trainingshortname';

        // Check if training not exist.
        self::assertFalse($dbinterface->training_exists($trainingshortname));

        // Create entity.
        $entityid = $this->init_create_entity();

        // Create session.
        $this->init_create_training('trainingname', 'trainingshortname', $entityid);

        // Check if training exist.
        self::assertTrue($dbinterface->training_exists($trainingshortname));

        self::resetAllData();
    }

    /**
     * Test get_session_sharing_by_session_id
     *
     * @covers \local_mentor_core\database_interface::get_session_sharing_by_session_id
     */
    public function test_get_session_sharing_by_session_id() {

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::setAdminUser();

        // Create entities.
        $entity1id = $this->init_create_entity('Entity1');
        $entity2id = $this->init_create_entity('Entity2');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);

        // Check if session is not sharing with other entity.
        $resultrequest = $dbinterface->get_session_sharing_by_session_id($session->id);
        self::assertCount(0, $resultrequest);

        // Update a session course with sharing.
        $data = new stdClass();
        $data->id = $session->id;
        $data->opento = 'other_entities';
        $data->opentolist = [$entity2id];
        $session->update($data);

        // Check if session is sharin with $entity2id.
        $resultrequest = $dbinterface->get_session_sharing_by_session_id($session->id);
        self::assertCount(1, $resultrequest);
        self::assertEquals($session->id, current($resultrequest)->sessionid);
        self::assertEquals($entity2id, current($resultrequest)->coursecategoryid);

        self::resetAllData();
    }

    /**
     * Test get_users_by_mainentity
     *
     * @covers \local_mentor_core\database_interface::get_users_by_mainentity
     */
    public function test_get_users_by_mainentity() {
        global $DB;

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::setAdminUser();

        $entityname = 'entitytest';

        // Create user.
        $userid = self::init_create_user();

        // Set mainentity to user.
        $field = $DB->get_record('user_info_field', ['shortname' => 'mainentity']);
        $userdata = new stdClass();
        $userdata->fieldid = $field->id;
        $userdata->data = $entityname;
        $userdata->userid = $userid;
        $DB->insert_record('user_info_data', $userdata);

        // Check entity is mainentity for user.
        $resultrequest = $dbinterface->get_users_by_mainentity($entityname);
        self::assertCount(1, $resultrequest);
        self::assertEquals($userid, current($resultrequest)->id);

        self::resetAllData();
    }

    /**
     * Test get_role_assignments
     *
     * @covers \local_mentor_core\database_interface::get_role_assignments
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_get_role_assignments() {
        global $USER;

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity1');

        $entity = \local_mentor_core\entity_api::get_entity($entity1id);

        \local_mentor_core\profile_api::role_assign('manager', $USER->id, $entity->get_context());

        $roles = $dbinterface->get_role_assignments($entity->get_context()->id);

        self::assertIsArray($roles);
        self::assertCount(1, $roles);

        self::resetAllData();
    }

    /**
     * Test unassign_roles
     *
     * @covers \local_mentor_core\database_interface::unassign_roles
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_unassign_roles() {
        global $USER, $DB;

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity1');

        $entity = \local_mentor_core\entity_api::get_entity($entity1id);

        \local_mentor_core\profile_api::role_assign('manager', $USER->id, $entity->get_context());

        $manager = $DB->get_record('role', ['shortname' => 'manager']);

        $dbinterface->unassign_roles($entity->get_context()->id, [$manager->id]);

        $roles = $dbinterface->get_role_assignments($entity->get_context()->id);

        self::assertIsArray($roles);
        self::assertCount(0, $roles);

        self::resetAllData();
    }

    /**
     * Test search_main_entities
     *
     * @covers \local_mentor_core\database_interface::search_main_entities
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_search_main_entities() {
        global $DB;

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity 1');
        $categoryoption = new \stdClass();
        $categoryoption->categoryid = $entity1id;
        $categoryoption->name = 'hidden';
        $categoryoption->value = 1;
        $DB->insert_record('category_options', $categoryoption);

        $entity2id = $this->init_create_entity('Entity 2');
        $categoryoption = new \stdClass();
        $categoryoption->categoryid = $entity2id;
        $categoryoption->name = 'hidden';
        $categoryoption->value = 0;
        $DB->insert_record('category_options', $categoryoption);

        // Not match.
        $result = $dbinterface->search_main_entities('test');
        self::assertCount(0, $result);

        // Match with all entity not hidden.
        $result = $dbinterface->search_main_entities('Entity', false);
        self::assertCount(1, $result);
        self::assertArrayNotHasKey($entity1id, $result);
        self::assertArrayHasKey($entity2id, $result);

        // Match with all entity.
        $result = $dbinterface->search_main_entities('Entity', true);
        self::assertCount(2, $result);
        self::assertArrayHasKey($entity1id, $result);
        self::assertArrayHasKey($entity2id, $result);

        self::resetAllData();
    }

    /**
     * Test delete_session
     *
     * @covers \local_mentor_core\database_interface::delete_session
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_delete_session() {
        global $DB;
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity 1');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);

        $dbinterface->delete_session($session);

        $result = $DB->get_record('course', ['id' => $session->courseid]);

        self::assertFalse($result);

        try {
            $dbinterface->delete_session($session);
        } catch (\Exception $e) {
            // Session course has already been deleted.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test get_course_roles
     *
     * @covers \local_mentor_core\database_interface::get_course_roles
     */
    public function test_get_course_roles() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $roles = $dbinterface->get_course_roles();

        $expectedroles = ['participant', 'participantnonediteur', 'concepteur', 'formateur', 'tuteur', 'participantdemonstration'];

        foreach ($roles as $role) {
            if (!in_array($role->shortname, $expectedroles)) {
                self::fail('Role ' . $role->shortname . 'is not allowed in courses.');
            }
        }

        self::resetAllData();
    }

    /**
     * Test is_course_section_visible
     *
     * @covers \local_mentor_core\database_interface::is_course_section_visible
     */
    public function test_is_course_section_visible() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity 1');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);

        $result = $dbinterface->is_course_section_visible($session->courseid, 0);
        self::assertTrue($result);

        $result = $dbinterface->is_course_section_visible($session->courseid, 1);
        self::assertFalse($result);

        // Add a section 1.
        course_create_section($session->courseid, 0);

        $result = $dbinterface->is_course_section_visible($session->courseid, 1);
        self::assertTrue($result);

        self::resetAllData();
    }

    /**
     * Test get_all_category_users
     *
     * @covers \local_mentor_core\database_interface::get_all_category_users
     */
    public function test_get_all_category_users() {
        global $USER;
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity 1');

        $entity = \local_mentor_core\entity_api::get_entity($entity1id);

        $entity->assign_manager($USER->id);

        $data = new stdClass();
        $data->search = false;

        $users = $dbinterface->get_all_category_users($data);

        self::assertCount(1, $users);
        self::assertEquals($USER->id, $users[0]->userid);
        self::assertEquals('Manager', $users[0]->rolename);

        // Wrong search.
        $data = new stdClass();
        $data->search['value'] = 'TEST';

        $users = $dbinterface->get_all_category_users($data);
        self::assertCount(0, $users);

        // Right search.
        $data = new stdClass();
        $data->search['value'] = 'Admin Manager';

        $users = $dbinterface->get_all_category_users($data);
        self::assertCount(1, $users);
        self::assertEquals($USER->id, $users[0]->userid);
        self::assertEquals('Manager', $users[0]->rolename);

        self::resetAllData();
    }

    /**
     * Test get_user_course_roles
     *
     * @covers \local_mentor_core\database_interface::get_user_course_roles
     */
    public function test_get_user_course_roles() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        // Create session.
        $session = $this->init_create_session();

        // Enable self enrol to session.
        $session->create_self_enrolment_instance();
        $session->enable_self_enrolment_instance();

        // Create user.
        $userid = self::init_create_user();

        // Set current user.
        self::setUser($userid);

        $result = $dbinterface->get_user_course_roles($userid, $session->courseid);
        self::assertCount(0, $result);

        // Enrol user to course session.
        $session->enrol_current_user();

        $result = $dbinterface->get_user_course_roles($userid, $session->courseid);

        $firstresult = reset($result);

        self::assertCount(1, $result);
        self::assertEquals('participant', $firstresult->shortname);

        self::resetAllData();
    }

    /**
     * Test get_entities_sessions
     *
     * @covers \local_mentor_core\database_interface::get_entities_sessions
     */
    public function test_get_entities_sessions() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity 1');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);
        $session->update_status(session::STATUS_IN_PROGRESS);

        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        // Create a second entity.
        $entity2id = $this->init_create_entity('Entity 2');

        // Create a training course.
        $training2 = $this->init_create_training('Training course 2', 'Training course 2', $entity2id);

        // Create a session course.
        $session2 = local_mentor_core\session_api::create_session($training2->id, "Session course 2", true);
        $session2->update_status(session::STATUS_IN_PROGRESS);

        $data = new stdClass();
        $data->opento = 'current_entity';
        $session2->update($data);

        // Create a session in preparation.
        $session3 = local_mentor_core\session_api::create_session($training2->id, "Session course 3", true);

        $data = new stdClass();
        $data->opento = 'current_entity';
        $session3->update($data);

        $result = $dbinterface->get_entities_sessions([
            $entity1id => $training->get_entity(), $entity2id => $training2->get_entity(),
        ]);

        self::assertCount(2, $result);

        self::resetAllData();
    }

    /**
     * Test get_all_training_files
     *
     * @covers \local_mentor_core\database_interface::get_all_training_files
     */
    public function test_get_all_training_files() {
        global $CFG;

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity 1');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        $fs = get_file_storage();

        $contextid = context_system::instance()->id;
        $component = 'local_trainings';
        $itemid = $training->id;
        $filearea = 'thumbnail';
        $contextid = $training->get_context()->id;

        $filerecord = new stdClass();
        $filerecord->contextid = $contextid;
        $filerecord->component = $component;
        $filerecord->filearea = $filearea;
        $filerecord->itemid = $itemid;
        $filerecord->filepath = '/';
        $filerecord->filename = 'logo.png';

        $filepath = $CFG->dirroot . '/local/mentor_core/pix/logo.png';

        // Create file.
        $fs->create_file_from_pathname($filerecord, $filepath);

        $files = $dbinterface->get_all_training_files($contextid);

        $file = reset($files);

        self::assertCount(1, $files);
        self::assertEquals($filerecord->filename, $file->filename);
        self::assertEquals($filerecord->contextid, $file->contextid);

        self::resetAllData();
    }

    /**
     * Test sheets deletion
     *
     * @covers \local_mentor_core\database_interface::delete_training_sheet
     * @covers \local_mentor_core\database_interface::delete_session_sheet
     */
    public function test_delete_sheets() {
        global $DB;
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity 1');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);
        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        $dbinterface->delete_session_sheet($session->courseshortname);

        self::assertFalse($DB->get_record('session', ['courseshortname' => $session->courseshortname]));

        $dbinterface->delete_training_sheet($training->courseshortname);

        self::assertFalse($DB->get_record('training', ['courseshortname' => $session->courseshortname]));

        self::resetAllData();
    }

    /**
     * Test get_max_training_session_index
     *
     * @covers \local_mentor_core\database_interface::get_max_training_session_index
     */
    public function test_get_max_training_session_index() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity 1');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);

        self::assertEquals(1, $dbinterface->get_max_training_session_index($training->id));

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course 2", true);

        self::assertEquals(2, $dbinterface->get_max_training_session_index($training->id));

        self::resetAllData();
    }

    /**
     * Test get_all_admin_sessions
     *
     * @covers \local_mentor_core\database_interface::get_all_admin_sessions
     */
    public function test_get_all_admin_sessions() {
        global $USER;
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entity 1');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);
        $session->update_status(\local_mentor_core\session::STATUS_OPENED_REGISTRATION);

        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course 2", true);
        $session->update_status(\local_mentor_core\session::STATUS_OPENED_REGISTRATION);

        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course 3", true);
        $session->update_status(\local_mentor_core\session::STATUS_IN_PROGRESS);

        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course 4", true);
        // Session with in preparation status must be ignored.
        $session->update_status(\local_mentor_core\session::STATUS_IN_PREPARATION);

        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        $sessions = $dbinterface->get_all_admin_sessions($USER);

        self::assertCount(3, $sessions);

        // Create a non admin user.
        $newuser = self::getDataGenerator()->create_user();

        self::setUser($newuser);

        $sessions = $dbinterface->get_all_admin_sessions($USER);
        self::assertFalse($sessions);

        self::resetAllData();
    }

    /**
     * Test update_cohort
     *
     * @covers \local_mentor_core\database_interface::update_cohort
     */
    public function test_update_cohort() {
        global $DB;
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $cohort = self::getDataGenerator()->create_cohort();

        $cohort->name = 'Cohort name';

        $result = $dbinterface->update_cohort($cohort);

        self::assertTrue($result);

        $dbcohort = $DB->get_record('cohort', ['id' => $cohort->id]);

        self::assertEquals($dbcohort->name, $cohort->name);

        self::resetAllData();
    }

    /**
     * Test search_main_entities_user_managed
     *
     * @covers \local_mentor_core\database_interface::search_main_entities_user_managed
     */
    public function test_search_main_entities_user_managed() {
        global $DB;

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entité Mentor');
        $entity1 = \local_mentor_core\entity_api::get_entity($entity1id);
        $categoryoption = new \stdClass();
        $categoryoption->categoryid = $entity1id;
        $categoryoption->name = 'hidden';
        $categoryoption->value = 1;
        $DB->insert_record('category_options', $categoryoption);

        $entity2id = $this->init_create_entity('Entité Mentor 2');
        $entity2 = \local_mentor_core\entity_api::get_entity($entity2id);
        $categoryoption = new \stdClass();
        $categoryoption->categoryid = $entity2id;
        $categoryoption->name = 'hidden';
        $categoryoption->value = 0;
        $DB->insert_record('category_options', $categoryoption);

        $role = $entity1->get_manager_role()->shortname;

        $user = self::getDataGenerator()->create_user();

        $result = $dbinterface->search_main_entities_user_managed('Mentor', $user->id, $role);
        self::assertCount(0, $result);

        // Assign manager role.
        $entity1->assign_manager($user->id);
        $entity2->assign_manager($user->id);

        $result = $dbinterface->search_main_entities_user_managed('Mentor', $user->id, $role, false);
        self::assertCount(1, $result);
        self::assertArrayNotHasKey($entity1id, $result);
        self::assertArrayHasKey($entity2id, $result);

        $result = $dbinterface->search_main_entities_user_managed('Mentor', $user->id, $role, true);
        self::assertCount(2, $result);
        self::assertArrayHasKey($entity1id, $result);
        self::assertArrayHasKey($entity2id, $result);

        self::resetAllData();
    }

    /**
     * Test get_sessions_shared_to_entities
     *
     * @covers \local_mentor_core\database_interface::get_sessions_shared_to_entities
     */
    public function test_get_sessions_shared_to_entities() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entité Mentor');
        $entity2id = $this->init_create_entity('Entité Mentor 2');
        $entity3id = $this->init_create_entity('Entité Mentor 3');
        $entity4id = $this->init_create_entity('Entité Mentor 4');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);
        $session->update_status(\local_mentor_core\session::STATUS_IN_PROGRESS);

        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        // Share the session.
        $session->update_session_sharing([$entity2id, $entity3id]);

        $result = $dbinterface->get_sessions_shared_to_entities([$entity4id]);
        self::assertCount(0, $result);

        $result = $dbinterface->get_sessions_shared_to_entities([$entity2id, $entity3id]);
        self::assertCount(1, $result);

        $result = $dbinterface->get_sessions_shared_to_entities($entity2id . ',' . $entity3id);
        self::assertCount(1, $result);

        self::resetAllData();
    }

    /**
     * Test get_sessions_shared_to_all_entities
     *
     * @covers \local_mentor_core\database_interface::get_sessions_shared_to_all_entities
     */
    public function test_get_sessions_shared_to_all_entities() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entity1id = $this->init_create_entity('Entité Mentor');

        // Create a training course.
        $training = $this->init_create_training('Training course', 'Training course', $entity1id);

        // Create a session course.
        $session = local_mentor_core\session_api::create_session($training->id, "Session course", true);
        $session->update_status(\local_mentor_core\session::STATUS_IN_PROGRESS);

        $result = $dbinterface->get_sessions_shared_to_all_entities();
        self::assertCount(0, $result);

        // Share the session.
        $data = new stdClass();
        $data->opento = 'all';
        $session->update($data);

        $result = $dbinterface->get_sessions_shared_to_all_entities();
        self::assertCount(1, $result);

        self::resetAllData();
    }

    /**
     * Test entity_shortname_exists
     *
     * @covers \local_mentor_core\database_interface::entity_shortname_exists
     */
    public function test_entity_shortname_exists() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $entityname = 'Entité Mentor';

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::assertFalse($dbinterface->entity_shortname_exists($entityname));

        $this->init_create_entity('Entité Mentor');

        self::assertTrue($dbinterface->entity_shortname_exists($entityname));

        self::resetAllData();
    }

    /**
     * Test get_all_admins
     *
     * @covers \local_mentor_core\database_interface::get_all_admins
     */
    public function test_get_all_admins() {
        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        $entityname = 'Entity 1';
        $this->init_create_entity($entityname);

        $data = new stdClass();
        $data->search = ['value' => ""];

        self::assertEmpty($dbinterface->get_all_admins($data));

        // Create new admin.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'test@test.com';
        $user->username = 'testusername';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $entityname;

        $user1id = \local_mentor_core\profile_api::create_user($user);

        $adminsid = get_config('moodle', 'siteadmins');
        set_config('siteadmins', "$adminsid,$user1id");

        $adminpilote = $dbinterface->get_all_admins($data);

        self::assertCount(1, $adminpilote);
        self::assertArrayHasKey($user1id, $adminpilote);

        // Create new admin.
        $user = new stdClass();
        $user->lastname = 'lastname2';
        $user->firstname = 'firstname2';
        $user->email = 'test2@test.com';
        $user->username = 'testusername2';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $entityname;

        $user2id = \local_mentor_core\profile_api::create_user($user);

        $adminsid = get_config('moodle', 'siteadmins');
        set_config('siteadmins', "$adminsid,$user2id");

        $adminpilote = $dbinterface->get_all_admins($data);

        self::assertCount(2, $adminpilote);
        self::assertArrayHasKey($user1id, $adminpilote);
        self::assertArrayHasKey($user2id, $adminpilote);

        $data->search = ['value' => "lastname2"];
        $adminpilote = $dbinterface->get_all_admins($data);

        self::assertCount(1, $adminpilote);
        self::assertArrayNotHasKey($user1id, $adminpilote);
        self::assertArrayHasKey($user2id, $adminpilote);

        self::resetAllData();
    }

    /**
     * Test is_shared_to_entity_by_session_id
     *
     * @covers \local_mentor_core\database_interface::is_shared_to_entity_by_session_id
     */
    public function test_is_shared_to_entity_by_session_id() {
        global $DB;

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();
        $this->init_role();

        $dbinterface = \local_mentor_core\database_interface::get_instance();

        self::assertFalse($dbinterface->is_shared_to_entity_by_session_id(1, 2));

        $data = new stdClass();
        $data->sessionid = 1;
        $data->coursecategoryid = 2;
        $DB->insert_record('session_sharing', $data);

        self::assertTrue($dbinterface->is_shared_to_entity_by_session_id(1, 2));

        self::resetAllData();
    }

    /**
     * Test get_available_sessions_to_catalog_by_entity
     *
     * @covers \local_mentor_core\database_interface::get_available_sessions_to_catalog_by_entity
     */
    public function test_get_available_sessions_to_catalog_by_entity() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $dbimock = $this->getMockBuilder('\local_mentor_core\database_interface')
            ->setMethods([
                'get_sessions_shared_to_all_entities',
                'get_entities_sessions',
                'get_sessions_shared_to_entities',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $dbimock->expects($this->once())
            ->method('get_sessions_shared_to_all_entities')
            ->will($this->returnValue([1]));

        $dbimock->expects($this->once())
            ->method('get_entities_sessions')
            ->will($this->returnValue([2]));

        $dbimock->expects($this->once())
            ->method('get_sessions_shared_to_entities')
            ->will($this->returnValue([3]));

        self::assertEquals(
            $dbimock->get_available_sessions_to_catalog_by_entity(1),
            [1, 2, 3]
        );

        self::resetAllData();
    }

    /**
     * Test get_profile_field_value
     *
     * @covers \local_mentor_core\database_interface::get_profile_field_value
     */
    public function test_get_profile_field_value() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $user = self::getDataGenerator()->create_user();

        // Field does not exist.
        self::assertFalse($dbi->get_profile_field_value($user->id, 'falsefield'));

        // Field exist but is empty.
        self::assertEmpty($dbi->get_profile_field_value($user->id, 'mainentity'));

        $dbi->set_profile_field_value($user->id, 'mainentity', 'entitytest');

        // Field exist.
        self::assertEquals('entitytest', $dbi->get_profile_field_value($user->id, 'mainentity'));

        self::resetAllData();
    }

    /**
     * Test get_course_singleactivity_type
     *
     * @covers \local_mentor_core\database_interface::get_course_singleactivity_type
     */
    public function test_get_course_singleactivity_type() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();

        $dbi = \local_mentor_core\database_interface::get_instance();

        self::assertFalse($dbi->get_course_singleactivity_type($course->id));

        $fielddata = new \stdClass();
        $fielddata->courseid = $course->id;
        $fielddata->format = 'singleactivity';
        $fielddata->name = 'activitytype';
        $fielddata->value = 'field value';

        $DB->insert_record('course_format_options', $fielddata);

        self::assertEquals('field value', $dbi->get_course_singleactivity_type($course->id));

        self::resetAllData();
    }

    /**
     * Test get_all_session_group
     *
     * @covers \local_mentor_core\database_interface::get_all_session_group
     */
    public function test_get_all_session_group() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $session = $this->init_create_session();
        $course = $session->get_course();
        $course2 = self::getDataGenerator()->create_course();

        $dbi = \local_mentor_core\database_interface::get_instance();

        // No existing group.
        self::assertEmpty($dbi->get_all_session_group($session->id));

        $group1 = self::getDataGenerator()->create_group(['courseid' => $course->id]);

        $allgroup = $dbi->get_all_session_group($session->id);

        // Existing group.
        self::assertCount(1, $allgroup);
        self::assertArrayHasKey($group1->id, $allgroup);

        $group2 = self::getDataGenerator()->create_group(['courseid' => $course->id]);

        $allgroup = $dbi->get_all_session_group($session->id);

        // Other existing group.
        self::assertCount(2, $allgroup);
        self::assertArrayHasKey($group1->id, $allgroup);
        self::assertArrayHasKey($group2->id, $allgroup);

        $group3 = self::getDataGenerator()->create_group(['courseid' => $course2->id]);

        $allgroup = $dbi->get_all_session_group($session->id);

        // The new group does not belong to the course.
        self::assertCount(2, $allgroup);
        self::assertArrayHasKey($group1->id, $allgroup);
        self::assertArrayHasKey($group2->id, $allgroup);
        self::assertArrayNotHasKey($group3->id, $allgroup);

        self::resetAllData();
    }

    /**
     * Test add_trainings_user_designer_favourite
     *
     * @covers \local_mentor_core\database_interface::add_trainings_user_designer_favourite
     * @covers \local_mentor_core\database_interface::add_user_favourite
     */
    public function test_add_trainings_user_designer_favourite() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);
        $user = self::getDataGenerator()->create_user();

        $DB->delete_records('favourite');

        // Favourite table is empty.
        self::assertEmpty($DB->get_records('favourite'));

        // Create favourite with defined user.
        $favouriteid = $dbi->add_trainings_user_designer_favourite($training->id, $training->get_context()->id, $user->id);

        $favourites = $DB->get_records('favourite');

        self::assertCount(1, $favourites);
        self::assertArrayHasKey($favouriteid, $favourites);
        self::assertEquals($favourites[$favouriteid]->component, 'local_trainings');
        self::assertEquals($favourites[$favouriteid]->itemtype, 'favourite_training');
        self::assertEquals($favourites[$favouriteid]->itemid, $training->id);
        self::assertEquals($favourites[$favouriteid]->contextid, $training->get_context()->id);
        self::assertEquals($favourites[$favouriteid]->userid, $user->id);

        // Create favourite with undefined user.
        $favouriteid2 = $dbi->add_trainings_user_designer_favourite($training->id, $training->get_context()->id);

        $favourites = $DB->get_records('favourite');

        self::assertCount(2, $favourites);
        self::assertArrayHasKey($favouriteid2, $favourites);
        self::assertEquals($favourites[$favouriteid2]->component, 'local_trainings');
        self::assertEquals($favourites[$favouriteid2]->itemtype, 'favourite_training');
        self::assertEquals($favourites[$favouriteid2]->itemid, $training->id);
        self::assertEquals($favourites[$favouriteid2]->contextid, $training->get_context()->id);
        self::assertEquals($favourites[$favouriteid2]->userid, $USER->id);

        self::resetAllData();
    }

    /**
     * Test remove_trainings_user_designer_favourite
     *
     * @covers \local_mentor_core\database_interface::remove_trainings_user_designer_favourite
     * @covers \local_mentor_core\database_interface::remove_user_favourite
     */
    public function test_remove_trainings_user_designer_favourite() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);
        $user = self::getDataGenerator()->create_user();

        $DB->delete_records('favourite');

        // Favourite table is empty.
        self::assertEmpty($DB->get_records('favourite'));

        // Add favourite with defined user.
        $favourite = new \stdClass();
        $favourite->component = 'local_trainings';
        $favourite->itemtype = 'favourite_training';
        $favourite->itemid = $training->id;
        $favourite->contextid = $training->get_context()->id;
        $favourite->userid = $user->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $favouriteid1 = $DB->insert_record('favourite', $favourite);

        // Add favourite with global user.
        $favourite = new \stdClass();
        $favourite->component = 'local_trainings';
        $favourite->itemtype = 'favourite_training';
        $favourite->itemid = $training->id;
        $favourite->contextid = $training->get_context()->id;
        $favourite->userid = $USER->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $favouriteid2 = $DB->insert_record('favourite', $favourite);

        $favourites = $DB->get_records('favourite');
        self::assertCount(2, $favourites);
        self::assertArrayHasKey($favouriteid1, $favourites);
        self::assertArrayHasKey($favouriteid2, $favourites);

        // Add favourite with defined user.
        $dbi->remove_trainings_user_designer_favourite($training->id, $training->get_context()->id, $user->id);

        $favourites = $DB->get_records('favourite');
        self::assertCount(1, $favourites);
        self::assertArrayNotHasKey($favouriteid1, $favourites);
        self::assertArrayHasKey($favouriteid2, $favourites);

        // Add favourite with undefined user.
        $dbi->remove_trainings_user_designer_favourite($training->id, $training->get_context()->id);

        $favourites = $DB->get_records('favourite');
        self::assertEmpty($favourites);
        self::assertArrayNotHasKey($favouriteid1, $favourites);
        self::assertArrayNotHasKey($favouriteid2, $favourites);

        self::resetAllData();
    }

    /**
     * Test is_training_user_favourite_designer
     *
     * @covers \local_mentor_core\database_interface::is_training_user_favourite_designer
     * @covers \local_mentor_core\database_interface::is_user_favourite
     */
    public function test_is_training_user_favourite_designer() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);
        $user = self::getDataGenerator()->create_user();

        $DB->delete_records('favourite');

        // Favourite table is empty.
        self::assertEmpty($DB->get_records('favourite'));

        // Check if training is favourite to user with user defined.
        self::assertFalse($dbi->is_training_user_favourite_designer($training->id, $training->get_context()->id, $user->id));

        // Check if training is favourite to user with user undefined.
        self::assertFalse($dbi->is_training_user_favourite_designer($training->id, $training->get_context()->id));

        // Add favourite with defined user.
        $favourite = new \stdClass();
        $favourite->component = 'local_trainings';
        $favourite->itemtype = 'favourite_training';
        $favourite->itemid = $training->id;
        $favourite->contextid = $training->get_context()->id;
        $favourite->userid = $user->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $DB->insert_record('favourite', $favourite);

        // Check if training is favourite to user with user defined.
        self::assertTrue($dbi->is_training_user_favourite_designer($training->id, $training->get_context()->id, $user->id));

        // Check if training is favourite to user with user undefined.
        self::assertFalse($dbi->is_training_user_favourite_designer($training->id, $training->get_context()->id));

        // Add favourite with global user.
        $favourite = new \stdClass();
        $favourite->component = 'local_trainings';
        $favourite->itemtype = 'favourite_training';
        $favourite->itemid = $training->id;
        $favourite->contextid = $training->get_context()->id;
        $favourite->userid = $USER->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $DB->insert_record('favourite', $favourite);

        // Check if training is favourite to user with user defined.
        self::assertTrue($dbi->is_training_user_favourite_designer($training->id, $training->get_context()->id, $user->id));

        // Check if training is favourite to user with user undefined.
        self::assertTrue($dbi->is_training_user_favourite_designer($training->id, $training->get_context()->id));

        self::resetAllData();
    }

    /**
     * Test get_training_user_favourite_designer_data
     *
     * @covers \local_mentor_core\database_interface::get_training_user_favourite_designer_data
     * @covers \local_mentor_core\database_interface::get_user_favourite
     */
    public function test_get_training_user_favourite_designer_data() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $entityid = $this->init_create_entity();
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);
        $user = self::getDataGenerator()->create_user();

        $DB->delete_records('favourite');

        // Favourite table is empty.
        self::assertEmpty($DB->get_records('favourite'));

        // Get training favourite to user with user defined.
        self::assertFalse($dbi->get_training_user_favourite_designer_data($training->id, $training->get_context()->id, $user->id));

        // Get training favourite to user with user undefined.
        self::assertFalse($dbi->get_training_user_favourite_designer_data($training->id, $training->get_context()->id));

        // Add favourite with defined user.
        $favourite = new \stdClass();
        $favourite->component = 'local_trainings';
        $favourite->itemtype = 'favourite_training';
        $favourite->itemid = $training->id;
        $favourite->contextid = $training->get_context()->id;
        $favourite->userid = $user->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $DB->insert_record('favourite', $favourite);

        // Get training favourite to user with user defined.
        $favouritedata1 = $dbi->get_training_user_favourite_designer_data($training->id, $training->get_context()->id, $user->id);

        self::assertEquals($favouritedata1->component, 'local_trainings');
        self::assertEquals($favouritedata1->itemtype, 'favourite_training');
        self::assertEquals($favouritedata1->itemid, $training->id);
        self::assertEquals($favouritedata1->contextid, $training->get_context()->id);
        self::assertEquals($favouritedata1->userid, $user->id);

        // Get training favourite to user with user undefined.
        self::assertFalse($dbi->get_training_user_favourite_designer_data($training->id, $training->get_context()->id));

        // Add favourite with global user.
        $favourite = new \stdClass();
        $favourite->component = 'local_trainings';
        $favourite->itemtype = 'favourite_training';
        $favourite->itemid = $training->id;
        $favourite->contextid = $training->get_context()->id;
        $favourite->userid = $USER->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $DB->insert_record('favourite', $favourite);

        // Get training favourite to user with user defined.
        $favouritedata1bis = $dbi->get_training_user_favourite_designer_data($training->id, $training->get_context()->id,
            $user->id);

        self::assertEquals($favouritedata1bis->component, 'local_trainings');
        self::assertEquals($favouritedata1bis->itemtype, 'favourite_training');
        self::assertEquals($favouritedata1bis->itemid, $training->id);
        self::assertEquals($favouritedata1bis->contextid, $training->get_context()->id);
        self::assertEquals($favouritedata1bis->userid, $user->id);

        // Get training favourite to user with user undefined.
        $favouritedata2 = $dbi->get_training_user_favourite_designer_data($training->id, $training->get_context()->id);

        self::assertEquals($favouritedata2->component, 'local_trainings');
        self::assertEquals($favouritedata2->itemtype, 'favourite_training');
        self::assertEquals($favouritedata2->itemid, $training->id);
        self::assertEquals($favouritedata2->contextid, $training->get_context()->id);
        self::assertEquals($favouritedata2->userid, $USER->id);

        self::resetAllData();
    }

    /**
     * Test add_user_favourite_session
     *
     * @covers \local_mentor_core\database_interface::add_user_favourite_session
     * @covers \local_mentor_core\database_interface::add_user_favourite
     */
    public function test_add_user_favourite_session() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $session = $this->init_create_session();
        $user = self::getDataGenerator()->create_user();

        $DB->delete_records('favourite');

        // Favourite table is empty.
        self::assertEmpty($DB->get_records('favourite'));

        // Create favourite with defined user.
        $favouriteid = $dbi->add_user_favourite_session($session->id, $session->get_context()->id, $user->id);

        $favourites = $DB->get_records('favourite');

        self::assertCount(1, $favourites);
        self::assertArrayHasKey($favouriteid, $favourites);
        self::assertEquals($favourites[$favouriteid]->component, 'local_session');
        self::assertEquals($favourites[$favouriteid]->itemtype, 'favourite_session');
        self::assertEquals($favourites[$favouriteid]->itemid, $session->id);
        self::assertEquals($favourites[$favouriteid]->contextid, $session->get_context()->id);
        self::assertEquals($favourites[$favouriteid]->userid, $user->id);

        // Create favourite with undefined user.
        $favouriteid2 = $dbi->add_user_favourite_session($session->id, $session->get_context()->id);

        $favourites = $DB->get_records('favourite');

        self::assertCount(2, $favourites);
        self::assertArrayHasKey($favouriteid2, $favourites);
        self::assertEquals($favourites[$favouriteid2]->component, 'local_session');
        self::assertEquals($favourites[$favouriteid2]->itemtype, 'favourite_session');
        self::assertEquals($favourites[$favouriteid2]->itemid, $session->id);
        self::assertEquals($favourites[$favouriteid2]->contextid, $session->get_context()->id);
        self::assertEquals($favourites[$favouriteid2]->userid, $USER->id);

        self::resetAllData();
    }

    /**
     * Test remove_user_favourite_session
     *
     * @covers \local_mentor_core\database_interface::remove_user_favourite_session
     * @covers \local_mentor_core\database_interface::remove_user_favourite
     */
    public function test_remove_user_favourite_session() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $session = $this->init_create_session();
        $user = self::getDataGenerator()->create_user();

        $DB->delete_records('favourite');

        // Favourite table is empty.
        self::assertEmpty($DB->get_records('favourite'));

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

        // Add favourite with global user.
        $favourite = new \stdClass();
        $favourite->component = 'local_session';
        $favourite->itemtype = 'favourite_session';
        $favourite->itemid = $session->id;
        $favourite->contextid = $session->get_context()->id;
        $favourite->userid = $USER->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $favouriteid2 = $DB->insert_record('favourite', $favourite);

        $favourites = $DB->get_records('favourite');
        self::assertCount(2, $favourites);
        self::assertArrayHasKey($favouriteid1, $favourites);
        self::assertArrayHasKey($favouriteid2, $favourites);

        // Add favourite with defined user.
        $dbi->remove_user_favourite_session($session->id, $session->get_context()->id, $user->id);

        $favourites = $DB->get_records('favourite');
        self::assertCount(1, $favourites);
        self::assertArrayNotHasKey($favouriteid1, $favourites);
        self::assertArrayHasKey($favouriteid2, $favourites);

        // Add favourite with undefined user.
        $dbi->remove_user_favourite_session($session->id, $session->get_context()->id);

        $favourites = $DB->get_records('favourite');
        self::assertEmpty($favourites);
        self::assertArrayNotHasKey($favouriteid1, $favourites);
        self::assertArrayNotHasKey($favouriteid2, $favourites);

        self::resetAllData();
    }

    /**
     * Test get_user_favourite_session_data
     *
     * @covers \local_mentor_core\database_interface::get_user_favourite_session_data
     * @covers \local_mentor_core\database_interface::get_user_favourite
     */
    public function test_get_user_favourite_session_data() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $session = $this->init_create_session();
        $user = self::getDataGenerator()->create_user();

        $DB->delete_records('favourite');

        // Favourite table is empty.
        self::assertEmpty($DB->get_records('favourite'));

        // Get training favourite to user with user defined.
        self::assertFalse($dbi->get_user_favourite_session_data($session->id, $session->get_context()->id, $user->id));

        // Get training favourite to user with user undefined.
        self::assertFalse($dbi->get_user_favourite_session_data($session->id, $session->get_context()->id));

        // Add favourite with defined user.
        $favourite = new \stdClass();
        $favourite->component = 'local_session';
        $favourite->itemtype = 'favourite_session';
        $favourite->itemid = $session->id;
        $favourite->contextid = $session->get_context()->id;
        $favourite->userid = $user->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $DB->insert_record('favourite', $favourite);

        // Get training favourite to user with user defined.
        $favouritedata1 = $dbi->get_user_favourite_session_data($session->id, $session->get_context()->id, $user->id);

        self::assertEquals($favouritedata1->component, 'local_session');
        self::assertEquals($favouritedata1->itemtype, 'favourite_session');
        self::assertEquals($favouritedata1->itemid, $session->id);
        self::assertEquals($favouritedata1->contextid, $session->get_context()->id);
        self::assertEquals($favouritedata1->userid, $user->id);

        // Get training favourite to user with user undefined.
        self::assertFalse($dbi->get_user_favourite_session_data($session->id, $session->get_context()->id));

        // Add favourite with global user.
        $favourite = new \stdClass();
        $favourite->component = 'local_session';
        $favourite->itemtype = 'favourite_session';
        $favourite->itemid = $session->id;
        $favourite->contextid = $session->get_context()->id;
        $favourite->userid = $USER->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $DB->insert_record('favourite', $favourite);

        // Get training favourite to user with user defined.
        $favouritedata1bis = $dbi->get_user_favourite_session_data($session->id, $session->get_context()->id,
            $user->id);

        self::assertEquals($favouritedata1bis->component, 'local_session');
        self::assertEquals($favouritedata1bis->itemtype, 'favourite_session');
        self::assertEquals($favouritedata1bis->itemid, $session->id);
        self::assertEquals($favouritedata1bis->contextid, $session->get_context()->id);
        self::assertEquals($favouritedata1bis->userid, $user->id);

        // Get training favourite to user with user undefined.
        $favouritedata2 = $dbi->get_user_favourite_session_data($session->id, $session->get_context()->id);

        self::assertEquals($favouritedata2->component, 'local_session');
        self::assertEquals($favouritedata2->itemtype, 'favourite_session');
        self::assertEquals($favouritedata2->itemid, $session->id);
        self::assertEquals($favouritedata2->contextid, $session->get_context()->id);
        self::assertEquals($favouritedata2->userid, $USER->id);

        self::resetAllData();
    }

    /**
     * Test get_user_preference
     *
     * @covers \local_mentor_core\database_interface::get_user_preference
     */
    public function test_get_user_preference() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $DB->delete_records('user_preferences');

        // User_preferences table is empty.
        self::assertEmpty($DB->get_records('user_preferences'));

        // Preference not exist.
        self::assertFalse($dbi->get_user_preference($USER->id, 'prefenrecename'));

        // Insert new preference user.
        $preference = new \stdClass();
        $preference->userid = $USER->id;
        $preference->name = 'preferencename';
        $preference->value = 'preferencevalue';
        $DB->insert_record('user_preferences', $preference);

        // Preference exist.
        self::assertEquals('preferencevalue', $dbi->get_user_preference($USER->id, 'preferencename'));

        self::resetAllData();
    }

    /**
     * Test set_user_preference
     *
     * @covers \local_mentor_core\database_interface::set_user_preference
     */
    public function test_set_user_preference() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $DB->delete_records('user_preferences');

        // User_preferences table is empty.
        self::assertEmpty($DB->get_records('user_preferences'));

        // Preference not exist.
        self::assertFalse($DB->get_record('user_preferences', ['userid' => $USER->id, 'name' => 'preferencename']));

        // Insert new preference user.
        $dbi->set_user_preference($USER->id, 'preferencename', 'preferencevalue');

        // Preference exist.
        $preference = $DB->get_record('user_preferences', ['userid' => $USER->id, 'name' => 'preferencename']);
        self::assertIsObject($preference);
        self::assertEquals($preference->value, 'preferencevalue');

        // Update new preference user.
        $dbi->set_user_preference($USER->id, 'preferencename', 'newpreferencevalue');

        // Same preference with update value.
        $preference2 = $DB->get_record('user_preferences', ['userid' => $USER->id, 'name' => 'preferencename']);
        self::assertIsObject($preference);
        self::assertEquals($preference2->id, $preference->id);
        self::assertNotEquals($preference2->value, $preference->value);
        self::assertEquals($preference2->value, 'newpreferencevalue');

        self::resetAllData();
    }

    /**
     * Test user_has_role_in_context
     *
     * @covers \local_mentor_core\database_interface::user_has_role_in_context
     */
    public function test_user_has_role_in_context() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $user = self::getDataGenerator()->create_user();
        $course = self::getDataGenerator()->create_course();

        self::assertFalse($dbi->user_has_role_in_context($user->id, 'participant', \context_course::instance($course->id)->id));

        self::getDataGenerator()->enrol_user($user->id, $course->id, 'participant');

        self::assertTrue($dbi->user_has_role_in_context($user->id, 'participant', \context_course::instance($course->id)->id));

        self::resetAllData();
    }

    /**
     * Test has_enroll_user_enabled
     *
     * @covers \local_mentor_core\database_interface::has_enroll_user_enabled
     */
    public function test_has_enroll_user_enabled() {
        global $DB, $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        $CFG->defaultauth = 'manual';

        self::setAdminUser();

        $course = self::getDataGenerator()->create_course();
        $user = self::getDataGenerator()->create_user();

        $dbi = \local_mentor_core\database_interface::get_instance();

        // No enrol exist.
        self::assertFalse($dbi->has_enroll_user_enabled($course->id, $user->id));

        // Manual enrol user.
        self::getDataGenerator()->enrol_user($user->id, $course->id, 'participant');

        // User is enrol.
        self::assertTrue($dbi->has_enroll_user_enabled($course->id, $user->id));

        // Disable enrol user.
        $enroluserinstance = $DB->get_record_sql('
            SELECT ue.*
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.enrol = \'manual\' AND
                e.courseid = :courseid AND
                ue.userid = :userid
        ', ['courseid' => $course->id, 'userid' => $user->id]);
        $enroluserinstance->status = 1;
        $DB->update_record('user_enrolments', $enroluserinstance);

        // User is enrol but enrol is disable.
        self::assertFalse($dbi->has_enroll_user_enabled($course->id, $user->id));

        // Create new enrol : self enrol.
        self::getDataGenerator()->enrol_user($user->id, $course->id, 'participant', 'self');

        // Manual enrol is disable, but self enrol is enable.
        self::assertTrue($dbi->has_enroll_user_enabled($course->id, $user->id));

        self::resetAllData();
    }

    /**
     * Test get_all_entities
     * with filter
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_all_entities
     */
    public function test_get_all_entities_db() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $DB->delete_records('course_categories');

        for ($i = 0; $i < 10; $i++) {
            $entityname = 'entity' . $i;

            $entity = self::getDataGenerator()->create_category(['name' => $entityname, 'shortname' => $entityname]);

            if ($i < 5) {
                $categoryoption = new \stdClass();
                $categoryoption->categoryid = $entity->id;
                $categoryoption->name = 'hidden';
                $categoryoption->value = 1;
                $DB->insert_record('category_options', $categoryoption);
            }

            $categoryunder = self::getDataGenerator()->create_category(
                ['name' => \local_mentor_core\entity::SUB_ENTITY_CATEGORY, 'parent' => $entity->id]
            );

            self::getDataGenerator()->create_category(
                ['name' => 'sub' . $entityname, 'shortname' => 'sub' . $entityname, 'parent' => $categoryunder->id]
            );
        }

        $data = new \stdClass();
        $data->order = [];
        $data->order['dir'] = 'ASC';
        $data->search = ['value' => null];
        $data->draw = null;
        $data->length = 10;
        $data->start = 0;

        $dbi = \local_mentor_core\database_interface::get_instance();

        // With admin.
        $managedentities = $dbi->get_all_entities(true, $data, true);
        self::assertCount(20, $managedentities);
        self::assertEquals('entity0', current($managedentities)->name);

        // With not show hidden entity.
        $managedentities = $dbi->get_all_entities(true, $data, false);
        self::assertCount(15, $managedentities);
        self::assertEquals('subentity0', current($managedentities)->name);

        $data->order['dir'] = 'DESC';
        $managedentities = $dbi->get_all_entities(true, $data, true);
        self::assertCount(20, $managedentities);
        self::assertEquals('subentity9', current($managedentities)->name);

        $data->order['dir'] = 'ASC';
        $data->search = ['value' => 'entity3'];
        $managedentities = $dbi->get_all_entities(true, $data, true);
        self::assertCount(2, $managedentities);
        self::assertEquals('entity3', current($managedentities)->name);

        self::resetAllData();
    }

    /**
     * Test get_library_object
     * with filter
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_library_object
     */
    public function test_get_library_object() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();
        $librarybject = $dbi->get_library_object();
        self::assertEquals($librarybject->name, \local_mentor_core\library::NAME);
        self::assertEquals($librarybject->idnumber, \local_mentor_core\library::SHORTNAME);

        self::resetAllData();
    }

    /**
     * Test get_library_publication
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_library_publication
     */
    public function test_get_library_publication() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $falsetrainingid = 10;
        $falseoriginaltrainingid = 20;
        $falseuserid = 64;

        $dbi = \local_mentor_core\database_interface::get_instance();
        self::assertFalse($dbi->get_library_publication($falsetrainingid, 'falsecollumn'));

        $dbi = \local_mentor_core\database_interface::get_instance();
        self::assertFalse($dbi->get_library_publication($falseoriginaltrainingid));
        self::assertFalse($dbi->get_library_publication($falsetrainingid, 'trainingid'));

        $DB->insert_record('library', [
            'trainingid' => $falsetrainingid,
            'originaltrainingid' => $falseoriginaltrainingid,
            'timecreated' => time(),
            'timemodified' => time(),
            'userid' => $falseuserid,
        ]);

        $traininglibrary = $dbi->get_library_publication($falseoriginaltrainingid);
        self::assertEquals($traininglibrary->trainingid, $falsetrainingid);
        self::assertEquals($traininglibrary->originaltrainingid, $falseoriginaltrainingid);
        self::assertEquals($traininglibrary->userid, $falseuserid);

        self::resetAllData();
    }

    /**
     * Test publish_to_library
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::publish_to_library
     */
    public function test_publish_to_library() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $falsetriningid = 10;
        $falsetrainingid = 20;
        $falseuserid = 64;

        $dbi = \local_mentor_core\database_interface::get_instance();
        self::assertFalse($dbi->get_library_publication($falsetriningid));

        $dbi->publish_to_library($falsetrainingid, $falsetriningid, $falseuserid);

        $traininglibrary = $dbi->get_library_publication($falsetriningid);
        self::assertEquals($traininglibrary->trainingid, $falsetrainingid);
        self::assertEquals($traininglibrary->originaltrainingid, $falsetriningid);
        self::assertEquals($traininglibrary->timecreated, $traininglibrary->timemodified);
        self::assertEquals($traininglibrary->userid, $falseuserid);

        // Create yesterday.
        $traininglibrary->timecreated = time() - 86400;
        $traininglibrary->timemodified = time() - 86400;
        $DB->update_record('library', $traininglibrary);

        $dbi->publish_to_library($falsetrainingid, $falsetriningid, $falseuserid);

        $traininglibrary2 = $dbi->get_library_publication($falsetriningid);
        self::assertEquals($traininglibrary->id, $traininglibrary2->id);
        self::assertEquals($traininglibrary2->trainingid, $falsetrainingid);
        self::assertEquals($traininglibrary2->originaltrainingid, $falsetriningid);
        self::assertEquals($traininglibrary->timecreated, $traininglibrary2->timecreated);
        self::assertTrue(intval($traininglibrary2->timecreated) < intval($traininglibrary2->timemodified));
        self::assertEquals($traininglibrary2->userid, $falseuserid);

        self::resetAllData();
    }

    /**
     * Test get_recyclebin_category_item
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_recyclebin_category_item
     */
    public function test_get_recyclebin_category_item() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entityid = $this->init_create_entity();
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);

        $dbi = \local_mentor_core\database_interface::get_instance();
        self::assertFalse($dbi->get_recyclebin_category_item('trainingshortname'));

        $training->delete();

        self::assertIsObject($dbi->get_recyclebin_category_item('trainingshortname'));

        self::resetAllData();
    }

    /**
     * Test has_enrol_by_instance_id
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::has_enrol_by_instance_id
     */
    public function test_has_enrol_by_instance_id() {
        global $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();
        $entityid = $this->init_create_entity();
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);

        $training->create_manual_enrolment_instance();
        $instance = $training->get_enrolment_instances_by_type('manual');

        self::assertFalse($dbi->has_enrol_by_instance_id($instance->id, $USER->id));

        self::getDataGenerator()->enrol_user($USER->id, $training->get_course()->id);

        self::assertTrue($dbi->has_enrol_by_instance_id($instance->id, $USER->id));

        self::resetAllData();
    }

    /**
     * Test update_secondary_entities_name
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::update_secondary_entities_name
     */
    public function test_update_secondary_entities_name() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $entityid1 = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity1 = \local_mentor_core\entity_api::get_entity($entityid1);

        $entityid2 = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 2',
            'shortname' => 'New Entity 2',
        ]);
        $entity2 = \local_mentor_core\entity_api::get_entity($entityid2);

        $userdata = new stdClass();
        $userdata->lastname = 'lastname2';
        $userdata->firstname = 'firstname2';
        $userdata->email = 'test2@test.com';
        $userdata->username = 'testusername2';
        $userdata->password = 'to be generated';
        $userdata->mnethostid = 1;
        $userdata->confirmed = 1;
        $userdata->auth = 'manual';
        $userdata->profile_field_mainentity = $entity1->name;
        $userdata->profile_field_secondaryentities = [$entity2->name];
        $userid = \local_mentor_core\profile_api::create_user($userdata);

        $userinfofield = $DB->get_record('user_info_field', ['shortname' => 'secondaryentities']);
        $userinfodata = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $userinfofield->id]);

        self::assertEquals($userinfodata->data, $entity2->name);

        self::assertTrue($dbi->update_secondary_entities_name($entity2->name, $entity2->name . ' bis'));

        $userinfodata = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $userinfofield->id]);

        self::assertEquals($userinfodata->data, $entity2->name . ' bis');

        self::resetAllData();
    }

    /**
     * Test update_secondary_entities_name not ok
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::update_secondary_entities_name
     */
    public function test_update_secondary_entities_name_nok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();

        $DB->delete_records('user_info_field', ['shortname' => 'secondaryentities']);

        self::assertFalse($dbi->update_secondary_entities_name('falseentity', 'falseentitybis'));

        self::resetAllData();
    }

    /**
     * Test get_course_tutors
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_course_tutors
     */
    public function test_get_course_tutors() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();
        $course = self::getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $user = self::getDataGenerator()->create_user();

        self::assertEmpty($dbi->get_course_tutors($context->id));

        self::getDataGenerator()->enrol_user($user->id, $course->id, \local_mentor_specialization\mentor_profile::ROLE_TUTEUR);

        $tutors = $dbi->get_course_tutors($context->id);

        self::assertCount(1, $tutors);
        self::assertArrayHasKey($user->id, $tutors);

        self::resetAllData();
    }

    /**
     * Test get_course_formateurs
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_course_formateurs
     */
    public function test_get_course_formateurs() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();
        $course = self::getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $user = self::getDataGenerator()->create_user();

        self::assertEmpty($dbi->get_course_formateurs($context->id));

        self::getDataGenerator()->enrol_user($user->id, $course->id, \local_mentor_specialization\mentor_profile::ROLE_FORMATEUR);

        $formateurs = $dbi->get_course_formateurs($context->id);

        self::assertCount(1, $formateurs);
        self::assertArrayHasKey($user->id, $formateurs);

        self::resetAllData();
    }

    /**
     * Test get_course_demonstrateurs
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_course_demonstrateurs
     */
    public function test_get_course_demonstrateurs() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $dbi = \local_mentor_core\database_interface::get_instance();
        $course = self::getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $user = self::getDataGenerator()->create_user();

        self::assertEmpty($dbi->get_course_demonstrateurs($context->id));

        self::getDataGenerator()
            ->enrol_user($user->id, $course->id, \local_mentor_specialization\mentor_profile::ROLE_PARTICIPANTDEMONSTRATION);

        $demonstrateurs = $dbi->get_course_demonstrateurs($context->id);

        self::assertCount(1, $demonstrateurs);
        self::assertArrayHasKey($user->id, $demonstrateurs);

        self::resetAllData();
    }

    /**
     * Test is_block_present_to_course
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::is_block_present_to_course
     */
    public function test_is_block_present_to_course() {
        global $DB;

        $this->resetAfterTest(true);

        // Create course.
        $course = self::getDataGenerator()->create_course();

        // Get course context.
        $coursecontext = \context_course::instance($course->id);

        // Add false block to database.
        $DB->insert_record(
            'block_instances',
            [
                'blockname' => 'falseexistblock',
                'parentcontextid' => $coursecontext->id,
                'showinsubcontexts' => 0,
                'defaultweight' => 0,
                'pagetypepattern' => 'my-index',
                'subpagepattern' => 2,
                'timecreated' => time(),
                'timemodified' => time(),
            ]
        );

        $dbi = \local_mentor_core\database_interface::get_instance();
        self::assertTrue($dbi->is_block_present_to_course($course->id, 'falseexistblock'));
        self::assertFalse($dbi->is_block_present_to_course($course->id, 'falsenoexistblock'));

        self::resetAllData();
    }

    /**
     * Test get_users_by_secondaryentity
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_users_by_secondaryentity
     */
    public function test_get_users_by_secondaryentity() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();
        // Generates users.
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        //secondary entities
        self::init_create_entity('falseentity');
        self::init_create_entity('falseentity bis');
        self::init_create_entity('falsebisentity');

        // Get secondary entity field.
        $field = $DB->get_record('user_info_field', ['shortname' => 'secondaryentities']);

        // Set secondary entity to users.
        $DB->insert_record('user_info_data', [
            'userid' => $user1->id,
            'fieldid' => $field->id,
            'data' => 'falseentity',
            'dataformat' => 0,
        ]);
        $DB->insert_record('user_info_data', [
            'userid' => $user2->id,
            'fieldid' => $field->id,
            'data' => 'falseentity bis', // With same first user word to second entity.
            'dataformat' => 0,
        ]);
        $DB->insert_record('user_info_data', [
            'userid' => $user3->id,
            'fieldid' => $field->id,
            'data' => 'falsebisentity',
            'dataformat' => 0,
        ]);

        $db = \local_mentor_core\database_interface::get_instance();

        $users = $db->get_users_by_secondaryentity('falseentity');
        self::assertCount(1, $users);
        self::assertEquals(current($users)->id, $user1->id);

        $users = $db->get_users_by_secondaryentity('falsebisentity');

        self::assertCount(1, $users);
        self::assertEquals(current($users)->id, $user3->id);

        self::resetAllData();
    }

    /**
     * Test get_role_by_name
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_role_by_name
     */
    public function test_get_role_by_name() {
        global $DB;

        $this->resetAfterTest(true);

        $falseroleid = self::getDataGenerator()->create_role(['shortname' => 'falserole']);
        $falserole = $DB->get_record('role', ['id' => $falseroleid]);

        $db = \local_mentor_core\database_interface::get_instance();
        $roledata = $db->get_role_by_name('falserole');

        self::assertEquals($falserole->id, $roledata->id);
        self::assertEquals($falserole->name, $roledata->name);
        self::assertEquals($falserole->shortname, $roledata->shortname);
        self::assertEquals($falserole->description, $roledata->description);
        self::assertEquals($falserole->sortorder, $roledata->sortorder);
        self::assertEquals($falserole->archetype, $roledata->archetype);

        self::resetAllData();
    }

    /**
     * Test get_all_main_categories
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_all_main_categories
     */
    public function test_get_all_main_categories() {
        global $DB;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        $category1 = self::getDataGenerator()->create_category(['idnumber' => 'ABCD']);
        $category2 = self::getDataGenerator()->create_category(['idnumber' => 'BCDE']);
        $category3 = self::getDataGenerator()->create_category(['idnumber' => 'CDEF', 'parent' => $category2->id]);

        $db = \local_mentor_core\database_interface::get_instance();

        // Default argument.
        $maincategories = $db->get_all_main_categories(true);

        self::assertCount(2, $maincategories);
        self::assertArrayHasKey($category1->id, $maincategories);
        self::assertArrayHasKey($category2->id, $maincategories);
        self::assertArrayNotHasKey($category3->id, $maincategories);

        // Check order.
        $maincategoriesorder = array_values($maincategories);
        self::assertEquals($maincategoriesorder[0]->id, $category1->id);
        self::assertEquals($maincategoriesorder[1]->id, $category2->id);

        // Order DESC by shortname.
        $filter = new stdClass();
        $filter->order = ['column' => 'shortname', 'dir' => 'desc'];
        $maincategories = $db->get_all_main_categories(true, true, $filter);

        self::assertCount(2, $maincategories);
        self::assertArrayHasKey($category1->id, $maincategories);
        self::assertArrayHasKey($category2->id, $maincategories);
        self::assertArrayNotHasKey($category3->id, $maincategories);

        // Check order.
        $maincategoriesorder = array_values($maincategories);
        self::assertEquals($maincategoriesorder[0]->id, $category2->id);
        self::assertEquals($maincategoriesorder[1]->id, $category1->id);

        // Hidden second category.
        $DB->insert_record('category_options', ['categoryid' => $category2->id, 'name' => 'hidden', 'value' => '1']);

        // No hidden category.
        $maincategories = $db->get_all_main_categories(true, false);

        self::assertCount(1, $maincategories);
        self::assertArrayHasKey($category1->id, $maincategories);
        self::assertArrayNotHasKey($category2->id, $maincategories);
        self::assertArrayNotHasKey($category3->id, $maincategories);

        self::resetAllData();
    }

    /**
     * Test get_sub_entities
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_sub_entities
     */
    public function test_get_sub_entities() {
        global $DB;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        // Create entity with 2 sub entity.
        $category1 = self::getDataGenerator()->create_category();
        $category2 = self::getDataGenerator()->create_category(
            ['name' => \local_mentor_core\entity::SUB_ENTITY_CATEGORY, 'parent' => $category1->id]
        );
        $subcategory1 = self::getDataGenerator()->create_category(['parent' => $category2->id]);
        $subcategory2 = self::getDataGenerator()->create_category(['parent' => $category2->id]);

        $subcategories = $db->get_sub_entities($category1->id);
        self::assertCount(2, $subcategories);
        self::assertArrayHasKey($subcategory1->id, $subcategories);
        self::assertArrayHasKey($subcategory2->id, $subcategories);

        // Create entity without sub entity.
        $category3 = self::getDataGenerator()->create_category();

        $subcategories = $db->get_sub_entities($category3->id);
        self::assertCount(0, $subcategories);

        // Create entity with 1 entity.
        $category5 = self::getDataGenerator()->create_category();
        $category6 = self::getDataGenerator()->create_category(
            ['name' => \local_mentor_core\entity::SUB_ENTITY_CATEGORY, 'parent' => $category5->id]
        );
        $subcategory3 = self::getDataGenerator()->create_category(['parent' => $category6->id]);

        $subcategories = $db->get_sub_entities($category5->id);
        self::assertCount(1, $subcategories);
        self::assertArrayHasKey($subcategory3->id, $subcategories);

        self::resetAllData();
    }

    /**
     * Test get_cohort_by_context_id
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_cohort_by_context_id
     */
    public function test_get_cohort_by_context_id() {
        global $DB;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        $category = self::getDataGenerator()->create_category();
        $contextcategory = \context_coursecat::instance($category->id);

        self::assertFalse($db->get_cohort_by_context_id($contextcategory->id));

        $cohort = self::getDataGenerator()->create_cohort(['contextid' => $contextcategory->id]);

        $cohortbycontext = $db->get_cohort_by_context_id($contextcategory->id);
        self::assertEquals($cohortbycontext->id, $cohort->id);

        self::resetAllData();
    }

    /**
     * Test get_edadmin_courses_by_category
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_edadmin_courses_by_category
     */
    public function test_get_edadmin_courses_by_category() {
        global $DB;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        $category = self::getDataGenerator()->create_category();

        $course = self::getDataGenerator()->create_course(['category' => $category->id, 'format' => 'edadmin']);
        $formatoption1 = $DB->get_record('course_format_options', [
            'courseid' => $course->id,
            'format' => 'edadmin',
            'name' => 'formattype',
        ]);
        $formatoption1->value = 'format1';
        $DB->update_record('course_format_options', $formatoption1);

        $course2 = self::getDataGenerator()->create_course(['category' => $category->id, 'format' => 'edadmin']);
        $formatoption2 = $DB->get_record('course_format_options', [
            'courseid' => $course2->id,
            'format' => 'edadmin',
            'name' => 'formattype',
        ]);
        $formatoption2->value = 'format2';
        $DB->update_record('course_format_options', $formatoption2);

        $edadmincourses = $db->get_edadmin_courses_by_category($category->id);

        self::assertCount(2, $edadmincourses);

        self::assertArrayHasKey($course->id, $edadmincourses);
        self::assertEquals($edadmincourses[$course->id]->formattype, 'format1');

        self::assertArrayHasKey($course2->id, $edadmincourses);
        self::assertEquals($edadmincourses[$course2->id]->formattype, 'format2');

        self::resetAllData();
    }

    /**
     * Test course_exists_in_recyclebin
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::course_exists_in_recyclebin
     */
    public function test_course_exists_in_recyclebin() {
        global $DB;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        self::assertFalse($db->course_exists_in_recyclebin('falsecourse'));

        $DB->insert_record('tool_recyclebin_category', [
            'categoryid' => 50,
            'shortname' => 'falsecourse',
            'fullname' => 'falsecourse',
            'timecreated' => time(),
        ]);

        self::assertTrue($db->course_exists_in_recyclebin('falsecourse'));

        self::resetAllData();
    }

    /**
     * Test get_course_format_option
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_course_format_option
     */
    public function test_get_course_format_option() {
        global $DB;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        self::assertFalse($db->get_course_format_option(10, 'falseoption'));

        $DB->insert_record('course_format_options', [
            'courseid' => 10,
            'format' => 'falseformat',
            'sectionid' => 0,
            'name' => 'falseoption',
            'value' => 'falsevalue',
        ]);

        self::assertEquals(
            $db->get_course_format_option(10, 'falseoption'),
            'falsevalue'
        );

        self::resetAllData();
    }

    /**
     * Test get_course_backup
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_course_backup
     */
    public function test_get_course_backup() {
        global $DB, $USER;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        self::assertFalse($db->get_course_backup(10, 'local_mentor_core', 'falsebackup'));

        $fileid = $DB->insert_record('files', [
            'contexthash' => 111111111111,
            'pathnamehash' => 111111111111,
            'contextid' => 10,
            'component' => 'local_mentor_core',
            'filearea' => 'falsebackup',
            'itemid' => 10,
            'filename' => 'falsebackup',
            'filesize' => 100,
            'userid' => $USER->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $coursebackup = $db->get_course_backup(10, 'local_mentor_core', 'falsebackup');

        self::assertEquals($coursebackup->id, $fileid);

        self::resetAllData();
    }

    /**
     * Test training_has_sessions_in_recycle_bin
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::training_has_sessions_in_recycle_bin
     */
    public function test_training_has_sessions_in_recycle_bin() {
        global $DB;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        self::assertFalse($db->training_has_sessions_in_recycle_bin(1064));

        $DB->insert_record('session', [
            'courseshortname' => 'falsesession',
            'status' => 'falsestatus',
            'trainingid' => 1064,
        ]);
        $DB->insert_record('tool_recyclebin_category', [
            'categoryid' => 1,
            'shortname' => 'falsesession',
            'fullname' => 'falsesession',
            'timecreated' => time(),
        ]);

        self::assertTrue($db->training_has_sessions_in_recycle_bin(1064));

        self::resetAllData();
    }

    /**
     * Test get_next_sessionnumber_index
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_next_sessionnumber_index
     */
    public function test_get_next_sessionnumber_index() {
        global $DB;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        self::assertEquals($db->get_next_sessionnumber_index(1064), 1);

        $DB->insert_record('session', [
            'courseshortname' => 'falsesession',
            'status' => 'falsestatus',
            'trainingid' => 1064,
            'sessionnumber' => 10000,
        ]);

        self::assertEquals($db->get_next_sessionnumber_index(1064), 10001);

        self::resetAllData();
    }

    /**
     * Test get_tasks_adhoc
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_tasks_adhoc
     */
    public function test_get_tasks_adhoc() {
        global $DB, $USER;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        // Create task.
        $adhoctask = new \local_mentor_core\task\duplicate_training_task();
        $adhoctask->set_custom_data([
            'trainingid' => 1064,
            'trainingshortname' => 'falsetraining',
            'destinationentity' => 10,
        ]);
        $adhoctask->set_userid($USER->id);
        \core\task\manager::queue_adhoc_task($adhoctask);

        $tasks = $db->get_tasks_adhoc('\local_mentor_core\task\duplicate_training_task');
        self::assertCount(1, $tasks);
        self::assertEquals(current($tasks)->component, 'local_mentor_core');
        self::assertEquals(current($tasks)->classname, '\local_mentor_core\task\duplicate_training_task');
        self::assertEquals(current($tasks)->customdata,
            '{"trainingid":1064,"trainingshortname":"falsetraining","destinationentity":10}');

        $adhoctask = new \local_mentor_core\task\create_session_task();
        $adhoctask->set_custom_data([
            'trainingid' => 1064,
            'sessionname' => 'falsesession',
            'entityid' => 10,
        ]);
        $adhoctask->set_userid($USER->id);
        \core\task\manager::queue_adhoc_task($adhoctask);

        $tasks = $db->get_tasks_adhoc('\local_mentor_core\task\create_session_task');
        self::assertCount(1, $tasks);
        self::assertEquals(current($tasks)->component, 'local_mentor_core');
        self::assertEquals(current($tasks)->classname, '\local_mentor_core\task\create_session_task');
        self::assertEquals(current($tasks)->customdata, '{"trainingid":1064,"sessionname":"falsesession","entityid":10}');

        self::resetAllData();
    }

    /**
     * Test get_highest_role_by_user
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_highest_role_by_user
     */
    public function test_get_highest_role_by_user() {
        global $DB;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        // Get 2 roles.
        $roles = $DB->get_records('role', null, 'sortorder');
        $role1 = array_shift($roles);
        $role2 = array_shift($roles);

        // Create categories.
        $category1 = self::getDataGenerator()->create_category();
        $category2 = self::getDataGenerator()->create_category();

        // Create user.
        $user = self::getDataGenerator()->create_user();

        // Assign roles.
        $DB->insert_record('role_assignments', [
            'roleid' => $role1->id,
            'contextid' => context_coursecat::instance($category1->id)->id,
            'userid' => $user->id,
        ]);
        $DB->insert_record('role_assignments', [
            'roleid' => $role2->id,
            'contextid' => context_coursecat::instance($category2->id)->id,
            'userid' => $user->id,
        ]);

        $highestrole = $db->get_highest_role_by_user($user->id);

        self::assertEquals($highestrole->id, $role1->id);
        self::assertEquals($highestrole->name, $role1->name);
        self::assertEquals($highestrole->shortname, $role1->shortname);
        self::assertEquals($highestrole->description, $role1->description);
        self::assertEquals($highestrole->sortorder, $role1->sortorder);
        self::assertEquals($highestrole->archetype, $role1->archetype);

        // Unassign role.
        $DB->delete_records('role_assignments', [
            'roleid' => $role1->id,
            'contextid' => context_coursecat::instance($category1->id)->id,
            'userid' => $user->id,
        ]);

        $highestrole = $db->get_highest_role_by_user($user->id);

        self::assertEquals($highestrole->id, $role2->id);
        self::assertEquals($highestrole->name, $role2->name);
        self::assertEquals($highestrole->shortname, $role2->shortname);
        self::assertEquals($highestrole->description, $role2->description);
        self::assertEquals($highestrole->sortorder, $role2->sortorder);
        self::assertEquals($highestrole->archetype, $role2->archetype);

        self::resetAllData();
    }

    /**
     * Test set_profile_field_value
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::set_profile_field_value
     */
    public function test_set_profile_field_value() {
        global $DB;

        $this->resetAfterTest(true);

        // Remove all category.
        $DB->delete_records('course_categories');

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        // Create user.
        $user = self::getDataGenerator()->create_user();

        // Get field.
        $field = $DB->get_record('user_info_field', ['shortname' => 'region']);

        self::assertFalse($DB->record_exists('user_info_data', ['userid' => $user->id, 'fieldid' => $field->id]));

        $db->set_profile_field_value($user->id, 'region', 'falseregion');

        $fielddata = $DB->get_record('user_info_data', ['userid' => $user->id, 'fieldid' => $field->id]);
        self::assertEquals($fielddata->data, 'falseregion');

        $db->set_profile_field_value($user->id, 'region', 'falseregionbis');

        $fielddatabis = $DB->get_record('user_info_data', ['userid' => $user->id, 'fieldid' => $field->id]);
        // Same id.
        self::assertEquals($fielddatabis->id, $fielddata->id);
        self::assertEquals($fielddatabis->data, 'falseregionbis');

        self::resetAllData();
    }

    /**
     * Test remove_user_owner_h5p_file
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::remove_user_owner_h5p_file
     */
    public function test_remove_user_owner_h5p_file() {
        global $DB;

        $this->resetAfterTest(true);

        // Get database interface.
        $db = \local_mentor_core\database_interface::get_instance();

        // Create course.
        $course = self::getDataGenerator()->create_course();
        $context = context_course::instance($course->id);

        // Create user.
        $user = self::getDataGenerator()->create_user();

        $DB->insert_record('files', [
            'contexthash' => 111111111111,
            'pathnamehash' => 111111111111,
            'contextid' => $context->id,
            'component' => 'local_mentor_core',
            'filearea' => 'falsebackup',
            'itemid' => 10,
            'filename' => 'falsebackup',
            'filesize' => 100,
            'userid' => $user->id,
            'mimetype' => 'application/zip.h5p',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        self::assertTrue($DB->record_exists(
            'files',
            ['contextid' => $context->id, 'mimetype' => 'application/zip.h5p', 'userid' => $user->id])
        );
        self::assertFalse($DB->record_exists(
            'files',
            ['contextid' => $context->id, 'mimetype' => 'application/zip.h5p', 'userid' => null])
        );

        $db->remove_user_owner_h5p_file($context->id);

        self::assertFalse($DB->record_exists(
            'files',
            ['contextid' => $context->id, 'mimetype' => 'application/zip.h5p', 'userid' => $user->id]
        ));
        self::assertTrue($DB->record_exists(
            'files',
            ['contextid' => $context->id, 'mimetype' => 'application/zip.h5p', 'userid' => null])
        );

        self::resetAllData();
    }

    /**
     * Test unpublish_to_library
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::unpublish_to_library
     */
    public function test_unpublish_to_library() {
        global $DB, $USER;

        $this->resetAfterTest(true);

        $originaltrainingid = 10;

        $DB->delete_records('library');

        self::assertFalse($DB->get_record('library', ['originaltrainingid' => $originaltrainingid]));

        $publishedlibrary = new \stdClass();
        $publishedlibrary->trainingid = 100;
        $publishedlibrary->originaltrainingid = $originaltrainingid;
        $publishedlibrary->timecreated = time();
        $publishedlibrary->timemodified = time();
        $publishedlibrary->userid = $USER->id;

        $DB->insert_record('library', $publishedlibrary);

        self::assertNotFalse($DB->get_record('library', ['originaltrainingid' => $originaltrainingid]));

        $dbi = \local_mentor_core\database_interface::get_instance();
        $dbi->unpublish_to_library($originaltrainingid);

        self::assertFalse($DB->get_record('library', ['originaltrainingid' => $originaltrainingid]));

        self::resetAllData();
    }

    /**
     * Test get_library_task_by_training_id
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_library_task_by_training_id
     */
    public function test_get_library_task_by_training_id() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $DB->delete_records('task_adhoc');

        $dbi = \local_mentor_core\database_interface::get_instance();
        $trainingid = 10;

        self::assertEmpty($dbi->get_library_task_by_training_id($trainingid));

        // Test with publication_library_task.
        $taskadhoc = new \stdClass();
        $taskadhoc->component = 'component';
        $taskadhoc->classname = '\\local_library\\task\\publication_library_task';
        $taskadhoc->nextruntime = time() + 86400;
        $taskadhoc->customdata = '{"trainingid":' . $trainingid . '}';
        $taskadhoc->userid = $USER->id;
        $taskadhoc->timecreated = time();
        $taskid = $DB->insert_record('task_adhoc', $taskadhoc);

        $taskdata = $dbi->get_library_task_by_training_id($trainingid);
        self::assertCount(1, $taskdata);
        self::assertArrayHasKey($taskid, $taskdata);

        // Test with depublication_library_task.
        $DB->delete_records('task_adhoc');
        self::assertEmpty($dbi->get_library_task_by_training_id($trainingid));

        $taskadhoc = new \stdClass();
        $taskadhoc->component = 'component';
        $taskadhoc->classname = '\\local_library\\task\\depublication_library_task';
        $taskadhoc->nextruntime = time() + 86400;
        $taskadhoc->customdata = '{"trainingid":' . $trainingid . '}';
        $taskadhoc->userid = $USER->id;
        $taskadhoc->timecreated = time();
        $taskid = $DB->insert_record('task_adhoc', $taskadhoc);

        $taskdata = $dbi->get_library_task_by_training_id($trainingid);
        self::assertCount(1, $taskdata);
        self::assertArrayHasKey($taskid, $taskdata);

        self::resetAllData();
    }

    /**
     * Test get_trainings_file
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_trainings_file
     */
    public function test_get_trainings_file() {
        global $DB;

        $this->resetAfterTest(true);

        $DB->delete_records('files');

        $filearea = 'file';
        $component = 'local_trainings';
        $filename = 'filename';
        $dbi = \local_mentor_core\database_interface::get_instance();

        self::assertEmpty($dbi->get_trainings_file($filearea));

        $filedata = new \stdClass();
        $filedata->contenthash = sha1('contenthash');
        $filedata->pathnamehash = '\path\\';
        $filedata->contextid = '10';
        $filedata->component = $component;
        $filedata->filearea = $filearea;
        $filedata->itemid = 100;
        $filedata->filename = $filename;
        $filedata->filesize = 10000;
        $filedata->status = 0;
        $filedata->timecreated = time();
        $filedata->timemodified = time();
        $filedata->sortoerder = 1;
        $DB->insert_record('files', $filedata);

        $files = $dbi->get_trainings_file($filearea);
        self::assertCount(1, $files);
        self::assertEquals(current($files)->filearea, $filearea);

        self::resetAllData();
    }

    /**
     * Test get_courses_with_role
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_courses_with_role
     */
    public function test_get_courses_with_role() {
        global $DB;

        $this->resetAfterTest(true);

        $DB->delete_records('files');

        $dbi = \local_mentor_core\database_interface::get_instance();
        $user = self::getDataGenerator()->create_user();
        $course = self::getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $courserole = $DB->get_records_sql('
            SELECT r.*
            FROM {role} r
            JOIN {role_context_levels} rl ON rl.roleid = r.id
            WHERE rl.contextlevel = :contextlevel
        ', ['contextlevel' => CONTEXT_COURSE]);

        foreach ($courserole as $role) {
            self::assertEmpty($dbi->get_courses_with_role($user->id, $role->shortname));

            self::getDataGenerator()->role_assign($role->id, $user->id, $coursecontext->id);

            self::assertCount(1, $dbi->get_courses_with_role($user->id, $role->shortname));
        }

        self::resetAllData();
    }

    /**
     * Test get_context_with_capability
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_context_with_capability
     */
    public function test_get_context_with_capability() {
        global $DB;

        $this->resetAfterTest(true);

        $dbi = \local_mentor_core\database_interface::get_instance();
        $user = self::getDataGenerator()->create_user();

        // Course context.
        $course = self::getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $coursecapability = 'moodle/course:update';
        $rolewithcapability = $DB->get_record('role', ['shortname' => 'editingteacher']);

        self::assertEmpty($dbi->get_context_with_capability(CONTEXT_COURSE, $user->id, $coursecapability));

        self::getDataGenerator()->role_assign($rolewithcapability->id, $user->id, $coursecontext->id);
        self::assertCount(1, $dbi->get_context_with_capability(CONTEXT_COURSE, $user->id, $coursecapability));

        // Category context.
        $cat = self::getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($cat->id);
        $catcontextcapability = 'moodle/course:update';
        $rolewithcapability = $DB->get_record('role', ['shortname' => 'admindedie']);

        self::assertEmpty($dbi->get_context_with_capability(CONTEXT_COURSECAT, $user->id, $catcontextcapability));

        self::getDataGenerator()->role_assign($rolewithcapability->id, $user->id, $catcontext->id);
        self::assertCount(1, $dbi->get_context_with_capability(CONTEXT_COURSECAT, $user->id, $catcontextcapability));

        self::resetAllData();
    }

    /**
     * Test get_categories_with_capability
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_categories_with_capability
     */
    public function test_get_categories_with_capability() {
        global $DB;

        $this->resetAfterTest(true);

        $dbi = \local_mentor_core\database_interface::get_instance();
        $user = self::getDataGenerator()->create_user();

        $cat = self::getDataGenerator()->create_category();
        $catcontext = \context_coursecat::instance($cat->id);
        $catcontextcapability = 'moodle/course:update';
        $rolewithcapability = $DB->get_record('role', ['shortname' => 'admindedie']);

        self::assertEmpty($dbi->get_categories_with_capability($user->id, $catcontextcapability));

        self::getDataGenerator()->role_assign($rolewithcapability->id, $user->id, $catcontext->id);
        self::assertCount(1, $dbi->get_categories_with_capability($user->id, $catcontextcapability));

        self::resetAllData();
    }

    /**
     * Test get_course_with_capability
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_course_with_capability
     */
    public function test_get_course_with_capability() {
        global $DB;

        $this->resetAfterTest(true);

        $dbi = \local_mentor_core\database_interface::get_instance();
        $user = self::getDataGenerator()->create_user();

        $course = self::getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $coursecapability = 'moodle/course:update';
        $rolewithcapability = $DB->get_record('role', ['shortname' => 'editingteacher']);

        self::assertEmpty($dbi->get_course_with_capability($user->id, $coursecapability));

        self::getDataGenerator()->role_assign($rolewithcapability->id, $user->id, $coursecontext->id);
        self::assertCount(1, $dbi->get_course_with_capability($user->id, $coursecapability));

        self::resetAllData();
    }

    /**
     * Test get_user_course_completion
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_user_course_completion
     */
    public function test_get_user_course_completion() {
        global $DB;

        $this->resetAfterTest(true);

        $DB->delete_records('user_completion');

        $dbi = \local_mentor_core\database_interface::get_instance();
        $userid = 10;
        $courseid = 20;

        self::assertFalse($dbi->get_user_course_completion($userid, $courseid));

        $usercompletion = new \stdClass();
        $usercompletion->userid = $userid;
        $usercompletion->courseid = $courseid;
        $usercompletion->completion = 100;
        $usercompletion->lastupdate = time();
        $DB->insert_record('user_completion', $usercompletion);

        self::assertIsObject($dbi->get_user_course_completion($userid, $courseid));

        self::resetAllData();
    }

    /**
     * Test get_never_logged_user_for_giver_day
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_never_logged_user_for_giver_day
     */
    public function test_get_never_logged_user_for_giver_day() {
        global $DB;

        $this->resetAfterTest(true);
        $DB->delete_records('user');

        $now = time();
        $daylimit = $now - (86400 * 10);
        $lastdaylimit = $daylimit - (86400 * 5);

        $user = self::getDataGenerator()->create_user();
        $dbi = \local_mentor_core\database_interface::get_instance();

        self::assertEmpty($dbi->get_never_logged_user_for_giver_day($daylimit, $lastdaylimit));

        // Never logged under date.
        $user->timecreated = $daylimit - 1000;
        $DB->update_record('user', $user);

        $usersneverlogged = $dbi->get_never_logged_user_for_giver_day($daylimit, $lastdaylimit);
        self::assertCount(1, $usersneverlogged);
        self::assertArrayHasKey($user->id, $usersneverlogged);

        // Never loffde outside data.
        $user->timecreated = $lastdaylimit - 1000;
        $DB->update_record('user', $user);

        self::assertEmpty($dbi->get_never_logged_user_for_giver_day($daylimit, $lastdaylimit));

        // No limit date.
        $usersneverlogged = $dbi->get_never_logged_user_for_giver_day($daylimit);
        self::assertCount(1, $usersneverlogged);
        self::assertArrayHasKey($user->id, $usersneverlogged);

        // Is suspended.
        $user->suspended = 1;
        $DB->update_record('user', $user);
        self::assertEmpty($dbi->get_never_logged_user_for_giver_day($daylimit, $lastdaylimit));

        // Is deleted.
        $user->suspended = 0;
        $user->deleted = 1;
        $DB->update_record('user', $user);
        self::assertEmpty($dbi->get_never_logged_user_for_giver_day($daylimit, $lastdaylimit));

        self::resetAllData();
    }

    /**
     * Test get_not_logged_user_for_giver_day
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_not_logged_user_for_giver_day
     */
    public function test_get_not_logged_user_for_giver_day() {
        global $DB;

        $this->resetAfterTest(true);
        $DB->delete_records('user');

        $now = time();
        $daylimit = $now - (86400 * 10);
        $lastdaylimit = $daylimit - (86400 * 5);

        $user = self::getDataGenerator()->create_user();
        $dbi = \local_mentor_core\database_interface::get_instance();

        self::assertEmpty($dbi->get_not_logged_user_for_giver_day($daylimit, $lastdaylimit));

        // Never logged under date.
        $user->lastaccess = $daylimit - 1000;
        $user->timemodified = $daylimit - 1000;
        $DB->update_record('user', $user);

        $usersneverlogged = $dbi->get_not_logged_user_for_giver_day($daylimit, $lastdaylimit);
        self::assertCount(1, $usersneverlogged);
        self::assertArrayHasKey($user->id, $usersneverlogged);

        // Never loffde outside data.
        $user->lastaccess = $lastdaylimit - 1000;
        $user->timemodified = $lastdaylimit - 1000;
        $DB->update_record('user', $user);

        self::assertEmpty($dbi->get_not_logged_user_for_giver_day($daylimit, $lastdaylimit));

        // No limit date.
        $usersneverlogged = $dbi->get_not_logged_user_for_giver_day($daylimit);
        self::assertCount(1, $usersneverlogged);
        self::assertArrayHasKey($user->id, $usersneverlogged);

        // Is suspended.
        $user->suspended = 1;
        $DB->update_record('user', $user);
        self::assertEmpty($dbi->get_not_logged_user_for_giver_day($daylimit, $lastdaylimit));

        // Is deleted.
        $user->suspended = 0;
        $user->deleted = 1;
        $DB->update_record('user', $user);
        self::assertEmpty($dbi->get_not_logged_user_for_giver_day($daylimit, $lastdaylimit));

        self::resetAllData();
    }

    /**
     * Test get_user_suspended_for_days_given
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_user_suspended_for_days_given
     */
    public function test_get_user_suspended_for_days_given() {
        global $DB;

        $this->resetAfterTest(true);
        $DB->delete_records('user');

        $now = time();
        $daylimit = $now - (86400 * 10);

        $user = self::getDataGenerator()->create_user();
        $dbi = \local_mentor_core\database_interface::get_instance();

        self::assertEmpty($dbi->get_user_suspended_for_days_given($daylimit));

        // No suspended and before limit.
        $user->timemodified = $daylimit - 1000;
        $DB->update_record('user', $user);
        self::assertEmpty($dbi->get_user_suspended_for_days_given($daylimit));

        // Before limit and suspended.
        $user->suspended = 1;
        $DB->update_record('user', $user);
        $usersneverlogged = $dbi->get_user_suspended_for_days_given($daylimit);
        self::assertCount(1, $usersneverlogged);
        self::assertArrayHasKey($user->id, $usersneverlogged);

        self::resetAllData();
    }

    /**
     * Test get_recall_users
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::get_recall_users
     */
    public function test_get_recall_users() {
        global $DB;

        $this->resetAfterTest(true);
        $DB->delete_records('user_recall');

        $dbi = \local_mentor_core\database_interface::get_instance();
        $userid = 10;
        $recalname = 'recalname';

        self::assertEmpty($dbi->get_recall_users($recalname));

        $userecall = new \stdClass();
        $userecall->userid = $userid;
        $userecall->recallname = $recalname;
        $userecall->timecreated = time();
        $DB->insert_record('user_recall', $userecall);

        $usersrecall = $dbi->get_recall_users($recalname);
        self::assertCount(1, $usersrecall);
        self::assertEquals(current($usersrecall)->userid, $userid);

        self::resetAllData();
    }

    /**
     * Test insert_recall_users
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::insert_recall_users
     */
    public function test_insert_recall_users() {
        global $DB;

        $this->resetAfterTest(true);
        $DB->delete_records('user_recall');

        $dbi = \local_mentor_core\database_interface::get_instance();
        $userid1 = 10;
        $userid2 = 11;
        $recalname = 'recalname';

        self::assertEmpty($DB->get_records('user_recall'));

        $dbi->insert_recall_users([$userid1, $userid2], $recalname);

        $usersrecall = $DB->get_records('user_recall', null, '', 'userid');
        self::assertCount(2, $usersrecall);
        self::assertArrayHasKey($userid1, $usersrecall);
        self::assertArrayHasKey($userid2, $usersrecall);

        self::resetAllData();
    }

    /**
     * Test delete_recall_user
     *
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\database_interface::delete_recall_user
     */
    public function test_delete_recall_user() {
        global $DB;

        $this->resetAfterTest(true);
        $DB->delete_records('user_recall');

        $dbi = \local_mentor_core\database_interface::get_instance();
        $userid = 10;
        $recalname1 = 'recalname1';
        $recalname2 = 'recalname2';

        self::assertEmpty($DB->get_records('user_recall'));

        $userecall = new \stdClass();
        $userecall->userid = $userid;
        $userecall->recallname = $recalname1;
        $userecall->timecreated = time();
        $DB->insert_record('user_recall', $userecall);

        $userecall = new \stdClass();
        $userecall->userid = $userid;
        $userecall->recallname = $recalname2;
        $userecall->timecreated = time();
        $DB->insert_record('user_recall', $userecall);

        $usersrecall = $DB->get_records('user_recall', null, '', 'recallname');
        self::assertCount(2, $usersrecall);
        self::assertArrayHasKey($recalname1, $usersrecall);
        self::assertArrayHasKey($recalname2, $usersrecall);

        $dbi->delete_recall_user($userid);

        self::assertEmpty($DB->get_records('user_recall'));

        self::resetAllData();
    }

/**
     * Test get user by username
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @covers \local_mentor_core\database_interface::get_user_by_username
     */
    public function test_get_secondaryentity_names_array() {
        $this->resetAfterTest(true);
        $dbinterface = \local_mentor_core\database_interface::get_instance();
        
        self::setAdminUser();

        self::init_create_entity(self::ENTITY_NAMES[1]);
        self::init_create_entity(self::ENTITY_NAMES[2]);

        $result = $dbinterface->get_secondaryentity_names_array('');
        self::assertCount(0, $result);

        $result = $dbinterface->get_secondaryentity_names_array(self::ENTITY_NAMES[1] . ', '. self::ENTITY_NAMES[2]);

        self::assertContains(self::ENTITY_NAMES[1], $result);
        self::assertContains(self::ENTITY_NAMES[2], $result);
        self::assertCount(2, $result);

        self::resetAllData();
    }

}
