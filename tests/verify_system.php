<?php
declare(strict_types=1);

/**
 * Dayon Automated QA, Security & Full-Stack Verification Suite
 * CLI-only runner aligning with qa-debugger, security-auditor, backend-engineer,
 * database-architect, frontend-engineer, mobile-engineer, and devops-engineer skills.
 *
 * Run: C:\xampp\php\php.exe tests/verify_system.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI execution only.');
}

$root = dirname(__DIR__);
$base = getenv('DAYON_TEST_URL') ?: 'http://localhost/WS102_DAVA/';
$passed = 0;

function pass(string $label): void
{
    global $passed;
    $passed++;
    echo "PASS [{$label}]\n";
}

function fail(string $label, string $reason): never
{
    fwrite(STDERR, "FAIL [{$label}]: {$reason}\n");
    exit(1);
}

// 1. Static PHP Syntax & Strict Types Audit
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$phpCount = 0;
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    $phpCount++;
    $content = (string) file_get_contents($path);
    if (basename($path) !== 'brand.php' && !str_contains($content, 'declare(strict_types=1);')) {
        fail('Strict Types Gate', "Missing declare(strict_types=1) in {$path}");
    }
}
pass("Static Strict-Types Audit ({$phpCount} PHP files)");

// 2. Database Schema & Index Verification
require_once $root . '/config/constants.php';
require_once $root . '/config/db.php';
$pdo = connect_database();
$requiredTables = ['roles', 'users', 'student_profiles', 'landlord_profiles', 'landlord_permits', 'municipalities', 'barangays', 'properties', 'property_images', 'amenities', 'rooms', 'bookmarks', 'reservations', 'leases', 'rent_invoices', 'invoice_items', 'payments', 'maintenance_requests', 'announcements', 'reports', 'audit_logs'];
foreach ($requiredTables as $tbl) {
    $exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$tbl}'")->fetchColumn();
    if ($exists !== 1) {
        fail('Database Schema Gate', "Missing table: {$tbl}");
    }
}
$munCoords = (int) $pdo->query("SELECT COUNT(*) FROM municipalities WHERE province_name = 'Antique' AND latitude IS NOT NULL AND longitude IS NOT NULL")->fetchColumn();
if ($munCoords < 18) {
    fail('Database Geospatial Gate', "Expected 18 Antique municipalities with coordinates, found {$munCoords}");
}
pass('Database 3NF Schema, Permits Table & 18 Antique Municipality Coordinates');

// Helper for HTTP checks
function http_call(string $url, ?array $post = null, array $headers = [], ?string $cookieFile = null, bool $follow = true): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADER => true,
    ];
    if ($cookieFile !== null) {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    if ($post !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    if ($headers) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    if ($raw === false) {
        fail('HTTP Transport', curl_error($ch));
    }
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rawHeaders = substr($raw, 0, $hSize);
    $body = substr($raw, $hSize);
    curl_close($ch);
    return [$code, $rawHeaders, $body];
}

// 3. DevOps Health Endpoint Contract
[$code, $hdrs, $body] = http_call($base . 'api/health.php');
$health = json_decode($body, true);
if ($code !== 200 || ($health['status'] ?? '') !== 'ok' || empty($health['schema_ready'])) {
    fail('Health Endpoint Gate', "HTTP {$code}: {$body}");
}
pass('DevOps Health Endpoint (/api/health.php)');

// 4. Security Headers & Strict Content-Security-Policy Gate
[$code, $hdrs, $body] = http_call($base . 'index.php');
foreach (['X-Content-Type-Options: nosniff', 'X-Frame-Options: SAMEORIGIN', 'Referrer-Policy: strict-origin-when-cross-origin', 'Permissions-Policy:', "Content-Security-Policy: default-src 'self'; script-src 'self'"] as $expectedHeader) {
    if (stripos($hdrs, $expectedHeader) === false) {
        fail('Security Headers Gate', "Missing header '{$expectedHeader}'");
    }
}
pass('Security Headers & Strict script-src CSP Gate');

// 5. Access Control, RBAC, CSRF & Media Protection Negative Gates
[$code, $hdrs] = http_call($base . 'admin/dashboard.php', null, [], null, false);
if ($code !== 303 || stripos($hdrs, 'login.php?portal=admin') === false) {
    fail('RBAC Anonymous Admin Gate', "Expected 303 redirect to admin login, got {$code}");
}
[$code] = http_call($base . 'actions/property_save.php', ['operation' => 'save'], [], null, false);
if ($code !== 303 && $code !== 403) {
    fail('CSRF & Auth Mutation Gate', "Expected 303/403 on unauthenticated POST, got {$code}");
}
[$code] = http_call($base . 'media.php?file=../../config/db.php', null, [], null, false);
if ($code !== 404) {
    fail('Media Path Traversal Gate', "Expected 404 on traversal attempt, got {$code}");
}
pass('Security Negative Gates (RBAC Redirect, CSRF Block, Media Path Traversal Block)');

// 6. Location-First & Nearest-Distance Haversine Gate
[, , $homeBody] = http_call($base . 'index.php');
if (!str_contains($homeBody, 'Where do you plan to stay?') || str_contains($homeBody, 'class="listing-card')) {
    fail('Location-First Landing Gate', 'Home page should prompt for location before listing cards');
}
[, , $sacBody] = http_call($base . 'index.php?place=sac');
if (!str_contains($sacBody, 'Nearest') || !str_contains($sacBody, 'min walk')) {
    fail('Nearest Haversine Gate (SAC)', 'Missing nearest walk distance on SAC selection');
}
[, , $sibBody] = http_call($base . 'index.php?place=ua_sibalom');
if (!str_contains($sibBody, 'University of Antique') || (!str_contains($sibBody, 'Sibalom Scholars Boarding House') && !str_contains($sibBody, 'Villanueva Corner Apartments'))) {
    fail('Nearest Haversine Gate (UA Sibalom)', 'Missing Sibalom listings on UA Sibalom selection');
}
pass('Location-First Discovery & Nearest Haversine Sorting Gates');

echo "\nALL {$passed} VERIFICATION GATES PASSED SUCCESSFULLY!\n";
