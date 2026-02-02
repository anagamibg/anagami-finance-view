# Handoff S6 → S10: Преглед на сигурността

## Статус
**Готово.** Прегледът на сигурността на фаза 1 е извършен от 10 пакета (P1–P10), критичните и високите находки в кода са поправени, протоколът е в `docs/mvp/security/2026-10-security-review.md`. **Поправките са само локални — нищо не е качено на production.** На production има две спешни неща, които са описани по-долу и в протокола (раздел 3): достъпен без вход restore инструмент и компрометиран `.env`.

## Какво е направено
- **122 находки** (11 критични, 11 високи, 34 средни, 66 ниски; 8 различни критични причини) — протокол `docs/mvp/security/2026-10-security-review.md`, по пакети в `docs/mvp/security/findings/P1.md … P10.md`.
- **Защитни слоеве (нови класове)**, всички в `app/Http/Middleware/Databackd/` освен ако е посочено:
  - `SecurityHeaders` (глобален) — `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Frame-Options: SAMEORIGIN` (публичните формове, вградени в чужди сайтове, са изключени), `Content-Security-Policy-Report-Only`, HSTS само по HTTPS. Настройки: `config/security-headers.php`.
  - `FinApiGate` (глобален) — за `/api/databackd/*`: HTTPS, метод, размер на тялото, без CORS. `FinApiTokenAuth` — rate limit на откази и на токен, еднакъв отговор, без токен в query/тяло. `app/Services/Databackd/Data/FinApiAudit.php` пише одит лог `storage/logs/databackd-api-<дата>.log` (никога токена), `FinApiExceptionHandler` — отговори без stack trace. Настройки: `config/databackd-api.php`.
  - `XssLinearMerge` (глобален) — заменя `Froiden\Envato\Middleware\XSS` в `$middleware` на `app/Http/Kernel.php` (същото чистене, но без квадратичното `Request::merge()`, което даваше неавтентикиран DoS).
  - `RestrictVendorMaintenanceRoutes` (глобален + група `web`) — `/update-database`, `/clear-cache`, `/refresh-cache`, `/admin/update-version/*` само за суперадмин; `/check-env` → 404.
  - `VerifyCsrfTokenPathOnly` (група `web`) — CSRF изключенията се сравняват само по път (преди `?x=-webhook` изключваше проверката).
  - `AuthHardening` (група `web`) — `check-code`/`resend-code` вързани към login-а в сесията и с лимит; `choose-workspace` проверява собствеността; еднакъв отговор на `forgot-password`; ротация на `remember_token`.
- **Нови помощни класове:** `app/Exports/Databackd/Concerns/NeutralizesFormulas.php` (trait за всички 8 Excel експорта), `app/DataTables/Databackd/ColumnWhitelist.php`, диск `databackd_private` в `config/filesystems.php` (`storage/app/databackd`, извън `public/`) с `AnalysisRequest::storeUpload()/resolveDownload()/deleteUpload()`.
- **Преработени:** `GoogleSheetsReader` (SSRF — публични методи `assertSafeUrl(string, bool $resolve = true, bool $redirect = false): string`, `assertSourceUrl`, `isPublicIp`, `isLocalDevelopment`), `FormulaEngine` (лимити), `FinEntryImporter` (лимити 50 000 реда / 300 колони / 20 листа, валидация на структурата), `PeriodBuckets`/`ReportFilters`/`ReportBuilder` (whitelist-и), всички `*Export`/`*Pdf` в `app/Exports/Databackd/` (без `enable_php`, без remote fetch).
- **Upstream поправки (извън `Databackd`; презаписват се при ъпдейт на AFV — записани в `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md`):** `app/Models/UserAuth.php` (3 заявки с bindings), `enable_php` е махнат от 12 файла (15 места: `HomeController`, `EstimateController`, `InvoiceController`, `ProposalController`, `PublicUrlController`, `ProposalTemplateController`, `CreditNoteController`, `ContractController`, `EstimateTemplateController`, `SuperAdmin/InvoiceController`, `Notifications/MonthlyAttendance`, `Notifications/DailyTimeLogReport`), изтрити `public/backup-and-restore/` и `public/check.php`.
- **Конфигурация:** `config/session.php` (бисквитката `secure` следва `APP_URL`), `config/hashing.php` (bcrypt 12), `config/log-viewer.php` (API зад `auth` + `super-admin`), `config/cors.php` (коментар), `config/sentry.php` (без SQL bindings), `config/mail.php` (`MAIL_VERIFY_PEER`), `config/filesystems.php`, `.gitignore` (`.env.*`, бекъпи, дъмпове, ключове, `demo-passwords*`, `.claude/`, `/Modules/`).
- **Artisan команда:** `php artisan databackd:check-security [--only=static,pure,isolation,roles,api,http,auth] [--show]` — 83 проверки за изолацията, правата, SSRF/формули/SQL whitelist-ите, API токена, заглавията и auth ендпойнтите. Само локална база (`*_local`), всичко в транзакция, която се връща назад; съдържа контролни проби.

