-- ============================================================================
-- DAYON REALISTIC ANTIQUE SEED DATA (Owners/Landlords, Students & Admin)
-- Idempotent: safe to run multiple times on dayon_db (MariaDB 10.4+ / XAMPP).
-- See credentials.md in project root for all plain-text passwords and details.
-- ============================================================================

USE dayon_db;
SET time_zone = '+08:00';

SET @admin_role    = (SELECT role_id FROM roles WHERE role_name = 'admin');
SET @landlord_role = (SELECT role_id FROM roles WHERE role_name = 'landlord');
SET @student_role  = (SELECT role_id FROM roles WHERE role_name = 'student');

-- ----------------------------------------------------------------------------
-- 1. REALISTIC USERS
-- ----------------------------------------------------------------------------

INSERT IGNORE INTO users (role_id, email, password_hash, first_name, last_name, phone_number, account_status) VALUES
    (@admin_role,    'katrina.salcedo@dayon-antique.ph', '$2y$10$EaStHY.b9NkwO9exodJUCu.5adKPyP9.QcrNqCfxbD2pwba.l6cnW', 'Katrina', 'Salcedo', '0917-900-4410', 'active'),
    (@landlord_role, 'elena.magbanua@gmail.com',         '$2y$10$ZlhWOc.nzpjWBfwo.EFw7ueqBP5kmaLfTHvMAu4x9/VFXzbp4Zoz.', 'Elena',   'Magbanua', '0917-482-3190', 'active'),
    (@landlord_role, 'ramon.villanueva@gmail.com',       '$2y$10$T0zhI0gaJFo6aoGfKwC1HuXb4HksGBrKIUIr8/GA7bFbYHkpCeoTu', 'Ramon',   'Villanueva', '0928-615-4402', 'active'),
    (@landlord_role, 'marites.delgado@gmail.com',        '$2y$10$3GsD0JVYCp.bIwKfwMXv2.63az68aQykRu.gGVh/tSTg95.xkx1gm', 'Marites', 'Delgado', '0956-739-8124', 'active'),
    (@student_role,  'joshua.bautista@sac.edu.ph',       '$2y$10$RLdREoZm1jUMxBCWltxxrep3tfYPDh1WmKurnBzXx2Ri.miC32BzO', 'Joshua',  'Bautista', '0915-824-6619', 'active'),
    (@student_role,  'clarisse.mendoza@ua.edu.ph',       '$2y$10$PhHmkObDBdWIDCIVXH9GXesj1i0W2o4zgz68mBh/3egXcrvV5tMbi', 'Clarisse','Mendoza', '0961-342-9078', 'active'),
    (@student_role,  'mark.soriano@sac.edu.ph',          '$2y$10$APpvcuQF2PPWR6UmtBapNepY4o8nvAA8HZUZk6zVJsGD.x/grNg56', 'Mark Angelo', 'Soriano', '0945-219-7730', 'active');

SET @katrina  = (SELECT user_id FROM users WHERE email = 'katrina.salcedo@dayon-antique.ph');
SET @elena    = (SELECT user_id FROM users WHERE email = 'elena.magbanua@gmail.com');
SET @ramon    = (SELECT user_id FROM users WHERE email = 'ramon.villanueva@gmail.com');
SET @marites  = (SELECT user_id FROM users WHERE email = 'marites.delgado@gmail.com');
SET @joshua   = (SELECT user_id FROM users WHERE email = 'joshua.bautista@sac.edu.ph');
SET @clarisse = (SELECT user_id FROM users WHERE email = 'clarisse.mendoza@ua.edu.ph');
SET @mark     = (SELECT user_id FROM users WHERE email = 'mark.soriano@sac.edu.ph');

-- ----------------------------------------------------------------------------
-- 2. LANDLORD & STUDENT PROFILES + EMERGENCY CONTACTS
-- ----------------------------------------------------------------------------

INSERT IGNORE INTO landlord_profiles (landlord_id, government_id_path, verification_status, verified_by, verified_at) VALUES
    (@elena,   '', 'verified', @katrina, CURRENT_TIMESTAMP),
    (@ramon,   '', 'verified', @katrina, CURRENT_TIMESTAMP),
    (@marites, '', 'pending',  NULL,     NULL);

