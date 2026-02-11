# A13 — регресия на цялото приложение (обновяване 6.0.10 → 6.0.19)

Worktree `ws-merge` (клон `upstream/worksuite-v6.0.19`, до `a6304a7`), база `databackd_upgrade_local`. Сравнение „преди“: основната папка (`main` = 6.0.10 + S1–S12, база `databackd_local`, стар vendor). Там е пускан само `smoke.php`, плюс `hardcoded-text.php` (по задание) и `lang-audit.php` без `--json` (само чете). Кодът не е променян. `git status` в `$W` е чист, освен този отчет. Без кеширащи команди. `databackd:check-*` и собствените скриптове вървят с `CACHE_DRIVER=array`.

Скриптове и изходи: `/private/tmp/claude-501/-Users-tsvet-Documents-Projects-databackd-com/db6ca00b-fbd7-487e-96e1-2ae875b1a9df/scratchpad/a13/`. Основните са `run-smoke.sh`/`run-all.sh`, `compare.php`, `table.php`, `file-route-check.php`, `public-urls-gen.php`, `blade-compile.php`, `blade-lint.sh`. Изходите на smoke са в `out/`, текстовите дъмпове в `dump/`, снимките в `snap/`.

## Резюме

- **Регресии: 0.** Пуснати са 412 заявки за всяка от трите роли (админ, служител, клиент), с `--ajax`, едни и същи URL-и и в `$W`, и в `main`. Никоя страница не е минала от 200 към 500, 403, неочаквано пренасочване или exception. Единствената разлика в статуса е `/account/products/grid`. Маршрутът е нов в 6.0.19: в `main` дава 404 (`products/{id}`=„grid“), а в `$W` дава 200 за админ и 403 за служител (служителят няма права в данните).
- Всички 77 AFV страници дават **еднакъв видим текст** в двете дървета (разликата е само версията и часът). Работното табло, сайдбарът и менюто съвпадат за трите роли, т.е. скриването S1/S8 е запазено.
- `FileController` (новият allowlist) е проверен със симулиран S3. Всички легитимни маскирани URL-и, генерирани от приложението, се отварят (302 към обекта): **95/95**. Подправените пътища (чужди папки, `..`, `.php`, `.sql`) дават 404 и с новия, и със стария ключ.
- Минават и останалите проверки:
  - публичните подписани страници: 200 с подпис, 403 без подпис;
  - вход с код по имейл: 5/5;
  - log-viewer: админ и служител получават 403, супер админът получава 200;
  - billing за админ на компания: 404;
  - `lang-audit --strict`: 0 критични;
  - `check-formulas`: 159/159, `check-consolidation`: 39/39, `check-forecasts`: 81/81, `check-security`: 83/83;
  - Blade: 1595 изгледа, 0 грешки;
  - `php -l` на 262 променени PHP файла: 0 грешки.
- Две бележки (не са регресии, детайли в §7):
  - Upstream промени поведението на `CustomConfigProvider`: SMTP парола, която не се дешифрира, вече не спира конфигурацията. Това носи предупреждение в лога при всяка заявка и използва суровата стойност като парола. Да се провери на production.
  - Страницата на продукта вече показва само цена **с ДДС** под надпис „Официална продажна цена“. Нетната цена я няма. Това е upstream дизайн; формулировката е за A01/A06.

## 1. Широк smoke: `$W` срещу `main`

Набор от URL-и (еднакъв в двете дървета, `--ajax` = всеки URL и като AJAX/DataTables):
- `databackd.` — всички GET маршрути без параметри (`databackd.analytics.`, `.data.`, `.gaps.`, `.planning.`, `.reports.`, `.teams.`; групи `integrations`/`ratios` няма — те са под `data`/`analytics`) + 25 URL-а с реални id-та (`urls-dbk-extra.txt`: builder/1, consolidation-rules/52, ratio/1, accounts/30, batches/45, entries/15068, alerts/52, fx-rates/3929, budget-editor/26, forecasts/11, saved/34, teams/analysis/119, requests/29, specialists/41 …);
- Anagami Finance View (`urls-ws.txt`, 103 URL-а). Всички GET маршрути без параметри на `dashboard*`, `invoices.`, `recurring-invoices.`, `creditnotes.`, `payments.`, `expenses.`, `products.`, `clients.`, `projects.`, `tasks.`, `timelogs.`, `employees.`. Към тях: настройките (`company-settings`, `invoice-settings`, `payment-gateway-settings`, `currency-settings` (+create), `profile-settings`, `notifications`, `theme-settings`, `app-settings`), `all-notifications`, `contracts`, `estimates`, `proposals` и show/edit с реални id-та: фактура 601/602, кредитно известие 16, плащане 610, разход 751, продукти 1 и 2, клиент 11 (+табове), проект 1 (+табове), задача 1, служител 3, договор 1. Изключен е `invoices.delete_image` (GET, който трие).

