# QA Report

Date: 21 August 2026  
Environment: Windows local build, PHP 8.5, SQLite, Node.js 20.19.4

## Automated coverage

- PHP feature/unit suite covers authentication, onboarding, enrollment, locked-route enforcement, resume ownership, notebook evidence, assessment attempts, misconception rejection, gate submission/review, admin authorization, evidence ownership, calculators, performance metrics, tutor safety, OpenAI contracts and source ingestion.
- The structured parser test applies 281 assertions to the supplied source artifacts, including 90 quizzes, 540 prompts, 540 protected answers, 379 exercises, 142 gate questions, the 100-question final examination, 26 appendices, 63 URLs, 12 books and four papers.
- Frontend validation includes Prettier, ESLint, TypeScript compilation and Vite's production build.
- Database audit verifies 928/928 pages, 5,807/5,807 meaningful mappings, 328/328 duplicate guided pages and full structured parity.

Final release-candidate results:

| Check | Result |
| --- | --- |
| PHPUnit | 68 passed, 493 assertions |
| PHP Pint | Passed |
| Prettier | Passed |
| ESLint | Passed |
| TypeScript `--noEmit` | Passed |
| Vite production build | Passed, 3,399 modules transformed |
| Composer manifest validation | Passed |
| Machine source coverage | Passed, 100% |
| Structured source parity | Passed |
| Public `/` and `/login` HTTP smoke | 200 |
| Anonymous `/dashboard` and `/admin/content-audit` | 302 to `/login` |

## Security and behavior checks

- Learners cannot open locked sessions or submit work for another curriculum version.
- Advanced practice creation is rejected until its chapter is available.
- Another learner cannot append to or complete an owned backtest.
- Serious demo cannot start without a frozen strategy, development and out-of-sample evidence, and robustness predicates.
- Gate submissions do not self-grade or unlock progression.
- Active-assessment answer requests, including close prompt paraphrases, are shielded before model invocation.
- Learner assessment payloads do not expose answer keys.
- AI-disabled states are honest and preserve all non-AI learning functions.

## Manual/browser limitation

The in-app browser runtime reported no available browser instance in this environment. Automated HTTP smoke checks and feature tests are therefore the release evidence here; authenticated visual interaction was not falsely marked complete. Before production, run a real-browser matrix on current Chrome, Edge, Firefox and mobile Safari/Chrome, including keyboard navigation, file uploads and the full gate flow.

## Known operational review

Machine mapping and source parity pass, but editorial approval is still 0/5,807. Later chapter quizzes use a deterministic protected-reference concept fallback when AI is disabled; the hand-authored Chapter 1 rubric has richer misconception handling, while optional AI provides broader paraphrase recognition. Gate exams always require human review.
