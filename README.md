# Dayon

Dayon is a PHP and MariaDB web application for finding student **boarding houses, apartments, dormitories, and bedspaces across Antique** (San Jose de Buenavista, Sibalom, Hamtic, and all 18 Antique municipalities), with flexible landlord listings, optional Antique permits, student bookmarking & side-by-side comparison, room/lease/billing tools, and an Admin Control Center.

## Run on XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. For a fresh installation, import [dayon_db.sql](database/dayon_db.sql), then [dayon_demo.sql](database/dayon_demo.sql), and [antique_upgrade.sql](database/antique_upgrade.sql) in phpMyAdmin.
3. For an existing `dayon_db` installation, import [antique_upgrade.sql](database/antique_upgrade.sql) once to add boarding house/apartment types, flexible optional necessities, optional Antique permits, bookmark notes, and Antique-wide municipalities.
4. Open [Dayon](http://localhost/WS102_DAVA/) in your browser.

## Demo Accounts (`DayonDemo2026!`)

| Role | Email | Purpose |
| --- | --- | --- |
| Student Finder | `student@dayon.test` | Browse, bookmark with personal notes, compare up to 4 listings, and request visits/bed holds |
| Active Tenant | `tenant@dayon.test` | View active lease, bills, receipts, maintenance requests, and advisories |
| Verified Landlord | `owner@dayon.test` | Post properties & multiple photos, manage rooms, optional Antique permits, leases, and billing |
| New Landlord | `pending@dayon.test` | Try posting a property immediately without requiring a business permit |
| Administrator | `admin@dayon.test` | Sign in via **Admin Portal** (`login.php?portal=admin`) |

## Key Capabilities

- **Landlord Account & Flexible Listing Builder**: Landlords register and immediately post boarding houses, apartments, dormitories, or bedspaces with **multi-photo drag-and-drop uploads**, photo labels, optional quick first room creation, utilities/policies, and custom necessities rows. Only Property Name, Type, and Barangay are required; blank fields stay hidden automatically on public pages.
- **Optional Antique Permits (Business Permit Not Required)**: Landlords can list and get approved with zero permits, or optionally add any Antique local permit (Barangay Clearance, Mayor's/BPLO Business Permit, BFP Fire Safety Certificate / FSIC, Municipal Sanitary Permit, Building/Occupancy Permit, DTI Registration, Gov ID, or Custom Local Permit) to earn verified public trust badges.
- **Student Bookmarking & Comparison**: Students can bookmark any boarding house or apartment directly from search cards or detail pages, save personal reminder notes, and compare up to 4 listings side by side.
- **Admin Control Center**:
  1. Approve landlord accounts (no permit required) and individually verify optional Antique permits.
  2. Review, approve (with 1-click "Verify Owner & Publish"), feature, or request revisions on property listings.
  3. Manage user accounts, reset passwords, and add new Administrator accounts directly in the UI.
  4. Maintain master Amenities/Necessities and Antique Barangays, resolve student reports, and review Audit Logs.
