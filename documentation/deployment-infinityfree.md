# InfinityFree Deployment Guide

## 0. Status

The application has been validated locally only. **InfinityFree compatibility has not been tested.** This guide is the deployment procedure and acceptance checklist. Do not state that ICOTAS “works on InfinityFree” until the verification section has been completed on a real account and the evidence is recorded.

## 1. Hosting requirements

| Requirement | Why |
|---|---|
| PHP 8.0 or newer (target 8.4) | Typed properties, `str_starts_with`, `match`-free code, PDO |
| `pdo_mysql`, `openssl`, `mbstring`, `json` | Database, token encryption, text handling |
| MySQL 5.7+ / MariaDB 10.3+ | Schema features, `utf8mb4` |
| Apache with `.htaccess`/`mod_rewrite` | Front controller and source protection |
| Writable `config/` and `storage/` during install | `local.php`, lock, logs |
| HTTPS (recommended) | Secure session cookies, student QR links |
| No SSH or cron required | Installer is browser-based; statuses refresh lazily |

InfinityFree provides a control panel, phpMyAdmin, FTP/File Manager, and selectable PHP versions. Confirm the exact PHP version and extensions in the panel before installing.

## 2. Build the deployment package

From the repository root, create a zip containing the runtime application but **not** local secrets, Git metadata, tests, tools, or runtime logs.

Include:

```
index.php
install.php
.htaccess
app/
assets/            (including vendor, css, js, img; exclude uploads content)
config/app.php
config/database.php
config/local.sample.php
config/.htaccess
database/schema.sql
database/.htaccess
storage/.htaccess
storage/cache/.gitkeep
storage/logs/.gitkeep
storage/reports/.gitkeep
documentation/     (optional; protected by .htaccess)
```

Exclude:

```
.git/
.freebuff/
config/local.php
config/local.php.tmp
storage/installed.lock
storage/logs/*
storage/cache/*
storage/reports/*
tests/
tools/
unit/
dist/
```

A prepared package is produced as `dist/ICOTAS-InfinityFree-v2.0.0.zip` when the release step is run. Verify its hash before upload and keep the hash with the deployment record.

## 3. Create the database

1. Sign in to the InfinityFree control panel.
2. Open **MySQL Databases**.
3. Create a database and note the **exact** values:
   - MySQL host (often `sqlXXX.infinityfree.com`, **not** `localhost`)
   - Database name (usually prefixed with your account ID)
   - Database username
   - Database password
   - Port (usually `3306`)
4. Open phpMyAdmin once to confirm access.

The installer can auto-create a database only on `localhost`, `127.0.0.1`, or `::1`. On InfinityFree, create it here first.

## 4. Upload the application

1. Open **File Manager** or connect with FTP.
2. Enter `htdocs/` (the document root).
3. Upload the contents of the package so that `htdocs/index.php` and `htdocs/install.php` exist at the top level.
4. Preserve `.htaccess` files. File Manager may hide dotfiles; enable “show hidden files” and verify:
   - `htdocs/.htaccess`
   - `htdocs/app/.htaccess`
   - `htdocs/config/.htaccess`
   - `htdocs/database/.htaccess`
   - `htdocs/storage/.htaccess`
   - `htdocs/documentation/.htaccess` (if documentation is included)
5. Do not upload `config/local.php` from your local machine. It must be generated on the server or created manually with the server's credentials.

Typical permissions: directories `755`, files `644`. The installer may set `config/local.php` to `0640`; if the host does not allow that, ensure it is at least not world-readable and never web-served.

## 5. Select PHP version

In the control panel, choose the highest available PHP 8.x version. The code targets 8.4 but is written to run on 8.0+. If the host offers only PHP 7.x, do not deploy; the code requires PHP 8.

## 6. Run the browser installer

1. Visit `https://your-domain/install.php`.
2. Confirm all server requirements show OK.
3. Enter:
   - DB host, port, database name, username, password (exact control-panel values)
   - Application URL: `https://your-domain` (recommended, no trailing slash)
   - Institution and system names
   - First Dean: first name, last name, username, e-mail, strong password (12+ characters, 3 character classes)
