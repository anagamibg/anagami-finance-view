# Сесия 3 — Финансови справки, репорти и визуализация (F1, F8, R8)

Прочети `docs/mvp/COMMON-RULES.md` (особено „Работа с до 10 паралелни агента“), `docs/mvp/00-gap-analysis.md`, **всички файлове в `docs/mvp/handoff/`** (задължително `01-foundation.md`) и `CLAUDE.md`. Ти си **S3** — оркестратор. Без сървър, без branch, без PR. S1 е готова (модели `FinEntity`, `FinAccount`, `FinDimension`, `FinEntry`, `FinancialDataService`). Паралелно работят S2, S4, S5 — пипай само своите файлове.

**Пространство на S3** (всичко ново тук е твое): route файлове `routes/databackd/reports-*.php`, route имена `databackd.reports.*`, menu файлове `resources/views/databackd/menu/{05,2N}-reports-*.blade.php`, views `resources/views/databackd/reports/**`, `app/Http/Controllers/Databackd/Reports/**`, `app/Services/Databackd/Reports/**`, `app/Exports/Databackd/Reports/**`, `app/Models/Databackd/FinSavedReport.php`, `app/Console/Commands/Databackd/SendSavedReports.php`, `app/Mail/Databackd/**`, lang `resources/lang/{eng,bg}/databackd-reports-*.php`, миграции `*_databackd_reports_*.php`, seeders `database/seeders/Databackd/Demo/42ReportsDemoSeeder.php`, `docs/mvp/{screenshots,features}/03-reports.md`, `docs/mvp/handoff/03-reports.md`.

Чети данни само през `FinancialDataService` или собствените класове в `app/Services/Databackd/Reports/`. Ако е бавно — групирай в SQL, не в PHP.

## Фаза 0 — ти, преди агентите
1. Миграция + модел `fin_saved_reports` (company_id, name, description, type enum(pivot, pnl, cashflow, counterparties), config json, is_shared, schedule enum(none, daily, weekly, monthly), recipients json, last_sent_at, created_by). `php artisan migrate`.
2. **`App\Services\Databackd\Reports\ReportFilters`** — `fromRequest(Request $r): self`, `toQuery(): array`, `toServiceFilters(): array` (формата на `FinancialDataService`), свойства `from, to, periodicity (day|week|month|quarter|year), entityIds, regionId, clientSegmentId, productGroupId, costCenterId, clientId, compare (none|previous|last_year)`; бързи периоди: today, this_week, this_month, last_3/6/12_months, this_year, last_year, custom. По подразбиране: последните 12 месеца, месечно.
3. **`App\Services\Databackd\Reports\PeriodBuckets`** — `make(Carbon $from, Carbon $to, string $periodicity): array` → `[['key' => '2026-10', 'label' => 'окт 2026', 'from' => ..., 'to' => ...], ...]` и `sqlExpression(string $periodicity, string $column): string` (за GROUP BY в MySQL).
4. **`App\Services\Databackd\Reports\ReportBuilder`** — `pivot(array $config, ReportFilters $f): array` → `['columns' => [...], 'rows' => [['key','label','values' => [colKey => float], 'total']], 'totals' => [...]]`; `config`: `rows` (account|account_type|entity|region|client_segment|product_group|cost_center|counterparty), `columns` (period|<разрез>), `measure` (sum|count|avg), `percent_of_total` bool, `show_change` bool. Плюс `compare(...)` за сравнителния период. Направи го работещо и провери с `tinker`.
5. Partial **`resources/views/databackd/reports/_filters.blade.php`** — обща лента с филтри (бързи бутони за период, daterange, периодичност, юр. лица multi, разрези, контрагент, „сравни с“), пази се в URL query string; приема `$filters` (ReportFilters) и `$options` (кои полета да покаже).
6. Lang `resources/lang/{eng,bg}/databackd-reports-common.php` (периоди, периодичности, разрези, мерки, месеци съкратено на БГ).

