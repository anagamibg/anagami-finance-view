# App Update & Customizations Guide

Този документ събира на едно място правилата за **обновяване на приложението** (upstream Anagami Finance View update) и **къстомизациите**, които трябва да се запазят при всеки merge.

**Последна верификация спрямо кода:** 2026-07-01; раздели 4.1–4.3 и CUS_000328 — коригирани 2026-10-28 (S10) след проверка в git; добавени 4.7 (S9) и 4.8 (S7); обновяване до 6.0.19 и раздел 4.10 — 2026-10-28.

---

## 1. Основни правила при app update

1. **Пази къстомизациите.** Преди merge на нова версия на Anagami Finance View винаги свери списъка от Раздел 3 по-долу — всеки от тези файлове съдържа custom код, който upstream ще презапише.
2. **Не работи в `app2/` и `resources1/`.** Това бяха стари backup-и отпреди къстъм разработката. **Вече са премахнати** (не са и в git) — merge-ът с upstream, който ги ползваше като база, е приключил. Ако някога се появят пак, само за справка са — работи винаги в `app/` и `resources/`.
3. **Тествай локално преди deploy.** `npm run dev` → провери на http://127.0.0.1:8000 → чак тогава deploy.
4. **Git commit за всяка къстъм фийчър / bug fix.** Осмислени commit съобщения (виж Раздел 7), за да е проследимо какво е къстъм при следващия merge.
5. **Документирай.** Всяка нова къстомизация се добавя в този файл и в [CLAUDE.md](../CLAUDE.md), за да не се загуби при бъдещ ъпгрейд.
6. **Не презаписвай `resources/lang/bg/`.** От S7 (2026-10) `resources/lang/eng/` **и** `resources/lang/bg/` (основният UI език) са в git. При upstream merge нови ключове от `eng/` се добавят на ръка и в `bg/` (виж `docs/mvp/tools/lang-audit.php` и речника в `docs/mvp/features/07-translation.md`) — никога не копирай upstream `bg/` върху нашия. `de/`, `ru/` и останалите езици живеят само на сървъра.

---

## 2. Процедура за upstream merge

> **От 6.0.19 (2026-10-28):** клонът `vendor/worksuite` пази чистите upstream версии (`37235f8` = 6.0.10, `ae7d718` = 6.0.19). Нова версия: worktree на `vendor/worksuite` → заменя се цялото дърво с новата версия (без `vendor/`, `node_modules/`, IDE артефакти като `storage/framework/lsp-*.php`) → commit `vendor: Worksuite X.Y clean baseline` → клон `upstream/worksuite-vX.Y` от `main` → `git merge vendor/worksuite`. Git прави истински 3-way merge и маркира само файловете, променени и от двете страни. **Прегледай и автоматично слетите файлове, които сме променяли** (`comm -12` на `git diff --name-only <база> main` и `<база> vendor/worksuite`): upstream изтриване на ред може мълчаливо да махне наша къстомизация (пример: `super-admin` в `config/log-viewer.php` при 6.0.19). Отчетът от 6.0.19 — `docs/mvp/upgrade-6.0.19/`.

```bash
# 1. Клон за ъпгрейда
git checkout main && git checkout -b upstream/worksuite-vX.Y

# 2. Разархивирай новата upstream версия отстрани и сравни срещу нашите app/ и resources/
#    За всеки файл от Раздел 3 → merge на ръка (нашите промени + upstream промените),
#    НЕ overwrite.

# 3. Останалите файлове (без къстъм) → може директно от upstream.

# 4. Rebuild
composer dump-autoload
npm run dev

# 5. Тествай локално: Dashboard, списък (DataTable), форма, detail/show, PDF, super-admin billing.

# 6. Merge в main + deploy (виж CLAUDE.md → Deploy Workflow)
```

Практично: държим списъка от Раздел 3 като checklist. Ако upstream пипне някой от тези файлове → ръчен merge. Ако не го е пипнал → нашата версия остава.

---

## 3. Legacy къстъм фийчъри — CUS_000326–329

Историческите къстомизации. Статусът е сверен спрямо текущия код на датата горе.

### CUS_000329 — Billing Package Limits (`max_clients`, `max_invoices`)  ✅ АКТИВЕН

Ограничава броя клиенти/фактури според billing пакета на компанията.

Ключови файлове:
- `app/Helper/start.php` — helper функции за проверка на лимити (`checkCompanyCanAddMoreClients()`, `checkCompanyCanAddMoreInvoices()`, `clearCompanyValidPackageCache()`)
- `app/Http/Middleware/CheckCompanyPackage.php`
- `app/Http/Controllers/InvoiceController.php`
- `app/Http/Controllers/ClientController.php`
- `app/Http/Controllers/SuperAdmin/BillingController.php`
- `app/Http/Controllers/SuperAdmin/PayFastController.php`
- `app/Jobs/ImportClientJob.php`
- `app/Http/Requests/SuperAdmin/Packages/StoreRequest.php`
- `app/Http/Requests/SuperAdmin/Packages/UpdateRequest.php`
- `app/DataTables/SuperAdmin/CompanyDataTable.php`
- `app/DataTables/SuperAdmin/PackageDataTable.php`
- `app/DataTables/InvoicesDataTable.php`
- `app/Observers/ClientDetailsObserver.php`
- `app/Observers/InvoiceObserver.php`
- `app/Observers/SuperAdmin/PackageObserver.php`
- `resources/views/super-admin/packages/ajax/create.blade.php`
- `resources/views/super-admin/packages/ajax/edit.blade.php`
- `resources/views/super-admin/billing/ajax/plan.blade.php`
- `resources/views/super-admin/companies/ajax/show.blade.php`
- `resources/views/super-admin/front/section/pricing-plan.blade.php`
- `resources/views/super-admin/saas/pricing-plan.blade.php`
- `resources/views/invoices/index.blade.php`
- `resources/views/invoices/ajax/show.blade.php`
- `resources/views/clients/index.blade.php`
- `resources/lang/eng/superadmin.php`

### CUS_000328 — Dual-language PDF (EN + BG)  ✅ АКТИВЕН (с известен бъг)

PDF шаблоните имат guard `@if ($invoiceSetting->locale != 'bg')`, за да рендират EN + BG в един PDF — към 2026-10-28 **34 файла** (`grep -rl "locale != 'bg'" resources/views`): `{invoices,estimates,orders,proposals}/pdf/invoice-{1..5}`, `credit-notes/pdf/invoice-{1..5,recurring}`, `proposals/pdf/invoice-recurring`, `proposal-template/pdf/invoice-5`, `super-admin/invoices/pdf/invoice-{1..5}`, `payments/ajax/pdf`. От S7 28 от тях носят и `__()`/`@lang` замени (виж 4.8).

