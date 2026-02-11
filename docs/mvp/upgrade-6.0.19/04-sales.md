# A04 — продажби (оферти, предложения, договори, кредитни известия, поръчки, банкови сметки, плащания, разходи, публични страници)

Списък: `assign/A04-sales.txt` — 16 BOTH + 26 UP. Worktree `ws-merge`, база `databackd_upgrade_local`. Git — само четене.

## 1. Проверено по файлове

### BOTH (16) — три версии (`37235f8` / `main` / `vendor/worksuite`) срещу файла в worktree-а

За всеки файл броят на променените редове в слетия `HEAD` е точно сумата „наши + upstream“ (няма загубени или застъпени hunk-ове); прочетени са и смислово.

| Файл | Наши промени | Upstream | Резултат |
|---|---|---|---|
| `BankAccountController` | CUS_000326 custom fields (create/store/show/edit/update/statement) | `pdfLoadHtml()` за извлечението | слято; **+ поправка на права** (виж 2) |
| `ContractController` | S6: махнат `enable_php` (2 места) | `pdfLoadHtml()` | слято, без `enable_php` |
| `CreditNoteController` | CUS_000326 + S6 `enable_php` | `Product::with('files')` (3 места), `pdfLoadHtml()` | слято |
| `EstimateController` | S6 `enable_php` | `Product::with('files')`, `pdfLoadHtml()` | слято |
| `EstimateTemplateController` | S6 `enable_php` | `Product::with('files')`, `pdfLoadHtml()` | слято |
| `HomeController` | S6 `enable_php` (PDF на предложението) | Worldpay modal, `{hash}` за invoice download / payment-failed / gantt data / proposal action, lead forms (`LeadForm`), `withoutGlobalScope(CompanyScope)` за предложението, `abort_403` за admin в `syncPermissions`, `pdfLoadHtml()` | слято; **+ 2 поправки и превод** (виж 2) |
| `PaymentController` | CUS_000326 + S7 (`Reply::error` → `ui-core.messages.transaction_id_*`) | `pdfLoadHtml()` | слято, преводите са налице |
| `ProposalController` | CUS_000326 + S6 `enable_php` | `Product::with('files')`, `pdfLoadHtml()` | слято |
| `ProposalTemplateController` | S6 `enable_php` | `Product::with('files')`, `pdfLoadHtml()` | слято |
| `PublicUrlController` | S6 `enable_php` (2) | `{hash}` за contract sign, estimate accept/decline; `pdfLoadHtml()` | слято; **+ проверка на статуса** (виж 2) |
| `contract.blade.php` | S7 (модал: заглавие/бутони/aria) | sign → `$contract->hash` | слято |
| `estimate.blade.php` | S7 (`@lang('app.yes')`, модал) | accept/decline → `$estimate->hash` | слято |
| `orders/ajax/show.blade.php` | S7 (Razorpay description) | бутон + JS „Pay via Worldpay“ | слято |
| `payments/ajax/pdf.blade.php` | CUS_000328 (BG копие с `@if ($invoiceSetting->locale != 'bg')`) | шрифт `DejaVu Sans` | слято, guard-ът е 1× |
| `proposal.blade.php` | S7 (aria, модал) | action → `$proposal->hash` (2×), формата за отказ `#declineProposal` | слято |
| `proposals/ajax/accept-proposal.blade.php` | S7 (aria) | скрито `id` | слято |

`setOption('enable_php', true)` не съществува никъде в `app/` (grep + `databackd:check-security` P10-01 OK). Новият helper `pdfLoadHtml()` само поправя арабски текст и задава `defaultFont` `DejaVu Sans` (кирилицата е ОК) — не пипа `enable_php`.

### UP (26)

