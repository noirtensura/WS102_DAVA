-- Optional local demonstration records. Import once into a fresh Dayon schema.
-- Password for all five example accounts is DayonDemo2026!
USE dayon_db;
SET time_zone = '+08:00';

START TRANSACTION;

SET @demo_hash = '$2y$10$uCKpZgBXxMC6JBCpS.lsCeW6hYjYLbN5VUVFApdI2B8ZGl7ld123K';
SET @admin_role = (SELECT role_id FROM roles WHERE role_name = 'admin');
SET @landlord_role = (SELECT role_id FROM roles WHERE role_name = 'landlord');
SET @student_role = (SELECT role_id FROM roles WHERE role_name = 'student');

INSERT INTO users (user_id, role_id, email, password_hash, first_name, last_name, phone_number) VALUES
    (1, @admin_role, 'admin@dayon.test', @demo_hash, 'Alex', 'Reyes', '09000000101'),
    (2, @landlord_role, 'owner@dayon.test', @demo_hash, 'Mila', 'Santos', '09000000102'),
    (3, @student_role, 'tenant@dayon.test', @demo_hash, 'Jamie', 'Ramos', '09000000103'),
    (4, @student_role, 'student@dayon.test', @demo_hash, 'Sam', 'Cruz', '09000000104'),
    (5, @landlord_role, 'pending@dayon.test', @demo_hash, 'Robin', 'Flores', '09000000105');

INSERT INTO landlord_profiles (landlord_id, verification_status, verified_by, verified_at) VALUES
    (2, 'verified', 1, CURRENT_TIMESTAMP),
    (5, 'pending', NULL, NULL);

INSERT INTO student_profiles (student_id, school_id_number, course_program, year_level) VALUES
    (3, 'DEMO-2025-001', 'BS Information Technology', 2),
    (4, 'DEMO-2026-001', 'BS Hospitality Management', 1);

INSERT INTO emergency_contacts (student_id, full_name, relationship, phone_number) VALUES
    (3, 'Taylor Ramos', 'Guardian', '09000000201');

-- Fictional listings with illustrative campus distances from St. Anthony's College.
SET @san_jose = (SELECT municipality_id FROM municipalities
                WHERE municipality_name = 'San Jose de Buenavista' AND province_name = 'Antique');

