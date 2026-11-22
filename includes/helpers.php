<?php
declare(strict_types=1);

const PROPERTY_TYPES = [
    'boarding_house' => 'Boarding House',
    'apartment' => 'Apartment',
    'dormitory' => 'Dormitory',
    'bedspace' => 'Bedspace',
];

const ANTIQUE_PERMIT_TYPES = [
    'barangay_clearance' => ['Barangay Clearance / Permit', 'Barangay Hall (Punong Barangay)'],
    'mayors_business_permit' => ["Mayor's / Business Permit (BPLO)", 'Municipal Business Permits & Licensing Office'],
    'bfp_fsic' => ['Fire Safety Certificate (FSIC)', 'Bureau of Fire Protection – Antique'],
    'sanitary_permit' => ['Municipal Sanitary Permit', 'Municipal Health Office (MHO)'],
    'building_occupancy_permit' => ['Building / Occupancy Permit', 'Office of the Building Official (OBO)'],
    'dti_registration' => ['DTI Business Name Registration', 'DTI Antique Provincial Office'],
    'government_id' => ['Valid Government-Issued ID', 'Government Issuing Agency'],
    'other_local_permit' => ['Other Local Antique Permit', 'Local Issuing Office in Antique'],
];

const WATER_BILLING_LABELS = [
    'included' => 'Included in rent',
    'submetered' => 'Sub-metered',
    'shared' => 'Shared / split bill',
    'fixed' => 'Fixed monthly fee',
];

const ELECTRICITY_BILLING_LABELS = [
    'included' => 'Included in rent',
    'submetered' => 'Sub-metered',
    'shared' => 'Shared / split bill',
    'own_meter' => 'Own ANTECO meter',
];

const WIFI_POLICY_LABELS = [
    'free' => 'Free Wi-Fi included',
    'paid' => 'Wi-Fi available (with fee)',
    'tenant_own' => 'Fiber-ready (tenant setup)',
    'none' => 'No Wi-Fi',
];

const FURNISHING_LABELS = [
    'unfurnished' => 'Unfurnished',
    'semi_furnished' => 'Semi-furnished',
    'fully_furnished' => 'Fully furnished',
];

const PAYMENT_METHOD_OPTIONS = ['Cash', 'GCash', 'Maya', 'Bank Transfer'];

const ANTIQUE_SCHOOL_PRESETS = [
    "St. Anthony's College (San Jose)",
    'University of Antique – Main Campus (Sibalom)',
    'University of Antique – Hamtic Campus',
    'University of Antique – Tario-Lim Campus (Tibiao)',
    'Advance Central College (San Jose)',
    'Antique National School (San Jose)',
];

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $configured = getenv('DAYON_BASE_PATH');
        $root = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? APP_ROOT));
        $app = str_replace('\\', '/', APP_ROOT);
        $prefix = rtrim($root, '/') . '/';
        $base = $configured !== false
            ? '/' . trim($configured, '/')
            : (str_starts_with(strtolower($app), strtolower($prefix)) ? '/' . substr($app, strlen($prefix)) : '');
        $base = rtrim($base, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path = ''): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function abort_request(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Dayon</title><link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '">';
    echo '<main class="container error-page"><h1>' . e($status) . '</h1><p>' . e($message) . '</p>';
    echo '<a class="button" href="' . e(url()) . '">Back to Dayon</a></main></html>';
    exit;
}

function query(string $sql, array $params = []): PDOStatement
{
    global $pdo;
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function row(string $sql, array $params = []): ?array
{
    $result = query($sql, $params)->fetch();
    return $result === false ? null : $result;
}

function rows(string $sql, array $params = []): array
{
    return query($sql, $params)->fetchAll();
}

function scalar(string $sql, array $params = []): mixed
{
    return query($sql, $params)->fetchColumn();
}

function money(mixed $value): string
{
    return '₱' . number_format((float) $value, 2);
}

function display_price(mixed $value): string
{
    $amount = (float) $value;
    return '₱' . number_format($amount, floor($amount) === $amount ? 0 : 2);
}

function property_type_label(?string $type): string
{
    return PROPERTY_TYPES[$type ?? 'boarding_house'] ?? 'Boarding House';
}

function permit_label(array $permit): string
{
    $type = (string) ($permit['permit_type'] ?? 'other_local_permit');
    if ($type === 'other_local_permit' && !empty($permit['custom_permit_name'])) {
        return (string) $permit['custom_permit_name'];
    }
    return ANTIQUE_PERMIT_TYPES[$type][0] ?? ucwords(str_replace('_', ' ', $type));
}

function parse_custom_details(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }
    try {
        $decoded = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return [];
    }
    if (!is_array($decoded)) {
        return [];
    }
    $clean = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string) ($item['label'] ?? ''));
        $value = trim((string) ($item['value'] ?? ''));
        if ($label !== '' && $value !== '') {
            $clean[] = ['label' => $label, 'value' => $value];
        }
    }
    return $clean;
}

