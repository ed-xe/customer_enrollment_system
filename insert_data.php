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
        'INSERT INTO customerlist (Code, Firstname, Lastname) VALUES (?, ?, ?)'
    );
    $statement->bind_param('sss', $userId, $firstName, $lastName);
    $statement->execute();

    api_respond(201, array(
        'success' => true,
        'message' => 'Customer enrolled.',
        'data' => array(
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName
        )
    ));
} catch (mysqli_sql_exception $exception) {
    if ((int) $exception->getCode() === 1062) {
        api_respond(409, array(
            'success' => false,
            'error' => array(
                'code' => 'duplicate_user_id',
                'message' => 'A customer with that user ID already exists.'
            )
        ));
    }

    api_database_error($exception);
}
