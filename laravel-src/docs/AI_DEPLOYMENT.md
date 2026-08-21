# AI deployment

## Profile A: zero-model-cost MVP

Run Laravel normally with `AI_PAID_FALLBACK_ALLOWED=false`. Keep Search Course/RAG enabled. Optionally configure a Groq or Gemini free-tier project or OpenRouter's verified free route. Explicitly classify each provider/model as free and set daily/provider budgets. Free tiers are not guaranteed and may have privacy limitations.

## Profile B: self-hosted AI

Run Ollama on suitable separate hardware when the Laravel host is resource constrained. Set `OLLAMA_BASE_URL` to the private AI host, choose a chat model appropriate to RAM/GPU, choose an embedding model, enable Ollama, test it, then run `ai:reindex-course`. Keep the connect timeout short so an offline local host does not occupy PHP workers.

## Deployment sequence

```text
php artisan migrate --force
php artisan admin:create admin@example.com --name="Platform Admin"
php artisan ai:status
php artisan ai:test-providers
php artisan ai:evaluate-models --provider=<configured-provider>
php artisan ai:reindex-course       # only when embeddings are configured
php artisan course:audit-coverage
```

The admin command prompts for a hidden password and verifies the account. Never pass passwords or provider keys as command arguments.

## Operations

- Provider keys: environment/secret manager only.
- Provider enable/model/priority/cost class/budgets: `/admin/ai`.
- User role/status/progress: `/admin/users`.
- Feature emergency stops: `/admin/ai`.
- Usage: admin page or `ai:usage-report`.
- Health: `ai:status`; live inference check: `ai:test-providers`.
- Course vectors: `ai:reindex-course`; unchanged hashes are skipped.

Back up the relational database before deployment. The migration is additive and preserves existing tutor response IDs/model history.
