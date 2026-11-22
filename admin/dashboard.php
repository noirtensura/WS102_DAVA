<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$administrator = require_role('admin');

$verifiedProperties = (int) scalar("SELECT COUNT(*) FROM properties p JOIN landlord_profiles lp USING (landlord_id)
    JOIN users u ON u.user_id = p.landlord_id JOIN barangays b USING (barangay_id) JOIN municipalities m USING (municipality_id)
    WHERE m.province_name = ? AND p.approval_status = 'approved'
      AND lp.verification_status = 'verified' AND u.account_status = 'active'", [DAYON_PROVINCE]);
$verifiedOwners = (int) scalar("SELECT COUNT(*) FROM landlord_profiles l JOIN users u ON u.user_id = l.landlord_id
    WHERE l.verification_status = 'verified' AND u.account_status = 'active'");
$totalStudents = (int) scalar("SELECT COUNT(*) FROM users u JOIN roles r USING (role_id) WHERE r.role_name = 'student'");
$verifiedPermits = (int) scalar("SELECT COUNT(*) FROM landlord_permits WHERE verification_status = 'verified'");
$areas = rows('SELECT CONCAT(b.barangay_name, ", ", m.municipality_name) AS area_label, SUM(v.occupied_beds) AS occupied, SUM(v.max_capacity) AS capacity
    FROM vw_room_vacancy v JOIN properties p USING (property_id) JOIN barangays b USING (barangay_id)
    JOIN municipalities m USING (municipality_id) WHERE v.is_active = 1 AND m.province_name = ?
    GROUP BY b.barangay_id, b.barangay_name, m.municipality_name ORDER BY (SUM(v.occupied_beds) / SUM(v.max_capacity)) DESC, b.barangay_name', [DAYON_PROVINCE]);
$activeLeases = (int) array_sum(array_column($areas, 'occupied'));
$capacity = (int) array_sum(array_column($areas, 'capacity'));
$occupancy = $capacity ? $activeLeases / $capacity * 100 : 0.0;
$averageRate = scalar('SELECT AVG(v.monthly_rate_per_bed)
    FROM vw_room_vacancy v JOIN properties p USING (property_id) JOIN barangays b USING (barangay_id)
    JOIN municipalities m USING (municipality_id) WHERE v.is_active = 1 AND m.province_name = ?', [DAYON_PROVINCE]);
$flaggedReports = (int) scalar("SELECT COUNT(*) FROM reports WHERE status IN ('open', 'reviewed')");

$pendingLandlords = rows("SELECT l.landlord_id, CONCAT(u.first_name, ' ', u.last_name) AS full_name, u.email, u.phone_number, u.account_status, u.created_at,
    (SELECT COUNT(*) FROM landlord_permits pm WHERE pm.landlord_id = l.landlord_id) AS permit_count,
    (SELECT COUNT(*) FROM properties p WHERE p.landlord_id = l.landlord_id) AS property_count
    FROM landlord_profiles l JOIN users u ON u.user_id = l.landlord_id
    WHERE l.verification_status = 'pending' ORDER BY u.created_at DESC LIMIT 6");

$pendingProperties = rows("SELECT p.property_id, p.property_name, p.property_type, p.updated_at, b.barangay_name, m.municipality_name,
    CONCAT(u.first_name, ' ', u.last_name) AS owner_name, lp.verification_status AS owner_status, u.account_status,
    (SELECT COUNT(*) FROM property_images pi WHERE pi.property_id = p.property_id) AS photo_count,
    (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.property_id AND r.is_active = 1) AS active_rooms
    FROM properties p JOIN landlord_profiles lp USING (landlord_id) JOIN users u ON u.user_id = p.landlord_id
    JOIN barangays b USING (barangay_id) JOIN municipalities m USING (municipality_id)
    WHERE p.approval_status = 'pending' ORDER BY p.updated_at DESC LIMIT 6");

$pendingPermits = rows("SELECT pm.*, CONCAT(u.first_name, ' ', u.last_name) AS landlord_name, p.property_name
    FROM landlord_permits pm JOIN users u ON u.user_id = pm.landlord_id
    LEFT JOIN properties p ON p.property_id = pm.property_id
    WHERE pm.verification_status = 'submitted' ORDER BY pm.created_at DESC LIMIT 6");

$pendingTotal = count($pendingLandlords) + count($pendingProperties) + count($pendingPermits);
$activity = rows("SELECT a.action_type, a.details, a.created_at, CONCAT(u.first_name, ' ', u.last_name) AS actor
    FROM audit_logs a JOIN users u ON u.user_id = a.actor_id ORDER BY a.log_id DESC LIMIT 6");

$pageTitle = 'Admin Control Center';
$pageDescription = 'Moderate boarding houses & apartments, verify landlords and optional Antique permits, and oversee platform health.';
$pageActions = static function () use ($flaggedReports, $administrator): void {
    ?>
    <a class="button secondary small" href="<?= e(url('admin/reports.php')) ?>"><?= icon('report') ?> <?= $flaggedReports ?> Open <?= $flaggedReports === 1 ? 'Report' : 'Reports' ?></a>
    <a class="button small" href="<?= e(url('admin/properties.php')) ?>"><?= icon('building') ?> Review Listings</a>
    <span class="avatar" aria-label="<?= e($administrator['full_name']) ?>"><?= e(user_initials($administrator)) ?></span>
    <?php
};
require __DIR__ . '/../includes/portal_header.php';
?>

<details class="panel admin-guide-panel" open>
    <summary class="admin-guide-summary">
        <span><?= icon('info') ?> <strong>How Admin Works in Dayon</strong> <span class="badge">Quick Workflow Guide</span></span>
        <span class="muted">Click to collapse</span>
    </summary>
    <div class="admin-guide-grid">
        <article>
            <h3>1. Verify Landlords (No Permits Required)</h3>
            <p>When a landlord registers, they can immediately post properties &amp; photos. Review their account and click <strong>Approve</strong> — business permits are strictly optional in Antique.</p>
        </article>
        <article>
            <h3>2. Approve &amp; Feature Listings</h3>
            <p>Inspect boarding houses &amp; apartments before they appear publicly. Approve in one click (even verify the owner simultaneously!), request revisions with notes, or mark standout homes as <strong>Featured</strong>.</p>
        </article>
        <article>
            <h3>3. Verify Optional Antique Permits</h3>
            <p>If a landlord uploads Barangay Clearance, Mayor's/BPLO Permit, BFP Fire Safety (FSIC), or Sanitary Permit, verify it to unlock public <strong>Permits on File</strong> trust badges for students.</p>
        </article>
        <article>
            <h3>4. Manage Users, Areas &amp; Reports</h3>
            <p>Suspend/activate accounts, reset passwords, add new Admin accounts, expand Antique barangays &amp; amenities, resolve student reports, and review the immutable Audit Log.</p>
        </article>
    </div>
</details>

<div class="metric-grid four">
    <article class="stat-card"><span><?= icon('building') ?> Live Listings</span><strong><?= $verifiedProperties ?></strong><small>Across <?= e(DAYON_PROVINCE) ?></small></article>
    <article class="stat-card"><span><?= icon('users') ?> Verified Landlords</span><strong><?= $verifiedOwners ?></strong><small><?= $totalStudents ?> registered students</small></article>
    <article class="stat-card"><span><?= icon('award') ?> Verified Permits</span><strong><?= $verifiedPermits ?></strong><small>Optional Antique badges</small></article>
    <article class="stat-card"><span><?= icon('bed') ?> Bed Occupancy</span><strong><?= e(number_format($occupancy, 1)) ?>%</strong><small><?= $activeLeases ?> of <?= $capacity ?> beds</small></article>
</div>

<section class="panel" id="verification-queue">
    <div class="panel-head">
        <div>
            <h2>Action Queue</h2>
            <p class="muted">Pending landlords, property submissions, and optional Antique permits waiting for your review.</p>
        </div>
        <span class="badge <?= $pendingTotal ? 'pending' : 'verified' ?>"><?= $pendingTotal ?> waiting</span>
    </div>

    <?php if (!$pendingTotal): ?>
        <p class="empty-state">All caught up! There are no pending landlords, listings, or permits right now.</p>
    <?php else: ?>
        <?php if ($pendingProperties): ?>
            <h3>Pending Boarding Houses &amp; Apartments</h3>
            <div class="table-wrap"><table>
                <thead><tr><th>Property</th><th>Location</th><th>Owner</th><th>Content</th><th>Quick Decision</th></tr></thead>
                <tbody>
                    <?php foreach ($pendingProperties as $prop): ?>
                        <tr>
                            <td><strong><?= e($prop['property_name']) ?></strong><br><small class="muted"><?= e(property_type_label($prop['property_type'] ?? 'boarding_house')) ?></small></td>
                            <td><?= e($prop['barangay_name']) ?>, <?= e($prop['municipality_name']) ?></td>
                            <td><?= e($prop['owner_name']) ?><br><span class="badge <?= e($prop['owner_status']) ?>"><?= e(ucfirst($prop['owner_status'])) ?> owner</span></td>
                            <td><?= (int) $prop['photo_count'] ?> photos · <?= (int) $prop['active_rooms'] ?> rooms<br><a href="<?= e(url('property.php?id=' . $prop['property_id'])) ?>">Preview listing</a></td>
                            <td>
                                <form method="post" action="<?= e(url('actions/admin_action.php')) ?>" class="queue-actions">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="operation" value="property">
                                    <input type="hidden" name="id" value="<?= (int) $prop['property_id'] ?>">
                                    <input type="hidden" name="return_to" value="overview">
                                    <?php if ($prop['account_status'] === 'active'): ?>
                                        <button class="button small approve" name="status" value="<?= $prop['owner_status'] === 'verified' ? 'approved' : 'approve_and_verify' ?>">
                                            <?= $prop['owner_status'] === 'verified' ? 'Approve &amp; Publish' : 'Verify Owner &amp; Publish' ?>
                                        </button>
                                    <?php endif; ?>
                                    <button class="button small secondary" name="status" value="revision_requested">Needs Edits</button>
                                    <button class="button small reject" name="status" value="rejected">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>

        <?php if ($pendingLandlords): ?>
            <h3>Pending Landlord Accounts</h3>
            <div class="table-wrap"><table>
                <thead><tr><th>Landlord</th><th>Contact</th><th>Activity</th><th>Quick Decision</th></tr></thead>
                <tbody>
                    <?php foreach ($pendingLandlords as $ll): ?>
                        <tr>
                            <td><strong><?= e($ll['full_name']) ?></strong><br><small class="muted">Joined <?= e(date('M j, Y', strtotime($ll['created_at']))) ?></small></td>
                            <td><?= e($ll['email']) ?><br><small class="muted"><?= e($ll['phone_number']) ?></small></td>
                            <td><?= (int) $ll['property_count'] ?> listings · <?= (int) $ll['permit_count'] ?> optional permits</td>
                            <td>
                                <form method="post" action="<?= e(url('actions/admin_action.php')) ?>" class="queue-actions">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="operation" value="verification">
                                    <input type="hidden" name="id" value="<?= (int) $ll['landlord_id'] ?>">
                                    <input type="hidden" name="return_to" value="overview">
                                    <?php if ($ll['account_status'] === 'active'): ?>
                                        <button class="button small approve" name="status" value="verified">Approve Landlord</button>
                                    <?php endif; ?>
                                    <button class="button small reject" name="status" value="rejected">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>

        <?php if ($pendingPermits): ?>
            <h3>Submitted Optional Antique Permits</h3>
            <div class="table-wrap"><table>
                <thead><tr><th>Permit</th><th>Landlord / Scope</th><th>Document</th><th>Quick Decision</th></tr></thead>
                <tbody>
                    <?php foreach ($pendingPermits as $pm): ?>
                        <tr>
                            <td><strong><?= e(permit_label($pm)) ?></strong><?php if (!empty($pm['permit_number'])): ?><br><small class="muted">#<?= e($pm['permit_number']) ?></small><?php endif; ?></td>
                            <td><?= e($pm['landlord_name']) ?><br><small class="muted"><?= $pm['property_name'] ? e($pm['property_name']) : 'All properties' ?></small></td>
                            <td>
                                <?php if (!empty($pm['document_path'])): ?>
                                    <a href="<?= e(media_url($pm['document_path'])) ?>" target="_blank" rel="noopener">Inspect file <?= icon('external') ?></a>
                                <?php else: ?><span class="muted">Declared details only</span><?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="<?= e(url('actions/admin_action.php')) ?>" class="queue-actions">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="operation" value="permit">
                                    <input type="hidden" name="id" value="<?= (int) $pm['permit_id'] ?>">
                                    <input type="hidden" name="return_to" value="overview">
                                    <button class="button small approve" name="status" value="verified">Verify Badge</button>
                                    <button class="button small reject" name="status" value="rejected">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="actions panel-actions-bottom">
        <a class="button small secondary" href="<?= e(url('admin/verifications.php')) ?>">All Landlords &amp; Permits</a>
        <a class="button small secondary" href="<?= e(url('admin/properties.php')) ?>">All Property Approvals</a>
        <a class="button small secondary" href="<?= e(url('admin/catalogs.php')) ?>">Manage Amenities &amp; Areas</a>
    </div>
</section>

<div class="dashboard-pair asymmetric">
    <section class="panel">
        <div class="panel-head"><h2>Occupancy by Area (<?= e(DAYON_PROVINCE) ?>)</h2></div>
        <?php if (!$areas): ?>
            <p class="muted">Occupancy statistics appear when properties have active rooms.</p>
        <?php else: ?>
            <div class="area-chart">
                <?php foreach ($areas as $index => $area): $rate = (float) $area['occupied'] / (float) $area['capacity'] * 100; ?>
                    <div>
                        <label for="area-<?= $index ?>"><?= e($area['area_label']) ?></label>
                        <progress id="area-<?= $index ?>" value="<?= e($rate) ?>" max="100" title="<?= (int) $area['occupied'] ?> of <?= (int) $area['capacity'] ?> beds occupied"></progress>
                        <span><?= e(number_format($rate, 1)) ?>%</span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($averageRate !== null && $averageRate !== false): ?><p class="muted panel-actions-bottom">Average monthly bed rate across Antique: <strong><?= e(money($averageRate)) ?></strong></p><?php endif; ?>
        <?php endif; ?>
    </section>
    <section class="panel">
        <div class="panel-head"><h2>Recent Audit Activity</h2></div>
        <?php if (!$activity): ?><p class="muted">Administrative actions will appear here.</p><?php else: ?>
            <ul class="audit-preview">
                <?php foreach ($activity as $entry): ?>
                    <li>
                        <strong><?= e(ucfirst(str_replace('_', ' ', $entry['action_type']))) ?></strong>
                        <?php if (!empty($entry['details'])): ?><span class="muted"><?= e(mb_strimwidth($entry['details'], 0, 90, '…')) ?></span><?php endif; ?>
                        <span class="muted"><?= e($entry['actor']) ?> · <?= e(date('M j, g:i A', strtotime($entry['created_at']))) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <a class="button secondary small panel-actions-bottom" href="<?= e(url('admin/audit_logs.php')) ?>">View Full Audit Log</a>
    </section>
</div>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