Брой OK / FAIL (заявки, AJAX вариантът се брои отделно):

| Префикс | admin W OK/FAIL | admin main | служител W | служител main | клиент W | клиент main |
|---|---|---|---|---|---|---|
| `clients` | 14 / 2 | 14 / 2 | 0 / 16 | 0 / 16 | 0 / 16 | 0 / 16 |
| `contracts` | 4 / 0 | 4 / 0 | 2 / 2 | 2 / 2 | 2 / 2 | 2 / 2 |
| `creditnotes` | 8 / 2 | 8 / 2 | 2 / 8 | 2 / 8 | 2 / 8 | 2 / 8 |
| `dashboard` | 8 / 0 | 8 / 0 | 8 / 0 | 8 / 0 | 6 / 2 | 6 / 2 |
| `databackd.analytics.*` | 22 / 0 | 22 / 0 | 22 / 0 | 22 / 0 | 0 / 22 | 0 / 22 |
| `databackd.analytics.*` (с id) | 14 / 2 | 14 / 2 | 14 / 2 | 14 / 2 | 0 / 16 | 0 / 16 |
| `databackd.data.*` | 38 / 0 | 38 / 0 | 38 / 0 | 38 / 0 | 0 / 38 | 0 / 38 |
| `databackd.data.*` (с id) | 12 / 0 | 12 / 0 | 12 / 0 | 12 / 0 | 0 / 12 | 0 / 12 |
| `databackd.gaps.*` | 38 / 0 | 38 / 0 | 28 / 10 | 28 / 10 | 0 / 38 | 0 / 38 |
| `databackd.gaps.*` (с id) | 4 / 0 | 4 / 0 | 2 / 2 | 2 / 2 | 0 / 4 | 0 / 4 |
| `databackd.planning.*` | 14 / 0 | 14 / 0 | 14 / 0 | 14 / 0 | 0 / 14 | 0 / 14 |
| `databackd.planning.*` (с id) | 2 / 4 | 2 / 4 | 2 / 4 | 2 / 4 | 0 / 6 | 0 / 6 |
| `databackd.reports.*` | 30 / 2 | 30 / 2 | 30 / 2 | 30 / 2 | 0 / 32 | 0 / 32 |
| `databackd.reports.*` (с id) | 2 / 2 | 2 / 2 | 2 / 2 | 2 / 2 | 0 / 4 | 0 / 4 |
| `databackd.teams.*` | 12 / 0 | 12 / 0 | 12 / 0 | 12 / 0 | 10 / 2 | 10 / 2 |
| `databackd.teams.*` (с id) | 8 / 0 | 8 / 0 | 6 / 2 | 6 / 2 | 4 / 4 | 4 / 4 |
| `employees` | 12 / 0 | 12 / 0 | 2 / 10 | 2 / 10 | 0 / 12 | 0 / 12 |
| `estimates` | 2 / 0 | 2 / 0 | 0 / 2 | 0 / 2 | 0 / 2 | 0 / 2 |
| `expenses` | 14 / 0 | 14 / 0 | 4 / 10 | 4 / 10 | 0 / 14 | 0 / 14 |
| `invoices` | 16 / 10 | 16 / 10 | 8 / 18 | 8 / 18 | 8 / 18 | 8 / 18 |
| `notifications` | 2 / 0 | 2 / 0 | 2 / 0 | 2 / 0 | 2 / 0 | 2 / 0 |
| `payments` | 10 / 2 | 10 / 2 | 2 / 10 | 2 / 10 | 2 / 10 | 2 / 10 |
| `products` | **18 / 2** | **16 / 4** | 4 / 16 | 4 / 16 | 0 / 20 | 0 / 20 |
| `projects` | 18 / 2 | 18 / 2 | 2 / 18 | 2 / 18 | 2 / 18 | 2 / 18 |
| `proposals` | 2 / 0 | 2 / 0 | 0 / 2 | 0 / 2 | 0 / 2 | 0 / 2 |
| `recurring-invoices` | 4 / 0 | 4 / 0 | 0 / 4 | 0 / 4 | 0 / 4 | 0 / 4 |
| `settings` | 18 / 0 | 18 / 0 | 2 / 16 | 2 / 16 | 2 / 16 | 2 / 16 |
| `tasks` | 16 / 2 | 16 / 2 | 4 / 14 | 4 / 14 | 4 / 14 | 4 / 14 |
| `timelogs` | 16 / 2 | 16 / 2 | 8 / 10 | 8 / 10 | 6 / 12 | 6 / 12 |
| **Общо** | **378 / 34** | **376 / 36** | **232 / 180** | **232 / 180** | **50 / 362** | **50 / 362** |