- `OrderController` — само `Product::with('files')->orderBy('name')` и `pdfLoadHtml()`; права непроменени.
- `ProposalAcceptRequest` — проектът се намира по `route('hash')` и без `CompanyScope` (преди — `findOrFail(request('id'))`, което при липсващо `id` даваше 404). ОК.
- `NewProposalListener` / `ProposalSigned` — админите на компанията на предложението се намират без `CompanyScope` (гост или влязъл в друга компания), `emailSetting` може да е `null`. ОК; в `ProposalSigned` статусът в имейла е преведен (виж 2).
- `ProposalObserver` — ранен `return` при `type=accept|decline`, след изпращането на `NewProposalEvent`, за да не „синхронизира“ редовете. ОК (тествано — редовете остават).
- `ExpenseImport` / `ImportExpenseJob` / `public/sample-import/expense-sample.xlsx` — датата на покупка от Excel (число/DateTime) се конвертира; празни категория/банкова сметка се пропускат; махнат `ShouldBeUnique` и неизползваното `$this->user`. Невалидна дата → `messages.invalidDate` (както преди). Примерният файл е на английски (и старият беше) — не е пипан.
- Изгледите `estimates*`, `proposals*`, `proposal-template*`, `orders*`, `credit-notes/convert_invoice`, `expenses/edit`, `recurring-expenses/edit` — новият избор на продукт (`products.ajax.select-option*`, `sanitize: false`); `payments/ajax/*` — опция „Worldpay“. Нов твърд текст няма (ключове: `app.sku`, `app.search`, `app.menu.selectProduct`, `app.worldpay`, `modules.invoices.payWorldpay` — всички налични в `eng` и `bg`, последните два добавени от A01). `expenses/edit.blade.php` и `recurring-expenses/edit.blade.php` не се ползват от контролерите (ползват се `*/ajax/edit`) — промените там са безвредни.
- **XSS при `sanitize: false`**: `data-content` се вмъква като HTML от bootstrap-select, а името на продукта беше екранирано само веднъж (импорт на продукти от Excel не минава през `XssLinearMerge`). A06 вече го поправи в `products/ajax/select-option{,-script}.blade.php` (двойно екраниране) — проверено; моите изгледи само включват тези partial-и, допълнителна промяна не е нужна.

### Публичните маршрути `{id}` → `{hash}`

Всички извиквания на `front.contract.sign`, `front.estimate.accept/decline/download`, `front.proposal_action`, `front.download_proposal`, `front.contract.download`, `front.gantt_data`, `front.invoice_download`, `front.invoice_payment_failed` в `resources/views` и `app/` подават `hash` — с две изключения извън моя списък (виж „Искания“ → A05). Твърдо зашити пътища (`/estimate/accept/…` и т.н.) в JS няма. В демо базата няма записи без `hash` (contracts 12, projects 5, invoices 200; estimates/proposals — 0).

### Скрити модули (proposals, estimates, orders)

Нови входни точки от upstream към тях в менюто/топбара/таблото няма (upstream `sections/menu` добавя само „Форми за потенциални клиенти“ — модулът leads е скрит, това е на A07/A10). Бутонът „Pay via Worldpay“ е в `orders/ajax/show` (страница на скрит модул) и се показва само при активен Worldpay.

## 2. Променено

