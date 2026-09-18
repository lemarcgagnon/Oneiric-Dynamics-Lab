# Scientific guardrails

## Fail-closed evidence rules

- No API response is evidence unless it parses to the declared answer schema.
- `UNKNOWN` is a valid model answer; the harness never replaces it with the oracle answer.
- Candidate labels are assigned by the deterministic benchmark rule, not by DeepSeek confidence.
- A rejected candidate can be logged but cannot enter active learning memory.
- A failed commit gate restores the byte-for-byte canonical reference memory representation.
- If final-test leakage is detected, the entire run is invalid.

## Reproducibility

Every run records:

- Python/package version;
- configuration hash;
- source-tree hash manifest;
- benchmark seed and split hash;
- model name and API base URL;
- prompt protocol version;
- condition definition;
- API usage returned by the provider;
- all candidate provenance and admission decisions;
- final-test access count.

The DeepSeek service itself is not frozen by this harness. Provider-side model updates can therefore create temporal variation even with identical local code and seeds. The model ID and run timestamp must be treated as part of the experimental context.

## Independence limits

The benchmark oracle is independent of DeepSeek, but all model answers come from the same provider/model family in a DeepSeek-only experiment. This is not independent replication. A later study should repeat the same frozen protocol with other models or local trainable models.

## Benchmark limits

The opaque-token factor task is intentionally diagnostic. It isolates compositional reuse and memory organization, but it does not represent open-world factual learning, causal discovery, or general intelligence. Strong positive results can still be benchmark-specific; strong null results can still reflect a ceiling/floor or prompt-capacity artifact.

## Secret handling

Only `DEEPSEEK_API_KEY` is read. It is redacted from exceptions and never written to run artifacts. The audit scans source and generated run files for common credential patterns.

## Local Mission Control trust boundary

The browser interface can trigger paid API work, so “localhost” alone is not treated as authorization. The server binds to loopback, rejects non-loopback `Host` / `Origin` values, and requires an unpredictable per-process CSRF token on mission-launch POST requests. The token is rendered only into the local page and is never persisted in run evidence.
