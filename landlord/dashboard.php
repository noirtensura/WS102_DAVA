<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$profile = row('SELECT verification_status, verified_at FROM landlord_profiles WHERE landlord_id = ?', [$landlordId]);
$verified = ($profile['verification_status'] ?? 'pending') === 'verified';
$occupancy = row('SELECT COALESCE(SUM(v.occupied_beds), 0) AS occupied, COALESCE(SUM(v.max_capacity), 0) AS capacity FROM vw_room_vacancy v JOIN properties p ON p.property_id = v.property_id WHERE p.landlord_id = ? AND v.is_active = 1', [$landlordId]);
$pending = (int) scalar("SELECT COUNT(*) FROM reservations q JOIN rooms r ON r.room_id = q.room_id JOIN properties p ON p.property_id = r.property_id WHERE p.landlord_id = ? AND q.status = 'pending'", [$landlordId]);
$income = scalar("SELECT COALESCE(SUM(m.amount_paid), 0) FROM payments m JOIN rent_invoices i ON i.invoice_id = m.invoice_id JOIN leases l ON l.lease_id = i.lease_id JOIN rooms r ON r.room_id = l.room_id JOIN properties p ON p.property_id = r.property_id WHERE p.landlord_id = ? AND m.verification_status = 'verified' AND m.paid_at >= ? AND m.paid_at < ?", [$landlordId, date('Y-m-01'), (new DateTimeImmutable('first day of next month'))->format('Y-m-d')]);
$overdue = rows("SELECT v.invoice_id, v.billing_month, v.due_date, v.balance_due, p.property_name, r.room_number, CONCAT(u.first_name, ' ', u.last_name) AS tenant_name
    FROM vw_invoice_summary v JOIN rooms r ON r.room_id = v.room_id JOIN properties p ON p.property_id = r.property_id JOIN users u ON u.user_id = v.student_id
    WHERE p.landlord_id = ? AND v.invoice_status = 'overdue' AND v.balance_due > 0 ORDER BY v.due_date, v.invoice_id", [$landlordId]);
$properties = rows('SELECT p.property_id, p.property_name, p.property_type, p.approval_status,
    (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.property_id) AS room_count,
    (SELECT COUNT(*) FROM property_images pi WHERE pi.property_id = p.property_id) AS photo_count
    FROM properties p WHERE p.landlord_id = ? ORDER BY p.updated_at DESC, p.property_name', [$landlordId]);
$permitCount = (int) scalar('SELECT COUNT(*) FROM landlord_permits WHERE landlord_id = ?', [$landlordId]);
$verifiedPermitCount = (int) scalar("SELECT COUNT(*) FROM landlord_permits WHERE landlord_id = ? AND verification_status = 'verified'", [$landlordId]);

$pageTitle = 'Landlord Dashboard';
$pageDescription = 'Post boarding houses or apartments, upload photos, manage occupancy, and track rent.';
$pageActions = static function (): void {
    ?>
    <a class="button" href="<?= e(url('landlord/properties.php?new=1#property-form')) ?>"><?= icon('plus') ?> Post Property</a>
    <a class="button secondary" href="<?= e(url('landlord/verification.php')) ?>"><?= icon('shield') ?> Optional Permits</a>
    <?php
};
require __DIR__ . '/../includes/portal_header.php';
?>

<?php if (!$verified || !$properties): ?>
<section class="panel onboarding-panel">
    <div class="panel-head">
        <h2><?= $properties ? 'Getting your listing live on Dayon' : 'Welcome! Start listing your boarding house or apartment' ?></h2>
        <?= landlord_badge($profile['verification_status'] ?? 'pending') ?>
    </div>
    <p class="muted">You can post your property details and photos right away. <strong>Business permits are not required</strong> — you may optionally add local Antique permits anytime for verified trust badges.</p>
    <ol class="onboarding-steps">
        <li class="<?= $properties ? 'is-done' : 'is-current' ?>">
            <span class="step-badge"><?= $properties ? icon('check') : '1' ?></span>
            <div>
                <strong>Add property details &amp; photos</strong>
                <span>Fill in as much or as little as you want — students only see what you enter.</span>
            </div>
            <a class="button small <?= $properties ? 'secondary' : '' ?>" href="<?= e(url('landlord/properties.php?new=1#property-form')) ?>"><?= $properties ? 'Add another' : 'Post property' ?></a>
        </li>
        <li class="<?= (int) $occupancy['capacity'] > 0 ? 'is-done' : '' ?>">
            <span class="step-badge"><?= (int) $occupancy['capacity'] > 0 ? icon('check') : '2' ?></span>
            <div>
                <strong>Add rooms &amp; bed rates (optional)</strong>
                <span>Add individual rooms so students can see live bed vacancies and request visits or holds.</span>
            </div>
            <a class="button small secondary" href="<?= e(url('landlord/rooms.php')) ?>">Manage rooms</a>
        </li>
        <li class="<?= $permitCount > 0 ? 'is-done' : '' ?>">
            <span class="step-badge"><?= $permitCount > 0 ? icon('check') : '3' ?></span>
            <div>
                <strong>Optional: Upload Antique permits</strong>
                <span>Barangay Clearance, Mayor's Permit, BFP Fire Safety, Sanitary Permit — never required.</span>
            </div>
            <a class="button small secondary" href="<?= e(url('landlord/verification.php')) ?>"><?= $permitCount ? $permitCount . ' on file' : 'Add permits' ?></a>
        </li>
    </ol>
</section>
<?php endif; ?>

<div class="stats-grid">
    <article class="stat-card"><span><?= icon('building') ?> Properties</span><strong><?= count($properties) ?></strong><small><?= count(array_filter($properties, static fn ($p) => $p['approval_status'] === 'approved')) ?> published</small></article>
    <article class="stat-card"><span><?= icon('bed') ?> Occupied Beds</span><strong><?= e($occupancy['occupied']) ?> / <?= e($occupancy['capacity']) ?></strong><small><?= max(0, (int) $occupancy['capacity'] - (int) $occupancy['occupied']) ?> open</small></article>
    <article class="stat-card"><span><?= icon('calendar') ?> Pending Requests</span><strong<?= $pending ? ' class="metric-watch"' : '' ?>><?= $pending ?></strong><small><a href="<?= e(url('landlord/reservations.php')) ?>">Review requests</a></small></article>
    <article class="stat-card"><span><?= icon('shield') ?> Antique Permits</span><strong><?= $permitCount ?></strong><small><?= $verifiedPermitCount ?> verified · Optional</small></article>
    <article class="stat-card"><span><?= icon('wallet') ?> Received in <?= e(date('F')) ?></span><strong><?= e(money($income)) ?></strong><small><a href="<?= e(url('landlord/billing.php')) ?>">Open billing</a></small></article>
</div>

<section class="panel">
    <div class="section-head actions">
        <div><h2>Your Boarding Houses &amp; Apartments</h2><p class="muted">Edit details, manage photo galleries, or preview how students see each listing.</p></div>
        <a class="button small" href="<?= e(url('landlord/properties.php?new=1#property-form')) ?>"><?= icon('plus') ?> Add Property</a>
    </div>
    <?php if (!$properties): ?><p class="empty-state">Add your first boarding house or apartment to start receiving student inquiries.</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Property</th><th>Type</th><th>Photos</th><th>Rooms</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody><?php foreach ($properties as $property): ?>
                <tr>
                    <td><strong><a href="<?= e(url('landlord/properties.php?edit=' . $property['property_id'] . '#property-form')) ?>"><?= e($property['property_name']) ?></a></strong></td>
                    <td><?= e(property_type_label($property['property_type'] ?? 'boarding_house')) ?></td>
                    <td><?= (int) $property['photo_count'] ?></td>
                    <td><?= (int) $property['room_count'] ?></td>
                    <td><?= landlord_badge($property['approval_status']) ?></td>
                    <td><div class="actions">
                        <a class="button small secondary" href="<?= e(url('landlord/properties.php?edit=' . $property['property_id'] . '#property-form')) ?>">Edit &amp; Photos</a>
                        <a class="button small secondary" href="<?= e(url('landlord/rooms.php?property_id=' . $property['property_id'])) ?>">Rooms</a>
                        <a class="button small secondary" href="<?= e(url('property.php?id=' . $property['property_id'])) ?>">Preview</a>
                    </div></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="section-head actions"><div><h2>Overdue Accounts</h2><p class="muted">Invoices with an unpaid balance after their due date.</p></div><a class="button secondary small" href="<?= e(url('landlord/billing.php')) ?>">Open billing</a></div>
    <?php if (!$overdue): ?><p class="empty-state">There are no overdue balances.</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Tenant</th><th>Property / Room</th><th>Due Date</th><th>Balance</th></tr></thead>
            <tbody><?php foreach ($overdue as $invoice): ?>
                <tr><td><a href="<?= e(url('landlord/billing.php?month=' . substr($invoice['billing_month'], 0, 7) . '&invoice=' . $invoice['invoice_id'])) ?>"><?= e($invoice['tenant_name']) ?></a></td><td><?= e($invoice['property_name']) ?> · <?= e($invoice['room_number']) ?></td><td><?= e(date('M j, Y', strtotime($invoice['due_date']))) ?></td><td><?= e(money($invoice['balance_due'])) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
