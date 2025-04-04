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
 * Test cases for class session
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');
require_once($CFG->dirroot . '/local/mentor_core/api/training.php');
require_once($CFG->dirroot . '/local/mentor_core/api/session.php');
require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/session.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

class local_mentor_core_session_class_testcase extends advanced_testcase {

    /**
     * Reset the singletons
     *
     * @throws ReflectionException
     */
    public function reset_singletons() {
        // Reset the mentor core specialization singleton.
        $specialization = \local_mentor_core\specialization::get_instance();
        $reflection = new ReflectionClass($specialization);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true); // Now we can modify that :).
        $instance->setValue(null, null); // Instance is gone.
        $instance->setAccessible(false); // Clean up.

        $specialization = \local_mentor_core\database_interface::get_instance();
        $reflection = new ReflectionClass($specialization);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true); // Now we can modify that :).
        $instance->setValue(null, null); // Instance is gone.
        $instance->setAccessible(false); // Clean up.

        \local_mentor_core\training_api::clear_cache();
    }

    protected static function get_protected_method($name) {
        $class = new ReflectionClass('\local_mentor_core\session');
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
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

        set_config('collections', 'accompagnement|Accompagnement des transitions professionnelles|#CECECE',
            'local_mentor_specialization');

        if ($training) {
            $data->name = 'fullname';
            $data->shortname = 'shortname';
            $data->content = 'summary';
            $data->status = 'ec';
        } else {
            $data->trainingname = 'fullname';
            $data->trainingshortname = 'shortname';
            $data->trainingcontent = 'summary';
            $data->trainingstatus = 'ec';
        }

        // Fields for taining.
        $data->teaser = 'http://www.edunao.com/';
        $data->teaserpicture = '';
        $data->prerequisite = 'TEST';
        $data->collection = 'accompagnement';
        $data->traininggoal = 'TEST TRAINING ';
        $data->idsirh = 'TEST ID SIRH';
        $data->licenseterms = 'cc-sa';
        $data->typicaljob = 'TEST';
        $data->skills = [];
        $data->certifying = '1';
        $data->presenceestimatedtimehours = '12';
        $data->presenceestimatedtimeminutes = '10';
        $data->remoteestimatedtimehours = '15';
        $data->remoteestimatedtimeminutes = '30';
        $data->trainingmodalities = 'd';
        $data->producingorganization = 'TEST';
        $data->producerorganizationlogo = '';
        $data->designers = 'TEST';
        $data->contactproducerorganization = 'TEST';
        $data->thumbnail = '';

        // Specific fields for session (only for update).
        if ($sessionid) {
            $data->id = $sessionid;
            $data->opento = 'all';
            $data->publiccible = 'TEST';
            $data->termsregistration = 'autre';
            $data->termsregistrationdetail = 'TEST';

            $data->onlinesessionestimatedtimehours = '10';
            $data->onlinesessionestimatedtimeminutes = '15';
            $data->presencesessionestimatedtimehours = '12';
            $data->presencesessionestimatedtimeminutes = '25';

            $data->sessionpermanent = 0;
            $data->sessionstartdate = 1609801200;
            $data->sessionenddate = 1609801200;
            $data->sessionmodalities = 'presentiel';
            $data->accompaniment = 'TEST';
            $data->maxparticipants = 10;
            $data->placesavailable = 8;
            $data->numberparticipants = 2;
            $data->location = 'PARIS';
            $data->organizingstructure = 'TEST ORGANISATION';
            $data->sessionnumber = 1;
            $data->opentolist = '';
        }

        return $data;
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

        // Test standard session creation.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        return $session->id;
    }

    /**
     * Test entity constructor
     *
     * @covers \local_mentor_core\session::__construct
     */
    public function test_construct() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();

        $session = \local_mentor_core\session_api::get_session($sessionid);

        $sessionobj = new \local_mentor_core\session($sessionid);

        self::assertEquals($session->id, $sessionobj->id);
        self::assertEquals($session->fullname, $sessionobj->fullname);
        self::assertEquals($session->shortname, $sessionobj->shortname);

        $baddata = new stdClass();
        $baddata->id = $sessionid;

        try {
            $sessionobj = new \local_mentor_core\session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test create_manual_enrolment_instance method
     *
     * @covers \local_mentor_core\session::create_manual_enrolment_instance
     * @covers \local_mentor_core\session::get_enrolment_instances_by_type
     * @covers \local_mentor_core\session::get_enrolment_instances
     * @covers \local_mentor_core\session::enable_manual_enrolment_instance
     */
    public function test_create_manual_enrolment_instance() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();

        $session = new \local_mentor_core\session($sessionid);

        // Create manual instance.
        self::assertIsInt($session->create_manual_enrolment_instance());

        $enrolmentinstance = $session->get_enrolment_instances_by_type('manual');
        self::assertIsObject($enrolmentinstance);

        // Update enrolment instance (disable).
        $enrolmentinstance->status = '1';
        $session->update_enrolment_instance($enrolmentinstance);

        // Enrolment instance exist and enabled this.
        self::assertTrue($session->create_manual_enrolment_instance());
        $enrolmentinstance = $session->get_enrolment_instances_by_type('manual');
        self::assertEquals($enrolmentinstance->status, '0');

        self::resetAllData();
    }

    /**
     * Test disable_enrolment_instance method
     *
     * @covers \local_mentor_core\session::disable_enrolment_instance
     * @covers \local_mentor_core\session::disable_self_enrolment_instance
     * @covers \local_mentor_core\session::disable_manual_enrolment_instance
     * @covers \local_mentor_core\session::get_enrolment_instances
     * @covers \local_mentor_core\session::enable_self_enrolment_instance
     */
    public function test_disable_enrolment_instance() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();

        $session = new \local_mentor_core\session($sessionid);

        // Create manuel enrolment instance.
        $session->create_manual_enrolment_instance();

        $manualenrolmentinstance = $session->get_enrolment_instances_by_type('manual');
        self::assertEquals('0', $manualenrolmentinstance->status);
        self::assertEquals($session->get_course()->id, $manualenrolmentinstance->courseid);

        // Create self enrolment instance.
        $session->create_self_enrolment_instance();
        $session->enable_self_enrolment_instance();

        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        self::assertEquals('0', $selfenrolmentinstance->status);
        self::assertEquals($session->get_course()->id, $selfenrolmentinstance->courseid);

        // Disable all enrolment instance.
        $session->disable_enrolment_instance();

        // Manual enrolment is disable.
        $manualenrolmentinstance = $session->get_enrolment_instances_by_type('manual');
        self::assertEquals('1', $manualenrolmentinstance->status);
        self::assertEquals($session->get_course()->id, $manualenrolmentinstance->courseid);

        // Self enrolment is disable.
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        self::assertEquals('1', $selfenrolmentinstance->status);
        self::assertEquals($session->get_course()->id, $selfenrolmentinstance->courseid);

        self::resetAllData();
    }

    /**
     * Test enable_self_enrolment_instance method
     *
     * @covers \local_mentor_core\session::enable_self_enrolment_instance
     */
    public function test_enable_self_enrolment_instance() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();

        $session = new \local_mentor_core\session($sessionid);

        // Self enrolment does not exist.
        self::assertFalse($session->enable_self_enrolment_instance());

        $session->create_self_enrolment_instance();

        // Self enrolment already enable.
        self::assertTrue($session->enable_self_enrolment_instance());

        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        $selfenrolmentinstance->status = '1';
        $session->update_enrolment_instance($selfenrolmentinstance);

        // Enabled self enrolment.
        self::assertTrue($session->enable_self_enrolment_instance());

        self::resetAllData();
    }

    /**
     * Test get_participants_number method
     *
     * @covers \local_mentor_core\session::get_participants_number
     * @covers \local_mentor_core\session::get_participants
     * @covers \local_mentor_core\session::get_course
     * @covers \local_mentor_core\session::is_participant
     * @covers \local_mentor_core\session::get_context
     * @covers \local_mentor_core\session::create_manual_enrolment_instance
     */
    public function test_get_participants_number() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();

        $session = new \local_mentor_core\session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);

        $session->create_manual_enrolment_instance();

        $participantsnumber = $session->get_participants_number();
        self::assertEquals($participantsnumber, 0);

        $user = self::getDataGenerator()->create_user();

        $courseid = $session->courseid;

        enrol_try_internal_enrol($courseid, $user->id);

        // Set user as participant.
        \local_mentor_core\profile_api::role_assign('participant', $user->id, context_course::instance($courseid)->id);

        $participantsnumber = $session->get_participants_number(true);
        self::assertEquals($participantsnumber, 1);

        // Add nom participant.
        $user2 = self::getDataGenerator()->create_user();
        enrol_try_internal_enrol($courseid, $user2->id);
        \local_mentor_core\profile_api::role_assign('participant', $user2->id, context_course::instance($courseid)->id);

        // Just one participant because no refresh cache.
        $participantsnumber = $session->get_participants_number();
        self::assertEquals($participantsnumber, 1);

        // Refresh cache.
        $participantsnumber = $session->get_participants_number(true);
        self::assertEquals($participantsnumber, 2);

        self::resetAllData();
    }

    /**
     * Test get_entities_sharing method
     *
     * @covers \local_mentor_core\session::get_entities_sharing
     * @covers \local_mentor_core\session::update_session_sharing
     */
    public function test_get_entities_sharing() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();

        $session = new \local_mentor_core\session($sessionid);

        // Create new entities.
        $entityid1 = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 2',
            'shortname' => 'New Entity 2',
        ]);

        $entityid2 = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 3',
            'shortname' => 'New Entity 3',
        ]);

        $result = $session->update_session_sharing([$entityid1, $entityid2]);

        self::assertTrue($result);

        // The session must be shared with 2 entities.
        $sharing = $session->get_entities_sharing();
        self::assertCount(2, $sharing);

        self::resetAllData();
    }

    /**
     * Test is_shared_with_entity method
     *
     * @covers \local_mentor_core\session::is_shared_with_entity
     * @covers \local_mentor_core\session::get_entities_sharing
     * @covers \local_mentor_core\session::update_session_sharing
     */
    public function test_is_shared_with_entity() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();

        $session = new \local_mentor_core\session($sessionid);

        // Shared to other entity.
        $data = new stdClass();
        $data->opento = 'current_entity';
        $data->opentolist = [];
        $session->update($data);

        // Create new entities.
        $entityid1 = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 2',
            'shortname' => 'New Entity 2',
        ]);

        $entityid2 = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 3',
            'shortname' => 'New Entity 3',
        ]);

        $isshared = $session->is_shared_with_entity($entityid1);
        self::assertFalse($isshared);

        // Shared to other entity.
        $data = new stdClass();
        $data->opento = 'other_entities';
        $data->opentolist = [];
        $session->update($data);

        $result = $session->update_session_sharing([$entityid1]);
        self::assertTrue($result);

        $isshared = $session->is_shared_with_entity($entityid1);
        self::assertTrue($isshared);

        $isshared = $session->is_shared_with_entity($entityid2);
        self::assertFalse($isshared);

        self::resetAllData();
    }

    /**
     * Test remove_session_sharing method
     *
     * @covers \local_mentor_core\session::remove_session_sharing
     * @covers \local_mentor_core\session::update_session_sharing
     */
    public function test_remove_session_sharing() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();

        $session = new \local_mentor_core\session($sessionid);

        // Create new entities.
        $entityid1 = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 2',
            'shortname' => 'New Entity 2',
        ]);

        $entityid2 = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 3',
            'shortname' => 'New Entity 3',
        ]);

        // Shared to other entity.
        $data = new stdClass();
        $data->opento = 'other_entities';
        $data->opentolist = [];
        $session->update($data);

        $result = $session->update_session_sharing([$entityid1, $entityid2]);
        self::assertTrue($result);

        $isshared = $session->is_shared_with_entity($entityid1);
        self::assertTrue($isshared);

        $isshared = $session->is_shared_with_entity($entityid2);
        self::assertTrue($isshared);

        $result = $session->remove_session_sharing();
        self::assertTrue($result);

        $isshared = $session->is_shared_with_entity($entityid1);
        self::assertFalse($isshared);

        $isshared = $session->is_shared_with_entity($entityid2);
        self::assertFalse($isshared);

        self::resetAllData();
    }

    /**
     * Test delete method
     *
     * @covers \local_mentor_core\session::delete
     * @covers \local_mentor_core\session::get_context
     */
    public function test_delete() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();

        $session = new \local_mentor_core\session($sessionid);

        $user = self::getDataGenerator()->create_user();

        // Try to delete with a simple user.
        self::setUser($user);

        try {
            $result = $session->delete();
        } catch (Exception $e) {
            self::assertInstanceOf('required_capability_exception', $e);
        }

        // Try to delete with an admin.
        self::setAdminUser();
        $result = $session->delete();
        self::assertTrue($result);

        try {
            $session = \local_mentor_core\session_api::get_session($sessionid, true);
        } catch (Exception $e) {
            self::assertInstanceOf('dml_missing_record_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test user permissions on session
     *
     * @covers  \local_mentor_core\session::is_manager
     * @covers  \local_mentor_core\session::is_creator
     * @covers  \local_mentor_core\session::is_deleter
     * @covers  \local_mentor_core\session::is_tutor
     * @covers  \local_mentor_core\session::is_updater
     * @covers  \local_mentor_core\session::is_trainer
     * @covers  \local_mentor_core\session::get_context
     */
    public function test_user_permissions() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();

        try {
            $session = \local_mentor_core\session_api::get_session($sessionid);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Create a simple user.
        $user = $this->getDataGenerator()->create_user();
        self::setUser($user);

        self::assertFalse($session->is_manager());
        self::assertFalse($session->is_creator($user));
        self::assertFalse($session->is_deleter($user));
        self::assertFalse($session->is_tutor($user));
        self::assertFalse($session->is_updater($user));
        self::assertFalse($session->is_trainer($user));

        self::setAdminUser();
        global $USER;

        self::assertTrue($session->is_manager());
        self::assertTrue($session->is_creator($USER));
        self::assertTrue($session->is_deleter($USER));
        self::assertFalse($session->is_tutor($USER));
        self::assertTrue($session->is_updater($USER));
        self::assertTrue($session->is_trainer($USER));

        $this->resetAllData();
    }

    /**
     * Test update_status method (to inpreparation)
     *
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::inpreparation
     */
    public function test_update_status_to_inpreparation() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $session->update_status(\local_mentor_core\session::STATUS_IN_PREPARATION);

        // Status is "in preparation".
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_IN_PREPARATION);
        self::assertEquals($session->get_course()->visible, 0);
        // Self enrolment instance does not exist.
        self::assertFalse($session->get_enrolment_instances_by_type('self'));

        $this->resetAllData();
    }

    /**
     * Test update_status method (to open_to_registration)
     *
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::open_to_registration
     * @covers  \local_mentor_core\session::show_course
     */
    public function test_update_status_to_open_to_registration() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $session->update_status(\local_mentor_core\session::STATUS_OPENED_REGISTRATION);

        // Status is "in preparation".
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_OPENED_REGISTRATION);
        self::assertEquals($session->get_course()->visible, 1);
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        self::assertEquals($selfenrolmentinstance->customint6, 1);

        $this->resetAllData();
    }

    /**
     * Test update_status method (to open)
     *
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::open
     * @covers  \local_mentor_core\session::show_course
     */
    public function test_update_status_to_open() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $session->update_status(\local_mentor_core\session::STATUS_IN_PROGRESS);

        // Status is "open".
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_IN_PROGRESS);
        self::assertEquals($session->get_course()->visible, 1);
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        self::assertEquals($selfenrolmentinstance->customint6, 1);

        $this->resetAllData();
    }

    /**
     * Test update_status method (to complete)
     *
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::complete
     */
    public function test_update_status_to_complete() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->create_self_enrolment_instance();

        $session->update_status(\local_mentor_core\session::STATUS_COMPLETED);

        // Status is "complete".
        $now = time();
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_COMPLETED);
        self::assertEquals($session->get_course()->visible, 1);
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        self::assertTrue($selfenrolmentinstance->enrolenddate >= $now);

        $this->resetAllData();
    }

    /**
     * Test update_status method (to complete)
     *
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::complete
     */
    public function test_update_status_to_complete_mail() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);
        $session->create_manual_enrolment_instance();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        self::getDataGenerator()->enrol_user($user1->id, $session->courseid, 'participant');
        self::getDataGenerator()->enrol_user($user2->id, $session->courseid, 'tuteur');
        self::getDataGenerator()->enrol_user($user3->id, $session->courseid, 'formateur');
        self::getDataGenerator()->enrol_user($user4->id, $session->courseid, 'concepteur');

        // Close the default email sink.
        $sink = $this->redirectEmails();
        $sink->close();
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        $session->update_status(\local_mentor_core\session::STATUS_COMPLETED, \local_mentor_core\session::STATUS_IN_PROGRESS);

        // Status is "complete".
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_COMPLETED);

        // Check if send mail.
        $this->assertSame(3, $sink->count());
        $resultmail = $sink->get_messages();
        $this->assertCount(3, $resultmail);
        $sink->close();

        self::assertEquals($resultmail[0]->to, $user1->email);
        self::assertEquals($resultmail[0]->subject,
            get_string('email_complete_session_object', 'local_mentor_core', $session->fullname));

        self::assertEquals($resultmail[1]->to, $user2->email);
        self::assertEquals($resultmail[1]->subject, get_string('email_complete_session_object', 'local_mentor_core',
            $session->fullname));

        self::assertEquals($resultmail[2]->to, $user3->email);
        self::assertEquals($resultmail[2]->subject, get_string('email_complete_session_object', 'local_mentor_core',
            $session->fullname));

        $this->resetAllData();
    }

    /**
     * Test update_status method (to archive)
     *
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::archive
     */
    public function test_update_status_to_archive() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->create_self_enrolment_instance();

        $session->update_status(\local_mentor_core\session::STATUS_ARCHIVED);

        // Status is "archived".
        $now = time();
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_ARCHIVED);
        self::assertEquals($session->get_course()->visible, 1);
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        self::assertTrue($selfenrolmentinstance->enrolenddate >= $now);

        $this->resetAllData();
    }

    /**
     * Test update_status method (to archive)
     *
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::archive
     */
    public function test_update_status_to_archive_mail() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);
        $session->create_manual_enrolment_instance();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        self::getDataGenerator()->enrol_user($user1->id, $session->courseid, 'participant');
        self::getDataGenerator()->enrol_user($user2->id, $session->courseid, 'tuteur');
        self::getDataGenerator()->enrol_user($user3->id, $session->courseid, 'formateur');
        self::getDataGenerator()->enrol_user($user4->id, $session->courseid, 'concepteur');

        // Close the default email sink.
        $sink = $this->redirectEmails();
        $sink->close();
        unset_config('noemailever');
        $sink = $this->redirectEmails();

        $session->update_status(\local_mentor_core\session::STATUS_ARCHIVED, \local_mentor_core\session::STATUS_COMPLETED);

        // Status is "archived".
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_ARCHIVED);

        // Check if send mail.
        $this->assertSame(3, $sink->count());
        $resultmail = $sink->get_messages();
        $this->assertCount(3, $resultmail);
        $sink->close();

        self::assertEquals($resultmail[0]->to, $user1->email);
        self::assertEquals($resultmail[0]->subject,
            get_string('email_archive_session_object', 'local_mentor_core', $session->fullname));

        self::assertEquals($resultmail[1]->to, $user2->email);
        self::assertEquals($resultmail[1]->subject, get_string('email_archive_session_object', 'local_mentor_core',
            $session->fullname));

        self::assertEquals($resultmail[2]->to, $user3->email);
        self::assertEquals($resultmail[2]->subject, get_string('email_archive_session_object', 'local_mentor_core',
            $session->fullname));

        $this->resetAllData();
    }

    /**
     * Test update_status method (to report)
     *
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::report
     */
    public function test_update_status_to_report() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->create_self_enrolment_instance();

        $session->update_status(\local_mentor_core\session::STATUS_REPORTED);

        // Status is "complete".
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_REPORTED);
        self::assertEquals($session->get_course()->visible, 0);
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        self::assertEquals($selfenrolmentinstance->status, 1);

        $this->resetAllData();
    }

    /**
     * Test update_status method (report to open_to_registration)
     *
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::open_to_registration
     * @covers  \local_mentor_core\session::send_message_to_all
     */
    public function test_update_status_report_to_open_to_registration() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->create_self_enrolment_instance();

        $session->update_status(\local_mentor_core\session::STATUS_REPORTED);

        // Status is "complete".
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_REPORTED);
        self::assertEquals($session->get_course()->visible, 0);
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        self::assertEquals($selfenrolmentinstance->status, 1);

        $session->update_status(
            \local_mentor_core\session::STATUS_OPENED_REGISTRATION,
            \local_mentor_core\session::STATUS_REPORTED
        );

        // Status is "in preparation".
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_OPENED_REGISTRATION);
        self::assertEquals($session->get_course()->visible, 1);
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        self::assertEquals($selfenrolmentinstance->customint6, 1);

        $this->resetAllData();
    }

    /**
     * Test update_status method (to cancel)
     *
     * @covers  \local_mentor_core\session::update_status
     * @covers  \local_mentor_core\session::cancel
     */
    public function test_update_status_to_cancel() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->create_self_enrolment_instance();

        $session->update_status(\local_mentor_core\session::STATUS_CANCELLED);

        // Status is "complete".
        self::assertEquals($session->status, \local_mentor_core\session::STATUS_CANCELLED);
        self::assertEquals($session->get_course()->visible, 0);
        $selfenrolmentinstance = $session->get_enrolment_instances_by_type('self');
        self::assertEquals($selfenrolmentinstance->status, 1);

        $this->resetAllData();
    }

    /**
     * Test update_status method (to other_status)
     *
     * @covers  \local_mentor_core\session::update_status
     */
    public function test_update_status_to_other_status() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->create_self_enrolment_instance();
        $otherstatus = 'other_status';

        $session->update_status($otherstatus);

        // Status is "other_status".
        self::assertEquals($session->status, $otherstatus);

        $this->resetAllData();
    }

    /**
     * Test session get url
     *
     * @covers  \local_mentor_core\session::get_url
     */
    public function test_get_url() {
        global $DB;
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $course = $session->get_course();

        // Set course display.
        if ($field = $DB->get_record('course_format_options', ['courseid' => $course->id, 'name' => 'coursedisplay'])) {
            $field->value = 1;
            $DB->update_record('course_format_options', $field);
        } else {
            $field = new stdClass();
            $field->value = 1;
            $field->courseid = $course->id;
            $field->name = 'coursedisplay';
            $DB->insert_record('course_format_options', $field);
        }

        $url = $session->get_url();
        $section = $url->get_param('section');
        self::assertNull($section);

        course_create_section($course->id, 0);

        $url = $session->get_url();
        $section = $url->get_param('section');
        self::assertEquals(1, $section);

        $this->resetAllData();
    }

    /**
     * Test generate_backup
     *
     * @covers  \local_mentor_core\session::generate_backup
     */
    public function test_generate_backup() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $sessionbackupfile = $session->generate_backup();

        self::assertEquals('backup', $sessionbackupfile->get_component());
        self::assertEquals('course', $sessionbackupfile->get_filearea());
        self::assertEquals($session->get_context()->id, $sessionbackupfile->get_contextid());

        $this->resetAllData();
    }

    /**
     * Test duplicate_into_training
     *
     * @covers  \local_mentor_core\session::duplicate_into_training
     * @covers  \local_mentor_core\training::get_all_training_files
     * @covers  \local_mentor_core\training::get_all_enrolments
     */
    public function test_duplicate_into_training_ok() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $db = \local_mentor_core\database_interface::get_instance();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $sessiontraining = $session->get_training();

        $fs = get_file_storage();

        $component = 'local_trainings';
        $itemid = $sessiontraining->id;
        $filearea = 'thumbnail';
        $contextid = $sessiontraining->get_context()->id;

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

        $teacherrole = $db->get_role_by_name('teacher');
        $user = self::getDataGenerator()->create_user();
        role_assign($teacherrole->id, $user->id, $sessiontraining->get_context());

        $newtraining = $session->duplicate_into_training();

        // Check training information.
        self::assertEquals($sessiontraining->name, $newtraining->name);
        self::assertEquals($sessiontraining->shortname, $newtraining->shortname);
        self::assertEquals($sessiontraining->courseshortname, $newtraining->courseshortname);
        self::assertEquals($sessiontraining->courseshortname, $newtraining->courseshortname);

        // Check training user enrolment.
        $allenrolmentssessiontraining = array_values($sessiontraining->get_all_enrolments());
        $allenrolmentsnewtraining = array_values($newtraining->get_all_enrolments());
        self::assertCount(1, $allenrolmentsnewtraining);
        self::assertEquals($allenrolmentssessiontraining[0]->roleid, $allenrolmentsnewtraining[0]->roleid);
        self::assertEquals($allenrolmentssessiontraining[0]->userid, $allenrolmentsnewtraining[0]->userid);

        // Check training files.
        $allfilessessiontraining = array_values($sessiontraining->get_all_training_files());
        $allfilesnewtraining = array_values($newtraining->get_all_training_files());
        self::assertCount(1, $allfilesnewtraining);
        self::assertEquals($allfilessessiontraining[0]->get_itemid(), $allfilesnewtraining[0]->get_itemid());
        self::assertEquals($allfilessessiontraining[0]->get_contextid(), $allfilesnewtraining[0]->get_contextid());

        $this->resetAllData();
    }

    /**
     * Test duplicate_into_training not ok
     *
     * @covers  \local_mentor_core\session::duplicate_into_training
     */
    public function test_duplicate_into_training_nok() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);
        $backup = $session->generate_backup();
        $training = $session->get_training();

        // Create Session mock.
        $sessionmock = $this->getMockBuilder('\local_mentor_core\session')
            ->setMethods(['generate_backup', 'get_training'])
            ->setConstructorArgs([$sessionid])
            ->getMock();

        // Return false value one time when generate_backup function call.
        $sessionmock->expects($this->exactly(2))
            ->method('generate_backup')
            ->willReturnOnConsecutiveCalls(
                false,
                // Second test part.
                $backup
            );

        // Generate backup fail.
        try {
            $sessionmock->duplicate_into_training();
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
            self::assertEquals('Backup file not created', $e->getMessage());
        }

        // Create Training mock.
        $trainingmock = $this->getMockBuilder('\local_mentor_core\training')
            ->setMethods([
                'restore_backup',
                'get_course',
                'get_all_enrolments',
                'get_all_training_files',
                'generate_backup',
            ])
            ->setConstructorArgs([$sessionmock->trainingid])
            ->getMock();

        // Return training course value one time when get_course function call.
        $trainingmock->expects($this->any())
            ->method('get_course')
            ->will($this->returnValue($training->get_course()));

        // Return all enrolments course value one time when get_all_enrolments function call.
        $trainingmock->expects($this->any())
            ->method('get_all_enrolments')
            ->will($this->returnValue($training->get_all_enrolments()));

        // Return all training files value one time when get_all_training_files function call.
        $trainingmock->expects($this->any())
            ->method('get_all_training_files')
            ->will($this->returnValue($training->get_all_enrolments()));

        // Return backup training value one time when generate_backup function call.
        $trainingmock->expects($this->any())
            ->method('generate_backup')
            ->will($this->returnValue($training->generate_backup()));

        // Return false value one time when restore_backup function call.
        $trainingmock->expects($this->any())
            ->method('restore_backup')
            ->will($this->returnValue(false));

        // Return $trainingmock object one time when get_training function call.
        $sessionmock->expects($this->any())
            ->method('get_training')
            ->will($this->returnValue($trainingmock));

        // Restore training backup fail.
        try {
            $sessionmock->duplicate_into_training();
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
            self::assertEquals('Restoration failed', $e->getMessage());
        }

        self::expectOutputString('Restoration failed' . "\n");

        $this->resetAllData();
    }

    /**
     * Test is_open_to_all
     *
     * @covers  \local_mentor_core\session::is_open_to_all
     */
    public function test_is_open_to_all() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        self::assertFalse($session->is_open_to_all());

        $data = new stdClass();
        $data->opento = 'all';
        $session->update($data);

        self::assertTrue($session->is_open_to_all());

        $this->resetAllData();
    }

    /**
     * Test get_opento_list
     *
     * @covers  \local_mentor_core\session::get_opento_list
     */
    public function test_get_opento_list() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $otherentityname = 'otherentity';
        $otherentityid = \local_mentor_core\entity_api::create_entity([
            'name' => $otherentityname,
            'shortname' => $otherentityname,
        ]);

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $getopentolistmethod = self::get_protected_method('get_opento_list');

        // Is not shared with an other entity.
        self::assertEquals('', $getopentolistmethod->invoke($session));

        $data = new stdClass();
        $data->opento = 'other_entities';
        $session->update($data);
        $session->update_session_sharing([$otherentityid]);

        // Is shared with an other entity.
        $sharedentities = $getopentolistmethod->invoke($session);
        self::assertEquals('' . $otherentityid . '', $sharedentities);

        $this->resetAllData();
    }

    /**
     * Test count_potential_available_places
     *
     * @covers \local_mentor_core\session::count_potential_available_places
     */
    public function test_count_potential_available_places() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/enrol/self/externallib.php');

        self::setAdminUser();

        $otherentityname = 'otherentity';
        \local_mentor_core\entity_api::create_entity(['name' => $otherentityname, 'shortname' => $otherentityname]);

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->create_self_enrolment_instance();

        $newusers = [];

        $data = new stdClass();
        $data->maxparticipants = '';
        $session->update($data);

        // Not has max participant data.
        self::assertFalse($session->count_potential_available_places($newusers));

        $data = new stdClass();
        $data->maxparticipants = 3;
        $session->update($data);

        // Not has participant.
        self::assertEquals(3, $session->count_potential_available_places($newusers));

        $newuser = $this->getDataGenerator()->create_user();
        self::setUser($newuser);
        \enrol_self_external::enrol_user($session->get_course()->id, null, $session->get_enrolment_instances_by_type('self')->id);
        self::setAdminUser();

        // Refresh participant cache data.
        $session->get_participants(true);

        // Has one participant.
        self::assertEquals(2, $session->count_potential_available_places($newusers));

        $newusers = [
            [
                'role' => 'concepteur',
                'email' => 'user1@test.gouv.fr',
            ],
            [
                'role' => 'participant',
                'email' => 'user2@test.gouv.fr',
            ],
        ];

        // Has one potential new participant.
        self::assertEquals(1, $session->count_potential_available_places($newusers));

        $newusers = [
            [
                'role' => 'concepteur',
                'email' => $newuser->email,
            ],
            [
                'role' => 'participant',
                'email' => 'user2@test.gouv.fr',
            ],
        ];

        // Has one potential new participant and first participant change role participant to concepteur.
        self::assertEquals(2, $session->count_potential_available_places($newusers));

        $this->resetAllData();
    }

    /**
     * Test get_actions
     *
     * @covers \local_mentor_core\session::get_actions
     */
    public function test_get_actions_ok() {
        global $CFG, $USER, $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        $DB->delete_records('course_categories');

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $sessioncourse = $session->get_entity()->get_edadmin_courses('session');
        $url = $CFG->wwwroot . '/course/view.php?id=' . $sessioncourse['id'];

        $actions = $session->get_actions();

        self::assertCount(4, $actions);

        self::assertArrayHasKey('sessionSheet', $actions);
        self::assertIsArray($actions['sessionSheet']);
        self::assertCount(2, $actions['sessionSheet']);
        self::assertArrayHasKey('url', $actions['sessionSheet']);
        self::assertEquals($session->get_sheet_url()->out() . '&returnto=' . $url, $actions['sessionSheet']['url']);
        self::assertArrayHasKey('tooltip', $actions['sessionSheet']);
        self::assertEquals(get_string('gotosessionsheet', 'local_mentor_core'), $actions['sessionSheet']['tooltip']);

        self::assertArrayHasKey('manageUser', $actions);
        self::assertIsArray($actions['manageUser']);
        self::assertCount(2, $actions['manageUser']);
        self::assertArrayHasKey('url', $actions['manageUser']);
        self::assertEquals($CFG->wwwroot . '/user/index.php?id=' . $session->courseid, $actions['manageUser']['url']);
        self::assertArrayHasKey('tooltip', $actions['manageUser']);
        self::assertEquals(get_string('manageusers', 'local_mentor_core'), $actions['manageUser']['tooltip']);

        self::assertArrayHasKey('importUsers', $actions);
        self::assertIsArray($actions['importUsers']);
        self::assertCount(2, $actions['importUsers']);
        self::assertArrayHasKey('url', $actions['importUsers']);
        self::assertEquals($CFG->wwwroot . '/local/mentor_core/pages/importcsv.php?courseid=' . $session->courseid,
            $actions['importUsers']['url']);
        self::assertArrayHasKey('tooltip', $actions['importUsers']);
        self::assertEquals(get_string('enrolusers', 'local_mentor_core'), $actions['importUsers']['tooltip']);

        self::assertArrayHasKey('deleteSession', $actions);
        self::assertIsArray($actions['deleteSession']);
        self::assertCount(2, $actions['deleteSession']);
        self::assertArrayHasKey('url', $actions['deleteSession']);
        self::assertEquals('', $actions['deleteSession']['url']);
        self::assertArrayHasKey('tooltip', $actions['deleteSession']);
        self::assertEquals(get_string('deletesession', 'local_mentor_core'), $actions['deleteSession']['tooltip']);

        // Create a subentity.
        $subentity = ['parentid' => $session->get_entity()->id, 'name' => 'subentity'];
        \local_mentor_core\entity_api::create_sub_entity($subentity);
        $actions = $session->get_actions($USER->id, true);

        self::assertCount(5, $actions);

        self::assertArrayHasKey('sessionSheet', $actions);
        self::assertIsArray($actions['sessionSheet']);
        self::assertCount(2, $actions['sessionSheet']);
        self::assertArrayHasKey('url', $actions['sessionSheet']);
        self::assertEquals($session->get_sheet_url()->out() . '&returnto=' . $url, $actions['sessionSheet']['url']);
        self::assertArrayHasKey('tooltip', $actions['sessionSheet']);
        self::assertEquals(get_string('gotosessionsheet', 'local_mentor_core'), $actions['sessionSheet']['tooltip']);

        self::assertArrayHasKey('moveSession', $actions);
        self::assertIsArray($actions['moveSession']);
        self::assertCount(2, $actions['moveSession']);
        self::assertArrayHasKey('url', $actions['moveSession']);
        self::assertEquals('', $actions['moveSession']['url']);
        self::assertArrayHasKey('tooltip', $actions['moveSession']);
        self::assertEquals(get_string('movesession', 'local_mentor_core'), $actions['moveSession']['tooltip']);

        self::assertArrayHasKey('manageUser', $actions);
        self::assertIsArray($actions['manageUser']);
        self::assertCount(2, $actions['manageUser']);
        self::assertArrayHasKey('url', $actions['manageUser']);
        self::assertEquals($CFG->wwwroot . '/user/index.php?id=' . $session->courseid, $actions['manageUser']['url']);
        self::assertArrayHasKey('tooltip', $actions['manageUser']);
        self::assertEquals(get_string('manageusers', 'local_mentor_core'), $actions['manageUser']['tooltip']);

        self::assertArrayHasKey('importUsers', $actions);
        self::assertIsArray($actions['importUsers']);
        self::assertCount(2, $actions['importUsers']);
        self::assertArrayHasKey('url', $actions['importUsers']);
        self::assertEquals($CFG->wwwroot . '/local/mentor_core/pages/importcsv.php?courseid=' . $session->courseid,
            $actions['importUsers']['url']);
        self::assertArrayHasKey('tooltip', $actions['importUsers']);
        self::assertEquals(get_string('enrolusers', 'local_mentor_core'), $actions['importUsers']['tooltip']);

        self::assertArrayHasKey('deleteSession', $actions);
        self::assertIsArray($actions['deleteSession']);
        self::assertCount(2, $actions['deleteSession']);
        self::assertArrayHasKey('url', $actions['deleteSession']);
        self::assertEquals('', $actions['deleteSession']['url']);
        self::assertArrayHasKey('tooltip', $actions['deleteSession']);
        self::assertEquals(get_string('deletesession', 'local_mentor_core'), $actions['deleteSession']['tooltip']);

        // Change session status to add cancel session action.
        $session->update_status(\local_mentor_core\session::STATUS_OPENED_REGISTRATION);

        $actions = $session->get_actions();

        self::assertCount(6, $actions);

        self::assertArrayHasKey('sessionSheet', $actions);
        self::assertIsArray($actions['sessionSheet']);
        self::assertCount(2, $actions['sessionSheet']);
        self::assertArrayHasKey('url', $actions['sessionSheet']);
        self::assertEquals($session->get_sheet_url()->out() . '&returnto=' . $url, $actions['sessionSheet']['url']);
        self::assertArrayHasKey('tooltip', $actions['sessionSheet']);
        self::assertEquals(get_string('gotosessionsheet', 'local_mentor_core'), $actions['sessionSheet']['tooltip']);

        self::assertArrayHasKey('moveSession', $actions);
        self::assertIsArray($actions['moveSession']);
        self::assertCount(2, $actions['moveSession']);
        self::assertArrayHasKey('url', $actions['moveSession']);
        self::assertEquals('', $actions['moveSession']['url']);
        self::assertArrayHasKey('tooltip', $actions['moveSession']);
        self::assertEquals(get_string('movesession', 'local_mentor_core'), $actions['moveSession']['tooltip']);

        self::assertArrayHasKey('manageUser', $actions);
        self::assertIsArray($actions['manageUser']);
        self::assertCount(2, $actions['manageUser']);
        self::assertArrayHasKey('url', $actions['manageUser']);
        self::assertEquals($CFG->wwwroot . '/user/index.php?id=' . $session->courseid, $actions['manageUser']['url']);
        self::assertArrayHasKey('tooltip', $actions['manageUser']);
        self::assertEquals(get_string('manageusers', 'local_mentor_core'), $actions['manageUser']['tooltip']);

        self::assertArrayHasKey('importUsers', $actions);
        self::assertIsArray($actions['importUsers']);
        self::assertCount(2, $actions['importUsers']);
        self::assertArrayHasKey('url', $actions['importUsers']);
        self::assertEquals($CFG->wwwroot . '/local/mentor_core/pages/importcsv.php?courseid=' . $session->courseid,
            $actions['importUsers']['url']);
        self::assertArrayHasKey('tooltip', $actions['importUsers']);
        self::assertEquals(get_string('enrolusers', 'local_mentor_core'), $actions['importUsers']['tooltip']);

        self::assertArrayHasKey('cancelSession', $actions);
        self::assertIsArray($actions['cancelSession']);
        self::assertCount(2, $actions['cancelSession']);
        self::assertArrayHasKey('url', $actions['cancelSession']);
        self::assertEquals('', $actions['cancelSession']['url']);
        self::assertArrayHasKey('tooltip', $actions['cancelSession']);
        self::assertEquals(get_string('cancelsession', 'local_mentor_core'), $actions['cancelSession']['tooltip']);

        self::assertArrayHasKey('deleteSession', $actions);
        self::assertIsArray($actions['deleteSession']);
        self::assertCount(2, $actions['deleteSession']);
        self::assertArrayHasKey('url', $actions['deleteSession']);
        self::assertEquals('', $actions['deleteSession']['url']);
        self::assertArrayHasKey('tooltip', $actions['deleteSession']);
        self::assertEquals(get_string('deletesession', 'local_mentor_core'), $actions['deleteSession']['tooltip']);

        $this->resetAllData();
    }

    /**
     * Test disable_self_enrolment_instance
     *
     * @covers \local_mentor_core\session::disable_self_enrolment_instance
     */
    public function test_disable_self_enrolment_instance_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        $getdisableselfenrolmentinstancemethod = self::get_protected_method('disable_self_enrolment_instance');

        // Self enrolment not create.
        self::assertFalse($getdisableselfenrolmentinstancemethod->invoke($session));

        $session->create_self_enrolment_instance();

        // Disable self enrolment.
        self::assertTrue($getdisableselfenrolmentinstancemethod->invoke($session));

        // Enrolment already disable.
        self::assertFalse($getdisableselfenrolmentinstancemethod->invoke($session));

        $this->resetAllData();
    }

    /**
     * Test disable_manual_enrolment_instance
     *
     * @covers \local_mentor_core\session::disable_manual_enrolment_instance
     */
    public function test_disable_manual_enrolment_instance_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->create_manual_enrolment_instance();

        $getdisablemanualenrolmentinstancemethod = self::get_protected_method('disable_manual_enrolment_instance');

        // Disable manual enrolment.
        self::assertTrue($getdisablemanualenrolmentinstancemethod->invoke($session));

        // Enrolment already disabled.
        self::assertTrue($getdisablemanualenrolmentinstancemethod->invoke($session));

        $this->resetAllData();
    }

    /**
     * Test user_is_enrolled
     *
     * @covers \local_mentor_core\session::user_is_enrolled
     */
    public function test_user_is_enrolled_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);
        $courseid = $session->courseid;

        $session->create_manual_enrolment_instance();

        $user = self::getDataGenerator()->create_user();

        self::assertFalse($session->user_is_enrolled($user->id));

        enrol_try_internal_enrol($courseid, $user->id);
        \local_mentor_core\profile_api::role_assign('participant', $user->id, context_course::instance($courseid)->id);

        self::assertTrue($session->user_is_enrolled($user->id));

        $this->resetAllData();
    }

    /**
     * Test user_is_enrolled
     *
     * @covers \local_mentor_core\session::enrol_current_user
     */
    public function test_enrol_current_user_ok() {
        global $CFG;

        $CFG->defaultauth = 'manual';

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        \local_mentor_core\entity_api::create_entity([
            'name' => 'New other entity',
            'shortname' => 'New other entity',
        ]);

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
        $user->profile_field_mainentity = 'New other entity';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        self::setUser($userid);

        // User does not have access to session enrol.
        $enrolmessage = $session->enrol_current_user();

        self::assertIsArray($enrolmessage);
        self::assertCount(3, $enrolmessage);

        self::assertArrayHasKey('status', $enrolmessage);
        self::assertFalse($enrolmessage['status']);

        self::assertArrayHasKey('warnings', $enrolmessage);
        self::assertIsArray($enrolmessage['warnings']);
        self::assertArrayHasKey('message', $enrolmessage['warnings']);
        self::assertEquals(get_string('selfenrolmentnotallowed', 'local_mentor_core'), $enrolmessage['warnings']['message']);

        self::assertArrayHasKey('lang', $enrolmessage);
        self::assertEquals('errorauthorizationselfenrolment', $enrolmessage['lang']);

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
        set_user_preference('auth_forcepasswordchange', 0, $user2);

        // Update sharing.
        self::setAdminUser();
        $session->opento = 'all';
        \local_mentor_core\session_api::update_session($session);

        self::setUser($userid2);

        // User have access to session enrol but self enrolment not create.
        $enrolmessage = $session->enrol_current_user();

        self::assertIsArray($enrolmessage);
        self::assertCount(3, $enrolmessage);

        self::assertArrayHasKey('status', $enrolmessage);
        self::assertFalse($enrolmessage['status']);

        self::assertArrayHasKey('warnings', $enrolmessage);
        self::assertIsArray($enrolmessage['warnings']);
        self::assertArrayHasKey('message', $enrolmessage['warnings']);
        self::assertEquals(get_string('selfenrolmentdisabled', 'local_mentor_core'), $enrolmessage['warnings']['message']);

        self::assertArrayHasKey('lang', $enrolmessage);
        self::assertEquals('errorselfenrolment', $enrolmessage['lang']);

        // Update status.
        self::setAdminUser();
        $session->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        \local_mentor_core\session_api::update_session($session);

        self::setUser($userid2);

        // Enrol does not exist, is create.
        $enrolmessage = $session->enrol_current_user();

        self::assertIsArray($enrolmessage);
        self::assertCount(2, $enrolmessage);

        self::assertArrayHasKey('status', $enrolmessage);
        self::assertTrue($enrolmessage['status']);

        self::assertArrayHasKey('warnings', $enrolmessage);
        self::assertIsArray($enrolmessage['warnings']);
        self::assertEmpty($enrolmessage['warnings']);

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
        $user3->profile_field_mainentity = 'New Entity 1';

        $user3 = local_mentor_core\profile_api::create_user($user3);
        set_user_preference('auth_forcepasswordchange', 0, $user3);

        self::setAdminUser();

        $session->disable_enrolment_instance();

        self::setUser($user3);

        // User have access to session enrol because enrol instance will be reactivated.
        $enrolmessage = $session->enrol_current_user();

        self::assertIsArray($enrolmessage);
        self::assertCount(2, $enrolmessage);

        self::assertArrayHasKey('status', $enrolmessage);
        self::assertTrue($enrolmessage['status']);

        self::assertArrayHasKey('warnings', $enrolmessage);
        self::assertIsArray($enrolmessage['warnings']);
        self::assertEmpty($enrolmessage['warnings']);

        self::setAdminUser();

        $plugin = enrol_get_plugin('self');
        $plugin->delete_instance($session->get_enrolment_instances_by_type('self'));

        self::setUser($user3);

        $enrolmessage = $session->enrol_current_user();

        self::assertIsArray($enrolmessage);
        self::assertCount(2, $enrolmessage);

        self::assertArrayHasKey('status', $enrolmessage);
        self::assertTrue($enrolmessage['status']);

        self::assertArrayHasKey('warnings', $enrolmessage);
        self::assertIsArray($enrolmessage['warnings']);
        self::assertEmpty($enrolmessage['warnings']);

        $this->resetAllData();
    }

    /**
     * Test convert_for_template
     *
     * @covers \local_mentor_core\session::convert_for_template
     */
    public function test_convert_for_template_ok() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);
        $sessiontraining = $session->get_training();
        $sessioncourse = $session->get_course();

        // Create thumbnail.
        $fs = get_file_storage();
        $component = 'local_trainings';
        $itemid = $sessiontraining->id;
        $filearea = 'thumbnail';
        $contextid = $sessiontraining->get_context()->id;
        $filerecord = new stdClass();
        $filerecord->contextid = $contextid;
        $filerecord->component = $component;
        $filerecord->filearea = $filearea;
        $filerecord->itemid = $itemid;
        $filerecord->filepath = '/';
        $filerecord->filename = 'logo.png';
        $filepath = $CFG->dirroot . '/local/mentor_core/pix/logo.png';
        $fs->create_file_from_pathname($filerecord, $filepath);

        $now = time();
        // Set Date Time Zone at France.
        $dtz = new \DateTimeZone('Europe/Paris');
        $nowdatetime = new \DateTime("@$now");
        $nowdatetime->setTimezone($dtz);
        $nowdate = $nowdatetime->format('d/m/Y');

        $nowplusoneday = $now + 86400;
        $nowplusonedaydatetime = new \DateTime("@$nowplusoneday");
        $nowplusonedaydatetime->setTimezone($dtz);
        $nowplusonedaydate = $nowplusonedaydatetime->format('d/m/Y');

        // Add start and en date session.
        $data = new stdClass();
        $data->sessionstartdate = $now;
        $data->sessionenddate = $nowplusoneday;
        $session->update($data);

        $datatempalte = $session->convert_for_template();

        self::assertIsObject($datatempalte);

        self::assertObjectHasAttribute('id', $datatempalte);
        self::assertEquals($session->id, $datatempalte->id);

        self::assertObjectHasAttribute('fullname', $datatempalte);
        self::assertEquals($session->fullname, $datatempalte->fullname);

        self::assertObjectHasAttribute('status', $datatempalte);
        self::assertEquals($session->status, $datatempalte->status);

        self::assertObjectHasAttribute('placesavailable', $datatempalte);
        self::assertEquals('', $datatempalte->placesavailable);

        self::assertObjectHasAttribute('istrainer', $datatempalte);
        self::assertTrue($datatempalte->istrainer);

        self::assertObjectHasAttribute('istutor', $datatempalte);
        self::assertFalse($datatempalte->istutor);

        self::assertObjectHasAttribute('isparticipant', $datatempalte);
        self::assertFalse($datatempalte->isparticipant);

        self::assertObjectHasAttribute('trainingid', $datatempalte);
        self::assertEquals($sessiontraining->id, $datatempalte->trainingid);

        self::assertObjectHasAttribute('thumbnail', $datatempalte);
        self::assertEquals(\moodle_url::make_pluginfile_url(
            $filerecord->contextid,
            $filerecord->component,
            $filerecord->filearea,
            $filerecord->itemid,
            $filerecord->filepath,
            $filerecord->filename
        )->out(), $datatempalte->thumbnail);

        self::assertObjectHasAttribute('sessionstartdate', $datatempalte);
        self::assertEquals($nowdate, $datatempalte->sessionstartdate);

        self::assertObjectHasAttribute('sessionenddate', $datatempalte);
        self::assertEquals($nowplusonedaydate, $datatempalte->sessionenddate);

        self::assertObjectHasAttribute('placesnotlimited', $datatempalte);
        self::assertTrue($datatempalte->placesnotlimited);

        self::assertObjectHasAttribute('sessiononedaydate', $datatempalte);
        self::assertFalse($datatempalte->sessiononedaydate);

        self::assertObjectHasAttribute('courseurl', $datatempalte);
        self::assertEquals($CFG->wwwroot . '/course/view.php?id=' . $sessioncourse->id, $datatempalte->courseurl);

        self::assertObjectHasAttribute('isenrol', $datatempalte);
        self::assertFalse($datatempalte->isenrol);

        self::assertObjectHasAttribute('isinpreparation', $datatempalte);
        self::assertTrue($datatempalte->isinpreparation);
        self::assertObjectNotHasAttribute('isopenedregistration', $datatempalte);
        self::assertObjectNotHasAttribute('isinprogress', $datatempalte);
        self::assertObjectNotHasAttribute('completed', $datatempalte);
        self::assertObjectNotHasAttribute('isarchived', $datatempalte);
        self::assertObjectNotHasAttribute('isreported', $datatempalte);
        self::assertObjectNotHasAttribute('iscanceled', $datatempalte);

        // Add max participant.
        $data = new stdClass();
        $data->maxparticipants = 12;
        $session->update($data);

        $datatempalte = $session->convert_for_template();
        self::assertObjectHasAttribute('placesavailable', $datatempalte);
        self::assertEquals(12, $datatempalte->placesavailable);

        self::assertObjectHasAttribute('placesnotlimited', $datatempalte);
        self::assertFalse($datatempalte->placesnotlimited);

        // Update status (to open registration).
        $session->update_status(\local_mentor_core\session::STATUS_OPENED_REGISTRATION);
        $datatempalte = $session->convert_for_template();

        self::assertObjectNotHasAttribute('isinpreparation', $datatempalte);
        self::assertObjectHasAttribute('isopenedregistration', $datatempalte);
        self::assertTrue($datatempalte->isopenedregistration);
        self::assertObjectNotHasAttribute('isinprogress', $datatempalte);
        self::assertObjectNotHasAttribute('completed', $datatempalte);
        self::assertObjectNotHasAttribute('isarchived', $datatempalte);
        self::assertObjectNotHasAttribute('isreported', $datatempalte);
        self::assertObjectNotHasAttribute('iscanceled', $datatempalte);

        // Update status (to in progress).
        $session->update_status(\local_mentor_core\session::STATUS_IN_PROGRESS);
        $datatempalte = $session->convert_for_template();

        self::assertObjectNotHasAttribute('isinpreparation', $datatempalte);
        self::assertObjectNotHasAttribute('isopenedregistration', $datatempalte);
        self::assertObjectHasAttribute('isinprogress', $datatempalte);
        self::assertTrue($datatempalte->isinprogress);
        self::assertObjectNotHasAttribute('completed', $datatempalte);
        self::assertObjectNotHasAttribute('isarchived', $datatempalte);
        self::assertObjectNotHasAttribute('isreported', $datatempalte);
        self::assertObjectNotHasAttribute('iscanceled', $datatempalte);

        // Update status (to completed).
        $session->update_status(\local_mentor_core\session::STATUS_COMPLETED);
        $datatempalte = $session->convert_for_template();

        self::assertObjectNotHasAttribute('isinpreparation', $datatempalte);
        self::assertObjectNotHasAttribute('isopenedregistration', $datatempalte);
        self::assertObjectNotHasAttribute('isinprogress', $datatempalte);
        self::assertObjectHasAttribute('completed', $datatempalte);
        self::assertTrue($datatempalte->completed);
        self::assertObjectNotHasAttribute('isarchived', $datatempalte);
        self::assertObjectNotHasAttribute('isreported', $datatempalte);
        self::assertObjectNotHasAttribute('iscanceled', $datatempalte);

        // Update status (to archived).
        $session->update_status(\local_mentor_core\session::STATUS_ARCHIVED);
        $datatempalte = $session->convert_for_template();

        self::assertObjectNotHasAttribute('isinpreparation', $datatempalte);
        self::assertObjectNotHasAttribute('isopenedregistration', $datatempalte);
        self::assertObjectNotHasAttribute('isinprogress', $datatempalte);
        self::assertObjectNotHasAttribute('completed', $datatempalte);
        self::assertObjectHasAttribute('isarchived', $datatempalte);
        self::assertTrue($datatempalte->isarchived);
        self::assertObjectNotHasAttribute('isreported', $datatempalte);
        self::assertObjectNotHasAttribute('iscanceled', $datatempalte);

        // Update status (to reported).
        $session->update_status(\local_mentor_core\session::STATUS_REPORTED);
        $datatempalte = $session->convert_for_template();

        self::assertObjectNotHasAttribute('isinpreparation', $datatempalte);
        self::assertObjectNotHasAttribute('isopenedregistration', $datatempalte);
        self::assertObjectNotHasAttribute('isinprogress', $datatempalte);
        self::assertObjectNotHasAttribute('completed', $datatempalte);
        self::assertObjectNotHasAttribute('isarchived', $datatempalte);
        self::assertObjectHasAttribute('isreported', $datatempalte);
        self::assertTrue($datatempalte->isreported);
        self::assertObjectNotHasAttribute('iscanceled', $datatempalte);

        // Update status (to cancelled).
        $session->update_status(\local_mentor_core\session::STATUS_CANCELLED);
        $datatempalte = $session->convert_for_template();

        self::assertObjectNotHasAttribute('isinpreparation', $datatempalte);
        self::assertObjectNotHasAttribute('isopenedregistration', $datatempalte);
        self::assertObjectNotHasAttribute('isinprogress', $datatempalte);
        self::assertObjectNotHasAttribute('completed', $datatempalte);
        self::assertObjectNotHasAttribute('isarchived', $datatempalte);
        self::assertObjectNotHasAttribute('isreported', $datatempalte);
        self::assertObjectHasAttribute('iscanceled', $datatempalte);
        self::assertTrue($datatempalte->iscanceled);

        $this->resetAllData();
    }

    /**
     * Test is_shared
     *
     * @covers \local_mentor_core\session::is_shared
     */
    public function test_is_shared_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        // Not shared.
        self::assertFalse($session->is_shared());

        // Open to all entities.
        $data = new stdClass();
        $data->opento = 'all';
        $session->update($data);

        self::assertTrue($session->is_shared());

        // Shared to other entity.
        $newentityid2 = \local_mentor_core\entity_api::create_entity(['name' => 'New Entity 2', 'shortname' => 'New Entity 2']);
        $newentityid3 = \local_mentor_core\entity_api::create_entity(['name' => 'New Entity 3', 'shortname' => 'New Entity 3']);

        $data = new stdClass();
        $data->opento = 'other_entities';
        $data->opentolist = [$newentityid2];
        $session->update($data);

        self::assertEquals(1, $session->is_shared());

        $data = new stdClass();
        $data->opento = 'other_entities';
        $data->opentolist = [$newentityid2, $newentityid3];
        $session->update($data);

        self::assertEquals(2, $session->is_shared());

        // Not shared and not visible.
        $data = new stdClass();
        $data->opento = 'not_visible';
        $session->update($data);

        self::assertFalse($session->is_shared());

        $this->resetAllData();
    }

    /**
     * Test is_available_to_user
     *
     * @covers \local_mentor_core\session::is_available_to_user
     */
    public function test_is_available_to_user_ok() {
        global $CFG;

        $CFG->defaultauth = 'manual';

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = \local_mentor_core\session_api::get_session($sessionid);

        // Update sharing.
        $session->opento = 'current_entity';
        \local_mentor_core\session_api::update_session($session);

        // User with 0 id.
        self::assertFalse($session->is_available_to_user(0));

        // Update sharing.
        $session->opento = 'all';
        \local_mentor_core\session_api::update_session($session);

        // Is user admin.
        self::assertTrue($session->is_available_to_user());

        $otherentityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New other entity',
            'shortname' => 'New other entity',
        ]);
        $otherentity = \local_mentor_core\entity_api::get_entity($otherentityid);
        // Create user with main entity in other entity.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'test@test.com';
        $user->username = 'testusername';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = 'New other entity';

        $userid = local_mentor_core\profile_api::create_user($user);
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //Due to test prepuces, we will override the main entity of the user and set it as we need 
        $profile = \local_mentor_core\profile_api::get_profile($userid);
        $profile->set_main_entity($otherentity);
        $otherentity->add_member($user);

        set_user_preference('auth_forcepasswordchange', 0, $user);

        // Open to all entities.
        $data = new stdClass();
        $data->opento = 'all';
        $session->update($data);

        // Is open to all.
        self::assertTrue($session->is_available_to_user($userid));

        // Shared to other entity.
        $data = new stdClass();
        $data->opento = 'other_entities';
        $data->opentolist = [$otherentityid];
        $session->update($data);

        // Is shared to user main entity.
        self::assertTrue($session->is_available_to_user($userid));

        // Open to current entity.
        $data = new stdClass();
        $data->opento = 'current_entity';
        $session->update($data);

        // Not access session.
        self::assertFalse($session->is_available_to_user($userid));

        // Create user with main entity in session entity.
        $user2 = new stdClass();
        $user2->lastname = 'lastname2';
        $user2->firstname = 'firstname2';
        $user2->email = 'test2@test.com';
        $user2->username = 'testusername2';
        $user2->password = 'to be generated';
        $user2->mnethostid = 1;
        $user2->confirmed = 1;
        $user2->auth = 'manual';
        $user2->profile_field_mainentity = $session->get_entity()->name;

        $userid2 = local_mentor_core\profile_api::create_user($user2);
                //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //Due to test prepuces, we will override the main entity of the user and set it as we need 
        $profile = \local_mentor_core\profile_api::get_profile($userid2);
        $profile->set_main_entity($session->get_entity());
        $session->get_entity()->add_member($user2);
        set_user_preference('auth_forcepasswordchange', 0, $user2);

        // Access session.
        self::assertTrue($session->is_available_to_user($userid2));

        $this->resetAllData();
    }

    /**
     * Test update
     *
     * @covers \local_mentor_core\session::update
     */
    public function test_session_class_update_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);

        $data = new \stdClass();
        $data->shortname = 'newshortname';
        $data->fullname = 'newfullname';
        $data->status = \local_mentor_core\session::STATUS_COMPLETED;
        $data->sessionstartdate = time();
        $data->maxparticipants = 30;
        $data->sessionenddate = time() + 3600;

        $session->create_self_enrolment_instance();
        $session->update($data);

        self::assertEquals($data->shortname, $session->courseshortname);
        self::assertEquals($data->fullname, $session->get_course(true)->fullname);
        self::assertEquals($data->status, $session->status);
        self::assertEquals($data->sessionstartdate, $session->sessionstartdate);
        self::assertEquals($data->maxparticipants, $session->maxparticipants);
        self::assertEquals($data->sessionenddate, $session->sessionenddate);

        $this->resetAllData();
    }

    /**
     * Test get_available_places
     *
     * @covers \local_mentor_core\session::get_available_places
     */
    public function test_get_available_places_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);

        self::assertEquals('', $session->get_available_places());

        $data = new \stdClass();
        $data->maxparticipants = 30;

        $session->update($data);

        self::assertEquals(30, $session->get_available_places());

        $this->resetAllData();
    }

    /**
     * Test duplicate_as_new_training not ok
     *
     * @covers \local_mentor_core\session::duplicate_as_new_training
     */
    public function test_duplicate_as_new_training_nok() {
        global $DB;
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);
        $course = $session->get_course();
        $backup = $session->generate_backup();

        // Create Training mock.
        $sessionmock = $this->getMockBuilder('\local_mentor_core\session')
            ->setMethods(['generate_backup'])
            ->setConstructorArgs([$sessionid])
            ->getMock();

        // Return false value one time when generate_backup function call.
        $sessionmock->expects($this->exactly(1))
            ->method('generate_backup')
            ->willReturnOnConsecutiveCalls(
                false
            );

        try {
            $sessionmock->duplicate_as_new_training('trainingfullname', 'trainingshortname', $sessionmock->get_entity()->id);
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
            self::assertEquals('Backup file not created', $e->getMessage());
        }

        $this->resetAllData();
    }

    /**
     * Test get_user_favourite_data
     *
     * @covers \local_mentor_core\session::get_user_favourite_data
     */
    public function test_get_user_favourite_data() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);
        $user = self::getDataGenerator()->create_user();

        self::assertFalse($session->get_user_favourite_data($user->id));

        \local_mentor_core\session_api::add_user_favourite_session($sessionid, $user->id);

        $userfavourite = $session->get_user_favourite_data($user->id);
        self::assertIsObject($userfavourite);
        self::assertEquals($userfavourite->component, 'local_session');
        self::assertEquals($userfavourite->itemtype, 'favourite_session');
        self::assertEquals($userfavourite->itemid, $session->id);
        self::assertEquals($userfavourite->contextid, $session->get_context()->id);
        self::assertEquals($userfavourite->userid, $user->id);

        $this->resetAllData();
    }

    /**
     * Test has_enroll_user_enabled
     *
     * @covers \local_mentor_core\session::has_enroll_user_enabled
     */
    public function test_has_enroll_user_enabled() {
        global $DB, $USER, $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);
        $session->sessionstartdate = time();
        $session->update($session);
        $session->create_self_enrolment_instance();
        $session->create_manual_enrolment_instance();
        $course = $session->get_course();

        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        // No enrol exist.
        self::assertFalse($session->has_enroll_user_enabled());

        // Manual enrol user.
        self::getDataGenerator()->enrol_user($USER->id, $course->id, 'participant');

        // User is enrol.
        self::assertTrue($session->has_enroll_user_enabled());

        // Disable enrol user.
        $enroluserinstance = $DB->get_record_sql('
            SELECT ue.*
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            WHERE e.enrol = \'manual\' AND
                e.courseid = :courseid AND
                ue.userid = :userid
        ', ['courseid' => $course->id, 'userid' => $USER->id]);
        $enroluserinstance->status = 1;
        $DB->update_record('user_enrolments', $enroluserinstance);

        // User is enrol but enrol is disable.
        self::assertFalse($session->has_enroll_user_enabled());

        // Create new enrol : self enrol.
        $session->create_self_enrolment_instance();
        self::getDataGenerator()->enrol_user($USER->id, $course->id, 'participant', 'self');

        // Manual enrol is disable, but self enrol is enable.
        self::assertTrue($session->has_enroll_user_enabled());

        self::resetAllData();
    }

    /**
     * Test get_editors
     *
     * @covers \local_mentor_core\session::get_editors
     */
    public function test_get_editors() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);
        $session->create_manual_enrolment_instance();
        $session->sessionstartdate = time();
        $session->update($session);
        $sessioncourse = $session->get_course();
        $entity = $session->get_entity()->get_main_entity();
        $entitycontext = $entity->get_context();

        $users = [];
        for ($i = 1; $i < 12; $i++) {
            $users[$i] = self::getDataGenerator()->create_user(['username' => 'user' . $i]);
        }

        // Entity context.
        $admindedie = $DB->get_record('role', ['shortname' => 'admindedie']);
        $coursecreator = $DB->get_record('role', ['shortname' => 'coursecreator']);
        $respformation = $DB->get_record('role', ['shortname' => 'respformation']);
        $referentlocal = $DB->get_record('role', ['shortname' => 'referentlocal']);
        $reflocalnonediteur = $DB->get_record('role', ['shortname' => 'reflocalnonediteur']);
        self::getDataGenerator()->role_assign($admindedie->id, $users[1]->id, $entitycontext->id);
        self::getDataGenerator()->role_assign($coursecreator->id, $users[2]->id, $entitycontext->id);
        self::getDataGenerator()->role_assign($respformation->id, $users[3]->id, $entitycontext->id);
        self::getDataGenerator()->role_assign($referentlocal->id, $users[4]->id, $entitycontext->id);
        self::getDataGenerator()->role_assign($reflocalnonediteur->id, $users[5]->id, $entitycontext->id);

        // Session context.
        $participant = $DB->get_record('role', ['shortname' => 'participant']);
        $participantnonediteur = $DB->get_record('role', ['shortname' => 'participantnonediteur']);
        $concepteur = $DB->get_record('role', ['shortname' => 'concepteur']);
        $formateur = $DB->get_record('role', ['shortname' => 'formateur']);
        $tuteur = $DB->get_record('role', ['shortname' => 'tuteur']);
        $participantdemonstration = $DB->get_record('role', ['shortname' => 'participantdemonstration']);
        self::getDataGenerator()->enrol_user($users[6]->id, $sessioncourse->id, $participant->id);
        self::getDataGenerator()->enrol_user($users[7]->id, $sessioncourse->id, $participantnonediteur->id);
        self::getDataGenerator()->enrol_user($users[8]->id, $sessioncourse->id, $concepteur->id);
        self::getDataGenerator()->enrol_user($users[9]->id, $sessioncourse->id, $formateur->id);
        self::getDataGenerator()->enrol_user($users[10]->id, $sessioncourse->id, $tuteur->id);
        self::getDataGenerator()->enrol_user($users[11]->id, $sessioncourse->id, $participantdemonstration->id);

        $editors = $session->get_editors();
        self::assertCount(5, $editors);
        $editorsname = array_map(function($e) {
            return $e->username;
        }, $editors);
        self::assertContains($users[1]->username, $editorsname);
        self::assertNotContains($users[2]->username, $editorsname);
        self::assertContains($users[3]->username, $editorsname);
        self::assertContains($users[4]->username, $editorsname);
        self::assertNotContains($users[5]->username, $editorsname);
        self::assertNotContains($users[6]->username, $editorsname);
        self::assertNotContains($users[7]->username, $editorsname);
        self::assertContains($users[8]->username, $editorsname);
        self::assertContains($users[9]->username, $editorsname);
        self::assertNotContains($users[10]->username, $editorsname);
        self::assertNotContains($users[11]->username, $editorsname);

        self::resetAllData();
    }

    /**
     * Test get_all_users
     *
     * @covers \local_mentor_core\session::get_all_users
     */
    public function test_get_all_users() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);
        $session->create_manual_enrolment_instance();
        $session->sessionstartdate = time();
        $session->update($session);
        $sessioncourse = $session->get_course();
        $entity = $session->get_entity()->get_main_entity();
        $entitycontext = $entity->get_context();

        $users = [];
        for ($i = 1; $i < 12; $i++) {
            $users[$i] = self::getDataGenerator()->create_user(['username' => 'user' . $i]);
        }

        // Entity context.
        $admindedie = $DB->get_record('role', ['shortname' => 'admindedie']);
        $coursecreator = $DB->get_record('role', ['shortname' => 'coursecreator']);
        $respformation = $DB->get_record('role', ['shortname' => 'respformation']);
        $referentlocal = $DB->get_record('role', ['shortname' => 'referentlocal']);
        $reflocalnonediteur = $DB->get_record('role', ['shortname' => 'reflocalnonediteur']);
        self::getDataGenerator()->role_assign($admindedie->id, $users[1]->id, $entitycontext->id);
        self::getDataGenerator()->role_assign($coursecreator->id, $users[2]->id, $entitycontext->id);
        self::getDataGenerator()->role_assign($respformation->id, $users[3]->id, $entitycontext->id);
        self::getDataGenerator()->role_assign($referentlocal->id, $users[4]->id, $entitycontext->id);
        self::getDataGenerator()->role_assign($reflocalnonediteur->id, $users[5]->id, $entitycontext->id);

        // Session context.
        $participant = $DB->get_record('role', ['shortname' => 'participant']);
        $participantnonediteur = $DB->get_record('role', ['shortname' => 'participantnonediteur']);
        $concepteur = $DB->get_record('role', ['shortname' => 'concepteur']);
        $formateur = $DB->get_record('role', ['shortname' => 'formateur']);
        $tuteur = $DB->get_record('role', ['shortname' => 'tuteur']);
        $participantdemonstration = $DB->get_record('role', ['shortname' => 'participantdemonstration']);
        self::getDataGenerator()->enrol_user($users[6]->id, $sessioncourse->id, $participant->id);
        self::getDataGenerator()->enrol_user($users[7]->id, $sessioncourse->id, $participantnonediteur->id);
        self::getDataGenerator()->enrol_user($users[8]->id, $sessioncourse->id, $concepteur->id);
        self::getDataGenerator()->enrol_user($users[9]->id, $sessioncourse->id, $formateur->id);
        self::getDataGenerator()->enrol_user($users[10]->id, $sessioncourse->id, $tuteur->id);
        self::getDataGenerator()->enrol_user($users[11]->id, $sessioncourse->id, $participantdemonstration->id);

        $allusers = $session->get_all_users();
        self::assertCount(9, $allusers);
        $allusersname = array_map(function($e) {
            return $e->username;
        }, $allusers);

        self::assertContains($users[1]->username, $allusersname);
        self::assertNotContains($users[2]->username, $allusersname);
        self::assertContains($users[3]->username, $allusersname);
        self::assertContains($users[4]->username, $allusersname);
        self::assertNotContains($users[5]->username, $allusersname);
        self::assertContains($users[6]->username, $allusersname);
        self::assertContains($users[7]->username, $allusersname);
        self::assertContains($users[8]->username, $allusersname);
        self::assertContains($users[9]->username, $allusersname);
        self::assertContains($users[10]->username, $allusersname);
        self::assertContains($users[11]->username, $allusersname);

        self::resetAllData();
    }

    /**
     * Test get_all_group
     *
     * @covers \local_mentor_core\session::get_all_group
     */
    public function test_get_all_group() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);

        self::assertEmpty($session->get_all_group());

        $group = new stdClass();
        $group->name = 'testgroup';
        $group->courseid = $session->get_course()->id;

        self::getDataGenerator()->create_group($group);

        $sessiongroups = $session->get_all_group();

        self::assertCount(1, $sessiongroups);
        $newgroupe = current($sessiongroups);
        self::assertEquals($newgroupe->name, 'testgroup');
        self::assertEquals($newgroupe->courseid, $session->get_course()->id);

        self::resetAllData();
    }

    /**
     * Test get_tutors
     *
     * @covers \local_mentor_core\session::get_tutors
     */
    public function test_get_tutors() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);
        $session->create_manual_enrolment_instance();
        $session->sessionstartdate = time();
        $session->update($session);
        $course = $session->get_course();
        $user = self::getDataGenerator()->create_user();

        self::assertEmpty($session->get_tutors());

        self::getDataGenerator()->enrol_user($user->id, $course->id, \local_mentor_specialization\mentor_profile::ROLE_TUTEUR);

        $tutors = $session->get_tutors();

        self::assertCount(1, $tutors);
        self::assertArrayHasKey($user->id, $tutors);

        self::resetAllData();
    }

    /**
     * Test get_formateurs
     *
     * @covers \local_mentor_core\session::get_formateurs
     */
    public function test_get_formateurs() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);
        $session->create_manual_enrolment_instance();
        $session->sessionstartdate = time();
        $session->update($session);
        $course = $session->get_course();
        $user = self::getDataGenerator()->create_user();

        self::assertEmpty($session->get_formateurs());

        self::getDataGenerator()->enrol_user($user->id, $course->id, \local_mentor_specialization\mentor_profile::ROLE_FORMATEUR);

        $formateurs = $session->get_formateurs();

        self::assertCount(1, $formateurs);
        self::assertArrayHasKey($user->id, $formateurs);

        self::resetAllData();
    }

    /**
     * Test get_demonstrateurs
     *
     * @covers \local_mentor_core\session::get_demonstrateurs
     */
    public function test_get_demonstrateurs() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $sessionid = $this->init_session_creation();
        $session = new \local_mentor_core\session($sessionid);
        $session->create_manual_enrolment_instance();
        $session->sessionstartdate = time();
        $session->update($session);
        $course = $session->get_course();
        $user = self::getDataGenerator()->create_user();

        self::assertEmpty($session->get_demonstrateurs());

        self::getDataGenerator()
            ->enrol_user($user->id, $course->id, \local_mentor_specialization\mentor_profile::ROLE_PARTICIPANTDEMONSTRATION);

        $demonstrateurs = $session->get_demonstrateurs();

        self::assertCount(1, $demonstrateurs);
        self::assertArrayHasKey($user->id, $demonstrateurs);

        self::resetAllData();
    }
}
