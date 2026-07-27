# Kingsway Academy — Codebase Guide

## Structure

This is a Composer-based PHP 7.4+ school-management application with a browser UI and JSON API.

- `api/`: API implementation. Controllers are in `api/controllers/`; request routing is in `api/router/`; middleware is in `api/middleware/`; reusable domain/application services are in `api/services/`; response, validation, auth, and other helpers are in `api/includes/` and `api/core/`.
- `api/index.php`: JSON API entry point. Routes generally use `/api/<controller>/<resource>/<id>` and controller methods follow names such as `getStudents`, `postPayments`, and `deleteProfile`. Return the standard normalized `ApiResponse` shape; unknown named resources should be 404s.
- Top-level PHP pages (`index.php`, `admissions.php`, `contact.php`, etc.): public website pages.
- `home.php`: authenticated application shell. Page views live in `pages/`, shared fragments in `components/`, and layout shells in `layouts/`.
- `js/`: browser code. Shared bootstrap/session/runtime code is under `js/core/`, reusable UI under `js/components/`, page/dashboard code under `js/pages/` and `js/dashboards/`; use `js/api.js` for API calls.
- `css/`, `assets/`, `public/`, `images/`: static frontend assets.
- `config/`: environment loading, application settings, permissions, and role/sidebar configuration. `database/`: PDO access, schema dumps, and migrations. `tests/Unit/`: PHPUnit tests. `scripts/`: migrations, checks, and UI smoke-test utilities.

Composer PSR-4 mappings include `App\\API\\...`, `App\\Config`, and `App\\Database`; test classes use the `Tests\\` namespace.

## Setup, development, and tests

```bash
composer install
npm install
cp config/.env.example config/.env
# Edit config/.env with local database credentials and a development JWT_SECRET.
php -S localhost:8000
```

Provision a local MySQL database from the appropriate dump in `database/` and review migration scripts before running any migration. Do not use production credentials or commit `.env` files.

```bash
composer test                 # PHPUnit suite (equivalent: vendor/bin/phpunit)
npm run test:ui               # Puppeteer smoke tests; requires the server above
BASE_URL=http://localhost:8000 npm run test:ui
```

There is no separate frontend build or lint step; assets are served directly. PHPUnit 10 in the lockfile requires PHP 8.1+, even though the application declares PHP `>=7.4`.

## Conventions

- Use four-space indentation and PHP 7.4-compatible syntax unless the runtime target is intentionally changed. Match nearby strict return types, namespaces, error handling, and semicolon/async style.
- Name PHP classes/files in PascalCase (`PolicyEngine.php`); use descriptive snake_case for procedural pages and scripts. Put new tests in `tests/Unit/<Area>/` as focused `*Test.php` classes.
- Keep API behavior in the appropriate controller, service, middleware, or router layer. Preserve the middleware/security pipeline (CORS, rate limiting, authentication, RBAC, route authorization, and device checks).
- Keep route-specific JavaScript in `js/pages/` or `js/dashboards/`, shared runtime code in `js/core/`, and styles scoped to existing component/role design tokens.
- Use prepared statements and validate input. Never expose bearer tokens, credentials, or sensitive diagnostics. Keep production `DEBUG=false` and use a strong random production `JWT_SECRET`.
- Use Conventional Commit messages such as `feat(ui): ...`, `fix(api): ...`, `test(router): ...`, or `chore(config): ...`.
