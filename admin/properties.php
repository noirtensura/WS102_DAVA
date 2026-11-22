<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_forms.php';
require_role('admin');

$properties = rows("SELECT p.*, b.barangay_name, m.municipality_name, lp.verification_status,
        u.account_status, CONCAT(u.first_name, ' ', u.last_name) AS owner,
        (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.property_id AND r.is_active = 1) AS active_rooms,
        (SELECT image_path FROM property_images i WHERE i.property_id = p.property_id ORDER BY is_primary DESC, image_id LIMIT 1) AS image_path
    FROM properties p JOIN barangays b ON b.barangay_id = p.barangay_id
    JOIN municipalities m ON m.municipality_id = b.municipality_id
    JOIN landlord_profiles lp ON lp.landlord_id = p.landlord_id
    JOIN users u ON u.user_id = p.landlord_id
    ORDER BY p.approval_status = 'pending' DESC, p.updated_at DESC, p.property_id DESC");
$pageTitle = 'Property listings';
$pageDescription = 'Review property details, publish ready listings, or return them for changes.';
require __DIR__ . '/../includes/portal_header.php';
?>
<?php if ($properties === []): ?><div class="panel empty-state">No listings have been submitted.</div><?php endif; ?>
<?php foreach ($properties as $property): ?>
    <?php
    $choices = [];
    $publishable = $property['verification_status'] === 'verified' && $property['account_status'] === 'active' && (int) $property['active_rooms'] > 0;
    if ($publishable && $property['approval_status'] !== 'approved') {
        $choices['approved'] = 'Approve listing';
    }
    if ($property['approval_status'] !== 'revision_requested') {
        $choices['revision_requested'] = 'Request revisions';
    }
    if ($property['approval_status'] !== 'rejected') {
        $choices['rejected'] = 'Reject listing';
    }
    ?>
    <article class="panel">
        <div class="grid-2">
            <div>
                <span class="badge"><?= e(admin_status_label($property['approval_status'])) ?></span>
                <h2><?= e($property['property_name']) ?></h2>
                <p class="muted"><?= e($property['street_address']) ?>, <?= e($property['barangay_name']) ?>, <?= e($property['municipality_name']) ?></p>
                <p><?= nl2br(e($property['description'])) ?></p>
                <p class="muted"><?= e($property['owner']) ?> · <?= e($property['active_rooms']) ?> active rooms · <?= e(str_replace('_', ' ', $property['gender_policy'])) ?></p>
                <?php if (!empty($property['house_rules'])): ?><details><summary>House rules</summary><p><?= nl2br(e($property['house_rules'])) ?></p></details><?php endif; ?>
                <?php if (!empty($property['image_path'])): ?><p><a href="<?= e(media_url($property['image_path'])) ?>">View listing photo</a></p><?php endif; ?>
                <?php if ($property['approval_status'] === 'approved' && $publishable): ?><p><a href="<?= e(url('property.php?id=' . $property['property_id'])) ?>">View public listing</a></p><?php endif; ?>
            </div>
            <div>
                <?php if (!$publishable): ?><p class="muted">Publishing requires a verified, active landlord and at least one active room.</p><?php endif; ?>
                <?php admin_status_form('property', (int) $property['property_id'], $choices, true); ?>
            </div>
        </div>
    </article>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
