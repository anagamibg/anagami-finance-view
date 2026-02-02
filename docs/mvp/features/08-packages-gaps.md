# S8 — Модули в пакетите и оставащите функционалности (техническа документация)

Покрива: (А) включването на функционалностите на AFV като модули на SaaS пакетите (`/account/packages`) и (Б) функционалностите от проектното предложение, които липсваха след MVP (банкови извлечения, шаблони на платформи, маржове, годишен отчет по НСС, известия при прагове, съветник за настройка, валутни курсове).

## 1. Модули в пакетите

### Как работят пакетите във AFV SaaS
`packages.module_in_package` (JSON със имена на модули) → при запис на пакет/смяна на пакет на компания `PackageObserver`/`CompanyObserver::updateModuleSettings()` попълват `module_settings` (`company_id, module_name, type admin|employee|client, status, is_allowed`) → `user_modules()` (`app/Helper/start.php`) връща разрешените модули по роля (кеш `user_modules_<userId>`). Модулите на AFV се включват в същата система.

### Модули (`modules` / `module_settings`)
| Модул | Какво включва | Роли |
|---|---|---|
| `databackd_data` | въвеждане, множествено въвеждане, импорт (Excel/CSV/Google Sheets, шаблони на платформи), банкови извлечения, партиди, сметки, разрези, курсове, съветник | admin, employee |
| `databackd_reports` | финансово табло, конструктор на справки, ОПР, парични потоци, контрагенти, запазени справки, маржове, годишен отчет по НСС | admin, employee |
| `databackd_analytics` | коефициенти, детайл, сравнение, конструктор, консолидация и правила, известия при прагове | admin, employee |
| `databackd_planning` | бюджети, редактор, бюджет vs. реално, прогнози | admin, employee |
| `databackd_teams` | услуга „Екипи“: заявки за екип, заявки за анализ, специалисти | admin, employee, **client** |
| `databackd_integrations` | API токени и REST endpoint, външни източници (Google Sheets/CSV по URL) | admin, employee |

Каталог: `databackd_modules_catalog()` (`app/Helper/databackd_modules.php`). Миграцията (`2026_10_28_090000_add_databackd_modules`) hard-code-ва същите имена (не чете config/helper — миграциите не бива да зависят от кеширан config).

### Миграция `2026_10_28_090000_add_databackd_modules`
Само query builder, без модели/observers/scopes, идемпотентна:
1. `modules`: добавя редовете (ако липсват).
2. `packages.module_in_package`: добавя липсващите имена във **всички** пакети (запазва формата — JSON списък или обект `{"<module id>":"<име>"}`, както го праща формата на пакета).
3. `module_settings`: за всяка компания — admin и employee × 6 модула + client × `databackd_teams`; `is_allowed`/`status` според пакета на компанията.
4. Чисти `user_modules_<id>` за всички потребители.
`down()` изтрива `module_settings`, `modules` и маха имената от всички пакети.

### Проверка на достъпа
- `databackd_module('databackd_x'): bool` — `in_array($module, user_modules(), true)`; за супер админ е `false` (`user_modules()` е `[]`).
- `databackd_company_module(int $companyId, string $module): bool` — чете админския ред на `module_settings` (без потребител: API, команди).
- `RequireModule` (route middleware, параметър — името на модула): `abort_if(!company(), 403)` (добавка на S6), после при липса на модула задава езика на потребителя (контролерът още не е извикан) и дава 403 със съобщение `databackd-packages.module_not_in_package`. `RequireCompanyModule` — същото за stateless API (след `FinApiTokenAuth`; 403 JSON).
- **Route файлове:** всеки `routes/databackd/*.php` има `RequireModule` на най-външната група (мапинг: `analytics-*` → analytics, `planning-*` → planning, `reports-*` → reports, `teams-*` → teams, `data-integrations`/`data-sources` → integrations, останалите `data-*` → data, `gaps-*` — по таблицата по-долу). `routes/databackd/api/fin-entries.php` → `databackd_integrations` чрез `RequireCompanyModule`.
- **Меню:** всеки `resources/views/databackd/menu/*.blade.php` е в `@if (databackd_module(...))`. View Composer-ът за `sections.menu` (`AppServiceProvider`) филтрира `$databackdMenuViews` през `databackd_menu_module()` — разделът „Финансов анализ“ изчезва, когато няма нито един модул.
- **Команди:** `databackd:send-reports` (`databackd_reports`), `databackd:recurring-analysis` (`databackd_teams`), `databackd:check-alerts` (`databackd_analytics`) пропускат компании без модула.
- **Зареждане на helper-ите:** `app/Helper/databackd_modules.php` се `require_once`-ва от `routes/databackd/00-modules.php` и от `app/Helper/databackd.php` (в composer `autoload.files`) — така работи и с кеширани routes (на production `route:cache` не изпълнява route файловете). Не се пипат `composer.json`/`dump-autoload`.

