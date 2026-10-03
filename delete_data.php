<?php

require_once __DIR__ . '/api_common.php';
require_once __DIR__ . '/database.php';

api_require_method('POST');
$payload = api_request_payload();
$userId = api_required_string($payload, 'user_id', 'User ID');

try {
    $connection = database_connection();
    $statement = $connection->prepare('DELETE FROM customerlist WHERE Code = ?');
    $statement->bind_param('s', $userId);
    $statement->execute();

    if ($statement->affected_rows === 0) {
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
        'message' => 'Customer deleted.'
    ));
} catch (mysqli_sql_exception $exception) {
    api_database_error($exception);
}