INSERT IGNORE INTO student_profiles (student_id, school_id_number, course_program, year_level) VALUES
    (@joshua,   'SAC-2024-0418', 'BS Computer Science', 3),
    (@clarisse, 'UA-2025-1182',  'BS Nursing',          2),
    (@mark,     'SAC-2023-0892', 'BS Accountancy',      4);

INSERT INTO emergency_contacts (student_id, full_name, relationship, phone_number)
SELECT @joshua, 'Lorna Bautista', 'Mother', '0917-503-2291'
WHERE NOT EXISTS (SELECT 1 FROM emergency_contacts WHERE student_id = @joshua AND full_name = 'Lorna Bautista');

INSERT INTO emergency_contacts (student_id, full_name, relationship, phone_number)
SELECT @clarisse, 'Roberto Mendoza', 'Father', '0920-811-4520'
WHERE NOT EXISTS (SELECT 1 FROM emergency_contacts WHERE student_id = @clarisse AND full_name = 'Roberto Mendoza');

INSERT INTO emergency_contacts (student_id, full_name, relationship, phone_number)
SELECT @mark, 'Teresita Soriano', 'Mother', '0918-442-1095'
WHERE NOT EXISTS (SELECT 1 FROM emergency_contacts WHERE student_id = @mark AND full_name = 'Teresita Soriano');

-- ----------------------------------------------------------------------------
-- 3. REALISTIC PROPERTIES (SAN JOSE, SIBALOM, HAMTIC)
-- ----------------------------------------------------------------------------

SET @brgy_sj8  = (SELECT b.barangay_id FROM barangays b JOIN municipalities m USING(municipality_id) WHERE m.municipality_name = 'San Jose de Buenavista' AND b.barangay_name = 'Barangay 8' LIMIT 1);
SET @brgy_sib1 = (SELECT b.barangay_id FROM barangays b JOIN municipalities m USING(municipality_id) WHERE m.municipality_name = 'Sibalom' AND b.barangay_name = 'District I (Poblacion)' LIMIT 1);
SET @brgy_ham2 = (SELECT b.barangay_id FROM barangays b JOIN municipalities m USING(municipality_id) WHERE m.municipality_name = 'Hamtic' AND b.barangay_name = 'Poblacion 2' LIMIT 1);

-- 3A. Elena Magbanua — Full-detail boarding house in Barangay 8, San Jose
INSERT INTO properties (
    landlord_id, barangay_id, property_name, property_type, description, street_address,
    nearest_school, nearest_landmark, latitude, longitude, distance_to_campus_m,
    gender_policy, curfew_time, no_curfew, house_rules,
    water_billing, water_notes, electricity_billing, electricity_notes, wifi_policy, wifi_notes,
    furnishing_status, deposit_terms, minimum_stay, payment_methods,
    visitor_policy, cooking_policy, pet_policy,
    contact_person, contact_phone, contact_social, viewing_hours, custom_details,
    is_featured, approval_status, approved_by, approved_at
)
SELECT
    @elena, @brgy_sj8,
    'Magbanua Residences & Student Boarding House', 'boarding_house',
    'Well-ventilated two-storey family-managed boarding house a 4-minute walk from St. Anthony''s College. Clean common kitchen, CCTV-monitored gate, reliable overhead water tank, and strong fiber Wi-Fi for online classes.',
    'Purok 2, Tobias Fornier St., Barangay 8',
    'St. Anthony''s College (San Jose)',
    '320 m from SAC Main Gate, near 7-Eleven & printing shops',
    10.7460, 121.9434, 320,
    'all', '22:00:00', 0,
    'Quiet hours start at 10:00 PM. Register daytime visitors with Ate Elena. Turn off fans and lights when leaving the room.',
    'included', 'Included in rent · 24/7 with backup overhead tank',
    'submetered', 'ANTECO rate · Individual room sub-meter',
    'free', 'PLDT Fiber 300 Mbps (2 routers per floor)',
    'semi_furnished', '1 month advance, 1 month refundable deposit',
    '1 semester (5 months)', 'Cash,GCash,Maya,Bank Transfer',
    'Daytime visitors welcome in lounge 8:00 AM – 8:00 PM',
    'Shared kitchen with LPG stove, sink & dish rack; rice cookers allowed',
    'No pets allowed',
    'Elena Magbanua (Owner)', '0917-482-3190', 'fb.com/MagbanuaResidencesAntique',
    'Mon–Sat, 8:00 AM – 6:00 PM (Sun by appointment)',
    '[{"label":"Flood Status","value":"Elevated, flood-free residential lot"},{"label":"Tricycle Fare","value":"₱15 to San Jose Public Market / Robinsons"},{"label":"Appliance Fee","value":"No extra fee for laptops & electric fans"}]',
    1, 'approved', @katrina, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM properties WHERE property_name = 'Magbanua Residences & Student Boarding House');

