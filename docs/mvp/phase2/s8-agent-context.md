# S8 — общ контекст за агентите (Фаза 1)

Работна папка: `/Users/tsvet/Documents/Projects/databackd.com/databackd.com - app` (Laravel 12, AFV SaaS, Blade + Bootstrap 4 + jQuery). Прочети `CLAUDE.md` (кратко), `docs/mvp/COMMON-RULES.md` и `docs/mvp/phase2/COMMON-RULES-2.md`, и handoff-ите в `docs/mvp/handoff/*.md` на нужните за теб области. Handoff-ът е с предимство пред този файл при противоречие за вече съществуващия код.

PHP за командите: `/opt/homebrew/opt/php@8.3/bin/php` (по-долу `php`). Локална БД: `databackd_local` (MySQL root без парола). **Не стартирай сървър.** Демо компания „Демо Финанс Груп“ = `company_id 4` локално; акаунти (паролата по подразбиране на `databackd:demo`, виж `DatabackdDemoSeeder::PASSWORD`): админ `admin@demofinans.bg`, служител `g.petrov@demofinans.bg`, клиент `p.yordanov@balkan.bg`.

## Какво е вече направено (Фаза 0 на S8) — ползвай го, не го пипай
**Пакети/модули.** Функционалностите са модули на пакетите: `databackd_data` (въвеждане и импорт), `databackd_reports` (табло и справки), `databackd_analytics` (коефициенти, консолидация), `databackd_planning` (бюджети, прогнози), `databackd_teams` (услуга „Екипи“), `databackd_integrations` (API, външни източници). Helper-и (`app/Helper/databackd_modules.php`): `databackd_module(string): bool` (за потребителя), `databackd_company_module(int $companyId, string): bool` (без потребител — команди, API), `databackd_module_label()`, `databackd_module_description()`. Middleware `\App\Http\Middleware\Databackd\RequireModule::class . ':databackd_xxx'` (403 с ясно съобщение).

**Задължително за всеки нов екран** (спазвай точно имената — те са в мапинга `databackd_menu_module()`):
- Route файл `routes/databackd/gaps-<slug>.php`; цялата група започва така: `Route::prefix('databackd/gaps/<slug>')->middleware(\App\Http\Middleware\Databackd\RequireModule::class . ':<модул>')->name('databackd.gaps.<slug>.')->group(function () { ... });` Файлът се зарежда автоматично (префикс `/account`, middleware `web, check-company-package, auto-logout, auth, multi-company-select, email_verified`).
- Меню файл `resources/views/databackd/menu/6N-gaps-<slug>.blade.php`: `@if (databackd_module('<модул>')) ... <x-sub-menu-item :link="route('...')" :text="__('databackd-gaps-<slug>.menu')" /> ... @endif` (виж `resources/views/databackd/menu/30-analytics-ratios.blade.php`).
- Lang `resources/lang/eng/databackd-gaps-<slug>.php` и `resources/lang/bg/databackd-gaps-<slug>.php`. Български: **главна буква само в началото на изречението/заглавието** („Банкови извлечения“, не „Банкови Извлечения“); кавички „…“; без твърд текст в Blade/PHP — всичко през `__('databackd-gaps-<slug>.ключ')`. Валута EUR.
- Views `resources/views/databackd/gaps/<slug>/`, контролери `app/Http/Controllers/Databackd/Gaps/<Slug>/` (наследяват `AccountBaseController`, `pageTitle` като в S3 — виж `app/Http/Controllers/Databackd/Reports/*`), services `app/Services/Databackd/Gaps/<Slug>/`.
- Копирай стила и компонентите от съществуващите екрани (`resources/views/databackd/reports/**`, `resources/views/databackd/data/**`: `x-cards.*`, `x-forms.*`, `x-table`, `x-filters.*`, `x-line-chart`, `x-bar-chart`, `x-pie-chart`). Визуалния стил глобално го променя S9 паралелно — **не пипай** `resources/scss`, `resources/js`, `public/css`, `public/js`, `resources/views/layouts`, `sections`, `components`. JS/CSS за екрана — inline през `@push('scripts')` / `@push('styles')`.

| slug | модул за middleware и меню | меню файл |
|---|---|---|
| `bank-import` (B1a) | `databackd_data` | `18-gaps-bank-import` |
| `margins` (B2) | `databackd_reports` | `25-gaps-margins` |
| `statutory` (B3) | `databackd_reports` | `26-gaps-statutory` |
| `alerts` (B4) | `databackd_analytics` | `35-gaps-alerts` |
| `onboarding` (B5) | `databackd_data` | `06-gaps-onboarding` |
| `fx-rates` (B6) | `databackd_data` | `19-gaps-fx-rates` |

