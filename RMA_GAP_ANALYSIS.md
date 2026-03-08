# RMA Gap Analysis (Audit Snapshot)

This document captures the current state of the delivered package against the requested RMA organogram, based on static audit of:

- `exertio/inc/options-init.php`
- packaged plugins in `plugins.zip`
- packaged UI snippets in `ui.zip`

## Implemented (high confidence)

- Public directory with filters for state, city, name search, and adimplência status.
- Public REST route feeding directory/list/map data.
- Interactive Brazil map and entity links.
- WooCommerce + PIX payment flow foundations (settings/sync modules in plugin package).
- Initial automation and analytics modules in plugin package.

## Partial / Missing

- Public filter by **Área de Interesse**.
- Fully structured public entity page (institutional profile + public docs policy + map block).
- Complete restricted entity area (status dashboard, document pendencies, notifications center).
- Formal 3-step approval workflow with explicit transitions and audit trail.
- Complete admin reporting suite (state, area, active/inactive, annual revenue, history).
- Strong observability/audit layer for critical actions and automation retries.

## Suggested Delivery Order

1. Close public/restricted core gaps (area filter + structured entity profile + restricted dashboard).
2. Formalize governance workflow (3 approvals, permissioned transitions).
3. Expand admin reporting package.
4. Harden automation observability and compliance audit trail.

