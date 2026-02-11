# A02 — фактури, повтарящи се фактури, настройки на фактурите, напомняния (upgrade 6.0.19)

Worktree `ws-merge` (клон `upstream/worksuite-v6.0.19`), база `databackd_upgrade_local`. Списък: `assign/A02-invoices.txt` (35 файла: 5 BOTH, 30 UP).

## Какво е проверено (по файлове)

### BOTH (сравнени три версии: `37235f8` / `main` / `vendor/worksuite` → резултат)

За всичките 5 файла `git diff vendor/worksuite -- <файл>` е точно равен на `git diff 37235f8 main -- <файл>`, а `git diff main -- <файл>` е точно upstream промяната — т.е. и двете страни са налице без загуби и без дублирания.

| Файл | Наше (запазено) | Upstream (налице) |
|---|---|---|
| `app/DataTables/InvoicesDataTable.php` | CUS_000329: `$packageAllowInvoice = checkCompanyCanAddMoreInvoices(...)` и всички 11 условия в действията | значка „N напомняния“ в колоната за статус |
| `app/Http/Controllers/InvoiceController.php` | CUS_000329 (`// WORKSUITESAAS` проверка на `max_invoices` в `store`, `clearCompanyValidPackageCache` в `index`/`destroy`); S6 — `enable_php` го няма и на двете PDF места (`grep enable_php` → 0) | `applyInvoiceReminderSettings()` в `store`/`update`, `reminderHistories` в `show`, `markManualSent` в `remindForPayment`, `pdfLoadHtml()`, продукти с `files` и сортиране |
| `app/Observers/InvoiceObserver.php` | CUS_000329: `clearCompanyValidPackageCache` в `creating` и `deleted` | редовете на повтарящите се фактури не се дублират (`is_null($invoice->invoice_recurring_id)`, `!routeIs('recurring-invoices.*')`) |
| `resources/views/invoice.blade.php` | S7: заглавие/бутони на модала, описание на Razorpay (`ui-misc.payments.invoicePayment`) | `front.invoice_download` и `front.invoice_payment_failed` с `$invoice->hash`; Worldpay |
| `resources/views/invoices/ajax/show.blade.php` | S7: `ui-core.finance.tax_deducted` (2 места) | брой/последно напомняне, история на напомнянията, бутон „Напомняне за плащане“, Worldpay |

S11 (синхронизация на фактурите към AFV): в `InvoiceObserver` няма `databackd` код — синхронизацията е pull (`app/Services/Databackd/Data/InvoiceExpenseSync.php`, пуска се от `FinSyncController`). Ползва `status`, `issue_date`, `sub_total`, `discount*`, `total`, `exchange_rate`, `default_currency_id` и релациите `items/currency/client/clientdetails` — upstream не ги променя. Проверено с `preview()` за демо компанията на 6.0.19 кода: 165 платени фактури, 229 разхода, без грешки.

CUS_000329 в `invoices/index.blade.php` и `invoices/ajax/show.blade.php` (споменати в `APP-UPDATE-AND-CUSTOMIZATIONS.md` §3): нито `main`, нито резултатът имат маркери там (`grep -i "package|max_inv|checkCompany"` → 0) — нищо не е загубено, описанието в документа е неточно.

### UP (прочетени като код)

