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
- **Due-date reminders** — automatic WhatsApp / email / SMS / browser-push reminders before and after the due date, with a per-invoice log so nobody is notified twice
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

Alternatively run `database.sql` manually via phpMyAdmin, then open
`install.php` again — it will skip the database step (the tables are
already there) and go straight to **Create Admin User**.

> **Table prefix:** all tables use the `wsit_` prefix (e.g. `wsit_invoices`).
> It is applied automatically at runtime from `DB_PREFIX` in `config/config.php`
> — the PHP code keeps writing plain names (`users`, `invoices`, …). If you
> change the prefix, rename the physical tables to match or set `DB_PREFIX = ''`.

## Deployment (shared hosting)

```
php tools/build_deploy.php
```

produces `oop-rms-deploy.zip` — the app with a production
`config/config.php`, no dev credentials, no logs and no uploaded documents.
See **`DEPLOY.md`** for the full step-by-step guide: upload, database,
config, permissions, Apache/nginx rewrite rules, scheduled invoice cron,
upgrading an existing install and a go-live checklist. Production config
template: `config/config.production.example.php`.

## Structure (MVC)

```
oop-rms/
├── index.php              # Front controller / router (entry point)
├── .htaccess              # Rewrites /payments -> index.php?r=payments (Options -MultiViews!)
├── install.php            # Standalone installer (DB + admin) — delete on production
├── file.php               # Authenticated download proxy for uploads/
├── database.sql           # Full schema + default settings (fresh install)
├── db_alter_upgrade.sql   # ALTER-only schema upgrade for existing databases
├── config/
│   ├── config.php                     # Local config (DB, BASE_URL, session, uploads)
│   └── config.production.example.php  # Template for live servers
├── tools/
│   └── build_deploy.php    # Builds oop-rms-deploy.zip (dev only, never shipped)
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

## Install as a Mobile App (PWA)

The app is already installable — no separate mobile codebase. On Android Chrome / desktop
Chrome / Edge, open the site and use **Install app** (or the install banner the app shows).
On iPhone/iPad, open it in Safari → **Share** → **Add to Home Screen**. It then opens
full screen with the RMS icon, keeps its own splash colour, and shows a proper offline
screen instead of a browser error.

| File | Role |
|---|---|
| `manifest.php` | Web app manifest (name, icons, colours, shortcuts). Generated in PHP so it works in a sub-folder and gets the `application/manifest+json` type without server config. |
| `sw.js` | Service worker. Network-first for page loads (never caches private HTML), cache-first for `assets/`, offline fallback page, and never touches `ajax/`, `file.php`, `install.php`, `cron/`. |
| `offline.html` | Bilingual offline screen, self-contained (no network, no CSS dependency). |
| `assets/js/pwa.js` | Registers the service worker, shows the install banner, handles the iOS "Add to Home Screen" hint and update notices. |
| `app/Views/partials/pwa_head.php` | The `<link rel="manifest">`, `theme-color`, Apple meta and icons — one include shared by the admin, tenant, login and landing layouts. |
| `assets/icons/*` | 192/512 icons, maskable variants and the 180px `apple-touch-icon`. Rebuild with `python tools/make_icons.py` (needs Pillow). |

Notes:

- Service workers require **HTTPS** (or `localhost`). Installability disappears on plain
  `http://` domains, so put the live site behind a certificate (Let's Encrypt is enough).
- After deploying, bump `VERSION` in `sw.js` so clients drop the old cache.
- Test with Chrome DevTools → **Application → Manifest / Service Workers**, and
  **Lighthouse → Installable**.
- Page HTML is deliberately never cached: tenant invoices and payments are private, and a
  cached page on a shared phone would outlive a logout.

## Due-date Reminders

Unpaid invoices get automatic reminders on a schedule you control
(**Settings -> Rent Reminders**): 3 days before the due date, on the due date,
then every 7 days, up to 3 reminders - all four numbers are editable.

Each reminder goes out through the **first channel that can reach the tenant**:

```
push  >  whatsapp  >  email  >  sms
```

Push is free, WhatsApp/SMS are not, so the order matters. A tenant with no push
subscription still gets WhatsApp or email instead of silence.

| File | Role |
|---|---|
| `classes/Reminder.php` | Schedule (slots), candidate query, channel fallback, message text, dedupe. |
| `classes/WebPush.php` | RFC 8291 payload encryption + RFC 8292 VAPID JWT, pure PHP + OpenSSL. |
| `classes/PushSubscription.php` | Browser endpoints per tenant (several devices allowed). |
| `cron/reminders.php` | Daily runner: `php cron/reminders.php <token>` (also over HTTP). |
| `ajax/push_subscribe.php`, `ajax/push_unsubscribe.php` | Tenant opt-in endpoints. |
| `ajax/reminder_settings.php` | Admin settings, VAPID key generation, test notification. |
| `assets/js/push.js` | "Turn on reminders" card on the tenant dashboard. |
| `db_reminders_upgrade.sql` | Upgrade for existing databases. |

Why it never double-sends: `wsit_invoice_reminders` has a UNIQUE key on
`(invoice_id, stage, channel)`, and `INSERT IGNORE` plus an `alreadySent()`
check make the cron idempotent - run it twice a day and the tenant still gets
one message per stage.

Tests (dev only, no network needed):

```bash
php tools/webpush_check.php                     # RFC 8291 vector + ES256 verify
php tools/remrun.php --dry                      # print today's reminder plan
node tools/fake_push_server.js %TEMP%/sub.json  # decrypts a real payload
```

Push requires **HTTPS** (or `localhost`). iOS needs 16.4+ and the app added to
the home screen; Android Chrome works everywhere.

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