### Package UI (A1)
`PackageController::create/edit` подават `$databackdModules = Module::databackdModules()->get()`; формите имат отделен блок „Финансов анализ (AFV)“ (чекбокс + описание; име на полето `module_in_package[<id>]`, както при другите модули); `PackageDataTable` има колона `databackd_modules`; страниците с цените (`front/section/pricing-plan`, `saas/pricing-plan`, `billing/plan`, `billing/package`) показват модулите с `databackd_module_label()`/`databackd_module_description()` (lang `databackd-packages`). Формите получиха и полетата `max_clients`, `max_invoices` (изискват се от `StoreRequest`/`UpdateRequest`).

### Скриване от generic страници
`SuperAdminModuleScope` добавя `module_name NOT LIKE 'databackd\_%'` към всички `Module::…` заявки (страници за роли и права, права на служител — иначе щяха да са 6 празни реда); `Module::databackdModules()` е изключението. `ModuleSettingController` ги скрива от „Настройки на модулите“ — включват се само от пакета.

### Супер админът вижда всичко (A3)
`databackd_hidden($group, $key)` връща `false`, ако `databackd_is_superadmin()` (`user()->is_superadmin`). `HideBillingPages` извиква helper-а, така че не дава 404 за супер админ. При „Влез като“ (`CompanyController::loginAsCompany`) `session('user')` е админът на компанията → скриването важи. Всички четения на `config('databackd.hidden…')` минават през helper-а (проверено с grep).

## 2. Нови функционалности (маршрути `databackd.gaps.*`)

| Slug | URL (`/account/databackd/gaps/…`) | Модул | Меню |
|---|---|---|---|
| `bank-import` | `bank-import` (upload, review, run, result/{batch}, statements, rules) | data | `18-gaps-bank-import` |
| `margins` | `margins` (+ `excel`, `pdf`) | reports | `25-gaps-margins` |
| `statutory` | `statutory` (+ `excel`, `pdf`, `print`) | reports | `26-gaps-statutory` |
| `alerts` | `alerts` (rules CRUD, toggle, check, history, acknowledge) | analytics | `35-gaps-alerts` |
| `onboarding` | `onboarding` (стъпки 1–5, POST company/entities/chart/step/complete/dismiss/restore) | data | `06-gaps-onboarding` |
| `fx-rates` | `fx-rates` (CRUD, `chart`, `fetch-ecb`) | data | `19-gaps-fx-rates` |

Номерата на меню файловете следват групите на сайдбара на S9 (`<10` общ преглед, `1x` данни, `2x` справки, `3x` анализи).

### Таблици (миграция `2026_10_28_100000_create_databackd_gaps_tables`)
- `products.cost_price decimal(15,2) null`; `fin_entries.source` ENUM + `bank_statement`.
- `fin_bank_statements` (`import_batch_id, entity_id, format mt940|camt053, account_iban, currency, statement_no, date_from, date_to, opening_balance, closing_balance, transactions_count, file_name`).
- `fin_counterparty_mappings` (`pattern, match_type contains|exact, direction credit|debit|any, account_id, entity_id, counterparty_name`).
- `fin_fx_rates` (`rate_date, base_currency, currency, rate (единици валута за 1 base — конвенцията на ЕЦБ), source manual|ecb`; unique `company_id, rate_date, base_currency, currency`).
- `fin_alert_rules` (`name, type, entity_id, params json, severity, notify_app, notify_email, recipients json, is_active, last_state, last_checked_at`), `fin_alert_events` (`rule_id, severity, message, value, threshold, period_from, period_to, context json, notified_app, notified_email, acknowledged_at/by`).
- `fin_onboarding` (едно на компания: `steps json, chart_template nss|micro, completed_at, dismissed_at`).
Всички с `company_id` (FK → `companies`, cascade) и модели с `HasCompany` в `app/Models/Databackd/`.

