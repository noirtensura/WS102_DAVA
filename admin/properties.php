<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_forms.php';
require_role('admin');

$filterStatus = is_string($_GET['status'] ?? null) && in_array($_GET['status'], ['pending', 'approved', 'revision_requested', 'rejected'], true) ? $_GET['status'] : '';
$where = $filterStatus ? 'WHERE p.approval_status = ?' : '';
$params = $filterStatus ? [$filterStatus] : [];

$properties = rows("SELECT p.*, b.barangay_name, m.municipality_name, lp.verification_status,
        u.account_status, CONCAT(u.first_name, ' ', u.last_name) AS owner, u.phone_number AS owner_phone,
        (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.property_id AND r.is_active = 1) AS active_rooms,
        (SELECT COUNT(*) FROM property_images i WHERE i.property_id = p.property_id) AS photo_count,
        (SELECT COUNT(*) FROM landlord_permits pm WHERE pm.landlord_id = p.landlord_id AND (pm.property_id = p.property_id OR pm.property_id IS NULL) AND pm.verification_status = 'verified') AS verified_permits,
        (SELECT image_path FROM property_images i WHERE i.property_id = p.property_id ORDER BY is_primary DESC, image_id LIMIT 1) AS image_path
    FROM properties p JOIN barangays b ON b.barangay_id = p.barangay_id
    JOIN municipalities m ON m.municipality_id = b.municipality_id
    JOIN landlord_profiles lp ON lp.landlord_id = p.landlord_id
    JOIN users u ON u.user_id = p.landlord_id
    {$where}
    ORDER BY p.approval_status = 'pending' DESC, p.is_featured DESC, p.updated_at DESC, p.property_id DESC", $params);

$pageTitle = 'Property Approvals';
$pageDescription = 'Inspect boarding houses & apartments, publish listings, feature top picks, or request revisions.';
require __DIR__ . '/../includes/portal_header.php';
?>

<section class="panel">
    <nav class="actions" aria-label="Filter listings by status">
        <a class="button small <?= $filterStatus === '' ? '' : 'secondary' ?>" href="<?= e(url('admin/properties.php')) ?>">All Listings</a>
        <?php foreach (['pending' => 'Pending Review', 'approved' => 'Published', 'revision_requested' => 'Revision Requested', 'rejected' => 'Rejected'] as $key => $label): ?>
            <a class="button small <?= $filterStatus === $key ? '' : 'secondary' ?>" href="<?= e(url('admin/properties.php?status=' . $key)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</section>

<?php if ($properties === []): ?><div class="panel empty-state">No listings match this filter.</div><?php endif; ?>
<?php foreach ($properties as $property): ?>
    <?php
    $choices = [];
    $ownerActive = $property['account_status'] === 'active';
    $ownerVerified = $property['verification_status'] === 'verified';
    if ($ownerActive && $property['approval_status'] !== 'approved') {
        if ($ownerVerified) {
            $choices['approved'] = 'Approve & Publish Listing';
        } else {
            $choices['approve_and_verify'] = 'Verify Owner & Publish Listing';
        }
    }
    if ($property['approval_status'] !== 'revision_requested') {
        $choices['revision_requested'] = 'Request Revisions';
    }
    if ($property['approval_status'] !== 'rejected') {
        $choices['rejected'] = 'Reject Listing';
    }
    if ($property['approval_status'] === 'approved') {
        $choices['pending'] = 'Unpublish (Set Pending)';
    }
    ?>
    <article class="panel" id="listing-review-<?= (int) $property['property_id'] ?>">
        <div class="review-layout">
            <div class="record-summary">
                <div class="actions">
                    <span class="badge <?= e($property['approval_status']) ?>"><?= e(admin_status_label($property['approval_status'])) ?></span>
                    <span class="badge"><?= e(property_type_label($property['property_type'] ?? 'boarding_house')) ?></span>
                    <?php if (!empty($property['is_featured'])): ?><span class="badge verified"><?= icon('star') ?> Featured</span><?php endif; ?>
                    <?php if ((int) $property['verified_permits'] > 0): ?><span class="badge verified"><?= icon('shield') ?> <?= (int) $property['verified_permits'] ?> Verified Permits</span><?php endif; ?>
                </div>
                <h2><?= e($property['property_name']) ?></h2>
                <p class="muted"><?= e(($property['street_address'] ? $property['street_address'] . ', ' : '') . $property['barangay_name'] . ', ' . $property['municipality_name']) ?></p>
                <?php if (!empty($property['image_path'])): ?><img class="record-cover" src="<?= e(media_url($property['image_path'])) ?>" alt="" loading="lazy"><?php endif; ?>
                <?php if (!empty($property['description'])): ?><p><?= nl2br(e($property['description'])) ?></p><?php endif; ?>
                <p class="muted">
                    Owner: <strong><?= e($property['owner']) ?></strong> (<?= e($property['owner_phone']) ?> · <?= e(ucfirst($property['verification_status'])) ?>) ·
                    <?= (int) $property['photo_count'] ?> photos · <?= (int) $property['active_rooms'] ?> active rooms
                </p>
                <?php if ((int) $property['active_rooms'] === 0): ?>
                    <p class="muted"><small>Note: No rooms added yet. Listing can still be published as "Inquire for rates".</small></p>
                <?php endif; ?>
                <?php if (!empty($property['house_rules'])): ?><details><summary>House rules</summary><p><?= nl2br(e($property['house_rules'])) ?></p></details><?php endif; ?>
                <div class="actions">
                    <a class="button secondary small" href="<?= e(url('property.php?id=' . $property['property_id'])) ?>">Preview Full Page <?= icon('arrow') ?></a>
                    <?php if ($property['approval_status'] === 'approved'): ?>
                        <form method="post" action="<?= e(url('actions/admin_action.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="operation" value="property">
                            <input type="hidden" name="id" value="<?= (int) $property['property_id'] ?>">
                            <button class="button small secondary" name="status" value="<?= !empty($property['is_featured']) ? 'unfeature' : 'feature' ?>">
                                <?= icon('star') ?> <?= !empty($property['is_featured']) ? 'Remove Featured' : 'Mark as Featured' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="record-decision">
                <h3>Review Decision</h3>
                <?php if (!$ownerActive): ?><p class="muted">Activate this landlord's account in User Accounts before publishing.</p><?php endif; ?>
                <?php admin_status_form('property', (int) $property['property_id'], $choices, true); ?>
            </div>
        </div>
    </article>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