## Отклонения от промпта
- **Поправки в upstream код извън `Databackd`** (SQL injection в `UserAuth`, `enable_php` на 15 места, изтрити `backup-and-restore` и `check.php`): според `COMMON-RULES-2` upstream файловете „не са на S6“, но не са и на друга сесия, а проблемите са критични/високи, затова са поправени с минимални промени и документирани.
- **Не е правена смяна на пакети** (P10): `composer update` не е възможен локално (частни Froiden хранилища), а ъпдейтите на `vendor/` изискват тест през реална уеб заявка — планът е в `findings/P10.md`.
- **Не са сменяни пароли** (демо паролата е само предложена за смяна).
- `database/seeders` не са променяни, няма нови миграции (предложените `expires_at`/обхват на API токена са описани в `P6.md`).

## Не е направено / известни проблеми
- Production не е променян — **всичко от „За S10“ чака изпълнение.**
- Не са поправени views, routes, преводи и файловете на S8 — указанията са в протокола (раздел 6.Б).
- Приети рискове: пълен достъп на служителя до финансовите модули (P2-05), липсата на JOIN `company_id` в някои агрегатни заявки (P1-04, защитено на запис), Quill 1.3.7 (P10).
- Модулът „Gaps“ на S8 е прегледан само в момента на пускането на пакетите (виж протокола, раздел 8) — **пуснете `databackd:check-security` и рецептите на `P1.md`/`P2.md` върху финалния му вид.**
- `FinImportController::progress()` стартира `queue:work` до 20 s вътре в уеб заявка (P3) — да се прецени при `QUEUE_CONNECTION != sync`.

## Какво трябва да знае следващата сесия
- В `app/Http/Kernel.php` са добавени глобални middleware (виж по-горе) — **деплойвайте новите класове ПРЕДИ `Kernel.php`**, а след това `php artisan optimize:clear && php artisan config:cache && php artisan route:cache`; иначе всяка страница дава 500. На сървъра няма `composer`; новите класове са в `app/` (PSR-4) и се зареждат без `dump-autoload`, както при S1–S5.
- `SESSION_SECURE_COOKIE` вече е `true` автоматично при `APP_URL=https://…` — вход по `http://` няма да задържи сесията (желано след включване на пренасочването към HTTPS).
- HSTS е с `includeSubDomains` по подразбиране (`SECURITY_HSTS_INCLUDE_SUBDOMAINS`): ако има поддомейни само по HTTP, сложете `false` в `.env`.
- Ако API-то върне `403 HTTPS is required` след деплой, прокси серверът не подава `X-Forwarded-Proto` — поправете прокси настройките или временно `DATABACKD_API_REQUIRE_HTTPS=false` (и `TrustProxies::$proxies` не бива да остава `'*'`).
- Не командвайте `/clear-cache` и `/refresh-cache` на production — изпълняват `optimize` и нулират rate limit броячите.
- Не пускайте `databackd:demo --force` на production.

## Как да се провери
```bash
php artisan databackd:check-security                  # 83 от 83
php artisan databackd:check-formulas && php artisan databackd:check-consolidation && php artisan databackd:check-forecasts
php -d memory_limit=1G docs/mvp/tools/smoke.php databackd. --ajax                                   # админ
php -d memory_limit=1G docs/mvp/tools/smoke.php databackd. --email=g.petrov@demofinans.bg           # служител
php -d memory_limit=1G docs/mvp/tools/smoke.php databackd. --email=p.yordanov@balkan.bg             # клиент (очаквани 403)
```
Очаквани „FAIL“ редове: `counterparties.show` (пренасочва към списъка), за служителя `gaps.alerts.create` и `gaps.onboarding.index` (само за админ), за клиента — 403 на всичко извън „Teams“.

## Локални файлове извън git
`.env` (не е променян), `.env.stage.backup` (съдържа тайни, вече в `.gitignore`), `.claude/`, `Modules/`. Приложенията на агентите са в репото (`docs/mvp/security/findings/`); техните тестови скриптове са временни и не са запазени.

## За S10: какво да се деплойне

