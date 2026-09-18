#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; cd "$ROOT"
PAPER="${1:-}"
if [[ -z "$PAPER" || ! -f "$PAPER" ]]; then echo "usage: $0 /path/to/Oneiric_Dynamics_Research_Framework_v3_2026-09-16.pdf" >&2; exit 2; fi
section(){ printf '\n===== %s =====\n' "$1"; }
section 'AUDITOR A — PHP/code integrity'
while IFS= read -r -d '' f; do php -l "$f" >/dev/null || exit 1; done < <(find app public tests -name '*.php' -type f -print0; printf '%s\0' install.php index.php)
echo 'PASS PHP syntax all application/test files'
bash tests/architecture_audit.sh
section 'AUDITOR B — paper/mathematics IV&V'
bash tests/paper_contract_check.sh "$PAPER"
php tests/paper_ivv_hostile.php
python3 tests/mathematical_crosscheck.py
section 'AUDITOR C — experimental semantics + analysis'
php tests/selftest.php
php tests/internal_qualification.php
php tests/hostile_qualification.php
php tests/analysis_qualification.php
section 'AUDITOR D — persistence/evidence/BYOK'
php tests/persistence_contract.php
php tests/evidence_integrity_hostile.php
bash tests/byok_security_audit.sh
php tests/byok_runtime_hostile.php
section 'AUDITOR E — real PHP vertical HTTP orchestration'
bash tests/installer_http.sh
bash tests/http_integration.sh
bash tests/full_research_pipeline.sh
printf '\nHOSTILE RELEASE QUALIFICATION: PASS\n'
