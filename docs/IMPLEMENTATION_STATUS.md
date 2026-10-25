# Exabyte MVP implementation status

Last reviewed: 2026-10-25

## Verified current state

| Area | Implemented and verified | Remaining limits |
|---|---|---|
| Identity | JWT login, refresh rotation and reuse revocation; registration creates a user and owned company transactionally | No email verification, account recovery, or persistent browser session |
| Tenancy and isolation | Company membership is checked from `X-Company-ID`; catalog, inventory, POS, cash and reports are tenant-scoped; owner membership management exists; migrated legacy companies are explicitly exempted from subscription gating | No multi-branch model; user, branch and cash-register limits need corresponding operational resources/endpoints |
| Catalog | API-backed category/product creation and listing, tenant uniqueness and server validation | Editing/archive, import/export, cost, tax policy and reorder thresholds are incomplete |
| Inventory and POS | Transactional inventory movements, idempotent sale flows, server-derived price/tax totals, cash session operations, tickets, voids and returns | Cash is the only sale payment method; no purchasing/receiving, warehouse or concurrency load test |
| Reporting | Real tenant-scoped sales, inventory and cash reports | No purchasing/branch reports |
| Plans and subscriptions | Persistent plan catalog; Superadmin CRUD; immutable terms snapshot with retained `terms_history`; monthly/annual ARS pricing; pending subscription and payment attempt; owner-authorized existing-subscription plan/period updates; immediate cancellation and lifecycle transitions; centralized server-side feature gating for catalog, inventory, POS and reports; product-count limit | Proration/effective-date behavior is not sandbox-verified; no scheduled cancellation, automated expiry/grace processing, user/branch/cash-register enforcement, or recurring reconciliation |
| Mercado Pago | Official PHP SDK adapter, hosted pending-preapproval checkout, signed webhook validation, provider-event deduplication, queue job and authoritative provider lookup before activation | No sandbox credentials or end-to-end sandbox evidence; production merchant/webhook setup is external |
| Platform administration | Separate `platform_admins` authorization, CLI grant/revoke for existing accounts, plan management, subscriber/payment lists, business metrics and audit of plan changes | No operational retry/reconciliation console, manual subscription actions, full audit coverage, or initial-admin provisioning workflow beyond CLI |
| Public and account UX | Landing page, active plan comparison, registration with company and plan, monthly/annual checkout selection, subscription and payment view | Legal terms/privacy text needs legal review; no browser return status/recovery screen |
| Frontend | Vue views use the REST API; public, commerce and platform pages added; red/white/neutral palette aligned with current brand specification | No entitlement-driven navigation; main bundle still triggers Vite's >500 KB warning |
| Local seed data | `DatabaseSeeder` provisions the configured local owner, three clearly labeled illustrative plans, and idempotent demo catalog/stock/cash/sale/return records | Billing/provider events, payments, subscriptions, Superadmin, and ephemeral security/framework tables intentionally are not fabricated |
| Customers, suppliers and purchasing | Not implemented | No modules, tables, endpoints or user views |
| Operations and assurance | Environment templates and code-level tests exist | Production HTTPS, secrets, queue, monitoring, backups/restore, incident response, legal approval and live provider configuration are not verified |

## Prioritized remaining commercial blockers

1. **Complete subscription limits:** enforce membership/user, branch and cash-register caps as those creation resources are introduced; add Superadmin exception management. Billing and recovery routes must remain available.
2. **Recurring payment lifecycle:** verify actual SDK request/response behavior in sandbox, process pending/rejected/out-of-order/renewal notifications, and add scheduled reconciliation and expiry/grace transitions.
3. **Checkout recovery and contract changes:** complete browser return-state UX and confirm provider timing/proration for the implemented plan/period update flow. Initialization retry preserves one pending contract and records a new attempt after a failed checkout request. Current cancellation is immediate.
4. **Operational Superadmin:** add event inspection, safe retry/reconciliation actions, company/subscriber filters, and audit sensitive actions.
5. **Commercial operations:** implement customers, suppliers, purchasing/receiving, alternate POS payment methods, and branch/cash-register modeling.
6. **Beta preparation:** legal terms/privacy, account recovery, installation/support runbooks, backup restoration, monitoring, HTTPS, production configuration and acceptance by a real merchant account.

