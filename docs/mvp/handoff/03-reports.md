# Handoff S3 → S4/S5: Финансови справки, репорти и визуализация

## Статус
**Готово.** Таблото, конструкторът с Excel/PDF/печат, запазените справки с автоматично изпращане, ОПР, паричният поток и справката по контрагенти работят върху демо данните. `php -d memory_limit=1G docs/mvp/tools/smoke.php databackd.reports. --ajax` → всички страници OK (единственото „FAIL“ е `counterparties.show` без `?name=`, който нарочно пренасочва към списъка). Екраните са проверени само чрез вътрешно рендиране (без браузър, без сървър); JS поведението (Chart.js, сортиране, разгъване в ОПР, модалът „Запази справката“) не е гледано на екран.

## Какво е направено
**Таблица** `fin_saved_reports` (`company_id, name, description, type pivot|pnl|cashflow|counterparties, config json, is_shared, schedule none|daily|weekly|monthly, recipients json, last_sent_at, created_by`), модел `App\Models\Databackd\FinSavedReport`.

**Service класове** (`App\Services\Databackd\Reports\`, всички `__construct(?int $companyId = null)` където е нужно):
- `ReportFilters` — `fromRequest(Request)`, `fromArray(array)`, `toQuery()`, `toServiceFilters()`, `comparePeriod()`, `withRange(Carbon,Carbon)`, `buckets()`, `summary(): string[]`, `static resolvePeriod(string)`.
- `PeriodBuckets` — `make(Carbon,Carbon,string)`, `sqlExpression(string $periodicity, string $column)`, `key()`, `label()`.
- `ReportBuilder` — `normalizeConfig(array)`, `pivot(array $config, ReportFilters $f)`, `compare(array $config, ReportFilters $f)`, `changePct()`.
- `DashboardMetrics` — `kpis(ReportFilters)`, `charts(ReportFilters)`; `PnlStatement::build(ReportFilters)`; `CashflowStatement::build(ReportFilters)`.
- PDF без сесия: `PivotPdf::render(array $config, ReportFilters, int $companyId)`, `PnlPdf::render(ReportFilters, int)`, `CashflowPdf::render(ReportFilters, int)`, `CounterpartyPdf::render(ReportFilters, int, string $side = 'revenue')` (в `App\Exports\Databackd\Reports\`); Excel: `PivotExport`, `PnlExport`, `CashflowExport`, `CounterpartyExport`; помощен `CounterpartyData`.

**Routes** (`databackd.reports.*`): `dashboard.index`; `builder.index`; `export.{excel,pdf,print}`; `saved.{index,store,open,duplicate,destroy}`; `pnl.{index,excel,pdf}`; `cashflow.{index,excel,pdf}`; `counterparties.{index,show,excel,pdf}`. Виж `docs/mvp/features/03-reports.md`.

**Меню** (раздел „Финансов анализ“): `05-reports-dashboard` (Финансово табло), `20-reports-builder` (Справки), `21-reports-pnl`, `22-reports-cashflow`, `23-reports-counterparties`, `24-reports-saved`. **Lang:** `databackd-reports-{common,dashboard,builder,export,saved,pnl,cashflow,counterparties}` (eng + bg). **Команда:** `databackd:send-reports [--dry-run] [--force] [--company=] [--report=]`. **Mail:** `App\Mail\Databackd\SavedReportMail`. **Seeder:** `42ReportsDemoSeeder` (9 справки).

## Отклонения от промпта
- ОПР: в сметкоплана няма сметка за данък → редът „Корпоративен данък 10% (ориентировъчен)“ се изчислява върху положителната печалба преди данъци (ако има сметки с подтип `tax`, ползват се те).
- Паричен поток: оборотите не са свързани с касовите салда → има ред „Други движения“ за съгласуване (начално салдо за периоди от май 2025 е 0 — първото салдо е към 31.05.2025).
- Таблото сравнява по подразбиране с предходния период (`compare=previous`), ако в URL няма `compare`.
- Конструкторът праща към експортите пълния ефективен query string (нормализирана конфигурация + филтри), не суровия.
- „Маржове по продуктови групи“ (демо) показва приходи по продуктови групи — разходите нямат продуктова група. 9-а демо справка: „Разходи по центрове на разходи“.
- Паричният поток на таблото е нетното изменение на салдото 501+503 по месеци (+ линия на салдото).

## Не е направено / известни проблеми
- Няма визуална проверка в браузър (виж „Статус“) — препоръчва се преглед на трите конфигурации на конструктора и разгъването в ОПР.
- **График за автоматичното изпращане не е добавен** (`Kernel::schedule()` е чужд файл): в `app/Console/Kernel.php` → `$schedule->command('databackd:send-reports')->dailyAt('07:00');` (на сървъра — и cron за `schedule:run`, ако го няма).
- ОПР: линкът от сума към `databackd.data.entries.index` носи само `account_id` (списъкът на S2 не приема период/юр. лице в query string), и то само за редове с една сметка.
- При избран регион/сегмент/продуктова група разходите са 0 (нямат тези разрези), при център на разходи — приходите; екраните показват предупреждение. Салдата (парични средства, вземания, задължения) следват само юр. лицата.
- Конструкторът показва най-много 366 колони и 300 реда на екрана (тоталите и графиката включват всичко).
- Логото в PDF се показва само ако компанията има собствено лого; подава се като файлов път (`data:` URI не работи със сегашния dompdf, `PivotPdf` разрешава `data://` само за заявката).
- Контролерите по образеца на S2 задават `pageTitle` в конструктора, преди да е приложен локалът → заглавието излиза на английски; в S3 контролерите го задават в middleware/метода.

## Какво трябва да знае следващата сесия
- Ползвай `ReportFilters` + `ReportBuilder` за всичко ново, което чете оборотите по периоди/разрези; `PeriodBuckets::sqlExpression` гарантира еднакви ключове между PHP и SQL.
- Запазени справки: ново тип справка = нов `type` в enum (нова миграция), `PDF_CLASSES` в `SendSavedReports`, redirect в `SavedReportController::open`.
- Не пипай `FinancialDataService`; PDF/Excel класовете не ползват `company()`/`user()` (за командата).

## Как да се провери
```bash
php artisan databackd:demo --only=ReportsDemoSeeder          # два пъти → 9 справки
php -d memory_limit=1G docs/mvp/tools/smoke.php databackd.reports. --ajax
php -d memory_limit=1G docs/mvp/tools/smoke.php - --url=/account/databackd/reports/builder?periodicity=day --url="/account/databackd/reports/counterparties/show?name=НАП%20%2F%20НОИ"
php artisan databackd:send-reports --dry-run
```

## Локални файлове извън git
`resources/lang/bg/databackd-reports-*.php` (8 файла; `bg/` е в `.gitignore` → качват се ръчно на сървъра), `.env`, `.env.stage.backup`, `.claude/`, `Modules/`.
