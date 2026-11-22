<div class="listing-heading">
    <p><?= count($listings) ?> verified <?= count($listings) === 1 ? 'listing' : 'listings' ?> in <?= e(DAYON_LOCATION) ?></p>
    <div class="actions">
        <label class="sort-label"><span>Sort by</span>
            <select name="sort" form="finder-form" data-finder-sort>
                <option value="recent"<?= $filters['sort'] === 'recent' ? ' selected' : '' ?>>Recently verified</option>
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
        <h2>No boarding houses match your filters.</h2>
        <a href="<?= e(url('index.php#listings')) ?>" class="button secondary">Clear Filters</a>
    </div>
<?php else: ?>
    <div class="listing-grid">
        <?php foreach ($listings as $listing):
            $id = (int) $listing['property_id'];
            $beds = (int) $listing['beds_available'];
            $detail = 'property.php?id=' . $id;
            $saved = isset($favorites[$id]);
            $amenitiesOnCard = explode('|', $listing['amenities'] ?? '');
            $priorityAmenities = array_intersect(array_keys(SHORT_AMENITIES), $amenitiesOnCard);
            $tags = array_slice($priorityAmenities ?: array_filter($amenitiesOnCard), 0, 2);
        ?>
        <article class="listing-card">
            <div class="listing-image">
                <a class="image-link" href="<?= e(url($detail)) ?>" aria-label="View <?= e($listing['property_name']) ?>">
                    <?php if ($listing['image_path']): ?>
                        <img loading="lazy" src="<?= e(media_url($listing['image_path'])) ?>" alt="">
                    <?php else: ?>
                        <span class="image-placeholder"><?= icon('home') ?></span>
                    <?php endif; ?>
                </a>
                <span class="vacancy-chip<?= availability_class($beds) ?>"><?= e(availability_label($beds, (int) $listing['bed_capacity'])) ?></span>
                <span class="verified-chip"><?= icon('check') ?> Verified</span>
            </div>
            <div class="listing-card-content">
                <div class="listing-title">
                    <h3><a href="<?= e(url($detail)) ?>"><?= e($listing['property_name']) ?></a></h3>
                    <?php if (($user['role_name'] ?? '') === 'student'): ?>
                        <form method="post" action="<?= e(url('api/toggle_bookmark.php')) ?>" data-bookmark>
                            <?= csrf_field() ?>
                            <input type="hidden" name="property_id" value="<?= $id ?>">
                            <button class="bookmark-button<?= $saved ? ' is-saved' : '' ?>" type="submit" aria-label="Bookmark <?= e($listing['property_name']) ?>" aria-pressed="<?= $saved ? 'true' : 'false' ?>"><?= icon('bookmark') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
                <p class="listing-location" title="Approximate distance to <?= e(DAYON_CAMPUS) ?>"><?= e(campus_walk_label((int) $listing['distance_to_campus_m'])) ?> <span class="area-name">· <?= e($listing['barangay_name']) ?></span></p>
                <?php if ($tags): ?><div class="listing-tags">
                    <?php foreach ($tags as $tag): ?><span><?= e(SHORT_AMENITIES[$tag] ?? $tag) ?></span><?php endforeach; ?>
                </div><?php endif; ?>
                <div class="listing-price">
                    <span>From <strong><?= e(display_price($listing['from_rate'])) ?></strong> / bed / mo</span>
                    <a class="button small secondary" href="<?= e(url($detail)) ?>">View Rooms</a>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
