# AI final audit

## Architecture

Provider-neutral contracts, DTOs, five adapters, capability registry, per-task router, circuit breaker, usage service, prompt registry, runtime resolver, and embedding/search services are implemented inside the Laravel monolith.

## Provider status

- Groq: adapter operational; live model NOT CONFIGURED.
- Gemini: text/structured/embedding adapter operational; live model NOT CONFIGURED.
- OpenRouter: adapter operational; free router supported; live key NOT CONFIGURED.
- Ollama: text/structured/embedding adapter operational; local server/model NOT CONFIGURED.
- OpenAI: optional text/structured/embedding adapter operational; disabled and classified paid.

## Free configuration

PASS. With paid fallback false, paid candidates are eliminated before an HTTP request. The complete canonical curriculum uses deterministic/mastery/manual-review pathways and requires no model.

## Retrieval

PASS for local lexical mapped-course search and cited retrieval. Vector/hybrid support is implemented but live embeddings are NOT CONFIGURED. It does not pretend semantic/vector search has passed.

## Privacy and administration

PASS for server-only secrets, learner local/cloud preference, private-source exclusion, provider metadata, user administration, audit events, last-admin protection, feature stops, routing controls, budgets, health and usage diagnostics.

## Verification

- PHPUnit: PASS — 81 tests, 540 assertions.
- Laravel Pint: PASS.
- ESLint: PASS.
- TypeScript (`tsc --noEmit`): PASS.
- Production Vite build: PASS — 3,402 modules transformed.
- Targeted PHPStan over the new and modified AI/admin/tutor surface: PASS with zero errors. A whole-project scan still reports 120 legacy findings in unrelated pre-existing controllers and models.
- Source audit: PASS — 928 source pages, 5,807 meaningful segments mapped, 100% coverage, 328/328 guided-study references, 90 chapters, 540 quiz questions, 379 exercises, 7 practical gates, and a 100-question final assessment.
- No-AI end-to-end course search and Ask-the-Course coverage: PASS with zero provider requests.

Provider live smoke tests are `NOT CONFIGURED`; vision and current-web retrieval are optional and remain deferred/disabled. A final in-app browser pass could not be completed because the sandbox-started local PHP server did not load its SQLite driver, although the same PHP configuration passed the HTTP/Inertia feature suite and production asset build.

## Explicit answer

Can this platform operate without OpenAI or any other paid model?

**Yes.**
