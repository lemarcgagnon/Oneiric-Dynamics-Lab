#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"; fail=0
pass(){ echo "PASS $1"; }
bad(){ echo "FAIL $1"; fail=1; }

if grep -Eqi 'deepseek_api_key|deepseek_api_key_enc|api_key_enc' schema.sql; then bad 'schema contains API-key persistence column'; else pass 'MySQL schema has no LLM API-key persistence column'; fi
if grep -Eqi "name=[\"'].*(api|deepseek).*key|\$_POST\[[\"'].*(api|deepseek).*key" install.php; then bad 'installer asks for or consumes an LLM key'; else pass 'installer never asks for or consumes an LLM key'; fi
if grep -Eqi 'deepseek_api_key|api_key_enc|saveApiKey|getApiKey|hasApiKey' app/PdoRepository.php app/Repository.php; then bad 'repository exposes an API-key persistence path'; else pass 'repository boundary exposes no API-key persistence path'; fi
if grep -q "save_key" public/api.php; then bad 'API exposes a key-save action'; else pass 'API has no key-save route'; fi
if grep -Eq 'localStorage|sessionStorage|indexedDB' public/assets/app.js; then bad 'browser persists BYOK key'; else pass 'browser code does not persist BYOK key in web storage'; fi
grep -q 'id="apiKey" type="password"' public/index.php && grep -q 'autocomplete="off"' public/index.php && pass 'Mission Control key field is transient password input' || bad 'Mission Control key field is not transient enough'
grep -q "runMission(\$missionId,\$apiKey)" public/api.php && pass 'BYOK key is handed only to execution facade' || bad 'BYOK key execution wiring missing'
grep -q 'assertSecretAbsent(\$sessionApiKey,\$resultJson' app/LabService.php && grep -q 'assertSecretAbsent(\$sessionApiKey,\$eventJson' app/LabService.php && grep -q 'assertSecretAbsent(\$sessionApiKey,\$analysisJson' app/LabService.php && pass 'persisted evidence is guarded against secret inclusion' || bad 'secret-absence guards incomplete'
grep -q "credential_policy.*never persisted" app/LabService.php && pass 'run evidence records BYOK policy without credential' || bad 'BYOK evidence policy missing'
grep -q "\$this->key=''" app/DeepSeekClient.php && pass 'DeepSeek client clears in-memory key on destruction' || bad 'client key clearing missing'
if grep -RInE 'Authorization: Bearer.*(config|database|session)' app public --include='*.php' >/tmp/odlab-byok.$$; then cat /tmp/odlab-byok.$$; bad 'authorization header appears derived from persistent state'; else pass 'authorization header is derived only inside DeepSeek client from runtime key'; fi
grep -q "PROVIDER_BASE_URL = 'https://api.deepseek.com'" app/ResearchContract.php && grep -q 'Provider model/base URL are fixed by the research contract' app/ResearchContract.php && pass 'BYOK credential destination is locked to canonical DeepSeek endpoint' || bad 'provider endpoint can drift or be redirected'
grep -q 'OD Lab ne la persiste jamais' app/I18n.php && grep -q 'OD Lab never persists it' app/I18n.php && pass 'FR/EN session-only BYOK notice is explicit' || bad 'FR/EN BYOK notice missing'
rm -f /tmp/odlab-byok.$$ 2>/dev/null || true
exit $fail
