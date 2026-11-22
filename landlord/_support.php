<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function landlord_page(): int
{
    return (int) require_role('landlord')['user_id'];
}

function field(string $name): string
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? trim($value) : '';
}

function field_text(string $name, string $label, int $limit, bool $required = true): string
{
    $value = field($name);
    if (($required && $value === '') || preg_match_all('/./us', $value) > $limit) {
        throw new DomainException("Enter a valid {$label} (up to {$limit} characters).");
    }
    return $value;
}

function field_integer(string $name, string $label, int $min = 1, int $max = PHP_INT_MAX): int
{
    $value = filter_var(field($name), FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($value === false) {
        throw new DomainException("Enter a valid {$label}.");
    }
    return $value;
}

function field_choice(string $name, array $choices): string
{
    $value = field($name);
    if (!in_array($value, $choices, true)) {
        throw new DomainException('Choose a valid option.');
    }
    return $value;
}

function field_date(string $name, string $format = 'Y-m-d'): string
{
    $value = field($name);
    $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
    if (!$date || $date->format($format) !== $value || (int) $date->format('Y') < 1900) {
        throw new DomainException('Choose a valid date.');
    }
    return $value;
}

function amount_cents(mixed $value, bool $allowZero = false): int
{
    if (!is_string($value) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) {
        throw new DomainException('Enter a valid amount with up to two decimal places.');
    }
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    $cents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    if ($cents === 0 && !$allowZero) {
        throw new DomainException('Enter an amount greater than zero.');
    }
    return $cents;
}

function decimal_amount(int $cents): string
{
    $amount = abs($cents);
    return ($cents < 0 ? '-' : '') . intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
}

function landlord_post(string $returnPath, callable $operation, bool $verified = false): never
{
    global $pdo;
    $landlordId = landlord_page();
    require_post();
    $newUploads = [];
    $obsoleteUploads = [];
    try {
        $pdo->beginTransaction();
        if ($verified && scalar('SELECT verification_status FROM landlord_profiles WHERE landlord_id = ? FOR UPDATE', [$landlordId]) !== 'verified') {
            throw new DomainException('Complete landlord verification to use this feature.');
        }
        $message = $operation($landlordId, $newUploads, $obsoleteUploads);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($newUploads as $path) {
            remove_upload($path);
        }
        if ($error instanceof DomainException) {
            $message = $error->getMessage();
        } elseif ($error instanceof PDOException && ($error->errorInfo[0] ?? '') === '45000') {
            $message = $error->errorInfo[2];
        } elseif ($error instanceof PDOException && ($error->errorInfo[1] ?? null) === 1062) {
            $message = str_contains($error->errorInfo[2], 'uq_one_active_lease') ? 'This student already has an active lease.' : 'That record already exists.';
        } elseif ($error instanceof PDOException && in_array(($error->errorInfo[1] ?? null), [1205, 1213], true)) {
            $message = 'Another update was made at the same time. Try again.';
        } else {
            error_log($error->__toString());
            $message = 'The update could not be saved. Try again.';
        }
        flash('error', $message);
        redirect($returnPath);
    }
    foreach ($obsoleteUploads as $path) {
        remove_upload($path);
    }
    flash('success', $message);
    redirect($returnPath);
}

function owned_property(int $landlordId, int $propertyId, bool $lock = false): array
{
    $property = row('SELECT * FROM properties WHERE landlord_id = ? AND property_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$landlordId, $propertyId]);
    if (!$property) {
        throw new DomainException('Choose one of your properties.');
    }
    return $property;
}

function owned_room(int $landlordId, int $roomId, bool $lock = false): array
{
    $room = row('SELECT r.*, p.approval_status FROM rooms r JOIN properties p ON p.property_id = r.property_id WHERE p.landlord_id = ? AND r.room_id = ?' . ($lock ? ' FOR UPDATE' : ''), [$landlordId, $roomId]);
    if (!$room) {
        throw new DomainException('Choose a room in one of your properties.');
    }
    return $room;
}

function replace_amenities(string $scope, int $targetId): void
{
    [$table, $column] = $scope === 'room' ? ['room_amenities', 'room_id'] : ['property_amenities', 'property_id'];
    query("DELETE FROM {$table} WHERE {$column} = ?", [$targetId]);
    $ids = $_POST['amenities'] ?? [];
    if (!is_array($ids) || count($ids) > 100) {
        throw new DomainException('Choose valid amenities.');
    }
    foreach (array_unique(array_filter($ids, 'is_string')) as $amenityId) {
        if (filter_var($amenityId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new DomainException('Choose valid amenities.');
        }
        if (query("INSERT INTO {$table} ({$column}, amenity_id) SELECT ?, amenity_id FROM amenities WHERE amenity_id = ? AND scope = ?", [$targetId, $amenityId, $scope])->rowCount() !== 1) {
            throw new DomainException('An amenity is unavailable for this listing.');
        }
    }
}

function landlord_badge(string $status): string
{
    return '<span class="badge ' . e($status) . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function landlord_properties(int $landlordId): array
{
    return rows('SELECT property_id, property_name, approval_status FROM properties WHERE landlord_id = ? ORDER BY property_name, property_id', [$landlordId]);
}

function landlord_verified(int $landlordId): bool
{
    return scalar('SELECT verification_status FROM landlord_profiles WHERE landlord_id = ?', [$landlordId]) === 'verified';
}

function landlord_verification_notice(): void
{
    ?>
    <div class="panel">
        <h2>Verify your landlord account</h2>
        <p class="muted">Admin approval unlocks requests, leases, billing and tenant services.</p>
        <a class="button secondary small" href="<?= e(url('landlord/verification.php')) ?>">View verification</a>
    </div>
    <?php
}

function landlord_month(string $path, string $month): string
{
    return $path . (preg_match('/^\d{4}-\d{2}$/D', $month) ? '?month=' . $month : '');
}

function room_capacity_after_holds(int $roomId, int $studentId = 0): int
{
    $capacity = (int) scalar('SELECT available_beds FROM vw_room_vacancy WHERE room_id = ?', [$roomId]);
    $holds = (int) scalar("SELECT COUNT(DISTINCT q.student_id) FROM reservations q
        WHERE q.room_id = ? AND q.student_id <> ? AND q.request_type = 'bed_reservation'
          AND q.status = 'approved' AND q.scheduled_at >= CURDATE()
          AND NOT EXISTS (SELECT 1 FROM leases l WHERE l.student_id = q.student_id AND l.lease_status = 'active')", [$roomId, $studentId]);

    return $capacity - $holds;
}

function utility_targets(int $landlordId, int $propertyId, ?int $roomId, string $month, bool $lock = false): array
{
    $params = [$month . '-01', $landlordId, $propertyId, (new DateTimeImmutable($month . '-01'))->format('Y-m-t')];
    if ($roomId !== null) {
        $params[] = $roomId;
    }
    return rows("SELECT i.invoice_id, l.lease_id, r.room_number, CONCAT(u.first_name, ' ', u.last_name) AS tenant_name
        FROM leases l JOIN users u ON u.user_id = l.student_id JOIN rooms r ON r.room_id = l.room_id
        JOIN properties p ON p.property_id = r.property_id
        LEFT JOIN rent_invoices i ON i.lease_id = l.lease_id AND i.billing_month = ?
        WHERE p.landlord_id = ? AND p.property_id = ? AND l.lease_status = 'active' AND l.move_in_date <= ?"
        . ($roomId !== null ? ' AND r.room_id = ?' : '') . ' ORDER BY r.room_number, l.lease_id' . ($lock ? ' FOR UPDATE' : ''), $params);
}

function locked_landlord_invoice(int $invoiceId, int $landlordId): array
{
    $invoice = row('SELECT i.* FROM rent_invoices i JOIN leases l ON l.lease_id = i.lease_id JOIN rooms r ON r.room_id = l.room_id JOIN properties p ON p.property_id = r.property_id WHERE i.invoice_id = ? AND p.landlord_id = ? FOR UPDATE', [$invoiceId, $landlordId]);
    if (!$invoice) {
        throw new DomainException('Choose one of your invoices.');
    }
    return $invoice;
}
