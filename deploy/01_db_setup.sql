-- ZPM Member Registry - one-time PostgreSQL provisioning.
-- Run as a PostgreSQL superuser on the production server:
--   sudo -u postgres psql -v app_password="'CHOOSE_A_LONG_RANDOM_PASSWORD'" -f deploy/01_db_setup.sql
-- Requires PostgreSQL 14+ and the PostGIS package (e.g. postgresql-16-postgis-3).

\set ON_ERROR_STOP on

-- Dedicated, non-superuser application account.
CREATE ROLE zpm_app LOGIN PASSWORD :app_password NOSUPERUSER NOCREATEDB NOCREATEROLE;

CREATE DATABASE zpm_membership OWNER zpm_app ENCODING 'UTF8' TEMPLATE template0;

\connect zpm_membership

-- PostGIS needs superuser to install; the application only uses it.
CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;
GRANT USAGE ON SCHEMA public TO zpm_app;

-- Application objects live in their own schema, created by scripts/migrate.php.
CREATE SCHEMA IF NOT EXISTS zpm_mms AUTHORIZATION zpm_app;
ALTER ROLE zpm_app IN DATABASE zpm_membership SET search_path TO zpm_mms, public;

REVOKE ALL ON DATABASE zpm_membership FROM PUBLIC;
GRANT CONNECT ON DATABASE zpm_membership TO zpm_app;