### 0. Спешно, независимо от деплоя (production, ръчно или със съгласие на собственика)
1. Изтрийте `~/public_html/backup-and-restore/` и `~/public_html/check.php` (достъпни без вход; `rsync` не трие). 2. Ротация на паролата на базата и на `APP_KEY` + преглед за злоупотреба (`.env` е бил публично изтеглим 01.10–27.10) — внимание към криптираните колони (`P8.md`, `2026-10-security-review.md` § 6). 3. Пренасочване HTTP→HTTPS + HSTS + коренов `.htaccess` (текст в `P7.md`/`P8.md`; LiteSpeed изисква рестарт). 4. 2FA на 2-мата суперадмини и 4-мата админи. 5. `chmod 700` на `~/deploy-backup-*`; решение за `~/public_html_5.4.6_old`.

### 1. Файлове (rsync от корена на проекта към `~/public_html/`)
**Нови (13):**
- `app/Console/Commands/Databackd/CheckSecurity.php`
- `app/DataTables/Databackd/ColumnWhitelist.php`
- `app/Exports/Databackd/Concerns/NeutralizesFormulas.php`
- `app/Http/Middleware/Databackd/AuthHardening.php`
- `app/Http/Middleware/Databackd/FinApiGate.php`
- `app/Http/Middleware/Databackd/RestrictVendorMaintenanceRoutes.php`
- `app/Http/Middleware/Databackd/SecurityHeaders.php`
- `app/Http/Middleware/Databackd/VerifyCsrfTokenPathOnly.php`
- `app/Http/Middleware/Databackd/XssLinearMerge.php`
- `app/Services/Databackd/Data/FinApiAudit.php`
- `app/Services/Databackd/Data/FinApiExceptionHandler.php`
- `config/databackd-api.php`
- `config/security-headers.php`

**Променени (64) — `app/Http/Kernel.php` се качва последен:**
- `app/Console/Commands/Databackd/SendSavedReports.php`
- `app/Console/Commands/DatabackdDemo.php`
- `app/DataTables/Databackd/Data/FinEntriesDataTable.php`
- `app/DataTables/Databackd/Data/FinImportBatchesDataTable.php`
- `app/DataTables/Databackd/Teams/TeamRequestsDataTable.php`
- `app/Exports/Databackd/Analytics/ConsolidationExport.php`
- `app/Exports/Databackd/Analytics/RatiosExport.php`
- `app/Exports/Databackd/Data/FinEntriesExport.php`
- `app/Exports/Databackd/Planning/VarianceExport.php`
- `app/Exports/Databackd/Reports/CashflowExport.php`
- `app/Exports/Databackd/Reports/CashflowPdf.php`
- `app/Exports/Databackd/Reports/CounterpartyExport.php`
- `app/Exports/Databackd/Reports/CounterpartyPdf.php`
- `app/Exports/Databackd/Reports/PivotExport.php`
- `app/Exports/Databackd/Reports/PivotPdf.php`
- `app/Exports/Databackd/Reports/PnlExport.php`
- `app/Exports/Databackd/Reports/PnlPdf.php`
- `app/Http/Controllers/ContractController.php`
- `app/Http/Controllers/CreditNoteController.php`
- `app/Http/Controllers/Databackd/Analytics/ConsolidationRuleController.php`
- `app/Http/Controllers/Databackd/Analytics/RatioBuilderController.php`
- `app/Http/Controllers/Databackd/Data/Api/FinEntryApiController.php`
- `app/Http/Controllers/Databackd/Data/FinApiTokenController.php`
- `app/Http/Controllers/Databackd/Data/FinDataSourceController.php`
- `app/Http/Controllers/Databackd/Data/FinImportBatchController.php`
- `app/Http/Controllers/Databackd/Data/FinImportController.php`
- `app/Http/Controllers/Databackd/Reports/SavedReportController.php`
- `app/Http/Controllers/Databackd/Teams/AnalysisRequestCommentController.php`
- `app/Http/Controllers/Databackd/Teams/AnalysisRequestController.php`
- `app/Http/Controllers/EstimateController.php`
- `app/Http/Controllers/EstimateTemplateController.php`
- `app/Http/Controllers/HomeController.php`
- `app/Http/Controllers/InvoiceController.php`
- `app/Http/Controllers/ProposalController.php`
- `app/Http/Controllers/ProposalTemplateController.php`
- `app/Http/Controllers/PublicUrlController.php`
- `app/Http/Controllers/SuperAdmin/InvoiceController.php`
- `app/Http/Kernel.php`
- `app/Http/Middleware/Databackd/FinApiTokenAuth.php`
- `app/Http/Requests/Databackd/Analytics/StoreRatioRequest.php`
- `app/Http/Requests/Databackd/Data/FinEntry/StoreFinEntry.php`
- `app/Http/Requests/Databackd/Teams/AnalysisRequest/DeliverableAnalysisRequest.php`
- `app/Http/Requests/Databackd/Teams/AnalysisRequest/StoreAnalysisRequest.php`
- `app/Http/Requests/Databackd/Teams/AnalysisRequest/UpdateAnalysisRequest.php`
- `app/Mail/Databackd/SavedReportMail.php`
- `app/Models/Databackd/AnalysisRequest.php`
- `app/Models/UserAuth.php`
- `app/Notifications/DailyTimeLogReport.php`
- `app/Notifications/Databackd/AnalysisRequestCommented.php`
- `app/Notifications/Databackd/AnalysisRequestCreated.php`
- `app/Notifications/Databackd/AnalysisRequestStatusChanged.php`
- `app/Notifications/MonthlyAttendance.php`
- `app/Services/Databackd/Analytics/FormulaEngine.php`
- `app/Services/Databackd/Data/FinEntryImporter.php`
- `app/Services/Databackd/Data/GoogleSheetsReader.php`
- `app/Services/Databackd/Reports/PeriodBuckets.php`
- `app/Services/Databackd/Reports/ReportFilters.php`
- `config/cors.php`
- `config/filesystems.php`
- `config/hashing.php`
- `config/log-viewer.php`
- `config/mail.php`
- `config/sentry.php`
- `config/session.php`

