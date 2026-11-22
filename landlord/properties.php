<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$properties = rows('SELECT p.*, b.barangay_name, m.municipality_name, (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.property_id) AS room_count FROM properties p JOIN barangays b ON b.barangay_id = p.barangay_id JOIN municipalities m ON m.municipality_id = b.municipality_id WHERE p.landlord_id = ? ORDER BY p.property_name', [$landlordId]);
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editing = $editId ? row('SELECT p.*, b.municipality_id FROM properties p JOIN barangays b ON b.barangay_id = p.barangay_id WHERE p.property_id = ? AND p.landlord_id = ?', [$editId, $landlordId]) : null;
$showForm = !$properties || isset($_GET['new']) || $editing;
if ($showForm) {
    $locationParams = [DAYON_MUNICIPALITY, DAYON_PROVINCE, $editing['municipality_id'] ?? 0];
    $supportedPlace = '(m.municipality_name = ? AND m.province_name = ?) OR m.municipality_id = ?';
    $municipalities = rows('SELECT m.* FROM municipalities m WHERE ' . $supportedPlace . ' ORDER BY municipality_name', $locationParams);
    $barangays = rows('SELECT b.*, m.municipality_name FROM barangays b JOIN municipalities m USING (municipality_id)
        WHERE ' . $supportedPlace . ' ORDER BY m.municipality_name, b.barangay_name', $locationParams);
    $municipalityId = $editing['municipality_id'] ?? ($municipalities[0]['municipality_id'] ?? null);
    $amenities = rows("SELECT * FROM amenities WHERE scope = 'property' ORDER BY amenity_name");
    $selected = $editing ? array_column(rows('SELECT amenity_id FROM property_amenities WHERE property_id = ?', [$editId]), 'amenity_id') : [];
    $photos = $editing ? rows('SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, image_id', [$editId]) : [];
    $feedback = null;
    if ($editing && in_array($editing['approval_status'], ['rejected', 'revision_requested'], true)) {
        $feedback = scalar("SELECT details FROM audit_logs WHERE target_table = 'properties' AND target_id = ? AND action_type = ? ORDER BY log_id DESC LIMIT 1", [$editId, 'property_' . $editing['approval_status']]);
    }
}
$pageTitle = 'Properties';
$pageDescription = 'Manage your boarding house profiles, gallery photos, and public listing details.';
$pageScripts = ['landlord/properties.js', 'landlord/forms.js'];
require __DIR__ . '/../includes/portal_header.php';
?>
<section class="panel">
    <div class="section-head actions"><h2>Your properties</h2><a href="<?= e(url('landlord/properties.php?new=1#property-form')) ?>" class="button small">Add property</a></div>
    <?php if (!$properties): ?><p class="empty-state">Your first property will appear here.</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Property</th><th>Barangay / municipality</th><th>Rooms</th><th>Status</th><th>Manage</th></tr></thead>
            <tbody><?php foreach ($properties as $property): ?>
                <tr><td><strong><?= e($property['property_name']) ?></strong></td><td><?= e($property['barangay_name']) ?> · <?= e($property['municipality_name']) ?></td><td><?= e($property['room_count']) ?></td><td><?= landlord_badge($property['approval_status']) ?></td><td><div class="actions"><a href="<?= e(url('landlord/properties.php?edit=' . $property['property_id'] . '#property-form')) ?>">Edit</a><a href="<?= e(url('landlord/rooms.php?property_id=' . $property['property_id'])) ?>">Rooms</a></div></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php if ($showForm): ?>
<section class="panel" id="property-form">
    <h2><?= $editing ? 'Edit property' : 'Add a property' ?></h2>
    <?php if ($feedback): ?><p><strong>Reviewer feedback</strong> <?= e($feedback) ?></p><?php endif; ?>
    <p class="muted">Listing details go to an administrator for review when saved.</p>
    <form class="form-grid" method="post" enctype="multipart/form-data" action="<?= e(url('actions/property_save.php')) ?>" data-location-form data-barangay-url="<?= e(url('api/get_barangays.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="operation" value="save">
        <input type="hidden" name="property_id" value="<?= e($editId && $editing ? $editId : '') ?>">
        <label class="form-field">Property name<input name="property_name" required maxlength="150" value="<?= e($editing['property_name'] ?? '') ?>"></label>
        <label class="form-field">Street address<input name="street_address" required maxlength="255" value="<?= e($editing['street_address'] ?? '') ?>"></label>
        <label class="form-field">Municipality or city
            <select data-municipality><option value="">Choose a municipality</option>
                <?php foreach ($municipalities as $place): ?><option value="<?= e($place['municipality_id']) ?>" <?= $municipalityId == $place['municipality_id'] ? 'selected' : '' ?>><?= e($place['municipality_name']) ?>, <?= e($place['province_name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="form-field">Barangay
            <select name="barangay_id" data-barangay required><option value="">Choose a barangay</option>
                <?php foreach ($barangays as $place): ?><option value="<?= e($place['barangay_id']) ?>" data-municipality="<?= e($place['municipality_id']) ?>" <?= ($editing['barangay_id'] ?? null) == $place['barangay_id'] ? 'selected' : '' ?>><?= e($place['barangay_name']) ?> · <?= e($place['municipality_name']) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label class="form-field">Distance to <?= e(DAYON_CAMPUS) ?> (m)<input type="number" name="distance_to_campus_m" required min="0" max="1000000" value="<?= e($editing['distance_to_campus_m'] ?? '') ?>"></label>
        <label class="form-field">Gender policy
            <select name="gender_policy"><?php foreach (['all' => 'All genders', 'female_only' => 'Female only', 'male_only' => 'Male only'] as $policy => $label): ?><option value="<?= e($policy) ?>" <?= ($editing['gender_policy'] ?? 'all') === $policy ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        </label>
        <label class="form-field">Latitude<input name="latitude" type="number" step="0.0000001" min="-90" max="90" value="<?= e($editing['latitude'] ?? '') ?>"></label>
        <label class="form-field">Longitude<input name="longitude" type="number" step="0.0000001" min="-180" max="180" value="<?= e($editing['longitude'] ?? '') ?>"></label>
        <label class="form-field">Curfew<input type="time" name="curfew_time" value="<?= e(substr($editing['curfew_time'] ?? '', 0, 5)) ?>"></label>
        <label class="form-field">Gallery photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input><img data-photo-preview hidden width="200" alt="New gallery photo preview"></label>
        <label class="form-field span-2">Description<textarea name="description" required maxlength="5000" rows="4"><?= e($editing['description'] ?? '') ?></textarea></label>
        <label class="form-field span-2">House rules<textarea name="house_rules" maxlength="5000" rows="4"><?= e($editing['house_rules'] ?? '') ?></textarea></label>
        <fieldset class="span-2"><legend>Amenities</legend><div class="actions">
            <?php foreach ($amenities as $amenity): ?><label><input name="amenities[]" type="checkbox" value="<?= e($amenity['amenity_id']) ?>" <?= in_array($amenity['amenity_id'], $selected) ? 'checked' : '' ?>> <?= e($amenity['amenity_name']) ?></label><?php endforeach; ?>
        </div></fieldset>
        <div class="actions span-2"><button type="submit" class="button">Save property</button></div>
    </form>
</section>
<?php if ($editing): ?>
<section class="panel">
    <h2>Property gallery</h2>
    <?php if ($photos): ?><div class="grid-3">
        <?php foreach ($photos as $photo): ?>
        <div>
            <img src="<?= e(media_url($photo['image_path'])) ?>" loading="lazy" width="200" alt="Photo of <?= e($editing['property_name']) ?>">
            <form method="post" action="<?= e(url('actions/property_save.php')) ?>" class="actions">
                <?= csrf_field() ?><input type="hidden" name="property_id" value="<?= e($editId) ?>"><input type="hidden" name="image_id" value="<?= e($photo['image_id']) ?>">
                <?php if ($photo['is_primary']): ?><span class="badge approved">Cover photo</span><?php else: ?><button class="button small secondary" name="operation" value="image_primary">Set as cover</button><?php endif; ?>
                <button class="button small danger" name="operation" value="image_delete" data-confirm="Remove this photo?">Remove</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('actions/property_save.php')) ?>" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="property_id" value="<?= e($editId) ?>"><input type="hidden" name="operation" value="image_add">
        <label class="form-field">Add a gallery photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required data-photo-input><img data-photo-preview hidden width="200" alt="New gallery photo preview"></label>
        <div class="actions"><button type="submit" class="button secondary small">Upload photo</button></div>
    </form>
</section>
<section class="panel">
    <h2>Remove property</h2>
    <p class="muted">Properties with reservation, lease, or report history are retained.</p>
    <form method="post" action="<?= e(url('actions/property_save.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="property_id" value="<?= e($editId) ?>">
        <button class="button danger small" name="operation" value="delete" data-confirm="Remove this property and its gallery?">Remove property</button>
    </form>
</section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
