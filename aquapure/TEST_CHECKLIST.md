# AquaPure verification and checkpoint — 13 September 2026

**Implementation and source checks completed; runtime acceptance is blocked.** This report supersedes test claims in the previous archive. A source check does not prove a rendered page or database workflow works.

## Recorded checks

| Check | Result | Evidence / scope |
| --- | --- | --- |
| JavaScript syntax | PASS | `node --check assets/js/app.js` |
| Baseline comparison | PASS | `tests/verify_source.py`; `verification/source-check-results.json` |
| Database/config/vendor/protected routes | PASS, source only | 52 selected baseline files are byte-identical, including route controllers, compatibility routes, RBAC, invitations, PayMongo, schema, upload restrictions |
| SQL literals | PASS, source only | 177 SQL-like string literals match after whitespace normalization; no query execution |
| Form contracts | PASS, source only | 28 form declarations, 127 named controls, 25 CSRF helper calls; both modal consumers checked separately |
| Tables | PASS, source only | 21 source templates with captions and scoped headers |
| PHP delimiters/strings | PASS, lexical only | 78 application files scanned; not PHP syntax validation |
| CSS | PASS, source only | Balanced braces, resolved AquaPure tokens, no global horizontal overflow suppression |
| Whitespace | PASS | `git diff --check` |
| PHP lint | BLOCKED | PHP executable unavailable |
| Form-recovery behavior test | BLOCKED | `tests/presentation_test.php` added but not executed; needs PHP |
| Apache/MySQL/MariaDB workflows | BLOCKED | No PHP/database service available |
| Browser UI/screenshots | BLOCKED | Browser URL policy rejected local previews; no workaround used |
| Keyboard, screen reader, print, performance | NOT RUN | Need a running app and allowed browser |

Baseline ZIP SHA-256: `6bb2dedb97134d0a759ebb7700ad279934091849f94ad7d5a673d1f445ad6075`.

The checker uses a limited PHP lexical scanner and HTML source parser. It does not evaluate PHP branches, render dynamic markup, execute JavaScript, or prove authorization.

## Token contrast

Calculated from configured solid colors, not rendered screens. Actual focus/hover/disabled/overlay combinations still need browser review.

| Combination | Ratio |
| --- | ---: |
| Body / white | 14.55:1 |
| Secondary / white | 6.04:1 |
| White / primary | 6.53:1 |
| White / primary hover | 8.82:1 |
| Control edge / white | 3.62:1 |
| Control edge / canvas | 3.37:1 |
| Sidebar text / sidebar | 13.33:1 |
| Success badge | 6.49:1 |
| Warning / Partial badge | 6.37:1 |
| Danger badge | 5.30:1 |
| Progress badge | 6.59:1 |
| Ready for Pickup badge | 6.48:1 |
| Neutral badge | 5.36:1 |
| Muted footer / footer | 9.62:1 |

## Runtime regression matrix

Every row remains **NOT RUN / environment blocked**. Use controlled records in a test database and record expected/actual results and relevant PHP/Apache logs. Unchanged authorization files are not a passed authorization test.

| Area | Required local checks |
| --- | --- |
| Auth/sessions | Customer, Staff, Admin login; bad password; logout; inactive account; expired session; role navigation; redirects |
| Direct access | Customer blocked from operations; Staff blocked from Admin-only pages/wrappers; customer ownership on read/change of orders |
| Invitations | Generate both roles; copy code/link; valid registration; malformed/expired/revoked/reused/wrong-email rejection; no privileged role selection |
| Customer ordering | Available/unavailable/missing-image products; add/remove/update; zero quantity; changed stock; unsaved quantity guard; empty cart; pickup/delivery; supported payment methods; totals/history |
| Walk-in | One/four rows; unused rows; duplicate product choices; stock limits; customer selection; cash change; references; retained invalid input; one intentional order |
| Order transitions | Valid next stages; rejected skips/backtracking; cancellation; final-state restrictions; pickup/delivery timelines |
| Payments | Cash exact/under/over payment; GCash/Maya references; due/paid/change; blocked manual PayMongo edit; provider success/pending/failure in configured test mode |
| Products/inventory | Admin create/edit/upload; missing image; Staff restrictions and quantity-only updates; zero/low stock; audit history retained |
| Deliveries | Staff assignment and valid next status; rejected transitions; final states; long recipient/address/contact data |
| Data views | GET filters/search; clear/filtered-empty; local search scope; list limits; displayed totals compared with database |
| Administration | User edit/toggle; last active Admin; invitation revoke; settings; activity logs and access |
| Forms/interactions | Linked required-field summary; server error; values retained; file reselection; blank secrets; Enter/double click; cancelled confirmation; Back; recoverable reset |
| Print | A4 reports and both order details; identity/date/items/totals/payment; long multipage reports; statuses without backgrounds |

## Viewports and accessibility

| Coverage | Widths / scenarios | Result |
| --- | --- | --- |
| Every screen in PAGE_COVERAGE.md | 390px and 1366px | NOT RUN |
| Shared shells, table/form, cart/order/report | 320, 375, 390, 430, 576, 768, 992, 1200, 1366, 1440px | NOT RUN |
| Reflow/zoom | 1280px at 400% zoom; 320 CSS px equivalent | NOT RUN |
| Other constraints | Short laptop height; mobile landscape/keyboard; long names/addresses/currency; many rows | NOT RUN |
| Keyboard | Skip link, navigation, dropdowns, offcanvas, dialogs, focus, table scrolling, Enter | NOT RUN |
| Assistive technology | Landmarks, headings, mobile table semantics, names/errors/announcements | NOT RUN |
| Motion/fallback | Reduced motion; JavaScript disabled; clipboard unavailable; broken image | NOT RUN |

No before/after screenshots are included because the application could not render here. Scratch fixtures are excluded and were not application evidence. No performance score or WCAG conformance claim is made.

## Exact XAMPP checkpoint

1. Follow [README.md](README.md), preserving the existing database, uploads, and environment settings. This UI update adds no migration.
2. Run PHP lint and the focused test in PowerShell. These commands were not executed here:

   ```powershell
   Set-Location C:\xampp\htdocs\aquapure
   $aquaPhp = 'C:\xampp\php\php.exe'
   $aquaLintFailed = $false
   Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
       & $aquaPhp -l $_.FullName
       if ($LASTEXITCODE -ne 0) { $aquaLintFailed = $true }
   }
   if ($aquaLintFailed) { throw 'Fix the reported PHP lint failures before continuing.' }
   & $aquaPhp .\tests\presentation_test.php
   if ($LASTEXITCODE -ne 0) { throw 'Presentation checks failed.' }
   ```

3. Start Apache/MySQL; open `http://localhost/aquapure/`. Check asset requests, console, and PHP/Apache error logs from this actual subdirectory.
4. Run the full regression matrix and every page/viewport entry. Check shared patterns first on an operations list, an edit form, and checkout.
5. Capture controlled-data screenshots of public/login, operations dashboard/orders/edit/delivery/report, customer catalog/cart/order, and Admin invitations. Review all other pages at phone/laptop widths too.
6. Record outcomes; fix shared defects; revisit affected pages; mark runtime acceptance complete only after passing.

Optional source recheck, requiring Python 3, Node.js, and the original ZIP kept separately:

```text
python tests/verify_source.py --baseline PATH_TO_ORIGINAL_ZIP --report verification/source-check-results.json
```

Remaining implementation limitation: backend validation errors are summary-level strings. Browser constraint errors have field associations; a future server field mapping should preserve existing validation rules.
