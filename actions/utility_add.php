<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

landlord_post(landlord_month('landlord/billing.php', field('month')) . '#utility-splitter', function (int $landlordId): string {
    $month = field_date('month', 'Y-m');
    $mode = field_choice('split_mode', ['flat', 'submetered']);
    $reference = field_text('bill_reference', 'bill reference', 150);
    $legacy = field('charge_type') !== '';
    if ($legacy) {
        $charges = [field_choice('charge_type', ['water', 'electricity']) => amount_cents(field('total_amount'))];
    } else {
        $charges = array_filter([
            'electricity' => amount_cents(field('electricity_amount') ?: '0', true),
            'water' => amount_cents(field('water_amount') ?: '0', true),
        ]);
    }
    if (!$charges) {
        throw new DomainException('Enter a positive amount for electricity or water.');
    }

    $propertyId = field_integer('property_id', 'property');
    owned_property($landlordId, $propertyId, true);
    $roomId = field('room_id') === '' ? null : field_integer('room_id', 'room');
    if ($roomId !== null && (int) owned_room($landlordId, $roomId, true)['property_id'] !== $propertyId) {
        throw new DomainException('Choose a room in the selected property.');
    }
    $targets = utility_targets($landlordId, $propertyId, $roomId, $month, true);
    if (!$targets) {
        throw new DomainException('There are no active tenants in this billing scope.');
    }
    if (count(array_filter(array_column($targets, 'invoice_id'))) !== count($targets)) {
        throw new DomainException('Generate the monthly invoices before adding this utility bill.');
    }

    $input = $_POST['meter_units'] ?? [];
    foreach ($charges as $type => $total) {
        $billLabel = ucfirst($type) . ' · ' . $reference;
        $labels = ['flat' => $billLabel . ' (equal split)', 'submetered' => $billLabel . ' (metered split)'];
        $usage = $legacy ? $input : (is_array($input) ? ($input[$type] ?? []) : []);
        $weights = [];

        foreach ($targets as $target) {
            $invoiceId = (int) $target['invoice_id'];
            if ($mode === 'flat') {
                $weights[$invoiceId] = 1;
                continue;
            }
            $units = is_array($usage) ? ($usage[$invoiceId] ?? '') : '';
            if (!is_string($units) || !preg_match('/^\d{1,5}(?:\.\d{1,3})?$/D', $units)) {
                throw new DomainException('Enter ' . $type . ' usage for every tenant, with up to three decimal places.');
            }
            [$whole, $fraction] = array_pad(explode('.', $units, 2), 2, '');
            $weights[$invoiceId] = (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
        }

        $totalWeight = array_sum($weights);
        if ($totalWeight === 0) {
            throw new DomainException('Enter ' . $type . ' usage greater than zero for at least one tenant.');
        }

        $shares = [];
        $remainders = [];
        foreach ($weights as $invoiceId => $weight) {
            $product = $weight * $total;
            $shares[$invoiceId] = intdiv($product, $totalWeight);
            $remainders[$invoiceId] = $product % $totalWeight;
        }
        arsort($remainders, SORT_NUMERIC);
        $left = $total - array_sum($shares);
        foreach (array_slice(array_keys($remainders), 0, $left) as $invoiceId) {
            $shares[$invoiceId]++;
        }

        foreach ($shares as $invoiceId => $cents) {
            if (scalar('SELECT item_id FROM invoice_items WHERE invoice_id = ? AND charge_type = ? AND description IN (?, ?)', [$invoiceId, $type, $labels['flat'], $labels['submetered']])) {
                throw new DomainException('This ' . $type . ' bill reference is already on one of these invoices.');
            }
            query('INSERT INTO invoice_items (invoice_id, charge_type, description, amount) VALUES (?, ?, ?, ?)', [$invoiceId, $type, $labels[$mode], decimal_amount($cents)]);
        }
        audit($landlordId, 'utilities_added', 'properties', $propertyId, $month . ' ' . $type . ' ' . $reference . ': ' . decimal_amount($total));
    }

    return 'Utility charges were added to ' . count($targets) . (count($targets) === 1 ? ' invoice.' : ' invoices.');
}, true);
