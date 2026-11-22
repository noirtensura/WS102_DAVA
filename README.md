# Dayon

Dayon is a PHP and MariaDB application for finding student boarding houses in San Jose de Buenavista, Antique, and managing rooms, leases, rent and maintenance.

## Run on XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. For a fresh installation, import [dayon_db.sql](database/dayon_db.sql) through [phpMyAdmin](http://localhost/phpmyadmin/). It creates the `dayon_db` database and loads the San Jose de Buenavista location and amenity catalogs.
3. To try the sample workflow, import [dayon_demo.sql](database/dayon_demo.sql) once into the fresh database. It seeds sample accounts, listings, requests, leases, invoices, receipts and tickets.
4. Open [Dayon](http://localhost/WS102_DAVA/) in the browser. If you rename the folder under `htdocs`, open the corresponding URL.

The default database connection uses XAMPP's `127.0.0.1:3306`, `root`, and a blank password. You can configure it using `DAYON_DB_HOST`, `DAYON_DB_PORT`, `DAYON_DB_NAME`, `DAYON_DB_USER`, and `DAYON_DB_PASSWORD`. Apache needs write access to `uploads/`. The app serves uploaded files through its access-controlled media route. The Apache configuration must honor the included `.htaccess` files.

The OpenStreetMap view requires an internet connection.

### Update an existing database

Import [san_jose_upgrade.sql](database/san_jose_upgrade.sql) to add the San Jose de Buenavista barangay catalog and relocate the original demo listings. The script uses the demo owner and original listing identifiers to update the sample records. Existing leases, bills, payments and accounts keep their records.

The barangay catalog follows the Philippine Standard Geographic Code. Campus distances use St. Anthony's College as the reference point. [PSA barangay catalog](https://psa.gov.ph/classification/psgc/barangays/0600613000) · [St. Anthony's College](https://www.sac.edu.ph/)

### Demo accounts

All five sample accounts use `DayonDemo2026!`.

| Account | Email |
| --- | --- |
| Student looking for a room | `student@dayon.test` |
| Student with an active lease | `tenant@dayon.test` |
| Verified landlord | `owner@dayon.test` |
| Landlord awaiting verification | `pending@dayon.test` |
| Administrator | `admin@dayon.test` |

For an installation with real data, import the schema and provision an administrator with a unique email and a PHP-generated password hash. In phpMyAdmin, use the `admin` role ID from `roles` when inserting the account into `users`. Public registration offers student and landlord accounts.

## Try the workflow

1. Browse the finder and use the budget, distance, room type, gender policy, barangay and amenity filters.
2. Sign in as the finder student to save and compare listings, then request an ocular visit or a bed hold.
3. Sign in as the landlord to approve requests and assign tenants from the Occupancy Board, then generate invoices and split water and electricity bills in Billing & Utilities.
4. Sign in as the active tenant to view itemized balances, submit payment proof, print existing verified receipts, and file a maintenance ticket.
5. Use the Admin Portal link on the login screen to review verification submissions, approve listings, and inspect occupancy by barangay and recent audit activity.

## Demo imagery

The sample listings and campus distances are illustrative. The [study-room hero](assets/images/san-jose-study.webp) and the `san-jose-*.webp` gallery assets in [assets/images](assets/images/) were created with built-in image generation using the supplied designs as references.

- The hero prompt requested a student bedroom in San Jose de Buenavista with a single bed, a natural oak desk, a central window, and warm neutral daylight.
- The gallery prompt requested six photos in the same style showing a bedroom, common lounge, solo student room, shared kitchen, housing exterior, and reading lounge.
