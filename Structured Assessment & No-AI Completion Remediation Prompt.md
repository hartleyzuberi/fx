# STRUCTURED ASSESSMENT & NO-AI COMPLETION REMEDIATION

Your audit has identified an important implementation gap.

The existing system currently:

- stores curriculum assessment questions primarily as `free_response`;
- renders textareas for learner answers;
- grades them through `RubricGrader`;
- optionally invokes AI semantic grading for partially correct/incorrect responses;
- uses `manual_review` for some gates and the final examination;
- has database fields such as `question_type` and nullable `choices`, but does not currently use them for the canonical curriculum.

Do **not** remove or break this existing functionality.

Instead, enhance it so that the learner can complete **100% of the canonical course, every mandatory gate, and the final graduation requirements with:**

```env
AI_ENABLED=false
```

and:

```text
no human reviewer
no AI model
no Ollama
no paid API
no cloud AI
```

Human review and AI semantic grading may remain available as richer optional paths.

They must not be mandatory for self-study completion.

---

# 1. PRESERVE THE EXISTING FREE-RESPONSE SYSTEM

Keep:

- existing source-ingested free-response questions;
- `reference_answer`;
- existing rubrics;
- `RubricGrader`;
- AI semantic reassessment;
- answer attempt history;
- source provenance;
- existing chapter quiz content.

Do not convert everything into MCQs and discard the original material.

The existing free-response questions remain valuable when AI semantic assessment is enabled.

We are adding a second deterministic pathway.

---

# 2. IMPLEMENT TWO VALID ASSESSMENT PATHS

Every mandatory concept/gate must ultimately support:

```text
                 CONCEPT MASTERY
                       │
          ┌────────────┴────────────┐
          │                         │
          ▼                         ▼
   SEMANTIC PATH              STRUCTURED PATH
          │                         │
   Free-response               MCQ
   + AI grading                Multi-select
          │                    Matching
          │                    Ordering
          │                    Calculations
          │                    Scenarios
          │                    Misconception detection
          │                    Application questions
          │                         │
          └────────────┬────────────┘
                       ▼
                 MASTERY ENGINE
```

Both paths must test the **same learning objective**.

AI availability must affect teaching experience, not whether graduation is possible.

---

# 3. STRUCTURED QUESTION TYPES

Implement first-class support for at least:

```text
single_choice
multiple_select
true_false
matching
ordering
numeric
calculation
scenario_choice
misconception_choice
classification
fill_blank
```

Keep:

```text
free_response
manual_review
```

as additional types.

Do not force all questions into one schema if a cleaner type-specific schema is appropriate.

---

# 4. QUESTION OPTION SCHEMA

Structured questions need proper stored options.

Example:

```json
{
  "question_type": "single_choice",
  "prompt": "What does EUR/USD = 1.2500 mean?",
  "options": [
    {
      "id": "a",
      "text": "One USD equals EUR 1.25",
      "misconception": "reversed_base_quote"
    },
    {
      "id": "b",
      "text": "One EUR equals USD 1.25"
    },
    {
      "id": "c",
      "text": "EUR 1.25 equals USD 1"
    },
    {
      "id": "d",
      "text": "The two currencies have equal value"
    }
  ],
  "correct_option_ids": ["b"]
}
```

The correct answer must remain server-side.

Never expose hidden grading metadata merely by rendering the question.

---

# 5. SOURCE-GROUNDED QUESTION BANK

Build structured questions from the **canonical supplied forex curriculum**, not from generic internet knowledge.

Use:

- chapter learning objectives;
- definitions;
- formulas;
- examples;
- worked examples;
- warnings;
- common misconceptions;
- chapter exercises;
- existing quiz questions;
- existing reference answers;
- mastery checks;
- gate requirements;
- glossary entries.

Every canonical structured question must maintain provenance.

Store fields such as:

```text
source_document
source_segment_id
source_page
source_section
course_version
chapter_id
lesson_id
concept_id
learning_objective_id
```

