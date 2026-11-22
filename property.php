<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/finder.php';

$id = positive_int($_GET['id'] ?? null);
$property = $id ? row(
    "SELECT p.*, b.barangay_name, m.municipality_name, m.province_name, (" . PUBLIC_APPROVAL . ") AS is_approved
     FROM properties p JOIN landlord_profiles lp USING (landlord_id)
     JOIN users owner ON owner.user_id = p.landlord_id
     JOIN barangays b USING (barangay_id) JOIN municipalities m USING (municipality_id)
     WHERE p.property_id = ? AND ((" . PUBLIC_APPROVAL . " AND " . PUBLIC_LOCATION . ") OR p.landlord_id = ? OR ? = 'admin')",
    [$id, DAYON_MUNICIPALITY, DAYON_PROVINCE, $user['user_id'] ?? 0, $user['role_name'] ?? '']
) : null;
if (!$property) {
    abort_request(404, 'This boarding house is no longer available.');
}
$place = $property['municipality_name'] . ', ' . $property['province_name'];
$isPublic = (bool) $property['is_approved'] && $place === DAYON_LOCATION;
$photos = rows('SELECT image_path FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, image_id', [$id]);
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
$student = ($user['role_name'] ?? '') === 'student';
$canRequest = $isPublic && $student && !active_lease((int) $user['user_id']);
$favorite = $student && isset(favorites_for($user)[$id]);
$coordinates = $property['latitude'] !== null && $property['longitude'] !== null;
$mapUrl = $coordinates
    ? 'https://www.openstreetmap.org/?mlat=' . rawurlencode($property['latitude']) . '&mlon=' . rawurlencode($property['longitude'])
    : 'https://www.openstreetmap.org/search?query=' . rawurlencode($property['barangay_name'] . ', ' . $place . ', Philippines');
