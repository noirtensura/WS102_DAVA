<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_post();

$email = trim((string) filter_input(INPUT_POST, 'email', FILTER_UNSAFE_RAW));
$password = (string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
$adminMode = ($_POST['portal'] ?? '') === 'admin';
$next = $adminMode ? '' : post_login_path((string) filter_input(INPUT_POST, 'next', FILTER_UNSAFE_RAW));
$account = strlen($email) <= 150 ? row(
    'SELECT u.*, r.role_name FROM users u JOIN roles r USING (role_id) WHERE u.email = ?',
    [$email]
) : null;
$hash = $account['password_hash'] ?? '$2y$10$8m31gA2lZSYL5zUfLuWLIuXvL2KArrJv6Ammp0KrtSaPYh1e1YHLC';
if (!password_verify($password, $hash) || !$account || $account['account_status'] !== 'active'
    || ($adminMode && $account['role_name'] !== 'admin')) {
    flash('error', 'We could not sign you in with those details.');
    redirect('login.php' . ($adminMode ? '?portal=admin' : ($next ? '?next=' . rawurlencode($next) : '')));
}
session_regenerate_id(true);
unset($_SESSION['csrf']);
$_SESSION['user_id'] = (int) $account['user_id'];
$_SESSION['role_name'] = $account['role_name'];
$_SESSION['full_name'] = $account['first_name'] . ' ' . $account['last_name'];
redirect($next ?: dashboard_path($account['role_name']));
