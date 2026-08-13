<?php

/*
|--------------------------------------------------------------------------
| MultiPie Authentication PostgreSQL Database
|--------------------------------------------------------------------------
| Database: multipie_auth_prod
| Table:   public.users
|
| Replace the values below with the corporate PostgreSQL server details.
|--------------------------------------------------------------------------
*/

return [
    'enabled' => true,

    'host' => 'localhost',
    'port' => '5400',
    'database' => 'multipie_auth_prod',
    'username' => 'postgres',
    'password' => 'Achal@27',

    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
