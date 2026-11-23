<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    echo json_encode(['status' => 'error', 'message' => 'Use GET or HEAD for health checks.']);
    exit;
}

$status = 'ok';
$httpCode = 200;
$dbConnected = false;
$schemaReady = false;

try {
    $pdo = connect_database();
    $dbConnected = (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
    $tables = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name IN ('users', 'properties', 'rooms', 'leases', 'landlord_permits', 'bookmarks')"
    )->fetchColumn();
    $schemaReady = $tables === 6;
    if (!$dbConnected || !$schemaReady) {
        $status = 'degraded';
        $httpCode = 503;
    }
} catch (Throwable $exception) {
    error_log('Dayon health check failure: ' . $exception->getMessage());
    $status = 'unavailable';
    $httpCode = 503;
}

http_response_code($httpCode);
echo json_encode([
    'status' => $status,
    'service' => 'dayon',
    'province' => DAYON_PROVINCE,
    'database' => $dbConnected ? 'connected' : 'unreachable',
    'schema_ready' => $schemaReady,
    'timestamp' => date(DATE_ATOM),
], JSON_UNESCAPED_SLASHES);