Сравнението ред по ред (`compare.txt`) показва **0 разлики** в статуса и вида на грешката, освен `products/grid`. Разликите в съобщенията са само в абсолютния път до файла.

### 1.1 Регресии

**Няма.**

### 1.2 Познати FAIL-ове (еднакви в `$W` и `main` — не са регресия)

| URL | Грешка | Причина |
|---|---|---|
| `databackd.reports.counterparties.show`, `analytics/builder/1/edit`, `planning/budgets/26`, `planning/forecasts/11`, `reports/saved/34` | 302 | по дизайн: без `?name=` към списъка; системният коефициент не се редактира; бюджетът се отваря в редактора; прогнозата — в `?forecast_id=`; запазената справка — в builder |
| `clients/gdpr-consent` | 500 `Attempt to read property "id" on null` (`clients/gdpr/consent-form.blade.php`) | без параметър |
| `creditnotes/create` | 404 | без `?invoice=` |
| `invoices/add-item`, `offline-payment-modal`, `tasks/client-detail` | 404 | без параметър |
| `invoices/committed-modal` | 500 `Class "Modules\Purchase\Entities\PurchaseProduct" not found` (`InvoiceController.php:417`) | модулът Purchase го няма (upstream) |
| `invoices/stripe-modal` | 500 липсва `invoiceId` за маршрута `stripe` | без параметър |
| `projects/projects/burndown` | 500 `ArgumentCountError` (`ProjectController.php:1625`) | без `projectId` |
| `timelogs/export` | 500 Carbon `Not enough data` | без дати (handoff 07) |
| `invoices/601/edit` | 403 | платена фактура |
| `payments/610/edit` | 404 | такъв маршрут няма |
| `products/cart` | 403 | само за клиент |
| `dashboard/private_calendar` (клиент) | 500 `calendar_view` on null (`DashboardController.php:314`) | пре-съществуващо |
| служител `g.petrov@` — 136 × 403 (Anagami Finance View) и `gaps.alerts.create`, `gaps.margins*`, `gaps.onboarding`, `alerts/52/edit`, `specialists/41/edit` | 403 | празни `user_permissions` (A09 §4.5) + AFV само за админ |
| клиент `p.yordanov@` — databackd: 192 × 403 (само `teams.*` е отворено), Anagami Finance View: 152 × 403 | 403 | по права |
| `company-settings` (служител, клиент) | 302 | пренасочване към профила |

### 1.3 Допълнителни нови/променени страници (админ, `out/{W,M}-admin-extra.txt`)

`$W`: 48 OK / 16 FAIL; `main`: 45 OK / 19 FAIL. В `$W` дават 200:
- табовете на invoice-settings (general, template, **reminder**, prefix, units, payment);
- `leaves-settings` (general, type), `attendance-settings`, `lead-settings` (email-templates, pipeline);
- `lead-forms`, `products/1?tab=resources`, `products/grid`;
- `leaves` (+create), `attendances`, `estimates|proposals|contracts/create`, `orders`, `notices`;
- `module-settings`, `security-settings`.

Останалите FAIL-ове в `$W`:
- `lead-form` и `deal-email-templates[/create]` — 302 по дизайн (към формата и към таба);
- `[ajax] attendances` — 500 `Could not parse '01--'` без параметри (същото в `main`);
- `two-fa-settings` — 500 `TwoFASettingController::index does not exist` (същото в `main`, маршрутът не е линкнат);
- billing — 404 (виж §2.5).

### 1.4 Видим текст (`--dump`, админ)

