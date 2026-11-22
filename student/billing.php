<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_support.php';

$student = require_role('student');
$studentId = (int) $student['user_id'];
$lease = student_active_lease_or_redirect($studentId);
$invoices = rows(
    'SELECT s.*, p.property_name, r.room_number
     FROM vw_invoice_summary s JOIN rooms r ON r.room_id = s.room_id JOIN properties p ON p.property_id = r.property_id
     WHERE s.student_id = ? ORDER BY s.billing_month DESC, s.invoice_id DESC',
    [$studentId]
);
$items = [];
foreach (rows('SELECT i.* FROM invoice_items i JOIN rent_invoices ri ON ri.invoice_id = i.invoice_id JOIN leases l ON l.lease_id = ri.lease_id WHERE l.student_id = ? ORDER BY i.item_id', [$studentId]) as $item) {
    $items[$item['invoice_id']][] = $item;
}
$payments = [];
$pending = [];
foreach (rows('SELECT p.* FROM payments p JOIN rent_invoices ri ON ri.invoice_id = p.invoice_id JOIN leases l ON l.lease_id = ri.lease_id WHERE l.student_id = ? ORDER BY p.paid_at DESC, p.payment_id DESC', [$studentId]) as $payment) {
    $payments[$payment['invoice_id']][] = $payment;
    if ($payment['verification_status'] === 'pending') {
        $pending[$payment['invoice_id']] = ($pending[$payment['invoice_id']] ?? 0) + (float) $payment['amount_paid'];
    }
}
$openTotal = 0.0;
$overdueCount = 0;
$pendingTotal = 0.0;
foreach ($invoices as $invoice) {
    if ((int) $invoice['lease_id'] === (int) $lease['lease_id']) {
        $openTotal += max(0, (float) $invoice['balance_due']);
        $overdueCount += (int) ($invoice['invoice_status'] === 'overdue');
        $pendingTotal += $pending[$invoice['invoice_id']] ?? 0;
    }
}
$methods = ['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank transfer'];
$pageTitle = 'Rent and payments';
$pageDescription = 'Review your itemized monthly bills, submit transfer proof, and open verified receipts.';
$pageScripts = ['student/upload-preview.js'];
require __DIR__ . '/../includes/portal_header.php';
?>

<div class="stats-grid">
    <article class="stat-card"><span>Outstanding balance</span><strong><?= money($openTotal) ?></strong></article>
    <article class="stat-card"><span>Past-due invoices</span><strong><?= $overdueCount ?></strong></article>
    <article class="stat-card"><span>Pending verification</span><strong><?= money($pendingTotal) ?></strong></article>
</div>

<?php if (!$invoices): ?>
    <section class="panel empty-state">
        <h2>No invoices yet</h2>
        <p>Your monthly bills will appear here when the landlord generates them.</p>
    </section>
<?php endif; ?>

<?php foreach ($invoices as $invoice): ?>
    <?php
    $id = (int) $invoice['invoice_id'];
    $balance = (float) $invoice['balance_due'];
    $available = max(0, round($balance - ($pending[$id] ?? 0), 2));
    $currentInvoice = (int) $invoice['lease_id'] === (int) $lease['lease_id'];
    ?>
    <section class="panel" id="invoice-<?= $id ?>" aria-labelledby="invoice-title-<?= $id ?>">
        <div class="actions">
            <h2 id="invoice-title-<?= $id ?>"><?= e(student_billing_month($invoice['billing_month'])) ?></h2>
            <span class="badge <?= e($invoice['invoice_status']) ?>"><?= e(student_status($invoice['invoice_status'])) ?></span>
        </div>
        <p class="muted"><?= e($invoice['property_name']) ?> · Room <?= e($invoice['room_number']) ?> · Due <?= e(student_short_date($invoice['due_date'])) ?></p>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Charge</th><th scope="col">Description</th><th scope="col">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($items[$id] ?? [] as $item): ?>
                        <tr><td><?= e(student_status($item['charge_type'])) ?></td><td><?= e($item['description']) ?></td><td><?= money($item['amount']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th scope="row" colspan="2">Total billed</th><td><?= money($invoice['total_billed']) ?></td></tr>
                    <tr><th scope="row" colspan="2">Verified payments</th><td><?= money($invoice['total_paid']) ?></td></tr>
                    <tr><th scope="row" colspan="2">Balance due</th><td><strong><?= money($invoice['balance_due']) ?></strong></td></tr>
                </tfoot>
            </table>
        </div>

        <?php if (!empty($payments[$id])): ?>
            <h3>Payments</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th scope="col">Date</th><th scope="col">Method</th><th scope="col">Amount</th><th scope="col">Status</th><th scope="col">Documents</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments[$id] as $payment): ?>
                            <tr>
                                <td><?= e(student_short_date($payment['paid_at'])) ?></td>
                                <td><?= e($methods[$payment['payment_method']]) ?><?php if ($payment['reference_number']): ?><div class="muted"><?= e($payment['reference_number']) ?></div><?php endif; ?></td>
                                <td><?= money($payment['amount_paid']) ?></td>
                                <td><span class="badge <?= e($payment['verification_status']) ?>"><?= e(student_status($payment['verification_status'])) ?></span></td>
                                <td><div class="actions">
                                    <?php if ($payment['proof_image_path']): ?><a href="<?= e(media_url($payment['proof_image_path'])) ?>">Proof</a><?php endif; ?>
                                    <?php if ($payment['verification_status'] === 'verified' && $payment['receipt_number']): ?>
                                        <a class="button secondary small" href="<?= e(url('student/receipt.php?id=' . $payment['payment_id'])) ?>">View receipt</a>
                                    <?php elseif (!$payment['proof_image_path']): ?><span class="muted">—</span><?php endif; ?>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($currentInvoice && $available > 0): ?>
            <details>
                <summary>Submit payment proof</summary>
                <form method="post" enctype="multipart/form-data" action="<?= e(url('actions/student_payment.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="invoice_id" value="<?= $id ?>">
                    <div class="form-grid">
                        <label class="form-field">Payment method<select name="payment_method" required><option value="gcash">GCash</option><option value="bank_transfer">Bank transfer</option></select></label>
                        <label class="form-field">Amount paid (PHP)<input type="number" name="amount_paid" step="0.01" min="0.01" max="<?= e(number_format($available, 2, '.', '')) ?>" required></label>
                        <label class="form-field">Transaction reference<input type="text" name="reference_number" maxlength="100" required></label>
                        <label class="form-field">Receipt screenshot
                            <input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required data-upload-preview="proof-preview-<?= $id ?>">
                            <small class="muted">JPEG, PNG or WebP, up to 5 MB.</small>
                        </label>
                    </div>
                    <img class="student-preview" id="proof-preview-<?= $id ?>" alt="Payment proof preview" width="200" hidden>
                    <div class="actions"><button class="button" type="submit">Send payment proof</button></div>
                </form>
            </details>
        <?php elseif ($currentInvoice && $balance > 0): ?>
            <p class="muted">Payment proofs cover the remaining balance and are awaiting verification.</p>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
