# Сесия 5 — Планиране, бюджетиране, прогнози + услуга „Екипи“ (F6, R4)

Прочети `docs/mvp/COMMON-RULES.md` (особено „Работа с до 10 паралелни агента“), `docs/mvp/00-gap-analysis.md`, **всички файлове в `docs/mvp/handoff/`** (задължително `01-foundation.md`) и `CLAUDE.md`. Ти си **S5** — оркестратор. Без сървър, без branch, без PR. S1 е готова (модели `FinEntity`, `FinAccount`, `FinDimension`, `FinEntry`, `FinancialDataService`; 8 служители-специалисти, 2 клиентски потребителя, проекти и задачи). Паралелно работят S2, S3, S4 — пипай само своите файлове.

**Пространство на S5** (всичко ново тук е твое): route файлове `routes/databackd/{planning,teams}-*.php`, route имена `databackd.planning.*` и `databackd.teams.*`, menu файлове `resources/views/databackd/menu/{4N-planning,5N-teams}-*.blade.php`, views `resources/views/databackd/{planning,teams}/**`, `app/Http/Controllers/Databackd/{Planning,Teams}/**`, `app/Http/Requests/Databackd/{Planning,Teams}/**`, `app/DataTables/Databackd/{Planning,Teams}/**`, `app/Models/Databackd/{FinBudget*,FinForecast*,TeamRequest*,AnalysisRequest*,SpecialistProfile}.php`, `app/Services/Databackd/{Planning,Teams}/**`, `app/Notifications/Databackd/**`, `app/Exports/Databackd/Planning/**`, `app/Console/Commands/Databackd/{CheckForecasts,RecurringAnalysis}.php`, lang `resources/lang/{eng,bg}/databackd-{planning,teams}-*.php`, миграции `*_databackd_{planning,teams}_*.php`, seeders `database/seeders/Databackd/Demo/{44PlanningDemoSeeder,45TeamsDemoSeeder}.php`, `storage/app/databackd-demo/teams/**`, `docs/mvp/{screenshots,features}/05-planning-teams.md`, `docs/mvp/handoff/05-planning-teams.md`.

## Фаза 0 — ти, преди агентите
1. Миграции + модели + Eloquent връзки за всичко по-долу. `php artisan migrate`.
   - `fin_budgets` (company_id, entity_id nullable, name, year, version enum(draft, approved), scenario enum(base, optimistic, pessimistic), currency, notes, approved_by, approved_at, created_by) + `fin_budget_lines` (budget_id, account_id, region_id nullable, product_group_id nullable, cost_center_id nullable, month 1–12, amount; unique по budget+account+разрези+month).
   - `fin_forecasts` (company_id, name, metric, metric_ref nullable, entity_id nullable, method enum(linear, moving_average, seasonal, manual), params json, horizon, scenarios json, created_by).
   - `specialist_profiles` (company_id, user_id, role enum(financial_analyst, accountant, controller, business_analyst, data_analyst, team_lead), seniority enum(junior, mid, senior), hourly_rate, weekly_capacity_hours, languages, skills json, is_available).
   - `team_requests` (company_id, client_id, project_id nullable, title, team_size enum('1','2','5','10','20','50','custom'), custom_size, roles json, engagement enum(hours_per_week, full_time), hours_per_week, start_date, duration_months, description, status enum(new, reviewing, proposed, active, paused, completed, rejected), monthly_price_estimate, created_by) + `team_request_members` (team_request_id, specialist_profile_id, role, hours_per_week) + `team_request_status_logs` (team_request_id, from_status, to_status, note, user_id, created_at).
   - `analysis_requests` (company_id, team_request_id nullable, client_id, type enum(report, analysis, ratio_review, budget, forecast, consolidation, other), title, description, period_from, period_to, due_date, priority enum(low, medium, high), recurrence enum(none, monthly, quarterly), parent_id nullable, status enum(new, in_progress, review, delivered, closed), assigned_to nullable, deliverable_file nullable, deliverable_link nullable, client_rating nullable, client_feedback nullable, delivered_at, created_by) + `analysis_request_comments` (analysis_request_id, user_id, comment, file nullable).
