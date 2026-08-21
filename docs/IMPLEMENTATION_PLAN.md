# Implementation Plan

## Current milestone

Phases A-I have an implemented release candidate. Both PDFs have been read end-to-end, all 928 pages and 5,807 meaningful segments retain provenance, structured parity is enforced in tests, and the Laravel application now exposes the learner, practice, optional AI and admin workflows. Phase J is in release verification. Machine coverage is complete; the separate human editorial queue remains open at 0/5,807 approved mappings.

## Phase B - Domain architecture

Deliver:

- an architecture decision record for the Laravel 13, Inertia, React and TypeScript monolith;
- PostgreSQL-first schema with UUID or ULID identifiers, curriculum/source versioning and immutable attempt history;
- source provenance model: source documents, versions, pages, segments, segment relations and content mappings;
- curriculum hierarchy: courses, curriculum versions, parts, phases, weeks, sessions, sections and ordered typed content blocks;
- concepts, prerequisites, objectives, rubrics, questions, exercises, gates and backend-owned unlock rules;
- notebook, practice, journal, strategy, backtest, robustness, demo and evidence domains;
- explicit authorization boundaries for student, optional reviewer and administrator;
- AI boundary: retrieval and grading recommendations only; progression remains deterministic backend logic.

Exit criteria: the schema can answer both provenance questions in plan.md, progression rules are expressible without UI code, and no curriculum prose is hard-coded in React.

## Phase C - Course ingestion

1. Import document metadata, hashes, pages, links and outlines from the audit artifacts.
2. Segment canonical pages into headings, paragraphs, lists, formulas, tables, questions, answers and resources.
3. Link all 328 duplicate guided pages to canonical segments.
4. Segment the 272 additional guided pages into guide, notebook, recall and mastery blocks.
5. Import all 90 chapters, 540 quiz questions, 379 explicit exercise tasks, seven named gate exams, Chapter 90 graduation criteria, the 100-question final exam, 12 books, four papers, 63 unique URLs and Appendices A-Z.
6. Generate mappings and an exception report after every import.
7. Require reviewed 100% meaningful-segment coverage before calling ingestion complete.

Exit criteria: zero unexplained segments; every source page resolves to application content and every application lesson resolves back to its sources.

## Phase D/E first polished vertical slice

Build onboarding through Week 1, Session 1:

1. Registration, verification, learner profile, timezone and AI opt-in.
2. Resume target and locked course map.
3. Chapter 1 guide broken into small steps.
4. Concept Notebook prompts and exact-position resume.
5. Free-text comprehension attempt with stored rubric and evidence.
6. Deterministic fallback when AI is disabled or unavailable; AI semantic grading when enabled.
7. Targeted remediation and a second attempt.
8. Six-question Chapter 1 quiz, protected answers and at least 85% threshold.
9. Backend mastery decision and next-session unlock.
10. Tests for paraphrase acceptance, keyword-rich misconception rejection, URL bypass and cross-user privacy.

No lorem ipsum or fake tutor responses are permitted.

## Remaining phases

| Phase | Main deliverable | Non-negotiable exit evidence |
| --- | --- | --- |
| D | Dashboard, course map, session engine, notebooks and resume | Implemented; automated learner-flow tests |
| E | Exercises, quizzes, rubrics, mastery, remediation and gates | Implemented; protected answers and manual gate workflow |
| F | Chart, macro, strategy, backtest and robustness systems | Implemented; chapter-gated, immutable evidence records |
| G | Serious demo, journal, integrity challenge and readiness | Implemented; 100-trade and 95% predicates are backend-owned |
| H | Optional Responses API tutor with retrieval | Implemented, disabled by default; grounded citations and shielding |
| I | Admin CMS, conflicts, source versions, costs and feature flags | Implemented audit/review operations |
| J | Coverage, security, performance, browser and deployment QA | In final verification; in-app browser unavailable in this environment |

## Cross-cutting tests

- Unit: pip, risk, position, R, expectancy, profit factor, drawdown, mastery, review scheduling, versioning and unlock predicates.
- Feature: authentication, onboarding, progression, attempts, notes, uploads, strategies, backtests, demo, exports and admin.
- Permission: no student can access another learner's private records or any admin function.
- AI contract: structured schema, source citations, assessment shielding, graceful failure and retained student answer.
- Content parity: chapters, headings, paragraphs, exercises, quizzes, links, books, gates, appendices and unmapped exceptions.
- Browser: onboarding through multiple lesson steps, remediation, Chapter 1 quiz, mastery and next unlock on desktop and mobile.

## Operational baseline

- Queues: document ingestion, embedding or file search, AI grading retries, exports and reports.
- Scheduler: reminders, spaced review, stale-resource checks, usage aggregation and coverage audits.
- Storage: private learner uploads behind authorization or signed access; object-storage compatible.
- Cache/Redis: navigation, glossary/resource metadata, rate limits and queues where configured.
- Configuration: AI_TUTOR_ENABLED=false must leave all core learning, objective grading, calculators, notes and progress operational.

## Framework setup after architecture review

The application setup sequence is:

    composer create-project laravel/laravel .
    composer require inertiajs/inertia-laravel
    npm install
    php artisan migrate
    php artisan test
    npm run build

Exact package versions and Laravel 13 compatibility must be checked at implementation time. The source audit is intentionally independent of framework setup.
