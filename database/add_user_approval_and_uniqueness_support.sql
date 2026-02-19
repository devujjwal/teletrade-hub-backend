-- Run this on existing DBs.
-- 1) Ensure users start as pending approval if they are newly inserted by app logic (handled in backend).
-- 2) Add uniqueness guard for phone to prevent duplicate registration by phone.

-- Check duplicates before adding unique key:
-- SELECT phone, COUNT(*) c FROM users WHERE phone IS NOT NULL AND phone <> '' GROUP BY phone HAVING c > 1;

ALTER TABLE `users`
  ALTER COLUMN `is_active` SET DEFAULT 0,
  ADD UNIQUE KEY `phone_unique` (`phone`);
