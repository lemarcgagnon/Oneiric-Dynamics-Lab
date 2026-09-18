# Oneiric Dynamics formalization coverage — PHP v1.4.0

This ledger is intentionally conservative. **Exact** means the runtime evaluates the paper equation under the declared finite benchmark specialization. **Specialized** means the same logical role is implemented for a fixed external model, with the specialization explicit. **Not claimed** means the application refuses to borrow a theorem whose assumptions are unavailable.

| Paper element | Status | Runtime implementation |
|---|---|---|
| Eq. (1) sufficient state | SPECIALIZED | `LabEngine` logs fixed provider model, memory distribution `mu`, anchor hashes and auxiliary seed/split state. Neural `theta` is fixed outside the API. |
| Eq. (2) post-external reference | SPECIALIZED | The benchmark external training state is frozen before each internal phase; its split/hash, anchors and novelty reference do not change during the phase. |
| Eq. (3)-(4) mixture | EXACT | `EquationSet::mixture()` plus `sampleMixtureOperator()`. Each of the `m` candidates receives an iid categorical operator draw from the frozen mixture. |
| `K_rep` | SPECIALIZED | Exact externally grounded record replay with lineage preserved. |
| `K_rec` | SPECIALIZED | Uses >=2 grounded source records and must create a conjunction not previously present in external training. |
| `K_cf` | SPECIALIZED | Exactly one declared factor intervention relative to one grounded source. |
| Eq. (5) absolute admission | SPECIALIZED | `LabEngine::admit()`; failed mandatory checks always imply zero learning weight. |
| Eq. (6) ranking | EXACT FORM / SPECIALIZED `A_k` | `EquationSet::rankingScore()`. Anchor validity is mandatory, so admitted candidates have `A_k=0` and `lambda_A=0`. |
| Eq. (7) Gibbs weighting | EXACT | Stabilized max-subtracted implementation; uniform weighting remains a comparator. |
| Eq. (8) `Qhat_k` | EXACT | Candidate weights are logged; mathematically identical marked records are aggregated when converted to a finite measure. |
| Eq. (9)-(11), Proposition 2 | NOT CLAIMED | DeepSeek API exposes no trainable `theta`; no Euclidean parameter theorem is imported by analogy. |
| Eq. (12) memory proposal | EXACT | `MemoryDistribution::mixture()`: `mu*=(1-eta)mu_W+eta Qhat`. |
| Eq. (13) protected gate | SPECIALIZED | `lossGate()` uses lower-is-better losses; accuracy is converted with `L=1-accuracy`. Structural/provenance invariants are also required. |
| Eq. (14) commit/rollback | EXACT LOGIC | Failed gate commits the exact reference memory distribution; passed gate commits the proposal. |
| Eq. (15) Markov representation | DOCUMENTED, NOT CLAIMED | No global sufficiency/time-homogeneity theorem is asserted for the web application. |
| Eq. (16)-(17) parameter bounds | NOT CLAIMED | `theta` inaccessible. |
| Proposition 3 / Eq. (18) | EXACT FINITE SPECIALIZATION | Discrete TV is `0.5*L1`; every nonempty proposal verifies `dTV(mu*,muW)=eta*dTV(Qhat,muW)<=eta` at runtime. |
| Proposition 4 invariant measure | NOT CLAIMED | Compactness/Feller/time-homogeneity are not established. |
| Eq. (19) novelty | EXACT SPECIALIZATION | Frozen categorical one-hot `phi`, finite external `B_k`, `sigma=sqrt(8)`, `Nmax=1`; exact Euclidean formula. |
| Eq. (20) diagnostics | EXACT | Proposal novelty, admission fraction and weighted learning novelty are separate quantities. |
| Appendix A cycle | IMPLEMENTED-SPECIALIZED | Freeze -> bounded generation -> absolute admission -> weighting -> memory proposal -> checks -> commit/rollback -> evidence. Parameter-update step is explicitly absent. |
| Appendix C.2 softmax failure | ENFORCED | Admission occurs before weighting; reject-all remains possible. |
| Appendix C.3 soft anchoring | ENFORCED | Soft scores never replace the hard final gate. |
| H1 | EXPERIMENTAL + PAIRED ANALYSIS | Replay+recombination is compared seed-by-seed with replay and ordinary augmentation; retention and known-combination transfer have separate paired deltas and Student-t intervals. |
| H2 | EXPERIMENTAL + UNCERTAINTY | Realized Eq. (19) learning novelty is used; flat/linear/quadratic models, AICc where defined, interior vertex, prespecified peak threshold and seed-cluster bootstrap vertex interval are reported. |
| H3 | H3-FAPI ONLY | Varies Eq. (12) proposal magnitude `eta` versus hard gate; no claim about neural proximal regularization. |
| H4 | EXPERIMENTAL + PAIRED DIFFERENCE-IN-DIFFERENCES | Primary contrast is `(replay+recombination known − replay known) − (replay+recombination absent − replay absent)` within seed. Raw known-minus-absent gaps are retained as diagnostics only. |
| H5 | EXPERIMENTAL + PAIRED COST VIEW | Targeted and uniform generation are paired by seed; performance, API calls, tokens and wall time are reported separately, with a diagnostic performance-per-1000-token ratio. |

## Important fixed-API boundary

The manuscript explicitly says a fixed model accessed through an interface does not inherit the Euclidean parameter theorem by analogy. OD Lab follows that boundary. It tests the memory/retrieval side of the proposal-check-revision framework while keeping neural parameter claims out of scope.

## Analysis non-claim

The analysis layer can establish whether this controlled run meets its prespecified comparisons; it cannot establish external validity, universal benefit, or the neural-parameter theorems that are outside a fixed API. Positive, null and adverse results are all retained.


## Evidence integrity and report validity

Every raw condition result and runtime event is persisted before analysis with a SHA-256. Canonical analysis and usage payloads are hashed separately, and the ordered condition/event hashes are sealed into a run-level evidence root. `LabService::getRun()` re-verifies all hashes before reports or exports are rendered. Run status is finalized only after the evidence root exists. This protects the report from silently accepting modified or missing evidence; it does not by itself establish external validity of the experiment.
