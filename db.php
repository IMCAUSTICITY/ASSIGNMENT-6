<?php
/**
 * db.php - bootstraps the whole app: config, secure session, PDO connection,
 * helper functions and global CSRF protection for every POST request.
 */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

// Auto-detect base path so the project works in any sub-folder
if (BASE_URL_OVERRIDE !== null) {
    define('BASE_URL', rtrim(BASE_URL_OVERRIDE, '/'));
} else {
    $root = str_replace('\\', '/', realpath(__DIR__));
    $doc  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    define('BASE_URL', ($doc !== '' && strpos($root, $doc) === 0) ? rtrim(substr($root, strlen($doc)), '/') : '');
}
define('ROOT_DIR', __DIR__);

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $ex) {
    http_response_code(500);
    exit('<h2>Database connection failed</h2><p>Import <code>database.sql</code> and check <code>config.php</code>.</p>');
}

require_once __DIR__ . '/includes/functions.php';
auto_login_from_cookie();

// Global CSRF check for every POST (API endpoints define API_MODE and use tokens instead)
if (!defined('API_MODE')) {
    csrf_check();
}
