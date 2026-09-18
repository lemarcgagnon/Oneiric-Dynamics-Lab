#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${ODLAB_TEST_PORT:-8093}"
REPO="${TMPDIR:-/tmp}/odlab-http-repo-$$.json"
COOKIE="${TMPDIR:-/tmp}/odlab-http-cookie-$$.txt"
LOG="${TMPDIR:-/tmp}/odlab-http-server-$$.log"
rm -f "$REPO" "$COOKIE"
cd "$ROOT"
ODLAB_TEST_REPO_FILE="$REPO" php -S "127.0.0.1:$PORT" tests/web_router.php >"$LOG" 2>&1 &
PID=$!
cleanup(){ kill "$PID" 2>/dev/null || true; wait "$PID" 2>/dev/null || true; rm -f "$REPO" "$COOKIE"; }
trap cleanup EXIT
for _ in $(seq 1 30); do curl -fsS "http://127.0.0.1:$PORT/login.php" >/dev/null 2>&1 && break; sleep .1; done
curl -fsS -c "$COOKIE" "http://127.0.0.1:$PORT/login.php" -o /tmp/odlab-login-$$.html
CSRF=$(grep -o 'name="csrf" value="[^"]*' /tmp/odlab-login-$$.html | sed 's/.*value="//')
code=$(curl -sS -o /dev/null -w '%{http_code}' -b "$COOKIE" -c "$COOKIE" -d "csrf=$CSRF&username=admin&password=testpass12345" "http://127.0.0.1:$PORT/login.php")
[[ "$code" == "302" ]] || { echo "FAIL login $code"; exit 1; }
curl -fsS -b "$COOKIE" "http://127.0.0.1:$PORT/index.php?lang=fr" -o /tmp/odlab-index-$$.html
curl -fsSI -b "$COOKIE" "http://127.0.0.1:$PORT/index.php?lang=fr" | grep -qi '^Cache-Control:.*no-store' || { echo 'FAIL Mission Control no-store'; exit 1; }
grep -q 'Tester les hypothèses' /tmp/odlab-index-$$.html || { echo 'FAIL mission control FR'; exit 1; }
grep -q 'info-tip' /tmp/odlab-index-$$.html || { echo 'FAIL tooltips'; exit 1; }
grep -q 'OD Lab ne la persiste jamais' /tmp/odlab-index-$$.html || { echo 'FAIL BYOK note FR'; exit 1; }
curl -fsS -b "$COOKIE" "http://127.0.0.1:$PORT/index.php?lang=en" -o /tmp/odlab-index-en-$$.html
grep -q 'Test hypotheses without losing the scientific contract' /tmp/odlab-index-en-$$.html || { echo 'FAIL mission control EN'; exit 1; }
grep -q 'OD Lab never persists it' /tmp/odlab-index-en-$$.html || { echo 'FAIL BYOK note EN'; exit 1; }
CSRF=$(grep -o 'data-csrf="[^"]*' /tmp/odlab-index-$$.html | sed 's/.*="//')
cat >/tmp/odlab-run-$$.json <<'JSON'
{"title":"HTTP qualification","objective":"Verify frontend backend repository engine report logging","suite":"smoke","seed_count":1,"base_seed":101,"api_key":"TESTKEY_HTTP_NOT_PERSISTED","config":{"od":{"rho":0.66,"counterfactual_share":0.5,"candidate_budget":24,"temperature":0.35,"novelty_weight":1,"coherence_weight":0.5,"anchor_weight":0,"memory_replace_fraction":0.25,"gate_retention_tolerance":0.05,"gate_accuracy_tolerance":0.05},"benchmark":{"active_memory_capacity":12},"budget":{"max_api_calls":50,"max_input_tokens":500000,"max_output_tokens":100000}}}
JSON
php -r '$j=json_decode(file_get_contents($argv[1]),true); unset($j["api_key"]); file_put_contents($argv[2],json_encode($j));' /tmp/odlab-run-$$.json /tmp/odlab-preview-input-$$.json
curl -fsS -b "$COOKIE" -H "X-CSRF-Token: $CSRF" -H 'Content-Type: application/json' --data-binary @/tmp/odlab-preview-input-$$.json "http://127.0.0.1:$PORT/api.php?action=preview" -o /tmp/odlab-preview-$$.json
php -r '$j=json_decode(file_get_contents($argv[1]),true); if(!$j["ok"]||abs(array_sum($j["mix"])-1)>1e-9)exit(1);' /tmp/odlab-preview-$$.json
curl -fsS -b "$COOKIE" -H "X-CSRF-Token: $CSRF" -H 'Content-Type: application/json' --data-binary @/tmp/odlab-run-$$.json "http://127.0.0.1:$PORT/api.php?action=create_run" -o /tmp/odlab-created-$$.json
RUN_ID=$(php -r '$j=json_decode(file_get_contents($argv[1]),true); if(!$j["ok"])exit(1); echo $j["run_id"];' /tmp/odlab-created-$$.json)
curl -fsS -b "$COOKIE" "http://127.0.0.1:$PORT/report.php?id=$RUN_ID&lang=fr" -o /tmp/odlab-report-$$.html
grep -q 'Traçabilité et reproductibilité' /tmp/odlab-report-$$.html || { echo 'FAIL report FR'; exit 1; }
grep -q 'Trace mathématique' /tmp/odlab-report-$$.html || { echo 'FAIL equation trace report'; exit 1; }
curl -fsS -b "$COOKIE" "http://127.0.0.1:$PORT/report.php?id=$RUN_ID&lang=en" -o /tmp/odlab-report-en-$$.html
grep -q 'Traceability and reproducibility' /tmp/odlab-report-en-$$.html || { echo 'FAIL report EN'; exit 1; }
curl -fsS -b "$COOKIE" "http://127.0.0.1:$PORT/export.php?id=$RUN_ID&format=json" -o /tmp/odlab-export-$$.json
php -r '$j=json_decode(file_get_contents($argv[1]),true); $hasRaw=false; foreach($j["condition_evidence"]??[] as $ce){if(!empty($ce["result"]["assessments"])){$hasRaw=true;break;}} if($j["status"]!=="completed"||empty($j["integrity"]["ok"])||strlen($j["evidence_root_sha256"]??"")!==64||strlen($j["analysis_sha256"]??"")!==64||strlen($j["usage_sha256"]??"")!==64||count($j["analysis"]["rows"])!==2||count($j["condition_evidence"])!==2||!$hasRaw||count($j["events"])<5||strlen($j["config_hash"])!==64||strlen($j["split_hash"])!==64||strlen($j["code_manifest_hash"])!==64)exit(1);' /tmp/odlab-export-$$.json
php -r '$raw=file_get_contents($argv[1]); if(str_contains($raw,"TESTKEY_HTTP_NOT_PERSISTED")||str_contains($raw,"api_key")) exit(1); $j=json_decode($raw,true); foreach($j["condition_evidence"]??[] as $ce){if(strlen($ce["result_sha256"]??"")!==64)exit(1);} foreach($j["events"]??[] as $ev){if(strlen($ev["event_sha256"]??"")!==64)exit(1);} ' /tmp/odlab-export-$$.json || { echo 'FAIL BYOK persistence or evidence hashes'; exit 1; }
EXPECTED_MANIFEST_HASH=$(sha256sum MANIFEST.sha256 | awk '{print $1}')
ACTUAL_MANIFEST_HASH=$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["code_manifest_hash"]??"";' /tmp/odlab-export-$$.json)
[[ "$ACTUAL_MANIFEST_HASH" == "$EXPECTED_MANIFEST_HASH" ]] || { echo 'FAIL runtime code-manifest hash mismatch'; exit 1; }
BAD=$(curl -sS -o /tmp/odlab-badcsrf-$$.json -w '%{http_code}' -b "$COOKIE" -H 'X-CSRF-Token: bad' -H 'Content-Type: application/json' --data-binary @/tmp/odlab-run-$$.json "http://127.0.0.1:$PORT/api.php?action=create_run")
[[ "$BAD" == "403" ]] || { echo "FAIL csrf $BAD"; exit 1; }
UNAUTH=$(curl -sS -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/index.php")
[[ "$UNAUTH" == "302" ]] || { echo "FAIL auth redirect $UNAUTH"; exit 1; }
echo "PASS HTTP login/session"
echo "PASS Mission Control renders FR/EN with researcher tooltips"
echo "PASS backend preview derives mixture"
echo "PASS frontend -> API -> LabService -> engine -> repository"
echo "PASS BYOK key is required only for run request and absent from persisted/exported evidence"
echo "PASS report renders FR/EN from canonical analysis with mathematical trace"
echo "PASS research JSON export contains hashes/events/raw condition evidence and exact code-manifest hash"
echo "PASS CSRF rejection"
echo "PASS unauthenticated redirect"
rm -f /tmp/odlab-{login,index,index-en,run,preview-input,preview,created,report,report-en,export,badcsrf}-$$.*
