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
 * Test cases for Mentor Core lib
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
use \local_categories_domains\repository\categories_domains_repository;
use \local_categories_domains\model\domain_name;
use local_mentor_specialization\mentor_entity;

require_once($CFG->dirroot . '/local/mentor_core/lib.php');
require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');
require_once($CFG->dirroot . '/mod/assign/tests/fixtures/testable_assign.php');

class local_mentor_core_lib_testcase extends advanced_testcase {

    /**
     * Init $CFG
     */
    public function init_config() {
        global $CFG;

        $CFG->mentor_specializations = [
            '\\local_mentor_specialization\\mentor_specialization' =>
                'local/mentor_specialization/classes/mentor_specialization.php',
        ];
    }

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

        // Reset the mentor core specialization singleton.
        $specialization = \local_mentor_core\specialization::get_instance();
        $reflection = new ReflectionClass($specialization);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true); // Now we can modify that :).
        $instance->setValue(null, null); // Instance is gone.
        $instance->setAccessible(false); // Clean up.

        \local_mentor_core\training_api::clear_cache();
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
     * Submit an assignment.
     * Pinched from mod/assign/tests/generator.php and modified.
     *
     * @param object $student
     * @param assign $assign
     *
     * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
     */
    private function submit_for_student($student, $assign) {
        $this->setUser($student);

        $sink = $this->redirectMessages();

        $assign->save_submission((object) [
            'userid' => $student->id,
            'onlinetext_editor' => [
                'itemid' => file_get_unused_draft_itemid(),
                'text' => 'Text',
                'format' => FORMAT_HTML,
            ],
        ], $notices);

        $assign->submit_for_grading((object) [
            'userid' => $student->id,
        ], []);

        $sink->close();
    }

    /**
     * Award a grade to a submission.
     * Pinched from mod/assign/tests/generator.php and modified.
     *
     * @param object $student
     * @param assign $assign
     * @param object $teacher
     * @param integer $grade
     * @param integer $attempt
     *
     * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
     */
    private function grade_student($student, $assign, $teacher, $grade, $attempt) {
        global $DB;

        $this->setUser($teacher);

        // Bump all timecreated and timemodified for this user back.
        try {
            $DB->execute('UPDATE {assign_submission} ' .
                         'SET timecreated = timecreated - 1, timemodified = timemodified - 1 ' .
                         'WHERE userid = :userid',
                ['userid' => $student->id]);
        } catch (\dml_exception $e) {
            self::fail($e->getMessage());
        }

        $assign->testable_apply_grade_to_user((object) ['grade' => $grade],
            $student->id, $attempt);
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
     *  Test local_mentor_core set moodle config function
     *
     * @covers ::local_mentor_core_set_moodle_config
     * @covers ::local_mentor_core_unset_moodle_config
     */
    public function test_set_moodle_config_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $plugin = 'local_mentor_core';
        $configname = 'test';
        $configvalue = 'test_function';

        // Config not exist.
        self::assertFalse(get_config($plugin, $configname));

        // Create config (Create printed OutPut).
        self::expectOutputString(
            "Set config " . $configname . " to " . $configvalue . "\n" .
            "Unset config " . $configname . " to plugin " . $plugin . "\n"
        );
        local_mentor_core_set_moodle_config($configname, $configvalue, $plugin);

        // Config exist.
        self::assertEquals(get_config($plugin, $configname), $configvalue);

        // Unset config.
        local_mentor_core_unset_moodle_config($configname, $plugin);

        // Config not exist.
        self::assertFalse(get_config($plugin, $configname));

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core remove capability function
     *
     * @covers ::local_mentor_core_remove_capability
     */
    public function test_remove_capability_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $rolename = 'admindedie';
        $capability = 'moodle/user:create';

        // Get role.
        $db = \local_mentor_core\database_interface::get_instance();
        $role = $db->get_role_by_name($rolename);

        // Has capability.
        $hascapability = $DB->record_exists('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capability,
        ]);
        self::assertTrue($hascapability);

        // Remove capability.
        self::expectOutputString("Remove capability " . $capability . " from role " . $role->name . "\n");
        local_mentor_core_remove_capability($role, $capability);

        // Capability not exist.
        local_mentor_core_remove_capability($role, $capability . "notexist");

        // Has not capability.
        $hascapability = $DB->record_exists('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capability,
        ]);
        self::assertFalse($hascapability);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core remove capabilities function
     *
     * @covers ::local_mentor_core_remove_capabilities
     */
    public function test_remove_capabilities_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $rolename = 'admindedie';
        $capabilities = [
            'moodle/user:create',
            'moodle/user:delete',
        ];

        // Get role.
        $db = \local_mentor_core\database_interface::get_instance();
        $role = $db->get_role_by_name($rolename);

        // Has capabilities.
        $hascapability = $DB->record_exists('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capabilities[0],
        ]);
        self::assertTrue($hascapability);
        $hascapability = $DB->record_exists('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capabilities[1],
        ]);
        self::assertTrue($hascapability);

        // Remove capabilities.
        self::expectOutputString(
            "Remove capability " . $capabilities[0] . " from role " . $role->name . "\n" .
            "Remove capability " . $capabilities[1] . " from role " . $role->name . "\n"
        );
        local_mentor_core_remove_capabilities($role, $capabilities);

        // Has not capabilities.
        $hascapability = $DB->record_exists('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capabilities[0],
        ]);
        self::assertFalse($hascapability);
        $hascapability = $DB->record_exists('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capabilities[1],
        ]);
        self::assertFalse($hascapability);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core add capability function
     *
     * @covers ::local_mentor_core_add_capability
     */
    public function test_add_capability_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $rolename = 'admindedie';
        $capability = 'moodle/course:configurecustomfields';

        // Get role.
        $db = \local_mentor_core\database_interface::get_instance();
        $role = $db->get_role_by_name($rolename);

        self::expectOutputString("Add capability " . $capability . " to role " . $role->name . "\n" .
                                 "Add capability " . $capability . " to role " . $role->name . "\n");

        // Has capability.
        $hascapability = $DB->record_exists('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capability,
        ]);
        self::assertFalse($hascapability);

        // Remove capability.
        local_mentor_core_add_capability($role, $capability);

        // Has not capability.
        $capabilities = $DB->get_records('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capability,
        ]);

        self::assertCount(1, $capabilities);
        $keycapability = array_key_first($capabilities);

        sleep(1);

        local_mentor_core_add_capability($role, $capability);
        $updatedcapabilities = $DB->get_record('role_capabilities', [
                'roleid' => $role->id,
                'capability' => $capability,
            ]
        );

        self::assertTrue($updatedcapabilities->timemodified > $capabilities[$keycapability]->timemodified);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core add capabilities
     *
     * @covers ::local_mentor_core_add_capabilities
     */
    public function test_add_capabilities_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $rolename = 'admindedie';
        $capabilities = [
            'moodle/course:configurecustomfields',
            'moodle/my:manageblocks',
        ];

        // Get role.
        $db = \local_mentor_core\database_interface::get_instance();
        $role = $db->get_role_by_name($rolename);

        self::expectOutputString("Add capability " . $capabilities[0] . " to role " . $role->name . "\n" .
                                 "Add capability " . $capabilities[1] . " to role " . $role->name . "\n");

        // Has no capability.
        foreach ($capabilities as $capability) {
            self::assertFalse($DB->record_exists('role_capabilities', [
                'roleid' => $role->id,
                'capability' => $capability,
            ]));
        }

        // Remove capability.
        local_mentor_core_add_capabilities($role, $capabilities);

        // Has capability.
        foreach ($capabilities as $capability) {
            self::assertTrue($DB->record_exists('role_capabilities', [
                'roleid' => $role->id,
                'capability' => $capability,
            ]));
        }

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core prevent capability
     *
     * @covers ::local_mentor_core_prevent_capability
     */
    public function test_prevent_capability_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $rolename = 'admindedie';
        $capabilitynotexist = 'moodle/course:configurecustomfields';
        $capabilityexist = 'moodle/course:create';

        // Get role.
        $db = \local_mentor_core\database_interface::get_instance();
        $role = $db->get_role_by_name($rolename);

        self::expectOutputString("Prevent capability " . $capabilitynotexist . " to role " . $role->name . "\n" .
                                 "Prevent capability " . $capabilityexist . " to role " . $role->name . "\n");

        // Has no capability.
        self::assertFalse($DB->record_exists('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capabilitynotexist,
        ]));

        // Has capability.
        self::assertTrue($DB->record_exists('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capabilityexist,
        ]));

        // Prevent not existing capability.
        local_mentor_core_prevent_capability($role, $capabilitynotexist);

        $capability = $DB->get_record('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capabilitynotexist,
        ]);
        self::assertIsObject($capability);
        self::assertEquals($capability->permission, -1);

        // Prevent existing capability.
        local_mentor_core_prevent_capability($role, $capabilityexist);

        $capability = $DB->get_record('role_capabilities', [
            'roleid' => $role->id,
            'capability' => $capabilityexist,
        ]);
        self::assertIsObject($capability);
        self::assertEquals($capability->permission, -1);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core validate users csv function
     *
     * @covers ::local_mentor_core_validate_users_csv
     */
    public function test_validate_users_csv_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $coursedata = new stdClass();
        $coursedata->fullname = "New course";
        $coursedata->shortname = "New course";
        $coursedata->category = 1;
        $coursedata->idnumber = 1;
        $course = create_course($coursedata);

        $userlist = [
            'lastname, firstname, email, group',
            '', // Empty line must be ignored.
            'lastname1, firstname1, lastname1.firstname1@gmail.com, gr1',
        ];

        local_mentor_core_validate_users_csv($userlist, 'comma', $course->id, $preview, $errors, $warnings);

        // Check preview data.
        self::assertCount(1, $preview);
        self::assertEquals($preview['list'][0]['linenumber'], 3);
        self::assertEquals($preview['list'][0]['lastname'], 'lastname1');
        self::assertEquals($preview['list'][0]['firstname'], 'firstname1');
        self::assertEquals($preview['list'][0]['email'], 'lastname1.firstname1@gmail.com');
        self::assertEquals($preview['list'][0]['groupname'], 'gr1');

        // Check warnings data.
        self::assertCount(2, $warnings);
        self::assertEquals($warnings['groupsnotfound']['gr1'], 'gr1');
        self::assertEquals($warnings['list'][0][0], 3);
        self::assertEquals($warnings['list'][0][1], "Attention, le groupe gr1 n'a pas été trouvé. Le groupe sera créé.");

        // Empty file.
        $userlist = [
            'lastname, firstname, email, group',
        ];
        $errors = local_mentor_core_validate_users_csv($userlist, ',', $course->id);
        self::assertEquals(1, $errors);

        $userlist = [
            'lastname, firstname, email, group, role',
            'lastname1, firstname1, lastname1.firstname1@gmail.com, gr1, Tuteur',
        ];

        $errors = local_mentor_core_validate_users_csv($userlist, ',', $course->id);
        self::assertEmpty($errors);

        $userlist = [
            'lastname, firstname, email, role',
            'lastname1, firstname1, lastname1.firstname1@gmail.com, FakeRole',
        ];

        $errors = local_mentor_core_validate_users_csv($userlist, ',', $course->id, $prev, $err);
        self::assertCount(1, $err['rolenotfound']);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core validate users csv function with more than 500 entries
     *
     * @covers ::local_mentor_core_validate_users_csv
     */
    public function test_validate_users_csv_with_more_entries_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        $userlist = ['lastname, firstname, email'];

        // Create 5005 entries.
        for ($i = 0; $i < 5005; $i++) {
            $userlist[] = 'lastname1, firstname1, lastname1.firstname1@gmail.com';
        }

        local_mentor_core_validate_users_csv($userlist, 'comma');

        $notification = \core\notification::fetch();

        self::assertCount(1, $notification);
        self::assertEquals($notification[0]->get_message_type(), 'error');
        self::assertEquals($notification[0]->get_message(), get_string('error_too_many_lines', 'local_mentor_core'));

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core validate users csv function with empty line
     *
     * @covers ::local_mentor_core_validate_users_csv
     */
    public function test_validate_users_csv_with_empty_line_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $userlist = [
            'lastname, firstname, email',
            null,
        ];

        $returnvalue = local_mentor_core_validate_users_csv($userlist, 'comma', null, $preview, $errors);

        self::assertTrue($returnvalue);

        self::assertCount(1, $preview);
        self::assertCount(0, $preview['list']);

        self::assertNull($errors);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core validate users csv function with invalid headers
     *
     * @covers ::local_mentor_core_validate_users_csv
     */
    public function test_validate_users_csv_with_invalid_headers_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        $userlist = [
            'lastname, test, email',
            'lastname1, firstname1, lastname1.firstname1@gmail.com',
        ];

        $returnvalue = local_mentor_core_validate_users_csv($userlist, 'comma', null, $preview, $errors);

        self::assertTrue($returnvalue);

        $notification = \core\notification::fetch();

        self::assertCount(1, $notification);
        self::assertEquals($notification[0]->get_message_type(), 'error');
        self::assertEquals($notification[0]->get_message(), get_string('invalid_headers', 'local_user'));

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core validate users csv function with missing headers
     *
     * @covers ::local_mentor_core_validate_users_csv
     */
    public function test_validate_users_csv_with_missing_headers_nok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        $userlist = [
            'lastname, email',
            'lastname1, firstname1, lastname1.firstname1@gmail.com',
        ];

        $returnvalue = local_mentor_core_validate_users_csv($userlist, 'comma', null, $preview, $errors);

        self::assertTrue($returnvalue);

        $notification = \core\notification::fetch();

        self::assertCount(1, $notification);
        self::assertEquals($notification[0]->get_message_type(), 'error');
        self::assertEquals($notification[0]->get_message(), get_string('missing_headers', 'local_user'));

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core validate users csv function with formateur role switching to participant role
     *
     * @covers ::local_mentor_core_validate_users_csv
     */
    public function test_validate_users_csv_switching_formateur_role() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Guest and admin users.
        self::assertCount(2, $DB->get_records("user"));

        $userlist = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => $USER->email,
                'auth' => 'manual',
                'groupname' => '',
                'role' => 'formateur',
                'linenumber' => 2
            ],
        ];

        // Create session.
        $session = $this->init_session_creation();
        $session->sessionstartdate = time();
        $session->update($session);
        $session->create_manual_enrolment_instance();

        local_mentor_core_enrol_users_csv($session->get_course(true)->id, $userlist);

        // Switch role to participant.
        $userlist = [
            'lastname, firstname, email, role',
            'lastname1, firstname1, ' . $USER->email . ', Participant,',
        ];

        // Preview array.
        $preview = [
            'list' => [], // Cleaned list of accounts.
            'validlines' => 0, // Number of lines without error.
            'validforcreation' => 0, // Number of lines that will create an account.
        ];

        local_mentor_core_validate_users_csv($userlist, 'comma', $session->get_course()->id, $preview, $errors, $warnings);

        self::assertCount(1, $errors['list']);
        self::assertEquals(2, $errors['list'][0][0]);

        self::resetAllData();
    }
    
    /**
     *  Test local_mentor_core validate users csv function ok
     *  Reactivation
     *
     * @covers ::local_mentor_core_validate_users_csv
     */
    public function test_validate_users_csv_ok_reactivation() {
        global $CFG;

        require_once($CFG->dirroot . '/local/mentor_core/forms/importcsv_form.php');

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->init_config();

        self::setAdminUser();

        $user = self::getDataGenerator()->create_user();
        $user->suspended = 1;
        // Update profile.
        user_update_user($user, false, false);

        $userlist = [
            'lastname, firstname, email',
            $user->lastname . ',' . $user->firstname . ',' . $user->email,
        ];

        // Preview array.
        $preview = [
            'list' => [], // Cleaned list of accounts.
            'validlines' => 0, // Number of lines without error.
            'validforcreation' => 0, // Number of lines that will create an account.
            'validforreactivation' => [], // Valid accounts for reactivation.
        ];

        local_mentor_core_validate_users_csv($userlist, 'comma', null, $preview, $errors, $warnings);

        self::assertCount(1, $preview['list']);
        self::assertEquals(2, $preview['list'][0]['linenumber']);
        self::assertEquals($user->lastname, $preview['list'][0]['lastname']);
        self::assertEquals($user->firstname, $preview['list'][0]['firstname']);
        self::assertEquals($user->email, $preview['list'][0]['email']);
        self::assertEquals(1, $preview['validlines']);
        self::assertEquals(0, $preview['validforcreation']);
        self::assertCount(1, $preview['validforreactivation']);
        self::assertEquals($user->id, $preview['validforreactivation'][$user->email]->id);
        self::assertEquals(1, $preview['validforreactivation'][$user->email]->suspended);
        self::assertEquals($user->email, $preview['validforreactivation'][$user->email]->email);

        self::assertNull($errors);

        self::assertCount(1, $warnings['list']);
        self::assertEquals(2, $warnings['list'][0][0]);
        self::assertEquals(
            get_string('warning_user_suspended', 'local_mentor_core'),
            $warnings['list'][0][1]
        );

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core enrol users csv function
     *
     * @covers ::local_mentor_core_enrol_users_csv
     */
    public function test_enrol_users_csv_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Guest and admin users.
        self::assertCount(2, $DB->get_records("user"));

        $userlist = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => 'lastname1.firstname1@gmail.com',
                'auth' => 'manual',
                'groupname' => 'gr1',
                'role' => 'FakeRole',
                'linenumber' => 2,
            ],
        ];

        // Create session.
        $session = $this->init_session_creation();
        $session->sessionstartdate = time();
        $session->update($session);
        $session->create_manual_enrolment_instance();

        $notification = local_mentor_core_enrol_users_csv($session->get_course(true)->id, $userlist);

        // One new user.
        self::assertCount(3, $DB->get_records('user'));

        // Import success.
        self::assertNotEmpty($notification);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core enrol users csv function
     * Activate user
     *
     * @covers ::local_mentor_core_enrol_users_csv
     */
    public function test_enrol_users_csv_ok_activate_user() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        $user = self::getDataGenerator()->create_user();
        $user->username = $user->email;
        $user->suspended = 1;
        user_update_user($user, false, false);

        // New user suspended.
        self::assertCount(3, $DB->get_records('user'));
        self::assertTrue($DB->record_exists('user', ['id' => $user->id, 'suspended' => 1]));

        $userstoreactivate = [
            ['email' => $user->email],
        ];

        // Create session.
        $session = $this->init_session_creation();

        local_mentor_core_enrol_users_csv($session->get_course()->id, [], $userstoreactivate);

        // User id active.
        self::assertCount(3, $DB->get_records('user'));
        self::assertFalse($DB->record_exists('user', ['id' => $user->id, 'suspended' => 1]));

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core create users csv function
     *
     * @covers ::local_mentor_core_create_users_csv
     */
    public function test_create_users_csv_ok() {
        global $DB, $CFG;

        $domain = 'gmail.com';
        $CFG->allowemailaddresses = $domain;;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Guest and admin users.
        self::assertCount(2, $DB->get_records('user'));
        $CFG->allowemailaddresses = 'gmail.com';
        $userlist = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => 'lastname1.firstname1@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];
       $notification = local_mentor_core_create_users_csv($userlist);

       // Two new user.
        self::assertCount(3, $DB->get_records('user'));
        self::assertEquals( get_string('created', 'local_mentor_core'), $notification[2]);
        // Import success.
        self::assertCount(1,$notification);

        // Create users and add to entity.
        $newentityname = 'Entity1';
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $newentityname, 'shortname' => $newentityname]);

        $userlist = [
            [
                'lastname' => 'lastname3',
                'firstname' => 'firstname3',
                'email' => 'lastname3.firstname1@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];

        
        local_mentor_core_create_users_csv($userlist, [], $entityid);

        // One new user.
        self::assertCount(4, $DB->get_records('user'));
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //So the user will have automatically main entity "Bibliothèque de formations"
        $user = $DB->get_record('user', ['email' => 'lastname3.firstname1@gmail.com']);
        $profile = \local_mentor_core\profile_api::get_profile($user->id);

        $userentity = $profile->get_main_entity();

        self::assertEquals("Bibliothèque de formations", $userentity->get_name());

        $userlist = [
            [
                'lastname' => 'lastname4',
                'firstname' => 'firstname4',
                'email' => 'lastname4.firstname4@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];

        // Add to secondary entity.
        local_mentor_core_create_users_csv($userlist, [], $entityid);

        // One new user.
        self::assertCount(5, $DB->get_records('user'));

        $user = $DB->get_record('user', ['email' => 'lastname4.firstname4@gmail.com']);

        $dbi = \local_mentor_core\database_interface::get_instance();
        $secondaryentities = $dbi->get_profile_field_value($user->id, 'secondaryentities');

        self::assertEquals($secondaryentities, $newentityname);

        self::resetAllData();
    }
    
    
    /**
     *  Test local_mentor_core create users csv function
     *
     * @covers ::local_mentor_core_create_users_csv
     */
    public function test_create_users_csv_toggle_externaluser_role() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $userlist = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => 'lastname1.firstname1@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];

        local_mentor_core_create_users_csv($userlist, [], null);

        $roleid = $DB->get_record('role', ['shortname' => 'utilisateurexterne'])->id;
        $userid = $DB->get_record('user', ['email' => 'lastname1.firstname1@gmail.com'])->id;
        $contextid = context_system::instance()->id;

        // Check external role is assigned
        self::assertTrue($DB->record_exists('role_assignments', ['roleid' =>  $roleid, 'userid' => $userid, 'contextid' => $contextid]));

        local_mentor_core_create_users_csv($userlist);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core reactivate users csv function
     *
     * @covers ::local_mentor_core_create_users_csv
     */
    public function test_local_mentor_core_create_users_csv_ok_reactivate() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Guest and admin users.
        self::assertCount(2, $DB->get_records('user'));

        $userlist = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => 'lastname1.firstname1@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];

        local_mentor_core_create_users_csv($userlist);

        $newuser = $DB->get_record('user', ['email' => 'lastname1.firstname1@gmail.com']);
        $newuser->suspended = 1;
        $DB->update_record('user', $newuser);

        $newuser = $DB->get_record('user', ['email' => 'lastname1.firstname1@gmail.com']);
        self::assertEquals(1, $newuser->suspended);

        local_mentor_core_create_users_csv([], $userlist);

        $newuser = $DB->get_record('user', ['email' => 'lastname1.firstname1@gmail.com']);
        self::assertEquals(0, $newuser->suspended);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core_create_users_csv : seconday entity
     *
     * @covers ::local_mentor_core_create_users_csv
     */
    public function test_local_mentor_core_create_users_csv_subentity_ok() {
        global $DB, $CFG;

        $domain = 'gmail.com';
        $CFG->allowemailaddresses = $domain;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        $dbi = \local_mentor_core\database_interface::get_instance();

        // Guest and admin users.
        self::assertCount(2, $DB->get_records('user'));

        $userlist = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => 'lastname1.firstname1@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];

        $entityid = \local_mentor_core\entity_api::create_entity(['name' => 'New Entity 1', 'shortname' => 'New Entity 1']);
        $domain = (object) [
            'course_categories_id' => $entityid,
            'domain_name' => $domain,
            'created_at' => time(),
            'disabled_at' => null
        ];
        $DB->insert_record('course_categories_domains', $domain, false);

        local_mentor_core_create_users_csv($userlist);

        $newuser = $DB->get_record('user', ['email' => 'lastname1.firstname1@gmail.com']);

        local_mentor_core_create_users_csv($userlist, [], $entityid);

        $mainentity = $dbi->get_profile_field_value($newuser->id, 'mainentity');
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //So the user will have automatically main entity "Bibliothèque de formations"
        self::assertEquals('New Entity 1', $mainentity);

        $entity2id = \local_mentor_core\entity_api::create_entity(['name' => 'New Entity 2', 'shortname' => 'New Entity 2']);

        // Add to secondary entity.
        local_mentor_core_create_users_csv($userlist, [], $entity2id);

        $secondaryentities = $dbi->get_profile_field_value($newuser->id, 'secondaryentities');

        self::assertEquals('New Entity 2', $secondaryentities);

        $entity3id = \local_mentor_core\entity_api::create_entity(['name' => 'New Entity 3', 'shortname' => 'New Entity 3']);

        // Add to an other secondary entity.
        local_mentor_core_create_users_csv($userlist, [], $entity3id);

        $secondaryentities = $dbi->get_profile_field_value($newuser->id, 'secondaryentities');

        self::assertEquals('New Entity 3', $secondaryentities);

        self::resetAllData();
    }


   
     /**
     ******************* TO DO : update this test on sprint61 **********************
     * Test local_mentor_core local_mentor_core_create_users_csv
     * MEN-187-RG001 assign current entity
     * @covers ::local_mentor_core_validate_users_csv
     */
    public function test_local_mentor_core_create_users_csv_assigned_current_entity(){
        global $DB;
        $this->resetAfterTest(true);
        $this->reset_singletons();
        self::setAdminUser();
        // Clean notification.
        \core\notification::fetch();
        self::assertCount(2, $DB->get_records('user'));

        $users = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => 'lastname1.firstname1@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];
        $newentityname = 'Entity1';

        $defaultentityname = 'DefaultEntity';
        $defaultentityid = \local_mentor_core\entity_api::create_entity(['name' => $defaultentityname, 'shortname' => $defaultentityname]);
        \local_mentor_specialization\mentor_entity::create_default_entity_option($defaultentityid);

        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $newentityname, 'shortname' => $newentityname]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        local_mentor_core_create_users_csv($users, [], $entityid);

        /*
        TU KO => to be fixed when we will be able to set the default entity on import CSV : TASK MEN-470
        $mainentityfieldid = $DB->get_record('user_info_field', ['shortname' => 'mainentity'])->id;
        $usermainentity = $DB->get_record('user_info_data', ['fieldid' => $mainentityfieldid])->data;
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        self::assertEquals("DefaultEntity", $usermainentity);*/
        self::resetAllData();
    }

    /**
     *  Test local_mentor_core get list status name changes function
     *
     * @covers ::local_mentor_core_get_list_status_name_changes
     */
    public function test_get_list_status_name_changes_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        $liststatus = local_mentor_core_get_list_status_name_changes();

        self::assertEquals($liststatus['dr'], 'draft');
        self::assertEquals($liststatus['tp'], 'template');
        self::assertEquals($liststatus['ec'], 'elaboration_completed');
        self::assertEquals($liststatus['ar'], 'archived');

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core get footer specialization function
     *
     * @covers ::local_mentor_core_get_footer_specialization
     */
    public function test_get_footer_specialization_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        $html = '';

        $html = local_mentor_core_get_footer_specialization($html);

        self::assertEquals('', $html);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core get profile fields values function
     *
     * @covers ::local_mentor_core_get_profile_fields_values
     */
    public function test_get_profile_fields_values_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        $profilefieldsvalues = local_mentor_core_get_profile_fields_values();

        self::assertIsArray($profilefieldsvalues);
        self::assertCount(1, $profilefieldsvalues);
        self::assertEquals($profilefieldsvalues[0][0], 'mainentity');
        self::assertEquals($profilefieldsvalues[0][1], 'Entité de rattachement');
        self::assertEquals($profilefieldsvalues[0][2], 'menu');
        self::assertEquals($profilefieldsvalues[0][3], '');
        self::assertEquals($profilefieldsvalues[0][4], 1);
        self::assertEquals($profilefieldsvalues[0][5], 1);
        self::assertEquals($profilefieldsvalues[0][6], 2);
        self::assertEquals($profilefieldsvalues[0][7], 1);
        self::assertEquals($profilefieldsvalues[0][8], 0);
        self::assertEquals($profilefieldsvalues[0][9], 2);
        self::assertEquals($profilefieldsvalues[0][10], 0);
        self::assertEquals($profilefieldsvalues[0][11], 0);
        self::assertEquals($profilefieldsvalues[0][12], '');
        self::assertEquals($profilefieldsvalues[0][13], 0);
        self::assertEquals($profilefieldsvalues[0][14], 'local_mentor_core_list_entities');

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core create field object to use function
     *
     * @covers ::local_mentor_core_create_field_object_to_use
     */
    public function test_create_field_object_to_use_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        self::setAdminUser();

        $DB->delete_records('course_categories');

        \local_mentor_core\entity_api::create_entity(['name' => 'Entity1', 'shortname' => 'Entity1']);

        $profilefieldsvalues = local_mentor_core_get_profile_fields_values();

        $profilefieldsvaluesobject = local_mentor_core_create_field_object_to_use($profilefieldsvalues[0]);

        self::assertIsObject($profilefieldsvaluesobject);

        self::assertObjectHasProperty('shortname', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->shortname, 'mainentity');

        self::assertObjectHasProperty('name', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->name, 'Entité de rattachement');

        self::assertObjectHasProperty('datatype', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->datatype, 'menu');

        self::assertObjectHasProperty('description', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->description, '');

        self::assertObjectHasProperty('descriptionformat', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->descriptionformat, 1);

        self::assertObjectHasProperty('categoryid', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->categoryid, 1);

        self::assertObjectHasProperty('sortorder', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->sortorder, 2);

        self::assertObjectHasProperty('required', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->required, 1);

        self::assertObjectHasProperty('locked', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->locked, 0);

        self::assertObjectHasProperty('visible', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->visible, 2);

        self::assertObjectHasProperty('forceunique', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->forceunique, 0);

        self::assertObjectHasProperty('signup', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->signup, 0);

        self::assertObjectHasProperty('defaultdata', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->defaultdata, '');

        self::assertObjectHasProperty('defaultdataformat', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->defaultdataformat, 0);

        self::assertObjectHasProperty('param1', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->param1, 'Entity1');

        self::assertObjectHasProperty('param2', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->param2, null);

        self::assertObjectHasProperty('param3', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->param3, null);

        self::assertObjectHasProperty('param4', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->param4, null);

        self::assertObjectHasProperty('param5', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->param5, null);

        // With field 14 unset.
        unset($profilefieldsvalues[0][14]);
        $profilefieldsvaluesobject = local_mentor_core_create_field_object_to_use($profilefieldsvalues[0]);

        self::assertObjectHasProperty('param1', $profilefieldsvaluesobject);
        self::assertEquals($profilefieldsvaluesobject->param1, null);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core generate user fields function
     *
     * @covers ::local_mentor_core_generate_user_fields
     */
    public function test_generate_user_fields_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        \local_mentor_core\entity_api::create_entity(['name' => 'Entity1', 'shortname' => 'Entity1']);

        $DB->delete_records('user_info_field');

        local_mentor_core_generate_user_fields();

        $userinfofields = $DB->get_records('user_info_field');
        $userinfofieldkey = array_key_first($userinfofields);

        self::assertCount(1, $userinfofields);
        self::assertEquals($userinfofields[$userinfofieldkey]->shortname, 'mainentity');
        self::assertEquals($userinfofields[$userinfofieldkey]->name, 'Entité de rattachement');
        self::assertEquals($userinfofields[$userinfofieldkey]->datatype, 'menu');
        self::assertEquals($userinfofields[$userinfofieldkey]->description, '');
        self::assertEquals($userinfofields[$userinfofieldkey]->descriptionformat, 1);
        self::assertEquals($userinfofields[$userinfofieldkey]->categoryid, 1);
        self::assertEquals($userinfofields[$userinfofieldkey]->sortorder, 2);
        self::assertEquals($userinfofields[$userinfofieldkey]->required, 1);
        self::assertEquals($userinfofields[$userinfofieldkey]->locked, 0);
        self::assertEquals($userinfofields[$userinfofieldkey]->visible, 2);
        self::assertEquals($userinfofields[$userinfofieldkey]->forceunique, 0);
        self::assertEquals($userinfofields[$userinfofieldkey]->signup, 0);
        self::assertEquals($userinfofields[$userinfofieldkey]->defaultdata, '');
        self::assertEquals($userinfofields[$userinfofieldkey]->defaultdataformat, 0);
        self::assertEquals($userinfofields[$userinfofieldkey]->param1, 'Entity1');
        self::assertEquals($userinfofields[$userinfofieldkey]->param2, null);
        self::assertEquals($userinfofields[$userinfofieldkey]->param3, null);
        self::assertEquals($userinfofields[$userinfofieldkey]->param4, null);
        self::assertEquals($userinfofields[$userinfofieldkey]->param5, null);

        // With record fields exist.
        local_mentor_core_generate_user_fields();

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core update entities list function
     *
     * @covers ::local_mentor_core_update_entities_list
     */
    public function test_update_entities_list_ok() {
        global $DB;
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        $newentityname = 'Entity1';
        \local_mentor_core\entity_api::create_entity(['name' => $newentityname, 'shortname' => $newentityname]);

        local_mentor_core_update_entities_list();

        $userinfodfield = $DB->get_record('user_info_field', ['shortname' => 'mainentity']);
        self::assertEquals($newentityname, $userinfodfield->param1);

        $newentityname2 = 'Entity2';
        \local_mentor_core\entity_api::create_entity(['name' => $newentityname2, 'shortname' => $newentityname2]);

        local_mentor_core_update_entities_list();

        $userinfodfield2 = $DB->get_record('user_info_field', ['shortname' => 'mainentity']);
        self::assertEquals($newentityname . "\n" . $newentityname2, $userinfodfield2->param1);

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core update entities list function with not exist user info field
     *
     * @covers ::local_mentor_core_update_entities_list
     */
    public function test_update_entities_list_nok() {
        global $DB;
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $DB->delete_records('user_info_field');

        try {
            local_mentor_core_update_entities_list();
        } catch (\Exception $e) {
            self::assertInstanceOf('moodle_exception', $e);
        }

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core list entities function
     *
     * @covers ::local_mentor_core_list_entities
     */
    public function test_list_entities_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $DB->delete_records('course_categories');

        self::assertEmpty(local_mentor_core_list_entities());

        \local_mentor_core\entity_api::create_entity(['name' => 'Entity1', 'shortname' => 'Entity1']);

        self::assertEquals(local_mentor_core_list_entities(), 'Entity1');

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core completion find exclusions function
     *
     * @covers ::local_mentor_core_completion_find_exclusions
     */
    public function test_completion_find_exclusions_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $coursedata = new \stdClass();
        $coursedata->category = 1;
        $coursedata->shortname = 'Course1';
        $coursedata->fullname = 'Course1';

        $course = create_course($coursedata);

        self::assertCount(0, local_mentor_core_completion_find_exclusions($course->id));

        self::resetAllData();
    }

    /**
     *  Test local_mentor_core sort array function
     *
     * @covers ::local_mentor_core_sort_array
     */
    public function test_sort_array_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        $data1 = new stdClass();
        $data1->name = "medium";
        $data1->value = 2;
        $data2 = new stdClass();
        $data2->name = "max";
        $data2->value = 3;
        $data3 = new stdClass();
        $data3->name = "min";
        $data3->value = 1;

        $datas = [
            $data1,
            $data2,
            $data3,
        ];

        local_mentor_core_sort_array($datas, 'value');

        self::assertEquals($datas[0]->name, 'max');
        self::assertEquals($datas[1]->name, 'medium');
        self::assertEquals($datas[2]->name, 'min');

        local_mentor_core_sort_array($datas, 'value', 'desc');

        self::assertEquals($datas[0]->name, 'min');
        self::assertEquals($datas[1]->name, 'medium');
        self::assertEquals($datas[2]->name, 'max');

        self::resetAllData();
    }

    /**
     * Test resize picture
     *
     * @covers ::local_mentor_core_resize_picture
     */
    public function test_resize_picture() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        $fs = get_file_storage();

        $filerecord = new stdClass();
        $filerecord->contextid = context_system::instance()->id;
        $filerecord->component = 'local_mentor_core';
        $filerecord->filearea = 'tests';
        $filerecord->itemid = 0;
        $filerecord->filepath = '/';
        $filerecord->filename = 'logo.png';

        $file = $fs->create_file_from_pathname($filerecord, $CFG->dirroot . '/local/mentor_core/pix/logo.png');

        // Try to resize with a bigger width than the original file.
        $result = local_mentor_core_resize_picture($file, 1000);
        self::assertFalse($result);

        // Invalid width.
        $result = local_mentor_core_resize_picture($file, -1);
        self::assertFalse($result);

        $newfile = local_mentor_core_resize_picture($file, 200);

        $content = $newfile->get_content();

        // Fetch the image information for this image.
        $imageinfo = @getimagesizefromstring($content);

        $originalwidth = $imageinfo[0];

        self::assertEquals(200, $originalwidth);

        self::resetAllData();
    }

    /**
     * Test decode csv content
     *
     * @covers ::local_mentor_core_decode_csv_content
     */
    public function test_decode_csv_content() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        $content = "test\r\ntest2";
        $finalcontent = local_mentor_core_decode_csv_content($content);
        self::assertEquals("test
test2", $finalcontent);

        $content = "test \xEF\xBB\xBF test2";
        $finalcontent = local_mentor_core_decode_csv_content($content);
        self::assertEquals('test  test2', $finalcontent);

        $content = "KLMÄšLENÃ";
        $finalcontent = local_mentor_core_decode_csv_content($content);
        self::assertEquals("KLMÄšLENÃ", $finalcontent);

        self::resetAllData();
    }

    /**
     * Test completion_get_activities
     *
     * @covers ::local_mentor_core_completion_get_activities
     */
    public function test_completion_get_activities() {

        $this->resetAfterTest(true);
        $this->reset_singletons();

        // Create course.
        $courserecord = new stdClass();
        $courserecord->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course($courserecord);

        // Create a mod without any completion.
        $record = new stdClass();
        $record->course = $course;
        $this->getDataGenerator()->create_module('forum', $record);

        $activities = local_mentor_core_completion_get_activities($course->id);
        self::assertCount(0, $activities);

        // Create a mod with completion enabled.
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $this->getDataGenerator()->create_module('url', $record);

        $activities = local_mentor_core_completion_get_activities($course->id);
        self::assertCount(1, $activities);

        self::resetAllData();
    }

    /**
     * Test extend_navigation_course
     *
     * @covers ::local_mentor_core_extend_navigation_course
     */
    public function test_extend_navigation_course() {

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create session.
        $session = $this->init_session_creation();
        $course = $session->get_course();

        $navigationnode = new navigation_node('Test node');

        $navigationnode->add(
            'copy',
            new moodle_url('/local/mentor_core/pages/importcsv.php', ['courseid' => $course->id]),
            navigation_node::TYPE_USER,
            null,
            'copy',
            new pix_icon('i/user', get_string('enrolusers', 'local_mentor_core'))
        );

        $navigationnode = local_mentor_core_extend_navigation_course($navigationnode, $course, context_course::instance
        ($course->id));

        $keys = $navigationnode->get_children_key_list();
        self::assertCount(1, $keys);

        // Check if copy node is hidden.
        $copy = $navigationnode->get('copy');
        self::assertFalse($copy->display);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_email_is_allowed
     *
     * @covers ::local_mentor_core_email_is_allowed
     */
    public function test_local_mentor_core_email_is_allowed() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        set_config('allowemailaddresses', 'agriculture.gouv.fr');

        // Allowed email.
        self::assertTrue(local_mentor_core_email_is_allowed('test@agriculture.gouv.fr'));

        // Not allowed email.
        self::assertFalse(local_mentor_core_email_is_allowed('test@hotmail.fr'));

        // Not validate email.
        self::assertFalse(local_mentor_core_email_is_allowed('test'));

        set_config('allowemailaddresses', '');
        self::resetAllData();
    }

    /**
     * Test local_mentor_core_completion_filter_activities
     *
     * @covers ::local_mentor_core_completion_filter_activities
     */
    public function test_local_mentor_core_completion_filter_activities() {
        global $USER;

        $this->resetAfterTest(true);
        self::setAdminUser();
        $user1 = $this->getDataGenerator()->create_user();

        // Create course.
        $courserecord = new stdClass();
        $courserecord->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course($courserecord);

        // Get gradebook exclusions list for students in a course.
        $exclusions = local_mentor_core_completion_find_exclusions($course->id, $USER->id);

        // Get activities list with completion set in current course.
        $activities = local_mentor_core_completion_get_activities($course->id);

        $filteractivities = local_mentor_core_completion_filter_activities($activities, $USER->id, $course->id, $exclusions);
        self::assertCount(0, $filteractivities);

        // Create a mod without any completion.
        $record = new stdClass();
        $record->course = $course;
        $this->getDataGenerator()->create_module('forum', $record);

        // Get activities list with completion set in current course.
        $activities = local_mentor_core_completion_get_activities($course->id);

        $filteractivities = local_mentor_core_completion_filter_activities($activities, $USER->id, $course->id, $exclusions);
        self::assertCount(0, $filteractivities);

        // Create a mod with completion enabled.
        $record = new stdClass();
        $record->course = $course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $this->getDataGenerator()->create_module('url', $record);

        // Get activities list with completion set in current course.
        $activities = local_mentor_core_completion_get_activities($course->id);

        $filteractivities = local_mentor_core_completion_filter_activities($activities, $USER->id, $course->id, $exclusions);

        self::assertCount(1, $filteractivities);

        // Add exclusion.
        $exclusions[] = $activities[0]['type'] . '-' . $activities[0]['instance'] . '-' . $USER->id;

        $filteractivities = local_mentor_core_completion_filter_activities($activities, $USER->id, $course->id, $exclusions);

        // Module is exclude.
        self::assertCount(0, $filteractivities);

        // Reset exclusions.
        $exclusions = local_mentor_core_completion_find_exclusions($course->id, $USER->id);

        // Create a mod with completion enabled.
        $record = new stdClass();
        $record->course = $course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $record->visible = 0;
        $this->getDataGenerator()->create_module('url', $record);

        self::setUser($user1);

        // Get activities list with completion set in current course.
        $activities = local_mentor_core_completion_get_activities($course->id);

        $filteractivities = local_mentor_core_completion_filter_activities($activities, $USER->id, $course->id, $exclusions);

        // New activity is not visible and user not has capability to view hidden activities.
        self::assertCount(1, $filteractivities);

        self::setAdminUser();

        $filteractivities = local_mentor_core_completion_filter_activities($activities, $USER->id, $course->id, $exclusions);

        // New activity is not visible but admin has capability to view hidden activities.
        self::assertCount(2, $filteractivities);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_completion_get_user_course_submissions
     *
     * @covers ::local_mentor_core_completion_get_user_course_submissions
     */
    public function test_local_mentor_core_completion_get_user_course_submissions() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create course.
        $courserecord = new stdClass();
        $courserecord->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course($courserecord);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'participant');

        // Create a mod without any completion.
        $record = new stdClass();
        $record->course = $course;
        $this->getDataGenerator()->create_module('forum', $record);

        $usersubmissions = local_mentor_core_completion_get_user_course_submissions($course->id, $student->id);
        self::assertCount(0, $usersubmissions);

        // Create a mod with completion enabled.
        $instance = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
            'maxattempts' => -1,
            'attemptreopenmethod' => 'untilpass',
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionusegrade' => 1,      // The student must receive a grade to complete.
            'completionexpected' => time() - DAYSECS,
            'teamsubmission' => 0,
        ]);
        $cm = get_coursemodule_from_id('assign', $instance->cmid);

        // Set the passing grade.
        $item = \grade_item::fetch([
            'courseid' => $course->id, 'itemtype' => 'mod',
            'itemmodule' => 'assign', 'iteminstance' => $instance->id, 'outcomeid' => null,
        ]);
        $item->gradepass = 50;
        $item->update();

        $assign = new \mod_assign_testable_assign(
            \context_module::instance($cm->id), $cm, $course);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'formateur');

        // Student 1 submits to the activity and gets graded correct.
        $this->submit_for_student($student, $assign);
        $this->grade_student($student, $assign, $teacher, 75, 0);

        // User has assign submission.
        $usersubmissions = local_mentor_core_completion_get_user_course_submissions($course->id, $student->id);
        self::assertCount(1, $usersubmissions);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_completion_get_progress
     *
     * @covers ::local_mentor_core_completion_get_progress
     */
    public function test_local_mentor_core_completion_get_progress() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        // Create course.
        $courserecord = new stdClass();
        $courserecord->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course($courserecord);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'participant');

        // Create a mod without any completion.
        $record = new stdClass();
        $record->course = $course;
        $this->getDataGenerator()->create_module('forum', $record);

        // Create a mod with completion enabled.
        $record = new stdClass();
        $record->course = $course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $record->visible = 1;
        $instance1 = $this->getDataGenerator()->create_module('url', $record);

        // Create a mod with completion enabled.
        $record = new stdClass();
        $record->course = $course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $record->visible = 1;
        $instance2 = $this->getDataGenerator()->create_module('url', $record);
        $completion = new completion_info($course);

        $cm = get_coursemodule_from_id('url', $instance2->cmid);
        $completion->update_state($cm, COMPLETION_COMPLETE, $student->id);

        $exclusions = local_mentor_core_completion_find_exclusions($course->id, $student->id);
        $activities = local_mentor_core_completion_get_activities($course->id);
        $activities = local_mentor_core_completion_filter_activities($activities, $student->id, $course->id, $exclusions);
        $submissions = local_mentor_core_completion_get_user_course_submissions($course->id, $student->id);
        $completions = local_mentor_core_completion_get_progress($activities, $student->id, $course, $submissions);

        self::assertCount(2, $completions);
        self::assertArrayHasKey($instance1->cmid, $completions);
        self::assertEquals(0, $completions[$instance1->cmid]);
        self::assertArrayHasKey($instance2->cmid, $completions);
        self::assertEquals('1', $completions[$instance2->cmid]);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_completion_get_progress_percentage
     *
     * @covers ::local_mentor_core_completion_get_progress_percentage
     */
    public function test_local_mentor_core_completion_get_progress_percentage() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        // Create course.
        $courserecord = new stdClass();
        $courserecord->enablecompletion = 0;
        $course = $this->getDataGenerator()->create_course($courserecord);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'participant');

        $progresspercentage = local_mentor_core_completion_get_progress_percentage($course, $student->id, true);
        // Disable completion.
        self::assertFalse($progresspercentage);

        // Create course.
        $courserecord = new stdClass();
        $courserecord->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course($courserecord);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'participant');

        $progresspercentage = local_mentor_core_completion_get_progress_percentage($course, $student->id, true);
        // No activities.
        self::assertFalse($progresspercentage);

        // Create a mod without any completion.
        $record = new stdClass();
        $record->course = $course;
        $this->getDataGenerator()->create_module('forum', $record);

        $progresspercentage = local_mentor_core_completion_get_progress_percentage($course, $student->id, true);
        self::assertEquals(0, $progresspercentage);

        // Create a mod with completion enabled.
        $record = new stdClass();
        $record->course = $course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $record->visible = 1;
        $instance1 = $this->getDataGenerator()->create_module('url', $record);

        // Create a mod with completion enabled.
        $record = new stdClass();
        $record->course = $course;
        $record->completion = 1;
        $record->completionview = 1;
        $record->completionexpected = 0;
        $record->completionunlocked = 1;
        $record->visible = 1;
        $instance2 = $this->getDataGenerator()->create_module('url', $record);
        $completion = new completion_info($course);

        $cm = get_coursemodule_from_id('url', $instance2->cmid);
        $completion->update_state($cm, COMPLETION_COMPLETE, $student->id);

        $progresspercentage = local_mentor_core_completion_get_progress_percentage($course, $student->id, true);
        self::assertEquals(50, $progresspercentage);

        $cm = get_coursemodule_from_id('url', $instance1->cmid);
        $completion->update_state($cm, COMPLETION_COMPLETE, $student->id);

        $progresspercentage = local_mentor_core_completion_get_progress_percentage($course, $student->id, true);
        self::assertEquals(100, $progresspercentage);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_get_available_sessions_csv_data
     *
     * @covers ::local_mentor_core_get_available_sessions_csv_data
     */
    public function test_local_mentor_core_get_available_sessions_csv_data() {
        global $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $CFG->mentor_specializations = [
            '\\local_mentor_specialization\\mentor_specialization' =>
                'local/mentor_specialization/classes/mentor_specialization.php',
        ];

        // Main entity.
        $entityid1 = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);

        // Main entity training.
        $entity1 = \local_mentor_core\entity_api::get_entity($entityid1);
        $entity1->update_sirh_list('RENOIRH_AES');
        $data = new stdClass();
        $data->name = 'training1';
        $data->shortname = 'training1';
        $data->content = 'summary';
        $data->status = \local_mentor_core\training::STATUS_ELABORATION_COMPLETED;
        // Fields for taining.
        $data->traininggoal = 'TEST TRAINING ';
        $data->thumbnail = '';
        $formationid = $entity1->get_entity_formation_category();
        $data->categorychildid = $formationid;
        $data->categoryid = $entity1->id;
        $data->creativestructure = $entity1->id;
        $training1 = \local_mentor_core\training_api::create_training($data);

        // Not available session.
        $sessionname1 = 'Session 1';
        $session1 = \local_mentor_core\session_api::create_session($training1->id, $sessionname1, true);
        $session1->status = \local_mentor_core\session::STATUS_OPENED_REGISTRATION;
        $session1->opento = 'current_entity';
        $now = time();
        $session1->sessionstartdate = $now;
        $session1->sessionenddate = $now + 38000;
        $session1->update($session1);

        $csvdata = local_mentor_core_get_available_sessions_csv_data($entityid1);

        self::assertCount(2, $csvdata);

        // Header.
        self::assertCount(20, $csvdata[0]);

        $headerrows = [
            'Espace dédié de la formation',
            'Intitulé de la formation',
            'Nom abrégé de la formation',
            'Collections',
            'Formation certifiante',
            'Identifiant SIRH d’origine',
            'Espace dédié de la session',
            'Libellé de la session',
            'Nom abrégé de la session',
            'Public cible',
            'Modalités de l\'inscription',
            'Durée en ligne',
            'Durée en présentiel',
            'Session permanente',
            'Date de début de la session de formation',
            'Date de fin de la session de formation',
            'Modalités de la session',
            'Accompagnement',
            'Nombre maximum de participants',
            'Places disponibles',
        ];

        foreach ($headerrows as $key => $headerrow) {
            self::assertEquals($headerrow, $csvdata[0][$key]);
        }

        // Content.
        self::assertCount(20, $csvdata[1]);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_ok() {
        global $DB, $CFG;

        $CFG->allowemailaddresses = 'test.fr';

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Main entity=> default entity => auto attachement of main entity
        $entity =  \local_mentor_specialization\mentor_entity::get_default_entity();

        // Create profile.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'email@test.fr';
        $user->username = 'email@test.fr';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        $preview = [];
        $errors = [];

        $content = [
            'email',
            'email@test.fr',
        ];

        self::assertFalse(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));
        self::assertEmpty($errors);

        self::assertNotEmpty($preview);

        self::assertArrayHasKey('list', $preview);
        self::assertCount(1, $preview['list']);
        self::assertCount(2, $preview['list'][0]);
        self::assertArrayHasKey('linenumber', $preview['list'][0]);
        self::assertEquals(2, $preview['list'][0]['linenumber']);
        self::assertArrayHasKey('email', $preview['list'][0]);
        self::assertEquals('email@test.fr', $preview['list'][0]['email']);

        self::assertArrayHasKey('validforsuspension', $preview);
        self::assertEquals(1, $preview['validforsuspension']);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     * Error too many lines.
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_too_many_lines() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Main entity.
        $entityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $preview = [];
        $errors = [];

        $content = [];
        for ($i = 0; $i < 5005; $i++) {
            $content[] = $i;
        }

        self::assertTrue(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));

        $notification = \core\notification::fetch();
        self::assertCount(1, $notification);
        self::assertEquals($notification[0]->get_message_type(), 'error');
        self::assertEquals($notification[0]->get_message(), get_string('error_too_many_lines', 'local_mentor_core'));

        self::resetAllData();

        self::setAdminUser();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     * Header error.
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_header_error() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Main entity.
        $entityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $preview = [];
        $errors = [];

        $content = [];
        for ($i = 0; $i < 2; $i++) {
            $content[] = $i;
        }

        self::assertTrue(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));

        $notification = \core\notification::fetch();
        self::assertCount(1, $notification);
        self::assertEquals($notification[0]->get_message_type(), 'error');
        self::assertEquals($notification[0]->get_message(),
            'L\'en-tête du fichier est incorrect. L\'en-tête attendu est : "email".');

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     * Missing data.
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_missing_data() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Main entity.
        $entityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $preview = [];
        $errors = [];

        $content = [
            'email',
        ];

        self::assertTrue(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));

        $notification = \core\notification::fetch();
        self::assertCount(1, $notification);
        self::assertEquals($notification[0]->get_message_type(), 'error');
        self::assertEquals($notification[0]->get_message(),
            get_string('missing_data', 'local_mentor_core'));

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     * Empty email line.
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_empty_email_line() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Main entity.
        $entityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $preview = [];
        $errors = [];

        $content = [
            'email',
            '',
        ];

        self::assertTrue(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));

        self::assertNotEmpty($errors);
        self::assertArrayHasKey('list', $errors);
        self::assertCount(1, $errors['list']);
        self::assertCount(2, $errors['list'][0]);
        self::assertEquals(2, $errors['list'][0][0]);
        self::assertEquals(get_string('invalid_email', 'local_mentor_core'), $errors['list'][0][1]);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     * Not valid email.
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_not_valid_email() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Main entity.
        $entityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $preview = [];
        $errors = [];

        $content = [
            'email',
            'email.fr',
        ];

        self::assertTrue(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));

        self::assertNotEmpty($errors);
        self::assertArrayHasKey('list', $errors);
        self::assertCount(1, $errors['list']);
        self::assertCount(2, $errors['list'][0]);
        self::assertEquals(2, $errors['list'][0][0]);
        self::assertEquals(get_string('email_not_valid', 'local_mentor_core'), $errors['list'][0][1]);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     * Not found email.
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_not_found_email() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Main entity.
        $entityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $preview = [];
        $errors = [];

        $content = [
            'email',
            'email@test.fr',
        ];

        self::assertTrue(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));

        self::assertNotEmpty($errors);
        self::assertArrayHasKey('list', $errors);
        self::assertCount(1, $errors['list']);
        self::assertCount(2, $errors['list'][0]);
        self::assertEquals(2, $errors['list'][0][0]);
        self::assertEquals('L\'adresse mél n\'a pas été trouvée. Cette ligne sera ignorée à l\'import.', $errors['list'][0][1]);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     * Email already use.
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_email_already_use() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Main entity.
        $entityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $preview = [];
        $errors = [];

        $content = [
            'email',
            'email@test.fr',
        ];

        // Two users with same email and username.
        $user1 = self::getDataGenerator()->create_user();
        $user1->email = 'email@test.fr';
        $user1->username = 'email@test.fr';
        $DB->update_record('user', $user1);
        $user2 = self::getDataGenerator()->create_user();
        $user2->email = 'email@test.fr';
        $user2->username = 'email@test.fr';
        $user2->mnethostid = 2;
        $DB->update_record('user', $user2);

        self::assertTrue(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));

        self::assertNotEmpty($errors);
        self::assertArrayHasKey('list', $errors);
        self::assertCount(1, $errors['list']);
        self::assertCount(2, $errors['list'][0]);
        self::assertEquals(2, $errors['list'][0][0]);
        self::assertEquals(get_string('email_already_used', 'local_mentor_core'), $errors['list'][0][1]);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     * Email already suspended.
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_email_already_suspended() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Main entity.
        $entityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $preview = [];
        $errors = [];

        $content = [
            'email',
            'email@test.fr',
        ];

        // Create user.
        $user1 = self::getDataGenerator()->create_user();
        $user1->email = 'email@test.fr';
        $user1->username = 'email@test.fr';
        $user1->suspended = 1;
        $DB->update_record('user', $user1);

        self::assertTrue(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));

        self::assertNotEmpty($errors);
        self::assertArrayHasKey('list', $errors);
        self::assertCount(1, $errors['list']);
        self::assertCount(2, $errors['list'][0]);
        self::assertEquals(2, $errors['list'][0][0]);
        self::assertEquals('Le compte utilisateur est déjà désactivé. Cette ligne sera ignorée à l\'import.',
            $errors['list'][0][1]);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     * Not connecting entity.
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_not_connecting_entity() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Main entity.
        $entityid = \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 1',
            'shortname' => 'New Entity 1',
        ]);
        $entity = \local_mentor_core\entity_api::get_entity($entityid);

        $preview = [];
        $errors = [];

        $content = [
            'email',
            'email@test.fr',
        ];

        // Main entity.
        \local_mentor_core\entity_api::create_entity([
            'name' => 'New Entity 2',
            'shortname' => 'New Entity 2',
        ]);

        // Create profile.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'email@test.fr';
        $user->username = 'email@test.fr';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

 
        self::assertTrue(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));

        self::assertNotEmpty($errors);
        self::assertArrayHasKey('list', $errors);
        self::assertCount(1, $errors['list']);
        self::assertCount(2, $errors['list'][0]);
        self::assertEquals(2, $errors['list'][0][0]);
        self::assertEquals('L\'utilisateur n\'est pas rattaché à l\'espace dédié ' . $entity->get_name() .
                           '. Cette ligne sera ignorée à l\'import', $errors['list'][0][1]);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_validate_suspend_users_csv
     * Highest role.
     *
     * @covers ::local_mentor_core_validate_suspend_users_csv
     */
    public function test_local_mentor_core_validate_suspend_users_csv_highest_role() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $this->init_role();

        // Clean notification.
        \core\notification::fetch();

        // Main entity.
        $defaultcategory = \local_mentor_specialization\mentor_entity::get_default_entity();
        $entity = \local_mentor_core\entity_api::get_entity($defaultcategory->id);

        $preview = [];
        $errors = [];

        $content = [
            'email',
            'email@test2.fr',
        ];

        // Create profile.
        $user = new stdClass();
        $user->lastname = 'lastname';
        $user->firstname = 'firstname';
        $user->email = 'email@test2.fr';
        $user->username = 'email@test2.fr';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';

        $userid2 = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);
      
        $entity->assign_manager($userid2);

        self::assertTrue(local_mentor_core_validate_suspend_users_csv($content, $entity, $preview, $errors));

        self::assertNotEmpty($errors);
        self::assertArrayHasKey('list', $errors);
        self::assertCount(1, $errors['list']);
        self::assertCount(2, $errors['list'][0]);
        self::assertEquals(2, $errors['list'][0][0]);
        self::assertEquals('L\'utilisateur possède un rôle élevé sur la plateforme. Cette ligne sera ignorée à l\'import.',
            $errors['list'][0][1]);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_suspend_users
     *
     * @covers ::local_mentor_core_suspend_users
     */
    public function test_local_mentor_core_suspend_users_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $user = self::getDataGenerator()->create_user();
        set_user_preference('auth_forcepasswordchange', 0, $user);

        self::assertEquals(0, $user->suspended);

        local_mentor_core_suspend_users([$user->email]);

        $user = $DB->get_record('user', ['id' => $user->id]);

        self::assertEquals(1, $user->suspended);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_clean_html
     *
     * @covers ::local_mentor_core_clean_html
     */
    public function test_local_mentor_core_clean_html_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $html = '<br><br/><p></p><p><br></p><div>test</div><p><br/></p><p dir="ltr" style="text-align: left;"></p>';

        self::assertEquals(
            '<div>test</div>',
            local_mentor_core_clean_html($html)
        );

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_remove_capability_for_all
     *
     * @covers ::local_mentor_core_remove_capability_for_all
     */
    public function test_local_mentor_core_remove_capability_for_all_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->setOutputCallback(function() {
        });

        self::setAdminUser();

        $mentorcorecapability = "local/mentor_core:changefullname";

        self::assertTrue($DB->record_exists('role_capabilities', ['capability' => $mentorcorecapability]));

        local_mentor_core_remove_capability_for_all($mentorcorecapability);

        self::assertFalse($DB->record_exists('role_capabilities', ['capability' => $mentorcorecapability]));

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_create_role
     *
     * @covers ::local_mentor_core_create_role
     * @covers ::local_mentor_core_add_context_levels
     */
    public function test_local_mentor_core_create_role_ok() {
        global $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();
        $this->setOutputCallback(function() {
        });

        self::setAdminUser();

        $newrolename = 'New Role Test';
        $newroleshortname = 'newroletest';
        $contextlevels = [
            CONTEXT_COURSECAT,
            CONTEXT_COURSE,
        ];

        $roleid = local_mentor_core_create_role($newrolename, $newroleshortname, $contextlevels);

        $role = $DB->get_record('role', ['id' => $roleid]);

        self::assertEquals($newrolename, $role->name);
        self::assertEquals($newroleshortname, $role->shortname);

        $rolecontextlevels = array_values($DB->get_records('role_context_levels', ['roleid' => $roleid]));

        self::assertCount(2, $rolecontextlevels);
        self::assertEquals(CONTEXT_COURSECAT, $rolecontextlevels[0]->contextlevel);
        self::assertEquals(CONTEXT_COURSE, $rolecontextlevels[1]->contextlevel);

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_sanitize_string
     *
     * @covers ::local_mentor_core_sanitize_string
     */
    public function test_local_mentor_core_sanitize_string_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        $string = "abcdefghijklmnopqrstuvwxyz";

        // Same string.
        self::assertEquals(local_mentor_core_sanitize_string($string), $string);

        $string2 = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";

        // Put everything in lower case.
        self::assertEquals(local_mentor_core_sanitize_string($string2), $string);

        $string3
            = "Montsieur @Mentor : retrouvez ci-dessous l’ensemble des cours dans" .
              " lesquelles vous pouvez vous inscrire en fonction des places disponibles.";
        self::assertEquals(
            local_mentor_core_sanitize_string($string3),
            "montsieur-mentor-retrouvez-ci-dessous-l-rsquo-ensemble-des-cours-dans-" .
            "lesquelles-vous-pouvez-vous-inscrire-en-fonction-des-places-disponibles-"
        );

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_usort_favourite_session_first
     *
     * @covers ::local_mentor_core_usort_favourite_session_first
     */
    public function test_local_mentor_core_usort_favourite_session_first_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        // No favourite.
        $element1 = new \stdClass();
        $element1->favouritesession = false;
        $element2 = new \stdClass();
        $element2->favouritesession = false;

        self::assertEquals(0, local_mentor_core_usort_favourite_session_first($element1, $element2));

        // Element1 has favourite.
        $element1 = new \stdClass();
        $element1->favouritesession = new \stdClass();
        $element2 = new \stdClass();
        $element2->favouritesession = false;

        self::assertEquals(-1, local_mentor_core_usort_favourite_session_first($element1, $element2));

        // Element2 has favourite.
        $element1 = new \stdClass();
        $element1->favouritesession = false;
        $element2 = new \stdClass();
        $element2->favouritesession = new \stdClass();

        self::assertEquals(1, local_mentor_core_usort_favourite_session_first($element1, $element2));

        // Same time.
        $element1 = new \stdClass();
        $element1->favouritesession = new \stdClass();
        $element1->favouritesession->timecreated = time();
        $element2 = new \stdClass();
        $element2->favouritesession = new \stdClass();
        $element2->favouritesession->timecreated = time();

        self::assertEquals(0, local_mentor_core_usort_favourite_session_first($element1, $element2));

        // Element1 has first favourite.
        $element1 = new \stdClass();
        $element1->favouritesession = new \stdClass();
        $element1->favouritesession->timecreated = time();
        $element2 = new \stdClass();
        $element2->favouritesession = new \stdClass();
        $element2->favouritesession->timecreated = time() + 2;

        self::assertEquals(1, local_mentor_core_usort_favourite_session_first($element1, $element2));

        // Element2 has first favourite.
        $element1 = new \stdClass();
        $element1->favouritesession = new \stdClass();
        $element1->favouritesession->timecreated = time() + 2;
        $element2 = new \stdClass();
        $element2->favouritesession = new \stdClass();
        $element2->favouritesession->timecreated = time();

        self::assertEquals(-1, local_mentor_core_usort_favourite_session_first($element1, $element2));

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_uasort_session_to_catalog
     * by entity shortname
     *
     * @covers ::local_mentor_core_uasort_session_to_catalog
     */
    public function test_local_mentor_core_uasort_session_to_catalog_ok_by_entity_shortname() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        // False entity data.
        $falseentity = new \stdClass();
        $falseentity->name = "A-entity";

        // Create entity Mock.
        $entitymock = $this->getMockBuilder('\local_mentor_core\entity')
            ->setMethods(['get_main_entity'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return false value when get_main_entity function call.
        $entitymock->expects($this->any())
            ->method('get_main_entity')
            ->will($this->returnValue($falseentity));

        // Create session Mock.
        $sessionmock = $this->getMockBuilder('\local_mentor_core\session')
            ->setMethods(['get_entity'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return entity Mock value when get_entity function call.
        $sessionmock->expects($this->any())
            ->method('get_entity')
            ->will($this->returnValue($entitymock));

        // Entity data.
        $falseentity2 = new \stdClass();
        $falseentity2->name = "B-entity";

        // Create entity Mock.
        $entitymock2 = $this->getMockBuilder('\local_mentor_core\entity')
            ->setMethods(['get_main_entity'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return false value when get_main_entity function call.
        $entitymock2->expects($this->any())
            ->method('get_main_entity')
            ->will($this->returnValue($falseentity2));

        // Create session Mock.
        $sessionmock2 = $this->getMockBuilder('\local_mentor_core\session')
            ->setMethods(['get_entity'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return entity Mock when get_entity function call.
        $sessionmock2->expects($this->any())
            ->method('get_entity')
            ->will($this->returnValue($entitymock2));

        // A-entity first.
        self::assertEquals(-1, (local_mentor_core_uasort_session_to_catalog($sessionmock, $sessionmock2)));

        // A-entity first.
        self::assertEquals(1, (local_mentor_core_uasort_session_to_catalog($sessionmock2, $sessionmock)));

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_uasort_session_to_catalog
     * by training shortname
     *
     * @covers ::local_mentor_core_uasort_session_to_catalog
     */
    public function test_local_mentor_core_uasort_session_to_catalog_ok_by_training_shortname() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        // False entity data.
        $falseentity = new \stdClass();
        $falseentity->name = "A-entity";

        // False training data.
        $falsetraining = new \stdClass();
        $falsetraining->shortname = "A-training";

        // Create entity Mock.
        $entitymock = $this->getMockBuilder('\local_mentor_core\entity')
            ->setMethods(['get_main_entity'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return false value when get_main_entity function call.
        $entitymock->expects($this->any())
            ->method('get_main_entity')
            ->will($this->returnValue($falseentity));

        // Create session Mock.
        $sessionmock = $this->getMockBuilder('\local_mentor_core\session')
            ->setMethods(['get_entity', 'get_training'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return entity Mock when get_entity function call.
        $sessionmock->expects($this->any())
            ->method('get_entity')
            ->will($this->returnValue($entitymock));

        // Return false value when get_training function call.
        $sessionmock->expects($this->any())
            ->method('get_training')
            ->will($this->returnValue($falsetraining));

        // False training data.
        $falsetraining2 = new \stdClass();
        $falsetraining2->shortname = "B-training";

        // Create entity Mock.
        $entitymock2 = $this->getMockBuilder('\local_mentor_core\entity')
            ->setMethods(['get_main_entity'])
            ->disableOriginalConstructor()
            ->getMock();

        // Same entity shortname.
        $entitymock2->expects($this->any())
            ->method('get_main_entity')
            ->will($this->returnValue($falseentity));

        // Create database interface Mock.
        $sessionmock2 = $this->getMockBuilder('\local_mentor_core\session')
            ->setMethods(['get_entity', 'get_training'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return entity Mock when get_entity function call.
        $sessionmock2->expects($this->any())
            ->method('get_entity')
            ->will($this->returnValue($entitymock2));

        // Return false value when get_training function call.
        $sessionmock2->expects($this->any())
            ->method('get_training')
            ->will($this->returnValue($falsetraining2));

        // A-training first.
        self::assertEquals(-1, (local_mentor_core_uasort_session_to_catalog($sessionmock, $sessionmock2)));

        // A-training first.
        self::assertEquals(1, (local_mentor_core_uasort_session_to_catalog($sessionmock2, $sessionmock)));

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_uasort_session_to_catalog
     * by session shortname
     *
     * @covers ::local_mentor_core_uasort_session_to_catalog
     */
    public function test_local_mentor_core_uasort_session_to_catalog_ok_by_session_shortname() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        // False entity data.
        $falseentity = new \stdClass();
        $falseentity->name = "A-entity";

        // False training data.
        $falsetraining = new \stdClass();
        $falsetraining->shortname = "A-training";

        // Create entity Mock.
        $entitymock = $this->getMockBuilder('\local_mentor_core\entity')
            ->setMethods(['get_main_entity'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return false value when get_main_entity function call.
        $entitymock->expects($this->any())
            ->method('get_main_entity')
            ->will($this->returnValue($falseentity));

        // Create session Mock.
        $sessionmock = $this->getMockBuilder('\local_mentor_core\session')
            ->setMethods(['get_entity', 'get_training'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return entity Mock when get_entity function call.
        $sessionmock->expects($this->any())
            ->method('get_entity')
            ->will($this->returnValue($entitymock));

        // Return false value when get_training function call.
        $sessionmock->expects($this->any())
            ->method('get_training')
            ->will($this->returnValue($falsetraining));

        // Update session shortname.
        $sessionmock->shortname = 'A-session';

        // Create entity Mock.
        $entitymock2 = $this->getMockBuilder('\local_mentor_core\entity')
            ->setMethods(['get_main_entity'])
            ->disableOriginalConstructor()
            ->getMock();

        // Same entity shortname.
        $entitymock2->expects($this->any())
            ->method('get_main_entity')
            ->will($this->returnValue($falseentity));

        // Create session Mock.
        $sessionmock2 = $this->getMockBuilder('\local_mentor_core\session')
            ->setMethods(['get_entity', 'get_training'])
            ->disableOriginalConstructor()
            ->getMock();

        // Return entity Mock when get_entity function call.
        $sessionmock2->expects($this->any())
            ->method('get_entity')
            ->will($this->returnValue($entitymock2));

        // Same training.
        $sessionmock2->expects($this->any())
            ->method('get_training')
            ->will($this->returnValue($falsetraining));

        // Update session shortname.
        $sessionmock2->shortname = 'B-session';

        // A-training first.
        self::assertEquals(-1, (local_mentor_core_uasort_session_to_catalog($sessionmock, $sessionmock2)));

        // A-training first.
        self::assertEquals(1, (local_mentor_core_uasort_session_to_catalog($sessionmock2, $sessionmock)));

        // Same session name.
        self::assertEquals(0, (local_mentor_core_uasort_session_to_catalog($sessionmock, $sessionmock)));

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_get_edadmin_course_view_capability for trainings
     *
     * @covers ::local_mentor_core_get_edadmin_course_view_capability
     */
    public function test_local_mentor_core_get_edadmin_course_view_capability_ok_trainings() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::assertEquals(
            'local/mentor_core:movetrainings',
            local_mentor_core_get_edadmin_course_view_capability('trainings')
        );

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_get_edadmin_course_view_capability for session
     *
     * @covers ::local_mentor_core_get_edadmin_course_view_capability
     */
    public function test_local_mentor_core_get_edadmin_course_view_capability_ok_session() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::assertEquals(
            'local/mentor_core:movesessions',
            local_mentor_core_get_edadmin_course_view_capability('session')
        );

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_get_edadmin_course_view_capability for user
     *
     * @covers ::local_mentor_core_get_edadmin_course_view_capability
     */
    public function test_local_mentor_core_get_edadmin_course_view_capability_ok_user() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::assertEquals(
            'local/entities:manageentity',
            local_mentor_core_get_edadmin_course_view_capability('user')
        );

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_get_edadmin_course_view_capability for entities
     *
     * @covers ::local_mentor_core_get_edadmin_course_view_capability
     */
    public function test_local_mentor_core_get_edadmin_course_view_capability_ok_entities() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::assertEquals(
            'local/entities:manageentity',
            local_mentor_core_get_edadmin_course_view_capability('entities')
        );

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_get_edadmin_course_view_capability for default
     *
     * @covers ::local_mentor_core_get_edadmin_course_view_capability
     */
    public function test_local_mentor_core_get_edadmin_course_view_capability_ok_default() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::assertEquals(
            'local/entities:manageentity',
            local_mentor_core_get_edadmin_course_view_capability()
        );

        self::resetAllData();
    }

    /**
     * Test local_mentor_core_get_course_url
     *
     * @covers ::local_mentor_core_get_course_url
     */
    public function test_local_mentor_core_local_mentor_core_get_course_url() {
        global $CFG, $DB;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        // Not topic course.
        $course = new stdClass();
        $course->id = 100;
        $course->format = 'nottopics';
        $url = local_mentor_core_get_course_url($course);

        self::assertEquals(
            $url->out(false),
            $CFG->wwwroot . '/course/view.php?id=' . $course->id
        );

        // Display all section.
        $course->format = 'topics';
        $formatoption = new stdClass();
        $formatoption->courseid = $course->id;
        $formatoption->format = $course->format;
        $formatoption->sectionid = 0;
        $formatoption->name = 'coursedisplay';
        $formatoption->value = 0;
        $DB->insert_record('course_format_options', $formatoption);
        $url = local_mentor_core_get_course_url($course);
        self::assertEquals(
            $url->out(false),
            $CFG->wwwroot . '/course/view.php?id=' . $course->id
        );

        // Clear DB.
        $DB->delete_records('course_format_options');

        // Url to first section.
        $course->format = 'topics';
        $formatoption = new stdClass();
        $formatoption->courseid = $course->id;
        $formatoption->format = $course->format;
        $formatoption->sectionid = 0;
        $formatoption->name = 'coursedisplay';
        $formatoption->value = 1;
        $DB->insert_record('course_format_options', $formatoption);
        $section = new stdClass();
        $section->course = $course->id;
        $section->section = 1;
        $section->visible = 1;
        $DB->insert_record('course_sections', $section);
        $url = local_mentor_core_get_course_url($course);
        self::assertEquals(
            $url->out(false),
            $CFG->wwwroot . '/course/view.php?id=' . $course->id . '&section=1'
        );

        self::resetAllData();
    }

     /**
     *  Test local_mentor_core create users csv function
     *
     * @covers ::local_mentor_core_create_users_csv
     */
    public function test_create_users_csv_secondary_entity() {
        global $DB, $CFG;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();

        // Guest and admin users.
        self::assertCount(2, $DB->get_records('user'));
        $CFG->allowemailaddresses = 'gmail.com';
        // ********************** Case import performed on a main space *******************
        // Create users and add to entity.
        $newentityname = 'Entity1';
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => $newentityname, 'shortname' => $newentityname]);
       
        $userlist = [
            [
                'lastname' => 'lastname3',
                'firstname' => 'firstname3',
                'email' => 'lastname3.firstname1@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];
        
        local_mentor_core_create_users_csv($userlist, [], $entityid);

        // One new user.
        self::assertCount(3, $DB->get_records('user'));
        //From sprint60, 
        //the main entity of the user on create/update, will be affected automatically basing on his email domain 
        //So the user will have automatically main entity "Bibliothèque de formations"
        $user = $DB->get_record('user', ['email' => 'lastname3.firstname1@gmail.com']);
        $profile = \local_mentor_core\profile_api::get_profile($user->id);

        $userentity = $profile->get_main_entity();

        self::assertEquals("Bibliothèque de formations", $userentity->get_name());
        $dbi = \local_mentor_core\database_interface::get_instance();
        // If the email is not linked to the main entity where the import is taking place,
        // assign the entity as a secondary attachment.
        $secondaryentities = $dbi->get_profile_field_value($user->id, 'secondaryentities');
        self::assertEquals($newentityname, $secondaryentities);

        // If the email is linked to the main entity where the import is taking place,
        // the user has no secondary attachment or it is assigned manually.
        $categoriesdomainsrepository = new categories_domains_repository();     

        $domain = new domain_name();
        $domain->domain_name = 'test.com';
        $domain->course_categories_id = $entityid;

        $result = $categoriesdomainsrepository->add_domain($domain);
        $userlist = [
            [
                'lastname' => 'lastname4',
                'firstname' => 'firstname4',
                'email' => 'lastname4.firstname4@test.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];
        
        local_mentor_core_create_users_csv($userlist, [], $entityid);
        $user = $DB->get_record('user', ['email' => 'lastname4.firstname4@test.com']);
        $secondaryentities = $dbi->get_profile_field_value($user->id, 'secondaryentities');
        self::assertEquals("", $secondaryentities);

        // ********************** Case import performed on a non main space *******************
        // Create users and add to entity.
        $notmainenttityname = 'Entity2';
        $notmainentityid = \local_mentor_core\entity_api::create_entity(['name' => $notmainenttityname, 'shortname' => $notmainenttityname]);
        $notmainentity = new local_mentor_specialization\mentor_entity($notmainentityid);
        $notmainentity->update_can_be_main_entity(false);

        $userlist = [
            [
                'lastname' => 'lastname5',
                'firstname' => 'firstname5',
                'email' => 'lastname5.firstname5@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];
        
        local_mentor_core_create_users_csv($userlist, [], $notmainentity->id);
        $user = $DB->get_record('user', ['email' => 'lastname5.firstname5@gmail.com']);
        // Assign the entity as a secondary attachment if the import is performed on a non-main space.
        $secondaryentities = $dbi->get_profile_field_value($user->id, 'secondaryentities');
        self::assertEquals($notmainenttityname, $secondaryentities);

        $userlist = [
            [
                'lastname' => 'lastname6',
                'firstname' => 'firstname6',
                'email' => 'lastname6.firstname6@testx.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];

        local_mentor_core_create_users_csv($userlist, [], $notmainentity->id);
        $user = $DB->get_record('user', ['email' => 'lastname6.firstname6@testx.com']);  
        // An external user has as a secondary attachment: the space on which the import is performed.
        $secondaryentities = $dbi->get_profile_field_value($user->id, 'secondaryentities');
        self::assertEquals($notmainenttityname, $secondaryentities);

        self::resetAllData();
    }

    
    /**
     * Test local_mentor_core_build_csv_report_lines with missing resultData.
     * @covers ::local_mentor_core_build_csv_report_lines
     */
    public function test_local_mentor_core_build_csv_report_lines_missing_result() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        $lines = [
            "email;lastname;firstname",
            "user3@example.com;Brown;Charlie"
        ];
        $resultData = []; // No result for line 1
        $delimiter = ';';

        $result = local_mentor_core_build_csv_report_lines($lines, $resultData, $delimiter);

        $this->assertStringContainsString(get_string('not_processed', 'local_mentor_core'), $result[1]);
    }

    /**
     * Test local_mentor_core_build_csv_report_lines trims and cleans columns.
     * @covers ::local_mentor_core_build_csv_report_lines
     */
    public function test_local_mentor_core_build_csv_report_lines_trims_and_cleans() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        $lines = [
            "email;lastname;firstname",
            " user4@example.com ; Doe ; John "
        ];
        $resultData = [2 => "created"];
        $delimiter = ';';

        $result = local_mentor_core_build_csv_report_lines($lines, $resultData, $delimiter);

        $this->assertStringContainsString('user4@example.com', $result[1]);
        $this->assertStringContainsString('created', $result[1]);
    }

    /**
     * Test local_mentor_core_send_report
     * @covers ::local_mentor_core_send_report
     */
    public function test_send_report_creates_file_successfully() {
        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user([
            'email' => 'noreply@example.com' 
        ]);

        // CSV content
        $csv_content = ['header1;header2', 'value1;value2'];
        $resultData = [['status' => 'ok', 'line' => 1]];

        $filenameprefix = 'report_test_' . time();

        $result = \local_mentor_core_send_report(
            $csv_content,
            $resultData,
            'semicolon',
            $filenameprefix,
            $user->id
        );

        $this->assertTrue($result);

        // Check that the file has been generated
        $dirpath = make_temp_directory('userimportreports');
        $filepath = $dirpath . '/Rapport_' . $filenameprefix . '.csv';

        $this->assertFileExists($filepath);

        // Check that the content is correctly written
        $content = file_get_contents($filepath);
        $this->assertStringContainsString('header1', $content);
        $this->assertStringContainsString('value1', $content);
    }

     /**
     *  Test local_mentor_core create users csv function
     *  test create users csv results data 
     *
     * @covers ::local_mentor_core_create_users_csv
     */
    public function test_create_users_csv_results_data() {
        global $DB, $CFG;

        $domain = 'gmail.com';
        $CFG->allowemailaddresses = $domain;;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Clean notification.
        \core\notification::fetch();
        $entityid = \local_mentor_core\entity_api::create_entity(['name' => 'New Entity 1', 'shortname' => 'New Entity 1']);

        // Guest and admin users.
        self::assertCount(2, $DB->get_records('user'));
        $CFG->allowemailaddresses = 'gmail.com';
        $userlist = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => 'lastname1.firstname1@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
        ];
        //test result data on user creation
       $resultData = local_mentor_core_create_users_csv($userlist,[], $entityid);

        self::assertEquals( get_string('created', 'local_mentor_core'), $resultData[2]);
        // Import success.
        self::assertCount(1,$resultData);
        // Create suspended user.
        $suspendeduser = self::getDataGenerator()->create_user([
            'lastname' => 'suspendeduser',
            'firstname' => 'suspendeduser',
            'email' => 'suspendeduser@mail.fr',
            'username' => 'suspendeduser@mail.fr',
            'mnethostid' => 1,
            'confirmed' => 1,
            'suspended' => 1,
        ]);
        //Create user 
        $user = self::getDataGenerator()->create_user();


        //test cases: 
            // - new user created
            // - duplated user already created
            // - reactivate user
            // - duplated user already reactivated
            // - user already exist 
        $userlist = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => 'lastname1.firstname1@gmail.com',
                'auth' => 'manual',
                'linenumber' => 2
            ],
            [
                'lastname' => 'user',
                'firstname' => 'user',
                'email' => 'user.user@gmail.com',
                'auth' => 'manual',
                'linenumber' => 3
            ],
            [
                'lastname' => 'user1',
                'firstname' => 'user1',
                'email' => 'user1@test.com',
                'auth' => 'manual',
                'linenumber' => 4
            ],
            [
                'lastname' => $user->lastname,
                'firstname' => $user->firstname,
                'email' => $user->email,
                'auth' => 'manual',
                'linenumber' => 5
            ],
            [
                'lastname' => 'user',
                'firstname' => 'user',
                'email' => 'user.user@gmail.com',
                'auth' => 'manual',
                'linenumber' => 6
            ],
            [
                'lastname' => 'suspendeduser',
                'firstname' => 'suspendeduser',
                'email' => 'suspendeduser@mail.fr',
                'auth' => 'manual',
                'linenumber' => 7
            ],
            [
                'lastname' => 'suspendeduser',
                'firstname' => 'suspendeduser',
                'email' => 'suspendeduser@mail.fr',
                'auth' => 'manual',
                'linenumber' => 8
            ],
        ];
        $userstoactivate = [
            [
                'lastname' => 'suspendeduser',
                'firstname' => 'suspendeduser',
                'email' => 'suspendeduser@mail.fr',
            ]
        ];
       $resultData = local_mentor_core_create_users_csv($userlist, $userstoactivate,$entityid);
    // Expected result.
            $expected = [
                7 =>  get_string('reactivated', 'local_mentor_core'),
                2 => get_string('alreadyexists', 'local_mentor_core'),
                3 =>  get_string('created', 'local_mentor_core'),
                4 =>  get_string('created', 'local_mentor_core'),
                5 => get_string('alreadyexists', 'local_mentor_core'),
            ];

        // Assert the notification array matches the expected result.
        self::assertEquals($expected, $resultData);    
    }

         /**
     *  Test local_mentor_core_enrol_users_csv create users csv function
     *  test create users csv results data 
     *
     * @covers ::local_mentor_core_enrol_users_csv
     */
       public function test_enrol_users_csv_results_data() {
        global $DB, $CFG;

        $domain = 'gmail.com';
        $CFG->allowemailaddresses = $domain;;

        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();
        // Create session.
        $session = $this->init_session_creation();
        $session->sessionstartdate = time();
        $session->update($session);
        $session->create_manual_enrolment_instance();
        // Clean notification.
        \core\notification::fetch();

        // Guest and admin users.
        self::assertCount(2, $DB->get_records('user'));
        $CFG->allowemailaddresses = 'gmail.com';
        $userlist = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => 'lastname1.firstname1@gmail.com',
                'auth' => 'manual',
                'groupname' => 'gr1',
                'role' => 'Participant',
                'linenumber' => 2
            ],
        ];
        //test result data on user creation
       $resultData = $resultData =  local_mentor_core_enrol_users_csv($session->get_course()->id, $userlist, []);

        self::assertEquals( get_string('createdenrolled', 'local_mentor_core'), $resultData[2]);
        // Import success.
        self::assertCount(1,$resultData);
        // Create suspended user.
        $suspendeduser = self::getDataGenerator()->create_user([
            'lastname' => 'suspendeduser',
            'firstname' => 'suspendeduser',
            'email' => 'suspendeduser@mail.fr',
            'username' => 'suspendeduser@mail.fr',
            'mnethostid' => 1,
            'confirmed' => 1,
            'groupname' => 'gr1',
            'suspended' => 1,
        ]);
        //Create user 
        $user = self::getDataGenerator()->create_user();


        //test cases: 
            // - new user 
            // - duplated user already created and enrolled but with different role => role will be updated 
            // - reactivate user not enrolled
            // - duplated user already reactivated and enrolled
            // - user already exist   
        $userlist = [
            [
                'lastname' => 'lastname1',
                'firstname' => 'firstname1',
                'email' => 'lastname1.firstname1@gmail.com',
                'auth' => 'manual',
                'groupname' => 'gr1',
                'role' => 'formateur',
                'linenumber' => 2
            ],
            [
                'lastname' => 'user',
                'firstname' => 'user',
                'email' => 'user.user@gmail.com',
                'auth' => 'manual',
                'groupname' => 'gr1',
                'role' => 'Participant',
                'linenumber' => 3
            ],
            [
                'lastname' => 'user1',
                'firstname' => 'user1',
                'email' => 'user1@test.com',
                'groupname' => 'gr1',
                'auth' => 'manual',
                'role' => 'Participant',
                'linenumber' => 4
            ],
            [
                'lastname' => $user->lastname,
                'firstname' => $user->firstname,
                'email' => $user->email,
                'groupname' => 'gr1',
                'auth' => 'manual',
                'role' => 'Participant',
                'linenumber' => 5
            ],
            [
                'lastname' => 'user',
                'firstname' => 'user',
                'email' => 'user.user@gmail.com',
                'groupname' => 'gr1',
                'auth' => 'manual',
                'role' => 'formateur',
                'linenumber' => 6
            ],
            [
                'lastname' => 'suspendeduser',
                'firstname' => 'suspendeduser',
                'email' => 'suspendeduser@mail.fr',
                'groupname' => 'gr1',
                'auth' => 'manual',
                'role' => 'Participant',
                'linenumber' => 7
            ],
            [
                'lastname' => 'suspendeduser',
                'firstname' => 'suspendeduser',
                'email' => 'suspendeduser@mail.fr',
                'groupname' => 'gr1',
                'auth' => 'manual',
                'role' => 'Participant',
                'linenumber' => 8
            ],
        ];
        $userstoactivate = [
            [
                'lastname' => 'suspendeduser',
                'firstname' => 'suspendeduser',
                'email' => 'suspendeduser@mail.fr',
                'groupname' => 'gr1',
                'role' => 'FakeRole',
            ]
        ];
        $resultData =  local_mentor_core_enrol_users_csv($session->get_course()->id, $userlist, $userstoactivate);

        // Expected result.
            $expected = [
                7 =>  get_string('reactivatedenrolled', 'local_mentor_core'),
                2 =>  get_string('enrolled', 'local_mentor_core'),
                3 =>  get_string('createdenrolled', 'local_mentor_core'),
                4 =>  get_string('createdenrolled', 'local_mentor_core'),
                5 => get_string('enrolled', 'local_mentor_core'),
                6 => get_string('enrolled', 'local_mentor_core'),
            ];

        self::assertEquals($expected, $resultData);    
    }

}
