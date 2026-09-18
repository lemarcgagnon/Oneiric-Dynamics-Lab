# Independent paper-to-code IV&V — OD Lab PHP v1.4.0

## Scope

The audit re-read the supplied 22-page manuscript and traced the formal model into the executable PHP path, then traced Mission Control through the backend and persistence boundary. The supplied PDF hash is:

`71d27adacc38fc8471c2a6d1be090f1279f3284d261f8c2addfc29401f4594b6`

The review was deliberately split into independent roles: mathematical equivalence, experimental semantics, code-path/wiring, persistence/evidence, and hostile counterexample testing.

## Mathematical findings

### Eq. (1)-(2)

The web laboratory is a fixed-model specialization. Neural `theta` is outside the DeepSeek API and is never fabricated. The runtime state records model identity, probability memory `mu`, frozen anchor/split hashes, seed/controller state and targeting state. The external benchmark initialization acts as the post-external reference; no parameter theorem is claimed.

### Eq. (3)-(4)

`EquationSet::sampleMixtureOperator()` makes one categorical draw for each candidate from the frozen `alpha,beta,gamma` vector. Component kernels then generate the candidate. Mixture validity is checked before execution; no deterministic operator quota substitutes for the paper's iid sampling law.

### Component kernels

`K_rep` preserves external historical origin and source lineage. `K_rec` requires at least two grounded source records and an externally unobserved conjunction. `K_cf` changes exactly one declared factor relative to one grounded source and records the intervention.

### Eq. (5)-(8)

Absolute admission executes before score/weight. Failed candidates remain weight zero. The ranking equation and stabilized Gibbs rule have one backend owner. `Qhat` is explicit in the result ledger and repeated identical marked atoms are aggregated only when converted to a finite probability measure.

### Eq. (9)-(11), (16)-(17)

Not implemented or claimed. The API exposes no trainable parameter vector. This is intentional compliance with the manuscript's fixed-interface limitation, not an omission disguised as an analogue.

### Eq. (12), Proposition 3 / Eq. (18)

The runtime uses the paper's probability-mixture update. `eta_bar` is specialized to 1, so the runtime enforces `0<=eta<=1`. On every nonempty proposal the code independently computes TV and verifies both the equality and upper bound before commit.

### Eq. (13)-(14)

The canonical gate evaluates native lower-is-better losses. Accuracy is specialized as `L=1-accuracy`. Probability/provenance/revision invariants are required. Failure restores the exact reference memory hash; no-change is distinguished from a passed gate.

### Eq. (15) / Proposition 4

Not claimed. The implementation does not assert time homogeneity, compactness, Feller continuity or a globally sufficient finite state.

### Eq. (19)

The exact formula is evaluated over a frozen external reference set. `phi` is categorical one-hot, Euclidean norm is literal, `sigma=sqrt(8)` and `Nmax=1`. Missing/unknown factor values fail instead of silently becoming novelty zero.

### Eq. (20)

Proposal novelty, admission fraction and weighted learning novelty are computed separately from the same assessment ledger; no extra multiplication by `rho` occurs.

## Experimental-boundary findings

- `final_known` is first evaluated after commit/rollback.
- H4's truly absent factor is excluded from known domains, generator token map, novelty representation, targeting and admission.
- Split hashing uses absent-factor IDs without constructing the sealed final label/token.
- H5 targeting uses only `target_dev`; the targeting values are recorded as controller state.
- Ordinary augmentation is a distinct strong control and does not masquerade as an OD mixture draw.
- H3 is explicitly `H3-FAPI`: it studies Eq. (12) proposal magnitude versus the hard gate, not neural proximal regularization.

## Frontend/backend/DB trace

```text
range slider / form
  -> public/assets/app.js
  -> public/api.php (auth + CSRF)
  -> LabService
  -> ResearchContract validation/freeze/hash
  -> Benchmark + EquationSet + MemoryDistribution + LabEngine
  -> Repository interface
  -> PdoRepository prepared statements
  -> MySQL: missions/runs/condition_results/run_events
  -> LabService::getRun
  -> persisted Analysis + raw evidence
  -> report.php / export.php
```

JavaScript does not contain the mixture, Gibbs, novelty, memory or gate equations. Public PHP routes do not instantiate the scientific engine and do not call the DB directly.

## Evidence required for a research report

The persisted package contains frozen configuration, code/split hashes, all candidate/provenance/admission/weight evidence, Eq. (8)/(12)/(18)/(19)/(20) traces, state hashes, per-question evaluator answers, gate/rollback status, and provider call/retry/token/latency events. The report reads those persisted records; it does not recompute scientific decisions in the browser. Individual condition/event rows, analysis and usage are SHA-256 protected; a run-level evidence root covers the ordered result/event hashes. Report/export loading re-verifies the ledger and fails closed on tampering or deletion.

## Limits that remain

The deterministic qualification harness validates implementation mechanics, not H1-H5. The build environment has no PDO MySQL driver/server and does not make a paid DeepSeek request; therefore real Namecheap MySQL and live provider transport remain deployment qualification items. Large synchronous suites may also encounter shared-hosting HTTP execution limits; this is operational, not a mathematical claim.


## v1.4 analysis-layer hostile re-audit

A second audit focused on the gap between a mathematically correct engine and a scientifically interpretable report. It found that condition means alone were insufficient for H1/H4 and that H2 lacked explicit uncertainty. v1.4 corrected the analysis owner rather than the OD engine:

- H1 is seed-paired and keeps retention separate from transfer;
- H2 adds model comparison, a prespecified peak threshold and seed-cluster bootstrap uncertainty;
- H4 now uses the seed-paired benefit contrast `(OD-known − replay-known) − (OD-absent − replay-absent)`, preventing both a misleading global average and the confound of raw task difficulty; raw known-minus-absent gaps remain diagnostic;
- H5 pairs targeted/uniform performance and keeps actual cost deltas separate.

`tests/analysis_qualification.php` uses synthetic data with known expected deltas/shape to verify these calculations. `tests/paper_ivv_hostile.php` independently exercises paper-derived counterexamples and nonclaims.

## BYOK boundary

v1.4 removes all server-side LLM-key persistence. The API key is not part of the paper state or experimental configuration. It is present only in the current Mission Control password field and the active run request, reaches only the provider client, and is guarded against persistence. This prevents credentials from contaminating frozen configs, hashes, logs or research packages.

## Evaluator-output validity

A provider formatting/transport failure must not masquerade as model task error. The DeepSeek adapter therefore accepts a batch only when the completion finishes normally and the JSON `answers` object contains exactly one string value for every requested question ID. Missing or truncated answers are retried and ultimately fail the run if unresolved.
