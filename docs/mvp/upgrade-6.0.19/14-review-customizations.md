# R1 — независимо ревю: запазени къстомизации и коректност (Anagami Finance View 6.0.10 → 6.0.19)

Дата: 2026-10-28. Клон `upstream/worksuite-v6.0.19`, прегледан HEAD `533ef15`. Той включва и двата commit-а след началото на ревюто: `ae3756d` (поправки от R2) и `533ef15` (етикет на цената). Ревюто е само четене: без запис в базата, без кеширащи команди, без production. Помощните скриптове и изходите са в `$S/r1/`, където `$S = /private/tmp/claude-501/-Users-tsvet-Documents-Projects-databackd-com/db6ca00b-fbd7-487e-96e1-2ae875b1a9df/scratchpad`.

Обхват:
- `git diff main HEAD`: 446 файла;
- 101-те файла, променени и от двете страни (`comm -12`, база `37235f8`);
- поправките след merge-а, `git diff 4f42ef7 HEAD -- app resources routes config`: 107 файла, плюс 7 файла от `ae3756d`/`533ef15`;
- езиковите файлове.

## Резюме

| Тежест | Брой | Накратко |
|---|---|---|
| Блокер | 0 | — |
| Висок | 1 | В1: лимитите на CUS_000329 приемат `NULL` за „0 позволени“. Нова компания (пакет Trial/Default) не може да добави клиент, фактура или служител. **Пре-съществуващо** (същото е в `main`), не е регресия от merge-а. |
| Среден | 1 | С1: публичната форма за тикети остава активна, макар модулът Tickets да е скрит. Анонимно създава клиентски акаунти в чужда компания и заобикаля `max_clients`. Пре-съществуващо; поправката на R2 за лийд формата не я обхваща. |
| Нисък | 5 | Н1 получатели на имейл от сделка (латентно); Н2 включването на лиценза след `ae3756d` заключва входа; Н3 `.env.example`; Н4 `alt` с целия base64 при `data:` картинки; Н5 `bg/superadmin.php` при rsync (за проверка). |

**Къстомизациите S1–S12 и CUS_000326/328/329 са оцелели при merge-а.** Не е намерена нито една тиха загуба от auto-merge-а. Подробности и доказателства са в „Проверено и наред“.

---

## Находки

### В1 (висок, пре-съществуващ): `NULL` в `packages.max_clients` / `max_invoices` блокира добавянето на клиенти, фактури и служители

- **Къде:**
  - `app/Helper/start.php:1576` (`checkCompanyPackageIsValid`), `:1608` (`checkCompanyCanAddMoreClients`), `:1626` (`checkCompanyCanAddMoreInvoices`);
  - `app/Http/Controllers/ClientController.php:165`, `app/Http/Controllers/InvoiceController.php:215`;
  - `app/Http/Middleware/CheckCompanyPackage.php:68,72`;
  - `app/Http/Controllers/SuperAdmin/BillingController.php:450,456`, `PayFastController.php:62,68`, `app/Observers/SuperAdmin/PackageObserver.php:31`.
- **Доказателство:**
  - В `databackd_upgrade_local` пакетите 1–5 (`Default`=`default yes`, `Trial`=`default trial`, Starter, Medium, Larger) са с `max_clients = NULL, max_invoices = NULL`. Колоните са `NULL`-able. `CompanyObserver.php:945/953` дава на нова компания пакет Trial или Default.
  - В PHP 8 сравнението с `null` минава през bool: `0 < null` е `false`, `0 >= null` е `true`, `3 <= null` е `false` (проверено с PHP 8.4).
  - Симулация без кеш (`$S/r1/pkg.php`), компания 1 (пакет 1, 0 клиента): `canAddClients=false`, `canAddInvoices=false`.
- **Сценарий на провала:**
  1. Супер админът създава компания или някой се регистрира. Компанията получава Trial или Default.
  2. Админът ѝ натиска „Добави клиент“. Получава `ui-crm.clients.maxClientsReached` („Достигнахте максималния брой клиенти…“), защото `0 >= null` е `true`. Същото става с фактурите.
  3. Заради `CheckCompanyPackage.php:68` (`!checkCompanyCanAddMoreEmployees || !checkCompanyCanAddMoreClients`) и `employees.create` дава 403.
  4. Ако такава компания все пак има ≥1 клиент или фактура (импорт, публичната форма за тикети от С1), `checkCompanyPackageIsValid` става `false`. Тогава всяка страница извън allowlist-а пренасочва към `superadmin.notify.admin`.
