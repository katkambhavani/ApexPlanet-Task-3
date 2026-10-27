# Database Normalization — ApexPlanet Task 3

## Database Overview

The application uses the `apexplanet_task3` database with two tables: `users` and `roles`.

## 1. First Normal Form (1NF)

**Rule:** Each column should contain a single, atomic value, and repeating groups should be avoided.

**Application:**
- Each user has a separate row in the `users` table.
- `name`, `email`, `password`, `role_id`, `created_at`, and `profile_picture` are stored in separate columns.
- Each role has its own row in the `roles` table.

**Assessment:** The shown table structure is consistent with 1NF.

## 2. Second Normal Form (2NF)

**Rule:** The table must satisfy 1NF, and every non-key attribute must depend on the whole primary key.

**Application:**
- The `users` table uses the single-column primary key `id`.
- The `roles` table uses the single-column primary key `id`.
- Neither table has a composite primary key, so partial dependency on part of a composite key does not arise.

**Assessment:** The shown table structure is consistent with 2NF, subject to the normal assumptions about the stored data.

## 3. Third Normal Form (3NF)

**Rule:** The table must satisfy 2NF and should not have non-key attributes depending transitively on a primary key.

**Application:**
- User information is stored in `users`.
- Role names are stored separately in `roles`.
- `users.role_id` references `roles.id` through a foreign-key constraint.
- Role names do not need to be repeated in every user record.

**Assessment:** Separating role information from user information supports a 3NF design. Full 3NF confirmation also depends on the functional dependencies in the application data.

## Relationship Summary

- `users.id` is the primary key of `users`.
- `roles.id` is the primary key of `roles`.
- `users.email` has a unique index.
- `roles.role_name` has a unique index.
- `users.role_id` references `roles.id`.
- One role can be associated with multiple users.

## Conclusion

The database uses separate user and role tables, primary keys, unique indexes, and a foreign-key relationship. These design choices support data integrity and a normalized database structure.

