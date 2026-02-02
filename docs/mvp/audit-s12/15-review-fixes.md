# S12 — поправки след независимото код ревю

## Ревю A — групово въвеждане и валута на група

### 1. Групово въвеждане: месец с ръчни записи в няколко валути
**Дефект (потвърден).** `FinEntryBulkController::gridCurrency()` връщаше валутата на юр. лице, когато управляваните от таблицата записи за месеца (ръчни, към последния ден, без контрагент/документ/клиент) са в повече от една валута. Таблицата показваше суровата `amount` под този етикет, а `store()` записваше всеки изпратен ред (JS праща всички съществуващи редове) с `currency = <валутата на юр. лице>` и ново `amount_base`.
Възпроизведено през Laravel kernel-а в транзакция с rollback: EUR юр. лице, ръчен запис 1955,83 BGN (amount_base 1000,00) на 31.03 + EUR запис → „Запази“ с нов ред → BGN записът става **1955,83 EUR, amount_base 1955,83** (загуба на данни).

**Избран вариант: месецът със смесени валути е само за преглед в таблицата** (а не валута на всеки ред). Причини: (а) нищо не се преписва — записите запазват валутата и `amount_base` си; (б) таблицата е „един месец в една валута“ — сумите в долния панел (приходи, разходи, резултат, активи/пасиви, разлика) и поставянето от Excel/новите редове нямат смисъл при смесени валути, а валута на ред би изисквала превалутиране в браузъра и избор на валута за всеки нов ред; (в) случаят е рядък (запис от единичната форма в друга валута или смяна на валутата на юр. лице) и се редактира пълноценно във „Финансови записи“, където всеки запис има своя валута.

**Поправка.**
- `load()`: всеки ред носи своята `currency`; при > 1 валута — `mixed_currencies` (напр. `["BGN","EUR"]`), `mixed_message` и `entries_url` (списъкът „Финансови записи“, филтриран по юр. лице и дата = края на месеца).
- `store()`: при смесени валути отказва с ясно съобщение (нищо не се записва) — защита и срещу стара/подправена страница. Допълнително: JS праща валутата, в която е заредена таблицата (`currency`); ако месецът междувременно е в друга валута (напр. празен месец в EUR, а през единичната форма е добавен BGN запис), записът се отказва със „заредете таблицата отново“ — иначе сумите, въведени като EUR, щяха да се запишат като BGN.
- View: жълто предупреждение над таблицата с текста и бутон „Отвори записите за месеца“; полетата (сума, разрез, бележка) са `disabled`, няма бутони за добавяне/премахване на ред, „Запази“ и „Изчисти“ са изключени (и Ctrl+S не праща нищо), заглавието на колоната е „Сума (BGN / EUR)“, под всяка сума е валутата на реда, общите суми са „—“ (title: „Не се сумира — записите са в различни валути.“).
- Месец с една валута (вкл. BGN месец в EUR юр. лице) работи както досега.

**Проверка.** Сценарият преди/след в транзакция с rollback (скрипт в scratchpad): преди — BGN → EUR; след — `store` връща fail, записите остават 1955,83 BGN / base 1000,00 и 500 EUR; BGN-only месец се записва в BGN (нов ред 100 BGN → base 51,13); „стара“ таблица (EUR) срещу BGN месец → отказ. UI: headless Chromium със заредената страница и подменен отговор на `load` (смесени / една валута), десктоп + мобилен — предупреждение и линк видими, 30/30 полета изключени, „Запази“ изключен, Ctrl+S не изпраща заявка; при една валута всичко е активно и заявката съдържа `currency=BGN`; без JS грешки. `smoke.php databackd.data.bulk --ajax` — админ и служител OK (клиент — очакван 403).

