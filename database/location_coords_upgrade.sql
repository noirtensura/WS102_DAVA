-- ============================================================================
-- DAYON LOCATION-FIRST UPGRADE — Antique Geographic Coordinates & Demo Hub Listings
-- Safe to run multiple times on MariaDB 10.4+ (XAMPP).
-- ============================================================================

USE dayon_db;

ALTER TABLE municipalities
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 7) NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10, 7) NULL;

ALTER TABLE barangays
    ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 7) NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(10, 7) NULL;

-- Fix Egaña UTF-8 name if mojibake exists
UPDATE barangays b
JOIN municipalities m USING (municipality_id)
SET b.barangay_name = 'Egaña'
WHERE m.municipality_name = 'Sibalom' AND m.province_name = 'Antique'
  AND b.barangay_name LIKE 'Ega%' AND b.barangay_name <> 'Egaña';

-- 1. Municipality coordinates across Antique
UPDATE municipalities SET latitude = 10.7450000, longitude = 121.9410000 WHERE municipality_name = 'San Jose de Buenavista' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 10.7885000, longitude = 122.0169000 WHERE municipality_name = 'Sibalom' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 10.7025000, longitude = 121.9818000 WHERE municipality_name = 'Hamtic' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 10.8380000, longitude = 121.9585000 WHERE municipality_name = 'Belison' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 10.9136000, longitude = 121.9942000 WHERE municipality_name = 'Patnongon' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 11.0431000, longitude = 122.0669000 WHERE municipality_name = 'Bugasong' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 11.0022000, longitude = 122.1292000 WHERE municipality_name = 'Valderrama' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 11.1475000, longitude = 122.0436000 WHERE municipality_name = 'Laua-an' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 11.2014000, longitude = 122.0372000 WHERE municipality_name = 'Barbaza' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 11.2921000, longitude = 122.0378000 WHERE municipality_name = 'Tibiao' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 11.4258000, longitude = 122.0542000 WHERE municipality_name = 'Culasi' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 11.5889000, longitude = 122.0947000 WHERE municipality_name = 'Sebaste' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 11.7222000, longitude = 122.0933000 WHERE municipality_name = 'Pandan' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 11.7667000, longitude = 121.9197000 WHERE municipality_name = 'Libertad' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 10.5172000, longitude = 121.9486000 WHERE municipality_name = 'Tobias Fornier' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 10.4292000, longitude = 121.9261000 WHERE municipality_name = 'Anini-y' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 10.8317000, longitude = 122.0886000 WHERE municipality_name = 'San Remigio' AND province_name = 'Antique';
UPDATE municipalities SET latitude = 11.9297000, longitude = 121.5542000 WHERE municipality_name = 'Caluya' AND province_name = 'Antique';

