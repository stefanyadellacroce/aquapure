# AquaPure page coverage — 13 September 2026

All **28 existing screen templates** are implemented and source-reviewed. **S** below means source coverage complete; PHP/database execution and 390px/1366px visual checks are still blocked for every row. Runtime acceptance is not complete. Detailed check cases and the full breakpoint set are in [TEST_CHECKLIST.md](TEST_CHECKLIST.md).

## Public and authentication

| Actual path | Role | Implemented components | States / actions covered in source | Status |
| --- | --- | --- | --- | --- |
| `index.php` | Visitor / signed-in | Public shell, hero, service/catalog sections, stored station information | Empty catalog, missing image, available product, sign-in state | S; runtime blocked |
| `login.php` | All roles | Focused auth card, labels, password toggle, summary | Invalid credentials, required values, retained identifier, blank password | S; runtime blocked |
| `register.php` | Visitor → Customer | Single-column auth form, safe existing recovery | Duplicate/invalid details, confirmation, blank passwords | S; runtime blocked |
| `staff/register.php` | Staff invitation recipient | Single-column invitation registration | Invalid/expired/used code, retained ordinary fields, blank secrets | S; runtime blocked |
| `admin/register.php` | Admin invitation recipient | Same focused invitation pattern | Invalid/expired/used code, no privileged role selector | S; runtime blocked |

## Customer

All customer screens are routed through `customer/index.php?page=KEY`, with `&id=ORDER_ID` on order/payment-return. The controller retains `require_customer()` before actions and rendering.

| Key / actual template | Implemented components | States / actions covered in source | Status |
| --- | --- | --- | --- |
| `dashboard` — `customer/pages/dashboard.php` | Active-order panel, timeline, recent-order table, notifications | No active order, five recent orders, five unread-first notices, mark read | S; runtime blocked |
| `products` — `customer/pages/products.php` | Product cards, categories, availability, lazy images | Empty/filtered catalog, unavailable/out-of-stock, missing image, add to cart | S; runtime blocked |
| `cart` — `customer/pages/cart.php` | Item table, fulfillment/payment sections, totals, error recovery | Empty cart, stock issues, quantity update/remove, dirty guard, pickup/delivery, payment reference, blocked checkout | S; runtime blocked |
| `orders` — `customer/pages/orders.php` | Status tabs and mobile history table | All/filtered-empty records, stored payment and fulfillment states | S; runtime blocked |
| `order` — `customer/pages/order.php` | Status badges, timeline, items, detail list, confirmation, print | Missing/foreign order, cancellation, final states, PayMongo continuation, pickup/delivery | S; runtime blocked |
| `payment-return` — `customer/pages/payment-return.php` | Payment feedback panel and return action | Missing/foreign order, provider confirmed/pending/unavailable/error | S; runtime blocked |
| `profile` — `customer/pages/profile.php` | Personal-information/security sections and recovery | Invalid details, password confirmation/current password, saved profile | S; runtime blocked |

Checkout is part of `cart.php`; customer notifications are part of `dashboard.php`. No disconnected checkout or notification route was introduced.

## Operations and administration

Routes use `portal/index.php?page=KEY`; order details add `&id=ORDER_ID`, and existing record edits use `&edit=RECORD_ID`. `require_system_user()` and Admin page/action guards are preserved.

