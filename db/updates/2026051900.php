<?php
#MEN-1169 - Script to remove "utilisateurexterne" in mentorRole profile field. 

use local_mentor_core\database_interface;
use local_mentor_core\profile_api;

defined('MOODLE_INTERNAL') || die();

global $DB;
$dbinterface = database_interface::get_instance();

$batchsize = 50000;

$sqlwhere = "FROM {user} u
    INNER JOIN {user_info_data} info_data on u.id = info_data.userid
    INNER JOIN {user_info_field} info_field on info_data.fieldid = info_field.id
    WHERE info_field.shortname = :rolelabel
    AND info_data.data = :rolevalue
    AND u.confirmed = 1 AND u.deleted = 0
";

$params = ['rolelabel' => 'roleMentor', 'rolevalue' => 'utilisateurexterne'];

$totalusers = $DB->count_records_sql("SELECT COUNT(*) $sqlwhere", $params);

$pages = (int) ceil($totalusers / $batchsize);

for ($page = 0; $page < $pages; $page++) {
    $users = $DB->get_records_sql("SELECT u.id $sqlwhere", $params, $page * $batchsize, $batchsize);

    foreach ($users as $user) {
        $profile = profile_api::get_profile($user->id);
        $profile->set_highestrole_into_profile();
    }
}