INSERT INTO properties (property_id, landlord_id, barangay_id, property_name, description, street_address, latitude, longitude, distance_to_campus_m, gender_policy, curfew_time, house_rules, approval_status, approved_by, approved_at) VALUES
    (1, 2, (SELECT barangay_id FROM barangays WHERE municipality_id = @san_jose AND barangay_name = 'Barangay 8'), 'Campus View Study Pad', 'An easygoing shared house with bright bedrooms, a common kitchen, and outdoor space to unwind after class. Rooms come with a bed, desk, and lockable storage.', 'Sample boarding house', NULL, NULL, 400, 'all', '22:00:00', 'Quiet hours begin at 10 PM. Check in with the owner before inviting visitors. Clean shared spaces after use.', 'approved', 1, CURRENT_TIMESTAMP),
    (2, 2, (SELECT barangay_id FROM barangays WHERE municipality_id = @san_jose AND barangay_name = 'Barangay 4'), 'University Dorm & Suites', 'A modern residence with a dedicated reading area, dependable Wi-Fi, and convenient jeepney access. The shared rooms suit students who want a regular study routine.', 'Sample boarding house', NULL, NULL, 640, 'all', NULL, 'Keep the study room quiet. Visitors may use the lobby during daytime hours.', 'approved', 1, CURRENT_TIMESTAMP),
    (3, 2, (SELECT barangay_id FROM barangays WHERE municipality_id = @san_jose AND barangay_name = 'Barangay 1'), 'Balay Central Boarding House', 'Furnished studios with warm wood finishes, their own comfort rooms, and room-specific electricity meters. The building is a short walk from cafés and convenience stores.', 'Sample boarding house', NULL, NULL, 240, 'all', NULL, 'Smoking is prohibited indoors. Separate recyclables from household waste.', 'approved', 1, CURRENT_TIMESTAMP),
    (4, 2, (SELECT barangay_id FROM barangays WHERE municipality_id = @san_jose AND barangay_name = 'San Pedro'), 'San Pedro Student Lodge', 'A quiet residential lodge with a shaded porch, a shared kitchen, and room for bicycles. The owner lives nearby and is available for scheduled viewings.', 'Sample boarding house', NULL, NULL, 2600, 'female_only', '21:30:00', 'Lights out in shared areas at 10 PM. Overnight visitors require advance approval.', 'approved', 1, CURRENT_TIMESTAMP),
    (5, 2, (SELECT barangay_id FROM barangays WHERE municipality_id = @san_jose AND barangay_name = 'Funda-Dalipe'), 'Funda-Dalipe Residence', 'A light, breezy residence with shared student rooms, a laundry area, and practical furnishings. Markets and public transport are close by.', 'Sample boarding house', NULL, NULL, 1600, 'male_only', '22:00:00', 'Label personal items in the kitchen. Return the laundry area key after use.', 'approved', 1, CURRENT_TIMESTAMP),
    (6, 2, (SELECT barangay_id FROM barangays WHERE municipality_id = @san_jose AND barangay_name = 'Atabay'), 'Atabay Loft', 'An airy loft with large windows and comfortable spaces for individual or shared study. Small shops and local transport are within walking distance.', 'Sample boarding house', NULL, NULL, 1200, 'all', NULL, 'Avoid loud calls in the common area after 9 PM. Park bikes in the designated rack.', 'approved', 1, CURRENT_TIMESTAMP),
    (7, 2, (SELECT barangay_id FROM barangays WHERE municipality_id = @san_jose AND barangay_name = 'Maybato Norte'), 'Maybato Courtyard House', 'A small boarding house arranged around a shared courtyard, with rooms ready for scheduled visits after the listing is published.', 'Sample boarding house', NULL, NULL, 1700, 'all', '22:00:00', 'Please clean the courtyard after group study sessions.', 'pending', NULL, NULL);

INSERT INTO property_images (property_id, image_path, is_primary) VALUES
    (1, 'assets/images/san-jose-study.webp', 1),
    (2, 'assets/images/san-jose-lounge.webp', 1),
    (3, 'assets/images/san-jose-solo.webp', 1),
    (4, 'assets/images/listing-4.webp', 1),
    (5, 'assets/images/listing-5.webp', 1),
    (6, 'assets/images/listing-6.webp', 1),
    (1, 'assets/images/san-jose-bedroom.webp', 0),
    (1, 'assets/images/san-jose-reading.webp', 0),
    (1, 'assets/images/san-jose-kitchen.webp', 0),
    (1, 'assets/images/san-jose-building.webp', 0);

INSERT INTO property_amenities (property_id, amenity_id) VALUES
    (1, 1), (1, 2), (1, 3), (1, 4),
    (2, 1), (2, 2), (2, 5), (2, 6),
    (3, 1), (3, 2), (3, 4), (3, 7),
    (4, 1), (4, 3), (4, 6), (4, 7),
    (5, 1), (5, 3), (5, 4), (5, 6),
    (6, 1), (6, 2), (6, 5), (6, 7),
    (7, 1), (7, 3);

INSERT INTO rooms (room_id, property_id, room_number, floor_number, room_type, max_capacity, monthly_rate_per_bed) VALUES
    (1, 1, '204', 2, 'shared', 4, 2300.00),
    (2, 1, '102', 1, 'solo', 1, 3800.00),
    (3, 2, '201', 2, 'shared', 3, 2700.00),
    (4, 2, '205', 2, 'solo', 1, 4200.00),
    (5, 3, 'Studio A', 1, 'studio', 1, 5400.00),
    (6, 3, 'Studio B', 2, 'studio', 2, 4500.00),
    (7, 4, 'A', 1, 'shared', 3, 1900.00),
    (8, 4, 'B', 1, 'solo', 1, 2900.00),
    (9, 5, 'River 101', 1, 'shared', 4, 2200.00),
    (10, 5, 'River 203', 2, 'shared', 2, 2600.00),
    (11, 6, 'Loft 301', 3, 'solo', 1, 3500.00),
    (12, 6, 'Loft 302', 3, 'shared', 2, 2800.00),
    (13, 7, 'Courtyard 101', 1, 'shared', 3, 2500.00);

