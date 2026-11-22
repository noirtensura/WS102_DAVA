<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$verified = landlord_verified($landlordId);
$today = date('Y-m-d');
$reservationId = filter_input(INPUT_GET, 'reservation', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$chosenRoom = (int) filter_input(INPUT_GET, 'room', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$draft = $reservationId ? row("SELECT q.room_id, q.scheduled_at, u.email FROM reservations q JOIN rooms r ON r.room_id = q.room_id JOIN properties p ON p.property_id = r.property_id JOIN users u ON u.user_id = q.student_id WHERE q.reservation_id = ? AND p.landlord_id = ? AND q.request_type = 'bed_reservation' AND q.status = 'approved' AND q.scheduled_at >= CURDATE()", [$reservationId, $landlordId]) : null;
$rooms = rows('SELECT v.room_id, v.room_number, v.available_beds, v.monthly_rate_per_bed, p.property_name FROM vw_room_vacancy v JOIN properties p ON p.property_id = v.property_id WHERE p.landlord_id = ? AND v.is_active = 1 AND v.available_beds > 0 ORDER BY p.property_name, v.room_number', [$landlordId]);
$leases = rows("SELECT l.*, r.room_number, p.property_name, CONCAT(u.first_name, ' ', u.last_name) AS tenant_name, u.email
    FROM leases l JOIN rooms r ON r.room_id = l.room_id JOIN properties p ON p.property_id = r.property_id JOIN users u ON u.user_id = l.student_id
    WHERE p.landlord_id = ? ORDER BY (l.lease_status = 'active') DESC, l.move_in_date DESC, l.lease_id DESC", [$landlordId]);
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editing = null;
if ($verified && $editId) {
    foreach ($leases as $lease) {
        if ((int) $lease['lease_id'] === $editId && $lease['lease_status'] === 'active') {
            $editing = $lease;
            break;
        }
    }
}
$pageTitle = 'Leases';
$pageDescription = 'Activate move-ins, manage billing terms, and record move-outs and deposit refunds.';
$pageScripts = ['landlord/forms.js'];
require __DIR__ . '/../includes/portal_header.php';
?>
<?php if (!$verified) landlord_verification_notice(); ?>
<?php if ($verified): ?>
<section class="panel" id="lease-form">
    <h2>Activate a lease</h2>
    <?php if (!$rooms): ?><p class="empty-state">An active room needs an available bed before you can create a lease.</p>
    <?php else: ?>
    <?php if ($draft): ?><p class="muted">Approved hold for <?= e(date('M j, Y', strtotime($draft['scheduled_at']))) ?>.</p><?php endif; ?>
    <form class="form-grid" method="post" action="<?= e(url('actions/lease_action.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="operation" value="activate">
        <label class="form-field">Student account email<input type="email" name="student_email" required maxlength="150" value="<?= e($draft['email'] ?? '') ?>"></label>
        <label class="form-field">Room<select name="room_id" required><option value="">Choose a room</option>
            <?php foreach ($rooms as $room): ?><option value="<?= e($room['room_id']) ?>" <?= (int) ($draft['room_id'] ?? $chosenRoom) === (int) $room['room_id'] ? 'selected' : '' ?>><?= e($room['property_name']) ?> · <?= e($room['room_number']) ?> · <?= e($room['available_beds']) ?> open · <?= e(money($room['monthly_rate_per_bed'])) ?></option><?php endforeach; ?>
        </select></label>
        <label class="form-field">Move-in date<input type="date" name="move_in_date" max="<?= e($today) ?>" value="<?= e($today) ?>" required></label>
        <label class="form-field">Billing due day (1–28)<input type="number" name="billing_due_day" min="1" max="28" value="5" required></label>
        <label class="form-field">Agreed monthly rent (₱)<input type="number" name="agreed_monthly_rent" min="0.01" max="99999999.99" step="0.01" placeholder="Use room rate"></label>
        <label class="form-field">Advance rent received (₱)<input type="number" name="advance_rent_amount" min="0" max="99999999.99" step="0.01" value="0"></label>
        <label class="form-field">Security deposit received (₱)<input type="number" name="security_deposit_amount" min="0" max="99999999.99" step="0.01" value="0"></label>
        <div class="actions span-2"><button type="submit" class="button">Activate lease</button></div>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php if ($editing): ?>
<div class="grid-2" id="lease-actions">
    <section class="panel">
        <h2>Update terms</h2><p class="muted"><?= e($editing['tenant_name']) ?> · <?= e($editing['property_name']) ?> · <?= e($editing['room_number']) ?></p>
        <form class="form-grid" method="post" action="<?= e(url('actions/lease_action.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="operation" value="update"><input type="hidden" name="lease_id" value="<?= e($editing['lease_id']) ?>">
            <label class="form-field">Monthly rent (₱)<input type="number" name="agreed_monthly_rent" min="0.01" max="99999999.99" step="0.01" required value="<?= e($editing['agreed_monthly_rent']) ?>"></label>
            <label class="form-field">Due day<input type="number" name="billing_due_day" min="1" max="28" required value="<?= e($editing['billing_due_day']) ?>"></label>
            <label class="form-field">Advance rent (₱)<input type="number" name="advance_rent_amount" min="0" max="99999999.99" step="0.01" required value="<?= e($editing['advance_rent_amount']) ?>"></label>
            <label class="form-field">Security deposit (₱)<input type="number" name="security_deposit_amount" min="0" max="99999999.99" step="0.01" required value="<?= e($editing['security_deposit_amount']) ?>"></label>
            <div class="actions span-2"><button class="button secondary" type="submit">Save terms</button></div>
        </form>
    </section>
    <section class="panel">
        <h2>Close lease</h2><p class="muted">Closing this lease makes the bed available immediately.</p>
        <form class="form-grid" method="post" action="<?= e(url('actions/lease_action.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="operation" value="move_out"><input type="hidden" name="lease_id" value="<?= e($editing['lease_id']) ?>">
            <label class="form-field">Move-out date<input type="date" name="move_out_date" min="<?= e($editing['move_in_date']) ?>" max="<?= e($today) ?>" required value="<?= e($today) ?>"></label>
            <label class="form-field">Deposit refund (₱)<input type="number" name="deposit_refunded_amount" min="0" max="<?= e($editing['security_deposit_amount']) ?>" step="0.01" value="0" required></label>
            <label class="form-field">Reason<select name="lease_status"><option value="ended">Moved out</option><option value="terminated">Terminated</option></select></label>
            <div class="actions span-2"><button class="button danger" type="submit" data-confirm="Close this lease and release its bed?">Close lease</button></div>
        </form>
    </section>
</div>
<?php endif; ?>
<section class="panel">
    <h2>Lease register</h2>
    <?php if (!$leases): ?><p class="empty-state">There are no leases for your properties yet.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Tenant</th><th>Property / room</th><th>Dates</th><th>Monthly rent</th><th>Deposits</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($leases as $lease): ?>
            <tr><td><strong><?= e($lease['tenant_name']) ?></strong><br><small><?= e($lease['email']) ?></small></td><td><?= e($lease['property_name']) ?> · <?= e($lease['room_number']) ?></td>
                <td><?= e(date('M j, Y', strtotime($lease['move_in_date']))) ?><?php if ($lease['move_out_date']): ?><br><small>To <?= e(date('M j, Y', strtotime($lease['move_out_date']))) ?></small><?php endif; ?></td>
                <td><?= e(money($lease['agreed_monthly_rent'])) ?><br><small>Due day <?= e($lease['billing_due_day']) ?></small></td>
                <td><small>Advance <?= e(money($lease['advance_rent_amount'])) ?><br>Security <?= e(money($lease['security_deposit_amount'])) ?><?php if ($lease['deposit_refunded_amount'] !== null): ?><br>Refund <?= e(money($lease['deposit_refunded_amount'])) ?><?php endif; ?></small></td>
                <td><?= landlord_badge($lease['lease_status']) ?><?php if ($verified && $lease['lease_status'] === 'active'): ?><br><a href="<?= e(url('landlord/leases.php?edit=' . $lease['lease_id'] . '#lease-actions')) ?>">Manage</a><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