Сравнени са 128 Anagami Finance View и AFV страници с параметри и 77 AFV страници без параметри. **databackd: 0 разлики.** Anagami Finance View: 14 страници с очаквани upstream промени:
- нов блок „История на напомнянията“ във фактурата;
- „Интервал по избор“ в повтарящите се фактури;
- „Настройки за напомняния“ вместо старите полета;
- „Закъсняло пристигане“ в известията;
- Worldpay в платежните шлюзове;
- новият избор на продукт (снимка, SKU, цена);
- новият изглед на продукта и „Видеоклипове и документи“;
- „Пряк ръководител“ в профила на служителя (upstream разкоментира полето; „Подчинени“ се показва само ако има).

`">` в текстовия дъмп на `invoices/create` е артефакт на наивното махане на тагове в `smoke.php` (`data-content` съдържа `>`). HTML-ът е валиден, а двойното екраниране от A06 е на място.

## 2. Поправките от вълна 1

### 2.1 Публичният `file/{type}/{path}` (`FileController`, allowlist) — най-рисковата промяна

- **Кой генерира такива линкове.** Единствено `App\Traits\HasMaskImage::generateMaskedImageAppUrl()` (`route('file.getFile')`), и **само при S3-съвместимо хранилище**. При `local` връща директно `user-uploads/...`. Локално и (по всичко личи) на production хранилището е `local` (`file_storage_settings` = local), т.е. маршрутът днес изобщо не се генерира.
- **Кои извикват `HasMaskImage`.** Аксесорите `masked_*`:
  - `Company`: `masked_default_logo`, `logo`, `light`, `dark`, `login_background`, `favicon`;
  - `GlobalSetting`: същите + `logo_front`;
  - `User`: `masked_image_url`;
  - `InvoiceSetting`: `logo`, `template_header`, `template_footer`, `authorised_signatory_signature`;
  - `OfflinePaymentMethod` и `InvoicePaymentDetail`: `image`;
  - `SeoDetail`: `og_image`; `FrontDetail`: фон;
  - `CustomConfigProvider`/`SmtpConfigProvider`: `app.logo` за имейлите.

  Папките са точно 8-те от `MASKED_IMAGE_DIRS` (`app-logo`, `avatar`, `favicon`, `login-background`, `offline-method`, `invoice-template` = `InvoiceSetting::TEMPLATE_BANNER_PATH`, `front/homepage-background`, `front/seo-detail`). Модули с `HasMaskImage` няма (има само `LanguagePack`). Прикачените файлове към фактури и задачи, файловете на проекти и съобщения, `quill-images` и `user-uploads` **не** минават през този маршрут. Те се обслужват като статични файлове или през автентикирани `*.download`.
- **Тест** (`file-route-check.php`). Симулиран S3 с фиктивни ключове (подписването е офлайн), URL-ите са генерирани точно като в приложението, а заявките са като гост през HTTP kernel-а. **95 pass / 0 fail:**
  - реалното лого от базата (`app-logo/demo-fin-group-logo.png`) през `company.masked_default_logo`/`masked_logo_url`/`masked_light_logo_url` дава 302 към `…/app-logo/demo-fin-group-logo.png`;
  - всички 18 `masked_*` аксесора (стойности в паметта, без запис) дават 302 към правилния обект;
  - всички 8 папки × 7 разширения (png, jpg, jpeg, gif, webp, bmp, ico; `Files::generateNewFileName` пише разширението с малки букви) дават 302;
  - URL, криптиран със **стария общ ключ** (стари линкове), дава 302;
  - подправени пътища (`project-files/…pdf`, `invoice-files/…`, `message-files/…`, `task-files/…`, `quill-images/…`, `app-logo/../../.env`, `app-logo/dump.sql`, `avatar/x.php`) — 404 с новия и със стария ключ (16/16).
- **Проверено, че няма скрит проблем с `/` в `{path}`.** Маршрутът няма `where('path', '.*')`, но `encryptDecrypt` кодира двойно в base64. Външният base64 на ASCII base64 текст никога не дава `/` или `+`, затова маршрутът винаги съвпада.
- **Остатъчен риск (нисък, само при S3).** Лого, фавикон и аватар нямат валидация на типа освен черния списък в `Files::validateUploadedFile` (`svg`/`php`… са забранени). Файл `.avif`, `.tiff`, `.jfif` или `.heic` би се качил, но маскираният URL ще даде 404. Ако някога се мине на S3, или да се добавят тези разширения в `MASKED_IMAGE_EXTENSIONS`, или да се сложи `mimes:` на качванията.
- **`quill-image/{image}`** (`ImageController::getImage`) чете файла чрез `file_get_contents(asset_url…)` по HTTP, затова не може да се тества без сървър. В базата няма записани `quill-image/` линкове (търсено във всички 290 текстови колони). Гост с невалиден код получава 404. Логиката е проверена от A09.

