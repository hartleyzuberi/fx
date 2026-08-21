# ADR 001: Application Architecture

- Status: Accepted
- Date: 2026-08-21
- Decision owners: Product and engineering

## Context

The platform must preserve and prove coverage of two large source books while delivering a mastery-based learning application. It also needs deterministic calculations and progression, private learner evidence, an optional AI tutor, administrative review, and a practical deployment path.

## Decision

Build a modular Laravel 13 monolith with Inertia 3, React 19, TypeScript, Tailwind CSS 4 and PostgreSQL as the production database. SQLite remains supported for fast automated tests and local evaluation.

Laravel owns authentication, authorization, validation, curriculum rules, progression, calculations, audit trails, queues and all persistence. React renders server-provided view models and never embeds curriculum prose or decides unlocks. Inertia is the application boundary; JSON endpoints are added only where a genuinely asynchronous interaction requires them.

All domain records use ULIDs except framework-owned tables. Curriculum content is immutable once published: editing creates a new curriculum version. Assessment attempts, mastery evidence, strategy versions, backtest observations and demo records are append-only; corrections are represented by superseding records or explicit review events.

## Module boundaries

| Module | Owns | Must not own |
| --- | --- | --- |
| Source | documents, versions, pages, segments, relations, hashes | learner progression |
| Curriculum | course versions, ordered units, content blocks, concepts, objectives | private learner answers |
| Assessment | rubrics, questions, attempts, grading evidence, gates | direct unlock mutation by AI |
| Learning | enrollment, exact resume state, concept mastery, review queue | source authoring |
| Notebooks | concept, chart, macro and journal evidence | public file access |
| Practice | assignments, charts, macro studies, strategies, backtests, robustness | fabricated market results |
| Demo | forward-test observations, adherence, integrity challenge, readiness | broker execution |
| Tutor | retrieval, citations, structured grading recommendations, usage | authoritative progression decisions |
| Admin | publishing, conflict review, coverage, flags, audit inspection | silent source overrides |

## Trust boundaries

1. Student-owned records are always scoped by authenticated user ID and policy checks.
2. Reviewers may inspect explicitly assigned evidence but cannot publish curriculum or change roles.
3. Administrators may manage content and exceptions, but learner attempt history remains immutable.
4. Uploaded files use private storage, validated MIME/size constraints and authorized download routes.
5. Retrieved text and user input are untrusted data. They cannot change system instructions, reveal active assessment answers or invoke course unlocks.
6. The AI tutor returns recommendations only. A Laravel mastery service evaluates stored rules and evidence before state changes.

## Runtime and operations

- PHP 8.4.1 or newer; the development environment currently uses PHP 8.5.
- PostgreSQL in production; Redis for queues, rate limiting and cache when configured.
- Queue jobs handle ingestion, tutor calls, exports and long-running reports.
- The scheduler handles spaced review, reminders, stale-resource checks, usage aggregation and coverage audits.
- `AI_TUTOR_ENABLED=false` is a fully supported mode; reading, objective grading, calculations, notebooks and progression remain functional.
- Source PDFs are not public web assets. Imported text and provenance are served only through authorized application views.

## Consequences

The monolith keeps transactions and authorization straightforward while preserving clear module seams for later extraction. Versioning and append-only evidence increase row counts, but they make auditability and learner-history reconstruction possible. The frontend stays replaceable because progression and course content remain backend data rather than React constants.

