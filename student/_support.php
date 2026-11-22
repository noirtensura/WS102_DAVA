<?php
declare(strict_types=1);

function student_post_text(string $key): string
{
    return is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
}

function student_post_id(string $key): int
{
    return (int) filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function student_active_lease_or_redirect(int $studentId): array
{
    $lease = active_lease($studentId);
    if (!$lease) {
        flash('info', 'This page becomes available when your landlord activates your lease.');
        redirect('student/dashboard.php');
    }
    return $lease;
}

function student_status(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}

function student_request_name(string $type): string
{
    return $type === 'ocular_visit' ? 'Ocular visit' : 'Bed reservation';
}

function student_short_date(string $timestamp): string
{
    return date('M j, Y', strtotime($timestamp));
}

function student_date_time(string $timestamp): string
{
    return date('M j, Y · g:i A', strtotime($timestamp));
}

function student_billing_month(string $timestamp): string
{
    return date('F Y', strtotime($timestamp));
}

function student_visible_property(int $propertyId): bool
{
    return (bool) scalar(
        "SELECT 1 FROM properties p JOIN landlord_profiles lp ON lp.landlord_id = p.landlord_id
         JOIN users u ON u.user_id = p.landlord_id
         JOIN barangays b ON b.barangay_id = p.barangay_id JOIN municipalities m ON m.municipality_id = b.municipality_id
         WHERE p.property_id = ? AND p.approval_status = 'approved'
           AND lp.verification_status = 'verified' AND u.account_status = 'active'
           AND m.province_name = ?",
        [$propertyId, DAYON_PROVINCE]
    );
}
