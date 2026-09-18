# Release notes — v1.4.0 Research Analysis + BYOK hardening

v1.4.0 supersedes v1.3.1 for research use.

## Scientific-analysis repairs

- H1 is now analyzed with **seed-paired deltas**, not only condition means, and retention is reported separately from transfer.
- H2 now compares flat/linear/quadratic response forms, uses AICc when defined, checks an interior quadratic vertex, records a minimum peak-effect threshold, and adds a deterministic seed-cluster bootstrap interval when enough seeds exist.
- H4 no longer treats a raw known-vs-absent gap as the hypothesis test. Its primary statistic is now a seed-paired **difference-in-differences against replay**, so it measures differential benefit rather than intrinsic task difficulty. Raw known-minus-absent gaps remain diagnostic.
- H5 now reports paired targeted-vs-uniform performance and separately logs API-call, token and wall-time overhead.
- Means/deltas use Student-t 95% intervals; prespecified effect thresholds are frozen in the research configuration.
- A synthetic known-answer analysis harness was added to make sure the report statistics themselves are wired correctly.

## BYOK hardening

- Removed all API-key fields from MySQL schema and runtime repository.
- Removed DeepSeek key from `install.php` and generated config.
- Removed the save-key API route.
- Mission Control keeps the key only in the live password field; no browser storage is used.
- The key is supplied only on the run request and is not part of the frozen scientific config/hash.
- Runtime persistence guards abort if credential text appears in result/event/analysis/usage payloads.
- Errors are credential-redacted before persistence.
- The provider endpoint/model are frozen server-side so a modified client request cannot redirect the BYOK key to an arbitrary HTTPS host.
- Added an authenticated live server-health page that checks real PDO MySQL tables, transaction rollback rights, and absence of API-key columns on deployment.

## Evidence hardening

- `condition_results.result_sha256` and `run_events.event_sha256` are stored and included in exports.
- Completed runs also store `analysis_sha256`, `usage_sha256`, and an ordered `evidence_root_sha256`; report/export loading fails closed on content tampering or evidence deletion.
- Added `condition_started` and `analysis_completed` run events and a frozen analysis-plan record.
- Reports expose analysis status, paired comparisons, uncertainty, analysis plan, mathematical traces and evidence integrity.
- Run finalization was reordered so a crash before evidence sealing cannot leave a false-completed scientific run.

## Paper/code IV&V

- Added a paper-derived hostile harness covering the mixture probability contract, Appendix C.2 reject-all logic, Eq. (12)/(18), hard-gate protection, Eq. (19) missing-reference failure, Eq. (20) no double-rho error, and fixed-API nonclaims for theta/Markov theorems.
- Existing 350-case independent Python↔PHP numerical equivalence remains in place for Eq. (3)-(4), (6), (7), (8), (12), (13), (18), (19), and (20).

## Strict evaluator-output validity

- DeepSeek batches must finish normally and return exactly the requested question IDs.
- Missing answers or truncated (`finish_reason=length`) outputs are evaluator failures, not silently scored as wrong task answers.
- The hostile harness injects both cases and requires fail-closed behavior.
