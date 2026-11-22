<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../student/_support.php';

$student = require_role('student');
require_post();
$studentId = (int) $student['user_id'];
$propertyId = student_post_id('property_id');
$json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
$destination = student_post_text('return_to') === 'bookmarks' || !$propertyId
    ? 'student/bookmarks.php' : 'property.php?id=' . $propertyId;
$saved = false;
$success = true;
$status = 200;

try {
    $pdo->beginTransaction();
    query('SELECT student_id FROM student_profiles WHERE student_id = ? FOR UPDATE', [$studentId]);
    $removed = query('DELETE FROM bookmarks WHERE student_id = ? AND property_id = ?', [$studentId, $propertyId]);
    if ($removed->rowCount()) {
        $message = 'Property removed from your shortlist.';
    } elseif (student_visible_property($propertyId)) {
        query('INSERT INTO bookmarks (student_id, property_id) VALUES (?, ?)', [$studentId, $propertyId]);
        $saved = true;
        $message = 'Property saved to your shortlist.';
    } else {
        throw new DomainException('This property is no longer available to save.');
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $success = false;
    if ($error instanceof DomainException) {
        $status = 404;
        $message = $error->getMessage();
    } else {
        error_log($error->getMessage());
        $status = 500;
        $message = 'Your shortlist could not be updated. Please try again.';
    }
}

if ($json) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'saved' => $saved, 'bookmarked' => $saved, 'message' => $message]);
    exit;
}

flash($success ? 'success' : 'error', $message);
redirect($destination);
