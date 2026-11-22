<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

$propertyFilter = (int) filter_input(INPUT_POST, 'property_filter', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
landlord_post('landlord/rooms.php' . ($propertyFilter ? '?property_id=' . $propertyFilter : ''), function (int $landlordId): string {
    $operation = field_choice('operation', ['save', 'delete']);
    $roomId = field('room_id') === '' ? null : field_integer('room_id', 'room');
    $room = $roomId === null ? null : owned_room($landlordId, $roomId, true);
    if ($operation === 'delete') {
        if ($room === null) {
            throw new DomainException('Choose one of your rooms.');
        }
        if (scalar('SELECT EXISTS (SELECT 1 FROM leases WHERE room_id = ?) OR EXISTS (SELECT 1 FROM reservations WHERE room_id = ?)', [$roomId, $roomId])) {
            throw new DomainException('Set rooms with lease or reservation history to inactive after move-out.');
        }
        query('DELETE FROM rooms WHERE room_id = ?', [$roomId]);
        audit($landlordId, 'room_deleted', 'rooms', $roomId);
        return 'Room removed.';
    }

    $propertyId = $room['property_id'] ?? field_integer('property_id', 'property');
    owned_property($landlordId, (int) $propertyId, true);
    $capacity = field_integer('max_capacity', 'bed capacity', 1, 255);
    $active = field_choice('is_active', ['0', '1']);
    if ($room !== null) {
        $occupied = (int) scalar("SELECT COUNT(*) FROM leases WHERE room_id = ? AND lease_status = 'active'", [$roomId]);
        if ($occupied > $capacity || ($occupied > 0 && $active === '0')) {
            throw new DomainException('Keep the room active and its capacity at or above the occupied bed count.');
        }
    }
    $fields = [
        field_text('room_number', 'room number', 30),
        field_integer('floor_number', 'floor', 0, 255),
        field_choice('room_type', ['solo', 'shared', 'studio']),
        $capacity,
        decimal_amount(amount_cents(field('monthly_rate_per_bed'))),
        $active,
    ];

    if ($room !== null) {
        query('UPDATE rooms SET room_number = ?, floor_number = ?, room_type = ?, max_capacity = ?, monthly_rate_per_bed = ?, is_active = ? WHERE room_id = ?', [...$fields, $roomId]);
    } else {
        global $pdo;
        query('INSERT INTO rooms (room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed, is_active, property_id) VALUES (?, ?, ?, ?, ?, ?, ?)', [...$fields, $propertyId]);
        $roomId = (int) $pdo->lastInsertId();
    }
    replace_amenities('room', $roomId);
    return 'Room saved.';
});
