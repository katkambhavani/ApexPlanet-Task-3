
# Entity Relationship Diagram

```mermaid
erDiagram
    ROLES ||--o{ USERS : has

    ROLES {
        INT id PK
        VARCHAR role_name UK
    }

    USERS {
        INT id PK
        VARCHAR name
        VARCHAR email UK
        VARCHAR password
        INT role_id FK
        TIMESTAMP created_at
        VARCHAR profile_picture
    }
```

## Relationship

- One role can be assigned to many users.
- Each user references a role through `users.role_id`.
- `users.role_id` references `roles.id`.
- `users.email` is unique.
- `roles.role_name` is unique.