It must be possible to answer:

> Which course material proves that option B is correct?

---

# 6. DO NOT INVENT CANONICAL FACTS

When deriving a structured question, the correct answer must be demonstrably supported by the source.

If source evidence is insufficient or ambiguous:

```text
status = requires_review
```

Do not publish the question into a formal assessment.

Do not use general model knowledge to manufacture the answer.

---

# 7. BUILD-TIME AI MAY CREATE CANDIDATES, NOT AUTHORITY

If an AI model is available during development/content generation, it may help produce **candidate**:

- distractors;
- scenario variants;
- question phrasings;
- misconception variants.

But generated candidates must be validated against canonical curriculum material before receiving:

```text
status = approved
```

The runtime learner platform must not require AI to regenerate these questions.

Once approved, questions live in the database.

---

# 8. NEVER GENERATE FORMAL EXAMS LIVE WITH AI

Do not implement:

```text
Learner starts exam
↓
AI invents 50 questions
↓
exam
```

Formal assessments must use the validated question bank.

AI-generated runtime questions may later be used for:

```text
Additional AI-generated practice
```

only.

They must not automatically contribute to canonical gate mastery.

---

# 9. WRONG ANSWERS MUST BE PLAUSIBLE

Do not produce trivial distractors.

Bad:

```text
What is margin?

A. Collateral
B. Banana
C. Nairobi
D. Aeroplane
```

Good:

```text
What is margin?

A. The maximum amount a trader can lose
B. Collateral required to support a leveraged position
C. The trader's stop-loss distance
D. The account's total equity
```

Incorrect answers should preferably correspond to actual misconceptions.

---

# 10. MISCONCEPTION TAGGING

Options may include metadata such as:

```text
reversed_base_quote
margin_equals_risk
leverage_equals_profit
stop_loss_guarantees_price
high_win_rate_equals_positive_expectancy
correlation_equals_causation
backtest_equals_future_guarantee
```

If a learner repeatedly selects answers with the same misconception tag, record that pattern.

Example:

```text
Known misconception:
margin_equals_risk

Evidence:
3 incorrect responses across 2 sessions
```

Use this for remediation.

---

# 11. DO NOT CONFUSE SCORE WITH MASTERY

A learner should not master an important concept because they guessed one MCQ.

Critical concepts should use several forms of evidence.

Example:

```text
Concept: Margin vs Risk

Definition                    PASS
Scenario                      PASS
Numeric application           PASS
Misconception recognition     PASS
Delayed recall                PASS

Mastery evidence: 5/5
```

Or according to configurable curriculum rules:

```text
minimum 4/5
AND
critical misconception question must pass
```

Use the existing mastery/progression engine.

Do not create a second independent progression system.

---

# 12. QUESTION BANK DEPTH

Do not create only one deterministic question per concept.

For important concepts, create a sufficiently broad bank containing different:

- wording;
- examples;
- numbers;
- scenarios;
- perspectives;
- misconception traps;
- difficulty levels.

The appropriate number depends on curriculum importance.

Do not mechanically create an arbitrary fixed number just to satisfy quantity.

Quality and concept coverage matter more than raw volume.

---

# 13. QUESTION DIFFICULTY

Support levels such as:

```text
foundation
basic_application
intermediate
advanced_application
integrative
```

or an equivalent existing project convention.

A mastery assessment should not consist entirely of definition recall.

---

# 14. PARAMETERIZED NUMERIC QUESTIONS

Calculations should support deterministic templates.

Example template:

```text
Account balance = approved random value
Risk percentage = approved random value

Expected monetary risk =
balance × risk percentage
```

Different attempts may therefore produce:

```text
$2,000 × 0.5%
$3,500 × 0.25%
$7,500 × 0.4%
```

Laravel computes the authoritative answer.

Do not pre-store thousands of unnecessary numerical combinations when a validated template can generate them safely.

---

# 15. FOREX CALCULATION ENGINE

Use deterministic application code for:

