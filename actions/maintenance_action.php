<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

landlord_post('landlord/maintenance.php', function (int $landlordId): string {
    global $pdo;
    $operation = field_choice('operation', ['ticket', 'announce', 'update_announcement', 'delete_announcement']);
    if ($operation === 'ticket') {
        $ticketId = field_integer('ticket_id', 'ticket');
        $ticket = row('SELECT t.* FROM maintenance_requests t JOIN leases l ON l.lease_id = t.lease_id JOIN rooms r ON r.room_id = l.room_id JOIN properties p ON p.property_id = r.property_id WHERE t.ticket_id = ? AND p.landlord_id = ? FOR UPDATE', [$ticketId, $landlordId]);
        if (!$ticket) {
            throw new DomainException('Choose a maintenance ticket from one of your properties.');
        }
        $status = field_choice('ticket_status', ['open', 'in_progress', 'resolved', 'closed']);
        $priority = field_choice('priority', ['low', 'medium', 'high']);
        $resolvedAt = in_array($status, ['resolved', 'closed'], true) ? ($ticket['resolved_at'] ?? date('Y-m-d H:i:s')) : null;
        query('UPDATE maintenance_requests SET ticket_status = ?, priority = ?, resolved_at = ? WHERE ticket_id = ?', [$status, $priority, $resolvedAt, $ticketId]);
        audit($landlordId, 'maintenance_updated', 'maintenance_requests', $ticketId, $status);
        return 'Maintenance ticket updated.';
    }

    if ($operation === 'announce') {
        $propertyId = field_integer('property_id', 'property');
        owned_property($landlordId, $propertyId, true);
        query('INSERT INTO announcements (property_id, title, message) VALUES (?, ?, ?)', [$propertyId, field_text('title', 'title', 150), field_text('message', 'advisory message', 5000)]);
        audit($landlordId, 'announcement_created', 'announcements', (int) $pdo->lastInsertId());
        return 'Advisory published to the property’s tenants.';
    }
    $announcementId = field_integer('announcement_id', 'advisory');
    $announcement = row('SELECT a.* FROM announcements a JOIN properties p ON p.property_id = a.property_id WHERE a.announcement_id = ? AND p.landlord_id = ? FOR UPDATE', [$announcementId, $landlordId]);
    if (!$announcement) {
        throw new DomainException('Choose an advisory from one of your properties.');
    }
    if ($operation === 'delete_announcement') {
        query('DELETE FROM announcements WHERE announcement_id = ?', [$announcementId]);
        audit($landlordId, 'announcement_deleted', 'announcements', $announcementId);
        return 'Advisory removed.';
    }
    query('UPDATE announcements SET title = ?, message = ? WHERE announcement_id = ?', [field_text('title', 'title', 150), field_text('message', 'advisory message', 5000), $announcementId]);
    audit($landlordId, 'announcement_updated', 'announcements', $announcementId);
    return 'Advisory updated.';
}, true);