-- 2. Barangay coordinates — San Jose de Buenavista
SET @sj = (SELECT municipality_id FROM municipalities WHERE municipality_name = 'San Jose de Buenavista' AND province_name = 'Antique');
UPDATE barangays SET latitude = 10.7438, longitude = 121.9418 WHERE municipality_id = @sj AND barangay_name = 'Barangay 1';
UPDATE barangays SET latitude = 10.7445, longitude = 121.9435 WHERE municipality_id = @sj AND barangay_name = 'Barangay 2';
UPDATE barangays SET latitude = 10.7460, longitude = 121.9425 WHERE municipality_id = @sj AND barangay_name = 'Barangay 3';
UPDATE barangays SET latitude = 10.7478, longitude = 121.9452 WHERE municipality_id = @sj AND barangay_name = 'Barangay 4';
UPDATE barangays SET latitude = 10.7490, longitude = 121.9430 WHERE municipality_id = @sj AND barangay_name = 'Barangay 5';
UPDATE barangays SET latitude = 10.7466, longitude = 121.9401 WHERE municipality_id = @sj AND barangay_name = 'Barangay 6';
UPDATE barangays SET latitude = 10.7442, longitude = 121.9396 WHERE municipality_id = @sj AND barangay_name = 'Barangay 7';
UPDATE barangays SET latitude = 10.7464, longitude = 121.9442 WHERE municipality_id = @sj AND barangay_name = 'Barangay 8';
UPDATE barangays SET latitude = 10.7524, longitude = 121.9502 WHERE municipality_id = @sj AND barangay_name = 'Atabay';
UPDATE barangays SET latitude = 10.7618, longitude = 121.9610 WHERE municipality_id = @sj AND barangay_name = 'Badiang';
UPDATE barangays SET latitude = 10.7460, longitude = 121.9675 WHERE municipality_id = @sj AND barangay_name = 'Bariri';
UPDATE barangays SET latitude = 10.7660, longitude = 121.9700 WHERE municipality_id = @sj AND barangay_name = 'Bugarot';
UPDATE barangays SET latitude = 10.7580, longitude = 121.9720 WHERE municipality_id = @sj AND barangay_name = 'Cansadan';
UPDATE barangays SET latitude = 10.7720, longitude = 121.9650 WHERE municipality_id = @sj AND barangay_name = 'Durog';
UPDATE barangays SET latitude = 10.7575, longitude = 121.9482 WHERE municipality_id = @sj AND barangay_name = 'Funda-Dalipe';
UPDATE barangays SET latitude = 10.7775, longitude = 121.9620 WHERE municipality_id = @sj AND barangay_name = 'Igbonglo';
UPDATE barangays SET latitude = 10.7845, longitude = 121.9560 WHERE municipality_id = @sj AND barangay_name = 'Inabasan';
UPDATE barangays SET latitude = 10.7355, longitude = 121.9510 WHERE municipality_id = @sj AND barangay_name = 'Madrangca';
UPDATE barangays SET latitude = 10.7425, longitude = 121.9620 WHERE municipality_id = @sj AND barangay_name = 'Magcalon';
UPDATE barangays SET latitude = 10.7395, longitude = 121.9565 WHERE municipality_id = @sj AND barangay_name = 'Malaiba';
UPDATE barangays SET latitude = 10.7590, longitude = 121.9368 WHERE municipality_id = @sj AND barangay_name = 'Maybato Norte';
UPDATE barangays SET latitude = 10.7545, longitude = 121.9372 WHERE municipality_id = @sj AND barangay_name = 'Maybato Sur';
UPDATE barangays SET latitude = 10.7695, longitude = 121.9560 WHERE municipality_id = @sj AND barangay_name = 'Mojon';
UPDATE barangays SET latitude = 10.7740, longitude = 121.9470 WHERE municipality_id = @sj AND barangay_name = 'Pantao';
UPDATE barangays SET latitude = 10.7510, longitude = 121.9400 WHERE municipality_id = @sj AND barangay_name = 'San Angel';
UPDATE barangays SET latitude = 10.7495, longitude = 121.9570 WHERE municipality_id = @sj AND barangay_name = 'San Fernando';
UPDATE barangays SET latitude = 10.7680, longitude = 121.9425 WHERE municipality_id = @sj AND barangay_name = 'San Pedro';
UPDATE barangays SET latitude = 10.7810, longitude = 121.9495 WHERE municipality_id = @sj AND barangay_name = 'Supa';

-- 3. Barangay coordinates — Sibalom
SET @sib = (SELECT municipality_id FROM municipalities WHERE municipality_name = 'Sibalom' AND province_name = 'Antique');
UPDATE barangays SET latitude = 10.7882, longitude = 122.0162 WHERE municipality_id = @sib AND barangay_name = 'District I (Poblacion)';
UPDATE barangays SET latitude = 10.7896, longitude = 122.0180 WHERE municipality_id = @sib AND barangay_name = 'District II (Poblacion)';
UPDATE barangays SET latitude = 10.7908, longitude = 122.0148 WHERE municipality_id = @sib AND barangay_name = 'District III (Poblacion)';
UPDATE barangays SET latitude = 10.7865, longitude = 122.0185 WHERE municipality_id = @sib AND barangay_name = 'District IV (Poblacion)';
UPDATE barangays SET latitude = 10.7812, longitude = 122.0080 WHERE municipality_id = @sib AND barangay_name = 'Bari';
UPDATE barangays SET latitude = 10.7955, longitude = 122.0215 WHERE municipality_id = @sib AND barangay_name = 'Catmon';
UPDATE barangays SET latitude = 10.7770, longitude = 122.0010 WHERE municipality_id = @sib AND barangay_name = 'Egaña';
UPDATE barangays SET latitude = 10.7920, longitude = 122.0065 WHERE municipality_id = @sib AND barangay_name = 'Iglanot';
UPDATE barangays SET latitude = 10.7960, longitude = 122.0280 WHERE municipality_id = @sib AND barangay_name = 'Nagdayao';
UPDATE barangays SET latitude = 10.7830, longitude = 122.0245 WHERE municipality_id = @sib AND barangay_name = 'Pangpang';
UPDATE barangays SET latitude = 10.8080, longitude = 122.0265 WHERE municipality_id = @sib AND barangay_name = 'Pis-anan';
UPDATE barangays SET latitude = 10.7850, longitude = 122.0110 WHERE municipality_id = @sib AND barangay_name = 'San Juan';
UPDATE barangays SET latitude = 10.7995, longitude = 122.0125 WHERE municipality_id = @sib AND barangay_name = 'Solong';
UPDATE barangays SET latitude = 10.7940, longitude = 122.0350 WHERE municipality_id = @sib AND barangay_name = 'Villahermosa';

