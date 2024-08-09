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
 * Library api tests
 *
 * @package    local_mentor_core
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/lib.php');
require_once($CFG->dirroot . '/local/library/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/api/library.php');

class local_mentor_core_library_testcase extends advanced_testcase {

    /**
     * Init $CFG
     */
    public function init_config() {
        global $CFG;

        $CFG->mentor_specializations = [
                '\\local_mentor_specialization\\mentor_specialization' =>
                        'local/mentor_specialization/classes/mentor_specialization.php',
        ];

        local_mentor_specialization_init_collections_settings();
        local_library_init_config();
    }

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
        $trainingdata->collection = 'management';
        $trainingdata->traininggoal = 'TEST TRAINING ';
        $trainingdata->idsirh = 'TEST ID SIRH';
        $trainingdata->licenseterms = 'cc-sa';
        $trainingdata->typicaljob = 'TEST';
        $trainingdata->skills = [1, 3];
        $trainingdata->certifying = '1';
        $trainingdata->presenceestimatedtime = '730';
        $trainingdata->remoteestimatedtime = '930';
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
     * Test test_get_library
     *
     * @covers  \local_mentor_core\library_api::get_library
     */
    public function test_get_library() {

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();
        $this->setOutputCallback(function() {
        });

        $library = \local_mentor_core\library_api::get_library();

        self::assertIsObject($library);
        self::assertInstanceOf('local_mentor_core\library', $library);

        self::resetAllData();
    }

    /**
     * Test create_library
     *
     * @covers  \local_mentor_core\library_api::create_library
     */
    public function test_create_library() {
        global $DB;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();
        $this->setOutputCallback(function() {
        });

        self::setAdminUser();

        // Library exist and add to config.
        self::assertIsString(get_config('core', \local_mentor_core\library::CONFIG_VALUE_ID));
        self::assertTrue(\local_mentor_core\library_api::create_library());

        // Create library and don't add to config.
        $category = core_course_category::get(get_config('core', \local_mentor_core\library::CONFIG_VALUE_ID));
        $category->delete_full(false);
        unset_config(\local_mentor_core\library::CONFIG_VALUE_ID);
        self::assertTrue(\local_mentor_core\library_api::create_library(false));
        self::assertFalse(get_config('core', \local_mentor_core\library::CONFIG_VALUE_ID));

        // Create library and add to config.
        $dbi = \local_mentor_core\database_interface::get_instance();
        $category = core_course_category::get($dbi->get_library_object()->id);
        $category->delete_full(false);
        self::assertTrue(\local_mentor_core\library_api::create_library());
        self::assertIsString(get_config('core', \local_mentor_core\library::CONFIG_VALUE_ID));

        self::resetAllData();
    }

    /**
     * Test test_get_or_create_library
     *
     * @covers  \local_mentor_core\library_api::get_or_create_library
     */
    public function test_get_or_create_library() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();
        $this->setOutputCallback(function() {
        });

        self::setAdminUser();

        // Remove library entity.
        $dbi = \local_mentor_core\database_interface::get_instance();
        $category = core_course_category::get($dbi->get_library_object()->id);
        $category->delete_full(false);
        unset_config(\local_mentor_core\library::CONFIG_VALUE_ID);

        // Create library.
        $library = \local_mentor_core\library_api::get_or_create_library();
        self::assertIsObject($library);
        self::assertInstanceOf('local_mentor_core\library', $library);

        // Get library.
        $library2 = \local_mentor_core\library_api::get_or_create_library();
        self::assertIsObject($library2);
        self::assertInstanceOf('local_mentor_core\library', $library2);
        self::assertEquals($library->id, $library2->id);

