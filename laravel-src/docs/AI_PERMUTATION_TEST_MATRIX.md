# AI permutation test matrix

Status reflects this repository on 2026-08-21. “Automated” uses provider HTTP fakes; “manual/not configured” is never reported as passing.

| # | Condition | Expected execution path | Learner experience | Provider | Fallback? | Mastery affected? | Persisted | Message/result | Status |
|---:|---|---|---|---|---|---|---|---|---|
| 1 | AI on + RAG on | retrieve then route | cited explanation | allowed capable | yes | evidence only | messages/usage | normal tutor | AUTOMATED (components) |
| 2 | AI on + RAG off | route without claimed course citation | reduced grounding | allowed capable | yes | evidence validated | usage | grounding warning | MANUAL |
| 3 | AI off + RAG on | lexical/hybrid retrieval | cited snippets | none | no | no | messages | Search Course | PASS |
| 4 | AI off + RAG off | canonical lesson only | course remains | none | no | no | normal progress | AI unavailable | PASS |
| 5 | Ollama only | local adapter | local explanation | Ollama | no | evidence only | usage | normal/local | AUTOMATED |
| 6 | Cloud only | cloud policy route | generated explanation | free cloud | yes | evidence only | usage | normal | AUTOMATED |
| 7 | Local embeddings + cloud tutor | local embed, cloud generate | semantic search + tutor | mixed | yes | evidence only | vectors/usage | normal | IMPLEMENTED, NOT CONFIGURED |
| 8 | Cloud embeddings + local tutor | cloud embed, local generate | semantic search + local tutor | mixed | yes | evidence only | vectors/usage | normal | IMPLEMENTED, NOT CONFIGURED |
| 9 | Free-only chain | exclude paid | explanation or retrieval | free/local | yes | no downgrade | usage | friendly fallback | AUTOMATED |
| 10 | Paid fallback enabled | include paid after policy | explanation | paid allowed | yes | evidence only | usage/cost | normal | AUTOMATED by routing unit |
| 11 | Paid fallback prohibited | filter paid before HTTP | retrieval only if free exhausted | none | no paid | no | no paid event | temporary unavailable | PASS |
| 12 | Primary rate limited | log 429, next candidate | uninterrupted | fallback free | yes | no duplicate | two usage events | friendly | PASS |
| 13 | Primary offline | circuit failure, next | fallback/retrieval | next allowed | yes | no | failure event | friendly | AUTOMATED equivalent |
| 14 | Every provider offline | retrieval | snippets/course | none | exhausted | deterministic only | answer/message | temporary unavailable | PASS |
| 15 | Semantic grading available | deterministic first, validated AI | richer feedback | structured capable | yes | Laravel decides | recommendation | graded | IMPLEMENTED, LIVE NOT CONFIGURED |
| 16 | Semantic grading unavailable | deterministic equivalent | completes curriculum | none | no | deterministic evidence | answer/evidence | normal remediation | PASS |
| 17 | Active assessment | guard before model | hints only | none/allowed hint | no | unchanged | safety status | answer shielded | PASS |
| 18 | Normal tutoring | retrieve/route | cited answer | capable | yes | none | thread/usage | normal | AUTOMATED components |
| 19 | Vision available | validate/own interpretation/route | chart feedback | vision capable | yes | no canonical mastery | private record | educational only | DEFERRED |
| 20 | Vision unavailable | retain upload/interpretation | no signal | none | no | none | learner work | unavailable | DEFERRED |
| 21 | Current retrieval available | authoritative connector then model | current-source label | configured | controlled | none | source metadata | current answer | DEFERRED |
| 22 | Current retrieval unavailable | guard refusal | no fabricated fact | none | no | none | safety message | cannot verify | PASS |
| 23 | Vector ready | hybrid ranking | semantic snippets | embed provider | n/a | none | vectors | search results | IMPLEMENTED, NOT CONFIGURED |
| 24 | Vector stale | lexical-first; retain state | search continues | none | n/a | none | stale state | lexical results | IMPLEMENTED |
| 25 | Vector rebuilding | lexical-first | search continues | embed worker | n/a | none | building state | lexical results | IMPLEMENTED |
| 26 | Vector failed | lexical-only | search continues | none | n/a | none | error state | lexical results | IMPLEMENTED |
| 27 | Local only preference | exclude cloud | local/retrieval | Ollama | no cloud | none | preference/usage | normal | PASS |
| 28 | Cloud only preference | exclude local | cloud/retrieval | cloud | yes | none | preference/usage | normal | AUTOMATED policy |
| 29 | All AI disabled by user | retrieval only | full course/search | none | no | deterministic | preference | AI off | PASS |
| 30 | VPS loses internet | local or retrieval | course continues | local/none | yes | none | failure event | friendly | AUTOMATED equivalent |
| 31 | Malformed JSON | server validation rejects | deterministic equivalent/pending | attempted | limited | no AI mastery | failure/recommendation | retry later | PASS (schema tests) |
| 32 | Provider timeout | bounded timeout/circuit/fallback | fallback/retrieval | next | yes | none | failure latency | friendly | IMPLEMENTED |
| 33 | Duplicate browser submission | client request ID lookup | original result | original | no duplicate | once | one assistant result | duplicate=true | IMPLEMENTED |
| 34 | Late response after fallback | synchronous timeout response ignored | fallback result | fallback | yes | once | accepted result | normal | MANUAL |
| 35 | Source conflict | canonical conflict workflow | no silent rewrite | none | n/a | none | conflict/audit | admin review | PASS existing |
| 36 | Course version changes | content hashes/version metadata | current course | as allowed | yes | versioned | course/prompt metadata | normal | IMPLEMENTED |
| 37 | Typo-heavy answer | deterministic normalization/AI optional | fair assessment | optional | yes | validated | evidence | feedback | PASS component |
| 38 | Different wording | rubric synonyms/semantic optional | accepted when concepts present | optional | yes | application score | evidence | correct | PASS |
| 39 | Partial answer | partial status/remediation | targeted review | optional | yes | no false pass | review item | feedback | PASS |
| 40 | Contradictory answer | contradiction before AI | misconception | none | no | no mastery | review evidence | correction | PASS |
| 41 | Prompt injection | guard/immutable system boundary | refusal | none | no | unchanged | safety status | blocked | PASS |
| 42 | Active-test answer request | protected prompt overlap | Socratic hint | none | no | unchanged | safety status | shielded | PASS |
| 43 | Live signal request | safety boundary | educational refusal | none | no | none | safety status | no signal | PASS |
| 44 | Future-topic preview | current-unit retrieval priority | limited preview | optional | yes | no unlock | message | prerequisite guidance | MANUAL |
| 45 | Outage during assessment | answer stored/deterministic equivalent | work retained | none | no | deterministic only | answer/evidence | completed/pending | PASS architecture |
| 46 | Outage during tutoring | saved question + retrieval | cited snippets | none | exhausted | none | thread | continue course | PASS |
| 47 | Private content cross-user | owner checks/private tables excluded | no leak | none | no | none | none | 404/empty | PASS architecture/tests |
| 48 | Citation correctness | citations built from retrieved records | document/page shown | any | yes | none | segment IDs | grounded | PASS |
| 49 | Core curriculum zero AI | deterministic application | 100% path | none | no | normal | normal records | full product | PASS |
| 50 | Core curriculum zero paid API | paid filter + deterministic/retrieval | 100% path | free/local/none | controlled | normal | zero paid event | full product | PASS |