-- 4. Barangay coordinates — Hamtic
SET @ham = (SELECT municipality_id FROM municipalities WHERE municipality_name = 'Hamtic' AND province_name = 'Antique');
UPDATE barangays SET latitude = 10.7025, longitude = 121.9815 WHERE municipality_id = @ham AND barangay_name = 'Poblacion 1';
UPDATE barangays SET latitude = 10.7038, longitude = 121.9830 WHERE municipality_id = @ham AND barangay_name = 'Poblacion 2';
UPDATE barangays SET latitude = 10.7010, longitude = 121.9800 WHERE municipality_id = @ham AND barangay_name = 'Poblacion 3';
UPDATE barangays SET latitude = 10.7045, longitude = 121.9795 WHERE municipality_id = @ham AND barangay_name = 'Poblacion 4';
UPDATE barangays SET latitude = 10.7000, longitude = 121.9835 WHERE municipality_id = @ham AND barangay_name = 'Poblacion 5';
UPDATE barangays SET latitude = 10.7180, longitude = 121.9690 WHERE municipality_id = @ham AND barangay_name = 'Guintas';
UPDATE barangays SET latitude = 10.7250, longitude = 121.9595 WHERE municipality_id = @ham AND barangay_name = 'Malandog';
UPDATE barangays SET latitude = 10.6920, longitude = 121.9890 WHERE municipality_id = @ham AND barangay_name = 'Piape I';
UPDATE barangays SET latitude = 10.7095, longitude = 121.9865 WHERE municipality_id = @ham AND barangay_name = 'Pu-ao';

-- Default Poblacion barangays inherit municipality coordinates
UPDATE barangays b
JOIN municipalities m USING (municipality_id)
SET b.latitude = m.latitude, b.longitude = m.longitude
WHERE m.province_name = 'Antique' AND b.latitude IS NULL AND m.latitude IS NOT NULL;

-- 5. Populate realistic coordinates for existing San Jose demo properties
UPDATE properties SET latitude = 10.7465, longitude = 121.9441 WHERE property_id = 1 AND latitude IS NULL;
UPDATE properties SET latitude = 10.7481, longitude = 121.9456 WHERE property_id = 2 AND latitude IS NULL;
UPDATE properties SET latitude = 10.7439, longitude = 121.9421 WHERE property_id = 3 AND latitude IS NULL;
UPDATE properties SET latitude = 10.7678, longitude = 121.9428 WHERE property_id = 4 AND latitude IS NULL;
UPDATE properties SET latitude = 10.7574, longitude = 121.9480 WHERE property_id = 5 AND latitude IS NULL;
UPDATE properties SET latitude = 10.7526, longitude = 121.9501 WHERE property_id = 6 AND latitude IS NULL;
UPDATE properties SET latitude = 10.7591, longitude = 121.9369 WHERE property_id = 7 AND latitude IS NULL;

