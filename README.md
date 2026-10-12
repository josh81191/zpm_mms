# ZPM Registry & GIS

This repository contains a locally runnable development demo of the ZPM Registry, Political Analysis, and GIS Decision Support System. It includes secure authentication, role-based authorization, PostgreSQL/PostGIS migrations, organizational hierarchy, registered-individuals workflows, audit logging, reports and CSV export, and aggregate GIS visualization.

## Prerequisites

- XAMPP with Apache and PHP 7.4 or later.
- PHP extensions enabled in `C:\xampp\php\php.ini`: `pdo_pgsql`, `pgsql`, `mbstring` and `curl`.
- PostgreSQL 10 or later with PostGIS installed.

Use a dedicated PostgreSQL application account with only the database privileges the application requires. Do not use the PostgreSQL superuser for web requests.

## Local setup

1. Create an empty PostgreSQL database named `zpm_membership` and an application account.
2. Copy `config/config.example.php` to `config/config.php`, then set the local database credentials. The real configuration is ignored by Git. Keep the database schema set to `zpm_mms`.
3. Enable Apache `mod_rewrite` and set the XAMPP virtual host document root to this repository's `public` directory. If using the default XAMPP alias instead, browse to `http://localhost/6_ZPM_nunna_bu/public`.
4. Run the migration command:

   ```powershell
   C:\xampp\php\php.exe scripts\migrate.php
   ```

5. Create the first administrator. The password prompt is visible in the default Windows terminal, so perform this step in a private terminal:

   ```powershell
   C:\xampp\php\php.exe scripts\create-user.php
   ```

6. Start Apache and open the configured local URL. Sign in with the administrator account you created.

For a standalone VS Code preview without Apache, run this from the project directory:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8097 scripts\dev-router.php
```

Then open `http://127.0.0.1:8097/6_ZPM_nunna_bu/public/login`.

## Operational demo routes

- `/dashboard` — live organization summary
- `/individuals` — searchable, paginated register of registered individuals with party/classification filters and create, edit, and archive workflows
- `/organization` — hierarchy register and unit creation
- `/reports` — date/status/district filters, trends, quality checks, and CSV export
- `/gis` — Leaflet map of aggregate unit reference points with party and classification counts
- `/audit` — authorized audit history

### Development demo accounts

For local testing only, create four role-specific demo accounts:

```powershell
C:\xampp\php\php.exe scripts\create-demo-users.php
```

The script runs only when `environment` is `development`, is idempotent, and must never be run in production. It creates the credentials listed below:

| Role | Email | Password |
| --- | --- | --- |
| System Administrator | `admin@zpm.test` | `ZpmDemo!2026Admin` |
| Authorized Central Office User | `central.office@zpm.test` | `ZpmDemo!2026Central` |
| Data Entry Operator | `data.entry@zpm.test` | `ZpmDemo!2026Data` |
| Read-Only User | `readonly@zpm.test` | `ZpmDemo!2026Read` |

### Development organization and GIS demo data

After creating the demo users, run the migration and seed a synthetic dataset:

```powershell
C:\xampp\php\php.exe scripts\migrate.php
C:\xampp\php\php.exe scripts\seed-demo-data.php
```

The seed creates 11 Mizoram district reference locations, 22 clearly labelled demo organizational blocks, 55 named locality/village units, and 1,200 synthetic registered individuals with a demo party mix and Green/Yellow/Red classifications for non-ZPM individuals. The records are fictional and contain no EPCI data. Unit points are synthetic reference points for development-only GIS aggregation; they are not approved administrative boundaries or authoritative coordinates.

## Initial roles

The migration creates and uses the dedicated `zpm_mms` PostgreSQL schema. Application tables and migration history are not created in `public`. The migration provides four proposed roles: `system-administrator`, `central-office`, `data-entry`, and `read-only`. Their server-side permissions are stored in `roles`, `permissions`, and `role_permissions`; links and visual controls are not relied upon for authorization.

## Reporting milestone

The current reporting milestone provides:

