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

const POPULAR_STAY_PLACES = [
    'sac' => [
        'name' => "St. Anthony's College",
        'area' => 'San Jose de Buenavista',
        'lat' => 10.7453,
        'lng' => 121.9410,
        'tag' => 'Campus',
    ],
    'ua_sibalom' => [
        'name' => 'University of Antique – Main',
        'area' => 'Sibalom',
        'lat' => 10.7885,
        'lng' => 122.0169,
        'tag' => 'Campus',
    ],
    'ua_hamtic' => [
        'name' => 'University of Antique – Hamtic',
        'area' => 'Hamtic',
        'lat' => 10.7029,
        'lng' => 121.9818,
        'tag' => 'Campus',
    ],
    'san_jose_proper' => [
        'name' => 'San Jose Proper / Robinsons',
        'area' => 'San Jose de Buenavista',
        'lat' => 10.7495,
        'lng' => 121.9462,
        'tag' => 'Town Center',
    ],
    'acc' => [
        'name' => 'Advance Central College',
        'area' => 'San Jose de Buenavista',
        'lat' => 10.7426,
        'lng' => 121.9441,
        'tag' => 'Campus',
    ],
    'ua_tibiao' => [
        'name' => 'UA Tario-Lim Campus',
        'area' => 'Tibiao',
        'lat' => 11.2921,
        'lng' => 122.0378,
        'tag' => 'Campus',
    ],
];

const SEARCH_RADIUS_OPTIONS = [
    1000 => 'Within 1 km',
    2000 => 'Within 2 km',
    5000 => 'Within 5 km',
    10000 => 'Within 10 km',
    25000 => 'Within 25 km',
    0 => 'All of Antique (nearest first)',
];

function positive_int(mixed $value, int $max = PHP_INT_MAX): int
{
    return (int) filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
}

function valid_coord(mixed $value, float $limit): ?float
{
    if (!is_string($value) && !is_numeric($value)) {
        return null;
    }
    $str = trim((string) $value);
    if ($str === '' || !is_numeric($str)) {
        return null;
    }
    $num = (float) $str;
    return abs($num) <= $limit ? $num : null;
}

function finder_filters(array $input): array
{
    $amenities = is_array($input['amenities'] ?? null) ? $input['amenities'] : [];
    $type = is_string($input['room_type'] ?? null) ? $input['room_type'] : '';
    $propertyType = is_string($input['property_type'] ?? null) ? $input['property_type'] : '';
    $gender = is_string($input['gender'] ?? null) ? $input['gender'] : '';
    $place = is_string($input['place'] ?? null) ? trim($input['place']) : '';
    $q = is_string($input['q'] ?? null) ? mb_substr(trim($input['q']), 0, 100) : '';
    $lat = valid_coord($input['lat'] ?? null, 90.0);
    $lng = valid_coord($input['lng'] ?? null, 180.0);
    if ($lat === null || $lng === null) {
        $lat = null;
        $lng = null;
    }
    $radiusRaw = $input['radius'] ?? null;
    $radius = null;
    if ($radiusRaw !== null && $radiusRaw !== '') {
        $parsed = filter_var($radiusRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 200000]]);
        if ($parsed !== false && isset(SEARCH_RADIUS_OPTIONS[$parsed])) {
            $radius = $parsed;
        }
    }
    $sortRaw = is_string($input['sort'] ?? null) ? $input['sort'] : '';
    $sort = in_array($sortRaw, ['nearest', 'featured', 'price', 'recent'], true) ? $sortRaw : '';

    return [
        'place' => isset(POPULAR_STAY_PLACES[$place]) ? $place : '',
        'lat' => $lat,
        'lng' => $lng,
        'radius' => $radius,
        'browse' => ($input['browse'] ?? null) === 'all' ? 'all' : '',
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
        'sort' => $sort,
    ];
}

