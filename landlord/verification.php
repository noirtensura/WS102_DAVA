<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$profile = row('SELECT * FROM landlord_profiles WHERE landlord_id = ?', [$landlordId]);
$status = $profile['verification_status'] ?? 'pending';
$feedback = $status === 'rejected'
    ? scalar("SELECT details FROM audit_logs WHERE target_table = 'landlord_profiles' AND target_id = ? AND action_type = 'landlord_rejected' ORDER BY log_id DESC LIMIT 1", [$landlordId])
    : null;
$properties = landlord_properties($landlordId);
$permits = rows(
    'SELECT lp.*, p.property_name
     FROM landlord_permits lp
     LEFT JOIN properties p ON p.property_id = lp.property_id
     WHERE lp.landlord_id = ?
     ORDER BY lp.created_at DESC, lp.permit_id DESC',
    [$landlordId]
);

$pageTitle = 'Optional Antique Permits & Verification';
$pageDescription = 'Business permits are NOT required to list on Dayon. Add any Antique boarding house or apartment permits you have to earn public trust badges.';
require __DIR__ . '/../includes/portal_header.php';
?>

<section class="panel trust-callout-panel">
    <div class="panel-head">
        <div>
            <h2>Account Verification Status</h2>
            <p class="muted">Administrators verify landlord accounts directly — <strong>no business permit is required</strong> to post properties or get approved.</p>
        </div>
        <?= landlord_badge($status) ?>
    </div>
    <?php if ($status === 'verified'): ?>
        <p>Your landlord account was verified<?= $profile['verified_at'] ? ' on ' . e(date('M j, Y', strtotime($profile['verified_at']))) : '' ?>. Adding optional permits below will <strong>not</strong> interrupt your verified status.</p>
    <?php elseif ($status === 'rejected'): ?>
        <p>Your previous review was declined. You can request a new administrator review at any time, with or without uploading permits.</p>
        <?php if ($feedback): ?><p class="flash flash-error"><strong>Reviewer note:</strong> <?= e($feedback) ?></p><?php endif; ?>
        <form method="post" action="<?= e(url('actions/landlord_verification.php')) ?>" class="actions">
            <?= csrf_field() ?>
            <input type="hidden" name="operation" value="request_review">
            <button type="submit" class="button small">Request New Admin Review</button>
        </form>
    <?php else: ?>
        <p>Your landlord account is already in the administrator review queue. While waiting, you can already <a href="<?= e(url('landlord/properties.php?new=1#property-form')) ?>">post your boarding house or apartment &amp; photos</a>.</p>
    <?php endif; ?>
</section>

<section class="panel" id="add-permit">
    <div class="panel-head">
        <div>
            <h2>Add an Optional Antique Permit or ID</h2>
            <p class="muted">Share any local permit applicable in Antique (e.g. Barangay Clearance, Mayor's/BPLO Permit, BFP Fire Safety, Sanitary Permit, Occupancy Permit, DTI, or Gov ID). Uploading a file scan is optional too.</p>
        </div>
        <span class="badge">100% Optional</span>
    </div>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('actions/landlord_verification.php')) ?>" class="form-grid" data-permit-form>
        <?= csrf_field() ?>
        <input type="hidden" name="operation" value="add_permit">
        <label class="form-field">Permit or document type
            <select name="permit_type" required data-permit-type>
                <?php foreach (ANTIQUE_PERMIT_TYPES as $typeKey => [$label, $office]): ?>
                    <option value="<?= e($typeKey) ?>" data-office="<?= e($office) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form-field">Applies to
            <select name="property_id">
                <option value="">All my properties (Landlord-level)</option>
                <?php foreach ($properties as $property): ?>
                    <option value="<?= (int) $property['property_id'] ?>"><?= e($property['property_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form-field" data-custom-permit-wrap hidden>Custom permit name
            <input type="text" name="custom_permit_name" maxlength="120" placeholder="e.g. Homeowners Association Clearance">
        </label>
        <label class="form-field">Permit / Reference number (optional)
            <input type="text" name="permit_number" maxlength="100" placeholder="e.g. BPLO-SJ-2026-0412 or BRGY-2026-088">
        </label>
        <label class="form-field">Issuing office in Antique (optional)
            <input type="text" name="issuing_office" maxlength="150" placeholder="e.g. Barangay Hall / BPLO San Jose / BFP Antique" data-permit-office>
        </label>
        <label class="form-field">Issue date (optional)
            <input type="date" name="issue_date">
        </label>
        <label class="form-field">Expiry date (optional)
            <input type="date" name="expiry_date">
        </label>
        <label class="form-field span-2">Attach photo or PDF scan (optional · JPG, PNG, WebP, or PDF up to 5 MB)
            <input type="file" name="document" accept="image/jpeg,image/png,image/webp,application/pdf">
        </label>
        <label class="form-field span-2">Notes (optional)
            <input type="text" name="notes" maxlength="255" placeholder="e.g. Renewed for 2026, covers main 2-storey building">
        </label>
        <div class="actions span-2">
            <button type="submit" class="button"><?= icon('plus') ?> Save Optional Permit</button>
        </div>
    </form>
</section>

<section class="panel" id="permits-list">
    <div class="panel-head">
        <h2>Your Submitted Permits (<?= count($permits) ?>)</h2>
        <span class="muted">Verified permits appear as public trust badges on your listing; uploaded scans stay private to you and admins.</span>
    </div>
    <?php if (!$permits): ?>
        <p class="empty-state">No permits added yet — and that's completely fine! Add any local Antique permit above whenever you wish.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Permit / Document</th><th>Property Scope</th><th>Details</th><th>Document File</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($permits as $permit): ?>
                    <tr>
                        <td><strong><?= e(permit_label($permit)) ?></strong>
                            <?php if (!empty($permit['permit_number'])): ?><br><small class="muted">#<?= e($permit['permit_number']) ?></small><?php endif; ?>
                        </td>
                        <td><?= $permit['property_name'] ? e($permit['property_name']) : '<span class="muted">All properties</span>' ?></td>
                        <td>
                            <?php if (!empty($permit['issuing_office'])): ?><?= e($permit['issuing_office']) ?><br><?php endif; ?>
                            <?php if (!empty($permit['expiry_date'])): ?><small class="muted">Expires <?= e(date('M j, Y', strtotime($permit['expiry_date']))) ?></small><?php endif; ?>
                            <?php if (!empty($permit['notes'])): ?><br><small class="muted"><?= e($permit['notes']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($permit['document_path'])): ?>
                                <a href="<?= e(media_url($permit['document_path'])) ?>" target="_blank" rel="noopener">View scan <?= icon('external') ?></a>
                            <?php else: ?>
                                <span class="muted">Declared (no scan)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= landlord_badge($permit['verification_status']) ?></td>
                        <td>
                            <form method="post" action="<?= e(url('actions/landlord_verification.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="operation" value="delete_permit">
                                <input type="hidden" name="permit_id" value="<?= (int) $permit['permit_id'] ?>">
                                <button type="submit" class="button small danger" data-confirm="Remove this permit record?">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
