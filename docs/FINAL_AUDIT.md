# Final Pre-Delivery Audit

Date: 21 August 2026

1. **How many source chapters exist?** 90 canonical chapters.
2. **How many are represented?** 90 chapter/session units, grouped into eight phases.
3. **How many exercises?** 379 first-class practice assignments.
4. **How many quizzes?** 90 chapter quizzes with 540 prompts and 540 protected answer references.
5. **How many external resources?** 63 unique external URLs, each retaining its source page and source verification date.
6. **How many book references?** 12. Four academic papers are also first-class resources.
7. **How many gate assessments?** Seven named gate exams with 142 questions, plus one separate 100-question Final Comprehensive Examination.
8. **Are all appendices represented?** Yes, Appendix A through Appendix Z: 26/26 units with readable blocks and provenance.
9. **Are all source segments mapped?** Yes at the machine-ingestion layer: 5,807/5,807 meaningful segments, zero unmapped, 328/328 duplicate guided pages linked. Human editorial approval remains a separate 0/5,807 review queue.
10. **Can the educational flow operate without AI?** Yes. Content, notebook evidence, exercises, protected-reference grading, manual gates, calculators, practice systems, readiness and progression remain available. AI is off by default.
11. **Does semantic grading understand paraphrases?** The hand-authored Chapter 1 deterministic rubric accepts ordinary paraphrases and alternatives. Later imported quizzes use a deterministic protected-reference concept fallback without AI; optional AI provides broader semantic paraphrase handling. This broader later-chapter parity remains a known qualitative limitation of AI-off grading.
12. **Does an incorrect but keyword-rich answer fail?** Yes for safety-critical Chapter 1 rubrics: explicit contradictions such as reversing base/quote meaning produce a `misconception` result even when other keywords appear.
13. **Can AI reveal active assessment keys?** The learner payload never contains keys. The tutor shields direct answer requests and prompts that overlap protected active questions before model invocation; covered by tutor-guard tests.
14. **Can a student bypass progression by changing URLs?** No. Session, exercise and gate routes validate active enrollment and locked status. Strategy, backtest and robustness creation additionally checks the required chapter server-side.
15. **Can a student modify another student's records?** Ownership filters protect bookmarks, notebooks, strategies, backtests, demo programs, journals, tutor threads and exports. Cross-user backtest mutation is explicitly tested.
16. **Is AI curriculum teaching grounded?** Yes. The tutor receives only retrieved mapped course segments and returns page/document citations. User URLs are removed and never fetched.
17. **Can administrators see coverage exceptions?** Yes. The admin audit separates machine coverage, structured parity, editorial review status, open conflicts, gate reviews, feature flags and AI usage. Two known source conflicts remain open: schedule order and time-sensitive-resource freshness.
18. **Does serious demo remain locked?** Yes. The backend requires the relevant curriculum stage, an owned frozen strategy, development and out-of-sample evidence, and robustness evidence. Micro-live readiness preserves the 100-trade and 95% adherence predicates.

## Release evidence

- Machine coverage: pass, 100%.
- Structured parity: pass.
- PHPUnit: 68 tests passed, 493 assertions.
- Pint, Prettier, ESLint and TypeScript: pass.
- Vite production build: pass, 3,399 modules transformed.
- HTTP smoke: public routes return 200; anonymous learner/admin routes redirect to login.
- Authenticated real-browser visual QA: not run because the in-app browser environment reported no browser instance. This limitation is recorded in `QA_REPORT.md`.
