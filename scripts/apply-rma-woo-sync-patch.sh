#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-.}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PATCH_FILE="$SCRIPT_DIR/../patches/rma-woo-sync-disable-cobranca-confirmada.patch"
patch -p1 -d "$ROOT" < "$PATCH_FILE"
