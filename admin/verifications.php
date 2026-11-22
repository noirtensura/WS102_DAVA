<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_forms.php';
require_role('admin');

$accounts = rows("SELECT lp.*, u.email, u.first_name, u.last_name, u.phone_number, u.account_status, u.created_at,
        CONCAT(v.first_name, ' ', v.last_name) AS reviewer,
        (SELECT COUNT(*) FROM properties p WHERE p.landlord_id = lp.landlord_id) AS property_count,
        (SELECT COUNT(*) FROM landlord_permits pm WHERE pm.landlord_id = lp.landlord_id) AS permit_count
    FROM landlord_profiles lp JOIN users u ON u.user_id = lp.landlord_id
    LEFT JOIN users v ON v.user_id = lp.verified_by
    ORDER BY lp.verification_status = 'pending' DESC, u.created_at DESC, lp.landlord_id DESC");

$permits = rows("SELECT pm.*, CONCAT(u.first_name, ' ', u.last_name) AS landlord_name, u.email, p.property_name,
        CONCAT(v.first_name, ' ', v.last_name) AS reviewer
    FROM landlord_permits pm
    JOIN users u ON u.user_id = pm.landlord_id
    LEFT JOIN properties p ON p.property_id = pm.property_id
    LEFT JOIN users v ON v.user_id = pm.verified_by
    ORDER BY FIELD(pm.verification_status, 'submitted', 'verified', 'rejected'), pm.created_at DESC, pm.permit_id DESC");

$pageTitle = 'Landlords & Optional Antique Permits';
$pageDescription = 'Approve landlord accounts (permits are optional) and verify any submitted Antique permits to display public trust badges.';
require __DIR__ . '/../includes/portal_header.php';
?>

<section class="panel">
    <div class="panel-head">
        <div>
            <h2>Landlord Accounts</h2>
            <p class="muted">You can approve any active landlord account even if they have not uploaded a business permit.</p>
        </div>
    </div>
    <?php if ($accounts === []): ?>
        <p class="empty-state">No landlord accounts have been registered yet.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th scope="col">Landlord</th><th scope="col">Listings &amp; Permits</th><th scope="col">Status</th><th scope="col">Review Decision</th></tr></thead>
            <tbody>
                <?php foreach ($accounts as $account): ?>
                    <?php
                    $choices = [];
                    if ($account['account_status'] === 'active' && $account['verification_status'] !== 'verified') {
                        $choices['verified'] = 'Approve landlord';
                    }
                    if ($account['verification_status'] !== 'rejected') {
                        $choices['rejected'] = 'Reject verification';
                    }
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($account['first_name'] . ' ' . $account['last_name']) ?></strong>
                            <br><span class="muted"><?= e($account['email']) ?> · <?= e($account['phone_number']) ?></span>
                        </td>
                        <td>
                            <?= (int) $account['property_count'] ?> <?= (int) $account['property_count'] === 1 ? 'listing' : 'listings' ?><br>
                            <span class="muted"><?= (int) $account['permit_count'] ?> optional <?= (int) $account['permit_count'] === 1 ? 'permit' : 'permits' ?> submitted</span>
                            <?php if (!empty($account['government_id_path'])): ?><br><a href="<?= e(media_url($account['government_id_path'])) ?>" target="_blank" rel="noopener">Legacy Gov ID</a><?php endif; ?>
                            <?php if (!empty($account['business_permit_path'])): ?><br><a href="<?= e(media_url($account['business_permit_path'])) ?>" target="_blank" rel="noopener">Legacy Permit</a><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= e($account['verification_status']) ?>"><?= e(admin_status_label($account['verification_status'])) ?></span>
                            <?php if ($account['reviewer']): ?><br><span class="muted"><?= e($account['reviewer']) ?> · <?= e(substr((string) $account['verified_at'], 0, 10)) ?></span><?php endif; ?>
                        </td>
                        <td><?php admin_status_form('verification', (int) $account['landlord_id'], $choices, true); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="panel" id="permits-review">
    <div class="panel-head">
        <div>
            <h2>Optional Antique Permits Review</h2>
            <p class="muted">Verify voluntarily uploaded permits (Barangay Clearance, Mayor's/BPLO Permit, BFP Fire Safety, Sanitary Permit, etc.) to display trust badges on public listings.</p>
        </div>
    </div>
    <?php if ($permits === []): ?>
        <p class="empty-state">No optional permits have been submitted yet.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th scope="col">Permit &amp; Office</th><th scope="col">Landlord &amp; Scope</th><th scope="col">Document</th><th scope="col">Status</th><th scope="col">Badge Decision</th></tr></thead>
            <tbody>
                <?php foreach ($permits as $permit):
                    $permitChoices = [];
                    if ($permit['verification_status'] !== 'verified') {
                        $permitChoices['verified'] = 'Verify permit badge';
                    }
                    if ($permit['verification_status'] !== 'rejected') {
                        $permitChoices['rejected'] = 'Reject permit';
                    }
                ?>
                    <tr>
                        <td>
                            <strong><?= e(permit_label($permit)) ?></strong>
                            <?php if (!empty($permit['permit_number'])): ?><br><small class="muted">#<?= e($permit['permit_number']) ?></small><?php endif; ?>
                            <?php if (!empty($permit['issuing_office'])): ?><br><small class="muted"><?= e($permit['issuing_office']) ?></small><?php endif; ?>
                            <?php if (!empty($permit['expiry_date'])): ?><br><small class="muted">Valid until <?= e(date('M j, Y', strtotime($permit['expiry_date']))) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e($permit['landlord_name']) ?></strong><br>
                            <span class="muted"><?= $permit['property_name'] ? e($permit['property_name']) : 'Account-wide (all properties)' ?></span>
                            <?php if (!empty($permit['notes'])): ?><br><small class="muted"><?= e($permit['notes']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($permit['document_path'])): ?>
                                <a class="button small secondary" href="<?= e(media_url($permit['document_path'])) ?>" target="_blank" rel="noopener">Open scan <?= icon('external') ?></a>
                            <?php else: ?>
                                <span class="muted">Declared details only</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= e($permit['verification_status'] === 'submitted' ? 'pending' : $permit['verification_status']) ?>"><?= e(admin_status_label($permit['verification_status'])) ?></span>
                            <?php if ($permit['reviewer']): ?><br><small class="muted"><?= e($permit['reviewer']) ?></small><?php endif; ?>
                        </td>
                        <td><?php admin_status_form('permit', (int) $permit['permit_id'], $permitChoices, true); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
