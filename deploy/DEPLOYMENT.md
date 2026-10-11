# Deployment guide

Target: Linux server (Ubuntu/Debian), Apache, PHP 8.1+, PostgreSQL 14+ with PostGIS. Paths assume `/var/www/zpm`.

## 1. Server packages

```bash
sudo apt install apache2 php php-pgsql php-mbstring libapache2-mod-php \
    postgresql postgresql-contrib postgresql-16-postgis-3 certbot python3-certbot-apache unzip
sudo a2enmod rewrite headers ssl
```

(Match the PostGIS package to your PostgreSQL version.)

## 2. Database

```bash
sudo -u postgres psql -v app_password="'A_LONG_RANDOM_PASSWORD'" -f /var/www/zpm/deploy/01_db_setup.sql
```

Creates the non-superuser `zpm_app` role, the `zpm_membership` database, PostGIS and the `zpm_mms` schema. Ensure `pg_hba.conf` allows `host zpm_membership zpm_app 127.0.0.1/32 scram-sha-256`, then reload PostgreSQL.

## 3. Upload the application

On Windows run `deploy\package.ps1` (creates `zpm-release.zip`; local config and demo/seed scripts are excluded), then:

```bash
scp zpm-release.zip user@server:/tmp/
sudo mkdir -p /var/www/zpm && sudo unzip -o /tmp/zpm-release.zip -d /var/www/zpm
sudo chown -R $USER:www-data /var/www/zpm
```

## 4. Configure

```bash
cd /var/www/zpm
cp deploy/config.production.example.php config/config.php
nano config/config.php          # set the DB password; keep environment = production
chmod 640 config/config.php
```

## 5. Tables and first administrator

```bash
php scripts/migrate.php
php scripts/create-user.php
```

Never run demo/seed scripts in production.

## 6. Web server and HTTPS

```bash
sudo certbot certonly --apache -d registry.example.org
sudo cp deploy/apache-vhost.conf /etc/apache2/sites-available/zpm.conf   # edit the domain first
sudo a2ensite zpm && sudo systemctl reload apache2
```

The document root is `public/`, so `config/`, `app/`, `database/` and `scripts/` are not web-accessible. `base_path` is `''` in this setup.

## 7. Backups

```bash
chmod +x deploy/backup.sh
echo "127.0.0.1:5432:zpm_membership:zpm_app:PASSWORD" > ~/.pgpass && chmod 600 ~/.pgpass
crontab -e   # 0 2 * * * /var/www/zpm/deploy/backup.sh
```

Test a restore (see the bottom of `backup.sh`) before relying on backups.

## 8. Verify

- `https://registry.example.org/login` loads and sign-in works.
- `https://registry.example.org/config/config.php` is not served.
- Dashboard, Registered individuals and GIS pages load.

## Updating later

Upload a new package, extract over `/var/www/zpm` (keeping `config/config.php`), then run `php scripts/migrate.php`. Applied migrations are tracked in `zpm_mms.schema_migrations`.

## Notes

- The map loads Leaflet from `unpkg.com` and tiles from `server.arcgisonline.com` (allowed by the CSP); users need internet access to those.
- Boundary polygons are not included; the map uses representative points until approved boundaries are imported.
- Local data is not copied by these steps. To copy it: `pg_dump -Fc -n zpm_mms zpm_membership` locally, then after step 2 `pg_restore --no-owner -d zpm_membership` on the server (instead of step 5's migrate), and still run `create-user.php` if needed.
