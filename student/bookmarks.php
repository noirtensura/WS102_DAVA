<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$student = require_role('student');
$properties = rows(
    "SELECT p.*, s.note AS bookmark_note, s.created_at AS saved_at,
            b.barangay_name, m.municipality_name,
            (p.approval_status = 'approved' AND lp.verification_status = 'verified' AND u.account_status = 'active'
             AND m.province_name = ?) AS visible,
            (SELECT MIN(v.monthly_rate_per_bed) FROM vw_room_vacancy v WHERE v.property_id = p.property_id AND v.is_active = 1) AS min_rate,
            (SELECT COALESCE(SUM(v.available_beds), 0) FROM vw_room_vacancy v WHERE v.property_id = p.property_id AND v.is_active = 1) AS vacancies,
            (SELECT image_path FROM property_images WHERE property_id = p.property_id ORDER BY is_primary DESC, image_id LIMIT 1) AS cover,
            (SELECT COUNT(*) FROM landlord_permits pm
             WHERE pm.landlord_id = p.landlord_id AND (pm.property_id = p.property_id OR pm.property_id IS NULL)
               AND pm.verification_status = 'verified' AND pm.permit_type <> 'government_id') AS verified_permits,
            (SELECT GROUP_CONCAT(a.amenity_name ORDER BY a.amenity_name SEPARATOR ', ') FROM property_amenities pa
             JOIN amenities a ON a.amenity_id = pa.amenity_id WHERE pa.property_id = p.property_id) AS amenities
     FROM bookmarks s JOIN properties p ON p.property_id = s.property_id JOIN barangays b ON b.barangay_id = p.barangay_id
     JOIN municipalities m ON m.municipality_id = b.municipality_id JOIN landlord_profiles lp ON lp.landlord_id = p.landlord_id
     JOIN users u ON u.user_id = p.landlord_id
     WHERE s.student_id = ? ORDER BY s.created_at DESC, p.property_id DESC",
    [DAYON_PROVINCE, $student['user_id']]
);
$choices = array_unique(array_filter((array) ($_GET['compare'] ?? []), static fn ($id) => is_string($id) && ctype_digit($id)));
$visible = array_filter($properties, static fn ($property) => $property['visible']);
$canCompare = count($visible) >= 2;
$compared = array_slice(array_values(array_filter($visible, static fn ($property) => in_array((string) $property['property_id'], $choices, true))), 0, 4);
$selectedIds = array_map('strval', array_column($compared, 'property_id'));
$gender = ['all' => 'All genders', 'male_only' => 'Male only', 'female_only' => 'Female only'];

