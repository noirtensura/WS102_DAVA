<?php
$activeFilters = $filters;
unset($activeFilters['sort']);
$activeCount = count(array_filter($activeFilters, static fn ($v) => is_array($v) ? $v !== [] : (bool) $v));
?>
<div class="listing-heading">
    <div>
        <h2 id="results-title" tabindex="-1"><?= $filters['property_type'] ? e(property_type_label($filters['property_type'])) . 's' : 'Boarding houses & apartments' ?></h2>
        <p data-finder-count><?= count($listings) ?> verified <?= count($listings) === 1 ? 'listing' : 'listings' ?><?= $activeCount ? ' matching your filters' : '' ?></p>
    </div>
    <div class="actions">
        <?php if ($activeCount && $listings): ?>
            <a class="clear-filters" data-clear-filters href="<?= e(url('index.php#listings')) ?>">Clear filters (<?= $activeCount ?>)</a>
        <?php endif; ?>
        <label class="sort-label"><span>Sort by</span>
            <select name="sort" form="finder-form" data-finder-sort>
                <option value="featured"<?= $filters['sort'] === 'featured' ? ' selected' : '' ?>>Recommended</option>
                <option value="recent"<?= $filters['sort'] === 'recent' ? ' selected' : '' ?>>Newest verified</option>
                <option value="nearest"<?= $filters['sort'] === 'nearest' ? ' selected' : '' ?>>Nearest to campus</option>
                <option value="price"<?= $filters['sort'] === 'price' ? ' selected' : '' ?>>Lowest price</option>
            </select>
        </label>
        <noscript><button type="submit" form="finder-form" class="button small secondary">Sort</button></noscript>
    </div>
</div>
<?php if (!$listings): ?>
    <div class="empty-state">
        <?= icon('search') ?>
        <h2>No boarding houses or apartments match your filters.</h2>
        <p>Try widening your budget, switching property types, or clearing a quick filter.</p>
        <a href="<?= e(url('index.php#listings')) ?>" class="button secondary" data-clear-filters>Reset filters</a>
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
                <p class="listing-location">
                    <?= icon('pin') ?>
                    <span>
                        <?= e($listing['barangay_name']) ?>, <?= e($listing['municipality_name']) ?>
                        <?php if ($listing['distance_to_campus_m'] !== null): ?>
                            <span class="area-name">· <?= e(campus_walk_label((int) $listing['distance_to_campus_m'], $listing['nearest_school'] ?: null)) ?></span>
                        <?php elseif (!empty($listing['nearest_landmark'])): ?>
                            <span class="area-name">· <?= e(mb_strimwidth((string) $listing['nearest_landmark'], 0, 48, '…')) ?></span>
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
