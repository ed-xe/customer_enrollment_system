# Customer Enrollment System

A small PHP and MySQL application for creating, finding, updating, and deleting customer enrollment records.

## Requirements

- PHP 7.4 or later with the `mysqli` extension
- MySQL 5.7+ or a compatible server
- A web server or PHP's built-in development server

## Database setup

Create the database and table using [`database/schema.sql`](database/schema.sql):

```sh
mysql -u root -p < database/schema.sql
```

The `Code` column must be unique, and all three columns must support values up to 255 characters. If you already have a `customerlist` table, check for duplicate `Code` values and confirm its column widths before running the updated application. Add a primary key or unique index if needed. Back up existing data before changing a production schema.

## Configure the database

Set these environment variables for the PHP process/web server:

| Variable | Required | Default |
| --- | --- | --- |
| `DB_HOST` | No | `127.0.0.1` |
| `DB_PORT` | No | `3306` |
| `DB_NAME` | No | `serverdata` |
| `DB_USER` | Yes | None |
| `DB_PASSWORD` | Yes | None |

Do not commit real database credentials. The application returns a generic error to clients and writes database details to the PHP error log.

For a local development server in PowerShell:

```powershell
$env:DB_USER = "your_mysql_user"
$env:DB_PASSWORD = "your_mysql_password"
php -S localhost:8000
```

Open `http://localhost:8000`. Configure the environment variables in the web server's process configuration for deployments; setting them in a terminal does not configure a separate service.

## Run with Docker Compose

Requirements: Docker Desktop installed and running, with the Compose plugin available (`docker compose version`).

From the project root, set development credentials using the syntax for your terminal, then start the containers.

PowerShell:

```powershell
$env:DB_USER = "enrollment_app"
$env:DB_PASSWORD = "change-this-local-password"
$env:MYSQL_ROOT_PASSWORD = "change-this-local-root-password"
docker compose up --build
```

Bash (Linux, WSL, or Git Bash):

```sh
export DB_USER="enrollment_app"
export DB_PASSWORD="change-this-local-password"
export MYSQL_ROOT_PASSWORD="change-this-local-root-password"
docker compose up --build
```

Open <http://127.0.0.1:8000>. The app is served by Apache/PHP in a container, and MySQL is available to it at the Compose service hostname `db`. On its first start, MySQL creates the database and table from `database/schema.sql`. The named `db_data` volume keeps database contents when containers stop or are recreated.

Keep the terminal running while using the app. Stop the containers with `Ctrl+C`, or run `docker compose down` in another terminal. To start them again, use `docker compose up`.

Compose reads these variables each time you run a command, including `exec`. If you use a new terminal window, set the same values you used to start the containers before running the smoke test.

PowerShell:

```powershell
$env:DB_USER = "enrollment_app"
$env:DB_PASSWORD = "change-this-local-password"
$env:MYSQL_ROOT_PASSWORD = "change-this-local-root-password"
docker compose exec -e API_BASE_URL=http://127.0.0.1 app php tests/crud_smoke_test.php
```

Bash:

```sh
export DB_USER="enrollment_app"
export DB_PASSWORD="change-this-local-password"
export MYSQL_ROOT_PASSWORD="change-this-local-root-password"
docker compose exec -e API_BASE_URL=http://127.0.0.1 app php tests/crud_smoke_test.php
```

The credentials above are for local development only. Use secret management and secure credentials for deployments. MySQL only runs the mounted schema script when initializing an empty data directory; changing the schema file will not alter an existing `db_data` volume. Removing that volume also deletes its data, so do not use `docker compose down -v` unless you intend to erase the local database.

## API behavior

- `GET get_data.php?user_id=...` returns one customer or `404`.
- `GET list_data.php?page=1&per_page=10&search=...&sort=user_id&direction=asc` returns a filtered, sorted, paginated customer list. `per_page` is limited to 50.
- `POST insert_data.php` accepts a JSON object with `user_id`, `first_name`, and `last_name`; duplicate IDs return `409`.
- `POST update_data.php` accepts the same JSON fields and returns `404` when the ID does not exist.
- `POST delete_data.php` accepts a JSON object with `user_id` and returns `404` when the ID does not exist.

Responses use JSON with a `success` boolean. Error responses include an `error.code` and user-safe `error.message`. The browser UI handles these responses and reports network/API errors instead of assuming success.

The list endpoint makes customer data available to any client that can reach the application. Add authentication and authorization before deploying it with real customer information.

## Smoke test

With the application and database running, point the smoke test at its base URL:

```powershell
$env:API_BASE_URL = "http://localhost:8000"
php tests/crud_smoke_test.php
```

The test checks method and input validation, creates a temporary customer, checks list/lookup, duplicate rejection, update, and missing-record behavior, then removes the test record after the run.
