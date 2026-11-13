-- Commercial Manager role setup (idempotent).
-- Run against the same database used by Investhood IT.
INSERT INTO roles (name, slug, description, created_at)
SELECT 'Commercial Manager', 'commercial_manager',
       'Manages commercial capability intelligence, tender packs and approved reporting.',
       CURRENT_TIMESTAMP
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE slug = 'commercial_manager'
);

-- This creates the role only. An administrator must assign it to the authorised
-- Commercial Manager user account by updating users.role_id to this role's id.
