# A07 — CRM: потенциални клиенти, сделки, имейли от сделки, форми, клиенти (6.0.10 → 6.0.19)

Агент A07, 2026-10-28. Worktree `ws-merge` (клон `upstream/worksuite-v6.0.19`). Списък: `assign/A07-crm.txt` (53 файла: 1 BOTH, 52 UP).

## Обобщение

- Сливането на `ClientController` е смислово правилно: upstream проверката на правата + `except([...])` срещу mass assignment и нашият лимит `max_clients` (CUS_000329) работят заедно (проверено с POST в транзакция).
- Модулът „Потенциални клиенти/Сделки“ остава скрит. Новият пункт „Форми за потенциални клиенти“ е вътре в `@unless (databackd_hidden('menu', 'leads'))`. Табът „Шаблони за имейли“ е в страницата на настройките за потенциални клиенти, а тя е скрита (`settings.lead_settings`). В бързото добавяне и на таблото няма нов вход към модула.
- Намерени и поправени в моите файлове: stored XSS в шаблоните за имейли (`dangerouslyPasteHTML`), имейл до произволен адрес през SMTP на компанията, суров `{!! !!}` в новите изгледи, `exists` правила без company scope (вкл. анонимната форма), липсващ ключ за лимита на клиентите (показваше се суровият ключ), твърд/слепен текст в новите изгледи.
- Не са поправени, защото файловете са чужди: в анонимната форма данните на съществуващ контакт се презаписват; няма throttle (виж „Искания“).

## Проверено (по файлове)