**Таблици (вече мигрирани, моделите са в `app/Models/Databackd/`, `HasCompany`, `$guarded=['id']`; не ги променяй — схемата е финална):**
- `products.cost_price decimal(15,2) null` (себестойност; B2).
- `fin_entries.source` вече допуска и `bank_statement` (`FinEntry::SOURCES`).
- `fin_bank_statements` → `FinBankStatement`: `import_batch_id, entity_id, format (mt940|camt053), account_iban, currency, statement_no, date_from, date_to, opening_balance, closing_balance, transactions_count, file_name, created_by`.
- `fin_counterparty_mappings` → `FinCounterpartyMapping`: `pattern, match_type (contains|exact), direction (credit|debit|any), account_id, entity_id, counterparty_name, created_by`.
- `fin_fx_rates` → `FinFxRate`: `rate_date, base_currency (EUR), currency, rate (единици валута за 1 единица base — конвенцията на ЕЦБ), source (manual|ecb), created_by`; unique `(company_id, rate_date, base_currency, currency)`.
- `fin_alert_rules` → `FinAlertRule` (константи `TYPES`, `SEVERITIES`; в PHPDoc е описан форматът на `params` за всеки тип): `name, type, entity_id, params json, severity, notify_app, notify_email, recipients json, is_active, last_state (ok|triggered), last_checked_at, created_by`.
- `fin_alert_events` → `FinAlertEvent`: `rule_id (null при изтрито правило), severity, message, value, threshold, period_from, period_to, context json, notified_app, notified_email, acknowledged_at, acknowledged_by`.
- `fin_onboarding` → `FinOnboarding` (едно на компания; `steps` json, `chart_template` nss|micro, `completed_at`, `dismissed_at`).
- Съществуващи: `fin_entities, fin_accounts, fin_dimensions, fin_entries, fin_import_batches, fin_data_sources, fin_budgets, fin_ratios …` — виж `docs/mvp/handoff/01..05` за колоните и service класовете (`FinancialDataService`, `FinEntryImporter`, `ReportFilters`, `ReportBuilder`, `FormulaEngine`, `RatioCalculator`, `ConsolidationService`, `BudgetActuals` …).

**Ако ти липсва колона/таблица/метод в чужд клас** — не го добавяй; работи в рамките на схемата и опиши липсата във финалния отчет.

## Общи ограничения (важат за всички)
- Мулти-тенант: заявките през модели с `HasCompany` са ограничени по компанията на логнатия потребител; в команди/seeders — `withoutGlobalScope(\App\Scopes\CompanyScope::class)` или `company_id` явно.
- Споделена локална БД: другите агенти работят паралелно в същата база. Тестови редове създавай само с име/префикс `TEST-<slug>` и ги изтривай накрая; не пипай чужди таблици и демо данни. Демо seeders (`database/seeders/Databackd/Demo/46…49`) пише само агентът B9 — останалите не.
- Не пускай `php artisan migrate*`, `db:seed`, `optimize*`, `config:cache`, `route:cache`, `cache:clear`, `composer`, `npm`, сървъри, `git add/commit/stash/checkout/reset`. Не редактирай `.env`, `composer.json`, `app/Providers/*`, `config/*`, `routes/web.php`, `resources/views/sections/**`.
- Нови PHP класове в `app/` се зареждат автоматично (PSR-4).
- Проверка: `php -l <файл>`; `php artisan route:list --name=databackd.gaps.<slug>`; `php -d memory_limit=1G docs/mvp/tools/smoke.php databackd.gaps.<slug>. --ajax` (+ `--url=` за route-ове с параметри; `--email=g.petrov@demofinans.bg` за служител, `--email=p.yordanov@balkan.bg` за клиент); `php artisan tinker --execute="..."`.
- Не commit-вай — commit прави оркестраторът.

## Правила за агенти (дословно)
> - Пипаш само файловете от своя списък. Ако ти трябва промяна извън него (нова колона, метод в споделен service, чужд файл) — **не я прави**; опиши я точно във финалния си отчет.
> - Не пускай: сървъри, `migrate*`, `composer`, `npm`, `config:cache`/`optimize*`/`cache:clear`, `git add/commit/stash/checkout/reset`. Не редактирай `.env`.
> - Можеш да пускаш: `php -l <файл>`, `php artisan route:list --name=<prefix>`, `php artisan tinker --execute="..."` (само четене или твоите демо данни), `php docs/mvp/tools/smoke.php <твоят route prefix> [--ajax] [--url=...]`.
> - UI текстове само през `__('databackd-gaps-<пакет>.ключ')` в своя lang файл — `resources/lang/eng/…` (английски) и `resources/lang/bg/…` (български). Без твърдо зададен текст в Blade.
> - Копирай стила от съществуващи екрани и ползвай съществуващите Blade компоненти.
> - Свършил си, когато smoke проверката за твоите routes минава (без FAIL) и екраните показват демо данни.
> - Финален отчет (кратък): създадени/променени файлове; routes (имена + URL); публични методи, които другите могат да ползват; резултат от smoke проверката; какво не е направено; нужни промени извън твоите файлове.