### B1a — банкови извлечения
`Mt940Parser` (тагове `:20: :25: :28C: :60F/M: :61: :86: :62F/M:`; три стила на `:86:`; UTF-8/BOM/UTF-16/Windows-1251) и `Camt053Parser` (`camt.053.001.02/.08`; без външни ентитети) връщат единна структура (`BankStatementParser::parseFile/parseString`): `format, file_hash, warnings, statements[]` с `account_iban, currency, date_from/to, opening/closing_balance (със знак), transactions[]` (`date, amount (със знак), direction, counterparty_name/iban, description, reference …`). `BankStatementImporter::review()` предлага сметка от `CounterpartyMatcher` (най-специфичното правило печели; приходи → приходни сметки, плащания → разходни; неразпределени → 709/609 или избор на потребителя); `import()` създава `fin_import_batches` (source `mt940|camt053`), `fin_bank_statements`, потоци `fin_entries` (`source=bank_statement`, `amount` положителна, `amount_base` през `FxRateService`) и записва **крайното салдо** като `kind=balance` по 503 (начално — само ако няма салдо). Защита от дублиране: хеш на файла, IBAN + период + номер/салда; припокриване → потвърждение; съществуващи транзакции се отмаркират. Отмяна — през партидите (`FinEntryImporter::undo` трие и `fin_bank_statements`) или от екрана на извлеченията. Лимит 5 MB; XXE защита.

### B1b — шаблони на платформи
`ImportTemplates` (`platforms()`, `templates()`, `find()`, `apply($id, $table, ['date'=>Y-m-d])`, `matchColumns()`, `sampleUrl()`): 8 шаблона (`<платформа>_trial_balance`, `<платформа>_journal`) с типични колони (синоними на български/английски) → полета на `FinEntryImporter::fields()`; `fields()` няма дебит/кредит, затова шаблонът добавя изчислени колони в края (оборотна ведомост: оборот по естествената страна за приходни/разходни сметки, крайно салдо за балансови; журнал: ред по приходната/разходната страна, обратни записи → отрицателни суми). Съветникът на импорта (`FinImportController`) има избор на шаблон и дата на ведомостта; „Интеграции“ показва активни карти + карта „Банкови извлечения“. Колоните не са проверени срещу реални експорти (казано в UI).

### B2 — маржове по продукти и услуги
`MarginReport::build(ReportFilters|array $filters, ?int $categoryId = null)` → редове (продукт, количество, приход, себестойност = количество × `cost_price`, брутен марж, марж %, дял), групи (продуктова категория), тотали, продукти без себестойност (с покритие %), сравнение с предходен период. Приход от издадените фактури по `issue_date`, нето след отстъпка на фактурата, минус кредитните известия по продукт. Excel (3 листа) и PDF; графики с Chart.js.

### B3 — годишен отчет по НСС
`NssMapping` (явен мапинг на сметките на `ChartOfAccounts` към редове на баланса и ОПР: по код, по префикс, по подтип; неразпознатите → „Други“ с предупреждение) и `StatutoryReport::balanceSheet($year, $scope)`/`incomeStatement(...)` (`$scope`: `['entity_id'=>…]` или `['group_id'=>…]` → `ConsolidationService::run()`). Сравнителни колони текуща/предходна година, проверка АКТИВИ = ПАСИВИ, сметка 121 се разделя на натрупан резултат и резултат за годината, данък 10% (ориентировъчен). Печат, PDF (dompdf, A4), Excel; disclaimer, че отчетът е опростен.

