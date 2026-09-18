# Mathematical implementation contract — v1.4.0

Authoritative manuscript: **Oneiric Dynamics for Artificial Intelligence**, revised 16 September 2026. Required SHA-256:

`71d27adacc38fc8471c2a6d1be090f1279f3284d261f8c2addfc29401f4594b6`

The formulas below are not documentation-only: the named PHP methods are the runtime owners used by `LabEngine`.

## Eq. (3)-(4): proposal mixture

```text
Q_k = alpha K_rep + beta K_rec + gamma K_cf
alpha + beta + gamma = 1
rho = beta + gamma

alpha = 1-rho
beta  = rho(1-c)
gamma = rho c
```

Owner: `EquationSet::mixture()` and `sampleMixtureOperator()`. The finite batch performs one categorical operator draw per candidate from the frozen probability vector; no deterministic quota approximation is used.

## Eq. (5): absolute admission

`a_k(x)` is evaluated before scores. Invalid structure, oracle incompatibility, bad provenance, invalid replay lineage, non-substantive recombination, invalid intervention, or a failed H2 novelty band gives zero learning weight.

Owner: `LabEngine::admit()`.

## Eq. (6): relative ranking

```text
r_k(x) = lambda_N N_k(x) - lambda_C C_k(x) - lambda_A A_k(x)
```

Owner: `EquationSet::rankingScore()`. In this controlled benchmark anchor correctness is mandatory in Eq. (5), so admitted candidates have `A_k=0` and `lambda_A` is fixed to zero rather than exposed as a misleading slider.

## Eq. (7): Gibbs weights

```text
omega_j = exp((r_j-r_max)/T) / sum_i exp((r_i-r_max)/T),  T>0
```

Owner: `EquationSet::gibbsWeights()`. Nonfinite values fail closed.

## Eq. (8): admitted empirical distribution

```text
Qhat_k = sum_{j in J_k} omega_j delta_{x_j}
```

Owner: `EquationSet::empiricalDistribution()`; conversion to a finite measure is owned by `MemoryDistribution::fromQhat()`.

## Eq. (12): memory consolidation

```text
mu*_k = (1-eta_k) mu^W_k + eta_k Qhat_k,   0 <= eta_k <= 1
```

Owner: `MemoryDistribution::mixture()`. If no candidate is admitted, no empty `Qhat` is constructed and `mu*=muW`.

The model is fixed, so `mu` is the revisable learning state. For evaluation, a deterministic systematic materializer turns the full probability distribution into a fixed-size prompt context. The same seed-derived quantile offset is used for reference/proposal comparisons, which preserves paired comparisons while leaving Eq. (12) itself exact.

## Eq. (13)-(14): gate and rollback

```text
L_q(u*) <= L_q(uW) + delta_q  for every protected q
pass -> commit proposal
fail -> restore reference
```

Owner: `EquationSet::lossGate()` and `LabEngine`. Accuracy checks use `L=1-accuracy`. The gate also requires a valid probability distribution, provenance integrity and the Eq. (18) revision bound.

## Eq. (18): finite memory TV displacement

```text
dTV(mu*,muW) = eta dTV(Qhat,muW) <= eta
```

For finite distributions, `dTV=0.5*sum_x |p(x)-q(x)|`. Owner: `MemoryDistribution::totalVariation()` / `verifyMixtureBound()`. The identity and upper bound are checked for every nonempty proposal; a failure aborts the condition before commit.

## Eq. (19): novelty

```text
N_k(x) = min{Nmax, min_{z_i in B_k} ||phi(z)-phi(z_i)||_2 / sigma_k}
```

Owner: `EquationSet::finiteReferenceNovelty()`. `B_k` is the frozen external-training reference. `phi` is concatenated categorical one-hot over four known factors; `sigma=sqrt(8)` and `Nmax=1`. The sealed H4 absent factor is intentionally outside this generator representation.

## Eq. (20): influence diagnostics

```text
I_gen   = (1/m) sum_j N_k(x_j)
p_adm   = |J_k|/m
I_learn = sum_{j in J_k} omega_j N_k(x_j)
```

Owner: `EquationSet::noveltyDiagnostics()`.

## Equations deliberately not claimed

Eq. (9)-(11), Eq. (16)-(17) and Proposition 2 require access to trainable neural parameters `theta`; DeepSeek's API does not provide them. Eq. (15) / Proposition 4 require state-sufficiency and stochastic-process assumptions not established by this web implementation. These elements remain visible in the UI contract as **not claimed**, not silently approximated.


## Post-run statistical contract (not an OD equation)

The paper requires meaningful effect thresholds, repeated runs, uncertainty, paired task orders/seeds, and separation of outcomes. `Analysis.php` implements the application-level analysis plan without altering OD state:

- means and paired deltas use Student-t 95% intervals;
- H1 uses seed-paired replay+recombination minus replay and ordinary augmentation;
- H2 compares flat/linear/quadratic response forms and, with >=3 seed clusters, bootstraps the quadratic vertex by resampling seeds as clusters;
- H4 uses a seed-paired difference-in-differences against replay as the primary structural-reuse contrast: `(OD_known-replay_known) - (OD_absent-replay_absent)`; raw known-minus-absent gaps are diagnostic only;
- H5 computes targeted-minus-uniform performance and separate observed compute deltas;
- effect thresholds and bootstrap settings are frozen in `ResearchContract::defaults()['analysis']` before final evaluation.

These analyses are diagnostic/experimental and never modify generation, admission, weighting, gate, or final-test decisions.
