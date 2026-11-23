<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/finder.php';

$filters = finder_filters($_GET);
$targetLocation = resolve_target_location($filters);
$locationChosen = has_location_selection($filters, $targetLocation);
$favorites = favorites_for($user);

$searchMeta = ['listings' => [], 'expanded' => false, 'radius_m' => 0, 'sort' => 'featured'];
$listings = [];
if ($locationChosen) {
    $searchMeta = find_nearest_properties($filters, $targetLocation);
    $listings = $searchMeta['listings'];
}

if (($_GET['fragment'] ?? '') === 'listings') {
    header('Content-Type: text/html; charset=UTF-8');
    require __DIR__ . '/includes/listing_results.php';
    exit;
}

$stats = row(
    "SELECT COUNT(DISTINCT p.property_id) AS verified_listings,
            COALESCE(SUM(CASE WHEN v.is_active = 1 THEN v.available_beds ELSE 0 END), 0) AS open_beds,
            COUNT(DISTINCT m.municipality_id) AS active_towns
     FROM properties p
     JOIN landlord_profiles lp USING (landlord_id)
     JOIN users owner ON owner.user_id = p.landlord_id
     JOIN barangays b USING (barangay_id)
     JOIN municipalities m USING (municipality_id)
     LEFT JOIN vw_room_vacancy v ON v.property_id = p.property_id
     WHERE " . PUBLIC_APPROVAL . " AND " . PUBLIC_LOCATION,
    [DAYON_PROVINCE]
);

$areaHighlights = rows(
    "SELECT m.municipality_id, m.municipality_name,
            COUNT(DISTINCT p.property_id) AS listing_count,
            COALESCE(SUM(CASE WHEN v.is_active = 1 THEN v.available_beds ELSE 0 END), 0) AS open_beds,
            MIN(CASE WHEN v.is_active = 1 THEN v.monthly_rate_per_bed END) AS min_rate
     FROM properties p
     JOIN landlord_profiles lp USING (landlord_id)
     JOIN users owner ON owner.user_id = p.landlord_id
     JOIN barangays b USING (barangay_id)
     JOIN municipalities m USING (municipality_id)
     LEFT JOIN vw_room_vacancy v ON v.property_id = p.property_id
     WHERE " . PUBLIC_APPROVAL . " AND " . PUBLIC_LOCATION . "
     GROUP BY m.municipality_id, m.municipality_name
     ORDER BY listing_count DESC, m.municipality_name",
    [DAYON_PROVINCE]
);

$amenities = rows('SELECT amenity_id, amenity_name FROM amenities ORDER BY amenity_name');
$quickNames = ['Free Wi-Fi', 'Shared Kitchen / Cooking Area', 'Own Comfort Room (CR)'];
$amenityIds = array_column($amenities, 'amenity_id', 'amenity_name');
$municipalities = rows(
    'SELECT municipality_id, municipality_name FROM municipalities WHERE province_name = ? ORDER BY FIELD(municipality_name, ?, ?, ?) DESC, municipality_name',
    [DAYON_PROVINCE, 'San Jose de Buenavista', 'Sibalom', 'Hamtic']
);
$areas = rows(
    'SELECT b.barangay_id, b.barangay_name, b.municipality_id, m.municipality_name
     FROM barangays b JOIN municipalities m USING (municipality_id)
     WHERE ' . PUBLIC_LOCATION . ' ORDER BY FIELD(m.municipality_name, ?, ?, ?) DESC, m.municipality_name, b.barangay_name',
    [DAYON_PROVINCE, 'San Jose de Buenavista', 'Sibalom', 'Hamtic']
);

$selectedRadius = $filters['radius'] !== null
    ? $filters['radius']
    : ($targetLocation ? (int) $targetLocation['default_radius'] : 5000);

$pageTitle = $targetLocation
    ? 'Nearest housing to ' . $targetLocation['short_label']
    : 'Where do you plan to stay in ' . DAYON_PROVINCE . '?';
