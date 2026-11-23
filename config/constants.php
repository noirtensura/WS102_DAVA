<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_ROOT', APP_ROOT . '/uploads');
const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
const DAYON_MUNICIPALITY = 'San Jose de Buenavista';
const DAYON_PROVINCE = 'Antique';
const DAYON_LOCATION = DAYON_MUNICIPALITY . ', ' . DAYON_PROVINCE;
const DAYON_PROVINCE_LABEL = 'Antique, Philippines';
const DAYON_CAMPUS = "St. Anthony's College";

(static function (): void {
    $envFile = APP_ROOT . '/.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $trimmed, 2);
        $name = trim($name);
        $value = trim($value);
        if (!preg_match('/^DAYON_[A-Z0-9_]+$/D', $name) || getenv($name) !== false) {
            continue;
        }
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }
})();

date_default_timezone_set('Asia/Manila');
