<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../student/_support.php';

$student = require_role('student');
require_post();
$propertyId = student_post_id('property_id');
$reason = student_post_text('reason');

if (!student_visible_property($propertyId)) {
    flash('error', 'This listing is no longer available.');
    redirect('index.php');
}

try {
    if ($reason === '' || strlen($reason) > 5000) {
        throw new DomainException('Enter a reason for your report within 5,000 characters.');
    }
    query('INSERT INTO reports (reporter_id, property_id, reason) VALUES (?, ?, ?)', [$student['user_id'], $propertyId, $reason]);
    flash('success', 'Your report was sent for administrator review.');
} catch (DomainException $error) {
    flash('error', $error->getMessage());
} catch (Throwable $error) {
    error_log($error->getMessage());
    flash('error', 'Your report could not be submitted. Please try again.');
}

redirect('property.php?id=' . $propertyId);
