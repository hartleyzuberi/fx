# AI provider configuration

Verified against official provider documentation on 2026-08-21. Runtime model defaults are intentionally blank. Administrators must select and evaluate a model; model availability and free-tier terms change.

## Current verified options

| Provider | Verified option/example | Cost policy | Notes |
|---|---|---|---|
| Ollama | Operator-selected chat model; `embeddinggemma` is an official recommended embedding model | `local` | `/api/chat` and `/api/embed` are supported. Hardware and retention are operator controlled. |
| Groq | Production catalog includes `openai/gpt-oss-20b` and `openai/gpt-oss-120b` | Explicit `GROQ_COST_CLASS` | Groq publishes free-plan rate limits, but an upgraded account can be billable. Confirm the account plan. |
| Gemini | `gemini-3.7-flash` was listed with eligible free-tier input/output | Explicit `GEMINI_COST_CLASS` | Free-tier content may be used to improve products; do not send private journals by default. Use a stable model ID and re-evaluate before changing it. |
| OpenRouter | `openrouter/free` | `free` only when the free router or a verified `:free` model is used | Free availability/rate limits vary. The returned model is logged for auditability. |
| OpenAI | Select from the account's `/v1/models` response | `paid` | Optional only. It is filtered out whenever `AI_PAID_FALLBACK_ALLOWED=false`. |

Official references:

- Groq: https://console.groq.com/docs/models and https://console.groq.com/docs/rate-limits
- Gemini: https://ai.google.dev/gemini-api/docs/models and https://ai.google.dev/gemini-api/docs/pricing
- OpenRouter: https://openrouter.ai/docs/guides/routing/routers/free-router
- Ollama: https://docs.ollama.com/api/embed and https://docs.ollama.com/api/chat
- OpenAI: https://platform.openai.com/docs/api-reference/models

## Configuration rules

1. Set the server-side key, model, and provider `*_ENABLED=true`.
2. Classify the active account/model as `local`, `free`, or `paid`.
3. Leave `AI_PAID_FALLBACK_ALLOWED=false` for zero-cost operation.
4. Run `php artisan ai:test-providers --provider=...`.
5. Run `php artisan ai:evaluate-models --provider=...` before routing assessment traffic.
6. Use the admin AI page to set priority/budgets and inspect failures. Keys are never returned or editable there.

## Capability registry

Capabilities are configuration-driven and can be narrowed by database override. The router requires the task capability before selection. A provider marked text-capable is not assumed to support vision or embeddings. Structured assessment requires `structured_output`; embeddings require an adapter implementing the embedding contract.

## Model change procedure

Do not silently replace a missing model. A `404` is classified `model_not_found`, recorded, and surfaced to administration. Evaluate the replacement, compare grading/citation/schema/latency results, then update the model through the admin page.
