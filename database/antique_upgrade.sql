-- ============================================================================
-- DAYON ANTIQUE UPGRADE — Boarding Houses, Apartments, Flexible Details & Optional Permits
-- Safe to run multiple times on MariaDB 10.4+ (XAMPP).
-- ============================================================================

USE dayon_db;

-- 1. Expand properties table for boarding houses & apartments with optional necessities
ALTER TABLE properties
    MODIFY COLUMN description TEXT NULL,
    MODIFY COLUMN street_address VARCHAR(255) NULL,
    MODIFY COLUMN distance_to_campus_m INT UNSIGNED NULL,
    MODIFY COLUMN gender_policy ENUM('all', 'male_only', 'female_only') NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS property_type ENUM('boarding_house', 'apartment', 'dormitory', 'bedspace') NOT NULL DEFAULT 'boarding_house' AFTER property_name,
    ADD COLUMN IF NOT EXISTS nearest_school VARCHAR(150) NULL AFTER street_address,
    ADD COLUMN IF NOT EXISTS nearest_landmark VARCHAR(255) NULL AFTER nearest_school,
    ADD COLUMN IF NOT EXISTS water_billing ENUM('included', 'submetered', 'shared', 'fixed') NULL AFTER house_rules,
    ADD COLUMN IF NOT EXISTS water_notes VARCHAR(150) NULL AFTER water_billing,
    ADD COLUMN IF NOT EXISTS electricity_billing ENUM('included', 'submetered', 'shared', 'own_meter') NULL AFTER water_notes,
    ADD COLUMN IF NOT EXISTS electricity_notes VARCHAR(150) NULL AFTER electricity_billing,
    ADD COLUMN IF NOT EXISTS wifi_policy ENUM('free', 'paid', 'tenant_own', 'none') NULL AFTER electricity_notes,
    ADD COLUMN IF NOT EXISTS wifi_notes VARCHAR(150) NULL AFTER wifi_policy,
    ADD COLUMN IF NOT EXISTS furnishing_status ENUM('unfurnished', 'semi_furnished', 'fully_furnished') NULL AFTER wifi_notes,
    ADD COLUMN IF NOT EXISTS deposit_terms VARCHAR(200) NULL AFTER furnishing_status,
    ADD COLUMN IF NOT EXISTS minimum_stay VARCHAR(100) NULL AFTER deposit_terms,
    ADD COLUMN IF NOT EXISTS payment_methods VARCHAR(150) NULL AFTER minimum_stay,
    ADD COLUMN IF NOT EXISTS no_curfew TINYINT(1) NOT NULL DEFAULT 0 AFTER curfew_time,
    ADD COLUMN IF NOT EXISTS visitor_policy VARCHAR(200) NULL AFTER payment_methods,
    ADD COLUMN IF NOT EXISTS cooking_policy VARCHAR(200) NULL AFTER visitor_policy,
    ADD COLUMN IF NOT EXISTS pet_policy VARCHAR(150) NULL AFTER cooking_policy,
    ADD COLUMN IF NOT EXISTS contact_person VARCHAR(120) NULL AFTER pet_policy,
    ADD COLUMN IF NOT EXISTS contact_phone VARCHAR(30) NULL AFTER contact_person,
    ADD COLUMN IF NOT EXISTS contact_social VARCHAR(200) NULL AFTER contact_phone,
    ADD COLUMN IF NOT EXISTS viewing_hours VARCHAR(150) NULL AFTER contact_social,
    ADD COLUMN IF NOT EXISTS custom_details TEXT NULL AFTER viewing_hours,
    ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER custom_details;

-- 2. Optional photo caption on property_images
ALTER TABLE property_images
    ADD COLUMN IF NOT EXISTS caption VARCHAR(120) NULL AFTER image_path;

-- 3. Optional personal note on student bookmarks
ALTER TABLE bookmarks
    ADD COLUMN IF NOT EXISTS note VARCHAR(255) NULL AFTER property_id;

