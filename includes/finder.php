<?php
declare(strict_types=1);

const ROOM_TYPES = ['solo' => 'Solo Room', 'shared' => 'Shared Room', 'studio' => 'Studio'];
const GENDER_POLICIES = ['all' => 'All Genders', 'male_only' => 'Male Only', 'female_only' => 'Female Only'];
const PUBLIC_APPROVAL = "p.approval_status = 'approved' AND lp.verification_status = 'verified' AND owner.account_status = 'active'";
const PUBLIC_LOCATION = "m.municipality_name = ? AND m.province_name = ?";
const WALKING_DISTANCE_METERS = 800;
const SHORT_AMENITIES = [
    'Free Wi-Fi' => 'Free Wi-Fi',
    'Shared Kitchen / Cooking Area' => 'Cooking Allowed',
    'Own Comfort Room (CR)' => 'Own CR',
    'Study Desk & Chair' => 'Study Desk',
    'Air Conditioning' => 'A/C',
];

function positive_int(mixed $value, int $max = PHP_INT_MAX): int
{
    return (int) filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
}

function finder_filters(array $input): array
{
    $amenities = is_array($input['amenities'] ?? null) ? $input['amenities'] : [];
    $type = is_string($input['room_type'] ?? null) ? $input['room_type'] : '';
    $gender = is_string($input['gender'] ?? null) ? $input['gender'] : '';
    return [
        'budget' => positive_int($input['budget'] ?? null, 1000000),
        'distance' => positive_int($input['distance'] ?? null, 1000000),
        'room_type' => isset(ROOM_TYPES[$type]) ? $type : '',
        'gender' => isset(GENDER_POLICIES[$gender]) ? $gender : '',
        'barangay' => positive_int($input['barangay'] ?? null),
        'amenities' => array_slice(array_values(array_unique(array_filter(array_map('positive_int', $amenities)))), 0, 30),
        'available' => ($input['available'] ?? null) === '1',
        'walkable' => ($input['walkable'] ?? null) === '1',
        'sort' => in_array($input['sort'] ?? '', ['nearest', 'price', 'recent'], true) ? $input['sort'] : 'recent',
    ];
}

function find_properties(array $filters): array
{
    $roomFilters = ['v.is_active = 1'];
    $roomParams = [];
    $propertyFilters = [PUBLIC_APPROVAL, PUBLIC_LOCATION];
    $propertyParams = [DAYON_MUNICIPALITY, DAYON_PROVINCE];
    if ($filters['budget']) {
        $roomFilters[] = 'v.monthly_rate_per_bed <= ?';
        $roomParams[] = $filters['budget'];
    }
    if ($filters['room_type']) {
        $roomFilters[] = 'v.room_type = ?';
        $roomParams[] = $filters['room_type'];
    }
    if ($filters['available']) {
        $roomFilters[] = 'v.available_beds > 0';
    }
    if ($filters['distance'] || $filters['walkable']) {
        $propertyFilters[] = 'p.distance_to_campus_m <= ?';
        $distance = $filters['distance'];
        $propertyParams[] = $filters['walkable'] ? min($distance ?: WALKING_DISTANCE_METERS, WALKING_DISTANCE_METERS) : $distance;
    }
    if ($filters['gender']) {
        $propertyFilters[] = 'p.gender_policy = ?';
        $propertyParams[] = $filters['gender'];
    }
    if ($filters['barangay']) {
        $propertyFilters[] = 'p.barangay_id = ?';
        $propertyParams[] = $filters['barangay'];
    }
    foreach ($filters['amenities'] as $amenity) {
        $roomFilters[] = '(EXISTS (SELECT 1 FROM room_amenities ra WHERE ra.room_id = v.room_id AND ra.amenity_id = ?)
            OR EXISTS (SELECT 1 FROM property_amenities pa WHERE pa.property_id = v.property_id AND pa.amenity_id = ?))';
        array_push($roomParams, $amenity, $amenity);
    }
    $roomConditions = implode(' AND ', $roomFilters);
    $propertyConditions = implode(' AND ', $propertyFilters);
    $sort = match ($filters['sort']) {
        'price' => 'room_totals.from_rate ASC, p.property_id ASC',
        'nearest' => 'p.distance_to_campus_m ASC, p.property_id ASC',
        default => 'p.approved_at DESC, p.property_id ASC',
    };
    return rows(
        "WITH matching_rooms AS (
             SELECT v.*, ROW_NUMBER() OVER (PARTITION BY v.property_id ORDER BY v.monthly_rate_per_bed, v.room_id) AS price_rank
             FROM vw_room_vacancy v WHERE {$roomConditions}
         ), room_totals AS (
             SELECT property_id, MIN(monthly_rate_per_bed) AS from_rate, SUM(available_beds) AS beds_available,
                    SUM(max_capacity) AS bed_capacity, MAX(CASE WHEN price_rank = 1 THEN room_id END) AS primary_room_id
             FROM matching_rooms GROUP BY property_id
         )
         SELECT p.property_id, p.property_name, p.distance_to_campus_m, p.gender_policy, b.barangay_name,
                room_totals.from_rate, room_totals.beds_available, room_totals.bed_capacity,
                (SELECT image_path FROM property_images WHERE property_id = p.property_id
                 ORDER BY is_primary DESC, image_id ASC LIMIT 1) AS image_path,
                (SELECT GROUP_CONCAT(a.amenity_name ORDER BY a.amenity_id SEPARATOR '|')
                 FROM amenities a
                 WHERE EXISTS (SELECT 1 FROM property_amenities pa WHERE pa.property_id = p.property_id AND pa.amenity_id = a.amenity_id)
                    OR EXISTS (SELECT 1 FROM room_amenities ra WHERE ra.room_id = room_totals.primary_room_id AND ra.amenity_id = a.amenity_id)) AS amenities
         FROM properties p JOIN landlord_profiles lp USING (landlord_id)
         JOIN users owner ON owner.user_id = p.landlord_id
         JOIN barangays b USING (barangay_id) JOIN municipalities m USING (municipality_id)
         JOIN room_totals ON room_totals.property_id = p.property_id
         WHERE {$propertyConditions} ORDER BY {$sort}",
        [...$roomParams, ...$propertyParams]
    );
}

function campus_distance(int $meters): string
{
    return $meters < 1000 ? $meters . ' m' : rtrim(rtrim(number_format($meters / 1000, 2), '0'), '.') . ' km';
}

function campus_walk_label(int $meters): string
{
    return $meters <= 1200
        ? 'About ' . max(1, (int) round($meters / 80)) . ' min walk to campus'
        : campus_distance($meters) . ' to campus';
}

function availability_label(int $available, int $capacity): string
{
    if (!$available) {
        return 'Currently full';
    }
    if ($available === 1 && $capacity > 1) {
        return '1 bed left';
    }
    return $available . ' of ' . $capacity . ' ' . ($capacity === 1 ? 'bed' : 'beds') . ' open';
}

function availability_class(int $available): string
{
    return match ($available) {
        0 => ' is-full',
        1 => ' is-limited',
        default => '',
    };
}

function favorites_for(?array $account): array
{
    if (($account['role_name'] ?? '') !== 'student') {
        return [];
    }
    return array_fill_keys(array_column(rows('SELECT property_id FROM bookmarks WHERE student_id = ?', [$account['user_id']]), 'property_id'), true);
}
