CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;

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