> ⚠️ **Известен бъг (неоправен):** PDF-ът рендира 2× на български вместо EN + BG.

### CUS_000327 — Invoice Series  ⚠️ СТАТУС НЕЯСЕН

Не е открита ясна имплементация в diff-а. При работа по фактури — провери миграциите преди да приемеш, че липсва.

### CUS_000326 — Custom Fields за Finance Docs  ✅ АКТИВЕН

`CustomFieldsTrait` е добавен към `BankAccount`, `CreditNotes`, `Payment`, `Proposal` (напр. `app/Models/Proposal.php` → `use CustomFieldsTrait, HasCompany;`).

> **Корекция 2026-07-01:** предишна верификация в този документ твърдеше грешно, че тези 4 модела вече не ползват trait-а. Директна проверка на кода (grep) показва, че `CustomFieldsTrait` е активен и в четирите. Пренесен коректно при 5.4.6→6.0.10 merge-а (виж CLAUDE.md → App upgrade).

---

## 4. Актуални къстомизации (пази при update)

### 4.1 Custom code injection страници (super-admin)  ❌ НЯМА ГИ В GIT

> **Корекция 2026-10-28 (S10):** предишната версия на този раздел описваше три страници в `/account/settings/` (Custom CSS / JS / HEAD), които инжектират `global_settings.custom_css`, `custom_js` и `custom_head_html` в `resources/views/layouts/app.blade.php`. Проверка в git показва, че **нищо от това не съществува** — нито в текущия код, нито в историята от първия commit (`636c16e`) насам:
> - `grep -n "custom_css\|custom_js\|custom_head_html" resources/views/layouts/app.blade.php` → нищо; `git log -S custom_css -- resources/views/layouts/app.blade.php` → нищо.
> - Няма `app/Http/Controllers/Custom{Css,Js,Head}Controller.php`, няма `resources/views/settings/custom-{css,js,head}/`, няма такива routes в `routes/web-settings.php`.
> - `config/xss_ignore.php` **не съдържа** `custom_css`/`custom_js`/`custom_head_html` (има само upstream полета като `header_script`, `footer_script`, `contact_html`).
>
> Единственото `custom_css` в кода е upstream поле на публичния SaaS сайт: `{!! $frontDetail->custom_css !!}` в `resources/views/super-admin/layouts/front-app.blade.php` и `{!! $frontDetail->custom_css_theme_two !!}` в `resources/views/super-admin/layouts/saas-app.blade.php` (модел `app/Models/SuperAdmin/FrontDetail.php`). Това е оригинален Anagami Finance View код, не къстомизация.
>
> Ако описаната функционалност е съществувала, тя е била само в сървърното копие отпреди 6.0.10 merge-а. Преди да се презапише `layouts/app.blade.php` на сървъра — сравни сървърното копие с git (виж и `docs/mvp/handoff/07-translation.md`, `09-ui-ux.md`).

Правилото остава валидно за бъдещи полета: ако се добави поле с raw HTML/JS, името му трябва да е в `config/xss_ignore.php`, иначе Froiden XSS middleware (при нас `XssLinearMerge`, виж 4.6) изчиства `<script>` при запис.

### 4.2 About страница  ❌ НЯМА Я В GIT

> **Корекция 2026-10-28 (S10):** `app/Http/Controllers/SuperAdmin/AboutController.php` не съществува и няма route за `/account/about` (проверено с `ls` и `grep` в `routes/`). Важи същото като за 4.1 — ако е имало такава страница, тя е била само на сървъра.

### 4.3 Baked-in bug fixes  ❌ НЕ СА В GIT

> **Корекция 2026-10-28 (S10):** нито една от четирите поправки, описани тук досега, не е в кода (и не е била от `636c16e` насам):
> - `app/Models/Expense.php` — `protected $with = ['currency', 'company:id'];` (**без** `date_format`).
> - `app/Http/Controllers/FinanceReportController.php` — `$projectNames` се присвоява само вътре в `if ($payments->count() > 0)`, а се ползва след блока (в `array_map(... use ($projectNames))`); предварителна инициализация няма. Когато няма плащания, `use ($projectNames)` е с недефинирана променлива → PHP warning „Undefined variable“, който Laravel превръща в `ErrorException`.
> - `resources/js/bootstrap.js` — `window.Echo = new Echo({... key: process.env.MIX_PUSHER_APP_KEY ...})` **без** guard `if (process.env.MIX_PUSHER_APP_KEY)`.
> - `resources/views/layouts/app.blade.php` — `$(window).on('load', function () { init(); $(".preloader-container").fadeOut(...) })` **без** `try/catch` и без 5s `setTimeout` предпазна мрежа (единственият `try` в оригиналния файл беше около `quill.insertEmbed`, а в текущия няма нито един).
>
> Ако тези поправки са нужни, те трябва да се направят наново (като отделен commit) — merge няма какво да „запази“.

---

### 4.4 CUS_databackd_mvp — Финансов анализ MVP (скриване на модули + финансов модел)  ✅

Базата за MVP на Anagami Finance View (сесия S1). Промените в оригиналните upstream файлове са минимални и маркирани с `{{-- databackd --}}` / `// databackd`. При upstream merge ги запазете на ръка.

Променени upstream файлове (hand-merge):
- `composer.json` — `app/Helper/databackd.php` в `autoload.files` (после `composer dump-autoload`)
- `app/Providers/RouteServiceProvider.php` — `mapDatabackdRoutes()` зарежда `routes/databackd/*.php` (префикс `account`)
- `app/Providers/RouteServiceProvider.php` — `mapDatabackdApiRoutes()` (S2) зарежда `routes/databackd/api/*.php` с middleware `api` и префикс `api`, без `auth` (REST endpoint за финансови записи, автентикация с API токен)
- `app/Providers/AppServiceProvider.php` — View Composer за `sections.menu` (променлива `$databackdMenuViews`) и `Company::created` → създава сметкоплана
- `resources/views/sections/menu.blade.php` — `@unless (databackd_hidden('menu', …))` около скритите елементи + нов раздел „Финансов анализ“ след „Табло“
- `app/Providers/AppServiceProvider.php` + `resources/views/sections/menu.blade.php` + `config/databackd.php` (S5) — раздел „Финансов анализ“ вече се показва и на роля `client`, но за клиенти View Composer-ът оставя само menu файловете от `config('databackd.client_menu')` (`50-teams-requests`, `51-teams-analysis`); всички останали пунктове остават само за admin/employee
- `resources/views/dashboard/admin.blade.php` — скрити табове на разширеното табло
- `resources/views/dashboard/employee/index.blade.php` — скрити widget-и на личното табло
- `resources/views/components/setting-sidebar.blade.php` — скрити пунктове в настройките
- `resources/views/sections/topbar.blade.php` — скрито „Тикет“ в бързото добавяне