- **Защо не е блокер на този деплой:** логиката е същата в `main`. Демо компания 4 е на пакет 6 (10000/1000000) и не е засегната. На production засяга само компании на пакети с `NULL`.
- **За проверка на production (само четене):**
  ```sql
  SELECT id, name, `default`, max_employees, max_clients, max_invoices FROM packages;
  SELECT package_id, COUNT(*) FROM companies GROUP BY package_id;
  ```
- **Поправка (две възможности):**
  - (а) **Данни:** Супер админ → Пакети → редакция на всеки пакет с `NULL`. Формата вече изисква `max_clients`/`max_invoices` (`UpdateRequest:39-40`).
  - (б) **Код:** един helper, напр. `databackd_limit_allows(int $count, $max, bool $strict = true)`, който връща `true` при `is_null($max)`. Ползва се на изброените места. `CheckCompanyPackage.php:68`: лимитът на клиентите да не спира `employees.create` (проверка само на служителите), освен ако това е съзнателно решение на CUS_000329.

### С1 (среден, пре-съществуващ): публичната форма за тикети работи, макар Tickets да е скрит, и създава клиенти в чужда компания

- **Къде:** `routes/web-public.php` (`/ticket-form/{id}`, `POST /lead-form/ticket-store`, CSRF изключение, `throttle:10,1`), `app/Http/Controllers/HomeController.php:927` (`ticketForm`), `:958-1035` (`ticketStore`).
- **Сценарий:**
  1. Анонимен POST с `company_id=<пореден номер>` и непознат имейл.
  2. Резултат: `UserAuth::createUserAuthCredentials` + нов `User` с роля client в тази компания + `NewUserEvent` (имейл към адреса).
  3. Няма проверка `checkCompanyCanAddMoreClients`, т.е. CUS_000329 се заобикаля. Броят на клиентите може да мине лимита, а тогава `checkCompanyPackageIsValid` блокира компанията (виж В1, т. 4).
  4. При **познат** имейл `User::withoutGlobalScope(ActiveScope::class)->where('email', …)` за гост е без `CompanyScope`. Тикетът на компания X получава `user_id` на потребител от компания Y. Това е по кода, не е пускано.
- **Връзка с R2:** `ae3756d` спря `leadForm`/`leadStore` при `databackd_hidden('menu', 'leads')`. Тикетите са скрити по същия начин (`menu.tickets`, `quick_add.ticket`, `settings.ticket_settings`), но формата им е отворена.
- **Поправка:** в `HomeController::ticketForm()` и `ticketStore()` като първи ред:
  ```php
  // databackd (upgrade 6.0.19): Tickets are hidden (config/databackd.php menu.tickets) - no anonymous tickets or client accounts
  abort_if(databackd_hidden('menu', 'tickets'), 404);
  ```
  При бъдещо показване: `checkCompanyCanAddMoreClients($company->id)` преди създаването на клиент и `->where('company_id', $company->id)` в търсенето по имейл.

### Н1 (нисък, латентен): `SendEmailRequest` спира легитимни имейли от сделка

- **Къде:** `app/Http/Requests/Deal/SendEmailRequest.php:25-36` („До“ = само имейлът на контакта), `:40-66` (копие само до контакта или до активни служители на компанията, най-много 10), `resources/views/leads/ajax/send-email.blade.php:24-26` (`readonly`).
- **Сценарий:**
  - Сделка, чийто контакт е без имейл: формата се отваря с празно readonly поле. Всяко изпращане връща `toMustBeContact`, без начин да се попълни адрес.
  - Копие до счетоводителя на клиента или до втори адрес на фирмата е невъзможно (`ccNotAllowed`). В upstream и двете работят.
- **Тежест:** CRM е скрит (`menu.leads`), затова сега ефектът е нулев. Като защита срещу open relay R2 приема правилото (Н2 там).
- **Предложение:** да се потвърди като продуктово решение. Когато CRM се покаже: бутонът „Изпрати имейл“ да не се показва при контакт без имейл и да се обмисли копие и до външни адреси с лимит (`MAX_CC` вече го има).

