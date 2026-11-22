<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$profile = row('SELECT * FROM landlord_profiles WHERE landlord_id = ?', [$landlordId]);
$status = $profile['verification_status'] ?? 'pending';
$feedback = $status === 'rejected' ? scalar("SELECT details FROM audit_logs WHERE target_table = 'landlord_profiles' AND target_id = ? AND action_type = 'landlord_rejected' ORDER BY log_id DESC LIMIT 1", [$landlordId]) : null;
$pageTitle = 'Landlord verification';
$pageDescription = 'Submit your government ID and a business or barangay permit for administrator review.';
require __DIR__ . '/../includes/portal_header.php';
?>
<section class="panel">
    <div class="actions"><h2>Verification status</h2><?= landlord_badge($status) ?></div>
    <?php if ($status === 'verified'): ?>
        <p>Approved<?= $profile['verified_at'] ? ' ' . e(date('M j, Y', strtotime($profile['verified_at']))) : '' ?>. Replacing a document starts a new review.</p>
    <?php elseif ($status === 'rejected'): ?>
        <p>Update your documents and submit them for another review.</p>
    <?php elseif (!empty($profile['government_id_path']) && !empty($profile['business_permit_path'])): ?>
        <p>Your documents are in the review queue.</p>
    <?php else: ?>
        <p>Upload both documents to enter the review queue.</p>
    <?php endif; ?>
    <?php if ($feedback): ?><p><strong>Reviewer feedback</strong> <?= e($feedback) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('actions/landlord_verification.php')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label class="form-field">Government ID
            <?php if (!empty($profile['government_id_path'])): ?><a href="<?= e(media_url($profile['government_id_path'])) ?>" target="_blank" rel="noopener">Open current government ID</a><?php endif; ?>
            <input type="file" name="government_id" accept="image/jpeg,image/png,image/webp,application/pdf" <?= empty($profile['government_id_path']) ? 'required' : '' ?>>
        </label>
        <label class="form-field">Business or barangay permit
            <?php if (!empty($profile['business_permit_path'])): ?><a href="<?= e(media_url($profile['business_permit_path'])) ?>" target="_blank" rel="noopener">Open current permit</a><?php endif; ?>
            <input type="file" name="business_permit" accept="image/jpeg,image/png,image/webp,application/pdf" <?= empty($profile['business_permit_path']) ? 'required' : '' ?>>
        </label>
        <p class="muted span-2">JPG, PNG, WebP, or PDF. Up to 5 MB per file.</p>
        <div class="actions span-2"><button type="submit" class="button">Submit for verification</button></div>
    </form>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
