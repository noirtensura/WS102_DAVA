<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_support.php';

$student = require_role('student');
$lease = active_lease((int) $student['user_id']);
$requests = rows(
    "SELECT rs.*, p.property_id, p.property_name, r.room_number,
            (p.approval_status = 'approved' AND lp.verification_status = 'verified' AND u.account_status = 'active') AS visible
     FROM reservations rs JOIN rooms r ON r.room_id = rs.room_id JOIN properties p ON p.property_id = r.property_id
     JOIN landlord_profiles lp ON lp.landlord_id = p.landlord_id JOIN users u ON u.user_id = p.landlord_id
     WHERE rs.student_id = ? ORDER BY rs.created_at DESC, rs.reservation_id DESC",
    [$student['user_id']]
);

$pageTitle = 'My requests';
$pageDescription = 'Track your ocular visits and bed reservation requests.';
require __DIR__ . '/../includes/portal_header.php';
?>

<?php if (!$requests): ?>
    <section class="panel empty-state">
        <h2>No requests yet</h2>
        <p><?= $lease ? 'Your previous visit and bed reservation requests will appear here.' : 'Open a listing to request an ocular visit or ask the landlord to reserve a bed.' ?></p>
        <?php if (!$lease): ?><a class="button" href="<?= e(url('index.php')) ?>">Find boarding houses</a><?php endif; ?>
    </section>
<?php else: ?>
    <section class="panel" aria-label="Visit and bed reservation history">
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Boarding house</th><th scope="col">Request</th><th scope="col">Scheduled</th><th scope="col">Status</th><?php if (!$lease): ?><th scope="col">Action</th><?php endif; ?></tr></thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <?php if ($request['visible']): ?>
                                    <a href="<?= e(url('property.php?id=' . $request['property_id'])) ?>"><?= e($request['property_name']) ?></a>
                                <?php else: ?>
                                    <?= e($request['property_name']) ?>
                                <?php endif; ?>
                                <div class="muted">Room <?= e($request['room_number']) ?></div>
                            </td>
                            <td><?= e(student_request_name($request['request_type'])) ?><?php if ($request['notes']): ?><div class="muted"><?= nl2br(e($request['notes'])) ?></div><?php endif; ?></td>
                            <td><?= e($request['request_type'] === 'bed_reservation' ? student_short_date($request['scheduled_at']) : student_date_time($request['scheduled_at'])) ?></td>
                            <td><span class="badge <?= e($request['status']) ?>"><?= e(student_status($request['status'])) ?></span></td>
                            <?php if (!$lease): ?>
                                <td>
                                    <?php if (in_array($request['status'], ['pending', 'approved'], true)): ?>
                                        <form method="post" action="<?= e(url('actions/reservation_action.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="operation" value="cancel">
                                            <input type="hidden" name="reservation_id" value="<?= e($request['reservation_id']) ?>">
                                            <button class="button secondary small" type="submit">Cancel request</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