2. **`App\Services\Databackd\Planning\BudgetActuals`** — `actualsByAccountMonth(int $year, ?int $entityId, array $filters = []): array` → `[account_id => [month => float]]` (през `FinancialDataService`); `variance(FinBudget $b, int $fromMonth, int $toMonth, array $filters = []): array` → редове `account_id, code, name, type, budget, actual, diff, diff_pct, favorable(bool)`.
3. **Интерфейс `App\Services\Databackd\Planning\ForecastService`** (имплементацията е в P4) с фиксиран формат: `forecast(array $history /* ['2025-05' => float, ...] */, string $method, int $horizon, array $params = []): array` → `['history' => [...], 'forecast' => ['2026-11' => float, ...], 'lower' => [...], 'upper' => [...], 'method' => ..., 'params' => ...]`; `scenarios(array $history, int $horizon, array $scenarioParams): array`; `historyFor(string $metric, ?string $metricRef, ?int $entityId, Carbon $from, Carbon $to): array`. Временно — проста имплементация (последната стойност, ±10% коридор), за да работи P5 паралелно.
4. **`App\Services\Databackd\Teams\PriceEstimator`** — `estimate(array $roles /* role => count */, string $engagement, ?int $hoursPerWeek): float` (средна ставка по роля от `specialist_profiles`, fallback константи: анализатор 25 €/ч, счетоводител 20, контролер 30, бизнес анализатор 28, data analyst 30, team lead 35; full_time = 40 ч/седмица; × 4.33 седмици).
5. Lang `resources/lang/{eng,bg}/databackd-planning-common.php` и `databackd-teams-common.php` (статуси, роли, нива, типове, методи, сценарии, месеци).
6. **Видимост за клиенти:** S1 скрива целия раздел „Финансов анализ“ за роля `client` (View Composer в `AppServiceProvider` + проверка в `menu.blade.php`). Пунктовете на P7 и P8 трябва да се виждат от клиентите. **В Фаза 0** (ти, преди агентите) направи минималната промяна там, маркирана с `databackd`: за клиенти се include-ват само menu файловете, чието име е в нов списък `config('databackd.client_menu', [])` — добави ключа `client_menu => ['50-teams-requests', '51-teams-analysis']` в `config/databackd.php`, и впиши промяната в `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md`. Това е единственото място, където S5 пипа файлове на S1 (другите сесии не ги пипат). Всички други пунктове на S5 са само за admin/employee. В P7/P8 клиентът вижда само своите заявки.

## Фаза 1 — пакети (пусни ги наведнъж)

| Пакет | Задача | Притежава (освен своите lang `databackd-{planning,teams}-<пакет>.php`) | Menu файл → пункт |
|---|---|---|---|
| P1 budgets | Задача 1 — списък и създаване | `routes/databackd/planning-budgets.php`, `Controllers/Databackd/Planning/BudgetController.php`, `Requests/Databackd/Planning/**`, `Services/Databackd/Planning/BudgetFactory.php` (празен / копирай реалните ± % / копирай бюджет), `views/databackd/planning/budgets/**` | `40-planning-budgets` → „Бюджети“ |
| P2 budget editor | Задача 1 — редактор (grid) | `routes/databackd/planning-budget-editor.php`, `Controllers/.../Planning/BudgetEditorController.php`, `views/databackd/planning/budget-editor/**` | — (линк „Редактирай“ от списъка на P1: `databackd.planning.budget-editor.edit` — дай го на P1) |
| P3 variance | Задача 2 | `routes/databackd/planning-variance.php`, `Controllers/.../Planning/BudgetVarianceController.php`, `Exports/Databackd/Planning/VarianceExport.php`, `views/databackd/planning/variance/**` | `41-planning-variance` → „Бюджет vs. реално“ |
| P4 forecast engine | Задача 3 — методите | `Services/Databackd/Planning/ForecastService.php` (заменя временната имплементация, **без да сменя формата**), `Console/Commands/Databackd/CheckForecasts.php` (assert-и за всеки метод върху синтетични серии) | — |
| P5 forecasts UI | Задача 3 — екран, сценарии, запазени прогнози | `routes/databackd/planning-forecasts.php`, `Controllers/.../Planning/ForecastController.php`, `views/databackd/planning/forecasts/**` | `42-planning-forecasts` → „Прогнози“ |
| P6 specialists | Задача 4 | `routes/databackd/teams-specialists.php`, `Controllers/Databackd/Teams/SpecialistController.php`, `views/databackd/teams/specialists/**` | `52-teams-specialists` → „Специалисти“ |
| P7 team requests | Задача 5 | `routes/databackd/teams-requests.php`, `Controllers/.../Teams/TeamRequestController.php`, `Requests/Databackd/Teams/TeamRequest/**`, `DataTables/Databackd/Teams/TeamRequestsDataTable.php`, `Notifications/Databackd/TeamRequest*.php`, `views/databackd/teams/requests/**` | `50-teams-requests` → „Заявки за екип“ |
| P8 analysis requests | Задача 6 | `routes/databackd/teams-analysis.php`, `Controllers/.../Teams/{AnalysisRequest,AnalysisRequestComment}Controller.php`, `Requests/Databackd/Teams/AnalysisRequest/**`, `Notifications/Databackd/AnalysisRequest*.php`, `Console/Commands/Databackd/RecurringAnalysis.php`, `views/databackd/teams/analysis/**` | `51-teams-analysis` → „Заявки за анализ“ |
| P9 demo planning | Демо данни — планиране | `database/seeders/Databackd/Demo/44PlanningDemoSeeder.php` | — |
| P10 demo teams | Демо данни — екипи | `database/seeders/Databackd/Demo/45TeamsDemoSeeder.php`, `storage/app/databackd-demo/teams/**` | — |