### 2.2 Публичните подписани страници

URL-ите са генерирани с `temporarySignedRoute` (`public-urls-gen.php`; `APP_KEY` е еднакъв в двете дървета). Резултатите са като гост (`guest-W.txt`) и като влязъл админ в `$W` и `main` (`out/{W,M}-public.txt`):

| URL | гост `$W` | админ `$W` | админ `main` |
|---|---|---|---|
| `/invoice/{hash}?signature` (фактура 601) | 200 | 200 | 200 |
| `/contract/{hash}?signature` (договор 1) | 200 | 200 | 200 |
| `/task-board/{hash}?signature` (проект 1) | 200 | 200 | 200 |
| `/gantt-chart/{hash}`, `/task/{hash}` | 200 | 200 | 200 |
| `/invoice/download/{hash}` (PDF) | 200 | 200 | 500 `MD5()` (локален MySQL 9.6, A09 §4.4) |
| `/invoice/download/601` (по id) | 404 | 404 | 500 (същото) |
| `/invoice/{hash}`, `/contract/{hash}` без подпис | 403 | 403 | 403 |

Оферти и предложения няма в демо базата. A04 е минал целия публичен поток в транзакция: 33/33. Старият `/invoice/download/{md5(id)}` е линкван само от публичната страница на фактурата (не от имейлите), така че стари изпратени линкове не се чупят. Всички извиквания на маршрутите с `{hash}` подават `hash`; единственото изключение е мъртвият `super-admin/packages/stripe-payment.blade.php:127` (A05, искане към координатора).

### 2.3 Вход с код по имейл (`AuthHardening`)

`$S/a11/twofa-email-check.php` с `CACHE_DRIVER=array`, в транзакция с rollback. `two_fa_verify_via` е проверено след теста (остава `NULL`). Резултатите:
- `resend-code` без `user_id` → 200;
- грешен код → 302 обратно;
- верен код + чужд `user_id` → 403;
- верен код без challenge → 403;
- верен код без `user_id` → 302 `/account/dashboard`, влязъл (id 2).

**5/5 OK.**

### 2.4 log-viewer

| Потребител | `/log-viewer` | `/log-viewer/api/files` |
|---|---|---|
| гост | 302 → `/login` | — |
| админ `admin@demofinans.bg` | 403 | 403 |
| служител `g.petrov@` | 403 | 403 |
| супер админ | 200 | 200 |

`main` дава същото. Smoke отбелязва `api/files` като FAIL „untranslated keys“, но това е име на лог файл: фалшиво попадение, както при A11.

### 2.5 Billing за админ на компания

`/account/settings/billing`, `/billing/upgrade-plan`, `/billing/package` и `/billing/offline-payment` дават **404** (+ajax) и в `$W`, и в `main` (`HideBillingPages`). Префиксът `billing.` не е пускан нарочно: съдържа `billing.paystack.callback`, който прави изходяща заявка към api.paystack.co.

Освен това, като гост: `/down/x` и `/up/x` дават 404, `/file/image/abc_masked.png` и `/quill-image/abc` дават 404, `/login` дава 200.

## 3. Езикови проверки

- **`lang-audit --strict`:** `$W` — exit 0, **0 критични** (66 файла, 10 706 ключа в eng и bg, warn 714, info 524); `main` — exit 0 (60 файла, 10 433 ключа, warn 662, info 509). Новите 68 бележки спрямо `main` (`lang-issues-new.tsv`) са фалшиви или нарочни:
  - `bg_latin`/`bg_title_case` за марки: Worldpay (14), Google Meet (6), PayFast, `envato.froid.works`;
  - „Ви“ в учтивата форма;
  - `bg_lowercase_start` за части от изречение (`invoiceSettings.afterDueDate/beforeDueDate/startingAfter/times/remindersShort`, `recurringInvoice.days/weeks/months/years`), които се вмъкват след число или в средата на изречение.
