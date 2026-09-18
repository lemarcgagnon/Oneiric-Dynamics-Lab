# OD Lab PHP/MySQL v1.4.0 — hostile release qualification

**Qualification date:** 2026-09-16  
**Release:** `ODLab_PHP_Namecheap_v1.4.0`  
**Canonical manuscript:** *Oneiric Dynamics for Artificial Intelligence — A Research Framework for Offline Generative Recombination, Memory Consolidation, and Constrained Self-Revision*  
**Required manuscript SHA-256:** `71d27adacc38fc8471c2a6d1be090f1279f3284d261f8c2addfc29401f4594b6`

## Release decision

The internal hostile release harness passes. This means the application has executable evidence that its declared fixed-API OD specialization, research logging, report generation, BYOK boundary, PHP HTTP orchestration, and repository/schema contract behave as specified in this package.

It does **not** mean that H1–H5 have been empirically validated on DeepSeek, nor that the neural-parameter theorems apply to an API-only model. A live Namecheap PDO/MySQL installation and a paid DeepSeek request remain deployment smoke gates because this build environment has no PDO MySQL driver/server and no PHP cURL extension or user API key.

## Independent hostile auditors executed

### Auditor A — PHP/code integrity and anti-silo architecture

- PHP syntax: PASS for all application/test PHP files.
- public routes do not instantiate the scientific engine directly: PASS.
- public routes issue no SQL: PASS.
- JavaScript does not own the mixture equation: PASS.
- sealed `final_known` and `final_absent` data are touched only at the final evaluation stage: PASS.
- light-only UI: PASS.
- FR/EN I18n authority and researcher tooltips: PASS.
- `LabService` is the execution/persistence facade: PASS.
- analysis -> terminal event -> evidence seal -> run finalize -> mission finalize ordering: PASS.
- package manifest integrity: PASS.

### Auditor B — manuscript/mathematics IV&V

- supplied manuscript SHA matches the executable research contract: PASS.
- hostile paper-derived suite: **9 PASS / 0 FAIL**.
- independent Python↔PHP numerical oracle: **350 cases PASS**.
- numerically cross-checked equations: Eq. (3)-(4), (6), (7), (8), (12), (13), (18), (19), (20).
- Eq. (9)-(11), Eq. (16)-(17), and global Markov/invariant-measure claims remain explicitly unclaimed where their assumptions are unavailable.

### Auditor C — experimental semantics and analysis

- internal qualification: **37 PASS / 0 FAIL**.
- hostile qualification: **39 PASS / 0 FAIL**.
- research-analysis known-answer qualification: **11 PASS / 0 FAIL**.
- H1 uses seed-paired deltas with retention separated from known-combination transfer.
- H2 compares flat/linear/quadratic response models, applies prespecified effect thresholding and seed-cluster bootstrap uncertainty when replication permits.
- H3 is explicitly H3-FAPI; it tests memory proposal magnitude versus hard gate/rollback, not the inaccessible neural `theta` theorem.
- H4 primary inference is seed-paired difference-in-differences against replay; raw known-minus-absent gaps are diagnostic only.
- H5 pairs targeted versus uniform generation and reports performance and observed cost separately.

### Auditor D — persistence, evidence integrity, and BYOK

- PDO/schema contract: **32 PASS / 0 FAIL**.
- hostile evidence-integrity tests: **5 PASS / 0 FAIL**.
- static BYOK security gates: **13 PASS**.
- runtime hostile BYOK persistence test: **6 PASS / 0 FAIL**.
- every condition result/event has a SHA-256; analysis and usage have SHA-256 values; the ordered condition/event ledger is sealed by `evidence_root_sha256`.
- report/export loading re-verifies the ledger and fails closed on modification or deletion.
- a run is not marked `completed` before the evidence root exists.

## BYOK credential contract

The DeepSeek credential is a **session-of-page / active-request secret**, not persistent application state.

- `install.php` never asks for an LLM key.
- MySQL has no API-key column.
- `config.php` has no API-key field.
- there is no save-key route.
- browser JavaScript uses neither `localStorage`, `sessionStorage`, nor IndexedDB for the key.
- Mission Control keeps the key only in its password field while the page is open.
- the key is sent over HTTPS only with the run request and is held in PHP request memory while the run executes.
- the scientific config/hash does not contain the key.
- result/event/analysis/usage persistence has an explicit secret-leak guard.
- failure messages are secret-redacted before persistence.
- a hostile evaluator that deliberately injects the supplied secret into result evidence is blocked before persistence; the mission fails closed and the repository remains secret-free.
- the provider destination/model are frozen server-side, so client JSON cannot redirect the credential to a different host.

