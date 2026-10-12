# Student ID Card Generator

A PHP, HTML, CSS, and JavaScript application with secure sign-in and role-based user management.

## Features

- Persian sign-in page at `index.php`
- Password hashing, PDO prepared statements, session ID regeneration, and CSRF protection
- Three roles: Super Admin, Admin, and User
- Admins can create User and Admin accounts
- Only Super Admins can create Super Admin accounts
- User management with account listing, deactivation/reactivation, and deletion
- Deactivated accounts are blocked at sign-in and checked again on protected requests
- The last Super Admin cannot be deleted or deactivated; users cannot delete or deactivate themselves
- Responsive right-side dashboard menu

## Project structure

```text
student-id-generator/
├── index.php
├── login.php
├── logout.php
├── auth.php
├── dashboard.php
├── users.php
├── config/database.php
├── database/schema.sql
├── database/migrations/2026_10_12_add_user_roles.sql
├── scripts/create_admin.php
└── assets/css/style.css
```

## Run locally with XAMPP

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open [phpMyAdmin](http://localhost/phpmyadmin).

### If you already have the project database

1. Back up the `student_id_generator` database first.
2. Open phpMyAdmin and select the `student_id_generator` database.
3. Import `database/migrations/2026_10_12_add_user_roles.sql` from this repository.
4. The migration adds role and active-status columns, treats existing accounts as Admins, and promotes the oldest account to Super Admin.

### If you are creating a fresh database

1. Import `database/schema.sql` in phpMyAdmin.
2. Open Command Prompt and run:

   ```cmd
   cd /d E:\xampp\htdocs\student-id-generator
   E:\xampp\php\php.exe scripts\create_admin.php
   ```

   The first account created in an empty database becomes Super Admin. Subsequent CLI-created accounts become Admins. Use a password of at least 8 characters.
3. Visit [http://localhost/student-id-generator/](http://localhost/student-id-generator/) and sign in.
4. Open **مدیریت کاربران** in the side menu or visit [http://localhost/student-id-generator/users.php](http://localhost/student-id-generator/users.php).

If XAMPP is installed somewhere other than `E:\xampp`, adjust the PHP executable path.

## Roles and permissions

- **Super Admin:** can create User, Admin, and Super Admin accounts, and manage all other accounts.
- **Admin:** can create User and Admin accounts and manage regular Admin/User accounts. Admins cannot create Super Admins or manage Super Admin accounts.
- **User:** cannot access user management.

All role checks are performed in PHP on the server; hiding menu items alone is not treated as security.

## Database settings

For default local XAMPP, `config/database.php` uses host `127.0.0.1`, database `student_id_generator`, and MySQL user `root` with an empty password. These are local-development defaults only. Production must use a dedicated database account, environment variables, and HTTPS.

## Security notes

- Back up the database before applying the migration.
- Use fictional student details while developing. Never commit real student records, passwords, database secrets, or student photographs.
- Before exposing the application to the internet, add persistent login rate limiting, account recovery, audit logging, and review server configuration.
