<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_support.php';

$student = require_role('student');
$studentId = (int) $student['user_id'];
$lease = active_lease($studentId);
$recentRequests = rows(
    'SELECT rs.request_type, rs.scheduled_at, rs.status, p.property_name, r.room_number
     FROM reservations rs JOIN rooms r ON rs.room_id = r.room_id JOIN properties p ON r.property_id = p.property_id
     WHERE rs.student_id = ? ORDER BY rs.created_at DESC, rs.reservation_id DESC LIMIT 4',
    [$studentId]
);
$pageTitle = 'Welcome, ' . $student['first_name'];
$pageDescription = $lease ? '' : 'Find and compare boarding houses in ' . DAYON_LOCATION . '.';
$pageActions = static function () use ($student, $lease): void {
    ?>
    <?php if ($lease): ?><a class="head-icon" href="<?= e(url('student/maintenance.php#advisories-heading')) ?>" aria-label="Property advisories"><?= icon('bell') ?></a><?php endif; ?>
    <a class="avatar" href="<?= e(url('student/profile.php')) ?>" aria-label="My profile"><?= e(user_initials($student)) ?></a>
    <?php
};

if ($lease) {
    $photo = scalar('SELECT image_path FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, image_id LIMIT 1', [$lease['property_id']]);
    $invoice = row('SELECT * FROM vw_invoice_summary WHERE lease_id = ? AND billing_month = ?', [$lease['lease_id'], date('Y-m-01')]);
    $charges = $invoice ? rows("SELECT charge_type, SUM(amount) AS amount FROM invoice_items WHERE invoice_id = ?
        GROUP BY charge_type ORDER BY FIELD(charge_type, 'base_rent', 'electricity', 'water', 'wifi', 'adjustment', 'penalty')", [$invoice['invoice_id']]) : [];
    $payments = rows('SELECT p.* FROM payments p JOIN rent_invoices ri USING (invoice_id) JOIN leases l USING (lease_id)
        WHERE l.student_id = ? ORDER BY p.paid_at DESC, p.payment_id DESC LIMIT 5', [$studentId]);
    $ticket = row('SELECT category, description, ticket_status, created_at FROM maintenance_requests
        WHERE lease_id = ? ORDER BY created_at DESC, ticket_id DESC LIMIT 1', [$lease['lease_id']]);
    $advisories = rows('SELECT title, message, created_at FROM announcements WHERE property_id = ? ORDER BY created_at DESC, announcement_id DESC LIMIT 3', [$lease['property_id']]);
    $chargeLabels = ['base_rent' => 'Base Rent', 'electricity' => 'Electricity', 'water' => 'Water', 'wifi' => 'Wi-Fi', 'adjustment' => 'Adjustments', 'penalty' => 'Penalties'];
    $methods = ['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank Transfer'];
    $invoiceLink = 'student/billing.php' . ($invoice ? '#invoice-' . $invoice['invoice_id'] : '');
} else {
    $savedCount = (int) scalar('SELECT COUNT(*) FROM bookmarks WHERE student_id = ?', [$studentId]);
    $pendingCount = (int) scalar("SELECT COUNT(*) FROM reservations WHERE student_id = ? AND status = 'pending'", [$studentId]);
}
require __DIR__ . '/../includes/portal_header.php';
?>

<?php if ($lease): ?>
    <div class="dashboard-pair">
        <section class="panel dash-card" id="active-lease">
            <div class="panel-head">
                <h2>Active Lease</h2>
                <span class="badge active">Active</span>
            </div>
            <div class="lease-card-grid">
                <?php if ($photo): ?>
                    <img src="<?= e(media_url($photo)) ?>" alt="<?= e($lease['property_name']) ?>">
                <?php else: ?><div class="image-placeholder" aria-hidden="true"><?= icon('home') ?></div><?php endif; ?>
                <div>
                    <h3><?= e($lease['property_name']) ?></h3>
                    <p>Room <?= e($lease['room_number']) ?></p>
                    <p class="muted">Move-in <?= e(student_short_date($lease['move_in_date'])) ?></p>
                    <p class="lease-rate"><strong><?= e(money($lease['agreed_monthly_rent'])) ?></strong> / month</p>
                </div>
            </div>
            <details class="lease-terms">
                <summary>View Lease Terms</summary>
                <p>Advance rent <?= e(money($lease['advance_rent_amount'])) ?></p>
                <p>Security deposit <?= e(money($lease['security_deposit_amount'])) ?></p>
                <p>Rent is due on day <?= (int) $lease['billing_due_day'] ?> of each month.</p>
            </details>
        </section>
        <section class="panel dash-card">
            <div class="panel-head">
                <h2>Current Month Bill · <?= e(date('F')) ?></h2>
                <?php if ($invoice): ?>
                    <span class="badge <?= e($invoice['invoice_status'] === 'paid' ? 'paid' : ($invoice['invoice_status'] === 'overdue' ? 'overdue' : 'due')) ?>">
                        <?= $invoice['invoice_status'] === 'paid' ? 'Paid' : (($invoice['invoice_status'] === 'overdue' ? 'Overdue · ' : 'Due ') . e(date('M j', strtotime($invoice['due_date'])))) ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php if ($invoice): ?>
                <dl class="line-totals">
                    <?php foreach ($charges as $charge): ?>
                        <div><dt><?= e($chargeLabels[$charge['charge_type']]) ?></dt><dd><?= e(money($charge['amount'])) ?></dd></div>
                    <?php endforeach; ?>
                    <div class="total-line"><dt>Total Due</dt><dd><?= e(money($invoice['total_billed'])) ?></dd></div>
                    <div class="mini-line"><dt>Paid</dt><dd><?= e(money($invoice['total_paid'])) ?></dd></div>
                    <div class="mini-line"><dt>Balance</dt><dd><?= e(money($invoice['balance_due'])) ?></dd></div>
                </dl>
            <?php else: ?>
                <p class="muted">Your landlord has yet to generate this month’s invoice.</p>
            <?php endif; ?>
            <div class="panel-actions-bottom actions">
                <a class="button secondary small" href="<?= e(url($invoiceLink)) ?>">View Full Ledger</a>
                <?php if ($invoice && $invoice['balance_due'] > 0): ?><a class="button small" href="<?= e(url($invoiceLink)) ?>">Submit Payment Proof</a><?php endif; ?>
            </div>
        </section>
    </div>

    <div class="dashboard-pair asymmetric">
        <section class="panel">
            <div class="panel-head"><h2>Recent Payments &amp; Receipts</h2></div>
            <?php if (!$payments): ?><p class="muted">Your submitted and verified payments will appear here.</p><?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr><th scope="col">Date</th><th scope="col">Method</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Receipt</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr><td><?= e(student_short_date($payment['paid_at'])) ?></td>
                                <td><?= e($methods[$payment['payment_method']]) ?></td>
                                <td><?= e(money($payment['amount_paid'])) ?></td>
                                <td><span class="badge <?= e($payment['verification_status']) ?>"><?= e(student_status($payment['verification_status'])) ?></span></td>
                                <td><?php if ($payment['verification_status'] === 'verified' && $payment['receipt_number']): ?>
                                    <a class="button small secondary" href="<?= e(url('student/receipt.php?id=' . $payment['payment_id'])) ?>">View Receipt</a>
                                <?php else: ?>—<?php endif; ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </section>
        <section class="panel dash-card">
            <div class="panel-head"><h2>Maintenance Tickets</h2></div>
            <div class="maintenance-summary">
                <?php if ($ticket): ?>
                    <h3><?= e(ucfirst($ticket['category'])) ?> Request</h3>
                    <span class="muted">Reported <?= e(student_short_date($ticket['created_at'])) ?></span>
                    <p class="preserve-lines"><?= e($ticket['description']) ?></p>
                    <span class="badge <?= e($ticket['ticket_status']) ?>"><?= e(student_status($ticket['ticket_status'])) ?></span>
                <?php else: ?>
                    <p class="muted">You have no repair requests for this lease.</p>
                <?php endif; ?>
            </div>
            <div class="panel-actions-bottom">
                <a class="button small wide" href="<?= e(url('student/maintenance.php#repair-heading')) ?>">Report an Issue</a>
            </div>
        </section>
    </div>
    <section class="panel">
        <div class="panel-head"><h2>Property Advisories</h2><a class="button small secondary" href="<?= e(url('student/maintenance.php#advisories-heading')) ?>">View Advisories</a></div>
        <?php if (!$advisories): ?><p class="muted">Your landlord has yet to post an advisory.</p><?php else: ?>
            <div class="announcements-list">
                <?php foreach ($advisories as $advisory): ?>
                    <article><h3><?= e($advisory['title']) ?></h3><p class="preserve-lines"><?= e($advisory['message']) ?></p><small class="muted"><?= e(student_short_date($advisory['created_at'])) ?></small></article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <div class="metric-grid three">
        <article class="stat-card"><span>Saved Boarding Houses</span><strong><?= $savedCount ?></strong></article>
        <article class="stat-card"><span>Pending Requests</span><strong><?= $pendingCount ?></strong></article>
        <article class="stat-card"><span>Account Mode</span><strong>Finder</strong></article>
    </div>
    <section class="panel">
        <div class="panel-head"><h2>Your Housing Search</h2></div>
        <p>Find a verified boarding house, compare your shortlist, or ask the landlord for a visit.</p>
        <div class="panel-actions-bottom actions">
            <a class="button" href="<?= e(url('index.php#listings')) ?>">Find Rooms</a>
            <a class="button secondary" href="<?= e(url('student/bookmarks.php')) ?>">Saved &amp; Compare</a>
        </div>
    </section>
<?php endif; ?>

<?php if ($recentRequests || !$lease): ?>
<section class="panel">
    <div class="panel-head"><h2>Recent Requests</h2><a class="button secondary small" href="<?= e(url('student/reservations.php')) ?>">All Requests</a></div>
    <?php if (!$recentRequests): ?>
        <p class="muted">Your visit and bed reservation requests will appear here.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th scope="col">Boarding House</th><th scope="col">Request</th><th scope="col">Scheduled</th><th scope="col">Status</th></tr></thead>
            <tbody>
                <?php foreach ($recentRequests as $request): ?>
                    <tr>
                        <td><?= e($request['property_name']) ?> · Room <?= e($request['room_number']) ?></td>
                        <td><?= e(student_request_name($request['request_type'])) ?></td>
                        <td><?= e($request['request_type'] === 'bed_reservation' ? student_short_date($request['scheduled_at']) : student_date_time($request['scheduled_at'])) ?></td>
                        <td><span class="badge <?= e($request['status']) ?>"><?= e(student_status($request['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
