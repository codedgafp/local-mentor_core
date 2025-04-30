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

/**
 * Prepare and construct the record parameters for multiple emails.
 *
 * @param array $emails email list.
 * @param string $addSelectParameters (Optionnal) To add some conditions (ex: AND ...).
 *
 * @return array
 */
function construct_user_by_emails_request(array $emails, string $addSelectParameters = ''): array {
    global $DB;

    if (empty($emails)) {
        throw new InvalidArgumentException("Le tableau des emails ne peut pas être vide.");
    }

   
    $params = [];
    $placeholders = [];

    foreach ($emails as $index => $email) {
        $paramName = "email$index";
        $placeholders[] = ":$paramName";
        $params[$paramName] = $email; 
    }

    $select = 'email IN (' . implode(', ', $placeholders) . ')';

    if ($addSelectParameters) {
        $select .= ' ' . $addSelectParameters;
    }

    return [
        "select" => $select,
        "params" => $params,
    ];
}

/**
 * Get a list of users depending on emails sent. Not case sensitive.
 * 
 * @param []string $emails
 * @param string $sort
 * @param string $fields A comma separated list of fields to be returned from the chosen table
 * 
 * @return array
 */
function get_users_by_emails(array $emails, string $sort = '', string $fields = '*'): array {
    global $DB;

    // prepare the parameters for the record
    $requestData = construct_user_by_emails_request($emails);

    return $DB->get_records_select('user', $requestData["select"], $requestData["params"], $sort, $fields);
}


/**
 * Get a list of users depending on usernames
 * 
 * @param array $usernames
 * @param string $sort
 * @param string $fields A comma separated list of fields to be returned from the chosen table
 * 
 * @return array
 */
function construct_user_by_usernames_request(array $usernames): array {

    if (empty($usernames)) {
        throw new InvalidArgumentException("The array of usernames cannot be empty.");
    }

    $params = [];

    $usernamePlaceholders = [];
    $emailPlaceholders = [];

    foreach ($usernames as $index => $value) {
        $usernameParamName = "username$index";
        $emailParamName = "email$index";

        $usernamePlaceholders[] = ":$usernameParamName";
        $emailPlaceholders[] = ":$emailParamName";

        $params[$usernameParamName] = $value;
        $params[$emailParamName] = $value;
    }

    $select = '(username IN (' . implode(', ', $usernamePlaceholders) . ') OR email IN (' . implode(', ', $emailPlaceholders) . '))';

    return [
        "select" => $select,
        "params" => $params,
    ];}

/**
 * Get a list of users depending on emails sent . Not case sensitive.
 * 
 * @param []string $emails
 * @param string $sort
 * @param string $fields A comma separated list of fields to be returned from the chosen table
 * 
 * @return array
 */
function get_users_by_usernames(array $usernames, string $fields = '*'): array {
    global $DB;

    $requestData = construct_user_by_usernames_request($usernames);
    return $DB->get_records_select('user', $requestData["select"], $requestData["params"], '', $fields);
}