        self::resetAllData();
    }

    /**
     * Test get_library_id
     *
     * @covers  \local_mentor_core\library_api::get_library_id
     */
    public function test_get_library_id() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();
        $this->setOutputCallback(function() {
        });

        self::setAdminUser();

        $libraryid = \local_mentor_core\library_api::get_library_id();
        $cat = core_course_category::get($libraryid);

        self::assertEquals($cat->name, \local_mentor_core\library::NAME);
        self::assertEquals($cat->idnumber, \local_mentor_core\library::SHORTNAME);

        self::resetAllData();
    }

    /**
     * Test set_library_id_to_config
     *
     * @covers  \local_mentor_core\library_api::set_library_id_to_config
     */
    public function test_set_library_id_to_config() {
        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();
        $this->setOutputCallback(function() {
        });

        self::setAdminUser();

        $oldlibraryid = \local_mentor_core\library_api::get_library_id();
        $cat = core_course_category::get($oldlibraryid);

        self::assertEquals($cat->name, \local_mentor_core\library::NAME);
        self::assertEquals($cat->idnumber, \local_mentor_core\library::SHORTNAME);

        $newlibraryid = 10;

        \local_mentor_core\library_api::set_library_id_to_config($newlibraryid);
        self::assertNotEquals($oldlibraryid, \local_mentor_core\library_api::get_library_id());
        self::assertEquals($newlibraryid, \local_mentor_core\library_api::get_library_id());

        self::resetAllData();
    }

    /**
     * Test publish_to_library
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\library_api::publish_to_library
     */
    public function test_publish_to_library() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $entityid = $this->init_create_entity();
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);

        $dbi = \local_mentor_core\database_interface::get_instance();
        self::assertFalse($dbi->get_library_publication($training->id));
        self::assertFalse($DB->get_record('task_adhoc', ['classname' => '\local_library\task\publication_library_task']));

        \local_mentor_core\library_api::publish_to_library($training->id);

        self::assertFalse($dbi->get_library_publication($training->id));
        $publicationlibrarytask = $DB->get_record(
                'task_adhoc',
                ['classname' => '\local_library\task\publication_library_task']
        );
        $customdata = json_decode($publicationlibrarytask->customdata);
        self::assertEquals($customdata->trainingid, $training->id);
        self::assertEquals($customdata->userid, $USER->id);

        $DB->delete_records('task_adhoc');

        \local_mentor_core\library_api::publish_to_library($training->id, true);

        self::assertFalse($DB->get_record('task_adhoc', ['classname' => '\local_library\task\publication_library_task']));
        $traininglibrary = $dbi->get_library_publication($training->id);
        self::assertEquals($traininglibrary->originaltrainingid, $training->id);
        self::assertEquals($traininglibrary->timecreated, $traininglibrary->timemodified);
        self::assertEquals($traininglibrary->userid, $USER->id);

        self::resetAllData();
    }

    /**
     * Test get_library_publication
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\library_api::get_library_publication
     */
    public function test_get_library_publication() {
        global $DB;

        $this->resetAfterTest(true);

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

        $traininglibrary = \local_mentor_core\library_api::get_library_publication($falseoriginaltrainingid);
        self::assertEquals($traininglibrary->trainingid, $falsetrainingid);
        self::assertEquals($traininglibrary->originaltrainingid, $falseoriginaltrainingid);
        self::assertEquals($traininglibrary->userid, $falseuserid);

        self::resetAllData();
    }

    /**
     * Test get_original_trainging
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\library_api::get_original_trainging
     */
    public function test_get_original_trainging() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $entityid = $this->init_create_entity();
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);

        self::assertFalse(local_mentor_core\library_api::get_original_trainging($training->id));

        $traininglibrary = \local_mentor_core\library_api::publish_to_library($training->id, true);

        $originaltraining = local_mentor_core\library_api::get_original_trainging($traininglibrary->id);
        self::assertEquals($originaltraining->id, $training->id);

        self::resetAllData();
    }

    /**
     * Test import_to_entity
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\library_api::import_to_entity
     */
    public function test_import_to_entity() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $entityid = $this->init_create_entity();
        $entityid2 = $this->init_create_entity('Entity2');
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);
        self::getDataGenerator()->create_course(['shortname' => 'course1']);
        $user = self::getDataGenerator()->create_user();

        $traininglibrary = \local_mentor_core\library_api::publish_to_library($training->id, true);

        // Training shortname exist.
        self::assertEquals(TRAINING_NAME_USED,
                \local_mentor_core\library_api::import_to_entity($traininglibrary->id, 'trainingshortname', $entityid2));

        // Course shortname exist.
        self::assertEquals(TRAINING_NAME_USED,
                \local_mentor_core\library_api::import_to_entity($traininglibrary->id, 'course1', $entityid2));

        self::setUser($user);

        try {
            // Not permission.
            self::assertEquals(TRAINING_NAME_USED,
                    \local_mentor_core\library_api::import_to_entity($traininglibrary->id, 'trainingshortname2', $entityid2));
            self::fail();
        } catch (\required_capability_exception $e) {
            self::assertEquals($e->getMessage(),
                    'Sorry, but you do not currently have permissions to do that (Créer une formation).');
        }

        self::setAdminUser();

        // Purge task adhoc.
        $DB->delete_records('task_adhoc');

        \local_mentor_core\library_api::import_to_entity($traininglibrary->id, 'trainingshortname2', $entityid2);

        $taskadhoc = $DB->get_records('task_adhoc');

        // Task adhoc created.
        self::assertCount(1, $taskadhoc);
        $current = current($taskadhoc);
        self::assertEquals($current->classname, '\local_library\task\import_to_entity_task');
        $customdata = json_decode($current->customdata);
        self::assertEquals($customdata->trainingid, $traininglibrary->id);
        self::assertEquals($customdata->trainingshortname, 'trainingshortname2');
        self::assertEquals($customdata->destinationentity, $entityid2);

        // Purge task adhoc.
        $DB->delete_records('task_adhoc');

        $newtraining = \local_mentor_core\library_api::import_to_entity($traininglibrary->id, 'trainingshortname2', $entityid2,
                true);

        // New training.
        self::assertEquals($entityid2, $newtraining->get_entity()->id);
        self::assertEquals('trainingshortname2', $newtraining->shortname);

        self::resetAllData();
    }

    /**
     * Test get_training_renderer
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\library_api::get_training_renderer
     */
    public function test_get_training_renderer() {
        global $DB;

        $this->resetAfterTest(true);
        $this->init_config();
        $this->reset_singletons();
        $this->setOutputCallback(function() {
        });

        self::setAdminUser();

        $entityid = $this->init_create_entity();
        $entity = \local_mentor_core\entity_api::get_entity($entityid);
        $training = $this->init_create_training('trainingname', 'trainingshortname', $entityid);

        $traininglibrary = \local_mentor_core\library_api::publish_to_library($training->id, true);

        $trainingrenderer = \local_mentor_core\library_api::get_training_renderer($traininglibrary);

        self::assertEquals($trainingrenderer->entityfullname, $entity->name);
        self::assertCount(1, $trainingrenderer->collectiontiles);
        self::assertEquals($trainingrenderer->collectiontiles[0]->name, 'Management');
        self::assertEquals($trainingrenderer->collectiontiles[0]->color, 'rgba(11, 107, 168, 0.3)');
        self::assertTrue($trainingrenderer->hasproducerorganization);
        self::assertEquals($trainingrenderer->presenceestimatedtime, '12h10');
        self::assertEquals($trainingrenderer->remoteestimatedtime, '15h30');
        self::assertEquals($trainingrenderer->modality, 'En ligne');
        self::assertEquals($trainingrenderer->timecreated, date('d/m/y', time()));
        self::assertFalse(isset($trainingrenderer->timemodified));

        $traininglibrary->presenceestimatedtime = null;
        $traininglibrary->remoteestimatedtime = null;
        $librarypublication
                = \local_mentor_core\library_api::get_library_publication($traininglibrary->id,
                'trainingid');
        $librarypublication->timemodified = time() + 1000;
        $DB->update_record('library', $librarypublication);

        $trainingrenderer = \local_mentor_core\library_api::get_training_renderer($traininglibrary);

        self::assertEquals($trainingrenderer->entityfullname, $entity->name);
        self::assertCount(1, $trainingrenderer->collectiontiles);
        self::assertEquals($trainingrenderer->collectiontiles[0]->name, 'Management');
        self::assertEquals($trainingrenderer->collectiontiles[0]->color, 'rgba(11, 107, 168, 0.3)');
        self::assertTrue($trainingrenderer->hasproducerorganization);
        self::assertFalse($trainingrenderer->presenceestimatedtime);
        self::assertFalse($trainingrenderer->remoteestimatedtime);
        self::assertEquals($trainingrenderer->modality, 'En ligne');
        self::assertEquals($trainingrenderer->timecreated, date('d/m/y', time()));
        self::assertTrue(isset($trainingrenderer->timemodified));
        self::assertEquals($trainingrenderer->timemodified, date('d/m/y', $librarypublication->timemodified));

        self::resetAllData();
    }

    /**
     * Test get_params_renderer
     *
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     *
     * @covers  \local_mentor_core\library_api::get_params_renderer
     */
    public function test_get_params_renderer() {
        global $DB, $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();
        $this->setOutputCallback(function() {
        });

        self::setAdminUser();

        $entityid = $this->init_create_entity();
        $entity = \local_mentor_core\entity_api::get_entity($entityid);
        $training = $this->init_create_training('name', 'shortname', $entityid);

        $traininglibrary = \local_mentor_core\library_api::publish_to_library($training->id, true);

        $renderer = \local_mentor_core\library_api::get_params_renderer();

        self::assertObjectHasAttribute('collections', $renderer);
        self::assertCount(1, $renderer->collections);
        self::assertEquals($renderer->collections['0'], 'Management');

        self::assertObjectHasAttribute('entities', $renderer);
        self::assertCount(1, $renderer->entities);
        self::assertEquals($renderer->entities[0]['id'], $entityid);
        self::assertEquals($renderer->entities[0]['name'], $entity->name);

        self::assertObjectHasAttribute('trainings', $renderer);
        self::assertCount(1, $renderer->trainings);
        self::assertEquals($renderer->trainings[0]->id, $traininglibrary->id);
        self::assertEquals(
                $renderer->trainings[0]->trainingsheeturl,
                $CFG->wwwroot . '/local/library/pages/training.php?trainingid=' . $traininglibrary->id
        );
        self::assertEquals($renderer->trainings[0]->name, $traininglibrary->name);
        self::assertEquals($renderer->trainings[0]->thumbnail, $traininglibrary->get_file_url());
        self::assertEquals($renderer->trainings[0]->entityid, $training->get_entity()->id);
        self::assertEquals($renderer->trainings[0]->entityname, $training->get_entity()->shortname);
        self::assertEquals($renderer->trainings[0]->entityfullname, $training->get_entity()->name);
        self::assertEquals($renderer->trainings[0]->producingorganization, $traininglibrary->producingorganization);
        self::assertEquals($renderer->trainings[0]->producerorganizationshortname, $traininglibrary->producerorganizationshortname);
        self::assertEquals($renderer->trainings[0]->catchphrase, $traininglibrary->catchphrase);
        self::assertEquals($renderer->trainings[0]->collection, $traininglibrary->collection);
        self::assertEquals($renderer->trainings[0]->collectionstr, $traininglibrary->collectionstr);
        self::assertEquals($renderer->trainings[0]->typicaljob, $traininglibrary->typicaljob);
        self::assertEquals($renderer->trainings[0]->skills, $traininglibrary->get_skills_name());
        self::assertEquals($renderer->trainings[0]->content, html_entity_decode($traininglibrary->content, ENT_COMPAT));
        self::assertEquals($renderer->trainings[0]->idsirh, $traininglibrary->idsirh);
        self::assertEquals($renderer->trainings[0]->time, local_mentor_core_minutes_to_hours
        ($traininglibrary->presenceestimatedtime + $traininglibrary->remoteestimatedtime));
        self::assertEquals($renderer->trainings[0]->modality, 'En ligne');
        self::assertCount(1, $renderer->trainings[0]->collectiontiles);
        self::assertEquals($renderer->trainings[0]->collectiontiles[0]->name, 'Management');
        self::assertEquals($renderer->trainings[0]->collectiontiles[0]->color, 'rgba(11, 107, 168, 0.3)');

        self::assertObjectHasAttribute('trainingscount', $renderer);
        self::assertEquals(1, $renderer->trainingscount);

        self::assertObjectHasAttribute('available_trainings', $renderer);

        self::assertObjectHasAttribute('trainings_dictionnary', $renderer);
        $dictonnary = json_decode($renderer->trainings_dictionnary);
        self::assertObjectHasAttribute($traininglibrary->id, $dictonnary);

        self::assertObjectHasAttribute('isdev', $renderer);
        self::assertEquals(0, $renderer->isdev);

        self::resetAllData();
    }
}
