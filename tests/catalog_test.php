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
 * Catalog api tests
 *
 * @package    local_mentor_specialization
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     mounir <mounir.ganem@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mentor_core\catalog_api;
use local_mentor_core\session;
use local_mentor_core\session_api;
use local_mentor_core\training_api;
use local_mentor_core\profile_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/catalog/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/api/catalog.php');

class local_mentor_core_catalog_testcase extends advanced_testcase {

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
     * Init training object
     *
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function get_training_data() {

        // Init test data.
        $trainingdata = new stdClass();

        $trainingdata->name = 'fullname';
        $trainingdata->shortname = 'shortname';
        $trainingdata->content = 'summary';

        // Create training object.
        $trainingdata->traininggoal = 'TEST TRAINING';
        $trainingdata->thumbnail = '';
        $trainingdata->status = \local_mentor_core\training::STATUS_DRAFT;

        try {
            // Get entity object for default category.
            $entityid = \local_mentor_core\entity_api::create_entity([
                    'name' => 'New Entity 1',
                    'shortname' => 'New Entity 1',
                // Set the admin user as manager of the entity.
                    'userid' => 2,
            ]);

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
     * Create a training
     *
     * @param $name
     * @param $shortname
     * @param $entityid
     * @return mixed
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function init_create_training($entityid) {
        // Init test data.
        $trainingdata = new stdClass();

        $trainingdata->name = 'fullname1';
        $trainingdata->shortname = 'shortname1';
        $trainingdata->content = 'summary1';

        // Create training object.
        $trainingdata->traininggoal = 'TEST TRAINING1';
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
     * Test get params renderer
     *
     * @covers \local_mentor_core\catalog_api::get_params_renderer
     */
    public function test_get_params_renderer() {
        global $USER;

        $this->resetAfterTest(true);

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        try {
            $training = training_api::create_training($this->get_training_data());

            $entity = $training->get_entity();

            // Add user to the default entity.
            $entity->add_member($USER);

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
        \local_mentor_core\session_api::update_session($sessionupdate);

        // Session creation.
        $session2 = session_api::create_session($training->id, 'session 2', true);

        // Update status.
        $updatedata = new stdClass();
        $updatedata->id = $session2->id;
        $updatedata->status = session::STATUS_OPENED_REGISTRATION;
        $updatedata->opento = 'all';
        session_api::update_session($updatedata);

        $paramsrenderer = \local_mentor_core\catalog_api::get_params_renderer();

        self::assertCount(1, $paramsrenderer->entities);
        self::assertCount(1, $paramsrenderer->trainings);
        self::assertEquals(1, $paramsrenderer->trainingscount);

        self::resetAllData();
    }

    /**
     * Test get catalog template
     *
     * @covers \local_mentor_core\catalog_api::get_catalog_template
     */
    public function test_get_catalog_template() {
        $this->resetAfterTest(true);

        $defaulttemplate = 'local_catalog/catalog';
        $template = catalog_api::get_catalog_template($defaulttemplate);
        self::assertEquals($defaulttemplate, $template);

        self::resetAllData();
    }

    /**
     * Test get catalog javascript
     *
     * @covers \local_mentor_core\catalog_api::get_catalog_javascript
     */
    public function test_get_catalog_javascript() {
        $this->resetAfterTest(true);

        $defaultjavascript = 'local_catalog/local_catalog';
        $javascript = catalog_api::get_catalog_javascript($defaultjavascript);
        self::assertEquals($defaultjavascript, $javascript);

        self::resetAllData();
    }

    /**
     * Test minutes to hours function
     *
     * @covers ::local_mentor_core_minutes_to_hours
     */
    public function test_minutes_to_hours() {
        $this->resetAfterTest(true);

        $result = local_mentor_core_minutes_to_hours(0);
        self::assertEquals('00min', $result);

        $result = local_mentor_core_minutes_to_hours(60);
        self::assertEquals('01h', $result);

        $result = local_mentor_core_minutes_to_hours(90);
        self::assertEquals('01h30', $result);

        self::resetAllData();
    }

    /**
     * get_sessions_template_by_training function not ok
     *
     * @covers \local_mentor_core\catalog_api::get_sessions_template_by_training
     * @covers \local_mentor_core\database_interface::is_shared_to_entity_by_session_id
     */
    public function test_get_sessions_template_by_training_nok() {
        $this->resetAfterTest(true);
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $training = training_api::create_training($this->get_training_data());

        // The training has no sessions.
        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        session_api::create_session($training->id, 'session 1', true);

        // Session is not opened.
        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::resetAllData();
    }

    /**
     * get_sessions_template_by_training function open to all
     *
     * @covers \local_mentor_core\catalog_api::get_sessions_template_by_training
     */
    public function test_get_sessions_template_by_training_open_to_all() {
        $this->resetAfterTest(true);
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        // Test with admin user.
        self::setAdminUser();

        $training = training_api::create_training($this->get_training_data());
        $data = new \stdClass();
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        $training->update($data);

        $sessionid = session_api::create_session($training->id, 'session 1', true);
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $data->opento = \local_mentor_core\session::OPEN_TO_ALL;
        $session->update($data);

        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with user with main entity same training entity.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'test@test.com';
        $user->username = 'testusername';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $training->get_entity()->name;

        $user1id = \local_mentor_core\profile_api::create_user($user);
        self::setUser($user1id);

        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::setAdminUser();

        // Test with user without main entity same training entity.
        $otherentityid = \local_mentor_core\entity_api::create_entity(['name' => 'Other Entity', 'shortname' => 'Other Entity']);
        $otherentity = \local_mentor_core\entity_api::get_entity($otherentityid);

        $user = new stdClass();
        $user->lastname = 'lastname2';
        $user->firstname = 'firstname2';
        $user->email = 'test2@test.com';
        $user->username = 'testusername2';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $otherentity->name;

        $user2id = \local_mentor_core\profile_api::create_user($user);

        self::setUser($user2id);

        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with no logging user.
        self::setUser(0);

        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::resetAllData();
    }

    /**
     * get_sessions_template_by_training function not visible
     *
     * @covers \local_mentor_core\catalog_api::get_sessions_template_by_training
     * @covers \local_mentor_core\database_interface::is_shared_to_entity_by_session_id
     */
    public function test_get_sessions_template_by_training_not_visible() {
        $this->resetAfterTest(true);
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $training = training_api::create_training($this->get_training_data());
        $data = new \stdClass();
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        $training->update($data);

        $sessionid = session_api::create_session($training->id, 'session 1', true);
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $data->opento = \local_mentor_core\session::OPEN_TO_NOT_VISIBLE;
        $session->update($data);

        // Test with admin user.
        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with user with main entity same training entity.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'test@test.com';
        $user->username = 'testusername';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $training->get_entity()->name;

        $user1id = \local_mentor_core\profile_api::create_user($user);
        self::setUser($user1id);

        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::setAdminUser();

        // Test with user without main entity same training entity.
        $otherentityid = \local_mentor_core\entity_api::create_entity(['name' => 'Other Entity', 'shortname' => 'Other Entity']);
        $otherentity = \local_mentor_core\entity_api::get_entity($otherentityid);

        $user = new stdClass();
        $user->lastname = 'lastname2';
        $user->firstname = 'firstname2';
        $user->email = 'test2@test.com';
        $user->username = 'testusername2';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $otherentity->name;

        $user2id = \local_mentor_core\profile_api::create_user($user);
        self::setUser($user2id);

        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with no logging user.
        self::setUser(0);

        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::resetAllData();
    }

    /**
     * get_sessions_template_by_training function not ok
     * Wrong status
     *
     * @covers \local_mentor_core\catalog_api::get_sessions_template_by_training
     */
    public function test_get_sessions_template_by_training_not_ok_wrong_status() {
        $this->resetAfterTest(true);
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $training = training_api::create_training($this->get_training_data());
        $data = new \stdClass();
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        $training->update($data);

        $sessionid = session_api::create_session($training->id, 'session 1', true);
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_CANCELLED;
        $data->opento = \local_mentor_core\session::OPEN_TO_ALL;
        $session->update($data);

        // Test with admin user.
        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with user with main entity same training entity.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'test@test.com';
        $user->username = 'testusername';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $training->get_entity()->name;

        $user1id = \local_mentor_core\profile_api::create_user($user);
        self::setUser($user1id);

        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::setAdminUser();

        // Test with user without main entity same training entity.
        $otherentityid = \local_mentor_core\entity_api::create_entity(['name' => 'Other Entity', 'shortname' => 'Other Entity']);
        $otherentity = \local_mentor_core\entity_api::get_entity($otherentityid);

        $user = new stdClass();
        $user->lastname = 'lastname2';
        $user->firstname = 'firstname2';
        $user->email = 'test2@test.com';
        $user->username = 'testusername2';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $otherentity->name;

        $user2id = \local_mentor_core\profile_api::create_user($user);
        self::setUser($user2id);

        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with no logging user.
        self::setUser(0);

        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::resetAllData();
    }

    /**
     * get_sessions_template_by_training function open to current entity
     *
     * @covers \local_mentor_core\catalog_api::get_sessions_template_by_training
     * @covers \local_mentor_core\database_interface::is_shared_to_entity_by_session_id
     */
    public function test_get_sessions_template_by_training_open_to_current_entity() {
        $this->resetAfterTest(true);
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        // Test with admin user.
        self::setAdminUser();
         //After auto-attach of main entity , the user will be affected the main entity that corresponds to its email domain
         //So this user will have "Bibliothèque de formations" as main entity after he'll be created (sprint60)
         // We need to create the training and session in this entity 
        $entityId = \local_mentor_core\entity_api::get_entity_by_name("Bibliothèque de formations")->id ;
        $training = $this->init_create_training($entityId);
        $data = new \stdClass();
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        $training->update($data);

        $sessionid = session_api::create_session($training->id, 'session 1', true);
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $data->opento = \local_mentor_core\session::OPEN_TO_CURRENT_ENTITY;
        $session->update($data);

        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with user with main entity same training entity "Bibliothèque de formations"
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'test@test.com';
        $user->username = 'testusername';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual'; 
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain
        
        $user1id = \local_mentor_core\profile_api::create_user($user);
        self::setUser($user1id);
        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::setAdminUser();

        // Test with the same user but main entity not the same as the training entity.
        $training2 = training_api::create_training($this->get_training_data());
        $data = new \stdClass();
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        $training2->update($data);

        $sessionid2 = session_api::create_session($training2->id, 'session 2', true);
        $session2 = \local_mentor_core\session_api::get_session($sessionid2);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_CANCELLED;
        $data->opento = \local_mentor_core\session::OPEN_TO_ALL;
        $session2->update($data);
        
        self::setUser($user1id);

        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training2->id));

        // Test with no logging user.
        self::setUser(0);

        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::resetAllData();
    }

    /**
     * get_sessions_template_by_training function open to other entity
     *
     * @covers \local_mentor_core\catalog_api::get_sessions_template_by_training
     * @covers \local_mentor_core\database_interface::is_shared_to_entity_by_session_id
     */
    public function test_get_sessions_template_by_training_open_to_other_entity() {
        $this->resetAfterTest(true);
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        // Test with admin user.
        self::setAdminUser();

        //After auto-attach of main entity , the user will be affected the main entity that corresponds to its email domain
        //So this user will have "Bibliothèque de formations" as main entity after he'll be created (sprint60)
        // We need to create the training and session in this entity 
        $entityId = \local_mentor_core\entity_api::get_entity_by_name("Bibliothèque de formations")->id ;
        $training = $this->init_create_training($entityId);       
         $data = new \stdClass();
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        $training->update($data);

        $otherentityid = \local_mentor_core\entity_api::create_entity(['name' => 'Other Entity', 'shortname' => 'Other Entity']);
        $otherentity = \local_mentor_core\entity_api::get_entity($otherentityid);
        $otherentityid2 = \local_mentor_core\entity_api::create_entity([
                'name' => 'Other Entity 2',
                'shortname' => 'Other Entity 2',
        ]);
        $otherentity2 = \local_mentor_core\entity_api::get_entity($otherentityid2);

        $sessionid = session_api::create_session($training->id, 'session 1', true);
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $data->opento = \local_mentor_core\session::OPEN_TO_OTHER_ENTITY;
        $data->opentolist = [$otherentityid];
        $session->update($data);

        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with user with main entity same training entity.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'test@test.com';
        $user->username = 'testusername';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';

        $user1id = \local_mentor_core\profile_api::create_user($user);
        self::setUser($user1id);

        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::setAdminUser();

        // Test with user without main entity same training entity but session is share to this entity.
        $training2 = training_api::create_training($this->get_training_data()); 
        $data = new \stdClass();
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        $training2->update($data);

        $sessionid2 = session_api::create_session($training2->id, 'session 2', true);
        $session2 = \local_mentor_core\session_api::get_session($sessionid2);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $data->opento = \local_mentor_core\session::OPEN_TO_OTHER_ENTITY;
        $data->opentolist = [$entityId];
        $session2->update($data);
        self::setUser($user1id);

        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training2->id));

        // Test with user without main entity same training entity and session is not share to this entity.
        self::setAdminUser();
        $data = new \stdClass();

        $data->opentolist = [$otherentityid2];
        $session2->update($data);
        self::setUser($user1id);

        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training2->id));

        // Test with no logging user.
        self::setUser(0);

        self::assertFalse(\local_mentor_core\catalog_api::get_sessions_template_by_training($training2->id));

        self::resetAllData();
    }

    /**
     * get_sessions_template_by_training function multiple session
     *
     * @covers \local_mentor_core\catalog_api::get_sessions_template_by_training
     * @covers \local_mentor_core\database_interface::is_shared_to_entity_by_session_id
     */
    public function test_get_sessions_template_by_training_multiple_session() {
        $this->resetAfterTest(true);
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $training = training_api::create_training($this->get_training_data());
        $data = new \stdClass();
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        $training->update($data);

        $otherentityid = \local_mentor_core\entity_api::create_entity(['name' => 'Other Entity', 'shortname' => 'Other Entity']);
        $otherentity = \local_mentor_core\entity_api::get_entity($otherentityid);
        $otherentityid2 = \local_mentor_core\entity_api::create_entity([
                'name' => 'Other Entity 2',
                'shortname' => 'Other Entity 2',
        ]);
        $otherentity2 = \local_mentor_core\entity_api::get_entity($otherentityid2);

        // Open to all.
        $sessionid = session_api::create_session($training->id, 'session 1', true);
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $data->opento = \local_mentor_core\session::OPEN_TO_ALL;
        $session->update($data);

        // Open to current entity.
        $sessionid2 = session_api::create_session($training->id, 'session 2', true);
        $session2 = \local_mentor_core\session_api::get_session($sessionid2);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $data->opento = \local_mentor_core\session::OPEN_TO_CURRENT_ENTITY;
        $session2->update($data);

        // Open to other entity.
        $sessionid3 = session_api::create_session($training->id, 'session 3', true);
        $session3 = \local_mentor_core\session_api::get_session($sessionid3);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $data->opento = \local_mentor_core\session::OPEN_TO_OTHER_ENTITY;
        $data->opentolist = [$otherentityid];
        $session3->update($data);

        // Not visible.
        $sessionid4 = session_api::create_session($training->id, 'session 4', true);
        $session4 = \local_mentor_core\session_api::get_session($sessionid4);
        $data = new \stdClass();
        $data->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $data->opento = \local_mentor_core\session::OPEN_TO_NOT_VISIBLE;
        $session4->update($data);

        // Test with admin user.
        self::assertCount(3, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with user with main entity same training entity.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'test@test.com';
        $user->username = 'testusername';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $training->get_entity()->name;

        $user1id = \local_mentor_core\profile_api::create_user($user);
       //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //So the user will have automatically main entity "Bibliothèque de formations"
        //Due to test prepuces, we will override the main entity of the user and set it as we need 
        $profile = profile_api::get_profile($user1id);
        $profile->set_main_entity($training->get_entity());
        $training->get_entity()->add_member($user);

        self::setUser($user1id);
        self::assertCount(3, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::setAdminUser();

        // Test with user without main entity same training entity but session is share to this entity.
        $user = new stdClass();
        $user->lastname = 'lastname2';
        $user->firstname = 'firstname2';
        $user->email = 'test2@test.com';
        $user->username = 'testusername2';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $otherentity->name;

        $user2id = \local_mentor_core\profile_api::create_user($user);
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //So the user will have automatically main entity "Bibliothèque de formations"
        //Due to test prepuces, we will override the main entity of the user and set it as we need 
        $profile = profile_api::get_profile($user2id);
        $profile->set_main_entity($otherentity);
        $otherentity->add_member($user);

        self::setUser($user2id);

        self::assertCount(2, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with user without main entity same training entity and session is not share to this entity.
        $user = new stdClass();
        $user->lastname = 'lastname3';
        $user->firstname = 'firstname3';
        $user->email = 'test3@test.com';
        $user->username = 'testusername3';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = $otherentity2->name;

        $user3id = \local_mentor_core\profile_api::create_user($user);
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //So the user will have automatically main entity "Bibliothèque de formations"
        //Due to test prepuces, we will override the main entity of the user and set it as we need 
        $profile = profile_api::get_profile($user3id);
        $profile->set_main_entity($otherentity2);
        $otherentity2->add_member($user);

        self::setUser($user3id);

        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        // Test with no logging user.
        self::setUser(0);

        self::assertCount(1, \local_mentor_core\catalog_api::get_sessions_template_by_training($training->id));

        self::resetAllData();
    }

    /**
     * get_training_template function
     *
     * @covers \local_mentor_core\catalog_api::get_training_template
     * @covers \local_mentor_core\database_interface::is_shared_to_entity_by_session_id
     */
    public function test_get_training_template() {
        $this->resetAfterTest(true);
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        self::assertEquals('local_catalog/training',
                \local_mentor_core\catalog_api::get_training_template('local_catalog/training'));

        self::resetAllData();
    }
}