- **`hardcoded-text --list`:** `$W` — 270 находки в 130 файла; `main` — 271. **Нови спрямо `main`: 6, и всичките са фалшиви:**
  - `sections/menu.blade.php` (2) — PHP условия (`module_enabled('Purchase')…`, `manage_lead_custom_forms`);
  - `recurring-invoices/ajax/create.blade.php` и `recurring-invoices/edit.blade.php` — JS `var unitLabel = $('#billing_unit option:selected').text();`;
  - `leads/lead-forms/fields.blade.php` — кодът за вграждане `<iframe …>`, показан като текст;
  - `update-settings/partials/partner-products-promo.blade.php` — `alt="Froiden"` (марка).

  **Реални нови твърди текстове: 0.**
- Наблюдение (не е текст): новото промо `update-settings/partials/partner-products-promo.blade.php` (страницата за обновления на супер админа) зарежда картинка от `https://envato.froid.works/logo-froiden.png` и линк към техния магазин. Това е външна заявка към Froiden при всяко отваряне на страницата, само за супер админа. Нисък приоритет; по желание да се скрие с `databackd_hidden`.

## 4. AFV проверки в `$W` (`CACHE_DRIVER=array`)

| Команда | Резултат |
|---|---|
| `databackd:check-formulas` | **159/159** OK |
| `databackd:check-consolidation` | **39/39** OK |
| `databackd:check-forecasts` | **81/81** OK |
| `databackd:check-security` | **83 passed, 0 failed, 0 skipped** |

Забележка: P9-06 в `check-security` („forgot-password отговаря еднакво“) изпраща реален `ResetPassword` през SMTP от базата (`smtp.gmail.com`, `myemail@gmail.com`). Локално автентикацията пада (535) и нищо не се изпраща, но в лога остава ERROR. За R2/A11: в теста да се ползва `Notification::fake()` или `mail.default=array`.

## 5. Визуално (`snapshot.php`, desktop 1440 + mobile 390, `snap/`)

| Страница | Наблюдение |
|---|---|
| `/account/dashboard` | ОК, без счупени елементи. Менюто е като в `main` (Анализ / Операции / Администрация, скритите модули ги няма). Пре-съществуващо, същото в `main`: „0s Тази седмица“, „Продължителност: 0s“, „Почивка: 0s“ (`s` не е преведено). Мобилната версия е ОК. |
| `/account/databackd/reports/dashboard` | ОК: KPI картите, графиките (`dbkChart`), донъти, регионите, сегментите и топ 10 се рендират, числата са по локала (`2 226 012,65 €`). На мобилно таблицата „Топ 10“ се скролва хоризонтално (пре-съществуващо). |
| `/account/invoices` | ОК: таблицата, филтрите и пагинацията са на място. На мобилно етикетът „Продължителност“ се пренася по средата на думата („Продължително / ст“) — пре-съществуващо (същият текст в `main`), CSS на филтъра. |
| `/account/products/1` | Новият upstream изглед се рендира добре и на двете ширини. „Себестойност 72,00 €“ (S8) е на място. Видими въпроси: (1) показва се само **„Официална продажна цена 144,00 € — с включени всички данъци“**, т.е. цената със ДДС; нетната цена (120 €, която `main` показваше като „Цена“) я няма; (2) значката „Разрешено“ не казва какво е разрешено (покупка от клиента; известно от A06); (3) „HSN/SAC“ е индийско поле (пре-съществуващо, преди „Hsn/Sac“). |

## 6. Компилация на всички Blade изгледи

`blade-compile.php`: зарежда приложението (с регистрираните директиви и компоненти) и минава всеки `resources/views/**/*.blade.php` през `app('blade.compiler')->compileString()`. Резултатът се пише в `$S/a13/compiled/W/` (не в `storage/framework/views`). После `blade-lint.sh` пуска `php -l` на всеки файл.

- **1595 изгледа, 0 грешки при компилация, 0 синтактични грешки.**
- Самопроверка: нарочно счупен файл се хваща (`LINT-FAIL`).
- Отделно: `php -l` на всички 262 PHP файла (без Blade), добавени или променени в `main..HEAD` — 0 грешки.

## 7. Други находки (не са регресии)

