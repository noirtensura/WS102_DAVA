<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$verified = landlord_verified($landlordId);
$requests = rows("SELECT q.*, r.room_number, p.property_name, CONCAT(u.first_name, ' ', u.last_name) AS student_name,
    EXISTS (SELECT 1 FROM leases l WHERE l.student_id = q.student_id AND l.lease_status = 'active') AS student_has_lease
    FROM reservations q JOIN rooms r ON r.room_id = q.room_id JOIN properties p ON p.property_id = r.property_id JOIN users u ON u.user_id = q.student_id
    WHERE p.landlord_id = ? ORDER BY (q.status = 'pending') DESC, q.scheduled_at DESC, q.reservation_id DESC", [$landlordId]);
$pageTitle = 'Visits & reservations';
$pageDescription = 'Review student inspection visits and bed holds for your rooms.';
require __DIR__ . '/../includes/portal_header.php';
?>
<?php if (!$verified) landlord_verification_notice(); ?>
<section class="panel">
    <h2>Student requests</h2>
    <?php if (!$requests): ?><p class="empty-state">There are no requests for your properties yet.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Student</th><th>Property / room</th><th>Request</th><th>Scheduled for</th><th>Status</th><th>Action</th></tr></thead>
        <tbody><?php foreach ($requests as $request): ?>
            <tr>
                <td><strong><?= e($request['student_name']) ?></strong><?php if ($request['notes']): ?><p class="muted"><?= e($request['notes']) ?></p><?php endif; ?></td>
                <td><?= e($request['property_name']) ?> · <?= e($request['room_number']) ?></td>
                <td><?= $request['request_type'] === 'ocular_visit' ? 'Ocular visit' : 'Bed hold' ?></td>
                <td><?= e(date($request['request_type'] === 'bed_reservation' ? 'M j, Y' : 'M j, Y · g:i A', strtotime($request['scheduled_at']))) ?></td>
                <td><?= landlord_badge($request['status']) ?></td>
                <td>
                    <?php if ($verified && $request['status'] === 'pending'): ?>
                        <form class="actions" method="post" action="<?= e(url('actions/landlord_reservation.php')) ?>">
                            <?= csrf_field() ?><input type="hidden" name="reservation_id" value="<?= e($request['reservation_id']) ?>">
                            <button class="button small" name="decision" value="approved">Approve</button><button class="button secondary small" name="decision" value="declined">Decline</button>
                        </form>
                    <?php elseif ($verified && $request['status'] === 'approved' && $request['request_type'] === 'bed_reservation' && !$request['student_has_lease'] && substr($request['scheduled_at'], 0, 10) >= date('Y-m-d')): ?>
                        <a href="<?= e(url('landlord/leases.php?reservation=' . $request['reservation_id'] . '#lease-form')) ?>">Create lease</a>
                    <?php else: ?><span class="muted">—</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
