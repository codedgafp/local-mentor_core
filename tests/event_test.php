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
 * Test cases for mentor core event
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/profile.php');

class local_mentor_core_event_testcase extends advanced_testcase {

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
                'name' => 'New Entity 1',
                'shortname' => 'New Entity 1',
        ];
    }

    /**
     * Init training creation
     *
     * @return stdClass
     * @throws moodle_exception
     */
    public function init_training_creation() {

        $entityid = \local_mentor_core\entity_api::create_entity([
                'name' => 'New Entity 1',
                'shortname' => 'New Entity 1',
        ]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $data = new stdClass();
        $data->name = 'fullname';
        $data->shortname = 'shortname';
        $data->content = 'summary';
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        // Fields for taining.
        $data->traininggoal = 'TEST TRAINING ';
        $data->thumbnail = '';
        $formationid = $entity->get_entity_formation_category();
        $data->categorychildid = $formationid;
        $data->categoryid = $entity->id;
        $data->creativestructure = $entity->id;

        return $data;
    }

    /**
     * Init session creation
     *
     * @return \local_mentor_core\session
     * @throws moodle_exception
     */
    public function init_session_creation() {

        $entityid = \local_mentor_core\entity_api::create_entity([
                'name' => 'New Entity 1',
                'shortname' => 'New Entity 1',
        ]);

        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $data = new stdClass();
        $data->name = 'fullname';
        $data->shortname = 'shortname';
        $data->content = 'summary';
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        // Fields for taining.
        $data->traininggoal = 'TEST TRAINING ';
        $data->thumbnail = '';
        $formationid = $entity->get_entity_formation_category();
        $data->categorychildid = $formationid;
        $data->categoryid = $entity->id;
        $data->creativestructure = $entity->id;

        $training = \local_mentor_core\training_api::create_training($data);

        $sessionname = 'TESTUNITCREATESESSION';

        // Test standard training creation.
        try {
            $session = \local_mentor_core\session_api::create_session($training->id, $sessionname, true);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }

        return $session;
    }

    /**
     * Test entity create event
     *
     * @covers \local_mentor_core\event\entity_create::init
     * @covers \local_mentor_core\event\entity_create::get_name
     * @covers \local_mentor_core\event\entity_create::get_url
     * @covers \local_mentor_core\event\entity_create::get_description
     * @covers \local_mentor_core\event\entity_create::get_legacy_logdata
     * @covers \local_mentor_core\event\entity_create::get_objectid_mapping
     */
    public function test_entity_create_event() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $entitydata = $this->get_entities_data();
        $entityid = \local_mentor_core\entity_api::create_entity($entitydata);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        // Entity created event.
        $event = \local_mentor_core\event\entity_create::create([
                'objectid' => $entityid,
                'context' => $entity->get_context(),
                'other' => [
                        'name' => $entity->get_name(),
                        'managementcourseid' => $entity->get_edadmin_courses('entities')['id'],
                ],
        ]);

        self::assertInstanceOf('local_mentor_core\event\entity_create', $event);
        self::assertEquals($event::get_name(), get_string('evententitycreated', 'local_mentor_core'));
        self::assertEquals($event->get_url()->out(),
                $CFG->wwwroot . '/course/view.php?id=' . $entity->get_edadmin_courses('entities')['id']);
        self::assertEquals($event->get_description(),
                "The user with id '2' created the entity with course category id '$entityid'.");
        $objectidmapping = $event::get_objectid_mapping();
        self::assertEquals($objectidmapping['db'], 'course_categories');
        self::assertEquals($objectidmapping['restore'], core\event\base::NOT_MAPPED);

        $this->resetAllData();
    }

    /**
     * Test entity update event
     *
     * @covers \local_mentor_core\event\entity_update::init
     * @covers \local_mentor_core\event\entity_update::get_name
     * @covers \local_mentor_core\event\entity_update::get_url
     * @covers \local_mentor_core\event\entity_update::get_description
     * @covers \local_mentor_core\event\entity_update::get_legacy_eventname
     * @covers \local_mentor_core\event\entity_update::set_legacy_logdata
     * @covers \local_mentor_core\event\entity_update::get_legacy_logdata
     * @covers \local_mentor_core\event\entity_update::get_objectid_mapping
     */
    public function test_entity_update_event() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $entitydata = $this->get_entities_data();
        $entityid = \local_mentor_core\entity_api::create_entity($entitydata);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $newdata = new stdClass();
        $newdata->name = 'Name updated';

        $result = \local_mentor_core\entity_api::update_entity($entity->id, $newdata);

        // Entity update event.
        $event = \local_mentor_core\event\entity_update::create([
                'objectid' => $entityid,
                'context' => $entity->get_context(),
                'other' => [
                        'name' => $entity->get_name(),
                        'managementcourseid' => $entity->get_edadmin_courses('entities')['id'],
                ],
        ]);

        self::assertInstanceOf('local_mentor_core\event\entity_update', $event);
        self::assertEquals($event::get_name(), get_string('evententityupdated', 'local_mentor_core'));
        self::assertEquals($event->get_url()->out(),
                $CFG->wwwroot . '/course/view.php?id=' . $entity->get_edadmin_courses('entities')['id']);
        self::assertEquals($event::get_legacy_eventname(), 'entity_updated');
        self::assertEquals($event->get_description(), "The user with id '2' updated the entity with category id '$entityid'.");

        $reflectionmethod = new ReflectionMethod('local_mentor_core\event\entity_update', 'get_legacy_logdata');
        $reflectionmethod->setAccessible(true);
        $legacylogdata = $reflectionmethod->invoke($event);
        self::assertEquals(
                $legacylogdata,
                [
                        "1",
                        "entity",
                        "update",
                        "course/view.php?id=" . $entity->get_edadmin_courses('entities')['id'],
                        $entityid,
                ]
        );

        $objectidmapping = $event::get_objectid_mapping();
        self::assertEquals($objectidmapping['db'], 'course_categories');
        self::assertEquals($objectidmapping['restore'], core\event\base::NOT_MAPPED);

        $this->resetAllData();
    }

    /**
     * Test training create event
     *
     * @covers \local_mentor_core\event\training_create::init
     * @covers \local_mentor_core\event\training_create::get_name
     * @covers \local_mentor_core\event\training_create::get_url
     * @covers \local_mentor_core\event\training_create::get_description
     * @covers \local_mentor_core\event\training_create::get_legacy_logdata
     * @covers \local_mentor_core\event\training_create::get_objectid_mapping
     */
    public function test_training_create_event() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $trainingdata = $this->init_training_creation();
        $training = \local_mentor_core\training_api::create_training($trainingdata);

        // Training created event.
        $event = \local_mentor_core\event\training_create::create([
                'objectid' => $training->id,
                'context' => $training->get_context(),
        ]);

        self::assertInstanceOf('local_mentor_core\event\training_create', $event);
        self::assertEquals($event::get_name(), get_string('eventtrainingcreated', 'local_mentor_core'));
        self::assertEquals($event->get_url()->out(),
                $CFG->wwwroot . '/local/trainings/pages/update_training.php?trainingid=' . $training->id);
        self::assertEquals($event->get_description(), "The user with id '2' created the training with id '$training->id'.");

        $reflectionmethod = new ReflectionMethod('local_mentor_core\event\training_create', 'get_legacy_eventdata');
        $reflectionmethod->setAccessible(true);
        $legacyeventdata = $reflectionmethod->invoke($event);
        self::assertNull($legacyeventdata);

        $objectidmapping = $event::get_objectid_mapping();
        self::assertEquals($objectidmapping['db'], 'training');
        self::assertEquals($objectidmapping['restore'], 'training');

        $this->resetAllData();
    }

    /**
     * Test training update event
     *
     * @covers \local_mentor_core\event\training_update::init
     * @covers \local_mentor_core\event\training_update::get_name
     * @covers \local_mentor_core\event\training_update::get_url
     * @covers \local_mentor_core\event\training_update::get_description
     * @covers \local_mentor_core\event\training_update::get_legacy_eventname
     * @covers \local_mentor_core\event\training_update::get_legacy_eventdata
     * @covers \local_mentor_core\event\training_update::set_legacy_logdata
     * @covers \local_mentor_core\event\training_update::get_legacy_logdata
     * @covers \local_mentor_core\event\training_update::get_objectid_mapping
     */
    public function test_training_update_event() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $trainingdata = $this->init_training_creation();
        $training = \local_mentor_core\training_api::create_training($trainingdata);

        // Init test data.
        $trainingdata->id = $training->id;
        $trainingdata->traininggoal = 'TEST NEW TRAININGGOAL';
        $trainingdata->status = \local_mentor_core\training::STATUS_ARCHIVED;

        $training = \local_mentor_core\training_api::update_training($trainingdata);

        // Training updated event.
        $event = \local_mentor_core\event\training_update::create([
                'objectid' => $training->id,
                'context' => $training->get_context(),
        ]);
        $event->set_legacy_logdata(['test']);

        self::assertInstanceOf('local_mentor_core\event\training_update', $event);
        self::assertEquals($event::get_name(), get_string('eventtrainingupdated', 'local_mentor_core'));
        self::assertEquals($event->get_url()->out(),
                $CFG->wwwroot . '/local/trainings/pages/update_training.php?trainingid=' . $training->id);
        self::assertEquals($event::get_legacy_eventname(), 'training_updated');
        self::assertEquals($event->get_description(), "The user with id '2' updated the training with id '$training->id'.");

        $reflectionmethod = new ReflectionMethod('local_mentor_core\event\training_update', 'get_legacy_eventdata');
        $reflectionmethod->setAccessible(true);
        $legacyeventdata = $reflectionmethod->invoke($event);

        self::assertIsObject($legacyeventdata);
        self::assertEquals($legacyeventdata->id, $training->id);
        self::assertEquals($legacyeventdata->courseshortname, $training->courseshortname);
        self::assertEquals($legacyeventdata->status, $training->status);

        $objectidmapping = $event::get_objectid_mapping();
        self::assertEquals($objectidmapping['db'], 'training');
        self::assertEquals($objectidmapping['restore'], 'training');

        $this->resetAllData();
    }

    /**
     * Test session create event
     *
     * @covers \local_mentor_core\event\session_create::init
     * @covers \local_mentor_core\event\session_create::get_name
     * @covers \local_mentor_core\event\session_create::get_url
     * @covers \local_mentor_core\event\session_create::get_description
     * @covers \local_mentor_core\event\session_create::get_legacy_logdata
     * @covers \local_mentor_core\event\session_create::get_objectid_mapping
     */
    public function test_session_create_event() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $session = $this->init_session_creation();

        // Session created event.
        $event = \local_mentor_core\event\session_create::create([
                'objectid' => $session->id,
                'context' => $session->get_context(),
        ]);

        self::assertInstanceOf('local_mentor_core\event\session_create', $event);
        self::assertEquals($event::get_name(), get_string('eventsessioncreated', 'local_mentor_core'));
        self::assertEquals($event->get_url()->out(),
                $CFG->wwwroot . '/local/session/pages/update_session.php?sessionid=' . $session->id);
        self::assertEquals($event->get_description(), "The user with id '2' created the session with id '$session->id'.");
        $objectidmapping = $event::get_objectid_mapping();
        self::assertEquals($objectidmapping['db'], 'session');
        self::assertEquals($objectidmapping['restore'], core\event\base::NOT_MAPPED);

        $this->resetAllData();
    }

    /**
     * Session update event
     *
     * @covers \local_mentor_core\event\session_update::init
     * @covers \local_mentor_core\event\session_update::get_name
     * @covers \local_mentor_core\event\session_update::get_url
     * @covers \local_mentor_core\event\session_update::get_description
     * @covers \local_mentor_core\event\session_update::get_legacy_eventname
     * @covers \local_mentor_core\event\session_update::get_legacy_eventdata
     * @covers \local_mentor_core\event\session_update::set_legacy_logdata
     * @covers \local_mentor_core\event\session_update::get_legacy_logdata
     * @covers \local_mentor_core\event\session_update::get_objectid_mapping
     */
    public function test_session_update_event() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $session = $this->init_session_creation();
        $data = new stdClass();

        // New session data.
        $data->id = $session->id;
        $data->maxparticipants = 12;
        $session = \local_mentor_core\session_api::update_session($data);

        // Session updated event.
        $event = \local_mentor_core\event\session_update::create([
                'objectid' => $session->id,
                'context' => $session->get_context(),
        ]);
        $event->set_legacy_logdata(['test']);

        self::assertInstanceOf('local_mentor_core\event\session_update', $event);
        self::assertEquals($event::get_name(), get_string('eventsessionupdated', 'local_mentor_core'));
        self::assertEquals($event->get_url()->out(),
                $CFG->wwwroot . '/local/session/pages/update_session.php?sessionid=' . $session->id);
        self::assertEquals($event::get_legacy_eventname(), 'session_updated');
        self::assertEquals($event->get_description(), "The user with id '2' updated the session with id '$session->id'.");

        $reflectionmethod = new ReflectionMethod('local_mentor_core\event\session_update', 'get_legacy_eventdata');
        $reflectionmethod->setAccessible(true);
        $legacyeventdata = $reflectionmethod->invoke($event);

        self::assertIsObject($legacyeventdata);
        self::assertEquals($legacyeventdata->id, $session->id);
        self::assertEquals($legacyeventdata->trainingid, $session->get_training()->id);

        $objectidmapping = $event::get_objectid_mapping();
        self::assertEquals($objectidmapping['db'], 'session');
        self::assertEquals($objectidmapping['restore'], core\event\base::NOT_MAPPED);

        $this->resetAllData();
    }
}