1. **`app/Providers/CustomConfigProvider.php::setMailConfig()`** — upstream промяна, A11 е за сведение, R2 за деплоя:
   - **Преди:** `Crypt::decryptString($setting->mail_password)` хвърляше при недешифрируема или `NULL` парола. Външният `catch` поглъщаше грешката и **пропускаше и `setPushNotification`, `setSessionDriver`, `translateSettingConfig`, `setStripConfigs`**.
   - **Сега:** `catch` пада към суровата стойност, записва `Log::warning('SMTP password decrypt failed…')` и продължава.
   - **Последствия:** (а) при такава парола в лога влиза WARNING **при всяка заявка** — локално 69 за ~20 минути; (б) настройките за сесия, push и Stripe от базата вече се прилагат и в този случай (по-коректно, но е промяна в поведението); (в) за SMTP се ползва суровата стойност като парола.
   - **Важи само ако** `APP_ENV` не е `demo`, `development` или `production` (условието в `setMailConfig`).
   - **След деплоя:** `grep -c 'SMTP password decrypt failed' storage/logs/laravel-$(date +%F).log`. Ако расте, паролата в Супер админ → SMTP да се запише наново.
2. **Продуктът** (`products/ajax/show.blade.php`, upstream): ключът `app.authorizedSellingPrice` е преведен като „Официална продажна цена“. Предложение за A01: „Продажна цена с ДДС“ или „Цена с данъци“, защото „официална“ подвежда. По желание A06 да покаже и нетната цена (`$product->price`) до цената с данъци; за счетоводна аудитория тя е основната.
3. **Изглед за избор на продукт** (`products/ajax/select-option.blade.php`, upstream): за продукт без снимка се изпълнява `$item->files()->first()`, т.е. по една заявка на продукт във формите за фактура, оферта и т.н. (N+1). При 20 продукта е без значение; при стотици — за R1.
4. **Артефакти на smoke** (за координатора, при бъдещи пускания):
   - в един процес с ~400 заявки паметта расте и 128M не стигат при Excel експортите (`FinEntriesExport`, `ProjectTimeLogsExport`), еднакво и в `main` → пускай с `php -d memory_limit=3G`;
   - логът в `$W` съдържа грешки от паралелни агенти (напр. `InvoiceController.php:1493` с несъществуващ проект, `BankAccountController` с дата `01-01-2026`), затова сравнението на логовете не е надеждно; меродавни са изходите на smoke.
5. **Пре-съществуващи** (същото в `main`, не са от обновяването):
   - `two-fa-settings` → 500 (липсва `index`);
   - `dashboard/private_calendar` за клиент → 500 (`DashboardController.php:314`);
   - `[ajax] attendances` без параметри → 500;
   - `invoices/committed-modal` → 500 без модула Purchase.

## 8. Искания към други файлове

- **A01** (`resources/lang/bg/app.php`, ключ `authorizedSellingPrice`): `'Официална продажна цена'` → `'Продажна цена с ДДС'` (или `'Цена с данъци'`). Само предложение; английското „Authorized selling price“ е upstream.
- **R2 / A11** (`app/Console/Commands/Databackd/CheckSecurity.php` ~ред 640–655, тест P9-06): да не праща реален имейл (`Notification::fake()` или `config(['mail.default' => 'array'])` в теста).
- **R2 / деплой:** т. 7.1 — проверка на лога след деплоя.
- **По желание (S3 в бъдеще):** т. 2.1 „остатъчен риск“ — `MASKED_IMAGE_EXTENSIONS` + `avif`, `tif`, `tiff`, `jfif`, или `mimes:` на качванията на лого, фавикон и аватар.

## 9. За деплоя

- Нищо ново от регресията не блокира деплоя. Ограничението във `FileController` не засяга `local` хранилище: маскираните URL-и не се генерират. При S3 всички легитимни пътища минават.
- След деплоя, освен списъка на A11: `/login`; `/account/dashboard`; `/account/databackd/reports/dashboard`; `/account/products/<id>` (новият изглед); фактура → „Копирай линка за плащане“ → отваря се като гост; `/invoice/download/<hash>` → PDF; `/log-viewer` като админ (403); WARNING-ът от т. 7.1 в лога.
- Скриптовете от тази проверка могат да се пуснат отново: `$S/a13/run-all.sh` (≈ 6 мин, паралелно в двете дървета), `php $S/a13/compare.php $S/a13/out`, `CACHE_DRIVER=array php $S/a13/file-route-check.php "$W"`.