### Н2 (нисък): `DATABACKD_LICENSE_CHECK=true` след `ae3756d` заключва входа

- **Къде:** `config/security-headers.php:147-148` (`verify-purchase`, `purchase-verified` в `super_admin_only`), `app/Http/Middleware/Databackd/RestrictVendorMaintenanceRoutes.php:40-45` (гост → 404), `app/Providers/FortifyServiceProvider.php:190`, `app/Http/Controllers/SuperAdmin/FrontendController.php:413`.
- **Сценарий:**
  1. Някой включва превключвателя, докато `isLegal()` е `false` (липсващ или отказан код).
  2. Входът (`loginView`, `loadLoginPage`) пренасочва към `/verify-purchase`.
  3. Пренасочваният е гост, затова получава 404. Никой не може да влезе, и супер админът също, докато флагът не се върне на `false`.
- **Поправка:** в `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` 4.10 (и CLAUDE.md, ред 19) да пише реда на действие. Първо кодът се въвежда като **влязъл супер админ** на `/verify-purchase` при `license_check=false`, после се включва флагът. Друг вариант: `verify-purchase`/`purchase-verified` да влизат в `super_admin_only` само докато `databackd_license_check()` е `false` (условие в конфигурацията).

### Н3 (нисък, документация): `.env.example` не съдържа `DATABACKD_LICENSE_CHECK`

- **Къде:** `.env.example` (31 реда, няма ключа). CLAUDE.md:19 и 4.10 описват ключа като env.
- **Поправка:** да се добави ред `DATABACKD_LICENSE_CHECK=false` с коментар „Anagami Finance View license verification (isLegal) — off while the site is not public“.

### Н4 (нисък): с `data:` (`ae3756d`) HTMLPurifier добавя `alt` с целия base64 низ

- **Къде:** `app/Helper/EditorSanitizer.php:69`.
- **Доказателство:** `clean_editor('<img src="data:image/png;base64,iVBOR…">')` връща `<img src="data:image/png;base64,iVBOR…" alt="png;base64,iVBOR…">`, т.е. данните се записват два пъти (`$S/r1/sanit2.php`). HTMLPurifier генерира `alt` от „името на файла“ в URI-то.
- **Последствие:** при поставена в редактора картинка от 300 KB записът в базата (описания, задачи, бележки) и HTML-ът за PDF стават ~600 KB.
- **Поправка:** `$config->set('Attr.DefaultImageAlt', '');` до `URI.AllowedSchemes`. Това е Attr настройка, извън HTML дефиницията, затова `DefinitionRev` не трябва да се вдига.

### Н5 (нисък, за проверка преди rsync): `resources/lang/bg/superadmin.php` е нов частичен файл

- **Факт:** в `main` няма `bg/superadmin.php`. В HEAD има частичен файл (A01, 4 ключа). Останалите 561 ключа минават към английски през `fallback_locale = eng` (`config/app.php:154`) и това е съзнателно.
- **Риск:** ако на сървъра има по-пълен `bg/superadmin.php` (S7 отбелязва, че сървърният `bg/` е бил подмножество, т.е. малко вероятно), rsync ще го замени. Тогава текстовете за компаниите (имейлите за пакета/лиценза) се връщат на английски.
- **Проверка:** `ls -la ~/public_html/resources/lang/bg/superadmin.php` преди качването. Ако съществува, да се сравни и обедини.

---

## Пресичания с други отчети (потвърдени, не ги броя отново)

