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
 * Test cases for front controller
 *
 * @package    local_mentor_core
 * @copyright  2022 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/classes/controllers/front_controller.php');

class local_mentor_core_front_controller_testcase extends advanced_testcase {

    /**
     * get value to object's protected attribute
     *
     * @param local_mentor_core\front_controller $obj
     * @param string $prop
     * @return mixed
     * @throws ReflectionException
     */
    public function access_protected($obj, $prop) {
        $reflection = new ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    /**
     * Test construct function ok
     *
     * @covers \local_mentor_core\front_controller::__construct
     * @covers \local_mentor_core\front_controller::set_controller
     * @covers \local_mentor_core\front_controller::set_action
     */
    public function test_front_controller_consctruct_ok() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $frontcontroller = new \local_mentor_core\front_controller(
                'mentor_core/tests',
                'local_mentor_core\\',
                [
                        'plugintype' => 'local',
                        'controller' => 'test',
                        'action' => 'test_action',
                ]
        );

        self::assertEquals('test_controller', $this->access_protected($frontcontroller, 'controller'));
        self::assertEquals('local_mentor_core\\', $this->access_protected($frontcontroller, 'namespace'));
        self::assertEquals('mentor_core/tests', $this->access_protected($frontcontroller, 'plugin'));
        self::assertEquals('local', $this->access_protected($frontcontroller, 'plugintype'));

        $conrollerparams = $this->access_protected($frontcontroller, 'params');

        self::assertEquals('local', $conrollerparams['plugintype']);
        self::assertEquals('test', $conrollerparams['controller']);
        self::assertEquals('test_action', $conrollerparams['action']);

        $this->resetAllData();
    }

    /**
     * Test set controller not ok
     * File not found
     *
     * @covers \local_mentor_core\front_controller::__construct
     * @covers \local_mentor_core\front_controller::set_controller
     */
    public function test_set_controller_nok_file_not_found() {
        global $CFG;

        $this->resetAfterTest(true);
        self::setAdminUser();

        try {
            new \local_mentor_core\front_controller(
                    'mentor_core/tests',
                    'local_mentor_core\\',
                    [
                            'plugintype' => 'local',
                            'controller' => 'test_false',
                            'action' => 'test_action',
                    ]
            );
            self::fail();
        } catch (\moodle_exception $e) {
            self::assertEquals(
                    $e->getMessage(),
                    'error/Controller file not found : ' . $CFG->dirroot .
                    "/local/mentor_core/tests/classes/controllers/test_false_controller.php\n\$a contents: "
            );
        }

        $this->resetAllData();
    }

    /**
     * Test set controller not ok
     * Callse not found
     *
     * @covers \local_mentor_core\front_controller::__construct
     * @covers \local_mentor_core\front_controller::set_controller
     */
    public function test_set_controller_nok_classe_not_found() {
        global $CFG;

        $this->resetAfterTest(true);
        self::setAdminUser();

        try {
            new \local_mentor_core\front_controller(
                    'mentor_core/tests',
                    'local_mentor_core\\',
                    [
                            'plugintype' => 'local',
                            'controller' => 'false_test',
                            'action' => 'test_action',
                    ]
            );
            self::fail();
        } catch (\InvalidArgumentException $e) {
            self::assertEquals(
                    $e->getMessage(),
                    "The controller 'false_test_controller' has not been defined."
            );
        }

        $this->resetAllData();
    }

    /**
     * Test set action not ok
     * Action not found
     *
     * @covers \local_mentor_core\front_controller::__construct
     * @covers \local_mentor_core\front_controller::set_action
     */
    public function test_set_action_nok_action_not_found() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        try {
            new \local_mentor_core\front_controller(
                    'mentor_core/tests',
                    'local_mentor_core\\',
                    [
                            'plugintype' => 'local',
                            'controller' => 'test',
                            'action' => 'false_action',
                    ]
            );
            self::fail();
        } catch (\InvalidArgumentException $e) {
            self::assertEquals(
                    $e->getMessage(),
                    "The controller action 'false_action' is undefined fot the controller 'local_mentor_core\\test_controller'."
            );
        }

        $this->resetAllData();
    }

    /**
     * Test execute ok
     *
     * @covers \local_mentor_core\front_controller::__construct
     * @covers \local_mentor_core\front_controller::execute
     */
    public function test_excecute_ok() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $frontcontroller = new \local_mentor_core\front_controller(
                'mentor_core/tests',
                'local_mentor_core\\',
                [
                        'plugintype' => 'local',
                        'controller' => 'test',
                        'action' => 'test_action',
                ]
        );

        $result = $frontcontroller->execute();

        self::assertTrue($result['success']);
        self::assertTrue($result['message']);

        $this->resetAllData();
    }
}
