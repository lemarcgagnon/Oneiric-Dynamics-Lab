# OD Lab PHP/MySQL v1.4.0 — Namecheap research laboratory

OD Lab is a **light-only, FR/EN** PHP/MySQL implementation of the experimental framework in **Oneiric Dynamics for Artificial Intelligence** (revised 16 September 2026). The application is bound to manuscript SHA-256:

`71d27adacc38fc8471c2a6d1be090f1279f3284d261f8c2addfc29401f4594b6`

## Install

1. In cPanel, create an empty MySQL database and user and grant that user all privileges on the database.
2. Upload and extract the ZIP.
3. Open `install.php`.
4. Enter DB host/port/name/user/password and an OD Lab administrator login.
5. When installation reports success, delete `install.php`.
6. Open `public/login.php` (or point a subdomain document root directly at `public/`).
7. Open **Diagnostic / Health** once to verify the real PHP/PDO/MySQL environment.

The installer checks PHP >= 8.1, PDO MySQL, cURL, JSON, `random_bytes`, and filesystem writability. **No LLM API key is requested or stored during installation.**

## BYOK credential policy

DeepSeek is Bring Your Own Key. The key is entered in Mission Control only when the user is ready to run a mission.

- the key is **not stored in MySQL**;
- it is **not written to `config.php`**;
- there is **no save-key route**;
- it is **not placed in the frozen scientific configuration**;
- it is **not written to logs, reports, or exports**;
- the browser does not use `localStorage` or `sessionStorage` for the key;
- the current page sends it only with the run request over HTTPS; the backend holds it in request memory while the run executes and discards it afterward;
- a persistence guard blocks a run if the credential text is detected in result, event, analysis, or usage payloads before persistence.
- the provider destination is server-side locked to `https://api.deepseek.com` / `deepseek-flash`; client JSON cannot redirect a BYOK key to another host.

Reloading/closing Mission Control forgets the key. `public/health.php` verifies that the installed MySQL schema has no API-key persistence column.

## Singular scientific execution path

`Mission Control -> api.php -> LabService -> ResearchContract -> LabEngine -> Repository -> PdoRepository -> MySQL -> Analysis -> report/export`

The browser never owns an OD equation, gate, model call, or SQL write. Slider previews are sent to the backend; the backend derives and validates canonical values before execution.

## Mathematical implementation

The runtime implements the following paper equations directly under the declared finite fixed-API specialization:

- Eq. (3)-(4): proposal mixture and iid categorical operator draws;
- Eq. (5): absolute admission before weighting;
- Eq. (6): relative ranking, with `lambda_A=0` because anchor correctness is mandatory in this controlled benchmark;
- Eq. (7): stabilized Gibbs weighting;
- Eq. (8): weighted empirical admitted distribution `Qhat`;
- Eq. (12): exact memory mixture `mu*=(1-eta)muW+eta Qhat`, with `eta_bar=1`;
- Eq. (13)-(14): protected-loss gate and exact commit/rollback logic;
- Eq. (18): discrete total-variation identity/bound, verified at runtime;
- Eq. (19): finite-reference Euclidean novelty with frozen one-hot `phi`, `sigma=sqrt(8)`, `Nmax=1`;
- Eq. (20): proposal novelty, admission fraction, and weighted learning novelty kept distinct.

Eq. (9)-(11) and Eq. (16)-(17) are **not claimed** because a fixed DeepSeek API does not expose trainable neural parameters `theta`. Eq. (15)/Proposition 4 is also not claimed because state sufficiency, time homogeneity, compactness and Feller continuity are not established for the web application.

## Research-analysis layer

v1.4 adds a prespecified analysis layer rather than treating condition means as hypothesis tests:

- H1: seed-paired replay+recombination vs replay and vs ordinary augmentation, with separate retention and known-combination transfer deltas;
- H2: flat/linear/quadratic response comparison, AICc where defined, interior-vertex check, effect threshold, and deterministic seed-cluster bootstrap interval for the vertex when replication is sufficient;
- H3-FAPI: proposal movement, gate, rollback and committed movement kept distinct;
- H4: seed-paired **difference-in-differences** relative to replay: `(OD known − replay known) − (OD absent − replay absent)`; raw known-minus-absent gaps remain diagnostic only;
- H5: seed-paired targeted vs uniform performance plus API-call, token and wall-time deltas, with efficiency ratio reported only as a diagnostic alongside raw cost/performance.

Means and paired deltas use Student-t 95% intervals. Effect thresholds are frozen in the mission config before final evaluation. The report never auto-declares a universal hypothesis “confirmed”; it states when replication is insufficient and preserves null/adverse findings.

## Research evidence

Every condition/seed persists raw evidence including frozen config/split/code hashes, candidate provenance, admission/rejection, scores and weights, `Qhat`, Eq. (12)/(18)/(19)/(20) traces, reference/proposal/committed state hashes, answer-level evaluator results, gate/rollback state, API usage events, and final sealed evaluations.

Every `condition_results` row has `result_sha256`; every `run_events` row has `event_sha256`; completed runs also store hashes for analysis/usage plus an `evidence_root_sha256` over the ordered result/event hashes. Reports and exports verify these hashes before using the evidence, so tampered or deleted evidence fails closed. A run is marked completed only after analysis, the terminal event and evidence seal have all succeeded, so an unsealed run cannot be reported as completed. The HTML report is generated inside the application from persisted canonical analysis/evidence. JSON export is the forensic research package; CSV is available for tabular analysis.

Evaluator responses are fail-closed: a DeepSeek batch must return a completed JSON object containing **exactly one string answer for every requested question ID**. Missing/truncated answers are retried/failed as evaluator errors rather than silently counted as task mistakes.

## UI

- light theme only;
- FR/EN via one `I18n` authority;
- keyboard-accessible researcher tooltips explaining the concept and intended experimental impact of controls;
- sliders for mixture, candidate count, Gibbs temperature, ranking weights, Eq. (12) `eta`, protected-loss tolerances, memory context size, seeds and budgets;
- no formula duplicated in JavaScript.

## Qualification

The package includes internal tests, hostile tests, a paper-derived adversarial IV&V suite, an independent Python↔PHP numerical oracle, a synthetic research-analysis qualification, HTTP end-to-end integration, PDO/schema contract tests, BYOK/secret gates, and architecture gates.

The build container executes the real PHP HTTP application path but does not provide `pdo_mysql`/a MySQL server for a live production PDO transaction. On Namecheap, `install.php` and `public/health.php` close that final environment-specific boundary before the first DeepSeek `smoke` run.
