<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_role('admin');
require_post();

$routes = [
    'verification' => 'admin/verifications.php',
    'property' => 'admin/properties.php',
    'user' => 'admin/users.php',
    'report' => 'admin/reports.php',
    'announcement' => 'admin/operations.php?view=announcements',
];
$operation = is_string($_POST['operation'] ?? null) ? $_POST['operation'] : '';
$status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
$note = is_string($_POST['note'] ?? null) ? trim($_POST['note']) : '';
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$returnTo = $routes[$operation] ?? 'admin/dashboard.php';
if (($_POST['return_to'] ?? '') === 'overview' && in_array($operation, ['verification', 'property'], true)) {
    $returnTo = 'admin/dashboard.php#verification-queue';
}
if (!isset($routes[$operation]) || $id === false || strlen($note) > 2000) {
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
            if (empty($target['government_id_path']) || empty($target['business_permit_path'])) {
                throw new DomainException('Wait for the landlord to submit both verification documents.');
            }
            if ($status === 'verified' && $target['account_status'] !== 'active') {
                throw new DomainException('Activate the landlord account before approving verification.');
            }
            query('UPDATE landlord_profiles SET verification_status = ?, verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE landlord_id = ?', [$status, $actor, $id]);
            $table = 'landlord_profiles';
            $action = 'landlord_' . $status;
            $previous = $target['verification_status'];
            break;

        case 'property':
            if (!in_array($status, ['approved', 'rejected', 'revision_requested'], true)) {
                throw new DomainException('Choose a listing decision.');
            }
            $target = row('SELECT p.approval_status, l.verification_status, u.account_status
                FROM properties p JOIN landlord_profiles l ON l.landlord_id = p.landlord_id
                JOIN users u ON u.user_id = p.landlord_id WHERE p.property_id = ? FOR UPDATE', [$id]);
            if ($target === null) {
                throw new DomainException('Listing was not found.');
            }
            if ($target['approval_status'] === $status) {
                throw new DomainException('This listing decision has already been saved.');
            }
            if ($status === 'approved') {
                $activeRoom = row('SELECT room_id FROM rooms WHERE property_id = ? AND is_active = 1 LIMIT 1 FOR UPDATE', [$id]);
                if ($target['account_status'] !== 'active' || $target['verification_status'] !== 'verified' || $activeRoom === null) {
                    throw new DomainException('Publishing requires a verified, active landlord and an active room.');
                }
            }
            query('UPDATE properties SET approval_status = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE property_id = ?', [$status, $actor, $id]);
            $table = 'properties';
            $action = 'property_' . $status;
            $previous = $target['approval_status'];
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
            break;
    }

    $details = ucfirst(str_replace('_', ' ', $previous)) . ' → ' . str_replace('_', ' ', $status);
    if ($note !== '') {
        $details .= '. ' . $note;
    }
    audit($actor, $action, $table, $id, $details);
    $pdo->commit();
    flash('success', 'Decision recorded.');
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $exception->getMessage());
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Dayon admin action failed: ' . $exception->getMessage());
    flash('error', 'The decision could not be saved. Try again.');
}
redirect($returnTo);