-- 6. Seed 3 realistic verified demo listings in Sibalom (near UA Main) & Hamtic (near UA Hamtic)
-- so selecting Sibalom or Hamtic immediately demonstrates nearest-location discovery!
INSERT INTO properties (
    landlord_id, barangay_id, property_name, property_type, description, street_address,
    nearest_school, nearest_landmark, latitude, longitude, distance_to_campus_m,
    gender_policy, curfew_time, no_curfew, house_rules,
    water_billing, water_notes, electricity_billing, electricity_notes, wifi_policy, wifi_notes,
    furnishing_status, deposit_terms, minimum_stay, payment_methods,
    cooking_policy, visitor_policy, contact_person, contact_phone, viewing_hours,
    approval_status, approved_by, approved_at, is_featured
)
SELECT
    2,
    (SELECT barangay_id FROM barangays WHERE municipality_id = @sib AND barangay_name = 'District I (Poblacion)' LIMIT 1),
    'Sibalom Scholars Boarding House',
    'boarding_house',
    'Clean, student-friendly boarding house just a 4-minute walk from University of Antique Main Campus. Features a quiet study deck, shared kitchen, and reliable water supply.',
    'Rizal St., District I',
    'University of Antique – Main Campus (Sibalom)',
    '300 m from UA Main Gate & Sibalom Public Market',
    10.7880, 122.0155, 300,
    'all', '22:00:00', 0, 'Observe quiet hours after 10 PM. Keep shared kitchen tidy.',
    'included', '24/7 water supply included', 'submetered', 'ANTECO room sub-meter', 'free', 'Fiber Wi-Fi included',
    'semi_furnished', '1 month advance, 1 month deposit', '1 semester (5 months)', 'Cash,GCash',
    'Shared kitchen with stove & electric kettle area', 'Daytime visitors until 7:00 PM',
    'Mila Santos', '09000000102', 'Mon–Sat, 8:00 AM – 5:30 PM',
    'approved', 1, CURRENT_TIMESTAMP, 1
WHERE EXISTS (SELECT 1 FROM landlord_profiles WHERE landlord_id = 2)
  AND NOT EXISTS (SELECT 1 FROM properties WHERE property_name = 'Sibalom Scholars Boarding House');

SET @sib_bh = (SELECT property_id FROM properties WHERE property_name = 'Sibalom Scholars Boarding House' LIMIT 1);
INSERT IGNORE INTO property_images (property_id, image_path, caption, is_primary)
SELECT @sib_bh, 'assets/images/san-jose-bedroom.webp', 'Student Bedroom', 1
WHERE @sib_bh IS NOT NULL AND NOT EXISTS (SELECT 1 FROM property_images WHERE property_id = @sib_bh);
INSERT IGNORE INTO property_amenities (property_id, amenity_id)
SELECT @sib_bh, amenity_id FROM amenities WHERE amenity_name IN ('Free Wi-Fi', 'Shared Kitchen / Cooking Area', 'CCTV Security', 'Backup Water Tank') AND @sib_bh IS NOT NULL;
INSERT INTO rooms (property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed)
SELECT @sib_bh, 'UA-101', 1, 'shared', 4, 1800.00 WHERE @sib_bh IS NOT NULL AND NOT EXISTS (SELECT 1 FROM rooms WHERE property_id = @sib_bh AND room_number = 'UA-101');
INSERT INTO rooms (property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed)
SELECT @sib_bh, 'UA-202', 2, 'solo', 1, 3200.00 WHERE @sib_bh IS NOT NULL AND NOT EXISTS (SELECT 1 FROM rooms WHERE property_id = @sib_bh AND room_number = 'UA-202');

INSERT INTO properties (
    landlord_id, barangay_id, property_name, property_type, description, street_address,
    nearest_school, nearest_landmark, latitude, longitude, distance_to_campus_m,
    gender_policy, curfew_time, no_curfew,
    water_billing, water_notes, electricity_billing, electricity_notes, wifi_policy, wifi_notes,
    furnishing_status, deposit_terms, payment_methods, cooking_policy,
    contact_person, contact_phone,
    approval_status, approved_by, approved_at, is_featured
)
SELECT
    2,
    (SELECT barangay_id FROM barangays WHERE municipality_id = @sib AND barangay_name = 'District II (Poblacion)' LIMIT 1),
    'Casa Sibalom Studio Apartments',
    'apartment',
    'Modern studio apartments with private comfort rooms and kitchenette sinks, ideal for senior UA students or working professionals looking for privacy in Sibalom.',
    'District II Poblacion',
    'University of Antique – Main Campus (Sibalom)',
    'Near Sibalom Plaza & 5 mins walk to UA',
    10.7898, 122.0184, 480,
    'all', NULL, 1,
    'submetered', 'Individual unit water meter', 'own_meter', 'Own ANTECO meter per unit', 'free', 'High-speed Fiber Wi-Fi',
    'fully_furnished', '1 month advance, 1 month refundable deposit', 'Cash,GCash,Bank Transfer', 'Private kitchenette inside apartment unit',
    'Mila Santos', '09000000102',
    'approved', 1, CURRENT_TIMESTAMP, 0