- `app/Console/Commands/SendInvoiceReminder.php` + `app/Services/InvoiceReminderService.php` — нова логика: напомняния преди падеж по правила (дни/часове, всяко правило веднъж — `reminder_before_sent`), след падеж „на всеки N часа/дни, започвайки M след падежа“, лимит (до плащане / N пъти / N дни / до дата), история `invoice_reminder_histories`. Командата е `hourly()` (Kernel вече е слят). **Демо компанията:** `reminder_before_enabled = 0`, `reminder_after_enabled = 0` за двете компании → сухо изпълнение на логиката (без събития и запис) дава 0 напомняния; ако „след падеж“ се включи с текущите настройки, първото пускане би пратило **27** напомняния наведнъж (всички просрочени неплатени фактури, `reminder_last_sent_at = NULL`). Нови компании: `CompanyObserver` създава настройките изключени.
- `app/Notifications/InvoiceReminder.php`, `InvoiceReminderAfter.php` — линк за плащане/PDF по настройките (`include_pdf` винаги `false` от формата → `new InvoiceController()` в конзолата реално не се вика); датата беше `toFormattedDateString()` (винаги английски) → поправено.
- `app/Http/Controllers/InvoiceSettingController.php` — таб `reminder` и `updateReminderSetting`: зад middleware-а `manage_finance_setting == 'all'` + модул `invoices`; `InvoiceSetting::findOrFail($id)` е с `CompanyScope` (чужд id → 404). `updateTemplate`: `template_header`/`template_footer` — `image|mimes:jpeg,jpg,png,bmp,webp|max:5120` (без SVG), качване с `Files::uploadLocalOrS3` (хеширано име) в `invoice-template/`, изтриване по `*_delete=yes`. В БД се записва само името на файла.
- `app/Http/Requests/UpdateInvoiceReminderSetting.php` — валидира правилата, единиците, лимита (`reminder_limit_date: date`); `*_times`/`*_days` не се валидират, но се режат с `max(1, (int))`. `UpdateTemplateSetting` — виж горе (`template` е само `required`, без `in:` — както и преди, пред-съществуващо).
- `app/Models/{Invoice,InvoiceSetting,RecurringInvoice,InvoiceReminderHistory}.php` — casts, `reminderHistories()`, `reminderSettingsArray()`, `template_header_url`, `nextDateFrom()`/custom интервал, `rotation_label`; `InvoiceReminderHistory` е с `HasCompany`.
- `app/Console/Commands/AutoCreateRecurringInvoices.php`, `app/Observers/InvoiceRecurringObserver.php`, `RecurringInvoiceController`, `Store/UpdateRecurringInvoice` — custom интервал (`rotation=custom`, `billing_interval` ≥ 1, `billing_unit` от списък — валидирано), следващата дата през `nextDateFrom()` (старият observer мутираше `issue_date`), Worldpay auto-pay (A05). Няма повтарящи се фактури в тестовата база.
- Изгледи: `invoice-settings/ajax/{general,reminder,reminder-form,reminder-before-row,template}`, `invoice-settings/index` (нов таб „Напомняния“), `invoices/ajax/{create,edit,reminder-scripts}`, `invoices/stripe/stripe-payment` (`{hash}`), `recurring-invoices/{ajax/create,edit,index}`, `InvoiceRecurringDataTable` — целият видим текст е през `__()`/`@lang` (без 5-те ключа на шаблон 6, виж по-долу); JS етикетите са през `@lang`. Линковете към публичните маршрути в моите изгледи подават `hash` (`invoice.blade.php` ×3, `invoices/stripe/stripe-payment`); рендерираната публична страница за фактура 796 съдържа `/invoice/download/<hash>`.

## Какво е променено

| Файл | Промяна (маркирана `databackd (upgrade 6.0.19)`) |
|---|---|
| `resources/lang/{eng,bg}/ui-invoices.php` | **нови**: `template.{banners_title,top_image,top_image_info,bottom_image,bottom_image_info}`, `limits.max_invoices_reached` |
| `resources/views/invoice-settings/ajax/template.blade.php` | `modules.invoiceSettings.template{CustomParts,Header,HeaderInfo,Footer,FooterInfo}` липсват в lang файловете на самия 6.0.19 (и в eng) → `ui-invoices.template.*` |
| `app/Http/Controllers/InvoiceController.php` | (1) CUS_000329: `superadmin.maxInvoicesLimitReached` никога не е съществувал (показваше се суровият ключ) → `ui-invoices.limits.max_invoices_reached`; (2) `remindForPayment` (GET, без проверка на правата, а сега и пише история/брояч) → `abort_403` за клиент или без `view_invoices` |
| `app/Services/InvoiceReminderService.php` | `parseFromRequest`: при неразпозната дата за лимита пазим `Carbon::parse()` или `null` вместо суровия низ (иначе `Carbon::parse()` в часовата команда хвърля и спира напомнянията за следващите компании; `StoreInvoice`/`UpdateInvoice` не валидират полето) |
| `app/Notifications/InvoiceReminder.php`, `InvoiceReminderAfter.php` | падежът е `translatedFormat($company->date_format)` вместо `toFormattedDateString()` („02.11.2026“ вместо „Nov 2, 2026“ в BG имейла) |
| `app/Models/RecurringInvoice.php` | `rotation_label` → `__('app.<rotation>')` (Месечно, Тримесечно…) вместо суровото „Monthly“; ползва се и от `SubscriptionDataTable`/`ClientSubscriptionDataTable` |
| `app/DataTables/InvoicesDataTable.php` | `invoices.reminder_sent_count` в `select()` — без него upstream значката „N напомняния“ никога не се показва |
| `resources/views/recurring-invoices/index.blade.php` | бутонът „Абонаменти“ се крие с `databackd_hidden('menu', 'subscriptions')` (6.0.19 го показва и при Worldpay) |

## Къстомизации — потвърдени