$pageScripts = ['assets/js/finder.js'];
require __DIR__ . '/includes/header.php';
?>
<main id="main">
    <div class="container finder-main">
        <!-- STEP 1: LOCATION-FIRST SELECTOR -->
        <section class="location-hero<?= $locationChosen ? ' is-compact' : '' ?>" id="location-picker" aria-labelledby="finder-title">
            <?php if (!$locationChosen): ?>
                <div class="hero-photo-stage">
                <div class="finder-hero">
                    <div class="hero-copy">
                        <p class="hero-location"><?= icon('pin') ?> Step 1 · Choose Your Location in <?= e(DAYON_PROVINCE) ?></p>
                        <h1 id="finder-title">Where do you plan to stay?</h1>
                        <p class="hero-description">Select your school, town, barangay, or current location first—we'll instantly find the nearest boarding houses and apartments in that area.</p>
                        <div class="hero-stats" aria-label="Live housing statistics">
                            <div><strong><?= (int) ($stats['verified_listings'] ?? 0) ?></strong><span>Verified Listings</span></div>
                            <div><strong><?= (int) ($stats['open_beds'] ?? 0) ?></strong><span>Open Beds Now</span></div>
                            <div><strong><?= (int) ($stats['active_towns'] ?? 0) ?></strong><span>Active Antique Towns</span></div>
                        </div>
                    </div>
                    <div class="hero-picture">
                        <img src="<?= e(url('assets/images/san-jose-study.webp')) ?>" width="1200" height="800" alt="Sunlit student room with a single bed and a study desk by the window" fetchpriority="high">
                        <div class="hero-badge-float">
                            <?= icon('shield') ?>
                            <div><strong>Nearest-first matching</strong><span>Real distances across Antique campuses &amp; towns</span></div>
                        </div>
                    </div>
                </div>
                </div>
            <?php else: ?>
                <div class="active-location-bar">
                    <div class="active-location-info">
                        <span class="active-location-icon"><?= icon('pin') ?></span>
                        <div>
                            <small>Showing nearest housing for</small>
                            <h1 id="finder-title"><?= e($targetLocation ? $targetLocation['label'] : 'All of Antique') ?></h1>
                        </div>
                    </div>
                    <div class="active-location-controls">
                        <?php if ($targetLocation): ?>
                            <label class="inline-radius-label">
                                <span>Radius</span>
                                <select name="radius" form="finder-form" data-quick-filter>
                                    <?php foreach (SEARCH_RADIUS_OPTIONS as $rVal => $rLabel): ?>
                                        <option value="<?= $rVal ?>"<?= $selectedRadius === $rVal ? ' selected' : '' ?>><?= e($rLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php endif; ?>
                        <button type="button" class="button small secondary" data-toggle-location-panel aria-expanded="false" aria-controls="location-selector-card">
                            <?= icon('map') ?> Change Location
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="location-selector-card" id="location-selector-card"<?= $locationChosen ? ' hidden' : '' ?>>
                <div class="location-card-head">
                    <div>
                        <h2><?= icon('pin') ?> Select where you plan to stay</h2>
                        <p class="muted">Pick a popular campus/hub below, choose any town &amp; barangay in Antique, or use GPS.</p>
                    </div>
                    <button type="button" class="button small secondary gps-button" data-use-gps>
                        <?= icon('pin') ?> Use My Current Location
                    </button>
                </div>
                <p class="gps-status muted" data-gps-status role="status" hidden></p>

                <div class="popular-places-grid" role="list" aria-label="Popular Antique campuses and hubs">
                    <?php foreach (POPULAR_STAY_PLACES as $placeKey => $placeInfo):
                        $isPickedPlace = $filters['place'] === $placeKey;
                    ?>
                        <a role="listitem" class="place-card<?= $isPickedPlace ? ' is-selected' : '' ?>" href="<?= e(url('index.php?place=' . $placeKey . '#listings')) ?>">
                            <span class="place-tag"><?= e($placeInfo['tag']) ?></span>
                            <strong><?= e($placeInfo['name']) ?></strong>
                            <small><?= e($placeInfo['area']) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>

                <form method="get" action="<?= e(url('index.php#listings')) ?>" class="location-custom-form" data-location-step-form>
                    <label>Municipality in Antique
                        <select name="municipality" data-step-municipality required>
                            <option value="">Choose a town in Antique...</option>
                            <?php foreach ($municipalities as $town): ?>
                                <option value="<?= (int) $town['municipality_id'] ?>"<?= $filters['municipality'] === (int) $town['municipality_id'] ? ' selected' : '' ?>><?= e($town['municipality_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Barangay (optional)
                        <select name="barangay" data-step-barangay>
                            <option value="">Any barangay in selected town</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?= (int) $area['barangay_id'] ?>" data-municipality="<?= (int) $area['municipality_id'] ?>"<?= $filters['barangay'] === (int) $area['barangay_id'] ? ' selected' : '' ?>><?= e($area['barangay_name']) ?> (<?= e($area['municipality_name']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Search radius
                        <select name="radius">
                            <?php foreach (SEARCH_RADIUS_OPTIONS as $rVal => $rLabel): ?>
                                <option value="<?= $rVal ?>"<?= $selectedRadius === $rVal ? ' selected' : '' ?>><?= e($rLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="button location-submit">Find Nearest Housing <?= icon('arrow') ?></button>
                </form>

                <div class="location-card-footer">
                    <span class="muted">Want to explore everything without picking a spot first?</span>
                    <a href="<?= e(url('index.php?browse=all#listings')) ?>">Browse all listings across Antique <?= icon('arrow') ?></a>
                </div>
            </div>
        </section>

        <?php if (!$locationChosen): ?>
            <!-- GUIDED AREA PREVIEW BEFORE LOCATION SELECTION -->
            <section class="area-preview-section" id="listings" aria-labelledby="areas-heading">
                <div class="section-head" style="margin-bottom:14px;">
                    <div>
                        <h2 id="areas-heading">Active Housing Areas in Antique</h2>
                        <p class="muted">Select an area below or choose your exact destination above to find the nearest boarding houses &amp; apartments.</p>
                    </div>
                </div>
                <div class="area-preview-grid">
                    <?php foreach ($areaHighlights as $hub): ?>
                        <a class="area-hub-card" href="<?= e(url('index.php?municipality=' . (int) $hub['municipality_id'] . '#listings')) ?>">
                            <div class="area-hub-top">
                                <span class="area-hub-icon"><?= icon('building') ?></span>
                                <span class="badge available"><?= (int) $hub['open_beds'] ?> open <?= (int) $hub['open_beds'] === 1 ? 'bed' : 'beds' ?></span>
                            </div>
                            <h3><?= e($hub['municipality_name']) ?></h3>
                            <p class="muted">
                                <?= (int) $hub['listing_count'] ?> verified <?= (int) $hub['listing_count'] === 1 ? 'listing' : 'listings' ?>
                                <?php if ($hub['min_rate'] !== null): ?> · From <strong><?= e(display_price($hub['min_rate'])) ?>/mo</strong><?php endif; ?>
                            </p>
                            <span class="area-hub-cta">Find nearest in <?= e($hub['municipality_name']) ?> <?= icon('arrow') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else: ?>
            <!-- STEP 2: REFINE NEAREST RESULTS -->
            <form id="finder-form" method="get" action="<?= e(url('index.php#listings')) ?>" aria-label="Refine nearest boarding houses and apartments">
                <?php if ($filters['place']): ?><input type="hidden" name="place" value="<?= e($filters['place']) ?>"><?php endif; ?>
                <?php if ($filters['municipality']): ?><input type="hidden" name="municipality" value="<?= (int) $filters['municipality'] ?>"><?php endif; ?>
                <?php if ($filters['barangay']): ?><input type="hidden" name="barangay" value="<?= (int) $filters['barangay'] ?>"><?php endif; ?>
                <?php if ($filters['lat'] !== null && $filters['lng'] !== null): ?>
                    <input type="hidden" name="lat" value="<?= e((string) $filters['lat']) ?>">
                    <input type="hidden" name="lng" value="<?= e((string) $filters['lng']) ?>">
                <?php endif; ?>
                <?php if ($filters['browse']): ?><input type="hidden" name="browse" value="all"><?php endif; ?>

                <div class="type-pills" role="group" aria-label="Property type filter">
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

                <div class="finder-fields finder-fields-refine">
                    <label class="finder-search-field">Filter by keyword
                        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Property name, street, landmark, school..." maxlength="100">
                    </label>
                    <label>Monthly budget
                        <select name="budget">
                            <option value="">Any budget</option>
                            <?php foreach (array_unique([1500, 2000, 2500, 3500, 5000, 7000, 10000, $filters['budget']]) as $budget): if (!$budget) continue; ?>
                                <option value="<?= $budget ?>"<?= $filters['budget'] === $budget ? ' selected' : '' ?>>Up to <?= e(display_price($budget)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Room type
                        <select name="room_type">
                            <option value="">Any room type</option>
                            <?php foreach (ROOM_TYPES as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $filters['room_type'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Gender policy
                        <select name="gender">
                            <option value="">Any gender policy</option>
                            <?php foreach (GENDER_POLICIES as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $filters['gender'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button finder-submit" type="submit"><?= icon('search') ?> Refine</button>
                </div>

                <div class="quick-controls" aria-label="Quick filters">
                    <label class="filter-chip"><input class="visually-hidden" type="checkbox" name="available" value="1"<?= $filters['available'] ? ' checked' : '' ?> data-quick-filter><?= icon('check') ?> Available now</label>
                    <label class="filter-chip" title="Within 800 m of your selected destination"><input class="visually-hidden" type="checkbox" name="walkable" value="1"<?= $filters['walkable'] ? ' checked' : '' ?> data-quick-filter><?= icon('check') ?> Walking distance (&le;800m)</label>
                    <?php foreach ($quickNames as $name): if (!isset($amenityIds[$name])) continue; $amenityId = (int) $amenityIds[$name]; ?>
                        <label class="filter-chip"><input class="visually-hidden" type="checkbox" name="amenities[]" value="<?= $amenityId ?>"<?= in_array($amenityId, $filters['amenities'], true) ? ' checked' : '' ?> data-quick-filter><?= icon('check') ?> <?= e(SHORT_AMENITIES[$name]) ?></label>
                    <?php endforeach; ?>
                    <label class="filter-chip"><input class="visually-hidden" type="checkbox" name="no_curfew" value="1"<?= $filters['no_curfew'] ? ' checked' : '' ?> data-quick-filter><?= icon('check') ?> No curfew</label>
                    <label class="filter-chip" title="Listings with verified Antique local permits on file"><input class="visually-hidden" type="checkbox" name="with_permits" value="1"<?= $filters['with_permits'] ? ' checked' : '' ?> data-quick-filter><?= icon('check') ?> Permits on file</label>
                    <details class="filter-menu">
                        <summary><?= icon('filter') ?> More amenities</summary>
                        <div class="advanced-content">
                            <fieldset>
                                <legend>Amenities &amp; necessities</legend>
                                <div class="amenities-checks">
                                    <?php foreach ($amenities as $amenity): if (in_array($amenity['amenity_name'], $quickNames, true)) continue; ?>
                                        <label class="check-label"><input type="checkbox" name="amenities[]" value="<?= (int) $amenity['amenity_id'] ?>"<?= in_array((int) $amenity['amenity_id'], $filters['amenities'], true) ? ' checked' : '' ?>> <?= e($amenity['amenity_name']) ?></label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                            <div class="actions">
                                <button type="submit" class="button small">Apply amenities</button>
                                <a class="button small secondary" href="<?= e(url('index.php?browse=all#listings')) ?>" data-clear-filters>Reset all</a>
                            </div>
                        </div>
                    </details>
                </div>
            </form>

            <p id="finder-status" class="visually-hidden" role="status"></p>
            <section id="listings" class="listing-section" data-finder-results aria-labelledby="results-title">
                <?php require __DIR__ . '/includes/listing_results.php'; ?>
            </section>
        <?php endif; ?>
    </div>

    <section class="how-it-works" id="how-it-works" aria-label="How Dayon works">
        <div class="container">
            <div class="section-intro">
                <p class="eyebrow">Built for Antique students &amp; property owners</p>
                <h2>How Dayon works</h2>
            </div>
            <div class="how-grid">
                <article class="how-card">
                    <span class="how-step"><?= icon('pin') ?></span>
                    <h3>1. Choose Your Location First</h3>
                    <p>Pick your campus, town, barangay, or GPS location in Antique and immediately see the nearest boarding houses and apartments ranked by distance.</p>
                </article>
                <article class="how-card">
                    <span class="how-step"><?= icon('bookmark') ?></span>
                    <h3>2. Bookmark &amp; Compare</h3>
                    <p>Save your favorite houses with personal notes, compare rates, utilities, and amenities side by side, and request a visit or bed hold online.</p>
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