## Фаза 1 — пакети (пусни ги наведнъж)

| Пакет | Задача | Притежава (освен своите lang `databackd-reports-<пакет>.php`) | Menu файл → пункт |
|---|---|---|---|
| P1 dashboard | Задача 1 | `routes/databackd/reports-dashboard.php`, `Controllers/Databackd/Reports/FinDashboardController.php`, `Services/Databackd/Reports/DashboardMetrics.php`, `views/databackd/reports/dashboard/**` | `05-reports-dashboard` → „Финансово табло“ (първи пункт) |
| P2 builder | Задача 2 — конструктор (UI + pivot таблица + графика) | `routes/databackd/reports-builder.php`, `Controllers/.../Reports/ReportBuilderController.php`, `views/databackd/reports/builder/**` | `20-reports-builder` → „Справки“ |
| P3 export | Задача 2 — Excel/PDF/печат за конструктора | `routes/databackd/reports-export.php`, `Controllers/.../Reports/ReportExportController.php`, `Exports/Databackd/Reports/PivotExport.php`, `views/databackd/reports/export/**` (PDF шаблон + print изглед) | — (бутоните са в P2: route-ове `databackd.reports.export.{excel,pdf,print}` с параметрите на конструктора в query string — дай ги на P2 в brief-а) |
| P4 saved | Задача 2 — запазени справки + автоматично изпращане | `routes/databackd/reports-saved.php`, `Controllers/.../Reports/SavedReportController.php`, `Console/Commands/Databackd/SendSavedReports.php`, `Mail/Databackd/**`, `views/databackd/reports/saved/**` | `24-reports-saved` → „Запазени справки“ |
| P5 pnl | Задача 3 — ОПР | `routes/databackd/reports-pnl.php`, `Controllers/.../Reports/PnlReportController.php`, `Services/Databackd/Reports/PnlStatement.php`, `Exports/Databackd/Reports/PnlExport.php`, `views/databackd/reports/pnl/**` | `21-reports-pnl` → „ОПР (приходи и разходи)“ |
| P6 cashflow | Задача 3 — паричен поток | `routes/databackd/reports-cashflow.php`, `Controllers/.../Reports/CashflowReportController.php`, `Services/Databackd/Reports/CashflowStatement.php`, `Exports/Databackd/Reports/CashflowExport.php`, `views/databackd/reports/cashflow/**` | `22-reports-cashflow` → „Паричен поток“ |
| P7 counterparties | Задача 3 — контрагенти | `routes/databackd/reports-counterparties.php`, `Controllers/.../Reports/CounterpartyReportController.php`, `Exports/Databackd/Reports/CounterpartyExport.php`, `views/databackd/reports/counterparties/**` | `23-reports-counterparties` → „Справка по контрагенти“ |
| P8 demo | Демо данни | `database/seeders/Databackd/Demo/42ReportsDemoSeeder.php` | — |

Бележки за brief-ите:
- P2 и P4: бутонът „Запази справката“ в P2 праща POST към `databackd.reports.saved.store` (P4) с `type=pivot` и `config` = текущата конфигурация; P4 отваря запазена справка с redirect към `databackd.reports.builder.index` + query string. Дай им общия формат на `config` от Фаза 0.
- PDF с кирилица: виж как съществуващите PDF-и (`resources/views/invoices/pdf/`) зареждат шрифт (DejaVu Sans) — P3, P5, P6, P7 да ползват същото.

## Задачи (текстът, който копираш в brief-ите)

