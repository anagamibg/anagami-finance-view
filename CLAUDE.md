# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**AFV** (Anagami Finance View) — a platform for managing, processing and analysing the financial data of micro, small and medium-sized companies, built on a CodeCanyon base by Froiden (the upstream product is "Anagami Finance View"; updates follow the section below). Laravel 12 / PHP 8.2+ with the base project-management, HR, CRM and invoicing modules. Upgraded from 5.4.6 (Laravel 10) to 6.0.10 (Laravel 12) on 2026-07-01 and to 6.0.19 on 2026-10-28 (3-way merge, see *Updating the app*). Server-rendered Blade + Bootstrap 4 + jQuery (no Vue/React SPA). The same codebase runs in two modes selected by `APP_NAME`:

- `worksuite-saas` → multi-tenant SaaS with a **SuperAdmin** panel that manages tenant companies, packages, and billing. `isWorksuiteSaas()` returns true.
- `worksuite` → single-company self-hosted. `isWorksuite()` returns true; SuperAdmin routes are not loaded.

Guard mode-specific code with `isWorksuiteSaas()` / `isWorksuite()` (defined in `app/Helper/start.php`). SuperAdmin routes/providers only load in SaaS mode (see `RouteServiceProvider::boot`).

## Critical constraints

- **Never change `APP_ENV` from `codecanyon`.** The app reads mail, payment, and other config from the database keyed on this env; other values break the admin panel and emailing. This is called out in `.env.example`.
- **Multi-tenant queries are auto-scoped.** Any model using the `HasCompany` trait gets a global `CompanyScope` (`app/Scopes/CompanyScope.php`) that appends `where company_id = company()->id` to every query while a user is logged in. In **migrations, seeders, and cross-tenant/console code** that touch these tables you must add `->withoutGlobalScope(App\Scopes\CompanyScope::class)`, or updates fail / leak across tenants. There is also an `ActiveScope` (soft filter for active rows) applied similarly.
- **Upstream app updates overwrite local customizations.** Before merging a new upstream Anagami Finance View version — or acting on any "update the app" request — follow [docs/APP-UPDATE-AND-CUSTOMIZATIONS.md](docs/APP-UPDATE-AND-CUSTOMIZATIONS.md) and hand-merge the files it flags. See *Updating the app* below.
- **Anagami Finance View license check is off** (`config/databackd.php` `license_check`, env `DATABACKD_LICENSE_CHECK`, default `false`): `isLegal()` is skipped on the login and front pages while the site is not public; don't enter a purchase code/license or re-enable it unless asked (order when enabling: verify the code as super admin at `/verify-purchase`, then remove `verify-purchase`/`purchase-verified`/`hide-review-modal/*` from `config/security-headers.php` `super_admin_only`, then set the env var — see docs/APP-UPDATE-AND-CUSTOMIZATIONS.md 4.10). Upstream updates are merged by hand, never through the license-gated auto-updater.
- **Never credit Claude or any AI as author/contributor.** Do not add `Co-Authored-By: Claude …` (or any AI-assistant) trailers to commits, and never list Claude as a contributor, author, or owner in commit messages, PR/MR descriptions, code comments, or `AUTHORS`/`CONTRIBUTORS`/metadata files. Commits are authored solely by the human developer.

## Commands

```bash
# Install
composer install
npm install

# Frontend assets (Laravel Mix / webpack — see webpack.mix.js)
npm run dev            # one-off dev build
npm run watch          # rebuild on change
npm run prod           # minified production build

# Run
php artisan serve
php artisan migrate
php artisan db:seed

# Tests
php artisan test                      # all
php artisan test --filter=SomeTest    # single test/class
```

Note: as of the 6.0.10 upgrade, `phpunit/phpunit`, `larastan/larastan`, and `laravel/pint` are no longer in `composer.json`'s require-dev (upstream dropped them) — `vendor/bin/{phpunit,phpstan,pint}` may still exist as stale leftovers from an older build, but don't rely on them; a fresh `composer install` won't provision them. `phpstan.neon.dist` and `.styleci.yml` are similarly orphaned config with no backing tool. Re-add these as dev dependencies if you want them back.

```bash
# IDE helpers (regenerate after model/schema changes)
php artisan ide-helper:generate && php artisan ide-helper:meta

# Modules (nwidart/laravel-modules)
php artisan module:list
php artisan module:migrate <Module>
```

