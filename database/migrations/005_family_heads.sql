-- Family grouping: an individual may be tied to a family head (another registered individual).
-- A family head is simply an individual who has no head of their own; only one level is allowed.
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
