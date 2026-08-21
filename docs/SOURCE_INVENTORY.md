# Source Inventory

Audit date: 21 August 2026

## Scope and method

This inventory covers both supplied PDFs from first page to last page. Text was extracted from every physical page, each page was hashed, embedded PDF outlines were flattened with hierarchy and destinations, hyperlink annotations were enumerated, and representative pages were rendered for visual inspection. Page-presence markers below are discovery aids, not counts of atomic curriculum items.

The source hierarchy is:

1. Complete Course - canonical curriculum content, sequence, quizzes, gates, appendices, answer material, resources and policy.
2. Tutor-Led Edition - canonical guided-study scaffolding. Its embedded canonical pages link to the Complete Course instead of creating a second editable copy.

## Document register

| Source ID | Role | Version/date | Pages | Extracted characters | Outline entries | SHA-256 |
| --- | --- | --- | ---: | ---: | ---: | --- |
| complete_course | Canonical curriculum | Version 1.0, 19 Aug 2026 | 328 | 485,759 | 1,117 | e50a854f1044ad231eee0032545eeb7b32cb0e420b87d59a8966eae2cbdcf20e |
| tutor_led | Canonical instructional scaffolding | Created 19 Aug 2026 | 600 | 1,003,556 | 274 | 91ee41327dec4c6e1967fa482fb1e6ddb89ccea3d9fcdc94712b0c3ac844ae7a |

No page is text-empty. The guided edition contains an exact one-to-one text-hash match for all 328 Complete Course pages and adds 272 guide, mastery and front-matter pages.

## Canonical structural inventory

- 10 parts.
- 90 explicitly titled curriculum chapters.
- 1,117 outline nodes: parts, chapters, sections and subsections.
- 90 chapter quizzes with six questions each: 540 quiz questions.
- 86 explicit exercise sections containing 377 numbered prompts plus two unnumbered structured exercises: 379 explicit exercise tasks. Practical protocols and gates are separate.
- 7 named gate exams (A-G).
- 1 Chapter 90 graduation gate before the IT/systematic track.
- 1 final comprehensive examination with 100 questions and a recommended 90% pass standard.
- 26 appendices (A-Z).
- 12 staged book references.
- 4 named research papers.
- 227 hyperlink annotations resolving to 63 unique external URLs.

## Guided-edition structural inventory

- 90 guided chapter units with week, multi-week, month, operational or graduation schedule labels.
- 90 Original Chapter anchors.
- 90 STOP - MASTERY CHECK units.
- Each guide supplies a session rule, required outcome, notebook setup, before-reading prompts, first-principles walkthrough, note-down headings, anchor statements, interaction instructions and companion references.
- Each mastery unit supplies closed-book teach-back questions, notebook verification, a progression standard, tutor handoff and a one-sentence retention test.

## Parts

| Source part | Title | Contents |
| --- | --- | --- |
| Part I | Orientation and Market Mechanics | Chapters 1-8 |
| Part II | Reading Price and Technical Analysis | Chapters 9-17 |
| Part III | Fundamental and Macro Analysis | Chapters 18-33 |
| Part IV | Risk, Probability and Money Management | Chapters 34-42 |
| Part V | Trading Styles, Strategy Design and Evidence | Chapters 43-58 |
| Part VI | Psychology, Execution Discipline and the Trading Journal | Chapters 59-65 |
| Part VII | Serious Demo Trading, Broker Due Diligence and Micro-Live Progression | Chapters 66-75 |
| Part VIII | Advanced Foreign-Exchange Literacy | Chapters 76-90 |
| Part IX | Curriculum Calendar, Reading Plan and Working Templates | Appendices A-U |
| Part X | Quiz Answer Key and Assessment Rubrics | Appendices V-Z |

The full 90-row chapter/session crosswalk is in CURRICULUM_MAP.md.

## Page-presence inventory

These counts show how many pages contain at least one conservative marker. They prevent categories from being forgotten during detailed segmentation; they are not final item counts.