| Файл | Резултат |
|---|---|
| `app/Http/Controllers/ClientController.php` (BOTH) | `store`: първо upstream `abort_403(add_clients)`, после `// WORKSUITESAAS` лимитът `max_clients`, после `DB::beginTransaction()`. `$request->except([... 'status', 'admin_approval', 'company_id', 'user_auth_id' ...])` — проверено: POST със `status=deactive`, `company_id=999` създава клиент с `company_id=4`, `status=active`. Лимитът връща грешка, когато се надвиши. `update` (активиране над лимита + `PackageUpdateNotify`), `destroy`/`deleteClient`, `changeStatus` (`clearCompanyValidPackageCache`) — запазени. **Поправено:** `superadmin.maxClientsLimitReached` не съществува нито в `eng`, нито в `bg` (`bg/superadmin.php` липсва), затова потребителят виждаше суровия ключ. Сега е `ui-crm.clients.maxClientsReached` (2 места). |
| `ImportClientJob`, `ClientDetailsObserver`, `clients/index` | Не са променени от upstream, нашите редове (CUS_000329) са непокътнати. |
| `clients/ajax/profile.blade.php` | Upstream поправка за `null` език. Стойността минава през `clean_editor` (`data-row html=true`). Без нов текст. |
| `DealController`, `LeadBoardController`, `LeadContactController` | `Product::with('files')`, `activeCustomFieldsForDefaultForm()`. Новите табове `emails` са с `view_deal_email` + досегашната проверка на достъпа до сделката/контакта. |
| `DealEmailController` | Права: `send_deal_email`/`view_deal_email` ≠ `none` + `authorizeDealAccess` (scope на `view_deals`). `DealEmailHistory` е с `HasCompany`, а свалянето на прикачен файл е през историята (CompanyScope) и `download_local_s3`. |
| `DealEmailService`, `Mail/DealEmail` | Header injection: адресите минават `email` / `FILTER_VALIDATE_EMAIL`, а Symfony Mime кодира CR/LF в темата и в името на файла. Прикачени файлове: `Files::validateUploadedFile` (забранени разширения/MIME, двойни разширения, лимит на пакета за място), по 10 MB на файл. **Поправено:** забранен файл оставяше ред „изпратен“ без реално изпратен имейл, защото историята се записваше преди проверката. |
| `SendEmailRequest` | **Поправено:** `to` беше произволен адрес, т.е. всеки служител с `send_deal_email` (миграцията го дава на ниво `view_deals`) можеше да праща произволни писма с прикачени файлове от SMTP на компанията. Виж „Променено“. |
| `DealEmailTemplateController/Service/Observer`, заявките | **Поправено:** `parseMergeFields` слагаше името на контакта/фирмата/сделката сурово в HTML тялото, а то отива в Quill през `clipboard.dangerouslyPasteHTML()`. Име от импорт (CSV не минава през XSS middleware) като `<img src=x onerror=…>` се изпълнява у служителя, който праща имейла. Сега стойностите в тялото са през `e()`. Темата отива в поле с `.val()`, затова остава без escape. `exists` в `fetchTemplate` вече е само за текущата компания. |
| `LeadFormController`, `LeadForm`, `LeadFormObserver`, `LeadFormsDataTable`, `LeadForm/*Request` | Права `manage_lead_custom_forms == all` + модулът `leads`. `LeadForm` е с `HasCompany`. Slug-ът е уникален за компанията. В DataTable-а `name`/`slug`/линкът минават през `e()`. **Поправено:** `exists:lead_pipelines,id` и др. бяха без company scope, а `HomeController::leadStore` ги чете като гост (без CompanyScope). Сега са само на компанията и етапът трябва да е от избрания pipeline. |
| `LeadCustomFormController` | Пренасочва към формата по подразбиране; `update`/`sortFields` са ограничени до нея. Наред е. |
| `Lead/StorePublicLead.php` | `lead_form_id` трябва да е на компанията и активен. Upstream махна `unique` за имейла (вж. „Искания“ към A04). **Поправено:** `source`, `category_id`, `product[]` от анонимната форма не се проверяваха (чужди id); `email` се проверява и когато полето е изключено. |
| `HomeController::leadForm/leadStore` (на A04, само преглед) | `/lead-form/{hash}/{slug?}` — формата се търси по хеша на компанията, само активна. `leadStore` е в CSRF изключенията (`VerifyCsrfToken::$except`, upstream — за iframe), няма throttle, reCAPTCHA е по избор (локално е `deactive`). Виж „Искания“. |
| Нови изгледи `leads/ajax/{send-email,emails,email-show}`, `lead-contact/ajax/emails`, `lead-settings/{ajax/email-templates,create-/edit-deal-email-template-modal,index}`, `leads/lead-forms/*` | Всичко е през `__()`/`@lang`. Новите upstream ключове (`modules.deal.*`, `modules.lead.*`, `app.cc`, `app.body`) са на A01. Датите са през `company()->timezone` + `translatedFormat(company()->date_format …)` (S12). **Поправено:** `{!! $body !!}` → `{!! clean_editor(...) !!}` (S6); `aria-label="Close"`; слепени заглавия „Edit“ + „Lead Form“, „Add New“ + „Email Templates“ → цели фрази в `ui-crm`; вградените полета на формата се показваха с английското `field_display_name` от базата. |
| `leads/ajax/{create,edit,show,profile,history}`, `lead-contact/{index,show,ajax/create}`, `leads/index`, `lead-form.blade.php`, `DealsDataTable`, `LeadContactDataTable`, моделите | Upstream промени без нов твърд текст (`products.ajax.select-option*` е на A06). `Showroom` е no-op (модулът не е инсталиран). |
| Нашите S7 промени в `leads/**`, `lead-contact/**`, `clients/**`, `lead-settings/**` | `git diff main HEAD`: 0 загубени реда. |
| Скриване (S1) | `sections/menu` — блокът „Потенциални клиенти“ (с новия подпункт `lead-forms.index`) е в `@unless (databackd_hidden('menu', 'leads'))` — потвърдено. `setting-sidebar` — `!databackd_hidden('settings', 'lead_settings')`. `dashboard/employee/index` — `follow_ups`, `lead` скрити. `topbar` — няма потенциални клиенти/сделки. Маршрутите остават (по замисъл). |

## Променено (16 файла + 2 нови)