### 2. Смяна на базовата валута на консолидационна група
**Дефект (потвърден).** Ръчният `fx_rate` на член означава „amount_base × курс = сума във валутата на групата“ (`ConsolidationService`), т.е. е обвързан с валутата на групата. `ConsolidationGroupController::update()` при смяна на `base_currency` нулираше само членовете, чиято валута съвпада с новата. Възпроизведено: група EUR → BGN, членове BGN (1,95583) и USD (1,08) → BGN курсът се изчиства, **USD остава 1,08** (вече тълкуван като EUR→BGN) → грешна консолидация.

**Поправка.** При реална смяна на валутата (сравнение с оригиналната стойност, без значение на регистъра) се изчистват **всички** ръчни `fx_rate` на групата в същата транзакция; ако има изчистени, отговорът е `Reply::successWithData(...)` с `fx_cleared = <брой>` и съобщение (bg+eng `messages.group_updated_fx_cleared`): ръчните курсове (N) са изчистени, бяха спрямо старата валута, въведете ги наново спрямо новата или оставете празно за курсовете от „Валутни курсове“. Без смяна на валутата курсовете се запазват (проверено).

**Проверка.** Сценарият в транзакция с rollback: след поправката и трите члена са с `fx_rate = NULL`, отговорът съдържа съобщението и `fx_cleared: 2`; повторен запис без смяна на валутата запазва ръчния курс. `php artisan databackd:check-consolidation` — 35 успешни, 0 неуспешни; `smoke.php databackd.analytics.consolidation --ajax` и `databackd.analytics.consolidation-rules --ajax` (+ `/consolidation-rules/52`) — OK.

### Нужни промени извън моята област
- `resources/views/databackd/analytics/consolidation-rules/group-form.blade.php` (~ред 123): модалът прави `window.location.reload()` веднага след успех, затова тост-съобщението за изчистените курсове едва се вижда. Предложение:
  ```js
  success: function (response) {
      if (response.status == 'success') {
          @if ($isEdit)
              if (response.fx_cleared) {
                  Swal.fire({icon: 'warning', text: response.message, confirmButtonText: @json(__('app.ok')),
                      customClass: {confirmButton: 'btn btn-primary'}, buttonsStyling: false})
                      .then(function () { window.location.reload(); });
                  return;
              }
              window.location.reload();
          @else
  ```
  (по желание и предупреждение преди запис, когато полето „Базова валута“ е променено и групата има ръчни курсове.)

### Нови lang ключове
- `databackd-data-bulk.php` (bg+eng): `buttons.month_entries`, `messages.mixed_currencies`, `messages.mixed_total`, `messages.currency_changed`.
- `databackd-analytics-consolidation-rules.php` (bg+eng): `messages.group_updated_fx_cleared`.

### Променени файлове
- `app/Http/Controllers/Databackd/Data/FinEntryBulkController.php`
- `resources/views/databackd/data/bulk/index.blade.php`
- `app/Http/Controllers/Databackd/Analytics/ConsolidationGroupController.php`
- `resources/lang/bg/databackd-data-bulk.php`, `resources/lang/eng/databackd-data-bulk.php`
- `resources/lang/bg/databackd-analytics-consolidation-rules.php`, `resources/lang/eng/databackd-analytics-consolidation-rules.php`
- `docs/mvp/audit-s12/15-review-fixes.md` (този раздел)

Без миграции, без SCSS промени.

## Ревю A — поправки (изчисления: подсметки, контра сметки, парични средства, паричен поток, валута на ОПР, табло)

Всеки дефект е възпроизведен преди/след в транзакция с rollback: скрипт, който зарежда копия на класовете от `HEAD` (преименувани `Old*`) и новите класове върху демо фирмата („Демо Финанс Груп“), с временни сметки 4531 (актив, под 453), 2411 (под 241), 5031 (под 503, без подтип), балансови записи 1000 / 500 / 700 и балансов запис на 703 (2000). След rollback — 0 временни сметки/записи, валутата на фирмата е непроменена.