- pips;
- pipettes where applicable;
- pip value;
- monetary risk;
- risk percentage;
- position sizing;
- R-multiple;
- expectancy;
- win rate;
- profit factor;
- drawdown;
- margin calculations where sufficient instrument metadata exists;
- open risk;
- correlated exposure rules where formally defined.

Never call a language model for the authoritative mathematical result.

---

# 16. NUMERICAL TOLERANCE

Some calculations require rounding.

Store:

```text
expected_value
tolerance
unit
rounding_rule
```

Example:

```text
expected = 22.50
tolerance = 0.01
currency = USD
```

Avoid marking conceptually correct answers wrong because of insignificant floating-point differences.

---

# 17. VALIDATE BROKER/SYMBOL ASSUMPTIONS

Do not blindly assume every forex/CFD instrument has identical:

- contract size;
- decimal precision;
- tick size;
- pip size;
- quote currency.

Parameterized exercises must explicitly carry required symbol metadata.

If assumptions are pedagogical rather than broker-specific, clearly identify them.

---

# 18. EXAMPLE — BASE/QUOTE CONCEPT

Canonical concept:

```text
EUR/USD = 1.2500
means
€1 = $1.25
```

Possible deterministic questions:

### Definition

```text
What does EUR/USD = 1.2500 mean?

A. $1 = €1.25
B. €1 = $1.25 ✅
C. €1.25 = $1
D. Both currencies have equal value
```

### Direction

```text
EUR/USD moves from 1.2500 to 1.2800.

Relative to USD, EUR has:

A. strengthened ✅
B. weakened
C. remained unchanged
D. become equal to USD
```

### Misconception

```text
A learner says:

"EUR/USD 1.25 means one dollar buys 1.25 euros."

A. Correct
B. Incorrect ✅
```

### Application

Use another valid pair and test transfer of understanding.

These collectively demonstrate more understanding than repeating one MCQ.

---

# 19. EXAMPLE — MARGIN VS RISK

AI semantic path:

```text
Explain in your own words why margin and planned trade risk are not
the same thing.
```

No-AI deterministic equivalent:

### Definition

Identify margin.

### Distinction

Identify planned trade risk.

### Scenario

```text
Balance = $2,000
Used margin = $250
Planned risk if stop is reached = $10

Which figure represents planned trade risk?
```

### Misconception

```text
"I used $250 margin, therefore I am risking exactly $250."

Correct / Incorrect
```

### Application

Present a different scenario requiring identification of both.

Passing the deterministic path should provide equivalent concept evidence.

---

# 20. EXAM BLUEPRINTS

Formal exams must be generated from explicit blueprints, not purely random selection.

Example conceptual blueprint:

```text
Foundation Gate

FX purpose/participants          N
Currency pairs/quotes            N
Base/quote                       N
Pips                             N
Bid/ask/spread                   N
Orders                           N
Leverage/margin                  N
Risk/safety                      N
Regulatory concepts              N
```

The exact distribution must come from the curriculum.

Do not invent curriculum weighting without source support.

---

# 21. CRITICAL CONCEPT REQUIREMENTS

Overall percentage alone may be insufficient.

Where justified by curriculum importance, gates may specify:

```text
overall score >= threshold

AND

critical concept requirements satisfied
```

Example:

A learner should not pass a risk-management gate while demonstrating a fundamental misconception about position sizing merely because they scored highly elsewhere.

Derive critical concepts from the source/mastery design.

---

# 22. EXAM RANDOMIZATION

Support:

- selecting different validated questions;
- shuffling question order;
- shuffling option order;
- parameterized calculation values.

Ensure option randomization never changes grading correctness.

Do not shuffle where ordering itself is semantically significant unless handled appropriately.

---

# 23. RETAKE BEHAVIOUR

A retake should not necessarily reproduce the identical examination.

Use the blueprint to draw another valid set where the bank allows it.

However:

Do not deliberately make retakes harder as punishment.

