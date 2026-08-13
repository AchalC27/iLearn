# MultiPie PostgreSQL

This package keeps the visual CSS/UI from the supplied `multipie_psql.zip` and removes all dummy-data dependencies.

## Database split

- `config/users_connection.php`
  - `multipie_auth_prod`
  - `public.users`

- `config/app_connection.php`
  - `multipie_main_prod`
  - main application tables

## Current live database module

Only Users is mapped to the real schema currently provided.

Users page:
- ID
- Username
- Mobile
- Display Name
- User Type (0 = User, 1 = Admin)
- Email
- Status
- Created
- Updated
- Edit (display only)
- Delete (disabled intentionally)

Status logic:
A user is Active when either `current_sign_in_at` or `last_sign_in_at` is within the last 30 days. Otherwise the user is Inactive.

## Important

Replace the connection values in both config files with the corporate PostgreSQL server credentials before running.

Do not commit corporate passwords to source control.

The application uses server-side pagination (25 users per page) so it does not load the full users table into PHP memory.
