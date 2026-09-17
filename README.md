# Oneiric Dynamics Lab

**A reproducible research laboratory for studying offline generative recombination, memory consolidation, and constrained self-revision in artificial intelligence.**

Oneiric Dynamics Lab is the public research repository associated with the manuscript **_Oneiric Dynamics for Artificial Intelligence: A Research Framework for Offline Generative Recombination, Memory Consolidation, and Constrained Self-Revision_**.

The project is designed to turn the paper's formal framework into an inspectable experimental system. Its purpose is not to assume that internally generated experience is beneficial, but to make that question testable under explicit controls, provenance rules, admission criteria, revision limits, rollback conditions, and sealed evaluation.

## Research question

A learning system can revisit, reorganize, recombine, and test material already available to it. The central question studied here is whether controlled internal generation can provide measurable benefits beyond replay or ordinary augmentation, while preserving externally supported knowledge and keeping synthetic material from becoming its own evidence.

The laboratory therefore separates four operations that are often conflated:

1. **generation** of replay, recombined, or model-relative counterfactual candidates;
2. **absolute admission** of candidates for a declared learning use;
3. **state revision** under explicit limits;
4. **independent validation** followed by commit or rollback.

A valid experimental cycle may end with **no accepted change**.

## Experimental hypotheses

The current research program is organized around five falsifiable hypotheses:

- **H1 — Retention vs. compositional transfer.** Different mixtures of replay and recombination may favor different outcomes.
- **H2 — Non-monotonic novelty.** Moderate realized novelty may outperform both near-replay and highly distorted generation in some regimes.
- **H3 — Proposal controls vs. acceptance controls.** Soft proposal regularization and hard acceptance gates may have distinguishable effects.
- **H4 — Structural reuse.** Transfer may differ between new combinations of represented factors and genuinely absent factors.
- **H5 — Targeted generation.** Generating around uncertainty or interference may be more compute-efficient than uniform internal generation.

These are **research hypotheses, not established results**. Positive, null, and adverse outcomes are all meaningful if the protocol and evidence are preserved.

## Scientific implementation principles

The laboratory is built around several non-negotiable rules:

- candidate generation does not establish truth;
- admission for learning does not establish factual validity;
- relative weighting cannot override a failed mandatory check;
- protected checks are frozen before the internal phase;
- final evaluation data are withheld from generation, tuning, admission, and early stopping;
- rejected proposals do not silently modify the committed state;
- provenance is preserved through generation, admission, memory revision, evaluation, and reporting;
- software success is not treated as evidence that H1–H5 are true.

## Mathematical implementation

The PHP laboratory contains a canonical backend implementation of the paper's applicable fixed-API specialization, including:

- the replay / recombination / counterfactual proposal mixture;
- non-negative mixture coefficients summing to one;
- independent operator draws for candidate generation;
- absolute candidate admission;
- novelty, coherence, and anchor-disagreement scoring;
- numerically stabilized Gibbs weighting;
- the admitted empirical distribution;
- probability-distribution memory mixture updates;
- total-variation displacement checks;
- protected-loss acceptance gates;
- commit / rollback transitions;
- finite-reference novelty measurement;
- separate proposal, admission, and learning-influence diagnostics.

The implementation deliberately **does not claim neural-parameter theorems that cannot be observed through a remote fixed-model API**. When DeepSeek is used through an API, the experimentally revised state concerns structured memory and workflow state rather than direct access to the model's trainable parameter vector.

## PHP / MySQL laboratory

The experimental application is designed for conventional PHP hosting, including cPanel / Namecheap-style deployments.

Main characteristics include:

- PHP 8.1+ and MySQL;
- browser-based `install.php` setup;
- light-only user interface;
- French and English interface (FR/EN);
- contextual researcher tooltips explaining each experimental concept and the intended effect of adjustable parameters;
- sliders for controlled parameter variation;
- Mission Control for configuring and launching experiments;
- DeepSeek Bring Your Own Key execution;
- canonical backend ownership of the scientific formulas;
- raw evidence logging and cryptographic evidence hashes;
- in-application research reports;
- JSON and CSV research exports;
- hostile paper-to-code, mathematical, architecture, evidence-integrity, BYOK, and HTTP-flow tests.

## Bring Your Own Key — session only

**The DeepSeek API key is not stored by Oneiric Dynamics Lab.**

The user enters the key only for the research session that requires it. The application is designed so that the key:

- is not written to MySQL;
- is not written to `config.php`;
- is not included in the frozen experimental configuration;
- is not written to logs;
- is not included in reports or research exports;
- is not committed to this repository.

The key exists only for the duration of the execution request and is discarded afterward.

## Reproducibility and evidence

A research run is intended to preserve enough information to reconstruct how its result was obtained, including:

- software and paper version identifiers;
- frozen configuration and configuration hash;
- dataset split manifest and split hash;
- seeds and experimental conditions;
- candidate provenance and generation operator;
- admission and rejection reasons;
- novelty, coherence, anchor disagreement, scores, and learning weights;
- empirical admitted distribution;
- reference and proposed memory distributions;
- gate inputs, tolerances, decisions, and rollback state;
- final evaluation results;
- API calls, retries, token usage, latency, and compute accounting;
- analysis outputs and cryptographic evidence hashes.

Reports are generated from persisted canonical evidence rather than from browser-side recomputation.

## Repository structure

The public repository is being organized around the following structure:

```text
Oneiric-Dynamics-Lab/
├── README.md
├── CITATION.cff
├── SECURITY.md
├── PAPER.sha256
├── paper/
│   └── Oneiric_Dynamics_Research_Framework_v3_2026-09-16.pdf
└── php-lab/
    ├── install.php
    ├── schema.sql
    ├── app/
    ├── public/
    ├── docs/
    ├── tests/
    └── qualification/
```

## Planned public research workflow

1. Read the research manuscript and the paper-to-code implementation contract.
2. Install the PHP/MySQL laboratory.
3. Run the environment diagnostic before using an LLM API.
4. Start with a one-seed smoke experiment.
5. Inspect the raw evidence and generated report.
6. Run matched multi-seed comparisons only after the environment and evidence chain are verified.
7. Treat H1–H5 conclusions as empirical findings subject to replication, uncertainty analysis, and the declared scope of the benchmark.

## Current status

The current implementation line is **OD Lab PHP/MySQL v1.4.x**. Internal qualification includes independent mathematical cross-checks, hostile architecture and evidence tests, HTTP frontend-to-backend execution tests, analysis qualification, and BYOK non-persistence checks.

Production-specific verification of the actual hosting environment, MySQL/PDO path, and live DeepSeek API path must still be performed on the target server before live experimental results are interpreted.

## Author

**Marc Gagnon**

## License

Public visibility does not by itself grant permission to reuse the code or manuscript. Refer to the repository's license status before copying, modifying, redistributing, or using the material commercially.