- **CUS_000329**: `InvoiceController` (WORKSUITESAAS + 2× `clearCompanyValidPackageCache`), `InvoiceObserver` (2×), `InvoicesDataTable` (11 условия) — идентични с `main`.
- **S6**: `enable_php` го няма в `InvoiceController`; нищо не е отслабено; новият `remindForPayment` е затегнат.
- **S7**: преводите в `invoice.blade.php` и `invoices/ajax/show.blade.php` са налице (`git diff vendor/worksuite` = `git diff 37235f8 main`).
- **S11**: синхронизацията работи на новия код (`preview()` без грешки).
- **S1/S8**: бутонът за абонаменти е скрит като менюто.

## Намерени проблеми (без промяна — за знание)

1. **Поведение на напомнянията след миграцията** (виж „За деплоя“): компания със `send_reminder_after > 0` получава `reminder_after_enabled = 1`; старият режим `reminder = 'after'` (еднократно) става „на всеки N дни до плащане“, а първото часово пускане праща напомняне за **всички** просрочени неплатени фактури наведнъж.
2. Правилото „N дни преди падежа“ съвпада с първото пускане на деня → имейлите излизат около 00:xx местно време (преди: 01:50 веднъж дневно).
3. `InvoiceController::sendInvoice` (POST) няма проверка на правата — пред-съществуващо в 6.0.10 и 6.0.19 (само CompanyScope). Не е пипано — за R2.
4. `reminder_limit_date` се валидира с `date` (strtotime) — при формат на компанията `d/m/Y` валидна дата може да бъде отхвърлена (upstream).
5. Премахване на ред „преди падеж“ в средата и добавяне на нов дава дублиран индекс в `reminder_before_rules[...]` (JS на upstream, губи се едно правило) — козметично.
6. Повтарящите се фактури (`AutoCreateRecurringInvoices`, `RecurringInvoiceController`) създават фактури без проверката на `max_invoices` (CUS_000329) — пред-съществуващо.
7. Демо клиентите са с реалистични домейни (`@balkan.bg` …) — напомнянията за демо компанията трябва да останат изключени.

## Искания към други файлове

- **A01 (lang)** — `resources/lang/{eng,bg}/superadmin.php`: липсва `maxClientsLimitReached` (и в eng, и в bg; ползва го `ClientController` — CUS_000329, показва се суровият ключ) и `bg` на `maxEmployeesLimitReached`. Предложение:
  ```php
  'maxClientsLimitReached' => 'You have reached the maximum number of clients allowed in your plan. Please upgrade your plan to add more clients.',
  // bg
  'maxClientsLimitReached' => 'Достигнахте максималния брой клиенти, разрешен от Вашия план. Надградете плана, за да добавяте още клиенти.',
  'maxEmployeesLimitReached' => 'Достигнахте максималния брой служители, разрешен от Вашия план. Надградете плана, за да добавяте още служители.',
  ```
  (`superadmin.maxInvoicesLimitReached` вече не се ползва — фактурите са на `ui-invoices`.) Ключовете `modules.invoiceSettings.template{CustomParts,Header,HeaderInfo,Footer,FooterInfo}` не трябват — заменени с `ui-invoices.template.*`. Всички останали нови upstream ключове от моите файлове вече имат bg (проверено програмно: 0 липсващи).
- **A07 (crm)** — `ClientController.php:166` и `:387` — същият липсващ ключ `superadmin.maxClientsLimitReached` (решава се от A01 по-горе или със собствен `ui-crm` ключ).
- **A05 (billing)** — `resources/views/super-admin/packages/stripe-payment.blade.php:127` (`route('front.invoice_payment_failed', [$company->id])`) и `super-admin/billing/payment-method-show.blade.php:813` (`':id'` → `invoiceId`): маршрутът вече е `{hash}` и `HomeController::invoicePaymentfailed` търси `Invoice::where('hash', …)` → тези извиквания дават 404 (същото е и в чистия 6.0.19; и преди подаваха id на компания/SaaS фактура към маршрут за фирмени фактури).
- **A03 (pdf)** — как се извеждат `template_header`/`template_footer`: в БД е само името на файла (качено с хеш в `user-uploads/invoice-template/`); `InvoiceSetting::template_header_url`/`template_footer_url` = `asset_url_local_s3('invoice-template/<file>')`; изглеждат се само в `*/pdf/invoice-6` чрез `invoices/pdf/partials/template-6-banners` (горе, статично) и `template-6-footer` (`position: fixed; bottom: 0` → на всяка страница; `template-6-styles` добавя 110px долен padding, когато има footer). Ескейпнато е (`{{ }}`), dompdf трябва `isRemoteEnabled` (налично). Проверих: при всичките 6 шаблона footer-ът е извън двуезичния `@foreach` (CUS_000328) — добре. `pdfLoadHtml()` (A11, `app/Helper/start.php`) задава `defaultFont = DejaVu Sans` за всички PDF на фактурите.
- **A11 (core)** — `app/Console/Kernel.php`: `send-invoice-reminder` вече е `hourly()` (слято) — промяна не трябва; само да се знае за деплоя (виж по-долу).
- **A12 (db)** — `2026_07_14_000001_add_advanced_reminder_settings.php::migrateLegacySettings()` — виж „За деплоя“; локално двете компании са 0/0 → нищо не се включва.

