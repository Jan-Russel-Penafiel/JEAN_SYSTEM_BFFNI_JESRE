<?php
$host = (string)config('database.host', '127.0.0.1');
$dbname = (string)config('database.name', 'jz_sisters_opc');
$username = (string)config('database.username', 'root');
$password = (string)config('database.password', '');
$charset = (string)config('database.charset', 'utf8mb4');

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