4. Submit.
5. The installer:
   - Imports `database/schema.sql` (43 tables).
   - Creates the Dean if no Dean exists.
   - Seeds 7 survey templates and the ISO/IEC 25010 UAT form.
   - Writes `config/local.php` with a random application key.
   - Writes `storage/installed.lock`.
6. If it reports a warning about default definitions, sign in and create them in the interface; the database is still installed.

### If the installer cannot write files

The host may block PHP file writes. Use the manual fallback below. Do not repeatedly rerun the installer against a partially imported database.

## 7. Post-installation (mandatory)

1. **Delete `install.php`.** The lock is a fallback, not a substitute.
2. Verify `config/local.php` exists and returns **403** when requested in a browser.
3. Verify `storage/installed.lock` exists and is not downloadable.
4. Request `https://your-domain/config/local.php` and `https://your-domain/database/schema.sql`; both must be denied.
5. Request `https://your-domain/app/` and `https://your-domain/storage/`; both must be denied (403/404).
6. Sign in as the Dean.
7. Confirm **Demo Data** shows demo mode OFF and no demo records (the installer does not create demo data).
8. Set the institution name, current academic year, and current semester.
9. Create or import real users.
10. Replace/remove any test accounts and set the privacy threshold to at least 3.
11. Configure HTTPS and confirm the session cookie is secure.

## 8. Manual installation fallback

Use this if the browser installer is blocked or PHP cannot write `config/local.php`.

### 8.1 Create the database and import the schema

1. Create the database in the control panel.
2. Open phpMyAdmin for that database.
3. Import `database/schema.sql`.
4. Confirm 43 tables and the four roles (`dean`, `academic_head`, `instructor`, `student`) exist.

### 8.2 Create `config/local.php`

Copy `config/local.sample.php` to `config/local.php` on the server (or create the file with the same structure) and fill in:

```php
<?php
return [
    'app' => [
        'key' => '<64 random hex characters>',
        'debug' => false,
        'base_url' => 'https://your-domain',
        'environment' => 'production',
    ],
    'database' => [
        'host' => 'sqlXXX.infinityfree.com',
        'port' => 3306,
        'database' => 'your_full_database_name',
        'username' => 'your_database_user',
        'password' => 'your_database_password',
        'charset' => 'utf8mb4',
    ],
];
```

Generate the key on your own computer with PHP:

```powershell
C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Do not reuse the sample key. Do not commit or share the real key.

### 8.3 Create the first Dean

Generate a password hash locally:

```powershell
C:\xampp\php\php.exe -r "echo password_hash('ReplaceWithAStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
```

In phpMyAdmin → SQL, insert the Dean (replace the placeholder hash and names):

```sql
INSERT INTO users
  (role_id, username, email, password_hash, first_name, last_name,
   status, is_demo, password_changed_at, created_at, updated_at)
SELECT id, 'dean', 'dean@example.edu', '$2y$10$REPLACE_WITH_GENERATED_HASH',
       'First', 'Last', 'active', 0, NOW(), NOW(), NOW()