## Резултати от проверките

- `php -l` — без грешки: `InvoiceController`, `InvoicesDataTable`, `RecurringInvoice`, `InvoiceReminder`, `InvoiceReminderAfter`, `InvoiceReminderService`, `lang/{eng,bg}/ui-invoices.php`.
- `smoke.php invoices. --ajax` — 10 FAIL, всички познати от baseline-а (`stripe_modal`, `committed_modal` (няма `Modules\Purchase`), `get_client_company` 500; `add_item`, `offline_payment_modal` 404 — без параметри).
- `smoke.php invoice-settings. --ajax` + `?tab=reminder|template|general` — OK; познатият `invoice-settings.create` 500 (няма метод).
- `smoke.php recurring-invoices. --ajax` — 4/4 OK (show/edit — няма записи в базата).
- `--url` за `/account/invoices/796` (неплатена, изпратена), `/796/edit`, `/792` (платена), `/800`, `/800/edit` (чернова), `/download/796`, DataTables JSON `/account/invoices?draw=1…` — всички 200 (и `--ajax`).
- Публична страница (подписан URL за фактура 796) — 200, линкът за изтегляне е с hash; `/invoice/download/<hash>` — 200.
- Клиент `p.yordanov@balkan.bg`: `/account/invoices/payment-reminder/777` → 403 (новата проверка; история/брояч не са пипнати); `/account/invoices`, `/777` → 403 (познато, по права). Проверката на guard-а: админ ALLOW, служител `g.petrov` (без `view_invoices`) DENY, клиент DENY.
- Сухо изпълнение на логиката на `send-invoice-reminder` (без `event()`/запис): компания 4 — 31 кандидата, 0 напомняния.
- Рендиране на двата имейла за фактура 796 (без изпращане, bg): „Напомняне за фактура …“, падеж `02.11.2026`.
- `hardcoded-text.php --list`, филтрирано до моите файлове — само фалшиви попадения (`placeholder="0.00"`, код в `@php`, JS шаблони с `@lang`).
- `lang-audit.php --file=ui-invoices` — 0 находки; всички ключове в моите файлове съществуват в eng и bg.
- Видимият текст (`smoke --dump`) на таба „Напомняния“, „Шаблон“, show/edit на фактура — английските текстове при първата проверка бяха само upstream ключове без bg (A01 ги добави междувременно) и 5-те липсващи ключа на шаблон 6 (поправено).

## За деплоя

- Миграции (A12): `2026_07_14_000001_add_advanced_reminder_settings`, `2026_07_14_000002_create_invoice_reminder_histories_table`, `2026_07_17_000000_add_custom_billing_interval_to_invoice_recurring_table` (ALTER на ENUM `rotation`), `2026_10_08_000001_add_template_header_footer_to_invoice_settings_table`.
- **Преди `migrate` на production** провери:
  ```sql
  SELECT company_id, send_reminder, reminder, send_reminder_after FROM invoice_settings
  WHERE send_reminder > 0 OR send_reminder_after > 0;
  ```
  Ако има редове с `send_reminder_after > 0`: след миграцията „след падеж“ е включено и първото часово `send-invoice-reminder` праща по едно напомняне за всяка просрочена неплатена фактура на компанията, после на всеки N дни до плащане. Ако това не е желано — веднага след `migrate`:
  ```sql
  UPDATE invoices SET reminder_last_sent_at = NOW()
  WHERE company_id IN (...) AND status NOT IN ('paid','canceled','draft') AND due_date < CURDATE();
  ```
  (или `UPDATE invoice_settings SET reminder_after_enabled = 0 WHERE company_id IN (...)` до потвърждение от клиента).
- Scheduler: `send-invoice-reminder` е на всеки час (беше 01:50 дневно) — изисква работещ `schedule:run`; няма нова команда.
- Качванията на шаблон 6 отиват в `public/user-uploads/invoice-template/` (папката се създава при първото качване; при rsync да не се трие).
- Кеш/сесия: настройките на фактурите се кешират в сесията (`invoice_setting`); след деплоя старите сесии виждат новите полета с подразбиращи се стойности — безопасно.
- Нови файлове за commit: `resources/lang/eng/ui-invoices.php`, `resources/lang/bg/ui-invoices.php`.