### 1. `balanceAt()` разгъваше към аналитични сметки от друг тип
**Дефект.** `FinancialDataService::accountIds()` (S12) добавяше към синтетичната сметка всички деца (по `parent_id` и по префикс на кода), без значение от типа. Активна 4531 „ДДС за възстановяване“ под пасивна 453: `balanceAt(453)` = 22 075,20 вместо 21 075,20; `TYPE[liability]` броеше 4531 (+1000), а `TYPE[asset]` — също ⇒ двойно броене. Формулите (FormulaEngine подава вече разрешени кодове) се променяха тихо: `ACC[503] + ACC[5031]` броеше 5031 два пъти, `ACC[241]` изваждаше и 2411.
**Поправка.**
- `accountIds()`: разгъва само от изрично подадените кодове (не от типове/подтипове) и само към деца със **същия `type`** като синтетичната/коренната сметка (по префикс 4531→453 и по `parent_id` на всякаква дълбочина). Синтетичен код, който липсва в сметкоплана, взима всички свои аналитични (както досега).
- `FormulaEngine::dataValue()`: `balanceAt(..., false)` за `ACC`, `TYPE`, `SUB` — `resolveAccounts()` вече изброява сметките на променливата; аналитичните се включват с `ACC[50*]` / `ACC[411*]`. Запазените формули дават същите числа като преди S12.
- Останалите извиквания (с разгъване, вече само в същия тип): табло (`CASH_CODES`, `RECEIVABLE_CODES`, 401), аларми (парични средства, вземания), прогнози (`CASH_CODES`); ГФО (`StatutoryReport`) остава `false` (чете всяка сметка поотделно).
**Преди/след:** `TYPE[liability]` 989 849,55 → 988 849,55; `TYPE[asset]` непроменено; `ACC[503] + ACC[5031]` 1 069 343,25 → 1 068 643,25; `ACC[50*]` непроменено.

### 2. Аналитични сметки на контра сметка (2411 под 241)
**Дефект.** `CashflowStatement::chart()`, `ConsolidationService` (resolver, ratioResolver, balance_check), `StatutoryReport` (консолидиран обхват), `ConsolidationExport` и `ImportTemplates::trialBalanceAmount()` проверяваха точния код в `CONTRA_CODES` ⇒ 2411 се добавяше към активите, а оборотната ведомост го записваше със знак „дебит − кредит“ (с новия `balanceAt()` знакът се обръщаше двойно).
**Поправка.** Нови helper-и в `FinAccount`: `isContraCode(string $code, ?string $syntheticCode = null)` (241, 2411, 241-1, „A1“ със синтетична 241) и `contraMap(iterable $accounts)` / `contraMapFor(int $companyId)` (флаг по id, по веригата `parent_id` на всякаква дълбочина). Ползват се в `balanceAt()` (групира по `account_id`), `CashflowStatement`, `ConsolidationService` (редовете носят `contra`), `ConsolidationExport`, `StatutoryReport`, `ImportTemplates`, `getIsContraAttribute()`, както и за значката „контра“ в сметкоплана (`FinAccountController` + `accounts/index.blade.php`, `accounts/form.blade.php` — JS проверява и префикса).
**Преди/след:** паричен поток, инвестиционна дейност −25 929,34 → −24 929,34 (разлика 2 × 500); консолидация `balance_check.assets` и `resolver('assets')` −1000; `ratioResolver ACC[24*]` −183 006,36 → −184 006,36.

### 3. Една дефиниция за „парични средства“ и „вземания“
**Дефект.** Табло/аларми: 501–504 + аналитични; прогнози (`ForecastService::historyFor('cash')`, `ForecastController` начални парични средства): подтип `cash` без аналитични ⇒ 5031 без подтип липсваше (1 077 882,25 вместо 1 078 582,25). Вземания: табло — 411 (+ аналитични), аларма `receivables_above` — подтип `receivable`.
**Поправка.** Прогнозите ползват `FinancialDataService::CASH_CODES` (с аналитичните). За вземанията избрах **411 с аналитичните ѝ сметки** (нова константа `FinancialDataService::RECEIVABLE_CODES = ['411']`) и я ползват и таблото, и алармата. Причини: алармата вече се описва като „вземания от клиенти“, таблото показва „Вземания от клиенти“ до „Задължения към доставчици“ (401) — същата логика код + аналитични като при паричните средства; не зависи от това дали потребителят е попълнил подтип на аналитична сметка; другите вземания (аванси, свързани лица) остават в `SUB[receivable]` на коефициентите. В демо данните стойността на алармата е непроменена (123 461,04 €).
Текстовете са уточнени (bg+eng): `databackd-gaps-alerts.type_help.cash_below` / `type_help.receivables_above`, `condition.cash_below` („501–504“ вместо „501 + 503“), `databackd-reports-dashboard.kpi.cash_info` / `kpi.receivables_info`.

