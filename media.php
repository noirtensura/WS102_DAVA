<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$name = is_string($_GET['file'] ?? null) ? $_GET['file'] : '';
$file = upload_path($name);
if ($file === null || !is_file($file)) {
    abort_request(404, 'This file is unavailable.');
}
$role = $user['role_name'] ?? '';
$id = (int) ($user['user_id'] ?? 0);
$folder = strstr($name, '/', true);

$allowed = match ($folder) {
    'properties' => (bool) scalar(
        "SELECT 1 FROM property_images i JOIN properties p USING (property_id)
         JOIN landlord_profiles lp USING (landlord_id) JOIN users u ON u.user_id = lp.landlord_id
         WHERE i.image_path = ? AND ((p.approval_status = 'approved' AND lp.verification_status = 'verified'
           AND u.account_status = 'active') OR p.landlord_id = ? OR ? = 'admin') LIMIT 1",
        [$name, $id, $role]
    ),
    'verifications' => (bool) scalar(
        "SELECT 1 FROM landlord_profiles WHERE (government_id_path = ? OR business_permit_path = ?)
         AND (landlord_id = ? OR ? = 'admin') LIMIT 1",
        [$name, $name, $id, $role]
    ) || (bool) scalar(
        "SELECT 1 FROM landlord_permits WHERE document_path = ?
         AND (landlord_id = ? OR ? = 'admin') LIMIT 1",
        [$name, $id, $role]
    ),
    'payments' => (bool) scalar(
        "SELECT 1 FROM payments pay JOIN rent_invoices ri USING (invoice_id) JOIN leases l USING (lease_id)
         JOIN rooms r USING (room_id) JOIN properties p USING (property_id)
         WHERE pay.proof_image_path = ? AND (l.student_id = ? OR p.landlord_id = ? OR ? = 'admin') LIMIT 1",
        [$name, $id, $id, $role]
    ),
    'maintenance' => (bool) scalar(
        "SELECT 1 FROM maintenance_requests m JOIN leases l USING (lease_id)
         JOIN rooms r USING (room_id) JOIN properties p USING (property_id)
         WHERE m.photo_path = ? AND (l.student_id = ? OR p.landlord_id = ? OR ? = 'admin') LIMIT 1",
        [$name, $id, $id, $role]
    ),
    default => false,
};
if (!$allowed) {
    abort_request(404, 'This file is unavailable.');
}

$extension = pathinfo($name, PATHINFO_EXTENSION);
$mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
session_write_close();
header('Content-Type: ' . $mime[$extension]);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, no-store');
if ($extension === 'pdf') {
    header('Content-Disposition: inline; filename="dayon-document.pdf"');
}
readfile($file);
