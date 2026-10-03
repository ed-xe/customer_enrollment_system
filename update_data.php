<?php

require_once __DIR__ . '/api_common.php';
require_once __DIR__ . '/database.php';

api_require_method('POST');
$payload = api_request_payload();
$userId = api_required_string($payload, 'user_id', 'User ID');
$firstName = api_required_string($payload, 'first_name', 'First name');
$lastName = api_required_string($payload, 'last_name', 'Last name');

try {
    $connection = database_connection();
    $statement = $connection->prepare(
        'UPDATE customerlist SET Firstname = ?, Lastname = ? WHERE Code = ?'
    );
    $statement->bind_param('sss', $firstName, $lastName, $userId);
    $statement->execute();

    if ($statement->affected_rows === 0) {
        $check = $connection->prepare('SELECT Code FROM customerlist WHERE Code = ? LIMIT 1');
        $check->bind_param('s', $userId);
        $check->execute();
        $check->store_result();

        if ($check->num_rows === 0) {
            api_respond(404, array(
                'success' => false,
                'error' => array(
                    'code' => 'not_found',
                    'message' => 'No customer was found for that user ID.'
                )
            ));
        }
    }

    api_respond(200, array(
        'success' => true,
        'message' => 'Customer updated.',
        'data' => array(
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName
        )
    ));
} catch (mysqli_sql_exception $exception) {
    api_database_error($exception);
}