Keep equivalent blueprint coverage and expected difficulty.

---

# 24. PROTECTED ANSWER KEYS

Never expose:

```text
correct_option_ids
reference_answer
hidden_rubric
misconception scoring rules
```

to an active assessment browser payload.

Frontend receives only what it needs to render.

Example:

```text
id
prompt
public options
allowed response type
```

Backend grades submissions.

---

# 25. PRACTICE VS FORMAL EXAM FEEDBACK

## Practice mode

May provide immediate:

```text
Correct / Incorrect
Explanation
Relevant course section
Try again
```

according to learning design.

## Formal gate

During an active attempt:

```text
Answer saved
```

may be preferable.

Do not expose correctness if doing so would compromise remaining questions.

After submission, show feedback according to exam policy.

---

# 26. AUTOMATIC GRADING SERVICES

Add deterministic graders such as appropriate to the architecture:

```text
SingleChoiceGrader
MultipleSelectGrader
TrueFalseGrader
MatchingGrader
OrderingGrader
NumericGrader
ScenarioGrader
```

or use a clean strategy/registry system.

Do not create unnecessary classes merely to match these names.

Use the existing project architecture where possible.

---

# 27. FREE-RESPONSE GRADING REMAINS

Retain:

```text
RubricGrader
```

for free response.

When AI is available and permitted:

```text
Deterministic rubric
↓
AI semantic assessment where appropriate
↓
validated result
```

When AI is disabled:

free-response reflection may still be stored, but mandatory progression must have the deterministic equivalent path defined below.

---

# 28. NO-AI MASTERY ALTERNATIVE

For every **mandatory** semantic free-response item:

associate an equivalent structured assessment set.

Example:

```text
semantic_assessment_id
↓
no_ai_assessment_blueprint_id
```

When:

```env
AI_ENABLED=false
```

offer:

> Complete structured mastery assessment

instead of blocking the learner.

---

# 29. SAME LEARNING OBJECTIVE

The no-AI pathway must not simply be easier.

Both pathways should test the same:

```text
learning_objective_id
concept_id
mastery_requirement
```

Different assessment modality does not mean different educational standard.

---

# 30. DO NOT REQUIRE HUMAN REVIEW FOR SELF-STUDY COMPLETION

The audit currently reports that gates/final exams may use:

```text
manual_review
```

Review every such mandatory item.

Classify it as:

```text
A. Truly requires external evidence/human judgment
B. Can be represented through structured learner evidence
C. Reflection only
D. Existing implementation shortcut
```

Where a human reviewer is not inherently required by the educational objective, create a deterministic completion path.

The learner must not reach:

```text
Final Exam
↓
Awaiting administrator
```

simply because AI is disabled.

---

# 31. PRACTICAL EVIDENCE IS DIFFERENT FROM HUMAN GRADING

Some gates require evidence such as:

```text
number of legitimate demo trades
strategy version frozen
adherence percentage
backtest count
robustness tests completed
30-trade integrity challenge
```

Laravel can verify much of this from stored application records.

Example:

```text
required_demo_trades >= 100
```

can be checked from legitimate recorded demo trades.

That does not require AI or a human reviewer.

Do not classify machine-verifiable evidence as `manual_review`.

---

# 32. SUBJECTIVE REFLECTIONS

A journal reflection may remain free-response.

Example:

```text
What did you learn from your largest drawdown?
```

The learner's answer can be stored for reflection.

If it is not possible to objectively grade without AI/human review:

do not make the subjective prose quality itself the sole automated graduation condition.

Instead ensure the objectively required behaviour/evidence is independently measurable.

---

# 33. FINAL EXAM NO-AI PATH

The final examination must support a fully deterministic route.

It may combine:

```text
structured concept questions
advanced scenarios
multi-step calculations
risk interpretation
strategy-rule reasoning
backtesting interpretation
robustness interpretation
psychology/process scenarios
evidence checks from learner records
```

The exact requirements must remain aligned to the canonical curriculum.