| Файл | Промяна | Защо |
|---|---|---|
| `app/Http/Controllers/BankAccountController.php` | `getBankStatement()`: `findOrFail($request->accountId)` + `view_bankaccount` (`all` / `added` + `added_by`) като в `show()` | **съществуваща дупка (и в 6.0.10):** служител без право изтегляше PDF извлечение на всяка банкова сметка в компанията (проверено: преди 200, сега 403; админ — 200) |
| `app/Http/Controllers/HomeController.php` | `worldpayModal()`: `abort_if(worldpay_status !== 'active', 404)` | новият публичен modal (без вход, по пореден `id`) показваше дължимата сума и валутата на **всяка** фактура/поръчка (и между компании); сега само при включен Worldpay (бутонът и без това се показва само тогава) |
| `app/Http/Controllers/HomeController.php` | `gantt()` / `taskboard()`: `abort_403('Public gantt chart …')` → `abort(403, __('ui-sales.public.gantt_disabled'))`, аналогично `taskboard_disabled` | искане от координатора (находка на A09); `abort_403()` приема само условие, т.е. текстът никога не се е показвал — сега се показва, преведен |
| `app/Http/Controllers/HomeController.php` | `leadStore()`: за съществуващ контакт (намерен по имейл) се попълват само празните полета (`company_name`, `website`, `address`, `client_name`, `mobile`, `city`, `state`, `country`, `postal_code`, `source_id`), имейлът не се пренаписва; същото правило за custom fields (само полетата без стойност) | искане от координатора (находка на A07, diff в `07-crm.md`): upstream махна `unique` за имейла и анонимно изпращане на публичната форма с имейла на съществуващ контакт презаписваше данните му. Новата сделка и бележката се създават както преди, т.е. запитването не се губи. Custom fields са добавени към diff-а на A07 по същата логика |
| `app/Http/Controllers/HomeController.php` | `leadStore()`: `clean_editor($request->message)` за `LeadNote.details` и `Deal.note` | **ВИСОК**, искане 6.1 на A11 (находка 5.1): публичната неавтентикирана форма записваше `message` дословно (полето е в `config/xss_ignore.php` и остава там — ползва се от тикети/чат) → stored XSS в бележките на CRM. Почистването на вече записаните данни в изгледите (`leads/ajax/notes`, `leads/notes/edit`, `lead-contact/notes/edit`) прави координаторът |
| `app/Http/Controllers/PublicUrlController.php` | `estimateAccept()` / `estimateDecline()`: отговор само при `status == 'waiting'` (иначе `ui-sales.estimates.already_answered`; при accept — `DB::rollBack()`) | съществуваща дупка: повторен POST „decline“ върху вече приета оферта сменяше статуса на `declined` и преизчисляваше сумите (а фактурата вече е създадена); accept на отхвърлена оферта създаваше фактура. Страницата предлага бутоните само при `waiting` |
| `app/Notifications/ProposalSigned.php` | статусът в имейла през `__('modules.proposal.<status>')` (с fallback към суровата стойност) | S7 — беше „accepted“/„declined“ на английски; проверено: „Статус : Прието“ / „Отхвърлено“ |
| `resources/lang/{eng,bg}/ui-sales.php` (нов) | `estimates.already_answered`, `public.gantt_disabled`, `public.taskboard_disabled` | еднакви ключове в двата файла, `lang-audit` без забележки |

Всички промени са маркирани `// databackd (upgrade 6.0.19): …`.

## 3. Потвърдени къстомизации

- **S6**: без `enable_php` в 12-те PDF места от списъка (`check-security` 83/83, P10-01 OK).
- **S7**: преводите в `contract`/`estimate`/`proposal.blade.php`, `accept-proposal`, `orders/ajax/show`, `PaymentController` (`ui-core.messages.transaction_id_*`) са запазени.
- **CUS_000326**: `CustomFieldsTrait` в `BankAccount`, `CreditNotes`, `Payment`, `Proposal`; custom fields в create/store/show/edit/update/PDF на четирите контролера.
- **CUS_000328**: guard-ът в `payments/ajax/pdf.blade.php`.

## 4. Проверки