Бележки за brief-ите:
- Пакетите за екипите (P7, P8) трябва да се проверят и с `--email=<клиентски потребител от DEMO.md>`: клиентът вижда само своите заявки и не вижда бутоните на админа.
- P10 се нуждае от профилите на специалистите — създава ги сам (P6 е само UI).
- Качените файлове — през съществуващия механизъм за файлове на Anagami Finance View (виж `app/Helper/Files.php`), в папка `databackd/analysis`.

## Задачи (текстът, който копираш в brief-ите)

### Част А — Финансово планиране (F6)

**Задача 1 — Бюджети.**
- Списък на бюджетите (име, година, юр. лице, сценарий, версия-статус, общо приходи/разходи/печалба, автор); създаване с опции: празен / **копирай реалните данни от година X ± %** / копирай друг бюджет; дублиране, изтриване, одобряване (чернова → одобрен).
- **Редактор** — grid (сметки × 12 месеца + Общо), групиран по приходи/разходи с междинни суми и ред „Печалба“, редактируеми клетки (jQuery), бутони за ред: „разпредели годишна сума равномерно“, „+X% спрямо миналата година“, „копирай месец надясно“; запис по AJAX; одобрен бюджет е само за четене.

**Задача 2 — Бюджет vs. реално.**
Избор на бюджет и период (месец / от началото на годината / цяла година), юр. лице и разрез. Таблица (`BudgetActuals::variance`): сметка, бюджет, реално, отклонение, отклонение % — с оцветяване (благоприятно/неблагоприятно според типа сметка) и междинни суми. Графика по месеци: бюджет (линия) vs. реално (колони) за приходи, разходи, печалба. Експорт Excel.

**Задача 3 — Прогнози.**
- `ForecastService` методи: **линеен тренд** (най-малки квадрати), **плъзгаща се средна** (3/6 месеца), **сезонност** (средно ниво × сезонен индекс от последните 12 месеца), **ръчно допускане** (% растеж месец към месец); коридор = ± стандартното отклонение на остатъците (за ръчния — ± параметър %).
- Екран: показател (приходи, разходи, печалба, парични средства, конкретна сметка или продуктова група), юр. лице, хоризонт 3/6/12 месеца, метод → графика: история (плътна линия) + прогноза (пунктир) + коридор (запълнена област), таблица по месеци.
- **Сценарии**: базов / оптимистичен / песимистичен (параметри: % ръст на приходите, % ръст на разходите) → сравнителна графика на трите + ефект върху печалбата и паричните средства в края на хоризонта.
- Запазване на прогноза (`fin_forecasts`) + списък със запазени прогнози (отваряне, изтриване).

### Част Б — Услуга „Екипи“ (R4: софтуер + реални екипи)
Документът описва, че клиентът „поръчва конкретно колко и какви специалисти са необходими и за каква финансова задача“, екипи от **1, 2, 5, 10, 20, 50 или повече души**, или само „няколко часа на седмица“, и „ежемесечно поставя задачи за анализи, справки, репорти“.