Нови (не се презаписват от upstream): `config/databackd.php`, `app/Helper/databackd.php`, `app/Console/Commands/DatabackdDemo.php`, `app/Models/Databackd/*`, `app/Services/Databackd/*`, `app/Http/Controllers/Databackd/*`, `routes/databackd/*`, `resources/views/databackd/*`, `database/migrations/2026_10_27_1000*_create_fin_*`, `database/seeders/Databackd/*`, `resources/lang/{eng,bg}/databackd*.php` (`bg/` е в git от S7).

Скриване на страницата „Billing“ (`/account/settings/billing/*`) за всички: `config/databackd.php` (`hidden.pages.billing`, `hidden.settings.billing`, `hidden.topbar.upgrade_plan`), `app/Http/Middleware/Databackd/HideBillingPages.php` (404) закачен в `routes/SuperAdmin/web.php` (група `account/settings`, маркирано `// databackd`), `resources/views/components/setting-sidebar.blade.php` и `resources/views/sections/topbar.blade.php` (`@unless (databackd_hidden(...))`), `app/Http/Middleware/CheckCompanyPackage.php` (при невалиден пакет админът не се пренасочва към скритата страница). Връщане: сложете `false` в трите ключа.

S2 (въвеждане и импорт на финансови данни) добавя само нови файлове: `routes/databackd/data-*.php`, `routes/databackd/api/*`, `app/{Http/Controllers,Http/Requests,Http/Middleware,DataTables,Exports,Jobs}/Databackd/**`, `app/Imports/Databackd/**`, `app/Services/Databackd/Data/**`, `app/Models/Databackd/{FinDataSource,FinApiToken}.php`, `resources/views/databackd/{data,menu}/**`, `database/migrations/*_databackd_data_*`, `public/databackd/templates/**`.

Скриването е само във фронтенда: routes и код на скритите модули остават. Управлява се от `config/databackd.php` → `hidden`.

---

### 4.5 CUS_databackd_packages — модулите на AFV в пакетите + нови функционалности (S8)  ✅

Функционалностите на AFV са модули на SaaS пакетите (`databackd_data|reports|analytics|planning|teams|integrations`). Промените в оригиналните upstream файлове са минимални и маркирани `databackd (S8)`; при upstream merge ги запазете на ръка:
- `app/Scopes/SuperAdminModuleScope.php` — `module_name NOT LIKE 'databackd\_%'` (модулите не се показват на страниците за роли/права); `app/Models/Module.php` — `Module::databackdModules()`
- `app/Http/Controllers/ModuleSettingController.php` — AFV модулите са скрити от „Настройки на модулите“
- `app/Observers/CompanyObserver.php` — `createModuleSettings()`/`moduleSettings()` добавят `databackd_modules_for_type(...)`
- `app/Providers/AppServiceProvider.php` — View Composer-ът на `sections.menu` филтрира менюто по `databackd_menu_module()`
- `app/Console/Kernel.php` — `databackd:recurring-analysis` (06:00), `databackd:send-reports` (07:00), `databackd:check-alerts` (на час)
- `app/Http/Controllers/SuperAdmin/{PackageController,FrontendController,BillingController}.php`, `app/DataTables/SuperAdmin/PackageDataTable.php`, `resources/views/super-admin/{packages/ajax,front/section/pricing-plan,saas/pricing-plan,billing/plan,billing/package}` — блок „Финансов анализ (AFV)“ в пакетите и цените; формите имат и полетата `max_clients`/`max_invoices` (CUS_000329)
- `app/Http/Controllers/ProductController.php`, `app/Http/Requests/Product/{Store,Update}ProductRequest.php`, `app/Models/Product.php`, `resources/views/products/ajax/{create,edit,show}.blade.php` — поле „Себестойност“ (`products.cost_price`)
- `app/Helper/databackd.php` — `databackd_hidden()` е `false` за супер админ; `require_once` на `databackd_modules.php`; `app/Http/Middleware/Databackd/HideBillingPages.php`

Нови (не се презаписват от upstream): `app/Helper/databackd_modules.php`, `app/Http/Middleware/Databackd/{RequireModule,RequireCompanyModule}.php`, `routes/databackd/{00-modules,gaps-*}.php`, `app/{Http/Controllers,Http/Requests,Exports}/Databackd/Gaps/**`, `app/Services/Databackd/Gaps/**`, `app/Services/Databackd/Data/ImportTemplates.php`, `app/Models/Databackd/{FinAlert*,FinBankStatement,FinCounterpartyMapping,FinFxRate,FinOnboarding}.php`, `app/Console/Commands/Databackd/CheckAlerts.php`, `resources/views/databackd/gaps/**`, `database/migrations/2026_10_28_*`, `database/seeders/Databackd/Demo/4[6-9]*`, `resources/lang/{eng,bg}/databackd-{packages,gaps-*}.php` (`bg/` е в git от S7). Подробности: `docs/mvp/features/08-packages-gaps.md`.

### 4.6 CUS_databackd_security — поправки по сигурността в оригиналния upstream код (S6)  ✅

Преглед на сигурността, октомври 2026 (`docs/mvp/security/2026-10-security-review.md`). Поправките в оригинални upstream файлове са минимални и маркирани `databackd S6`; **при upstream merge ги запазете на ръка** (или проверете, че новата версия на Anagami Finance View ги е поправила):
- `app/Http/Kernel.php` — глобални middleware `SecurityHeaders`, `FinApiGate`, `RestrictVendorMaintenanceRoutes`; `XssLinearMerge` **вместо** `Froiden\Envato\Middleware\XSS` (оригиналът е със квадратично `Request::merge()` → неавтентикиран DoS); в групата `web` — `VerifyCsrfTokenPathOnly` **вместо** `App\Http\Middleware\VerifyCsrfToken` (CSRF изключенията се сравняват само по път) и `AuthHardening` (2FA код, `choose-workspace`, `forgot-password`).
- `app/Models/UserAuth.php` — `validateLoginActiveDisabled()`: трите `DB::select(...)` са с `?` bindings (SQL injection чрез имейл с кавички).
- `enable_php` е **махнат** от `HomeController`, `EstimateController`, `InvoiceController` (2 места), `ProposalController`, `PublicUrlController` (2), `ProposalTemplateController`, `CreditNoteController`, `ContractController` (2), `EstimateTemplateController`, `SuperAdmin/InvoiceController`, `Notifications/MonthlyAttendance`, `Notifications/DailyTimeLogReport` — иначе бележка със `<script type="text/php">` изпълнява PHP при PDF. Нов upstream код с `setOption('enable_php', true)` не бива да се връща (`databackd:check-security` го хваща).
- Изтрити: `public/backup-and-restore/` (аварийният restore инструмент е с обърната проверка за вход — достъпен за всички) и `public/check.php`. Ако upstream ги върне — изтрийте ги отново.
- Конфигурация: `config/log-viewer.php` (`api_middleware` със `auth` + `super-admin` — иначе логовете са достъпни анонимно), `config/session.php` (`secure` по `APP_URL`), `config/hashing.php` (bcrypt 12), `config/filesystems.php` (диск `databackd_private`), `config/sentry.php`, `config/mail.php`, `config/cors.php`; нови `config/security-headers.php`, `config/databackd-api.php`.
- Нови (не се презаписват): `app/Http/Middleware/Databackd/{SecurityHeaders,FinApiGate,XssLinearMerge,RestrictVendorMaintenanceRoutes,VerifyCsrfTokenPathOnly,AuthHardening}.php`, `app/Services/Databackd/Data/{FinApiAudit,FinApiExceptionHandler}.php`, `app/DataTables/Databackd/ColumnWhitelist.php`, `app/Exports/Databackd/Concerns/NeutralizesFormulas.php`, `app/Console/Commands/Databackd/CheckSecurity.php`. Подробности: `docs/mvp/features/06-security.md`. След upstream merge: `php artisan databackd:check-security`.