## Commercial and security decisions currently encoded

- A plan is an offer; a subscription is the company's contract; the billing user is recorded separately from company membership; Superadmin is stored separately from commerce roles.
- Plan contract edits increment `terms_version`. The subscription captures price, currency, period, benefits, limits and entitlements in `terms_snapshot`; existing contracts are not silently repriced.
- New checkout prices and currency are derived from the server-side active plan. Only ARS is accepted.
- An approved browser return is never treated as payment confirmation. Webhooks are signature-validated, persisted idempotently and queued. Activation requires an authoritative provider lookup of the authorized payment and a matching amount/currency.
- Retrying an initial checkout reuses the pending contract/reference. If checkout initialization previously failed, the retry creates a new pending attempt instead of rewriting the failed attempt.
- Webhook storage is intentionally limited to notification metadata; card data and access tokens are not persisted.
- Current cancellation is immediate and asks the provider to cancel the preapproval. Historical attempts and sales are retained.
- `trial_days` is restricted to zero because the implemented pending-preapproval flow has not been verified for trials.
- Local plan seed amounts are illustrative and visibly labeled as development examples; they are not a real commercial offer. Production plans must be configured and approved by Superadmin.
- The database seeder is blocked outside `local` and `testing`. Demo seeds never invent provider payments, subscriptions, webhook events, or Superadmin permissions.
- The public catalog excludes inactive plans; historical subscriptions retain their plan reference and terms snapshot.
- `SUBSCRIPTION_ACCESS_ENFORCEMENT` defaults to enabled. New companies are gated until they have an active, in-period contract or a supported trial/grace window. Catalog, inventory, POS and report entitlements are enforced; product count is capped transactionally.
- Existing companies present when the exemption migration runs receive an explicit non-expiring `subscription_access_exemptions` record to avoid silently locking out existing businesses. This is a compatibility policy, not a paid entitlement; review and migrate those companies commercially before removing exemptions.
- User, branch and cash-register limits are not yet enforced because membership invitation and branch/register resources are not implemented. Do not claim all plan limits are active.

## Mercado Pago integration references and external prerequisites

The implementation targets Mercado Pago's recurring `preapproval` checkout for Argentina, using the official PHP SDK. A merchant account, sandbox access token, public key as applicable, webhook signing secret, accessible HTTPS notification URL and sandbox evidence are unavailable in this environment. Do not run production charges in automated tests.

Official sources reviewed:

