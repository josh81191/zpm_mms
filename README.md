# ZPM Membership Management

This repository contains the Phase 1 foundation for the ZPM Membership Management, Political Analysis, and GIS Decision Support System. It intentionally provides only the approved foundation: secure authentication, role-based authorization, migrations, audit-ready data structures, and a responsive application shell. Organizational records, member data, reports, and mapping begin in later phases.

## Prerequisites

- XAMPP with Apache and PHP 8.0 or later.
- PHP extensions enabled in `C:\xampp\php\php.ini`: `pdo_pgsql` and `pgsql`.
- PostgreSQL 14 or later. Install PostGIS before the Phase 5 GIS implementation.

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

## Initial roles

The migration creates and uses the dedicated `zpm_mms` PostgreSQL schema. Application tables and migration history are not created in `public`. The migration provides four proposed roles: `system-administrator`, `central-office`, `data-entry`, and `read-only`. Their server-side permissions are stored in `roles`, `permissions`, and `role_permissions`; links and visual controls are not relied upon for authorization.

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
