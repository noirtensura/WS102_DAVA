<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../student/_support.php';

$student = require_role('student');
require_post();
$studentId = (int) $student['user_id'];
$lease = student_active_lease_or_redirect($studentId);
$category = student_post_text('category');
$description = student_post_text('description');
$priority = student_post_text('priority');
$photo = null;

try {
    if (!in_array($category, ['electrical', 'plumbing', 'furniture', 'internet', 'other'], true) ||
        !in_array($priority, ['low', 'medium', 'high'], true) || $description === '' || strlen($description) > 5000) {
        throw new DomainException('Choose a category and priority, then describe the issue within 5,000 characters.');
    }

    $pdo->beginTransaction();
    if (!row("SELECT lease_id FROM leases WHERE lease_id = ? AND lease_status = 'active' FOR UPDATE", [$lease['lease_id']])) {
        throw new DomainException('This room assignment is no longer active.');
    }
    $photo = save_upload('photo', 'maintenance');
    query('INSERT INTO maintenance_requests (lease_id, category, description, photo_path, priority) VALUES (?, ?, ?, ?, ?)',
        [$lease['lease_id'], $category, $description, $photo, $priority]);
    $pdo->commit();
    flash('success', 'Your repair request was sent to the landlord.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    remove_upload($photo);
    if ($error instanceof DomainException || $error instanceof InvalidArgumentException) {
        $message = $error->getMessage();
    } else {
        error_log($error->getMessage());
        $message = 'Your repair request could not be submitted. Please try again.';
    }
    flash('error', $message);
}

redirect('student/maintenance.php');