WHERE EXISTS (SELECT 1 FROM landlord_profiles WHERE landlord_id = 2)
  AND NOT EXISTS (SELECT 1 FROM properties WHERE property_name = 'Casa Sibalom Studio Apartments');

SET @sib_apt = (SELECT property_id FROM properties WHERE property_name = 'Casa Sibalom Studio Apartments' LIMIT 1);
INSERT IGNORE INTO property_images (property_id, image_path, caption, is_primary)
SELECT @sib_apt, 'assets/images/san-jose-building.webp', 'Apartment Exterior', 1
WHERE @sib_apt IS NOT NULL AND NOT EXISTS (SELECT 1 FROM property_images WHERE property_id = @sib_apt);
INSERT IGNORE INTO property_amenities (property_id, amenity_id)
SELECT @sib_apt, amenity_id FROM amenities WHERE amenity_name IN ('Free Wi-Fi', 'CCTV Security', 'Gated / Fenced Compound', 'Motorcycle / Bike Parking') AND @sib_apt IS NOT NULL;
INSERT INTO rooms (property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed)
SELECT @sib_apt, 'Apt A', 1, 'studio', 2, 4200.00 WHERE @sib_apt IS NOT NULL AND NOT EXISTS (SELECT 1 FROM rooms WHERE property_id = @sib_apt AND room_number = 'Apt A');

INSERT INTO properties (
    landlord_id, barangay_id, property_name, property_type, description, street_address,
    nearest_school, nearest_landmark, latitude, longitude, distance_to_campus_m,
    gender_policy, no_curfew,
    water_billing, electricity_billing, wifi_policy, deposit_terms, payment_methods,
    approval_status, approved_by, approved_at, is_featured
)
SELECT
    2,
    (SELECT barangay_id FROM barangays WHERE municipality_id = @ham AND barangay_name = 'Poblacion 1' LIMIT 1),
    'Hamtic Breeze Student House',
    'boarding_house',
    'Peaceful student boarding house steps away from University of Antique Hamtic Campus with shaded garden lounge and reliable Wi-Fi.',
    'Poblacion 1, Hamtic',
    'University of Antique – Hamtic Campus',
    'Near UA Hamtic Gate & Hamtic Public Plaza',
    10.7030, 121.9814, 250,
    'all', 0,
    'included', 'shared', 'free', '1 month advance only', 'Cash,GCash',
    'approved', 1, CURRENT_TIMESTAMP, 0
WHERE EXISTS (SELECT 1 FROM landlord_profiles WHERE landlord_id = 2)
  AND NOT EXISTS (SELECT 1 FROM properties WHERE property_name = 'Hamtic Breeze Student House');

SET @ham_bh = (SELECT property_id FROM properties WHERE property_name = 'Hamtic Breeze Student House' LIMIT 1);
INSERT IGNORE INTO property_images (property_id, image_path, caption, is_primary)
SELECT @ham_bh, 'assets/images/san-jose-reading.webp', 'Common Study Lounge', 1
WHERE @ham_bh IS NOT NULL AND NOT EXISTS (SELECT 1 FROM property_images WHERE property_id = @ham_bh);
INSERT IGNORE INTO property_amenities (property_id, amenity_id)
SELECT @ham_bh, amenity_id FROM amenities WHERE amenity_name IN ('Free Wi-Fi', 'Study Lounge', 'Shared Kitchen / Cooking Area') AND @ham_bh IS NOT NULL;
INSERT INTO rooms (property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed)
SELECT @ham_bh, 'H-1', 1, 'shared', 3, 1700.00 WHERE @ham_bh IS NOT NULL AND NOT EXISTS (SELECT 1 FROM rooms WHERE property_id = @ham_bh AND room_number = 'H-1');