AI may add semantic teach-back.

Human review may add richer evaluation.

Neither may be required for ordinary self-study graduation.

---

# 34. INGESTION ENHANCEMENT

Do not discard:

```text
StructuredSourceParser
SourceIngestionService
```

Enhance the ingestion pipeline.

Current:

```text
source
↓
free_response assessment
```

Target:

```text
source
↓
canonical concepts/objectives
↓
original free-response items
+
structured assessment candidates
+
misconception mappings
+
calculation templates
+
source provenance
```

---

# 35. EXISTING 540 CHAPTER QUESTIONS

Preserve the currently ingested:

```text
90 chapter quizzes
540 free-response questions
```

Do not delete them merely because structured questions are added.

Treat them as canonical source-derived assessment material.

Associate structured equivalents with their source concepts where appropriate.

---

# 36. QUESTION VALIDATION STATUS

Use lifecycle states such as:

```text
candidate
validated
approved
rejected
retired
```

or the project's equivalent.

Only:

```text
approved
```

questions may enter high-stakes formal exams.

---

# 37. QUESTION VERSIONING

Questions must be version-aware.

If wording or answer is materially changed:

retain historical attempt integrity.

Do not rewrite the meaning of an assessment after learners have already answered it.

Track:

```text
question_version
course_version
```

or equivalent.

---

# 38. SOURCE CHANGES

If canonical source material changes:

identify affected questions by source mapping/content hash.

Mark them for review/revalidation where appropriate.

Do not regenerate the entire question bank unnecessarily.

---

# 39. ADMIN QUESTION BANK UI

Add an appropriate admin interface.

Allow authorized administrators to:

- search questions;
- filter by chapter;
- filter by concept;
- filter by type;
- filter by difficulty;
- filter by validation status;
- inspect source provenance;
- inspect correct answers;
- inspect distractor misconceptions;
- preview learner rendering;
- disable a defective question;
- approve/reject candidates;
- inspect performance statistics.

Do not expose this interface to learners.

---

# 40. QUESTION ANALYTICS

Track useful statistics such as:

```text
attempt_count
correct_rate
incorrect option distribution
average response time where useful
misconception selections
```

Do not automatically conclude that:

```text
high failure = good hard question
```

A high failure rate may indicate ambiguity or defective wording.

Flag suspicious questions for review.

---

# 41. AMBIGUOUS QUESTION PROTECTION

A formal question should be rejected when:

- two options can reasonably be correct;
- wording depends on unstated assumptions;
- answer conflicts with source;
- answer depends on current information not supplied;
- terminology is ambiguous;
- calculation lacks required metadata.

Prefer fewer high-quality questions over a huge unreliable bank.

---

# 42. TIME-SENSITIVE QUESTIONS

Do not put volatile facts into static canonical exams without versioning.

Example:

Bad long-term canonical question:

```text
What is the current CBK rate?
```

unless current-data retrieval is explicitly part of the assessment.

Prefer testing the principle:

```text
How can a central bank rate change affect...
```

Current-data exercises must be separately classified and timestamped.

---

# 43. MULTILINGUAL/IMPERFECT ENGLISH

Structured questions should use clear language.

Free-response semantic assessments should assess forex understanding rather than grammar.

No-AI assessment should not force sophisticated English where the concept itself can be tested more directly.

---

# 44. ACCESSIBILITY

Structured assessments must remain accessible.

Support:

- keyboard navigation;
- visible focus;
- proper radio/checkbox labels;
- screen-reader semantics;
- sufficient contrast;
- no meaning conveyed only by color.

---

# 45. SAVE ANSWERS SAFELY

Continue using server-side answer-attempt persistence.

For structured responses store the normalized learner response plus:

```text
question_version
selected options / numeric response / matching response
grading result
awarded points
attempt
timestamp
```

Do not store only a display string if richer structured data is needed for audit.

---

# 46. IDEMPOTENT SUBMISSION

