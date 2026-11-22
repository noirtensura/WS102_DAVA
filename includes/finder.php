<?php
declare(strict_types=1);

const ROOM_TYPES = ['solo' => 'Solo Room', 'shared' => 'Shared Room', 'studio' => 'Studio'];
const GENDER_POLICIES = ['all' => 'All Genders', 'male_only' => 'Male Only', 'female_only' => 'Female Only'];
const PUBLIC_APPROVAL = "p.approval_status = 'approved' AND lp.verification_status = 'verified' AND owner.account_status = 'active'";
const PUBLIC_LOCATION = "m.province_name = ?";
const WALKING_DISTANCE_METERS = 800;
const SHORT_AMENITIES = [
    'Free Wi-Fi' => 'Free Wi-Fi',
    'Shared Kitchen / Cooking Area' => 'Cooking Allowed',
    'Own Comfort Room (CR)' => 'Own CR',
    'Study Desk & Chair' => 'Study Desk',
    'Air Conditioning' => 'A/C',
    'CCTV Security' => 'CCTV',
];

function positive_int(mixed $value, int $max = PHP_INT_MAX): int
{
    return (int) filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
}

function finder_filters(array $input): array
{
    $amenities = is_array($input['amenities'] ?? null) ? $input['amenities'] : [];
    $type = is_string($input['room_type'] ?? null) ? $input['room_type'] : '';
    $propertyType = is_string($input['property_type'] ?? null) ? $input['property_type'] : '';
    $gender = is_string($input['gender'] ?? null) ? $input['gender'] : '';
    $q = is_string($input['q'] ?? null) ? mb_substr(trim($input['q']), 0, 100) : '';
    return [
        'q' => $q,
        'property_type' => isset(PROPERTY_TYPES[$propertyType]) ? $propertyType : '',
        'municipality' => positive_int($input['municipality'] ?? null),
        'barangay' => positive_int($input['barangay'] ?? null),
        'budget' => positive_int($input['budget'] ?? null, 1000000),
        'distance' => positive_int($input['distance'] ?? null, 1000000),
        'room_type' => isset(ROOM_TYPES[$type]) ? $type : '',
        'gender' => isset(GENDER_POLICIES[$gender]) ? $gender : '',
        'amenities' => array_slice(array_values(array_unique(array_filter(array_map('positive_int', $amenities)))), 0, 30),
        'available' => ($input['available'] ?? null) === '1',
        'walkable' => ($input['walkable'] ?? null) === '1',
        'no_curfew' => ($input['no_curfew'] ?? null) === '1',
        'with_permits' => ($input['with_permits'] ?? null) === '1',
        'sort' => in_array($input['sort'] ?? '', ['featured', 'nearest', 'price', 'recent'], true) ? $input['sort'] : 'featured',
    ];
}

