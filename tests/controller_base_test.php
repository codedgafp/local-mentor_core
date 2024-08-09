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

require_once($CFG->dirroot . '/local/mentor_core/classes/controllers/controller_base.php');

class local_mentor_core_controller_base_testcase extends advanced_testcase {

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
     * @covers \local_mentor_core\controller_base::__construct
     */
    public function test_controller_base_consctruct_ok() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $controllermock = $this->getMockForAbstractClass(
                '\local_mentor_core\controller_base',
                [['key' => 'value']]
        );

        $controllerabseparams = $this->access_protected($controllermock, 'params');

        self::assertIsArray($controllerabseparams);
        self::assertEquals('value', $controllerabseparams['key']);

        $this->resetAllData();
    }

    /**
     * Test construct function ok
     * get value
     *
     * @covers \local_mentor_core\controller_base::get_param
     */
    public function test_get_param_ok_get_value() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $controllermock = $this->getMockForAbstractClass(
                '\local_mentor_core\controller_base',
                [['key' => 'value']]
        );

        self::assertEquals('value', $controllermock->get_param('key'));

        $this->resetAllData();
    }

    /**
     * Test construct function ok
     * get value with int type
     *
     * @covers \local_mentor_core\controller_base::get_param
     */
    public function test_get_param_ok_get_value_with_int_type() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $controllermock = $this->getMockForAbstractClass(
                '\local_mentor_core\controller_base',
                [['key' => 10]]
        );

        self::assertEquals(10, $controllermock->get_param('key', PARAM_INT));

        $this->resetAllData();
    }

    /**
     * Test construct function not ok
     * get value with int type
     *
     * @covers \local_mentor_core\controller_base::get_param
     */
    public function test_get_param_nok_get_value_with_int_type() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $controllermock = $this->getMockForAbstractClass(
                '\local_mentor_core\controller_base',
                [['key' => 'value']]
        );

        try {
            $controllermock->get_param('key', PARAM_INT);
            self::fail();
        } catch (\moodle_exception $e) {
            self::assertEquals(
                    $e->getMessage(),
                    "error/param : key must be an integer for the value : value\n\$a contents: "
            );
        }

        $this->resetAllData();
    }

    /**
     * Test construct function ok
     * get value with other type
     *
     * @covers \local_mentor_core\controller_base::get_param
     */
    public function test_get_param_ok_get_value_with_other_type() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $controllermock = $this->getMockForAbstractClass(
                '\local_mentor_core\controller_base',
                [['key' => 'value']]
        );

        self::assertEquals('value', $controllermock->get_param('key', PARAM_RAW));

        $this->resetAllData();
    }

    /**
     * Test construct function not ok
     * get default value
     *
     * @covers \local_mentor_core\controller_base::get_param
     */
    public function test_get_param_ok_get_default_value() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $controllermock = $this->getMockForAbstractClass(
                '\local_mentor_core\controller_base',
                [[]]
        );

        self::assertEquals(
                'defaultvalue',
                $controllermock->get_param('key', PARAM_INT, 'defaultvalue')
        );

        $this->resetAllData();
    }

    /**
     * Test success ok
     *
     * @covers \local_mentor_core\controller_base::success
     */
    public function test_success_ok() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $controllermock = $this->getMockForAbstractClass(
                '\local_mentor_core\controller_base',
                [[]]
        );

        $success = $controllermock->success('success message');

        self::assertIsArray($success);
        self::assertArrayHasKey('success', $success);
        self::assertTrue($success['success']);
        self::assertArrayHasKey('message', $success);
        self::assertEquals('success message', $success['message']);

        $this->resetAllData();
    }

    /**
     * Test error ok
     *
     * @covers \local_mentor_core\controller_base::error
     */
    public function test_error_ok() {
        $this->resetAfterTest(true);
        self::setAdminUser();

        $controllermock = $this->getMockForAbstractClass(
                '\local_mentor_core\controller_base',
                [[]]
        );

        $success = $controllermock->error('success message');

        self::assertIsArray($success);
        self::assertArrayHasKey('success', $success);
        self::assertFalse($success['success']);
        self::assertArrayHasKey('message', $success);
        self::assertEquals('success message', $success['message']);

        $this->resetAllData();
    }
}