// Build dynamic comparison rows — only display rows where at least 1 compared listing has data!
$compareRows = [];
if ($compared) {
    $candidates = [
        ['Property type', static fn (array $p): string => property_type_label($p['property_type'] ?? 'boarding_house')],
        ['Monthly rates from', static fn (array $p): string => $p['min_rate'] === null ? '' : display_price($p['min_rate']) . ' / mo'],
        ['Open beds', static fn (array $p): string => (string) (int) $p['vacancies']],
        ['Location', static fn (array $p): string => $p['barangay_name'] . ', ' . $p['municipality_name']],
        ['Campus distance', static fn (array $p): string => $p['distance_to_campus_m'] !== null ? number_format((int) $p['distance_to_campus_m']) . ' m' . (!empty($p['nearest_school']) ? ' (' . $p['nearest_school'] . ')' : '') : ''],
        ['Gender policy', static fn (array $p): string => !empty($p['gender_policy']) ? ($gender[$p['gender_policy']] ?? '') : ''],
        ['Water', static fn (array $p): string => !empty($p['water_billing']) ? (WATER_BILLING_LABELS[$p['water_billing']] . (!empty($p['water_notes']) ? ' · ' . $p['water_notes'] : '')) : (string) ($p['water_notes'] ?? '')],
        ['Electricity', static fn (array $p): string => !empty($p['electricity_billing']) ? (ELECTRICITY_BILLING_LABELS[$p['electricity_billing']] . (!empty($p['electricity_notes']) ? ' · ' . $p['electricity_notes'] : '')) : (string) ($p['electricity_notes'] ?? '')],
        ['Wi-Fi', static fn (array $p): string => !empty($p['wifi_policy']) ? (WIFI_POLICY_LABELS[$p['wifi_policy']] . (!empty($p['wifi_notes']) ? ' · ' . $p['wifi_notes'] : '')) : (string) ($p['wifi_notes'] ?? '')],
        ['Furnishing', static fn (array $p): string => !empty($p['furnishing_status']) ? (FURNISHING_LABELS[$p['furnishing_status']] ?? '') : ''],
        ['Advance & Deposit', static fn (array $p): string => (string) ($p['deposit_terms'] ?? '')],
        ['Curfew', static fn (array $p): string => !empty($p['no_curfew']) ? 'No curfew (24/7)' : (!empty($p['curfew_time']) ? date('g:i A', strtotime($p['curfew_time'])) : '')],
        ['Cooking policy', static fn (array $p): string => (string) ($p['cooking_policy'] ?? '')],
        ['Visitor policy', static fn (array $p): string => (string) ($p['visitor_policy'] ?? '')],
        ['Verified Antique permits', static fn (array $p): string => (int) $p['verified_permits'] > 0 ? (int) $p['verified_permits'] . ' on file' : ''],
        ['Amenities', static fn (array $p): string => (string) ($p['amenities'] ?? '')],
        ['House rules', static fn (array $p): string => (string) ($p['house_rules'] ?? '')],
    ];
    foreach ($candidates as [$rowLabel, $getter]) {
        $vals = array_map($getter, $compared);
        if (array_filter($vals, static fn ($v) => trim($v) !== '')) {
            $compareRows[] = [$rowLabel, $vals];
        }
    }
}

$pageTitle = 'Bookmarked Houses & Apartments';
$pageDescription = 'Keep track of your saved boarding houses and apartments, add personal notes, and compare up to four side by side.';
$pageScripts = $canCompare ? ['student/compare.js'] : [];
$pageActions = static function (): void {
    ?>
    <a class="button secondary" href="<?= e(url('index.php#listings')) ?>"><?= icon('search') ?> Browse More Housing</a>
    <?php
};
require __DIR__ . '/../includes/portal_header.php';
?>

<?php if (!$properties): ?>
    <section class="panel empty-state">
        <?= icon('bookmark') ?>
        <h2>No bookmarked houses yet</h2>
        <p>Tap the bookmark button on any boarding house or apartment to save it here and compare rates, utilities, and amenities.</p>
        <a class="button" href="<?= e(url('index.php#listings')) ?>">Explore Boarding Houses &amp; Apartments</a>
    </section>
