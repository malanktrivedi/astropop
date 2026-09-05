<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

    if ($connection->connect_errno) {
        error_log('ASTROPOP database connection failed: ' . $connection->connect_error);
        http_response_code(500);
        exit('A database connection error occurred.');
    }

    if (!$connection->set_charset('utf8mb4')) {
        error_log('ASTROPOP database charset setup failed: ' . $connection->error);
        http_response_code(500);
        exit('A database configuration error occurred.');
    }

    return $connection;
}