INSERT INTO room_amenities (room_id, amenity_id) VALUES
    (1, 10), (1, 11), (1, 12), (2, 8), (2, 9), (2, 11),
    (3, 10), (3, 11), (3, 12), (4, 9), (4, 11),
    (5, 8), (5, 9), (5, 13), (6, 8), (6, 9), (6, 13),
    (7, 10), (7, 12), (8, 11), (8, 12),
    (9, 10), (9, 12), (10, 9), (10, 11),
    (11, 9), (11, 11), (11, 12), (12, 10), (12, 11), (13, 10);

INSERT INTO bookmarks (student_id, property_id) VALUES
    (4, 1), (4, 2), (4, 6);

INSERT INTO reservations (student_id, room_id, request_type, scheduled_at, notes, status) VALUES
    (4, 3, 'ocular_visit', TIMESTAMP(CURDATE() + INTERVAL 3 DAY, '14:00:00'), 'I would like to view the study room as well.', 'pending'),
    (4, 12, 'bed_reservation', TIMESTAMP(CURDATE() + INTERVAL 8 DAY, '10:00:00'), 'Looking to move in before classes.', 'approved');

SET @this_month = DATE_SUB(CURDATE(), INTERVAL (DAYOFMONTH(CURDATE()) - 1) DAY);
SET @last_month = @this_month - INTERVAL 1 MONTH;

INSERT INTO leases (lease_id, student_id, room_id, move_in_date, agreed_monthly_rent, advance_rent_amount, security_deposit_amount, billing_due_day) VALUES
    (1, 3, 1, @last_month, 2300.00, 2300.00, 2300.00, 24);

INSERT INTO rent_invoices (invoice_id, lease_id, billing_month, due_date) VALUES
    (1, 1, @last_month, @last_month + INTERVAL 23 DAY),
    (2, 1, @this_month, @this_month + INTERVAL 23 DAY);

INSERT INTO invoice_items (invoice_id, charge_type, description, amount) VALUES
    (1, 'base_rent', 'Monthly room rent', 2300.00),
    (1, 'water', 'Shared water bill', 125.00),
    (2, 'base_rent', 'Monthly room rent', 2300.00),
    (2, 'electricity', 'Room electricity share', 240.00),
    (2, 'water', 'Shared water bill', 125.00);

INSERT INTO payments (invoice_id, receipt_number, amount_paid, payment_method, verification_status, verified_by, paid_at, verified_at) VALUES
    (1, 'DAYON-DEMO-0001', 2000.00, 'cash', 'verified', 2, @last_month + INTERVAL 23 DAY, @last_month + INTERVAL 23 DAY),
    (2, 'DAYON-DEMO-0002', 1000.00, 'cash', 'verified', 2, @this_month, @this_month);

INSERT INTO maintenance_requests (lease_id, category, description, priority, ticket_status) VALUES
    (1, 'plumbing', 'The common wash area tap drips even when closed.', 'medium', 'in_progress');

INSERT INTO announcements (property_id, title, message) VALUES
    (1, 'Laundry area schedule', 'The laundry area is open every day from 7 AM to 8 PM. Please collect your laundry before closing time.');

INSERT INTO reports (reporter_id, property_id, reason) VALUES
    (4, 2, 'Please confirm if the study lounge is open on weekends. The viewing information was unclear.');

INSERT INTO audit_logs (actor_id, action_type, target_table, target_id, details) VALUES
    (1, 'landlord_verified', 'landlord_profiles', 2, 'Approved the example landlord account.'),
    (1, 'property_approved', 'properties', 1, 'Published the example boarding house.');

COMMIT;