<?php else: ?>
    <?php if ($canCompare): ?>
        <form class="panel compare-toolbar" id="compare-form" method="get" action="<?= e(url('student/bookmarks.php#compare-results')) ?>">
            <div>
                <h2>Side-by-Side Comparison</h2>
                <p id="compare-selection" class="muted" role="status">Select two to four saved listings to compare rates, utilities, and necessities.</p>
            </div>
            <button class="button" type="submit" aria-describedby="compare-selection">Compare Selected <?= icon('arrow') ?></button>
        </form>
    <?php endif; ?>

    <div class="shortlist-grid">
        <?php foreach ($properties as $property): ?>
            <?php $propertyId = (string) $property['property_id']; ?>
            <article class="panel shortlist-item">
                <?php if ($property['visible']): ?>
                    <div class="shortlist-media">
                        <?php if ($property['cover']): ?>
                            <img src="<?= e(media_url($property['cover'])) ?>" class="shortlist-cover" alt="<?= e($property['property_name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="image-placeholder shortlist-cover"><?= icon('building') ?></div>
                        <?php endif; ?>
                        <span class="type-chip shortlist-type"><?= e(property_type_label($property['property_type'] ?? 'boarding_house')) ?></span>
                    </div>
                    <div class="shortlist-badges">
                        <span class="badge <?= (int) $property['vacancies'] > 0 ? 'available' : 'full' ?>"><?= (int) $property['vacancies'] ?> open <?= (int) $property['vacancies'] === 1 ? 'bed' : 'beds' ?></span>
                        <?php if ((int) $property['verified_permits'] > 0): ?>
                            <span class="badge verified"><?= icon('shield') ?> <?= (int) $property['verified_permits'] ?> <?= (int) $property['verified_permits'] === 1 ? 'Permit' : 'Permits' ?></span>
                        <?php endif; ?>
                    </div>
                    <h2><a href="<?= e(url('property.php?id=' . $propertyId)) ?>"><?= e($property['property_name']) ?></a></h2>
                    <p class="muted"><?= e($property['barangay_name']) ?>, <?= e($property['municipality_name']) ?></p>
                    <?php if ($property['min_rate'] !== null): ?>
                        <p class="shortlist-price">From <strong><?= e(display_price($property['min_rate'])) ?></strong> / month</p>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('api/toggle_bookmark.php')) ?>" class="bookmark-note-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="operation" value="save_note">
                        <input type="hidden" name="property_id" value="<?= e($propertyId) ?>">
                        <input type="hidden" name="return_to" value="bookmarks">
                        <label class="visually-hidden" for="note-<?= e($propertyId) ?>">Personal note</label>
                        <input id="note-<?= e($propertyId) ?>" type="text" name="note" maxlength="255" value="<?= e($property['bookmark_note'] ?? '') ?>" placeholder="Add a personal reminder note...">
                        <button type="submit" class="button small secondary">Save note</button>
                    </form>

                    <?php if ($canCompare): ?>
                        <label class="check-label shortlist-select">
                            <input form="compare-form" type="checkbox" name="compare[]" value="<?= e($propertyId) ?>" aria-label="Select for comparison: <?= e($property['property_name']) ?>"<?= in_array($propertyId, $selectedIds, true) ? ' checked' : '' ?>>
                            Select to compare
                        </label>
                    <?php endif; ?>
                <?php else: ?>
                    <h2>Listing unavailable</h2>
                    <p class="muted">This property is currently not listed in the public finder.</p>
                <?php endif; ?>
                <div class="actions">
                    <?php if ($property['visible']): ?><a class="button small" href="<?= e(url('property.php?id=' . $propertyId)) ?>">View Listing</a><?php endif; ?>
                    <form method="post" action="<?= e(url('api/toggle_bookmark.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="property_id" value="<?= e($propertyId) ?>">
                        <input type="hidden" name="return_to" value="bookmarks">
                        <button class="button secondary small" type="submit">Remove</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($choices && count($compared) < 2): ?>
        <p class="panel compare-results" id="compare-results" role="status">Select at least two available listings to compare.</p>
    <?php elseif ($compared): ?>
        <section class="panel compare-results" id="compare-results" aria-labelledby="compare-heading">
            <div class="panel-head">
                <h2 id="compare-heading">Comparison Table</h2>
                <span class="muted">Only fields provided by landlords are shown.</span>
            </div>
            <?php if (count($choices) > 4): ?><p class="muted">Showing four selected listings.</p><?php endif; ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th scope="col">Details</th><?php foreach ($compared as $property): ?><th scope="col"><a href="<?= e(url('property.php?id=' . $property['property_id'])) ?>"><?= e($property['property_name']) ?></a></th><?php endforeach; ?></tr></thead>
                    <tbody>
                        <?php foreach ($compareRows as [$rowLabel, $vals]): ?>
                            <tr>
                                <th scope="row"><?= e($rowLabel) ?></th>
                                <?php foreach ($vals as $val): ?>
                                    <td><?= trim($val) === '' ? '<span class="muted">—</span>' : nl2br(e($val)) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
