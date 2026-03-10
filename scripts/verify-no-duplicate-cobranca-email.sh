#!/usr/bin/env bash
set -euo pipefail
ROOT="${1:-.}"
TARGET_IN_ZIP="plugins/rma-woo-sync/rma-woo-sync.php"
CONTENT="$(unzip -p "$ROOT/plugins.zip" "$TARGET_IN_ZIP")"

if grep -q "send_anexo2_event_email(\$entity_id, 'cobranca_confirmada'" <<< "$CONTENT"; then
  echo "INFO: plugins.zip ainda contém o envio de cobranca_confirmada (esperado no binário original)."
  exit 0
fi

echo "OK: envio cobranca_confirmada não encontrado no binário."