Duplicate POST/browser retry must not create duplicate grading outcomes.

Use the existing idempotency architecture where available.

---

# 47. QUESTION ATTEMPT AUDIT

It must be possible to reconstruct:

```text
what learner saw
what learner answered
which question version
which grading rule
which course version
score awarded
why
```

for important assessments.

---

# 48. REMEDIATION

Incorrect answers should map back to:

```text
concept
lesson
source section
misconception
```

Then the system can recommend:

```text
Review:
Margin vs Risk

Why:
You repeatedly selected answers treating used margin as maximum loss.
```

AI is optional.

This remediation should work from deterministic misconception tags.

---

# 49. DELAYED RECALL

Important concepts may be checked again later.

Do not count repeated answers to the exact same question as equivalent independent mastery evidence.

Where possible use a different scenario/question form.

---

# 50. QUESTION BANK GENERATION COMMAND

Provide an appropriate reproducible command/process such as:

```text
php artisan course:build-question-bank
```

or equivalent.

It should:

1. inspect canonical structured course content;
2. identify concepts/objectives;
3. preserve existing source questions;
4. generate/derive structured candidates according to approved rules;
5. validate provenance;
6. produce a report;
7. never automatically publish uncertain questions.

Do not require a live AI provider unless the administrator explicitly enables AI-assisted candidate generation.

---

# 51. VALIDATION COMMAND

Provide something like:

```text
php artisan course:validate-question-bank
```

Check:

- missing answers;
- invalid option references;
- duplicate options;
- multiple "correct" answers in single-choice;
- source mapping missing;
- broken concept mapping;
- unsupported calculation template;
- missing blueprint coverage;
- retired questions still referenced;
- insufficient question pool for a gate;
- ambiguous configuration.

Fail deployment/test where serious formal-assessment integrity issues exist.

---

# 52. COVERAGE REPORT

Generate:

```text
docs/ASSESSMENT_COVERAGE.md
```

For every:

```text
phase
chapter
lesson
concept
learning objective
gate
```

show:

```text
free-response coverage
structured question coverage
calculation coverage
no-AI mastery path
AI semantic path
manual-review dependency
```

Mandatory item:

```text
manual-review dependency = NONE
```

for ordinary self-study completion.

Any exception must be explicitly justified.

---

# 53. NO-AI COMPLETION AUDIT

Generate:

```text
docs/NO_AI_COMPLETION_AUDIT.md
```

Prove that a new learner starting with:

```env
AI_ENABLED=false
AI_SEMANTIC_GRADING_ENABLED=false
AI_VISION_ENABLED=false
OLLAMA_ENABLED=false
OPENAI_ENABLED=false
```

can progress:

```text
Onboarding
↓
Chapter 1
↓
...
↓
Gate A
↓
...
↓
all curriculum phases
↓
final assessment
↓
graduation
```

without human intervention.

Do not simply assert this.

Trace every required progression dependency.

---

# 54. AI-ENABLED COMPLETION AUDIT

Also verify the richer path:

```text
structured assessments
+
free-response teach-back
+
semantic grading
+
personalized remediation
```

AI enhances the experience but shares the same canonical mastery framework.

---

# 55. TESTS

Add unit/feature tests for at least:

- single choice correct;
- single choice incorrect;
- option shuffle;
- multiple select;
- numeric tolerance;
- matching;
- ordering;
- scenario grading;
- misconception tagging;
- parameterized calculations;
- critical concept failure;
- overall pass but critical concept fail;
- no-AI structured mastery;
- AI semantic mastery;
- AI unavailable mid-assessment;
- manual review unavailable;
- gate completion without AI;
- final examination without AI;
- hidden answer not returned to frontend;
- malformed structured response;
- duplicate submission;
- question version preservation;
- source provenance;
- exam blueprint coverage.

---

# 56. ACCEPTANCE TEST — SIMPLE MCQ

Create a source-grounded question:

