<?php

function api_respond($statusCode, array $body)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body);
    exit;
}

function api_require_method($expectedMethod)
{
    if ($_SERVER['REQUEST_METHOD'] !== $expectedMethod) {
        header('Allow: ' . $expectedMethod);
        api_respond(405, array(
            'success' => false,
            'error' => array(
                'code' => 'method_not_allowed',
                'message' => 'This request method is not supported.'
            )
        ));
    }
}

function api_request_payload()
{
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (stripos($contentType, 'application/json') === 0) {
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            api_respond(400, array(
                'success' => false,
                'error' => array(
                    'code' => 'invalid_json',
                    'message' => 'The request body must be a valid JSON object.'
                )
            ));
        }

        return $payload;
    }

    return $_POST;
}

function api_required_string(array $payload, $field, $label, $maxLength = 255)
{
    if (!isset($payload[$field]) || !is_string($payload[$field])) {
        api_respond(400, array(
            'success' => false,
            'error' => array(
                'code' => 'invalid_field',
                'message' => $label . ' is required.'
            )
        ));
    }

    $value = trim($payload[$field]);
    if ($value === '' || preg_match_all('/./us', $value, $characters) === false) {
        api_respond(400, array(
            'success' => false,
            'error' => array(
                'code' => 'invalid_field',
                'message' => $label . ' must be a non-empty valid text value.'
            )
        ));
    }

    if (count($characters[0]) > $maxLength) {
        api_respond(400, array(
            'success' => false,
            'error' => array(
                'code' => 'invalid_field',
                'message' => $label . ' must be no longer than ' . $maxLength . ' characters.'
            )
        ));
    }

    return $value;
}

function api_database_error(mysqli_sql_exception $exception)
{
    error_log(
        'Customer enrollment database error (' . $exception->getCode() . '): ' .
        $exception->getMessage()
    );
    api_respond(500, array(
        'success' => false,
        'error' => array(
            'code' => 'database_error',
            'message' => 'The request could not be completed because of a database error.'
        )
    ));
}