- Filterable totals by registration date, status, district, party, and classification, plus party and classification breakdowns.
- Active, inactive/departed, and recent-enrollment indicators.
- District comparison tables and enrollment trend summaries.
- Data-quality checks for empty units, active individuals in inactive units, missing EPCI values, and possible duplicate groups.
- Authorized CSV export including party and classification.
- An administrator-only audit-log view with login events.

Reporting uses recorded data only. Classification is entered manually (who/when is recorded in `party_classification_history`); it is never inferred. Map classification counts are hidden for units with fewer than 5 non-ZPM individuals.

## Security notes

- All state-changing browser requests include CSRF protection.
- Passwords are stored using PHP's `password_hash`; plaintext passwords are never stored.
- Authentication rotates the session identifier on sign-in and uses `HttpOnly`/`SameSite=Strict` cookies.
- Database queries use native PDO prepared statements.
- Errors shown in the browser never contain database details or stack traces.
- `config/config.php` must remain outside the web document root and must never be committed.

## Validation

Run syntax checks after enabling PHP:

```powershell
Get-ChildItem app, public, scripts -Filter *.php -Recurse | ForEach-Object {
    & C:\xampp\php\php.exe -l $_.FullName
}
```

Database-backed login validation requires the PostgreSQL driver and a configured local database, so it must be performed after completing the setup steps above.

## Party and classification model

- Every registered individual has a party: ZPM, MNF, Congress, BJP, or Other (with a party name).
- Individuals whose party is not ZPM must carry a classification: Green, Yellow, or Red. ZPM individuals have none. This rule is enforced both in the application and by a database constraint (migration 004).
- The definition of each colour is to be confirmed by the project owner.

## Party migration chart

The dashboard shows net monthly party change for the past 12 months across all Mizoram, from party_classification_history. For demo data run:

    C:\xampp\php\php.exe scripts\seed-party-movement.php

The script is idempotent and development-only.

## Families

An individual can be tied to a family head (another registered individual) from the register form. Only one level is allowed: a head has no head of their own, and a head with linked members cannot be archived or tied to another head. Rules are enforced in the application and by a database trigger (migration 005). Demo families:

    C:\xampp\php\php.exe scripts\seed-demo-families.php

## PHP, database and hosting compatibility

The application runs on **PHP 7.4 or later** (PHP 8.x also works) and PostgreSQL 10 or later with PostGIS. Avoid PHP 8-only syntax when contributing; `app/polyfills.php` provides `str_starts_with`, `str_ends_with` and `str_contains`.

## GIS map

`/gis` shows a party-population pie marker for each district or, after switching the level, each block. There is no unit-level data. Party counts under 5 are suppressed. Choosing a district or block zooms to it and shows its figures. The demo geometry is a set of reference points, not administrative boundaries.

## Registered individuals filters

`/individuals` can be filtered by district, block and unit (cascading selects) as well as by search text, party, classification, status and family role.

## AI analysis

The Analysis & Reports page can answer aggregate questions in plain language. It calls any OpenAI-compatible chat API configured in the `ai` block of `config/config.php`:

    'ai' => [
        'enabled'  => true,
        'base_url' => 'https://api.groq.com/openai/v1',   // or http://localhost:11434/v1 for Ollama
        'model'    => 'openai/gpt-oss-20b',               // must be a model your key can access
        'api_key'  => '',
        'timeout'  => 40,
    ],

Only the question, a data-free schema and aggregate counts (small groups suppressed) are sent. Member records are never sent. If a request fails, the cause appears in the browser console (`AI analysis error: ...`) and the PHP error log. Using a third-party provider needs the project owner's explicit approval.

## Deployment

See `deploy/DEPLOYMENT.md` and the files in `deploy/`:

- `deploy/init.sql` creates the complete schema on a fresh database: `psql -U <user> -d <database> -f deploy/init.sql`.
- `deploy/config.production.example.php` is the production configuration template.
- `deploy/data.sql` (local export of the demo data, gitignored because it contains personal data and password hashes) can be copied to the server and loaded with `psql -f`. It truncates the application tables first.
- On shared hosting, select PHP 7.4 or later and enable `pdo_pgsql`, `pgsql`, `mbstring` and `curl`. Set `environment` to `production` and `base_path` to the URL path of the site.