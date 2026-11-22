<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../student/_support.php';

$student = require_role('student');
require_post();
$studentId = (int) $student['user_id'];
$destination = 'student/reservations.php';
$cancel = student_post_text('operation') === 'cancel';

try {
    $pdo->beginTransaction();
    query('SELECT student_id FROM student_profiles WHERE student_id = ? FOR UPDATE', [$studentId]);
    if (active_lease($studentId)) {
        throw new DomainException('Visit and reservation requests are read-only during an active lease.');
    }

    if ($cancel) {
        $changed = query(
            "UPDATE reservations SET status = 'cancelled'
             WHERE reservation_id = ? AND student_id = ? AND status IN ('pending', 'approved')",
            [student_post_id('reservation_id'), $studentId]
        );
        if (!$changed->rowCount()) {
            throw new DomainException('This request is no longer available to cancel.');
        }
        $pdo->commit();
        flash('success', 'Your request was cancelled.');
        redirect($destination);
    }

    $roomId = student_post_id('room_id');
    $room = row('SELECT room_id, property_id, is_active, max_capacity FROM rooms WHERE room_id = ? FOR UPDATE', [$roomId]);
    if (!$room || !$room['is_active'] || !student_visible_property((int) $room['property_id'])) {
        throw new DomainException('This room is no longer accepting requests.');
    }
    $type = student_post_text('request_type');
    $notes = student_post_text('notes');
    if (!in_array($type, ['ocular_visit', 'bed_reservation'], true)) {
        throw new DomainException('Choose an ocular visit or bed reservation.');
    }
    $hold = $type === 'bed_reservation';
    $destination = 'property.php?id=' . $room['property_id'] . '&room=' . $roomId . '&request=' . ($hold ? 'hold' : 'visit') . '#booking';
    $requestedDate = $hold ? student_post_text('move_in_date') : '';
    $time = $requestedDate !== '' ? $requestedDate . 'T23:59' : student_post_text('scheduled_at');
    $scheduled = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $time);
    if (!$scheduled || $scheduled->format('Y-m-d\TH:i') !== $time || $scheduled <= new DateTimeImmutable()) {
        throw new DomainException($hold ? 'Choose a valid move-in date.' : 'Choose a date and time in the future.');
    }
    if ($hold && array_key_exists('requested_months', $_POST)) {
        $months = filter_var(student_post_text('requested_months'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
        if ($months === false) {
            throw new DomainException('Choose between 1 and 12 months for your stay.');
        }
        $notes = 'Requested stay of ' . $months . ($months === 1 ? ' month.' : ' months.') . ($notes !== '' ? "\n" . $notes : '');
    }
    if (strlen($notes) > 5000) {
        throw new DomainException('Please keep your notes within 5,000 characters.');
    }

    if ($hold) {
        $occupants = rows("SELECT lease_id FROM leases WHERE room_id = ? AND lease_status = 'active' FOR UPDATE", [$roomId]);
        if (count($occupants) >= (int) $room['max_capacity']) {
            throw new DomainException('There are currently no available beds in this room.');
        }
    }
    if (scalar(
        "SELECT reservation_id FROM reservations WHERE student_id = ? AND room_id = ? AND request_type = ?
         AND status IN ('pending', 'approved') AND scheduled_at >= NOW() LIMIT 1",
        [$studentId, $roomId, $type]
    )) {
        throw new DomainException('You already have an upcoming request of this type for this room.');
    }

    query(
        'INSERT INTO reservations (student_id, room_id, request_type, scheduled_at, notes) VALUES (?, ?, ?, ?, ?)',
        [$studentId, $roomId, $type, $scheduled->format('Y-m-d H:i:s'), $notes ?: null]
    );
    $pdo->commit();
    flash('success', student_request_name($type) . ' requested. Track the landlord’s response under My requests.');
} catch (DomainException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $error->getMessage());
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($error->getMessage());
    flash('error', 'Your request could not be saved. Please try again.');
}

redirect($destination);
