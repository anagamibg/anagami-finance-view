# A11 — ядро (core): helpers, providers, middleware, config, routes, лиценз, преглед по сигурността

Обновяване 6.0.10 → 6.0.19, worktree `ws-merge`, клон `upstream/worksuite-v6.0.19` (`9f05fe3`). База `databackd_upgrade_local`.

## 1. Какво е променено (5 файла, всички маркирани `databackd (upgrade 6.0.19)`)

| Файл | Промяна | Защо |
|---|---|---|
| `app/Http/Middleware/Databackd/AuthHardening.php` (`guardEmailTwoFactor`) | `user_id` вече не е задължителен; ако е подаден, пак трябва да е `login.id` | **Регресия от merge-а:** 6.0.19 махна скритото `user_id` от `auth/two-factor-challenge.blade.php` и от `resendCode()` (контролерът взема идентичността само от `login.id`), а нашият guard изискваше `user_id === login.id` → **всеки** вход с код по имейл (`check-code`/`resend-code`) щеше да е 403. Проверено с in-process скрипт (виж §8). |
| `app/Helper/start.php` (`pdfFixArabic`) | `if (!class_exists(\ArPHP\I18N\Arabic::class)) { return $html; }` преди `new \ArPHP\I18N\Arabic()` | искане на координатора: `khaled.alshamaa/ar-php` е в `composer.json/lock`, но липсва във `vendor/` → PDF с арабски текст = fatal error. Други места с ArPHP: **няма** (само коментар в същата функция). |
| `app/Helper/EditorSanitizer.php` | `'i'` → `'i[class]'`; `span` + `data-id|data-value|data-index|data-denotation-char|data-link`; нов `pre[class|spellcheck]`; `HTML.DefinitionID/Rev` + `addAttribute()` за data-* (`data-id` = Number, `data-link` = URI) и `spellcheck` (Enum true/false) | искания на A10 (иконите в `x-cards.data-row html` губеха `class` → без цвят/точки на статусите) и A09 (споменаванията губеха `data-id` → при редакция на задача `mention_user_ids` = празно; блоковете с код ставаха текст). `style` остава ограничен до 5-те CSS свойства, `on*`/`id`/`javascript:` се махат — 11/11 проверки (§8). |
| `config/security-headers.php` (`restricted_routes.hidden`) | + `down/*`, `up/*` → 404 за всички | дистанционният превключвател на Froiden (`vendor/froiden/envato` `AppBoot::down()/up()`, bcrypt хеш, който знае само Froiden): `artisan down --secret=froiden`, когато няма purchase code, и `artisan up` по всяко време. Няма легитимен маршрут `up/…`/`down/…` (проверено в `route:list`). |

| `routes/web-public.php` | `->middleware('throttle:10,1')` на `POST /lead-form/leadStore` (`front.lead_store`) и `POST /lead-form/ticket-store` (`front.ticket_store`) | искане на координатора (находка на A07, виж и 5.1): анонимни, без CSRF, компанията се избира по id. Без именуван limiter Laravel ключува госта по `домейн|IP` → двата маршрута делят **общо** 10 заявки/мин на IP (проверено: 11-ата → 429, след това и другият маршрут → 429). |

Нов езиков файл не е нужен (`ui-platform.php` не е създаден).

## 2. BOTH файлове — смислово сливане (база `37235f8` / наши `main` / upstream `vendor/worksuite` / резултат)

