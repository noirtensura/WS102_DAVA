<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

$propertyFilter = (int) filter_input(INPUT_POST, 'property_filter', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$returnPath = field('return_to') === 'rooms'
    ? 'landlord/rooms.php' . ($propertyFilter ? '?property_id=' . $propertyFilter : '') . '#board-requests'
    : 'landlord/reservations.php';
landlord_post($returnPath, function (int $landlordId): string {
    $requestId = field_integer('reservation_id', 'request');
    $decision = field_choice('decision', ['approved', 'declined']);
    $request = row('SELECT q.* FROM reservations q JOIN rooms r ON r.room_id = q.room_id JOIN properties p ON p.property_id = r.property_id WHERE q.reservation_id = ? AND p.landlord_id = ? FOR UPDATE', [$requestId, $landlordId]);
    if (!$request || $request['status'] !== 'pending') {
        throw new DomainException('This request is unavailable or has already been reviewed.');
    }
    if ($decision === 'approved') {
        $room = owned_room($landlordId, (int) $request['room_id'], true);
        if (!$room['is_active'] || $room['approval_status'] !== 'approved') {
            throw new DomainException('The room and its property must be available for new requests.');
        }
        if ($request['request_type'] === 'bed_reservation') {
            if (substr($request['scheduled_at'], 0, 10) < date('Y-m-d')) {
                throw new DomainException('The proposed move-in date has passed.');
            }
            if (active_lease((int) $request['student_id'])) {
                throw new DomainException('This student already has an active lease.');
            }
            $sameStudent = scalar("SELECT EXISTS (SELECT 1 FROM reservations WHERE room_id = ? AND student_id = ? AND request_type = 'bed_reservation' AND status = 'approved' AND scheduled_at >= CURDATE())", [$room['room_id'], $request['student_id']]);
            if ($sameStudent) {
                throw new DomainException('This student already has an approved hold for this room.');
            }
            if (room_capacity_after_holds((int) $room['room_id']) < 1) {
                throw new DomainException('The room has no beds available after approved holds.');
            }
        } elseif ($request['scheduled_at'] < date('Y-m-d H:i:s')) {
            throw new DomainException('The proposed visit time has passed.');
        }
    }
    query('UPDATE reservations SET status = ? WHERE reservation_id = ?', [$decision, $requestId]);
    audit($landlordId, 'reservation_' . $decision, 'reservations', $requestId);
    return $decision === 'approved' ? 'Request approved.' : 'Request declined.';
}, true);