1. `app/Http/Controllers/ClientController.php` — ключ `ui-crm.clients.maxClientsReached` (2×).
2. `app/Http/Requests/Deal/SendEmailRequest.php` — `to` трябва да е имейлът на контакта по сделката (без значение главни/малки букви). `cc` — до 10 адреса, само контактът или потребители на компанията (`User` с `employeeDetail`, CompanyScope). `deal_email_template_id` — `Rule::exists(...)->where('company_id', …)`. `file` — до 10 файла. **Това е решение по политика** — ако трябва свободен получател, махни двете затваряния (маркирани `databackd (upgrade 6.0.19)`).
3. `resources/views/leads/ajax/send-email.blade.php` — полето „До“ е `readonly` (стойността е имейлът на контакта; бутонът „Изпрати имейл“ се показва само ако контактът има имейл).
4. `app/Services/DealEmailService.php` — `Files::validateUploadedFile()` за всички файлове преди `DealEmailHistory::create`.
5. `app/Services/DealEmailTemplateService.php` — `parseMergeFields(..., bool $escapeHtml = false)`; тялото се вика с `true`.
6. `app/Http/Controllers/DealEmailTemplateController.php` — `fetchTemplate`: `exists` само в текущата компания.
7. `app/Http/Requests/LeadForm/{Store,Update}Request.php` — pipeline/етап/категория/източник само на компанията; етапът — от избрания pipeline.
8. `app/Http/Requests/Lead/StorePublicLead.php` — `source`, `category_id`, `product.*` → `exists` в компанията на формата; `email` винаги `email:rfc,strict`.
9. `resources/views/leads/ajax/email-show.blade.php` — `clean_editor($emailHistory->body)`.
10. `resources/views/lead-settings/edit-deal-email-template-modal.blade.php` — `clean_editor($template->body)` (2×), `aria-label`, заглавие `ui-crm.emailTemplates.title.edit`.
11. `resources/views/lead-settings/create-deal-email-template-modal.blade.php` — `aria-label`, заглавие `ui-crm.emailTemplates.title.add`.
12. `resources/views/lead-settings/index.blade.php` — бутон `ui-crm.emailTemplates.add`.
13. `app/Http/Controllers/LeadFormController.php` + `resources/views/leads/lead-forms/ajax/edit.blade.php` — заглавие `ui-crm.leadForms.title.edit` (в контролера през `__()`, защото `returnAjax` го праща в JS `.html(response.title)`).
14. `resources/views/leads/lead-forms/fields.blade.php` — вградените полета са през ключовете на публичната форма (`modules.leads.<поле>`, `modules.module.product`, `modules.lead.source`); потребителските полета са с името си.
15. **Нови:** `resources/lang/eng/ui-crm.php`, `resources/lang/bg/ui-crm.php` — 8 ключа (едни и същи в двата).

Всички промени са маркирани `databackd (upgrade 6.0.19)`.

## Потвърдени къстомизации

- **CUS_000329** — `ClientController` (store/update/destroy/deleteClient/changeStatus), `ImportClientJob`, `ClientDetailsObserver` (`clearCompanyValidPackageCache`).
- **S1** — скриване на потенциални клиенти/сделки в менюто, настройките и таблото (вж. таблицата).
- **S6** — няма `enable_php`, суровият HTML в новите изгледи е през `clean_editor`, XSS middleware (`XssLinearMerge`) реже таговете в `body`/`subject` при запис.
- **S7** — новите изгледи са през `__()`. Нашите преводи в старите CRM изгледи са запазени.
- **S12** — датите в новите таблици са по часовата зона и формата на компанията.

## Проблеми, които остават (upstream, не са поправени — по преценка)

