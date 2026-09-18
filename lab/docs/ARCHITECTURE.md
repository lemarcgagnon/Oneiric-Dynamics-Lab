# OD Lab v1.4.0 — transversal architecture contract

## Rule zero

The scientific path is singular. Public pages never own equations, model logic, scientific state, SQL, or hypothesis-specific engines.

```text
install.php -> config.php(DB only) + MySQL schema + install lock

Browser / Mission Control (FR/EN, light only)
        |
        | scientific config + BYOK key only on run request
        v
public/api.php
        |
        v
LabService  <---- single application facade
        |
        +--> ResearchContract: validate/freeze protocol, conditions, budgets, analysis thresholds
        +--> EquationSet: Eq. (3)-(8), (13), (19)-(20)
        +--> Benchmark: split authority, task oracle, frozen phi/B_k
        +--> MemoryDistribution: Eq. (12), Eq. (18), materialization
        +--> Analysis: paired/statistical research summaries only after all condition evidence exists
        |
        v
LabEngine
  frozen reference S_k^W
        |
        +--> iid mixture draw -> K_rep / K_rec / K_cf
        +--> absolute admission
        +--> relative weighting -> Qhat
        +--> mu*=(1-eta)mu_W+eta Qhat
        +--> Eq.18 check
        +--> protected-loss + structural/provenance gate
        +--> commit OR exact rollback
        |
        v
SEALED final evaluation
        |
        v
Repository -> PdoRepository -> MySQL
        |
        +--> hashed raw condition results
        +--> hashed append-style run events
        +--> canonical analysis JSON
        v
report.php / JSON / CSV
```

## BYOK boundary

The DeepSeek credential is not scientific state and is intentionally outside `ResearchContract`, mission config, config hash, MySQL schema, logs, reports and exports. `api.php` accepts it only at `create_run`, passes it to `LabService::runMission()`, and it reaches only `DeepSeekClient` request headers. Persistence guards reject any payload containing the credential text.

## Scientific authorities

- `ResearchContract.php`: protocol, ranges, comparisons, effect thresholds, exact paper SHA-256.
- `EquationSet.php`: canonical equations/gates.
- `MemoryDistribution.php`: finite probability measures, Eq. (12), TV/Eq. (18).
- `Benchmark.php`: immutable splits, task rule/oracle, novelty representation.
- `LabEngine.php`: only owner of cycle ordering and commit/rollback.
- `Analysis.php`: only owner of post-run paired/uncertainty calculations; never changes scientific state.
- `LabService.php`: only mission-execution/persistence facade.
- `PdoRepository.php`: MySQL adapter.
- `HealthService.php`: deployment diagnostics only; never participates in a scientific run.

## Frontend contract

Sliders are inputs, not authorities. JavaScript contains no mixture, Gibbs, novelty, memory, or gate formula. Preview calls the backend. At launch the validated protocol is serialized deterministically, hashed, persisted, then revalidated before execution.

## Failure behavior

Invalid mathematics fails before commit. Empty admission and `eta=0` may yield explicit no-change. Failed gates restore the reference state. Provider budgets fail closed. Credential leakage is blocked before persistence. Run failures remain failed evidence, never successful experiments.