**Задача 4 — Специалисти.** Екран с карти: аватар (съществуващия на служителя), име, роля, ниво, ставка, заетост (% от капацитета според активните заявки за екип и отворените заявки за анализ), умения (badges), наличност. Филтър по роля/ниво/наличност. Редакция на профила (модал) за admin.

**Задача 5 — Заявки за екип.**
- Форма (клиент и admin): визуален избор на размера на екипа — 6 големи карти **1 / 2 / 5 / 10 / 20 / 50+** (+ „друг брой“); роли и брой от всяка (сборът = размера); ангажираност (часове седмично или пълно работно време); начало, продължителност в месеци; описание на финансовата задача → **ориентировъчна месечна цена на живо** (`PriceEstimator`, AJAX).
- Списък (DataTable): заглавие, клиент, размер, ангажираност, статус (badge), цена/месец, начало; филтри. Клиентът вижда само своите.
- Детайл: данни, таймлайн на статусите (`team_request_status_logs`), назначени специалисти (admin ги избира от профилите — Select2 с роля и заетост), отработени часове за текущия месец (от `project_time_logs`, ако има `project_id`), бутони за смяна на статус (admin). При статус `active` — опция „Създай проект“ в съществуващия модул „Проекти“ (с назначените специалисти като членове) и запис на `project_id`.
- Известия (Laravel Notification, database + mail през текущия driver): към админите при нова заявка; към клиента при смяна на статуса.

**Задача 6 — Заявки за анализ (месечни задачи към екипа).**
- Клиентът създава заявка (тип, заглавие, описание, период на данните, краен срок, приоритет, повторение, по избор — към коя заявка за екип), вижда статуса, сваля резултата, оценява (1–5 звезди + коментар).
- Специалистът/admin: назначава, сменя статус, качва файл с резултата, добавя линк към запазена справка/прогноза (ако route-ът съществува — `Route::has`), коментира (с прикачен файл).
- Изгледи: **Kanban** по статуси (jQuery UI sortable — плъзгането сменя статуса, само за admin/специалист) и списък; филтри по клиент, специалист, тип, месец. Детайл с коментари.
- `databackd:recurring-analysis` — за доставените/затворени заявки с `recurrence` създава следващата инстанция (`parent_id`), ако още я няма; `--dry-run`. Не редактирай чужди файлове за scheduler — документирай ръчното пускане в handoff-а.

### Демо данни
- **P9 `44PlanningDemoSeeder`**: одобрен базов бюджет 2026 за всяко юр. лице + един за цялата група, изграден от реалните данни за 2025 (+8%), с реалистични отклонения спрямо реалното 2026 (някои месеци над, някои под); чернова на оптимистичен бюджет 2027; 3 запазени прогнози (приходи — сезонност, разходи — линеен тренд, парични средства — сценарии).
- **P10 `45TeamsDemoSeeder`**: профили за 8-те специалисти; 6 заявки за екип в различни статуси и размери (1 човек × 10 ч/седмица — активна; 2 — активна; 5 — активна с проект; 10 — предложение; 20 — в преглед; 50 — нова) с таймлайн; 25–30 заявки за анализ във всички статуси, разпределени между двата клиента и специалистите, с качени примерни файлове (генерирай малки PDF с dompdf и XLSX с maatwebsite/excel), коментари и оценки, няколко повтарящи се.

## Фаза 2 и край
Виж COMMON-RULES. Пусни `php artisan databackd:check-forecasts`, `php artisan databackd:demo --only=...` за двата seeder-а, smoke: `php docs/mvp/tools/smoke.php databackd.planning. --ajax`, `php docs/mvp/tools/smoke.php databackd.teams. --ajax`, и същото за teams с `--email=<клиент>`, + `--url=` за редактора на бюджет, детайл на заявка за екип и детайл на заявка за анализ.

Скрийншот списъкът да включва: списък бюджети, редактор на бюджет, бюджет vs. реално (таблица + графика), прогноза с коридор, сравнение на сценарии, специалисти, форма за заявка за екип (с избран размер и цена), детайл на заявка за екип, Kanban на заявките за анализ, детайл на заявка за анализ с резултат и оценка — **и екраните за екипи от гледна точка на клиентски потребител**.
