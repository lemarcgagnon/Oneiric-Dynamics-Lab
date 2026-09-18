# Research contract

This file is the controlling scientific contract for OD Lab v0.2.0. Code changes that conflict with it are defects even when unit tests pass.

## Source manuscript

**Oneiric Dynamics for Artificial Intelligence: A Research Framework for Offline Generative Recombination, Memory Consolidation, and Constrained Self-Revision**, revised 16 September 2026.

Expected SHA-256: `71d27adacc38fc8471c2a6d1be090f1279f3284d261f8c2addfc29401f4594b6`.

## Non-negotiable rules

1. **Generation, admission, weighting, revision, and validation are separate operations.** No component may collapse them into one score.
2. **Absolute admission precedes relative weighting.** A high novelty or ranking score can never rescue a failed mandatory check.
3. **Reject-all is valid.** If no candidate is admitted, the learning state remains unchanged.
4. **Provenance is persistent.** Every replay, recombination, and counterfactual record carries historical origin, production operator, source lineage, model/version context, and intervention scope where applicable.
5. **Synthetic material is never promoted to external evidence by reuse.** Its origin remains synthetic even when it is useful.
6. **The final test set is sealed.** It is excluded from source memory, generation pools, targeting probes, admission, tuning, and commit gates.
7. **The proposer does not certify itself.** Candidate correctness is checked by the benchmark's deterministic external task rule; commit checks are performed on distinct data.
8. **Frozen phase policy.** A cycle freezes its reference memory, split manifest, evaluators, tolerances, novelty definition, and budget before candidate generation.
9. **Hard commit gate and rollback.** A failed mandatory state check restores the reference learning state exactly.
10. **Finite budget.** Candidate count, API calls, tokens, and retries are bounded both per condition and across the complete run. Budget exhaustion fails closed.
11. **Report proposal, admission, influence, and commit separately.** Never report only the final accepted metric.
12. **Count all relevant compute.** Targeting probes, generation, scoring, verification, rejections, and final evaluation are accounted separately.
13. **Fixed-model scope is explicit.** DeepSeek parameters are immutable from the laboratory's perspective. Memory/prompt-state results do not inherit the manuscript's Euclidean parameter theorem.
14. **No hidden future-factor access.** Candidate generation may use only factor values present in the external source archive for the known-factor experiment.
15. **No silent benchmark drift.** Split definitions, oracle rules, model ID, prompt protocol, memory capacity, and condition definitions are frozen in the run manifest.

## Change rule

Any code change touching a rule above must update `docs/02_PAPER_TRACEABILITY.md`, `docs/paper_alignment.json`, and at least one executable test. If a rule cannot be satisfied, the run must be labeled non-conformant rather than silently relaxed.

## Application-level non-silo rules (v0.2.0)

16. **One application facade.** CLI and browser Mission Control call `LabService`; neither may create an alternate execution pipeline.
17. **UI is configuration selection, not policy authority.** User mission fields cannot silently rewrite scientific gates, oracle rules, benchmark splits, provider policy, or budgets.
18. **One result authority.** CSV/JSON evidence is canonical; Markdown and illustrated HTML are renderings of the same evidence, not independently recomputed conclusions.
19. **Formalization ledger is mandatory.** Every major manuscript mechanism must be `implemented`, explicitly specialized, or explicitly not claimed. Silent omissions are defects.
20. **No local patch without transversal review.** A change to a user surface, reporting layer, experiment condition, scientific mechanism, or data boundary must be checked against architecture, research contract, paper traceability, formalization coverage, tests, and audit before release.
