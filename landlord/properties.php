<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$properties = rows(
    'SELECT p.*, b.barangay_name, m.municipality_name,
            (SELECT COUNT(*) FROM rooms r WHERE r.property_id = p.property_id) AS room_count,
            (SELECT COUNT(*) FROM property_images pi WHERE pi.property_id = p.property_id) AS photo_count,
            (SELECT image_path FROM property_images pi WHERE pi.property_id = p.property_id ORDER BY is_primary DESC, image_id LIMIT 1) AS cover_path
     FROM properties p JOIN barangays b ON b.barangay_id = p.barangay_id
     JOIN municipalities m ON m.municipality_id = b.municipality_id
     WHERE p.landlord_id = ? ORDER BY p.updated_at DESC, p.property_name',
    [$landlordId]
);
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$editing = $editId ? row('SELECT p.*, b.municipality_id FROM properties p JOIN barangays b ON b.barangay_id = p.barangay_id WHERE p.property_id = ? AND p.landlord_id = ?', [$editId, $landlordId]) : null;
$showForm = !$properties || isset($_GET['new']) || $editing;

if ($showForm) {
    $locationParams = [DAYON_PROVINCE, $editing['municipality_id'] ?? 0, DAYON_MUNICIPALITY];
    $supportedPlace = 'm.province_name = ? OR m.municipality_id = ?';
    $municipalities = rows('SELECT m.* FROM municipalities m WHERE ' . $supportedPlace . ' ORDER BY (m.municipality_name = ?) DESC, m.municipality_name', $locationParams);
    $barangays = rows('SELECT b.*, m.municipality_name FROM barangays b JOIN municipalities m USING (municipality_id)
        WHERE ' . $supportedPlace . ' ORDER BY (m.municipality_name = ?) DESC, m.municipality_name, b.barangay_name', $locationParams);
    $municipalityId = $editing['municipality_id'] ?? ($municipalities[0]['municipality_id'] ?? null);
    $amenities = rows("SELECT * FROM amenities WHERE scope = 'property' ORDER BY amenity_name");
    $selected = $editing ? array_column(rows('SELECT amenity_id FROM property_amenities WHERE property_id = ?', [$editId]), 'amenity_id') : [];
    $photos = $editing ? rows('SELECT * FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, image_id', [$editId]) : [];
    $selectedPayments = $editing && !empty($editing['payment_methods'])
        ? array_map('trim', explode(',', (string) $editing['payment_methods']))
        : [];
    $customDetails = $editing ? parse_custom_details($editing['custom_details'] ?? null) : [];
    $feedback = null;
    if ($editing && in_array($editing['approval_status'], ['rejected', 'revision_requested'], true)) {
        $feedback = scalar("SELECT details FROM audit_logs WHERE target_table = 'properties' AND target_id = ? AND action_type = ? ORDER BY log_id DESC LIMIT 1", [$editId, 'property_' . $editing['approval_status']]);
    }
}

$pageTitle = 'Properties & Photos';
$pageDescription = 'Post your boarding house or apartment, upload multiple pictures, and share only the necessities you want displayed.';
$pageScripts = ['landlord/properties.js'];
$pageActions = static function (): void {
    ?>
    <a href="<?= e(url('landlord/properties.php?new=1#property-form')) ?>" class="button"><?= icon('plus') ?> Add Property</a>
    <?php
};
require __DIR__ . '/../includes/portal_header.php';
?>

<section class="panel">
    <div class="section-head">
        <h2>Your Listings (<?= count($properties) ?>)</h2>
        <span class="muted">Select any property to edit details, upload more photos, or manage rooms.</span>
    </div>
    <?php if (!$properties): ?>
        <p class="empty-state">No properties posted yet. Use the form below to post your first boarding house or apartment.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Property</th><th>Type</th><th>Location</th><th>Photos</th><th>Rooms</th><th>Status</th><th>Manage</th></tr></thead>
            <tbody><?php foreach ($properties as $property): ?>
                <tr>
                    <td>
                        <div class="table-property-cell">
                            <?php if (!empty($property['cover_path'])): ?>
                                <img src="<?= e(media_url($property['cover_path'])) ?>" alt="" class="table-thumb" loading="lazy">
                            <?php endif; ?>
                            <strong><?= e($property['property_name']) ?></strong>
                        </div>
                    </td>
                    <td><?= e(property_type_label($property['property_type'] ?? 'boarding_house')) ?></td>
                    <td><?= e($property['barangay_name']) ?>, <?= e($property['municipality_name']) ?></td>
                    <td><?= (int) $property['photo_count'] ?></td>
                    <td><?= (int) $property['room_count'] ?></td>
                    <td><?= landlord_badge($property['approval_status']) ?></td>
                    <td><div class="actions">
                        <a class="button small secondary" href="<?= e(url('landlord/properties.php?edit=' . $property['property_id'] . '#property-form')) ?>">Edit</a>
                        <a class="button small secondary" href="<?= e(url('landlord/rooms.php?property_id=' . $property['property_id'])) ?>">Rooms</a>
                        <a class="button small secondary" href="<?= e(url('property.php?id=' . $property['property_id'])) ?>">Preview</a>
                    </div></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php if ($showForm): ?>
<section class="panel property-editor-panel" id="property-form">
    <div class="panel-head">
        <div>
            <h2><?= $editing ? 'Edit ' . e($editing['property_name']) : 'Post a Boarding House or Apartment' ?></h2>
            <p class="muted">Only <strong>Property Name</strong>, <strong>Type</strong>, and <strong>Barangay</strong> are required. Fill in every detail or only what you want students to see — blank fields stay hidden automatically.</p>
        </div>
        <?php if ($editing): ?>
            <a class="button small secondary" href="<?= e(url('property.php?id=' . $editId)) ?>">Preview Public View <?= icon('external') ?></a>
        <?php endif; ?>
    </div>

    <?php if ($feedback): ?>
        <div class="flash flash-error" role="status"><strong>Reviewer feedback:</strong> <?= e($feedback) ?></div>
    <?php endif; ?>

    <form class="property-editor-form" method="post" enctype="multipart/form-data" action="<?= e(url('actions/property_save.php')) ?>" data-location-form data-barangay-url="<?= e(url('api/get_barangays.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="operation" value="save">
        <input type="hidden" name="property_id" value="<?= e($editId && $editing ? $editId : '') ?>">

        <!-- 1. Essentials -->
        <div class="editor-section">
            <div class="editor-section-head">
                <h3>1. Essentials</h3>
                <span class="badge active">Required</span>
            </div>
            <div class="form-grid">
                <label class="form-field">Property name
                    <input name="property_name" required maxlength="150" placeholder="e.g. Balay San Jose Apartments or Villa Maria Boarding House" value="<?= e($editing['property_name'] ?? '') ?>">
                </label>
                <label class="form-field">Property type
                    <select name="property_type" required>
                        <?php foreach (PROPERTY_TYPES as $typeKey => $typeLabel): ?>
                            <option value="<?= e($typeKey) ?>"<?= ($editing['property_type'] ?? 'boarding_house') === $typeKey ? ' selected' : '' ?>><?= e($typeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">Municipality (Antique)
                    <select data-municipality>
                        <option value="">Choose a municipality</option>
                        <?php foreach ($municipalities as $place): ?>
                            <option value="<?= e($place['municipality_id']) ?>" <?= $municipalityId == $place['municipality_id'] ? 'selected' : '' ?>><?= e($place['municipality_name']) ?>, <?= e($place['province_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">Barangay
                    <select name="barangay_id" data-barangay required>
                        <option value="">Choose a barangay</option>
                        <?php foreach ($barangays as $place): ?>
                            <option value="<?= e($place['barangay_id']) ?>" data-municipality="<?= e($place['municipality_id']) ?>" <?= ($editing['barangay_id'] ?? null) == $place['barangay_id'] ? 'selected' : '' ?>><?= e($place['barangay_name']) ?> · <?= e($place['municipality_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>

        <!-- 2. Photos (Multi-Upload!) -->
        <div class="editor-section">
            <div class="editor-section-head">
                <h3>2. Property Photos</h3>
                <span class="badge">Optional · Multiple pictures supported</span>
            </div>
            <div class="upload-dropzone" data-multi-dropzone>
                <input id="property-photos-input" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-multi-photo-input>
                <label for="property-photos-input" class="dropzone-label">
                    <?= icon('upload') ?>
                    <strong><?= $editing ? 'Upload additional photos' : 'Select or drop property photos' ?></strong>
                    <span>Choose up to 12 JPG, PNG, or WebP photos (max 5 MB each). First photo becomes cover if none exists.</span>
                </label>
                <div class="photo-preview-grid" data-multi-photo-preview hidden></div>
            </div>
        </div>

        <?php if (!$editing): ?>
        <!-- 3. Quick First Room (Optional on creation!) -->
        <details class="editor-section editor-disclosure" open>
            <summary>
                <span><strong>3. Quick First Room / Unit &amp; Monthly Rate</strong> <span class="badge">Optional</span></span>
                <small class="muted">Add a room now so students see your monthly price right away (you can add more rooms later).</small>
            </summary>
            <div class="form-grid">
                <label class="form-field">Room / Unit label <input name="initial_room_number" maxlength="30" placeholder="e.g. Room 101, Unit A, Bedspace 1"></label>
                <label class="form-field">Room type
                    <select name="initial_room_type">
                        <option value="shared">Shared Room</option>
                        <option value="solo">Solo Room</option>
                        <option value="studio">Studio / Apartment Unit</option>
                    </select>
                </label>
                <label class="form-field">Bed / tenant capacity <input type="number" name="initial_max_capacity" min="1" max="255" placeholder="e.g. 2"></label>
                <label class="form-field">Monthly rate per bed / unit (₱) <input type="number" name="initial_monthly_rate" min="0.01" step="0.01" max="99999999.99" placeholder="e.g. 2500"></label>
            </div>
        </details>
        <?php endif; ?>

        <!-- 4. Location, Campus & Landmarks -->
        <details class="editor-section editor-disclosure"<?= $editing ? ' open' : '' ?>>
            <summary>
                <span><strong><?= $editing ? '3' : '4' ?>. Address, Nearest School &amp; Landmarks</strong> <span class="badge">Optional</span></span>
                <small class="muted">Share only the location info you want visible.</small>
            </summary>
            <div class="form-grid">
                <label class="form-field">Street address / Purok / Sitio <input name="street_address" maxlength="255" placeholder="e.g. Purok 3, San Jose St." value="<?= e($editing['street_address'] ?? '') ?>"></label>
                <label class="form-field">Nearest school or campus
                    <input name="nearest_school" maxlength="150" list="antique-schools" placeholder="e.g. St. Anthony's College or UA Sibalom" value="<?= e($editing['nearest_school'] ?? '') ?>">
                    <datalist id="antique-schools">
                        <?php foreach (ANTIQUE_SCHOOL_PRESETS as $school): ?><option value="<?= e($school) ?>"><?php endforeach; ?>
                    </datalist>
                </label>
                <label class="form-field">Distance to campus (meters) <input type="number" name="distance_to_campus_m" min="0" max="1000000" placeholder="e.g. 400 (leave blank if unsure)" value="<?= e($editing['distance_to_campus_m'] ?? '') ?>"></label>
                <label class="form-field">Nearby landmarks <input name="nearest_landmark" maxlength="255" placeholder="e.g. Near Robinsons Antique, beside tricycle terminal" value="<?= e($editing['nearest_landmark'] ?? '') ?>"></label>
                <label class="form-field">Map Latitude (optional) <input name="latitude" type="number" step="0.0000001" min="-90" max="90" placeholder="e.g. 10.7450" value="<?= e($editing['latitude'] ?? '') ?>"></label>
                <label class="form-field">Map Longitude (optional) <input name="longitude" type="number" step="0.0000001" min="-180" max="180" placeholder="e.g. 121.9410" value="<?= e($editing['longitude'] ?? '') ?>"></label>
            </div>
        </details>

        <!-- 5. Utilities, Terms & Living Policies -->
        <details class="editor-section editor-disclosure" open>
            <summary>
                <span><strong><?= $editing ? '4' : '5' ?>. Utilities, Terms &amp; Living Necessities</strong> <span class="badge">Optional · Only filled items display</span></span>
                <small class="muted">Water, electricity, Wi-Fi, deposit terms, curfew, cooking, visitors &amp; pets.</small>
            </summary>
            <div class="form-grid">
                <label class="form-field">Water billing
                    <select name="water_billing">
                        <option value="">Leave unspecified</option>
                        <?php foreach (WATER_BILLING_LABELS as $k => $v): ?>
                            <option value="<?= e($k) ?>"<?= ($editing['water_billing'] ?? '') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">Water details / rate <input name="water_notes" maxlength="150" placeholder="e.g. ₱150/head, 24/7 overhead tank" value="<?= e($editing['water_notes'] ?? '') ?>"></label>

                <label class="form-field">Electricity billing
                    <select name="electricity_billing">
                        <option value="">Leave unspecified</option>
                        <?php foreach (ELECTRICITY_BILLING_LABELS as $k => $v): ?>
                            <option value="<?= e($k) ?>"<?= ($editing['electricity_billing'] ?? '') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">Electricity details / rate <input name="electricity_notes" maxlength="150" placeholder="e.g. ANTECO sub-meter ₱16/kWh" value="<?= e($editing['electricity_notes'] ?? '') ?>"></label>

                <label class="form-field">Wi-Fi / Internet
                    <select name="wifi_policy">
                        <option value="">Leave unspecified</option>
                        <?php foreach (WIFI_POLICY_LABELS as $k => $v): ?>
                            <option value="<?= e($k) ?>"<?= ($editing['wifi_policy'] ?? '') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">Wi-Fi details <input name="wifi_notes" maxlength="150" placeholder="e.g. PLDT Fiber 200 Mbps, ₱150/mo optional" value="<?= e($editing['wifi_notes'] ?? '') ?>"></label>

                <label class="form-field">Furnishing status
                    <select name="furnishing_status">
                        <option value="">Leave unspecified</option>
                        <?php foreach (FURNISHING_LABELS as $k => $v): ?>
                            <option value="<?= e($k) ?>"<?= ($editing['furnishing_status'] ?? '') === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="form-field">Gender policy
                    <select name="gender_policy">
                        <option value="">Leave unspecified</option>
                        <?php foreach (['all' => 'All genders', 'female_only' => 'Female only', 'male_only' => 'Male only'] as $policy => $label): ?>
                            <option value="<?= e($policy) ?>"<?= ($editing['gender_policy'] ?? '') === $policy ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="form-field">Advance &amp; deposit terms <input name="deposit_terms" maxlength="200" placeholder="e.g. 1 month advance, 1 month deposit (or No deposit)" value="<?= e($editing['deposit_terms'] ?? '') ?>"></label>
                <label class="form-field">Minimum stay <input name="minimum_stay" maxlength="100" placeholder="e.g. Month-to-month, 1 semester (5 months)" value="<?= e($editing['minimum_stay'] ?? '') ?>"></label>

                <label class="form-field">Curfew time <input type="time" name="curfew_time" value="<?= e(substr($editing['curfew_time'] ?? '', 0, 5)) ?>"></label>
                <div class="form-field checkbox-align">
                    <span>24/7 Access</span>
                    <label class="check-label"><input type="checkbox" name="no_curfew" value="1"<?= !empty($editing['no_curfew']) ? ' checked' : '' ?>> No curfew (24/7 tenant gate access)</label>
                </div>

                <label class="form-field">Cooking policy <input name="cooking_policy" maxlength="200" placeholder="e.g. Shared kitchen with stove, rice cooker allowed" value="<?= e($editing['cooking_policy'] ?? '') ?>"></label>
                <label class="form-field">Visitor policy <input name="visitor_policy" maxlength="200" placeholder="e.g. Daytime visitors until 8 PM, no overnight" value="<?= e($editing['visitor_policy'] ?? '') ?>"></label>
                <label class="form-field">Pet policy <input name="pet_policy" maxlength="150" placeholder="e.g. No pets, or Small indoor pets allowed" value="<?= e($editing['pet_policy'] ?? '') ?>"></label>
                <fieldset class="payment-methods-field">
                    <legend>Accepted payment methods</legend>
                    <div class="actions">
                        <?php foreach (PAYMENT_METHOD_OPTIONS as $method): ?>
                            <label class="check-label"><input type="checkbox" name="payment_methods[]" value="<?= e($method) ?>"<?= in_array($method, $selectedPayments, true) ? ' checked' : '' ?>> <?= e($method) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>
        </details>

        <!-- 6. Description, Amenities, Rules & Custom Necessities -->
        <details class="editor-section editor-disclosure" open>
            <summary>
                <span><strong><?= $editing ? '5' : '6' ?>. Description, Amenities, House Rules &amp; Custom Info</strong> <span class="badge">Optional</span></span>
                <small class="muted">Check available facilities and add any custom necessities unique to your house.</small>
            </summary>
            <div class="form-grid">
                <label class="form-field span-2">Description (optional)
                    <textarea name="description" maxlength="5000" rows="3" placeholder="Tell students what makes your boarding house or apartment comfortable..."><?= e($editing['description'] ?? '') ?></textarea>
                </label>
                <fieldset class="span-2 amenity-picker">
                    <legend>Available Amenities &amp; Necessities</legend>
                    <div class="amenity-pill-grid">
                        <?php foreach ($amenities as $amenity): ?>
                            <label class="amenity-pill-option">
                                <input name="amenities[]" type="checkbox" value="<?= e($amenity['amenity_id']) ?>" <?= in_array($amenity['amenity_id'], $selected) ? 'checked' : '' ?>>
                                <span><?= e($amenity['amenity_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <label class="form-field span-2">House rules (optional)
                    <textarea name="house_rules" maxlength="5000" rows="3" placeholder="Quiet hours, shared cleaning rotation, appliance rules..."><?= e($editing['house_rules'] ?? '') ?></textarea>
                </label>

                <div class="span-2 custom-details-editor" data-custom-details>
                    <div class="section-head">
                        <div>
                            <strong>Custom Necessities / Extra Details (Optional)</strong>
                            <p class="muted">Add any extra info students ask about (e.g. Flood Status, Tricycle Fare, Mattress Included, Appliance Fee).</p>
                        </div>
                        <button type="button" class="button small secondary" data-add-custom-row><?= icon('plus') ?> Add info row</button>
                    </div>
                    <div class="custom-rows" data-custom-rows>
                        <?php if (!$customDetails): ?>
                            <div class="custom-detail-row" data-custom-row>
                                <input type="text" name="custom_label[]" maxlength="80" placeholder="Label (e.g. Flood Status)">
                                <input type="text" name="custom_value[]" maxlength="240" placeholder="Detail (e.g. Elevated, flood-free lot)">
                                <button type="button" class="button small secondary icon-only" data-remove-custom-row aria-label="Remove row"><?= icon('trash') ?></button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($customDetails as $detail): ?>
                                <div class="custom-detail-row" data-custom-row>
                                    <input type="text" name="custom_label[]" maxlength="80" placeholder="Label" value="<?= e($detail['label']) ?>">
                                    <input type="text" name="custom_value[]" maxlength="240" placeholder="Detail" value="<?= e($detail['value']) ?>">
                                    <button type="button" class="button small secondary icon-only" data-remove-custom-row aria-label="Remove row"><?= icon('trash') ?></button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </details>

        <!-- 7. Contact & Viewing Details -->
        <details class="editor-section editor-disclosure"<?= $editing && (!empty($editing['contact_phone']) || !empty($editing['contact_person']) || !empty($editing['contact_social'])) ? ' open' : '' ?>>
            <summary>
                <span><strong><?= $editing ? '6' : '7' ?>. Public Contact &amp; Viewing Schedule</strong> <span class="badge">Optional</span></span>
                <small class="muted">Only the contact info you enter here will be displayed on your public listing.</small>
            </summary>
            <div class="form-grid">
                <label class="form-field">Contact person / Caretaker name <input name="contact_person" maxlength="120" placeholder="e.g. Mila Santos (Owner) or Ate Rose (Caretaker)" value="<?= e($editing['contact_person'] ?? '') ?>"></label>
                <label class="form-field">Public contact number <input type="tel" name="contact_phone" maxlength="30" placeholder="e.g. 0917 123 4567" value="<?= e($editing['contact_phone'] ?? '') ?>"></label>
                <label class="form-field">Facebook / Messenger page or link <input name="contact_social" maxlength="200" placeholder="e.g. fb.com/balaysanjose or Messenger name" value="<?= e($editing['contact_social'] ?? '') ?>"></label>
                <label class="form-field">Viewing / Ocular hours <input name="viewing_hours" maxlength="150" placeholder="e.g. Mon–Sat, 8:00 AM – 6:00 PM" value="<?= e($editing['viewing_hours'] ?? '') ?>"></label>
            </div>
        </details>

        <div class="editor-submit-bar">
            <p class="muted"><?= $editing && $editing['approval_status'] === 'approved' ? 'Updates to your published listing save immediately.' : 'New listings are sent to an administrator for quick approval.' ?></p>
            <div class="actions">
                <a class="button secondary" href="<?= e(url('landlord/properties.php')) ?>">Cancel</a>
                <button type="submit" class="button"><?= $editing ? 'Save Property Changes' : 'Save & Submit Property' ?></button>
            </div>
        </div>
    </form>
</section>

<?php if ($editing): ?>
<section class="panel" id="property-gallery">
    <div class="panel-head">
        <div>
            <h2>Property Gallery (<?= count($photos) ?> <?= count($photos) === 1 ? 'photo' : 'photos' ?>)</h2>
            <p class="muted">Set your cover photo, add optional room/area captions, or upload multiple new pictures at once.</p>
        </div>
    </div>
    <?php if ($photos): ?>
        <div class="gallery-manager-grid">
            <?php foreach ($photos as $photo): ?>
                <article class="gallery-manager-card">
                    <div class="gallery-manager-thumb">
                        <img src="<?= e(media_url($photo['image_path'])) ?>" loading="lazy" alt="Photo of <?= e($editing['property_name']) ?>">
                        <?php if ($photo['is_primary']): ?><span class="badge approved cover-pill">Cover Photo</span><?php endif; ?>
                    </div>
                    <form method="post" action="<?= e(url('actions/property_save.php')) ?>" class="caption-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="property_id" value="<?= e($editId) ?>">
                        <input type="hidden" name="image_id" value="<?= e($photo['image_id']) ?>">
                        <input type="hidden" name="operation" value="image_caption">
                        <input type="text" name="caption" maxlength="120" value="<?= e($photo['caption'] ?? '') ?>" placeholder="Optional label (e.g. Kitchen, Exterior)">
                        <button type="submit" class="button small secondary">Save label</button>
                    </form>
                    <form method="post" action="<?= e(url('actions/property_save.php')) ?>" class="actions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="property_id" value="<?= e($editId) ?>">
                        <input type="hidden" name="image_id" value="<?= e($photo['image_id']) ?>">
                        <?php if (!$photo['is_primary']): ?>
                            <button class="button small secondary" name="operation" value="image_primary">Set as Cover</button>
                        <?php endif; ?>
                        <button class="button small danger" name="operation" value="image_delete" data-confirm="Remove this photo from the listing gallery?" data-confirm-label="Remove photo">Remove</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="empty-state">No photos uploaded yet. Upload pictures below so students can inspect your property.</p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('actions/property_save.php')) ?>" class="gallery-batch-form">
        <?= csrf_field() ?>
        <input type="hidden" name="property_id" value="<?= e($editId) ?>">
        <input type="hidden" name="operation" value="image_add">
        <div class="upload-dropzone" data-multi-dropzone>
            <input id="gallery-add-input" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required data-multi-photo-input>
            <label for="gallery-add-input" class="dropzone-label">
                <?= icon('upload') ?>
                <strong>Upload more photos</strong>
                <span>Select one or multiple JPG, PNG, or WebP pictures (up to 12 at once, 5 MB each).</span>
            </label>
            <div class="photo-preview-grid" data-multi-photo-preview hidden></div>
        </div>
        <div class="actions"><button type="submit" class="button">Upload Selected Photos</button></div>
    </form>
</section>

<section class="panel">
    <h2>Remove Property</h2>
    <p class="muted">Properties with reservation, lease, or report history are retained for records.</p>
    <form method="post" action="<?= e(url('actions/property_save.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="property_id" value="<?= e($editId) ?>">
        <button class="button danger small" name="operation" value="delete" data-confirm="Remove this property and all its gallery photos?">Remove Property</button>
    </form>
</section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