### 4.7 CUS_databackd_ui — дизайн система, групиран сайдбар, общи графики (S9, `3fbb462`)  ✅

Единна визия на AFV (палитра с light/dark токени, KPI карти, графики през `dbkChart`, групиран сайдбар, мобилни поправки). Правилата са в `docs/mvp/phase2/ui-guidelines.md`, подробности — `docs/mvp/features/09-ui-ux.md`, `docs/mvp/handoff/09-ui-ux.md`. Промените в оригиналните upstream файлове са **визуални** (CSS класове `dbk-*`, разметка на графики) и в Blade са маркирани `{{-- databackd ui --}}` / `{{-- databackd --}}`.

Променени upstream файлове (hand-merge):
- `resources/scss/main.scss` — в края 12 `@import "databackd/..."` (tokens, components, sidebar, layout, dashboard, reports, analytics, planning, teams, data, modules, mobile).
- `webpack.mix.js` — `.scripts(['resources/js/databackd-charts.js'], 'public/js/databackd-charts.js')` и `.disableNotifications()`.
- Компилирани (в git, генерират се от горните): `public/css/main.css`, `public/css/main.css.map`, `public/js/main.js`, `public/js/bootstrap.js`, `public/js/bootstrap.js.map`. При merge **не** ги merge-вай на ръка — пусни `npm run prod`.
- `resources/views/layouts/app.blade.php` — `<html lang="{{ str_replace('_', '-', app()->getLocale() ?: 'en') }}">` (вместо твърдо `en`; `dbkChart` избира bg-BG/en-GB формат по него); `?v={{ @filemtime(...) }}` след `css/main.css`, `js/main.js` и `js/databackd-charts.js` (cache-busting след deploy); Chart.js (`vendor/jquery/Chart.min.js`) и `js/databackd-charts.js` се зареждат **глобално** на всяка страница след `main.js`. S7 променя същия файл след това (`moment.locale`, виж 4.8).
- `resources/views/sections/menu.blade.php` — секции „Анализ“/„Операции“ (`dbk-sb-section`, текстовете от `databackd-ui.menu_sections.*`), подгрупи на „Финансов анализ“ по числовия префикс на menu файла (05 общ преглед, 1x данни, 2x отчети, 3x анализи, 4x планиране, 5x екипи; `databackd-ui.menu_groups.*`), блокът с базовите (upstream) справки е преместен в „Анализ“ без промяна; съществуващите `@unless (databackd_hidden(...))` от S1 са запазени.
- `resources/views/sections/sidebar.blade.php` — класове `dbk-sb-*` за името на компанията/потребителя и нов `<script>` в края: активен пункт по най-дълъг префикс на пътя, отваряне на групите с Enter/Space, tooltip-и и flyout менюта при свит сайдбар, затваряне на мобилното чекмедже.
- Компоненти: `components/app-title.blade.php` (класове `dbk-layout-title*`, `dbk-layout-crumbs`), `components/menu-item.blade.php` и `components/sub-menu-item.blade.php` (активен пункт/отворена група се определят на сървъра по URL, `aria-current`, `tabindex`; класът на `<li>` трябва да остане точно `accordionItem openIt|closeIt` — `toggleItem()` в `public/js/main.js` сравнява целия `className`), `components/cards/{card-header,data,no-record,no-record-found-list,widget}.blade.php` (класове `dbk-layout-*`; `no-record` е с нова разметка `dbk-empty`; `card-header` показва `$action` само ако не е празен), `components/filters/filter-box.blade.php` (`dbk-layout-filters`), `components/forms/label.blade.php` (`dbk-form-label`).
- `resources/views/dashboard/**` — `admin.blade.php`, `ajax/{client,finance,hr,lead-by-pipeline,overview,project,ticket}.blade.php`, `client/index.blade.php`, `employee/index.blade.php`, `employee/widgets/{follow_ups,lead,projects,week_timelog}.blade.php`: `<x-pie-chart>`/`<x-bar-chart>`/`<x-line-chart>` са заменени с `@include('dashboard._dbk-chart', [...])`, локалното зареждане на `frappe-charts.min.iife.js` и `Chart.min.js` е махнато (Chart.js идва от layout-а), класове `dbk-ws-dash`, `dbk-dash-stat`.

Нови файлове в upstream директории (upstream не ги презаписва, но внимавай при копиране на цели директории): `resources/views/components/dbk/{card,chart,empty,kpi,page-header}.blade.php` (Blade компоненти `x-dbk.*`), `resources/views/dashboard/_dbk-chart.blade.php`, `resources/views/notifications/all/fin_alert_notification.blade.php` (добавен в S8, `4a65de9`).

Нови (само AFV): `resources/scss/databackd/*.scss`, `resources/js/databackd-charts.js`, `public/js/databackd-charts.js`, `resources/lang/eng/databackd-ui.php` (+ `bg/`), `docs/mvp/tools/snapshot.{php,js}`. Екраните в `resources/views/databackd/**` също са преработени, но те не са upstream.

**При upstream merge:** промените са визуални — вземи upstream версията на файла и **приложи нашите промени отгоре** по diff-а на `3fbb462` (`git show 3fbb462 -- <файл>`), вместо обратното. Ако upstream е сменил разметката на сайдбара/картите — запази класовете `dbk-*` и логиката за активния пункт. Накрая `npm run prod` (пресъздава `public/css/main.css`, `public/js/main.js`, `public/js/bootstrap.js`, `public/js/databackd-charts.js`) и провери табло, сайдбар (разгънат/свит/мобилен) и една графика.

### 4.8 CUS_databackd_translation — цялото приложение на български и английски (S7, `3fb1674`)  ✅

