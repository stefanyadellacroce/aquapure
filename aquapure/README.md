# AquaPure Water Refilling Station Management System

AquaPure is a PHP/MySQL water-refilling station management system for Customer, Staff, and Admin users. This September 2026 interface update keeps the supplied application's PHP/XAMPP architecture, business workflows, database schema, and existing security controls.

The updated shared styles, navigation, mobile tables, forms, checkout, feedback, and printing cover all 28 existing screen templates. See [PAGE_COVERAGE.md](PAGE_COVERAGE.md), [UI_UX_MODERNIZATION_REPORT.md](UI_UX_MODERNIZATION_REPORT.md), and [CHANGES_MANIFEST.md](CHANGES_MANIFEST.md).

**Verification:** JavaScript syntax and source comparisons passed. PHP lint, database workflows, browser layouts, and print preview could not run in the implementation environment. [TEST_CHECKLIST.md](TEST_CHECKLIST.md) contains the exact local checks. The startup paths below were checked against the source configuration; a successful XAMPP launch is not claimed here.

## Requirements

- PHP 8.1 or newer
- MySQL 8.x or MariaDB (XAMPP is supported)
- PHP extensions: PDO MySQL, mbstring, fileinfo; cURL for optional PayMongo
- Apache
- Modern browser

Bootstrap 5.3 and Bootstrap Icons are included locally, so the normal UI does not depend on a CDN.

## Apply this UI update to an existing installation

1. Back up the current project folder and database.
2. Extract the archive's `aquapure` folder. Copy the updated project files into `C:\xampp\htdocs\aquapure`, preserving your existing `assets/uploads/`, local configuration, and Apache environment settings. Do not delete the existing project folder as a shortcut.
3. Keep the existing database. This UI update adds no migration and does not require a fresh SQL import.
4. Start Apache and MySQL, then open `http://localhost/aquapure/`. Use `Ctrl + F5` to refresh cached CSS/JavaScript. The full path must be `C:\xampp\htdocs\aquapure\index.php`, without an extra nested `aquapure` folder.
5. Run the PHP lint and workflow checks in `TEST_CHECKLIST.md` before signing off the update.

## Important: choose the correct database setup

### A. Fresh installation (no AquaPure data to preserve)

Use `database/aquapure.sql`.

The fresh installer is intentionally non-destructive: it does **not** drop an existing AquaPure database or its tables. If it detects existing core AquaPure tables, it stops and tells you to use the migration instead.

1. Put this project at:

   ```text
   C:\xampp\htdocs\aquapure
   ```

2. Start **Apache** and **MySQL** in XAMPP.
3. Open `http://localhost/phpmyadmin`.
4. Choose **Import** and import:

   ```text
   C:\xampp\htdocs\aquapure\database\aquapure.sql
   ```

5. Open:

   ```text
   http://localhost/aquapure/
   ```

### B. Older AquaPure database that needs the existing migration

Do **not** import the fresh installer over your existing database.

Only follow this section if the application reports that the existing schema is outdated/incomplete. An installation already using the supplied August migration needs no database change for this UI update.

1. In phpMyAdmin, export a backup first.
2. Click/select the exact AquaPure database you are already using.
3. Open the **SQL** tab or **Import** tab.
4. Run this file **once**:

   ```text
   database/migrations/20260818_upgrade_existing_aquapure.sql
   ```

5. Review the verification rows shown at the end of the migration.
6. Refresh AquaPure with `Ctrl + F5`.

The migration is designed to preserve existing data. It fixes known old-schema mismatches such as `products.availability` vs `products.status`, `payments.method` vs `payments.payment_method`, upgrades invitation storage, and adds missing support tables/fields where safe.

## Default database connection

The project defaults to XAMPP's common local settings:

```text
Host: 127.0.0.1
Port: 3306
Database: aquapure_db
Username: root
Password: (blank)
```

For a different setup, configure Apache/PHP environment variables instead of hardcoding production secrets:

```text
AQUAPURE_DB_HOST
AQUAPURE_DB_PORT
AQUAPURE_DB_NAME
AQUAPURE_DB_USER
AQUAPURE_DB_PASS
AQUAPURE_BASE_URL
AQUAPURE_APP_URL
```

Example if your existing database is named `aquapure` instead of `aquapure_db`:

```apache
SetEnv AQUAPURE_DB_NAME aquapure
```

Restart Apache after changing Apache environment settings.

## Demo accounts for a fresh import

All seeded demo accounts use:

```text
Password: AquaPure123!
```

| Role | Username / Email |
| --- | --- |
| Admin | `admin` / `admin@aquapure.local` |
| Staff | `staff1` / `staff1@aquapure.local` |
| Staff | `staff2` / `staff2@aquapure.local` |
| Customer | `angela@example.com` |

Change demo passwords before real deployment.

## Account and RBAC rules

### Customer

