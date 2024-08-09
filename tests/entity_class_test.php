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
 * Test cases for class entity
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_mentor_core\database_interface;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/entity.php');

class local_mentor_core_entity_class_testcase extends advanced_testcase {

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
     * Test entity constructor
     *
     * @covers \local_mentor_core\entity::__construct
     * @covers \local_mentor_core\specialization::__construct
     */
    public function test_construct_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $entityname = "Entity name";

        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        self::assertSame($entity->get_name(), $entityname);

        // Try to instantiate an entity with a bad id.
        try {
            $entity = \local_mentor_core\entity_api::get_entity(1234);
        } catch (Exception $e) {
            self::assertInstanceOf('Exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test entity constructor with object
     *
     * @covers \local_mentor_core\entity::__construct
     * @covers \local_mentor_core\model::__construct
     */
    public function test_construct_with_object_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $entityname = "Entity name";

        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        // Get last created entity.
        $db = database_interface::get_instance();
        $entityrecord = $db->get_course_category_by_id($entityid);

        self::assertSame($entityrecord->name, $entityname);

        // Instantiate an entity with an object.
        $entity = new \local_mentor_core\entity($entityrecord);

        self::assertSame($entity->name, $entityname);

        self::resetAllData();
    }

    /**
     * Test entity constructor with wrong object
     *
     * @covers \local_mentor_core\entity::__construct
     */
    public function test_construct_with_wrong_object_ko() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        $entityname = "Entity name";
        $entityparentid = "123456";
        $entityparentname = "Entity parent name";

        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        // Get last created entity.
        $db = database_interface::get_instance();
        $entityrecord = $db->get_course_category_by_id($entityid);
        $entityrecord->parent = $entityparentid;
        $entityrecord->parentname = $entityparentname;

        self::assertSame($entityrecord->name, $entityname);

        // Instantiate an entity with an object.
        try {
            $entity = new \local_mentor_core\entity($entityrecord);
        } catch (\moodle_exception $e) {
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test entity constructor
     *
     * @covers \local_mentor_core\entity::update
     */
    public function test_update_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Update the entity name.
        $newname = 'Updated name';
        $entity->update(['name' => $newname]);

        self::assertSame($entity->get_name(), $newname);

        self::resetAllData();
    }

    /**
     * Test add member
     *
     * @covers \local_mentor_core\entity::add_member
     * @covers \local_mentor_core\entity::get_members
     */
    public function test_add_member_ok() {
        global $DB;
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Add a user.
        $user = self::getDataGenerator()->create_user();
        $result = $entity->add_member($user);

        self::assertSame($result, $user->id);

        // Set mainentity to user.
        $field = $DB->get_record('user_info_field', ['shortname' => 'mainentity']);
        $userdata = new stdClass();
        $userdata->fieldid = $field->id;
        $userdata->data = $entityname;
        $userdata->userid = $user->id;
        $DB->insert_record('user_info_data', $userdata);

        // Check entity members.
        $members = $entity->get_members();

        self::assertCount(1, $members);
        self::assertArrayHasKey($user->id, $members);

        self::resetAllData();
    }

    /**
     * Test add member KO
     *
     * @covers \local_mentor_core\entity::add_member
     */
    public function test_add_member_ko() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create Entity mock.
        $entitymock = $this->getMockBuilder('\local_mentor_core\entity')
            ->setMethods(['is_main_entity'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return false value when is_main_entity function call.
        $entitymock->expects($this->any())
            ->method('is_main_entity')
            ->will($this->returnValue(false));

        // Add a user.
        $user = self::getDataGenerator()->create_user();

        try {
            $entitymock->add_member($user);
            self::fail();
        } catch (\moodle_exception $e) {
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test add member with wrong cohort KO
     *
     * @covers \local_mentor_core\entity::add_member
     */
    public function test_add_member_with_wrong_cohort_ko() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Create Entity mock.
        $entitymock = $this->getMockBuilder('\local_mentor_core\entity')
            ->setMethods(['is_main_entity', 'get_cohort'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return false value when is_main_entity function call.
        $entitymock->expects($this->any())
            ->method('is_main_entity')
            ->will($this->returnValue(true));

        // Return false value when get_cohort function call.
        $entitymock->expects($this->any())
            ->method('get_cohort')
            ->will($this->returnValue($entity->get_cohort()));

        // Create database interface Mock.
        $dbinterfacemock = $this->getMockBuilder('\local_mentor_core\database_interface')
            ->setMethods(['add_cohort_member'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return one time false when call add_cohort_member function.
        $dbinterfacemock->expects($this->any())
            ->method('add_cohort_member')
            ->will($this->returnValue(false));

        // Replace dbinterface data to entity object with mock.
        $reflection = new ReflectionClass($entitymock);
        $reflectionproperty = $reflection->getProperty('dbinterface');
        $reflectionproperty->setAccessible(true);
        $reflectionproperty->setValue($entitymock, $dbinterfacemock);

        // Add a user.
        $user = self::getDataGenerator()->create_user();

        self::assertFalse($entitymock->add_member($user));

        self::resetAllData();
    }

    /**
     * Test create presentation page
     *
     * @covers \local_mentor_core\entity::create_presentation_page
     * @covers \local_mentor_core\entity::get_presentation_page_course
     */
    public function test_create_presentation_page_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $presentationpage = $entity->create_presentation_page();
        self::assertEquals($presentationpage->fullname, 'Présentation de l\'espace Mentor ' . $entity->name);
        self::assertEquals($presentationpage->shortname, 'Présentation de l\'espace Mentor ' . $entity->name);
        self::assertEquals($presentationpage->idnumber, 'presentation_' . $entity->id);

        // Presentation page exist.
        self::assertFalse($entity->create_presentation_page());

        self::resetAllData();
    }

    /**
     * Test create presentation page with not manager
     *
     * @covers \local_mentor_core\entity::create_presentation_page
     */
    public function test_create_presentation_page_nok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

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

        self::setUser($userid);

        // User is not manager.
        self::assertFalse($entity->create_presentation_page());

        self::resetAllData();
    }

    /**
     * Test get contact page url
     *
     * @covers \local_mentor_core\entity::get_contact_page_url
     * @covers \local_mentor_core\entity::create_contact_page
     */
    public function test_get_contact_page_url_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $contactpageurl = $entity->get_contact_page_url();
        $contactpagecourse = get_course($contactpageurl->get_param('id'));

        self::assertEquals($contactpageurl->get_path(), '/moodle/course/view.php');
        self::assertEquals($contactpagecourse->fullname, $entityname . ' - Page de contact');
        self::assertEquals($contactpagecourse->shortname, $entityname . ' - Page de contact');

        // Delete and recreate contact page course.
        delete_course($contactpageurl->get_param('id'), false);
        $contactpageurl = $entity->get_contact_page_url();
        $contactpagecourse = get_course($contactpageurl->get_param('id'));

        self::assertEquals($contactpageurl->get_path(), '/moodle/course/view.php');
        self::assertEquals($contactpagecourse->fullname, $entityname . ' - Page de contact');
        self::assertEquals($contactpagecourse->shortname, $entityname . ' - Page de contact');

        self::resetAllData();
    }

    /**
     * Test get presentation page url
     *
     * @covers \local_mentor_core\entity::get_presentation_page_url
     * @covers \local_mentor_core\entity::get_presentation_page_course
     * @covers \local_mentor_core\entity::create_presentation_page
     */
    public function test_get_presentation_page_url_nok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Page not create.
        self::assertFalse($entity->get_presentation_page_url());

        // Create presentation page.
        $entity->create_presentation_page();

        $presentationpageurl = $entity->get_presentation_page_url();
        $presentationpagecourse = get_course($presentationpageurl->get_param('id'));

        self::assertEquals($presentationpageurl->get_path(), '/moodle/course/view.php');
        self::assertEquals($presentationpagecourse->fullname, 'Présentation de l\'espace Mentor ' . $entityname);
        self::assertEquals($presentationpagecourse->shortname, 'Présentation de l\'espace Mentor ' . $entityname);

        self::resetAllData();
    }

    /**
     * Test contact page is not initialized
     *
     * @covers \local_mentor_core\entity::contact_page_is_initialized
     * @covers \local_mentor_core\entity::get_contact_page_course
     */
    public function test_contact_page_is_initialized_nok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Contact page module not create.
        self::assertFalse($entity->contact_page_is_initialized());

        // Create contact page.
        $contactpageurl = $entity->get_contact_page_url();
        // Delete and recreate contact page course.
        delete_course($contactpageurl->get_param('id'), false);

        // Contact page is recreate but is not initialize.
        self::assertFalse($entity->contact_page_is_initialized());

        self::resetAllData();
    }

    /**
     * Test update contact page name
     *
     * @covers \local_mentor_core\entity::update_contact_page_name
     * @covers \local_mentor_core\entity::get_contact_page_course
     * @covers \local_mentor_core\entity::get_entity_path
     */
    public function test_update_contact_page_name_ok() {
        global $DB;

        $this->resetAfterTest(true);

        self::setAdminUser();

        $DB->delete_records('course_categories');

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $contactpageurl = $entity->get_contact_page_url();
        $contactpagecourse = get_course($contactpageurl->get_param('id'));

        self::assertEquals($contactpagecourse->fullname, $entityname . ' - Page de contact');
        self::assertEquals($contactpagecourse->shortname, $entityname . ' - Page de contact');

        $newdata = new stdClass();
        $newdata->name = 'Name updated';
        $newdata->shortname = 'Name updated';
        \local_mentor_core\entity_api::update_entity($entity->id, $newdata);

        // Refresh entity data.
        $entityupdate = \local_mentor_core\entity_api::get_entity($entity->id, true);

        // Update contact page.
        $entityupdate->update_contact_page_name();
        $contactpageurlupdate = $entityupdate->get_contact_page_url();
        $contactpagecourseupdate = get_course($contactpageurlupdate->get_param('id'));

        self::assertEquals($contactpagecourseupdate->fullname, $newdata->name . ' - Page de contact');
        self::assertEquals($contactpagecourseupdate->shortname, $newdata->name . ' - Page de contact');

        self::resetAllData();
    }

    /**
     * Test update presentation page name
     *
     * @covers \local_mentor_core\entity::update_presentation_page_name
     * @covers \local_mentor_core\entity::get_presentation_page_course
     */
    public function test_update_presentation_page_name_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $presentationpage = $entity->create_presentation_page();

        self::assertEquals($presentationpage->fullname, 'Présentation de l\'espace Mentor ' . $entityname);
        self::assertEquals($presentationpage->shortname, 'Présentation de l\'espace Mentor ' . $entityname);

        $newdata = new stdClass();
        $newdata->name = 'Name updated';
        $newdata->shortname = 'Name updated';
        \local_mentor_core\entity_api::update_entity($entity->id, $newdata);

        // Refresh entity data.
        $entityupdate = \local_mentor_core\entity_api::get_entity($entity->id, true);

        // Update presentation page.
        $entityupdate->update_presentation_page_name();
        $presentationpage = $entityupdate->get_presentation_page_course();

        self::assertEquals($presentationpage->fullname, 'Présentation de l\'espace Mentor ' . $newdata->name);
        self::assertEquals($presentationpage->shortname, 'Présentation de l\'espace Mentor ' . $newdata->name);

        self::resetAllData();
    }

    /**
     * Test update its sub entity edadmin courses name
     *
     * @covers \local_mentor_core\entity::update_its_sub_entity_edadmin_courses_name
     * @covers \local_mentor_core\entity::update_edadmin_courses_name
     * @covers \local_mentor_core\entity::get_edadmin_courses
     * @covers \local_mentor_core\entity::is_main_entity
     * @covers \local_mentor_core\entity::get_entity_path
     * @covers \local_mentor_core\entity::get_sub_entities
     */
    public function test_update_its_sub_entity_edadmin_courses_name_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $edadmincourses = $entity->get_edadmin_courses();

        self::assertEquals($edadmincourses['entities']['name'], $entityname . ' - Gestion des paramètres');
        self::assertEquals($edadmincourses['entities']['shortname'], $entityname . ' - Gestion des paramètres');

        self::assertEquals($edadmincourses['session']['name'], $entityname . ' - Gestion des sessions');
        self::assertEquals($edadmincourses['session']['shortname'], $entityname . ' - Gestion des sessions');

        self::assertEquals($edadmincourses['trainings']['name'], $entityname . ' - Gestion des formations');
        self::assertEquals($edadmincourses['trainings']['shortname'], $entityname . ' - Gestion des formations');

        self::assertEquals($edadmincourses['user']['name'], $entityname . ' - Gestion des utilisateurs');
        self::assertEquals($edadmincourses['user']['shortname'], $entityname . ' - Gestion des utilisateurs');

        $newdata = new stdClass();
        $newdata->name = 'Name updated';
        $newdata->shortname = 'Name updated';
        \local_mentor_core\entity_api::update_entity($entity->id, $newdata);

        // Refresh entity data.
        $entityupdate = \local_mentor_core\entity_api::get_entity($entity->id, true);

        // Update presentation page.
        $entityupdate->update_its_sub_entity_edadmin_courses_name();

        $edadmincoursesupdate = $entityupdate->get_edadmin_courses();

        self::assertEquals($edadmincoursesupdate['entities']['name'], $newdata->name . ' - Gestion des paramètres');
        self::assertEquals($edadmincoursesupdate['entities']['shortname'], $newdata->name . ' - Gestion des paramètres');

        self::assertEquals($edadmincoursesupdate['session']['name'], $newdata->name . ' - Gestion des sessions');
        self::assertEquals($edadmincoursesupdate['session']['shortname'], $newdata->name . ' - Gestion des sessions');

        self::assertEquals($edadmincoursesupdate['trainings']['name'], $newdata->name . ' - Gestion des formations');
        self::assertEquals($edadmincoursesupdate['trainings']['shortname'], $newdata->name . ' - Gestion des formations');

        self::assertEquals($edadmincoursesupdate['user']['name'], $newdata->name . ' - Gestion des utilisateurs');
        self::assertEquals($edadmincoursesupdate['user']['shortname'], $newdata->name . ' - Gestion des utilisateurs');

        self::resetAllData();
    }