| Файл | Наше | Upstream | Резултат |
|---|---|---|---|
| `app/Console/Kernel.php` | 3 AFV задачи (S8) | `ChargeWorldpaySubscriptions` (+ use, `$commands`), `worldpay-subscription-charge` 02:05, `send-invoice-reminder` daily → **hourly** | ✅ и двете; виж §6 |
| `app/Helper/start.php` | CUS_000329: `checkCompanyPackageIsValid` (clients+invoices), `checkCompanyCanAddMoreClients/Invoices`, `clearCompanyValidPackageCache` | `trim_editor()` → HTMLPurifier, `clean_editor()`, `pdfFixArabic()`, `pdfLoadHtml()` | ✅ всичко; + guard за ar-php |
| `app/Models/UserAuth.php` | S6: 3× `DB::select(... ?)` с bindings | `random_int()` за 2FA кода | ✅ |
| `app/Models/Module.php` | S8 `databackdModules()` | 3 нови права за имейлите на сделки | ✅ |
| `app/Observers/CompanyObserver.php` | S8 `databackd_modules_for_type()` ×2 | LeadForm по подразбиране, настройки за напомняния, `allowed_work_from` | ✅ |
| `app/Providers/AppServiceProvider.php` | S1/S5/S8 View Composer на менюто, `Company::created` → сметкоплан | `ExceptionHandler` → `App\Exceptions\ApiExceptionHandler`, `refuseInsecureAppKey()` | ✅ (виж §3 и „За деплоя“) |
| `app/Providers/RouteServiceProvider.php` | `mapDatabackdRoutes()`, `mapDatabackdApiRoutes()` | `RateLimiter::for('two-factor')` 5/мин по `login.id` | ✅ |
| `app/Http/Controllers/RegisterController.php` | S7 `__()` в 3 `Reply::*` | ограничение по домейн на поканата, `abort_if(!isSetupAllowed())` | ✅ |
| `composer.json` | `app/Helper/databackd.php` в `autoload.files`, описание | `ezyang/htmlpurifier`, `khaled.alshamaa/ar-php`, пренареждане | ✅; `vendor/composer/autoload_files.php` съдържа `start.php` + `databackd.php`; от `composer.lock` липсва във `vendor/` **само** ar-php |
| `config/log-viewer.php` | S6 API: `web, auth, super-admin` | UI без `super-admin` (в Gate), API `web, auth` | ✅ `super-admin` и на UI, и на API + Gate `viewLogViewer` |
| `routes/web.php` | S10: изтрити `/test-broadcast`, `/debug-broadcast` | същото + нови маршрути (deal emails, lead forms, product grid/resources, auto-stopped memo) | ✅ всички нови са в групата `auth` |
| `routes/web-public.php` | S10: `sync-user-permissions` с `auth` | `{hash}` вместо `{id}`, `throttle:two-factor`, `cropper` с `auth`, Worldpay | ✅ |
| `routes/web-settings.php` (UP) | — | `deal-email-templates`, `update-reminder-setting` | ✅ в група `auth` |
| `.env.example` | празен `APP_KEY=` | празен `APP_KEY=` + коментар | ✅ |
| `.gitignore` | S6/P7 блок, `!resources/lang/bg/` | `public/app-icons/cache/*`, инсталатори, **`docs/`** | ✅ upstream редовете без `docs/` |
| `.env.dev`, `public/backup-and-restore/*` (BOTH) | изтрити | upstream ги връща (+ `require-auth.php`) | ✅ остават изтрити (и `public/check.php`) |

UP файловете (`LoginController`, `AttemptToAuthenticate`, `Common`, `Files`, `EditorSanitizer`, `ApiExceptionHandler`, `VerifyCsrfToken`, `AccountSetupRequest`, `User`, `Role`, `EmailNotificationSetting`, `ResetPassword`, `CustomFieldsObserver`, `Auth/CustomConfig/Event/FortifyServiceProvider`, `config/services.php`, `config/froiden_envato.php`, `public/version.txt` = 6.0.19, `two-factor-challenge`) — прочетени; без конфликт с наши промени. `storage/framework/lsp-*.php` не съществува в worktree-а (IDE артефакт, махнат от координатора). `_ide_helper*.php`, `.phpstorm.meta.php`, `composer.lock` — генерирани, upstream.

## 3. S6 — точка по точка

