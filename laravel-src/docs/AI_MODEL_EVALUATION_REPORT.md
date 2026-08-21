# AI model evaluation report

Run: 2026-08-21

Command: `php artisan ai:evaluate-models`

## Results

| Evaluator | Result | Status |
|---|---:|---|
| Deterministic equivalent rubric | 28 / 36 expected classifications | PASS for no-AI pathway; improvement candidates identified |
| Groq | no credentials/model | NOT CONFIGURED |
| Gemini | no credentials/model | NOT CONFIGURED |
| OpenRouter | no credentials/model | NOT CONFIGURED |
| Ollama | no local model | NOT CONFIGURED |
| OpenAI | disabled | NOT CONFIGURED |

The dataset spans quotes, pips, leverage, margin, risk, R-multiples, expectancy, drawdown, technical structure, macro expectations, backtesting, robustness, psychology, demo evidence, signal safety, and course authority. It includes correct paraphrases, partial answers, direction reversals, and misconceptions.

No provider is recommended as “best” without a live run. Once configured, run `php artisan ai:evaluate-models --provider=groq` (or another provider), record schema failures/accuracy/latency, and compare against this report. Model routing must be based on those local results rather than advertised benchmarks.

The 28/36 deterministic score does not block progression: the cases that deliberately need broader semantics receive partial/incorrect deterministic evidence and remediation. It does identify answer-key synonym/contradiction expansions for future editorial review; thresholds were not weakened.