`resources/lang/bg/` е в git (60 файла, ~9 900 ключа на език), твърдо кодираният английски текст в оригиналните изгледи, контролери, DataTables, известия, PDF и имейл шаблони е заменен с `__()` / `@lang`. Речник BG/EN и правила за главни букви — `docs/mvp/features/07-translation.md`; handoff — `docs/mvp/handoff/07-translation.md`. Инструменти: `docs/mvp/tools/lang-audit.php` (сравнява `eng/` и `bg/`: липсващи ключове, разлики в placeholder-ите, забранени термини, главни букви) и `docs/mvp/tools/hardcoded-text.php` (намира видим текст извън `__()`).

Променени upstream файлове (hand-merge):
- **305 изгледа** в `resources/views/` (извън `databackd/`) — твърд текст → `__()`/`@lang`, на места и смяна на ключа (напр. `@lang('modules.invoices.billedTo')` → `@lang('ui-shared.pdf.billed_to')`). По области:
  - финанси (73): `invoices`, `credit-notes`, `estimates`, `estimate-requests`, `proposals`, `proposal-template`, `orders`, `contracts`, `bank-account`, `public-payment`, `products`, `expenses`, `recurring-invoices`, `recurring-expenses`, `tax`, публичните `invoice`/`estimate`/`proposal`/`contract.blade.php`;
  - проекти и задачи (63): `projects`, `tasks`, `taskboard` (+ `taskboard.blade.php`), `project-templates`, `recurring-task`, `timelogs`, `timelog-break`, `weekly-timesheets`, `discussions`, `front/tasks/**`, `gantt.blade.php`, `gantt_dhtml.blade.php`;
  - настройки (62): `lead-settings`, `ticket-settings`, `security-settings`, `invoice-settings`, `attendance-settings`, `currency-settings`, `app-settings`, `custom-fields`, `tax-settings`, `storage-settings`, `profile-settings`, `notification-settings`, `custom-link-settings`, `company-address`, `theme-settings`, `role-permissions`, `leave-settings`, `language-settings`, `gdpr-settings`, `database-backup-settings`, `custom-modules`;
  - HR (33): `employees`, `attendances`, `leaves`, `holiday`, `shift-rosters`, `departments`, `designation`, `appreciations`, `attendance-report.blade.php`, `exports/attendancebymember.blade.php`;
  - общи (31): `layouts/{app,public}.blade.php` (в `app` — `moment.locale(...)` по езика на потребителя и преведено „New message received.“), `sections/{menu,sidebar,modals,add_shipping_address,mobile-app-modal,raise-support-ticket-modal,universal-bundle-alert}.blade.php`, `components/{app-title,auth,cron-message,menu-item,sub-menu-item,cards/onboarding-item,cards/ticket,filters/more-filter-box}.blade.php`, `dashboard/{checklist,update-message,employee/index,employee/clock_in_modal,employee/show_clocked_hours,employee/widgets/documents}.blade.php`, `vendor/datatables/print.blade.php`, `vendor/froiden-envato/update/*.blade.php` (6), `vendor/mail/html/paymentbutton.blade.php`. `app-title`, `menu-item`, `sub-menu-item`, `menu`, `sidebar`, `layouts/app` и `dashboard/employee/index` са променени и в S9 (4.7) — S7 е върху S9;
  - други (26): `tickets`, `notices`, `knowledge-base`, `event-calendar`, `recurring-event`, `my-calendar`, `messages`, `reports`, `gdpr`, `app-downloads`, `notifications/client`, `common/smtp-error`, `errors/{325,404,file_not_found,link-expired}`, `auth/{password-confirm-modal,passwords/reset-password,verify-email}`;
  - CRM (17): `leads`, `lead-contact`, `clients`.
- **PDF шаблони** — променени в S7 и носещи guard-а на CUS_000328 (`@if ($invoiceSetting->locale != 'bg')`, раздел 3), т.е. при merge трябва да се запазят **и двете** промени:
  - `invoices/pdf/invoice-{1,2,3,4,5}.blade.php`
  - `estimates/pdf/invoice-{1,2,3,4,5}.blade.php`
  - `orders/pdf/invoice-{1,2,3,4,5}.blade.php`
  - `proposals/pdf/invoice-{1,2,3,4,5,recurring}.blade.php`
  - `credit-notes/pdf/invoice-{1,2,3,4,5,recurring}.blade.php`
  - `proposal-template/pdf/invoice-5.blade.php`

  Променени в S7, но **без** guard: `bank-account/pdf/statement.blade.php`, `contracts/contract-pdf.blade.php`, `invoices/pdf/invoice-recurring.blade.php`. С guard, но **не** пипнати от S7: `super-admin/invoices/pdf/invoice-{1..5}.blade.php`, `payments/ajax/pdf.blade.php`.
- **Контролери** (`Reply::success('...')`, page title и съобщения → `__()`): `app/Http/Controllers/{AttendanceController,CustomModuleController,DepartmentController,DesignationController,EmployeeController,GdprSettingsController,PaymentController,ProjectController,PushNotificationController,RegisterController,SettingsController,SlackSettingController,TaskCommentController,TaskFileController,TimelogController}.php`, `app/Http/Controllers/Payment/{MollieController,RazorPayController}.php`.
- **DataTables:** `app/DataTables/{KnowledgeBaseDataTable,NoticeBoardDataTable,ProjectsDataTable}.php`.
- **Notifications:** `app/Notifications/{EmployeeDocumentExpiryAlert,ImmigrationExpiryAlert,NewLeaveRequest,PromotionAdded,PromotionUpdated,TestSlack}.php`.
- `public/i18n/bg.json` — преводът на DataTables (`processing`, `lengthMenu`, `info`, ... — „записа“ вместо „резултата“ и т.н.).
- `.gitignore` — `!resources/lang/bg/` (bg влиза в git).
- **`resources/lang/eng/` (upstream файлове):** променени стойности без изтрити ключове — `app.php` (155 стойности + 47 нови ключа), `modules.php` (506 стойности + 1 нов ключ), `email.php` (210 стойности; файлът е **преформатиран** от `array(...)` на `[...]`, затова diff-ът обхваща целия файл), `clan.php` (44), `messages.php` (37), `placeholders.php` (21), `permissions.php` (21 нови ключа), `validation.php` (5), `auth.php`, `cookie.php`, `installer_messages.php`, `passwords.php` (по няколко). Посоката е унифициран английски: Title Case за менюта и заглавия, sentence case за бутони и съобщения, поправени правописни грешки.

Нови (само AFV): `resources/lang/bg/*` (60 файла), `resources/lang/eng/{ui-core,ui-shared,ui-misc,databackd-misc}.php`, `docs/mvp/tools/{lang-audit,hardcoded-text}.php`. Контролерите в `app/Http/Controllers/Databackd/**` също са пипнати, но не са upstream.

