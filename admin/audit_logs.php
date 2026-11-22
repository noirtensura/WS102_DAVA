<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_forms.php';
require_role('admin');

$beforeId = max(0, (int) filter_var($_GET['before'] ?? 0, FILTER_VALIDATE_INT));
$activity = rows("SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS actor, u.email
    FROM audit_logs a JOIN users u ON u.user_id = a.actor_id"
    . ($beforeId > 0 ? ' WHERE a.log_id < ?' : '')
    . ' ORDER BY a.log_id DESC LIMIT 41', $beforeId > 0 ? [$beforeId] : []);
$hasOlder = count($activity) > 40;
if ($hasOlder) {
    array_pop($activity);
}
$pageTitle = 'Audit log';
$pageDescription = 'View the record of approvals, account changes, and other privileged actions.';
require __DIR__ . '/../includes/portal_header.php';
?>
<section class="panel">
    <?php if ($activity === []): ?>
        <p class="empty-state">There are no audit entries on this page.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th scope="col">Recorded</th><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Target</th><th scope="col">Details</th></tr></thead>
            <tbody>
                <?php foreach ($activity as $entry): ?>
                    <tr>
                        <td><?= e($entry['created_at']) ?></td>
                        <td><strong><?= e($entry['actor']) ?></strong><br><span class="muted"><?= e($entry['email']) ?></span></td>
                        <td><?= e(admin_status_label($entry['action_type'])) ?></td>
                        <td><?= e(admin_status_label($entry['target_table'])) ?> #<?= e($entry['target_id']) ?></td>
                        <td><?= $entry['details'] === null ? '—' : nl2br(e($entry['details'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
    <div class="actions">
        <?php if ($beforeId > 0): ?><a class="button secondary small" href="<?= e(url('admin/audit_logs.php')) ?>">Latest entries</a><?php endif; ?>
        <?php if ($hasOlder): ?><a class="button secondary small" href="<?= e(url('admin/audit_logs.php?before=' . end($activity)['log_id'])) ?>">Older entries</a><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
