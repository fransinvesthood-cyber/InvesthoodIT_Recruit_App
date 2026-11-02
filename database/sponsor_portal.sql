-- Investhood IT Sponsor Portal role setup
-- Run this migration against the same database used by the application.
-- It adds a dedicated read-only sponsor role; it does not create sample users or change existing users.
INSERT INTO roles (name, slug, description, created_at)
SELECT 'Executive / Sponsor', 'sponsor', 'Read-only executive portfolio, programme and outcome dashboards.', CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug = 'sponsor');

-- After running this migration, assign the sponsor role only to authorised sponsor accounts.
-- Login redirects use ROLE_DASHBOARD_MAP in config/constants.php (updated in this package).
