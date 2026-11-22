<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../student/_support.php';

$student = require_role('student');
require_post();
$studentId = (int) $student['user_id'];
$propertyId = student_post_id('property_id');
$operation = student_post_text('operation');
$json = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
$destination = student_post_text('return_to') === 'bookmarks' || !$propertyId
    ? 'student/bookmarks.php' : 'property.php?id=' . $propertyId;
$saved = false;
$success = true;
$status = 200;

try {
    $pdo->beginTransaction();
    query('SELECT student_id FROM student_profiles WHERE student_id = ? FOR UPDATE', [$studentId]);

    if ($operation === 'save_note') {
        $note = mb_substr(student_post_text('note'), 0, 255);
        $updated = query(
            'UPDATE bookmarks SET note = ? WHERE student_id = ? AND property_id = ?',
            [$note === '' ? null : $note, $studentId, $propertyId]
        );
        if (!$updated->rowCount() && !scalar('SELECT 1 FROM bookmarks WHERE student_id = ? AND property_id = ?', [$studentId, $propertyId])) {
            throw new DomainException('Bookmark this property first before saving a note.');
        }
        $saved = true;
        $message = $note === '' ? 'Bookmark note cleared.' : 'Personal note saved.';
    } else {
        $removed = query('DELETE FROM bookmarks WHERE student_id = ? AND property_id = ?', [$studentId, $propertyId]);
        if ($removed->rowCount()) {
            $message = 'Removed from your bookmarked houses.';
        } elseif (student_visible_property($propertyId)) {
            query('INSERT INTO bookmarks (student_id, property_id) VALUES (?, ?)', [$studentId, $propertyId]);
            $saved = true;
            $message = 'Bookmarked! View or compare anytime in Bookmarked Houses.';
        } else {
            throw new DomainException('This listing is no longer available to bookmark.');
        }
    }
    $count = (int) scalar('SELECT COUNT(*) FROM bookmarks WHERE student_id = ?', [$studentId]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $success = false;
    $count = student_bookmark_count($student);
    if ($error instanceof DomainException) {
        $status = 404;
        $message = $error->getMessage();
    } else {
        error_log($error->getMessage());
        $status = 500;
        $message = 'Your bookmarks could not be updated. Please try again.';
    }
}

if ($json) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'saved' => $saved, 'bookmarked' => $saved, 'count' => $count, 'message' => $message]);
    exit;
}

flash($success ? 'success' : 'error', $message);
redirect($destination);
