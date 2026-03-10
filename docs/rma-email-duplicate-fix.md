# Correção textual (sem alterar binários)

Para evitar PR com arquivos binários, a correção foi entregue como patch textual.

## Arquivos
- `patches/rma-woo-sync-disable-cobranca-confirmada.patch`
- `scripts/apply-rma-woo-sync-patch.sh`

## Escopo
Remove **somente** a chamada:
`send_anexo2_event_email($entity_id, 'cobranca_confirmada', ...)`
no trecho de atualização para `adimplente`.

## Aplicação
1. Extraia `plugins.zip`.
2. Aplique: `scripts/apply-rma-woo-sync-patch.sh <diretório-extraído>`.
3. Recompacte no seu pipeline de release.