SET @prop_elena = (SELECT property_id FROM properties WHERE property_name = 'Magbanua Residences & Student Boarding House' LIMIT 1);

INSERT INTO property_images (property_id, image_path, caption, is_primary)
SELECT @prop_elena, 'assets/images/hero.webp', 'Front Exterior & Gate', 1
WHERE @prop_elena IS NOT NULL AND NOT EXISTS (SELECT 1 FROM property_images WHERE property_id = @prop_elena AND is_primary = 1);
INSERT INTO property_images (property_id, image_path, caption, is_primary)
SELECT @prop_elena, 'assets/images/san-jose-study.webp', 'Solo Study Room', 0
WHERE @prop_elena IS NOT NULL AND NOT EXISTS (SELECT 1 FROM property_images WHERE property_id = @prop_elena AND image_path = 'assets/images/san-jose-study.webp');
INSERT INTO property_images (property_id, image_path, caption, is_primary)
SELECT @prop_elena, 'assets/images/san-jose-kitchen.webp', 'Shared Kitchen', 0
WHERE @prop_elena IS NOT NULL AND NOT EXISTS (SELECT 1 FROM property_images WHERE property_id = @prop_elena AND image_path = 'assets/images/san-jose-kitchen.webp');

INSERT IGNORE INTO property_amenities (property_id, amenity_id)
SELECT @prop_elena, amenity_id FROM amenities
WHERE amenity_name IN ('Free Wi-Fi','CCTV Security','Shared Kitchen / Cooking Area','Laundry Area','Backup Water Tank','Fire Extinguisher','Gated / Fenced Compound','Caretaker On-Site')
  AND @prop_elena IS NOT NULL;

INSERT INTO rooms (property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed)
SELECT @prop_elena, '101', 1, 'shared', 4, 2400.00 WHERE @prop_elena IS NOT NULL AND NOT EXISTS (SELECT 1 FROM rooms WHERE property_id = @prop_elena AND room_number = '101');
INSERT INTO rooms (property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed)
SELECT @prop_elena, '201', 2, 'solo', 1, 3900.00 WHERE @prop_elena IS NOT NULL AND NOT EXISTS (SELECT 1 FROM rooms WHERE property_id = @prop_elena AND room_number = '201');

SET @room_elena_101 = (SELECT room_id FROM rooms WHERE property_id = @prop_elena AND room_number = '101' LIMIT 1);
SET @room_elena_201 = (SELECT room_id FROM rooms WHERE property_id = @prop_elena AND room_number = '201' LIMIT 1);

INSERT IGNORE INTO room_amenities (room_id, amenity_id)
SELECT @room_elena_101, amenity_id FROM amenities WHERE amenity_name IN ('Electric Fan Included','Study Desk & Chair','Personal Cabinet / Locker','Bed & Mattress Included','Sub-Metered Electricity') AND @room_elena_101 IS NOT NULL;
INSERT IGNORE INTO room_amenities (room_id, amenity_id)
SELECT @room_elena_201, amenity_id FROM amenities WHERE amenity_name IN ('Own Comfort Room (CR)','Air Conditioning','Study Desk & Chair','Bed & Mattress Included','Sub-Metered Electricity') AND @room_elena_201 IS NOT NULL;