**Да се ИЗТРИЯТ на сървъра:** `public/backup-and-restore/` (10 файла), `public/check.php`.

Няма миграции, няма seeders, няма нови lang файлове (`resources/lang/**` не е пипан от S6).

### 2. След качването
```bash
cd ~/public_html
php artisan optimize:clear && php artisan config:cache && php artisan route:cache
mkdir -p storage/app/databackd/databackd/analysis   # частният диск за файловете към заявки за анализ (права като на storage/)
```
Преместване на старите файлове (нужно, за да спрат да бъдат публични; `AnalysisRequest::resolveDownload()` пробва първо новия диск, после стария път): `public/user-uploads/databackd/analysis/*` → `storage/app/databackd/databackd/analysis/`; след това `Require all denied` за `public/user-uploads/databackd/` в `.htaccess`.

### 3. `.env` на production (да се провери/зададе)
`APP_DEBUG=false` (вече е) и `global_settings.app_debug = 0` в базата · `LOG_LEVEL=warning` · `SESSION_SECURE_COOKIE` не е нужен при `APP_URL=https://databackd.com` · по избор `SECURITY_HSTS_INCLUDE_SUBDOMAINS=false`, `MAIL_VERIFY_PEER=true`, `LOG_VIEWER_ENABLED=false`.
Проверки: `grep '^APP_KEY=' .env | shasum -a 256 | cut -c1-10` НЕ е `fa9430d687` (примерният ключ от репото) · съществува `storage/installed` (без него `/install/*` е отворен) · `.env` е `600`, няма `777` · демо акаунтите на company 17 нямат публичната парола по подразбиране (`DatabackdDemoSeeder::PASSWORD`) · `SELECT COUNT(*) FROM fin_api_tokens WHERE token_hash = SHA2('<демо токена от 41DataDemoSeeder>', 256)` = 0.

### 4. Проверка след деплоя
`curl -sI https://databackd.com/login` → `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Strict-Transport-Security`, `Content-Security-Policy-Report-Only` · `curl -I https://databackd.com/backup-and-restore/` → 404 · `curl -I https://databackd.com/log-viewer/api/files` → 401/302, не 200 · `curl -I http://databackd.com/login` → 301 към https · `curl -X POST 'https://databackd.com/login?x=-webhook'` без токен → 419 · API без токен → 401 · вход като админ (и 2FA с имейл код, ако е включена), като служител и като клиент; запазване на форма с HTML поле (регресия на `XssLinearMerge`); PDF на фактура и на справка; качване и сваляне на файл към заявка за анализ; импорт на примерния шаблон. Локално след деплоя: `databackd:check-security` (повторно).

### 5. Рискове
- Глобални middleware в `Kernel.php` (грешка в клас = 500 навсякъде) → качвайте последен, имайте готов откат: предишният `Kernel.php` (в git) + `route:clear`.
- `XssLinearMerge` замества upstream `XSS` — при регресия в полетата с HTML върнете реда `XSS::class` в `$middleware` (поправката за DoS отпада).
- `AuthHardening` променя потока на входа с имейл код/избор на работно пространство — тествайте с реален акаунт, преди да затворите сесията.
- Бисквитката `Secure`: изисква достъп през HTTPS.
- Смяната на `APP_KEY` прекъсва сесиите и криптираните стойности (виж протокола).
- Зависимостите (P10) не са ъпдейтвани — планът с реда и командите е в `findings/P10.md`.

### 6. Какво е за други сесии/файлове (не е в този commit)
Виж протокола, раздел 6.Б: views (S9), routes (S8), преводи (S7), Gaps PDF/Camt053 (S8), `.env.example`/`git rm --cached .env.dev`/публични демо пароли в `docs/` (S10 преди `push`).
