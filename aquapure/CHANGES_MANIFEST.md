# AquaPure changed-file summary — 13 September 2026

This manifest compares the current source with the supplied AquaPure-Modernized.zip. Earlier repair changes are already part of the baseline. No baseline application file was removed.

## Significant changes

| Area | Change and reason |
| --- | --- |
| Shared CSS | Unified colors and Bootstrap variables; focus/control contrast; wrapping/mobile tables; focused auth forms; A4 printing |
| Shared JavaScript | Modular initializers; source-based table filtering; conditional fields; password/copy/image fallback; linked browser errors; preserved submit values; Back reset; dirty-cart guard |
| Shared includes | Consolidated alerts/confirmations; shell state/accessibility; bounded form recovery; print identity; local asset cache version |
| Public/auth | Focused labels/forms, native fallback, secret exclusion, image fit and station information copy |
| Customer pages | Catalog/cart/checkout clarity; stock and unsaved-quantity feedback; status/history states; profile recovery; order printing |
| Operations/Admin pages | Table captions/scopes/mobile cells; clearer filter scope and empty states; record-form recovery; delivery labels; payment and walk-in input recovery; report printing |
| PHP action changes | Clear stale presentation retry state before processing; preserve existing edit query on failed saves. SQL, business rules, transactions, and permissions remain unchanged |
| Tests/documentation | Source comparator and recorded results; CLI-only presentation test (not executed here); page register, design note, audit, verification and XAMPP checkpoint |

## Modified baseline files

45 modified files:

- `AUDIT_REPORT.md`
- `CHANGES_MANIFEST.md`
- `README.md`
- `TEST_CHECKLIST.md`
- `UI_UX_MODERNIZATION_REPORT.md`
- `actions/customer.php`
- `actions/portal.php`
- `admin/register.php`
- `assets/css/style.css`
- `assets/js/app.js`
- `customer/pages/cart.php`
- `customer/pages/dashboard.php`
- `customer/pages/order.php`
- `customer/pages/orders.php`
- `customer/pages/payment-return.php`
- `customer/pages/products.php`
- `customer/pages/profile.php`
- `includes/app-footer.php`
- `includes/bootstrap.php`
- `includes/customer-header.php`
- `includes/functions.php`
- `includes/portal-footer.php`
- `includes/portal-header.php`
- `includes/public-footer.php`
- `includes/public-header.php`
- `index.php`
- `login.php`
- `portal/pages/activity-logs.php`
- `portal/pages/customers.php`
- `portal/pages/dashboard.php`
- `portal/pages/deliveries.php`
- `portal/pages/inventory.php`
- `portal/pages/invitations.php`
- `portal/pages/notifications.php`
- `portal/pages/order-create.php`
- `portal/pages/order.php`
- `portal/pages/orders.php`
- `portal/pages/payments.php`
- `portal/pages/products.php`
- `portal/pages/reports.php`
- `portal/pages/settings.php`
- `portal/pages/transactions.php`
- `portal/pages/users.php`
- `register.php`
- `staff/register.php`

## Added files

7 added files:

- `PAGE_COVERAGE.md`
- `includes/confirmation-modal.php`
- `includes/flash-messages.php`
- `includes/presentation.php`
- `tests/presentation_test.php`
- `tests/verify_source.py`
- `verification/source-check-results.json`

## Preserved contracts

Configuration, database installer/migration, local vendor files, existing images, upload restrictions, route controllers/wrappers, RBAC, invitation and PayMongo helpers remain byte-identical. Source checks compare SQL literals, form methods/actions/encoding, named controls, and CSRF helper calls. They do not replace runtime validation.

No new business feature, external service, runtime dependency, database migration, client-generated total, pagination simulation, or export endpoint is added. Python/Node are optional developer verification tools; PHP/Apache/MySQL remain the application runtime.

All runtime and visual limitations are recorded in `TEST_CHECKLIST.md`.