function resolve_target_location(array $filters): ?array
{
    if ($filters['lat'] !== null && $filters['lng'] !== null) {
        return [
            'mode' => 'gps',
            'label' => 'Your current location',
            'short_label' => 'your location',
            'lat' => $filters['lat'],
            'lng' => $filters['lng'],
            'default_radius' => 5000,
        ];
    }
    if ($filters['place'] !== '' && isset(POPULAR_STAY_PLACES[$filters['place']])) {
        $p = POPULAR_STAY_PLACES[$filters['place']];
        return [
            'mode' => 'place',
            'key' => $filters['place'],
            'label' => $p['name'] . ', ' . $p['area'],
            'short_label' => $p['name'],
            'lat' => (float) $p['lat'],
            'lng' => (float) $p['lng'],
            'default_radius' => 5000,
        ];
    }
    if ($filters['barangay']) {
        $b = row(
            'SELECT b.barangay_id, b.barangay_name, m.municipality_id, m.municipality_name,
                    COALESCE(b.latitude, m.latitude) AS lat, COALESCE(b.longitude, m.longitude) AS lng
             FROM barangays b JOIN municipalities m USING (municipality_id)
             WHERE b.barangay_id = ? AND m.province_name = ?',
            [$filters['barangay'], DAYON_PROVINCE]
        );
        if ($b) {
            return [
                'mode' => 'barangay',
                'barangay_id' => (int) $b['barangay_id'],
                'municipality_id' => (int) $b['municipality_id'],
                'label' => $b['barangay_name'] . ', ' . $b['municipality_name'],
                'short_label' => $b['barangay_name'],
                'lat' => $b['lat'] !== null ? (float) $b['lat'] : null,
                'lng' => $b['lng'] !== null ? (float) $b['lng'] : null,
                'default_radius' => 5000,
            ];
        }
    }
    if ($filters['municipality']) {
        $m = row(
            'SELECT municipality_id, municipality_name, latitude AS lat, longitude AS lng
             FROM municipalities WHERE municipality_id = ? AND province_name = ?',
            [$filters['municipality'], DAYON_PROVINCE]
        );
        if ($m) {
            return [
                'mode' => 'municipality',
                'municipality_id' => (int) $m['municipality_id'],
                'label' => $m['municipality_name'] . ', Antique',
                'short_label' => $m['municipality_name'],
                'lat' => $m['lat'] !== null ? (float) $m['lat'] : null,
                'lng' => $m['lng'] !== null ? (float) $m['lng'] : null,
                'default_radius' => 10000,
            ];
        }
    }
    return null;
}

function has_location_selection(array $filters, ?array $target): bool
{
    if ($target !== null || $filters['browse'] === 'all') {
        return true;
    }
    foreach (['q', 'property_type', 'budget', 'distance', 'room_type', 'gender', 'available', 'walkable', 'no_curfew', 'with_permits'] as $key) {
        if (!empty($filters[$key])) {
            return true;
        }
    }
    return !empty($filters['amenities']);
}