### Задача 1 — Финансово табло (F8, R8)
- Обща лента с филтри (`_filters` partial).
- KPI карти (стил `x-cards.widget`): Приходи, Разходи, Печалба, Марж %, Парични средства (салдо 501+503), Вземания (411), Задължения (401) — всяка с % промяна спрямо сравнителния период (зелено/червено).
- Графики (Chart.js или съществуващите Frappe компоненти):
  1. Приходи vs. Разходи vs. Печалба по периоди (комбинирана bar + line).
  2. Приходи по продуктови групи (donut).
  3. Приходи по региони (хоризонтален bar).
  4. Приходи по сегменти клиенти (stacked bar по периоди).
  5. Разходи по вид (602/603/604/605/609…) (donut).
  6. Топ 10 контрагенти по оборот (таблица с мини-бар).
  7. Паричен поток по месеци (bar).
- Бутон „Обнови“ + „Данни към: дата и час“ (смята се live от БД = реално време). Добре изглеждащо и на мобилна ширина (Bootstrap grid).

### Задача 2 — Гъвкави справки (F1 „пластични“)
- Конструктор: избор на **редове** (сметка, тип сметка, юр. лице, регион, сегмент, продуктова група, център на разходи, контрагент), **колони** (периоди според периодичността, или разрез), **стойност** (сума, брой записи, среден размер), филтри (обща лента), „покажи % от общото“, „покажи изменение спрямо предходния период“. Резултат: pivot таблица (`ReportBuilder::pivot`) с тотали, сортиране, + графика под нея (тип избираем: колони/линия/кръгова).
- **Експорт**: Excel (maatwebsite/excel), PDF (dompdf, лого и име на компанията, период и филтри в заглавието, кирилица), печат (print-friendly изглед).
- **Запазени справки**: „Запази справката“ (име, описание, споделена с екипа да/не, автоматично изпращане: няма/дневно/седмично/месечно, получатели). Списък: име, тип, автор, споделена, график, последно изпращане; отваряне, дублиране, изтриване. Команда `databackd:send-reports` — генерира PDF и праща по имейл през текущия mail driver (локално е `log`) на справките, на които им е време; `--dry-run`.

### Задача 3 — Стандартни отчети (всички с обща лента с филтри и експорт Excel/PDF)
- **ОПР (P&L)** по НСС: нетни приходи от продажби (702, 703, 704), други приходи (709), разходи за материали (601), външни услуги (602), амортизации (603), възнаграждения (604), осигуровки (605), други разходи (609), финансови приходи/разходи (721/621), печалба преди данъци, данък, нетна печалба. Колони: периодите (напр. 12 месеца или 4 тримесечия) + общо + % от приходите. Разгъване на ред → по сметки; клик на сума → линк към `databackd.data.entries.index` с филтри, ако `Route::has(...)`.
- **Паричен поток** (опростен): начално салдо, постъпления, плащания, крайно салдо по периоди + графика.
- **Справка по контрагенти**: оборот, брой документи, дял %, тенденция (sparkline), сортиране; клик → детайл по месеци.

### Демо данни (P8, `42ReportsDemoSeeder`)
7–8 запазени справки с реалистични имена и конфигурации, които наистина дават резултати: „Месечни приходи по региони“, „Разходи за персонал по тримесечия“, „Топ клиенти 2026“, „Дневни постъпления — октомври 2026“, „Маржове по продуктови групи“, „Годишно сравнение 2025/2026“, „ОПР — Q3 2026“ (type pnl), „Паричен поток — 12 месеца“ (type cashflow); част споделени, част с автоматично изпращане и `last_sent_at`.

## Фаза 2 и край
Виж COMMON-RULES. Smoke: `php docs/mvp/tools/smoke.php databackd.reports. --ajax`, плюс `--url=` за конструктора с 3 различни конфигурации (дневна, месечна, годишна) и за PDF/Excel експортите (очаква се 200 и файл). Провери, че различните периодичности дават различни, коректни суми (сборът по дни = сборът по месеци за същия период) — с `tinker`.

Скрийншот списъкът да включва: таблото (десктоп + мобилна ширина), конструктора с 3 конфигурации (дневна, месечна, годишна), ОПР (сгънат и разгънат), паричен поток, контрагенти, запазени справки, отворен PDF експорт.
