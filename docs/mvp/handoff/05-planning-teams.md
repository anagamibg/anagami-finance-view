# Handoff S5: Планиране, бюджетиране, прогнози + услуга „Екипи“

## Статус
**Готово.** Бюджети (списък, създаване, редактор), бюджет vs. реално с Excel, прогнози със сценарии, специалисти, заявки за екип и заявки за анализ (Kanban) работят за админ и за клиент. Smoke (`--ajax`) минава за всички routes; останалите „FAIL“ са очаквани (redirect от `show` към редактора/прогнозата и 403 за клиента на „Специалисти“). Екраните са проверени само чрез вътрешно рендериране — JS (редактор, Kanban, Chart.js коридорът, POST при запис на прогноза) не е гледан в браузър.

## Какво е направено
**Таблици** (миграции `2026_10_27_500000_create_databackd_planning_tables`, `2026_10_27_500100_create_databackd_teams_tables`): `fin_budgets`, `fin_budget_lines`, `fin_forecasts`, `specialist_profiles`, `team_requests`, `team_request_members`, `team_request_status_logs`, `analysis_requests`, `analysis_request_comments` — колоните са в `docs/mvp/features/05-planning-teams.md`.
**Модели** (`App\Models\Databackd\`): `FinBudget`, `FinBudgetLine`, `FinForecast`, `SpecialistProfile`, `TeamRequest`, `TeamRequestMember`, `TeamRequestStatusLog`, `AnalysisRequest`, `AnalysisRequestComment`.
**Service класове:**
- `Planning\BudgetActuals` — `actualsByAccountMonth(int $year, ?int $entityId, array $filters = []): array`, `budgetByAccountMonth(FinBudget $b, array $filters = []): array`, `variance(FinBudget $b, int $fromMonth, int $toMonth, array $filters = []): array`
- `Planning\ForecastService` — `forecast(array $history, string $method, int $horizon, array $params = []): array`, `scenarios(array $history, int $horizon, array $scenarioParams): array`, `historyFor(string $metric, ?string $metricRef, ?int $entityId, Carbon $from, Carbon $to): array`
- `Planning\BudgetFactory` — `createEmpty`, `fromActuals(array $data, int $sourceYear, float $pct = 0.0, bool $fillGaps = true)`, `fromBudget`, `duplicate`, `totals(array $budgetIds)`
- `Teams\PriceEstimator` — `estimate(array $roles, string $engagement, ?int $hoursPerWeek): float`, `averageRates(): array`
- Публични помощни методи в контролери: `SpecialistController::utilisation(iterable $profiles): array`, `TeamRequestController::specialistLoad(?array $profileIds = null): array`, `BudgetVarianceController::buildReport(Request, Collection): array`.
**Routes:** виж таблицата във `features/05-planning-teams.md`. Имена `databackd.planning.{budgets,budget-editor,variance,forecasts}.*` и `databackd.teams.{specialists,requests,analysis}.*`.
**Menu файлове:** `40-planning-budgets`, `41-planning-variance`, `42-planning-forecasts`, `50-teams-requests`, `51-teams-analysis`, `52-teams-specialists`.
**Lang:** `databackd-planning-{common,budgets,budget-editor,variance,forecasts}.php`, `databackd-teams-{common,specialists,requests,analysis}.php` (eng + bg).
**Seeders:** `44PlanningDemoSeeder`, `45TeamsDemoSeeder`. **Команди:** `databackd:check-forecasts`, `databackd:recurring-analysis [--dry-run]`.
**Известия:** 5 Notification класа + 11 мини-view-та в `resources/views/notifications/{all,client}/` (включват `databackd/notifications/item.blade.php`), за да се виждат в камбанката.

## Отклонения от промпта
- Единствените файлове на S1, които са пипнати: `AppServiceProvider.php`, `sections/menu.blade.php`, `config/databackd.php` (`client_menu`) и `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` (както е разрешено).
- Добавени извън списъка файлове (само нови): `resources/views/databackd/notifications/item.blade.php` и `resources/views/notifications/{all,client}/{team_request_*,analysis_request_*}.blade.php`.
- Бюджет в редактора е на ниво без разрези (region/product_group/cost_center = NULL); редовете на сметка с разрези са само за четене. Филтрите по разрез във „Бюджет vs. реално“ сравняват реалните данни с бюджет 0 (демо бюджетите са без разрези).
- Допълнително: страница „редакция“ на заявка за анализ (`teams.analysis.edit/update`), `BudgetFactory::fromActuals` попълва липсващи месеци (2025 е частична).
- Seasonal методът е „ниво × индекс“ без тренд — слаб при растящи серии (напр. парични средства).
- Mail известията за заявки за анализ се пропускат, ако SMTP не е потвърден (локално само database).

## Не е направено / известни проблеми
- `databackd:recurring-analysis` не е в scheduler (не се пипат чужди файлове). Ръчно: `php artisan databackd:recurring-analysis`; за автоматично пускане добави `Schedule::command('databackd:recurring-analysis')->dailyAt('06:00');`.
- Breadcrumb-ът на детайла на заявка за анализ показва английската дума „Analysis“ (извежда се от името на route-а от layout-а).
- Файловете към коментарите се свалят като `attachment-<id>.<ext>` (оригиналното име не се пази).
- Няма автосейв на клетките в редактора на бюджет; няма подреждане на картите в колона на Kanban.
- Заявка за екип #„5 души, пълно работно време“ има `hours_per_week = 40`, а часовете на членовете са 24–36.
- Демо: консолидираният бюджет 2026 показва печалба ~ −12% спрямо плана (разходите растат от други сесии), сметка 609 е +38% (еднократни пикове в реалните данни).

## Какво трябва да знае следващата сесия
- Ползвай `ForecastService` и `BudgetActuals` за каквото и да е планиране; не пипай формата на резултата на `forecast()`.
- Файловете от заявки за анализ: `Files::UPLOAD_FOLDER/databackd/analysis/<име>`; демо изходните файлове са в `storage/app/databackd-demo/teams/`.
- Номерата на демо бюджетите/заявките се менят при всяко пускане на seeder-ите (изтриват и създават наново).
- Не пускай `databackd:demo` без `--only`.

## Как да се провери
```bash
php artisan databackd:demo --only=PlanningDemoSeeder
php artisan databackd:demo --only=TeamsDemoSeeder
php artisan databackd:check-forecasts
php -d memory_limit=1G docs/mvp/tools/smoke.php databackd.planning. --ajax --url=/account/databackd/planning/budget-editor/<id>/edit
php -d memory_limit=1G docs/mvp/tools/smoke.php databackd.teams. --ajax --url=/account/databackd/teams/requests/<id> --url=/account/databackd/teams/analysis/<id>
php -d memory_limit=1G docs/mvp/tools/smoke.php databackd.teams. --ajax --email=p.yordanov@balkan.bg --url=/account/databackd/teams/requests/<id на негова заявка>
```

## Локални файлове извън git
`resources/lang/bg/databackd-planning-*.php` (5 файла) и `resources/lang/bg/databackd-teams-*.php` (4 файла) — `bg/` е в `.gitignore`, качват се ръчно на сървъра; `public/user-uploads/databackd/analysis/*` (демо файлове); `.env`, `.env.stage.backup`, `.claude/`, `Modules/`.
