# AquaPure UI/UX audit — 13 September 2026

This update modifies the supplied AquaPure application in place. The baseline contains 102 files, including 75 PHP files, the existing database installer/migration, local Bootstrap 5.3, Bootstrap Icons, logos, and filesystem upload directories. Earlier security/database repairs in that archive are the baseline, not new work claimed by this update.

There are 28 screen templates: five public/authentication, seven customer, and 16 operations. Checkout lives in the customer cart; customer notifications live on the dashboard. The two index controllers and 29 compatibility redirects remain in place. [PAGE_COVERAGE.md](PAGE_COVERAGE.md) inventories every screen and redirect.

## Findings and implemented changes

| Baseline finding | Implemented change | Source evidence |
| --- | --- | --- |
| Inconsistent aliases, weak control edges, and low-contrast footer copy | Unified brand/Bootstrap tokens, stronger borders, focus outlines, and footer text | `assets/css/style.css` |
| Mobile tables depended on JavaScript restructuring; filtered rows could reappear | Added labels/value wrappers to 21 source table templates; CSS handles narrow layouts and `[hidden]` | Page templates and `assets/js/app.js` |
| Long delivery cells retained desktop widths on phones | Reset minimum widths in card tables; wrap addresses, actions, and statuses | Shared CSS and deliveries |
| Validation redirects lost values and left create forms collapsed | Added bounded, action/record-scoped recovery; open affected forms; preserve edit URLs on save failure | `includes/presentation.php`, record templates, action entrypoints |
| Authentication recovery could retain invitation codes/current passwords | Excluded secrets from `keep_old()`; password and invitation fields stay blank on retry | `includes/functions.php` |
| Cart quantities and checkout are separate submissions | Explain Update quantities; guard unsaved quantities; show current stock issues; retain checkout details | Customer cart and shared JS |
| Busy states risked losing named submitters or remaining stale after Back | Handle submit events; preserve submitted controls; prevent repeats; reset on `pageshow` | Shared JS |
| Conditional reference fields discarded values | Preserve values while updating visibility and required state | Shared JS |
| Local search and limited lists needed clearer scope | Label displayed-record filtering, list limits, and filtered empty states | Search, notifications, activity logs, orders/payments |
| Repeated alerts and confirmation markup differed between shells | Shared partials, persistent error summary, modal focus restoration | New flash/confirmation includes |
| Auth forms exceeded the requested focused width | 480px maximum, single-column registration, native-validation fallback, password toggles | Auth templates and shared CSS |
| Cropped/missing product images and invented fallback hours | Contain images, add failed-image fallback, show only stored hours | Product templates, JS, settings helper |
| Screen table scrolling and responsive rules affected print | Scope breakpoints to screen; add A4 rules, repeated headings, order print controls and payment summary | Shared CSS and order details |

## Preservation review

- Schema, vendor assets, configuration, RBAC/invitation/PayMongo helpers, central route controllers, and compatibility redirects are byte-identical to the baseline.
- The source comparison found 177 SQL-like string literals unchanged after whitespace normalization. Transaction logic, query parameters, business statuses, stock calculations, and server totals were not intentionally changed.
- All 28 non-modal form declarations and 127 named controls match the baseline methods/actions/encoding and names. The two footer confirmation copies were checked against their shared replacement. CSRF helper calls remain present.
- New PHP recovery stores allowlisted non-sensitive values. Hidden IDs, roles, passwords, tokens, submitted totals, and browser-supplied prices are not recovered authoritative data. Walk-in customer/product IDs are editable selections rendered against existing authorized lists.
- Images retain filesystem storage and database paths. Existing base-URL helpers still build assets and navigation.

## Verification boundary

Source checks and Node syntax validation completed. PHP, MySQL, and MariaDB are unavailable here. The browser URL policy blocks local previews. No application workflow, screenshot, print preview, rendered contrast, screen-reader result, or before/after performance measurement is claimed as passed.

Runtime/visual acceptance therefore remains open. [TEST_CHECKLIST.md](TEST_CHECKLIST.md) records the exact outstanding checks and XAMPP commands. [UI_UX_MODERNIZATION_REPORT.md](UI_UX_MODERNIZATION_REPORT.md) describes the implemented design system.
