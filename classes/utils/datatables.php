<?php
require_once "$CFG->dirroot/local/user/classes/utils/datatablescolumns.php";
defined('MOODLE_INTERNAL') || die();

/**
 * $tablecolumns must contains all the columns names, that can be order in the table
 * 
 * example of the array :
 * [
 *   0 => u.lastname,
 *   1 => u.firstname,
 *   ...
 * ]
 * 
 * if there is a default sort column, add a default key to the array :
 * [
 *   0 => u.lastname,
 *   1 => u.firstname,
 *   ...
 *   'default' => u.lastname
 * ]
 */

/**
 * Create an order request for a datatables query
 * 
 * @param array|null $orderarray
 * @param array $tablecolumns
 * @return string
 */
function order_cohort_members(array|null $orderarray): string
{
    $datatablescolumns = new local_user\datatablescolumns();
    $tablecolumns = $datatablescolumns::local_user_colums;
    $orderby = isset($tablecolumns['default']) ? "ORDER BY " . $tablecolumns['default'] . " ASC" : "";

    if ($orderarray !== null) {
        $orderargument = implode(', ', array_map(fn($order): string => $tablecolumns[$order['column']] . " " . $order['dir'], $orderarray));
        $orderby = "ORDER BY $orderargument";
    }

    return $orderby;
}