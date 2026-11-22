<?php
declare(strict_types=1);

function require_role(string|array $roles): array
{
    global $user;
    if (!$user) {
        flash('info', 'Sign in to continue.');
        redirect((array) $roles === ['admin'] ? 'login.php?portal=admin' : 'login.php');
    }
    if (!in_array($user['role_name'], (array) $roles, true)) {
        abort_request(403, 'Your account does not have access to this page.');
    }
    return $user;
}
