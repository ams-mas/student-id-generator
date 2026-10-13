-- Expand roles, map legacy accounts to least-privilege roles, then remove legacy role names.
-- Existing super_admin accounts remain unchanged. Back up the database before applying.
ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','user','head_teacher','finance_manager','administrative_staff','teacher') NOT NULL DEFAULT 'administrative_staff';
UPDATE users SET role = 'head_teacher' WHERE role = 'admin';
UPDATE users SET role = 'administrative_staff' WHERE role = 'user';
ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','head_teacher','finance_manager','administrative_staff','teacher') NOT NULL DEFAULT 'administrative_staff';

-- Keep the system administrable if a legacy database had no super admin account.
UPDATE users SET role = 'super_admin' WHERE id = (SELECT id FROM (SELECT id FROM users ORDER BY id ASC LIMIT 1) AS first_user) AND NOT EXISTS (SELECT 1 FROM users WHERE role = 'super_admin');
