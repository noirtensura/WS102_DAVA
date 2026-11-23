-- ============================================================================
-- DAYON DATABASE SCHEMA (3NF) — XAMPP / MariaDB / MySQL Compatible
-- ============================================================================

CREATE DATABASE IF NOT EXISTS dayon_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE dayon_db;

-- ============================================================================
-- 1. AUTHENTICATION, RBAC & USER SUBTYPES
-- ============================================================================

CREATE TABLE roles (
    role_id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE users (
    user_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    account_status ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

CREATE TABLE student_profiles (
    student_id BIGINT UNSIGNED PRIMARY KEY,
    school_id_number VARCHAR(50) NOT NULL UNIQUE,
    course_program VARCHAR(120) NOT NULL,
    year_level TINYINT UNSIGNED NOT NULL,
    CONSTRAINT chk_student_year CHECK (year_level BETWEEN 1 AND 6),
    CONSTRAINT fk_student_user FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE emergency_contacts (
    contact_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    relationship VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    CONSTRAINT fk_contact_student FOREIGN KEY (student_id) REFERENCES student_profiles(student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE landlord_profiles (
    landlord_id BIGINT UNSIGNED PRIMARY KEY,
    government_id_path VARCHAR(255) NOT NULL DEFAULT '',
    business_permit_path VARCHAR(255) NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_by BIGINT UNSIGNED NULL,
    verified_at TIMESTAMP NULL,
    CONSTRAINT fk_landlord_user FOREIGN KEY (landlord_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_landlord_verifier FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================================
-- 2. NORMALIZED LOCATIONS, PROPERTIES & ROOMS
-- ============================================================================

CREATE TABLE municipalities (
    municipality_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipality_name VARCHAR(100) NOT NULL,
    province_name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 7) NULL,
    longitude DECIMAL(10, 7) NULL,
    UNIQUE KEY uq_municipality_province (municipality_name, province_name)
) ENGINE=InnoDB;

CREATE TABLE barangays (
    barangay_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipality_id INT UNSIGNED NOT NULL,
    barangay_name VARCHAR(100) NOT NULL,
    latitude DECIMAL(10, 7) NULL,
    longitude DECIMAL(10, 7) NULL,
    UNIQUE KEY uq_barangay_municipality (municipality_id, barangay_name),
    CONSTRAINT fk_barangay_municipality FOREIGN KEY (municipality_id) REFERENCES municipalities(municipality_id)
) ENGINE=InnoDB;

CREATE TABLE properties (
    property_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    landlord_id BIGINT UNSIGNED NOT NULL,
    barangay_id INT UNSIGNED NOT NULL,
    property_name VARCHAR(150) NOT NULL,
    property_type ENUM('boarding_house', 'apartment', 'dormitory', 'bedspace') NOT NULL DEFAULT 'boarding_house',
    description TEXT NULL,
    street_address VARCHAR(255) NULL,
    nearest_school VARCHAR(150) NULL,
    nearest_landmark VARCHAR(255) NULL,
    latitude DECIMAL(10, 7) NULL,
    longitude DECIMAL(10, 7) NULL,
    distance_to_campus_m INT UNSIGNED NULL,
    gender_policy ENUM('all', 'male_only', 'female_only') NULL DEFAULT NULL,
    curfew_time TIME NULL,
    no_curfew TINYINT(1) NOT NULL DEFAULT 0,
    house_rules TEXT NULL,
    water_billing ENUM('included', 'submetered', 'shared', 'fixed') NULL,
    water_notes VARCHAR(150) NULL,
    electricity_billing ENUM('included', 'submetered', 'shared', 'own_meter') NULL,
    electricity_notes VARCHAR(150) NULL,
    wifi_policy ENUM('free', 'paid', 'tenant_own', 'none') NULL,
    wifi_notes VARCHAR(150) NULL,
    furnishing_status ENUM('unfurnished', 'semi_furnished', 'fully_furnished') NULL,
    deposit_terms VARCHAR(200) NULL,
    minimum_stay VARCHAR(100) NULL,
    payment_methods VARCHAR(150) NULL,
    visitor_policy VARCHAR(200) NULL,
    cooking_policy VARCHAR(200) NULL,
    pet_policy VARCHAR(150) NULL,
    contact_person VARCHAR(120) NULL,
    contact_phone VARCHAR(30) NULL,
    contact_social VARCHAR(200) NULL,
    viewing_hours VARCHAR(150) NULL,
    custom_details TEXT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    approval_status ENUM('pending', 'approved', 'rejected', 'revision_requested') NOT NULL DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_properties_search (approval_status, property_type, gender_policy, distance_to_campus_m),
    INDEX idx_properties_featured_type (approval_status, is_featured, property_type, barangay_id),
    CONSTRAINT fk_property_landlord FOREIGN KEY (landlord_id) REFERENCES landlord_profiles(landlord_id),
    CONSTRAINT fk_property_barangay FOREIGN KEY (barangay_id) REFERENCES barangays(barangay_id),
    CONSTRAINT fk_property_approver FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE landlord_permits (
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

CREATE TABLE property_images (
    image_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id BIGINT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    caption VARCHAR(120) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_image_property FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE amenities (
    amenity_id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    amenity_name VARCHAR(80) NOT NULL UNIQUE,
    scope ENUM('property', 'room') NOT NULL
) ENGINE=InnoDB;

CREATE TABLE property_amenities (
    property_id BIGINT UNSIGNED NOT NULL,
    amenity_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (property_id, amenity_id),
    CONSTRAINT fk_pa_property FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_amenity FOREIGN KEY (amenity_id) REFERENCES amenities(amenity_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rooms (
    room_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id BIGINT UNSIGNED NOT NULL,
    room_number VARCHAR(30) NOT NULL,
    floor_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
    room_type ENUM('solo', 'shared', 'studio') NOT NULL,
    max_capacity TINYINT UNSIGNED NOT NULL,
    monthly_rate_per_bed DECIMAL(10, 2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_property_room_number (property_id, room_number),
    INDEX idx_rooms_filter (room_type, monthly_rate_per_bed, is_active),
    CONSTRAINT chk_room_capacity CHECK (max_capacity > 0),
    CONSTRAINT chk_room_rate CHECK (monthly_rate_per_bed > 0),
    CONSTRAINT fk_room_property FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE room_amenities (
    room_id BIGINT UNSIGNED NOT NULL,
    amenity_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (room_id, amenity_id),
    CONSTRAINT fk_ra_room FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    CONSTRAINT fk_ra_amenity FOREIGN KEY (amenity_id) REFERENCES amenities(amenity_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- 3. BOOKMARKS, VISITS, RESERVATIONS & LEASES
-- ============================================================================

CREATE TABLE bookmarks (
    student_id BIGINT UNSIGNED NOT NULL,
    property_id BIGINT UNSIGNED NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id, property_id),
    INDEX idx_bookmarks_student_time (student_id, created_at),
    CONSTRAINT fk_bookmark_student FOREIGN KEY (student_id) REFERENCES student_profiles(student_id) ON DELETE CASCADE,
    CONSTRAINT fk_bookmark_property FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reservations (
    reservation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    request_type ENUM('ocular_visit', 'bed_reservation') NOT NULL,
    scheduled_at DATETIME NOT NULL,
    notes TEXT NULL,
    status ENUM('pending', 'approved', 'declined', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_res_room_status (room_id, status),
    CONSTRAINT fk_res_student FOREIGN KEY (student_id) REFERENCES student_profiles(student_id),
    CONSTRAINT fk_res_room FOREIGN KEY (room_id) REFERENCES rooms(room_id)
) ENGINE=InnoDB;

CREATE TABLE leases (
    lease_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NOT NULL,
    move_in_date DATE NOT NULL,
    move_out_date DATE NULL,
    agreed_monthly_rent DECIMAL(10, 2) NOT NULL,
    advance_rent_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    security_deposit_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    deposit_refunded_amount DECIMAL(10, 2) NULL,
    billing_due_day TINYINT UNSIGNED NOT NULL,
    lease_status ENUM('active', 'ended', 'terminated') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active_student_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN lease_status = 'active' THEN student_id ELSE NULL END
    ) VIRTUAL,
    UNIQUE KEY uq_one_active_lease (active_student_id),
    INDEX idx_leases_room_status (room_id, lease_status),
    INDEX idx_leases_student_status (student_id, lease_status),
    CONSTRAINT chk_lease_rent CHECK (agreed_monthly_rent > 0),
    CONSTRAINT chk_lease_advance CHECK (advance_rent_amount >= 0),
    CONSTRAINT chk_lease_deposit CHECK (security_deposit_amount >= 0),
    CONSTRAINT chk_lease_refund CHECK (deposit_refunded_amount IS NULL OR deposit_refunded_amount BETWEEN 0 AND security_deposit_amount),
    CONSTRAINT chk_lease_due_day CHECK (billing_due_day BETWEEN 1 AND 28),
    CONSTRAINT chk_lease_dates CHECK (move_out_date IS NULL OR move_out_date >= move_in_date),
    CONSTRAINT fk_lease_student FOREIGN KEY (student_id) REFERENCES student_profiles(student_id),
    CONSTRAINT fk_lease_room FOREIGN KEY (room_id) REFERENCES rooms(room_id)
) ENGINE=InnoDB;

-- ============================================================================
-- 4. BILLING, ITEMIZED UTILITIES & PAYMENTS
-- ============================================================================

CREATE TABLE rent_invoices (
    invoice_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lease_id BIGINT UNSIGNED NOT NULL,
    billing_month DATE NOT NULL COMMENT 'Normalized to the 1st of the billing month (YYYY-MM-01)',
    due_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lease_billing_month (lease_id, billing_month),
    INDEX idx_invoices_due_date (due_date),
    CONSTRAINT chk_billing_month CHECK (DAYOFMONTH(billing_month) = 1),
    CONSTRAINT fk_invoice_lease FOREIGN KEY (lease_id) REFERENCES leases(lease_id)
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
    item_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    charge_type ENUM('base_rent', 'water', 'electricity', 'wifi', 'penalty', 'adjustment') NOT NULL,
    description VARCHAR(200) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    INDEX idx_items_invoice (invoice_id),
    CONSTRAINT fk_item_invoice FOREIGN KEY (invoice_id) REFERENCES rent_invoices(invoice_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
    payment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    receipt_number VARCHAR(50) NULL UNIQUE,
    amount_paid DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'gcash', 'bank_transfer') NOT NULL,
    reference_number VARCHAR(100) NULL,
    proof_image_path VARCHAR(255) NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    verified_by BIGINT UNSIGNED NULL,
    paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    INDEX idx_payments_invoice_status (invoice_id, verification_status),
    CONSTRAINT chk_payment_amount CHECK (amount_paid > 0),
    CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES rent_invoices(invoice_id),
    CONSTRAINT fk_payment_verifier FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================================
-- 5. MAINTENANCE, ANNOUNCEMENTS, REPORTS & AUDIT TRAIL
-- ============================================================================

CREATE TABLE maintenance_requests (
    ticket_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lease_id BIGINT UNSIGNED NOT NULL,
    category ENUM('electrical', 'plumbing', 'furniture', 'internet', 'other') NOT NULL,
    description TEXT NOT NULL,
    photo_path VARCHAR(255) NULL,
    priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    ticket_status ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    CONSTRAINT fk_ticket_lease FOREIGN KEY (lease_id) REFERENCES leases(lease_id)
) ENGINE=InnoDB;

CREATE TABLE announcements (
    announcement_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    property_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcement_property FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reports (
    report_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id BIGINT UNSIGNED NOT NULL,
    property_id BIGINT UNSIGNED NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('open', 'reviewed', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
    reviewed_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_report_user FOREIGN KEY (reporter_id) REFERENCES users(user_id),
    CONSTRAINT fk_report_property FOREIGN KEY (property_id) REFERENCES properties(property_id) ON DELETE CASCADE,
    CONSTRAINT fk_report_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NOT NULL,
    action_type VARCHAR(80) NOT NULL,
    target_table VARCHAR(60) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_actor_time (actor_id, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ============================================================================
-- 6. DATABASE TRIGGERS (BED CAPACITY & SINGLE-ACTIVE-LEASE ENFORCEMENT)
-- ============================================================================

DELIMITER $$

CREATE TRIGGER trg_leases_before_insert
BEFORE INSERT ON leases
FOR EACH ROW
BEGIN
    DECLARE v_max_capacity INT;
    DECLARE v_current_active INT;

    IF NEW.lease_status = 'active' THEN
        -- Serialize activations for this room and read the current occupancy.
        SELECT max_capacity INTO v_max_capacity
        FROM rooms
        WHERE room_id = NEW.room_id
          AND is_active = 1
        FOR UPDATE;

        IF v_max_capacity IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Target room does not exist or is inactive.';
        END IF;

        SELECT COUNT(*) INTO v_current_active
        FROM leases
        WHERE room_id = NEW.room_id
          AND lease_status = 'active'
        LOCK IN SHARE MODE;

        IF v_current_active >= v_max_capacity THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Room is already at maximum bed capacity.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_leases_before_update
BEFORE UPDATE ON leases
FOR EACH ROW
BEGIN
    DECLARE v_max_capacity INT;
    DECLARE v_current_active INT;

    -- The unique virtual key also covers changes to an active lease's student.
    IF NEW.lease_status = 'active' AND (OLD.lease_status <> 'active' OR NEW.room_id <> OLD.room_id) THEN
        SELECT max_capacity INTO v_max_capacity
        FROM rooms
        WHERE room_id = NEW.room_id
          AND is_active = 1
        FOR UPDATE;

        IF v_max_capacity IS NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Target room does not exist or is inactive.';
        END IF;

        SELECT COUNT(*) INTO v_current_active
        FROM leases
        WHERE room_id = NEW.room_id
          AND lease_status = 'active'
          AND lease_id <> OLD.lease_id
        LOCK IN SHARE MODE;

        IF v_current_active >= v_max_capacity THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Target room is already at maximum bed capacity.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_rooms_before_update
BEFORE UPDATE ON rooms
FOR EACH ROW
BEGIN
    DECLARE v_current_active INT;

    IF NEW.max_capacity < OLD.max_capacity OR (OLD.is_active = 1 AND NEW.is_active = 0) THEN
        SELECT COUNT(*) INTO v_current_active
        FROM leases
        WHERE room_id = OLD.room_id
          AND lease_status = 'active'
        LOCK IN SHARE MODE;

        IF NEW.max_capacity < v_current_active OR (NEW.is_active = 0 AND v_current_active > 0) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'End or transfer active leases before reducing capacity or deactivating this room.';
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_audit_before_update
BEFORE UPDATE ON audit_logs
FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit entries cannot be edited.'$$

CREATE TRIGGER trg_audit_before_delete
BEFORE DELETE ON audit_logs
FOR EACH ROW
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit entries cannot be deleted.'$$

DELIMITER ;

-- ============================================================================
-- 7. REAL-TIME SQL VIEWS (DERIVED VACANCY & INVOICE / OVERDUE ENGINE)
-- ============================================================================

CREATE OR REPLACE VIEW vw_room_vacancy AS
SELECT
    r.room_id,
    r.property_id,
    r.room_number,
    r.floor_number,
    r.room_type,
    r.max_capacity,
    r.monthly_rate_per_bed,
    r.is_active,
    COUNT(l.lease_id) AS occupied_beds,
    (r.max_capacity - COUNT(l.lease_id)) AS available_beds,
    CASE
        WHEN r.is_active = 0 THEN 'inactive'
        WHEN (r.max_capacity - COUNT(l.lease_id)) <= 0 THEN 'full'
        WHEN (r.max_capacity - COUNT(l.lease_id)) = 1 THEN 'few_slots_left'
        ELSE 'available'
    END AS vacancy_status
FROM rooms r
LEFT JOIN leases l
    ON l.room_id = r.room_id
   AND l.lease_status = 'active'
GROUP BY
    r.room_id,
    r.property_id,
    r.room_number,
    r.floor_number,
    r.room_type,
    r.max_capacity,
    r.monthly_rate_per_bed,
    r.is_active;

CREATE OR REPLACE VIEW vw_invoice_summary AS
SELECT
    ri.invoice_id,
    ri.lease_id,
    l.student_id,
    l.room_id,
    ri.billing_month,
    ri.due_date,
    COALESCE(charges.total, 0.00) AS total_billed,
    COALESCE(paid.total, 0.00) AS total_paid,
    COALESCE(charges.total, 0.00) - COALESCE(paid.total, 0.00) AS balance_due,
    CASE
        WHEN charges.total <= COALESCE(paid.total, 0.00) THEN 'paid'
        WHEN charges.total IS NOT NULL AND CURDATE() > ri.due_date THEN 'overdue'
        WHEN paid.total > 0 THEN 'partial'
        ELSE 'unpaid'
    END AS invoice_status
FROM rent_invoices ri
INNER JOIN leases l ON l.lease_id = ri.lease_id
LEFT JOIN (
    SELECT invoice_id, SUM(amount) AS total
    FROM invoice_items
    GROUP BY invoice_id
) charges ON charges.invoice_id = ri.invoice_id
LEFT JOIN (
    SELECT invoice_id, SUM(amount_paid) AS total
    FROM payments
    WHERE verification_status = 'verified'
    GROUP BY invoice_id
) paid ON paid.invoice_id = ri.invoice_id;

-- ============================================================================
-- 8. INITIAL SEED DATA (ROLES & MASTER AMENITIES)
-- ============================================================================

INSERT INTO roles (role_name) VALUES
    ('student'),
    ('landlord'),
    ('admin');

INSERT INTO amenities (amenity_name, scope) VALUES
    ('Free Wi-Fi', 'property'),
    ('CCTV Security', 'property'),
    ('Shared Kitchen / Cooking Area', 'property'),
    ('Laundry Area', 'property'),
    ('Study Lounge', 'property'),
    ('Drinking Water Dispenser', 'property'),
    ('Motorcycle / Bike Parking', 'property'),
    ('Own Comfort Room (CR)', 'room'),
    ('Air Conditioning', 'room'),
    ('Electric Fan Included', 'room'),
    ('Study Desk & Chair', 'room'),
    ('Personal Cabinet / Locker', 'room'),
    ('Sub-Metered Electricity', 'room');

-- Local barangays use the Philippine Statistics Authority PSGC names.
INSERT INTO municipalities (municipality_name, province_name) VALUES
    ('San Jose de Buenavista', 'Antique');

SET @san_jose = (SELECT municipality_id FROM municipalities
                WHERE municipality_name = 'San Jose de Buenavista' AND province_name = 'Antique');

INSERT INTO barangays (municipality_id, barangay_name) VALUES
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