- `php -l` — всички PHP файлове от списъка + променените: без грешки.
- **Публичен поток end-to-end** (скрипт извън репото, в една транзакция с `DB::rollBack(0)`, `Notification::fake()`, `Mail::fake()`, изтрити тестови подписи; уеб контекст — observer-ите работят): **33/33 OK** — оферта: signed GET 200 (без подпис 403), страницата праща accept/decline/download по hash, POST по `id` → 404, decline → declined, accept на отхвърлена → отказ, accept → подпис + фактура, повторен accept → „Already signed“, decline на приета → отказ (статус и сума запазени); предложение: GET 200, action по hash (2×), `#declineProposal`, POST по id → 404, accept без подпис при `signature_approval` → 422, decline → declined + коментар, accept → подпис, редовете запазени и при двата, `ProposalSigned` до админа на компанията (гост), повторно → „Already signed“; договор 10: GET 200, sign по hash, по id → 404, sign → подписан; PDF download по hash за договор/оферта/предложение → `application/pdf`.
- `leadStore` (транзакция с rollback, гост, `Notification`/`Mail::fake`, временни 2 custom fields): **12/12 OK** — нов контакт се създава с данните и custom field; второ анонимно изпращане със същия имейл и други име/телефон/град/фирма → данните остават („Иван Петров“, а не „Hacker“), празният `website` и празният custom field се попълват, попълненият custom field остава, няма дублиран контакт, двете запитвания са 2 сделки + 2 бележки; `message` = `Здравейте … & срок.\nВтори ред <img src=x onerror=alert(document.domain)><script>alert(1)</script><b>важно</b>` → в `lead_notes.details` и `deals.note` се записва `Здравейте … &amp; срок.\nВтори ред <img src="x" alt="x" /><b>важно</b>` (без `onerror`/`script`, кирилицата и новият ред остават за `nl2br`).
- Worldpay modal: при `deactive` (демо) → 404; при `active` (в транзакция) → 200.
- Gantt/taskboard: при `enable` 200, при `disable` 403 с новия текст.
- `smoke.php` (админ, `--ajax`) за `estimates.`, `estimate-template`, `proposals.`, `proposal-template`, `contracts.`, `creditnotes`, `orders.`, `payments.`, `bankaccounts.`, `expenses.`, `recurring-expenses`: всички FAIL-ове са от липсващи параметри — `*.add_item` 404 (познат), `orders.stripe_modal` 500 (като познатия `invoices.stripe_modal`), `creditnotes.create` 404 без `?invoice=`, `bankaccounts.create_transaction` 404 без `accountId`, `bankaccounts.get_bank_statement` 500 без дати. С параметри: `--url` за contracts 1/10 (show, edit, download), creditnotes 16 (show, edit, download), `convert-invoice/601`, `creditnotes/create?invoice=601`, payments 610 (show, download), bankaccounts 7/8 (show, edit, generate-statement, get-bank-statement с дати, create-transaction) , expenses 751 (show, edit), payments/expenses create — **всички 200**.
- Демото няма оферти/предложения/поръчки/шаблони — създадени в транзакция (rollback) и рендирани като админ: show/edit/download на оферта, предложение, шаблон на предложение, show/edit на шаблон на оферта, create с `template`/`deal_id` — 200 (+ ajax); поръчка show/edit/download — 200 (клиент — 403 по права).
- Служител `g.petrov` / клиент `p.yordanov` за същите префикси: само 403 по права и познатите FAIL-ове; 500 няма (освен `orders.stripe_modal` без параметър). `get_bank_statement` за служител — сега 403.
- `hardcoded-text.php --list` за моите файлове: 18 записа, всички фалшиви (JS шаблони с `@lang(...)` и PHP условия в `orders/ajax/show`), същите като преди промените.
- `lang-audit.php`: 66 файла, 10703 = 10703 ключа, `ui-sales.php` без забележки.

## 5. Искания към други файлове