- **`refuseInsecureAppKey()`** (A11 §5.2, R2 В2): ако `APP_KEY` на production е примерният или празен, целият сайт дава 500. Кодовият път е потвърден (`app/Providers/AppServiceProvider.php`, ред с `$shippedSample`). Паметта на проекта казва, че ключът **не е ротиран**. Проверката от R2 §5.1 е задължителна.
- **Масови напомняния за фактури** (R2 В1): `send-invoice-reminder` е `hourly()` (`app/Console/Kernel.php`). Проверено с `schedule:list` под PHP 8.4.
- **`isCheckScript()`** (`DashboardController.php:61`, `SuperAdmin/DashboardController.php:32,39`) не е зад `databackd_license_check()`. Праща данни към Froiden само при `purchase_code IS NULL`. Локално е `NULL`, но хостът е `127.0.0.1` и функцията излиза веднага. На production кодът е записан (R2 §3). По желание: `if (databackd_license_check()) { $this->isCheckScript(); }`.
- **Тялото на имейла от сделка губи форматирането** (`body` не е в `config/xss_ignore.php`): A07 т. 1, A11 т. 4. Същото е при upstream.
- **CSP (Report-Only) без хостовете на Worldpay/Cardinal:** A05. Само отчети, не блокира.
- **`MASKED_IMAGE_EXTENSIONS` без `avif/tif/jfif`:** A13 §8. Важи само при S3.

---

## Проверено и наред

### 1. Тихи загуби при auto-merge (101 файла „и от двете страни“)

- **Скрипт `$S/r1/lost.sh`.** За всеки от 101-те файла: (а) всеки ред, добавен от нас (`37235f8..main`), трябва да е в HEAD; (б) ред, който сме махнали, не бива да се е върнал. Резултат: **0 загубени** и **0 върнати** реда. Петте попадения са съзнателни премествания или замени:
  - `superadmin.max{Clients,Invoices}LimitReached` → `ui-crm.clients.maxClientsReached` / `ui-invoices.limits.max_invoices_reached` (ключовете ги нямаше в `eng`/`bg`);
  - `edit-leave-setting-type-modal`: същият ред, разделен на два;
  - `products/ajax/show`: редът „Себестойност“ е в новия upstream дизайн с `@unless ($isClient)`;
  - коментарът на `sync-user-permissions`: разширен.
- **Маркери.** Броят на `databackd|WORKSUITESAAS|dbk-|locale != 'bg'` във **всички** файлове, променени от нас спрямо `37235f8`, никъде не е по-малък в HEAD (единственото изключение е `package-lock.json`). Нито един наш файл не е изтрит (`git diff --name-status main HEAD`: 139 A, 307 M, 0 D).
- **Дублирани методи и `use` (PHP), дублирани JS функции и handler-и (Blade)** в 101-те файла: 0.
- **Логика, обезсмислена от upstream:**
  - `config/log-viewer.php`: `super-admin` остава и на UI, и на API, плюс Gate.
  - `VerifyCsrfTokenPathOnly` наследява новия upstream `$except`: `worldpay/3ds-return`, а `*-webhook/*` покрива `worldpay-webhook/{hash}` и `billing-worldpay-webhook/{hash}`.
  - `XssLinearMerge` е идентичен по логика с vendor `XSS`; `config/xss_ignore.php` е непроменен от upstream.
  - Публичните маршрути минаха от `{id}` към `{hash}`. Всички генератори в изгледите ползват `->hash`: `estimate`, `contract`, `proposal`, `invoice`, `gantt`, `stripe-payment`.
  - `app-title`: upstream смени `__($pageTitle)` с `$pageTitle` в трохите. Безопасно е, защото `App\View\Components\AppTitle::__construct` вече превежда.

### 2. Къстомизации (CLAUDE.md, APP-UPDATE §3–4)

- **CUS_000329:**
  - helper-ите, `clearCompanyValidPackageCache`, `CheckCompanyPackage`;
  - `ClientController::store`: първо upstream проверката на правата, после лимитът;
  - `ClientController::update`, `InvoiceController::store`, `InvoicesDataTable` (`$packageAllowInvoice` на всички действия);
  - `InvoiceObserver`, `ClientDetailsObserver`, `ImportClientJob`;
  - `Billing`/`PayFast`: downgrade проверките (Razorpay пътят никога не е имал проверка за клиенти/фактури, и в `main`);
  - формите и request-ите на пакетите (19 срещания).

  Логическият проблем с `NULL` е В1.
