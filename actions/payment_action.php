<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

$returnPath = landlord_month('landlord/billing.php', field('month'));
$invoice = filter_var(field('invoice_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($invoice !== false) {
    $returnPath .= (str_contains($returnPath, '?') ? '&' : '?') . 'invoice=' . $invoice;
}

landlord_post($returnPath, function (int $landlordId): string {
    global $pdo;
    $operation = field_choice('operation', ['cash', 'verify', 'reject', 'charge']);
    $invoiceId = field_integer('invoice_id', 'invoice');
    locked_landlord_invoice($invoiceId, $landlordId);
    $balance = (int) scalar('SELECT ROUND(balance_due * 100) FROM vw_invoice_summary WHERE invoice_id = ?', [$invoiceId]);
    if ($operation === 'charge') {
        $description = field_text('description', 'line item description', 200);
        $type = field_choice('charge_type', ['wifi', 'penalty', 'adjustment']);
        $sign = field_choice('charge_sign', ['debit', 'credit']);
        if ($sign === 'credit' && $type !== 'adjustment') {
            throw new DomainException('Choose Adjustment to apply a credit.');
        }
        $amount = amount_cents(field('amount'));
        $cents = $sign === 'credit' ? -$amount : $amount;
        if ($cents < 0 && $amount > $balance) {
            throw new DomainException('A credit cannot exceed the current balance.');
        }
        query('INSERT INTO invoice_items (invoice_id, charge_type, description, amount) VALUES (?, ?, ?, ?)', [$invoiceId, $type, $description, decimal_amount($cents)]);
        audit($landlordId, 'invoice_item_added', 'rent_invoices', $invoiceId, $description . ': ' . decimal_amount($cents));
        return 'Invoice line item added.';
    }

    if ($operation !== 'cash') {
        $paymentId = field_integer('payment_id', 'payment');
        $payment = row('SELECT * FROM payments WHERE payment_id = ? AND invoice_id = ? FOR UPDATE', [$paymentId, $invoiceId]);
        if (!$payment || $payment['verification_status'] !== 'pending') {
            throw new DomainException('This proof is unavailable or has already been reviewed.');
        }
        if ($operation === 'reject') {
            query("UPDATE payments SET verification_status = 'rejected', verified_by = ?, verified_at = NOW() WHERE payment_id = ?", [$landlordId, $paymentId]);
            audit($landlordId, 'payment_rejected', 'payments', $paymentId);
            return 'Payment proof rejected.';
        }
        $amount = amount_cents((string) $payment['amount_paid']);
    } else {
        $amount = amount_cents(field('amount'));
    }
    if ($amount > $balance) {
        throw new DomainException('Payment exceeds the current invoice balance.');
    }
    if ($operation === 'cash') {
        query("INSERT INTO payments (invoice_id, amount_paid, payment_method, verification_status, verified_by, verified_at) VALUES (?, ?, 'cash', 'verified', ?, NOW())", [$invoiceId, decimal_amount($amount), $landlordId]);
        $paymentId = (int) $pdo->lastInsertId();
    }
    $receipt = 'DAY-' . date('Ymd') . '-' . str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);
    query("UPDATE payments SET verification_status = 'verified', receipt_number = ?, verified_by = ?, verified_at = NOW() WHERE payment_id = ?", [$receipt, $landlordId, $paymentId]);
    audit($landlordId, $operation === 'cash' ? 'cash_payment_recorded' : 'payment_verified', 'payments', $paymentId, $receipt);
    return 'Payment recorded and receipt ' . $receipt . ' issued.';
}, true);
