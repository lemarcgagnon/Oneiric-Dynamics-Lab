#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${ODLAB_INSTALLER_TEST_PORT:-8095}"
LOG="${TMPDIR:-/tmp}/odlab-installer-http-$$.log"
FR="${TMPDIR:-/tmp}/odlab-installer-fr-$$.html"
EN="${TMPDIR:-/tmp}/odlab-installer-en-$$.html"
cd "$ROOT"
[[ ! -f config.php && ! -f storage/.installed ]] || { echo 'FAIL installer fixture is already installed'; exit 1; }
php -S "127.0.0.1:$PORT" -t "$ROOT" >"$LOG" 2>&1 & PID=$!
cleanup(){ kill "$PID" 2>/dev/null || true; wait "$PID" 2>/dev/null || true; rm -f "$FR" "$EN" "$LOG"; }
trap cleanup EXIT
for _ in $(seq 1 30); do curl -fsS "http://127.0.0.1:$PORT/install.php?lang=fr" -o "$FR" 2>/dev/null && break; sleep .1; done
curl -fsS "http://127.0.0.1:$PORT/install.php?lang=en" -o "$EN"
grep -q 'Installation PHP/MySQL' "$FR" || { echo 'FAIL installer FR'; exit 1; }
grep -q 'PHP/MySQL installation' "$EN" || { echo 'FAIL installer EN'; exit 1; }
grep -q 'Générateur aléatoire sécurisé' "$FR" || { echo 'FAIL installer translated preflight FR'; exit 1; }
grep -q 'Secure random generator' "$EN" || { echo 'FAIL installer translated preflight EN'; exit 1; }
grep -q 'name="db_host"' "$FR" && grep -q 'name="admin_pass"' "$FR" || { echo 'FAIL installer DB/admin fields'; exit 1; }
! grep -Eqi 'name="[^\"]*(api|deepseek)[^\"]*"|sk-' "$FR" || { echo 'FAIL installer exposes LLM-key field'; exit 1; }
grep -q 'name="color-scheme" content="light"' "$FR" || { echo 'FAIL installer is not light-only'; exit 1; }
! grep -qi 'prefers-color-scheme.*dark' "$FR" || { echo 'FAIL installer dark mode branch'; exit 1; }
echo 'PASS install.php executes through real PHP HTTP server'
echo 'PASS installer renders FR/EN and translated preflight'
echo 'PASS installer collects only DB/admin credentials, never LLM key'
echo 'PASS installer remains light-only'