$pageTitle = $property['property_name'];
$isPropertyPage = true;
require __DIR__ . '/includes/header.php';
?>
<main id="main" class="container property-main">
    <div class="property-layout">
        <div class="property-content">
            <p class="breadcrumbs"><a href="<?= e(url('index.php#listings')) ?>">Find Rooms</a> › <?= e($property['barangay_name']) ?> › <?= e($property['property_name']) ?></p>
            <header class="property-heading">
                <div class="property-title">
                    <h1><?= e($property['property_name']) ?></h1>
                    <?php if ($isPublic): ?><span class="verified-chip"><?= icon('check') ?> Verified Property</span>
                    <?php else: ?><span class="badge"><?= e(ucwords(str_replace('_', ' ', $property['approval_status']))) ?> listing preview</span><?php endif; ?>
                </div>
                <p class="property-meta"><?= e($property['barangay_name'] . ', ' . $place) ?> · <?= e(GENDER_POLICIES[$property['gender_policy']]) ?>
                    <?php if ($property['curfew_time']): ?> · Curfew <?= e(date('g:i A', strtotime($property['curfew_time']))) ?><?php endif; ?>
                </p>
            </header>
            <?php if ($photos): ?>
                <div class="property-gallery<?= count($photos) === 1 ? ' is-single' : '' ?>" role="group" aria-label="Property photos">
                    <?php foreach (array_slice($photos, 0, 5) as $index => $photo): ?>
                        <a href="<?= e(media_url($photo['image_path'])) ?>">
                            <img src="<?= e(media_url($photo['image_path'])) ?>" alt="<?= e($property['property_name']) ?>, photo <?= $index + 1 ?>" <?= $index ? 'loading="lazy"' : 'fetchpriority="high"' ?>>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($photos) > 5): ?>
                    <details class="gallery-expand">
                        <summary>More property photos (<?= count($photos) - 5 ?>)</summary>
                        <div class="property-gallery">
                            <?php foreach (array_slice($photos, 5) as $index => $photo): ?>
                                <a href="<?= e(media_url($photo['image_path'])) ?>"><img src="<?= e(media_url($photo['image_path'])) ?>" alt="<?= e($property['property_name']) ?>, photo <?= $index + 6 ?>" loading="lazy"></a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>
            <?php else: ?>
                <div class="image-placeholder property-gallery-empty" aria-hidden="true"><?= icon('home') ?></div>
            <?php endif; ?>
            <section class="property-section" id="rooms">
                <h2>Available Rooms</h2>
                <?php if (!$rooms): ?><p class="empty-state">This property has no active rooms.</p><?php endif; ?>
                <div class="room-list">
                    <?php foreach ($rooms as $room):
                        $beds = (int) $room['available_beds'];
                        $picked = $selectedRoom['room_id'] === $room['room_id'];
                        $roomLink = 'property.php?id=' . $id . '&room=' . $room['room_id'] . '#booking';
                    ?>
                        <article class="room-card<?= $picked ? ' is-selected' : '' ?>">
                            <div class="room-thumb" aria-hidden="true">
                                <?php if ($photos): ?>
                                    <img src="<?= e(media_url($photos[0]['image_path'])) ?>" alt="" loading="lazy">
                                <?php else: ?><span class="image-placeholder"><?= icon('bed') ?></span><?php endif; ?>
                            </div>
                            <div>
                                <h3>Room <?= e($room['room_number']) ?> (<?= e(ROOM_TYPES[$room['room_type']]) ?>)</h3>
                                <span class="vacancy-chip<?= availability_class($beds) ?>"><?= e(availability_label($beds, (int) $room['max_capacity'])) ?></span>
                                <?php if (!empty($roomAmenities[$room['room_id']])): ?>
                                    <p class="room-amenities"><?= e(implode(' · ', $roomAmenities[$room['room_id']])) ?></p>
                                <?php endif; ?>
                                <div class="room-rate">
                                    <span><strong><?= e(display_price($room['monthly_rate_per_bed'])) ?></strong> / bed / mo</span>
                                    <a class="button small secondary" href="<?= e(url($roomLink)) ?>"<?= $picked ? ' aria-current="true"' : '' ?>>View Room</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
        <aside class="property-aside" aria-label="Requests and bookmarks">
            <section class="booking-card" id="booking" tabindex="-1">
                <h2>Book a Slot <?php if ($selectedRoom): ?><span class="booking-room">Room <?= e($selectedRoom['room_number']) ?></span><?php endif; ?></h2>
                <?php if ($selectedRoom): ?>
                    <p class="booking-rate"><strong><?= e(display_price($selectedRoom['monthly_rate_per_bed'])) ?></strong> / bed / month</p>
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
                                <label>Number of Months
                                    <select name="requested_months" required>
                                        <?php for ($months = 1; $months <= 12; $months++): ?>
                                            <option value="<?= $months ?>"<?= $months === 3 ? ' selected' : '' ?>><?= $months ?> <?= $months === 1 ? 'Month' : 'Months' ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                            <?php else: ?>
                                <label>Visit Date and Time<input type="datetime-local" name="scheduled_at" min="<?= e(date('Y-m-d\TH:i')) ?>" required></label>
                            <?php endif; ?>
                            <label>Notes to Landlord (Optional)<textarea name="notes" rows="3" maxlength="4900"></textarea></label>
                        </fieldset>
                        <?php if ($canRequest): ?>
                            <button type="submit" class="button wide">Submit Request</button>
                            <p class="booking-note">Your landlord reviews the request before confirming.</p>
                        <?php else: ?>
                            <a class="button wide" href="<?= e(url($bookingLogin)) ?>">Log In to Request <?= icon('arrow') ?></a>
                        <?php endif; ?>
                    </form>
                <?php elseif ($student): ?>
                    <p class="booking-note">See your submitted requests in <a href="<?= e(url('student/reservations.php')) ?>">My Reservations</a>.</p>
                <?php elseif (!$selectedRoom): ?>
                    <p class="booking-note">Requests open when the landlord adds an active room.</p>
                <?php endif; ?>
                <?php if ($student): ?>
                    <form class="booking-bookmark" method="post" action="<?= e(url('api/toggle_bookmark.php')) ?>" data-bookmark>
                        <?= csrf_field() ?>
                        <input type="hidden" name="property_id" value="<?= $id ?>">
                        <button class="button secondary wide<?= $favorite ? ' is-saved' : '' ?>" type="submit" aria-pressed="<?= $favorite ? 'true' : 'false' ?>">
                            <?= icon('bookmark') ?> <span data-bookmark-label><?= $favorite ? 'Bookmarked' : 'Bookmark Property' ?></span>
                        </button>
                    </form>
                <?php elseif (!$user): ?>
                    <a class="button secondary wide booking-bookmark" href="<?= e(url($bookingLogin)) ?>"><?= icon('bookmark') ?> Bookmark Property</a>
                <?php endif; ?>
            </section>
            <?php if ($student): ?>
                <details class="report-listing">
                    <summary><?= icon('report') ?> Report Listing</summary>
                    <form method="post" action="<?= e(url('actions/student_report.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="property_id" value="<?= $id ?>">
                        <label>Reason<textarea name="reason" required rows="3" maxlength="5000"></textarea></label>
                        <button type="submit" class="button small secondary">Send Report</button>
                    </form>
                </details>
            <?php endif; ?>
        </aside>
    </div>
    <div class="property-lower">
        <section class="property-section property-info">
            <h2>About This Boarding House</h2>
            <p class="preserve-lines"><?= e($property['description']) ?></p>
            <?php if ($propertyAmenities): ?>
                <h3>Property Amenities</h3>
                <ul class="amenity-tags">
                    <?php foreach ($propertyAmenities as $amenity): ?><li><?= icon('check') ?> <?= e($amenity['amenity_name']) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($property['house_rules']): ?>
                <h3>House Rules</h3>
                <p class="preserve-lines"><?= e($property['house_rules']) ?></p>
            <?php endif; ?>
        </section>
        <section class="property-section property-info" id="location">
            <h2>Location</h2>
            <p><?= e($property['street_address'] . ', ' . $property['barangay_name'] . ', ' . $place) ?></p>
            <p><?= e(campus_distance((int) $property['distance_to_campus_m'])) ?> from <?= e(DAYON_CAMPUS) ?></p>
            <?php if ($coordinates):
                $lat = (float) $property['latitude'];
                $lng = (float) $property['longitude'];
                $bbox = implode(',', [$lng - .005, $lat - .0035, $lng + .005, $lat + .0035]);
                $embed = 'https://www.openstreetmap.org/export/embed.html?' . http_build_query(['bbox' => $bbox, 'layer' => 'mapnik', 'marker' => $lat . ',' . $lng]);
            ?>
                <iframe class="property-map" loading="lazy" title="Map of <?= e($property['property_name']) ?>" src="<?= e($embed) ?>"></iframe>
            <?php endif; ?>
            <a class="button small secondary" target="_blank" rel="noopener" href="<?= e($mapUrl) ?>"><?= icon('map') ?> Open Map</a>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
