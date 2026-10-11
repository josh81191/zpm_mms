-- Registered individuals carry a party; non-ZPM individuals carry a Green/Yellow/Red classification.
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

-- Synthetic demo mix: ~60% ZPM, remainder spread across other parties, with classifications.
UPDATE members SET
    party = CASE
        WHEN id % 100 < 60 THEN 'zpm'
        WHEN id % 100 < 78 THEN 'mnf'
        WHEN id % 100 < 91 THEN 'congress'
        WHEN id % 100 < 96 THEN 'bjp'
        ELSE 'other'
    END
WHERE is_demo;

UPDATE members SET other_party_name = 'Independent (demo)' WHERE is_demo AND party = 'other';

UPDATE members SET
    classification = CASE (id * 7) % 10
        WHEN 0 THEN 'red' WHEN 1 THEN 'red' WHEN 2 THEN 'red'
        WHEN 3 THEN 'yellow' WHEN 4 THEN 'yellow' WHEN 5 THEN 'yellow' WHEN 6 THEN 'yellow'
        ELSE 'green'
    END,
    classification_set_at = CURRENT_TIMESTAMP,
    classification_note = 'Synthetic demo classification'
WHERE is_demo AND party <> 'zpm';

ALTER TABLE members
    ADD CONSTRAINT members_party_check CHECK (party IN ('zpm', 'mnf', 'congress', 'bjp', 'other')),
    ADD CONSTRAINT members_classification_check CHECK (classification IS NULL OR classification IN ('green', 'yellow', 'red')),
    ADD CONSTRAINT members_party_classification_rule CHECK (
        (party = 'zpm' AND classification IS NULL)
        OR (party <> 'zpm' AND classification IS NOT NULL)
    ),
    ADD CONSTRAINT members_other_party_name_rule CHECK (party = 'other' OR other_party_name IS NULL);
