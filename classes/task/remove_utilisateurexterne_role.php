<?php
// #MEN-1169 - Remove "utilisateurexterne" in mentorRole profile field.

namespace local_mentor_core\task;

use local_mentor_core\profile_api;

class remove_utilisateurexterne_role extends \core\task\adhoc_task
{

    public function execute()
    {
        global $DB;

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
        mtrace("remove_utilisateurexterne_role: $totalusers utilisateurs à traiter");

        $pages = (int) ceil($totalusers / $batchsize);

        for ($page = 0; $page < $pages; $page++) {
            $users = $DB->get_records_sql("SELECT u.id $sqlwhere", $params, $page * $batchsize, $batchsize);

            foreach ($users as $user) {
                $profile = profile_api::get_profile($user->id);
                $profile->set_highestrole_into_profile();
            }

            mtrace("remove_utilisateurexterne_role: page " . ($page + 1) . "/$pages traitée");
        }

        mtrace("remove_utilisateurexterne_role: terminé");
    }
}
