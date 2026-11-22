<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/finder.php';

$id = positive_int($_GET['id'] ?? null);
$property = $id ? row(
    "SELECT p.*, b.barangay_name, m.municipality_name, m.province_name,
            CONCAT(owner.first_name, ' ', owner.last_name) AS owner_name,
            lp.verification_status AS landlord_verification_status,
            (" . PUBLIC_APPROVAL . ") AS is_approved
     FROM properties p JOIN landlord_profiles lp USING (landlord_id)
     JOIN users owner ON owner.user_id = p.landlord_id
     JOIN barangays b USING (barangay_id) JOIN municipalities m USING (municipality_id)
     WHERE p.property_id = ? AND ((" . PUBLIC_APPROVAL . " AND " . PUBLIC_LOCATION . ") OR p.landlord_id = ? OR ? = 'admin')",
    [$id, DAYON_PROVINCE, $user['user_id'] ?? 0, $user['role_name'] ?? '']
) : null;
if (!$property) {
    abort_request(404, 'This property listing is no longer available.');
}

$place = $property['municipality_name'] . ', ' . $property['province_name'];
$isPublic = (bool) $property['is_approved'] && $property['province_name'] === DAYON_PROVINCE;
$isOwnerOrAdmin = ($user && ((int) $user['user_id'] === (int) $property['landlord_id'] || $user['role_name'] === 'admin'));

$photos = rows('SELECT image_id, image_path, caption FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, image_id', [$id]);
$rooms = rows(
    'SELECT * FROM vw_room_vacancy WHERE property_id = ? AND is_active = 1
     ORDER BY (available_beds > 0) DESC, monthly_rate_per_bed, room_id',
    [$id]
);
$roomsById = array_column($rooms, null, 'room_id');
$selectedRoom = $roomsById[positive_int($_GET['room'] ?? null)] ?? ($rooms[0] ?? null);
$open = (int) ($selectedRoom['available_beds'] ?? 0);
$requestedMode = $_GET['request'] ?? '';
$holdMode = $open > 0 && $requestedMode !== 'visit';
$bookingPath = 'property.php?id=' . $id . ($selectedRoom ? '&room=' . $selectedRoom['room_id'] : '');
$bookingLogin = 'login.php?next=' . rawurlencode($bookingPath . '&request=' . ($holdMode ? 'hold' : 'visit') . '#booking');

$propertyAmenities = rows('SELECT amenity_name FROM property_amenities JOIN amenities USING (amenity_id) WHERE property_id = ? ORDER BY amenity_name', [$id]);
$roomAmenities = [];
foreach (rows(
    'SELECT ra.room_id, a.amenity_name FROM room_amenities ra JOIN amenities a USING (amenity_id)
     JOIN rooms r USING (room_id) WHERE r.property_id = ? ORDER BY a.amenity_name',
    [$id]
) as $amenity) {
    $roomAmenities[$amenity['room_id']][] = $amenity['amenity_name'];
}

$permits = property_verified_permits($id, (int) $property['landlord_id'], $isOwnerOrAdmin);
$customDetails = parse_custom_details($property['custom_details'] ?? null);

// Build adaptive "Necessities & Living Details" list — ONLY include fields the landlord actually filled in!
$necessities = [];
if (!empty($property['water_billing'])) {
    $necessities[] = [
        'icon' => 'droplet',
        'label' => 'Water',
        'value' => WATER_BILLING_LABELS[$property['water_billing']] ?? $property['water_billing'],
        'note' => $property['water_notes'] ?? '',
    ];
} elseif (!empty($property['water_notes'])) {
    $necessities[] = ['icon' => 'droplet', 'label' => 'Water', 'value' => $property['water_notes'], 'note' => ''];
}

if (!empty($property['electricity_billing'])) {
    $necessities[] = [
        'icon' => 'zap',
        'label' => 'Electricity',
        'value' => ELECTRICITY_BILLING_LABELS[$property['electricity_billing']] ?? $property['electricity_billing'],
        'note' => $property['electricity_notes'] ?? '',
    ];
} elseif (!empty($property['electricity_notes'])) {
    $necessities[] = ['icon' => 'zap', 'label' => 'Electricity', 'value' => $property['electricity_notes'], 'note' => ''];
}

