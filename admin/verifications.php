<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_forms.php';
require_role('admin');

$accounts = rows("SELECT lp.*, u.email, u.first_name, u.last_name, u.account_status,
        CONCAT(v.first_name, ' ', v.last_name) AS reviewer
    FROM landlord_profiles lp JOIN users u ON u.user_id = lp.landlord_id
    LEFT JOIN users v ON v.user_id = lp.verified_by
    ORDER BY lp.verification_status = 'pending' DESC, u.created_at DESC, lp.landlord_id DESC");
$pageTitle = 'Landlord verification';
$pageDescription = 'Check the submitted government ID and permit before approving a landlord.';
require __DIR__ . '/../includes/portal_header.php';
?>
<section class="panel">
    <h2>Landlord accounts</h2>
    <?php if ($accounts === []): ?>
        <p class="empty-state">No landlord accounts have been registered.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th scope="col">Landlord</th><th scope="col">Documents</th><th scope="col">Status</th><th scope="col">Review</th></tr></thead>
            <tbody>
                <?php foreach ($accounts as $account): ?>
                    <?php
                    $hasDocuments = !empty($account['government_id_path']) && !empty($account['business_permit_path']);
                    $choices = [];
                    if ($hasDocuments && $account['account_status'] === 'active' && $account['verification_status'] !== 'verified') {
                        $choices['verified'] = 'Approve landlord';
                    }
                    if ($hasDocuments && $account['verification_status'] !== 'rejected') {
                        $choices['rejected'] = 'Reject verification';
                    }
                    ?>
                    <tr>
                        <td><strong><?= e($account['first_name'] . ' ' . $account['last_name']) ?></strong><br><span class="muted"><?= e($account['email']) ?></span></td>
                        <td>
                            <?php if (!empty($account['government_id_path'])): ?><a href="<?= e(media_url($account['government_id_path'])) ?>">Government ID</a><br><?php endif; ?>
                            <?php if (!empty($account['business_permit_path'])): ?><a href="<?= e(media_url($account['business_permit_path'])) ?>">Business or barangay permit</a><?php endif; ?>
                            <?php if (!$hasDocuments): ?><span class="muted"><?= $account['verification_status'] === 'pending' ? 'Awaiting documents' : 'Documents unavailable' ?></span><?php endif; ?>
                        </td>
                        <td><span class="badge"><?= e(admin_status_label($account['verification_status'])) ?></span><?php if ($account['reviewer']): ?><br><span class="muted"><?= e($account['reviewer']) ?> · <?= e($account['verified_at']) ?></span><?php endif; ?></td>
                        <td><?php admin_status_form('verification', (int) $account['landlord_id'], $choices, $hasDocuments); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
