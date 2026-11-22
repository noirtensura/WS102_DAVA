<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../student/_support.php';

$student = require_role('student');
require_post();
$studentId = (int) $student['user_id'];
$lease = student_active_lease_or_redirect($studentId);
$invoiceId = student_post_id('invoice_id');
$method = student_post_text('payment_method');
$amount = student_post_text('amount_paid');
$reference = student_post_text('reference_number');
$upload = null;

try {
    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $amount) || (float) $amount <= 0 ||
        !in_array($method, ['gcash', 'bank_transfer'], true) || $reference === '' || strlen($reference) > 100) {
        throw new DomainException('Enter a valid amount, payment method, and transaction reference.');
    }

    $pdo->beginTransaction();
    $invoice = row('SELECT invoice_id, lease_id FROM rent_invoices WHERE invoice_id = ? AND lease_id = ? FOR UPDATE', [$invoiceId, $lease['lease_id']]);
    if (!$invoice || !row("SELECT lease_id FROM leases WHERE lease_id = ? AND lease_status = 'active' FOR UPDATE", [$lease['lease_id']])) {
        throw new DomainException('This invoice is unavailable for payment proof.');
    }

    $balance = (float) scalar('SELECT balance_due FROM vw_invoice_summary WHERE invoice_id = ?', [$invoiceId]);
    $pending = (float) scalar("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE invoice_id = ? AND verification_status = 'pending'", [$invoiceId]);
    if ($balance <= 0 || (int) round((float) $amount * 100) > (int) round(($balance - $pending) * 100)) {
        throw new DomainException('The amount exceeds the balance available after pending proofs.');
    }

    if (scalar("SELECT payment_id FROM payments WHERE invoice_id = ? AND payment_method = ? AND reference_number = ? AND verification_status IN ('pending', 'verified') LIMIT 1", [$invoiceId, $method, $reference])) {
        throw new DomainException('This transaction reference has already been submitted for the invoice.');
    }

    $upload = save_upload('proof', 'payments', true);
    query(
        'INSERT INTO payments (invoice_id, amount_paid, payment_method, reference_number, proof_image_path) VALUES (?, ?, ?, ?, ?)',
        [$invoiceId, $amount, $method, $reference, $upload]
    );
    $pdo->commit();
    flash('success', 'Payment proof submitted for landlord verification.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    remove_upload($upload);
    if ($error instanceof DomainException || $error instanceof InvalidArgumentException) {
        $message = $error->getMessage();
    } else {
        error_log($error->getMessage());
        $message = 'Your payment proof could not be submitted. Please try again.';
    }
    flash('error', $message);
}

redirect('student/billing.php' . ($invoiceId ? '#invoice-' . $invoiceId : ''));
