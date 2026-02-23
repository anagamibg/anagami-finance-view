# Сравнение на дублирани директории: app vs app2, resources vs resources1

> **Дата на анализа**: 2026-03-19
>
> **Контекст**: В проекта има дублирани директории — `app2` и `resources1` са копия на `app` и `resources` **преди** прилагане на custom разработките от外部 dev екип. Директориите `app` и `resources` съдържат **актуалния код** с всички промени по 4-те задания (CUS_000326–CUS_000329).

---

## Резюме

| Задание | ID | Статус в `app` / `resources` | Засегнати файлове |
|---|---|---|---|
| Billing Packages — лимити за фактури и клиенти | CUS_000329 | **Имплементирано** | ~15 файла (app) + ~10 файла (resources) |
| Фактура на EN и BG | CUS_000328 | **Имплементирано** (с бъг, виж бележка) | ~30 PDF шаблона (resources) |
| Invoice series (10-цифрен номер) | CUS_000327 | **Не е открита разлика** между директориите | Вероятно в DB миграции или конфигурация |
| Custom fields за финансови документи | CUS_000326 | **Имплементирано** | ~15 файла (app) + ~15 файла (resources) |

---

## Бележки от чата с индийския екип (`indian-chat-transcript.txt`)

- Екипът потвърди получаване на плащане и качи код за тестване.
- **Открит бъг (CUS_000328)**: При генериране на PDF, файлът съдържа **2 фактури на български**, вместо 1 на английски + 1 на български. Смяната на езика на приложението на английски не помага — пак излизат 2 на български. Екипът потвърди, че ще оправи проблема.
- Екипът е искал cPanel достъп многократно (минимум 3 пъти).
- Последно — екипът е поискал app URL и login credentials за тестване.

---

## 1. CUS_000329 — Billing Packages: лимити за фактури и клиенти

### Какво е направено

Добавени са две нови ограничения към billing пакетите: **max_clients** (максимум клиенти) и **max_invoices** (максимум фактури), по аналогия с вече съществуващото `max_employees`.

### Засегнати файлове в `app/` (липсват в `app2/`)

#### Helper
- **`app/Helper/start.php`** — Добавени 2 нови helper функции:
  - `checkCompanyCanAddMoreClients()` — проверява дали компанията не е надхвърлила лимита за клиенти
  - `checkCompanyCanAddMoreInvoices()` — проверява лимита за фактури
  - Разширена `checkCompanyPackageValidity()` — брои клиенти и фактури спрямо пакетните лимити
  - Добавени cache ключове за тези проверки

#### Middleware
- **`app/Http/Middleware/CheckCompanyPackage.php`** — Добавени клиентски и фактурни routes към allowed routes при надхвърлен лимит. Нов масив `$notAllowedInvoiceRoutes` с проверка чрез `checkCompanyCanAddMoreInvoices()`. Извиква и `checkCompanyCanAddMoreClients()`.

#### Controllers
- **`app/Http/Controllers/InvoiceController.php`** — Проверка за max invoices в `store()`, връща грешка при достигнат лимит. Извиква `clearCompanyValidPackageCache()` в `index()` и bulk delete.
- **`app/Http/Controllers/ClientController.php`** — Проверка за max clients в `store()`, проверка при смяна на статус на "active" в `update()`, изчистване на `PackageUpdateNotify` при изтриване.
- **`app/Http/Controllers/SuperAdmin/BillingController.php`** — `withCount(['clients', 'invoices'])` и downgrade проверки за `max_clients` и `max_invoices`.
- **`app/Http/Controllers/SuperAdmin/PayFastController.php`** — Downgrade проверки за clients/invoices.

#### Jobs
- **`app/Jobs/ImportClientJob.php`** — Guard `checkCompanyCanAddMoreClients()` — при лимит, import job-ът се отказва.

#### Request Validators
- **`app/Http/Requests/SuperAdmin/Packages/StoreRequest.php`** — Добавено: `'max_clients' => 'required|numeric'`, `'max_invoices' => 'required|numeric'`
- **`app/Http/Requests/SuperAdmin/Packages/UpdateRequest.php`** — Същото.