Public customer registration is available at `/register.php`. The Customer role is never read from a form or URL. Customer accounts cannot access Staff/Admin operations pages.

### Staff

Staff registration is invitation-only at `/staff/register.php`. The invitation must have been created by an active Admin, must belong to the Staff role, must match the invited email stored in the database, must be unexpired, unused, and not revoked.

### Admin

Additional Admin registration is invitation-only at `/admin/register.php`. The system has no public Admin role selector and no browser-controlled Admin role value. Fresh demo data includes an Admin so invitations can be created immediately.

### Invitation security

- Only an active Admin can create invitations.
- Role and recipient email are stored in the database.
- Only the token hash is stored permanently.
- Expiry choices are 24, 48, or 72 hours.
- Codes are single-use and revocable.
- The usable code/link is shown only immediately after generation.
- Invitation links place the token in the URL fragment (`#invite=...`), which is not sent to Apache in the initial HTTP request; JavaScript fills the registration code field and removes the fragment from the visible URL.
- The registration backend reads the account role and email from the locked database invitation, never from editable browser input.

## Security implemented

- `password_hash()` and `password_verify()`
- PDO native prepared statements
- server-side RBAC and active-account revalidation
- CSRF tokens on state-changing forms
- session ID regeneration after login and password changes
- strict cookie sessions with HttpOnly and SameSite=Lax; Secure is enabled automatically under HTTPS
- output escaping with `htmlspecialchars()`
- input validation and database length/value checks
- generic user-facing database errors; technical details go to PHP/Apache logs
- database transactions and row locks around orders, payments, inventory, invitations, and critical status changes
- protection against deactivating the last active Admin
- upload MIME/type validation and script execution blocking in the uploads directory
- Admin-only invitation, user, activity-log, and system-settings operations
- Staff cannot reactivate/deactivate customers by editing their details
- normal web requests never execute schema-changing DDL

## Main functions

### Customer

- registration, login/logout, profile update and password change
- product browsing and cart
- pickup/delivery checkout
- Cash, GCash, Maya, and optional PayMongo payment selection
- order tracking/history
- eligible order cancellation
- notifications

### Staff

- dashboard and operational metrics
- customer/walk-in customer records
- walk-in orders
- online order processing
- payment recording
- delivery updates
- product viewing
- inventory quantity updates
- transactions and basic reports

### Admin

- all Staff-authorized operations
- product CRUD and availability
- system-user management and account activation/deactivation
- secure Staff/Admin invitations
- inventory item CRUD
- activity logs
- station settings
- full reports

## Optional PayMongo integration

PayMongo support remains optional. If no server-side key is configured, the Customer checkout UI does not offer PayMongo.

Configure only through a server environment variable:

```apache
SetEnv PAYMONGO_SECRET_KEY sk_test_your_test_key_here
SetEnv AQUAPURE_APP_URL http://localhost/aquapure
```

For live payments, use HTTPS and a live key. Do not put secret keys in JavaScript, HTML, or committed PHP files.

PayMongo is an external service and requires live/test-provider verification in your own environment; the included static test report does not claim a real remote charge was executed.

## Folder structure

```text
aquapure/
├── actions/
│   ├── customer.php
│   └── portal.php
├── admin/
├── assets/
│   ├── css/
│   ├── images/
│   ├── js/
│   ├── uploads/
│   └── vendor/
├── config/
│   ├── app.php
│   └── database.php
├── customer/
│   └── pages/
├── database/
│   ├── aquapure.sql
│   └── migrations/
│       └── 20260818_upgrade_existing_aquapure.sql
├── includes/
├── portal/
│   └── pages/
├── staff/
├── AUDIT_REPORT.md
├── TEST_CHECKLIST.md
├── index.php
├── login.php
├── register.php
├── logout.php
└── README.md
```

## Troubleshooting

### `Unknown column 'status' in 'where clause'`

Your live database is older than the PHP code. Back it up, select it in phpMyAdmin, and run the included existing-database migration once.

### `AquaPure database schema is outdated or incomplete`

This is the intentional schema guard. Run the migration on the selected existing AquaPure database. Do not edit PHP queries randomly to match one old table because that can create more mismatches elsewhere.

### UI still looks old

Press `Ctrl + F5`. Also make sure VS Code/Codex edited the same folder XAMPP serves:

```text
C:\xampp\htdocs\aquapure
```

### Database has another name

Set `AQUAPURE_DB_NAME` to the real database name or change only the local default in `config/database.php` if this is strictly a local student setup.

## Verification and handoff

See [TEST_CHECKLIST.md](TEST_CHECKLIST.md) for completed source checks, blocked runtime checks, and the precise XAMPP checkpoint. The CLI-only `tests/presentation_test.php` checks recovery scope, secret exclusion, and authoritative defaults without touching the database. It still needs execution with your PHP runtime.
