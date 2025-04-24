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

// use local_mentor_core\session;

use PHPUnit\Framework\TestCase;

defined('MOODLE_INTERNAL') || die();

// global $CFG;

// require_once($CFG->dirroot . '/local/mentor_core/lib.php');
// require_once($CFG->dirroot . '/local/mentor_core/classes/database_interface.php');
// require_once($CFG->dirroot . '/local/mentor_core/api/training.php');

class local_mentor_core_entity_helper_testcase extends TestCase {

    ### is_value_existing_in_string tests ###

    /**
     * Test if the exact value exists in the expected string.
     */
    public function test_value_exists_exactly()
    {
        $value = 'apple';
        $expectedvalues = 'apple, banana, orange';
        $existingvalues = ['applepie', 'pineapple', 'apple'];

        $result = is_value_existing_in_string($value, $expectedvalues, $existingvalues);

        $this->assertTrue($result);
    }

    /**
     * Test if the value does not exist in the expected string.
     */
    public function test_value_does_not_exist()
    {
        $value = 'apple';
        $expectedvalues = 'banana, orange';
        $existingvalues = ['applepie', 'pineapple', 'apple'];

        $result = is_value_existing_in_string($value, $expectedvalues, $existingvalues);

        $this->assertFalse($result);
    }

    /**
     * Test if a similar value exists but not the actual value.
     */
    public function test_similar_value_exists_but_not_actual_value()
    {
        $value = 'apple';
        $expectedvalues = 'applepie, banana';
        $existingvalues = ['applepie', 'pineapple'];

        $result = is_value_existing_in_string($value, $expectedvalues, $existingvalues);

        $this->assertFalse($result);
    }

    /**
     * Test if a value exists along with similar values.
     */
    public function test_value_do_not_exists_with_similar_values()
    {
        $value = 'pine';
        $expectedvalues = 'pineapple, banana';
        $existingvalues = ['apple', 'pine', 'pineapple'];

        $result = is_value_existing_in_string($value, $expectedvalues, $existingvalues);

        $this->assertFalse($result, 'The value "pine" should exist even with similar values.');
    }

    /**
     * Test with empty value and empty expected string.
     */
    public function test_empty_value_and_empty_expected_string()
    {
        $value = '';
        $expectedvalues = '';
        $existingvalues = ['apple', 'orange'];

        $result = is_value_existing_in_string($value, $expectedvalues, $existingvalues);

        $this->assertFalse($result);
    }

    /**
     * Test an exact match with no similarity issues.
     */
    public function test_exact_match_no_similarity_issues()
    {
        $value = 'banana';
        $expectedvalues = 'apple, banana, orange';
        $existingvalues = ['banana', 'bananabread'];

        $result = is_value_existing_in_string($value, $expectedvalues, $existingvalues);

        $this->assertTrue($result);
    }

    ### array_filter_values_existing_in_string tests ###

    /**
     * Test filtering exact matches from expected values.
     */
    public function test_filter_exact_matches()
    {
        $existingvalues = ['apple', 'banana', 'orange'];
        $expectedvalues = 'apple, banana';

        $result = array_filter_values_existing_in_string($existingvalues, $expectedvalues);

        $this->assertEquals(['apple', 'banana'], $result);
    }

    /**
     * Test filtering when no values match.
     */
    public function test_no_values_match()
    {
        $existingvalues = ['apple', 'banana', 'orange'];
        $expectedvalues = 'grape, watermelon';

        $result = array_filter_values_existing_in_string($existingvalues, $expectedvalues);

        $this->assertEmpty($result);
    }

    /**
     * Test filtering when some values are similar but not exact.
     */
    public function test_similar_values_do_not_match()
    {
        $existingvalues = ['applepie', 'pineapple', 'banana'];
        $expectedvalues = 'apple, banana';

        $result = array_filter_values_existing_in_string($existingvalues, $expectedvalues);
        
        $this->assertEqualsCanonicalizing(['banana'], $result);
    }

    /**
     * Test filtering when expected values contain similar names but no exact matches.
     */
    public function test_no_exact_match_with_similar_values_in_expected()
    {
        $existingvalues = ['apple', 'pineapple', 'banana'];
        $expectedvalues = 'pineapple, orange';

        $result = array_filter_values_existing_in_string($existingvalues, $expectedvalues);

        $this->assertEqualsCanonicalizing(['pineapple'], $result);
    }


    /**
     * Test filtering with an empty expected values string.
     */
    public function test_empty_expected_values()
    {
        $existingvalues = ['apple', 'banana', 'orange'];
        $expectedvalues = '';

        $result = array_filter_values_existing_in_string($existingvalues, $expectedvalues);

        $this->assertEmpty($result);
    }

    /**
     * Test filtering with an empty existing values array.
     */
    public function test_empty_existing_values()
    {
        $existingvalues = [];
        $expectedvalues = 'apple, banana, orange';

        $result = array_filter_values_existing_in_string($existingvalues, $expectedvalues);

        $this->assertEmpty($result);
    }
}
