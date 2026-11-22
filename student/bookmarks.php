<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$student = require_role('student');
$properties = rows(
    "SELECT p.property_id, p.property_name, p.distance_to_campus_m, p.gender_policy, p.curfew_time, p.house_rules,
            b.barangay_name, m.municipality_name,
            (p.approval_status = 'approved' AND lp.verification_status = 'verified' AND u.account_status = 'active'
             AND m.municipality_name = ? AND m.province_name = ?) AS visible,
            (SELECT MIN(v.monthly_rate_per_bed) FROM vw_room_vacancy v WHERE v.property_id = p.property_id AND v.is_active = 1) AS min_rate,
            (SELECT COALESCE(SUM(v.available_beds), 0) FROM vw_room_vacancy v WHERE v.property_id = p.property_id AND v.is_active = 1) AS vacancies,
            (SELECT GROUP_CONCAT(a.amenity_name ORDER BY a.amenity_name SEPARATOR ', ') FROM property_amenities pa
             JOIN amenities a ON a.amenity_id = pa.amenity_id WHERE pa.property_id = p.property_id) AS amenities
     FROM bookmarks s JOIN properties p ON p.property_id = s.property_id JOIN barangays b ON b.barangay_id = p.barangay_id
     JOIN municipalities m ON m.municipality_id = b.municipality_id JOIN landlord_profiles lp ON lp.landlord_id = p.landlord_id
     JOIN users u ON u.user_id = p.landlord_id
     WHERE s.student_id = ? ORDER BY s.created_at DESC, p.property_id DESC",
    [DAYON_MUNICIPALITY, DAYON_PROVINCE, $student['user_id']]
);
$choices = array_filter((array) ($_GET['compare'] ?? []), static fn ($id) => is_string($id) && ctype_digit($id));
$compared = array_slice(array_values(array_filter($properties, static fn ($property) => $property['visible'] && in_array((string) $property['property_id'], $choices, true))), 0, 4);
$gender = ['all' => 'All students', 'male_only' => 'Male only', 'female_only' => 'Female only'];

$pageTitle = 'My shortlist';
$pageDescription = 'Keep track of saved boarding houses and compare up to four listings side by side.';
require __DIR__ . '/../includes/portal_header.php';
?>

<?php if (!$properties): ?>
    <section class="panel empty-state">
        <h2>No saved boarding houses</h2>
        <p>Save a listing to compare its rates, amenities, and house rules here.</p>
        <a class="button" href="<?= e(url('index.php')) ?>">Find boarding houses</a>
    </section>
<?php else: ?>
    <form id="compare-form" method="get" action="<?= e(url('student/bookmarks.php')) ?>"></form>
    <div class="grid-3">
        <?php foreach ($properties as $property): ?>
            <?php $propertyId = (string) $property['property_id']; ?>
            <article class="panel">
                <?php if ($property['visible']): ?>
                    <span class="badge"><?= (int) $property['vacancies'] ?> open <?= (int) $property['vacancies'] === 1 ? 'bed' : 'beds' ?></span>
                    <h2><?= e($property['property_name']) ?></h2>
                    <p class="muted"><?= e($property['barangay_name']) ?>, <?= e($property['municipality_name']) ?></p>
                    <?php if ($property['min_rate'] !== null): ?><p>From <strong><?= money($property['min_rate']) ?></strong> per month</p><?php endif; ?>
                    <p><label><input form="compare-form" type="checkbox" name="compare[]" value="<?= e($propertyId) ?>" <?= in_array($propertyId, $choices, true) ? 'checked' : '' ?>> Compare <?= e($property['property_name']) ?></label></p>
                <?php else: ?>
                    <h2>Listing unavailable</h2>
                    <p class="muted">This boarding house is currently hidden from the finder.</p>
                <?php endif; ?>
                <div class="actions">
                    <?php if ($property['visible']): ?><a class="button small" href="<?= e(url('property.php?id=' . $propertyId)) ?>">View listing</a><?php endif; ?>
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
    <?php if (count(array_filter($properties, static fn ($property) => $property['visible'])) >= 2): ?>
        <div class="actions"><button form="compare-form" class="button" type="submit">Compare selected</button></div>
    <?php endif; ?>
    <?php if ($choices && count($compared) < 2): ?>
        <p class="panel" role="status">Select at least two available listings to compare.</p>
    <?php elseif ($compared): ?>
        <section class="panel" aria-labelledby="compare-heading">
            <h2 id="compare-heading">Compare boarding houses</h2>
            <?php if (count($choices) > 4): ?><p class="muted">Showing four selected listings.</p><?php endif; ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th scope="col">Details</th><?php foreach ($compared as $property): ?><th scope="col"><?= e($property['property_name']) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                        <tr><th scope="row">Monthly rates from</th><?php foreach ($compared as $property): ?><td><?= $property['min_rate'] === null ? 'Unavailable' : money($property['min_rate']) ?></td><?php endforeach; ?></tr>
                        <tr><th scope="row">Open beds</th><?php foreach ($compared as $property): ?><td><?= (int) $property['vacancies'] ?></td><?php endforeach; ?></tr>
                        <tr><th scope="row">Campus distance</th><?php foreach ($compared as $property): ?><td><?= number_format((int) $property['distance_to_campus_m']) ?> m</td><?php endforeach; ?></tr>
                        <tr><th scope="row">Gender policy</th><?php foreach ($compared as $property): ?><td><?= e($gender[$property['gender_policy']]) ?></td><?php endforeach; ?></tr>
                        <tr><th scope="row">Amenities</th><?php foreach ($compared as $property): ?><td><?= e($property['amenities'] ?: 'Unspecified') ?></td><?php endforeach; ?></tr>
                        <tr><th scope="row">Curfew</th><?php foreach ($compared as $property): ?><td><?= $property['curfew_time'] ? e(date('g:i A', strtotime($property['curfew_time']))) : 'Unspecified' ?></td><?php endforeach; ?></tr>
                        <tr><th scope="row">House rules</th><?php foreach ($compared as $property): ?><td><?= nl2br(e($property['house_rules'] ?: 'Unspecified')) ?></td><?php endforeach; ?></tr>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