#### DataTables
- **`app/DataTables/SuperAdmin/CompanyDataTable.php`** — Показва `max_invoices`, `max_clients`, `totalInvoice`, `totalClient` в списъка с компании.
- **`app/DataTables/SuperAdmin/PackageDataTable.php`** — Колона `max_employees` показва комбиниран списък (employees, clients, invoices).
- **`app/DataTables/InvoicesDataTable.php`** — Ново свойство `$packageAllowInvoice` (чрез `checkCompanyCanAddMoreInvoices()`). Бутоните за действия (send, approve, mark sent, edit, upload, cancel, copy link, credit note, reminder, duplicate) се показват само ако пакетът позволява.

#### Observers
- **`app/Observers/ClientDetailsObserver.php`** — `clearCompanyValidPackageCache()` в `saving`.
- **`app/Observers/InvoiceObserver.php`** — `clearCompanyValidPackageCache()` в saving и deleting.
- **`app/Observers/SuperAdmin/PackageObserver.php`** — Проверка на clients/invoices count при решение за изчистване на `PackageUpdateNotify`.

### Засегнати файлове в `resources/` (липсват в `resources1/`)

- **`views/super-admin/packages/ajax/create.blade.php`** — Нови полета `max_clients` и `max_invoices` с popovers.
- **`views/super-admin/packages/ajax/edit.blade.php`** — Същото, с попълнени стойности от `$package`.
- **`views/super-admin/billing/ajax/plan.blade.php`** — Три отделни проверки (employees, clients, invoices) с отделни предупреждения и линкове. Показва count/limit за клиенти и фактури.
- **`views/super-admin/companies/ajax/show.blade.php`** — Показва clients и invoices с лимити, danger highlighting при достигнат лимит.
- **`views/super-admin/front/section/pricing-plan.blade.php`** — Max clients и max invoices в публичния pricing план.
- **`views/super-admin/saas/pricing-plan.blade.php`** — Същото за SaaS pricing.
- **`views/invoices/index.blade.php`** — Бутоните "Add Invoice" / "Recurring Invoice" / "Create TimeLog Invoice" обвити в `@if (checkCompanyCanAddMoreInvoices())`.
- **`views/invoices/ajax/show.blade.php`** — Invoice actions (edit, send, approve, mark sent, upload, add payment, cancel, etc.) зад `$packageAllowInvoice` проверка.
- **`views/clients/index.blade.php`** — "Add Client" и "Import Excel" бутоните зад `@if (checkCompanyCanAddMoreClients())`.
- **`lang/eng/superadmin.php`** — Нови ключове: `maxClientsInfo`, `maxInvoicesInfo`, `manageClients`, `manageInvoices`, `maxClientsLimitReached`, `maxInvoicesLimitReached`, `updatePlanClientNote`, `updatePlanInvoiceNote`.

---

## 2. CUS_000328 — Фактура на английски и български

### Какво е направено

Всички PDF шаблони (фактури, кредитни известия, оферти, поръчки, proposals, плащания) имат **дублирана българска секция**. В `resources` (актуалния код) тази секция е обвита в:

```php
@if ($invoiceSetting->locale != 'bg')
    {{-- Българска копия на документа --}}
@endif
```

Когато locale-ът вече е `bg`, се пропуска дублираната българска секция (логично — документът вече е на български). В `resources1` тази условна проверка **липсва** — българската секция винаги се рендира като втора страница.

### Известен бъг

От chat transcript-а: **PDF-ът съдържа 2 фактури на български**, дори когато приложението е превключено на английски. Това означава, че или locale-ът не се превключва правилно до `$invoiceSetting->locale`, или първата секция също рендира на български заради друга настройка. **Бъгът не е оправен** — екипът потвърди, че ще го разгледа, но няма данни за fix.

### Засегнати файлове (~30 PDF шаблона)