if (!empty($property['wifi_policy'])) {
    $necessities[] = [
        'icon' => 'wifi',
        'label' => 'Wi-Fi / Internet',
        'value' => WIFI_POLICY_LABELS[$property['wifi_policy']] ?? $property['wifi_policy'],
        'note' => $property['wifi_notes'] ?? '',
    ];
} elseif (!empty($property['wifi_notes'])) {
    $necessities[] = ['icon' => 'wifi', 'label' => 'Wi-Fi / Internet', 'value' => $property['wifi_notes'], 'note' => ''];
}

if (!empty($property['furnishing_status'])) {
    $necessities[] = [
        'icon' => 'home',
        'label' => 'Furnishing',
        'value' => FURNISHING_LABELS[$property['furnishing_status']] ?? $property['furnishing_status'],
        'note' => '',
    ];
}
if (!empty($property['deposit_terms'])) {
    $necessities[] = ['icon' => 'wallet', 'label' => 'Advance & Deposit', 'value' => $property['deposit_terms'], 'note' => ''];
}
if (!empty($property['minimum_stay'])) {
    $necessities[] = ['icon' => 'calendar', 'label' => 'Minimum Stay', 'value' => $property['minimum_stay'], 'note' => ''];
}
if (!empty($property['payment_methods'])) {
    $methods = array_filter(array_map('trim', explode(',', (string) $property['payment_methods'])));
    if ($methods) {
        $necessities[] = ['icon' => 'card', 'label' => 'Accepted Payments', 'value' => implode(' · ', $methods), 'note' => ''];
    }
}
if (!empty($property['no_curfew'])) {
    $necessities[] = ['icon' => 'clock', 'label' => 'Curfew / Access', 'value' => 'No curfew · 24/7 tenant access', 'note' => ''];
} elseif (!empty($property['curfew_time'])) {
    $necessities[] = ['icon' => 'clock', 'label' => 'Curfew', 'value' => date('g:i A', strtotime($property['curfew_time'])), 'note' => ''];
}
if (!empty($property['cooking_policy'])) {
    $necessities[] = ['icon' => 'info', 'label' => 'Cooking Policy', 'value' => $property['cooking_policy'], 'note' => ''];
}
if (!empty($property['visitor_policy'])) {
    $necessities[] = ['icon' => 'users', 'label' => 'Visitor Policy', 'value' => $property['visitor_policy'], 'note' => ''];
}
if (!empty($property['pet_policy'])) {
    $necessities[] = ['icon' => 'heart', 'label' => 'Pet Policy', 'value' => $property['pet_policy'], 'note' => ''];
}

$hasContactInfo = !empty($property['contact_person']) || !empty($property['contact_phone']) || !empty($property['contact_social']) || !empty($property['viewing_hours']);
$hasAboutSection = !empty($property['description']) || !empty($propertyAmenities) || !empty($property['house_rules']);

$student = ($user['role_name'] ?? '') === 'student';
$canRequest = $isPublic && $student && !active_lease((int) $user['user_id']);
$favorite = $student && isset(favorites_for($user)[$id]);
$coordinates = $property['latitude'] !== null && $property['longitude'] !== null;
$mapQuery = trim(($property['street_address'] ? $property['street_address'] . ', ' : '') . $property['barangay_name'] . ', ' . $place . ', Philippines');
$mapUrl = $coordinates
    ? 'https://www.openstreetmap.org/?mlat=' . rawurlencode((string) $property['latitude']) . '&mlon=' . rawurlencode((string) $property['longitude'])
    : 'https://www.openstreetmap.org/search?query=' . rawurlencode($mapQuery);