function find_properties(array $filters): array
{
    $roomFilters = ['v.is_active = 1'];
    $roomParams = [];
    $requireMatchingRooms = false;
    $propertyFilters = [PUBLIC_APPROVAL, PUBLIC_LOCATION];
    $propertyParams = [DAYON_PROVINCE];

    if ($filters['q'] !== '') {
        $like = '%' . $filters['q'] . '%';
        $propertyFilters[] = '(p.property_name LIKE ? OR b.barangay_name LIKE ? OR m.municipality_name LIKE ? OR COALESCE(p.street_address, \'\') LIKE ? OR COALESCE(p.nearest_landmark, \'\') LIKE ? OR COALESCE(p.nearest_school, \'\') LIKE ? OR COALESCE(p.description, \'\') LIKE ?)';
        array_push($propertyParams, $like, $like, $like, $like, $like, $like, $like);
    }
    if ($filters['property_type']) {
        $propertyFilters[] = 'p.property_type = ?';
        $propertyParams[] = $filters['property_type'];
    }
    if ($filters['municipality']) {
        $propertyFilters[] = 'm.municipality_id = ?';
        $propertyParams[] = $filters['municipality'];
    }
    if ($filters['barangay']) {
        $propertyFilters[] = 'p.barangay_id = ?';
        $propertyParams[] = $filters['barangay'];
    }
    if ($filters['budget']) {
        $roomFilters[] = 'v.monthly_rate_per_bed <= ?';
        $roomParams[] = $filters['budget'];
        $requireMatchingRooms = true;
    }
    if ($filters['room_type']) {
        $roomFilters[] = 'v.room_type = ?';
        $roomParams[] = $filters['room_type'];
        $requireMatchingRooms = true;
    }
    if ($filters['available']) {
        $roomFilters[] = 'v.available_beds > 0';
        $requireMatchingRooms = true;
    }
    if ($filters['distance'] || $filters['walkable']) {
        $propertyFilters[] = 'p.distance_to_campus_m IS NOT NULL AND p.distance_to_campus_m <= ?';
        $distance = $filters['distance'];
        $propertyParams[] = $filters['walkable'] ? min($distance ?: WALKING_DISTANCE_METERS, WALKING_DISTANCE_METERS) : $distance;
    }
    if ($filters['gender']) {
        $propertyFilters[] = 'p.gender_policy = ?';
        $propertyParams[] = $filters['gender'];
    }
    if ($filters['no_curfew']) {
        $propertyFilters[] = 'p.no_curfew = 1';
    }
    if ($filters['with_permits']) {
        $propertyFilters[] = "EXISTS (SELECT 1 FROM landlord_permits pm WHERE pm.landlord_id = p.landlord_id AND (pm.property_id = p.property_id OR pm.property_id IS NULL) AND pm.verification_status = 'verified' AND pm.permit_type <> 'government_id')";
    }
    foreach ($filters['amenities'] as $amenity) {
        $propertyFilters[] = '(EXISTS (SELECT 1 FROM property_amenities pa WHERE pa.property_id = p.property_id AND pa.amenity_id = ?)
            OR EXISTS (SELECT 1 FROM rooms r JOIN room_amenities ra USING (room_id) WHERE r.property_id = p.property_id AND r.is_active = 1 AND ra.amenity_id = ?))';
        array_push($propertyParams, $amenity, $amenity);
    }

    $roomConditions = implode(' AND ', $roomFilters);
    $propertyConditions = implode(' AND ', $propertyFilters);
    $roomJoin = $requireMatchingRooms ? 'JOIN room_totals' : 'LEFT JOIN room_totals';
    $sort = match ($filters['sort']) {
        'price' => 'room_totals.from_rate IS NULL ASC, room_totals.from_rate ASC, p.property_id ASC',
        'nearest' => 'p.distance_to_campus_m IS NULL ASC, p.distance_to_campus_m ASC, p.property_id ASC',
        'recent' => 'p.approved_at DESC, p.property_id DESC',
        default => 'p.is_featured DESC, p.approved_at DESC, p.property_id ASC',
    };

    return rows(
        "WITH matching_rooms AS (
             SELECT v.*, ROW_NUMBER() OVER (PARTITION BY v.property_id ORDER BY v.monthly_rate_per_bed, v.room_id) AS price_rank
             FROM vw_room_vacancy v WHERE {$roomConditions}
         ), room_totals AS (
             SELECT property_id, MIN(monthly_rate_per_bed) AS from_rate, SUM(available_beds) AS beds_available,
                    SUM(max_capacity) AS bed_capacity, COUNT(*) AS room_count,
                    MAX(CASE WHEN price_rank = 1 THEN room_id END) AS primary_room_id
             FROM matching_rooms GROUP BY property_id
         )
         SELECT p.property_id, p.property_name, p.property_type, p.distance_to_campus_m, p.gender_policy,
                p.nearest_school, p.nearest_landmark, p.water_billing, p.electricity_billing, p.wifi_policy,
                p.no_curfew, p.is_featured, b.barangay_name, m.municipality_name,
                room_totals.from_rate, COALESCE(room_totals.beds_available, 0) AS beds_available,
                COALESCE(room_totals.bed_capacity, 0) AS bed_capacity,
                COALESCE(room_totals.room_count, 0) AS room_count,
                (SELECT image_path FROM property_images WHERE property_id = p.property_id
                 ORDER BY is_primary DESC, image_id ASC LIMIT 1) AS image_path,
                (SELECT COUNT(*) FROM property_images WHERE property_id = p.property_id) AS photo_count,
                (SELECT COUNT(*) FROM landlord_permits pm
                 WHERE pm.landlord_id = p.landlord_id
                   AND (pm.property_id = p.property_id OR pm.property_id IS NULL)
                   AND pm.verification_status = 'verified'
                   AND pm.permit_type <> 'government_id') AS verified_permit_count,
                (SELECT GROUP_CONCAT(a.amenity_name ORDER BY a.amenity_id SEPARATOR '|')
                 FROM amenities a
                 WHERE EXISTS (SELECT 1 FROM property_amenities pa WHERE pa.property_id = p.property_id AND pa.amenity_id = a.amenity_id)
                    OR (room_totals.primary_room_id IS NOT NULL AND EXISTS (SELECT 1 FROM room_amenities ra WHERE ra.room_id = room_totals.primary_room_id AND ra.amenity_id = a.amenity_id))) AS amenities
         FROM properties p JOIN landlord_profiles lp USING (landlord_id)
         JOIN users owner ON owner.user_id = p.landlord_id
         JOIN barangays b USING (barangay_id) JOIN municipalities m USING (municipality_id)
         {$roomJoin} ON room_totals.property_id = p.property_id
         WHERE {$propertyConditions} ORDER BY {$sort}",
        [...$roomParams, ...$propertyParams]
    );
}

function campus_distance(int $meters): string
{
    return $meters < 1000 ? $meters . ' m' : rtrim(rtrim(number_format($meters / 1000, 2), '0'), '.') . ' km';
}

function campus_walk_label(int $meters, ?string $school = null): string
{
    $target = $school ? $school : 'campus';
    return $meters <= 1200
        ? 'About ' . max(1, (int) round($meters / 80)) . ' min walk to ' . $target
        : campus_distance($meters) . ' from ' . $target;
}

function availability_label(int $available, int $capacity): string
{
    if ($capacity === 0) {
        return 'Inquire for rooms';
    }
    if (!$available) {
        return 'Currently full';
    }
    if ($available === 1 && $capacity > 1) {
        return '1 bed left';
    }
    return $available . ' of ' . $capacity . ' ' . ($capacity === 1 ? 'bed' : 'beds') . ' open';
}

function availability_class(int $available, int $capacity = 1): string
{
    if ($capacity === 0) {
        return ' is-inquiry';
    }
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
