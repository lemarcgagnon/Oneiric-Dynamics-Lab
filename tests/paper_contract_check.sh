#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PAPER="${1:-}"
EXPECTED="71d27adacc38fc8471c2a6d1be090f1279f3284d261f8c2addfc29401f4594b6"
[[ -n "$PAPER" && -f "$PAPER" ]] || { echo "FAIL paper path required"; exit 2; }
ACTUAL=$(sha256sum "$PAPER" | awk '{print $1}')
[[ "$ACTUAL" == "$EXPECTED" ]] || { echo "FAIL paper hash mismatch: $ACTUAL"; exit 1; }
grep -q "$EXPECTED" "$ROOT/app/ResearchContract.php" || { echo "FAIL code not bound to supplied paper hash"; exit 1; }
echo "PASS supplied manuscript SHA-256 matches canonical research contract"
