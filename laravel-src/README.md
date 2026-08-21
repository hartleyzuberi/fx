# FX Mastery

FX Mastery is a Laravel 13, Inertia 3 and React 19 learning platform built from the supplied *Forex Trading From First Principles to Advanced Practice* course and its Tutor-Led Guided Study Edition.

It provides 90 sequential chapters, complete source provenance, four notebooks, 90 quizzes, 379 exercises, seven practical gates, calculators, immutable strategy/backtest/demo evidence, optional grounded AI tutoring, an admin audit surface and learner CSV exports.

## Local setup on this workspace

```powershell
Copy-Item .env.example .env
C:\tools\php85\php.exe -c php.dev.ini artisan key:generate
C:\tools\php85\php.exe -c php.dev.ini artisan migrate
C:\tools\php85\php.exe -c php.dev.ini artisan course:ingest
npm install
npm run build
C:\tools\php85\php.exe -c php.dev.ini artisan serve
```

For development, run the web server, Vite and a queue worker in separate terminals. The production requirements and supervisor/scheduler setup are in [`../docs/DEPLOYMENT.md`](../docs/DEPLOYMENT.md).

## Verification

```powershell
C:\tools\php85\php.exe -c php.dev.ini artisan course:audit-coverage
C:\tools\php85\php.exe -c php.dev.ini vendor/bin/phpunit
npx eslint .
npx tsc --noEmit
npm run build
```

## First administrator

The database does not ship with a known admin password. Create the first account interactively; the password is hidden and is never accepted as a command argument:

```powershell
C:\tools\php85\php.exe -c php.dev.ini artisan admin:create admin@example.com --name="Platform Admin"
```

Administrators can manage users at `/admin/users`, AI operations at `/admin/ai`, and content integrity at `/admin/content-audit`.

## Running without AI

AI is disabled by default. Lessons, Search Course, notebooks, calculators, deterministic exercises/quizzes, mastery, progression, gates, strategies, backtests, the demo journal and resources remain functional. Ask the Course returns retrieved, cited source excerpts when generation is off.

```env
AI_ENABLED=false
AI_PAID_FALLBACK_ALLOWED=false
RAG_ENABLED=true
SEMANTIC_SEARCH_ENABLED=false
```

## Running with free hosted AI

Choose a current model from official provider documentation, set its server-side key/model, classify the account accurately, and enable the provider. Keep paid fallback prohibited. Example using OpenRouter's verified free router:

```env
AI_ENABLED=true
AI_PAID_FALLBACK_ALLOWED=false
OPENROUTER_ENABLED=true
OPENROUTER_API_KEY=
OPENROUTER_MODEL=openrouter/free
OPENROUTER_COST_CLASS=free
```

Free-tier availability, privacy, and rate limits can change. Run `php artisan ai:test-providers` and review [AI provider configuration](docs/AI_PROVIDER_CONFIGURATION.md).

## Running with Ollama

Ollama can provide tutoring, embeddings, both, or neither. It may run on a separate private host; do not install a large model on a small web VPS by default.

```env
AI_ENABLED=true
AI_LOCAL_FIRST=true
OLLAMA_ENABLED=true
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=
OLLAMA_EMBEDDING_MODEL=
AI_EMBEDDING_PROVIDER=ollama
SEMANTIC_SEARCH_ENABLED=true
```

After selecting/pulling models:

```powershell
C:\tools\php85\php.exe -c php.dev.ini artisan ai:test-providers --provider=ollama
C:\tools\php85\php.exe -c php.dev.ini artisan ai:reindex-course
```

## Adding OpenAI later

OpenAI is optional and classified paid. Set `OPENAI_ENABLED=true`, a model and key, then explicitly set `AI_PAID_FALLBACK_ALLOWED=true`. Without that global permission the router will not contact it.

## Routing, monitoring and privacy

The default chain is configured with `AI_PROVIDER_CHAIN`. Administrators can override provider priority, task routing, models, cost class and budgets without exposing keys. Useful commands:

```powershell
php artisan ai:status
php artisan ai:test-providers
php artisan ai:evaluate-models
php artisan ai:reindex-course
php artisan ai:usage-report
```

Cloud/local consent is learner-controlled under Settings → AI preferences. Student journals, answers and charts are not part of the canonical retrieval corpus. Free-tier data policies must be reviewed before cloud use.

See [AI architecture](docs/AI_ARCHITECTURE.md), [deployment](docs/AI_DEPLOYMENT.md), and the [permutation matrix](docs/AI_PERMUTATION_TEST_MATRIX.md).

The application is educational software, not investment advice, a signal service or a broker integration.
