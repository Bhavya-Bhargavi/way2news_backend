# Way2News Backend API

Laravel 12 JSON API for the Way2News Android app. It provides phone OTP registration/login, Sanctum bearer tokens, a paginated public news feed, category filtering, and admin CRUD for news and categories.

## Requirements


## Local Setup

1. Create a MySQL database and update `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`.
2. Install dependencies and initialize the app:

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
if (-not (Select-String -Path .env -Pattern '^APP_KEY=base64:.+' -Quiet)) { php artisan key:generate }
php artisan migrate --seed
```

If `.env` already exists, keep its `APP_KEY` and update only the settings you need. Set `ADMIN_PHONE` and `ADMIN_NAME` before seeding to create an admin account. Use international phone format such as `+15551234567`. The included Postman collection defaults to the local test admin phone `+15550002222`; for a fresh local database, set `ADMIN_PHONE` to that number before seeding, or change the Postman phone variable to match your configured admin.

For local Postman testing, run `powershell -ExecutionPolicy Bypass -File .\run-local.ps1`. The script uses the ignored `database/database.sqlite` demo database, sets `APP_ENV=local`, `OTP_TEST_MODE=true`, and the requested dummy `INFINITO_SMS_ENABLED=true`, then starts PHP on port 8000. Test mode skips SMS delivery and includes `debug_otp` directly in the response. It is guarded by the `local` or `testing` environment, so setting the flag in production will not return the code. `INFINITO_SMS_ENABLED` is only a placeholder toggle; the real provider adapter needs Infinito's API documentation and credentials before it can send messages.

```powershell
php artisan serve
```

Base URL: `http://127.0.0.1:8000/api`

Import [postman/Way2News-API.postman_collection.json](postman/Way2News-API.postman_collection.json) into Postman. Update its `phone` and `name` collection variables, run **Request OTP**, then **Verify OTP and save token**. The collection captures the local OTP and bearer token automatically. To use a cPanel database from your computer instead of local MySQL, the host must enable remote MySQL and allowlist your client IP.

## API

All requests and responses are JSON. Send `Accept: application/json`; send `Content-Type: application/json` with JSON bodies. Authenticated endpoints use `Authorization: Bearer <token>`.

| Method | Endpoint | Access | Purpose |
| --- | --- | --- | --- |
| POST | `/auth/otp/request` | Public, rate limited | Request OTP; `phone`, optional `name` |
| POST | `/auth/otp/verify` | Public, rate limited | Verify `phone` and six-digit `otp`; creates a user on first successful verification |
| GET | `/auth/me` | Bearer token | Current user |
| POST | `/auth/logout` | Bearer token | Revoke current token |
| GET | `/news` | Public | Published feed; supports `page`, `per_page` (max 50), `category` slug, and `q` search |
| GET | `/news/{slug}` | Public | Published story including `image_url` and `article_url` |
| GET | `/categories` | Public | Active categories with published story counts |
| GET | `/categories/{slug}/news` | Public | Paginated published stories in a category |
| GET/POST | `/admin/news` | Admin token | List all stories / create story |
| GET/PUT/PATCH/DELETE | `/admin/news/{id}` | Admin token | Read, update, or delete story |
| GET/POST | `/admin/categories` | Admin token | List all categories / create category |
| GET/PUT/PATCH/DELETE | `/admin/categories/{id}` | Admin token | Read, update, or delete category |

Story create fields: `title`, `summary`, `article_url` are required. `category_id`, `slug`, `image_url`, `source_name`, `is_published`, and `published_at` are optional. A published story must include `published_at`; a future publish time remains hidden until it arrives. `image_url` and `article_url` must be absolute URLs. Public news responses include `category`, `image_url`, and `article_url`; the Android app can open `article_url` when a story is tapped.

OTP values are stored hashed, expire after `OTP_EXPIRY_MINUTES` (default five), and are limited to five verification attempts. OTP requests are rate-limited. Infinito is not yet integrated: no provider API details or credentials were supplied, and production OTP requests fail closed until a real adapter is configured. The Laravel `MAIL_*` variables are unrelated to phone OTP and are not used by these API routes.

Admin access is granted by setting `ADMIN_PHONE` and running the database seeder. Do not make an admin role editable through public registration.

## Tests

Run `php artisan test`. The included feature tests use an in-memory SQLite test database, so the CLI PHP used for tests must have `pdo_sqlite`; otherwise configure PHPUnit to use an available test MySQL database. On Windows PHP distributions that include but do not enable the SQLite DLLs, run PHPUnit directly with temporary extension overrides:

```powershell
$phpExt = Join-Path (Split-Path (Get-Command php).Source) 'ext'
php -d "extension=$phpExt\php_sqlite3.dll" -d "extension=$phpExt\php_pdo_sqlite.dll" vendor/bin/phpunit
```

## cPanel Deployment

Use PHP 8.2+ and enable `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, and `xml` in cPanel MultiPHP INI Editor. Prefer storing the Laravel project outside the web root (for example `/home/CPANEL_USER/way2news_backend`) and setting the domain document root to that project's `public/` directory. Do not make the Laravel project root, `.env`, `vendor/`, or `storage/` directly web-accessible. If cPanel cannot point the domain to `public/`, keep the application outside `public_html`, copy only the contents of Laravel's `public/` into the web root, and update `index.php` paths to the private project directory.

1. Upload the project and `vendor/` folder, or upload source and run `composer install --no-dev --optimize-autoloader` over SSH/Terminal.
2. Create a MySQL database and database user in cPanel, assign the user to the database, then configure `.env` with production values: `APP_ENV=production`, `APP_DEBUG=false`, the real `APP_URL`, and the cPanel database credentials. Keep `OTP_TEST_MODE=false`; configure the Infinito adapter before enabling public OTP login.
3. Generate `APP_KEY` once with `php artisan key:generate --force` and preserve it for future deployments. Never upload a development `.env` or commit production credentials.
4. For a phpMyAdmin first push, select the new database and import `database_export.sql`. It creates the schema, starter categories, and Laravel migration history. Do not run `php artisan migrate` after this import. Set `ADMIN_PHONE` and run `php artisan db:seed --force` to grant that phone admin access. Alternatively, skip the SQL import and run `php artisan migrate --force` followed by `php artisan db:seed --force`; do not use both approaches on the same empty database.
5. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP process (typically `775`, subject to the host's ownership policy). Confirm `storage/logs/laravel.log` is writable.
6. Verify the domain's document root points to `public/`, then test `/up`, `/api/categories`, and the Postman collection against the deployed base URL.

After changing production environment values, clear cached config with `php artisan optimize:clear`, then optionally run `php artisan config:cache`.