| S6 точка | Статус |
|---|---|
| `Kernel` global: `SecurityHeaders`, `FinApiGate`, `RestrictVendorMaintenanceRoutes` | ✅ (`git diff main` = 0 реда; upstream не пипа `Kernel.php`) |
| `XssLinearMerge` вместо `Froiden\Envato\Middleware\XSS` | ✅; vendor `froiden/envato` 5.3.0 е **идентичен** с 6.0.10 (`diff -r`), т.е. поведението е същото |
| `web`: `VerifyCsrfTokenPathOnly` вместо `VerifyCsrfToken` | ✅; наследява `$except` → новото `worldpay/3ds-return` идва автоматично (GET/POST, рендерира само `@json($reference)` + `postMessage` към същия origin — безопасно) |
| `web`: `AuthHardening` | ✅ + поправката от §1. Съчетание с upstream: `LoginController::twoFactorLoginUser()` (само `login.id`, `hash_equals`, проверка на изтичане), `throttle:two-factor` 5/мин по `login.id` **и** нашите 5/600 s (`check-code`) / 1/60 s + 5/900 s (`resend-code`) — броят се и двете, по-строгото печели; upstream вече не нулира кода при грешка → нашият лимит 5/600 s е единствената защита срещу изброяване в 10-минутния прозорец (запазен) |
| `setup_account` | ✅ `AuthHardening` (отказ, ако има какъвто и да е потребител) е по-строг от новото `AccountSetupRequest::isSetupAllowed()` (SaaS: няма супер админ) — съвместими |
| `UserAuth::validateLoginActiveDisabled()` bindings | ✅ 3× `?` |
| без `enable_php` | ✅ `grep` — няма (само коментари в AFV PDF класовете); `config/dompdf.php` `enable_php => false`; новото `pdfLoadHtml()` слага само `defaultFont` |
| без `public/backup-and-restore`, `public/check.php` | ✅ (upstream ги връща — в git остават изтрити) |
| без debug маршрути | ✅ (upstream също ги махна) |
| `config/log-viewer.php` | ✅ UI и API: `super-admin` + Gate `viewLogViewer` (upstream) + `AuthorizeLogViewer`. Гост → 302 login; админ на компания и служител → 403; супер админ → 200 (UI и `/log-viewer/api/files`) |
| `config/session|hashing|filesystems|sentry|mail|cors`, `security-headers`, `databackd-api` | ✅ upstream не ги пипа |
| `sync-user-permissions` | ✅ `auth` (upstream) + `super_admin_only` (наш) → гост 404 |
| `cropper` | ✅ upstream `auth` → гост 302 |
| `.env.example` / `.env.dev` / `.gitignore` | ✅ |
| `bar-chart`/`pie-chart` `@json` (S10, A10) | ✅ на място |
| `FinApiExceptionHandler` срещу новия `App\Exceptions\ApiExceptionHandler` | ✅ **няма конфликт.** Ред: `Froiden ApiServiceProvider::register()` → Froiden handler; `AppServiceProvider::boot()` → `App\Exceptions\ApiExceptionHandler` (всички `api/*`; при `debug=false` вече не гърми с `$response`); `FinApiGate` (по време на заявката) → `app()->singleton(...FinApiExceptionHandler)` само за `api/databackd/*` — `bind()` изтрива старата инстанция, а kernel-ът резолвва handler-а при изключението → за нашия API печели нашият. Трите класа наследяват `App\Exceptions\Handler`. Потвърдено от `check-security --only=api` (14/14). |
| `databackd:check-security` | ✅ **83/83** (static 26 · pure 14 · isolation 10 · roles 6 · api 14 · http 8 · auth 5) преди и след моите промени |

## 4. Лиценз / phone-home