    /**
     * Test update its sub entity edadmin courses name with subentity
     *
     * @covers \local_mentor_core\entity::update_its_sub_entity_edadmin_courses_name
     * @covers \local_mentor_core\entity::update_edadmin_courses_name
     * @covers \local_mentor_core\entity::get_edadmin_courses
     * @covers \local_mentor_core\entity::is_main_entity
     * @covers \local_mentor_core\entity::get_entity_path
     * @covers \local_mentor_core\entity::get_sub_entities
     */
    public function test_update_its_sub_entity_edadmin_courses_name_with_sub_entity_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $edadmincourses = $entity->get_edadmin_courses();

        self::assertEquals($edadmincourses['entities']['name'], $entityname . ' - Gestion des paramètres');
        self::assertEquals($edadmincourses['entities']['shortname'], $entityname . ' - Gestion des paramètres');

        self::assertEquals($edadmincourses['session']['name'], $entityname . ' - Gestion des sessions');
        self::assertEquals($edadmincourses['session']['shortname'], $entityname . ' - Gestion des sessions');

        self::assertEquals($edadmincourses['trainings']['name'], $entityname . ' - Gestion des formations');
        self::assertEquals($edadmincourses['trainings']['shortname'], $entityname . ' - Gestion des formations');

        self::assertEquals($edadmincourses['user']['name'], $entityname . ' - Gestion des utilisateurs');
        self::assertEquals($edadmincourses['user']['shortname'], $entityname . ' - Gestion des utilisateurs');

