#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)";cd "$ROOT";fail=0
pass(){ echo "PASS $1"; }
bad(){ echo "FAIL $1"; fail=1; }
if grep -RInE 'new[[:space:]]+(ODLab\\)?(LabEngine|Benchmark|DeepSeekClient)|EquationSet::|MemoryDistribution::' public --include='*.php' >/tmp/odlab-arch.$$; then cat /tmp/odlab-arch.$$; bad 'public routes bypass LabService'; else pass 'public routes do not instantiate scientific engine'; fi
if grep -RInE '\bdb\s*\(' public --include='*.php' >/tmp/odlab-db.$$; then cat /tmp/odlab-db.$$; bad 'public routes issue direct DB calls'; else pass 'public routes do not issue direct DB calls'; fi
if grep -E '1[[:space:]]*-[[:space:]]*rho|rho[[:space:]]*\*|counterfactual_share[[:space:]]*\*' public/assets/app.js >/dev/null; then bad 'frontend duplicates mixture equation'; else pass 'frontend requests canonical backend mixture'; fi
[[ $(grep -Fc "splits['final_known']" app/LabEngine.php) -eq 1 ]] && pass 'final_known data touched once in engine final stage' || bad 'unexpected final_known data access'
[[ $(grep -Fc 'sealedFinalAbsent()' app/LabEngine.php) -eq 1 ]] && pass 'sealed final-absent data touched once in engine final stage' || bad 'unexpected final_absent data access'
grep -q 'run_events' schema.sql && grep -q 'split_manifest_json' schema.sql && grep -q 'code_manifest_hash' schema.sql && pass 'research evidence schema present' || bad 'research evidence schema incomplete'
[[ -d storage ]] && pass 'storage directory exists in package' || bad 'storage directory absent'
grep -q 'mkdir($storage' install.php && pass 'installer creates storage defensively' || bad 'installer does not create storage'
grep -q 'beginTransaction' install.php && grep -q 'rollBack' install.php && pass 'installer account/settings write is transactional' || bad 'installer transaction missing'
if grep -RInE 'sk-[A-Za-z0-9_-]{20,}|DEEPSEEK_API_KEY[[:space:]]*=[[:space:]]*[A-Za-z0-9]' . --exclude='*.md' --exclude='architecture_audit.sh' >/tmp/odlab-secret.$$; then cat /tmp/odlab-secret.$$; bad 'embedded secret candidate'; else pass 'no embedded API key candidate'; fi
if grep -RIn 'ODLabTests\\' app public install.php >/tmp/odlab-testdep.$$; then cat /tmp/odlab-testdep.$$; bad 'production depends on test namespace'; else pass 'production code has no test namespace dependency'; fi
grep -q '71d27adacc38fc8471c2a6d1be090f1279f3284d261f8c2addfc29401f4594b6' app/ResearchContract.php && pass 'research contract binds exact manuscript SHA-256' || bad 'paper hash missing'
if grep -RInE 'color-scheme[[:space:]]*:[[:space:]]*dark|prefers-color-scheme[[:space:]]*:[^)]*dark' public install.php >/tmp/odlab-dark.$$; then cat /tmp/odlab-dark.$$; bad 'dark-mode branch present'; else pass 'application has no dark-mode branch'; fi
grep -q 'final class I18n' app/I18n.php && grep -q "'fr'=>" app/I18n.php && grep -q "'en'=>" app/I18n.php && pass 'FR/EN translation authority present' || bad 'i18n authority incomplete'
grep -q 'class="info-tip"' app/bootstrap.php && grep -q "info_tip('rho')" public/index.php && grep -q "slider('param.temperature','temperature'" public/index.php && pass 'researcher information bubbles wired to Mission Control' || bad 'research tooltips incomplete'
grep -q "t('health.title')" public/health.php && ! grep -q '\$fr?' public/health.php && grep -q "il('install.check.php')" install.php && grep -q "t('report.h2_models'" public/report.php && pass 'FR/EN UI routes use canonical I18n authority for health/install/report research text' || bad 'i18n authority bypass detected in user-facing routes'
! grep -q 'ABSENT_TEXTURE' app/LabEngine.php && pass 'sealed absent factor is outside generation engine' || bad 'absent-factor knowledge leaked into generation engine'
grep -q 'finiteReferenceNovelty' app/EquationSet.php && grep -q 'evaluationEmbedding' app/Benchmark.php && grep -q 'noveltyScale' app/Benchmark.php && pass 'Eq19 formula and declared phi/sigma are centralized' || bad 'Eq19 implementation incomplete'
grep -q 'sampleMixtureOperator' app/EquationSet.php && grep -q "iid_categorical_operator_draws" app/LabEngine.php && ! grep -q 'floor($budget' app/LabEngine.php && pass 'Eq3 finite batch uses iid mixture draws, not deterministic quotas' || bad 'Eq3 sampling law drift'
grep -q 'empiricalDistribution' app/EquationSet.php && grep -q "'eq8_qhat'" app/LabEngine.php && pass 'Eq8 Q-hat is explicit and logged' || bad 'Eq8 trace missing'
grep -q 'mu_star=(1-eta)mu_W+eta\*Qhat' app/LabEngine.php && grep -q 'verifyMixtureBound' app/MemoryDistribution.php && pass 'Eq12 memory mixture and Eq18 TV bound are canonical runtime code' || bad 'Eq12/18 implementation missing'
grep -q 'lossGate' app/EquationSet.php && grep -q 'accuracyGate' app/EquationSet.php && pass 'Eq13 native loss gate and accuracy specialization present' || bad 'Eq13 specialization unclear'

