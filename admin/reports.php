<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_forms.php';
require_role('admin');

$reports = rows("SELECT t.*, p.property_name, CONCAT(u.first_name, ' ', u.last_name) AS reporter,
        CONCAT(a.first_name, ' ', a.last_name) AS reviewer
    FROM reports t JOIN properties p ON p.property_id = t.property_id
    JOIN users u ON u.user_id = t.reporter_id
    LEFT JOIN users a ON a.user_id = t.reviewed_by
    ORDER BY FIELD(t.status, 'open', 'reviewed', 'resolved', 'dismissed'), t.created_at DESC, t.report_id DESC");
$pageTitle = 'Reports and disputes';
$pageDescription = 'Review concerns submitted by students and record the outcome.';
require __DIR__ . '/../includes/portal_header.php';
?>
<?php if ($reports === []): ?><section class="panel empty-state">There are no listing reports to review.</section><?php endif; ?>
<?php foreach ($reports as $report): ?>
    <?php
    $choices = match ($report['status']) {
        'open' => ['reviewed' => 'Mark under review', 'resolved' => 'Resolve report', 'dismissed' => 'Dismiss report'],
        'reviewed' => ['resolved' => 'Resolve report', 'dismissed' => 'Dismiss report'],
        default => ['open' => 'Reopen report'],
    };
    ?>
    <article class="panel review-layout" id="report-<?= (int) $report['report_id'] ?>">
        <div class="record-summary">
            <span class="badge <?= e($report['status']) ?>"><?= e(admin_status_label($report['status'])) ?></span>
            <h2><?= e($report['property_name']) ?></h2>
            <p class="muted">Report #<?= e($report['report_id']) ?> · <?= e($report['reporter']) ?> · <?= e($report['created_at']) ?></p>
            <p><?= nl2br(e($report['reason'])) ?></p>
            <?php if ($report['reviewer']): ?><p class="muted">Reviewed by <?= e($report['reviewer']) ?></p><?php endif; ?>
        </div>
        <div class="record-decision"><h3>Report decision</h3><?php admin_status_form('report', (int) $report['report_id'], $choices, true); ?></div>
    </article>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