-- Optional Antique Permits for Elena (verified by Admin Katrina)
INSERT INTO landlord_permits (landlord_id, property_id, permit_type, permit_number, issuing_office, issue_date, expiry_date, notes, verification_status, verified_by, verified_at)
SELECT @elena, @prop_elena, 'barangay_clearance', 'BRGY8-2026-0219', 'Barangay 8, San Jose de Buenavista, Antique', '2026-01-18', '2026-12-31', 'Annual Barangay Clearance for Boarding House', 'verified', @katrina, CURRENT_TIMESTAMP
WHERE @prop_elena IS NOT NULL AND NOT EXISTS (SELECT 1 FROM landlord_permits WHERE landlord_id = @elena AND permit_number = 'BRGY8-2026-0219');

INSERT INTO landlord_permits (landlord_id, property_id, permit_type, permit_number, issuing_office, issue_date, expiry_date, notes, verification_status, verified_by, verified_at)
SELECT @elena, @prop_elena, 'bfp_fsic', 'FSIC-SJ-2026-0144', 'Bureau of Fire Protection – San Jose, Antique', '2026-01-25', '2027-01-25', 'Passed fire safety inspection with extinguishers on both floors', 'verified', @katrina, CURRENT_TIMESTAMP
WHERE @prop_elena IS NOT NULL AND NOT EXISTS (SELECT 1 FROM landlord_permits WHERE landlord_id = @elena AND permit_number = 'FSIC-SJ-2026-0144');

INSERT INTO landlord_permits (landlord_id, property_id, permit_type, permit_number, issuing_office, issue_date, expiry_date, notes, verification_status, verified_by, verified_at)
SELECT @elena, @prop_elena, 'sanitary_permit', 'MHO-SJ-2026-0488', 'Municipal Health Office – San Jose de Buenavista', '2026-02-02', '2026-12-31', 'Sanitary & potable water inspection clearance', 'verified', @katrina, CURRENT_TIMESTAMP
WHERE @prop_elena IS NOT NULL AND NOT EXISTS (SELECT 1 FROM landlord_permits WHERE landlord_id = @elena AND permit_number = 'MHO-SJ-2026-0488');

-- 3B. Ramon Villanueva — Verified Apartment in Sibalom (near UA Main, Barangay Clearance ONLY, NO Business Permit)
INSERT INTO properties (
    landlord_id, barangay_id, property_name, property_type, description, street_address,
    nearest_school, nearest_landmark, latitude, longitude, distance_to_campus_m,
    gender_policy, curfew_time, no_curfew, house_rules,
    water_billing, water_notes, electricity_billing, electricity_notes, wifi_policy, wifi_notes,
    furnishing_status, deposit_terms, minimum_stay, payment_methods,
    cooking_policy, contact_person, contact_phone, viewing_hours,
    is_featured, approval_status, approved_by, approved_at
)
SELECT
    @ramon, @brgy_sib1,
    'Villanueva Corner Apartments', 'apartment',
    'Clean studio apartment units just 3 minutes on foot from University of Antique Main Campus. Each unit has its own CR, private kitchenette sink, own ANTECO electric meter, and 24/7 independent gate access.',
    'Corner Mabini St., District I Poblacion',
    'University of Antique – Main Campus (Sibalom)',
    '220 m from UA Main Gate & near Sibalom Church Plaza',
    10.7889, 122.0160, 220,
    'all', NULL, 1,
    'Maintain clean hallways and keep music low after 10:00 PM. Ideal for nursing, engineering, and working students.',
    'submetered', 'Individual unit water sub-meter',
    'own_meter', 'Own ANTECO electric meter per unit',
    'free', 'Shared fiber Wi-Fi included',
    'semi_furnished', '1 month advance, 1 month deposit',
    '6 months', 'Cash,GCash,Bank Transfer',
    'Private cooking allowed inside each studio unit',
    'Ramon Villanueva (Owner)', '0928-615-4402', 'Daily, 9:00 AM – 6:00 PM',
    1, 'approved', @katrina, CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM properties WHERE property_name = 'Villanueva Corner Apartments');

SET @prop_ramon = (SELECT property_id FROM properties WHERE property_name = 'Villanueva Corner Apartments' LIMIT 1);