- [Recurring subscriptions overview](https://www.mercadopago.com.ar/developers/es/docs/subscriptions/overview)
- [Create a subscription without an associated plan](https://www.mercadopago.com.ar/developers/es/docs/subscriptions/integration-configuration/subscription-no-associated-plan/pending-payments)
- [Create preapproval API](https://www.mercadopago.com.ar/developers/es/reference/online-payments/subscriptions/create-preapproval/post)
- [Webhook notifications and signature verification](https://www.mercadopago.com.ar/developers/es/docs/your-integrations/notifications/webhooks)
- [Official PHP SDK](https://github.com/mercadopago/sdk-php)

## Work completed and evidence

| Work | Status | Evidence |
|---|---|---|
| Inspect existing Laravel/Vue implementation and record module gaps | Complete | Existing module and test inspection; this document |
| Add plan, subscription, payment-attempt, provider-event and platform-audit persistence | Implemented | Billing migrations and Eloquent models |
| Add public plans, protected plan administration, registration and company subscription endpoints | Implemented | `PlanApiTest`, `RegistrationApiTest`, `SubscriptionFlowApiTest` |
| Add Mercado Pago SDK adapter, signature validation, event deduplication and payment-confirmed activation | Implemented in code; provider behavior not sandbox-verified | Focused billing tests with controlled fixtures/mocks |
| Add public/account billing pages and Superadmin plan and reporting views | Implemented against API | Frontend type-check/build and Vitest suite |
| Add platform subscriber/payment queries and metrics | Implemented | `PlatformBillingApiTest`; Superadmin denial and aggregate assertions |
| Change an existing subscription's plan or billing period | Implemented in code; provider behavior not sandbox-verified | Owner-only `PUT /api/v1/companies/{company}/subscription`; server-derived active-plan terms; prior terms appended to `terms_history`; payment attempts retained. Provider success followed by local commit failure can leave provider and database out of sync; reconciliation/compensation is required before production |
| Fix plan catalog empty state, public/registration styling and signup navigation | Implemented | Missing local plan records were the reason the API returned an empty catalog; public/auth stylesheet and post-registration guards corrected |
| Add safe, idempotent local plan and commerce seeders | Implemented | `PlanCatalogSeederTest`, `DemoCommerceSeederTest`; local API returns three illustrative plans |
| Apply central entitlements and product limit to commerce routes | Implemented; user/branch/cash-register limits remain | `RequireSubscriptionEntitlement`, `SubscriptionAccess`, `SubscriptionAccessApiTest`; enforcement can be configured through `SUBSCRIPTION_ACCESS_ENFORCEMENT` |
| Full production/sandbox readiness | Not verified | Requires merchant credentials, HTTPS/webhook access, operational configuration and external acceptance |

## Verification log

- Original baseline: backend 67 tests / 366 assertions; frontend 25 tests.
- Full backend regression suite: **88 tests / 521 assertions passed** (`$env:APP_ENV='testing'; $env:APP_DEBUG='false'; Set-Location backend; php artisan test`).
- Focused billing API suite: **9 tests / 69 assertions passed** (`$env:APP_ENV='testing'; $env:APP_DEBUG='false'; Set-Location backend; php artisan test tests/Feature/Billing/SubscriptionFlowApiTest.php`); includes mocked provider success/failure, owner authorization, unavailable/invalid choices and pending checkout retry.
- Frontend suite: **11 test files / 32 tests passed** (`Set-Location frontend; npm test`), including plan/period selection and success/error handling on both subscription pages.
- Frontend type-check and production build passed using `npm run build`; the tracked `frontend/dist` output was rebuilt from the current frontend sources. Vite reports the existing >500 KB bundle warning.
- PHP syntax checks passed for all nine changed PHP files. Pint could not run: `vendor/bin/pint --test ...` reports PHP 8.2.26 while the locked Pint runtime requirement is PHP 8.3.
- Seeder-focused tests: **2 tests / 22 assertions passed**; Composer manifest validation passed.
- `composer validate --no-check-publish`: passed after adjusting the Mercado Pago SDK constraint to a semver-compatible range.
- OpenAPI YAML parsing and internal `$ref` validation: passed.
- PHP in this environment is 8.2.26. The locked Pint version requires PHP 8.3, so Pint cannot run here. The Mercado Pago SDK dependency was installed while bypassing that platform check; verify dependencies in the project's supported PHP 8.3 CI/runtime.
- Provider interactions were mocked in tests. No sandbox credentials or live payment capability were used; no real charge was attempted.

## Next exact implementation steps

1. Add enforcement for user/member limits with invitations, and branch/cash-register limits when those resources are introduced; provide audited Superadmin management of legacy exemptions.
2. Add tests for each lifecycle transition, invalid/duplicate/out-of-order webhook, failed renewal, cancellation, expiry and reconciliation.
3. **Next concrete step:** using an authorized Mercado Pago sandbox account, verify `PreApprovalClient::update` request/response and when the changed amount/frequency takes effect; record proration and effective-date behavior before enabling this change in a production release.
4. Add queued/scheduled reconciliation and expiry/grace processing; add audited Superadmin retry/recovery controls.
5. Extend OpenAPI, data/class diagrams and operating instructions as modules evolve.
6. Implement customer/supplier/purchasing and non-cash payment requirements, then run the complete backend/frontend suites.
7. Complete legal/operational readiness and record sandbox, backup-restore, HTTPS and monitoring evidence before inviting paying customers.
