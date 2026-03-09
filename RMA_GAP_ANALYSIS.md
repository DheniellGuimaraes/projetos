# RMA Gap Analysis (Audit Snapshot)

This document captures the current state of the delivered package against the requested RMA organogram, based on static audit of:

- `exertio/inc/options-init.php`
- plugins in `plugins/`
- map UI in `exertio/template-parts/dashboard/rma-brazil-map.php`

## Implemented (high confidence)

- Public directory with filters for state, city, name search, adimplência and **Área de Interesse**.
- Public REST route feeding directory/list/map data.
- Interactive Brazil map and entity links.
- Public entity profile shortcode with institutional overview, public document policy/list, and location block.
- Governance workflow with operational transitions, logs and entity upload area.
- WooCommerce + PIX payment flow foundations with annual due generation.
- Finance CRM dashboards/reports with state/area/active-inactive/revenue/history.
- Daily automation module for annual-cycle reminders and status actions.
- Centralized cross-plugin audit timeline (`rma_audit_timeline`) for governance/finance/automation events.

## Partial / Remaining for 100%

- Formalized multi-stage governance with role-based approval matrix (explicit stage actor constraints).
- Full observability panel (admin UI) for cross-plugin timeline filtering/export/retry insights.
- Deeper public profile completeness (media/gallery, richer compliance cards, public KPIs).
- Advanced analytics package (trend dashboards/charts) beyond tabular reporting.
- Gradual modularization of large theme file (`options-init.php`) into domain-focused modules/plugins.

## Suggested Delivery Order

1. Enforce role-based stage transitions in governance.
2. Add admin observability console for unified timeline.
3. Extend public profile blocks and premium visual polish.
4. Add advanced analytics views.
5. Refactor/segment domain logic for maintainability.
