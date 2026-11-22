<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$verified = landlord_verified($landlordId);
$occupancy = row('SELECT COALESCE(SUM(v.occupied_beds), 0) AS occupied, COALESCE(SUM(v.max_capacity), 0) AS capacity FROM vw_room_vacancy v JOIN properties p ON p.property_id = v.property_id WHERE p.landlord_id = ? AND v.is_active = 1', [$landlordId]);
$pending = (int) scalar("SELECT COUNT(*) FROM reservations q JOIN rooms r ON r.room_id = q.room_id JOIN properties p ON p.property_id = r.property_id WHERE p.landlord_id = ? AND q.status = 'pending'", [$landlordId]);
$income = scalar("SELECT COALESCE(SUM(m.amount_paid), 0) FROM payments m JOIN rent_invoices i ON i.invoice_id = m.invoice_id JOIN leases l ON l.lease_id = i.lease_id JOIN rooms r ON r.room_id = l.room_id JOIN properties p ON p.property_id = r.property_id WHERE p.landlord_id = ? AND m.verification_status = 'verified' AND m.paid_at >= ? AND m.paid_at < ?", [$landlordId, date('Y-m-01'), (new DateTimeImmutable('first day of next month'))->format('Y-m-d')]);
$overdue = rows("SELECT v.invoice_id, v.billing_month, v.due_date, v.balance_due, p.property_name, r.room_number, CONCAT(u.first_name, ' ', u.last_name) AS tenant_name
    FROM vw_invoice_summary v JOIN rooms r ON r.room_id = v.room_id JOIN properties p ON p.property_id = r.property_id JOIN users u ON u.user_id = v.student_id
    WHERE p.landlord_id = ? AND v.invoice_status = 'overdue' AND v.balance_due > 0 ORDER BY v.due_date, v.invoice_id", [$landlordId]);
$properties = rows('SELECT p.property_id, p.property_name, p.approval_status, COUNT(r.room_id) AS room_count FROM properties p LEFT JOIN rooms r ON r.property_id = p.property_id WHERE p.landlord_id = ? GROUP BY p.property_id, p.property_name, p.approval_status ORDER BY p.property_name', [$landlordId]);

$pageTitle = 'Landlord overview';
$pageDescription = 'Track occupancy, incoming requests, and rent across your properties.';
require __DIR__ . '/../includes/portal_header.php';
?>
<?php if (!$verified) landlord_verification_notice(); ?>
<div class="stats-grid">
    <article class="stat-card"><span><?= icon('home') ?> Properties</span><strong><?= count($properties) ?></strong></article>
    <article class="stat-card"><span><?= icon('bed') ?> Occupied beds</span><strong><?= e($occupancy['occupied']) ?> / <?= e($occupancy['capacity']) ?></strong></article>
    <article class="stat-card"><span><?= icon('calendar') ?> Pending requests</span><strong><?= $pending ?></strong></article>
    <article class="stat-card"><span><?= icon('wallet') ?> Received in <?= e(date('F')) ?></span><strong><?= e(money($income)) ?></strong></article>
</div>

<section class="panel">
    <div class="section-head actions"><div><h2>Overdue accounts</h2><p class="muted">Invoices with a balance after their due date.</p></div><a class="button secondary small" href="<?= e(url('landlord/billing.php')) ?>">Open billing</a></div>
    <?php if (!$overdue): ?><p class="empty-state">There are no overdue balances.</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Tenant</th><th>Property / room</th><th>Due date</th><th>Balance</th></tr></thead>
            <tbody><?php foreach ($overdue as $invoice): ?>
                <tr><td><a href="<?= e(url('landlord/billing.php?month=' . substr($invoice['billing_month'], 0, 7) . '&invoice=' . $invoice['invoice_id'])) ?>"><?= e($invoice['tenant_name']) ?></a></td><td><?= e($invoice['property_name']) ?> · <?= e($invoice['room_number']) ?></td><td><?= e(date('M j, Y', strtotime($invoice['due_date']))) ?></td><td><?= e(money($invoice['balance_due'])) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="section-head actions"><h2>Properties</h2><a class="button secondary small" href="<?= e(url('landlord/properties.php')) ?>">Manage properties</a></div>
    <?php if (!$properties): ?><p class="empty-state">Add a property to start setting up rooms.</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Property</th><th>Rooms</th><th>Listing status</th></tr></thead>
            <tbody><?php foreach ($properties as $property): ?>
                <tr><td><a href="<?= e(url('landlord/properties.php?edit=' . $property['property_id'])) ?>"><?= e($property['property_name']) ?></a></td><td><?= e($property['room_count']) ?></td><td><?= landlord_badge($property['approval_status']) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
