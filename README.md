# Rent Management System (OOP PHP + AJAX)

A lightweight, bilingual (English / Bengali) rent management system built with object-oriented PHP 8 and AJAX.

## Features

- **Nice Dashboard** — stat cards, Chart.js bar & doughnut charts, recent payments, available flats
- **Buildings & Flats (Units)** — manage buildings, floors, bedrooms/bathrooms, rent & advance amounts, status tracking (available / occupied / maintenance)
- **Tenants** — contact info, NID, phone, email, status
- **Leases (Rent Agreements)** — tenant ↔ flat assignment, start/end dates, rent + utility fee, auto-expiry
- **Rent Collection (Payments)** — record monthly payments, duplicate detection, cash / bank / bKash / Nogod / Rocket
- **Expenses** — category-based expense tracking
- **Reports** — yearly income/expense comparison chart + per-building income
- **Bilingual UI** — full English ↔ Bengali switch (session + cookie based)
- **AJAX everywhere** — add/edit/delete with modals, no page reloads
- **Lightweight CSS** — Tailwind CSS (CDN runtime) + a custom stylesheet
- **Auth system** — hashed passwords (bcrypt), session-based login

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10+
- A web server (WAMP / XAMPP / Apache)

## Setup

1. Place this folder in your web root, e.g. `E:\wamp64\www\oop-rms`
2. Make sure MySQL is running
3. Open the installer in your browser: `http://localhost/oop-rms/install.php`
4. Click **Install Database** (creates database `oop_rms` + all tables)
5. Enter your admin username/password and click **Create Admin User**
6. Login and start using the system

Alternatively run `database.sql` manually via phpMyAdmin, then add a user with:
`INSERT INTO users (username, email, password, full_name) VALUES ('admin','admin@rms.com', PASSWORD_HASH_HERE, 'System Admin');`

## Default Structure

```
oop-rms/
├── index.php          # Dashboard
├── buildings.php      # Buildings list + CRUD modals (AJAX)
├── flats.php          # Flats list + CRUD modals (AJAX)
├── tenants.php        # Tenants list + CRUD modals (AJAX)
├── leases.php         # Leases list + CRUD modals (AJAX)
├── payments.php       # Rent collection (AJAX)
├── expenses.php       # Expenses + CRUD modals (AJAX)
├── reports.php        # Yearly income/expense report
├── login.php          # Login page
├── logout.php
├── install.php        # Installer (DB + admin setup)
├── database.sql       # Schema + seed data
├── config/config.php  # Database & app config
├── classes/           # OOP classes (Database, BaseModel, Auth, Lang, models...)
├── lang/              # en.php + bn.php translations
├── ajax/              # All AJAX endpoints
├── inc/               # header.php / footer.php templates
└── assets/            # CSS & JS
```

## Usage Notes

- Modify `config/config.php` if your MySQL credentials differ (WAMP default is root / empty password).
- Change `BASE_URL` if the project is deployed at a different path.
- The admin "total_flats" summary on many views is computed live, not from the stored column.
- Leases auto-expire on each page load when `end_date` passes.
- Payments reject duplicates for the same lease + month + year.

### Invoice Parking/Gas/Water/Waste Bills

Invoices now support Parking, Gas, Water, and Waste Management bill amounts. If upgrading from an older version, add these columns to your existing `invoices` table:

```sql
ALTER TABLE `invoices` ADD `parking_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `utility_fee`;
ALTER TABLE `invoices` ADD `gas_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `parking_amount`;
ALTER TABLE `invoices` ADD `water_fee` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `gas_amount`;
ALTER TABLE `invoices` ADD `waste_fee` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `water_fee`;
```