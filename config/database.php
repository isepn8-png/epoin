<?php
/**
 * Database settings for mysqli ($host, $user, $pass, $db, $port).
 * Override via .env on each server; do not commit production secrets.
 */
require_once dirname(__DIR__) . '/includes/env.php';
epoin_load_env(dirname(__DIR__));

$host = epoin_env('DB_HOST', '127.0.0.1');
$user = epoin_env('DB_USERNAME', 'root');
$pass = epoin_env('DB_PASSWORD', '');
$db   = epoin_env('DB_DATABASE', 'epoin_local');
$port = (int) epoin_env('DB_PORT', '3308');

if (!defined('APP_ENV')) {
    define('APP_ENV', epoin_env('APP_ENV', 'local'));
}
