<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_support.php';

$viewer = require_role(['student', 'landlord', 'admin']);
$paymentId = (int) filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$receipt = row(
    "SELECT pay.receipt_number, pay.amount_paid, pay.payment_method, pay.reference_number, pay.paid_at, pay.verified_at,
            ri.billing_month, l.student_id, r.room_number, p.landlord_id, p.property_name, p.street_address,
            CONCAT(tenant.first_name, ' ', tenant.last_name) AS tenant_name,
            CONCAT(owner.first_name, ' ', owner.last_name) AS owner_name
     FROM payments pay JOIN rent_invoices ri ON ri.invoice_id = pay.invoice_id JOIN leases l ON l.lease_id = ri.lease_id
     JOIN rooms r ON r.room_id = l.room_id JOIN properties p ON p.property_id = r.property_id
     JOIN users tenant ON tenant.user_id = l.student_id JOIN users owner ON owner.user_id = p.landlord_id
     WHERE pay.payment_id = ? AND pay.verification_status = 'verified' AND pay.receipt_number IS NOT NULL",
    [$paymentId]
);
$back = ['student' => 'student/billing.php', 'landlord' => 'landlord/billing.php', 'admin' => 'admin/dashboard.php'][$viewer['role_name']];
$hasAccess = $receipt && match ($viewer['role_name']) {
    'student' => (int) $receipt['student_id'] === (int) $viewer['user_id'],
    'landlord' => (int) $receipt['landlord_id'] === (int) $viewer['user_id'],
    'admin' => true,
};
if (!$hasAccess) {
    http_response_code(404);
    $pageTitle = 'Receipt unavailable';
    require __DIR__ . '/../includes/portal_header.php';
    ?>
    <section class="panel empty-state">
        <h2>Receipt unavailable</h2>
        <p>Open a verified receipt from the payment history you have access to.</p>
        <a class="button secondary" href="<?= e(url($back)) ?>">Go back</a>
    </section>
    <?php
    require __DIR__ . '/../includes/portal_footer.php';
    exit;
}

header('Cache-Control: private, no-store');
$method = ['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank transfer'][$receipt['payment_method']];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt <?= e($receipt['receipt_number']) ?> | Dayon</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('student/receipt.css')) ?>">
    <script defer src="<?= e(url('student/receipt.js')) ?>"></script>
</head>
<body class="receipt-page">
    <main class="receipt-layout">
        <nav class="actions receipt-actions" aria-label="Receipt actions">
            <a class="button secondary" href="<?= e(url($back)) ?>">Go back</a>
            <button class="button" type="button" id="receipt-print">Print receipt</button>
        </nav>
        <article class="receipt-paper" aria-labelledby="receipt-heading">
            <header class="receipt-header">
                <div>
                    <p class="receipt-brand">Dayon</p>
                    <h1 id="receipt-heading">Payment receipt</h1>
                    <p class="receipt-number"><?= e($receipt['receipt_number']) ?></p>
                </div>
                <span class="badge verified">Verified</span>
            </header>
            <div class="receipt-address">
                <div><h2>Received from</h2><p><?= e($receipt['tenant_name']) ?></p></div>
                <div>
                    <h2>Received by</h2>
                    <p><?= e($receipt['owner_name']) ?></p>
                    <p><?= e($receipt['property_name']) ?> · Room <?= e($receipt['room_number']) ?></p>
                    <p class="muted"><?= e($receipt['street_address']) ?></p>
                </div>
            </div>
            <dl class="receipt-details">
                <div><dt>Billing month</dt><dd><?= e(student_billing_month($receipt['billing_month'])) ?></dd></div>
                <div><dt>Payment method</dt><dd><?= e($method) ?></dd></div>
                <?php if ($receipt['reference_number']): ?><div><dt>Transaction reference</dt><dd><?= e($receipt['reference_number']) ?></dd></div><?php endif; ?>
                <div><dt>Payment recorded</dt><dd><?= e(student_short_date($receipt['paid_at'])) ?></dd></div>
                <?php if ($receipt['verified_at']): ?><div><dt>Verified on</dt><dd><?= e(student_short_date($receipt['verified_at'])) ?></dd></div><?php endif; ?>
            </dl>
            <div class="receipt-total"><strong>Amount received</strong><strong><?= money($receipt['amount_paid']) ?></strong></div>
        </article>
    </main>
</body>
</html>
