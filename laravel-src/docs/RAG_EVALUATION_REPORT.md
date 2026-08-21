# Retrieval evaluation report

Run: 2026-08-21

## Implementation status

- Lexical mapped-course retrieval: PASS
- Lesson metadata filtering: PASS
- Canonical/guided source restriction: PASS
- Citation provenance (segment/document/physical page): PASS
- Semantic vector ranking: NOT CONFIGURED (embedding model intentionally absent)
- Hash-based incremental indexing: implemented; live indexing NOT CONFIGURED

## Evaluation queries

The retrieval evaluator/test set uses:

1. What does EUR/USD 1.25 mean?
2. Why is margin not the same as risk?
3. Why can inflation rise but a currency still fall?
4. What is a valid backtest?
5. Why can't I optimize until I find good parameters?

Current no-model operation uses lexical term overlap, mapped learning-unit metadata, and source provenance. The learner UI labels results as Search Course and shows relevance, a snippet, unit title, document, and physical page. Semantic evaluation must be rerun after `ai:reindex-course`; it is not marked passing before an embedding provider is configured.