        // Create an sub entity.
        $subentityname = "Sub entity name";
        $subentityid = \local_mentor_core\entity_api::create_entity(['name' => $subentityname, 'parentid' => $entity->id]);

        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);

        $subentityedadmincourses = $subentity->get_edadmin_courses();

        self::assertEquals($subentityedadmincourses['entities']['name'], $subentityname . ' - Gestion des paramètres');
        self::assertEquals($subentityedadmincourses['entities']['shortname'],
            $subentity->get_entity_path() . ' - Gestion des paramètres');

        self::assertEquals($subentityedadmincourses['session']['name'], $entityname . ' - Gestion des sessions');
        self::assertEquals($subentityedadmincourses['session']['shortname'], $entityname . ' - Gestion des sessions');

        self::assertEquals($subentityedadmincourses['trainings']['name'], $entityname . ' - Gestion des formations');
        self::assertEquals($subentityedadmincourses['trainings']['shortname'], $entityname . ' - Gestion des formations');

        // Update.

        $newdata = new stdClass();
        $newdata->name = 'Name updated';
        $newdata->shortname = 'Name updated';
        \local_mentor_core\entity_api::update_entity($entity->id, $newdata);

        // Refresh entity data.
        $entityupdate = \local_mentor_core\entity_api::get_entity($entity->id, true);

        // Update presentation page.
        $entityupdate->update_its_sub_entity_edadmin_courses_name();

        $edadmincoursesupdate = $entityupdate->get_edadmin_courses();

        self::assertEquals($edadmincoursesupdate['entities']['name'], $newdata->name . ' - Gestion des paramètres');
        self::assertEquals($edadmincoursesupdate['entities']['shortname'], $newdata->name . ' - Gestion des paramètres');

        self::assertEquals($edadmincoursesupdate['session']['name'], $newdata->name . ' - Gestion des sessions');
        self::assertEquals($edadmincoursesupdate['session']['shortname'], $newdata->name . ' - Gestion des sessions');

        self::assertEquals($edadmincoursesupdate['trainings']['name'], $newdata->name . ' - Gestion des formations');
        self::assertEquals($edadmincoursesupdate['trainings']['shortname'], $newdata->name . ' - Gestion des formations');

        self::assertEquals($edadmincoursesupdate['user']['name'], $newdata->name . ' - Gestion des utilisateurs');
        self::assertEquals($edadmincoursesupdate['user']['shortname'], $newdata->name . ' - Gestion des utilisateurs');

        // Refresh sub entity data.
        $subentityupdate = \local_mentor_core\entity_api::get_entity($subentityid);

        // Update presentation page.
        $subentityedadmincoursesupdate = $subentityupdate->get_edadmin_courses();

        self::assertEquals($subentityedadmincoursesupdate['entities']['name'], $subentityname . ' - Gestion des paramètres');
        self::assertEquals($subentityedadmincoursesupdate['entities']['shortname'],
            $subentityupdate->get_entity_path() . ' - Gestion des paramètres');

        self::assertEquals($subentityedadmincoursesupdate['session']['name'], $newdata->name . ' - Gestion des sessions');
        self::assertEquals($subentityedadmincoursesupdate['session']['shortname'], $newdata->name . ' - Gestion des sessions');

        self::assertEquals($subentityedadmincoursesupdate['trainings']['name'], $newdata->name . ' - Gestion des formations');
        self::assertEquals($subentityedadmincoursesupdate['trainings']['shortname'], $newdata->name . ' - Gestion des formations');

        self::resetAllData();
    }

    /**
     * Test is manager
     *
     * @covers \local_mentor_core\entity::is_manager
     * @covers \local_mentor_core\entity::get_context
     */
    public function test_is_manager_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

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

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);
        $entity->assign_manager($userid);

        self::setUser($userid);

        // Is manager.
        self::assertTrue($entity->is_manager());

        self::setAdminUser();

        $user2 = new stdClass();
        $user2->lastname = 'lastname2';
        $user2->firstname = 'firstname2';
        $user2->email = 'test2@test.com';
        $user2->username = 'testusername2';
        $user2->password = 'to be generated';
        $user2->mnethostid = 1;
        $user2->confirmed = 1;
        $user2->auth = 'manual';
        $user2id = local_mentor_core\profile_api::create_user($user2);

        self::setUser($user2id);

        // Is not manager.
        self::assertFalse($entity->is_manager());

        self::setAdminUser();

        self::resetAllData();
    }

    /**
     * Test get trainings
     *
     * @covers \local_mentor_core\entity::get_trainings
     */
    public function test_get_trainings_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // No training.
        self::assertCount(0, $entity->get_trainings());

        // Init training data.
        $trainingdata = new stdClass();
        $trainingdata->name = 'fullname';
        $trainingdata->shortname = 'shortname';
        $trainingdata->content = 'summary';
        $trainingdata->traininggoal = 'TEST TRAINING';
        $trainingdata->thumbnail = '';
        $trainingdata->status = \local_mentor_core\training::STATUS_DRAFT;
        $trainingdata->categorychildid = $entity->get_entity_formation_category();
        $trainingdata->categoryid = $entity->id;
        $trainingdata->creativestructure = $entity->id;

        // Create training.
        $training = \local_mentor_core\training_api::create_training($trainingdata);

        // One training.
        $entitytrainings = $entity->get_trainings();
        self::assertCount(1, $entitytrainings);
        self::assertArrayHasKey($training->id, $entitytrainings);
        self::assertEquals($entitytrainings[$training->id]->courseshortname, $training->courseshortname);
        self::assertEquals($entitytrainings[$training->id]->status, $training->status);
        self::assertEquals($entitytrainings[$training->id]->traininggoal, $training->traininggoal);

        self::resetAllData();
    }

    /**
     * Test get form data
     *
     * @covers \local_mentor_core\entity::get_form_data
     * @covers \local_mentor_core\entity::is_main_entity
     * @covers \local_mentor_core\entity::get_logo
     */
    public function test_get_form_data_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $formdata = $entity->get_form_data();
        self::assertEquals($formdata->namecategory, $entityname);
        self::assertEquals($formdata->idcategory, $entityid);

        // Create a sub entity.
        $subentityname = "Sub entity name";
        $subentityid = \local_mentor_core\entity_api::create_entity(['name' => $subentityname, 'parentid' => $entityid]);
        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);

        $formdatasubentity = $subentity->get_form_data();
        self::assertEquals($formdatasubentity->namecategory, $subentityname);
        self::assertEquals($formdatasubentity->idcategory, $subentityid);
        self::assertEquals($formdatasubentity->parentid, $entityid);

        self::resetAllData();
    }

    /**
     * Test get course category
     *
     * @covers \local_mentor_core\entity::get_course_category
     */
    public function test_get_course_category_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $coursecategory = $entity->get_course_category();

        self::assertEquals($coursecategory->id, $entity->id);
        self::assertEquals($coursecategory->name, $entity->name);

        self::resetAllData();
    }

    /**
     * Test change parent entity
     *
     * @covers \local_mentor_core\entity::change_parent_entity
     * @covers \local_mentor_core\entity::get_course_category
     * @covers \local_mentor_core\entity::get_entity_space_category
     * @covers \local_mentor_core\entity::get_main_entity
     */
    public function test_change_parent_entity_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);

        // Create an entity.
        $entityname2 = "Entity name 2";
        $entityid2 = \local_mentor_core\entity_api::create_entity(['name' => $entityname2, 'shortname' => $entityname2]);

        // Create a sub entity.
        $subentityname = "Sub entity name";
        $subentityid = \local_mentor_core\entity_api::create_entity(['name' => $subentityname, 'parentid' => $entityid]);
        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);

        self::assertFalse($subentity->is_main_entity());
        self::assertEquals($entityid, $subentity->get_main_entity()->id);

        // Change parent entity.
        $subentity->change_parent_entity($entityid2);

        self::assertEquals($entityid2, $subentity->get_main_entity()->id);

        self::resetAllData();
    }

    /**
     * Test get main entity
     *
     * @covers \local_mentor_core\entity::get_main_entity
     * @covers \local_mentor_core\entity::is_main_entity
     */
    public function test_get_main_entity_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Create a sub entity.
        $subentityname = "Sub entity name";
        $subentityid = \local_mentor_core\entity_api::create_entity(['name' => $subentityname, 'parentid' => $entityid]);
        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);

        // Is main entity.
        self::assertEquals($entityid, $entity->get_main_entity()->id);

        // Is sub entity.
        self::assertEquals($entityid, $subentity->get_main_entity()->id);

        self::resetAllData();
    }

    /**
     * Test has sub entities
     *
     * @covers \local_mentor_core\entity::has_sub_entities
     * @covers \local_mentor_core\entity::is_main_entity
     */
    public function test_has_sub_entities_ok() {
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Create a sub entity.
        $subentityname = "Sub entity name";
        $subentityid = \local_mentor_core\entity_api::create_entity(['name' => $subentityname, 'parentid' => $entityid]);
        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);

        // Has sub entities.
        self::assertTrue($entity->has_sub_entities());

        // Create sub entity data cache for entity.
        $entity->get_sub_entities();

        // Has sub entities (with sub entity cache).
        self::assertTrue($entity->has_sub_entities());

        // Has not sub entities because is sub entity.
        self::assertFalse($subentity->has_sub_entities());

        self::resetAllData();
    }

    /**
     * Test is trainings manager
     *
     * @covers \local_mentor_core\entity::is_trainings_manager
     * @covers \local_mentor_core\entity::get_context
     * @covers \local_mentor_core\entity::get_sub_entities
     */
    public function test_is_trainings_manager_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

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

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Assign role user.
        $entity->assign_manager($userid);

        self::setUser($userid);

        // Is training manager.
        self::assertTrue($entity->is_trainings_manager());

        self::setAdminUser();

        $user2 = new stdClass();
        $user2->lastname = 'lastname2';
        $user2->firstname = 'firstname2';
        $user2->email = 'test2@test.com';
        $user2->username = 'testusername2';
        $user2->password = 'to be generated';
        $user2->mnethostid = 1;
        $user2->confirmed = 1;
        $user2->auth = 'manual';
        $user2id = local_mentor_core\profile_api::create_user($user2);

        $subentityname = "Sub entity name";
        $subentityid = \local_mentor_core\entity_api::create_entity(['name' => $subentityname, 'parentid' => $entityid]);
        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);

        self::setUser($user2id);

        // Is not training manager.
        self::assertFalse($entity->is_trainings_manager());

        self::setAdminUser();

        // Assign role user.
        $subentity->assign_manager($user2id);

        self::setUser($user2id);

        // Is training manager.
        self::assertTrue($entity->is_trainings_manager());

        self::setAdminUser();

        self::resetAllData();
    }

    /**
     * Test is sessions manager
     *
     * @covers \local_mentor_core\entity::is_sessions_manager
     * @covers \local_mentor_core\entity::get_context
     * @covers \local_mentor_core\entity::get_sub_entities
     */
    public function test_is_sessions_manager_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

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

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Assign role user.
        $entity->assign_manager($userid);

        self::setUser($userid);

        // Is training manager.
        self::assertTrue($entity->is_sessions_manager());

        self::setAdminUser();

        $user2 = new stdClass();
        $user2->lastname = 'lastname2';
        $user2->firstname = 'firstname2';
        $user2->email = 'test2@test.com';
        $user2->username = 'testusername2';
        $user2->password = 'to be generated';
        $user2->mnethostid = 1;
        $user2->confirmed = 1;
        $user2->auth = 'manual';
        $user2id = local_mentor_core\profile_api::create_user($user2);

        $subentityname = "Sub entity name";
        $subentityid = \local_mentor_core\entity_api::create_entity(['name' => $subentityname, 'parentid' => $entityid]);
        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);

        self::setUser($user2id);

        // Is not training manager.
        self::assertFalse($entity->is_sessions_manager());

        self::setAdminUser();

        // Assign role user.
        $subentity->assign_manager($user2id);

        self::setUser($user2id);

        // Is training manager.
        self::assertTrue($entity->is_sessions_manager());

        self::setAdminUser();

        self::resetAllData();
    }

    /**
     * Test get sessions recyclebin page url
     *
     * @covers \local_mentor_core\entity::get_sessions_recyclebin_page_url
     */
    public function test_get_sessions_recyclebin_page_url_ok() {
        global $CFG;
        $this->resetAfterTest(true);

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        self::assertEquals(
            $entity->get_sessions_recyclebin_page_url()->out(),
            $CFG->wwwroot . '/local/session/pages/recyclebin_sessions.php?entityid=' . $entityid
        );

        self::resetAllData();
    }

    /**
     * Test get training recyclebin items
     *
     * @covers \local_mentor_core\entity::get_training_recyclebin_items
     * @covers \local_mentor_core\entity::get_entity_formation_category
     * @covers \local_mentor_core\entity::get_context
     * @covers \local_mentor_core\entity::get_recyclebin_items
     * @covers \local_mentor_core\entity::has_sub_entities
     * @covers \local_mentor_core\entity::get_sub_entities
     */
    public function test_get_training_recyclebin_items_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Init training data.
        $trainingdata = new stdClass();
        $trainingdata->name = 'fullname';
        $trainingdata->shortname = 'shortname';
        $trainingdata->content = 'summary';
        $trainingdata->traininggoal = 'TEST TRAINING';
        $trainingdata->thumbnail = '';
        $trainingdata->status = \local_mentor_core\training::STATUS_DRAFT;
        $trainingdata->categorychildid = $entity->get_entity_formation_category();
        $trainingdata->categoryid = $entity->id;
        $trainingdata->creativestructure = $entity->id;

        // Create training in entity.
        $training = \local_mentor_core\training_api::create_training($trainingdata);

        // Create sub entity.
        $subentityname = "Sub entity name";
        $subentityid = \local_mentor_core\entity_api::create_entity(['name' => $subentityname, 'parentid' => $entityid]);
        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);

        // Init training data.
        $trainingdata2 = new stdClass();
        $trainingdata2->name = 'fullname2';
        $trainingdata2->shortname = 'shortname2';
        $trainingdata2->content = 'summary2';
        $trainingdata2->traininggoal = 'TEST TRAINING 2';
        $trainingdata2->thumbnail = '';
        $trainingdata2->status = \local_mentor_core\training::STATUS_DRAFT;
        $trainingdata2->categorychildid = $subentity->get_entity_formation_category();
        $trainingdata2->categoryid = $subentity->id;
        $trainingdata2->creativestructure = $subentity->id;

        // Create training in sub entity.
        $training2 = \local_mentor_core\training_api::create_training($trainingdata2);

        // Entity not has items remove.
        self::assertCount(0, $entity->get_training_recyclebin_items());

        // Remove training in entity.
        \local_mentor_core\training_api::remove_training($training->id);

        // Entity has one item remove.
        self::assertCount(1, $entity->get_training_recyclebin_items());

        // Remove training in sub entity.
        \local_mentor_core\training_api::remove_training($training2->id);

        // Entity has two items remove (in entity and sub entity).
        self::assertCount(2, $entity->get_training_recyclebin_items());

        self::resetAllData();
    }

    /**
     * Test get sessions recyclebin items
     *
     * @covers \local_mentor_core\entity::get_sessions_recyclebin_items
     * @covers \local_mentor_core\entity::get_entity_session_category
     * @covers \local_mentor_core\entity::get_context
     * @covers \local_mentor_core\entity::get_recyclebin_items
     * @covers \local_mentor_core\entity::has_sub_entities
     * @covers \local_mentor_core\entity::get_sub_entities
     */
    public function test_get_sessions_recyclebin_items_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Init training data.
        $trainingdata = new stdClass();
        $trainingdata->name = 'fullname';
        $trainingdata->shortname = 'shortname';
        $trainingdata->content = 'summary';
        $trainingdata->traininggoal = 'TEST TRAINING';
        $trainingdata->thumbnail = '';
        $trainingdata->status = \local_mentor_core\training::STATUS_DRAFT;
        $trainingdata->categorychildid = $entity->get_entity_formation_category();
        $trainingdata->categoryid = $entity->id;
        $trainingdata->creativestructure = $entity->id;

        // Create training in entity.
        $training = \local_mentor_core\training_api::create_training($trainingdata);

        // Create session in entity.
        $sessionname = 'Session';
        $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);

        // Create sub entity.
        $subentityname = "Sub entity name";
        $subentityid = \local_mentor_core\entity_api::create_entity(['name' => $subentityname, 'parentid' => $entityid]);
        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);

        // Init training data.
        $trainingdata2 = new stdClass();
        $trainingdata2->name = 'fullname2';
        $trainingdata2->shortname = 'shortname2';
        $trainingdata2->content = 'summary';
        $trainingdata2->traininggoal = 'TEST TRAINING 2';
        $trainingdata2->thumbnail = '';
        $trainingdata2->status = \local_mentor_core\training::STATUS_DRAFT;
        $trainingdata2->categorychildid = $subentity->get_entity_formation_category();
        $trainingdata2->categoryid = $subentity->id;
        $trainingdata2->creativestructure = $subentity->id;

        // Create training in sub entity.
        $training2 = \local_mentor_core\training_api::create_training($trainingdata2);

        // Create session in sub entity.
        $sessionname2 = 'Session 2';
        $session2 = \local_mentor_core\session_api::create_session($training2->id, $sessionname2, true);

        // Entity not has items remove.
        self::assertCount(0, $entity->get_sessions_recyclebin_items());

        // Remove session in entity.
        $session->delete();

        // Entity has one item remove.
        self::assertCount(1, $entity->get_sessions_recyclebin_items());

        // Remove training in sub entity.
        $session2->delete();

        // Entity has two items remove (in entity and sub entity).
        self::assertCount(2, $entity->get_sessions_recyclebin_items());

        self::resetAllData();
    }

    /**
     * Test get recycle bin items with recycle bin is disable
     *
     * @covers \local_mentor_core\entity::get_sessions_recyclebin_items
     * @covers \local_mentor_core\entity::get_entity_session_category
     * @covers \local_mentor_core\entity::get_context
     * @covers \local_mentor_core\entity::get_recyclebin_items
     * @covers \local_mentor_core\entity::has_sub_entities
     * @covers \local_mentor_core\entity::get_sub_entities
     */
    public function test_get_recyclebin_items_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Disable recycle bin.
        set_config('categorybinenable', '0', 'tool_recyclebin');

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);
        $sessioncategoryid = $entity->get_entity_session_category();
        $category = \context_coursecat::instance($sessioncategoryid);
        $recyclebin = new \tool_recyclebin\category_bin($category->instanceid);

        try {
            $entity->get_sessions_recyclebin_items();
        } catch (\Exception $e) {
            // Recycle bin is disable.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Disable recycle bin.
        set_config('categorybinenable', '1', 'tool_recyclebin');

        // Recycle bin is disable.
        try {
            self::assertCount(0, $entity->get_sessions_recyclebin_items());
        } catch (\Exception $e) {
            self::fail($e);
        }

        self::resetAllData();
    }

    /**
     * Test is member
     *
     * @covers \local_mentor_core\entity::is_member
     */
    public function test_is_member() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        self::setAdminUser();

        $user1 = self::getDataGenerator()->create_user();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        self::assertFalse($entity->is_member($user1->id));

        $entity->add_member($user1);

        self::assertTrue($entity->is_member($user1->id));

        // Create a sub entity.
        $subentityname = "Sub entity name";
        $subentityid = \local_mentor_core\entity_api::create_entity(['name' => $subentityname, 'parentid' => $entity->id]);
        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);

        self::assertEmpty($subentity->is_member($user1->id));

        self::resetAllData();
    }

    /**
     * Test get available sessions to catalog
     *
     * @covers \local_mentor_core\entity::get_available_sessions_to_catalog
     */
    public function test_get_available_sessions_to_catalog() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        self::assertEmpty($entity->get_available_sessions_to_catalog());

        // Init training data.
        $trainingdata = new stdClass();
        $trainingdata->name = 'fullname';
        $trainingdata->shortname = 'shortname';
        $trainingdata->content = 'summary';
        $trainingdata->traininggoal = 'TEST TRAINING';
        $trainingdata->thumbnail = '';
        $trainingdata->status = \local_mentor_core\training::STATUS_DRAFT;
        $trainingdata->categorychildid = $entity->get_entity_formation_category();
        $trainingdata->categoryid = $entity->id;
        $trainingdata->creativestructure = $entity->id;

        // Create training in entity.
        $training = \local_mentor_core\training_api::create_training($trainingdata);

        // Create session in entity.
        $sessionname = 'Session';
        $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);

        self::assertEmpty($entity->get_available_sessions_to_catalog());

        $session->update_status(\local_mentor_core\session::STATUS_OPENED_REGISTRATION);

        $session->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $session->opento = \local_mentor_core\session::OPEN_TO_CURRENT_ENTITY;
        $session->update($session);

        self::assertCount(1, $entity->get_available_sessions_to_catalog());

        self::resetAllData();
    }

    /**
     * Test count_available_sessions_to_catalog
     *
     * @covers \local_mentor_core\entity::count_available_sessions_to_catalog
     */
    public function test_count_available_sessions_to_catalog() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        self::assertEquals(0, $entity->count_available_sessions_to_catalog());

        // Init training data.
        $trainingdata = new stdClass();
        $trainingdata->name = 'fullname';
        $trainingdata->shortname = 'shortname';
        $trainingdata->content = 'summary';
        $trainingdata->traininggoal = 'TEST TRAINING';
        $trainingdata->thumbnail = '';
        $trainingdata->status = \local_mentor_core\training::STATUS_DRAFT;
        $trainingdata->categorychildid = $entity->get_entity_formation_category();
        $trainingdata->categoryid = $entity->id;
        $trainingdata->creativestructure = $entity->id;

        // Create training in entity.
        $training = \local_mentor_core\training_api::create_training($trainingdata);

        // Create session in entity.
        $sessionname = 'Session';
        $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);

        self::assertEquals(0, $entity->count_available_sessions_to_catalog());

        $session->update_status(\local_mentor_core\session::STATUS_OPENED_REGISTRATION);

        $session->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $session->opento = \local_mentor_core\session::OPEN_TO_CURRENT_ENTITY;
        $session->update($session);

        self::assertEquals(1, $entity->count_available_sessions_to_catalog());

        self::resetAllData();
    }

    /**
     * Test get_edadmin_courses_url
     *
     * @covers \local_mentor_core\entity::get_edadmin_courses_url
     */
    public function test_get_edadmin_courses_url() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $entity->create_edadmin_courses_if_missing();
        $edadmincourse = $entity->get_edadmin_courses();

        self::assertEquals(
            $edadmincourse['trainings']['link'],
            $entity->get_edadmin_courses_url()
        );
        self::assertEquals(
            $edadmincourse['session']['link'],
            $entity->get_edadmin_courses_url('session')
        );
        self::assertEquals(
            $edadmincourse['entities']['link'],
            $entity->get_edadmin_courses_url('entities')
        );
        self::assertEquals(
            $edadmincourse['user']['link'],
            $entity->get_edadmin_courses_url('user')
        );
        self::assertEmpty($entity->get_edadmin_courses_url('other'));

        $subentityname = "Subentity name";
        $subentityid = \local_mentor_core\entity_api::create_entity([
            'name' => $subentityname, 'shortname' => $subentityname, 'parentid' => $entity->id,
        ]);
        $subentity = \local_mentor_core\entity_api::get_entity($subentityid);
        $subentityedadmincourse = $subentity->get_edadmin_courses();

        self::assertEquals(
            $subentityedadmincourse['trainings']['link'],
            $subentity->get_edadmin_courses_url()
        );
        self::assertEquals(
            $subentityedadmincourse['session']['link'],
            $subentity->get_edadmin_courses_url('session')
        );
        self::assertEquals(
            $subentityedadmincourse['entities']['link'],
            $subentity->get_edadmin_courses_url('entities')
        );
        // Same parent.
        self::assertEquals(
            $edadmincourse['user']['link'] . '&subentityid=' . $subentityid,
            $subentity->get_edadmin_courses_url('user')
        );
        self::assertEmpty($subentity->get_edadmin_courses_url('other'));

        self::resetAllData();
    }

    /**
     * Test is_hidden
     *
     * @covers \local_mentor_core\entity::is_hidden
     */
    public function test_is_hidden() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        self::setAdminUser();

        // Create an entity.
        $entityname = "Entity name";
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $entityname, 'shortname' => $entityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        self::assertEquals(0, $entity->is_hidden());

        self::resetAllData();
    }
}
