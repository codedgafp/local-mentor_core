<?php

/**
 * Get a user depending on the email sent. Not case sensitive.
 * 
 * @param string $email
 * @param string $field A comma separated list of fields to be returned from the chosen table
 * 
 * @return bool|\stdClass
 */
function get_user_by_email(string $email, string $fields = '*'): bool|stdClass {
    global $DB;

    // prepare the parameters for the record
    $requestData = construct_user_by_email_request($email);

    return $DB->get_record_select('user', $requestData["select"], $requestData["params"], $fields);
}

/**
 * Get a list of users depending on the email sent. Not case sensitive.
 * 
 * @param string $email
 * @param string $sort
 * @param string $fields A comma separated list of fields to be returned from the chosen table
 * 
 * @return array
 */
function get_users_by_email(string $email, string $sort = '', string $fields = '*'): array {
    global $DB;

    // prepare the parameters for the record
    $requestData = construct_user_by_email_request($email);

    return $DB->get_records_select('user', $requestData["select"], $requestData["params"], $sort, $fields);
}

/**
 * Get a suspended user depending on the email sent. Not case sensitive.
 * 
 * @param string $email
 * @param string $fields A comma separated list of fields to be returned from the chosen table
 * 
 * @return bool|\stdClass
 */
function get_suspended_user_by_email(string $email, string $fields = '*'): bool|stdClass {
    global $DB;

    $requestData = construct_user_by_email_request($email, " AND suspended = 1");

    return $DB->get_record_select('user', $requestData["select"], $requestData["params"], $fields);
}

/**
 * Check if one or many users have the same email than the given one
 * 
 * @param string $email
 * 
 * @return bool
 */
function check_users_by_email(string $email): bool {
    global $DB;

    $requestData = construct_user_by_email_request($email);

    return $DB->record_exists_select('user', $requestData["select"], $requestData["params"]);
}

/**
 * Prepare and construct the record parameters.
 * 
 * @param string $email
 * @param string $addSelectParameters If you want to add some WHERE instructions
 * 
 * @return array
 */
function construct_user_by_email_request(string $email, string $addSelectParameters = ''): array {
    global $DB;

    // compose the LIKE part of the query with the email parameter
    $select = $DB->sql_like('email', ':email', false, true, false, '|');
    if ($addSelectParameters) {
        $select .= $addSelectParameters;
    }

    $params = ['email' => $DB->sql_like_escape($email, '|')];

    return [
        "select" => $select,
        "params" => $params,
    ];
}