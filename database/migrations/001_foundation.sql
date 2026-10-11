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

