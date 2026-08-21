# Content Coverage

## Honest status

Deterministic ingestion and structured-source parity are complete for both supplied PDFs. Every meaningful extracted segment has an application content mapping. These mappings remain labelled `machine_mapped`; no human editorial approval is implied.

| Coverage layer | Result | Status |
| --- | ---: | --- |
| Source files imported | 2 / 2 | Complete |
| Physical pages imported | 928 / 928 | Complete |
| Pages with meaningful segments | 928 / 928 | Complete |
| Meaningful source segments mapped | 5,807 / 5,807 | Complete (machine) |
| Unmapped meaningful segments | 0 | Complete |
| Canonical pages duplicated in guided edition | 328 / 328 linked | Complete |
| Chapter learning units | 90 / 90 | Complete |
| Chapter quizzes | 90 / 90 | Complete |
| Chapter quiz prompts and protected answers | 540 / 540 | Complete |
| Source exercises | 379 / 379 | Complete |
| Gate exams | 7 / 7 | Complete |
| Gate questions | 142 / 142 | Complete |
| Final comprehensive examination | 1 / 1 | Complete |
| Final examination questions | 100 / 100 | Complete |
| Appendices | 26 / 26 | Complete |
| Unique external URLs | 63 / 63 | Complete |
| Book references | 12 / 12 | Complete |
| Academic papers | 4 / 4 | Complete |
| Editorially approved mappings | 0 / 5,807 | Pending human review |

## Coverage denominator and deduplication

The two sources contain 928 physical pages. Exact page hashing identified all 328 canonical course pages inside the guided edition. The imported source layer still retains both documents and their page-level provenance; it does not delete the guided copy. Duplicate guided segments point to canonical segments and reuse canonical application blocks.

The application currently contains:

- 5,807 meaningful source segments;
- 3,432 learner-facing content blocks after exact deduplication;
- 5,807 segment-to-block mappings;
- 2,375 exact segment-level `duplicate_of` relations;
- zero unmapped meaningful segments.

## Structured parity contract

`StructuredSourceParserTest` reads the audited canonical JSONL and fails if any source count changes. `course:audit-coverage` independently checks the imported database. The audit passes only when page coverage, duplicate linkage, and every structured count above match.

Run:

```powershell
C:\tools\php85\php.exe -c php.dev.ini artisan course:audit-coverage
```

## Mapping and answer protection

Each source segment retains document/version, physical page, section identifier where available, hierarchy, source order, content type, source text hash, and application mapping. Quiz prompts and answer explanations live in separate protected question fields; learner-facing assessment payloads exclude answer keys and explanations before submission.

Appendix answer-key pages map to appendix units, never to chapter lesson blocks. Gate exams use manual practical review against the Appendix Y framework.

## Current exceptions and review queue

There are no machine-coverage or structured-parity exceptions. The remaining queue is editorial: all 5,807 mappings are awaiting human approval. This is operational review work, not an ingestion gap. The admin audit deliberately presents machine coverage and editorial approval as different metrics.
