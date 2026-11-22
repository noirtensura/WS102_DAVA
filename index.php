<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/finder.php';

$filters = finder_filters($_GET);
$listings = find_properties($filters);
$favorites = favorites_for($user);
if (($_GET['fragment'] ?? '') === 'listings') {
    header('Content-Type: text/html; charset=UTF-8');
    require __DIR__ . '/includes/listing_results.php';
    exit;
}
$amenities = rows('SELECT amenity_id, amenity_name FROM amenities ORDER BY amenity_name');
$quickNames = ['Free Wi-Fi', 'Shared Kitchen / Cooking Area', 'Own Comfort Room (CR)'];
$amenityIds = array_column($amenities, 'amenity_id', 'amenity_name');
$areas = rows(
    'SELECT b.barangay_id, b.barangay_name FROM barangays b JOIN municipalities m USING (municipality_id)
     WHERE ' . PUBLIC_LOCATION . ' ORDER BY b.barangay_name',
    [DAYON_MUNICIPALITY, DAYON_PROVINCE]
);
$pageTitle = 'Find student boarding in ' . DAYON_LOCATION;
$pageScripts = ['assets/js/finder.js'];
require __DIR__ . '/includes/header.php';
?>
<main id="main">
    <div class="container finder-main">
        <form id="finder-form" method="get" action="<?= e(url('index.php#listings')) ?>" aria-label="Find student boarding houses">
            <section class="finder-hero" aria-labelledby="finder-title">
                <div class="hero-copy">
                    <h1 id="finder-title">Find Verified Student Boarding Houses Near Campus</h1>
                    <p class="hero-description">See live bed vacancies, book visits, and compare monthly rent in <?= e(DAYON_LOCATION) ?>.</p>
                    <div class="finder-fields">
                        <label><span class="visually-hidden">Campus area or barangay</span>
                            <select name="barangay">
                                <option value="">Campus Area</option>
                                <?php foreach ($areas as $area): ?>
                                    <option value="<?= (int) $area['barangay_id'] ?>"<?= $filters['barangay'] === (int) $area['barangay_id'] ? ' selected' : '' ?>><?= e($area['barangay_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><span class="visually-hidden">Maximum monthly budget in pesos</span>
                            <select name="budget">
                                <option value="">Max Budget (PHP)</option>
                                <?php foreach (array_unique([1500, 2000, 2500, 3500, 5000, 7000, $filters['budget']]) as $budget): if (!$budget) continue; ?>
                                    <option value="<?= $budget ?>"<?= $filters['budget'] === $budget ? ' selected' : '' ?>>Up to <?= e(display_price($budget)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><span class="visually-hidden">Room type</span>
                            <select name="room_type">
                                <option value="">Room Type</option>
                                <?php foreach (ROOM_TYPES as $key => $label): ?>
                                    <option value="<?= e($key) ?>"<?= $filters['room_type'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><span class="visually-hidden">Gender policy</span>
                            <select name="gender">
                                <option value="">Gender Policy</option>
                                <?php foreach (GENDER_POLICIES as $key => $label): ?>
                                    <option value="<?= e($key) ?>"<?= $filters['gender'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="button finder-submit" type="submit">Search</button>
                    </div>
                </div>
                <div class="hero-picture">
                    <img src="<?= e(url('assets/images/san-jose-study.webp')) ?>" width="1200" height="800" alt="Student room with a single bed and a study desk by the window" fetchpriority="high">
                </div>
            </section>
            <div class="quick-controls" aria-label="Quick filters">
                <?php foreach ($quickNames as $name): if (!isset($amenityIds[$name])) continue; $amenityId = (int) $amenityIds[$name]; ?>
                    <label class="filter-chip"><input class="visually-hidden" type="checkbox" name="amenities[]" value="<?= $amenityId ?>"<?= in_array($amenityId, $filters['amenities'], true) ? ' checked' : '' ?> data-quick-filter><?= e(SHORT_AMENITIES[$name]) ?></label>
                <?php endforeach; ?>
                <label class="filter-chip" title="Within 800 m of <?= e(DAYON_CAMPUS) ?>"><input class="visually-hidden" type="checkbox" name="walkable" value="1"<?= $filters['walkable'] ? ' checked' : '' ?> data-quick-filter>Walking Distance</label>
                <label class="filter-chip"><input class="visually-hidden" type="checkbox" name="available" value="1"<?= $filters['available'] ? ' checked' : '' ?> data-quick-filter>Available Now</label>
                <details class="filter-menu">
                    <summary><?= icon('filter') ?> More Filters</summary>
                    <div class="advanced-content">
                        <label>Distance to <?= e(DAYON_CAMPUS) ?>
                            <select name="distance">
                                <option value="">Any distance</option>
                                <?php foreach (array_unique([500, 1000, 2000, 5000, $filters['distance']]) as $distance): if (!$distance) continue; ?>
                                    <option value="<?= $distance ?>"<?= $filters['distance'] === $distance ? ' selected' : '' ?>>Within <?= e(campus_distance($distance)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <fieldset>
                            <legend>More amenities</legend>
                            <div class="amenities-checks">
                                <?php foreach ($amenities as $amenity): if (in_array($amenity['amenity_name'], $quickNames, true)) continue; ?>
                                    <label class="check-label"><input type="checkbox" name="amenities[]" value="<?= (int) $amenity['amenity_id'] ?>"<?= in_array((int) $amenity['amenity_id'], $filters['amenities'], true) ? ' checked' : '' ?>> <?= e($amenity['amenity_name']) ?></label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="actions">
                            <button type="submit" class="button small">Apply Filters</button>
                            <a class="button small secondary" href="<?= e(url('index.php#listings')) ?>">Clear Filters</a>
                        </div>
                    </div>
                </details>
            </div>
        </form>
        <section id="listings" class="listing-section" data-finder-results aria-label="Search results" aria-live="polite">
            <?php require __DIR__ . '/includes/listing_results.php'; ?>
        </section>
    </div>
    <section class="how-it-works" id="how-it-works" aria-label="How Dayon works">
        <div class="container how-grid">
            <article><h2>1. Find &amp; Filter</h2><p>Compare verified rooms by budget, amenities, and vacancy.</p></article>
            <article><h2>2. Book a Visit</h2><p>Pick a room and send a visit or bed reservation request.</p></article>
            <article><h2>3. Track Your Rent</h2><p>View your monthly bill, submit proof, and print receipts.</p></article>
        </div>
    </section>
    <section class="container landlord-invite" id="for-landlords">
        <div><h2>Manage a boarding house in <?= e(DAYON_MUNICIPALITY) ?>?</h2><p>List rooms, review requests, and manage rent in one place.</p></div>
        <?php if (!$user): ?>
            <a class="button" href="<?= e(url('register.php?role=landlord')) ?>">List Your Property <?= icon('arrow') ?></a>
        <?php elseif ($user['role_name'] === 'landlord'): ?>
            <a class="button" href="<?= e(url('landlord/properties.php')) ?>">Manage Properties <?= icon('arrow') ?></a>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