- **CUS_000328:** guard-ът е в **40 файла**: всичките 34 от `main` плюс 6-те нови `*/pdf/invoice-6`. Шаблон 6 е с цикъл `['', 'bg']`. `@php` блоковете в цикъла са идемпотентни: присвоявания без натрупване, т.е. сумите не се удвояват при второто копие. Upstream промените в двуезичните шаблони 1–5 са само CSS в `<head>` и се отнасят и за двете копия. Поправката на upstream `s<td` в `orders/pdf/invoice-5` е приложена.
- **CUS_000326:** `CustomFieldsTrait` е в `BankAccount`, `CreditNotes`, `Payment`, `Proposal`.
- **S1/S8 (скриване и пакетни модули):**
  - `sections/menu`: новото меню „Форми за потенциални клиенти“ е **вътре** в `@unless (databackd_hidden('menu', 'leads'))`, а абонаментите (`autoPayEnabled()`) са зад `menu.subscriptions`;
  - `recurring-invoices/index`: бутонът „Абонаменти“ е скрит;
  - `employees/ajax/profile`: джаджата „Оставащи отпуски“ е скрита;
  - `LateClockInListener`: не праща нищо, докато присъствието е скрито;
  - `Module::databackdModules()`, `SuperAdminModuleScope`, `CompanyObserver` (2× `databackd_modules_for_type`), `RouteServiceProvider` (`mapDatabackdRoutes`/`mapDatabackdApiRoutes`), `composer.json` + `vendor/composer/autoload_files.php` (`app/Helper/databackd.php`);
  - `Kernel`: 3-те `databackd:*` команди. `schedule:list` под PHP 8.4 ги показва;
  - „Себестойност“: `ProductController::store/update`, request-ите, show с `@unless ($isClient)`. `Product::$hidden` скрива `cost_price` и платения файл от JSON. Проверено: `Product::first()->toArray()` е без `cost_price`. Нито един JS не чете тези полета.
- **S6:**
  - няма `enable_php` извън `config/dompdf.php` (`false`) и коментари;
  - няма `public/backup-and-restore/` (и новият upstream `require-auth.php` не е влязъл) и `public/check.php`;
  - debug маршрутите са махнати;
  - `Kernel`: `SecurityHeaders`, `FinApiGate`, `RestrictVendorMaintenanceRoutes` (2×), `XssLinearMerge`, `VerifyCsrfTokenPathOnly`, `AuthHardening`;
  - `UserAuth::validateLoginActiveDisabled()`: 3× `?` bindings;
  - `config/{session,hashing,filesystems}` са с нашите стойности;
  - всички нови S6 файлове съществуват;
  - `down/*` и `up/*` са `hidden`, `verify-purchase`/`purchase-verified`/`hide-review-modal/*` са `super_admin_only` (Н2).
- **S7:**
  - Брой `__(`/`@lang(`/`trans(` във всеки файл, който сме пипали: намалява само в 3 файла и и трите са обяснени. `app-title`: виж т. 1. `show_clocked_hours`: upstream цикъл `@lang('modules.attendance.' . $type)`. `topbar`: `title` → нашият `aria-label`.
  - `eng/`: от **всички** наши стойности в `main` е променена само една, `app.hsnSac` („Hsn/Sac“ → „HSN/SAC“, по искане на A06). Изтрити ключове: 0. Същото важи за `bg/`.
  - **219-те нови upstream ключа** са в `bg/` с превод. Еднакви с английския са само `app.worldpay` и `modules.module.googleMeet` (марки).
  - Еднакви ключове `eng`/`bg` във всичките 66 файла, включително новите `ui-{billing,crm,invoices,products,sales,shell}.php`. Изключение е само `superadmin.php`, който е частичен съзнателно (Н5).
  - Placeholder-ите (`:name`): 0 разлики.
  - Дублирани ключове в масивите: същите 7 като в `main` (пре-съществуващи), нови няма.
  - Всичките 438 ключа, ползвани в новия код (`git diff main HEAD`), съществуват в `eng` и `bg`. Без `bg` е само `superadmin.paymentSuccessfullyDone` (супер админ, минава към английски).
  - `lang-audit.php --strict`: exit 0, 0 критични.
- **S9:**
  - `layouts/app`: `lang`, `?v=filemtime`, глобалните Chart.js и `databackd-charts.js`, `moment.locale`;
  - `main.scss`: 14 `@import "databackd/…"`;
  - `webpack.mix.js`;
  - компилираният `public/css/main.css` в HEAD съдържа 4167 `dbk-` (колкото в `main`) и upstream добавките на SCSS (+539 B).

  Новите upstream стилове на продукта и избора на продукт минават през `var(--dbk-…, <upstream>)`.
