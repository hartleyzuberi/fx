# Free-first AI mode test

Policy: `AI_PAID_FALLBACK_ALLOWED=false`.

Automated mocks verify:

- a rate-limited Groq primary falls back to the OpenRouter free route;
- both attempts are logged and only the second is marked fallback;
- OpenAI is never contacted when it is the only remaining paid candidate;
- a non-retryable provider rejection does not fan out;
- local-only preference calls Ollama and never a cloud provider;
- cloud-disabled preference results in retrieval-only learning.

Live status on 2026-08-21: every provider is `DISABLED`, so live quality/rate-limit behavior is `NOT CONFIGURED`. This is an acceptable zero-cost deployment: the course and retrieval continue. Run `ai:test-providers` after supplying a free-tier key/model.