Reloading or closing Mission Control forgets the key.

## Auditor E — real PHP HTTP orchestration

The release harness launches the actual PHP built-in HTTP server and exercises browser-facing routes, not only unit methods.

- `install.php` executes over HTTP: PASS.
- installer FR/EN/preflight/light-only/no-LLM-key-field checks: PASS.
- login/session: PASS.
- Mission Control FR/EN + tooltips: PASS.
- slider preview -> backend-derived mixture: PASS.
- frontend -> API -> `LabService` -> engine -> repository: PASS.
- BYOK run request without credential persistence: PASS.
- report FR/EN from persisted canonical analysis: PASS.
- mathematical trace in report: PASS.
- forensic JSON export with raw evidence/hashes: PASS.
- CSRF rejection and unauthenticated redirect: PASS.
- full `all` suite with **3 seeds / 45 condition×seed results** through the canonical service/engine/repository: PASS.
- all 45 evidence rows and event hashes verify: PASS.
- H1–H5 report renders from persisted analysis: PASS.
- full forensic JSON export contains all 45 condition evidence rows: PASS.

## Mathematical runtime mapping

| Paper element | Release status | Canonical owner |
|---|---|---|
| Eq. (1)-(2) state/reference cycle | SPECIALIZED | `LabEngine` fixed-API state |
| Eq. (3)-(4) mixture / exploratory share | EXACT finite categorical implementation | `EquationSet` + component kernels |
| Eq. (5) absolute admission | SPECIALIZED | `LabEngine::admit()` |
| Eq. (6) relative ranking | EXACT FORM / specialized anchor policy | `EquationSet::rankingScore()` |
| Eq. (7) Gibbs weighting | EXACT | `EquationSet::gibbsWeights()` |
| Eq. (8) admitted empirical distribution | EXACT | `EquationSet` + `MemoryDistribution` |
| Eq. (9)-(11) neural parameter proposal | NOT CLAIMED | fixed DeepSeek API boundary |
| Eq. (12) memory mixture | EXACT finite marked-space implementation | `MemoryDistribution::mixture()` |
| Eq. (13)-(14) gate / rollback | SPECIALIZED | `EquationSet` + `LabEngine` |
| Eq. (15) Markov kernel | NOT CLAIMED globally | documented boundary |
| Eq. (16)-(17) neural parameter bounds | NOT CLAIMED | fixed API boundary |
| Eq. (18) TV displacement identity/bound | EXACT finite-discrete implementation | `MemoryDistribution` |
| Eq. (19) finite-reference novelty | EXACT declared specialization | `EquationSet` + frozen benchmark representation |
| Eq. (20) novelty/admission/influence diagnostics | EXACT | `EquationSet::noveltyDiagnostics()` |

## What is actually logged for research

For every condition × seed, the persisted evidence contains candidates, generation operator, source lineage/provenance, intervention, admission/rejection reasons, novelty/coherence/anchor values, score, learning weight, `Qhat`, Eq. (12) reference/proposal probability distributions, Eq. (18) TV terms/residual, Eq. (19)/(20) diagnostics, reference/proposal/committed state hashes, answer-level evaluator evidence, gate status, rollback/no-change, final known/absent outcomes, API call/token/latency/wall-time usage, and run events.

The run also stores the frozen configuration/hash, split manifest/hash, software version, provider model, code-manifest hash, analysis JSON/hash, usage JSON/hash and run-level evidence root. Reports and exports are generated from those persisted records.

## Remaining deployment-only gates

These are intentionally not claimed as executed in the build container:

1. `install.php` connecting to the actual Namecheap MySQL server through `pdo_mysql`;
2. actual MySQL write/rollback and schema-health verification on Namecheap;
3. a live DeepSeek API request from the deployed Namecheap PHP/cURL runtime.

After deployment, open `public/health.php`. All checks should pass and `install.php` should be deleted. Then run only `smoke`, 1 seed, with a BYOK key. Inspect the report and forensic JSON before increasing replication. Large synchronous suites may also be subject to shared-hosting HTTP execution limits; this is an operational hosting constraint, not a mathematical result.

## Raw qualification output

See `qualification/TEST_RESULTS.txt` for the complete hostile release transcript.
