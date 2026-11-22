<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

landlord_post('landlord/verification.php', function (int $landlordId, array &$newUploads, array &$obsoleteUploads): string {
    $profile = row('SELECT * FROM landlord_profiles WHERE landlord_id = ? FOR UPDATE', [$landlordId]);
    if (!$profile) {
        throw new DomainException('Your landlord profile is unavailable.');
    }
    $id = save_upload('government_id', 'verifications', empty($profile['government_id_path']));
    if ($id !== null) {
        $newUploads[] = $id;
        $obsoleteUploads[] = $profile['government_id_path'] ?: null;
    }
    $permit = save_upload('business_permit', 'verifications', empty($profile['business_permit_path']));
    if ($permit !== null) {
        $newUploads[] = $permit;
        $obsoleteUploads[] = $profile['business_permit_path'];
    }
    if ($id === null && $permit === null) {
        throw new DomainException('Choose at least one document to upload.');
    }
    query("UPDATE landlord_profiles SET government_id_path = ?, business_permit_path = ?, verification_status = 'pending', verified_by = NULL, verified_at = NULL WHERE landlord_id = ?", [$id ?? $profile['government_id_path'], $permit ?? $profile['business_permit_path'], $landlordId]);
    audit($landlordId, 'landlord_verification_submitted', 'landlord_profiles', $landlordId);
    return 'Your documents were sent for review.';
});
