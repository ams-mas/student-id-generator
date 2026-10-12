-- ONE-TIME MIGRATION for an existing Student ID Card Generator database.
-- Run this in phpMyAdmin after selecting the student_id_generator database.
-- Existing accounts become admins, and the oldest account is promoted to Super Admin.
-- Back up your database before running this migration.

ALTER TABLE users
    ADD COLUMN role ENUM('super_admin', 'admin', 'user') NOT NULL DEFAULT 'admin' AFTER password_hash,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    ADD KEY idx_users_role (role),
    ADD KEY idx_users_active (is_active);

UPDATE users
SET role = 'super_admin'
WHERE id = (
    SELECT id FROM (
        SELECT id FROM users ORDER BY id ASC LIMIT 1
    ) AS first_user
);
