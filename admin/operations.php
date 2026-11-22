<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_forms.php';
require_role('admin');

$views = [
    'rooms' => [
        'Rooms',
        ['property' => 'Property', 'room_number' => 'Room', 'room_type' => 'Type', 'rate' => 'Monthly bed rate', 'occupied_beds' => 'Occupied beds', 'available_beds' => 'Available beds', 'status' => 'Availability'],
        'SELECT p.property_name AS property, v.room_number, v.room_type, v.monthly_rate_per_bed AS rate,
            v.occupied_beds, IF(v.is_active = 1, v.available_beds, 0) AS available_beds, v.vacancy_status AS status
            FROM vw_room_vacancy v JOIN properties p ON p.property_id = v.property_id ORDER BY p.property_name, v.room_number, v.room_id',
    ],
    'requests' => [
        'Visits and holds',
        ['property' => 'Property', 'room_number' => 'Room', 'student' => 'Student', 'request_type' => 'Request', 'scheduled_at' => 'Scheduled for', 'status' => 'Status'],
        "SELECT p.property_name AS property, r.room_number, CONCAT(u.first_name, ' ', u.last_name) AS student,
            v.request_type, v.scheduled_at, v.status FROM reservations v JOIN rooms r ON r.room_id = v.room_id
            JOIN properties p ON p.property_id = r.property_id JOIN users u ON u.user_id = v.student_id
            ORDER BY v.created_at DESC, v.reservation_id DESC",
    ],
    'leases' => [
        'Leases',
        ['property' => 'Property', 'room_number' => 'Room', 'student' => 'Student', 'move_in_date' => 'Move-in', 'move_out_date' => 'Move-out', 'rate' => 'Agreed rent', 'advance' => 'Advance', 'deposit' => 'Deposit', 'refund' => 'Refund', 'status' => 'Status'],
        "SELECT p.property_name AS property, r.room_number, CONCAT(u.first_name, ' ', u.last_name) AS student,
            l.move_in_date, l.move_out_date, l.agreed_monthly_rent AS rate, l.advance_rent_amount AS advance,
            l.security_deposit_amount AS deposit, l.deposit_refunded_amount AS refund, l.lease_status AS status
            FROM leases l JOIN rooms r ON r.room_id = l.room_id JOIN properties p ON p.property_id = r.property_id
            JOIN users u ON u.user_id = l.student_id ORDER BY l.created_at DESC, l.lease_id DESC",
    ],
    'invoices' => [
        'Invoices',
        ['property' => 'Property', 'student' => 'Student', 'billing_month' => 'Billing month', 'due_date' => 'Due date', 'billed' => 'Billed', 'paid' => 'Paid', 'balance' => 'Balance', 'status' => 'Status', 'line_items' => 'Charges'],
        "SELECT p.property_name AS property, CONCAT(u.first_name, ' ', u.last_name) AS student,
            i.billing_month, i.due_date, i.total_billed AS billed, i.total_paid AS paid, i.balance_due AS balance, i.invoice_status AS status,
            i.invoice_id FROM vw_invoice_summary i JOIN rooms r ON r.room_id = i.room_id
            JOIN properties p ON p.property_id = r.property_id JOIN users u ON u.user_id = i.student_id
            ORDER BY i.billing_month DESC, i.invoice_id DESC",
    ],
    'payments' => [
        'Payments',
        ['property' => 'Property', 'student' => 'Student', 'paid_at' => 'Submitted', 'amount' => 'Amount', 'payment_method' => 'Method', 'status' => 'Verification', 'reference_number' => 'Reference', 'receipt_number' => 'Receipt', 'media' => 'Proof'],
        "SELECT p.property_name AS property, CONCAT(u.first_name, ' ', u.last_name) AS student,
            pm.paid_at, pm.amount_paid AS amount, pm.payment_method, pm.verification_status AS status,
            pm.reference_number, pm.receipt_number, pm.proof_image_path AS media
            FROM payments pm JOIN rent_invoices i ON i.invoice_id = pm.invoice_id JOIN leases l ON l.lease_id = i.lease_id
            JOIN rooms r ON r.room_id = l.room_id JOIN properties p ON p.property_id = r.property_id
            JOIN users u ON u.user_id = l.student_id ORDER BY pm.paid_at DESC, pm.payment_id DESC",
    ],
    'maintenance' => [
        'Maintenance',
        ['property' => 'Property', 'room_number' => 'Room', 'category' => 'Category', 'description' => 'Issue', 'priority' => 'Priority', 'status' => 'Status', 'media' => 'Photo'],
        'SELECT p.property_name AS property, r.room_number, t.category, t.description,
            t.priority, t.ticket_status AS status, t.photo_path AS media FROM maintenance_requests t
            JOIN leases l ON l.lease_id = t.lease_id JOIN rooms r ON r.room_id = l.room_id
            JOIN properties p ON p.property_id = r.property_id ORDER BY t.created_at DESC, t.ticket_id DESC',
    ],
    'announcements' => [
        'Announcements',
        ['property' => 'Property', 'title' => 'Title', 'message' => 'Message', 'created_at' => 'Posted', 'decision' => 'Moderation'],
        'SELECT p.property_name AS property, a.title, a.message, a.created_at, a.announcement_id
            FROM announcements a JOIN properties p ON p.property_id = a.property_id ORDER BY a.created_at DESC, a.announcement_id DESC',
    ],
];
$view = is_string($_GET['view'] ?? null) && isset($views[$_GET['view']]) ? $_GET['view'] : 'rooms';
[$label, $columns, $sql] = $views[$view];
$page = max(1, min(1000000, (int) filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT)));
$offset = ($page - 1) * 25;
$entries = rows($sql . " LIMIT 26 OFFSET $offset");
$hasNext = count($entries) > 25;
if ($hasNext) {
    array_pop($entries);
}

