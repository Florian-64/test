<?php
/**
 * config.php — Database connection (PDO) & session bootstrap
 */

// Start session on every page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Database credentials (edit for your environment) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'tai_etu_florian_signarbieux');
define('DB_USER', 'tai_etu_florian_signarbieux');
define('DB_PASS', 'ZRXVJDDA5A');          // WampServer default
define('DB_CHARSET', 'utf8mb4');

// ---- PDO singleton ----
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

// ---- Base URL helper ----
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'));

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}
