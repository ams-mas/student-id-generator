-- Add a separate display name for user accounts while preserving login usernames.
ALTER TABLE users
    ADD COLUMN full_name VARCHAR(160) NOT NULL DEFAULT '' AFTER username;

-- Existing accounts keep working; administrators can edit these placeholder display names later.
UPDATE users
SET full_name = username
WHERE TRIM(full_name) = '';
