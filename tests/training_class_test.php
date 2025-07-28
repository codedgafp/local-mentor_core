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
require_once($CFG->dirroot . '/local/mentor_core/classes/model/training.php');

class local_mentor_core_training_class_testcase extends advanced_testcase {

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

    public function create_entity($entityname) {
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        return \local_mentor_core\entity_api::get_entity($entityid);
    }

    public function create_training($entity) {

        $trainingdata = new stdClass();
        $trainingdata->name = 'fullname';
        $trainingdata->shortname = 'shortname';
        $trainingdata->content = 'summary';
        $trainingdata->traininggoal = 'TEST TRAINING';
        $trainingdata->thumbnail = '';
        $trainingdata->status = \local_mentor_core\training::STATUS_DRAFT;
        $formationid = $entity->get_entity_formation_category();
        $trainingdata->categorychildid = $formationid;
        $trainingdata->categoryid = $entity->id;
        $trainingdata->creativestructure = $entity->id;
        return \local_mentor_core\training_api::create_training($trainingdata);
    }

    /**
     * Init training object
     *
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function get_training_data($entitydata = null) {

        if ($entitydata === null) {
            $entitydata = $this->get_entities_data()[0];
        }

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
     * Test restore backup
     *
     * @covers \local_mentor_core\training::restore_backup
     * @covers \local_mentor_core\training::generate_backup
     */
    public function test_restore_backup_ok() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        $backupfile = $training->generate_backup();

        // Define a new restore dir.
        $CFG->mentorbackuproot = $CFG->dataroot . '/temp/backupunittests';

        if (is_dir($CFG->mentorbackuproot)) {
            rmdir($CFG->mentorbackuproot);
        }

        self::assertTrue($training->restore_backup($backupfile));

        self::resetAllData();
    }

    /**
     * Test restore backup not ok
     *
     * @covers \local_mentor_core\training::restore_backup
     */
    public function test_restore_backup_nok() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        $backupfile = $training->generate_backup();
        $dirname = basename($backupfile->get_filename(), '.mbz');

        // Create backup file Mock.
        $backupfilemock = $this->createMock(get_class($backupfile));
        $backupfilemock->expects($this->once())
            ->method('get_filename')
            ->will($this->returnValue($backupfile->get_filename()));

        // Return false one time when call extract_to_pathname function.
        $backupfilemock->expects($this->once())
            ->method('extract_to_pathname')
            ->will($this->returnValue(false));

        try {
            $training->restore_backup($backupfilemock);
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
            self::assertEquals($e->getMessage(), 'extract error in folder : ' . $CFG->backuptempdir . '/' . $dirname);
        }

        self::resetAllData();
    }

    /**
     * Test is manager
     *
     * @covers \local_mentor_core\training::is_manager
     */
    public function test_is_manager_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        \local_mentor_core\profile_api::create_and_add_user('lastname', 'firstname', 'user@gouv.fr', null, null, 'manual');
        $user = \core_user::get_user_by_email('user@gouv.fr');

        // Is not training manager.
        self::assertFalse($training->is_manager($user->id));

        $entity->assign_manager($user->id);

        // Is training manager.
        self::assertTrue($training->is_manager($user->id));

        self::resetAllData();
    }

    /**
     * Test is updater
     *
     * @covers \local_mentor_core\training::is_updater
     */
    public function test_is_updater_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        \local_mentor_core\profile_api::create_and_add_user('lastname', 'firstname', 'user@gouv.fr', null, null, 'manual');
        $user = \core_user::get_user_by_email('user@gouv.fr');
