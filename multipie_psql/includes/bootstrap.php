<?php
/*
|--------------------------------------------------------------------------
| MultiPie PostgreSQL Bootstrap
|--------------------------------------------------------------------------
|
| There are two PostgreSQL databases:
|
|   multipie_auth_prod -> public.users
|   multipie_main_prod -> application tables
|
| This file contains only shared helpers and DB connection functions.
| No dummy JSON data is loaded anywhere in the application.
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function getUsersDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config/users_connection.php';

    if (empty($config['enabled'])) {
        throw new RuntimeException(
            'Users PostgreSQL connection is disabled. Configure multipie_psql/config/users_connection.php.'
        );
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $config['host'],
        $config['port'],
        $config['database']
    );

    $options = $config['options'] ?? [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        $options
    );

    return $pdo;
}

function getAppDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config/app_connection.php';

    if (empty($config['enabled'])) {
        throw new RuntimeException(
            'Application PostgreSQL connection is disabled. Configure multipie_psql/config/app_connection.php.'
        );
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $config['host'],
        $config['port'],
        $config['database']
    );

    $options = $config['options'] ?? [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        $options
    );

    return $pdo;
}