**При upstream merge:**
1. **Lang файловете — по ключ, никога overwrite.** За всеки upstream файл от `resources/lang/eng/`: вземи новите ключове от upstream, а за съществуващите — **нашите** стойности (иначе се губят поправките на S7). След merge-а добави новите ключове и в `resources/lang/bg/` с български превод и пусни `php docs/mvp/tools/lang-audit.php` (липсващи ключове, placeholder-и, термини; `--strict` за exit code). `resources/lang/bg/` **никога** не се презаписва с upstream `bg/`.
2. **Изгледите, контролерите, DataTables, Notifications** — вземи upstream версията и приложи наново замените с `__()` по diff-а (`git show 3fb1674 -- <файл>`). Използваните ключове вече са в `ui-core`/`ui-shared`/`ui-misc` и в upstream файловете — не ги трий.
3. PDF шаблоните — запази и guard-а на CUS_000328, и `__()` замените.
4. Накрая `php docs/mvp/tools/hardcoded-text.php --list` — новият upstream код почти сигурно носи нов твърд английски текст.

### 4.9 CUS_databackd_review — поправки от независимото ревю преди деплоя (S10, `c22724a`)  ✅

Повечето поправки са в AFV файлове (не се презаписват при upstream merge). В оригинални upstream файлове (маркирани `databackd (S10)`, **запазете ги на ръка**):
- `routes/web.php` — изтрити дебъг маршрутите `/test-broadcast` и `/debug-broadcast` (closures без вход). Ако upstream ги върне — изтрийте ги отново.
- `routes/web-public.php` — `sync-user-permissions` е с `->middleware('auth')`; освен това `config/security-headers.php` → `restricted_routes.super_admin_only` го ограничава до суперадмин (пуска artisan командата за всички компании).
- `resources/views/components/bar-chart.blade.php`, `pie-chart.blade.php` — етикетите и цветовете се подават в JS с `@json(...)` (преди — сурово в низ; етикет с `'`/`</script>` чупеше скрипта). Еднакъв JS за нормални стойности.
- `.env.example` — празен `APP_KEY=` (примерният ключ беше реален); `.env.dev` е махнат от git (`git rm --cached`, `.gitignore` → `.env.*`) — ако upstream го върне в пакета, не го commit-вайте.
- **Само на сървъра (не е в git):** кореновият `~/public_html/.htaccess` има блок „HTTP → HTTPS“ (`RewriteCond %{HTTPS} !=on` + `X-Forwarded-Proto`) преди `RewriteRule ^(.*)$ public/$1`; `public/user-uploads/databackd/.htaccess` = `Require all denied`. При подмяна на тези файлове от upstream ги възстановете (копие: `~/deploy-backup-databackd-phase2-20261028/htaccess.root.backup` е версията **преди** S10).

Подробности за ревюто и деплоя: `docs/mvp/handoff/10-review-deploy.md`.

### 4.10 CUS_databackd_upgrade_6019 — обновяване до Anagami Finance View 6.0.19 (2026-10-28)  ✅

Merge `4f42ef7` (3-way от `37235f8`), подробности — `docs/mvp/upgrade-6.0.19/`. В оригинални upstream файлове (маркирани `databackd (upgrade 6.0.19)`, **запазете ги на ръка**):
- **Лицензът на Anagami Finance View е изключен** (`9f05fe3`): `config/databackd.php` → `license_check` (`DATABACKD_LICENSE_CHECK`, по подразбиране `false`), helper `databackd_license_check()` в `app/Helper/databackd.php`; `app/Providers/FortifyServiceProvider.php` (loginView) и `app/Http/Controllers/SuperAdmin/FrontendController.php` (`loadLoginPage`) викат `isLegal()` само ако е включен. Сайтът е в разработка и не е публичен; purchase code-ът в `global_settings` не се пипа, нов ключ не се добавя. При въвеждане на лиценза (**редът е важен**): 1) като супер админ отвори `/verify-purchase` и въведи/провери purchase code-а (маршрутите `verify-purchase`, `purchase-verified`, `hide-review-modal/*` са в `config/security-headers.php` → `restricted_routes.super_admin_only` — за гост дават 404); 2) махни трите реда от `super_admin_only`; 3) `DATABACKD_LICENSE_CHECK=true` + `php artisan config:cache`. Ако включиш проверката без валиден код, а маршрутите още са ограничени, входът пренасочва гостите към `/verify-purchase` = 404 и никой не може да влезе. Обновяването става само с ръчен merge, не през вградения (лицензиран) updater.
- `config/security-headers.php` `super_admin_only` — `verify-purchase`, `purchase-verified`, `hide-review-modal/*` (при изключена проверка гост можеше да прати съхранения purchase code към Froiden или да го презапише); `sections/raise-support-ticket-modal` — purchase code-ът само за супер админа.
- Публичните форми за потенциални клиенти и тикети (`HomeController::leadForm/leadStore/ticketForm/ticketStore`) дават 404, докато `menu.leads` / `menu.tickets` са скрити в `config/databackd.php`.
- `app/Helper/EditorSanitizer.php` (upstream HTMLPurifier) — `i[class]`, data-* на споменаванията, `pre.ql-syntax`, `data:image`, забранени класове `openRightModal`/`inModal`, без генериран `alt`.
- `config/log-viewer.php` — upstream махна middleware-а `super-admin` в полза на Gate `viewLogViewer` (`AuthServiceProvider`); при нас остават и двете (middleware на UI и на API + Gate).
- `app/Http/Controllers/ClientController.php::store` — upstream проверката на правата, след нея лимитът `max_clients` (CUS_000329).
- `resources/views/products/ajax/show.blade.php` — новият upstream дизайн + редът „Себестойност“ (S8, `@unless ($isClient)`).
- `.gitignore` — upstream добави `docs/`; при нас **не** (docs/ е в git).
- `vendor/`: `khaled.alshamaa/ar-php` е в `composer.lock`, но липсва в upstream vendor пакета и на сървъра.

### 4.11 CUS_databackd_access — права и видимост на модулите (одит 2026-10-30)  ✅