### 4. Паричен поток: балансови записи на приходни/разходни сметки
**Дефект.** `CashflowStatement` стъпка 2 добавяше промяната на салдото на всяка сметка с `kind=balance`, вкл. приходни/разходни, към реда им в оперативната дейност, където оборотът вече е включен в стъпка 1. Балансов запис 2000 на 703 ⇒ оперативна дейност 41 932,56 вместо 39 932,56.
**Поправка.** В стъпка 2 сметките от тип revenue/expense се пропускат.

### 5. ОПР: твърдо „EUR“
**Дефект.** `PnlStatement::build()` връщаше `'currency' => 'EUR'` (печата се в `PnlExport` и `reports/pnl/pdf.blade.php`). Фирма с BGN ⇒ „EUR“ в Excel/PDF.
**Поправка.** `PnlStatement::currency()` чете валутата на фирмата по `company_id` (`companies.currency_id → currencies.currency_code`, резервно EUR) — работи еднакво в уеб и в console (PDF/e-mail задачи), без да зависи от `company()` в сесията. Преди/след (BGN в транзакцията): EUR → BGN.

### 6. Табло: фалшива бележка „предходният период е покрит частично“
**Дефект.** `DashboardMetrics::firstFlowDate()` парсва `entry_date` в UTC, а сравнителният период е в часовата зона на фирмата (Europe/Sofia) ⇒ 01.09 00:00 UTC е „след“ 01.09 00:00 София ⇒ бележката се показваше при напълно покрит период. Възпроизведено: старото сравнение `true`, сравнение по дата `false`.
**Поправка.** `FinDashboardController::index()` сравнява `Y-m-d` низове (`toDateString()`); описанието на `firstFlowDate()` го казва изрично.

### Проверки
- `php -l` на всички променени PHP файлове — OK.
- `databackd:check-formulas` — 159/159 (нов раздел „Analytic accounts…“: 4531 под 453 не влиза в 453/`TYPE[liability]`, `TYPE[asset]` +800, 2411 се изважда от 241, 4111 влиза в `balanceAt(411)`, `ACC[411]` остава само 411, `ACC[411] + ACC[4111]` не удвоява, `ACC[411*]`, `isContraCode`, паричен поток с 2411 и с балансов запис на 703); сравненията на `ACC`/`SUB` в проверката са вече без разгъване, както формулите.
- `databackd:check-consolidation` — 39/39 (нов сценарий 2411 под 241: ред с `contra`, balance_check, `resolver('assets')`, `ratioResolver ACC[24*]`).
- `databackd:check-forecasts` 81/81, `check-alerts --dry-run` (същите стойности като преди), `check-security` 83/83.
- `smoke.php databackd. --ajax`: админ — само очакваният `counterparties.show` 302; служител — същият 302 + 403 по права; клиент — само 403 по права; `databackd.data.accounts` — OK.

### Нужни промени извън моята област
- `app/Http/Controllers/Databackd/Data/FinEntryBulkController.php` ~119 (не е пипан — друг агент): `'contra' => in_array($account->code, FinAccount::CONTRA_CODES, true)` → `'contra' => FinAccount::isContraCode((string)$account->code, $account->parent_id ? (string)optional($accounts->firstWhere('id', $account->parent_id))->code : null)` (или `FinAccount::contraMap($accounts)[$account->id]`), за да се маркира и 2411 в груповото въвеждане.

