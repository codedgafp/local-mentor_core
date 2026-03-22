<?php

defined('MOODLE_INTERNAL') || die();

global $DB;

$DB->execute(
    "DELETE FROM {user_completion}
        WHERE id IN (
            SELECT uc.id
            FROM {user_completion} uc
            INNER JOIN (
                SELECT userid, courseid, MAX(lastupdate) as lastupdate
                FROM {user_completion} 
                GROUP BY userid, courseid
                HAVING COUNT(id) > 1
            ) lastupdated_uc ON lastupdated_uc.userid = uc.userid AND lastupdated_uc.courseid = uc.courseid
            WHERE lastupdated_uc.lastupdate != uc.lastupdate
        )"
);