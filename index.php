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

$stats = row(
    "SELECT COUNT(DISTINCT p.property_id) AS verified_listings,
            COALESCE(SUM(CASE WHEN v.is_active = 1 THEN v.available_beds ELSE 0 END), 0) AS open_beds,
            COUNT(DISTINCT b.barangay_id) AS active_barangays
     FROM properties p
     JOIN landlord_profiles lp USING (landlord_id)
     JOIN users owner ON owner.user_id = p.landlord_id
     JOIN barangays b USING (barangay_id)
     JOIN municipalities m USING (municipality_id)
     LEFT JOIN vw_room_vacancy v ON v.property_id = p.property_id
     WHERE " . PUBLIC_APPROVAL . " AND " . PUBLIC_LOCATION,
    [DAYON_PROVINCE]
);
$amenities = rows('SELECT amenity_id, amenity_name FROM amenities ORDER BY amenity_name');
$quickNames = ['Free Wi-Fi', 'Shared Kitchen / Cooking Area', 'Own Comfort Room (CR)'];
$amenityIds = array_column($amenities, 'amenity_id', 'amenity_name');
$municipalities = rows(
    'SELECT municipality_id, municipality_name FROM municipalities WHERE province_name = ? ORDER BY (municipality_name = ?) DESC, municipality_name',
    [DAYON_PROVINCE, DAYON_MUNICIPALITY]
);
$areas = rows(
    'SELECT b.barangay_id, b.barangay_name, b.municipality_id, m.municipality_name
     FROM barangays b JOIN municipalities m USING (municipality_id)
     WHERE ' . PUBLIC_LOCATION . ' ORDER BY (m.municipality_name = ?) DESC, m.municipality_name, b.barangay_name',
    [DAYON_PROVINCE, DAYON_MUNICIPALITY]
);
$pageTitle = 'Find boarding houses & apartments in ' . DAYON_PROVINCE;
$pageScripts = ['assets/js/finder.js'];
require __DIR__ . '/includes/header.php';
?>
<main id="main">
    <div class="container finder-main">
        <section class="finder-hero" aria-labelledby="finder-title">
            <div class="hero-copy">
                <p class="hero-location"><?= icon('pin') ?> <?= e(DAYON_PROVINCE_LABEL) ?> · Boarding Houses &amp; Apartments</p>
                <h1 id="finder-title">Find student housing you can trust across Antique</h1>
                <p class="hero-description">Browse boarding houses, apartments, dormitories, and bedspaces with real photos, flexible amenities, live bed vacancies, and easy bookmarking.</p>
                <div class="hero-stats" aria-label="Live housing statistics">
                    <div><strong><?= (int) ($stats['verified_listings'] ?? 0) ?></strong><span>Verified Listings</span></div>
                    <div><strong><?= (int) ($stats['open_beds'] ?? 0) ?></strong><span>Open Beds Now</span></div>
                    <div><strong><?= (int) ($stats['active_barangays'] ?? 0) ?></strong><span>Active Barangays</span></div>
                </div>
            </div>
            <div class="hero-picture">
                <img src="<?= e(url('assets/images/san-jose-study.webp')) ?>" width="1200" height="800" alt="Sunlit student room with a single bed and a study desk by the window" fetchpriority="high">
                <div class="hero-badge-float">
                    <?= icon('shield') ?>
                    <div><strong>Admin-reviewed listings</strong><span>Optional Antique permits clearly displayed</span></div>
                </div>
            </div>
        </section>

        <form id="finder-form" method="get" action="<?= e(url('index.php#listings')) ?>" aria-label="Search and filter boarding houses and apartments">
            <div class="type-pills" role="group" aria-label="Property type quick filter">
                <label class="type-pill">
                    <input class="visually-hidden" type="radio" name="property_type" value=""<?= $filters['property_type'] === '' ? ' checked' : '' ?> data-quick-filter>
                    <span><?= icon('grid') ?> All Housing</span>
                </label>
                <?php foreach (PROPERTY_TYPES as $typeKey => $typeLabel): ?>
                    <label class="type-pill">
                        <input class="visually-hidden" type="radio" name="property_type" value="<?= e($typeKey) ?>"<?= $filters['property_type'] === $typeKey ? ' checked' : '' ?> data-quick-filter>
                        <span><?= icon($typeKey === 'apartment' ? 'building' : ($typeKey === 'bedspace' ? 'bed' : 'home')) ?> <?= e($typeLabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="finder-fields">
                <label class="finder-search-field">Search place, school or name
                    <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="e.g. Barangay 8, SAC, studio, apartment..." maxlength="100">
                </label>
                <label>Municipality
                    <select name="municipality" data-finder-municipality>
                        <option value="">All Antique towns</option>
                        <?php foreach ($municipalities as $town): ?>
                            <option value="<?= (int) $town['municipality_id'] ?>"<?= $filters['municipality'] === (int) $town['municipality_id'] ? ' selected' : '' ?>><?= e($town['municipality_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Barangay
                    <select name="barangay" data-finder-barangay>
                        <option value="">All barangays</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?= (int) $area['barangay_id'] ?>" data-municipality="<?= (int) $area['municipality_id'] ?>"<?= $filters['barangay'] === (int) $area['barangay_id'] ? ' selected' : '' ?>><?= e($area['barangay_name']) ?> (<?= e($area['municipality_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Monthly budget
                    <select name="budget">
                        <option value="">Any budget</option>
                        <?php foreach (array_unique([1500, 2000, 2500, 3500, 5000, 7000, 10000, $filters['budget']]) as $budget): if (!$budget) continue; ?>
                            <option value="<?= $budget ?>"<?= $filters['budget'] === $budget ? ' selected' : '' ?>>Up to <?= e(display_price($budget)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button finder-submit" type="submit"><?= icon('search') ?> Search</button>
            </div>

            <div class="quick-controls" aria-label="Quick filters">
                <label class="filter-chip"><input class="visually-hidden" type="checkbox" name="available" value="1"<?= $filters['available'] ? ' checked' : '' ?> data-quick-filter><?= icon('check') ?> Available now</label>
                <?php foreach ($quickNames as $name): if (!isset($amenityIds[$name])) continue; $amenityId = (int) $amenityIds[$name]; ?>
                    <label class="filter-chip"><input class="visually-hidden" type="checkbox" name="amenities[]" value="<?= $amenityId ?>"<?= in_array($amenityId, $filters['amenities'], true) ? ' checked' : '' ?> data-quick-filter><?= icon('check') ?> <?= e(SHORT_AMENITIES[$name]) ?></label>
                <?php endforeach; ?>
                <label class="filter-chip"><input class="visually-hidden" type="checkbox" name="no_curfew" value="1"<?= $filters['no_curfew'] ? ' checked' : '' ?> data-quick-filter><?= icon('check') ?> No curfew</label>
                <label class="filter-chip" title="Listings with verified Antique local permits on file"><input class="visually-hidden" type="checkbox" name="with_permits" value="1"<?= $filters['with_permits'] ? ' checked' : '' ?> data-quick-filter><?= icon('check') ?> Permits on file</label>
                <label class="filter-chip" title="Within 800 m of campus"><input class="visually-hidden" type="checkbox" name="walkable" value="1"<?= $filters['walkable'] ? ' checked' : '' ?> data-quick-filter><?= icon('check') ?> Walking distance</label>
                <details class="filter-menu">
                    <summary><?= icon('filter') ?> More filters</summary>
                    <div class="advanced-content">
                        <div class="grid-2">
                            <label>Room type
                                <select name="room_type"<?= $filters['room_type'] ? ' data-chosen' : '' ?>>
                                    <option value="">Any room type</option>
                                    <?php foreach (ROOM_TYPES as $key => $label): ?>
                                        <option value="<?= e($key) ?>"<?= $filters['room_type'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Gender policy
                                <select name="gender"<?= $filters['gender'] ? ' data-chosen' : '' ?>>
                                    <option value="">Any gender policy</option>
                                    <?php foreach (GENDER_POLICIES as $key => $label): ?>
                                        <option value="<?= e($key) ?>"<?= $filters['gender'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <label>Distance to nearest campus
                            <select name="distance"<?= $filters['distance'] ? ' data-chosen' : '' ?>>
                                <option value="">Any distance</option>
                                <?php foreach (array_unique([500, 1000, 2000, 5000, $filters['distance']]) as $distance): if (!$distance) continue; ?>
                                    <option value="<?= $distance ?>"<?= $filters['distance'] === $distance ? ' selected' : '' ?>>Within <?= e(campus_distance($distance)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <fieldset>
                            <legend>Amenities &amp; necessities</legend>
                            <div class="amenities-checks">
                                <?php foreach ($amenities as $amenity): if (in_array($amenity['amenity_name'], $quickNames, true)) continue; ?>
                                    <label class="check-label"><input type="checkbox" name="amenities[]" value="<?= (int) $amenity['amenity_id'] ?>"<?= in_array((int) $amenity['amenity_id'], $filters['amenities'], true) ? ' checked' : '' ?>> <?= e($amenity['amenity_name']) ?></label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="actions">
                            <button type="submit" class="button small">Apply filters</button>
                            <a class="button small secondary" href="<?= e(url('index.php#listings')) ?>" data-clear-filters>Reset all</a>
                        </div>
                    </div>
                </details>
            </div>
        </form>

        <p id="finder-status" class="visually-hidden" role="status"></p>
        <section id="listings" class="listing-section" data-finder-results aria-labelledby="results-title">
            <?php require __DIR__ . '/includes/listing_results.php'; ?>
        </section>
    </div>

    <section class="how-it-works" id="how-it-works" aria-label="How Dayon works">
        <div class="container">
            <div class="section-intro">
                <p class="eyebrow">Built for Antique students &amp; property owners</p>
                <h2>How Dayon works</h2>
            </div>
            <div class="how-grid">
                <article class="how-card">
                    <span class="how-step"><?= icon('search') ?></span>
                    <h3>1. Discover &amp; Bookmark</h3>
                    <p>Filter boarding houses, apartments, dormitories, and bedspaces by town, barangay, budget, and utilities. Bookmark favorites and compare them side by side.</p>
                </article>
                <article class="how-card">
                    <span class="how-step"><?= icon('calendar') ?></span>
                    <h3>2. Visit or Hold a Slot</h3>
                    <p>Review exact room vacancies, utilities, and landlord details—then schedule an ocular visit or request a bed hold online.</p>
                </article>
                <article class="how-card">
                    <span class="how-step"><?= icon('shield') ?></span>
                    <h3>3. Transparent &amp; Admin-Reviewed</h3>
                    <p>Every listing is reviewed by Dayon administrators. Landlords can also display verified optional Antique permits like Barangay Clearance, BFP Fire Safety, or Sanitary Permits.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="container landlord-invite" id="for-landlords">
        <div class="landlord-invite-card">
            <div>
                <p class="eyebrow">For Boarding House &amp; Apartment Owners</p>
                <h2>Own a boarding house or apartment in <?= e(DAYON_PROVINCE) ?>?</h2>
                <p>Create a free landlord account, upload multiple photos, share only the necessities you want to display, and optionally add local Antique permits—business permits are never required to list.</p>
            </div>
            <div class="actions">
                <?php if (!$user): ?>
                    <a class="button" href="<?= e(url('register.php?role=landlord')) ?>">Create Landlord Account <?= icon('arrow') ?></a>
                    <a class="button secondary" href="<?= e(url('login.php')) ?>">Landlord Sign In</a>
                <?php elseif ($user['role_name'] === 'landlord'): ?>
                    <a class="button" href="<?= e(url('landlord/properties.php?new=1#property-form')) ?>">Post a Property <?= icon('arrow') ?></a>
                <?php elseif ($user['role_name'] === 'admin'): ?>
                    <a class="button" href="<?= e(url('admin/dashboard.php')) ?>">Open Admin Portal <?= icon('arrow') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