FROM roles WHERE slug = 'dean';
```

Verify `SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'dean';` returns 1.

### 8.4 Seed or create definitions

The standard survey templates and ISO/IEC 25010 form are definitions, not demo data. Either:

- Sign in and create surveys/templates in the interface, or
- If the host has shell access (InfinityFree does not), run `php tools/seed_defaults.php`.

`tools/` is excluded from the hosting package. On InfinityFree, use the interface.

### 8.5 Finish manual installation

1. Delete `install.php` (or create `storage/installed.lock` **and** delete the installer; deleting is safer).
2. Sign in at `/login`.
3. Set the institution and academic period settings.
4. Run the post-install checks in section 7.
5. Record the manual steps in the deployment log.

## 9. Verification checklist (record evidence)

Perform on the live host and save the output/screenshots:

- [ ] `GET /` returns 200.
- [ ] `GET /login` returns 200.
- [ ] Dean login succeeds; dashboard loads.
- [ ] Create a department, program, academic year, semester, course, section.
- [ ] Create a survey, publish it, create an assignment.
- [ ] Create a QR session; session page shows QR and manual code.
- [ ] Print poster opens; PNG download works in a desktop browser.
- [ ] Student login; manual code opens the evaluation; submit one response.
- [ ] Reopen the link: duplicate guard appears.
- [ ] Instructor login: own dashboard loads; dean URL returns 403.
- [ ] Analytics and AI pages load without errors.
- [ ] CSV and PDF exports download and open.
- [ ] UAT survey creation and one invitation submission work.
- [ ] `GET /config/local.php`, `/database/schema.sql`, `/storage/`, `/app/` are denied.
- [ ] `install.php` is absent (404).
- [ ] Error log is not web-readable.
- [ ] Demo mode is OFF; no demo accounts remain.
- [ ] Record PHP version, MySQL version, and host response headers.

## 10. InfinityFree-specific caveats

| Caveat | Impact / action |
|---|---|
| No SSH / cron | Use the browser installer; statuses refresh lazily. Do not plan shell-based maintenance. |
| MySQL host is not `localhost` | Use the exact host from the control panel; the installer will not auto-create the DB. |
| File/inode limits | Upload only the runtime package; do not upload `tests/`, `tools/`, `.git`, or logs. |
| PHP version selection | Choose PHP 8.x; verify extensions. |
| `.htaccess` may be ignored or restricted | Verify deny rules and rewrites immediately. If blocked, do not deploy until equivalent server config exists. |
| PHP file write restrictions | Use the manual fallback if `config/local.php` cannot be written. |
| Upload limits | The app accepts CSV up to 5 MB; the host may impose a lower `upload_max_filesize`. Adjust `config/app.php` or split the CSV. |
| No email | UAT invitations must be copied and sent manually; there is no password-reset e-mail. |
| Free SSL / HTTPS | Enable it; set `base_url` to the HTTPS URL; confirm secure cookies. |
| Shared infrastructure | Provider staff/other tenants are outside the application trust boundary. |
| Ads or injected content | Test the login and evaluation pages for host-injected scripts; CSP may block them. Do not weaken CSP without review. |
| No shell for `tools/seed_defaults.php` | Create templates in the interface or import seed SQL manually. |

## 11. Backup, rollback, and maintenance

### Before an update

1. Export the database via phpMyAdmin (SQL).
2. Download `config/local.php` to a secure offline location.
3. Download `storage/installed.lock` (optional metadata).
4. Keep the prior application zip.

### Rollback

1. Put the site in maintenance (replace `index.php` with a static notice or restrict access).
2. Restore the previous application files.
3. Restore the database dump if the schema changed.
4. Restore `config/local.php`.
5. Re-run the verification checklist.

### Routine

- Review audit logs for unexpected admin actions.
- Export and archive the database before schema changes.
- Keep PHP and the host panel current.
- Remove departed users or set them inactive.
- Do not leave demo data on a production site.

## 12. Package manifest and hash

The current release was built on 2026-11-10:

```
File: dist/ICOTAS-InfinityFree-v2.0.0.zip
Files: 176
Included: runtime files only (index.php, install.php, .htaccess, app/,
          assets/, config/, database/, documentation/, storage skeleton)
Excluded: config/local.php, storage runtime data, tests/, tools/, unit/,
          .git/, .freebuff/, dist/
```

The SHA-256 is written to `dist/ICOTAS-InfinityFree-v2.0.0.sha256` after the
archive is closed. A checksum cannot be embedded inside the archive it
describes, so the companion `.sha256` file is the authoritative record. Verify
before upload:

```powershell
Get-FileHash .\ICOTAS-InfinityFree-v2.0.0.zip -Algorithm SHA256
Get-Content .\ICOTAS-InfinityFree-v2.0.0.sha256
```

Keep the zip and checksum together in the deployment record. Regenerate the
checksum after any rebuild. Do not claim the package is deployment-verified
until section 9 is complete on InfinityFree.
