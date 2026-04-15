<?php

return [
    'app' => [
        'name' => 'JZ Sisters Trading OPC Management System',
        // Set this to your project path, for example: /client1
        'base_url' => getenv('APP_URL') ?: '/client1',
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Manila',
    ],
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'jz_sisters_opc',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
];
