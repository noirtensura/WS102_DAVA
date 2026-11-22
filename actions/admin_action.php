<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_role('admin');
require_post();

$routes = [
    'verification' => 'admin/verifications.php',
    'permit' => 'admin/verifications.php#permits-review',
    'property' => 'admin/properties.php',
    'user' => 'admin/users.php',
    'create_admin' => 'admin/users.php',
    'reset_password' => 'admin/users.php',
    'amenity_add' => 'admin/catalogs.php',
    'amenity_delete' => 'admin/catalogs.php',
    'barangay_add' => 'admin/catalogs.php#areas-section',
    'report' => 'admin/reports.php',
    'announcement' => 'admin/operations.php?view=announcements',
];
$operation = is_string($_POST['operation'] ?? null) ? $_POST['operation'] : '';
$status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
$note = is_string($_POST['note'] ?? null) ? trim($_POST['note']) : '';
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$returnTo = $routes[$operation] ?? 'admin/dashboard.php';

if (($_POST['return_to'] ?? '') === 'overview' && in_array($operation, ['verification', 'permit', 'property'], true)) {
    $returnTo = 'admin/dashboard.php#verification-queue';
} elseif ($id && $operation === 'property') {
    $returnTo .= '#listing-review-' . $id;
} elseif ($id && $operation === 'report') {
    $returnTo .= '#report-' . $id;
} elseif (in_array($operation, ['user', 'reset_password'], true) && is_string($_POST['account_search'] ?? null) && $_POST['account_search'] !== '') {
    $returnTo .= '?' . http_build_query(['q' => mb_substr(trim($_POST['account_search']), 0, 150)]);
}

$needsId = !in_array($operation, ['create_admin', 'amenity_add', 'barangay_add'], true);
if (!isset($routes[$operation]) || ($needsId && $id === false) || strlen($note) > 2000) {
    flash('error', 'Check the decision and review note, then try again.');
    redirect($returnTo);
}

$actor = (int) $admin['user_id'];

