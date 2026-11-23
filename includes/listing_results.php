<?php
$activeSort = $searchMeta['sort'] ?? ($targetLocation ? 'nearest' : 'featured');
$expanded = !empty($searchMeta['expanded']);
$effectiveRadius = (int) ($searchMeta['radius_m'] ?? 0);

$refineActive = [
    $filters['q'],
    $filters['property_type'],
    $filters['budget'],
    $filters['room_type'],
    $filters['gender'],
    $filters['available'],
    $filters['walkable'],
    $filters['no_curfew'],
    $filters['with_permits'],
    $filters['amenities'],
];
$refineCount = count(array_filter($refineActive, static fn ($v) => is_array($v) ? $v !== [] : (bool) $v));
?>

<?php if ($expanded && $targetLocation): ?>
    <div class="flash flash-info nearest-fallback-notice" role="status">
        <strong>No listings inside <?= e(campus_distance($effectiveRadius)) ?> of <?= e($targetLocation['label']) ?> yet.</strong>
        Showing the nearest available boarding houses &amp; apartments across Antique sorted closest first.
    </div>
<?php endif; ?>

<div class="listing-heading">
    <div>
        <h2 id="results-title" tabindex="-1">
            <?php if ($targetLocation): ?>
                Nearest <?= $filters['property_type'] ? e(strtolower(property_type_label($filters['property_type']))) . 's' : 'boarding houses & apartments' ?>
                to <?= e($targetLocation['short_label']) ?>
            <?php else: ?>
                <?= $filters['property_type'] ? e(property_type_label($filters['property_type'])) . 's' : 'All boarding houses & apartments in Antique' ?>
            <?php endif; ?>
        </h2>
        <p data-finder-count>
            <?= count($listings) ?> verified <?= count($listings) === 1 ? 'listing' : 'listings' ?>
            <?php if ($targetLocation && !$expanded && $effectiveRadius > 0): ?>
                within <?= e(campus_distance($effectiveRadius)) ?>
            <?php endif; ?>
            <?= $refineCount ? ' · ' . $refineCount . ' ' . ($refineCount === 1 ? 'filter' : 'filters') . ' active' : '' ?>
        </p>
    </div>
    <div class="actions">
        <?php if ($refineCount): ?>
            <a class="clear-filters" data-clear-refine href="<?= e(url('index.php?' . http_build_query(array_filter([
                'place' => $filters['place'],
                'municipality' => $filters['municipality'] ?: null,
                'barangay' => $filters['barangay'] ?: null,
                'lat' => $filters['lat'],
                'lng' => $filters['lng'],
                'radius' => $filters['radius'],
                'browse' => $filters['browse'],
            ], static fn ($v) => $v !== null && $v !== '')) . '#listings')) ?>">Reset filters</a>
        <?php endif; ?>
        <label class="sort-label"><span>Sort by</span>
            <select name="sort" form="finder-form" data-finder-sort>
                <?php if ($targetLocation): ?>
                    <option value="nearest"<?= $activeSort === 'nearest' ? ' selected' : '' ?>>Nearest first</option>
                <?php endif; ?>
                <option value="featured"<?= $activeSort === 'featured' ? ' selected' : '' ?>>Recommended</option>
                <option value="price"<?= $activeSort === 'price' ? ' selected' : '' ?>>Lowest price</option>
                <?php if (!$targetLocation): ?>
                    <option value="nearest"<?= $activeSort === 'nearest' ? ' selected' : '' ?>>Nearest to campus</option>
                <?php endif; ?>
                <option value="recent"<?= $activeSort === 'recent' ? ' selected' : '' ?>>Newest verified</option>
            </select>
        </label>
        <noscript><button type="submit" form="finder-form" class="button small secondary">Sort</button></noscript>
    </div>
</div>

<?php if (!$listings): ?>
    <div class="empty-state">
        <?= icon('search') ?>
        <h2>No boarding houses or apartments match these filters.</h2>
        <p>Try widening the distance radius, switching property types, or clearing your filters.</p>
        <div class="actions" style="justify-content:center; margin-top:12px;">
            <a href="<?= e(url('index.php?browse=all#listings')) ?>" class="button secondary" data-clear-filters>Show all in Antique</a>
        </div>
    </div>
