<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

landlord_post(landlord_month('landlord/billing.php', field('month')) . '#generate-monthly', function (int $landlordId): string {
    global $pdo;
    $month = field_date('month', 'Y-m');
    $endOfMonth = (new DateTimeImmutable($month . '-01'))->format('Y-m-t');
    $params = [$landlordId, $endOfMonth];
    $filter = '';
    if (field('property_id') !== '') {
        $propertyId = field_integer('property_id', 'property');
        owned_property($landlordId, $propertyId, true);
        $filter = ' AND p.property_id = ?';
        $params[] = $propertyId;
    }
    $leases = rows("SELECT l.lease_id, l.agreed_monthly_rent, l.billing_due_day FROM leases l
        JOIN rooms r ON r.room_id = l.room_id JOIN properties p ON p.property_id = r.property_id
        WHERE p.landlord_id = ? AND l.lease_status = 'active' AND l.move_in_date <= ?{$filter}
        ORDER BY l.lease_id FOR UPDATE", $params);
    if (!$leases) {
        throw new DomainException('There are no active leases to invoice for this month.');
    }
    $count = 0;
    foreach ($leases as $lease) {
        if (scalar('SELECT invoice_id FROM rent_invoices WHERE lease_id = ? AND billing_month = ?', [$lease['lease_id'], $month . '-01'])) {
            continue;
        }
        $due = $month . '-' . str_pad((string) $lease['billing_due_day'], 2, '0', STR_PAD_LEFT);
        query('INSERT INTO rent_invoices (lease_id, billing_month, due_date) VALUES (?, ?, ?)', [$lease['lease_id'], $month . '-01', $due]);
        $invoiceId = (int) $pdo->lastInsertId();
        query("INSERT INTO invoice_items (invoice_id, charge_type, description, amount) VALUES (?, 'base_rent', 'Monthly bed rent', ?)", [$invoiceId, $lease['agreed_monthly_rent']]);
        $count++;
    }
    if ($count === 0) {
        return 'All eligible leases already have an invoice for this month.';
    }
    audit($landlordId, 'invoices_generated', 'rent_invoices', $invoiceId, $month . ': ' . $count . ' created');
    return $count === 1 ? 'One rent invoice created.' : "{$count} rent invoices created.";
}, true);