**Invoices:**
- `views/invoices/pdf/invoice-{1,2,3,4,5}.blade.php`
- `views/invoices/pdf/invoice-recurring.blade.php`

**Credit Notes:**
- `views/credit-notes/pdf/invoice-{1,2,3,4,5}.blade.php`
- `views/credit-notes/pdf/invoice-recurring.blade.php`

**Estimates:**
- `views/estimates/pdf/invoice-{1,2,3,4,5}.blade.php`

**Orders:**
- `views/orders/pdf/invoice-{1,2,3,4,5}.blade.php`

**Proposals:**
- `views/proposals/pdf/invoice-{1,2,3,4,5}.blade.php`
- `views/proposals/pdf/invoice-recurring.blade.php`

**Proposal Templates:**
- `views/proposal-template/pdf/invoice-5.blade.php`

**Payments:**
- `views/payments/ajax/pdf.blade.php`

**Super-admin invoices:**
- `views/super-admin/invoices/pdf/invoice-{1,2,3,4,5}.blade.php`

---

## 3. CUS_000327 — Invoice Series (10-цифрен номер със серия)

### Статус: НЕ Е ОТКРИТА РАЗЛИКА

Между `app`/`app2` и `resources`/`resources1` **не са открити разлики**, свързани с номерация на фактури или invoice series. Възможни обяснения:

1. Функционалността е в DB миграции, които не се сравняват тук
2. Имплементирана е в конфигурация или routes файлове
3. **Не е имплементирана** от dev екипа

**Препоръка**: Проверете ръчно DB миграциите (`database/migrations/`) за промени свързани с invoice number format или series. Проверете и `app/Models/Invoice.php` за логика за генериране на номера.

---

## 4. CUS_000326 — Custom fields за финансови документи

### Какво е направено

Добавена е поддръжка на custom fields (включително **dropdown**) към 4 типа финансови документи: **Payment**, **CreditNotes**, **Proposal**, **BankAccount**.

### Засегнати файлове в `app/` (липсват в `app2/`)

#### Models — добавен `use CustomFieldsTrait` и `CUSTOM_FIELD_MODEL`:
- `app/Models/BankAccount.php`
- `app/Models/CreditNotes.php`
- `app/Models/Payment.php`
- `app/Models/Proposal.php`

#### Регистрация:
- **`app/Models/CustomFieldGroup.php`** — Регистрирани 4 нови custom field модела: Payment, CreditNotes, Proposal, BankAccount.

#### Controllers — `getCustomFieldGroupsWithFields()` в create/edit/show, `updateCustomFieldData()` в store/update:
- `app/Http/Controllers/BankAccountController.php`
- `app/Http/Controllers/CreditNoteController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/ProposalController.php`

#### DataTables — custom field колони:
- `app/DataTables/BankAccountDataTable.php`
- `app/DataTables/CreditNotesDataTable.php`
- `app/DataTables/PaymentsDataTable.php`
- `app/DataTables/ProposalDataTable.php`

#### Request Validators — `CustomFieldsRequestTrait`:
- `app/Http/Requests/BankAccount/StoreAccount.php`
- `app/Http/Requests/CreditNotes/StoreCreditNotes.php`
- `app/Http/Requests/CreditNotes/UpdateCreditNote.php`
- `app/Http/Requests/Payments/StorePayment.php`
- `app/Http/Requests/Payments/UpdatePayments.php`
- `app/Http/Requests/Proposal/StoreRequest.php`

### Засегнати файлове в `resources/` (липсват в `resources1/`)

#### Proposals:
- `views/proposals/ajax/create.blade.php` — `<x-forms.custom-field :fields="$fields">`
- `views/proposals/ajax/edit.blade.php` — `<x-forms.custom-field :fields="$fields" :model="$proposal">`
- `views/proposals/ajax/show.blade.php` — `<x-forms.custom-field-show>`
- `views/proposals/pdf/invoice-{1,2,3,4,5}.blade.php` — Custom fields в PDF, включително **select (dropdown)** поддръжка