$lineItems = [];
if ($view === 'invoices' && $entries !== []) {
    $invoiceIds = array_column($entries, 'invoice_id');
    $placeholders = implode(', ', array_fill(0, count($invoiceIds), '?'));
    foreach (rows("SELECT invoice_id, description, amount FROM invoice_items WHERE invoice_id IN ($placeholders) ORDER BY item_id", $invoiceIds) as $item) {
        $lineItems[$item['invoice_id']][] = $item;
    }
}

$pageTitle = 'Housing operations';
$pageDescription = 'Inspect housing, billing, and maintenance records across properties.';
require __DIR__ . '/../includes/portal_header.php';
?>
<section class="panel">
    <nav class="actions" aria-label="Housing operations">
        <?php foreach ($views as $key => [$name]): ?>
            <a class="button small <?= $view === $key ? '' : 'secondary' ?>" href="<?= e(url('admin/operations.php?view=' . $key)) ?>" <?= $view === $key ? 'aria-current="page"' : '' ?>><?= e($name) ?></a>
        <?php endforeach; ?>
    </nav>
    <h2><?= e($label) ?></h2>
    <?php if ($entries === []): ?>
        <p class="empty-state">There are no records on this page.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><?php foreach ($columns as $columnLabel): ?><th scope="col"><?= e($columnLabel) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <?php foreach ($columns as $column => $columnLabel): ?>
                            <?php $value = $entry[$column] ?? null; ?>
                            <td>
                                <?php if ($column === 'decision'): ?>
                                    <details>
                                        <summary>Remove announcement</summary>
                                        <?php admin_status_form('announcement', (int) $entry['announcement_id'], ['removed' => 'Confirm removal'], true); ?>
                                    </details>
                                <?php elseif ($column === 'line_items'): ?>
                                    <details>
                                        <summary>View charges</summary>
                                        <?php foreach ($lineItems[$entry['invoice_id']] ?? [] as $item): ?>
                                            <p><?= e($item['description']) ?> · <?= e(money($item['amount'])) ?></p>
                                        <?php endforeach; ?>
                                    </details>
                                <?php elseif ($value === null || $value === ''): ?>
                                    —
                                <?php elseif ($column === 'media'): ?>
                                    <a href="<?= e(media_url($value)) ?>">Open <?= e(strtolower($columnLabel)) ?></a>
                                <?php elseif ($column === 'status'): ?>
                                    <span class="badge"><?= e(admin_status_label($value)) ?></span>
                                <?php elseif (in_array($column, ['request_type', 'room_type', 'payment_method', 'category', 'priority'], true)): ?>
                                    <?= e(admin_status_label($value)) ?>
                                <?php elseif (in_array($column, ['rate', 'advance', 'deposit', 'refund', 'billed', 'paid', 'balance', 'amount'], true)): ?>
                                    <?= e(money($value)) ?>
                                <?php else: ?>
                                    <?= nl2br(e($value)) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
    <div class="actions">
        <?php if ($page > 1): ?><a class="button secondary small" href="<?= e(url('admin/operations.php?view=' . $view . '&page=' . ($page - 1))) ?>">Previous page</a><?php endif; ?>
        <?php if ($hasNext): ?><a class="button secondary small" href="<?= e(url('admin/operations.php?view=' . $view . '&page=' . ($page + 1))) ?>">Next page</a><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