- **S10:**
  - `.env.example`: празен `APP_KEY`;
  - `.env.*` е в `.gitignore`, `docs/` **не** е;
  - `sync-user-permissions`: `auth` + `super_admin_only`;
  - `bar-chart`/`pie-chart`: `@json`.
- **Лицензът (`9f05fe3`):**
  - `databackd_license_check()` стои пред `isLegal()` и на двете места (`FortifyServiceProvider.php:190`, `SuperAdmin/FrontendController.php:413`);
  - `config('databackd.license_check')` чете env в конфигурационния файл, т.е. работи и с `config:cache`;
  - други извиквания на `isLegal()` в `app/`, `routes/`, `resources/` няма; `froiden-verify/` не съществува и PSR-4 зарежда `vendor/froiden/envato`.

### 3. Поправките след merge-а — регресии в нормалния поток

| Поправка | Проверено |
|---|---|
| `FileController` allowlist | 8-те папки съвпадат точно с **всички** извиквания на `generateMaskedImageAppUrl` (Company, GlobalSetting, InvoiceSetting, User, OfflinePaymentMethod, InvoicePaymentDetail, FrontDetail, SeoDetail, Smtp/CustomConfigProvider). SVG е забранен при качване (`Files::validateUploadedFile`). При локално хранилище маршрутът не се ползва. |
| `AuthHardening` (2FA по имейл) | Upstream `LoginController::checkCode` взема самоличността от `session('login.id')`. Ако `user_id` все пак е подаден, трябва да съвпада. Празен низ → `null` (ConvertEmptyStringsToNull). |
| `MessageFileController` | `user_one` е подателят (`MessageController.php:197`). Dropzone качва файловете с `message_id` на току-що изпратеното съобщение. `UserchatFile::chat()` съществува. |
| Ресурси на продукта | 7 случая през `Validator` (`$S/r1/valtest.php`): https минава; `javascript:`, адрес без схема и `ftp:` се отхвърлят; `source=file` с боклук в `url` минава; съществуващ ред без нов файл минава. `exclude_if`/`exclude_unless` с `*` са в `Validator::$dependentRules` (Laravel 12.61). Имената на полетата съвпадат с формата. `company_id` на `ProductResource` се записва. |
| `DealEmail` получатели | Виж Н1. `User` е под `CompanyScope` + `ActiveScope`, а `employeeDetail` съществува (заявката е изпълнена). |
| `leadStore` | `StorePublicLead`: правилата `source`/`product` никога не стават масив с низ, съдържащ `|` (само `required`/`nullable`). Таблиците `lead_sources`, `lead_category`, `products`, `lead_pipelines`, `pipeline_stages`, `deal_email_templates`, `deals` имат `company_id`. Етап без фуния се отхвърля, етап с фунията си минава. `custom_fields_data`: заявката работи. `DB` и `Lead::CUSTOM_FIELD_MODEL` са налични. След `ae3756d` формата е 404, докато CRM е скрит. |
| `getBankStatement` | Същото правило като `show()`. `view_bankaccount` има само `all/added/none`. JS-ът праща `accountId`. Smoke: админ 200, служител 403. |
| `estimateAccept`/`Decline` | Публичната страница показва бутоните само при `status == 'waiting'` (`estimate.blade.php:617`). `rollBack()` е преди отговора. |
| `LeavesQuotaController` | `update_leaves_quota` е `{all, none}`, а UI-ят показва формата при `all`. `whereHas('user')` работи (27 квоти за компания 4). `view_leave_report` е `{all, none}`. Експортът се вика само от отчета. |
| `AttendanceController::employeeData` | JS-ът праща `userId`. При `owned` изборът съдържа само текущия потребител. `users` няма колона `added_by`, т.е. филтърът `both` чете `attendances.added_by` правилно. |
| `remindForPayment` | Бутонът е само за роли, различни от client, и при `view_invoices` ≠ `none`. Админът има `all`. |
| `removeCartItem` | Всички 3 извиквания с `all_data` подават `user()->id`. |
| `ProductResourceController` | Проверката на модула е след `parent::__construct()`, т.е. `$this->user` е готов. |
| `EditorSanitizer` | Споменаванията (`data-*`), `pre[spellcheck]`, `i[class]`, `data:image/png` се запазват. `javascript:` и `data:text/html` в `href`, `onerror`, `<script>` и класът `openRightModal` се махат. Без PHP warning (HTMLPurifier би го превърнал в ErrorException). `DefinitionRev` е вдигнат при промяната на дефиницията. |
| `x-cards.data-row` (upstream: `e()` / `clean_editor` при `html="true"`) | Сканирани са 293 тага: 0 подават HTML без `html="true"` (и като литерал, и като променлива с HTML от същия файл). 0 `html="true"` със `openRightModal` (засегнато от `Attr.ForbiddenClasses`). |
| Worldpay `release()` / `payerMessage()` | Сигнатурата `release(string, int)` и ключовете `type`/`id` са както в upstream редовете 201/224. `$normalized` е дефинирана в `respond()`. |
| `notification_fallback` | Типът е преведен, полезният текст е ескейпнат. Нашето `fin_alert_notification` е с отделен изглед. |
| `public/user-uploads/.htaccess` (upstream) | Съдържа `Header set` без `IfModule`. Production (LiteSpeed) вече изпълнява същото в `public/.htaccess:31,36`, т.е. няма 500. |