#### Credit Notes:
- `views/credit-notes/ajax/create.blade.php`
- `views/credit-notes/edit.blade.php`
- `views/credit-notes/pdf/invoice-{1,2,3,4,5}.blade.php`

#### Payments:
- `views/payments/ajax/create.blade.php`
- `views/payments/ajax/show.blade.php`
- `views/payments/ajax/pdf.blade.php`

#### Bank Accounts:
- `views/bank-account/ajax/create.blade.php`
- `views/bank-account/ajax/edit.blade.php`
- `views/bank-account/pdf/statement.blade.php`

### Dropdown поддръжка в PDF шаблоните:
```php
@elseif($field->type == 'select')
    {{ (!is_null(...) && ... != '') ? $field->values[...] : '-' }}
```

---

## 5. Файлове само в `app/` (не съществуват в `app2/`)

Тези файлове са добавени в `app/`, но не са част от четирите задания:

- `app/Console/Commands/CacheTest.php`
- `app/DataTables/LeadsDataTable.php`
- `app/Http/Controllers/LeadCategoyController.php`
- `app/Http/Controllers/LeadController.php`
- `app/Http/Controllers/LeadStatusSettingController.php`
- `app/Http/Requests/LeadSetting/StoreLeadStatus.php`
- `app/Http/Requests/LeadSetting/UpdateLeadStatus.php`
- `app/Mail/BulkShiftEmail.php`
- `app/Mail/DailyTimeLogReport.php`
- `app/Mail/MonthlyAttendance.php`
- `app/Mail/TicketReply.php`
- `app/Models/LeadFiles.php`
- `app/Models/LeadFollowUp.php`
- `app/Traits/SuperAdmin/SuperAdminDashboard.php`

---

## 6. Файлове само в `resources1/` (не съществуват в `resources/`)

Тези файлове вероятно идват от по-нова версия на upstream платформата:

- `lang/*/cookie.php` — Нов файл за cookie преводи (всички езици)
- `lang/*/superadmin.php` — SuperAdmin преводи за всички езици (в `resources` само в `lang/eng/`)
- `lang/cs/` — Чешки език (нова локализация)
- `lang/uk/` — Украински език (нова локализация)
- `views/lead-settings/create-status-modal.blade.php`
- `views/lead-settings/edit-status-modal.blade.php`

---

## 7. Други разлики в езикови файлове

`resources1` съдържа по-нови upstream преводи с добавени ключове:

- `en/app.php`: `emailid`, `mobileno`, `companyowner`, `timezone`, `nextpaymentdate`, `alreadyLoggedIn`
- `en/auth.php`: `companyAccountDisabled`, `failedCompanyUnapproved`
- `en/email.php`: Малка текстова промяна (премахнат `<br>`)
- `en/messages.php`: Променен `reminderMailSuccess`, добавен `currencyConvertKeyUpdated`
- `en/modules.php`: Поправен правопис `autoClockInDefualtLocation`, добавен `custom_domain`
- `en/permissions.php`: 24 нови superadmin permission ключа
- `en/validation.php`: Добавен `notAllowed`

---

## Заключение и препоръки

1. **CUS_000329 (Billing Packages)** — Напълно имплементирано. Може да се ползва. Тествайте edge cases при downgrade на пакети.
2. **CUS_000328 (EN/BG Invoice)** — Имплементирано, но **има незакърпен бъг** — PDF показва 2 фактури на български. Нужна е проверка на `$invoiceSetting->locale` стойността и кога/как се сетва.
3. **CUS_000327 (Invoice Series)** — **Не е открита имплементация** в сравнените директории. Проверете миграциите и моделите ръчно.
4. **CUS_000326 (Custom Fields)** — Напълно имплементирано за Proposals, Credit Notes, Payments, Bank Accounts. Dropdown тип е поддържан.
5. **`app2` и `resources1` могат да бъдат изтрити** след финално потвърждение — те са backup преди промените.
6. **`resources1` съдържа по-нови upstream преводи**, които трябва да бъдат merge-нати в `resources` преди изтриване.