| Key / actual template | Role | Implemented components | States / actions covered in source | Status |
| --- | --- | --- | --- | --- |
| `dashboard` — `portal/pages/dashboard.php` | Staff/Admin | Metrics, attention panels, recent orders, stock/activity | Existing date/query scope, empty queues, low stock | S; runtime blocked |
| `customers` — `portal/pages/customers.php` | Staff/Admin | List, local filter, create/edit form, confirmation | New/edit/retry, empty/filtered-empty, Admin activation; Staff permissions unchanged | S; runtime blocked |
| `products` — `portal/pages/products.php` | Staff/Admin; edits Admin | List, image fallback, Admin form | New/edit/retry/upload reselection, availability/quantity, local-filter empty | S; runtime blocked |
| `orders` — `portal/pages/orders.php` | Staff/Admin | GET filter bar, status badges, mobile table | Existing date/type/order/payment/q filters, clear, all/filtered-empty | S; runtime blocked |
| `order-create` — `portal/pages/order-create.php` | Staff/Admin | Four-row walk-in form and recovery | No customers/products, selected rows/quantities, payment reference, invalid input | S; runtime blocked |
| `order` — `portal/pages/order.php` | Staff/Admin | Progress/items, next action, payment form, print | Missing record, next/cancel/final status, manual-payment retry, PayMongo restrictions | S; runtime blocked |
| `deliveries` — `portal/pages/deliveries.php` | Staff/Admin | Mobile delivery queue, assignment/status labels | Empty, valid next status, final state, long address, assignment | S; runtime blocked |
| `inventory` — `portal/pages/inventory.php` | Staff/Admin; metadata Admin | List, forms, local filter, stock badges | Admin create/edit; Staff quantity-only; zero/low stock; history-protected delete | S; runtime blocked |
| `payments` — `portal/pages/payments.php` | Staff/Admin | Payment metrics/tabs/table | Selected-status totals, all/filtered-empty, payment details link | S; runtime blocked |
| `transactions` — `portal/pages/transactions.php` | Staff/Admin | Date filters, local filter, mobile table | Selected-range records, filtered/no-match; existing stock audit semantics | S; runtime blocked |
| `reports` — `portal/pages/reports.php` | Staff/Admin; extra panels Admin | Date range, summaries, tables, A4 print | Empty data, reversed/custom dates, existing aggregate definitions, role panels | S; runtime blocked |
| `notifications` — `portal/pages/notifications.php` | Staff/Admin | Notice feed, read state/actions | Up to 100 displayed, empty, unread/read, mark all/individual read | S; runtime blocked |
| `users` — `portal/pages/users.php` | Admin | User table, edit form, status confirmation | Edit/retry, immutable role, activation, last active Admin guard | S; runtime blocked |
| `invitations` — `portal/pages/invitations.php` | Admin | Create form, one-time copy panel, local-filter history | Email/expiry retry, Active/Used/Expired/Revoked, copy fallback, revoke | S; runtime blocked |
| `activity-logs` — `portal/pages/activity-logs.php` | Admin | GET search and mobile log table | Latest 250 matching entries, empty/filtered-empty, clear | S; runtime blocked |
| `settings` — `portal/pages/settings.php` | Admin | Station fields, focused form and recovery | Stored values, validation retry, no invented hours | S; runtime blocked |

## Compatibility and session routes

These files are preserved byte-for-byte. They resolve to the modernized screens; they are not additional independent page templates. Customer destination authorization remains in the central customer controller.

