# AquaPure interface system — 13 September 2026

The implementation keeps PHP includes, Bootstrap 5.3, Bootstrap Icons, Vanilla JavaScript, existing URLs, and MySQL workflows. All 28 screen templates consume the shared system. Runtime and visual acceptance remain pending in [TEST_CHECKLIST.md](TEST_CHECKLIST.md).

## Tokens and hierarchy

| Purpose | Value / convention |
| --- | --- |
| Primary / hover | `#075EAD` / `#064B8A` |
| Sidebar / text | `#0B263B` / `#E7EFF5` |
| Teal | `#0F766E` |
| Canvas / surface | `#F4F7FA` / white |
| Body / secondary text | `#172B3A` / `#526575` |
| Surface / control border | `#D9E2EA` / `#75899A` |
| Font | System stack; Inter only if installed; no font download |
| Spacing / radius | Shared 4px-based spacing; 6px controls through 16px surfaces |
| Layout | 256px desktop sidebar; 64px topbar; content bounded at 1560px |
| Authentication | 480px maximum; vertical fields |

Tokens at the start of `assets/css/style.css` map to Bootstrap variables. Referenced aliases remain. Documented sections cover base/typography, shells, components, tables/statuses, feedback, experience-specific patterns, responsive rules, reduced motion, and print.

Use `.page-header` with a title/purpose wrapper and `.page-header__actions`; `.section-header` inside `.panel-card`; `.metric-card` for authoritative metrics; `.form-section` for related fields; `.form-actions` for actions; `.filter-bar` for existing GET filters. `.money`/`.numeric` use tabular digits. Actions have labels; decorative icons use `aria-hidden="true"`.

## Status presentation

`badge_class()` remains the central mapping. Stored values and transitions are unchanged, and every badge retains text.

| Existing values | Presentation |
| --- | --- |
| Paid, Completed, Delivered, Available, Active, In Stock | Green |
| Pending, Unpaid, Partial, Low Stock | Amber |
| Confirmed, Preparing, Out for Delivery, Assigned | Blue |
| Ready for Pickup | Teal |
| Cancelled, Out of Stock, Failed | Red |
| Inactive, Unavailable, Refunded, unknown values | Neutral |

## Tables and data scope

Every table has a caption and scoped headers. Source cells have `data-column-label` and a `.table-cell-value` wrapper. Wide operational tables use `.mobile-table-cards` below 576px. Reports and compact item tables keep bounded horizontal scrolling. No global horizontal overflow suppression is used.

Client filters operate only on rendered rows and explicitly say “displayed.” Server search and GET parameters stay intact. No server pagination or export endpoint was present to reuse; no simulated pagination or new export totals were added. Existing query limits remain, including 250 activity logs, 100 operations notifications, and five customer notifications.

## Forms, errors, and processing

- `includes/presentation.php` retains bounded values by action and record for record edits, settings, profile, invitations, checkout, walk-in order entry, and manual payment entry. Recovery ends after the next page render. Templates still escape with `e()`.
- `includes/flash-messages.php` groups server errors into a focusable persistent summary. Existing backend messages remain summary-level strings without structured field IDs.
- Browser constraint errors have linked summaries, `aria-invalid`, and associated messages. Delivery/reference fields become required when relevant without losing values. Passwords and invitation secrets are not repopulated.
- Auth/profile forms retain native validation without JavaScript. Static navigation, form panels, and account menus remain available. Optional modal, password, copy, local-filter, and print-button enhancements require JavaScript. Cart items can still be removed by saving quantity zero.
- Submission enhancement handles Enter, preserves submit-button names/values, respects other handlers, marks busy without disabling submitted fields, and resets on Back. A future recoverable asynchronous handler can dispatch a bubbling `aquapure:submit-reset` event on its form.
- Confirmations retain protected POST actions, IDs, and CSRF tokens. The Bootstrap modal focuses the safe dismissal action, then returns focus to its trigger.
- Checkout totals remain server-generated. Update quantities is required before submitting changed quantities. The server still validates stock, permissions, totals, and payments. After an uncertain submission, inspect the existing order/payment status before retrying.

## Responsive, media, and print

The sidebar becomes Bootstrap offcanvas below 992px. Headers, filters, grids, and actions wrap. Phone controls use 16px text and approximately 44px targets as a design preference, not a measured WCAG claim. Timelines stack below 576px. There is no fixed checkout bar covering the keyboard or fields.

Product images use existing paths, intrinsic/aspect containers, `object-fit: contain`, lazy loading where appropriate, and failed-image fallback. Logos/vendor bundles stay local. Each shared footer loads one Bootstrap bundle and one app script, with cache version `v=8`.

Screen breakpoints are scoped to `screen`. A4 print rules remove navigation/actions/filters, expand table regions, repeat headings, and preserve titles, dates, items, totals, status text, and payment details. No thermal receipt format was present. Print preview is still unverified.

Implementation references: [WCAG 2.2](https://www.w3.org/TR/WCAG22/), [Bootstrap variables](https://getbootstrap.com/docs/5.3/customize/css-variables/), [Bootstrap offcanvas](https://getbootstrap.com/docs/5.3/components/offcanvas/). Full conformance is not claimed.
