<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

$returnPath = 'landlord/properties.php';
$editId = filter_var(field('property_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$opPreview = field('operation');
if ($editId !== false && $opPreview !== 'delete') {
    $returnPath .= '?edit=' . $editId . (str_starts_with($opPreview, 'image_') ? '#property-gallery' : '');
}

landlord_post($returnPath, function (int $landlordId, array &$newUploads, array &$obsoleteUploads): string {
    global $pdo;
    $operation = field_choice('operation', ['save', 'delete', 'image_add', 'image_delete', 'image_primary', 'image_caption']);
    $propertyId = field('property_id') === '' ? null : field_integer('property_id', 'property');
    $property = $propertyId === null ? null : owned_property($landlordId, $propertyId, true);

    if ($operation !== 'save' && $property === null) {
        throw new DomainException('Choose one of your properties.');
    }
    if ($operation === 'delete') {
        if (scalar('SELECT EXISTS (SELECT 1 FROM rooms r JOIN leases l ON l.room_id = r.room_id WHERE r.property_id = ?) OR EXISTS (SELECT 1 FROM rooms r JOIN reservations q ON q.room_id = r.room_id WHERE r.property_id = ?) OR EXISTS (SELECT 1 FROM reports WHERE property_id = ?)', [$propertyId, $propertyId, $propertyId])) {
            throw new DomainException('This property has lease, request, or report history and must be retained.');
        }
        $obsoleteUploads = array_column(rows('SELECT image_path FROM property_images WHERE property_id = ?', [$propertyId]), 'image_path');
        query('DELETE FROM properties WHERE property_id = ?', [$propertyId]);
        audit($landlordId, 'property_deleted', 'properties', $propertyId);
        return 'Property removed.';
    }
    if (in_array($operation, ['image_delete', 'image_primary', 'image_caption'], true)) {
        $imageId = field_integer('image_id', 'photo');
        $image = row('SELECT * FROM property_images WHERE property_id = ? AND image_id = ? FOR UPDATE', [$propertyId, $imageId]);
        if (!$image) {
            throw new DomainException('That photo is unavailable.');
        }
        if ($operation === 'image_primary') {
            query('UPDATE property_images SET is_primary = (image_id = ?) WHERE property_id = ?', [$imageId, $propertyId]);
            return 'Cover photo updated.';
        }
        if ($operation === 'image_caption') {
            $caption = field_text('caption', 'photo caption', 120, false) ?: null;
            query('UPDATE property_images SET caption = ? WHERE image_id = ?', [$caption, $imageId]);
            return 'Photo label saved.';
        }
        query('DELETE FROM property_images WHERE image_id = ?', [$imageId]);
        if ($image['is_primary']) {
            query('UPDATE property_images SET is_primary = 1 WHERE property_id = ? ORDER BY image_id LIMIT 1', [$propertyId]);
        }
        $obsoleteUploads[] = $image['image_path'];
        return 'Photo removed.';
    }

    if ($operation === 'save') {
        $barangayId = field_integer('barangay_id', 'barangay');
        if (!scalar(
            'SELECT b.barangay_id FROM barangays b JOIN municipalities m USING (municipality_id)
             WHERE b.barangay_id = ? AND (m.province_name = ?
                 OR m.municipality_id = (SELECT municipality_id FROM barangays WHERE barangay_id = ?))',
            [$barangayId, DAYON_PROVINCE, $property['barangay_id'] ?? 0]
        )) {
            throw new DomainException('Choose a barangay in ' . DAYON_PROVINCE . '.');
        }

        $coordinates = [];
        foreach (['latitude' => 90, 'longitude' => 180] as $name => $limit) {
            $coordinate = field($name);
            if ($coordinate !== '' && (!preg_match('/^-?\d{1,3}(?:\.\d{1,7})?$/D', $coordinate) || abs((float) $coordinate) > $limit)) {
                throw new DomainException('Enter valid latitude and longitude coordinates.');
            }
            $coordinates[] = $coordinate === '' ? null : $coordinate;
        }
        if (($coordinates[0] === null) !== ($coordinates[1] === null)) {
            throw new DomainException('Provide both latitude and longitude for the map pin, or leave both blank.');
        }

        $noCurfew = field('no_curfew') === '1' ? 1 : 0;
        $curfew = ($noCurfew || field('curfew_time') === '') ? null : field_date('curfew_time', 'H:i');

        $distanceRaw = field('distance_to_campus_m');
        $distance = $distanceRaw === '' ? null : field_integer('distance_to_campus_m', 'campus distance', 0, 1000000);

        $optionalChoice = static function (string $name, array $allowed): ?string {
            $val = field($name);
            if ($val === '') {
                return null;
            }
            if (!in_array($val, $allowed, true)) {
                throw new DomainException('Choose a valid option.');
            }
            return $val;
        };

        $rawMethods = $_POST['payment_methods'] ?? [];
        $methodsClean = [];
        if (is_array($rawMethods)) {
            foreach ($rawMethods as $m) {
                if (is_string($m) && in_array($m, PAYMENT_METHOD_OPTIONS, true)) {
                    $methodsClean[] = $m;
                }
            }
        }
        $paymentMethods = $methodsClean ? implode(',', array_unique($methodsClean)) : null;

        // Custom necessities key-value pairs
        $customLabels = $_POST['custom_label'] ?? [];
        $customValues = $_POST['custom_value'] ?? [];
        $customPairs = [];
        if (is_array($customLabels) && is_array($customValues)) {
            $count = min(count($customLabels), count($customValues), 20);
            for ($i = 0; $i < $count; $i++) {
                $lbl = is_string($customLabels[$i] ?? null) ? trim($customLabels[$i]) : '';
                $val = is_string($customValues[$i] ?? null) ? trim($customValues[$i]) : '';
                if ($lbl !== '' && $val !== '') {
                    $customPairs[] = [
                        'label' => mb_substr($lbl, 0, 80),
                        'value' => mb_substr($val, 0, 240),
                    ];
                }
            }
        }
        $customJson = $customPairs ? json_encode($customPairs, JSON_UNESCAPED_UNICODE) : null;

        $data = [
            $barangayId,
            field_text('property_name', 'property name', 150),
            field_choice('property_type', array_keys(PROPERTY_TYPES)),
            field_text('description', 'description', 5000, false) ?: null,
            field_text('street_address', 'street address', 255, false) ?: null,
            field_text('nearest_school', 'nearest school', 150, false) ?: null,
            field_text('nearest_landmark', 'nearby landmarks', 255, false) ?: null,
            ...$coordinates,
            $distance,
            $optionalChoice('gender_policy', ['all', 'male_only', 'female_only']),
            $curfew,
            $noCurfew,
            field_text('house_rules', 'house rules', 5000, false) ?: null,
            $optionalChoice('water_billing', array_keys(WATER_BILLING_LABELS)),
            field_text('water_notes', 'water notes', 150, false) ?: null,
            $optionalChoice('electricity_billing', array_keys(ELECTRICITY_BILLING_LABELS)),
            field_text('electricity_notes', 'electricity notes', 150, false) ?: null,
            $optionalChoice('wifi_policy', array_keys(WIFI_POLICY_LABELS)),
            field_text('wifi_notes', 'Wi-Fi notes', 150, false) ?: null,
            $optionalChoice('furnishing_status', array_keys(FURNISHING_LABELS)),
            field_text('deposit_terms', 'deposit terms', 200, false) ?: null,
            field_text('minimum_stay', 'minimum stay', 100, false) ?: null,
            $paymentMethods,
            field_text('visitor_policy', 'visitor policy', 200, false) ?: null,
            field_text('cooking_policy', 'cooking policy', 200, false) ?: null,
            field_text('pet_policy', 'pet policy', 150, false) ?: null,
            field_text('contact_person', 'contact person', 120, false) ?: null,
            field_text('contact_phone', 'contact phone', 30, false) ?: null,
            field_text('contact_social', 'social link or page', 200, false) ?: null,
            field_text('viewing_hours', 'viewing hours', 150, false) ?: null,
            $customJson,
        ];

        $keepApproved = $property !== null && $property['approval_status'] === 'approved';
        if ($property !== null) {
            if ($keepApproved) {
                query(
                    'UPDATE properties SET barangay_id = ?, property_name = ?, property_type = ?, description = ?, street_address = ?,
                            nearest_school = ?, nearest_landmark = ?, latitude = ?, longitude = ?, distance_to_campus_m = ?,
                            gender_policy = ?, curfew_time = ?, no_curfew = ?, house_rules = ?,
                            water_billing = ?, water_notes = ?, electricity_billing = ?, electricity_notes = ?,
                            wifi_policy = ?, wifi_notes = ?, furnishing_status = ?, deposit_terms = ?, minimum_stay = ?,
                            payment_methods = ?, visitor_policy = ?, cooking_policy = ?, pet_policy = ?,
                            contact_person = ?, contact_phone = ?, contact_social = ?, viewing_hours = ?, custom_details = ?
                     WHERE property_id = ?',
                    [...$data, $propertyId]
                );
            } else {
                query(
                    "UPDATE properties SET barangay_id = ?, property_name = ?, property_type = ?, description = ?, street_address = ?,
                            nearest_school = ?, nearest_landmark = ?, latitude = ?, longitude = ?, distance_to_campus_m = ?,
                            gender_policy = ?, curfew_time = ?, no_curfew = ?, house_rules = ?,
                            water_billing = ?, water_notes = ?, electricity_billing = ?, electricity_notes = ?,
                            wifi_policy = ?, wifi_notes = ?, furnishing_status = ?, deposit_terms = ?, minimum_stay = ?,
                            payment_methods = ?, visitor_policy = ?, cooking_policy = ?, pet_policy = ?,
                            contact_person = ?, contact_phone = ?, contact_social = ?, viewing_hours = ?, custom_details = ?,
                            approval_status = 'pending', approved_by = NULL, approved_at = NULL
                     WHERE property_id = ?",
                    [...$data, $propertyId]
                );
            }
        } else {
            query(
                'INSERT INTO properties (
                    barangay_id, property_name, property_type, description, street_address,
                    nearest_school, nearest_landmark, latitude, longitude, distance_to_campus_m,
                    gender_policy, curfew_time, no_curfew, house_rules,
                    water_billing, water_notes, electricity_billing, electricity_notes,
                    wifi_policy, wifi_notes, furnishing_status, deposit_terms, minimum_stay,
                    payment_methods, visitor_policy, cooking_policy, pet_policy,
                    contact_person, contact_phone, contact_social, viewing_hours, custom_details,
                    landlord_id
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [...$data, $landlordId]
            );
            $propertyId = (int) $pdo->lastInsertId();

            // Optional quick initial room on new property creation
            $initRoomNum = field('initial_room_number');
            $initRateRaw = field('initial_monthly_rate');
            if ($initRoomNum !== '' || $initRateRaw !== '') {
                $roomLabel = $initRoomNum !== '' ? mb_substr($initRoomNum, 0, 30) : 'Room 1';
                $roomRate = $initRateRaw !== '' ? decimal_amount(amount_cents($initRateRaw)) : null;
                if ($roomRate === null) {
                    throw new DomainException('Enter a monthly rate for the initial room, or leave the quick room fields blank.');
                }
                $roomCap = field('initial_max_capacity') !== '' ? field_integer('initial_max_capacity', 'bed capacity', 1, 255) : 1;
                $roomType = $optionalChoice('initial_room_type', ['solo', 'shared', 'studio']) ?? 'shared';
                query(
                    'INSERT INTO rooms (property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed, is_active)
                     VALUES (?, ?, 1, ?, ?, ?, 1)',
                    [$propertyId, $roomLabel, $roomType, $roomCap, $roomRate]
                );
            }
        }
        replace_amenities('property', $propertyId);
    }

    // Handle multi-photo upload (photos[]) and legacy single photo field
    $uploadedPaths = save_uploads('photos', 'properties', 12);
    $legacyPhoto = save_upload('photo', 'properties', false);
    if ($legacyPhoto !== null) {
        $uploadedPaths[] = $legacyPhoto;
    }
    if ($operation === 'image_add' && !$uploadedPaths) {
        throw new DomainException('Choose at least one photo to upload.');
    }
    if ($uploadedPaths) {
        $hasPrimary = (bool) scalar('SELECT COUNT(*) > 0 FROM property_images WHERE property_id = ? AND is_primary = 1', [$propertyId]);
        foreach ($uploadedPaths as $idx => $path) {
            $newUploads[] = $path;
            $isPrimary = (!$hasPrimary && $idx === 0) ? 1 : 0;
            query('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, ?)', [$propertyId, $path, $isPrimary]);
        }
    }
    if ($operation === 'image_add') {
        $count = count($uploadedPaths);
        return $count === 1 ? '1 photo added to your gallery.' : "{$count} photos added to your gallery.";
    }
    if ($property !== null && $property['approval_status'] === 'approved') {
        audit($landlordId, 'property_updated', 'properties', $propertyId);
        return 'Property details updated.';
    }
    audit($landlordId, 'property_submitted', 'properties', $propertyId);
    return 'Property saved and submitted for administrator approval.';
});