function property_verified_permits(int $propertyId, int $landlordId, bool $includeAllStatuses = false): array
{
    $statusClause = $includeAllStatuses ? '' : "AND verification_status = 'verified' AND permit_type <> 'government_id'";
    return rows(
        "SELECT permit_id, property_id, permit_type, custom_permit_name, permit_number, issuing_office,
                issue_date, expiry_date, verification_status, notes
         FROM landlord_permits
         WHERE landlord_id = ? AND (property_id = ? OR property_id IS NULL) {$statusClause}
         ORDER BY FIELD(verification_status, 'verified', 'submitted', 'rejected'), property_id IS NULL ASC, permit_id ASC",
        [$landlordId, $propertyId]
    );
}

function student_bookmark_count(?array $account): int
{
    if (($account['role_name'] ?? '') !== 'student') {
        return 0;
    }
    return (int) scalar('SELECT COUNT(*) FROM bookmarks WHERE student_id = ?', [$account['user_id']]);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function audit(int $actorId, string $action, string $table, int $targetId, ?string $details = null): void
{
    query(
        'INSERT INTO audit_logs (actor_id, action_type, target_table, target_id, details) VALUES (?, ?, ?, ?, ?)',
        [$actorId, $action, $table, $targetId, $details]
    );
}

function active_lease(int $studentId): ?array
{
    return row(
        "SELECT l.*, r.room_number, r.room_type, p.property_id, p.property_name, p.street_address
         FROM leases l JOIN rooms r USING (room_id) JOIN properties p USING (property_id)
         WHERE l.student_id = ? AND l.lease_status = 'active'",
        [$studentId]
    );
}

function media_url(string $path): string
{
    if (preg_match('~^assets/images/[a-z\d/_-]+\.(?:webp|jpe?g|png)$~iD', $path)) {
        return url($path);
    }
    return url('media.php?file=' . rawurlencode($path));
}

function upload_path(string $path): ?string
{
    return preg_match('~^(properties|verifications|payments|maintenance)/[a-f0-9]{32}\.(jpg|png|webp|pdf)$~D', $path)
        ? UPLOAD_ROOT . '/' . $path
        : null;
}

function store_uploaded_file(array $file, string $folder): string
{
    if (!in_array($folder, ['properties', 'verifications', 'payments', 'maintenance'], true)) {
        throw new LogicException('Unknown upload folder.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])
        || ($file['size'] ?? 0) < 1 || ($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
        throw new DomainException('The file could not be uploaded. The size limit is 5 MB per file.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if ($folder === 'verifications') {
        $allowed['application/pdf'] = 'pdf';
    }
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$type]) || ($type !== 'application/pdf' && !@getimagesize($file['tmp_name']))) {
        throw new DomainException('Choose a JPG, PNG or WebP image' . ($folder === 'verifications' ? ', or a PDF.' : '.'));
    }
    $path = $folder . '/' . bin2hex(random_bytes(16)) . '.' . $allowed[$type];
    $directory = UPLOAD_ROOT . '/' . $folder;
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create upload directory.');
    }
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_ROOT . '/' . $path)) {
        throw new RuntimeException('Unable to store uploaded file.');
    }
    return $path;
}

function save_upload(string $field, string $folder, bool $required = false): ?string
{
    $file = $_FILES[$field] ?? null;
    if (!$file || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new DomainException('Choose a file to upload.');
        }
        return null;
    }
    return store_uploaded_file($file, $folder);
}

function save_uploads(string $field, string $folder, int $maxFiles = 12): array
{
    $bag = $_FILES[$field] ?? null;
    if (!$bag || !isset($bag['name'])) {
        return [];
    }
    if (!is_array($bag['name'])) {
        $single = save_upload($field, $folder, false);
        return $single ? [$single] : [];
    }
    $files = [];
    $count = count($bag['name']);
    for ($i = 0; $i < $count; $i++) {
        $err = $bag['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $files[] = [
            'name' => $bag['name'][$i] ?? '',
            'type' => $bag['type'][$i] ?? '',
            'tmp_name' => $bag['tmp_name'][$i] ?? '',
            'error' => $err,
            'size' => $bag['size'][$i] ?? 0,
        ];
    }
    if (count($files) > $maxFiles) {
        throw new DomainException("Upload up to {$maxFiles} photos at a time.");
    }
    $saved = [];
    try {
        foreach ($files as $file) {
            $saved[] = store_uploaded_file($file, $folder);
        }
    } catch (Throwable $error) {
        foreach ($saved as $path) {
            remove_upload($path);
        }
        throw $error;
    }
    return $saved;
}

function remove_upload(?string $path): void
{
    $file = $path === null ? null : upload_path($path);
    if ($file !== null && is_file($file)) {
        @unlink($file);
    }
}

function dashboard_path(string $role): string
{
    return in_array($role, ['student', 'landlord', 'admin'], true) ? $role . '/dashboard.php' : '';
}

