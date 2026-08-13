<?php

$host = "localhost";
$port = "3306";
$dbname = "ilearn_prod";
$user = "root";
$password = "Achal@27";

try {

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

} catch (PDOException $e) {

    die(
        "Database connection failed: " .
        htmlspecialchars($e->getMessage())
    );
}