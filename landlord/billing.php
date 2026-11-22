<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$verified = landlord_verified($landlordId);
$month = $_GET['month'] ?? date('Y-m');
if (!is_string($month) || !preg_match('/^(19\d{2}|[2-9]\d{3})-(0[1-9]|1[0-2])$/D', $month)) {
    $month = date('Y-m');
}
$invoiceId = filter_input(INPUT_GET, 'invoice', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$properties = landlord_properties($landlordId);
$rooms = rows('SELECT r.room_id, r.room_number, r.property_id, p.property_name FROM rooms r JOIN properties p ON p.property_id = r.property_id WHERE p.landlord_id = ? AND r.is_active = 1 ORDER BY p.property_name, r.room_number', [$landlordId]);
$invoices = rows("SELECT v.*, p.property_id, p.property_name, r.room_number, line.rent, line.utilities, CONCAT(u.first_name, ' ', u.last_name) AS tenant_name,
    (SELECT COUNT(*) FROM payments m WHERE m.invoice_id = v.invoice_id AND m.verification_status = 'pending') AS pending_proofs
    FROM vw_invoice_summary v JOIN rooms r ON r.room_id = v.room_id JOIN properties p ON p.property_id = r.property_id JOIN users u ON u.user_id = v.student_id
    LEFT JOIN (SELECT invoice_id, SUM(CASE WHEN charge_type = 'base_rent' THEN amount ELSE 0 END) AS rent,
        SUM(CASE WHEN charge_type IN ('water', 'electricity', 'wifi') THEN amount ELSE 0 END) AS utilities
        FROM invoice_items GROUP BY invoice_id) AS line ON line.invoice_id = v.invoice_id
    WHERE p.landlord_id = ? AND v.billing_month = ? ORDER BY (v.invoice_status = 'overdue') DESC, pending_proofs DESC, v.due_date, u.last_name, v.invoice_id", [$landlordId, $month . '-01']);
$selectedInvoice = null;
foreach ($invoices as $invoice) {
    if ((int) $invoice['invoice_id'] === $invoiceId) {
        $selectedInvoice = $invoice;
        break;
    }
}
if ($selectedInvoice) {
    $items = rows('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY item_id', [$invoiceId]);
    $payments = rows("SELECT * FROM payments WHERE invoice_id = ? ORDER BY (verification_status = 'pending') DESC, paid_at DESC, payment_id DESC", [$invoiceId]);
}
$collected = array_sum(array_column($invoices, 'total_paid'));
$outstanding = 0.0;
$overdue = [];
foreach ($invoices as $invoice) {
    $outstanding += max(0, (float) $invoice['balance_due']);
    if ($invoice['invoice_status'] === 'overdue') {
        $overdue[$invoice['student_id']] = true;
    }
}
$selectedUtilityProperty = (int) ($invoices[0]['property_id'] ?? (count($properties) === 1 ? $properties[0]['property_id'] : 0));
$pageTitle = 'Billing & Utilities';
$pageDescription = date('F Y', strtotime($month . '-01')) . ' billing period';
$pageScripts = ['landlord/billing.js'];
$pageActions = static function () use ($month, $verified, $properties): void {
    ?>
    <div class="billing-toolbar">
        <form method="get" action="<?= e(url('landlord/billing.php')) ?>">
            <label><span class="visually-hidden">Billing Month</span><input name="month" type="month" value="<?= e($month) ?>" required></label>
            <button class="button secondary" type="submit">View month</button>
        </form>
    </div>
    <?php if ($verified && $properties): ?><a class="button" href="#generate-monthly"><?= icon('plus') ?> Generate invoices</a><?php endif; ?>
    <?php
};
require __DIR__ . '/../includes/portal_header.php';
?>
<?php if (!$verified) landlord_verification_notice(); ?>
<div class="metric-grid three">
    <article class="stat-card"><span>Collected Rent &amp; Utilities</span><strong><?= e(money($collected)) ?></strong></article>
    <article class="stat-card"><span>Outstanding Balance</span><strong><?= e(money($outstanding)) ?></strong></article>
    <article class="stat-card"><span>Overdue Accounts</span><strong<?= $overdue ? ' class="metric-watch"' : '' ?>><?= count($overdue) ?></strong></article>
</div>
<?php if ($verified && $properties): ?>
<section class="panel" id="utility-splitter">
    <div class="panel-head"><h2>Shared Utility Bill Splitter</h2></div>
    <form method="post" class="utility-grid" action="<?= e(url('actions/utility_add.php')) ?>" data-utility-form data-split-url="<?= e(url('api/calc_utility_split.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <label>Property<select name="property_id" required>
            <option value="">Choose a property</option>
            <?php foreach ($properties as $property): ?>
                <option value="<?= (int) $property['property_id'] ?>"<?= $selectedUtilityProperty === (int) $property['property_id'] ? ' selected' : '' ?>><?= e($property['property_name']) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label>Room<select name="room_id"><option value="">Whole property</option>
            <?php foreach ($rooms as $room): ?>
                <option value="<?= (int) $room['room_id'] ?>" data-property="<?= (int) $room['property_id'] ?>"><?= e($room['property_name']) ?> · Room <?= e($room['room_number']) ?></option>
            <?php endforeach; ?>
        </select></label>
        <label>Split method<select name="split_mode"><option value="flat">Equal share</option><option value="submetered">By meter usage</option></select></label>
        <label>Electricity Bill (₱)<input name="electricity_amount" type="number" min="0" step="0.01" max="99999999.99" placeholder="0.00"></label>
        <label>Water Bill (₱)<input name="water_amount" type="number" min="0" step="0.01" max="99999999.99" placeholder="0.00"></label>
        <label>Bill Reference<input name="bill_reference" required maxlength="150" placeholder="Bill number or period"></label>
        <p class="utility-preview span-2" data-utility-preview role="status"></p>
        <div class="form-grid span-2" data-meter-inputs></div>
        <noscript><p class="muted span-2">Equal splitting works with JavaScript disabled. Enable JavaScript to enter meter readings.</p></noscript>
        <p class="utility-error span-2" data-utility-error role="alert"></p>
        <div class="actions span-2"><button type="submit" class="button">Apply utility charges</button></div>
    </form>
    <details class="billing-actions-panel form-disclosure" id="generate-monthly">
        <summary>Generate monthly rent invoices</summary>
        <form id="generate-invoices" method="post" class="form-grid" action="<?= e(url('actions/invoice_generate.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="month" value="<?= e($month) ?>">
            <label>Property<select name="property_id"><option value="">All properties</option>
                <?php foreach ($properties as $property): ?><option value="<?= (int) $property['property_id'] ?>"><?= e($property['property_name']) ?></option><?php endforeach; ?>
            </select></label>
            <div class="actions"><button type="submit" class="button"><?= icon('plus') ?> Generate invoices</button></div>
            <p class="muted span-2">Dayon creates missing base-rent invoices for active leases in the selected month.</p>
        </form>
    </details>
</section>
<?php endif; ?>
<?php if ($selectedInvoice): ?>
<section class="panel" id="invoice-detail">
    <div class="section-head actions"><div><h2>Invoice #<?= e($invoiceId) ?></h2><p class="muted"><?= e($selectedInvoice['tenant_name']) ?> · <?= e($selectedInvoice['property_name']) ?> · Room <?= e($selectedInvoice['room_number']) ?></p></div><?= landlord_badge($selectedInvoice['invoice_status']) ?></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Line item</th><th>Amount</th></tr></thead>
        <tbody><?php foreach ($items as $item): ?><tr><td><?= e($item['description']) ?></td><td><?= e(money($item['amount'])) ?></td></tr><?php endforeach; ?></tbody>
        <tfoot>
            <tr><th>Total billed</th><td><?= e(money($selectedInvoice['total_billed'])) ?></td></tr>
            <tr><th>Verified payments</th><td><?= e(money($selectedInvoice['total_paid'])) ?></td></tr>
            <tr><th>Balance due</th><td><strong><?= e(money($selectedInvoice['balance_due'])) ?></strong></td></tr>
        </tfoot>
    </table></div>
    <?php if ($verified): ?>
    <details class="form-disclosure">
        <summary>Add charge or adjustment</summary>
        <form method="post" class="form-grid" action="<?= e(url('actions/payment_action.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="invoice_id" value="<?= e($invoiceId) ?>"><input type="hidden" name="operation" value="charge">
            <label class="form-field">Description<input name="description" maxlength="200" required></label>
            <label class="form-field">Category<select name="charge_type"><option value="adjustment">Adjustment</option><option value="wifi">Wi-Fi</option><option value="penalty">Penalty</option></select></label>
            <label class="form-field">Amount (₱)<input name="amount" type="number" min="0.01" step="0.01" max="99999999.99" required></label>
            <label class="form-field">Type<select name="charge_sign"><option value="debit">Charge</option><option value="credit">Credit</option></select></label>
            <div class="actions span-2"><button type="submit" class="button secondary small">Add line item</button></div>
        </form>
    </details>
    <?php endif; ?>
</section>
<section class="panel">
    <h2>Payments for invoice #<?= e($invoiceId) ?></h2>
    <?php if ($verified && $selectedInvoice['balance_due'] > 0): ?>
    <form class="actions" method="post" action="<?= e(url('actions/payment_action.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="invoice_id" value="<?= e($invoiceId) ?>"><input type="hidden" name="operation" value="cash">
        <label class="form-field">Cash received (₱)<input type="number" name="amount" min="0.01" max="<?= e(min((float) $selectedInvoice['balance_due'], 99999999.99)) ?>" step="0.01" required></label>
        <button type="submit" class="button small">Record cash payment</button>
    </form>
    <?php endif; ?>
    <?php if (!$payments): ?><p class="empty-state">There are no payment records for this invoice.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Received</th><th>Method / reference</th><th>Amount</th><th>Status</th><th>Receipt or action</th></tr></thead>
        <tbody><?php foreach ($payments as $payment): ?>
            <tr><td><?= e(date('M j, Y', strtotime($payment['paid_at']))) ?></td><td><?= e(match ($payment['payment_method']) {'gcash' => 'GCash', 'bank_transfer' => 'Bank transfer', default => 'Cash'}) ?>
                    <?php if ($payment['reference_number']): ?><br><small><?= e($payment['reference_number']) ?></small><?php endif; ?>
                    <?php if ($payment['proof_image_path']): ?><br><a href="<?= e(media_url($payment['proof_image_path'])) ?>" target="_blank" rel="noopener">View proof</a><?php endif; ?>
                </td><td><?= e(money($payment['amount_paid'])) ?></td><td><?= landlord_badge($payment['verification_status']) ?></td><td>
                    <?php if ($payment['receipt_number']): ?>
                        <a href="<?= e(url('student/receipt.php?id=' . $payment['payment_id'])) ?>"><?= e($payment['receipt_number']) ?></a>
                    <?php elseif ($verified && $payment['verification_status'] === 'pending'): ?>
                        <form class="actions" method="post" action="<?= e(url('actions/payment_action.php')) ?>">
                            <?= csrf_field() ?><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="invoice_id" value="<?= e($invoiceId) ?>"><input type="hidden" name="payment_id" value="<?= e($payment['payment_id']) ?>">
                            <button class="button small" name="operation" value="verify">Verify</button><button class="button small secondary" name="operation" value="reject">Reject</button>
                        </form>
                    <?php else: ?>—<?php endif; ?>
                </td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
<section class="panel">
    <div class="panel-head"><h2>Billing Status &amp; Verification</h2></div>
    <?php if (!$invoices): ?>
        <p class="empty-state">There are no invoices for <?= e(date('F Y', strtotime($month . '-01'))) ?>.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th scope="col">Tenant</th><th scope="col">Room</th><th scope="col">Rent</th><th scope="col">Utilities</th><th scope="col">Balance</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><strong><?= e($invoice['tenant_name']) ?></strong></td>
                        <td><?= e($invoice['room_number']) ?><br><small class="muted"><?= e($invoice['property_name']) ?></small></td>
                        <td><?= e(money($invoice['rent'] ?? 0)) ?></td>
                        <td><?= e(money($invoice['utilities'] ?? 0)) ?></td>
                        <td><?= e(money($invoice['balance_due'])) ?></td>
                        <td><?= landlord_badge($invoice['invoice_status']) ?>
                            <br><small class="muted">Due <?= e(date('M j', strtotime($invoice['due_date']))) ?></small>
                            <?php if ($invoice['pending_proofs']): ?><br><small><?= (int) $invoice['pending_proofs'] ?> proof<?= $invoice['pending_proofs'] == 1 ? '' : 's' ?> pending</small><?php endif; ?>
                        </td>
                        <td><a class="button small secondary" href="<?= e(url('landlord/billing.php?month=' . $month . '&invoice=' . $invoice['invoice_id'] . '#invoice-detail')) ?>"><?= $invoice['pending_proofs'] ? 'Review Proof' : 'View Invoice' ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