Note: `tests/` currently holds only the framework example tests — coverage is minimal; most verification is manual.

## Updating the app (upstream Anagami Finance View merges)

When asked to **update the app** / merge a new upstream Anagami Finance View version, **read [docs/APP-UPDATE-AND-CUSTOMIZATIONS.md](docs/APP-UPDATE-AND-CUSTOMIZATIONS.md) first** (written in Bulgarian) — it is the authoritative checklist of local customizations upstream will overwrite. Never blind-overwrite `app/` or `resources/`; hand-merge every file it flags.

Merge flow (since 6.0.19): the branch `vendor/worksuite` holds the pristine upstream releases (`37235f8` = 6.0.10, `ae7d718` = 6.0.19). For a new release: worktree on `vendor/worksuite` → replace the tree with the new release (without `vendor/`, `node_modules/`, IDE artefacts) → commit `vendor: Worksuite X.Y clean baseline` → branch `upstream/worksuite-vX.Y` off `main` → `git merge vendor/worksuite` (real 3-way merge; git flags only the files both sides changed) → resolve conflicts and **also review the auto-merged files we changed** (an upstream removal can silently drop a customization, e.g. `super-admin` in `config/log-viewer.php`) → `composer dump-autoload && npm run prod` → smoke-test dashboard, a DataTable list, a form, a detail/show, PDF, and super-admin billing. Report of the 6.0.19 merge: `docs/mvp/upgrade-6.0.19/`.

Customizations that must survive a merge (full file lists live in the doc):
- **Billing package limits** (CUS_000329): `checkCompanyCanAddMoreClients()` / `checkCompanyCanAddMoreInvoices()` in `app/Helper/start.php`, the `CheckCompanyPackage` middleware, `Invoice`/`Client` + `SuperAdmin/BillingController` controllers, plus related observers, DataTables, and package/billing views.
- **Dual-language PDF** (CUS_000328): `@if ($invoiceSetting->locale != 'bg')` guard in 34 PDF templates (`grep -rl "locale != 'bg'" resources/views`; known bug — currently renders BG twice instead of EN + BG). Since S7 most of them also carry `__()` replacements — keep both on merge.
- **Custom code injection — not in git.** Earlier docs claimed `custom_css` / `custom_js` / `custom_head_html` settings injected in `resources/views/layouts/app.blade.php`; no such code, controllers, views, routes or `config/xss_ignore.php` entries exist anywhere in git history (verified 2026-10-28). The only `custom_css` is upstream's `$frontDetail->custom_css` in `resources/views/super-admin/layouts/front-app.blade.php` (and `custom_css_theme_two` in `saas-app.blade.php`). Compare the server copy of `layouts/app.blade.php` before overwriting it. Rule still applies: any new raw-HTML/JS field must be whitelisted in `config/xss_ignore.php`, or it gets silently stripped on save.
- **Baked-in bug fixes — not in git.** The four previously listed fixes (`Expense::$with` with `date_format`, `FinanceReportController` `$projectNames` pre-init, `resources/js/bootstrap.js` Echo/Pusher guard, `layouts/app.blade.php` preloader `try/catch` + timeout) are absent from the code and its history (verified 2026-10-28); re-implement them if needed — see doc §4.3.
- **Modules** (`Modules/`): only `LanguagePack` is installed (production verified 2026-07-01; `storage/app/modules_statuses.json` = `{"LanguagePack": true}`). `Asset` (2.1.22) and `Payroll` (2.1.8) are deliberately **not** installed. If Payroll is ever installed, its `Routes/api.php` must stay stubbed (original kept as `api.php.disabled`) — see doc §5.
- **Translations:** `resources/lang/eng/` and `resources/lang/bg/` (main UI language, source of truth for Bulgarian wording; terminology table in `docs/mvp/features/07-translation.md`) are in git; each module's `eng/` too. `de/`, `ru/` and the other languages, module `bg/` dirs and demo data live only on the server — never merge upstream lang files over `bg/` (hand-merge new upstream keys into it).

CUS_000327 (invoice series) and CUS_000326 (custom fields on finance docs) are unverified / inactive as of 2026-07-01 — re-check the code before assuming either exists.

## Architecture

### Multi-tenancy (single database)
- `Company` is the tenant. Tenant-owned tables carry a `company_id`.
- Models opt in via the `HasCompany` trait (`app/Traits/HasCompany.php`), which registers `CompanyScope` and the `company()` relation.
- `company()` (global helper) resolves the current tenant from the session/user and caches it in the session. `user()` resolves the current app user (distinct from the auth record — see below).

