<?php

function database_connection()
{
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT');
    $database = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASSWORD');

    if ($host === false) {
        $host = '127.0.0.1';
    }
    if ($port === false || $port === '') {
        $port = '3306';
    }
    if ($database === false) {
        $database = 'serverdata';
    }

    $numericPort = filter_var($port, FILTER_VALIDATE_INT);
    if ($host === '' || $database === '' ||
        $username === false || $username === '' ||
        $password === false ||
        $numericPort === false || $numericPort < 1 || $numericPort > 65535) {
        error_log('Customer enrollment database configuration is incomplete or invalid.');
        api_respond(500, array(
            'success' => false,
            'error' => array(
                'code' => 'configuration_error',
                'message' => 'Database configuration is incomplete on the server.'
            )
        ));
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli($host, $username, $password, $database, $numericPort);
    $connection->set_charset('utf8mb4');

    return $connection;
}
