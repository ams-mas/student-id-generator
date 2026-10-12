# Student ID Card Generator

A PHP, HTML, CSS, and JavaScript application for creating student ID card previews. The application now includes a database-backed sign-in flow.

## Features

- Modern school-themed sign-in page at `index.php`
- Password hashes created with PHP `password_hash()` and checked with `password_verify()`
- PDO prepared statements for database queries
- Session ID regeneration after successful sign-in
- HttpOnly, SameSite=Lax session cookies and CSRF tokens on sign-in/sign-out
- Existing student card generator preserved in `dashboard.php`
- Sign-out action at the top of the generator

## Project structure

```text
student-id-generator/
├── index.php                 # Sign-in page
├── login.php                 # Sign-in handler
├── logout.php                # Sign-out handler
├── auth.php                  # Session, CSRF, and access helpers
├── dashboard.php             # Protected student card generator
├── config/database.php       # PDO connection
├── database/schema.sql       # Database and users table
├── scripts/create_admin.php  # CLI-only account creation
├── assets/css/style.css
├── assets/js/app.js
└── assets/images/default-avatar.svg
```

## Run locally with XAMPP

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open [phpMyAdmin](http://localhost/phpmyadmin).
3. Choose **Import**, select `database/schema.sql` from this project, and click **Import**. The SQL file creates the `student_id_generator` database and its `users` table.
4. Open Command Prompt and run:

   ```cmd
   cd /d E:\xampp\htdocs\student-id-generator
   E:\xampp\php\php.exe scripts\create_admin.php
   ```

   Choose a username and a strong password of at least 12 characters, then confirm the password. The CLI script creates a password hash; it does not store the plain-text password. The terminal may display typed password characters, so do this privately and do not share screenshots of the setup.
5. Visit [http://localhost/student-id-generator/](http://localhost/student-id-generator/) and sign in with the account you created.
6. Confirm the original generator appears at [http://localhost/student-id-generator/dashboard.php](http://localhost/student-id-generator/dashboard.php), then test the preview, photo preview, print, and sign-out buttons.

If your XAMPP installation is not at `E:\xampp`, adjust the PHP executable path in step 4.

## Database settings

For a default local XAMPP setup, `config/database.php` uses host `127.0.0.1`, database `student_id_generator`, and MySQL user `root` with an empty password. These are local-development defaults only. Production must use environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`, a dedicated database account with limited permissions, and HTTPS.

## Security notes

- No default username or password is created automatically. Create the first account with the CLI script.
- The generator currently previews card data in the browser and does not save student records or upload photos.
- Use fictional student details while developing. Never commit real student records, passwords, database secrets, or student photographs.
- Before exposing the application to the internet, add persistent login rate limiting, account recovery and administration controls, and verify the server's HTTPS and PHP configuration.