| Content family | Complete Course pages | Tutor-Led pages |
| --- | ---: | ---: |
| Learning objectives | 112 | 202 |
| First-principles explanations | 205 | 301 |
| Examples/worked examples | 49 | 250 |
| Formula/calculation material | 91 | 247 |
| Tables | 10 | 15 |
| Charts/diagrams | 38 | 62 |
| Notebook/write-down instructions | 7 | 279 |
| Warnings/traps | 88 | 273 |
| Misconceptions/common mistakes | 5 | 6 |
| Exercises/drills/assignments | 116 | 298 |
| Comprehension/mastery checks | 0 | 181 |
| Assessments/exams | 27 | 27 |
| Gates/progression standards | 42 | 141 |
| Resources/references | 33 | 126 |
| Regulatory material | 36 | 75 |
| Central-bank material | 40 | 76 |
| Software/platform material | 45 | 62 |
| Practice/replay/backtest material | 213 | 249 |
| Strategy material | 151 | 259 |
| Demo/forward-test material | 66 | 98 |
| Journal material | 32 | 68 |
| Micro-live material | 20 | 34 |
| Glossary material | 3 | 3 |
| Worksheets/templates | 7 | 8 |

## Required semantic content families

| Source content | Required representation |
| --- | --- |
| Parts, phases, weeks, sessions, chapters, sections, subsections | Versioned curriculum hierarchy and ordered units |
| Objectives, definitions, first-principles explanations, key concepts | Objectives, concepts and structured content blocks |
| Examples, equations, formulas, tables and diagrams | Typed examples/formulas/media with accessible alternatives |
| Notebook setup, note-this and write-down instructions | Notebook tasks linked to Concept, Chart, Macro or Trading Journal |
| Warnings, traps and misconceptions | Safety callouts plus misconception taxonomy |
| Exercises, reflection, comprehension and teach-back | Typed exercises with attempts, rubrics and feedback |
| Quizzes, choices, answers and explanations | Versioned question bank; answers protected until submission |
| Gate exams, mastery gates and graduation criteria | Backend-owned rule sets and evidence requirements |
| Books, papers, URLs and official resources | Resource records, citations, authority, freshness and lesson links |
| Platform, broker and regulator material | Time-sensitive resource records; no implied current endorsement |
| Chart/replay/macro/strategy/backtest/robustness/demo work | Dedicated practice-domain records and evidence uploads |
| Journal fields and integrity/100-trade requirements | Structured journal schema and immutable audit trail |
| Appendices, glossary, formulas and worksheets | Dedicated libraries/templates, not hidden attachments |
| Final assessment and answer frameworks | Protected assessment version plus scoring/remediation rubrics |

## Appendices

| Appendix | Title | Canonical physical page |
| --- | --- | ---: |
| A | The 52-Week Core Curriculum | 258 |
| B | Book Curriculum and Reading Order | 262 |
| C | Free Primary Resource Library | 266 |
| D | Research Papers Worth Reading After the Foundations | 269 |
| E | Formula Sheet | 270 |
| F | Course Risk Policy Card | 272 |
| G | 100-Chart Annotation Worksheet | 273 |
| H | Economic Event Study Worksheet | 275 |
| I | Weekly Macro Sheet | 276 |
| J | Position-Sizing Drill Sheet | 278 |
| K | One-Page Strategy Specification Template | 279 |
| L | Manual Backtest / Replay Log | 281 |
| M | Robustness Matrix | 282 |
| N | Pre-Trade Checklist | 283 |
| O | Full Trade Journal Template | 284 |
| P | Weekly Review Template | 286 |
| Q | Monthly Strategy Scorecard | 288 |
| R | Broker Scorecard | 289 |
| S | Micro-Live Go/No-Go Form | 290 |
| T | Strategy Retirement / Pause Form | 291 |
| U | Forex and Trading Glossary | 292 |
| V | Chapter Quiz Answer Key, Chapters 1-30 | 300 |
| W | Chapter Quiz Answer Key, Chapters 31-60 | 308 |
| X | Chapter Quiz Answer Key, Chapters 61-90 | 315 |
| Y | Gate Exam Answer Framework | 322 |
| Z | Source Framework, Bibliography and Update Policy | 325 |

## Audit artifacts

- scripts/source_audit.py recreates page and outline evidence.
- tmp/pdfs/source-audit/manifest.json stores metadata and counts.
- The two pages JSONL files store every page, text hash, marker and external link.
- The two outline JSON files store every outline node and physical destination.
- canonical_to_guided_page_map.json stores all 328 exact duplicate-page relationships.

These are audit inputs, not application-ready curriculum segments. Paragraph/block segmentation remains a Phase C responsibility.