1. **Тялото на имейла от сделка губи форматирането.** `body` не е в `config/xss_ignore.php`, затова `XssLinearMerge` (и оригиналният Froiden `XSS`) прави `strip_tags`: `<p>а</p><p>б</p>` → „аб“. Същото е и при upstream. Ако модулът се включи: `body` в `xss_ignore` + `clean_editor()` при запис в `DealEmailService` и `DealEmailTemplateController` (`body` е общо име — първо провери другите форми с поле `body`). Засега — не.
2. `deal-email-templates.fetch` е зад middleware-а на контролера с `manage_deal_email_template`. Служител, който има право да праща имейли, но не и да управлява шаблоните (миграцията дава шаблоните само на админа), получава 403 при избор на шаблон. Поправка (не е направена, засяга права): в `DealEmailTemplateController::__construct` — `if ($request->route()?->getActionMethod() === 'fetchTemplate') { abort_403(user()->permission('send_deal_email') == 'none'); return $next($request); }` преди сегашната проверка.
3. `send_deal_email`/`view_deal_email` са със scope (`added`/`owned`/`both`), а кодът проверява само `!= 'none'` + достъпа до сделката по `view_deals`. Табът „Имейли“ на контакта показва всички имейли към контакта, без проверка на scope-а по сделки.
4. `LeadFormController::updateField` не валидира `status`/`required` (само за админ; при грешна стойност — SQL грешка 500).
5. Забранен прикачен файл → `Exception` от `Files` → HTTP 500 „Server Error“ (без ред в историята). Така е за всички качвания в upstream. Dropzone пак ограничава типовете с `DROPZONE_FILE_ALLOW`.
6. Прикачените файлове са в `public/user-uploads/deal-email-attachments/<id>/<md5(microtime)>.<ext>` (публични по URL, както всички upstream качвания). При изтриване на сделка редовете отпадат (cascade), но файловете остават.
7. Неуспешно изпращане показва текста на SMTP грешката (`meta.error`) на всеки с `view_deal_email`.

## Искания към други файлове

**A04 — `app/Http/Controllers/HomeController.php::leadStore`** (сигурност, публичен маршрут):
1. Upstream махна `unique` за имейла и сега **анонимно изпращане с имейла на съществуващ контакт презаписва данните му** (име, телефон, адрес, фирма, източник). Проверено в транзакция: контактът „A07 гост“ стана „Hacker“. Предложение — за съществуващ контакт попълвай само празните полета:
   ```diff
   +        $isExisting = !is_null($leadContact); // databackd (upgrade 6.0.19): an anonymous submission must not overwrite a known contact
            if (is_null($leadContact)) {
   ...
   -        if ($request->has('company_name')) {
   +        if ($request->has('company_name') && (!$isExisting || blank($leadContact->company_name))) {
   ```
   (същото за `website`, `address`, `name`→`client_name`, `mobile`, `city`, `state`, `country`, `postal_code`, `source_id`; редът `if ($request->has('email')) { $leadContact->client_email = … }` е излишен за съществуващ контакт). Новата сделка + бележката си остават, т.е. запитването не се губи.
2. По желание (решение на координатора): докато `config('databackd.hidden.menu.leads')` е `true`, никой не може да конфигурира формата, а тя пак приема анонимни запитвания за всяка компания (`company_id` и `lead_form_id` са поредни числа, хешът не е нужен за POST). Вариант: `abort_if(config('databackd.hidden.menu.leads'), 404);` в началото на `leadForm()` и `leadStore()`.
3. Публичната форма и съобщенията ѝ са на английски (`<html lang="en">`, локалът не се взима от компанията). Не е от тази версия.

**A11 — `routes/web-public.php`** (ред 45 и 47): throttle на анонимните POST-ове (няма CSRF, reCAPTCHA е по избор):
```diff
-Route::post('/lead-form/leadStore', [HomeController::class, 'leadStore'])->name('front.lead_store');
+Route::post('/lead-form/leadStore', [HomeController::class, 'leadStore'])->middleware('throttle:10,1')->name('front.lead_store'); // databackd (upgrade 6.0.19)
-Route::post('/lead-form/ticket-store', [HomeController::class, 'ticketStore'])->name('front.ticket_store');
+Route::post('/lead-form/ticket-store', [HomeController::class, 'ticketStore'])->middleware('throttle:10,1')->name('front.ticket_store'); // databackd (upgrade 6.0.19)
```

**A02 — `app/Http/Controllers/InvoiceController.php:216`**: същият проблем като при клиентите — `__('superadmin.maxInvoicesLimitReached')` го няма в `eng/superadmin.php`, а `bg/superadmin.php` не съществува, затова се показва суровият ключ. Предложение: собствен ключ в `ui-invoices.php` (напр. `'maxInvoicesReached' => 'You have reached the maximum number of invoices allowed in your plan.'` / `'Достигнахте максималния брой фактури, разрешен във Вашия план.'`). Също `app/Jobs/ImportClientJob.php:53` — `superadmin.updatePlanClientNote` липсва. Това е само причина за неуспех на job-а (не се вижда в UI), но е добре да е реален ключ.