| Actual path | Existing destination / access | Status |
| --- | --- | --- |
| `admin/activity-logs.php` | `portal/index.php?page=activity-logs`; Admin wrapper | Preserved; runtime blocked |
| `admin/customers.php` | `portal/index.php?page=customers`; Admin wrapper | Preserved; runtime blocked |
| `admin/dashboard.php` | `portal/index.php?page=dashboard`; Admin wrapper | Preserved; runtime blocked |
| `admin/deliveries.php` | `portal/index.php?page=deliveries`; Admin wrapper | Preserved; runtime blocked |
| `admin/inventory.php` | `portal/index.php?page=inventory`; Admin wrapper | Preserved; runtime blocked |
| `admin/invitations.php` | `portal/index.php?page=invitations`; Admin wrapper | Preserved; runtime blocked |
| `admin/orders.php` | `portal/index.php?page=orders`; Admin wrapper | Preserved; runtime blocked |
| `admin/payments.php` | `portal/index.php?page=payments`; Admin wrapper | Preserved; runtime blocked |
| `admin/products.php` | `portal/index.php?page=products`; Admin wrapper | Preserved; runtime blocked |
| `admin/reports.php` | `portal/index.php?page=reports`; Admin wrapper | Preserved; runtime blocked |
| `admin/settings.php` | `portal/index.php?page=settings`; Admin wrapper | Preserved; runtime blocked |
| `admin/transactions.php` | `portal/index.php?page=transactions`; Admin wrapper | Preserved; runtime blocked |
| `admin/users.php` | `portal/index.php?page=users`; Admin wrapper | Preserved; runtime blocked |
| `staff/customers.php` | `portal/index.php?page=customers`; Staff/Admin wrapper | Preserved; runtime blocked |
| `staff/dashboard.php` | `portal/index.php?page=dashboard`; Staff/Admin wrapper | Preserved; runtime blocked |
| `staff/deliveries.php` | `portal/index.php?page=deliveries`; Staff/Admin wrapper | Preserved; runtime blocked |
| `staff/inventory.php` | `portal/index.php?page=inventory`; Staff/Admin wrapper | Preserved; runtime blocked |
| `staff/orders.php` | `portal/index.php?page=orders`; Staff/Admin wrapper | Preserved; runtime blocked |
| `staff/payments.php` | `portal/index.php?page=payments`; Staff/Admin wrapper | Preserved; runtime blocked |
| `staff/products.php` | `portal/index.php?page=products`; Staff/Admin wrapper | Preserved; runtime blocked |
| `staff/reports.php` | `portal/index.php?page=reports`; Staff/Admin wrapper | Preserved; runtime blocked |
| `staff/transactions.php` | `portal/index.php?page=transactions`; Staff/Admin wrapper | Preserved; runtime blocked |
| `customer/dashboard.php` | `customer/index.php?page=dashboard`; Customer destination guard | Preserved; runtime blocked |
| `customer/my-orders.php` | `customer/index.php?page=orders`; Customer destination guard | Preserved; runtime blocked |
| `customer/order-details.php` | `customer/index.php?page=order + existing id query when present`; Customer destination guard | Preserved; runtime blocked |
| `customer/order.php` | `customer/index.php?page=cart`; Customer destination guard | Preserved; runtime blocked |
| `customer/products.php` | `customer/index.php?page=products`; Customer destination guard | Preserved; runtime blocked |
| `customer/profile.php` | `customer/index.php?page=profile`; Customer destination guard | Preserved; runtime blocked |
| `products.php` | `index.php#products`; Public catalog redirect | Preserved; runtime blocked |
| `logout.php` | `login.php`; Existing session-ending route (GET), then login; no separate logout screen | Preserved; runtime blocked |

## Shared source and infrastructure

| Files | Responsibility / coverage |
| --- | --- |
| `portal/index.php`, `customer/index.php` | Preserved route allowlists, access checks, action-before-render order |
| `includes/public-header.php`, `includes/public-footer.php` | All five public/auth screens |
| `includes/customer-header.php`, `includes/app-footer.php` | All seven customer screens |
| `includes/portal-header.php`, `includes/portal-footer.php` | All 16 operations screens; role-aware navigation |
| `includes/flash-messages.php`, `includes/confirmation-modal.php`, `includes/presentation.php` | Shared errors, confirmations, bounded form recovery |
| `includes/functions.php`, `includes/bootstrap.php` | Existing utilities plus presentation integration; existing safe error response |
| `includes/rbac.php`, `includes/invitations.php`, `includes/paymongo.php` | Preserved authorization, invitation, provider helpers |
| `actions/portal.php`, `actions/customer.php` | Existing business actions; presentation retry reset/redirect changes only |
| `config/app.php`, `config/database.php` | Preserved base path, connection, timezone, schema guard |
| `assets/css/style.css`, `assets/js/app.js` | Every shell/page; responsive/print and optional interactions |
| `database/`, `assets/vendor/`, `assets/images/`, upload restrictions | Preserved schema/migration, local dependencies, logos, filesystem storage |
| `tests/presentation_test.php` | Added CLI-only focused behavior test; execution blocked |
| `tests/verify_source.py` | Added optional source comparator; executed successfully |
