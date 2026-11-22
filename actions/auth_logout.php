<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_post();
$_SESSION = [];
session_regenerate_id(true);
flash('success', 'You have been signed out.');
redirect();
