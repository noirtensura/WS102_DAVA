<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

landlord_post('landlord/leases.php', function (int $landlordId): string {
    global $pdo;
    $operation = field_choice('operation', ['activate', 'update', 'move_out']);
    if ($operation === 'activate') {
        $roomId = field_integer('room_id', 'room');
        $room = owned_room($landlordId, $roomId, true);
        if (!$room['is_active']) {
            throw new DomainException('Choose an active room.');
        }
        $studentEmail = field_text('student_email', 'student email', 150);
        $student = row("SELECT s.student_id FROM student_profiles s JOIN users u ON u.user_id = s.student_id WHERE u.email = ? AND u.account_status = 'active'", [$studentEmail]);
        if (!$student) {
            throw new DomainException('Enter the email of an active student account.');
        }
        if (room_capacity_after_holds($roomId, (int) $student['student_id']) < 1) {
            throw new DomainException('This room has no bed slots available to this student.');
        }
        $moveIn = field_date('move_in_date');
        if ($moveIn > date('Y-m-d')) {
            throw new DomainException('Activate the lease on or after the actual move-in date.');
        }
        $rate = field('agreed_monthly_rent') === '' ? $room['monthly_rate_per_bed'] : field('agreed_monthly_rent');
        query('INSERT INTO leases (student_id, room_id, move_in_date, agreed_monthly_rent, advance_rent_amount, security_deposit_amount, billing_due_day) VALUES (?, ?, ?, ?, ?, ?, ?)', [
            $student['student_id'], $roomId, $moveIn, decimal_amount(amount_cents((string) $rate)),
            decimal_amount(amount_cents(field('advance_rent_amount') ?: '0', true)),
            decimal_amount(amount_cents(field('security_deposit_amount') ?: '0', true)),
            field_integer('billing_due_day', 'billing due day', 1, 28),
        ]);
        $leaseId = (int) $pdo->lastInsertId();
        audit($landlordId, 'lease_activated', 'leases', $leaseId);
        return 'Lease activated. Live vacancy has been updated.';
    }

    $leaseId = field_integer('lease_id', 'lease');
    $lease = row('SELECT l.* FROM leases l JOIN rooms r ON r.room_id = l.room_id JOIN properties p ON p.property_id = r.property_id WHERE l.lease_id = ? AND p.landlord_id = ? FOR UPDATE', [$leaseId, $landlordId]);
    if (!$lease || $lease['lease_status'] !== 'active') {
        throw new DomainException('Choose an active lease at one of your properties.');
    }
    if ($operation === 'update') {
        query('UPDATE leases SET agreed_monthly_rent = ?, advance_rent_amount = ?, security_deposit_amount = ?, billing_due_day = ? WHERE lease_id = ?', [
            decimal_amount(amount_cents(field('agreed_monthly_rent'))),
            decimal_amount(amount_cents(field('advance_rent_amount') ?: '0', true)),
            decimal_amount(amount_cents(field('security_deposit_amount') ?: '0', true)),
            field_integer('billing_due_day', 'billing due day', 1, 28), $leaseId,
        ]);
        audit($landlordId, 'lease_terms_updated', 'leases', $leaseId);
        return 'Lease terms updated for upcoming invoices.';
    }
    $moveOut = field_date('move_out_date');
    $refund = amount_cents(field('deposit_refunded_amount') ?: '0', true);
    if ($moveOut < $lease['move_in_date'] || $moveOut > date('Y-m-d')) {
        throw new DomainException('Use the actual move-out date, between move-in and today.');
    }
    if ($refund > amount_cents((string) $lease['security_deposit_amount'], true)) {
        throw new DomainException('The refund cannot exceed the security deposit.');
    }
    $status = field_choice('lease_status', ['ended', 'terminated']);
    query('UPDATE leases SET lease_status = ?, move_out_date = ?, deposit_refunded_amount = ? WHERE lease_id = ?', [$status, $moveOut, decimal_amount($refund), $leaseId]);
    audit($landlordId, 'lease_' . $status, 'leases', $leaseId, 'Deposit refund: ' . decimal_amount($refund));
    return 'Lease closed. Live vacancy has been updated.';
}, true);
