# Oneiric Dynamics Lab

**A reproducible research laboratory for studying offline generative recombination, memory consolidation, and constrained self-revision in artificial intelligence.**

Oneiric Dynamics Lab is the public research repository associated with the manuscript **_Oneiric Dynamics for Artificial Intelligence: A Research Framework for Offline Generative Recombination, Memory Consolidation, and Constrained Self-Revision_** by Marc Gagnon.

The repository contains both the scientific manuscript and an inspectable PHP/MySQL laboratory that operationalizes the portions of the framework applicable to a fixed model accessed through an API. The project is intended to make the research program testable rather than to presume that internally generated experience is beneficial.

## Video

Watch the project video: [Oneiric Dynamics Lab on YouTube](https://www.youtube.com/watch?v=S37zfRoS06Y)

[![Oneiric Dynamics Lab video](https://img.youtube.com/vi/S37zfRoS06Y/hqdefault.jpg)](https://www.youtube.com/watch?v=S37zfRoS06Y)

## Repository contents

- [`paper/`](paper/) — the reference research manuscript.
- [`php-lab/`](php-lab/) — the PHP/MySQL experimental laboratory.
- [`php-lab/docs/`](php-lab/docs/) — architecture, mathematical implementation contract, paper-to-code IV&V, frontend/backend/database trace, deployment guidance, and formalization coverage.
- [`php-lab/tests/`](php-lab/tests/) — internal, hostile, numerical cross-check, analysis, HTTP, persistence, evidence-integrity, architecture, and BYOK tests.
- [`php-lab/qualification/`](php-lab/qualification/) — the packaged qualification record for the public laboratory version.

## Scientific reference

The laboratory is bound to the published repository copy of the manuscript by SHA-256:

```text
71d27adacc38fc8471c2a6d1be090f1279f3284d261f8c2addfc29401f4594b6
```

Paper: [`paper/Oneiric_Dynamics_Research_Framework_v3_2026-09-16.pdf`](paper/Oneiric_Dynamics_Research_Framework_v3_2026-09-16.pdf)

The framework separates:

1. generation of replay, recombined, or model-relative counterfactual candidates;
2. absolute admission for a declared learning use;
3. relative weighting among admitted candidates;
4. a proposed learning-state revision;
5. protected checks;
6. commit or rollback;
7. sealed final evaluation;
8. provenance and evidence preservation.

A valid cycle may end with **no accepted change**.

## Experimental hypotheses

The current program is organized around five falsifiable hypotheses:

- **H1 — Retention vs. compositional transfer.** Different mixtures of replay and recombination may favor different outcomes.
- **H2 — Non-monotonic novelty.** Moderate realized novelty may be more useful than either near-identity rehearsal or highly distorted generation in some regimes.
- **H3 — Proposal controls vs. acceptance controls.** Proposal regularization and hard acceptance checks may have distinguishable effects.
- **H4 — Structural reuse.** Transfer may differ between new combinations of represented factors and genuinely absent factors.
- **H5 — Targeted generation.** Sampling around uncertainty or interference may be more compute-efficient than uniform generation.

These are **research hypotheses, not established results**. Positive, null, and adverse outcomes are all informative when the protocol and evidence are preserved.

## Mathematical implementation

The PHP laboratory centralizes the implemented mathematical rules in the backend. The browser UI does not own a second copy of the scientific equations.

The current fixed-API specialization directly implements and tests the applicable mechanisms for:

- proposal mixture and iid operator draws;
- absolute admission before relative weighting;
- ranking and stabilized Gibbs weighting;
- the admitted empirical distribution;
- the explicit memory-mixture update;
- protected-loss gate and rollback;
- total-variation identity/bound checks for the declared memory mixture;
- finite-reference novelty;
- proposal, admission, and learning-influence diagnostics.

The repository does **not** claim the neural-parameter results that require direct access to trainable model parameters when using a remote fixed DeepSeek model through an API.

See:

- [`php-lab/docs/MATHEMATICAL_IMPLEMENTATION_CONTRACT.md`](php-lab/docs/MATHEMATICAL_IMPLEMENTATION_CONTRACT.md)
- [`php-lab/docs/PAPER_TO_CODE_IVV.md`](php-lab/docs/PAPER_TO_CODE_IVV.md)
- [`php-lab/docs/FORMALIZATION_COVERAGE.md`](php-lab/docs/FORMALIZATION_COVERAGE.md)

## PHP/MySQL laboratory

Current public implementation: **OD Lab PHP/MySQL v1.4.0**.

Main characteristics:

- PHP 8.1+ and MySQL;
- browser-based `install.php` setup for conventional PHP hosting;
- light-only interface;
- French and English UI;
- researcher tooltips explaining concepts and the intended impact of experimental controls;
- sliders for the principal experimental parameters;
- DeepSeek Bring Your Own Key (BYOK);
- canonical backend ownership of scientific calculations;
- raw evidence logging and cryptographic evidence hashes;
- in-application HTML research reports;
- JSON and CSV research exports;
- hostile qualification and paper-to-code checks.

## BYOK: session-only API key

**The DeepSeek API key is not stored by OD Lab.**

The user enters the key when launching a research session. It is transmitted with that execution request, held only in PHP request memory while the run is executing, and then discarded. The application is designed not to persist the key in:

- MySQL;
- `config.php`;
- logs;
- frozen scientific configuration;
- reports;
- JSON/CSV exports;
- browser local storage or session storage.

Reloading or closing Mission Control forgets the key.

Never commit API keys, production database credentials, production `config.php`, `.env` files, private logs, or private research exports to this repository.

## Installation

1. Create an empty MySQL database and database user.
2. Upload the contents of [`php-lab/`](php-lab/) to the target server directory.
3. Open `install.php` in a browser.
4. Enter the database host, port, database name, database user/password, and OD Lab administrator credentials.
5. Complete installation and then delete `install.php` from the server.
6. Open `public/login.php`, or configure the web document root to `public/`.
7. Run **Diagnostic / Health** before the first experiment.
8. Start with a one-seed `smoke` run before running the complete H1–H5 program.

No LLM API key is requested or stored during installation.

## Evidence and reproducibility

Each run is designed to preserve enough information to reconstruct how its result was produced, including configuration and split hashes, seeds, candidate provenance, admission/rejection reasons, scores and weights, memory distributions, gate and rollback decisions, state hashes, evaluator answers, API usage, timing, statistical analysis outputs, and evidence hashes.

The final test data are intended to remain outside generation, tuning, admission, and early stopping. Reports are generated from persisted canonical evidence rather than browser-side recomputation.

The research-analysis layer includes paired comparisons and uncertainty-oriented diagnostics for the H1–H5 program. Software qualification does not itself establish any empirical hypothesis.

## Verification

The repository includes checks for:

- PHP syntax and internal execution;
- independent Python ↔ PHP numerical cross-checks;
- hostile mathematical and architecture checks;
- H1–H5 analysis qualification;
- HTTP frontend → backend → service → engine → repository flow;
- repository/schema persistence contracts;
- evidence-integrity and tamper detection;
- BYOK secret non-persistence;
- manuscript SHA contract.

See [`php-lab/qualification/QUALIFICATION.md`](php-lab/qualification/QUALIFICATION.md) for the packaged qualification record.

Environment-specific validation of the production MySQL/PDO path and the live DeepSeek network path must still be performed on the actual deployment server before interpreting live experimental results.

## Research status

This repository is a **research implementation**, not evidence that Oneiric Dynamics is empirically superior to replay, ordinary augmentation, or other baselines. The manuscript explicitly treats the proposed empirical effects as open questions to be tested.

## Citation

Use [`CITATION.cff`](CITATION.cff) for machine-readable citation metadata.

## Security

See [`SECURITY.md`](SECURITY.md) for credential-handling and reporting guidance.
