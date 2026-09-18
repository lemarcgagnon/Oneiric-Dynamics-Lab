#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
PORT="${ODLAB_FULL_TEST_PORT:-8094}"
REPO="${TMPDIR:-/tmp}/odlab-full-research-$$.json"
LOG="${TMPDIR:-/tmp}/odlab-full-research-server-$$.log"
rm -f "$REPO"
RUN_ID=$(php tests/create_research_fixture.php "$REPO")
[[ "$RUN_ID" =~ ^[0-9]+$ ]] || { echo "FAIL full fixture run id"; exit 1; }
# Verify the persistent research package before rendering it.
php -r '
$f=$argv[1];$s=json_decode(file_get_contents($f),true);if(!is_array($s))exit(1);
$r=array_values($s["runs"]??[])[0]??null;if(!$r||($r["status"]??"")!=="completed")exit(2);
$a=json_decode($r["analysis_json"]??"{}",true);if(($a["research_status"]??"")!=="controlled_analysis_ready")exit(3);if(strlen($r["analysis_sha256"]??"")!==64||strlen($r["usage_sha256"]??"")!==64||strlen($r["evidence_root_sha256"]??"")!==64)exit(11);
if(count($a["rows"]??[])!==45)exit(4);
if(!isset($a["h1"]["recombination_vs_replay"],$a["h2"]["models"],$a["h4"]["primary_recombination_vs_replay"],$a["h5"]["known_transfer"]))exit(5);
if(count($s["results"]??[])!==45)exit(6);
foreach($s["results"] as $row){if(strlen($row["result_sha256"]??"")!==64)exit(7);$payload=$row["result_json"]??"";if(hash("sha256",$payload)!==($row["result_sha256"]??""))exit(8);}
foreach($s["events"]??[] as $ev){if(strlen($ev["event_sha256"]??"")!==64)exit(9);$payload=$ev["event_json"]??"";if(hash("sha256",($ev["event_type"]??"")."\\n".$payload)!==($ev["event_sha256"]??""))exit(10);}
' "$REPO"

ODLAB_TEST_REPO_FILE="$REPO" php -S "127.0.0.1:$PORT" tests/visual_router.php >"$LOG" 2>&1 &
PID=$!
cleanup(){ kill "$PID" 2>/dev/null || true; wait "$PID" 2>/dev/null || true; rm -f "$REPO"; }
trap cleanup EXIT
for _ in $(seq 1 30); do curl -fsS "http://127.0.0.1:$PORT/index.php?lang=fr" >/dev/null 2>&1 && break; sleep .1; done
curl -fsS "http://127.0.0.1:$PORT/report.php?id=$RUN_ID&lang=fr" -o /tmp/odlab-full-report-$$.html
grep -q 'H1–H5' /tmp/odlab-full-report-$$.html || { echo 'FAIL full report hypotheses'; exit 1; }
grep -q 'ΔΔ' /tmp/odlab-full-report-$$.html || { echo 'FAIL H4 difference-in-differences render'; exit 1; }
grep -q 'Plan d’analyse gelé' /tmp/odlab-full-report-$$.html || { echo 'FAIL frozen analysis plan render'; exit 1; }
grep -q 'Trace mathématique' /tmp/odlab-full-report-$$.html || { echo 'FAIL mathematical trace render'; exit 1; }
curl -fsS "http://127.0.0.1:$PORT/report.php?id=$RUN_ID&lang=en" -o /tmp/odlab-full-report-en-$$.html
grep -q 'Primary benefit contrast' /tmp/odlab-full-report-en-$$.html || { echo 'FAIL full report EN H4'; exit 1; }
curl -fsS "http://127.0.0.1:$PORT/export.php?id=$RUN_ID&format=json" -o /tmp/odlab-full-export-$$.json
php -r '$j=json_decode(file_get_contents($argv[1]),true); if(count($j["condition_evidence"]??[])!==45||count($j["analysis"]["rows"]??[])!==45||($j["analysis"]["research_status"]??"")!=="controlled_analysis_ready")exit(1);' /tmp/odlab-full-export-$$.json
rm -f /tmp/odlab-full-{report,report-en,export}-$$.*
echo 'PASS full all-suite 3-seed experiment completes through canonical service/engine/repository'
echo 'PASS all 45 condition×seed evidence rows and event hashes verify'
echo 'PASS full H1-H5 report renders FR/EN from persisted analysis'
echo 'PASS H4 difference-in-differences and frozen analysis plan render in application'
echo 'PASS full forensic JSON export contains all 45 condition evidence rows'
