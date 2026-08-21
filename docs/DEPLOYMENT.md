# Deployment Guide

## Runtime requirements

- PHP 8.3+ with `curl`, `fileinfo`, `mbstring`, `openssl`, PDO and the selected database driver.
- Composer 2.
- Node.js 22+ and npm for the production asset build.
- PostgreSQL 16+ or MySQL 8.0+ for production; SQLite is supported for local development and tests.
- A process supervisor for queue workers and the scheduler.

## Install and build

```bash
composer install --no-dev --classmap-authoritative
npm ci
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan course:ingest
php artisan course:audit-coverage
php artisan optimize
```

The source audit defaults to `../tmp/pdfs/source-audit` relative to `laravel-src`. Set `COURSE_SOURCE_AUDIT_PATH` to an absolute secured deployment path when artifacts are stored elsewhere. Preserve the audit manifest and JSONL files used for the published curriculum version.

## Web, queue and schedule

- Point the web server document root to `laravel-src/public`; never expose the repository root, `.env`, source artifacts or private storage.
- Run queue workers under a supervisor, for example: `php artisan queue:work --sleep=1 --tries=3 --timeout=120`.
- Run one scheduler process: `php artisan schedule:work`, or invoke `php artisan schedule:run` every minute.
- The scheduled resource-freshness audit queues daily at 02:15 application time. Keep at least one queue worker running.
- Restart workers after every deployment with `php artisan queue:restart`.

## Filesystem

The runtime account needs write access to `storage/` and `bootstrap/cache/`. Notebook and exercise evidence stays on the private `local` disk. Back up the database and private storage together so record/file references remain consistent.

## OpenAI configuration

AI is optional. Core lessons, source reference, notebook evidence, deterministic grading, manual gate review, calculators and progression work with AI disabled.

```dotenv
AI_TUTOR_ENABLED=false
AI_ASSESSMENT_ENABLED=false
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5.6-terra
OPENAI_GRADING_MODEL=gpt-5.6-luna
```

When enabled, set the key only in the server secret manager, enable the required feature flag, monitor `ai_usage_events`, and obtain learner consent. Test the Responses API contract in staging before rollout.

## Release verification

```bash
php artisan migrate:status
php artisan course:audit-coverage
php artisan route:list
php artisan schedule:list
php artisan test
npm run build
```

Verify `/`, `/login`, a complete authenticated learner flow, an admin gate review, a private export and restoration from backup. Do not publish while either machine coverage or structured parity fails.