### 4. PHP 8.4 (CLI на production) / 8.5 (web) и MariaDB 11.8

- `php -l` с **8.4.22** и **8.5.3** на всички променени PHP файлове: 0 грешки.
- 151-те променени Blade изгледа са компилирани с `blade.compiler` и минават `php -l` (8.4): 0 грешки (`$S/r1/bladelint.php`).
- Няма маркери за конфликт и debug остатъци (`dd`, `dump`, `console.log`) в diff-а.
- Без функции/синтаксис само за 8.5 (`array_first`, `|>`, `Uri\…`) и без функции само за 8.4 в новия код. Под 8.4: приложението се зарежда, `route:list` дава 2420 маршрута, `schedule:list` е OK.
- **SQL в новия код:**
  - `whereJsonContains` → `JSON_CONTAINS` (има го в MariaDB);
  - `renameColumn` (Laravel 12 → `RENAME COLUMN`, MariaDB ≥ 10.5.2);
  - `ALTER … MODIFY … ENUM`;
  - `json()` колони (в MariaDB са LONGTEXT + CHECK);
  - `whereRaw('md5(id) = ?')`, `Rule::exists()->where()`.

  Специфични за MySQL 8 функции няма. Локалната база е MySQL 9.6, затова не е тествано на истински MariaDB.

### 5. Smoke (рендиране в процеса, `databackd_upgrade_local`)

- **Админ, 200:** `products.*`, `invoices.*`, `clients.*`, `bankaccounts.*`, `lead-forms.*`, `dashboard`, `/account/products/1` (show/edit), `/account/invoices/601`, `/account/bankaccounts/7`, извлечението (`accountId=7`, дати във формата на компанията `d.m.Y`). FAIL-овете са само маршрути без задължителния параметър и същите като при A13 (`stripe_modal`, `committed_modal` без Purchase и т.н.).
- **Служител/клиент:** продукт и извлечение → 403, очаквано по права.
- **След `ae3756d`/`533ef15`:** `/account/products/1` и `/account/dashboard` → 200. `php -l` и Blade на 7-те файла → 0 грешки.

## Скриптове (`$S/r1/`)

| Скрипт | Какво прави |
|---|---|
| `lost.sh` → `lost.txt` | Загубени и върнати редове в 101-те файла „и от двете страни“ |
| `langcmp.php`, `langdiff.php`, `newkeys.php`, `dupkeys.php`, `keycheck.php` | Еднакви ключове `eng`/`bg` и placeholder-и; стойности `main` → HEAD; 219-те нови ключа; дублирани ключове; ключовете, ползвани в новия код |
| `bladelint.php` | Компилира Blade и пуска `php -l` |
| `datarow.php`, `datarow2.php`, `dr-modal.php` | Ескейпването на `x-cards.data-row` |
| `valtest.php` | Валидация на ресурсите на продукта |
| `q.php` | Заявки само за четене в транзакция с `rollBack` |
| `sanit.php`, `sanit2.php` | HTMLPurifier |
| `pkg.php` | Лимитите на пакета, без кеш (В1) |
