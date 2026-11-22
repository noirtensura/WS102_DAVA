-- Localize existing Dayon installations and the original optional demo fixtures.
-- Barangay names: https://psa.gov.ph/classification/psgc/barangays/0600613000
USE dayon_db;
START TRANSACTION;

INSERT IGNORE INTO municipalities (municipality_name, province_name) VALUES
    ('San Jose de Buenavista', 'Antique');

SET @san_jose = (SELECT municipality_id FROM municipalities
                WHERE municipality_name = 'San Jose de Buenavista' AND province_name = 'Antique');

INSERT IGNORE INTO barangays (municipality_id, barangay_name) VALUES
    (@san_jose, 'Atabay'),
    (@san_jose, 'Badiang'),
    (@san_jose, 'Barangay 1'),
    (@san_jose, 'Barangay 2'),
    (@san_jose, 'Barangay 3'),
    (@san_jose, 'Barangay 4'),
    (@san_jose, 'Barangay 5'),
    (@san_jose, 'Barangay 6'),
    (@san_jose, 'Barangay 7'),
    (@san_jose, 'Barangay 8'),
    (@san_jose, 'Bariri'),
    (@san_jose, 'Bugarot'),
    (@san_jose, 'Cansadan'),
    (@san_jose, 'Durog'),
    (@san_jose, 'Funda-Dalipe'),
    (@san_jose, 'Igbonglo'),
    (@san_jose, 'Inabasan'),
    (@san_jose, 'Madrangca'),
    (@san_jose, 'Magcalon'),
    (@san_jose, 'Malaiba'),
    (@san_jose, 'Maybato Norte'),
    (@san_jose, 'Maybato Sur'),
    (@san_jose, 'Mojon'),
    (@san_jose, 'Pantao'),
    (@san_jose, 'San Angel'),
    (@san_jose, 'San Fernando'),
    (@san_jose, 'San Pedro'),
    (@san_jose, 'Supa');

UPDATE properties p
JOIN users u ON u.user_id = p.landlord_id AND u.email = 'owner@dayon.test'
JOIN barangays old_barangay ON old_barangay.barangay_id = p.barangay_id
JOIN municipalities old_place ON old_place.municipality_id = old_barangay.municipality_id
JOIN (
    SELECT 1 AS property_id, 'Casa Molo Boarding House' AS old_name, 'Campus View Study Pad' AS new_name,
           'Barangay 8' AS area, 400 AS campus_distance
    UNION ALL SELECT 2, 'The Jaro Study House', 'University Dorm & Suites', 'Barangay 4', 640
    UNION ALL SELECT 3, 'Balay Verde Studio', 'Balay Central Boarding House', 'Barangay 1', 240
    UNION ALL SELECT 4, 'Villa Arevalo Lodge', 'San Pedro Student Lodge', 'San Pedro', 2600
    UNION ALL SELECT 5, 'Riverside Student Residence', 'Funda-Dalipe Residence', 'Funda-Dalipe', 1600
    UNION ALL SELECT 6, 'Aninipay Loft', 'Atabay Loft', 'Atabay', 1200
    UNION ALL SELECT 7, 'Tanza Courtyard House', 'Maybato Courtyard House', 'Maybato Norte', 1700
) demo ON demo.property_id = p.property_id AND demo.old_name = p.property_name
JOIN barangays b ON b.municipality_id = @san_jose AND b.barangay_name = demo.area
SET p.property_name = demo.new_name, p.barangay_id = b.barangay_id,
    p.street_address = 'Sample boarding house', p.latitude = NULL, p.longitude = NULL,
    p.distance_to_campus_m = demo.campus_distance
WHERE old_place.municipality_name = 'Iloilo City' AND old_place.province_name = 'Iloilo';

UPDATE rooms r
JOIN properties p ON p.property_id = r.property_id
JOIN users u ON u.user_id = p.landlord_id AND u.email = 'owner@dayon.test'
JOIN (
    SELECT 1 AS property_id, 'Campus View Study Pad' AS property_name, 'Molo 101' AS old_room, '204' AS new_room
    UNION ALL SELECT 1, 'Campus View Study Pad', 'Molo 202', '102'
    UNION ALL SELECT 2, 'University Dorm & Suites', 'Jaro 201', '201'
    UNION ALL SELECT 2, 'University Dorm & Suites', 'Jaro 204', '205'
    UNION ALL SELECT 3, 'Balay Central Boarding House', 'Verde Studio A', 'Studio A'
    UNION ALL SELECT 3, 'Balay Central Boarding House', 'Verde Studio B', 'Studio B'
    UNION ALL SELECT 4, 'San Pedro Student Lodge', 'Arevalo A', 'A'
    UNION ALL SELECT 4, 'San Pedro Student Lodge', 'Arevalo B', 'B'
) demo ON demo.property_id = p.property_id AND demo.property_name = p.property_name AND demo.old_room = r.room_number
LEFT JOIN rooms existing ON existing.property_id = p.property_id AND existing.room_number = demo.new_room
SET r.room_number = demo.new_room
WHERE existing.room_id IS NULL;

UPDATE property_images i
JOIN properties p ON p.property_id = i.property_id
JOIN users u ON u.user_id = p.landlord_id AND u.email = 'owner@dayon.test'
JOIN (
    SELECT 1 AS property_id, 'Campus View Study Pad' AS property_name, 'assets/images/listing-1.webp' AS old_image, 'assets/images/san-jose-study.webp' AS image
    UNION ALL SELECT 2, 'University Dorm & Suites', 'assets/images/listing-2.webp', 'assets/images/san-jose-lounge.webp'
    UNION ALL SELECT 3, 'Balay Central Boarding House', 'assets/images/listing-3.webp', 'assets/images/san-jose-solo.webp'
) demo ON demo.property_id = p.property_id AND demo.property_name = p.property_name AND demo.old_image = i.image_path
SET i.image_path = demo.image;

INSERT INTO property_images (property_id, image_path, is_primary)
SELECT p.property_id, demo.image, 0
FROM properties p
JOIN users u ON u.user_id = p.landlord_id AND u.email = 'owner@dayon.test'
CROSS JOIN (
    SELECT 'assets/images/san-jose-bedroom.webp' AS image
    UNION ALL SELECT 'assets/images/san-jose-reading.webp'
    UNION ALL SELECT 'assets/images/san-jose-kitchen.webp'
    UNION ALL SELECT 'assets/images/san-jose-building.webp'
) demo
WHERE p.property_id = 1 AND p.property_name = 'Campus View Study Pad'
  AND NOT EXISTS (SELECT 1 FROM property_images i WHERE i.property_id = p.property_id AND i.image_path = demo.image);

COMMIT;
