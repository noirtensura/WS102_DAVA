<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$administrator = require_role('admin');

$locality = [DAYON_MUNICIPALITY, DAYON_PROVINCE];
$verifiedProperties = (int) scalar("SELECT COUNT(*) FROM properties p JOIN landlord_profiles lp USING (landlord_id)
    JOIN users u ON u.user_id = p.landlord_id JOIN barangays b USING (barangay_id) JOIN municipalities m USING (municipality_id)
    WHERE m.municipality_name = ? AND m.province_name = ? AND p.approval_status = 'approved'
      AND lp.verification_status = 'verified' AND u.account_status = 'active'", $locality);
$verifiedOwners = (int) scalar("SELECT COUNT(*) FROM landlord_profiles l JOIN users u ON u.user_id = l.landlord_id
    WHERE l.verification_status = 'verified' AND u.account_status = 'active'");
$areas = rows('SELECT b.barangay_name, SUM(v.occupied_beds) AS occupied, SUM(v.max_capacity) AS capacity
    FROM vw_room_vacancy v JOIN properties p USING (property_id) JOIN barangays b USING (barangay_id)
    JOIN municipalities m USING (municipality_id) WHERE v.is_active = 1 AND m.municipality_name = ? AND m.province_name = ?
    GROUP BY b.barangay_id, b.barangay_name ORDER BY (SUM(v.occupied_beds) / SUM(v.max_capacity)) DESC, b.barangay_name', $locality);
$activeLeases = (int) array_sum(array_column($areas, 'occupied'));
$capacity = (int) array_sum(array_column($areas, 'capacity'));
$occupancy = $capacity ? $activeLeases / $capacity * 100 : 0.0;
$averageRate = scalar('SELECT AVG(v.monthly_rate_per_bed)
    FROM vw_room_vacancy v JOIN properties p USING (property_id) JOIN barangays b USING (barangay_id)
    JOIN municipalities m USING (municipality_id) WHERE v.is_active = 1 AND m.municipality_name = ? AND m.province_name = ?', $locality);
$flaggedReports = (int) scalar("SELECT COUNT(*) FROM reports WHERE status IN ('open', 'reviewed')");
$reviewCount = (int) scalar("SELECT
    (SELECT COUNT(*) FROM landlord_profiles WHERE verification_status = 'pending'
        AND government_id_path <> '' AND COALESCE(business_permit_path, '') <> '') +
    (SELECT COUNT(*) FROM properties WHERE approval_status = 'pending')");

$queue = rows("SELECT * FROM (
    SELECT 'verification' AS operation, l.landlord_id AS target_id, 'Landlord' AS entry_type,
           CONCAT(u.first_name, ' ', u.last_name) AS display_name, u.email AS context, u.created_at AS date_added,
           l.government_id_path, l.business_permit_path,
           (l.government_id_path <> '' AND COALESCE(l.business_permit_path, '') <> '') AS ready,
           (u.account_status = 'active') AS approvable
    FROM landlord_profiles l JOIN users u ON u.user_id = l.landlord_id WHERE l.verification_status = 'pending'
    UNION ALL
    SELECT 'property', p.property_id, 'Property', p.property_name, b.barangay_name, p.updated_at, NULL, NULL, 1,
           (u.account_status = 'active' AND lp.verification_status = 'verified'
              AND EXISTS (SELECT 1 FROM rooms r WHERE r.property_id = p.property_id AND r.is_active = 1))
    FROM properties p JOIN landlord_profiles lp USING (landlord_id) JOIN users u ON u.user_id = p.landlord_id
    JOIN barangays b USING (barangay_id) WHERE p.approval_status = 'pending'
    ) AS review_queue ORDER BY ready DESC, date_added DESC, target_id DESC LIMIT 8");
$activity = rows("SELECT a.action_type, a.created_at, CONCAT(u.first_name, ' ', u.last_name) AS actor
    FROM audit_logs a JOIN users u ON u.user_id = a.actor_id ORDER BY a.log_id DESC LIMIT 5");

$pageTitle = 'Platform Overview';
$pageDescription = DAYON_LOCATION;
$pageActions = static function () use ($flaggedReports, $administrator): void {
    ?>
    <a class="button secondary small" href="<?= e(url('admin/reports.php')) ?>"><?= icon('report') ?> <?= $flaggedReports ?> Open <?= $flaggedReports === 1 ? 'Report' : 'Reports' ?></a>
    <span class="avatar" aria-label="<?= e($administrator['full_name']) ?>"><?= e(user_initials($administrator)) ?></span>
    <?php
};
require __DIR__ . '/../includes/portal_header.php';
?>
<div class="metric-grid four">
    <article class="stat-card"><span>Verified Properties</span><strong><?= $verifiedProperties ?></strong></article>
    <article class="stat-card"><span>Verified Landlords</span><strong><?= $verifiedOwners ?></strong></article>
    <article class="stat-card"><span>System Occupancy Rate</span><strong><?= e(number_format($occupancy, 1)) ?>%</strong><small><?= $activeLeases ?> of <?= $capacity ?> beds</small></article>
    <article class="stat-card"><span>Active Leases</span><strong><?= $activeLeases ?></strong></article>
</div>

<section class="panel" id="verification-queue">
    <div class="panel-head"><h2>Verification Queue</h2><span class="badge pending"><?= $reviewCount ?> ready for review</span></div>
    <?php if (!$queue): ?>
        <p class="empty-state">There are no pending landlord accounts or property listings.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th scope="col">Type</th><th scope="col">Name</th><th scope="col">Date Added</th><th scope="col">Documents or Listing</th><th scope="col">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($queue as $entry):
                    $landlord = $entry['operation'] === 'verification';
                    $reviewPath = $landlord ? 'admin/verifications.php' : 'admin/properties.php';
                ?>
                    <tr>
                        <td><?= e($entry['entry_type']) ?> Verification</td>
                        <td><strong><?= e($entry['display_name']) ?></strong><br><small class="muted"><?= e($entry['context']) ?></small></td>
                        <td><?= e(date('M j, Y', strtotime($entry['date_added']))) ?></td>
                        <td>
                            <?php if (!$landlord): ?>
                                <a href="<?= e(url('property.php?id=' . $entry['target_id'])) ?>">View Listing</a>
                            <?php elseif ($entry['ready']): ?>
                                <div class="queue-document-links">
                                    <a href="<?= e(media_url($entry['government_id_path'])) ?>">Government ID</a>
                                    <a href="<?= e(media_url($entry['business_permit_path'])) ?>">Business Permit</a>
                                </div>
                            <?php else: ?><span class="badge pending">Awaiting Documents</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entry['ready']): ?>
                                <form method="post" action="<?= e(url('actions/admin_action.php')) ?>" class="queue-actions">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="operation" value="<?= e($entry['operation']) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $entry['target_id'] ?>">
                                    <input type="hidden" name="return_to" value="overview">
                                    <?php if ($entry['approvable']): ?><button class="button small approve" name="status" value="<?= $landlord ? 'verified' : 'approved' ?>">Approve</button><?php endif; ?>
                                    <button class="button small reject" name="status" value="rejected">Reject</button>
                                </form>
                            <?php else: ?><a href="<?= e(url($reviewPath)) ?>">Review Account</a><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
    <div class="actions panel-actions-bottom">
        <a class="button small secondary" href="<?= e(url('admin/verifications.php')) ?>">All Landlord Reviews</a>
        <a class="button small secondary" href="<?= e(url('admin/properties.php')) ?>">All Property Approvals</a>
    </div>
</section>

<div class="dashboard-pair asymmetric">
    <section class="panel">
        <div class="panel-head"><h2>Occupancy by Barangay</h2></div>
        <?php if (!$areas): ?>
            <p class="muted">Occupancy will appear when properties have active rooms in <?= e(DAYON_MUNICIPALITY) ?>.</p>
        <?php else: ?>
            <div class="area-chart">
                <?php foreach ($areas as $index => $area): $rate = (float) $area['occupied'] / (float) $area['capacity'] * 100; ?>
                    <div>
                        <label for="area-<?= $index ?>"><?= e($area['barangay_name']) ?></label>
                        <progress id="area-<?= $index ?>" value="<?= e($rate) ?>" max="100" title="<?= (int) $area['occupied'] ?> of <?= (int) $area['capacity'] ?> beds occupied"></progress>
                        <span><?= e(number_format($rate, 1)) ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($averageRate !== null && $averageRate !== false): ?><p class="muted panel-actions-bottom">Average monthly bed rate <?= e(money($averageRate)) ?></p><?php endif; ?>
        <?php endif; ?>
    </section>
    <section class="panel">
        <div class="panel-head"><h2>Recent Audit Logs</h2></div>
        <?php if (!$activity): ?><p class="muted">Administrative actions will appear here.</p><?php else: ?>
            <ul class="audit-preview">
                <?php foreach ($activity as $entry): ?>
                    <li><strong><?= e(ucfirst(str_replace('_', ' ', $entry['action_type']))) ?></strong><span class="muted"><?= e($entry['actor']) ?> · <?= e(date('M j, g:i A', strtotime($entry['created_at']))) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <a class="button secondary small panel-actions-bottom" href="<?= e(url('admin/audit_logs.php')) ?>">View All Audit Logs</a>
    </section>
</div>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
