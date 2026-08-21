# Source Conflicts

## Resolution policy

The Complete Course is authoritative for curriculum content. The Tutor-Led Edition is authoritative for guide, notebook, active-recall and mastery scaffolding. Exact repetitions are linked, not copied. Any unresolved item must remain visible to an administrator and must not silently change progression.

## Confirmed relationship

Every one of the 328 Complete Course pages has a unique exact extracted-text hash match inside the guided edition. No conflicting duplicate curriculum paragraph was found. The guided edition adds 272 pages rather than rewriting canonical chapter bodies.

## Review register

| ID | Type | Evidence | Required application treatment | Status |
| --- | --- | --- | --- | --- |
| SRC-001 | Schedule/order discrepancy | Guided Chapter 8 is labelled WEEK 3 - Session 3 but appears after Chapters 6-7, both labelled Week 4. Canonical order remains Chapters 1-8. | Preserve canonical chapter order. Store the guided schedule label verbatim, flag it for admin review, and do not sort curriculum solely by week number. | Open |
| SRC-002 | Presentation numbering defect | Canonical physical page 33 visibly shows automatic Chapter 2 above the explicit title Chapter 1 - What Forex Actually Is; later chapters and appendices have similarly offset automatic counters. | Use explicit Chapter N - Title and Appendix L - Title labels as identifiers. Never expose the automatic counter as curriculum identity. | Resolved by rule |
| SRC-003 | Threshold rendering ambiguity | Some canonical lists render source strings such as >=85% poorly, and text extraction may return =85%. The curriculum elsewhere states at least 85%, at least 100 serious demo trades and at least 95% adherence in unambiguous prose or frameworks. | Normalize to explicit >= comparators only where plain-language source text or an answer framework corroborates it. Store raw source text and flag uncorroborated cases. | Resolved for known gates; importer test required |
| SRC-004 | Parallel schedule versus sequence | Chapters 52-54 overlap by week labels (31-33, Week 32 audit, 34-36); Chapter 65 review spans Weeks 43-52 while serious demo Chapters 66-67 span Weeks 45-52. | Model schedule windows separately from prerequisite edges. Overlap is intentional parallel work, not permission to bypass prerequisite gates. | Resolved by model |
| SRC-005 | Micro-live versus IT eligibility nuance | Chapter 69 places micro-live in Months 15-18. Chapter 90 says micro-live is desirable before automating live execution, while research-only programming may begin after the knowledge/evidence gate. | Implement two decisions: micro-live unlock requires all live-risk prerequisites; research-only IT eligibility follows the Chapter 90 gate and cannot enable live automation. | Resolved by separate gates |
| SRC-006 | Time-sensitive facts | Regulation, broker licensing, tax, platform features, central-bank policy and market statistics are dated statements, mostly verified 19 Aug 2026. | Preserve the book statement/version, mark it time-sensitive, attach the official source and last-checked date, and show current updates separately. | Open freshness workflow |
| SRC-007 | Guided Chapter 90 mastery concatenation | The Chapter 90 mastery page appends the Final Comprehensive Examination introduction to teach-back question 6, creating an awkward combined prompt. | Preserve provenance but split the quiz question and final-exam instruction into separate blocks. Flag the segmentation decision in admin. | Resolved by segmentation rule |

## No silent choices

Open items must become admin-review records during ingestion. A source-version update may resolve them, but historical learner attempts must remain tied to the version they used.