var_dump($training->is_updater($user->id));
        // Is not training updater.
        self::assertFalse($training->is_updater($user->id));

        $entity->assign_manager($user->id);

        // Is training updater.
        self::assertTrue($training->is_updater($user->id));

        self::resetAllData();
    }

    /**
     * Test is deleter
     *
     * @covers \local_mentor_core\training::is_deleter
     */
    public function test_is_deleter_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        \local_mentor_core\profile_api::create_and_add_user('lastname', 'firstname', 'user@gouv.fr', null, null, 'manual');
        $user = \core_user::get_user_by_email('user@gouv.fr');

        // Is not training deleter.
        self::assertFalse($training->is_deleter($user->id));

        $entity->assign_manager($user->id);

        // Is training deleter.
        self::assertTrue($training->is_deleter($user->id));

        self::resetAllData();
    }

    /**
     * Test get_enrolment_instances
     *
     * @covers \local_mentor_core\training::get_enrolment_instances
     */
    public function test_get_enrolment_instances_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        \local_mentor_core\profile_api::create_and_add_user('lastname', 'firstname', 'user@gouv.fr', null, null, 'manual');

        // 3 enrolment instances.
        self::assertCount(3, $training->get_enrolment_instances());

        // Remove manual enrolment instance.
        $manualenrolment = $training->get_enrolment_instances_by_type('manual');
        $plugin = enrol_get_plugin('manual');
        $plugin->delete_instance($manualenrolment);

        // 2 enrolment instance.
        self::assertCount(2, $training->get_enrolment_instances());

        self::resetAllData();
    }

    /**
     * Test get enrolment instances by type
     *
     * @covers \local_mentor_core\training::get_enrolment_instances_by_type
     */
    public function test_get_enrolment_instances_by_type_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        \local_mentor_core\profile_api::create_and_add_user('lastname', 'firstname', 'user@gouv.fr', null, null, 'manual');

        // Manual enrolment instance exist.
        $manualenrolment = $training->get_enrolment_instances_by_type('manual');
        self::assertEquals('manual', $manualenrolment->enrol);
        self::assertEquals($training->get_course()->id, $manualenrolment->courseid);

        // Delete manual enrolment instance.
        $plugin = enrol_get_plugin('manual');
        $plugin->delete_instance($manualenrolment);

        // Manual enrolment instance not exist.
        self::assertFalse($training->get_enrolment_instances_by_type('manual'));

        self::resetAllData();
    }

    /**
     * Test update enrolment instance
     *
     * @covers \local_mentor_core\training::update_enrolment_instance
     */
    public function test_update_enrolment_instance_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        \local_mentor_core\profile_api::create_and_add_user('lastname', 'firstname', 'user@gouv.fr', null, null, 'manual');

        // Manual enrolment instance exist.
        $manualenrolment = $training->get_enrolment_instances_by_type('manual');
        self::assertEquals('manual', $manualenrolment->enrol);
        self::assertEquals($training->get_course()->id, $manualenrolment->courseid);
        self::assertEquals('0', $manualenrolment->status);

        // Update enrolment instance (disable).
        $manualenrolment->status = '1';
        $training->update_enrolment_instance($manualenrolment);

        // Status is udpdated.
        $manualenrolment = $training->get_enrolment_instances_by_type('manual');
        self::assertEquals('1', $manualenrolment->status);

        self::resetAllData();
    }

    /**
     * Test enable manual enrolment instance
     *
     * @covers \local_mentor_core\training::create_manual_enrolment_instance
     * @covers \local_mentor_core\training::enable_manual_enrolment_instance
     */
    public function test_enable_manual_enrolment_instance_ok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        \local_mentor_core\profile_api::create_and_add_user('lastname', 'firstname', 'user@gouv.fr', null, null, 'manual');

        // Manual enrolment instance exist.
        self::assertTrue($training->create_manual_enrolment_instance());

        // Updated manual enrolment (disable).
        $manualenrolment = $training->get_enrolment_instances_by_type('manual');
        $manualenrolment->status = '1';
        $training->update_enrolment_instance($manualenrolment);

        // Manual enrolment instance and update status to enable.
        self::assertTrue($training->create_manual_enrolment_instance());

        $plugin = enrol_get_plugin('manual');
        $plugin->delete_instance($manualenrolment);

        // Create new manual instance (return instance id).
        self::assertIsInt($training->create_manual_enrolment_instance());

        self::resetAllData();
    }

    /**
     * Test enable manual enrolment instance with 'manual' instance not ok
     *
     * @covers \local_mentor_core\training::enable_manual_enrolment_instance
     */
    public function test_enable_manual_enrolment_instance_nok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        // Create Training mock.
        $trainingmock = $this->getMockBuilder('\local_mentor_core\training')
            ->setMethods(['get_enrolment_instances_by_type'])
            ->setConstructorArgs([$training->id])
            ->getMock();

        // Return false value one time when get_enrolment_instances_by_type function call.
        // with first argument is 'manual'.
        $trainingmock->expects($this->once())
            ->method('get_enrolment_instances_by_type')
            ->with('manual')
            ->will($this->returnValue(false));

        // Use ReflectionClass to call protected function.
        $class = new ReflectionClass('\local_mentor_core\training');
        $method = $class->getMethod('enable_manual_enrolment_instance');
        $method->setAccessible(true);

        // Manual enrolment instance not exist.
        self::assertFalse($method->invokeArgs($trainingmock, []));

        self::resetAllData();
    }

    /**
     * Test get_training_picture
     *
     * @covers \local_mentor_core\training::get_training_picture
     */
    public function test_get_training_picture_ok() {
        global $CFG;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        self::assertFalse($training->get_training_picture());

        $fs = get_file_storage();

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

        $thumbnailtraining = $training->get_training_picture();

        self::assertEquals($component, $thumbnailtraining->get_component());
        self::assertEquals($itemid, $thumbnailtraining->get_itemid());
        self::assertEquals($filearea, $thumbnailtraining->get_filearea());
        self::assertEquals($contextid, $thumbnailtraining->get_contextid());
        self::assertEquals($training->id, $thumbnailtraining->get_itemid());

        // Not allowed area.
        $othernotexitpicturetraining = $training->get_training_picture('notexistpicture');
        self::assertFalse($othernotexitpicturetraining);

        self::resetAllData();
    }

    /**
     * Test get_course ok
     *
     * @covers \local_mentor_core\training::get_course
     */
    public function test_get_course_ok() {
        global $DB;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        self::assertEquals($training->get_course()->shortname, $training->shortname);

        self::resetAllData();
    }

    /**
     * Test get_course nok
     *
     * @covers \local_mentor_core\training::get_course
     */
    public function test_get_course_nok() {

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        // Create database interface Mock.
        $dbinterfacemock = $this->getMockBuilder('\local_mentor_core\database_interface')
            ->setMethods(['get_course_by_shortname'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return false value when get_course_by_shortname function call.
        $dbinterfacemock->expects($this->any())
            ->method('get_course_by_shortname')
            ->will($this->returnValue(false));

        // Use ReflectionClass to replace dbinterface data with database interface mock.
        // And course data with null value.
        $reflection = new ReflectionClass($training);
        $reflectionproperty = $reflection->getProperty('dbinterface');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($training, $dbinterfacemock);
        $reflectionproperty = $reflection->getProperty('course');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($training, null);

        try {
            $training->get_course();
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
            self::assertEquals($e->getMessage(), 'Course does not exist for shortname: ' . $training->shortname);
        }

        self::resetAllData();
    }

    /**
     * Test delete not ok
     *
     * @covers \local_mentor_core\training::delete
     */
    public function test_delete_nok() {
        global $DB;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        $training->courseid = -2;

        try {
            $training->delete();
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test get_session_number ok
     *
     * @covers \local_mentor_core\training::get_session_number
     */
    public function test_get_session_number_ok() {
        global $CFG;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        self::assertEquals(0, $training->get_session_number());

        self::resetAllData();
    }

    /**
     * Test get_url ok
     *
     * @covers \local_mentor_core\training::get_url
     */
    public function test_get_url_ok() {
        global $CFG;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        self::assertEquals(new \moodle_url('/course/view.php', ['id' => $training->courseid]), $training->get_url());

        $training->courseformat = 'summary';
        self::assertEquals(new \moodle_url('/course/view.php', ['id' => $training->courseid]), $training->get_url());

        self::resetAllData();
    }

    /**
     * Test get_actions ok
     *
     * @covers \local_mentor_core\training::get_actions
     */
    public function test_get_actions_ok() {
        global $USER;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        $actions = $training->get_actions($USER, ['move' => true]);
        self::assertCount(4, $actions);

        self::assertArrayHasKey('trainingsheet', $actions);
        $trainingcourse = $entity->get_edadmin_courses('trainings');
        $url = new \moodle_url('/course/view.php', ['id' => $trainingcourse['id']]);
        $trainingsheet = [
            'url' => $training->get_sheet_url()->out() . '&returnto=' . $url,
            'tooltip' => get_string('gototrainingsheet', 'local_mentor_core'),
        ];
        self::assertEquals($trainingsheet, $actions['trainingsheet']);

        self::assertArrayHasKey('movetraining', $actions);
        self::assertEquals('', $actions['movetraining']);

        self::assertArrayHasKey('assignusers', $actions);
        self::assertEquals((new \moodle_url('/user/index.php', ['id' => $training->courseid]))->out(),
            $actions['assignusers']);

        self::assertArrayHasKey('duplicatetraining', $actions);
        self::assertEquals('', $actions['duplicatetraining']);

        $training->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        $training->update($training);

        $actions = $training->get_actions($USER, ['move' => true]);
        self::assertCount(5, $actions);

        self::assertArrayHasKey('trainingsheet', $actions);
        $trainingcourse = $entity->get_edadmin_courses('trainings');
        $url = new \moodle_url('/course/view.php', ['id' => $trainingcourse['id']]);
        $trainingsheet = [
            'url' => $training->get_sheet_url()->out() . '&returnto=' . $url,
            'tooltip' => get_string('gototrainingsheet', 'local_mentor_core'),
        ];
        self::assertEquals($trainingsheet, $actions['trainingsheet']);

        self::assertArrayHasKey('movetraining', $actions);
        self::assertEquals('', $actions['movetraining']);

        self::assertArrayHasKey('assignusers', $actions);
        self::assertEquals((new \moodle_url('/user/index.php', ['id' => $training->courseid]))->out(),
            $actions['assignusers']);

        self::assertArrayHasKey('duplicatetraining', $actions);
        self::assertEquals('', $actions['duplicatetraining']);

        self::assertArrayHasKey('createsessions', $actions);
        self::assertEquals('', $actions['createsessions']);

        self::resetAllData();
    }

    /**
     * Test prepare_edit_form
     *
     * @covers \local_mentor_core\training::prepare_edit_form
     */
    public function test_prepare_edit_form_ok() {
        global $CFG;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        $fs = get_file_storage();

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

        $editform = $training->prepare_edit_form();

        self::assertObjectHasProperty('content', $editform);
        self::assertObjectHasProperty('thumbnail', $editform);
        self::assertObjectHasProperty('traininggoal', $editform);

        self::resetAllData();
    }

    /**
     * Test convert_for_template
     *
     * @covers \local_mentor_core\training::convert_for_template
     */
    public function test_convert_for_template_ok() {
        global $CFG;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        $fs = get_file_storage();

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

        // With admin user.
        $templatedata = $training->convert_for_template();

        self::assertObjectHasProperty('id', $templatedata);
        self::assertEquals($training->id, $templatedata->id);

        self::assertObjectHasProperty('name', $templatedata);
        self::assertEquals($training->name, $templatedata->name);

        self::assertObjectHasProperty('courseurl', $templatedata);
        self::assertEquals($training->get_url()->out(), $templatedata->courseurl);

        self::assertObjectHasProperty('content', $templatedata);
        self::assertEquals($training->content, $templatedata->content);

        self::assertObjectHasProperty('traininggoal', $templatedata);
        self::assertEquals($training->traininggoal, $templatedata->traininggoal);

        self::assertObjectHasProperty('isreviewer', $templatedata);
        self::assertFalse($templatedata->isreviewer);

        self::assertObjectHasProperty('entityid', $templatedata);
        self::assertEquals($training->get_entity()->id, $templatedata->entityid);

        self::assertObjectHasProperty('entityname', $templatedata);
        self::assertEquals($training->get_entity()->name, $templatedata->entityname);

        self::assertObjectHasProperty('thumbnail', $templatedata);
        $urlfile = \moodle_url::make_pluginfile_url(
            $filerecord->contextid,
            $filerecord->component,
            $filerecord->filearea,
            $filerecord->itemid,
            $filerecord->filepath,
            $filerecord->filename
        )->out();
        self::assertEquals($urlfile, $templatedata->thumbnail);

        $user = self::getDataGenerator()->create_user();
        self::setUser($user);

        // With participant.
        $templatedata = $training->convert_for_template();

        self::assertObjectHasProperty('id', $templatedata);
        self::assertEquals($training->id, $templatedata->id);

        self::assertObjectHasProperty('name', $templatedata);
        self::assertEquals($training->name, $templatedata->name);

        self::assertObjectHasProperty('courseurl', $templatedata);
        self::assertEquals($training->get_url()->out(), $templatedata->courseurl);

        self::assertObjectHasProperty('content', $templatedata);
        self::assertEquals($training->content, $templatedata->content);

        self::assertObjectHasProperty('traininggoal', $templatedata);
        self::assertEquals($training->traininggoal, $templatedata->traininggoal);

        self::assertObjectHasProperty('isreviewer', $templatedata);
        self::assertTrue($templatedata->isreviewer);

        self::assertObjectHasProperty('entityid', $templatedata);
        self::assertEquals($training->get_entity()->id, $templatedata->entityid);

        self::assertObjectHasProperty('entityname', $templatedata);
        self::assertEquals($training->get_entity()->name, $templatedata->entityname);

        self::assertObjectHasProperty('thumbnail', $templatedata);
        $urlfile = \moodle_url::make_pluginfile_url(
            $filerecord->contextid,
            $filerecord->component,
            $filerecord->filearea,
            $filerecord->itemid,
            $filerecord->filepath,
            $filerecord->filename
        )->out();
        self::assertEquals($urlfile, $templatedata->thumbnail);

        self::resetAllData();
    }

    /**
     * Test duplicate ok
     *
     * @covers \local_mentor_core\training::duplicate
     */
    public function test_duplicate_ok() {

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        $newtraining = $training->duplicate('duplicate_training');

        self::assertEquals($newtraining->shortname, 'duplicate_training');
        self::assertEquals($newtraining->get_entity()->id, $training->get_entity()->id);

        // In other entity.
        $entity2 = $this->create_entity('ENTITY2');
        $newtraining2 = $training->duplicate('duplicate_training_2', $entity2->id);

        self::assertEquals($newtraining2->shortname, 'duplicate_training_2');
        self::assertNotEquals($newtraining2->get_entity()->id, $training->get_entity()->id);
        self::assertEquals($newtraining2->get_entity()->id, $entity2->id);

        self::resetAllData();
    }

    /**
     * Test duplicate generate_backup not ok
     *
     * @covers \local_mentor_core\training::duplicate
     */
    public function test_duplicate_generate_backup_nok() {

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        // Create training Mock.
        $trainingmock = $this->getMockBuilder('\local_mentor_core\training')
            ->setMethods(['generate_backup'])
            ->setConstructorArgs([$training->id])
            ->getMock();

        // Return false value when generate_backup function call first time.
        $trainingmock->expects($this->exactly(1))
            ->method('generate_backup')
            ->willReturnOnConsecutiveCalls(
                false
            );

        try {
            $trainingmock->duplicate('trainingshortname');
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
            self::assertEquals($e->getMessage(), 'Backup file not created');
        }

        self::resetAllData();
    }

    /**
     * Test duplicate course category not ok
     *
     * @covers \local_mentor_core\training::duplicate
     */
    public function test_duplicate_course_category_nok() {
        global $DB;

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        $course = get_course($training->courseid);
        $backup = $training->generate_backup();
        $backup->delete();

        // Create backup file Mock.
        $backupmock = $this->getMockBuilder(get_class($backup))
            ->setMethods(['delete'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return true value when delete function call one time.
        $backupmock->expects($this->once())
            ->method('delete')
            ->will($this->returnValue(true));

        // Create training Mock.
        $trainingmock = $this->getMockBuilder('\local_mentor_core\training')
            ->setMethods(['generate_backup'])
            ->setConstructorArgs([$training->id])
            ->getMock();

        // Return backupmock variable value when generate_backup function call one time.
        $trainingmock->expects($this->once())
            ->method('generate_backup')
            ->will($this->returnValue($backupmock));

        // Create DB Mock.
        $DB = $this->createMock(get_class($DB));

        // Return false value when get_record function call one time.
        // With 'course_categories' and array('id' => $course->category) arguments.
        $DB->expects($this->once())
            ->method('get_record')
            ->with('course_categories', ['id' => $course->category])
            ->will($this->returnValue(false));

        try {
            $trainingmock->duplicate('trainingshortname');
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
            self::assertEquals($e->getMessage(), 'Inexistant category : ' . $course->category);
        }

        self::resetAllData();
    }

    /**
     * Test duplicate limit of the number of loops not ok
     *
     * @covers \local_mentor_core\training::duplicate
     */
    public function test_duplicate_limit_of_the_number_of_loops_nok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        $backup = $training->generate_backup();
        $backup->delete();

        // Create backupfile Mock.
        $backupmock = $this->getMockBuilder(get_class($backup))
            ->setMethods(['delete'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return true value when delete function call one time.
        $backupmock->expects($this->once())
            ->method('delete')
            ->will($this->returnValue(true));

        // Create training Mock.
        $trainingmock = $this->getMockBuilder('\local_mentor_core\training')
            ->setMethods(['generate_backup'])
            ->setConstructorArgs([$training->id])
            ->getMock();

        // Return backupmock variable value when generate_backup function call one time.
        $trainingmock->expects($this->once())
            ->method('generate_backup')
            ->will($this->returnValue($backupmock));

        // Create database_interface Mock.
        $dbinterfacemock = $this->getMockBuilder('\local_mentor_core\database_interface')
            ->setMethods(['course_exists', 'training_exists'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return true value when course_exists function call any time.
        $dbinterfacemock->expects($this->any())
            ->method('course_exists')
            ->will($this->returnValue(true));

        // Return true value when training_exists function call any time.
        $dbinterfacemock->expects($this->any())
            ->method('training_exists')
            ->will($this->returnValue(true));

        // Replace dbinterface data with database interface Mock in training Mock.
        $reflection = new ReflectionClass($trainingmock);
        $reflectionproperty = $reflection->getProperty('dbinterface');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($trainingmock, $dbinterfacemock);

        try {
            $trainingmock->duplicate('trainingshortname');
        } catch (\Exception $e) {
            self::assertInstanceOf('exception', $e);
            self::assertEquals($e->getMessage(), 'Limit of the number of loops reached!');
        }

        self::resetAllData();
    }

    /**
     * Test generate_backup not ok
     *
     * @covers \local_mentor_core\training::generate_backup
     */
    public function test_generate_backup_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        // Create database interface Mock.
        $dbinterfacemock = $this->getMockBuilder('\local_mentor_core\database_interface')
            ->setMethods(['get_course_backup'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return false value when get_course_backup function call any time.
        $dbinterfacemock->expects($this->any())
            ->method('get_course_backup')
            ->will($this->returnValue(false));

        // Replace dbinterface data with database interface Mock to training object.
        $reflection = new ReflectionClass($training);
        $reflectionproperty = $reflection->getProperty('dbinterface');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($training, $dbinterfacemock);

        self::assertFalse($training->generate_backup());

        self::resetAllData();
    }

    /**
     * Test update training
     *
     * @covers \local_mentor_core\training::update
     */
    public function test_update() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        $data = new stdClass();
        $data->traininggoal = ['text' => 'new training goal'];
        $data->content = ['text' => 'new content'];
        $data->deletethumbnail = 1;

        $training->update($data);

        self::assertEquals('new training goal', $training->traininggoal);
        self::assertEquals('new content', $training->content);
        self::assertEquals('', $training->thumbnail);

        self::resetAllData();
    }

    /**
     * Test get favourite designer data
     *
     * @covers \local_mentor_core\training::get_favourite_designer_data
     */
    public function test_get_favourite_designer_data() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        $user = self::getDataGenerator()->create_user();

        self::assertFalse($training->get_favourite_designer_data($user->id));

        $favourite = new \stdClass();
        $favourite->component = 'local_trainings';
        $favourite->itemtype = 'favourite_training';
        $favourite->itemid = $training->id;
        $favourite->contextid = $training->get_context()->id;
        $favourite->userid = $user->id;
        $favourite->timecreated = time();
        $favourite->timemodified = time();
        $DB->insert_record('favourite', $favourite);

        $favouriteobject = $training->get_favourite_designer_data($user->id);
        self::assertIsObject($favouriteobject);
        self::assertEquals($favouriteobject->component, 'local_trainings');
        self::assertEquals($favouriteobject->itemtype, 'favourite_training');
        self::assertEquals($favouriteobject->itemid, $training->id);
        self::assertEquals($favouriteobject->contextid, $training->get_context()->id);
        self::assertEquals($favouriteobject->userid, $user->id);

        self::resetAllData();
    }

    /**
     * Test has_enroll_user_enabled
     *
     * @covers \local_mentor_core\training::has_enroll_user_enabled
     */
    public function test_has_enroll_user_enabled() {
        global $DB, $USER, $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        $CFG->defaultauth = 'manual';

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        $course = $training->get_course();

        $user = $this->init_create_user();
        self::setUser($user);

        // No enrol exist.
        self::assertFalse($training->has_enroll_user_enabled());

        // Manual enrol user.
        self::getDataGenerator()->enrol_user($USER->id, $course->id, 'concepteur');

        // User is enrol.
        self::assertTrue($training->has_enroll_user_enabled());

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
        self::assertFalse($training->has_enroll_user_enabled());

        // Create new enrol : self enrol.
        self::getDataGenerator()->enrol_user($USER->id, $course->id, 'tuteur', 'self');

        // Manual enrol is disable, but self enrol is enable.
        self::assertTrue($training->has_enroll_user_enabled());

        self::resetAllData();
    }

    /**
     * Test get_sessions
     *
     * @covers  \local_mentor_core\training::get_sessions
     */
    public function test_get_sessions() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        self::assertCount(0, $training->get_sessions());

        $session1 = \local_mentor_core\session_api::create_session($training->id, 'session 1', true);
        $sessions = $training->get_sessions();
        self::assertCount(1, $sessions);
        self::assertEquals($session1->id, $sessions[0]->id);

        $session2 = \local_mentor_core\session_api::create_session($training->id, 'session 3', true);
        $sessions = $training->get_sessions();
        self::assertCount(2, $sessions);
        self::assertEquals($session1->id, $sessions[0]->id);
        self::assertEquals($session2->id, $sessions[1]->id);

        $session3 = \local_mentor_core\session_api::create_session($training->id, 'session 2', true);
        $sessions = $training->get_sessions('id');
        self::assertCount(3, $sessions);
        self::assertEquals($session1->id, $sessions[0]->id);
        self::assertEquals($session2->id, $sessions[1]->id);
        self::assertEquals($session3->id, $sessions[2]->id);

        $sessions = $training->get_sessions('courseshortname');
        self::assertCount(3, $sessions);
        self::assertEquals($session1->id, $sessions[0]->id);
        self::assertEquals($session2->id, $sessions[2]->id);
        self::assertEquals($session3->id, $sessions[1]->id);

        self::resetAllData();
    }

    /**
     * Test is_available_to_user
     *
     * @covers \local_mentor_core\training::is_available_to_user
     */
    public function test_is_available_to_user_ok() {
        global $CFG;

        $CFG->defaultauth = 'manual';

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        // No session.
        self::assertFalse($training->is_available_to_user());

        $session1 = \local_mentor_core\session_api::create_session($training->id, 'session 1', true);

        // One session not available.
        self::assertFalse($training->is_available_to_user());

        $session1->opento = 'current_entity';
        \local_mentor_core\session_api::update_session($session1);

        // One session available.
        self::assertTrue($training->is_available_to_user());

        $this->resetAllData();
    }

    /**
     * Test create_self_enrolment_instance
     *
     * @covers \local_mentor_core\training::create_self_enrolment_instance
     */
    public function test_create_self_enrolment_instance() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create training.
        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        $DB->delete_records('enrol', ['courseid' => $training->get_course()->id]);
        self::assertFalse($DB->record_exists('enrol', ['enrol' => 'self', 'courseid' => $training->get_course()->id]));

        // Create new self enrol instance.
        $training->create_self_enrolment_instance();

        $selfenrol = $DB->get_record('enrol', ['enrol' => 'self', 'courseid' => $training->get_course()->id]);

        self::assertIsObject($selfenrol);
        self::assertEquals(0, $selfenrol->status);

        $selfenrol->status = 1;

        // Disable self enrol instance.
        $DB->update_record('enrol', $selfenrol);

        $selfenrol = $DB->get_record('enrol', ['enrol' => 'self', 'courseid' => $training->get_course()->id]);

        self::assertIsObject($selfenrol);
        self::assertEquals(1, $selfenrol->status);

        // Function enable self enrol instance.
        $training->create_self_enrolment_instance();

        $selfenrol = $DB->get_record('enrol', ['enrol' => 'self', 'courseid' => $training->get_course()->id]);

        self::assertIsObject($selfenrol);
        self::assertEquals(0, $selfenrol->status);

        $this->resetAllData();
    }

    /**
     * Test enable_self_enrolment_instance
     *
     * @covers \local_mentor_core\training::enable_self_enrolment_instance
     */
    public function test_enable_self_enrolment_instance() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create training.
        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        $DB->delete_records('enrol', ['courseid' => $training->get_course()->id]);
        self::assertFalse($DB->record_exists('enrol', ['enrol' => 'self', 'courseid' => $training->get_course()->id]));

        // Self enrol instance not exist.
        self::assertFalse($training->enable_self_enrolment_instance());

        // Create new self enrol instance.
        $training->create_self_enrolment_instance();

        // Self enrol instance is already enable.
        self::assertFalse($training->enable_self_enrolment_instance());

        // Disable self enrol instance.
        $selfenrol = $DB->get_record('enrol', ['enrol' => 'self', 'courseid' => $training->get_course()->id]);
        $selfenrol->status = 1;
        $DB->update_record('enrol', $selfenrol);

        // Self enrol instance is disable, the function will activate it.
        self::assertTrue($training->enable_self_enrolment_instance());

        // Self enrol instance is enable.
        $selfenrol = $DB->get_record('enrol', ['enrol' => 'self', 'courseid' => $training->get_course()->id]);
        self::assertIsObject($selfenrol);
        self::assertEquals(0, $selfenrol->status);

        $this->resetAllData();
    }

    /**
     * Test is_from_library
     *
     * @covers \local_mentor_core\training::is_from_library
     */
    public function test_is_from_library() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create training.
        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        \local_mentor_core\library_api::get_or_create_library();

        self::assertFalse($training->is_from_library());

        $traininglibrary = \local_mentor_core\library_api::publish_to_library($training->id, true);

        self::assertTrue($traininglibrary->is_from_library());

        $this->resetAllData();
    }

    /**
     * Test enrol_current_user
     *
     * @covers \local_mentor_core\training::enrol_current_user
     */
    public function test_enrol_current_user() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create training.
        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);
        $user = self::getDataGenerator()->create_user();
        $library = \local_mentor_core\library_api::get_or_create_library();

        self::setUser($user);

        // Not access to library.
        $result = $training->enrol_current_user();

        self::assertIsArray($result);
        self::assertFalse($result['status']);
        self::assertEquals($result['warnings']['message'], get_string('librarynotaccessible', 'local_mentor_core'));

        self::setAdminUser();

        $visiteurbiblio = $DB->get_record('role', ['shortname' => 'visiteurbiblio']);
        role_assign($visiteurbiblio->id, $user->id, $library->get_context()->id);

        self::setUser($user);

        // Is not training library.
        $result = $training->enrol_current_user();

        self::assertIsArray($result);
        self::assertFalse($result['status']);
        self::assertEquals($result['warnings']['message'], get_string('trainingnotinthelibrary', 'local_mentor_core'));

        self::setAdminUser();
        $traininglibrary = \local_mentor_core\library_api::publish_to_library($training->id, true);
        $traininglibrarycourseid = $traininglibrary->get_course()->id;
        $DB->delete_records('enrol', ['courseid' => $traininglibrarycourseid]);
        self::assertFalse($DB->record_exists('enrol', ['enrol' => 'self', 'courseid' => $traininglibrarycourseid]));

        $result = $traininglibrary->enrol_current_user();

        self::assertIsArray($result);
        self::assertTrue($result['status']);
        self::assertEmpty($result['warnings']);

        $this->resetAllData();
    }

    /**
     * Test has_self_enrol
     *
     * @covers \local_mentor_core\training::has_self_enrol
     */
    public function test_has_self_enrol() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create training.
        $entity = $this->create_entity('ENTITY');
        $training = $this->create_training($entity);

        $library = \local_mentor_core\library_api::get_or_create_library();
        $traininglibrary = \local_mentor_core\library_api::publish_to_library($training->id, true);
        $traininglibrary->create_self_enrolment_instance();

        $user = self::getDataGenerator()->create_user();
        $visiteurbiblio = $DB->get_record('role', ['shortname' => 'visiteurbiblio']);
        role_assign($visiteurbiblio->id, $user->id, $library->get_context()->id);

        self::setUser($user);

        self::assertFalse($traininglibrary->has_self_enrol());

        $traininglibrary->enrol_current_user();

        self::assertTrue($traininglibrary->has_self_enrol());

        $this->resetAllData();
    }
}
