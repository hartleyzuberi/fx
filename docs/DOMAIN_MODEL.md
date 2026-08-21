# Domain Model

## Identity and authorization

`users.role` is one of `student`, `reviewer` or `admin`. A learner profile stores timezone, experience, objectives, pace, available study hours, reminder preference and explicit AI-tutor consent. Roles are enforced by policies and middleware; hiding a link is never treated as authorization.

## Source provenance graph

```text
source_document -> source_version -> source_page -> source_segment
                                               \-> source_link
source_segment -> source_relation -> source_segment
source_segment -> content_mapping -> content_block -> learning_unit
```

Every page and segment stores stable source order and a SHA-256 checksum. A mapping records whether the relationship is canonical, guided, duplicate, supporting or administrative, plus its review state. This answers both required questions:

- Page to lesson: document/version/page -> segments -> mappings -> content blocks -> units.
- Lesson to passages: unit -> content blocks -> mappings -> segments -> page/document.

Duplicate Tutor-Led pages point to their Complete Course equivalents with a `duplicate_of` segment relation. They may share a content block mapping without maintaining a second editable curriculum copy.

## Curriculum graph

```text
course -> curriculum_version -> learning_unit (tree)
learning_unit -> content_block
learning_unit <-> concept
learning_unit -> objective -> rubric
learning_unit -> assessment -> question
learning_unit -> unlock_rule -> prerequisite unit/concept/evidence
```

`learning_units` uses a parent ULID and a materialized path so the same table can represent parts, phases, weeks, sessions, chapters, appendices and reference libraries. Ordered typed `content_blocks` represent objectives, explanations, examples, notes, recall prompts, exercises, quizzes, resources and source views without hard-coding prose in React.

Published versions are immutable. A new version may supersede another, while learner attempts retain the exact version and question revision they encountered.

## Learner evidence and progression

```text
enrollment -> unit_progress -> learning_position
enrollment -> concept_mastery -> mastery_evidence
enrollment -> assessment_attempt -> answer_attempt
enrollment -> review_item
```

An answer attempt is never overwritten. Reattempts append evidence. AI grading, when enabled, is stored separately as a structured recommendation with model, prompt version and citations. Deterministic grading handles objective items and calculations. `ProgressionService` evaluates database rules and promotes state only when all required evidence is present.

Allowed unit states are `locked`, `available`, `in_progress`, `needs_review`, `passed` and `mastered`. Concept states are `unseen`, `exposed`, `practicing`, `weak`, `competent`, `mastered` and `due_for_review`.

## Practice and readiness

Practice assignments reference curriculum versions and concepts. Chart evidence, macro event studies, strategy versions, backtest observations, robustness runs and demo observations are separately typed, versioned and learner-owned. Demo readiness requires backend predicates including sample size, adherence, required gates and unresolved safety-critical misconceptions. There is no profit leaderboard.

## AI boundary

The tutor can retrieve mapped source segments, explain cited material, assess free text against a rubric and suggest remediation. It cannot reveal protected answers during active assessments, modify progression, claim live/current facts without an approved current-data path, or operate when learner consent/feature flags disallow it. Structured assessment output is validated before persistence.