- **A05 (billing):** `resources/views/super-admin/packages/stripe-payment.blade.php:127` — `route('front.invoice_payment_failed', [$company->id])` и `resources/views/super-admin/billing/payment-method-show.blade.php:813` — `.replace(':id', invoiceId)`: маршрутът вече е `{hash}` и търси `Invoice::where('hash', …)`. И преди не работеше (`Invoice::findOrFail($companyId)`), сега дава 404 — колбекът при неуспешно плащане на абонамент не записва нищо. Ако трябва — отделен маршрут за суперадмин фактурите; иначе само за знание.
- **A07 (crm):** поправката на `leadStore` от вашия diff е приложена (виж 2). Потвърдете, че миграцията на A12 създава активна форма с `is_default = 1` за всяка компания (иначе публичната форма дава 404) — по вашия отчет е така. **Опция за собственика (не е направена):** докато `config('databackd.hidden.menu.leads')` е `true`, формата не може да се конфигурира от UI-то, но приема анонимни запитвания за всяка компания (`company_id`/`lead_form_id` са поредни, хешът не е нужен за POST) — може да се изключи с `abort_if(databackd_hidden('menu', 'leads'), 404);` в началото на `leadForm()` и `leadStore()` (за суперадмин `databackd_hidden()` е `false`). Throttle на POST-а е искане на A07 към A11.
- **A11 (core):** искане 6.1 (`clean_editor` за `message` в `leadStore`) е изпълнено. По желание от същото искане — `category_id` от заявката до компанията — не е дублирано в контролера: `StorePublicLead` (A07) вече го валидира (`$ofCompany('lead_category')`, проверено от A07: чужд → 422). Остатъчно: HTMLPurifier пропуска `<img src="https://…">` — външна картинка в бележката може да служи за „пиксел“ (IP на служителя, който я отваря); ако е важно — `HTML.Allowed` без `img` за този вход или `strip_tags` (решение за вас).
- **A11 (core):** за знание — `sync-user-permissions`: upstream добави `abort_403(!user()->hasRole('admin'))`, а нашето S10 (`security-headers.restricted_routes.super_admin_only`) го пуска само за суперадмин → маршрутът е недостъпен за всички. Не е проблем (командата върви всяка минута от scheduler-а), но може да се махне от `super_admin_only` или да се остави така съзнателно.
- **A09 (work):** за знание — в `HomeController::gantt()`/`taskboard()` съобщението при изключена публична диаграма/табло вече се показва (преведено, `ui-sales.public.*`); преди се показваше общото „Достъпът е отказан“.
- **A06 (products):** само потвърждение — двойното екраниране в `products/ajax/select-option{,-script}.blade.php` покрива и моите изгледи с `sanitize: false`.

## 6. Известни, непоправени (upstream, съществуват и в 6.0.10)

- Публичното приемане на оферта създава фактура без лимита на пакета (CUS_000329 `checkCompanyCanAddMoreInvoices` се проверява само в UI маршрутите). Модулът „Оферти“ е скрит.
- `estimateAccept()` при „Already signed“ връща грешка, без да затвори транзакцията (затваря се с края на заявката).
- `proposalActionStore()` позволява приемане след отказ (без подпис преди това) — не създава фактура, оставено.
- `contractSign()` връща redirect към `/account/contracts/{id}` и за гост.
- Маршрутът `estimate-template.download` сочи несъществуващ метод `EstimateTemplateController::download` (500; никъде не е линкнат).
- Публичните 403/404 съобщения за гости са на езика по подразбиране на приложението (страниците не задават езика на компанията преди `abort`).

## 7. За деплоя

- Миграции/настройки от A04 — няма. Нови файлове: `resources/lang/eng/ui-sales.php`, `resources/lang/bg/ui-sales.php`.
- Преди деплоя на production: `SELECT 'contracts', COUNT(*) FROM contracts WHERE hash IS NULL OR hash='' UNION ALL SELECT 'estimates', COUNT(*) FROM estimates WHERE hash IS NULL OR hash='' UNION ALL SELECT 'proposals', COUNT(*) FROM proposals WHERE hash IS NULL OR hash='' UNION ALL SELECT 'projects', COUNT(*) FROM projects WHERE hash IS NULL OR hash='' UNION ALL SELECT 'invoices', COUNT(*) FROM invoices WHERE hash IS NULL OR hash='';` — трябва да е 0 навсякъде (иначе публичните страници на тези записи дават грешка при генериране на линковете).
- Публична страница на договор/оферта/предложение, отворена **преди** деплоя, ще прати подписа към стария `/{id}` → 404; презареждане на страницата го решава. Самите линкове в имейлите (`/contract/{hash}` и т.н., signed) не са променени.
- Stored XSS през публичната форма (5.1 на A11) съществува и на production сега. `clean_editor` пази само новите записи; вече записаните се покриват от изгледите (координаторът). Проверка на production (само четене): `SELECT 'lead_notes', id FROM lead_notes WHERE details REGEXP 'onerror|<script|javascript:' UNION ALL SELECT 'deals', id FROM deals WHERE note REGEXP 'onerror|<script|javascript:';`.
- Рискове: нисък. Промените в поведението са само отказ (403/404/грешка) в случаи, които UI-то и без това не предлага, и `leadStore`, който вече не презаписва съществуващ контакт (ако собственикът иска старото поведение — „последното изпращане печели“ — това е съзнателен компромис в полза на сигурността).