-- 4. Optional Antique permits & compliance documents table (none required)
CREATE TABLE IF NOT EXISTS landlord_permits (
    permit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    landlord_id BIGINT UNSIGNED NOT NULL,
    property_id BIGINT UNSIGNED NULL,
    permit_type ENUM(
        'barangay_clearance',
        'mayors_business_permit',
        'bfp_fsic',
        'sanitary_permit',
        'building_occupancy_permit',
        'dti_registration',
        'government_id',
        'other_local_permit'
    ) NOT NULL,
    custom_permit_name VARCHAR(120) NULL,
    permit_number VARCHAR(100) NULL,
    issuing_office VARCHAR(150) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    document_path VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    verification_status ENUM('submitted', 'verified', 'rejected') NOT NULL DEFAULT 'submitted',
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_permits_landlord (landlord_id),
    INDEX idx_permits_property (property_id),
    INDEX idx_permits_status (verification_status),
    CONSTRAINT fk_permit_landlord FOREIGN KEY (landlord_id) REFERENCES landlord_profiles(landlord_id) ON DELETE CASCADE,
    CONSTRAINT fk_permit_property FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    CONSTRAINT fk_permit_verifier FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Expand Antique Province municipalities & key barangays (San Jose de Buenavista + Antique towns)
INSERT IGNORE INTO municipalities (municipality_name, province_name) VALUES
    ('San Jose de Buenavista', 'Antique'),
    ('Sibalom', 'Antique'),
    ('Hamtic', 'Antique'),
    ('Belison', 'Antique'),
    ('Patnongon', 'Antique'),
    ('Bugasong', 'Antique'),
    ('Culasi', 'Antique'),
    ('Tibiao', 'Antique'),
    ('Tobias Fornier', 'Antique'),
    ('Pandan', 'Antique'),
    ('Barbaza', 'Antique'),
    ('Laua-an', 'Antique'),
    ('Anini-y', 'Antique'),
    ('Valderrama', 'Antique'),
    ('Sebaste', 'Antique'),
    ('Libertad', 'Antique'),
    ('San Remigio', 'Antique'),
    ('Caluya', 'Antique');

SET @sibalom = (SELECT municipality_id FROM municipalities WHERE municipality_name = 'Sibalom' AND province_name = 'Antique');
INSERT IGNORE INTO barangays (municipality_id, barangay_name) VALUES
    (@sibalom, 'District I (Poblacion)'),
    (@sibalom, 'District II (Poblacion)'),
    (@sibalom, 'District III (Poblacion)'),
    (@sibalom, 'District IV (Poblacion)'),
    (@sibalom, 'Bari'),
    (@sibalom, 'Catmon'),
    (@sibalom, 'Egaña'),
    (@sibalom, 'Iglanot'),
    (@sibalom, 'Nagdayao'),
    (@sibalom, 'Pangpang'),
    (@sibalom, 'Pis-anan'),
    (@sibalom, 'San Juan'),
    (@sibalom, 'Solong'),
    (@sibalom, 'Villahermosa');

SET @hamtic = (SELECT municipality_id FROM municipalities WHERE municipality_name = 'Hamtic' AND province_name = 'Antique');
INSERT IGNORE INTO barangays (municipality_id, barangay_name) VALUES
    (@hamtic, 'Poblacion 1'),
    (@hamtic, 'Poblacion 2'),
    (@hamtic, 'Poblacion 3'),
    (@hamtic, 'Poblacion 4'),
    (@hamtic, 'Poblacion 5'),
    (@hamtic, 'Guintas'),
    (@hamtic, 'Malandog'),
    (@hamtic, 'Piape I'),
    (@hamtic, 'Pu-ao');

INSERT IGNORE INTO barangays (municipality_id, barangay_name)
SELECT municipality_id, 'Poblacion'
FROM municipalities
WHERE province_name = 'Antique'
  AND municipality_name NOT IN ('San Jose de Buenavista', 'Sibalom', 'Hamtic');

-- 6. Seed additional student housing necessities in amenities
INSERT IGNORE INTO amenities (amenity_name, scope) VALUES
    ('Fire Extinguisher', 'property'),
    ('Backup Water Tank', 'property'),
    ('Gated / Fenced Compound', 'property'),
    ('Caretaker On-Site', 'property'),
    ('Clothes Drying Area', 'property'),
    ('Common Dining Area', 'property'),
    ('Refrigerator Access', 'property'),
    ('Emergency Lights / Exit', 'property'),
    ('Generator / Solar Backup', 'property'),
    ('Bed & Mattress Included', 'room'),
    ('Private Kitchenette / Sink', 'room'),
    ('Balcony / Terrace', 'room'),
    ('Aircon Provision / Ready', 'room'),
    ('Private Entrance', 'room'),
    ('Window with Screen', 'room');

-- 7. Enrich existing demo listings with boarding house / apartment types & realistic optional details
UPDATE properties
SET property_type = 'boarding_house',
    nearest_school = 'St. Anthony''s College',
    nearest_landmark = 'Near SAC Main Gate & Barangay 8 Plaza',
    water_billing = 'shared',
    water_notes = '₱125/head monthly (24/7 overhead tank)',
    electricity_billing = 'submetered',
    electricity_notes = 'ANTECO rate · Room sub-meter',
    wifi_policy = 'free',
    wifi_notes = 'PLDT Fiber 200 Mbps included',
    furnishing_status = 'semi_furnished',
    deposit_terms = '1 month advance, 1 month refundable deposit',
    minimum_stay = '1 semester (5 months)',
    payment_methods = 'Cash,GCash,Bank Transfer',
    visitor_policy = 'Daytime visitors allowed in common lounge until 8:00 PM',
    cooking_policy = 'Shared kitchen with gas stove & rice cooker area',
    pet_policy = 'No pets allowed',
    contact_person = 'Mila Santos (Owner)',
    contact_phone = '09000000102',
    viewing_hours = 'Mon–Sat, 8:00 AM – 6:00 PM',
    custom_details = '[{"label":"Flood Status","value":"Elevated, flood-free lot"},{"label":"Tricycle Access","value":"1 min walk to main road (₱15 to town proper)"},{"label":"Beddings","value":"Foam mattress provided; bring own pillows & sheets"}]',
    is_featured = 1
WHERE property_id = 1 AND property_name = 'Campus View Study Pad' AND nearest_school IS NULL;

UPDATE properties
SET property_type = 'dormitory',
    nearest_school = 'St. Anthony''s College',
    nearest_landmark = 'Along Tobias Fornier St., near cafes & printing shops',
    water_billing = 'included',
    water_notes = 'Included in monthly rate',
    electricity_billing = 'submetered',
    electricity_notes = 'Split per room sub-meter',
    wifi_policy = 'free',
    wifi_notes = 'Dedicated study hall fiber Wi-Fi',
    furnishing_status = 'fully_furnished',
    deposit_terms = '1 month advance, 1 month deposit',
    minimum_stay = '1 semester',
    payment_methods = 'Cash,GCash',
    no_curfew = 1,
    visitor_policy = 'Lobby visitors allowed 8:00 AM – 7:00 PM',
    cooking_policy = 'Light reheating / electric kettle allowed in pantry',
    contact_person = 'Mila Santos',
    contact_phone = '09000000102',
    viewing_hours = 'Daily, 9:00 AM – 5:00 PM',
    is_featured = 1
WHERE property_id = 2 AND property_name = 'University Dorm & Suites' AND nearest_school IS NULL;

UPDATE properties
SET property_type = 'apartment',
    nearest_school = 'St. Anthony''s College',
    nearest_landmark = '240 m from SAC & near Robinsons Place Antique',
    water_billing = 'submetered',
    water_notes = 'Own unit meter',
    electricity_billing = 'own_meter',
    electricity_notes = 'Own ANTECO meter per apartment studio',
    wifi_policy = 'free',
    wifi_notes = 'High-speed Wi-Fi included',
    furnishing_status = 'fully_furnished',
    deposit_terms = '1 month advance, 1 month security deposit',
    minimum_stay = '6 months',
    payment_methods = 'Cash,GCash,Bank Transfer',
    no_curfew = 1,
    visitor_policy = 'Visitors allowed until 9:00 PM',
    cooking_policy = 'Private cooking allowed inside studio unit',
    pet_policy = 'Small indoor pets subject to owner approval',
    contact_person = 'Mila Santos',
    contact_phone = '09000000102',
    viewing_hours = 'By appointment, 8:00 AM – 6:00 PM',
    custom_details = '[{"label":"Private Entrance","value":"Independent studio door with deadbolt"},{"label":"Parking","value":"Covered motorcycle & bicycle bay"}]',
    is_featured = 1
WHERE property_id = 3 AND property_name = 'Balay Central Boarding House' AND nearest_school IS NULL;

UPDATE properties
SET property_type = 'boarding_house',
    nearest_school = 'St. Anthony''s College',
    nearest_landmark = 'Quiet residential street in Brgy. San Pedro',
    water_billing = 'included',
    electricity_billing = 'shared',
    wifi_policy = 'free',
    furnishing_status = 'semi_furnished',
    deposit_terms = '1 month advance only (no deposit)',
    payment_methods = 'Cash,GCash',
    cooking_policy = 'Shared kitchen available'
WHERE property_id = 4 AND property_name = 'San Pedro Student Lodge' AND nearest_school IS NULL;

UPDATE properties
SET property_type = 'bedspace',
    nearest_school = 'St. Anthony''s College',
    nearest_landmark = 'Near Funda-Dalipe Highway & Public Transport',
    water_billing = 'included',
    electricity_billing = 'shared',
    wifi_policy = 'free',
    payment_methods = 'Cash,GCash'
WHERE property_id = 5 AND property_name = 'Funda-Dalipe Residence' AND nearest_school IS NULL;

-- Leave property 6 (Atabay Loft) intentionally concise to showcase minimal landlord input!
UPDATE properties
SET property_type = 'apartment',
    no_curfew = 1,
    wifi_policy = 'free'
WHERE property_id = 6 AND property_name = 'Atabay Loft' AND property_type = 'boarding_house';

UPDATE properties
SET property_type = 'boarding_house',
    nearest_school = 'St. Anthony''s College',
    water_billing = 'included',
    wifi_policy = 'free'
WHERE property_id = 7 AND property_name = 'Maybato Courtyard House' AND nearest_school IS NULL;

-- 8. Seed sample optional Antique permits on demo verified landlord (id=2) so students & admin can see how optional permits work
INSERT INTO landlord_permits (landlord_id, property_id, permit_type, permit_number, issuing_office, issue_date, expiry_date, notes, verification_status, verified_by, verified_at)
SELECT 2, 1, 'barangay_clearance', 'BRGY8-2026-0142', 'Barangay 8, San Jose de Buenavista, Antique', '2026-01-12', '2026-12-31', 'Annual Barangay Boarding House Clearance', 'verified', 1, CURRENT_TIMESTAMP
WHERE EXISTS (SELECT 1 FROM landlord_profiles WHERE landlord_id = 2)
  AND EXISTS (SELECT 1 FROM properties WHERE property_id = 1)
  AND NOT EXISTS (SELECT 1 FROM landlord_permits WHERE landlord_id = 2 AND property_id = 1 AND permit_type = 'barangay_clearance');

INSERT INTO landlord_permits (landlord_id, property_id, permit_type, permit_number, issuing_office, issue_date, expiry_date, notes, verification_status, verified_by, verified_at)
SELECT 2, 1, 'bfp_fsic', 'FSIC-ANT-2026-089', 'Bureau of Fire Protection – San Jose, Antique', '2026-01-20', '2027-01-20', 'Fire Safety Inspection Certificate with fire extinguishers checked', 'verified', 1, CURRENT_TIMESTAMP
WHERE EXISTS (SELECT 1 FROM landlord_profiles WHERE landlord_id = 2)
  AND EXISTS (SELECT 1 FROM properties WHERE property_id = 1)
  AND NOT EXISTS (SELECT 1 FROM landlord_permits WHERE landlord_id = 2 AND property_id = 1 AND permit_type = 'bfp_fsic');

INSERT INTO landlord_permits (landlord_id, property_id, permit_type, permit_number, issuing_office, issue_date, expiry_date, notes, verification_status, verified_by, verified_at)
SELECT 2, 3, 'sanitary_permit', 'MHO-SJ-2026-305', 'Municipal Health Office – San Jose de Buenavista', '2026-02-04', '2026-12-31', 'Sanitary & water quality clearance for apartment studios', 'verified', 1, CURRENT_TIMESTAMP
WHERE EXISTS (SELECT 1 FROM landlord_profiles WHERE landlord_id = 2)
  AND EXISTS (SELECT 1 FROM properties WHERE property_id = 3)
  AND NOT EXISTS (SELECT 1 FROM landlord_permits WHERE landlord_id = 2 AND property_id = 3 AND permit_type = 'sanitary_permit');

INSERT INTO landlord_permits (landlord_id, property_id, permit_type, permit_number, issuing_office, issue_date, expiry_date, notes, verification_status, verified_by, verified_at)
SELECT 2, NULL, 'mayors_business_permit', 'BPLO-SJ-2026-1120', 'Business Permits & Licensing Office – San Jose de Buenavista, Antique', '2026-01-15', '2026-12-31', 'Optional LGU Mayor''s Business Permit on file', 'verified', 1, CURRENT_TIMESTAMP
WHERE EXISTS (SELECT 1 FROM landlord_profiles WHERE landlord_id = 2)
  AND NOT EXISTS (SELECT 1 FROM landlord_permits WHERE landlord_id = 2 AND property_id IS NULL AND permit_type = 'mayors_business_permit');
