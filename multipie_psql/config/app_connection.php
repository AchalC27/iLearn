<?php
return [
    'enabled' => true,

    'host' => 'localhost',
    'port' => '5400',
    'database' => 'multipie_main_prod',
    'username' => 'postgres',
    'password' => 'Achal@27',

    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
