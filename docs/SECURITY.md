# Security Model

## Boundaries

- Authentication, verified-email middleware, password confirmation, passkeys and two-factor settings come from the Laravel starter stack.
- Student/admin authorization is enforced server-side. Admin routes use the `admin` middleware; learner records are always filtered by the authenticated user or active enrollment.
- Locked sessions, exercises, strategy construction, backtesting, robustness work, demo programs and journals are checked on the server. Changing a URL cannot make a locked curriculum unit available.
- Gate progression is backend-authoritative. A learner submission enters `awaiting_review`; only an audited admin review can pass the gate and unlock the next session.

## Assessment confidentiality

- Answer keys and answer explanations are never returned in the unanswered assessment payload.
- Source answer-key appendices are separate reference units, not chapter lesson blocks.
- The tutor detects direct answer requests and prompt overlap with active protected questions. It returns a Socratic hint instead of calling the model.
- AI never decides progression. Laravel services apply scores, notebook evidence, gate thresholds and readiness predicates.

## Data ownership and uploads

- Notebook, strategy, backtest, demo, journal, tutor-thread, bookmark and export queries are user-scoped.
- Uploaded notebook and exercise evidence is stored on Laravel's private `local` disk. There is no public storage route in this release.
- Uploads are MIME/extension allow-listed and size-limited. Production deployments should add malware scanning before allowing broader file types.
- Evidence records use append-only revisions where later edits would compromise auditability.

## AI and external content

- AI is disabled by default and requires both an environment flag and learner consent.
- Course retrieval is limited to mapped source segments. User-supplied URLs are stripped and are not fetched by the tutor.
- Responses use strict JSON Schema where a machine decision shape is required. Prompts treat retrieved excerpts and learner text as untrusted data.
- API keys remain server-side. Do not place `OPENAI_API_KEY` in frontend environment variables.

## Production checklist

1. Set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, HTTPS-only cookies, and trusted proxy/host settings.
2. Use a dedicated least-privilege database account and Redis credentials.
3. Restrict private storage and log directories to the application/worker account.
4. Configure rate limiting, WAF/proxy request limits, backups, alerting and log retention.
5. Run dependency, SAST and upload-malware scans in CI.
6. Rotate application, database and OpenAI secrets; never commit `.env`.
7. Review all machine mappings before making an editorial-completeness claim.