### Променени файлове
- `app/Models/Databackd/FinAccount.php`
- `app/Services/Databackd/FinancialDataService.php`
- `app/Services/Databackd/Analytics/FormulaEngine.php`
- `app/Services/Databackd/Analytics/ConsolidationService.php`
- `app/Exports/Databackd/Analytics/ConsolidationExport.php`
- `app/Services/Databackd/Gaps/Statutory/StatutoryReport.php`
- `app/Services/Databackd/Data/ImportTemplates.php`
- `app/Services/Databackd/Reports/CashflowStatement.php`
- `app/Services/Databackd/Reports/PnlStatement.php`
- `app/Services/Databackd/Reports/DashboardMetrics.php`
- `app/Services/Databackd/Gaps/Alerts/AlertChecker.php`
- `app/Services/Databackd/Planning/ForecastService.php`
- `app/Http/Controllers/Databackd/Planning/ForecastController.php`
- `app/Http/Controllers/Databackd/Reports/FinDashboardController.php`
- `app/Http/Controllers/Databackd/Data/FinAccountController.php`
- `app/Console/Commands/Databackd/CheckFormulas.php`
- `app/Console/Commands/Databackd/CheckConsolidation.php`
- `resources/views/databackd/data/accounts/index.blade.php`, `resources/views/databackd/data/accounts/form.blade.php`
- `resources/lang/bg/databackd-gaps-alerts.php`, `resources/lang/eng/databackd-gaps-alerts.php`
- `resources/lang/bg/databackd-reports-dashboard.php`, `resources/lang/eng/databackd-reports-dashboard.php`

## Ревю B (сигурност) — поправки
- **Клиент приема оферта, която не е видял** (`TeamRequestController::changeStatus`): клиентската форма праща `seen_version` (`updated_at` на заявката при отваряне на страницата); при разлика → „Заявката беше променена междувременно…“ (`messages.changed_meanwhile`). Редът се заключва (`lockForUpdate`) до записа, така че клиентско „Приеми“ и админска промяна не минават едновременно през `canMoveTo()`. Тест през kernel-а: остаряла версия → отказ, статусът остава `proposed`; актуална → `active` (rollback).
- **CSV „Изтегли редовете с грешки“** (`FinImportBatchController::neutralizeCsvCell`): освен първия знак се неутрализира и формула след `,` / `;` / таб в клетката (Excel с разделител „,“).

## Ревю C (регресии) — поправки
- **Ctrl/⌘+K** (`sections/sidebar.blade.php`): не се прихваща в полета, `[contenteditable]`, Quill (`.ql-editor` — там е „вмъкни линк“), при отворен модал или вече обработено събитие.
- **Бутоните в page header на мобилен** (`_components.scss`): `flex: 1 0 auto` — пренасят се на нов ред, вместо да се свиват под текста си.
- **Формулите на системните коефициенти** (`POS(...)` в ROE, ROA, дълг/капитал, обращаемост) се записват в базата само при отваряне на таблото на коефициентите → **стъпка при деплоя:** `SystemRatios::syncForCompanyId()` за всяка компания със системни коефициенти (иначе алертите и детайлът ползват старите формули до първото отваряне).
- Групово въвеждане: флагът „контра“ вече ползва `FinAccount::contraMapFor()` (2411 под 241).
- Модалът на консолидационна група показва предупреждение, когато смяната на валутата е изчистила ръчните курсове.

## За деплоя (S12)
Файлове: `git diff --name-only --diff-filter=AMR f98be1c..HEAD` без `docs/` (вкл. `public/css/main.css(.map)`, `public/js/databackd-charts.js`, `resources/lang/{bg,eng}/**`, `config/dompdf.php`). Без миграции, без seeders. След rsync: `optimize:clear`, `config:cache`, `route:cache`, `view:clear`; синхронизация на системните коефициенти. На production `global_app_name` трябва да е „AFV“ (подпис в имейлите на алертите и справките).
