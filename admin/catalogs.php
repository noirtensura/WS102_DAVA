<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$amenities = rows('SELECT a.*,
    (SELECT COUNT(*) FROM property_amenities pa WHERE pa.amenity_id = a.amenity_id) +
    (SELECT COUNT(*) FROM room_amenities ra WHERE ra.amenity_id = a.amenity_id) AS usage_count
    FROM amenities a ORDER BY a.scope, a.amenity_name');

$municipalities = rows(
    'SELECT m.*, (SELECT COUNT(*) FROM barangays b WHERE b.municipality_id = m.municipality_id) AS barangay_count
     FROM municipalities m WHERE m.province_name = ? ORDER BY (m.municipality_name = ?) DESC, m.municipality_name',
    [DAYON_PROVINCE, DAYON_MUNICIPALITY]
);

$pageTitle = 'Amenities & Antique Areas';
$pageDescription = 'Maintain the shared list of boarding house/apartment necessities and barangays across Antique.';
require __DIR__ . '/../includes/portal_header.php';
?>

<div class="grid-2">
    <section class="panel" id="amenities-section">
        <h2>Master Amenities &amp; Necessities (<?= count($amenities) ?>)</h2>
        <p class="muted">Landlords can check any of these on their property or room forms, and students can filter by them.</p>
        <form method="post" action="<?= e(url('actions/admin_action.php')) ?>" class="form-grid" style="margin-top:12px;">
            <?= csrf_field() ?>
            <input type="hidden" name="operation" value="amenity_add">
            <label class="form-field">Amenity / Necessity name
                <input name="amenity_name" required maxlength="80" placeholder="e.g. Solar Streetlights or Study Table">
            </label>
            <label class="form-field">Applies to
                <select name="scope" required>
                    <option value="property">Whole Property</option>
                    <option value="room">Individual Room</option>
                </select>
            </label>
            <div class="actions span-2"><button type="submit" class="button small"><?= icon('plus') ?> Add Amenity</button></div>
        </form>
        <div class="table-wrap" style="margin-top:14px;"><table>
            <thead><tr><th>Amenity</th><th>Scope</th><th>Used</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($amenities as $item): ?>
                    <tr>
                        <td><strong><?= e($item['amenity_name']) ?></strong></td>
                        <td><span class="badge"><?= e(ucfirst($item['scope'])) ?></span></td>
                        <td><?= (int) $item['usage_count'] ?></td>
                        <td>
                            <form method="post" action="<?= e(url('actions/admin_action.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="operation" value="amenity_delete">
                                <input type="hidden" name="id" value="<?= (int) $item['amenity_id'] ?>">
                                <button type="submit" class="button small secondary" data-confirm="Remove <?= e($item['amenity_name']) ?> from the master catalog?">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <section class="panel" id="areas-section">
        <h2>Antique Municipalities &amp; Barangays</h2>
        <p class="muted">All 18 municipalities of Antique are available. Add extra barangays anytime so landlords can list anywhere in Antique.</p>
        <form method="post" action="<?= e(url('actions/admin_action.php')) ?>" class="form-grid" style="margin-top:12px;">
            <?= csrf_field() ?>
            <input type="hidden" name="operation" value="barangay_add">
            <label class="form-field">Municipality
                <select name="municipality_id" required>
                    <?php foreach ($municipalities as $town): ?>
                        <option value="<?= (int) $town['municipality_id'] ?>"><?= e($town['municipality_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form-field">New Barangay name
                <input name="barangay_name" required maxlength="100" placeholder="e.g. Barangay San Jose">
            </label>
            <div class="actions span-2"><button type="submit" class="button small"><?= icon('plus') ?> Add Barangay</button></div>
        </form>
        <div class="table-wrap" style="margin-top:14px;"><table>
            <thead><tr><th>Municipality</th><th>Province</th><th>Barangays</th></tr></thead>
            <tbody>
                <?php foreach ($municipalities as $town): ?>
                    <tr>
                        <td><strong><?= e($town['municipality_name']) ?></strong></td>
                        <td><?= e($town['province_name']) ?></td>
                        <td><?= (int) $town['barangay_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
</div>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
