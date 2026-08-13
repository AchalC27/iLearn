# iLearn / MultiPie CMS

This project keeps the two database implementations separate while sharing only the main application switcher/header and footer.

## Applications

- `ilearn_mysql/` — iLearn + MySQL implementation.
- `multipie_psql/` — MultiPie + PostgreSQL implementation.
- `shared/` — only common main header/footer assets.
- `storage/data.json` — temporary dummy data from the original ZIP.

## Switching applications

- `index.php?app=ilearn_mysql&page=users`
- `index.php?app=multipie_psql&page=users`

The top buttons generate these routes automatically.

## Database configuration

Database credentials are intentionally placeholders. Do not commit corporate passwords.

For iLearn, edit:

`ilearn_mysql/config/database.php`

For MultiPie, edit:

`multipie_psql/config/database.php`

MultiPie has two PostgreSQL connections:

- `users_connection.php` — users database.
- `app_connection.php` — other application database.

Set `enabled` to `true` only after the correct corporate connection details and schema mappings are available.

## Query separation

All application-specific SQL belongs in that application's `queries/` folder. The UI should consume normalized arrays so that the two database schemas can remain completely different.
