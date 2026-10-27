# ApexPlanet Task 3 — User Management System

## Project Overview

The User Management System is a PHP and MySQL web application developed as part of the ApexPlanet Software Pvt. Ltd. Full Stack Web Development Internship.

The application supports user registration, login, profile management, profile-picture uploads, and administrative user management with role-based access control.

## Technologies Used

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP 8.0
- **Database:** MySQL
- **Database Management:** phpMyAdmin
- **Local Development Environment:** XAMPP
- **Version Control:** Git and GitHub

## Features

### User Features

- User registration with server-side validation
- Duplicate-email validation
- Secure password hashing
- User login and logout
- Session-based authentication
- User dashboard
- Personal profile editing
- Profile-picture upload with file type and size validation

### Admin Features

- Admin-only access to user management
- View registered users
- Edit user details and roles
- Delete users with confirmation
- Role-based access control

## Database Structure

The application uses the `apexplanet_task3` database.

### `users` Table

| Column | Purpose |
|---|---|
| `id` | Primary key; auto-increment |
| `name` | User's name |
| `email` | Unique email address |
| `password` | Hashed password |
| `role_id` | Foreign key referencing `roles.id` |
| `created_at` | Account creation timestamp |
| `profile_picture` | Profile-picture path; nullable |

### `roles` Table

| Column | Purpose |
|---|---|
| `id` | Primary key; auto-increment |
| `role_name` | Unique role name |

The `users.role_id` foreign key references `roles.id`. The application uses role ID `1` for Admin and role ID `2` for User.

## Security Measures

- Passwords are hashed using PHP's `password_hash()` function.
- Prepared statements and bound parameters are used in the verified database operations.
- Server-side registration validation is implemented.
- Duplicate email addresses are checked in the application and constrained by a unique database index.
- Sessions are used for authentication and role-based access control.
- Profile-picture uploads are restricted by file size and image type.
- Uploaded filenames are generated to avoid relying on user-supplied filenames.
- An `.htaccess` file restricts PHP-related file execution in the profile-upload directory.

## Project Setup

1. Install XAMPP with Apache, PHP, and MySQL.
2. Copy the project folder into `C:\xampp\htdocs\ApexPlanet-Task-3`.
3. Start Apache and MySQL from the XAMPP Control Panel.
4. Open phpMyAdmin at `http://localhost/phpmyadmin/`.
5. Create or import the `apexplanet_task3` database and its `users` and `roles` tables.
6. Configure `db.php` with the local database connection settings.
7. Open the application at `http://localhost/ApexPlanet-Task-3/`.

## Testing

The following checks have been performed:

- Registration rejects an empty name.
- Registration rejects an already registered email.
- Passwords are stored as hashes.
- Login and logout behavior has been tested.
- Regular users are blocked from the Admin page.
- Admin user listing, editing, and deletion have been tested.
- Delete confirmation has been tested.
- Profile editing has been tested.
- Profile-picture upload and persistence after refresh have been tested.
- PHP syntax checks passed for the 12 PHP files checked.

## Database Normalization

The database design is consistent with the principles of normalization:

- **First Normal Form (1NF):** Columns store individual values without repeating groups.
- **Second Normal Form (2NF):** Each table uses a single-column primary key, avoiding partial dependencies on composite keys.
- **Third Normal Form (3NF):** Role information is stored separately in the `roles` table instead of repeating role names in each user record.

## Project Status

The main user-management features have been implemented and tested. Further review of security requirements, database documentation, and the project demonstration remains part of the final verification process.