**A01 — lang:** в моите файлове се ползват 29 нови upstream ключа (`modules.deal.*` за имейлите и шаблоните, `modules.lead.*` за формите, `app.cc`, `app.body`). При началото на работата нямаха `bg`. При последната проверка A01 вече ги е добавил в работното дърво (`eng` е в sentence case, `bg` е преведен). Липсва само `superadmin.emailCantUse`: `bg/superadmin.php` не съществува, а съобщението не се ползва (правилото `check_superadmin` не е в `rules()`), т.е. не е нужно нищо. Две дреболии: `modules.deal.templateUsed` = „Template Used“ е заглавие на колона, а `modules.lead.shareDirectLink` = „Share Direct Link“ е етикет („Share direct link“). При `mergeFieldsHelp` placeholder-ите `{contact_name}` … трябва да останат буквално и в `bg`.

**A10 / A12** — нищо. Менюто е наред. Миграциите на A12 създават формата по подразбиране и дават `send_deal_email`/`view_deal_email` на служителите по нивото на `view_deals` (модулът е скрит, но маршрутите работят).

## Резултати от проверките

- `php -l` — 9 PHP файла + 2 lang файла: без грешки.
- `smoke.php` (админ): `lead-forms.` и `lead-contact.` — всичко OK. `deals.` — 4 FAIL, `clients.` — 2 FAIL, `lead-settings.` — 2 FAIL; всички са и в базовото пускане преди промените. Причините: `follow_up_edit` без id → 404, `gdpr_consent` без параметър → 500 (`clients.gdpr_consent` е познат FAIL от фаза 1), `lead-settings.create` няма метод. `clients/{id}`, `clients/{id}/edit`, `?tab=profile` — OK. Служител `g.petrov` — 403 по права (очаквано).
- Свой in-process тест в една DB транзакция с rollback, с `Mail::fake`/`Notification::fake`/`Queue::fake` (`scratchpad/a07/crm-test.php`, `client-test.php`, `public-test.php`). Проверих, че след него не остават нито редове, нито файлове. Резултати:
  - Страници: `deals.show ?tab=emails` (обикновена + ajax), `send-email` (полето „До“ е readonly), `deal-emails.show` (тяло `<p>ok</p><img src="x" alt="x" />` — без `onerror`/`script`), `lead-contact.show ?tab=emails`, `lead-settings ?tab=email-templates`, създаване/редакция на шаблон, `lead-forms/2` (етикетите са на български), `lead-forms/2/edit` (заглавието е преведено) — 200, без сурови ключове.
  - Шаблон: `fetch` връща тяло `&lt;img src=x onerror=alert(1)&gt;Иван от Тест &amp; Ко` — escape-нато.
  - Изпращане: до контакта → успех; до друг адрес → 422 `toMustBeContact`; копие до външен адрес → 422 `ccNotAllowed`; копие до потребител на компанията + контакта → успех; чужд `deal_email_template_id` → 422; `evil.html` → отказ, без ред в историята.
  - Форми: чужд pipeline → 422, собствен pipeline + етап → успех.
  - Анонимна форма: чужди `source`/`category_id` → 422, собствени → успех. Презаписът на съществуващ контакт е потвърден — искане към A04.
  - Клиенти: `status`/`company_id` от заявката се игнорират; над `max_clients` → съобщението на `bg`.
- `hardcoded-text.php --list` за моите 53 файла: остава само 1 фалшиво попадение (кодът на iframe в `fields.blade.php:89`). Преди промените бяха и 2× `aria-label="Close"`.
- `lang-audit.php --file=ui-crm`: 0 проблема.

## За деплоя

- Миграции — нищо ново от A07 (таблиците и правата идват от миграциите на A12).
- Настройки/команди — няма. Новите `resources/lang/{eng,bg}/ui-crm.php` се качват заедно с кода.
- Рискове:
  - Получателят на имейл от сделка е ограничен до контакта (и копие до потребители на компанията) — съзнателна промяна спрямо upstream.
  - Анонимната форма приема само id-та на собствената компания.
  - Докато искането към A04 (точка 1) не е направено, анонимен посетител, който знае имейла на съществуващ контакт, може да презапише данните му. Засяга само компании с използвана форма — модулът е скрит.
