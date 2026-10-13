-- Expand roles, map legacy accounts to least-privilege roles, then remove legacy role names.
-- Existing super_admin accounts remain unchanged. Back up the database before applying.
ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','user','head_teacher','finance_manager','administrative_staff','teacher') NOT NULL DEFAULT 'administrative_staff';
UPDATE users SET role = 'head_teacher' WHERE role = 'admin';
UPDATE users SET role = 'administrative_staff' WHERE role = 'user';
ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','head_teacher','finance_manager','administrative_staff','teacher') NOT NULL DEFAULT 'administrative_staff';