<?php else: ?>
    <div class="listing-grid">
        <?php foreach ($listings as $position => $listing):
            $id = (int) $listing['property_id'];
            $beds = (int) $listing['beds_available'];
            $capacity = (int) $listing['bed_capacity'];
            $detail = 'property.php?id=' . $id;
            $saved = isset($favorites[$id]);
            $amenitiesOnCard = array_filter(explode('|', (string) ($listing['amenities'] ?? '')));
            $priorityAmenities = array_intersect(array_keys(SHORT_AMENITIES), $amenitiesOnCard);
            $tags = [];
            foreach (array_slice($priorityAmenities ?: $amenitiesOnCard, 0, 2) as $tag) {
                $tags[] = SHORT_AMENITIES[$tag] ?? $tag;
            }
            if (!empty($listing['no_curfew']) && count($tags) < 3) {
                $tags[] = 'No Curfew';
            } elseif (($listing['water_billing'] ?? '') === 'included' && count($tags) < 3) {
                $tags[] = 'Water Included';
            } elseif (($listing['electricity_billing'] ?? '') === 'own_meter' && count($tags) < 3) {
                $tags[] = 'Own Meter';
            }
            $permitCount = (int) ($listing['verified_permit_count'] ?? 0);
            $photoCount = (int) ($listing['photo_count'] ?? 0);
            $distFromTarget = $listing['distance_from_target_m'] !== null ? (int) $listing['distance_from_target_m'] : null;
        ?>
        <article class="listing-card<?= !empty($listing['is_featured']) ? ' is-featured' : '' ?>">
            <div class="listing-image">
                <a class="image-link" href="<?= e(url($detail)) ?>" aria-label="View <?= e($listing['property_name']) ?>">
                    <?php if ($listing['image_path']): ?>
                        <img<?= $position >= 3 ? ' loading="lazy"' : '' ?> decoding="async" src="<?= e(media_url($listing['image_path'])) ?>" alt="<?= e($listing['property_name']) ?>">
                    <?php else: ?>
                        <span class="image-placeholder"><?= icon('building') ?></span>
                    <?php endif; ?>
                </a>
                <div class="card-badges-top">
                    <span class="vacancy-chip<?= availability_class($beds, $capacity) ?>"><?= e(availability_label($beds, $capacity)) ?></span>
                    <?php if (($user['role_name'] ?? '') === 'student'): ?>
                        <form method="post" action="<?= e(url('api/toggle_bookmark.php')) ?>" data-bookmark class="card-bookmark-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="property_id" value="<?= $id ?>">
                            <button class="bookmark-fab<?= $saved ? ' is-saved' : '' ?>" type="submit" aria-label="<?= $saved ? 'Remove bookmark for ' : 'Bookmark ' ?><?= e($listing['property_name']) ?>" aria-pressed="<?= $saved ? 'true' : 'false' ?>" title="<?= $saved ? 'Bookmarked' : 'Bookmark this house' ?>">
                                <?= icon('bookmark') ?>
                            </button>
                        </form>
                    <?php elseif (!$user): ?>
                        <a class="bookmark-fab" href="<?= e(url('login.php?next=' . rawurlencode($detail))) ?>" aria-label="Sign in as student to bookmark <?= e($listing['property_name']) ?>" title="Sign in as student to bookmark">
                            <?= icon('bookmark') ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-badges-bottom">
                    <span class="type-chip"><?= e(property_type_label($listing['property_type'] ?? 'boarding_house')) ?></span>
                    <?php if (!empty($listing['is_featured'])): ?>
                        <span class="featured-chip"><?= icon('star') ?> Featured</span>
                    <?php endif; ?>
                    <?php if ($photoCount > 1): ?>
                        <span class="photo-count-chip"><?= icon('image') ?> <?= $photoCount ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="listing-card-content">
                <div class="listing-title">
                    <h3><a href="<?= e(url($detail)) ?>"><?= e($listing['property_name']) ?></a></h3>
                </div>
                <?php if ($distFromTarget !== null && $targetLocation): ?>
                    <div class="nearest-distance-pill">
                        <?= icon('pin') ?>
                        <strong><?= e(campus_distance($distFromTarget)) ?></strong>
                        <span>from <?= e($targetLocation['short_label']) ?> · <?= e(walk_or_ride_label($distFromTarget)) ?></span>
                    </div>
                <?php endif; ?>
                <p class="listing-location">
                    <?= icon('map') ?>
                    <span>
                        <?= e($listing['barangay_name']) ?>, <?= e($listing['municipality_name']) ?>
                        <?php if ($distFromTarget === null && $listing['distance_to_campus_m'] !== null): ?>
                            <span class="area-name">· <?= e(campus_walk_label((int) $listing['distance_to_campus_m'], $listing['nearest_school'] ?: null)) ?></span>
                        <?php elseif (!empty($listing['nearest_landmark'])): ?>
                            <span class="area-name">· <?= e(mb_strimwidth((string) $listing['nearest_landmark'], 0, 44, '…')) ?></span>
                        <?php endif; ?>
                    </span>
                </p>
                <?php if ($tags || $permitCount > 0): ?>
                    <div class="listing-tags">
                        <?php foreach ($tags as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?>
                        <?php if ($permitCount > 0): ?>
                            <span class="permit-mini-tag" title="Verified optional Antique permit(s) on file"><?= icon('shield') ?> <?= $permitCount ?> <?= $permitCount === 1 ? 'Permit' : 'Permits' ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="listing-price">
                    <?php if ($listing['from_rate'] !== null): ?>
                        <span>From <strong><?= e(display_price($listing['from_rate'])) ?></strong> <small class="muted">/ mo</small></span>
                    <?php else: ?>
                        <span class="muted"><strong>Inquire</strong> for rates</span>
                    <?php endif; ?>
                    <a class="button small secondary" href="<?= e(url($detail)) ?>">View Details</a>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
