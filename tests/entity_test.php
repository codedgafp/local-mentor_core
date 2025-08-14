<?php

use local_mentor_core\entity_api;
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
 * Test cases for entity API
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

use local_mentor_core\helper\testhelper;
use local_categories_domains\repository\categories_domains_repository;

require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');
require_once($CFG->dirroot . '/local/mentor_core/api/training.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/entity.php');

class local_mentor_core_entity_testcase extends advanced_testcase {
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
                        'userid' => 2,  // Set the admin user as manager of the entity.
                ],
                [
                        'name' => 'New Entity 2',
                        'shortname' => 'New Entity 2',
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
            $entityid = entity_api::create_entity($entitydata);

            $entity = entity_api::get_entity($entityid);
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
     * Test of the creation of an entity
     *
     * @covers entity_api::create_entity
     * @covers entity_api::entity_exists
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\entity::assign_manager
     * @covers \local_mentor_core\entity::update
     * @covers \local_mentor_core\entity::create_edadmin_courses_if_missing
     * @covers \local_mentor_core\entity::get_context
     * @covers \local_mentor_core\entity::get_name
     * @covers \local_mentor_core\entity::get_edadmin_courses
     * @covers \local_mentor_core\entity::get_cohort
     */
    public function test_create_entity_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];
        $entitydata['shortname'] = 'shortnameentity';

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Test if we have received an identifier.
        self::assertIsInt($entityid);

        $entity = entity_api::get_entity($entityid);
        self::assertEquals('shortnameentity', $entity->shortname);

        $entitydata = $this->get_entities_data()[1];
        unset($entitydata['name']);

        // Test standard Entity creation.
        try {
            entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test of the creation of a sub entity
     *
     * @covers entity_api::create_entity
     * @covers entity_api::create_sub_entity
     * @covers entity_api::entity_exists
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\entity::assign_manager
     * @covers \local_mentor_core\entity::update
     * @covers \local_mentor_core\entity::create_edadmin_courses_if_missing
     * @covers \local_mentor_core\entity::get_context
     * @covers \local_mentor_core\entity::get_name
     * @covers \local_mentor_core\entity::get_edadmin_courses
     * @covers \local_mentor_core\entity::get_cohort
     * @covers \local_mentor_core\specialization::__construct
     */
    public function test_create_sub_entity_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Get entity main data.
        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Test if we have received an identifier.
        self::assertIsInt($entityid);

        // Get sub entity data.
        $subentitydata = $this->get_entities_data()[1];
        // Set parent's sub entity with main entity.
        $subentitydata['parentid'] = $entityid;

        // Test standard sub entity creation.
        try {
            $subentityid = entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Test if we have received an identifier.
        self::assertIsInt($subentityid);

        // Check name and parent of sub entity.
        $subentity = entity_api::get_entity($subentityid);
        self::assertEquals($subentity->get_name(), $subentitydata['name']);
        self::assertEquals($subentity->get_main_entity()->id, $entityid);

        // Try to create the same sub entity.
        try {
            entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test of the creation of a sub entity with name used
     *
     * @covers entity_api::create_entity
     * @covers entity_api::create_sub_entity
     * @covers entity_api::entity_exists
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\entity::assign_manager
     * @covers \local_mentor_core\entity::update
     * @covers \local_mentor_core\entity::create_edadmin_courses_if_missing
     * @covers \local_mentor_core\entity::get_context
     * @covers \local_mentor_core\entity::get_name
     * @covers \local_mentor_core\entity::get_edadmin_courses
     * @covers \local_mentor_core\entity::get_cohort
     */
    public function test_create_sub_entity_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Get entity main data.
        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Test if we have received an identifier.
        self::assertIsInt($entityid);

        // Get sub entity data.
        $subentitydata = $this->get_entities_data()[1];

        // Set an empty parent id.
        $subentitydata['parentid'] = '';

        // Test with an empty parent id.
        try {
            $subentityid = entity_api::create_sub_entity($subentitydata);
            self::fail();
        } catch (\Exception $e) {
            // Failed because parentid is empty.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Unset parent id.
        unset($subentitydata['parentid']);

        // Test with an empty parent id.
        try {
            $subentityid = entity_api::create_sub_entity($subentitydata);
            self::fail();
        } catch (\Exception $e) {
            // Failed because parentid is unset.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Set parent's sub entity with main entity.
        $subentitydata['parentid'] = $entityid;

        // Test standard sub entity creation.
        try {
            $subentityid = entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Test if we have received an identifier.
        self::assertIsInt($subentityid);

        // Check name and parent of sub entity.
        $subentity = entity_api::get_entity($subentityid);
        self::assertEquals($subentity->get_name(), $subentitydata['name']);
        self::assertEquals($subentity->get_main_entity()->id, $entityid);

        // Try to create the same sub entity.
        try {
            entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed because name of this entity is already in use.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test the entity update
     *
     * @covers entity_api::update_entity
     * @covers entity_api::create_entity
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\entity::update
     * @covers \local_mentor_core\entity::get_context
     * @covers \local_mentor_core\entity::get_name
     * @covers \local_mentor_core\entity::get_edadmin_courses
     * @covers \local_mentor_core\entity::rename_cohort
     * @covers \local_mentor_core\entity::get_cohort
     * @covers \local_mentor_core\entity::get_edadmin_courses
     * @covers \local_mentor_core\entity::get_logo
     *
     * @throws moodle_exception
     */
    public function test_update_entity_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $entity = entity_api::get_entity($entityid);

        $newdata = new stdClass();
        $newdata->name = 'Name updated';

        try {
            $result = entity_api::update_entity($entity->id, $newdata);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Check if the update is ok.
        self::assertTrue($result);

        self::resetAllData();
    }

    /**
     * Test the sub entity update
     *
     * @covers entity_api::update_entity
     * @covers entity_api::create_entity
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\entity::update
     * @covers \local_mentor_core\entity::get_context
     * @covers \local_mentor_core\entity::get_name
     * @covers \local_mentor_core\entity::get_edadmin_courses
     * @covers \local_mentor_core\entity::rename_cohort
     * @covers \local_mentor_core\entity::get_cohort
     * @covers \local_mentor_core\entity::get_edadmin_courses
     * @covers \local_mentor_core\entity::get_logo
     *
     * @throws moodle_exception
     */
    public function test_update_sub_entity_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            $subentityid = entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Set new data sub entity.
        $newdata = new stdClass();
        $newdata->name = 'Name updated';
        $newdata->parentid = $entityid;

        try {
            $result = entity_api::update_entity($subentityid, $newdata);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        $subentity = entity_api::get_entity($subentityid);

        // Check if the update is ok.
        self::assertTrue($result);
        self::assertNotEquals($subentity->get_name(), $subentitydata['name']);
        self::assertEquals($subentity->get_name(), $newdata->name);

        self::resetAllData();
    }

    /**
     * Test get entity
     *
     * @covers entity_api::get_entity
     * @covers entity_api::create_entity
     * @covers \local_mentor_core\specialization::__construct
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     * @covers \local_mentor_core\entity::__construct
     */
    public function test_get_entity_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Test get entity.
        try {
            $entity = entity_api::get_entity($entityid);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::assertEquals($entityid, $entity->id);

        self::resetAllData();
    }

    /**
     * Test get sub entity
     *
     * @covers entity_api::get_entity
     * @covers entity_api::create_entity
     * @covers \local_mentor_core\specialization::__construct
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     * @covers \local_mentor_core\entity::__construct
     */
    public function test_get_sub_entity_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            $subentityid = entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Test get sub entity.
        try {
            $subentity = entity_api::get_entity($subentityid);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::assertEquals($subentityid, $subentity->id);

        self::resetAllData();
    }

    /**
     * Test get entity by name
     *
     * @covers entity_api::get_entity_by_name
     * @covers entity_api::get_entity
     * @covers entity_api::create_entity
     */
    public function test_get_entity_by_name_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Test check entity.
        try {
            $entity = entity_api::get_entity_by_name($entitydata['name']);
        } catch (\Exception $e) {
            // Failed if entity does not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::assertIsObject($entity);
        self::assertEquals($entitydata['name'], $entity->name);

        // Test check only main entity.
        try {
            $entity = entity_api::get_entity_by_name($entitydata['name'], true);
        } catch (\Exception $e) {
            // Failed if entity does not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::assertIsObject($entity);
        self::assertEquals($entitydata['name'], $entity->name);

        self::resetAllData();
    }

    /**
     * Test get sub entity by name
     *
     * @covers entity_api::get_entity_by_name
     * @covers entity_api::get_entity
     * @covers entity_api::create_entity
     */
    public function test_get_sub_entity_by_name_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Test get sub entity with name.
        try {
            $subentity = entity_api::get_entity_by_name($subentitydata['name']);
        } catch (\Exception $e) {
            // Failed if entity not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::assertIsObject($subentity);
        self::assertEquals($subentitydata['name'], $subentity->name);

        self::resetAllData();
    }

    /**
     * Test get all entities function
     *
     * @covers  entity_api::get_all_entities
     * @covers  entity_api::create_entity
     * @covers  entity_api::get_entity
     */
    public function test_get_all_entities_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        $entitiesdata = $this->get_entities_data();

        // Create all entities.
        foreach ($entitiesdata as $entitydata) {
            // Test standard Entity creation.
            try {
                $entityid = entity_api::create_entity($entitydata);
            } catch (\Exception $e) {
                // Failed if the name of this entity is already in use.
                self::fail($e->getMessage());
            }
        }

        // Get all entities.
        try {
            $allentities = entity_api::get_all_entities(true, [], true);
        } catch (\Exception $e) {
            // Moodle exception.
            self::fail($e->getMessage());
        }

        // Check if all entities had been created.
        self::assertEquals(count($entitiesdata), count($allentities));

        // Get all entities with last entity exclude.
        try {
            $allentitieswithexlude = entity_api::get_all_entities(true, [$entityid], true);
        } catch (\Exception $e) {
            // Moodle exception.
            self::fail($e->getMessage());
        }

        // Check if all entities had been created.
        self::assertEquals(count($entitiesdata) - 1, count($allentitieswithexlude));

        self::resetAllData();
    }

    /**
     * Test get_all_entities with search
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  entity_api::get_all_entities
     */
    public function test_get_all_entities_ok_with_search() {
        global $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entityid = entity_api::create_entity([
                'name' => 'New Entity 1',
                'shortname' => 'New Entity 1',
        ]);

        $entityid2 = entity_api::create_entity([
                'name' => 'Sub Entity 2',
                'shortname' => 'Sub Entity 2',
                'parentid' => $entityid,
        ]);

        $filter = new \stdClass();
        $filter->search['value'] = 'entity';
        $filter->order['dir'] = 'ASC';
        $filter->start = 0;
        $filter->length = 100;

        $allentities = entity_api::get_all_entities(false, [], true, $filter);
        self::assertCount(2, $allentities);
        self::assertEquals($allentities[0]->id, $entityid);
        self::assertEquals($allentities[1]->id, $entityid2);

        $filter = new \stdClass();
        $filter->search['value'] = 'New';
        $filter->order['dir'] = 'ASC';
        $filter->start = 0;
        $filter->length = 100;

        $allentities = entity_api::get_all_entities(false, [], true, $filter);
        self::assertCount(2, $allentities);
        self::assertEquals($allentities[0]->id, $entityid);
        self::assertEquals($allentities[1]->id, $entityid2);

        $filter = new \stdClass();
        $filter->search['value'] = 'Sub';
        $filter->order['dir'] = 'ASC';
        $filter->start = 0;
        $filter->length = 100;

        $allentities = entity_api::get_all_entities(false, [], true, $filter);
        self::assertCount(1, $allentities);
        self::assertEquals($allentities[0]->id, $entityid2);

        self::resetAllData();
    }

    /**
     * Test get_all_entities with order
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  entity_api::count_managed_entities
     */
    public function test_get_all_entities_ok_with_order() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        $entityid = entity_api::create_entity([
                'name' => 'New Entity A',
                'shortname' => 'New Entity A',
        ]);

        $entityid2 = entity_api::create_entity([
                'name' => 'Sub Entity A',
                'shortname' => 'Sub Entity A',
                'parentid' => $entityid,
        ]);

        $entityid3 = entity_api::create_entity([
                'name' => 'New Entity B',
                'shortname' => 'New Entity B',
        ]);

        $filter = new \stdClass();
        $filter->search['value'] = null;
        $filter->order['dir'] = 'ASC';
        $filter->start = 0;
        $filter->length = 100;

        $allentities = entity_api::get_all_entities(false, [], true, $filter);
        self::assertCount(3, $allentities);
        self::assertEquals($allentities[0]->id, $entityid);
        self::assertEquals($allentities[1]->id, $entityid2);
        self::assertEquals($allentities[2]->id, $entityid3);

        $filter = new \stdClass();
        $filter->search['value'] = null;
        $filter->order['dir'] = 'DESC';
        $filter->start = 0;
        $filter->length = 100;

        $allentities = entity_api::get_all_entities(false, [], true, $filter);
        self::assertCount(3, $allentities);
        self::assertEquals($allentities[0]->id, $entityid3);
        self::assertEquals($allentities[1]->id, $entityid2);
        self::assertEquals($allentities[2]->id, $entityid);

        self::resetAllData();
    }

    /**
     * Test get all entities function with sub entity
     *
     * @covers  entity_api::get_all_entities
     * @covers  entity_api::create_entity
     * @covers  entity_api::get_entity
     */
    public function test_get_all_entities_with_sub_entity_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Get all entities.
        try {
            $allentities = entity_api::get_all_entities(false, [], true);
        } catch (\Exception $e) {
            // Moodle exception.
            self::fail($e->getMessage());
        }

        // Check if all entities had been created.
        self::assertEquals(count($this->get_entities_data()), count($allentities));

        // Get all entities with last entity exclude.
        try {
            $allentitieswithexlude = entity_api::get_all_entities(false, [$entityid], true);
        } catch (\Exception $e) {
            // Moodle exception.
            self::fail($e->getMessage());
        }

        // Check if all entities had been created.
        self::assertEquals(count($this->get_entities_data()) - 1, count($allentitieswithexlude));

        self::resetAllData();
    }

    /**
     * Test if entity exist
     *
     * @covers  entity_api::entity_exists
     * @covers  entity_api::create_entity
     */
    public function test_entity_exists_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Check if entity exist after its creation.
        try {
            self::assertTrue(entity_api::entity_exists($entitydata['name']));
        } catch (\moodle_exception $e) {
            self::fail($e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test if sub entity exist
     *
     * @covers  entity_api::entity_exists
     * @covers  entity_api::create_entity
     * @covers  \local_mentor_core\database_interface::get_sub_entity_by_name
     */
    public function test_sub_entity_exists_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Check if sub entity exist after its creation.
        try {
            self::assertTrue(entity_api::entity_exists($subentitydata['name']));
        } catch (\moodle_exception $e) {
            self::fail($e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test of effect of a user as manager of an entity
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  entity_api::get_managed_entities
     * @covers  entity_api::get_managed_entities_object
     * @covers  entity_api::get_all_entities
     * @covers  entity_api::create_entity
     * @covers  entity_api::get_entity
     * @covers  \local_mentor_core\entity::is_manager
     * @covers  \local_mentor_core\entity::get_members
     * @covers  \local_mentor_core\entity::get_cohort
     * @covers  \local_mentor_core\entity::assign_manager
     */
    public function test_get_managed_entities_ok() {
        global $USER, $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $DB->delete_records('course_categories');
        testhelper::create_default_entity($this);

        $newuser = self::getDataGenerator()->create_user();

        $entitydata = $this->get_entities_data()[0];
        $entitydata['userid'] = 0; // Remove the manager.

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            $subentityid = entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        self::setUser($newuser);

        $managedentities = entity_api::get_managed_entities();

        // The user must not manage any entity at this point.
        self::assertCount(0, $managedentities);

        try {
            $entity = entity_api::get_entity($entityid);
        } catch (\Exception $e) {
            // Fail if entity not exist.
            self::fail($e->getMessage());
        }

        try {
            $subentity = entity_api::get_entity($subentityid);
        } catch (\Exception $e) {
            // Fail if entity not exist.
            self::fail($e->getMessage());
        }

        // No members exist.
        self::assertCount(0, $subentity->get_members());

        // Standard test assign user.
        // If return true, the user has been assigned.
        try {
            self::assertTrue($entity->assign_manager($newuser->id));
        } catch (\Exception $e) {
            // Fail if user not exist.
            self::fail($e->getMessage());
        }

        // One members exist.
        self::assertCount(0, $subentity->get_members());

        $managedentities = entity_api::get_managed_entities();

        // Check if the user can manage the entity.
        self::assertCount(1, $managedentities);

        self::setAdminUser();

        $managedentities = entity_api::get_managed_entities($USER, false);

        // Check if the user can manage the entity. 
        // Two created just before, +1 created at the begining to insert a default entity (create_default_entity).
        self::assertCount(3, $managedentities);

        self::resetAllData();
    }

    /**
     * Test of effect of a user as manager of an entity
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  entity_api::get_managed_entities
     * @covers  entity_api::get_managed_entities_object
     * @covers  entity_api::get_all_entities
     * @covers  entity_api::create_entity
     * @covers  entity_api::get_entity
     * @covers  \local_mentor_core\entity::is_manager
     * @covers  \local_mentor_core\entity::get_members
     * @covers  \local_mentor_core\entity::get_cohort
     * @covers  \local_mentor_core\entity::assign_manager
     */
    public function test_get_managed_entiies_ok_with_other_manage() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        // Disable event and observer.
        self::redirectEvents();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        $newuser = self::getDataGenerator()->create_user();

        $entity1id = entity_api::create_entity(
            [
                'name' => 'entity1',
                'shortname' => 'entity1',
                'userid' => $newuser->id,
            ]
        );

        $entity2id = entity_api::create_entity(
            [
                'name' => 'entity2',
                'shortname' => 'entity2',
            ]
        );

        $entity2 = entity_api::get_entity($entity2id);
        self::getDataGenerator()->role_assign('referentlocal', $newuser->id, $entity2->get_context()->id);

        $managedentities = entity_api::get_managed_entities($newuser);

        // Check if the user can manage the entity.
        self::assertCount(1, $managedentities);

        $managedentities = entity_api::get_managed_entities($newuser, true, null, false, true, true);

        // Check if the user can manage the entity or session/training to the entity.
        self::assertCount(2, $managedentities);

        self::resetAllData();
    }

    /**
     * Test of effect of a user as manager of an entity
     * with filter
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  entity_api::get_managed_entities
     * @covers  entity_api::get_managed_entities_object
     * @covers  \local_mentor_core\database_interface::get_all_entities
     */
    public function test_get_managed_entities_ok_with_filter() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();
        local_library_init_config();

        $DB->delete_records('course_categories');

        $firstentityid = testhelper::create_default_entity($this, 'entity0');
        entity_api::create_entity(
            [
                    'name' => 'subentity0',
                    'shortname' => 'subentity0',
                    'parentid' => $firstentityid,
            ]
        );

        $newuser = self::getDataGenerator()->create_user();

        for ($i = 1; $i < 10; $i++) {
            $entityname = 'entity' . $i;

            $entityid = entity_api::create_entity(
                    [
                            'name' => $entityname,
                            'shortname' => $entityname,
                    ]
            );

            entity_api::create_entity(
                    [
                            'name' => 'sub' . $entityname,
                            'shortname' => 'sub' . $entityname,
                            'parentid' => $entityid,
                    ]
            );
        }

        $data = new \stdClass();
        $data->search = null;
        $data->order = [];
        $data->order['dir'] = 'ASC';
        $data->search = ['value' => null];
        $data->draw = null;
        $data->length = 10;
        $data->start = 0;

        // With admin.
        $managedentities = entity_api::get_managed_entities(null, false, $data, true);
        self::assertCount(10, $managedentities);
        self::assertEquals('entity0', current($managedentities)->name);

        $data->length = 5;
        $data->start = 5;
        $managedentities = entity_api::get_managed_entities(null, false, $data, true);
        self::assertCount(5, $managedentities);
        self::assertEquals('subentity2', current($managedentities)->name);

        $data->length = 10;
        $data->start = 0;
        $data->order['dir'] = 'DESC';
        $managedentities = entity_api::get_managed_entities(null, false, $data, true);
        self::assertCount(10, $managedentities);
        self::assertEquals('subentity9', current($managedentities)->name);

        $data->order['dir'] = 'ASC';
        $data->search = ['value' => 'entity3'];
        $managedentities = entity_api::get_managed_entities(null, false, $data, true);
        self::assertCount(2, $managedentities);
        self::assertEquals('entity3', current($managedentities)->name);

        for ($i = 10; $i < 20; $i++) {
            $entityname = 'entity' . $i;

            $entityid = entity_api::create_entity(
                    [
                            'name' => $entityname,
                            'shortname' => $entityname,
                            'userid' => $newuser->id,
                    ]
            );

            entity_api::create_entity(
                    [
                            'name' => 'sub' . $entityname,
                            'shortname' => 'sub' . $entityname,
                            'parentid' => $entityid,
                    ]
            );
        }

        // With user.
        $data = new \stdClass();
        $data->search = null;
        $data->order = [];
        $data->order['dir'] = 'ASC';
        $data->search = ['value' => null];
        $data->draw = null;
        $data->length = 10;
        $data->start = 0;

        $managedentities = entity_api::get_managed_entities($newuser, false, $data, true);
        self::assertCount(10, $managedentities);
        self::assertEquals('entity10', current($managedentities)->name);

        $data->length = 5;
        $data->start = 5;
        $managedentities = entity_api::get_managed_entities($newuser, false, $data, true);
        self::assertCount(5, $managedentities);
        self::assertEquals('subentity12', current($managedentities)->name);

        $data->length = 10;
        $data->start = 0;
        $data->order['dir'] = 'DESC';
        $managedentities = entity_api::get_managed_entities($newuser, false, $data, true);
        self::assertCount(10, $managedentities);
        self::assertEquals('subentity19', current($managedentities)->name);

        $data->order['dir'] = 'ASC';
        $data->search = ['value' => 'entity13'];
        $managedentities = entity_api::get_managed_entities($newuser, false, $data, true);
        self::assertCount(2, $managedentities);
        self::assertEquals('entity13', current($managedentities)->name);

        self::resetAllData();
    }

    /**
     * Test get entities list
     *
     * @covers  entity_api::get_entities_list
     * @covers  entity_api::get_all_entities
     * @covers  entity_api::get_entity
     * @covers  entity_api::create_entity
     */
    public function test_get_entities_list_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        $entitiesdata = $this->get_entities_data();

        $list = [];

        // Create all entities.
        foreach ($entitiesdata as $entitydata) {
            // Test standard Entity creation.
            try {
                $entityid = entity_api::create_entity($entitydata);
                $list[] = $entitydata['name'];
            } catch (\Exception $e) {
                // Failed if the name of this entity is already in use.
                self::fail($e->getMessage());
            }
        }

        try {
            self::assertEquals(
                    implode("\n", $list),
                    entity_api::get_entities_list(true, true)
            );
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test get entities list with sub entity
     *
     * @covers  entity_api::get_entities_list
     * @covers  entity_api::get_all_entities
     * @covers  entity_api::get_entity
     * @covers  entity_api::create_entity
     */
    public function test_get_entities_list_with_sub_entity_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        $list = [];

        $entitydata = $this->get_entities_data()[0];
        $entitydata['userid'] = 0; // Remove the manager.

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
            $list[] = $entitydata['name'];
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            $subentityid = entity_api::create_entity($subentitydata);
            $list[] = $subentitydata['name'];
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        try {
            self::assertEquals(
                    implode("\n", $list),
                    entity_api::get_entities_list(false, true)
            );
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test of the no creation of an entity
     *
     * @covers  entity_api::create_entity
     */
    public function test_create_entity_nok() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];

        // Test with missing entity shortname.
        try {
            $entity = entity_api::create_entity(['name' => 'missingshortname']);
        } catch (\Exception $e) {
            // Failed if the shortname is missing.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Test with empty entity shortname.
        try {
            $entity = entity_api::create_entity(['name' => 'missingshortname', 'shortname' => '']);
        } catch (\Exception $e) {
            // Failed if the shortname is empty.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Test with invalid entity shortname.
        try {
            $entity = entity_api::create_entity(['name' => 'missingshortname', 'shortname' => '<>']);
        } catch (\Exception $e) {
            // Failed if the shortname is invalid.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Test with no entity name.
        try {
            $entity = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Test duplicate entity.
        try {
            $entity = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Test with an empty entity name.
        try {
            $entity = entity_api::create_entity(['name' => '']);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Test with a shortname too long.
        try {
            $entity = entity_api::create_entity([
                    'name' => 'entityname',
                    'shortname' => 'shortname too long for this entity',
            ]);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Create a valid entity.
        try {
            $entity = entity_api::create_entity([
                    'name' => 'entityname',
                    'shortname' => 'entityshortname',
            ]);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Test with a duplicated shortname.
        try {
            $entity = entity_api::create_entity([
                    'name' => 'entityname2',
                    'shortname' => 'entityshortname',
            ]);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test get entity fail
     *
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\specialization::__construct
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     */
    public function test_get_entity_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $falseentityid = 10;

        // Test entity not found.
        try {
            $entity = entity_api::get_entity($falseentityid);
            self::fail('Not possible exist');
        } catch (\Exception $e) {
            // Entity not exist.
            self::assertInstanceOf('dml_missing_record_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test get entity by name not exist
     *
     * @covers entity_api::get_entity_by_name
     * @covers entity_api::get_entity
     */
    public function test_get_entity_by_name_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $existingentity = 'Entity Inexistant Test Name ';

        try {
            entity_api::get_entity_by_name($existingentity);
        } catch (\Exception $e) {
            self::assertInstanceOf('dml_missing_record_exception', $e);
        }

        self::resetAllData();
    }

    /**
     * Test if entity not exist
     *
     * @covers  entity_api::entity_exists
     */
    public function test_entity_exists_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Check if entity not exist.
        self::assertFalse(entity_api::entity_exists('Entity Inexistant Test Name'));
    }

    /**
     * Test of assigning an unknown user as manager of an entity
     *
     * @covers entity_api::get_managed_entities
     * @covers entity_api::get_all_entities
     * @covers entity_api::create_entity
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\entity::is_manager
     * @covers \local_mentor_core\entity::get_members
     * @covers \local_mentor_core\entity::assign_manager
     */
    public function test_get_managed_entities_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $this->init_database();

        // Userid=3 is an unregistered user.
        $userid = 3;

        try {
            $entityid = entity_api::create_entity(['name' => 'Test managed entity', 'shortname' => 'TME']);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        try {
            $entity = entity_api::get_entity($entityid);
        } catch (\Exception $e) {
            // Fail if entity not exist.
            self::fail($e->getMessage());
        }

        self::assertCount(0, $entity->get_members());

        // Standard test assign user.
        // If return true, the user has been assigned.
        try {
            $entity->assign_manager($userid);
        } catch (\Exception $e) {
            // Failled because user not exist.
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::assertCount(0, $entity->get_members());

        self::resetAllData();
    }

    /**
     * Test get entity form
     *
     * @covers entity_api::get_entity_form
     * @covers entity_api::create_entity
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\specialization::__construct
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     * @covers \local_mentor_core\entity_form::definition
     * @covers \local_mentor_core\entity::create_edadmin_courses_if_missing
     * @covers \local_mentor_core\entity::get_edadmin_courses
     */
    public function test_get_entity_form_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $this->init_database();

        try {
            $entityid = entity_api::create_entity(['name' => 'Test managed entity', 'shortname' => 'TME']);
            $entity = entity_api::get_entity($entityid);
            $entity->create_edadmin_courses_if_missing();
            $entitycourse = $entity->get_edadmin_courses('entities');
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $url = new moodle_url('/course/view.php', ['id' => $entitycourse['id']]);

        $entityform = entity_api::get_entity_form($url->out(), $entity->id);

        // Check if the returned value is an object.
        self::assertIsObject($entityform);
        self::assertInstanceOf('local_mentor_core\entity_form', $entityform);

        self::resetAllData();
    }

    /**
     * Test get sub entity form
     *
     * @covers entity_api::get_entity_form
     * @covers entity_api::get_sub_entity_form
     * @covers entity_api::create_entity
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\specialization::__construct
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     * @covers \local_mentor_core\entity_form::definition
     * @covers \local_mentor_core\entity::create_edadmin_courses_if_missing
     * @covers \local_mentor_core\entity::get_edadmin_courses
     */
    public function test_get_sub_entity_form_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $this->init_database();

        $entitydata = $this->get_entities_data()[0];
        $entitydata['userid'] = 0; // Remove the manager.

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            $subentityid = entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        try {
            $subentity = entity_api::get_entity($subentityid);
            $subentity->create_edadmin_courses_if_missing();
            $subentitycourse = $subentity->get_edadmin_courses('entities');
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $url = new moodle_url('/course/view.php', ['id' => $subentitycourse['id']]);

        $entityform = entity_api::get_entity_form($url->out(), $subentity->id);

        // Check if the returned value is an object.
        self::assertIsObject($entityform);
        self::assertInstanceOf('local_mentor_core\sub_entity_form', $entityform);

        self::resetAllData();
    }

    /**
     * Test get new entity form
     *
     * @covers entity_api::get_new_entity_form
     * @covers \local_mentor_core\specialization::__construct
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     */
    public function test_get_new_entity_form_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();
        $entityform = entity_api::get_new_entity_form();
        self::assertIsString($entityform);
        self::resetAllData();
    }

    /**
     * Test get new sub entity form
     *
     * @covers entity_api::get_new_sub_entity_form
     * @covers \local_mentor_core\specialization::__construct
     * @covers \local_mentor_core\specialization::get_instance
     * @covers \local_mentor_core\specialization::get_specialization
     */
    public function test_get_new_sub_entity_form_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();
        $entityform = entity_api::get_new_sub_entity_form();
        self::assertIsString($entityform);
        self::resetAllData();
    }

    /**
     * Test get user entities
     *
     * @covers entity_api::get_user_entities
     * @covers entity_api::get_entity
     * @covers entity_api::create_entity
     * @covers \local_mentor_core\entity::add_member
     */
    public function test_get_user_entities_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $newuser = self::getDataGenerator()->create_user();

        // The user is a member of the default entity => auto-attach of main entity
        $userentities = entity_api::get_user_entities($newuser->id);
        self::assertEquals(1, count($userentities));

        $entitydata = $this->get_entities_data()[0];
        $entitydata['userid'] = 0; // Remove the manager.

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
            $entity = entity_api::get_entity($entityid);

            $entity->add_member($newuser);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            $subentityid = entity_api::create_entity($subentitydata);
            $subentity = entity_api::get_entity($subentityid);

            $subentity->add_member($newuser);

        } catch (\Exception $e) {
            // Failed if is sub entity.
            self::assertInstanceOf('moodle_exception', $e);
        }

        // Check if the user is a member of each main entity.
        $userentities = entity_api::get_user_entities($newuser->id);

        self::assertEquals(count($this->get_entities_data()), count($userentities));

        self::resetAllData();
    }

    /**
     * Test of number of Edadmin courses in an entity
     *
     * @covers entity_api::create_entity
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\entity::get_edadmin_courses
     */
    public function test_number_of_edadmin_courses_in_an_entity() {
        global $CFG;
        require_once($CFG->dirroot . '/local/entities/lib.php');

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity(['name' => 'Test local entities', 'shortname' => 'TLE']);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $listtypecourse = format_edadmin::get_all_type_name();

        // Test of number of edadmin course in the entity.
        try {
            $entity = entity_api::get_entity($entityid);
            self::assertCount(count($listtypecourse),
                    $entity->get_edadmin_courses());
        } catch (\Exception $e) {
            // Fail if entity does not exist.
            self::fail($e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test of number of Edadmin courses in a sub entity
     *
     * @covers entity_api::create_entity
     * @covers entity_api::get_entity
     * @covers \local_mentor_core\entity::get_edadmin_courses
     */
    public function test_number_of_edadmin_courses_in_a_sub_entity() {
        global $CFG;
        require_once($CFG->dirroot . '/local/entities/lib.php');

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];
        $entitydata['userid'] = 0; // Remove the manager.

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            $subentityid = entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $listtypecourse = format_edadmin::get_all_type_name();
        $listedadmincourseexception = \local_mentor_core\entity::SUB_ENTITY_EDADMIN_EXCEPT;
        $listedadmincourseexceptionbutmustseen = \local_mentor_core\entity::SUB_ENTITY_COURSES_EXCLUDED_BUT_MUST_SEEN;

        // Test of number of edadmin course in the sub entity.
        try {
            $subentity = entity_api::get_entity($subentityid);
            self::assertCount((count($listtypecourse) - count($listedadmincourseexception)) +
                              count($listedadmincourseexceptionbutmustseen),
                    $subentity->get_edadmin_courses());
        } catch (\Exception $e) {
            // Fail if entity does not exist.
            self::fail($e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test search main entities
     *
     * @covers entity_api::search_main_entities
     */
    public function test_search_main_entities() {
        global $CFG;
        require_once($CFG->dirroot . '/local/entities/lib.php');

        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];
        $entitydata['userid'] = 0; // Remove the manager.

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
            $entity1 = entity_api::get_entity($entityid);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $entitydata = $this->get_entities_data()[1];
        $entitydata['userid'] = 0; // Remove the manager.

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        $searchtext = 'New Entity';

        // Search as an admin.
        $result = entity_api::search_main_entities($searchtext);
        self::assertCount(2, $result);

        // Set an entity manager.
        $newuser = self::getDataGenerator()->create_user();
        $entity1->assign_manager($newuser->id);
        self::setUser($newuser);

        // Search as a manager.
        $result = entity_api::search_main_entities($searchtext);
        self::assertCount(1, $result);

        self::resetAllData();
    }

    /**
     * Test get_entity_javascript
     *
     * @covers entity_api::get_entity_javascript
     *
     */
    public function test_get_entity_javascript() {
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        try {
            entity_api::get_entity_javascript('local_entities/local_entities');
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        self::resetAllData();
    }

    /**
     * Test get sub entity form
     *
     * @covers entity_api::get_entity
     * @covers entity_api::has_sub_entities
     * @covers \local_mentor_core\entity::has_sub_entities
     */
    public function test_has_sub_entities_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $this->init_database();

        $entitydata = $this->get_entities_data()[0];
        $entitydata['userid'] = 0; // Remove the manager.

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Has not sub entity.
        self::assertFalse(entity_api::has_sub_entities($entityid));

        $subentitydata = $this->get_entities_data()[1];
        $subentitydata['parentid'] = $entityid;

        // Test standard sub Entity creation.
        try {
            $subentityid = entity_api::create_entity($subentitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Has sub entity.
        self::assertTrue(entity_api::has_sub_entities($entityid));

        self::resetAllData();
    }

    /**
     * Test cleanup training recyblebin without redirection
     *
     * @covers entity_api::cleanup_training_recyblebin
     *
     */
    public function test_cleanup_training_recyblebin_without_redirection() {
        global $CFG;
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $trainingdata = $this->get_training_data();
        $training = \local_mentor_core\training_api::create_training($trainingdata);
        $trainingdata->shortname = "shortname2";
        $trainingdata->name = "fullname2";
        $training2 = \local_mentor_core\training_api::create_training($trainingdata);
        $entity = $training->get_entity();

        local_mentor_core\training_api::remove_training($training->id);
        self::assertCount(1, $entity->get_training_recyclebin_items());

        local_mentor_core\training_api::remove_training($training2->id);
        self::assertCount(2, $entity->get_training_recyclebin_items());

        entity_api::cleanup_training_recyblebin($entity->id);

        self::assertCount(0, $entity->get_training_recyclebin_items());

        self::resetAllData();
    }

    /**
     * Test cleanup session recyblebin without redirection
     *
     * @covers entity_api::cleanup_session_recyblebin
     *
     */
    public function test_cleanup_session_recyblebin_without_redirection() {
        global $CFG;
        $this->resetAfterTest();
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $training = \local_mentor_core\training_api::create_training($this->get_training_data());
        $sessionid1 = \local_mentor_core\session_api::create_session($training->id, 'session1', true);
        $session1 = \local_mentor_core\session_api::get_session($sessionid1, true);
        $sessionid2 = \local_mentor_core\session_api::create_session($training->id, 'session2', true);
        $session2 = \local_mentor_core\session_api::get_session($sessionid2, true);
        $entity = $training->get_entity();

        $session1->delete();
        self::assertCount(1, $entity->get_sessions_recyclebin_items());

        $session2->delete();
        self::assertCount(2, $entity->get_sessions_recyclebin_items());

        entity_api::cleanup_session_recyblebin($entity->id);

        self::assertCount(0, $entity->get_sessions_recyclebin_items());

        self::resetAllData();
    }

    /**
     * Test if entity shortname exists
     *
     * @covers entity_api::shortname_exists
     *
     */
    public function test_shortname_exists() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $entitydata = $this->get_entities_data()[0];
        $entitydata['shortname'] = 'shortnameentity';

        // Test standard Entity creation.
        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            // Failed if the name of this entity is already in use.
            self::fail($e->getMessage());
        }

        // Test if we have received an identifier.
        self::assertIsInt($entityid);

        self::assertFalse(entity_api::shortname_exists('wrongshortname'));
        self::assertFalse(entity_api::shortname_exists('shortnameentity', $entityid));

        self::assertTrue(entity_api::shortname_exists('shortnameentity'));

        self::resetAllData();
    }

    /**
     * Test if entity shortname exists
     *
     * @covers entity_api::get_entities_where_sessions_managed
     *
     */
    public function test_get_entities_where_sessions_managed() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        $entitydata = $this->get_entities_data()[0];
        $entityid = entity_api::create_entity($entitydata);
        $entity = entity_api::get_entity($entityid);
        $entity2data = $this->get_entities_data()[1];
        $entity2id = entity_api::create_entity($entity2data);
        entity_api::get_entity($entity2id);

        $user1entitysessionmanage = entity_api::get_entities_where_sessions_managed();

        self::assertIsArray($user1entitysessionmanage);
        self::assertCount(2, $user1entitysessionmanage);
        self::assertArrayHasKey($entityid, $user1entitysessionmanage);
        self::assertArrayHasKey($entity2id, $user1entitysessionmanage);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        $entity->assign_manager($user1->id);

        $user1entitysessionmanage = entity_api::get_entities_where_sessions_managed($user1);

        self::assertIsArray($user1entitysessionmanage);
        self::assertCount(1, $user1entitysessionmanage);
        self::assertArrayHasKey($entityid, $user1entitysessionmanage);

        $user1entitysessionmanage = entity_api::get_entities_where_sessions_managed($user2);

        self::assertIsArray($user1entitysessionmanage);
        self::assertCount(0, $user1entitysessionmanage);

        self::resetAllData();
    }

    /**
     * Test get_entities_can_import_training_library_object
     *
     * @covers entity_api::get_entities_can_import_training_library_object
     *
     */
    public function test_get_entities_can_import_training_library_object() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        $entitydata = $this->get_entities_data()[0];
        $entityid = entity_api::create_entity($entitydata);
        $entity = entity_api::get_entity($entityid);
        $entity2data = $this->get_entities_data()[1];
        $entity2id = entity_api::create_entity($entity2data);
        $entity2 = entity_api::get_entity($entity2id);

        // Admin.
        $adminentitysessionmanage = entity_api::get_entities_can_import_training_library_object();

        self::assertIsArray($adminentitysessionmanage);
        self::assertCount(2, $adminentitysessionmanage);
        self::assertArrayHasKey($entityid, $adminentitysessionmanage);
        self::assertArrayHasKey($entity2id, $adminentitysessionmanage);

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        // Entity manager.
        $entity->assign_manager($user1->id);

        self::setUser($user1);

        $user1entitysessionmanage = entity_api::get_entities_can_import_training_library_object();

        self::assertIsArray($user1entitysessionmanage);
        self::assertCount(1, $user1entitysessionmanage);
        self::assertArrayHasKey($entityid, $user1entitysessionmanage);
        self::assertArrayNotHasKey($entity2id, $user1entitysessionmanage);

        self::setAdminUser();

        $rfc = $DB->get_record('role', ['shortname' => 'respformation']);
        role_assign($rfc->id, $user2->id, $entity2->get_context()->id);

        self::setUser($user2);

        // Responsable central de formation.
        $user2entitysessionmanage = entity_api::get_entities_can_import_training_library_object();

        self::assertIsArray($user2entitysessionmanage);
        self::assertCount(1, $user2entitysessionmanage);
        self::assertArrayNotHasKey($entityid, $user2entitysessionmanage);
        self::assertArrayHasKey($entity2id, $user2entitysessionmanage);

        self::setAdminUser();

        $rfl = $DB->get_record('role', ['shortname' => 'referentlocal']);
        role_assign($rfl->id, $user3->id, $entity->get_context()->id);

        self::setUser($user3);

        // Référent local de formation.
        $user3entitysessionmanage = entity_api::get_entities_can_import_training_library_object();

        self::assertIsArray($user3entitysessionmanage);
        self::assertEmpty($user3entitysessionmanage);

        self::resetAllData();
    }

    /**
     * Test get edadmin course view capability
     *
     * @covers entity_api::get_edadmin_course_view_capability
     *
     */
    public function test_get_edadmin_course_view_capability_entity() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        self::assertEquals(
            'local/entities:manageentity',
            entity_api::get_edadmin_course_view_capability()
        );

        self::resetAllData();
    }

 /**
     * Test get_main_entity_by_shortname function
     */
    public function test_get_main_entity_by_shortname() {
        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_role();

        self::setAdminUser();

        // Create a main entity.
        $entitydata = [
            'name' => 'Main Entity',
            'shortname' => 'mainentity'
        ];

        try {
            $entityid = entity_api::create_entity($entitydata);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        // Test if the entity can be retrieved by shortname.

        $entity = entity_api::get_main_entity_by_shortname('mainentity');


        self::assertEquals($entityid, $entity->id);
        self::assertEquals('Main Entity', $entity->name);
        self::assertEquals('mainentity', $entity->shortname);

        // Test with a non-existing shortname.

        $entity = entity_api::get_main_entity_by_shortname('nonexistentshortname');
        self::assertFalse($entity);
        self::resetAllData();
    }

    public function test_check_entity_no_more_domain_link()
    {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        global $DB;

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);

        // test category with no domains link
        $iscategorycanbesecondaryentity = entity_api::check_entity_no_more_domain_link(["courseid" => $course->id]);
        self::assertTrue($iscategorycanbesecondaryentity);

        // test category with at least 1 domain link
        $domainname = 'mail.com';
        $domain = (object) [
            'course_categories_id' => $category->id,
            'domain_name' => $domainname,
            'created_at' => time(),
            'disabled_at' => null
        ];
        $DB->insert_record('course_categories_domains', $domain, false);

        $iscategorycanbesecondaryentity = entity_api::check_entity_no_more_domain_link(["courseid" => $course->id]);
        self::assertFalse($iscategorycanbesecondaryentity);
        
        // test category with no more domains link
        $categoriesdomainsrepository = new categories_domains_repository();
        $categoriesdomainsrepository->delete_domain($category->id, $domainname);

        $iscategorycanbesecondaryentity = entity_api::check_entity_no_more_domain_link(["courseid" => $course->id]);
        self::assertTrue($iscategorycanbesecondaryentity);
    }
}