INSERT INTO property_images (property_id, image_path, caption, is_primary)
SELECT @prop_ramon, 'assets/images/san-jose-building.webp', 'Apartment Building Front', 1
WHERE @prop_ramon IS NOT NULL AND NOT EXISTS (SELECT 1 FROM property_images WHERE property_id = @prop_ramon AND is_primary = 1);
INSERT INTO property_images (property_id, image_path, caption, is_primary)
SELECT @prop_ramon, 'assets/images/san-jose-solo.webp', 'Studio Unit Interior', 0
WHERE @prop_ramon IS NOT NULL AND NOT EXISTS (SELECT 1 FROM property_images WHERE property_id = @prop_ramon AND image_path = 'assets/images/san-jose-solo.webp');

INSERT IGNORE INTO property_amenities (property_id, amenity_id)
SELECT @prop_ramon, amenity_id FROM amenities
WHERE amenity_name IN ('Free Wi-Fi','CCTV Security','Gated / Fenced Compound','Motorcycle / Bike Parking')
  AND @prop_ramon IS NOT NULL;

INSERT INTO rooms (property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed)
SELECT @prop_ramon, 'Unit A', 1, 'studio', 2, 4500.00 WHERE @prop_ramon IS NOT NULL AND NOT EXISTS (SELECT 1 FROM rooms WHERE property_id = @prop_ramon AND room_number = 'Unit A');
INSERT INTO rooms (property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed)
SELECT @prop_ramon, 'Unit B', 2, 'studio', 2, 3800.00 WHERE @prop_ramon IS NOT NULL AND NOT EXISTS (SELECT 1 FROM rooms WHERE property_id = @prop_ramon AND room_number = 'Unit B');

INSERT INTO landlord_permits (landlord_id, property_id, permit_type, permit_number, issuing_office, issue_date, expiry_date, notes, verification_status, verified_by, verified_at)
SELECT @ramon, @prop_ramon, 'barangay_clearance', 'SIB-D1-2026-077', 'Barangay District I, Sibalom, Antique', '2026-02-10', '2026-12-31', 'Barangay Clearance only (business permit optional)', 'verified', @katrina, CURRENT_TIMESTAMP
WHERE @prop_ramon IS NOT NULL AND NOT EXISTS (SELECT 1 FROM landlord_permits WHERE landlord_id = @ramon AND permit_number = 'SIB-D1-2026-077');

-- 3C. Marites Delgado — New Pending Landlord in Hamtic with ZERO permits & concise details
INSERT INTO properties (
    landlord_id, barangay_id, property_name, property_type, nearest_school,
    latitude, longitude, distance_to_campus_m,
    water_billing, wifi_policy, deposit_terms, contact_person, contact_phone,
    approval_status
)
SELECT
    @marites, @brgy_ham2,
    'Delgado Family Bedspace', 'bedspace', 'University of Antique – Hamtic Campus',
    10.7036, 121.9826, 290,
    'included', 'free', '1 month advance only (no deposit)', 'Marites Delgado', '0956-739-8124',
    'pending'
WHERE NOT EXISTS (SELECT 1 FROM properties WHERE property_name = 'Delgado Family Bedspace');

SET @prop_marites = (SELECT property_id FROM properties WHERE property_name = 'Delgado Family Bedspace' LIMIT 1);
INSERT INTO property_images (property_id, image_path, caption, is_primary)
SELECT @prop_marites, 'assets/images/san-jose-bedroom.webp', 'Shared Student Room', 1
WHERE @prop_marites IS NOT NULL AND NOT EXISTS (SELECT 1 FROM property_images WHERE property_id = @prop_marites);
INSERT INTO rooms (property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed)
SELECT @prop_marites, 'Bedspace 1', 1, 'shared', 4, 1600.00 WHERE @prop_marites IS NOT NULL AND NOT EXISTS (SELECT 1 FROM rooms WHERE property_id = @prop_marites AND room_number = 'Bedspace 1');

-- ----------------------------------------------------------------------------
-- 4. REALISTIC STUDENT BOOKMARKS, VISIT REQUEST & ACTIVE LEASE
-- ----------------------------------------------------------------------------