Одит `docs/mvp/audit-modules-20261030/` (правило: модул извън пакета не се вижда; нищо видимо не води към 403). Поправките в оригинални upstream файлове са маркирани `databackd`; **запазете ги на ръка** при upstream merge (или проверете, че новата версия на Anagami Finance View ги е поправила):
- Менюто (`d0e339b`): `resources/views/sections/menu.blade.php` — „Длъжности“/„Отдели“ само с модул `employees`; разделът „Човешки ресурси“ не се показва, когато единствените му модули са скрити в `config/databackd.php`.
- G01 — проверки на права, които липсват в upstream: `RolePermissionController` (middleware `manage_role_permission_setting`; вградените роли admin/employee/client не се трият), `SecuritySettingController::update` (само супер админ — настройката е глобална), `DashboardController::widget` (само admin), `OfflinePaymentSettingController` (middleware `manage_payment_setting`; `index()` пренасочва към таба offline — изгледът `payment-gateway-credentials.index` не съществува), методите за графики/суми/експорт на справките (`Finance/Expense/Task/Timelog/Lead/LeaveReportController` — същата проверка като `index()`), `Ticket{Type,Channel,Group,Agent,ReplyTemplates,EmailSetting,File}Controller`, `LeaveTypeController`, `EmployeeShiftController` (без index/show), `EstimateTemplateController`, `ProposalTemplateController` (беше `in_array('contract')` — обърнато и не е модул), `InvoiceFilesController`, `EventFileController`, `KnowledgeBaseCategoryController`.
- G02 — връзките към профил на служител/клиент: helper-и `databackd_employee_url()` / `databackd_client_url()` / `databackd_profile_access()` в `app/Helper/databackd.php` (същите условия като `EmployeeController`/`ClientController`, иначе `javascript:;`). Ползват ги `components/employee.blade.php`, `components/employee-image.blade.php`, `components/cards/{task-card,lead-card,message,ticket}.blade.php`, собственият профил в `sections/sidebar.blade.php` и `dashboard/employee/index.blade.php` (без модула → настройки на профила), DataTables на задачи/проекти/събития/дискусии/справки и ~20 изгледа (`grep -rn databackd_employee_url resources/views app`). Нов upstream код с `route('employees.show', …)` в списъци или карти трябва да мине през helper-а. Споменаванията (@mention) в контролерите не са пипани — връзката се записва в текста.
- G04 — отчитане на време само с модул `timelogs`: `AccountBaseController` (`selfActiveTimer` = null без модула → без часовник и polling в горната лента), `EmployeeDashboard` (`myActiveTimer`, `autoStoppedTimelog`; clock-in само с `attendance`, clock-out остава отворен), `TasksDataTable`/`RecurringTasksDataTable::timer()`, `ArchiveTasksDataTable` (колони timer/hoursLogged), `ProjectController` (`viewProjectTimelogPermission` = `none` без модула), формите на проект (`manual_timelog` се пази в скрито поле), `InvoiceController` (фактура по време иска и `timelogs`), `TimelogController::startTimer` (нова задача само с `tasks` + `add_tasks`), `HomeController::taskDetail` + `front/tasks/ajax/show` (`$hasTimelogs` по пакета на фирмата), `TaskSettingController` (скритите опции пазят стойността си), `TaskController` (клиентски таб), `DashboardController::weekTimelog`, `TeamRequestController` (часове на екипа), командите `SendTimeTracker`/`SendDailyTimelogReport` (без имейли без модула).
- G07 — `sections/menu.blade.php`: групите Справки/ЧР/Работа/Финанси се буферират (`ob_start`) и се показват само ако имат поне една връзка; „Финансов отчет“ само при `view_finance_report` = all. `sections/topbar.blade.php`: бутонът (+) само ако има елемент; „Добави служител“ иска и `view_employee_menu`. `DashboardController::advancedDashboard`: табовете като в `dashboard/admin.blade.php` (скрити/без модул не се сервират и през `?tab=`), поправена размяната финанси/тикети в клона по подразбиране (upstream бъг), без видим таб → личното табло. `search/index.blade.php`: опциите по модул + скриване + правото на списъка (задачата беше по `tickets`). Табла: джаджата „Дата на договор“ и `DashboardWidget::MODULE` по `employees` (беше `contracts`), зъбното колело показва само джаджи с включен модул (другите се пазят в скрити полета), картите на финанси/общ преглед/клиентското табло водят към списък само при право, банерите за отпуск/празник само с модула.
- G05 — профили: връзките към клиент (`components/client.blade.php`, `client-image`, плащания, договори, известия, обзор на проект…) минават през `databackd_client_url()`. Табовете на клиента (`clients/show.blade.php`) искат модула и правото на обработчика (кредитни известия = фактури, грешният приоритет при „Поръчки“ е поправен); обработчиците в `ClientController` проверяват модула. „Редактирай“/„Одобри“ в профила, филтрите Проект/Тип договор (JS `?? 'all'`), груповите действия. Табовете на проект Задачи/Burndown/Тикети, модулни проверки по `?tab=` в `ProjectController::show`, обзорът (задачи/плащания/разходи → `none` без модула; публичните Gantt/табло само ако са включени), задачите на шаблоните (`ProjectTemplate*`). Профил на служител: броячите, табовете Отпуски/Признания, модулни проверки в обработчиците; „Документи“ в профилните настройки само с `employees`. `ClientNoteController::edit` проверява `edit_client_note` (беше `view_client_note`).
- G03 — бутоните „+ Добави служител“ (задачи, повтарящи се задачи, проекти, шаблони) искат `employees` + `add_employees` + `view_employee_menu`; „+ Категория“ в шаблоните — `add_task_category`; „+ Добави клиент“ (`client-selection-dropdown`) — модул `clients`; „Добави служител“ в (+) спазва лимитите на пакета. Отделите в проектните форми и филтрите по отдел (проекти, архив, календар, време) само с `employees` (JS проверките за „Изчисти“ с `?? 'all'`).
- G06 — финанси: `PaymentController` пуска `accountList` (банкови сметки + курс) и при модул фактури/разходи; колоните Код/Проект/Поръчка № в плащанията и Проект в договорите по модул; проект и банкова сметка във формите на фактури, повтарящи се фактури, разходи, повтарящи се разходи и договори само с модула (в редакция стойността се пази в скрито поле — `update()` иначе я нулира); „Получено плащане“ при нова фактура само с `payments`; „Добави кредитно известие“ и „Добави плащане“ по правата на целевата страница; връзката към настройките за напомняния и бутонът за повтарящи се фактури по правата; `UnitTypeController`/`InvoicePaymentDetailController` проверяват `manage_finance_setting` (беше `manage_project_category`); груповите действия на разходите проверяват `delete_expenses`/`approve_expenses` (бяха `*_employees`), падащият статус само при `approve_expenses` = all, „Редактирай“ на повтарящ се разход при `manage_recurring_expense`; връзките от банкова транзакция само при модул + право.
- G11 — databackd: „Шаблон на сметкоплана“ само за администратор; връзките между модулите (П&Л → записи, източници/интеграции → импорт/партиди/записи, синхронизация ↔ интеграции, консолидация → юридически лица/валутни курсове) проверяват `databackd_module()`; синхронизацията иска и модул фактури или разходи; „Маржове“ — модули `invoices` + `products` (меню и `canViewReport()`); отчетите по ЗСч предлагат консолидационни групи само с `databackd_analytics` (`StatutoryReport` аргумент `withGroups`); „Екипи“: целевите екрани на резултата по пакета, клиентът не получава вътрешна връзка (само файл/външен адрес); проектът на заявка за екип — само с `projects` и достъп; трохите `/account/databackd` и `/account/databackd/<група>` не са връзки (`components/app-title.blade.php`).
- G08 — проверка на модула на сървъра (middleware в конструктора, `user_modules()`, коментар `databackd (audit 2026-10-30, G08)`) в 45 контролера: справките (`reports` + модулът на справката, както в менюто), празници, признания/награди/повишения/паспорти (`employees`), обявления, бележки/документи/категории на клиенти (`clients`; клиентът минава за своите бележки/документи), подзадачи/файлове/етикети/категории на задачи (`tasks`), шаблони/типове/файлове на договори, категории/файлове на продукти, бележки/файлове на потенциални клиенти и сделки (`leads`), дискусии/файлове/категории/етикети/оценки/Гант връзки на проекти (`projects`), шаблоните за имейли на сделки. `LeadReportController` има конструктор с аргумент — проверката е в него.
- G09 — известията: `notifications/_notification_item.blade.php` пуска известие на изключен модул през `notification_fallback` (без връзка към 403; карта тип → модул); известията за пробен период/лиценз/план водят към таблото, докато `pages.billing` е скрит.
- G10 — настройките на известията (имейл, Slack, push) показват само превключвателите на модули в пакета и не скрити от config — скритите се пращат обратно в скрити полета, защото записът нулира всичко непратено; персонализираните полета предлагат само групи на налични модули.
- G12 — табове, бутони и джаджи на скрити от config функции: табовете Присъствие/Тикети/Смени при служител, Оферти/Поръчки при проект, GDPR при клиент, бутонът „Съобщение“ в проекта, „Абонаменти“ във фактурите, таб „Оферти“ във финансовите настройки, джаджите за присъствие/тикети/отпуски/последващи действия/оферти/предложения в таблата и профила на служител.

