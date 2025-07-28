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
 * Test cases for profile API
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/profile.php');

class local_mentor_core_profile_testcase extends advanced_testcase {

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

        // Create the manager role if it doesn't exist.
        if (!$manager) {
            $otherrole = $DB->get_record('role', ['archetype' => 'manager'], '*', IGNORE_MULTIPLE);
            $this->duplicate_role($otherrole->shortname, 'manager', 'Manager',
                'manager');
        }
    }

    /**
     * Init entities data
     *
     * @return array
     */
    public function get_entities_data() {

        return [
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
            'userid' => 2,  // Set the admin user as manager of the entity.
        ];
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
     * @return mixed
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
     * @return \local_mentor_core\session
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
     * Test get profile
     *
     * @covers \local_mentor_core\profile_api::get_profile
     */
    public function test_get_profile() {
        $this->resetAfterTest(true);

        $profile = \local_mentor_core\profile_api::get_profile(2);
        self::assertEquals(2, $profile->id);
        self::assertEquals('admin', $profile->username);

        $this->resetAllData();
    }

    /**
     * Test for user search
     *
     * @covers \local_mentor_core\profile_api::search_users
     */
    public function test_search_users() {

        $this->resetAfterTest(true);

        try {
            $result = \local_mentor_core\profile_api::search_users('admin');
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $this->assertCount(1, $result);
        $this->assertEquals('Admin', $result[0]->firstname);

        $this->resetAllData();
    }

    /**
     * Test for role assign ok
     *
     * @covers \local_mentor_core\profile_api::role_assign
     */
    public function test_role_assign_ok() {

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $db = \local_mentor_core\database_interface::get_instance();

        try {
            $profile = \local_mentor_core\profile_api::search_users('admin');
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $context = context_coursecat::instance(1);

        $role = $db->get_role_by_name('manager');

        // Add admin as manager.
        try {
            $result = \local_mentor_core\profile_api::role_assign($role->shortname, $profile[0]->id, $context->id);
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $managers = array_values(get_role_users($role->id, $context));

        // Check if is just one manage.
        $this->assertCount(1, $managers);

        // Check if Admin is manager.
        $this->assertEquals($profile[0]->id, $managers[0]->id);

        $this->resetAllData();
    }

    /**
     * Test for role assignnot ok
     *
     * @covers \local_mentor_core\profile_api::role_assign
     */
    public function test_role_assign_nok() {

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        try {
            $profile = \local_mentor_core\profile_api::search_users('admin');
        } catch (\Exception $e) {
            $this->fail($e->getMessage());
        }

        $context = context_coursecat::instance(1);

        $rolename = 'dontexist';

        // Add admin as manager.
        try {
            $result = \local_mentor_core\profile_api::role_assign($rolename, $profile[0]->id, $context->id);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        $this->resetAllData();
    }

    /**
     * Test get user main entity
     *
     * @covers \local_mentor_core\profile_api::get_user_main_entity
     */
    public function test_get_user_main_entity() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $defaultcategory = \local_mentor_specialization\mentor_entity::get_default_entity();
        $entityid = $defaultcategory->id;
        $userid = $this->init_create_user();

        self::setUser($userid);

        $mainentity = \local_mentor_core\profile_api::get_user_main_entity();

        self::assertEquals($entityid, $mainentity->id);

        $this->resetAllData();
    }

    /**
     * Test get user template
     *
     * @covers \local_mentor_core\profile_api::get_user_template
     */
    public function test_get_user_template() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $defaultusertemplate = 'local_user/local_user';

        $usertemplate = \local_mentor_core\profile_api::get_user_template($defaultusertemplate);
        self::assertEquals($defaultusertemplate, $usertemplate);

        $this->resetAllData();
    }

    /**
     * Test get user javascript
     *
     * @covers \local_mentor_core\profile_api::get_user_javascript
     */
    public function test_get_user_javascript() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $defalultuserjavascript = 'local_user/local_user';

        $userjavascript = \local_mentor_core\profile_api::get_user_javascript($defalultuserjavascript);
        self::assertEquals($defalultuserjavascript, $userjavascript);

        $this->resetAllData();
    }

    /**
     * Test get_users_by_mainentity
     *
     * @covers \local_mentor_core\profile_api::get_users_by_mainentity
     */
    public function test_get_users_by_mainentity() {

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();

        self::setAdminUser();
        $defaultcategory = \local_mentor_specialization\mentor_entity::get_default_entity();
        $entityname = $defaultcategory->name;

        // Create user.
        $userid = self::init_create_user();

        // Check entity is mainentity for user.
        $resultrequest = \local_mentor_core\profile_api::get_users_by_mainentity($entityname);
        self::assertCount(1, $resultrequest);
        self::assertEquals($userid, current($resultrequest)->id);

        self::resetAllData();
    }

    /**
     * Test get_user_logo
     *
     * @covers \local_mentor_core\profile_api::get_user_logo
     */
    public function test_get_user_logo() {
        global $CFG;

        $this->resetAfterTest(true);
        self::setAdminUser();
        $this->reset_singletons();

        self::setAdminUser();

        // User not loggedin.
        $result = \local_mentor_core\profile_api::get_user_logo(null);
        self::assertFalse($result);

        // New user without main entity.
        $newuser = self::getDataGenerator()->create_user();
        $result = \local_mentor_core\profile_api::get_user_logo($newuser->id);
        self::assertFalse($result);

        // Create a user with a main entity.
        $entity = \local_mentor_specialization\mentor_entity::get_default_entity();
        $entityid = $entity->id;
        $userid = self::init_create_user();
        $result = \local_mentor_core\profile_api::get_user_logo($userid);

        // The entity has no logo.
        self::assertFalse($result);

        // Set an entity logo.
        $fs = get_file_storage();

        $entity = \local_mentor_core\entity_api::get_entity($entityid);
        $filerecord = [
            'component' => 'local_entities',
            'filearea' => 'logo',
            'contextid' => $entity->get_context()->id,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'logo.png',
        ];

        $fs->create_file_from_pathname($filerecord, $CFG->dirroot . '/local/mentor_core/pix/logo.png');
        $result = \local_mentor_core\profile_api::get_user_logo($userid);

        self::assertInstanceOf('stored_file', $result);

        self::resetAllData();
    }

    /**
     * Test get user template params
     *
     * @covers \local_mentor_core\profile_api::get_user_template_params
     */
    public function test_get_user_template_params() {
        global $PAGE;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        // Create course.
        $course = self::getDataGenerator()->create_course();

        // Set PAGE.
        $PAGE->set_course($course);

        $templateparams = \local_mentor_core\profile_api::get_user_template_params();
        self::assertIsArray($templateparams);
        self::assertEmpty($templateparams);

        $this->resetAllData();
    }

    /**
     * Test get user manager role name
     *
     * @covers \local_mentor_core\profile_api::get_user_manager_role_name
     */
    public function test_get_user_manager_role_name() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        $templateparams = \local_mentor_core\profile_api::get_user_manager_role_name();
        self::assertEquals('manager', $templateparams);

        $this->resetAllData();
    }

    /**
     * Test create and add user
     *
     * @covers \local_mentor_core\profile_api::create_and_add_user
     * @covers \local_mentor_core\profile_api::create_user
     * @covers \local_mentor_core\database_interface::get_instance
     * @covers \local_mentor_core\entity_api::get_entity
     * @covers \local_mentor_core\entity_api::get_entity_by_name
     */
    public function test_create_and_add_user_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        global $DB;

        set_config('allowemailaddresses', 'gouv.fr');

        $entityid = \local_mentor_core\entity_api::create_entity(['name' => 'New Entity 1', 'shortname' => 'New Entity 1']);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);
        $domain = (object) [
            'course_categories_id' => $entityid,
            'domain_name' => 'gouv.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $DB->insert_record('course_categories_domains', $domain, false);

        $region = 'Corse';
        $auth = 'manual';

        // Whith entity id.
        $lastname = "user1";
        $firstname = "user1";
        $email = "user1@gouv.fr";

        self::assertCount(0, $entity->get_members());
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, $entityid, $region,
            $auth));
        self::assertCount(1, $entity->get_members());

        // Whith entity object.
        $lastname = "user2";
        $firstname = "user2";
        $email = "user2@gouv.fr";

        self::assertCount(1, $entity->get_members());
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, $entity, $region,
            $auth));
        self::assertCount(2, $entity->get_members());

        // Whith entity name.
        $lastname = "user3";
        $firstname = "user3";
        $email = "user3@gouv.fr";
        $entityname = $entity->get_name();

        self::assertCount(2, $entity->get_members());
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, $entityname,
            $region,
            $auth));
        self::assertCount(3, $entity->get_members());

        $this->resetAllData();
    }
     /**
      * Test create and add user
      * With secondary entity
      *
      * @covers \local_mentor_core\profile_api::create_and_add_user
      * @covers \local_mentor_core\profile_api::create_user
      * @covers \local_mentor_core\database_interface::get_instance
      * @covers \local_mentor_core\entity_api::get_entity
      * @covers \local_mentor_core\entity_api::get_entity_by_name
      */
    public function test_create_and_add_user_ok_secondary_entity() {
        global $CFG ,$DB;
        
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $CFG->allowemailaddresses = '.archi.fr .interieur.gouv.fr ira-nantes.fr';

        $entityid = \local_mentor_core\entity_api::create_entity(['name' => 'New Entity 1', 'shortname' => 'New Entity 1']);

        $activeDomain1 = (object) [
            'course_categories_id' => $entityid,
            'domain_name' => '.interieur.gouv.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];       

        $DB->insert_record('course_categories_domains', $activeDomain1, false);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);
        $region = 'Corse';
        $auth = 'manual';

        // Whith entity id.
        $lastname = "user1";
        $firstname = "user1";
        $email = "user1@g.interieur.gouv.fr";


        self::assertCount(0, $entity->get_members());
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, $entityid,
            $region,
            $auth));
        self::assertCount(1, $entity->get_members());

        // Whith entity object.
        $lastname = "user2";
        $firstname = "user2";
        $email = "user2@gouv.fr";

        self::assertCount(1, $entity->get_members());
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, $entityid,
            $region,
            $auth));
        self::assertCount(2, $entity->get_members());

        // Whith entity name.
        $lastname = "user3";
        $firstname = "user3";
        $email = "user3@gouv.fr";

        self::assertCount(2, $entity->get_members());
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, $entityid,
            $region,
            $auth));
        self::assertCount(3, $entity->get_members());

        $this->resetAllData();
    }

    /**
     * Test create and add user with existed user
     *
     * @covers \local_mentor_core\profile_api::create_and_add_user
     * @covers \local_mentor_core\profile_api::create_user
     * @covers \local_mentor_core\database_interface::get_instance
     */
    public function test_create_and_add_user_exist_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entityid = \local_mentor_core\entity_api::create_entity(['name' => 'New Entity 1', 'shortname' => 'New Entity 1']);
        $region = 'Corse';
        $auth = 'manual';
        $lastname = "user1";
        $firstname = "user1";
        $email = "user1@gouv.fr";

        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, $entityid, $region,
            $auth));

        // User exist.
        self::assertEquals(\local_mentor_core\profile_api::EMAIL_USED, \local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, $entityid, $region, $auth));

        $this->resetAllData();
    }

    /**
     * Test create and add user with not allowed mail
     *
     * @covers \local_mentor_core\profile_api::create_and_add_user
     * @covers \local_mentor_core\profile_api::create_user
     * @covers \local_mentor_core\database_interface::get_instance
     */
    public function test_create_and_add_not_allowed_email_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        set_config('allowemailaddresses',
            'agriculture.gouv.fr');

        $entityid = \local_mentor_core\entity_api::create_entity(['name' => 'New Entity 1', 'shortname' => 'New Entity 1']);
        $region = 'Corse';
        $auth = 'manual';
        $lastname = "user1";
        $firstname = "user1";
        $email = "user1.gouv.fr.fr";

        self::assertTrue(\local_mentor_core\profile_api::EMAIL_NOT_ALLOWED === \local_mentor_core\profile_api::create_and_add_user
            ($lastname, $firstname,
                $email, $entityid,
                $region,
                $auth));

        $this->resetAllData();
    }

    /**
     * Test get highest role by user
     *
     * @covers \local_mentor_core\profile_api::get_highest_role_by_user
     * @covers \local_mentor_core\profile_api::get_profile
     * @covers \local_mentor_core\profile::get_highest_role
     */
    public function test_get_highest_role_by_user_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

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

        $highestrole = \local_mentor_core\profile_api::get_highest_role_by_user($user->id);

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

        $highestrole = \local_mentor_core\profile_api::get_highest_role_by_user($user->id);

        self::assertEquals($highestrole->id, $role2->id);
        self::assertEquals($highestrole->name, $role2->name);
        self::assertEquals($highestrole->shortname, $role2->shortname);
        self::assertEquals($highestrole->description, $role2->description);
        self::assertEquals($highestrole->sortorder, $role2->sortorder);
        self::assertEquals($highestrole->archetype, $role2->archetype);

        $this->resetAllData();
    }

    /**
     * Test get all users roles
     *
     * @covers \local_mentor_core\profile_api::get_all_users_roles
     * @covers \local_mentor_core\database_interface::get_all_admins
     * @covers \local_mentor_core\database_interface::get_all_category_users
     */
    public function test_get_all_users_roles_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $data = new \stdClass();
        $data->start = 0;
        $data->length = 0;
        $data->search = false;
        $data->order = false;
        $data->filters = [];

        $userroles = \local_mentor_core\profile_api::get_all_users_roles($data);

        self::assertEmpty($userroles);

        $entityid1 = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity1 = \local_mentor_core\entity_api::get_entity($entityid1);

        $user = new stdClass();
        $user->lastname = 'lastname2';
        $user->firstname = 'firstname2';
        $user->email = 'test2@test.com';
        $user->username = 'testusername2';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $entity1->name;
        $userid = local_mentor_core\profile_api::create_user($user);
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //So the user will have automatically main entity "Bibliothèque de formations" => id = 2;
        $entity1->assign_manager($userid);

        $userroles = \local_mentor_core\profile_api::get_all_users_roles($data);
        $now = time();

        self::assertCount(1, $userroles);

        self::assertEquals($userroles[0]->entityname, 'New Entity 1');
        self::assertEquals($userroles[0]->categoryid, $entity1->id);
        self::assertEquals($userroles[0]->parentid, 0);
        self::assertEquals($userroles[0]->rolename, 'Manager');
        self::assertEquals($userroles[0]->firstname, $user->firstname);
        self::assertEquals($userroles[0]->lastname, $user->lastname);
        self::assertEquals($userroles[0]->email, $user->email);
        self::assertEquals($userroles[0]->lastaccess, "0");
        self::assertEquals($userroles[0]->lastaccessstr, "Jamais");
        $dtz = new \DateTimeZone('Europe/Paris');
        $startdate = new \DateTime("@$now");
        $startdate->setTimezone($dtz);
        $dateformat = $startdate->format('d/m/Y');
        self::assertEquals($userroles[0]->timemodified, $dateformat);
        self::assertEquals($userroles[0]->profilelink, "https://www.example.com/moodle/user/profile.php?id=" . $userid);
        self::assertEquals($userroles[0]->mainentity, $entity1->name);

        $this->resetAllData();
    }

    /**
     * Test get course roles
     *
     * @covers \local_mentor_core\profile_api::get_course_roles
     */
    public function test_get_course_roles_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create session.
        $session = $this->init_create_session();

        // Updating the status session to have return sessions.
        $session->status = \local_mentor_core\session::STATUS_IN_PROGRESS;
        $session->opento = \local_mentor_core\session::OPEN_TO_ALL;
        \local_mentor_core\session_api::update_session($session);

        // Enable self enrol to session.
        $session->create_self_enrolment_instance();
        $session->enable_self_enrolment_instance();

        // Create user.
        $userid = self::init_create_user();

        // Set current user.
        self::setUser($userid);

        $result = \local_mentor_core\profile_api::get_course_roles($userid, $session->courseid);
        self::assertCount(0, $result);

        // Enrol user to course session.
        $session->enrol_current_user();

        $result = \local_mentor_core\profile_api::get_course_roles($userid, $session->courseid);

        $firstresult = reset($result);

        self::assertCount(1, $result);
        self::assertEquals('participant', $firstresult->shortname);

        $this->resetAllData();
    }

    /**
     * Test has profile config access
     *
     * @covers \local_mentor_core\profile_api::has_profile_config_access
     * @covers \local_mentor_core\profile_api::get_profile
     * @covers \local_mentor_core\profile::get_main_entity
     */
    public function test_has_profile_config_access_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create Entities.
        $entityid1 = self::init_create_entity();
        $entity1 = \local_mentor_core\entity_api::get_entity($entityid1);
        $entityid2 = self::init_create_entity('New entity 2');

        // Create user.
        $user1 = self::getDataGenerator()->create_user();
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //So the user will have automatically main entity "Bibliothèque de formations"
        //Due to test prepuces, we will override the main entity of the user and set it as we need 
        $profile = \local_mentor_core\profile_api::get_profile($user1->id);
        $profile->set_main_entity($entity1);
        $entity1->assign_manager($user1->id);

        // Create user.
        $user2 = new stdClass();
        $user2->lastname = 'lastname1';
        $user2->firstname = 'firstname1';
        $user2->email = 'test1@test.com';
        $user2->username = 'testusername1';
        $user2->password = 'to be generated';
        $user2->mnethostid = 1;
        $user2->confirmed = 1;
        $user2->auth = 'manual';
        $user2->profile_field_mainentity = 'New Entity 1';

        $userid2 = local_mentor_core\profile_api::create_user($user2);
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //Due to test prepuces, we will override the main entity of the user and set it as we need 
        $profile = \local_mentor_core\profile_api::get_profile($userid2);
        $profile->set_main_entity($entity1);
        set_user_preference('auth_forcepasswordchange', 0, $user2);

        // Create user.
        $user3 = new stdClass();
        $user3->lastname = 'lastname2';
        $user3->firstname = 'firstname2';
        $user3->email = 'test2@test.com';
        $user3->username = 'testusername2';
        $user3->password = 'to be generated';
        $user3->mnethostid = 1;
        $user3->confirmed = 1;
        $user3->auth = 'manual';
        $user3->profile_field_mainentity = 'New entity 2';

        $userid3 = local_mentor_core\profile_api::create_user($user3);
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //Due to test prepuces, we will override the main entity of the user and set it as we need 
        $profile = \local_mentor_core\profile_api::get_profile($userid3);
        $profile->set_main_entity(\local_mentor_core\entity_api::get_entity($entityid2));
        set_user_preference('auth_forcepasswordchange', 0, $user3);

        // Create user.
        $user4 = new stdClass();
        $user4->lastname = 'lastname3';
        $user4->firstname = 'firstname3';
        $user4->email = 'test3@test.com';
        $user4->username = 'testusername3';
        $user4->password = 'to be generated';
        $user4->mnethostid = 1;
        $user4->confirmed = 1;
        $user4->auth = 'manual';
        // Without main entity.
        $user4->profile_field_mainentity = '';

        $userid4 = local_mentor_core\profile_api::create_user($user4);
        set_user_preference('auth_forcepasswordchange', 0, $user4);

        self::setUser($user1);

        self::assertTrue(\local_mentor_core\profile_api::has_profile_config_access($userid2));
        self::assertFalse(\local_mentor_core\profile_api::has_profile_config_access($userid3));
        self::assertFalse(\local_mentor_core\profile_api::has_profile_config_access($userid4));

        $this->resetAllData();
    }

    /**
     * Test set user preference
     *
     * @covers \local_mentor_core\profile_api::set_user_preference
     */
    public function test_set_user_preference_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create Entities.
        $entityid1 = self::init_create_entity();
        $entity1 = \local_mentor_core\entity_api::get_entity($entityid1);

        // Create user.
        $user1 = self::getDataGenerator()->create_user();

        $entity1->assign_manager($user1->id);

        // Create user.
        $user = new stdClass();
        $user->lastname = 'lastname1';
        $user->firstname = 'firstname1';
        $user->email = 'test1@test.com';
        $user->username = 'testusername1';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = 'New Entity 1';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        // Preference not exist.
        self::assertFalse($DB->record_exists('user_preferences', ['userid' => $userid, 'name' => 'preferencename']));

        \local_mentor_core\profile_api::set_user_preference($userid, 'preferencename', 'preferencevalue');

        // New preference.
        self::assertTrue($DB->record_exists('user_preferences', ['userid' => $userid, 'name' => 'preferencename']));
        $prefenreceuser = $DB->get_record('user_preferences', ['userid' => $userid, 'name' => 'preferencename']);
        self::assertEquals($prefenreceuser->value, 'preferencevalue');

        \local_mentor_core\profile_api::set_user_preference($userid, 'preferencename', 'preferencevalue2');

        // Same preference with new value.
        self::assertTrue($DB->record_exists('user_preferences', ['userid' => $userid, 'name' => 'preferencename']));
        $prefenreceuser2 = $DB->get_record('user_preferences', ['userid' => $userid, 'name' => 'preferencename']);
        self::assertEquals($prefenreceuser->id, $prefenreceuser2->id);
        self::assertEquals($prefenreceuser2->value, 'preferencevalue2');

        $this->resetAllData();
    }

    /**
     * Test set user preference
     *
     * @covers \local_mentor_core\profile_api::get_user_preference
     */
    public function test_get_user_preference_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create Entities.
        $entityid1 = self::init_create_entity();
        $entity1 = \local_mentor_core\entity_api::get_entity($entityid1);

        // Create user.
        $user1 = self::getDataGenerator()->create_user();

        $entity1->assign_manager($user1->id);

        // Create user.
        $user = new stdClass();
        $user->lastname = 'lastname1';
        $user->firstname = 'firstname1';
        $user->email = 'test1@test.com';
        $user->username = 'testusername1';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = 'New Entity 1';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        // Preference not exist.
        self::assertFalse(\local_mentor_core\profile_api::get_user_preference($userid, 'preferencename'));

        // Set new preference.
        $preference = new \stdClass();
        $preference->userid = $userid;
        $preference->name = 'preferencename';
        $preference->value = 'preferencevalue';
        $DB->insert_record('user_preferences', $preference);

        // Preference exist.
        self::assertEquals('preferencevalue', \local_mentor_core\profile_api::get_user_preference($userid, 'preferencename'));

        $this->resetAllData();
    }

    /**
     * Test get edadmin course view capability
     *
     * @covers \local_mentor_core\profile_api::get_edadmin_course_view_capability
     */
    public function test_get_edadmin_course_view_capability_profile() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        self::assertEquals(
            'local/entities:manageentity',
            \local_mentor_core\profile_api::get_edadmin_course_view_capability()
        );

        $this->resetAllData();
    }
}
