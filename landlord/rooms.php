<?php
declare(strict_types=1);

require_once __DIR__ . '/_support.php';

$landlordId = landlord_page();
$verified = landlord_verified($landlordId);
$properties = landlord_properties($landlordId);
$propertyFilter = (int) filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($propertyFilter && !in_array($propertyFilter, array_map('intval', array_column($properties, 'property_id')), true)) {
    $propertyFilter = 0;
}
$filterSql = $propertyFilter ? ' AND p.property_id = ?' : '';
$params = $propertyFilter ? [$landlordId, $propertyFilter] : [$landlordId];
$boardPath = 'landlord/rooms.php' . ($propertyFilter ? '?property_id=' . $propertyFilter : '');
$boardMore = $propertyFilter ? '&' : '?';
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$rooms = rows('SELECT v.*, p.property_name FROM vw_room_vacancy v JOIN properties p ON p.property_id = v.property_id
    WHERE p.landlord_id = ?' . $filterSql . ' ORDER BY p.property_id, v.floor_number, v.room_number', $params);
$occupants = [];
foreach (rows("SELECT l.lease_id, l.room_id, l.move_in_date, CONCAT(u.first_name, ' ', u.last_name) AS tenant_name
    FROM leases l JOIN users u ON u.user_id = l.student_id JOIN rooms r ON r.room_id = l.room_id
    JOIN properties p ON p.property_id = r.property_id
    WHERE l.lease_status = 'active' AND p.landlord_id = ?" . $filterSql . ' ORDER BY l.move_in_date, l.lease_id', $params) as $occupant) {
    $occupants[$occupant['room_id']][] = $occupant;
}
$pendingSql = "FROM reservations q JOIN rooms r ON r.room_id = q.room_id JOIN properties p ON p.property_id = r.property_id
    JOIN users u ON u.user_id = q.student_id WHERE q.status = 'pending' AND p.landlord_id = ?" . $filterSql;
$pendingCount = (int) scalar('SELECT COUNT(*) ' . $pendingSql, $params);
$pending = rows("SELECT q.*, r.room_number, p.property_name, CONCAT(u.first_name, ' ', u.last_name) AS requester "
    . $pendingSql . ' ORDER BY q.scheduled_at, q.reservation_id LIMIT 6', $params);
$activeRooms = array_filter($rooms, static fn (array $room): bool => (bool) $room['is_active']);
$capacity = (int) array_sum(array_column($activeRooms, 'max_capacity'));
$occupied = (int) array_sum(array_column($activeRooms, 'occupied_beds'));
$available = (int) array_sum(array_column($activeRooms, 'available_beds'));

$editing = $editId ? row('SELECT r.* FROM rooms r JOIN properties p ON p.property_id = r.property_id WHERE r.room_id = ? AND p.landlord_id = ?', [$editId, $landlordId]) : null;
$showForm = $properties && (!$rooms || isset($_GET['new']) || $editing);
$amenities = $showForm ? rows("SELECT * FROM amenities WHERE scope = 'room' ORDER BY amenity_name") : [];
$selected = $editing ? array_column(rows('SELECT amenity_id FROM room_amenities WHERE room_id = ?', [$editId]), 'amenity_id') : [];
$pageTitle = 'Live Occupancy Board';
$pageScripts = ['landlord/forms.js'];
$pageActions = static function () use ($properties, $boardPath, $boardMore, $verified, $available): void {
    ?>
    <?php if ($properties): ?><a class="button secondary" href="<?= e(url($boardPath . $boardMore . 'new=1#room-form')) ?>"><?= icon('plus') ?> New Room</a><?php endif; ?>
    <?php if ($verified && $available): ?><a class="button" href="<?= e(url('landlord/leases.php#lease-form')) ?>"><?= icon('plus') ?> Assign Tenant</a><?php endif; ?>
    <?php
};
require __DIR__ . '/../includes/portal_header.php';
?>

<?php if ($properties): ?>
    <form method="get" class="occupancy-toolbar">
        <label>Property<select name="property_id"><option value="">All properties</option>
            <?php foreach ($properties as $property): ?>
                <option value="<?= (int) $property['property_id'] ?>"<?= $propertyFilter === (int) $property['property_id'] ? ' selected' : '' ?>><?= e($property['property_name']) ?></option>
            <?php endforeach; ?>
        </select></label>
        <button type="submit" class="button secondary">Show Rooms</button>
    </form>
<?php endif; ?>

<div class="metric-grid four">
    <article class="stat-card"><span>Total Beds</span><strong><?= $capacity ?></strong></article>
    <article class="stat-card"><span>Occupied Beds</span><strong><?= $occupied ?></strong></article>
    <article class="stat-card"><span>Available Beds</span><strong class="metric-good"><?= $available ?></strong></article>
    <article class="stat-card"><span>Pending Requests</span><strong<?= $pendingCount ? ' class="metric-watch"' : '' ?>><?= $pendingCount ?></strong></article>
</div>

<div class="occupancy-layout">
    <section class="occupancy-view" aria-label="Rooms and bed assignments">
        <?php if (!$properties): ?>
            <div class="empty-state"><h2>Add your first property</h2><a class="button" href="<?= e(url('landlord/properties.php?new=1#property-form')) ?>">Add Property</a></div>
        <?php elseif (!$rooms): ?>
            <p class="empty-state">No rooms have been added here yet.</p>
        <?php else: ?>
            <div class="room-board">
                <?php foreach ($rooms as $room):
                    $roomId = (int) $room['room_id'];
                    $isActive = (bool) $room['is_active'];
                    $roomOccupants = $occupants[$roomId] ?? [];
                    $openBeds = (int) $room['available_beds'];
                    $shown = array_slice($roomOccupants, 0, 6);
                    $showOpen = $isActive ? min($openBeds, max(0, 6 - count($shown))) : 0;
                    $more = $isActive ? (int) $room['max_capacity'] - count($shown) - $showOpen : 0;
                ?>
                    <article class="panel occupancy-card">
                        <div class="panel-head">
                            <div><h2>Room <?= e($room['room_number']) ?></h2><p class="muted card-meta"><?= e($room['property_name']) ?> · <?= e(ucfirst($room['room_type'])) ?> · Floor <?= (int) $room['floor_number'] ?></p></div>
                            <span class="badge <?= e($room['vacancy_status']) ?>"><?= $isActive ? (int) $room['occupied_beds'] . '/' . (int) $room['max_capacity'] . ' occupied' : 'Inactive' ?></span>
                        </div>
                        <div class="bed-slots">
                            <?php foreach ($shown as $occupant): ?>
                                <div class="bed-slot occupied"><?= icon('bed') ?><div><strong><?= e($occupant['tenant_name']) ?></strong><small>Since <?= e(date('M j', strtotime($occupant['move_in_date']))) ?></small></div></div>
                            <?php endforeach; ?>
                            <?php for ($bed = 0; $bed < $showOpen; $bed++): ?>
                                <?php if ($verified): ?>
                                    <a class="bed-slot open" href="<?= e(url('landlord/leases.php?room=' . $roomId . '#lease-form')) ?>" aria-label="Assign a tenant to Room <?= e($room['room_number']) ?>"><?= icon('bed') ?> Open Bed</a>
                                <?php else: ?>
                                    <div class="bed-slot open"><?= icon('bed') ?> Open Bed</div>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($more): ?><p class="muted">+<?= $more ?> more beds</p><?php endif; ?>
                        </div>
                        <div class="room-panel-bottom">
                            <span><?= e(money($room['monthly_rate_per_bed'])) ?> / bed / mo</span>
                            <a href="<?= e(url($boardPath . $boardMore . 'edit=' . $roomId . '#room-form')) ?>">Edit Room</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="board-requests">
        <div class="panel-head"><h2 id="board-requests">Pending Requests</h2></div>
        <?php if (!$pending): ?><p class="muted">No requests need a decision for the selected properties.</p><?php endif; ?>
        <div class="pending-stack">
            <?php foreach ($pending as $request):
                $isHold = $request['request_type'] === 'bed_reservation';
                $expired = $isHold ? substr($request['scheduled_at'], 0, 10) < date('Y-m-d') : $request['scheduled_at'] < date('Y-m-d H:i:s');
            ?>
                <article class="pending-card">
                    <h3><?= $isHold ? 'Bed Hold' : 'Scheduled Visit' ?> · Room <?= e($request['room_number']) ?></h3>
                    <p><?= e($request['requester']) ?> · <?= e($request['property_name']) ?></p>
                    <p class="muted"><?= e(date($isHold ? 'M j, Y' : 'M j, Y · g:i A', strtotime($request['scheduled_at']))) ?><?= $expired ? ' · Date passed' : '' ?></p>
                    <?php if ($request['notes']): ?><p class="preserve-lines"><?= e($request['notes']) ?></p><?php endif; ?>
                    <?php if ($verified): ?>
                        <form method="post" action="<?= e(url('actions/landlord_reservation.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="reservation_id" value="<?= (int) $request['reservation_id'] ?>">
                            <input type="hidden" name="return_to" value="rooms">
                            <input type="hidden" name="property_filter" value="<?= $propertyFilter ?>">
                            <button class="button small approve" name="decision" value="approved"<?= $expired ? ' disabled' : '' ?>>Approve</button>
                            <button class="button small reject" name="decision" value="declined">Decline</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($pendingCount): ?>
            <p class="panel-actions-bottom"><a href="<?= e(url('landlord/reservations.php')) ?>">View All Requests <?= icon('arrow') ?></a></p>
        <?php endif; ?>
    </section>
</div>
<?php if ($showForm): ?>
<section class="panel" id="room-form">
    <h2><?= $editing ? 'Edit room ' . e($editing['room_number']) : 'Add a room' ?></h2>
    <form class="form-grid" method="post" action="<?= e(url('actions/room_save.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="operation" value="save"><input type="hidden" name="room_id" value="<?= e($editing['room_id'] ?? '') ?>">
        <input type="hidden" name="property_filter" value="<?= $propertyFilter ?>">
        <label class="form-field">Property<select name="property_id" required <?= $editing ? 'disabled' : '' ?>><option value="">Choose property</option>
            <?php foreach ($properties as $property): ?><option value="<?= e($property['property_id']) ?>" <?= ($editing['property_id'] ?? $propertyFilter) == $property['property_id'] ? 'selected' : '' ?>><?= e($property['property_name']) ?></option><?php endforeach; ?>
        </select></label>
        <label class="form-field">Room number or label<input name="room_number" maxlength="30" required value="<?= e($editing['room_number'] ?? '') ?>"></label>
        <label class="form-field">Floor<input type="number" name="floor_number" min="0" max="255" required value="<?= e($editing['floor_number'] ?? 1) ?>"></label>
        <label class="form-field">Room type<select name="room_type"><?php foreach (['solo', 'shared', 'studio'] as $type): ?><option value="<?= e($type) ?>" <?= ($editing['room_type'] ?? 'shared') === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select></label>
        <label class="form-field">Bed capacity<input type="number" name="max_capacity" min="1" max="255" required value="<?= e($editing['max_capacity'] ?? 1) ?>"></label>
        <label class="form-field">Monthly rate per bed (₱)<input type="number" name="monthly_rate_per_bed" min="0.01" step="0.01" max="99999999.99" required value="<?= e($editing['monthly_rate_per_bed'] ?? '') ?>"></label>
        <label class="form-field">Room status<select name="is_active"><option value="1">Active</option><option value="0" <?= isset($editing['is_active']) && !$editing['is_active'] ? 'selected' : '' ?>>Inactive</option></select></label>
        <fieldset class="span-2"><legend>Room amenities</legend><div class="actions">
            <?php foreach ($amenities as $amenity): ?><label><input name="amenities[]" type="checkbox" value="<?= e($amenity['amenity_id']) ?>" <?= in_array($amenity['amenity_id'], $selected) ? 'checked' : '' ?>> <?= e($amenity['amenity_name']) ?></label><?php endforeach; ?>
        </div></fieldset>
        <div class="actions span-2"><button type="submit" class="button">Save room</button></div>
    </form>
</section>
<?php if ($editing): ?>
    <section class="panel">
        <h2>Remove room</h2><p class="muted">Rooms with reservation or lease history can be set to inactive once vacant.</p>
        <form method="post" action="<?= e(url('actions/room_save.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="room_id" value="<?= e($editId) ?>">
            <input type="hidden" name="property_filter" value="<?= $propertyFilter ?>">
            <button type="submit" class="button small danger" name="operation" value="delete" data-confirm="Remove this room?">Remove room</button>
        </form>
    </section>
<?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/portal_footer.php'; ?>
