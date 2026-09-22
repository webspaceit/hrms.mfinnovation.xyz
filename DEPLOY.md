# Deployment Guide — Shared Hosting (no Node, no Composer)

This system is **pure PHP 8 + MySQL** — there is no build step, no package
manager, and no framework to install. Upload the files, import the SQL,
fill in one config file, and it runs.

---

## 1. Prerequisites

| Requirement | Notes |
|---|---|
| PHP 8.0+ | Any shared hosting in the last few years qualifies |
| MySQL 5.7+ / MariaDB 10+ | Standard cPanel/Plesk hosting |
| Apache with `mod_rewrite` | Default on most shared hosts — pretty URLs need it |
| Writable folders | `logs/`, `uploads/signatures/`, `uploads/tenants/` |

The installer (`install.php`), all `ajax/*.php` endpoints and all cron
scripts are plain files — nothing to compile or bundle.

---

## 2. Upload the files

1. Unzip the deployment package.
2. Upload **everything** (keeping the folder structure) into your web root
   (usually `public_html/` or `httpdocs/`) — either directly at the root or
   inside a sub-folder such as `public_html/rms/`.
3. The `.htaccess` file at the root **must be uploaded** — it is what makes
   the clean URLs work. Some FTP/SFTP clients hide dotfiles; make sure it
   is included.

---

## 3. Create the database

**Option A — phpMyAdmin (recommended):**
1. Create an empty database in your host's MySQL panel.
2. Open phpMyAdmin → select that database → **Import** → choose
   `database.sql`.
3. All tables + seed data are created.

**Option B — built-in installer:**
1. Open `https://yourdomain.com/install.php` (or `/rms/install.php`).
2. Click **Install Database**, then **Create Admin User**.

> **Security:** once setup is finished, **delete `install.php`** from the
> server (or protect it with an `.htaccess` password). It has no built-in
> auth — anyone who finds it can re-run it.

---

## 4. Configure the app

One file only:

```
cp config/config.production.example.php config/config.php
```

Then edit `config/config.php`:

- **DB_HOST / DB_USER / DB_PASS / DB_NAME** — from your MySQL panel.
- **BASE_URL** — the trickiest one; every link is built from it:
  - `'/'` for `example.com/`
  - `'/rms/'` for `example.com/rms/`
  - Keep the **trailing slash** — a missing `/` breaks all URLs.

Leave everything else as-is (error display is already off; logs go to
`logs/error.log`).

---

## 5. Folder permissions

Your hosting must let PHP write to:

- `logs/` — error log file is created here
- `uploads/signatures/` — owner signature image
- `uploads/tenants/` — tenant documents (NID / deed)

Typical shared hosting: `755` for directories and `644` for files already
works; if uploads fail, try `775` on those three directories (and keep the
included `uploads/tenants/.htaccess` which blocks PHP execution inside
that folder).

---

## 6. Clean URLs & the router

### How it works
- `index.php` is the **single front controller** — every page is a route:
  `payments`, `flats`, `tenant-payments`, …
- `app/routes.php` maps each route to a Controller method; Controllers
  render Views; Models/classes handle data.
- The rewrite hands anything that is not a real file/folder to the router:
  `example.com/payments` → `index.php?r=payments`.

### Apache (shipped — `.htaccess`)
The project's root `.htaccess` already contains everything required:

```apache
Options -MultiViews

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([a-zA-Z0-9_/-]+)/?$ index.php?r=$1 [L,QSA]
```

Two things to know:

1. **`Options -MultiViews` is REQUIRED.** Apache's content-negotiation can
   silently map `/payments` to a file named `payments.php` and bypass the
   router entirely. The system no longer ships those legacy files, so leave
   this line in place.
2. If your host reports *"RewriteEngine not allowed"*, they have
   `AllowOverride None` — ask support to enable `AllowOverride All` for
   your folder, or use the `<VirtualHost>` snippet below.

If you control the Apache config (VPS/dedicated), this is the equivalent
server block:

```apache
<Directory /var/www/html/rms>
    Options -MultiViews
    AllowOverride All
</Directory>
```

### nginx (if your host uses it)
Most nginx hosts still run PHP-FPM via a proxy — put this inside the
`server { }` block and restart:

```nginx
location / {
    try_files $uri $uri/ /index.php?r=$1;
}
```

### No rewrite at all (fallback)
The app **works without** rewriting too — URLs just become
`index.php?r=payments`. If your host cannot enable rewrites, every page
still functions via that form.

---

## 7. Scheduled task (auto invoice generation)

`cron/invoices.php` generates + auto-sends monthly invoices. Guard it with
the token you set in the invoice settings, and run it daily:

```cron
# cPanel cron — every day at 09:00 (auto-send only triggers on the
# first days of the month, before the configured deadline day)
0 9 * * * php /home/USER/public_html/rms/cron/invoices.php YOUR_TOKEN >> /home/USER/public_html/rms/logs/cron.log 2>&1
```

No CLI? Use the HTTP form (still token-guarded) — cPanel cron or an
external service like cron-job.org:

```
https://example.com/rms/cron/invoices.php?token=YOUR_TOKEN
```

Dry run (prints the plan without sending):

```
php cron/invoices.php YOUR_TOKEN --dry
```

---

## 8. PDF compression (optional)

Uploaded PDFs are re-compressed with Ghostscript when:

- PHP `exec()` is allowed, **and**
- Ghostscript is installed, **and**
- `GS_PATH` in config points to the binary.

On hosts that block `exec()` (common on cheap shared hosting), leave
`GS_PATH = ''` — documents upload uncompressed, nothing breaks.

---

## 9. Upgrade an existing install

If you already had the pre-MVC version:

1. **Migrate data first** — your old `database.sql`-schema database works
   as-is; run `portal_upgrade.sql` only if you upgraded from a version
   before the tenant portal (it adds tenant-portal columns/tables).
2. Then replace the files with this package (keep `config/config.php`,
   `uploads/`, `logs/`).
3. The old page URLs (`payments.php`, `flats.php`, …) are gone — bookmark
   the new clean ones (`/payments`, `/flats`, …). Bookmarks pointing at
   the old files return 404 by design.

---

## 10. Go-live checklist

- [ ] Database imported and `config/config.php` filled in (BASE_URL exact)
- [ ] Admin user created (`install.php` or SQL insert) — `install.php` deleted after
- [ ] `logs/` and `uploads/*/` writable
- [ ] `.htaccess` uploaded; `/payments` and `/flats` open clean URLs
- [ ] `cron/invoices.php` scheduled with its token
- [ ] Test: bilingual switch works, receipt/invoice print fine
- [ ] Non-Apache hosts: rewrite block applied (or accept `index.php?r=` URLs)