INSERT IGNORE INTO bookmarks (student_id, property_id, note)
SELECT @joshua, @prop_elena, 'Very close to SAC gate, fiber Wi-Fi + BFP Fire Safety verified' WHERE @prop_elena IS NOT NULL;
INSERT IGNORE INTO bookmarks (student_id, property_id, note)
SELECT @joshua, 1, 'Compare Room 204 vs Magbanua Room 101' WHERE EXISTS (SELECT 1 FROM properties WHERE property_id = 1);
INSERT IGNORE INTO bookmarks (student_id, property_id, note)
SELECT @joshua, 3, 'Nice apartment studio if splitting with cousin' WHERE EXISTS (SELECT 1 FROM properties WHERE property_id = 3);

INSERT IGNORE INTO bookmarks (student_id, property_id, note)
SELECT @clarisse, @prop_ramon, 'No curfew — great for Nursing hospital duty shifts!' WHERE @prop_ramon IS NOT NULL;
INSERT IGNORE INTO bookmarks (student_id, property_id, note)
SELECT @clarisse, p.property_id, 'Affordable ₱1,800/mo, 4 min walk to UA Main'
FROM properties p WHERE p.property_name = 'Sibalom Scholars Boarding House' LIMIT 1;

-- Upcoming ocular visit for Joshua at Magbanua Residences Room 101
INSERT INTO reservations (student_id, room_id, request_type, scheduled_at, notes, status)
SELECT @joshua, @room_elena_101, 'ocular_visit', TIMESTAMP(CURDATE() + INTERVAL 2 DAY, '15:00:00'),
       'Hi Ma''am Elena, may I check Room 101 after my 2:30 PM class at SAC?', 'pending'
WHERE @room_elena_101 IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM reservations WHERE student_id = @joshua AND room_id = @room_elena_101);

-- Active lease + current month bill + verified GCash payment for Mark Angelo Soriano at Magbanua Residences Room 101
SET @this_month = DATE_SUB(CURDATE(), INTERVAL (DAYOFMONTH(CURDATE()) - 1) DAY);
SET @last_month = @this_month - INTERVAL 1 MONTH;

INSERT INTO leases (student_id, room_id, move_in_date, agreed_monthly_rent, advance_rent_amount, security_deposit_amount, billing_due_day, lease_status)
SELECT @mark, @room_elena_101, @last_month, 2400.00, 2400.00, 2400.00, 5, 'active'
WHERE @room_elena_101 IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM leases WHERE student_id = @mark AND lease_status = 'active');

SET @mark_lease = (SELECT lease_id FROM leases WHERE student_id = @mark AND lease_status = 'active' LIMIT 1);

INSERT IGNORE INTO rent_invoices (lease_id, billing_month, due_date)
SELECT @mark_lease, @this_month, @this_month + INTERVAL 4 DAY WHERE @mark_lease IS NOT NULL;

SET @mark_inv = (SELECT invoice_id FROM rent_invoices WHERE lease_id = @mark_lease AND billing_month = @this_month LIMIT 1);

INSERT INTO invoice_items (invoice_id, charge_type, description, amount)
SELECT @mark_inv, 'base_rent', 'Monthly room rent (Room 101)', 2400.00
WHERE @mark_inv IS NOT NULL AND NOT EXISTS (SELECT 1 FROM invoice_items WHERE invoice_id = @mark_inv AND charge_type = 'base_rent');
INSERT INTO invoice_items (invoice_id, charge_type, description, amount)
SELECT @mark_inv, 'electricity', 'ANTECO room sub-meter share', 285.00
WHERE @mark_inv IS NOT NULL AND NOT EXISTS (SELECT 1 FROM invoice_items WHERE invoice_id = @mark_inv AND charge_type = 'electricity');

INSERT INTO payments (invoice_id, receipt_number, amount_paid, payment_method, reference_number, verification_status, verified_by, paid_at, verified_at)
SELECT @mark_inv, 'DAYON-2026-0814', 2400.00, 'gcash', 'GCASH-9028417635', 'verified', @elena, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
WHERE @mark_inv IS NOT NULL AND NOT EXISTS (SELECT 1 FROM payments WHERE receipt_number = 'DAYON-2026-0814');
