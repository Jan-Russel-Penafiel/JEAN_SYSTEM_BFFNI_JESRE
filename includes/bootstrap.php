<?php
$configPath = dirname(__DIR__) . '/config.php';
if (!file_exists($configPath)) {
	die('Missing config.php.');
}

$loadedConfig = require $configPath;
if (!is_array($loadedConfig)) {
	die('Invalid config.php format.');
}

$GLOBALS['app_config'] = $loadedConfig;

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

ensure_department_accounts($pdo);

date_default_timezone_set((string)config('app.timezone', 'Asia/Manila'));
