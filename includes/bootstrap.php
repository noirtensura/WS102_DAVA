<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth_guard.php';

ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(), payment=(), geolocation=(self)');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; frame-src https://www.openstreetmap.org; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

ini_set('session.use_strict_mode', '1');
session_name('dayon_session');
session_set_cookie_params([
    'path' => url(),
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

try {
    $pdo = connect_database();
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $user = row(
            "SELECT u.user_id, u.email, u.first_name, u.last_name, u.phone_number, u.account_status,
                    r.role_name, CONCAT(u.first_name, ' ', u.last_name) AS full_name
             FROM users u JOIN roles r USING (role_id)
             WHERE u.user_id = ? AND u.account_status = 'active'",
            [$_SESSION['user_id']]
        );
        if (!$user) {
            unset($_SESSION['user_id'], $_SESSION['role_name'], $_SESSION['full_name']);
            session_regenerate_id(true);
            flash('error', 'Your account is currently unavailable.');
        }
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    abort_request(503, 'Dayon could not connect to the database. Check that MySQL is running and import database/dayon_db.sql.');
}
