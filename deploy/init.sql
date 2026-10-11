-- ZPM Member Registry - database initialisation (migrations 001-005 included).
-- Run against an existing, empty database that you can write to:
--   psql -U josh -d YOUR_DATABASE -f deploy/init.sql
-- Re-running is safe: tables are only created when the schema is empty.
-- PostGIS must be available (the CREATE EXTENSION line needs the right to install it).

\set ON_ERROR_STOP on

CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;
CREATE SCHEMA IF NOT EXISTS zpm_mms;
SET search_path TO zpm_mms, public;

CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

SELECT to_regclass('zpm_mms.roles') IS NULL AS fresh \gset
\if :fresh
BEGIN;

-- ============================================================ 001 foundation
CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    role_key VARCHAR(80) NOT NULL UNIQUE,
    display_name VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    permission_key VARCHAR(120) NOT NULL UNIQUE,
    display_name VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE RESTRICT,
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE user_accounts (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT user_accounts_email_lowercase_unique UNIQUE (email),
    CONSTRAINT user_accounts_email_lowercase CHECK (email = lower(email))
);

CREATE TABLE user_role_assignments (
    user_id BIGINT NOT NULL REFERENCES user_accounts(id) ON DELETE RESTRICT,
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE RESTRICT,
    assigned_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_by_user_id BIGINT NULL REFERENCES user_accounts(id) ON DELETE RESTRICT,
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    actor_user_id BIGINT NULL REFERENCES user_accounts(id) ON DELETE RESTRICT,
    action_key VARCHAR(120) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id VARCHAR(80) NULL,
    request_ip INET NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX audit_logs_actor_occurred_at_idx ON audit_logs (actor_user_id, occurred_at DESC);
CREATE INDEX audit_logs_entity_idx ON audit_logs (entity_type, entity_id);

INSERT INTO roles (role_key, display_name, description) VALUES
    ('system-administrator', 'System Administrator', 'Manages application settings, accounts, master data, and audit functions.'),
    ('central-office', 'Authorized Central Office User', 'Manages authorized membership records and organization-wide reporting.'),
    ('data-entry', 'Data Entry Operator', 'Creates and updates records within their assigned permissions and scope.'),
    ('read-only', 'Read-Only User', 'Views authorized records, summaries, and reports without editing.');

INSERT INTO permissions (permission_key, display_name, description) VALUES
    ('dashboard.view', 'View dashboard', 'View the authenticated application dashboard.'),
    ('users.manage', 'Manage users', 'Manage user accounts and role assignments.'),
    ('organization.manage', 'Manage organization', 'Maintain districts, blocks, and units.'),
    ('members.view', 'View members', 'View authorized membership records.'),
    ('members.manage', 'Manage members', 'Create and update authorized membership records.'),
    ('reports.view', 'View reports', 'View authorized aggregate reports.'),
    ('audit.view', 'View audit log', 'View system audit records.');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_key = 'system-administrator';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key IN ('dashboard.view', 'members.view', 'members.manage', 'reports.view')
WHERE r.role_key = 'central-office';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key IN ('dashboard.view', 'members.view', 'members.manage')
WHERE r.role_key = 'data-entry';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.permission_key IN ('dashboard.view', 'members.view', 'reports.view')
WHERE r.role_key = 'read-only';

-- ============================================================ 003 organization, members, spatial data
CREATE TABLE districts (
    id BIGSERIAL PRIMARY KEY,
    district_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL UNIQUE,
    administrative_status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (administrative_status IN ('active', 'inactive', 'archived')),
    boundary public.geometry(MULTIPOLYGON, 4326) NULL,
    representative_location public.geometry(POINT, 4326) NULL,
    is_demo BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE blocks (
    id BIGSERIAL PRIMARY KEY,
    district_id BIGINT NOT NULL REFERENCES districts(id) ON DELETE RESTRICT,
    block_code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    administrative_status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (administrative_status IN ('active', 'inactive', 'archived')),
    boundary public.geometry(MULTIPOLYGON, 4326) NULL,
    representative_location public.geometry(POINT, 4326) NULL,
    is_demo BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT blocks_district_name_unique UNIQUE (district_id, name)
);

CREATE TABLE units (
    id BIGSERIAL PRIMARY KEY,
    block_id BIGINT NOT NULL REFERENCES blocks(id) ON DELETE RESTRICT,
    unit_code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    unit_type VARCHAR(20) NOT NULL CHECK (unit_type IN ('village', 'locality', 'other')),
    administrative_status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (administrative_status IN ('active', 'inactive', 'archived')),
    boundary public.geometry(MULTIPOLYGON, 4326) NULL,
    representative_location public.geometry(POINT, 4326) NULL,
    remarks TEXT NULL,
    is_demo BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT units_block_name_unique UNIQUE (block_id, name)
);

CREATE TABLE designations (
    id BIGSERIAL PRIMARY KEY,
    designation_key VARCHAR(80) NOT NULL UNIQUE,
    display_name VARCHAR(120) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE members (
    id BIGSERIAL PRIMARY KEY,
    member_code VARCHAR(40) NOT NULL UNIQUE,
    full_name VARCHAR(160) NOT NULL,
    gender VARCHAR(20) NOT NULL DEFAULT 'not-specified'
        CHECK (gender IN ('male', 'female', 'not-specified')),
    epci_number VARCHAR(80) NULL UNIQUE,
    enrollment_date DATE NOT NULL,
    current_unit_id BIGINT NOT NULL REFERENCES units(id) ON DELETE RESTRICT,
    membership_status VARCHAR(20) NOT NULL DEFAULT 'active'
        CHECK (membership_status IN ('active', 'inactive', 'resigned', 'deceased', 'archived')),
    status_effective_date DATE NOT NULL,
    remarks TEXT NULL,
    is_demo BOOLEAN NOT NULL DEFAULT FALSE,
    created_by_user_id BIGINT NULL REFERENCES user_accounts(id) ON DELETE RESTRICT,
    updated_by_user_id BIGINT NULL REFERENCES user_accounts(id) ON DELETE RESTRICT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT members_enrollment_not_future CHECK (enrollment_date <= CURRENT_DATE)
);

CREATE TABLE member_designation_history (
    id BIGSERIAL PRIMARY KEY,
    member_id BIGINT NOT NULL REFERENCES members(id) ON DELETE RESTRICT,
    designation_id BIGINT NOT NULL REFERENCES designations(id) ON DELETE RESTRICT,
    organizational_level VARCHAR(20) NOT NULL CHECK (organizational_level IN ('unit', 'block', 'district')),
    organizational_id BIGINT NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    recorded_by_user_id BIGINT NULL REFERENCES user_accounts(id) ON DELETE RESTRICT,
    remarks TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT designation_dates_valid CHECK (effective_to IS NULL OR effective_to >= effective_from)
);

CREATE TABLE membership_events (
    id BIGSERIAL PRIMARY KEY,
    member_id BIGINT NOT NULL REFERENCES members(id) ON DELETE RESTRICT,
    event_type VARCHAR(80) NOT NULL,
    previous_value JSONB NULL,
    new_value JSONB NULL,
    effective_date DATE NOT NULL,
    recorded_by_user_id BIGINT NULL REFERENCES user_accounts(id) ON DELETE RESTRICT,
    reason TEXT NULL,
    is_demo BOOLEAN NOT NULL DEFAULT FALSE,
    recorded_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX blocks_district_id_idx ON blocks (district_id);
CREATE INDEX units_block_id_idx ON units (block_id);
CREATE INDEX members_current_unit_id_idx ON members (current_unit_id);
CREATE INDEX members_status_enrollment_date_idx ON members (membership_status, enrollment_date);
CREATE INDEX members_demo_idx ON members (is_demo);
CREATE INDEX membership_events_member_recorded_at_idx ON membership_events (member_id, recorded_at DESC);
CREATE INDEX districts_representative_location_gix ON districts USING GIST (representative_location);
CREATE INDEX blocks_representative_location_gix ON blocks USING GIST (representative_location);
CREATE INDEX units_representative_location_gix ON units USING GIST (representative_location);

INSERT INTO designations (designation_key, display_name) VALUES
    ('president', 'President'),
    ('vice-president', 'Vice President'),
    ('secretary', 'Secretary'),
    ('treasurer', 'Treasurer'),
    ('executive', 'Executive'),
    ('general', 'General')
ON CONFLICT (designation_key) DO NOTHING;

-- ============================================================ 004 party and classification
ALTER TABLE members
    ADD COLUMN party VARCHAR(20) NOT NULL DEFAULT 'zpm',
    ADD COLUMN other_party_name VARCHAR(120),
    ADD COLUMN classification VARCHAR(10),
    ADD COLUMN classification_set_at TIMESTAMPTZ,
    ADD COLUMN classification_set_by_user_id BIGINT REFERENCES user_accounts (id),
    ADD COLUMN classification_note TEXT;

CREATE INDEX members_party_idx ON members (party);
CREATE INDEX members_classification_idx ON members (classification) WHERE classification IS NOT NULL;

CREATE TABLE party_classification_history (
    id BIGSERIAL PRIMARY KEY,
    member_id BIGINT NOT NULL REFERENCES members (id),
    previous_party VARCHAR(20),
    new_party VARCHAR(20) NOT NULL,
    previous_classification VARCHAR(10),
    new_classification VARCHAR(10),
    note TEXT,
    recorded_by_user_id BIGINT REFERENCES user_accounts (id),
    recorded_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX party_classification_history_member_idx ON party_classification_history (member_id);

ALTER TABLE members
    ADD CONSTRAINT members_party_check CHECK (party IN ('zpm', 'mnf', 'congress', 'bjp', 'other')),
    ADD CONSTRAINT members_classification_check CHECK (classification IS NULL OR classification IN ('green', 'yellow', 'red')),
    ADD CONSTRAINT members_party_classification_rule CHECK (
        (party = 'zpm' AND classification IS NULL)
        OR (party <> 'zpm' AND classification IS NOT NULL)
    ),
    ADD CONSTRAINT members_other_party_name_rule CHECK (party = 'other' OR other_party_name IS NULL);

-- ============================================================ 005 family heads
ALTER TABLE members ADD COLUMN IF NOT EXISTS family_head_id BIGINT REFERENCES members(id);

ALTER TABLE members DROP CONSTRAINT IF EXISTS members_family_head_not_self;
ALTER TABLE members ADD CONSTRAINT members_family_head_not_self CHECK (family_head_id IS NULL OR family_head_id <> id);

CREATE INDEX IF NOT EXISTS members_family_head_idx ON members (family_head_id) WHERE family_head_id IS NOT NULL;

CREATE OR REPLACE FUNCTION enforce_family_head_depth() RETURNS trigger AS $$
BEGIN
    IF NEW.family_head_id IS NOT NULL THEN
        IF EXISTS (SELECT 1 FROM members WHERE id = NEW.family_head_id AND family_head_id IS NOT NULL) THEN
            RAISE EXCEPTION 'The selected family head is already a member of another family.';
        END IF;
        IF TG_OP = 'UPDATE' AND EXISTS (SELECT 1 FROM members WHERE family_head_id = NEW.id) THEN
            RAISE EXCEPTION 'This individual is a family head and cannot be tied to another head.';
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS members_family_head_depth ON members;
CREATE TRIGGER members_family_head_depth
    BEFORE INSERT OR UPDATE OF family_head_id ON members
    FOR EACH ROW EXECUTE FUNCTION enforce_family_head_depth();

-- Mark migrations as applied so scripts/migrate.php does not repeat them.
INSERT INTO schema_migrations (filename) VALUES
    ('001_foundation.sql'),
    ('002_move_foundation_to_zpm_mms.sql'),
    ('003_organization_members_and_spatial_data.sql'),
    ('004_registered_individuals_party_classification.sql'),
    ('005_family_heads.sql')
ON CONFLICT DO NOTHING;

COMMIT;
\else
\echo 'Schema zpm_mms already exists - skipping table creation.'
\endif