- `isLegal()` е само на 2 места — `FortifyServiceProvider::loginView` (ред 190) и `SuperAdmin/FrontendController` (ред 413), и двете зад `databackd_license_check()` (`9f05fe3`, не са пипани).
- `vendor/froiden/envato` (5.3.0) е **същият** като в 6.0.10 → няма нова логика за лиценз. Upstream diff-ът (`app routes resources/views config Modules`) не добавя нито `isLegal`, нито нови извиквания към Froiden; `config/froiden_envato.php` — само коментар.
- Останалите контакти с Froiden **не блокират** сайта: `DashboardController`/`SuperAdmin/DashboardController` → `isCheckScript()` (изпраща примерен код само ако `purchase_code` е `NULL`, резултатът не се ползва); `update-settings` и `custom-modules` → `EnvatoUpdate::updateVersionInfo()/plugins()` (GET, `try/catch`, кеш 30 мин, `null` при грешка); `hide-review-modal/{type}` (GET без вход, пише `show_review_modal=0` и праща purchase code към Froiden — пре-съществуващо, нисък риск).
- `down/{hash}` / `up/{hash}` — затворени (§1).
- Проверено като супер админ (с изключена проверка): `/account/update-settings` 200, `/account/settings/custom-modules` 200, `/account/super-admin-dashboard` 200; `/login` като гост 200 (без пренасочване към `/verify-purchase`).
- `composer.json` мапва `Froiden\Envato\` и към `froiden-verify/src` (с приоритет пред vendor) — папката не съществува локално; на сървъра да се провери, че също я няма.

## 5. Находки по сигурността (upstream diff + свързан стар код)

1. **ВИСОК (пре-съществуващ, остава в 6.0.19, важи и за production сега) — неавтентикиран stored XSS + запис в CRM на всяка компания.** `POST /lead-form/leadStore` е публичен и без CSRF; `StorePublicLead`/`HomeController::leadStore` намират компанията по **поредния** `company_id`, а миграцията `2026_08_03_140000_create_lead_forms_table` създава активна форма по подразбиране за всяка компания (`lead_form_id` се изброява). `message` е в `config/xss_ignore.php` → не се чисти → `HomeController.php:857` (`LeadNote.details`) и `:868` (`Deal.note`) → изход `{!! nl2br($note->details) !!}` в `resources/views/leads/ajax/notes.blade.php:34` и `{!! $note->details !!}` в `leads/notes/edit.blade.php:23`, `lead-contact/notes/edit.blade.php:67`. **Доказано** (транзакция + rollback): гост → 200, записано дословно `<img src=x onerror=alert(document.domain)>` в компания 4. CRM е скрит в менюто (S1), но екраните са достъпни по URL. Throttle 10/мин на IP вече е добавен (§1) — ограничава спама, но **не** и XSS-а (една заявка стига). Поправка → искане 6.1 (A04 + A07). `message` не бива да се маха от `xss_ignore` (ползва се от тикети/чат).
2. **СРЕДЕН (deploy) — `refuseInsecureAppKey()`** (upstream, `AppServiceProvider`): при празен `APP_KEY` или примерния `base64:wID/…` **всяка** уеб заявка става 500. На production ключът не е ротиран и не е потвърдено, че не е примерният (P7-11). Виж „За деплоя“ т. 1.
3. **Нисък — `Common::encryptDecrypt()`** вече е с ключ от `APP_KEY` (+ fallback към стария общ ключ само за декриптиране). Следствие: бъдеща ротация на `APP_KEY` (препоръчана от S6) ще счупи `quill-image/…` и маскираните URL-и, създадени след деплоя. Fallback-ът трябва да остане (старите описания); след allowlist-а на A09 във `FileController` не дава достъп до чужди файлове. Без промяна.
4. **Нисък (функционален) — имейли от сделки:** полето `body` (шаблони и изпращане) **не** е в `xss_ignore` → `XssLinearMerge` маха тага → шаблонът губи форматирането, но е безопасен. Не добавям `body` (CRM е скрит; без сървърно чистене би бил stored XSS през `{!! $template->body !!}` в `edit-deal-email-template-modal:24-25` и `{!! $emailHistory->body !!}` в `email-show:44`). Ако CRM се включи → искане 6.2. `template_header`/`template_footer` са **изображения** (`image|mimes:jpeg,jpg,png,bmp,webp`), не суров HTML — не са за `xss_ignore`.
5. **Нисък — `config/dompdf.php` `enable_remote => true`** (пре-съществуващо): новите шаблони `invoice-6` (5 модула) имат същите `{!! nl2br($…->address|note|invoice_terms) !!}` като 1–5; `billing_address`, `note` са в `xss_ignore` → `<img src="http://вътрешен-адрес">` в адрес/бележка = blind SSRF при PDF. Не го сменям (логата в PDF може да зависят от remote) — за R2/отделна задача: `enable_remote=false` + проверка на логата.
6. **Нисък — `manage_deal_email_template` = `ALL_ADDED_NONE`**, но `DealEmailTemplateController` проверява само `== 'none'` → с „added“ се редактират/трият всички шаблони на компанията (A07).
7. **Функционален (A05) —** `super-admin/packages/stripe-payment.blade.php:127` и `super-admin/billing/payment-method-show.blade.php:814` викат `front.invoice_payment_failed` с `company->id`/`invoiceId`, а маршрутът вече е `{hash}` → 404, неуспешното плащане не се записва (upstream бъг; billing е скрит).
8. **Функционален (за сведение) — HTMLPurifier в `trim_editor()`** (116 извиквания): пази само изброените тагове — `table`, `data:` изображения и др. се махат при следващ запис. Кешът — „За деплоя“ т. 3.
9. Без находки: нов суров SQL (`DB::raw`/`whereRaw`/`DB::select`) в upstream кода — **няма**; нови `withoutGlobalScope(CompanyScope)` — само на публични страници по `hash` (`proposal`) или с изричен `company_id` (`leadStore`); нови DataTables `rawColumns` — с `e()`; `Files::validateUploadedFile` — по-строг (`php7/8`, `pht`, `html/htm/svg`); всички нови маршрути в `web.php`/`web-settings.php` са в групи с `auth`; новите публични — Worldpay (неактивен → `activeCredentials` грешка; webhook с HMAC подпис; плащане по пореден id като другите шлюзове) и `/lead-form/{hash}/{slug?}`. Новите модели (`DealEmail*`, `LeadForm`, `ProductResource`) са с `HasCompany` и `$guarded = ['id']` (контролерите присвояват полетата поотделно).

## 6. Scheduler (нови upstream команди)

| Команда | Кога | На production |
|---|---|---|
| `worldpay-subscription-charge` | 02:05 дневно | безвредна: `return` при `GlobalPaymentGatewayCredentials.worldpay_status != 'active'` (Worldpay не е настроен); иска колоните от миграцията `2026_10_23_000001_add_worldpay_to_global_billing` |
| `send-invoice-reminder` | **на час** (беше 01:50 дневно) | идемпотентна по дизайн (`InvoiceReminderService::markBeforeSent` + история); миграцията `2026_07_14_000001` пренася старото `send_reminder_after` в дни; локално всички компании са с изключени напомняния. Детайлите — A02 |
| databackd: `recurring-analysis` 06:00, `send-reports` 07:00, `check-alerts` hourly | — | запазени |

Исканията на A02/A05/A08 за `Kernel.php` — очаквам ги от координатора.

## 7. `public/user-uploads/.htaccess`

Нашата версия = 6.0.10 (`<FilesMatch "\.(php|php\.)$"> Order Allow,Deny / Deny from all`). Upstream 6.0.19 (вече в worktree-а) е по-строг: `Require all denied` за `php|php[0-9]|phtml|pht|phtm|phar|shtml|cgi|pl|asp|aspx|js`, `php_flag engine off` (в `IfModule mod_php*`), `Content-Disposition: attachment` + `nosniff` за `html|htm|svg|svgz`. Съвместим с LiteSpeed (`Require` вече се ползва в `user-uploads/databackd/.htaccess`, `Header` се поддържа). `public/user-uploads/databackd/.htaccess` (`Require all denied`, само на сървъра) е в подпапка → не се засяга; rsync да е **без** `--delete` за `public/user-uploads/`.

## 8. Проверки

- `php -l` — всички мои файлове: без грешки.
- `php artisan route:list` — 2420 маршрута (2432 реда), без грешки (и след throttle-а; `front.lead_store`/`front.ticket_store` → `web, ThrottleRequests:10,1`).
- Throttle (собствен скрипт, IP `10.99.0.7`, `company_id=0` → нищо не се записва): `leadStore` 10× 404 → 11-а и 12-а 429; `ticket-store` веднага 429 (общ лимит по IP).
- `php artisan databackd:check-security` — **83 passed, 0 failed, 0 skipped** (преди и след промените).
- `smoke.php` — админ (`admin@demofinans.bg`): `/account/dashboard` 200, `/login` 302 → табло (логнат), `/log-viewer` и `/log-viewer/api/files` **403**; служител (`g.petrov@`): `/log-viewer` 403; супер админ: `/log-viewer` 200, `/log-viewer/api/files` 200 („untranslated keys“ = име на лог файл, фалшиво), `/account/update-settings` 200, `/account/settings/custom-modules` 200, `/account/super-admin-dashboard` 200; `notices.*`, `/account/tasks/47`, `/account/tasks/47/edit` 200. (Два изгледа в един процес дават `Cannot redeclare moveUniversalToFront()` — артефакт на smoke-а: `vendor/froiden-envato/update/plugins.blade.php` декларира функция без `function_exists`; при реална заявка се рендерира веднъж.)
- Гост (собствен скрипт): `/login` 200, `/log-viewer` и `/log-viewer/api/files` 302 → login, `/down/abc`, `/up/abc`, `/check-env`, `/sync-user-permissions` 404, `/cropper/x` 302, `/verify-purchase` 200, `/worldpay/3ds-return` 200.
- Имейл 2FA (транзакция + rollback): `resend-code` без `user_id` → 200; грешен код → 302 back; верен код + чужд `user_id` → 403; верен код без challenge → 403; верен код без `user_id` → 302 `/account/dashboard`, логнат.
- `EditorSanitizer` — 11/11: `i[class]`, data-* на споменаванията, `pre.ql-syntax` се пазят; `style`/`on*`/`id`, невалиден `data-id`, `javascript:` (и в `data-link`), `<script>`, `onerror`, чужд iframe се махат; идемпотентен; кешът се пише.
- `hardcoded-text.php --list` — нищо от моите файлове.
- Скриптове: `/private/tmp/claude-501/-Users-tsvet-Documents-Projects-databackd-com/db6ca00b-fbd7-487e-96e1-2ae875b1a9df/scratchpad/a11/` (`twofa-email-check.php`, `public-lead-check.php`, `guest-get.php`, `sanitizer-check.php`, `throttle-check.php`; пускат се с `php <скрипт> "$W"`), резултатите на `check-security` — `check-security-before.txt`, `check-security-after.txt` там.

## 9. Искания към други файлове

**6.1 — A04 (`app/Http/Controllers/HomeController.php`) + A07 (изгледите): находка 5.1**
```diff
@@ HomeController::leadStore (ред ~857 и ~868)
-        $note->details = (request()->has('message') ? $request->message : '');
+        // databackd (upgrade 6.0.19): public, unauthenticated form - "message" is in config/xss_ignore.php (stored XSS)
+        $note->details = (request()->has('message') ? clean_editor($request->message) : '');
@@
-        $lead->note = (request()->has('message') ? $request->message : null);
+        $lead->note = (request()->has('message') ? clean_editor($request->message) : null);
```
и за вече записаните данни (A07):
```diff
- resources/views/leads/ajax/notes.blade.php:34       {!! nl2br($note->details) !!}
+                                                     {!! nl2br(clean_editor($note->details)) !!} {{-- databackd (upgrade 6.0.19) --}}
- resources/views/leads/notes/edit.blade.php:23        {!! $note->details !!}
+                                                     {!! clean_editor($note->details) !!}
- resources/views/lead-contact/notes/edit.blade.php:67 {!! $note->details !!}
+                                                     {!! clean_editor($note->details) !!}
```
По желание (A04): `$categoryId = $leadForm->category_id ?? $request->category_id` — `category_id` от заявката не е ограничено до компанията (`LeadCategory::where('company_id', $company->id)->find(...)`).

**6.2 — A07 (само ако CRM се покаже): находка 5.4** — `config/xss_ignore.php` (+ `'body'`, A11) **заедно** с `clean_editor($request->body)` в `DealEmailTemplateController::store()`/`update()` (редове 47, 70) и в `DealEmailController::store()` (ред 65). Едното без другото = или загубено форматиране, или stored XSS.

**6.3 — A07:** находка 5.6 (`manage_deal_email_template` „added“). **A05:** находка 5.7 (`front.invoice_payment_failed` с hash).

**6.4 — R2 / A13:** находка 5.5 (`dompdf.enable_remote`); да се добави проверка в `check-security` за e-mail 2FA с код (позитивен сценарий) — регресията от §1 не се хваща от сегашните 83 проверки.

## 10. За деплоя

1. **Преди качването** (иначе целият сайт е 500 заради `refuseInsecureAppKey()`): `grep '^APP_KEY=' ~/public_html/.env | shasum -a 256 | cut -c1-10` **не** трябва да е `fa9430d687` и ключът да не е празен. Ако е примерният — ротацията на `APP_KEY` трябва да стане **преди** този деплой (страничните ефекти — `docs/mvp/security/2026-10-security-review.md` §6 т. 2).
2. **ar-php липсва** във `vendor/` (и в upstream пакета, и на production); composer няма на сървъра. С guard-а PDF с арабски текст се генерира без оформяне на арабските букви (без fatal). Ако някога трябва — пакетът да се добави във `vendor/` локално (`composer`) и `vendor/` да се качи наново.
3. `storage/app/htmlpurifier/` (кеш на HTMLPurifier, `EditorSanitizer` го създава с `@mkdir(0755)`) трябва да може да се пише от PHP потребителя; ако не може, HTMLPurifier дава `E_USER_WARNING` → в Laravel това е изключение → **500 при всеки запис с редактор**. След деплоя: `mkdir -p storage/app/htmlpurifier` като потребителя на сайта и една редакция на бележка/задача. Кешът е по хеш на конфигурацията — смяната на списъка с тагове не изисква чистене. В git е игнориран (`storage/app/.gitignore`).
4. `public/user-uploads/.htaccess` — новата upstream версия; `public/user-uploads/databackd/.htaccess` остава (rsync без `--delete`).
5. Да не се качват `public/backup-and-restore/` и `public/check.php` от upstream архива (деплой само от git); да се провери, че на сървъра няма `froiden-verify/`.
6. Конфигурация: без нови `.env` ключове за нас (`WORLDPAY_*` от `config/services.php` не са нужни); `DATABACKD_LICENSE_CHECK` да **не** се задава. Миграциите (A12) създават worldpay колоните, нужни на `worldpay-subscription-charge`.
7. Без кеширащи команди извън стандартния деплой; след деплоя: `/login`, вход с код по имейл (ако някой акаунт го ползва), `/log-viewer` като админ (403) и супер админ (200), `/down/x` → 404.