function post_login_path(string $path): string
{
    return preg_match('~^property\.php\?id=[1-9][0-9]*(?:&room=[1-9][0-9]*)?(?:&request=(?:visit|hold))?(?:\#booking)?$~D', $path) ? $path : '';
}

function user_initials(array $user): string
{
    return mb_strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1));
}

function icon(string $name, string $class = ''): string
{
    $paths = [
        'pin' => '<path d="M20 10c0 5-8 10-8 10S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'search' => '<circle cx="10.8" cy="10.8" r="6.5"/><path d="m16 16 4 4"/>',
        'arrow' => '<path d="M4 12h15m-6-6 6 6-6 6"/>',
        'arrow-left' => '<path d="M20 12H5m6-6-6 6 6 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'chevron' => '<path d="m7 10 5 5 5-5"/>',
        'filter' => '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="2" fill="currentColor"/><circle cx="15" cy="17" r="2" fill="currentColor"/>',
        'bed' => '<path d="M3 18V8m18 10v-5a2 2 0 0 0-2-2h-6v5m-10 0h18M6 11h2a2 2 0 0 1 2 2v3"/>',
        'shield' => '<path d="M12 22s7-3 7-9V5l-7-3-7 3v8c0 6 7 9 7 9Z"/><path d="m9 12 2 2 4-4"/>',
        'heart' => '<path d="M20.8 8.7c0 4-8.8 9.3-8.8 9.3s-8.8-5.3-8.8-9.3a4.5 4.5 0 0 1 8.8-1.1 4.5 4.5 0 0 1 8.8 1.1Z"/>',
        'bookmark' => '<path d="M6 4h12v17l-6-4-6 4V4Z"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12a2 2 0 0 0 4 0"/>',
        'eye' => '<path d="M2 12s3.8-6 10-6 10 6 10 6-3.8 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>',
        'eye-off' => '<path d="m3 3 18 18M10 6c.6-.1 1.3-.2 2-.2 6.2 0 10 6.2 10 6.2a15 15 0 0 1-3.1 3.5M6.2 7.7A17.5 17.5 0 0 0 2 12s3.8 6 10 6c1.4 0 2.6-.3 3.7-.8"/>',
        'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5.5 16v-.5c0-1 1-1.5 2.5-1.5s2.5.5 2.5 1.5v.5M13 10h5m-5 4h4"/>',
        'home' => '<path d="m3 11 9-8 9 8M5 10v10h14V10m-9 10v-6h4v6"/>',
        'building' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 11h4a1 1 0 0 1 1 1v9M2 21h20M8 7h1m3 0h1M8 11h1m3 0h1M8 15h1m3 0h1"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
        'wallet' => '<path d="M19 8V5H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2V7m18 6h-5v2h5"/>',
        'tool' => '<path d="m14 7 3 3 4-4a6 6 0 0 1-7 8l-6 6a2.1 2.1 0 0 1-3-3l6-6a6 6 0 0 1 8-7Z"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2m1-15a3 3 0 0 1 0 6m1 3a4 4 0 0 1 4 4v2"/>',
        'report' => '<path d="M5 21V4m0 1c6-4 8 4 14 0v10c-6 4-8-4-14 0"/>',
        'file' => '<path d="M14 3H6v18h12V7l-4-4Zm0 0v5h4m-9 5h6m-6 4h6"/>',
        'logout' => '<path d="M10 4H4v16h6m4-4 4-4-4-4m-6 4h12"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8" cy="8" r="1.5"/><path d="m3 17 5-5 4 4 3-3 6 6"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'map' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Zm6-3v15m6-12v15"/>',
        'star' => '<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.8 1-6.1-4.4-4.3 6.1-.9L12 3Z"/>',
        'wifi' => '<path d="M5 12.6a10 10 0 0 1 14 0M8.5 16.1a5 5 0 0 1 7 0M2 8.8a15 15 0 0 1 20 0"/><circle cx="12" cy="19.5" r="1" fill="currentColor"/>',
        'droplet' => '<path d="M12 2.7 6.3 9.3a7.5 7.5 0 1 0 11.4 0L12 2.7Z"/>',
        'zap' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.4 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
        'message' => '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/>',
        'upload' => '<path d="M12 16V4m0 0-4 4m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'trash' => '<path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m-9 0h10l-1 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L6 6Z"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4m0-4h.01"/>',
        'layers' => '<path d="m12 3 9 4.5-9 4.5-9-4.5L12 3Zm-9 9 9 4.5 9-4.5M3 16.5 12 21l9-4.5"/>',
        'award' => '<circle cx="12" cy="8" r="5"/><path d="M8.5 12.7 7 22l5-2.5L17 22l-1.5-9.3"/>',
        'external' => '<path d="M14 4h6v6m0-6L10 14M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
    ];
    return '<svg class="icon ' . e($class) . '" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? '') . '</svg>';
}
