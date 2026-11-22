<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$verified = landlord_verified($landlordId);
$properties = landlord_properties($landlordId);
$tickets = rows("SELECT t.*, r.room_number, p.property_name, CONCAT(u.first_name, ' ', u.last_name) AS tenant_name
    FROM maintenance_requests t JOIN leases l ON l.lease_id = t.lease_id JOIN rooms r ON r.room_id = l.room_id
    JOIN properties p ON p.property_id = r.property_id JOIN users u ON u.user_id = l.student_id
    WHERE p.landlord_id = ? ORDER BY FIELD(t.ticket_status, 'open', 'in_progress', 'resolved', 'closed'), FIELD(t.priority, 'high', 'medium', 'low'), t.created_at DESC", [$landlordId]);
$advisories = rows('SELECT a.*, p.property_name FROM announcements a JOIN properties p ON p.property_id = a.property_id WHERE p.landlord_id = ? ORDER BY a.created_at DESC, a.announcement_id DESC', [$landlordId]);
$reports = rows('SELECT q.report_id, q.status, q.reason, q.created_at, p.property_name FROM reports q JOIN properties p ON p.property_id = q.property_id WHERE p.landlord_id = ? ORDER BY q.created_at DESC, q.report_id DESC', [$landlordId]);
$pageTitle = 'Maintenance & notices';
$pageDescription = 'Respond to repair requests and publish advisories for current tenants.';
require __DIR__ . '/../includes/portal_header.php';
?>
<?php if (!$verified) landlord_verification_notice(); ?>
<section class="panel">
    <h2>Repair requests</h2>
    <?php if (!$tickets): ?><p class="empty-state">There are no maintenance tickets yet.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Ticket</th><th>Description</th><th>Status</th><th>Update</th></tr></thead>
        <tbody><?php foreach ($tickets as $ticket): ?>
            <tr>
                <td><strong>#<?= e($ticket['ticket_id']) ?> · <?= e(ucfirst($ticket['category'])) ?></strong><br><small><?= e($ticket['tenant_name']) ?><br><?= e($ticket['property_name']) ?> · Room <?= e($ticket['room_number']) ?><br><?= e(date('M j, Y', strtotime($ticket['created_at']))) ?></small></td>
                <td><?= nl2br(e($ticket['description'])) ?><?php if ($ticket['photo_path']): ?><br><a href="<?= e(media_url($ticket['photo_path'])) ?>" target="_blank" rel="noopener">View photo</a><?php endif; ?></td>
                <td><?= landlord_badge($ticket['ticket_status']) ?><br><small><?= e(ucfirst($ticket['priority'])) ?> priority</small></td>
                <td><?php if ($verified): ?>
                    <details><summary>Change status</summary>
                        <form class="form-grid" method="post" action="<?= e(url('actions/maintenance_action.php')) ?>">
                            <?= csrf_field() ?><input type="hidden" name="operation" value="ticket"><input type="hidden" name="ticket_id" value="<?= e($ticket['ticket_id']) ?>">
                            <label class="form-field">Status<select name="ticket_status"><?php foreach (['open', 'in_progress', 'resolved', 'closed'] as $status): ?><option value="<?= e($status) ?>" <?= $status === $ticket['ticket_status'] ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></label>
                            <label class="form-field">Priority<select name="priority"><?php foreach (['low', 'medium', 'high'] as $priority): ?><option value="<?= e($priority) ?>" <?= $priority === $ticket['priority'] ? 'selected' : '' ?>><?= e(ucfirst($priority)) ?></option><?php endforeach; ?></select></label>
                            <div class="actions span-2"><button type="submit" class="button small secondary">Save ticket</button></div>
                        </form>
                    </details>
                <?php else: ?>—<?php endif; ?></td>
            </tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php if ($verified && $properties): ?>
<section class="panel">
    <h2>Publish a property advisory</h2>
    <form class="form-grid" method="post" action="<?= e(url('actions/maintenance_action.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="operation" value="announce">
        <label class="form-field">Property<select name="property_id" required><option value="">Choose a property</option><?php foreach ($properties as $property): ?><option value="<?= e($property['property_id']) ?>"><?= e($property['property_name']) ?></option><?php endforeach; ?></select></label>
        <label class="form-field">Title<input name="title" maxlength="150" required></label>
        <label class="form-field span-2">Message<textarea name="message" maxlength="5000" rows="4" required></textarea></label>
        <div class="actions span-2"><button type="submit" class="button">Publish advisory</button></div>
    </form>
</section>
<?php endif; ?>
<section class="panel">
    <h2>Published advisories</h2>
    <?php if (!$advisories): ?><p class="empty-state">You have no published advisories.</p><?php else: ?>
    <?php foreach ($advisories as $advisory): ?>
        <article class="panel">
            <h3><?= e($advisory['title']) ?></h3><p class="muted"><?= e($advisory['property_name']) ?> · <?= e(date('M j, Y', strtotime($advisory['created_at']))) ?></p>
            <p><?= nl2br(e($advisory['message'])) ?></p>
            <?php if ($verified): ?>
            <details>
                <summary>Edit advisory</summary>
                <form class="form-grid" method="post" action="<?= e(url('actions/maintenance_action.php')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="announcement_id" value="<?= e($advisory['announcement_id']) ?>">
                    <label class="form-field span-2">Title<input name="title" maxlength="150" required value="<?= e($advisory['title']) ?>"></label>
                    <label class="form-field span-2">Message<textarea name="message" maxlength="5000" rows="3" required><?= e($advisory['message']) ?></textarea></label>
                    <div class="actions span-2"><button class="button small secondary" name="operation" value="update_announcement">Save advisory</button><button class="button small danger" name="operation" value="delete_announcement" data-confirm="Remove this advisory from the property?" data-confirm-label="Remove advisory" formnovalidate>Remove</button></div>
                </form>
            </details>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <?php endif; ?>
</section>
<section class="panel">
    <h2>Listing reports</h2>
    <?php if (!$reports): ?><p class="empty-state">There are no reports for your properties.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Property</th><th>Report</th><th>Submitted</th><th>Review status</th></tr></thead>
        <tbody><?php foreach ($reports as $report): ?><tr><td><?= e($report['property_name']) ?></td><td><?= nl2br(e($report['reason'])) ?></td><td><?= e(date('M j, Y', strtotime($report['created_at']))) ?></td><td><?= landlord_badge($report['status']) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