grep -q "new LabService" public/api.php && grep -q "runMission" public/api.php && pass 'API routes through LabService for execution' || bad 'API execution facade wiring missing'
grep -q "saveConditionResult" app/LabService.php && grep -q "saveRunEvent" app/LabService.php && grep -q "saveRunAnalysis" app/LabService.php && grep -q "finalizeRun" app/LabService.php && pass 'LabService persists evidence and finalizes only after sealing' || bad 'LabService research persistence/finalization wiring incomplete'
grep -q "INSERT INTO condition_results" app/PdoRepository.php && grep -q "INSERT INTO run_events" app/PdoRepository.php && grep -q "UPDATE runs SET status=\"completed\"" app/PdoRepository.php && pass 'PdoRepository maps research evidence and final completion to MySQL' || bad 'PDO research evidence mapping incomplete'
grep -q 'evidence_root_sha256' schema.sql && grep -q 'sealRunEvidence' app/LabService.php && grep -q 'evidenceRoot' app/Util.php && pass 'research evidence is sealed with a run-level integrity root' || bad 'run-level evidence sealing missing'
if python - <<'PY2'
from pathlib import Path
s=Path('app/LabService.php').read_text()
order=[s.find('saveRunAnalysis($runId'),s.find("saveRunEvent($runId,'run_completed'"),s.find('sealRunEvidence($runId'),s.find('finalizeRun($runId'),s.find('markMissionCompleted($missionId')]
raise SystemExit(0 if all(x>=0 for x in order) and order==sorted(order) else 1)
PY2
then pass 'run completion is ordered analysis -> terminal event -> evidence seal -> run finalize -> mission finalize'; else bad 'run finalization order can mark incomplete evidence completed'; fi


# BYOK is a request-memory execution input, never part of persistent scientific/application state.
if grep -Eqi 'deepseek_api_key|deepseek_api_key_enc|api_key_enc' schema.sql; then bad 'schema persists LLM API key'; else pass 'schema has no LLM API-key persistence column'; fi
if grep -Eq 'localStorage|sessionStorage|indexedDB' public/assets/app.js; then bad 'frontend persists session-only key'; else pass 'frontend has no web-storage key persistence'; fi
if grep -q 'save_key' public/api.php; then bad 'API exposes key persistence route'; else pass 'API exposes no key persistence route'; fi
grep -q "PROVIDER_BASE_URL = 'https://api.deepseek.com'" app/ResearchContract.php && grep -q "Provider model/base URL are fixed by the research contract" app/ResearchContract.php && pass 'BYOK provider destination/model are server-side frozen' || bad 'BYOK provider destination can drift from canonical research contract'
grep -q 'assertSecretAbsent' app/LabService.php && pass 'LabService guards persisted evidence against BYOK secret' || bad 'LabService secret guard missing'
grep -q 'tests|qualification' .htaccess && pass 'internal test/qualification directories are denied from web access' || bad 'internal test artifacts are web-exposed'

sha256sum -c MANIFEST.sha256 >/dev/null 2>&1 && pass 'package manifest matches current files' || bad 'package manifest is stale or incomplete'
rm -f /tmp/odlab-{arch,db,secret,testdep,dark}.$$ 2>/dev/null || true
exit $fail
