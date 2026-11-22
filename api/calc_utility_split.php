<?php
declare(strict_types=1);

require_once __DIR__ . '/../landlord/_support.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$landlordId = landlord_page();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Use GET to load the billing scope.']);
    exit;
}
if (scalar('SELECT verification_status FROM landlord_profiles WHERE landlord_id = ?', [$landlordId]) !== 'verified') {
    http_response_code(403);
    echo json_encode(['error' => 'Complete landlord verification first.']);
    exit;
}

$propertyId = filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$roomId = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$month = $_GET['month'] ?? date('Y-m');
if (!$propertyId || $roomId === false || !is_string($month) || !preg_match('/^(19\d{2}|[2-9]\d{3})-(0[1-9]|1[0-2])$/D', $month)) {
    http_response_code(400);
    echo json_encode(['error' => 'Choose a valid month and property.']);
    exit;
}
try {
    owned_property($landlordId, $propertyId);
    if ($roomId !== null && (int) owned_room($landlordId, $roomId)['property_id'] !== $propertyId) {
        throw new DomainException('Choose a room in the selected property.');
    }
    $targets = utility_targets($landlordId, $propertyId, $roomId, $month);
    echo json_encode(['count' => count($targets), 'tenants' => $targets], JSON_THROW_ON_ERROR);
} catch (DomainException $error) {
    http_response_code(404);
    echo json_encode(['error' => $error->getMessage()]);
}
