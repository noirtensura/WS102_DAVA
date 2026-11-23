# Dayon — Realistic Account Credentials (Antique)

All accounts below are pre-loaded in the local `dayon_db` database (seeded via [database/realistic_seed.sql](database/realistic_seed.sql)) with realistic Antique student, landlord/owner, and administrator data.

- **Student & Landlord Sign-In:** `http://localhost/WS102_DAVA/login.php`
- **Admin Portal Sign-In:** `http://localhost/WS102_DAVA/login.php?portal=admin`

---

## 1. Landlords / Property Owners

| Name | Email | Password | Mobile | Account Status | Owned Property & Realistic Data |
| --- | --- | --- | --- | --- | --- |
| **Elena Magbanua** | `elena.magbanua@gmail.com` | `ElenaStay2026!` | `0917-482-3190` | Verified | **Magbanua Residences & Student Boarding House** (*Barangay 8, San Jose de Buenavista*, 320 m from St. Anthony's College). Full necessities filled in (PLDT Fiber 300 Mbps, water included, ANTECO sub-meter, custom flood/tricycle info), 2 rooms (Shared ₱2,400/mo, Solo ₱3,900/mo), 1 active tenant, 1 pending visit request, and **3 verified optional Antique permits** (Barangay Clearance, BFP Fire Safety FSIC, Municipal Sanitary Permit). |
| **Ramon Villanueva** | `ramon.villanueva@gmail.com` | `RamonApt2026!` | `0928-615-4402` | Verified | **Villanueva Corner Apartments** (*District I Poblacion, Sibalom*, 220 m from University of Antique – Main). Modern studio apartments (Unit A ₱4,500/mo, Unit B ₱3,800/mo), own ANTECO meter, **No Curfew (24/7)**, private kitchenette, and **Barangay Clearance only** (no business permit). |
| **Marites Delgado** | `marites.delgado@gmail.com` | `MaritesHouse2026!` | `0956-739-8124` | Pending Admin Review | **Delgado Family Bedspace** (*Poblacion 2, Hamtic*, 290 m from UA Hamtic). Demonstrates **minimal landlord input** (only core essentials + 1 bedspace at ₱1,600/mo) and **zero permits uploaded**—ready for Admin to approve in 1 click without requiring a business permit. |

---

## 2. Students (House Hunters & Active Tenant)

| Name | Email | Password | Mobile | School ID & Program | Realistic Activity |
| --- | --- | --- | --- | --- | --- |
| **Joshua Bautista** | `joshua.bautista@sac.edu.ph` | `JoshuaSAC2026!` | `0915-824-6619` | `SAC-2024-0418` · BS Computer Science (Year 3)<br>Emergency: Lorna Bautista (Mother) · `0917-503-2291` | **House Hunter (San Jose / SAC)**: Has **3 bookmarked houses with personal notes** (Magbanua Residences, Campus View Study Pad, Balay Central) ready to compare side by side, plus a pending ocular visit request at Magbanua Residences Room 101. |
| **Clarisse Mendoza** | `clarisse.mendoza@ua.edu.ph` | `ClarisseUA2026!` | `0961-342-9078` | `UA-2025-1182` · BS Nursing (Year 2)<br>Emergency: Roberto Mendoza (Father) · `0920-811-4520` | **House Hunter (Sibalom / UA Main)**: Has **2 bookmarked Sibalom listings with notes** (Villanueva Corner Apartments & Sibalom Scholars Boarding House) ready for side-by-side comparison. |
| **Mark Angelo Soriano** | `mark.soriano@sac.edu.ph` | `MarkTenant2026!` | `0945-219-7730` | `SAC-2023-0892` · BS Accountancy (Year 4)<br>Emergency: Teresita Soriano (Mother) · `0918-442-1095` | **Active Student Tenant**: Active lease at **Magbanua Residences Room 101** (₱2,400/mo), current-month itemized bill (Base Rent + ANTECO electricity share), and a verified GCash payment (`DAYON-2026-0814`) with printable receipt. |

---

## 3. Administrator

| Name | Email | Password | Mobile | Role & Capabilities |
| --- | --- | --- | --- | --- |
| **Katrina Salcedo** | `katrina.salcedo@dayon-antique.ph` | `KatrinaAdmin2026!` | `0917-900-4410` | **Platform Administrator** — Sign in at `login.php?portal=admin` to approve Marites Delgado & **Delgado Family Bedspace** in 1 click (even with zero permits), verify optional Antique permits, feature listings, manage users/passwords/admins, and manage Antique barangays & amenities. |

---

## Original Built-In Quick Demo Accounts (`DayonDemo2026!`)

The original sample accounts also remain active and accessible from the **Quick Demo Accounts** box on the login screen:

| Role | Email | Password |
| --- | --- | --- |
| Student Finder | `student@dayon.test` | `DayonDemo2026!` |
| Active Tenant | `tenant@dayon.test` | `DayonDemo2026!` |
| Verified Landlord | `owner@dayon.test` | `DayonDemo2026!` |
| Pending Landlord | `pending@dayon.test` | `DayonDemo2026!` |
| Administrator | `admin@dayon.test` | `DayonDemo2026!` |

---

## Re-Seeding Realistic Data (If You Reset the Database)

```powershell
Get-Content database\realistic_seed.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4
```
