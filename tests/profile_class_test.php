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
 * Test cases for class profile
 *
 * @package    local_mentor_core
 * @copyright  2021 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');
require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/profile.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

class local_mentor_core_profile_class_testcase extends advanced_testcase {

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

        $databaseinterface = \local_mentor_core\database_interface::get_instance();
        $reflection = new ReflectionClass($databaseinterface);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true); // Now we can modify that :).
        $instance->setValue(null, null); // Instance is gone.
        $instance->setAccessible(false); // Clean up.

        \local_mentor_core\training_api::clear_cache();
    }

    /**
     * Test profile constructor
     *
     * @covers \local_mentor_core\profile::__construct
     */
    public function test_profile_construct_ok() {
        global $DB;
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $db = \local_mentor_core\database_interface::get_instance();

        $entityid = \local_mentor_core\entity_api::create_entity([
                'name' => 'Entity',
                'shortname' => 'Entity',
        ]);

        $lastname = 'lastname';
        $firstname = 'firstname';
        $email = 'user@gouv.fr';
        $auth = 'manual';

        // Create user.
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user(
                $lastname, $firstname, $email, $entityid, [], null, $auth));

        // Get user.
        $profiledata = $db->get_user_by_email('user@gouv.fr');

        $profile = new \local_mentor_core\profile($profiledata->id);

        self::assertEquals($profile->lastname, $lastname);
        self::assertEquals($profile->firstname, $firstname);
        self::assertEquals($profile->email, $email);
        self::assertEquals($profile->email, $email);
        self::assertEquals($profile->lastconnection['display'], 'Jamais');
        self::assertEquals($profile->lastconnection['timestamp'], 0);

        // Simulate an access user.
        $userdata = new stdClass();
        $userdata->id = $profile->id;
        $userdata->lastaccess = time();
        $DB->update_record('user', $userdata);

        // Refresh profile data.
        $profiledata = $db->get_user_by_id($profiledata->id, true);
        $profile = new \local_mentor_core\profile($profiledata->id);

        $format = strlen(userdate($userdata->lastaccess, '%d')) === 1 ?
                '0%d/%m/%Y %R' : '%d/%m/%Y %R';
        self::assertEquals($profile->lastconnection['display'], userdate($userdata->lastaccess, $format));
        self::assertEquals($profile->lastconnection['timestamp'], $userdata->lastaccess);

        self::resetAllData();
    }

    /**
     * Test get url
     *
     * @covers \local_mentor_core\profile::__construct
     * @covers \local_mentor_core\profile::get_url
     */
    public function test_get_url_ok() {
        global $CFG;
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $db = \local_mentor_core\database_interface::get_instance();

        $lastname = 'lastname';
        $firstname = 'firstname';
        $email = 'user@gouv.fr';
        $auth = 'manual';

        // Create user.
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, null, [], null, $auth));

        // Get user.
        $profiledata = $db->get_user_by_email('user@gouv.fr');
        $profile = new \local_mentor_core\profile($profiledata->id);

        self::assertEquals($profile->get_url(), $CFG->wwwroot . '/local/profile/pages/editadvanced.php?id=' . $profile->id);

        self::resetAllData();
    }

    /**
     * Test is cohort member
     *
     * @covers \local_mentor_core\profile::__construct
     * @covers \local_mentor_core\profile::is_cohort_member
     * @covers \local_mentor_core\profile::get_entities_cohorts
     */
    public function test_is_cohort_member_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $db = \local_mentor_core\database_interface::get_instance();

        $lastname = 'lastname';
        $firstname = 'firstname';
        $email = 'user@gouv.fr';
        $auth = 'manual';

        // Create user.
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, null, [], null, $auth));

        // Get user.
        $profiledata = $db->get_user_by_email('user@gouv.fr');
        $profile = new \local_mentor_core\profile($profiledata->id);

        // Is not cohort member.
        self::assertFalse($profile->is_cohort_member());

        $entity = \local_mentor_core\entity_api::get_entity(1);
        $entity->add_member($profile);

        // Is cohort member.
        self::assertTrue($profile->is_cohort_member());

        self::resetAllData();
    }

    /**
     * Test is suspended
     *
     * @covers \local_mentor_core\profile::__construct
     * @covers \local_mentor_core\profile::is_suspended
     */
    public function test_is_suspended_ok() {
        global $DB;
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $db = \local_mentor_core\database_interface::get_instance();

        $lastname = 'lastname';
        $firstname = 'firstname';
        $email = 'user@gouv.fr';
        $auth = 'manual';

        // Create user.
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, null, [], null, $auth));

        // Get user.
        $profiledata = $db->get_user_by_email('user@gouv.fr');
        $profile = new \local_mentor_core\profile($profiledata->id);

        // Is not suspended.
        self::assertEquals($profile->is_suspended(), '0');

        // Suspend user.
        $userdata = new stdClass();
        $userdata->id = $profile->id;
        $userdata->suspended = '1';
        $DB->update_record('user', $userdata);

        // Refresh profile data.
        $profiledata = $db->get_user_by_id($profiledata->id, true);
        $profile = new \local_mentor_core\profile($profiledata->id);

        // Is suspended.
        self::assertEquals($profile->is_suspended(), '1');

        self::resetAllData();
    }

    /**
     * Test get main entity
     *
     * @covers \local_mentor_core\profile::__construct
     * @covers \local_mentor_core\profile::get_main_entity
     */
    public function test_get_main_entity_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $db = \local_mentor_core\database_interface::get_instance();

        $lastname = 'lastname';
        $firstname = 'firstname';
        $email = 'user@gouv.fr';
        $auth = 'manual';

        // Create user.
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, null, [], null, $auth));

        // Get user.
        $profiledata = $db->get_user_by_email($email);
        $profile = new \local_mentor_core\profile($profiledata->id);

        // Not has main entity.
        self::assertFalse($profile->get_main_entity());

        $lastname2 = 'lastname2';
        $firstname2 = 'firstname2';
        $email2 = 'user2@gouv.fr';

        // Create user.
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname2, $firstname2, $email2, 1, [], null, $auth));

        // Get user.
        $profiledata2 = $db->get_user_by_email($email2);
        $profile2 = new \local_mentor_core\profile($profiledata2->id);

        // Has main entity.
        $mainentity = $profile2->get_main_entity();
        self::assertEquals($mainentity->id, 1);

        self::resetAllData();
    }

    /**
     * Test sync entities
     *
     * @covers \local_mentor_core\profile::__construct
     * @covers \local_mentor_core\profile::sync_entities
     */
    public function test_sync_entities_ok() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $db = \local_mentor_core\database_interface::get_instance();

        $lastname = 'lastname';
        $firstname = 'firstname';
        $email = 'user@gouv.fr';
        $auth = 'manual';

        // Create user.
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, null, [], null, $auth));

        // Get user.
        $profiledata = $db->get_user_by_email($email);
        $profile = new \local_mentor_core\profile($profiledata->id);

        self::assertNull($profile->sync_entities());

        $lastname2 = 'lastname2';
        $firstname2 = 'firstname2';
        $email2 = 'user2@gouv.fr';

        // Create user.
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname2, $firstname2, $email2, 1, [], null, $auth));

        // Get user.
        $profiledata2 = $db->get_user_by_email($email2);
        $profile2 = new \local_mentor_core\profile($profiledata2->id);

        self::assertTrue($profile2->sync_entities());

        self::resetAllData();
    }

    /**
     * Test can edit profile
     *
     * @covers \local_mentor_core\profile::can_edit_profile
     * @covers \local_mentor_core\profile::set_main_entity
     * @covers \local_mentor_core\profile::set_profile_field
     */
    public function test_can_edit_profile() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        $db = \local_mentor_core\database_interface::get_instance();

        $lastname = 'lastname';
        $firstname = 'firstname';
        $email = 'user@gouv.fr';
        $auth = 'manual';

        // Create user.
        self::assertTrue(\local_mentor_core\profile_api::create_and_add_user($lastname, $firstname, $email, null, [], null, $auth));

        // Get user.
        $profiledata = $db->get_user_by_email($email);
        $profile = new \local_mentor_core\profile($profiledata->id);

        self::assertFalse($profile->can_edit_profile());

        $mainentity = \local_mentor_core\entity_api::get_entity(1);
        $profile->set_main_entity($mainentity);

        self::assertEquals($profile->mainentity, $mainentity->get_name());

        self::assertTrue($profile->can_edit_profile());

        self::setUser($profiledata->id);
        self::assertTrue($profile->can_edit_profile());

        $newuser = self::getDataGenerator()->create_user();
        self::setUser($newuser);
        self::assertFalse($profile->can_edit_profile());

        self::resetAllData();
    }

    /**
     * Test set preference
     *
     * @covers \local_mentor_core\profile::set_preference
     */
    public function test_set_preference_ok() {
        global $DB;
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create Entities.
        \local_mentor_core\entity_api::create_entity([
                'name' => 'Entity',
                'shortname' => 'Entity',
        ]);

        // Create user.
        $user = new stdClass();
        $user->lastname = 'lastname1';
        $user->firstname = 'firstname1';
        $user->email = 'test1@test.com';
        $user->username = 'testusername1';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = 'Entity';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        $profile = \local_mentor_core\profile_api::get_profile($userid);

        // Preference not exist.
        self::assertFalse($DB->record_exists('user_preferences', ['userid' => $userid, 'name' => 'preferencename']));

        $profile->set_preference('preferencename', 'preferencevalue');

        // New preference.
        self::assertTrue($DB->record_exists('user_preferences', ['userid' => $userid, 'name' => 'preferencename']));
        $prefenreceuser = $DB->get_record('user_preferences', ['userid' => $userid, 'name' => 'preferencename']);
        self::assertEquals($prefenreceuser->value, 'preferencevalue');

        $profile->set_preference('preferencename', 'preferencevalue2');

        // Same preference with new value.
        self::assertTrue($DB->record_exists('user_preferences', ['userid' => $userid, 'name' => 'preferencename']));
        $prefenreceuser2 = $DB->get_record('user_preferences', ['userid' => $userid, 'name' => 'preferencename']);
        self::assertEquals($prefenreceuser->id, $prefenreceuser2->id);
        self::assertEquals($prefenreceuser2->value, 'preferencevalue2');

        $this->resetAllData();
    }

    /**
     * Test get preference
     *
     * @covers \local_mentor_core\profile::get_preference
     */
    public function test_get_preference_ok() {
        global $DB;
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create Entities.
        \local_mentor_core\entity_api::create_entity([
                'name' => 'Entity',
                'shortname' => 'Entity',
        ]);

        // Create user.
        $user = new stdClass();
        $user->lastname = 'lastname1';
        $user->firstname = 'firstname1';
        $user->email = 'test1@test.com';
        $user->username = 'testusername1';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = 'Entity';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        $profile = \local_mentor_core\profile_api::get_profile($userid);

        // Preference not exist.
        self::assertFalse($profile->get_preference('preferencename'));

        $profile->set_preference('preferencename', 'preferencevalue');

        // New preference.
        self::assertTrue($DB->record_exists('user_preferences', ['userid' => $userid, 'name' => 'preferencename']));
        $prefenreceuser = $profile->get_preference('preferencename');
        self::assertEquals($prefenreceuser, 'preferencevalue');

        $profile->set_preference('preferencename', 'preferencevalue2');

        // Same preference with new value.
        self::assertTrue($DB->record_exists('user_preferences', ['userid' => $userid, 'name' => 'preferencename']));
        $prefenreceuser2 = $profile->get_preference('preferencename');
        self::assertEquals($prefenreceuser2, 'preferencevalue2');

        $this->resetAllData();
    }

    /**
     * Test suspend
     *
     * @covers \local_mentor_core\profile::suspend
     */
    public function test_suspend() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create Entities.
        \local_mentor_core\entity_api::create_entity([
                'name' => 'Entity',
                'shortname' => 'Entity',
        ]);

        // Create user.
        $user = new stdClass();
        $user->lastname = 'lastname1';
        $user->firstname = 'firstname1';
        $user->email = 'test1@test.com';
        $user->username = 'testusername1';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = 'Entity';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        $profile = \local_mentor_core\profile_api::get_profile($userid);

        self::assertEquals(0, $profile->is_suspended());

        // Suspend user.
        self::assertTrue($profile->suspend());

        self::assertEquals(1, $profile->is_suspended());

        // Already suspend.
        self::assertFalse($profile->suspend());

        $this->resetAllData();
    }

    /**
     * Test reactivate
     *
     * @covers \local_mentor_core\profile::reactivate
     */
    public function test_reactivate() {
        $this->resetAfterTest(true);
        $this->reset_singletons();

        self::setAdminUser();

        // Create Entities.
        \local_mentor_core\entity_api::create_entity([
                'name' => 'Entity',
                'shortname' => 'Entity',
        ]);

        // Create user.
        $user = new stdClass();
        $user->lastname = 'lastname1';
        $user->firstname = 'firstname1';
        $user->email = 'test1@test.com';
        $user->username = 'testusername1';
        $user->password = 'to be generated';
        $user->mnethostid = 1;
        $user->confirmed = 1;
        $user->auth = 'manual';
        $user->profile_field_mainentity = 'Entity';

        $userid = local_mentor_core\profile_api::create_user($user);
        set_user_preference('auth_forcepasswordchange', 0, $user);

        $profile = \local_mentor_core\profile_api::get_profile($userid);

        // Already active.
        self::assertFalse($profile->reactivate());

        // Suspend user.
        self::assertTrue($profile->suspend());

        // Active user.
        self::assertTrue($profile->reactivate());

        $this->resetAllData();
    }
}
