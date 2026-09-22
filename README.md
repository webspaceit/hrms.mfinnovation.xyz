# Rent Management System (OOP PHP + AJAX)

A lightweight, bilingual (English / Bengali) rent management system built with object-oriented PHP 8 and AJAX. The whole app runs on a **front controller** — every page is a route served by `index.php`, with clean URLs like `/payments`. No Node, no Composer — upload to any PHP 8 shared host and it runs.

## Features

- **Nice Dashboard** — stat cards, Chart.js bar & doughnut charts, recent payments, available flats
- **Buildings & Flats (Units)** — manage buildings, floors, bedrooms/bathrooms, rent & advance amounts, status tracking (available / occupied / maintenance)
- **Tenants** — contact info, NID, phone, email, status, uploaded documents
- **Leases (Rent Agreements)** — tenant ↔ flat assignment, start/end dates, rent + utility fee, auto-expiry
- **Rent Collection (Payments)** — record monthly payments, duplicate detection, cash / bank / bKash / Nogod / Rocket
- **Invoices** — auto-generated monthly invoices with utility/parking/gas/water/waste bills, WhatsApp-ready, printable + PDF download
- **Tenant Portal** — tenants log in to see their dues, invoices, receipts and profile
- **Expenses** — category-based expense tracking
- **Reports** — yearly income/expense comparison chart + per-building income
- **Bilingual UI** — full English ↔ Bengali switch (session + cookie based)
- **AJAX everywhere** — add/edit/delete with modals, no page reloads
- **Auth system** — hashed passwords (bcrypt), session-based login, CSRF tokens

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10+
- A web server — Apache recommended (mod_rewrite for clean URLs)

## Setup (local dev with WAMP/XAMPP)

1. Place this folder in your web root, e.g. `E:\wamp64\www\oop-rms`
2. Make sure MySQL is running
3. Open the installer: `http://localhost/oop-rms/install.php`
4. Click **Install Database** (creates database `oop_rms` + all tables)
5. Enter your admin username/password and click **Create Admin User**
6. Login and start using the system

Alternatively run `database.sql` manually via phpMyAdmin, then add a user with:
`INSERT INTO users (username, email, password, full_name) VALUES ('admin','admin@rms.com', PASSWORD_HASH_HERE, 'System Admin');`

## Deployment (shared hosting)

See **`DEPLOY.md`** — full step-by-step guide covering upload, database,
production config, folder permissions, Apache/nginx rewrite rules,
scheduled invoice cron and a go-live checklist. Production config
template: `config/config.production.example.php`.

## Structure (MVC)

```
oop-rms/
├── index.php              # Front controller / router (entry point)
├── .htaccess              # Rewrites /payments -> index.php?r=payments (Options -MultiViews!)
├── install.php            # Standalone installer (DB + admin) — delete on production
├── database.sql           # Schema + seed data
├── portal_upgrade.sql     # Upgrade for pre-tenant-portal databases
├── config/
│   ├── config.php                     # Local config (DB, BASE_URL, session, uploads)
│   └── config.production.example.php  # Template for live servers
├── app/
│   ├── Core/              # Router, Controller base, bootstrap (autoload, view())
│   ├── routes.php         # Route table: every page -> Controller method
│   ├── Controllers/       # Page logic (dashboard, payments, invoices, tenant portal…)
│   └── Views/             # Html templates (+ partials/ header/footer, receipt/)
├── classes/               # OOP classes (Database, BaseModel, Models, Auth, Helpers, Lang)
├── ajax/                  # Real JSON endpoints (building_save.php, invoice_generate.php, …)
├── cron/                  # invoices.php — scheduled invoice generation/send
├── lang/                  # en.php + bn.php translations
├── assets/                # CSS & JS
├── uploads/               # Signatures + tenant documents (writable)
└── logs/                  # error.log (writable)
```

Why are things at the project root? Because this is a plain PHP app with
no build step: the router (`index.php`), the rewrite rules (`.htaccess`),
the installer and the SQL dumps must sit at document root, and the web
server needs `assets/`, `ajax/`, `uploads/` reachable. The MVC code lives
in `app/`; `classes/` is the model layer kept from the original build.

## Usage Notes

- Modify `config/config.php` if your MySQL credentials differ (WAMP default is root / empty password).
- Change `BASE_URL` if the project is deployed at a different path (value must end with `/`).
- The admin "total_flats" summary on many views is computed live, not from the stored column.
- Leases auto-expire on each page load when `end_date` passes.
- Payments reject duplicates for the same lease + month + year.

### Invoice Parking/Gas/Water/Waste Bills

Invoices support Parking, Gas, Water, and Waste Management bill amounts. If upgrading from an older version, add these columns to your existing `invoices` table:

```sql
ALTER TABLE `invoices` ADD `parking_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `utility_fee`;
ALTER TABLE `invoices` ADD `gas_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `parking_amount`;
ALTER TABLE `invoices` ADD `water_fee` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `gas_amount`;
ALTER TABLE `invoices` ADD `waste_fee` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `water_fee`;
```