try {
    $pdo->beginTransaction();

    switch ($operation) {
        case 'verification':
            if (!in_array($status, ['verified', 'rejected'], true)) {
                throw new DomainException('Choose a verification decision.');
            }
            $target = row('SELECT l.*, u.account_status FROM landlord_profiles l JOIN users u ON u.user_id = l.landlord_id WHERE l.landlord_id = ? FOR UPDATE', [$id]);
            if ($target === null) {
                throw new DomainException('Landlord account was not found.');
            }
            if ($target['verification_status'] === $status) {
                throw new DomainException('This verification decision has already been saved.');
            }
            if ($status === 'verified' && $target['account_status'] !== 'active') {
                throw new DomainException('Activate the landlord account before approving verification.');
            }
            // Permits are optional in Antique — Admin can verify landlord with or without uploaded permits.
            query('UPDATE landlord_profiles SET verification_status = ?, verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE landlord_id = ?', [$status, $actor, $id]);
            $table = 'landlord_profiles';
            $action = 'landlord_' . $status;
            $previous = $target['verification_status'];
            $targetId = (int) $id;
            $flashMsg = $status === 'verified' ? 'Landlord account verified.' : 'Landlord verification updated.';
            break;

        case 'permit':
            if (!in_array($status, ['verified', 'rejected'], true)) {
                throw new DomainException('Choose a permit review decision.');
            }
            $target = row('SELECT * FROM landlord_permits WHERE permit_id = ? FOR UPDATE', [$id]);
            if ($target === null) {
                throw new DomainException('Permit record was not found.');
            }
            if ($target['verification_status'] === $status) {
                throw new DomainException('That permit status is already saved.');
            }
            query('UPDATE landlord_permits SET verification_status = ?, verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE permit_id = ?', [$status, $actor, $id]);
            $table = 'landlord_permits';
            $action = 'permit_' . $status;
            $previous = permit_label($target) . ' (' . $target['verification_status'] . ')';
            $targetId = (int) $id;
            $flashMsg = $status === 'verified' ? 'Permit verified and badge enabled on listing.' : 'Permit marked as rejected.';
            break;

        case 'property':
            if (!in_array($status, ['approved', 'approve_and_verify', 'rejected', 'revision_requested', 'pending', 'feature', 'unfeature'], true)) {
                throw new DomainException('Choose a listing decision.');
            }
            $target = row('SELECT p.approval_status, p.is_featured, p.landlord_id, l.verification_status, u.account_status
                FROM properties p JOIN landlord_profiles l ON l.landlord_id = p.landlord_id
                JOIN users u ON u.user_id = p.landlord_id WHERE p.property_id = ? FOR UPDATE', [$id]);
            if ($target === null) {
                throw new DomainException('Listing was not found.');
            }
            $targetId = (int) $id;
            $table = 'properties';
            if (in_array($status, ['feature', 'unfeature'], true)) {
                $flag = $status === 'feature' ? 1 : 0;
                query('UPDATE properties SET is_featured = ? WHERE property_id = ?', [$flag, $id]);
                $action = $flag ? 'property_featured' : 'property_unfeatured';
                $previous = $target['is_featured'] ? 'featured' : 'standard';
                $flashMsg = $flag ? 'Listing marked as Featured.' : 'Listing removed from Featured.';
                break;
            }
            if ($status === 'approve_and_verify') {
                if ($target['account_status'] !== 'active') {
                    throw new DomainException('Activate the landlord account first.');
                }
                if ($target['verification_status'] !== 'verified') {
                    query("UPDATE landlord_profiles SET verification_status = 'verified', verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE landlord_id = ?", [$actor, $target['landlord_id']]);
                    audit($actor, 'landlord_verified', 'landlord_profiles', (int) $target['landlord_id'], 'Verified alongside property approval #' . $id);
                }
                $status = 'approved';
            }
            if ($status === 'approved') {
                if ($target['account_status'] !== 'active') {
                    throw new DomainException('Activate the landlord account before publishing this listing.');
                }
                if ($target['verification_status'] !== 'verified') {
                    throw new DomainException('Verify the landlord first, or use "Verify Owner & Approve".');
                }
            }
            if ($target['approval_status'] === $status) {
                throw new DomainException('This listing decision has already been saved.');
            }
            query('UPDATE properties SET approval_status = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE property_id = ?', [$status, $actor, $id]);
            $action = 'property_' . $status;
            $previous = $target['approval_status'];
            $flashMsg = $status === 'approved' ? 'Listing approved and published to students.' : 'Listing status updated.';
            break;

        case 'user':
            if (!in_array($status, ['active', 'suspended'], true)) {
                throw new DomainException('Choose an account status.');
            }
            $target = row("SELECT u.account_status FROM users u JOIN roles r ON r.role_id = u.role_id
                WHERE u.user_id = ? AND r.role_name IN ('student', 'landlord') FOR UPDATE", [$id]);
            if ($target === null || $id === $actor) {
                throw new DomainException('Student or landlord account was not found.');
            }
            if ($target['account_status'] === $status) {
                throw new DomainException('The account already has that status.');
            }
            query('UPDATE users SET account_status = ? WHERE user_id = ?', [$status, $id]);
            $table = 'users';
            $action = $status === 'active' ? 'account_activated' : 'account_suspended';
            $previous = $target['account_status'];
            $targetId = (int) $id;
            $flashMsg = $status === 'active' ? 'Account activated.' : 'Account suspended.';
            break;

        case 'reset_password':
            $newPass = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
            if (strlen($newPass) < 8 || strlen($newPass) > 72 || str_contains($newPass, "\0")) {
                throw new DomainException('Enter a temporary password between 8 and 72 characters.');
            }
            $target = row("SELECT u.user_id, u.email FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ? AND r.role_name IN ('student', 'landlord') FOR UPDATE", [$id]);
            if (!$target) {
                throw new DomainException('Student or landlord account was not found.');
            }
            query('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($newPass, PASSWORD_DEFAULT), $id]);
            $table = 'users';
            $action = 'password_reset';
            $previous = $target['email'];
            $status = 'password updated';
            $targetId = (int) $id;
            $flashMsg = 'Password updated for ' . $target['email'] . '.';
            break;

        case 'create_admin':
            $first = trim((string) ($_POST['first_name'] ?? ''));
            $last = trim((string) ($_POST['last_name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone_number'] ?? ''));
            $pass = (string) ($_POST['password'] ?? '');
            if ($first === '' || $last === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8 || strlen($pass) > 72 || strlen($phone) < 7) {
                throw new DomainException('Enter valid name, email, phone number, and password (at least 8 characters).');
            }
            query(
                "INSERT INTO users (role_id, email, password_hash, first_name, last_name, phone_number, account_status)
                 VALUES ((SELECT role_id FROM roles WHERE role_name = 'admin'), ?, ?, ?, ?, ?, 'active')",
                [$email, password_hash($pass, PASSWORD_DEFAULT), mb_substr($first, 0, 80), mb_substr($last, 0, 80), mb_substr($phone, 0, 20)]
            );
            $targetId = (int) $pdo->lastInsertId();
            $table = 'users';
            $action = 'admin_created';
            $previous = 'new';
            $status = $email;
            $flashMsg = 'New administrator account created for ' . $email . '.';
            break;

        case 'amenity_add':
            $name = trim((string) ($_POST['amenity_name'] ?? ''));
            $scope = (string) ($_POST['scope'] ?? '');
            if ($name === '' || mb_strlen($name) > 80 || !in_array($scope, ['property', 'room'], true)) {
                throw new DomainException('Enter a valid amenity name (up to 80 characters) and scope.');
            }
            query('INSERT INTO amenities (amenity_name, scope) VALUES (?, ?)', [$name, $scope]);
            $targetId = (int) $pdo->lastInsertId();
            $table = 'amenities';
            $action = 'amenity_added';
            $previous = $scope;
            $status = $name;
            $flashMsg = 'Amenity "' . $name . '" added.';
            break;

        case 'amenity_delete':
            $target = row('SELECT amenity_name FROM amenities WHERE amenity_id = ? FOR UPDATE', [$id]);
            if (!$target) {
                throw new DomainException('Amenity was not found.');
            }
            query('DELETE FROM amenities WHERE amenity_id = ?', [$id]);
            $targetId = (int) $id;
            $table = 'amenities';
            $action = 'amenity_removed';
            $previous = $target['amenity_name'];
            $status = 'removed';
            $flashMsg = 'Amenity "' . $target['amenity_name'] . '" removed.';
            break;

        case 'barangay_add':
            $munId = filter_var($_POST['municipality_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $bName = trim((string) ($_POST['barangay_name'] ?? ''));
            if ($munId === false || $bName === '' || mb_strlen($bName) > 100) {
                throw new DomainException('Choose an Antique municipality and enter a valid barangay name.');
            }
            $munName = scalar('SELECT municipality_name FROM municipalities WHERE municipality_id = ? AND province_name = ?', [$munId, DAYON_PROVINCE]);
            if (!$munName) {
                throw new DomainException('Selected municipality is unavailable.');
            }
            query('INSERT INTO barangays (municipality_id, barangay_name) VALUES (?, ?)', [$munId, $bName]);
            $targetId = (int) $pdo->lastInsertId();
            $table = 'barangays';
            $action = 'barangay_added';
            $previous = (string) $munName;
            $status = $bName;
            $flashMsg = "Barangay \"{$bName}\" added to {$munName}.";
            break;

        case 'report':
            $target = row('SELECT status FROM reports WHERE report_id = ? FOR UPDATE', [$id]);
            if ($target === null) {
                throw new DomainException('Report was not found.');
            }
            $decisions = match ($target['status']) {
                'open' => ['reviewed', 'resolved', 'dismissed'],
                'reviewed' => ['resolved', 'dismissed'],
                default => ['open'],
            };
            if (!in_array($status, $decisions, true)) {
                throw new DomainException('That decision is unavailable for this report.');
            }
            query('UPDATE reports SET status = ?, reviewed_by = ? WHERE report_id = ?', [$status, $status === 'open' ? null : $actor, $id]);
            $table = 'reports';
            $action = 'report_' . ($status === 'open' ? 'reopened' : $status);
            $previous = $target['status'];
            $targetId = (int) $id;
            $flashMsg = 'Report status updated.';
            break;

        case 'announcement':
            if ($status !== 'removed') {
                throw new DomainException('Choose to remove the announcement.');
            }
            $target = row('SELECT title FROM announcements WHERE announcement_id = ? FOR UPDATE', [$id]);
            if ($target === null) {
                throw new DomainException('Announcement was not found.');
            }
            query('DELETE FROM announcements WHERE announcement_id = ?', [$id]);
            $table = 'announcements';
            $action = 'announcement_removed';
            $previous = $target['title'];
            $targetId = (int) $id;
            $flashMsg = 'Announcement removed.';
            break;
    }

    $details = ucfirst(str_replace('_', ' ', (string) $previous)) . ' → ' . str_replace('_', ' ', (string) $status);
    if ($note !== '') {
        $details .= '. ' . $note;
    }
    audit($actor, $action, $table, $targetId, $details);
    $pdo->commit();
    flash('success', $flashMsg ?? 'Decision recorded.');
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $exception->getMessage());
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
        flash('error', 'That record already exists.');
    } else {
        error_log('Dayon admin action failed: ' . $exception->getMessage());
        flash('error', 'The decision could not be saved. Try again.');
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Dayon admin action failed: ' . $exception->getMessage());
    flash('error', 'The decision could not be saved. Try again.');
}
redirect($returnTo);
