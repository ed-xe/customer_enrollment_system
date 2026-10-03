<?php

require_once __DIR__ . '/api_common.php';
require_once __DIR__ . '/database.php';

api_require_method('GET');

$queryKeys = array('page', 'per_page', 'search', 'sort', 'direction');
foreach ($queryKeys as $queryKey) {
    if (isset($_GET[$queryKey]) && !is_string($_GET[$queryKey])) {
        api_respond(400, array(
            'success' => false,
            'error' => array(
                'code' => 'invalid_query',
                'message' => 'The list filters or pagination values are invalid.'
            )
        ));
    }
}

$requestedPage = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
$perPage = isset($_GET['per_page']) ? filter_var($_GET['per_page'], FILTER_VALIDATE_INT) : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'user_id';
$direction = isset($_GET['direction']) ? strtolower($_GET['direction']) : 'asc';
$sortColumns = array(
    'user_id' => 'Code',
    'first_name' => 'Firstname',
    'last_name' => 'Lastname'
);
$searchCharacters = array();

if ($requestedPage === false || $requestedPage < 1 ||
    $perPage === false || $perPage < 1 || $perPage > 50 ||
    !isset($sortColumns[$sort]) ||
    !in_array($direction, array('asc', 'desc'), true) ||
    preg_match_all('/./us', $search, $searchCharacters) === false ||
    count($searchCharacters[0]) > 255) {
    api_respond(400, array(
        'success' => false,
        'error' => array(
            'code' => 'invalid_query',
            'message' => 'The list filters or pagination values are invalid.'
        )
    ));
}

try {
    $connection = database_connection();
    if ($search === '') {
        $count = $connection->prepare('SELECT COUNT(*) FROM customerlist');
        $count->execute();
    } else {
        $count = $connection->prepare(
            'SELECT COUNT(*) FROM customerlist ' .
            'WHERE Code LIKE ? OR Firstname LIKE ? OR Lastname LIKE ?'
        );
        $searchPattern = '%' . $search . '%';
        $count->bind_param('sss', $searchPattern, $searchPattern, $searchPattern);
        $count->execute();
    }
    $count->bind_result($total);
    $count->fetch();
    $count->close();

    $totalPages = (int) ceil($total / $perPage);
    $page = min($requestedPage, max(1, $totalPages));
    $offset = ($page - 1) * $perPage;
    $sql = 'SELECT Code, Firstname, Lastname FROM customerlist';
    if ($search !== '') {
        $sql .= ' WHERE Code LIKE ? OR Firstname LIKE ? OR Lastname LIKE ?';
    }
    $sql .= ' ORDER BY ' . $sortColumns[$sort] . ' ' . strtoupper($direction);
    $sql .= ' LIMIT ? OFFSET ?';
    $statement = $connection->prepare($sql);

    if ($search === '') {
        $statement->bind_param('ii', $perPage, $offset);
    } else {
        $statement->bind_param(
            'sssii',
            $searchPattern,
            $searchPattern,
            $searchPattern,
            $perPage,
            $offset
        );
    }
    $statement->execute();
    $statement->bind_result($userId, $firstName, $lastName);
    $customers = array();

    while ($statement->fetch()) {
        $customers[] = array(
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName
        );
    }

    api_respond(200, array(
        'success' => true,
        'data' => array(
            'customers' => $customers,
            'pagination' => array(
                'page' => $page,
                'per_page' => (int) $perPage,
                'total' => (int) $total,
                'total_pages' => $totalPages
            )
        )
    ));
} catch (mysqli_sql_exception $exception) {
    api_database_error($exception);
}
