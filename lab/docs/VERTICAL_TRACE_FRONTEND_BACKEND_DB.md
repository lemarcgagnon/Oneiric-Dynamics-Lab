# Vertical trace — Mission Control to research evidence (v1.4.0)

Canonical path:

`slider/form -> app.js -> public/api.php -> LabService -> ResearchContract -> LabEngine -> Repository -> PdoRepository -> MySQL -> Analysis -> LabService::getRun -> report/export`

## Control trace

- sliders -> `preview`: backend validates and derives canonical mixture;
- run form -> `create_run`: top-level BYOK credential is separated from scientific config before mission creation;
- `LabService` freezes/hash-stores validated config and revalidates it before execution;
- `LabEngine` executes reference -> generation -> admission -> weighting -> proposal -> gate -> commit/rollback -> sealed final test;
- `LabService` persists each raw condition result and evaluator event through `Repository`;
- `PdoRepository` writes MySQL; public pages issue no SQL;
- `Analysis` consumes the completed condition evidence, not browser state;
- finalization order is `analysis -> terminal event -> evidence seal -> run completed -> mission completed`, so an unsealed run cannot be labelled completed;
- `report.php` and exports read canonical persisted run data.

## Evidence integrity

`condition_results.result_sha256` hashes the exact stored result JSON. `run_events.event_sha256` hashes event type plus exact event JSON. The run also keeps config, split, code-manifest, analysis and usage hashes. The ordered condition/event hashes are sealed into `runs.evidence_root_sha256`.

## BYOK trace

`#apiKey DOM field -> create_run JSON top-level api_key -> LabService::runMission(sessionApiKey) -> DeepSeekClient Authorization header`

There is no branch from the credential to `ResearchContract`, `missions.config_json`, `app_settings`, `condition_results`, `run_events`, reports, or exports. `Util::assertSecretAbsent()` guards persisted scientific/evidence payloads.

## Release proof

- `tests/http_integration.sh`: real PHP HTTP path through authentication, Mission Control, preview, run, report and export using the test repository/evaluator adapters.
- `tests/persistence_contract.php`: repository-to-PDO/schema wiring and BYOK no-column assertion.
- `tests/architecture_audit.sh`: anti-silo/public-route/static authority gates.
- `public/health.php`: actual deployed PDO/MySQL/transaction diagnostic on Namecheap.

## Evidence-integrity return path

`PdoRepository` hashes each condition result/event, hashes final analysis/usage, then seals the ordered result/event hash list into `runs.evidence_root_sha256`. `LabService::getRun()` verifies these hashes before report/export rendering. Corruption or deletion therefore stops report production rather than silently changing a research conclusion.