### Models
- Most models extend `App\Models\BaseModel`, which extends `Froiden\RestAPI\ApiModel` (from the `froiden/laravel-rest-api` package). This is a custom REST layer, **not** plain Eloquent — models expose API field/relation whitelists and integrate with `ApiRoute`.
- `app/Models/` is large (~240 models). SaaS/tenant-admin models live under `app/Models/SuperAdmin/`.

### Controllers & responses
- Authenticated app controllers extend `App\Http\Controllers\AccountBaseController`, whose constructor middleware boots per-request context: current `user()`, tenant `company`, enabled `user_modules()`, theme, permissions, notification counts, and (in SaaS) SuperAdmin context. Read this class before adding a controller — most shared view data comes from here.
- AJAX/JSON responses use the `App\Helper\Reply` helper (`Reply::success`, `::error`, `::dataOnly`, `::redirect`, `::formErrors`) — return these arrays rather than hand-building JSON. The frontend `froiden-helper` JS expects this `{status, message, ...}` shape.
- Validation uses FormRequest classes under `app/Http/Requests/`, namespaced by feature (e.g. `Requests/Project/StoreProject`).
- List/table screens are server-side DataTables (yajra) — classes in `app/DataTables/` extending `BaseDataTable`.

### Routes (`routes/`, wired in `app/Providers/RouteServiceProvider.php`)
- `web.php` — main authed app; middleware `web`, `check-company-package`, `auto-logout`.
- `web-settings.php` — the Settings section (same middleware).
- `web-public.php` — guest/public pages.
- `api.php` — uses the custom **`ApiRoute`** facade (Froiden REST), not `Route`.
- `SuperAdmin/web.php` & `SuperAdmin/web-public.php` — **only registered when `isWorksuiteSaas()`**.

### Global helper pattern
`app/Helper/start.php` (autoloaded via composer `files`) defines ~150 global functions used pervasively instead of DI/config lookups: `user()`, `company()`, `user_modules()`, `module_enabled('X')`, `global_setting()`, and many cached `*_setting()` accessors. Most cache into the session. Prefer these existing helpers over re-querying settings. Other helpers live in `app/Helper/` (`Common.php`, `Files.php`, `NumberFormat.php`).

### Permissions & roles
Entrust-based (`mitchbred/entrust`). Roles: `admin`, `employee`, `client`, and (SaaS) `superadmin`. Permission checks return a *scope string*, not a bool — `user()->permission('view_timelogs')` yields `all` / `added` / `owned` / `both` / `none`; branch on that to scope queries (see `AccountBaseController::adminSpecific`).

### Modules & plugins (`nwidart/laravel-modules`)
- Modules live in `Modules/` (core ships only `LanguagePack`; purchased add-ons — Subdomain, RecruitModule, etc. — install here). Config in `config/modules.php`.
- Purchased/enabled module flags are cached as files in `bootstrap/cache/*_module.php`; check enablement with `module_enabled('Name')` or `user_modules()`, not by inspecting the filesystem.

### Frontend
Laravel Mix (`webpack.mix.js`) bundles `resources/js/*` + vendored jQuery plugins into `public/js/main.js`, and `resources/scss/main.scss` → `public/css`. Bootstrap 4, Select2, Quill, SweetAlert2, Dropify, Cropper.js, Frappe Charts. Blade views in `resources/views/`.

## Conventions & gotchas
- The auth identity (`UserAuth` / Fortify) is **separate** from the app `User`. A single `UserAuth` can map to multiple `User` rows across companies; `user()` resolves the active one and may swap to a client-contact context. Don't assume `auth()->user()` equals `user()`.
- Payments: many gateways are integrated (Stripe, PayPal, Razorpay, Mollie, Paystack, Square, Authorize.net, PayFast, Flutterwave). Shared logic lives in `app/Traits/PaymentGatewayTrait.php`, `MakePaymentTrait.php`, `MakeOrderInvoiceTrait.php`.
- `_ide_helper.php` and `_ide_helper_models.php` are generated — don't edit by hand.
- `upload.sh` + `laraupdater.json` package a versioned zip to Froiden's S3 update hub (the CodeCanyon auto-updater flow); not part of normal local dev.
