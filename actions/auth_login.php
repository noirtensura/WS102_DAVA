<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_post();

$email = trim((string) filter_input(INPUT_POST, 'email', FILTER_UNSAFE_RAW));
$password = (string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
$adminMode = ($_POST['portal'] ?? '') === 'admin';
$next = $adminMode ? '' : post_login_path((string) filter_input(INPUT_POST, 'next', FILTER_UNSAFE_RAW));
$loginRedirect = 'login.php' . ($adminMode ? '?portal=admin' : ($next ? '?next=' . rawurlencode($next) : ''));

// Abuse-aware session throttle (max 5 failed attempts within a 60-second window)
$now = time();
$throttle = $_SESSION['login_throttle'] ?? ['count' => 0, 'window_start' => $now, 'locked_until' => 0];
if (($throttle['locked_until'] ?? 0) > $now) {
    $wait = (int) $throttle['locked_until'] - $now;
    flash('error', "Too many failed sign-in attempts. Please wait {$wait} seconds and try again.");
    redirect($loginRedirect);
}
if ($now - ($throttle['window_start'] ?? 0) > 60) {
    $throttle = ['count' => 0, 'window_start' => $now, 'locked_until' => 0];
}

$account = strlen($email) <= 150 ? row(
    'SELECT u.*, r.role_name FROM users u JOIN roles r USING (role_id) WHERE u.email = ?',
    [$email]
) : null;
$hash = $account['password_hash'] ?? '$2y$10$8m31gA2lZSYL5zUfLuWLIuXvL2KArrJv6Ammp0KrtSaPYh1e1YHLC';
if (!password_verify($password, $hash) || !$account || $account['account_status'] !== 'active'
    || ($adminMode && $account['role_name'] !== 'admin')) {
    $throttle['count'] = (int) ($throttle['count'] ?? 0) + 1;
    if ($throttle['count'] >= 5) {
        $throttle['locked_until'] = $now + 60;
    }
    $_SESSION['login_throttle'] = $throttle;
    flash('error', 'We could not sign you in with those details.');
    redirect($loginRedirect);
}

session_regenerate_id(true);
unset($_SESSION['csrf'], $_SESSION['login_throttle']);
$_SESSION['user_id'] = (int) $account['user_id'];
$_SESSION['role_name'] = $account['role_name'];
$_SESSION['full_name'] = $account['first_name'] . ' ' . $account['last_name'];
redirect($next ?: dashboard_path($account['role_name']));