```text
If EUR/USD = 1.2500, what does it mean?

A. One USD equals EUR 1.25
B. One EUR equals USD 1.25
C. Both currencies have equal value
D. One EUR equals USD 0.25
```

Expected:

```text
B = correct
```

Test with:

```env
AI_ENABLED=false
```

No AI request must occur.

---

# 57. ACCEPTANCE TEST — MISCONCEPTION

Learner chooses:

```text
A. One USD equals EUR 1.25
```

Expected:

```text
incorrect
misconception = reversed_base_quote
```

System recommends appropriate remediation.

No AI required.

---

# 58. ACCEPTANCE TEST — CALCULATION

Question:

```text
Balance = $2,000
Risk = 0.5%

Maximum planned monetary risk?
```

Expected:

```text
$10
```

Laravel must calculate this.

No hidden source answer comparison and no AI request.

---

# 59. ACCEPTANCE TEST — EQUIVALENT MASTERY

Concept:

```text
margin_vs_risk
```

Test:

### AI enabled

Learner successfully completes semantic teach-back.

### AI disabled

Learner completes structured equivalent consisting of definition, scenario, misconception and application evidence.

Both should be capable of satisfying the same concept mastery requirement when their respective rubric/blueprint passes.

---

# 60. ACCEPTANCE TEST — ENTIRE COURSE WITHOUT AI

Set:

```env
AI_ENABLED=false
AI_SEMANTIC_GRADING_ENABLED=false
OLLAMA_ENABLED=false
OPENAI_ENABLED=false
```

Using automated test fixtures where practical, demonstrate that every mandatory curriculum progression state has a valid non-AI path.

There must be no mandatory:

```text
manual_review
```

blocker for self-study graduation unless an explicit curriculum requirement genuinely requires a human.

If such a requirement exists in the source, document it rather than inventing an automatic replacement.

---

# 61. DO NOT LOWER EDUCATIONAL QUALITY

The objective is not:

```text
replace serious learning with easy MCQs
```

The objective is:

```text
rich, source-grounded structured assessment
+
multiple forms of evidence
+
deterministic grading
+
AI semantic enhancement when available
```

A no-AI learner should still have to demonstrate understanding.

---

# 62. FINAL IMPLEMENTATION REPORT

At completion report:

### Existing system retained

What free-response behaviour remains?

### Question types

Which structured question types are operational?

### Question bank

How many approved questions exist by:

```text
phase
chapter
type
difficulty
```

### Provenance

What percentage of formal questions have verified canonical source mappings?

Target:

```text
100%
```

### Exam blueprints

Which gates/final exams now have deterministic blueprints?

### No-AI path

Can a learner complete every mandatory course requirement with AI disabled?

### Human dependency

List every remaining mandatory `manual_review`.

Expected for normal self-study completion:

```text
NONE
```

unless the source itself genuinely mandates external human evaluation.

### AI path

What additional semantic assessment does AI provide?

### Testing

Report:

```text
tests run
tests passed
tests failed
manual tests
```

### Final explicit answer

Answer:

> Can a learner complete this entire curriculum from onboarding through graduation with AI permanently disabled and without requiring an administrator to mark their exams?

The expected answer after correct implementation is:

> **Yes.**

---

# FINAL NON-NEGOTIABLE

Do not replace the existing source-derived free-response curriculum.

Enhance it.

The finished assessment architecture should be:

```text
             CANONICAL COURSE
                    │
             LEARNING OBJECTIVES
                    │
          ┌─────────┴──────────┐
          │                    │
   FREE-RESPONSE         STRUCTURED BANK
          │                    │
      AI optional       Laravel grading
          │                    │
          └─────────┬──────────┘
                    │
               MASTERY ENGINE
                    │
                PROGRESSION
```

AI provides richer semantic teaching.

Structured deterministic assessment guarantees independence.

Human review provides optional deeper evaluation.

**Neither AI nor a human reviewer may be a mandatory dependency of ordinary self-study completion unless the canonical curriculum explicitly and genuinely requires one.**