<?php

require_once __DIR__ . '/api_common.php';
require_once __DIR__ . '/database.php';

api_require_method('GET');
$userId = api_required_string($_GET, 'user_id', 'User ID');

try {
    $connection = database_connection();
    $statement = $connection->prepare(
        'SELECT Firstname, Lastname FROM customerlist WHERE Code = ? LIMIT 1'
    );
    $statement->bind_param('s', $userId);
    $statement->execute();
    $statement->bind_result($firstName, $lastName);

    if (!$statement->fetch()) {
        api_respond(404, array(
            'success' => false,
            'error' => array(
                'code' => 'not_found',
                'message' => 'No customer was found for that user ID.'
            )
        ));
    }

    api_respond(200, array(
        'success' => true,
        'data' => array(
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName
        )
    ));
} catch (mysqli_sql_exception $exception) {
    api_database_error($exception);
}
