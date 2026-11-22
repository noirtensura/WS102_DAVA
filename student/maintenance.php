<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/_support.php';

$student = require_role('student');
$lease = student_active_lease_or_redirect((int) $student['user_id']);
$tickets = rows('SELECT * FROM maintenance_requests WHERE lease_id = ? ORDER BY created_at DESC, ticket_id DESC', [$lease['lease_id']]);
$advisories = rows('SELECT title, message, created_at FROM announcements WHERE property_id = ? ORDER BY created_at DESC, announcement_id DESC', [$lease['property_id']]);
$pageTitle = 'Maintenance and advisories';
$pageDescription = 'Report a repair for room ' . $lease['room_number'] . ' at ' . $lease['property_name'] . ' and follow property updates.';
$pageScripts = ['student/upload-preview.js'];
require __DIR__ . '/../includes/portal_header.php';
?>

<div class="grid-2">
    <section class="panel" aria-labelledby="repair-heading">
        <h2 id="repair-heading">Request a repair</h2>
        <form method="post" enctype="multipart/form-data" action="<?= e(url('actions/student_maintenance.php')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label class="form-field">Category
                    <select name="category" required>
                        <option value="">Choose a category</option>
                        <option value="electrical">Electrical</option>
                        <option value="plumbing">Plumbing</option>
                        <option value="furniture">Furniture</option>
                        <option value="internet">Internet</option>
                        <option value="other">Other</option>
                    </select>
                </label>
                <label class="form-field">Priority
                    <select name="priority" required>
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </label>
            </div>
            <label class="form-field">Describe what needs repair<textarea name="description" rows="4" maxlength="5000" required></textarea></label>
            <label class="form-field">Photo (optional)
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-upload-preview="repair-preview">
                <small class="muted">JPEG, PNG or WebP, up to 5 MB.</small>
            </label>
            <img id="repair-preview" alt="Repair photo preview" width="200" hidden>
            <div class="actions"><button class="button" type="submit">Send repair request</button></div>
        </form>
    </section>
    <section class="panel" aria-labelledby="advisories-heading">
        <h2 id="advisories-heading">Property advisories</h2>
        <?php if (!$advisories): ?><p class="muted">Your landlord has not posted any advisories yet.</p><?php endif; ?>
        <?php foreach ($advisories as $notice): ?>
            <article>
                <h3><?= e($notice['title']) ?></h3>
                <p><?= nl2br(e($notice['message'])) ?></p>
                <p class="muted"><?= e(student_short_date($notice['created_at'])) ?></p>
            </article>
        <?php endforeach; ?>
    </section>
</div>

<section class="panel" aria-labelledby="repair-history-heading">
    <h2 id="repair-history-heading">Repair requests</h2>
    <?php if (!$tickets): ?>
        <p class="muted">You have no repair requests on this lease.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th scope="col">Issue</th><th scope="col">Priority</th><th scope="col">Submitted</th><th scope="col">Status</th></tr></thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><strong><?= e(student_status($ticket['category'])) ?></strong><div><?= nl2br(e($ticket['description'])) ?></div>
                                <?php if ($ticket['photo_path']): ?><a href="<?= e(media_url($ticket['photo_path'])) ?>">View photo</a><?php endif; ?>
                            </td>
                            <td><?= e(student_status($ticket['priority'])) ?></td>
                            <td><?= e(student_short_date($ticket['created_at'])) ?></td>
                            <td><span class="badge <?= e($ticket['ticket_status']) ?>"><?= e(student_status($ticket['ticket_status'])) ?></span>
                                <?php if ($ticket['resolved_at']): ?><div class="muted"><?= e(student_short_date($ticket['resolved_at'])) ?></div><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
