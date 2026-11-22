<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
$municipalityId = filter_input(INPUT_GET, 'municipality_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
echo json_encode($municipalityId ? rows(
    'SELECT barangay_id, barangay_name FROM barangays WHERE municipality_id = ? ORDER BY barangay_name',
    [$municipalityId]
) : [], JSON_THROW_ON_ERROR);