function find_nearest_properties(array $filters, ?array $target = null): array
{
    $roomFilters = ['v.is_active = 1'];
    $roomParams = [];
    $requireMatchingRooms = false;
    $propertyFilters = [PUBLIC_APPROVAL, PUBLIC_LOCATION];
    $propertyParams = [DAYON_PROVINCE];

    if ($filters['q'] !== '') {
        $like = '%' . $filters['q'] . '%';
        $propertyFilters[] = "(p.property_name LIKE ? OR b.barangay_name LIKE ? OR m.municipality_name LIKE ? OR COALESCE(p.street_address, '') LIKE ? OR COALESCE(p.nearest_landmark, '') LIKE ? OR COALESCE(p.nearest_school, '') LIKE ? OR COALESCE(p.description, '') LIKE ?)";
        array_push($propertyParams, $like, $like, $like, $like, $like, $like, $like);
    }
    if ($filters['property_type']) {
        $propertyFilters[] = 'p.property_type = ?';
        $propertyParams[] = $filters['property_type'];
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

    $hasTargetCoords = $target !== null && $target['lat'] !== null && $target['lng'] !== null;
    $effectiveRadius = $hasTargetCoords
        ? ($filters['radius'] !== null ? $filters['radius'] : (int) $target['default_radius'])
        : 0;

    if ($filters['walkable']) {
        if ($hasTargetCoords) {
            $effectiveRadius = $effectiveRadius > 0 ? min($effectiveRadius, WALKING_DISTANCE_METERS) : WALKING_DISTANCE_METERS;
        } else {
            $propertyFilters[] = 'p.distance_to_campus_m IS NOT NULL AND p.distance_to_campus_m <= ?';
            $propertyParams[] = WALKING_DISTANCE_METERS;
        }
    } elseif ($filters['distance'] && !$hasTargetCoords) {
        $propertyFilters[] = 'p.distance_to_campus_m IS NOT NULL AND p.distance_to_campus_m <= ?';
        $propertyParams[] = $filters['distance'];
    }

    // Distance expression using Haversine (meters) against effective property coords (property -> barangay -> municipality)
    $selectDistance = 'NULL AS distance_from_target_m';
    $distanceSelectParams = [];
    if ($hasTargetCoords) {
        $lat = $target['lat'];
        $lng = $target['lng'];
        $effLat = 'COALESCE(p.latitude, b.latitude, m.latitude)';
        $effLng = 'COALESCE(p.longitude, b.longitude, m.longitude)';
        $selectDistance = "CASE WHEN {$effLat} IS NOT NULL AND {$effLng} IS NOT NULL THEN ROUND(6371000 * 2 * ASIN(LEAST(1.0, SQRT(POWER(SIN(RADIANS({$effLat} - ?) / 2), 2) + COS(RADIANS(?)) * COS(RADIANS({$effLat})) * POWER(SIN(RADIANS({$effLng} - ?) / 2), 2))))) ELSE NULL END AS distance_from_target_m";
        $distanceSelectParams = [$lat, $lat, $lng];
    }

    $activeSort = $filters['sort'] !== '' ? $filters['sort'] : ($hasTargetCoords ? 'nearest' : 'featured');
    $orderSql = match ($activeSort) {
        'price' => 'from_rate IS NULL ASC, from_rate ASC, distance_from_target_m IS NULL ASC, distance_from_target_m ASC, property_id ASC',
        'nearest' => $hasTargetCoords
            ? 'distance_from_target_m IS NULL ASC, distance_from_target_m ASC, is_featured DESC, property_id ASC'
            : 'distance_to_campus_m IS NULL ASC, distance_to_campus_m ASC, property_id ASC',
        'recent' => 'approved_at DESC, property_id DESC',
        default => 'is_featured DESC, distance_from_target_m IS NULL ASC, distance_from_target_m ASC, approved_at DESC, property_id ASC',
    };

    $roomConditions = implode(' AND ', $roomFilters);
    $propertyConditions = implode(' AND ', $propertyFilters);
    $roomJoin = $requireMatchingRooms ? 'JOIN room_totals' : 'LEFT JOIN room_totals';

    $baseSql = "WITH matching_rooms AS (
             SELECT v.*, ROW_NUMBER() OVER (PARTITION BY v.property_id ORDER BY v.monthly_rate_per_bed, v.room_id) AS price_rank
             FROM vw_room_vacancy v WHERE {$roomConditions}
         ), room_totals AS (
             SELECT property_id, MIN(monthly_rate_per_bed) AS from_rate, SUM(available_beds) AS beds_available,
                    SUM(max_capacity) AS bed_capacity, COUNT(*) AS room_count,
                    MAX(CASE WHEN price_rank = 1 THEN room_id END) AS primary_room_id
             FROM matching_rooms GROUP BY property_id
         ), scored AS (
             SELECT p.property_id, p.property_name, p.property_type, p.distance_to_campus_m, p.gender_policy,
                    p.nearest_school, p.nearest_landmark, p.water_billing, p.electricity_billing, p.wifi_policy,
                    p.no_curfew, p.is_featured, p.approved_at, b.barangay_id, b.barangay_name,
                    m.municipality_id, m.municipality_name,
                    room_totals.from_rate, COALESCE(room_totals.beds_available, 0) AS beds_available,
                    COALESCE(room_totals.bed_capacity, 0) AS bed_capacity,
                    COALESCE(room_totals.room_count, 0) AS room_count,
                    {$selectDistance},
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
             WHERE {$propertyConditions}
         )";

    $allParams = [...$roomParams, ...$distanceSelectParams, ...$propertyParams];

    $expandedFallback = false;
    if ($hasTargetCoords && $effectiveRadius > 0) {
        $within = rows(
            $baseSql . " SELECT * FROM scored WHERE distance_from_target_m IS NOT NULL AND distance_from_target_m <= ? ORDER BY {$orderSql}",
            [...$allParams, $effectiveRadius]
        );
        if ($within !== [] || $filters['walkable']) {
            return [
                'listings' => $within,
                'expanded' => false,
                'radius_m' => $effectiveRadius,
                'sort' => $activeSort,
            ];
        }
        // Smart fallback: nothing within the selected radius -> return nearest across Antique so user isn't stuck!
        $expandedFallback = true;
    }

    $allNearest = rows($baseSql . " SELECT * FROM scored ORDER BY {$orderSql}", $allParams);
    return [
        'listings' => $allNearest,
        'expanded' => $expandedFallback,
        'radius_m' => $effectiveRadius,
        'sort' => $activeSort,
    ];
}

function find_properties(array $filters): array
{
    $target = resolve_target_location($filters);
    return find_nearest_properties($filters, $target)['listings'];
}

function campus_distance(int $meters): string
{
    return $meters < 1000 ? $meters . ' m' : rtrim(rtrim(number_format($meters / 1000, 1), '0'), '.') . ' km';
}

function walk_or_ride_label(int $meters): string
{
    if ($meters <= 1200) {
        return '~' . max(1, (int) round($meters / 80)) . ' min walk';
    }
    return '~' . max(3, (int) round($meters / 400)) . ' min ride';
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