$pageTitle = $property['property_name'];
$isPropertyPage = true;
$pageScripts = $photos ? ['assets/js/property.js'] : [];
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container property-main">
    <?php if (!$isPublic): ?>
        <div class="flash flash-info preview-banner" role="status">
            <strong>Preview mode:</strong> This listing is currently <em><?= e(str_replace('_', ' ', $property['approval_status'])) ?></em> and visible only to you and Dayon administrators.
            <?php if (($user['role_name'] ?? '') === 'landlord'): ?>
                <a href="<?= e(url('landlord/properties.php?edit=' . $id . '#property-form')) ?>">Edit listing</a>
            <?php elseif (($user['role_name'] ?? '') === 'admin'): ?>
                <a href="<?= e(url('admin/properties.php#listing-review-' . $id)) ?>">Review in Admin Portal</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="property-layout">
        <div class="property-content">
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <a href="<?= e(url('index.php#listings')) ?>">Find housing</a> <span aria-hidden="true">›</span>
                <a href="<?= e(url('index.php?municipality=' . (int) scalar('SELECT municipality_id FROM barangays WHERE barangay_id = ?', [$property['barangay_id']]) . '#listings')) ?>"><?= e($property['municipality_name']) ?></a> <span aria-hidden="true">›</span>
                <?= e($property['barangay_name']) ?>
            </nav>

            <header class="property-heading">
                <div class="property-title-row">
                    <div class="property-title">
                        <span class="type-pill-inline"><?= e(property_type_label($property['property_type'] ?? 'boarding_house')) ?></span>
                        <?php if ($isPublic): ?>
                            <span class="verified-chip"><?= icon('check') ?> Verified Listing</span>
                        <?php else: ?>
                            <span class="badge <?= e($property['approval_status']) ?>"><?= e(ucwords(str_replace('_', ' ', $property['approval_status']))) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($property['is_featured'])): ?>
                            <span class="featured-chip"><?= icon('star') ?> Featured</span>
                        <?php endif; ?>
                    </div>
                </div>
                <h1><?= e($property['property_name']) ?></h1>
                <p class="property-meta">
                    <span><?= icon('pin') ?> <?= e($property['barangay_name'] . ', ' . $place) ?></span>
                    <?php if (!empty($property['gender_policy'])): ?>
                        <span><?= icon('users') ?> <?= e(GENDER_POLICIES[$property['gender_policy']] ?? $property['gender_policy']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($property['no_curfew'])): ?>
                        <span><?= icon('clock') ?> No Curfew</span>
                    <?php elseif (!empty($property['curfew_time'])): ?>
                        <span><?= icon('clock') ?> Curfew <?= e(date('g:i A', strtotime($property['curfew_time']))) ?></span>
                    <?php endif; ?>
                    <?php if ($property['distance_to_campus_m'] !== null): ?>
                        <span><?= icon('map') ?> <?= e(campus_distance((int) $property['distance_to_campus_m'])) ?> to <?= e($property['nearest_school'] ?: DAYON_CAMPUS) ?></span>
                    <?php endif; ?>
                </p>
            </header>

            <?php if ($photos): ?>
                <div class="property-gallery<?= count($photos) === 1 ? ' is-single' : '' ?>" data-photo-count="<?= min(count($photos), 5) ?>" role="group" aria-label="Property photos">
                    <?php foreach (array_slice($photos, 0, 5) as $index => $photo): ?>
                        <a href="<?= e(media_url($photo['image_path'])) ?>" data-gallery-photo data-caption="<?= e($photo['caption'] ?? '') ?>">
                            <img src="<?= e(media_url($photo['image_path'])) ?>" alt="<?= e($photo['caption'] ?: ($property['property_name'] . ', photo ' . ($index + 1))) ?>" <?= $index ? 'decoding="async"' : 'fetchpriority="high"' ?>>
                            <?php if (!empty($photo['caption'])): ?>
                                <span class="photo-caption-pill"><?= e($photo['caption']) ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div class="property-gallery-actions">
                    <button class="button small secondary" type="button" data-open-gallery aria-haspopup="dialog" hidden><?= icon('image') ?> View <?= count($photos) === 1 ? 'photo' : 'all ' . count($photos) . ' photos' ?></button>
                </div>
                <?php if (count($photos) > 5): ?>
                    <details class="gallery-expand" data-extra-photos>
                        <summary>More property photos (<?= count($photos) - 5 ?>)</summary>
                        <div class="property-gallery">
                            <?php foreach (array_slice($photos, 5) as $index => $photo): ?>
                                <a href="<?= e(media_url($photo['image_path'])) ?>" data-gallery-photo data-caption="<?= e($photo['caption'] ?? '') ?>">
                                    <img src="<?= e(media_url($photo['image_path'])) ?>" alt="<?= e($photo['caption'] ?: ($property['property_name'] . ', photo ' . ($index + 6))) ?>" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
            <?php else: ?>
                <div class="image-placeholder property-gallery-empty" aria-hidden="true"><?= icon('building') ?></div>
            <?php endif; ?>

            <?php if ($necessities || $customDetails): ?>
                <section class="property-section property-info" id="necessities">
                    <h2>Necessities &amp; Living Details</h2>
                    <?php if ($necessities): ?>
                        <div class="necessities-grid">
                            <?php foreach ($necessities as $item): ?>
                                <div class="necessity-item">
                                    <span class="necessity-icon"><?= icon($item['icon']) ?></span>
                                    <div>
                                        <span class="necessity-label"><?= e($item['label']) ?></span>
                                        <strong><?= e($item['value']) ?></strong>
                                        <?php if ($item['note'] !== ''): ?>
                                            <small class="muted"><?= e($item['note']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($customDetails): ?>
                        <div class="custom-details-block">
                            <h3>Additional Information from Landlord</h3>
                            <dl class="custom-details-list">
                                <?php foreach ($customDetails as $detail): ?>
                                    <div>
                                        <dt><?= e($detail['label']) ?></dt>
                                        <dd><?= e($detail['value']) ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="property-section" id="rooms">
                <div class="section-head">
                    <h2>Rooms &amp; Rates</h2>
                    <?php if ($selectedRoom && ($canRequest || !$user)): ?>
                        <a class="button small mobile-booking-link" href="#booking">Book / Inquire <?= icon('arrow') ?></a>
                    <?php endif; ?>
                </div>
                <?php if (!$rooms): ?>
                    <div class="property-info">
                        <p class="muted">The landlord has not listed individual room units yet. <?= $hasContactInfo ? 'Use the contact details on this page to inquire directly.' : 'Check back soon or bookmark this property for updates.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="room-list">
                        <?php foreach ($rooms as $room):
                            $beds = (int) $room['available_beds'];
                            $picked = $selectedRoom && (int) $selectedRoom['room_id'] === (int) $room['room_id'];
                            $roomLink = 'property.php?id=' . $id . '&room=' . $room['room_id'] . '#booking';
                        ?>
                            <article class="room-card<?= $picked ? ' is-selected' : '' ?>">
                                <div class="room-thumb" aria-hidden="true">
                                    <?php if ($photos): ?>
                                        <img src="<?= e(media_url($photos[0]['image_path'])) ?>" alt="" loading="lazy">
                                    <?php else: ?><span class="image-placeholder"><?= icon('bed') ?></span><?php endif; ?>
                                </div>
                                <div>
                                    <h3>Room <?= e($room['room_number']) ?> <span class="muted" style="font-weight:500;">· <?= e(ROOM_TYPES[$room['room_type']] ?? ucfirst($room['room_type'])) ?></span></h3>
                                    <div class="room-chips">
                                        <span class="vacancy-chip<?= availability_class($beds, (int) $room['max_capacity']) ?>"><?= e(availability_label($beds, (int) $room['max_capacity'])) ?></span>
                                        <span class="floor-chip">Floor <?= (int) $room['floor_number'] ?></span>
                                    </div>
                                    <?php if (!empty($roomAmenities[$room['room_id']])): ?>
                                        <p class="room-amenities"><?= e(implode(' · ', $roomAmenities[$room['room_id']])) ?></p>
                                    <?php endif; ?>
                                    <div class="room-rate">
                                        <span><strong><?= e(display_price($room['monthly_rate_per_bed'])) ?></strong> / bed / mo</span>
                                        <?php if ($picked): ?>
                                            <a class="button small secondary" href="#booking" aria-current="true">Selected <?= icon('check') ?></a>
                                        <?php else: ?>
                                            <a class="button small secondary" href="<?= e(url($roomLink)) ?>">Select <?= icon('arrow') ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($hasAboutSection): ?>
                <section class="property-section property-info">
                    <h2>About This <?= e(property_type_label($property['property_type'] ?? 'boarding_house')) ?></h2>
                    <?php if (!empty($property['description'])): ?>
                        <p class="preserve-lines"><?= e($property['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($propertyAmenities): ?>
                        <h3>Amenities &amp; Shared Facilities</h3>
                        <ul class="amenity-tags">
                            <?php foreach ($propertyAmenities as $amenity): ?><li><?= icon('check') ?> <?= e($amenity['amenity_name']) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($property['house_rules'])): ?>
                        <h3>House Rules</h3>
                        <p class="preserve-lines"><?= e($property['house_rules']) ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($permits): ?>
                <section class="property-section property-info permits-showcase" id="permits">
                    <div class="section-head">
                        <h2>Antique Permits &amp; Safety Documents on File</h2>
                        <span class="badge verified"><?= icon('shield') ?> Optional · Landlord Provided</span>
                    </div>
                    <p class="muted">Permits are not required on Dayon, but this landlord voluntarily submitted the following local compliance records for transparency.</p>
                    <ul class="permit-badge-grid">
                        <?php foreach ($permits as $permit): ?>
                            <li class="permit-badge-card status-<?= e($permit['verification_status']) ?>">
                                <span class="permit-badge-icon"><?= icon($permit['verification_status'] === 'verified' ? 'award' : 'file') ?></span>
                                <div>
                                    <strong><?= e(permit_label($permit)) ?></strong>
                                    <?php if (!empty($permit['issuing_office'])): ?>
                                        <span class="muted"><?= e($permit['issuing_office']) ?></span>
                                    <?php endif; ?>
                                    <div class="permit-badge-meta">
                                        <span class="badge <?= e($permit['verification_status'] === 'verified' ? 'verified' : $permit['verification_status']) ?>"><?= e($permit['verification_status'] === 'verified' ? 'Admin Verified' : ucfirst($permit['verification_status'])) ?></span>
                                        <?php if (!empty($permit['expiry_date'])): ?>
                                            <small class="muted">Valid until <?= e(date('M Y', strtotime($permit['expiry_date']))) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section class="property-section property-info" id="location">
                <h2>Location</h2>
                <p><strong><?= e(($property['street_address'] ? $property['street_address'] . ', ' : '') . $property['barangay_name'] . ', ' . $place) ?></strong></p>
                <?php if ($property['distance_to_campus_m'] !== null): ?>
                    <p class="muted"><?= e(campus_distance((int) $property['distance_to_campus_m'])) ?> from <?= e($property['nearest_school'] ?: DAYON_CAMPUS) ?></p>
                <?php elseif (!empty($property['nearest_school'])): ?>
                    <p class="muted">Near <?= e($property['nearest_school']) ?></p>
                <?php endif; ?>
                <?php if (!empty($property['nearest_landmark'])): ?>
                    <p class="muted">Landmark: <?= e($property['nearest_landmark']) ?></p>
                <?php endif; ?>
                <?php if ($coordinates):
                    $lat = (float) $property['latitude'];
                    $lng = (float) $property['longitude'];
                    $bbox = implode(',', [$lng - .005, $lat - .0035, $lng + .005, $lat + .0035]);
                    $embed = 'https://www.openstreetmap.org/export/embed.html?' . http_build_query(['bbox' => $bbox, 'layer' => 'mapnik', 'marker' => $lat . ',' . $lng]);
                ?>
                    <iframe class="property-map" loading="lazy" title="Map of <?= e($property['property_name']) ?>" src="<?= e($embed) ?>"></iframe>
                <?php endif; ?>
                <div class="actions">
                    <a class="button small secondary" target="_blank" rel="noopener" href="<?= e($mapUrl) ?>"><?= icon('map') ?> Open in OpenStreetMap <?= icon('external') ?></a>
                </div>
            </section>
        </div>

        <aside class="property-aside" aria-label="Booking, bookmarks and landlord contact">
            <section class="booking-card" id="booking" tabindex="-1">
                <h2><?= $canRequest || !$user ? 'Book or Inquire' : 'Selected Room' ?> <?php if ($selectedRoom): ?><span class="booking-room">Room <?= e($selectedRoom['room_number']) ?></span><?php endif; ?></h2>
                <?php if ($selectedRoom): ?>
                    <p class="booking-rate"><strong><?= e(display_price($selectedRoom['monthly_rate_per_bed'])) ?></strong> <span class="muted">/ bed / month</span></p>
                <?php endif; ?>
                <?php if ($selectedRoom && ($canRequest || !$user)): ?>
                    <nav class="booking-tabs" aria-label="Request type">
                        <a href="<?= e(url($bookingPath . '&request=visit#booking')) ?>"<?= !$holdMode ? ' aria-current="page"' : '' ?>>Schedule Visit</a>
                        <?php if ($open): ?>
                            <a href="<?= e(url($bookingPath . '&request=hold#booking')) ?>"<?= $holdMode ? ' aria-current="page"' : '' ?>>Request Bed Hold</a>
                        <?php else: ?><span class="unavailable-tab">Room Full</span><?php endif; ?>
                    </nav>
                    <form class="booking-form" method="post" action="<?= e(url('actions/reservation_action.php')) ?>">
                        <?php if ($canRequest): ?>
                            <?= csrf_field() ?>
                            <input type="hidden" name="room_id" value="<?= (int) $selectedRoom['room_id'] ?>">
                            <input type="hidden" name="request_type" value="<?= $holdMode ? 'bed_reservation' : 'ocular_visit' ?>">
                        <?php endif; ?>
                        <fieldset class="booking-fields"<?= $canRequest ? '' : ' disabled' ?>>
                            <legend class="visually-hidden"><?= $holdMode ? 'Bed hold details' : 'Visit details' ?></legend>
                            <?php if ($holdMode): ?>
                                <label>Move-in Date (est.)<input type="date" name="move_in_date" min="<?= e(date('Y-m-d')) ?>" required></label>
                                <label>Estimated Stay
                                    <select name="requested_months" required>
                                        <?php for ($months = 1; $months <= 12; $months++): ?>
                                            <option value="<?= $months ?>"<?= $months === 5 ? ' selected' : '' ?>><?= $months ?> <?= $months === 1 ? 'Month' : 'Months' ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                            <?php else: ?>
                                <label>Visit Date and Time<input type="datetime-local" name="scheduled_at" min="<?= e(date('Y-m-d\TH:i')) ?>" required></label>
                            <?php endif; ?>
                            <label>Notes to Landlord (Optional)<textarea name="notes" rows="3" maxlength="4900" placeholder="Ask about room availability, appliances, or viewing schedule..."></textarea></label>
                        </fieldset>
                        <?php if ($canRequest): ?>
                            <button type="submit" class="button wide">Submit Request</button>
                            <p class="booking-note">Your landlord reviews the request before confirming.</p>
                        <?php else: ?>
                            <p class="booking-note">Sign in as a student to request a visit or bed hold.</p>
                            <a class="button wide" href="<?= e(url($bookingLogin)) ?>">Log in to request <?= icon('arrow') ?></a>
                        <?php endif; ?>
                    </form>
                <?php elseif ($student): ?>
                    <p class="booking-note">See your submitted requests in <a href="<?= e(url('student/reservations.php')) ?>">My Requests</a>.</p>
                <?php elseif (!$selectedRoom): ?>
                    <p class="booking-note">Online room booking opens once the landlord adds room units.</p>
                <?php endif; ?>

                <?php if ($student): ?>
                    <form class="booking-bookmark" method="post" action="<?= e(url('api/toggle_bookmark.php')) ?>" data-bookmark>
                        <?= csrf_field() ?>
                        <input type="hidden" name="property_id" value="<?= $id ?>">
                        <button class="button secondary wide<?= $favorite ? ' is-saved' : '' ?>" type="submit" aria-pressed="<?= $favorite ? 'true' : 'false' ?>">
                            <?= icon('bookmark') ?> <span data-bookmark-label><?= $favorite ? 'Bookmarked' : 'Bookmark This Property' ?></span>
                        </button>
                    </form>
                <?php elseif (!$user): ?>
                    <a class="button secondary wide booking-bookmark" href="<?= e(url('login.php?next=' . rawurlencode('property.php?id=' . $id))) ?>"><?= icon('bookmark') ?> Sign in to Bookmark</a>
                <?php endif; ?>
            </section>

            <?php if ($hasContactInfo): ?>
                <section class="booking-card contact-card">
                    <h3>Landlord &amp; Viewing Details</h3>
                    <dl class="contact-list">
                        <?php if (!empty($property['contact_person'])): ?>
                            <div><dt><?= icon('user') ?> Contact</dt><dd><?= e($property['contact_person']) ?></dd></div>
                        <?php endif; ?>
                        <?php if (!empty($property['contact_phone'])): ?>
                            <div><dt><?= icon('phone') ?> Phone</dt><dd><a href="tel:<?= e(preg_replace('/[^+0-9]/', '', (string) $property['contact_phone'])) ?>"><?= e($property['contact_phone']) ?></a></dd></div>
                        <?php endif; ?>
                        <?php if (!empty($property['contact_social'])):
                            $social = (string) $property['contact_social'];
                            $isUrl = (bool) preg_match('~^https?://~i', $social);
                        ?>
                            <div><dt><?= icon('message') ?> Social / Messenger</dt><dd><?php if ($isUrl): ?><a href="<?= e($social) ?>" target="_blank" rel="noopener"><?= e($social) ?></a><?php else: ?><?= e($social) ?><?php endif; ?></dd></div>
                        <?php endif; ?>
                        <?php if (!empty($property['viewing_hours'])): ?>
                            <div><dt><?= icon('clock') ?> Viewing Hours</dt><dd><?= e($property['viewing_hours']) ?></dd></div>
                        <?php endif; ?>
                    </dl>
                </section>
            <?php endif; ?>

            <?php if ($student): ?>
                <details class="report-listing">
                    <summary><?= icon('report') ?> Report Listing</summary>
                    <form method="post" action="<?= e(url('actions/student_report.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="property_id" value="<?= $id ?>">
                        <label>Reason<textarea name="reason" required rows="3" maxlength="5000" placeholder="Share incorrect rates, unavailable rooms, or safety concerns..."></textarea></label>
                        <button type="submit" class="button small secondary">Send Report</button>
                    </form>
                </details>
            <?php endif; ?>
        </aside>
    </div>

    <?php if ($photos): ?>
        <dialog class="gallery-dialog" id="property-gallery-view" aria-labelledby="gallery-view-title">
            <div class="gallery-dialog-top">
                <h2 id="gallery-view-title"><?= e($property['property_name']) ?> photos</h2>
                <form method="dialog"><button type="submit" class="button icon-button secondary" aria-label="Close gallery" autofocus><?= icon('close') ?></button></form>
            </div>
            <img src="<?= e(media_url($photos[0]['image_path'])) ?>" alt="<?= e($property['property_name']) ?>, photo 1" data-gallery-preview>
            <div class="gallery-dialog-controls">
                <?php if (count($photos) > 1): ?><button type="button" class="button small secondary" data-gallery-step="-1"><?= icon('arrow-left') ?> Previous</button><?php endif; ?>
                <p data-gallery-caption role="status"></p>
                <?php if (count($photos) > 1): ?><button type="button" class="button small secondary" data-gallery-step="1">Next <?= icon('arrow') ?></button><?php endif; ?>
            </div>
        </dialog>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
