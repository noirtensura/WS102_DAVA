<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

$returnPath = 'landlord/properties.php';
$editId = filter_var(field('property_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($editId !== false && field('operation') !== 'delete') {
    $returnPath .= '?edit=' . $editId;
}

landlord_post($returnPath, function (int $landlordId, array &$newUploads, array &$obsoleteUploads): string {
    global $pdo;
    $operation = field_choice('operation', ['save', 'delete', 'image_add', 'image_delete', 'image_primary']);
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
    if (in_array($operation, ['image_delete', 'image_primary'], true)) {
        $imageId = field_integer('image_id', 'photo');
        $image = row('SELECT * FROM property_images WHERE property_id = ? AND image_id = ? FOR UPDATE', [$propertyId, $imageId]);
        if (!$image) {
            throw new DomainException('That photo is unavailable.');
        }
        if ($operation === 'image_primary') {
            query('UPDATE property_images SET is_primary = (image_id = ?) WHERE property_id = ?', [$imageId, $propertyId]);
            return 'Cover photo updated.';
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
             WHERE b.barangay_id = ? AND ((m.municipality_name = ? AND m.province_name = ?)
                 OR m.municipality_id = (SELECT municipality_id FROM barangays WHERE barangay_id = ?))',
            [$barangayId, DAYON_MUNICIPALITY, DAYON_PROVINCE, $property['barangay_id'] ?? 0]
        )) {
            throw new DomainException('Choose a barangay in ' . DAYON_LOCATION . '.');
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
            throw new DomainException('Provide both latitude and longitude for the map pin.');
        }
        $curfew = field('curfew_time') === '' ? null : field_date('curfew_time', 'H:i');
        $data = [
            $barangayId,
            field_text('property_name', 'property name', 150),
            field_text('description', 'description', 5000),
            field_text('street_address', 'street address', 255),
            ...$coordinates,
            field_integer('distance_to_campus_m', 'campus distance', 0, 1000000),
            field_choice('gender_policy', ['all', 'male_only', 'female_only']),
            $curfew,
            field_text('house_rules', 'house rules', 5000, false) ?: null,
        ];
        if ($property !== null) {
            query("UPDATE properties SET barangay_id = ?, property_name = ?, description = ?, street_address = ?, latitude = ?, longitude = ?, distance_to_campus_m = ?, gender_policy = ?, curfew_time = ?, house_rules = ?, approval_status = 'pending', approved_by = NULL, approved_at = NULL WHERE property_id = ?", [...$data, $propertyId]);
        } else {
            query('INSERT INTO properties (barangay_id, property_name, description, street_address, latitude, longitude, distance_to_campus_m, gender_policy, curfew_time, house_rules, landlord_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [...$data, $landlordId]);
            $propertyId = (int) $pdo->lastInsertId();
        }
        replace_amenities('property', $propertyId);
    }

    $path = save_upload('photo', 'properties', $operation === 'image_add');
    if ($path !== null) {
        $newUploads[] = $path;
        $primary = (int) scalar('SELECT COUNT(*) = 0 FROM property_images WHERE property_id = ?', [$propertyId]);
        query('INSERT INTO property_images (property_id, image_path, is_primary) VALUES (?, ?, ?)', [$propertyId, $path, $primary]);
    }
    if ($operation === 'image_add') {
        return 'Photo added.';
    }
    audit($landlordId, 'property_submitted', 'properties', $propertyId);
    return 'Property details saved and sent for admin review.';
});
