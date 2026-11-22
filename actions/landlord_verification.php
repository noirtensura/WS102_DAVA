<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

landlord_post('landlord/verification.php', function (int $landlordId, array &$newUploads, array &$obsoleteUploads): string {
    global $pdo;
    $profile = row('SELECT * FROM landlord_profiles WHERE landlord_id = ? FOR UPDATE', [$landlordId]);
    if (!$profile) {
        throw new DomainException('Your landlord profile is unavailable.');
    }
    $operation = field_choice('operation', ['add_permit', 'delete_permit', 'request_review']);

    if ($operation === 'request_review') {
        if ($profile['verification_status'] === 'verified') {
            return 'Your landlord account is already verified.';
        }
        query("UPDATE landlord_profiles SET verification_status = 'pending', verified_by = NULL, verified_at = NULL WHERE landlord_id = ?", [$landlordId]);
        audit($landlordId, 'landlord_verification_submitted', 'landlord_profiles', $landlordId, 'Requested admin review (optional permits).');
        return 'Your account is back in the administrator review queue.';
    }

    if ($operation === 'delete_permit') {
        $permitId = field_integer('permit_id', 'permit');
        $permit = row('SELECT * FROM landlord_permits WHERE permit_id = ? AND landlord_id = ? FOR UPDATE', [$permitId, $landlordId]);
        if (!$permit) {
            throw new DomainException('Permit record was not found.');
        }
        if (!empty($permit['document_path'])) {
            $obsoleteUploads[] = $permit['document_path'];
        }
        query('DELETE FROM landlord_permits WHERE permit_id = ?', [$permitId]);
        audit($landlordId, 'permit_deleted', 'landlord_permits', $permitId, permit_label($permit));
        return 'Permit record removed.';
    }

    // add_permit — completely optional, file scan also optional
    $permitType = field_choice('permit_type', array_keys(ANTIQUE_PERMIT_TYPES));
    $customName = $permitType === 'other_local_permit'
        ? field_text('custom_permit_name', 'custom permit name', 120, true)
        : null;
    $propertyIdRaw = field('property_id');
    $propertyId = null;
    if ($propertyIdRaw !== '') {
        $propertyId = field_integer('property_id', 'property');
        owned_property($landlordId, $propertyId);
    }
    $permitNumber = field_text('permit_number', 'permit number', 100, false) ?: null;
    $issuingOffice = field_text('issuing_office', 'issuing office', 150, false) ?: null;
    $issueDate = field('issue_date') === '' ? null : field_date('issue_date', 'Y-m-d');
    $expiryDate = field('expiry_date') === '' ? null : field_date('expiry_date', 'Y-m-d');
    if ($issueDate && $expiryDate && $expiryDate < $issueDate) {
        throw new DomainException('Expiry date must be on or after the issue date.');
    }
    $notes = field_text('notes', 'notes', 255, false) ?: null;
    $docPath = save_upload('document', 'verifications', false);
    if ($docPath !== null) {
        $newUploads[] = $docPath;
    }

    query(
        'INSERT INTO landlord_permits (landlord_id, property_id, permit_type, custom_permit_name, permit_number, issuing_office, issue_date, expiry_date, document_path, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$landlordId, $propertyId, $permitType, $customName, $permitNumber, $issuingOffice, $issueDate, $expiryDate, $docPath, $notes]
    );
    $permitId = (int) $pdo->lastInsertId();

    // Keep legacy columns in sync when relevant without resetting an already-verified landlord!
    if ($docPath !== null && $permitType === 'government_id' && empty($profile['government_id_path'])) {
        query('UPDATE landlord_profiles SET government_id_path = ? WHERE landlord_id = ?', [$docPath, $landlordId]);
    } elseif ($docPath !== null && in_array($permitType, ['mayors_business_permit', 'barangay_clearance'], true) && empty($profile['business_permit_path'])) {
        query('UPDATE landlord_profiles SET business_permit_path = ? WHERE landlord_id = ?', [$docPath, $landlordId]);
    }

    audit($landlordId, 'permit_submitted', 'landlord_permits', $permitId, ANTIQUE_PERMIT_TYPES[$permitType][0]);
    return 'Optional permit saved and queued for admin badge verification.';
});
