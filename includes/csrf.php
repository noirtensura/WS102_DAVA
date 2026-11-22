<?php
declare(strict_types=1);

function csrf_field(): string
{
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    return '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf']) . '">';
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        abort_request(405, 'This action requires a form submission.');
    }
    $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($submitted) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $submitted)) {
        abort_request(403, 'This form has expired. Reload the page and try again.');
    }
}