### B4 — известия при прагове
`AlertChecker::checkCompany($companyId, $dryRun)`/`checkRule()`: типове `ratio_range`, `budget_deviation` (`params.metric` any|revenue|expense|profit; годината до последния завършен месец), `cash_below` (сметки 501+503), `receivables_above`. Известие само при преход `ok → triggered` или промяна на стойността с ≥ 10% (без спам на всеки час); връщане към нормата записва тих „възстановен“ запис. Канали: в приложението (`FinAlertNotification`, `database`, админите на компанията, камбанка през `notifications/all/fin_alert_notification`) и имейл (`FinAlertMail`, пропуска се при непотвърден SMTP). Команда `databackd:check-alerts [--dry-run] [--company=]`. Екрани: правила (CRUD, превключвател, „Провери сега“), история с потвърждаване; типовете, чийто модул липсва (`budget_deviation` → `databackd_planning`), се скриват. Само админ променя.

### B5 — съветник за първоначална настройка
5 стъпки (фирма → юр. лица → сметкоплан → първи импорт → табло) със състояние в `fin_onboarding.steps`; `OnboardingService` (`progress`, `markStep`, `applyChartTemplate`, `complete`, `dismiss`, `visibleState`). Сметкоплан „опростен за микро“ оставя 16 активни сметки (101, 121, 501, 503, 411, 401, 421, 453, 702, 703, 709, 601, 602, 604, 605, 609), не трие и не деактивира сметка със записи/редове на бюджет/правила. Банер с прогрес във финансовото табло (един `@include`), пункт в менюто с индикатор „n/5“, докато не е завършен. Само за админ.

### B6 — валутни курсове
`FxRateService`: `rate($currency, $date, $base='EUR')` (най-близката предишна дата ≤ 31 дни; BGN↔EUR фиксиран 1,95583; кръстосан курс през EUR), `convert()`, `fetchEcb()` (само при бутон; GET към `eurofxref-daily.xml`, таймаут 10 s, rate-limit от S6, DOM без ентитети), `parseEcb()`, `storeEcb()` (ръчните курсове за същия ден се запазват). Интеграция: `FinEntryImporter::toBase($amount, $currency, $date)` (същата валута → пег → курс по дата → курс на компанията → 1:1); `ConsolidationService` конвертира само юр. лица с валута ≠ валутата на групата и без ръчен `fx_rate` в членството, от записите им във валутата на лицето (потоци по курс към датата на записа, салда към крайната дата), без двойна конверсия; формата на `run()` е непроменена (`fxMissing()` дава лица без курс).

### B7 — пропуски от handoff-ите
Колона „Център на разходи“ (`FinEntriesDataTable`); `?from=&to=&entity_id=&account_id=` (един id или списък със запетаи) в списъка със записи, споделено между страницата, DataTable-а и експорта; линковете от ОПР носят период, юр. лица и всички сметки на реда; breadcrumb „Заявки за анализ“; автосейв на клетка в редактора на бюджет (`PUT planning/budget-editor/{budget}/cell`, сериализирани заявки, `persist()` със заключване на реда); етикет „Банково извлечение“ във филтъра на източниците.

### B8 — автоматизация
`app/Console/Kernel.php`: `databackd:recurring-analysis` 06:00, `databackd:send-reports` 07:00, `databackd:check-alerts` на час (`withoutOverlapping`). Изисква cron `* * * * * php artisan schedule:run`.

### B9 — демо данни
`46ProductCostAndFxDemoSeeder`, `47BankStatementsDemoSeeder`, `48AlertsDemoSeeder`, `49OnboardingAndPackagesDemoSeeder` (виж handoff `08-packages-gaps.md`) + частен пакет „Стартов“ без `databackd_analytics` и `databackd_planning`.

## 3. Проверки
`php docs/mvp/tools/smoke.php databackd. --ajax` (админ, служител `g.petrov@demofinans.bg`, клиент `p.yordanov@balkan.bg`); gating с временна смяна на пакета (Стартов; пакет без AFV модули); `databackd:check-formulas` (109), `check-consolidation` (25), `check-forecasts` (69), `check-alerts --dry-run`. Подробности — в handoff-а.