### 4.12 CUS_databackd_dashboard_ui — табла и избор на клиент (2026-11-12)  ✅

Промени в оригинални upstream изгледи — **запазете ги на ръка** при upstream merge:
- `resources/views/dashboard/admin.blade.php` — филтърът по дата (`#datatableRange2`) се вижда и в таб „Преглед“ (upstream го скрива с `d-none` за `tab=overview`); `OverviewDashboard` вече чете `startDate`/`endDate`.
- `resources/views/dashboard/ajax/overview.blade.php`, `resources/views/dashboard/employee/widgets/documents.blade.php` — датата на изтичащите документи е във формата на фирмата (`translatedFormat(company()->date_format)` вместо твърдото `'M d, Y'`).
- `resources/views/components/client-search-option.blade.php` — в падащото меню за клиент (фактури, проекти, заявки за оферта) основният ред е името на фирмата (голямо и удебелено), под него лицето за контакт и имейлът; без фирма — името на лицето.

Демо данни за текущия период: `database/seeders/Databackd/Demo/50CurrentPeriodDemoSeeder.php` (добавя, не трие; виж коментара в класа) — пуска се на production през tinker, никога `databackd:demo`.

## 5. Plugin модули (`Modules/`)

> **Корекция 2026-07-01:** директна проверка на production сървъра показва, че `Modules/` съдържа **само** `LanguagePack`. `Asset` и `Payroll` **не са инсталирани** — предишна версия на този документ бе грешна по този пункт (описваше ги като закупени/налични add-on-и). Това е **съзнателно решение**, не пропуск: решихме, че не ни трябват засега и няма да ги инсталираме. Таблицата по-долу описва какво биха правили тези модули, ако/когато решението се промени — не текущото състояние.

| Модул | Версия | Предназначение | Статус на production |
|-------|--------|----------------|----------------------|
| `LanguagePack` | bundled | Управление на преводи | ✅ инсталиран |
| `Asset` | 2.1.22 | Фирмени активи (лаптопи, монитори, лицензи) + история на заемане + поддръжка | ❌ не инсталираме засега |
| `Payroll` | 2.1.8 | Заплати, месечни фишове, payroll цикли, извънреден труд, разходи | ❌ не инсталираме засега |

### Install pattern за нов/обновен модул

```bash
# 1. Разархивирай в Modules/<Name>/
# 2. Регистрирай в storage/app/modules_statuses.json
# 3. composer dump-autoload
# 4. php artisan module:migrate <Name> --force
# 5. php artisan module:seed <Name>
# 6. php artisan module:enable <Name>
```

На production, миграция след pull на нова версия на модул:
```bash
ssh backoffix-new 'cd /var/www/backoffix && sudo -u www-data php artisan module:migrate <Name> --force'
```

### Специфики

- **Payroll API е stub-нат.** `Modules/Payroll/Routes/api.php` е празен (оригиналът е запазен като `api.php.disabled`), защото `PayrollApiController` зависи от `Modules\RestAPI\…ApiBaseController`, който идва от RestAPI модула — а него не го инсталираме. **Web routes работят напълно.** При update на Payroll не връщай оригиналния `api.php`.
- Всеки `Modules/<X>/Resources/lang/*` (без `eng/`) е gitignored от собствения `.gitignore` на модула — преводите живеят на сървъра.
- **Забележка за `modules_statuses.json`:** към 2026-07-01 файлът съдържа само `{"LanguagePack": true}` — съответства на реалността (Asset/Payroll не са инсталирани, виж корекцията в началото на Раздел 5). При проблем с активиране на модул — свери този файл.

---

## 6. Multi-tenancy при миграции/сийдъри

Всички tenant модели ползват `HasCompany` trait + глобален `CompanyScope`, който авто-филтрира по `company_id`. При миграция/сийдър, който трябва да види всички компании:

```php
Model::withoutGlobalScope(CompanyScope::class)->...
```

---

## 7. Git конвенции

- **НЕ добавяй `Co-Authored-By` trailer-и** към commit-ите (историята е чистена от тях).
- Solo repo — force-push на `main` е ОК при изрична заявка.
- BG преводите са в git от S7 (`resources/lang/bg/`); demo данните и останалите езици живеят само на production сървъра.

Commit съобщения за къстъм работа:
```bash
git commit -m "fix(invoices): guard null company address on show view"
git commit -m "feat(custom-css): inject global_settings.custom_css into head"
```

---

## 8. Свързани документи

- [CLAUDE.md](../CLAUDE.md) — пълен project overview, deploy workflow, server достъп
- [app-vs-app2-resources-vs-resources1-differences.md](app-vs-app2-resources-vs-resources1-differences.md) — исторически diff анализ на премахнатите backup-и
