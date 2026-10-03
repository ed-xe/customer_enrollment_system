<?php

$baseUrl = getenv('API_BASE_URL');
if ($baseUrl === false || trim($baseUrl) === '') {
    fwrite(STDERR, "Set API_BASE_URL to the running application's base URL.\n");
    exit(1);
}

$baseUrl = rtrim($baseUrl, '/') . '/';
$userId = 'smoke-' . str_replace('.', '', uniqid('', true));
$created = false;

function api_call($url, $method, ?array $payload = null)
{
    $headers = "Accept: application/json\r\n";
    $content = '';
    if ($payload !== null) {
        $headers .= "Content-Type: application/json\r\n";
        $content = json_encode($payload);
    }

    $context = stream_context_create(array(
        'http' => array(
            'method' => $method,
            'header' => $headers,
            'content' => $content,
            'ignore_errors' => true
        )
    ));
    $body = file_get_contents($url, false, $context);
    if ($body === false || !isset($http_response_header[0])) {
        throw new RuntimeException('Could not reach the API at ' . $url);
    }

    preg_match('/\s([0-9]{3})\s/', $http_response_header[0], $matches);
    if (!isset($matches[1])) {
        throw new RuntimeException('The API returned an invalid HTTP status line.');
    }

    $response = json_decode($body, true);
    if (!is_array($response)) {
        throw new RuntimeException('The API returned invalid JSON.');
    }

    return array((int) $matches[1], $response);
}

function assert_response($actualStatus, array $body, $expectedStatus, $message)
{
    if ($actualStatus !== $expectedStatus || !isset($body['success']) ||
        $body['success'] !== ($expectedStatus < 400)) {
        throw new RuntimeException(
            $message . ' (HTTP ' . $actualStatus . ', expected ' . $expectedStatus . ').'
        );
    }
}

try {
    list($status, $body) = api_call($baseUrl . 'insert_data.php', 'GET');
    assert_response($status, $body, 405, 'Reject unsupported method');

    list($status, $body) = api_call(
        $baseUrl . 'insert_data.php',
        'POST',
        array('user_id' => $userId)
    );
    assert_response($status, $body, 400, 'Reject incomplete customer');

    $customer = array(
        'user_id' => $userId,
        'first_name' => 'Smoke',
        'last_name' => 'Test'
    );
    list($status, $body) = api_call($baseUrl . 'insert_data.php', 'POST', $customer);
    assert_response($status, $body, 201, 'Create customer');
    $created = true;

    list($status, $body) = api_call(
        $baseUrl . 'list_data.php?search=' . rawurlencode($userId),
        'GET'
    );
    assert_response($status, $body, 200, 'List customer');
    if (count($body['data']['customers']) !== 1 ||
        $body['data']['customers'][0]['user_id'] !== $userId) {
        throw new RuntimeException('Customer list returned unexpected data.');
    }

    list($status, $body) = api_call(
        $baseUrl . 'get_data.php?user_id=' . rawurlencode($userId),
        'GET'
    );
    assert_response($status, $body, 200, 'Find customer');
    if ($body['data']['first_name'] !== 'Smoke' || $body['data']['last_name'] !== 'Test') {
        throw new RuntimeException('Lookup returned unexpected customer data.');
    }

    list($status, $body) = api_call($baseUrl . 'insert_data.php', 'POST', $customer);
    assert_response($status, $body, 409, 'Reject duplicate customer');

    $customer['first_name'] = 'Updated';
    list($status, $body) = api_call($baseUrl . 'update_data.php', 'POST', $customer);
    assert_response($status, $body, 200, 'Update customer');

    list($status, $body) = api_call(
        $baseUrl . 'get_data.php?user_id=' . rawurlencode($userId),
        'GET'
    );
    assert_response($status, $body, 200, 'Find updated customer');
    if ($body['data']['first_name'] !== 'Updated') {
        throw new RuntimeException('The update was not reflected by lookup.');
    }

    list($status, $body) = api_call(
        $baseUrl . 'delete_data.php',
        'POST',
        array('user_id' => $userId)
    );
    assert_response($status, $body, 200, 'Delete customer');
    $created = false;

    list($status, $body) = api_call(
        $baseUrl . 'get_data.php?user_id=' . rawurlencode($userId),
        'GET'
    );
    assert_response($status, $body, 404, 'Return not found after deletion');

    list($status, $body) = api_call(
        $baseUrl . 'update_data.php',
        'POST',
        array(
            'user_id' => $userId,
            'first_name' => 'Missing',
            'last_name' => 'Customer'
        )
    );
    assert_response($status, $body, 404, 'Return not found when updating missing customer');

    list($status, $body) = api_call(
        $baseUrl . 'delete_data.php',
        'POST',
        array('user_id' => $userId)
    );
    assert_response($status, $body, 404, 'Return not found when deleting missing customer');

    fwrite(STDOUT, "CRUD API smoke test passed.\n");
} finally {
    if ($created) {
        api_call(
            $baseUrl . 'delete_data.php',
            'POST',
            array('user_id' => $userId)
        );
    }
}
