-- ============================================================
-- Seed role description strings into SS_CONFIG.
-- PREFIX: ROLE_DESC_  — easy to find all role descriptions later.
-- Safe to run multiple times (ON DUPLICATE KEY UPDATE).
-- ============================================================

INSERT INTO SS_CONFIG (CONFIG_KEY, CONFIG_VALUE) VALUES
  ('ROLE_DESC_ADMIN',           'Full access to all admin tools, user management, messaging, and app settings.'),
  ('ROLE_DESC_SECRET_SANTA',    'Participates in the Secret Santa gift exchange and can view their assigned giftee''s wish list once matches are revealed.'),
  ('ROLE_DESC_WISHLIST_ONLY',   'Can build and manage their own wish list but is not included in the Secret Santa draw. Intended for kids or younger family members.'),
  ('ROLE_DESC_WISHLIST_GIFTER', 'Can view wish lists of assigned Wishlist Only users, mark items as purchased, add items, and email the list. Intended for parents or grandparents.')
ON DUPLICATE KEY UPDATE CONFIG_VALUE = VALUES(CONFIG_VALUE);
