# Техническа документация — Планиране, бюджетиране, прогнози и услуга „Екипи“ (S5)

## Таблици (миграции `2026_10_27_5000*` и `2026_10_27_5001*`, всички с `company_id` и модели с `HasCompany`)
**Планиране** (`create_databackd_planning_tables`)
- `fin_budgets`: `entity_id` (null = цялата група), `name, year, version (draft|approved), scenario (base|optimistic|pessimistic), currency, notes, approved_by, approved_at, created_by`
- `fin_budget_lines`: `budget_id, account_id, region_id, product_group_id, cost_center_id` (разрезите са nullable), `month 1–12, amount` (положителна, знакът идва от типа на сметката). Уникалният индекс по `(budget_id, account_id, region_id, product_group_id, cost_center_id, month)` не пази редове с NULL разрези → записът е винаги ръчен upsert.
- `fin_forecasts`: `name, metric (revenue|expense|profit|cash|account|product_group), metric_ref, entity_id, method (linear|moving_average|seasonal|manual), params json, horizon, scenarios json, created_by`

**Екипи** (`create_databackd_teams_tables`)
- `specialist_profiles`: `user_id, role, seniority, hourly_rate, weekly_capacity_hours, languages, skills json, is_available`; unique `(company_id, user_id)`
- `team_requests`: `client_id, project_id, title, team_size ('1'|'2'|'5'|'10'|'20'|'50'|'custom'), custom_size, roles json, engagement, hours_per_week, start_date, duration_months, description, status (new|reviewing|proposed|active|paused|completed|rejected), monthly_price_estimate, created_by`
- `team_request_members` (`team_request_id, specialist_profile_id, role, hours_per_week`), `team_request_status_logs` (`from_status, to_status, note, user_id, created_at`)
- `analysis_requests`: `team_request_id, client_id, type, title, description, period_from, period_to, due_date, priority, recurrence (none|monthly|quarterly), parent_id, status (new|in_progress|review|delivered|closed), assigned_to, deliverable_file, deliverable_link, client_rating, client_feedback, delivered_at, created_by`; `analysis_request_comments` (`user_id, comment, file`)

## Service класове (`App\Services\Databackd\…`)
- `Planning\BudgetActuals` — `actualsByAccountMonth`, `budgetByAccountMonth`, `variance` (реално − бюджет; „благоприятно“ = приходи над плана / разходи под плана). Реалните данни идват от `FinancialDataService::query()`.
- `Planning\ForecastService` — `forecast(history, method, horizon, params)`, `scenarios(...)`, `historyFor(metric, ref, entityId, from, to)`. Методи: **линеен тренд** (най-малки квадрати, коридор ± σ на остатъците), **плъзгаща се средна** (3/6 мес., прогнозите се връщат в средната), **сезонност** (ниво × сезонен индекс от последните 12 мес.), **ръчно допускане** (месечен % растеж, коридор ± %). Проверка: `php artisan databackd:check-forecasts` (69 проверки върху синтетични серии).
- `Planning\BudgetFactory` — празен бюджет / от реални данни ± % (с попълване на липсващи месеци) / от друг бюджет / дублиране; `totals()`.
- `Teams\PriceEstimator` — `estimate(roles, engagement, hoursPerWeek)`: средна ставка по роля от профилите (резервни 25/20/30/28/30/35 €/ч), пълно работно време = 40 ч, × 4,33 седмици.

## Routes (под `/account/databackd/…`, имена `databackd.planning.*`, `databackd.teams.*`)
| Пакет | Routes |
|---|---|
| Бюджети | `planning/budgets` — `index, create, store, show (→ редактор), destroy, duplicate, approve` |
| Редактор | `planning/budget-editor/{budget}/edit`, `…/save` (AJAX) |
| Бюджет vs. реално | `planning/variance` (+ `variance/export` — Excel) |
| Прогнози | `planning/forecasts` — `index, data (JSON), saved, store, show, destroy` |
| Специалисти | `teams/specialists` — `index, edit (модал), update` |
| Заявки за екип | `teams/requests` — `index, create, estimate (AJAX цена), store, show, status, assign, create-project, destroy` |
| Заявки за анализ | `teams/analysis` — `index (kanban/list), create, store, show, edit, update, move, deliverable, download, rate, comment, comments/{id}/download` |

## Достъп
Планирането и „Специалисти“ са за admin/employee (клиентът получава 403). Клиентът вижда само пунктовете от `config('databackd.client_menu')` (`50-teams-requests`, `51-teams-analysis`) и само свои заявки (чужда → 404, админски действия → 403). Единствените промени във файлове на S1: `AppServiceProvider` (филтър на менюто за клиенти), `sections/menu.blade.php`, `config/databackd.php` → `client_menu` (описани в `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md`).

## Известия
`App\Notifications\Databackd\TeamRequestCreated` (към админите), `TeamRequestStatusChanged` (към клиента), `AnalysisRequestCreated`, `AnalysisRequestStatusChanged`, `AnalysisRequestCommented`; канали database + mail. Показват се в камбанката чрез `resources/views/notifications/{all,client}/<snake_class>.blade.php` → `databackd/notifications/item.blade.php`.

## Файлове и повтарящи се заявки
Резултатите и прикачените към коментарите файлове се пазят през `Files` (папка `databackd/analysis`, `AnalysisRequest::FILE_FOLDER`). `php artisan databackd:recurring-analysis [--dry-run]` създава следващата инстанция (`parent_id`) на доставените/затворените заявки с повторение — идемпотентно; не е в scheduler.

## Демо данни
`44PlanningDemoSeeder` (5 бюджета, 684 реда, 3 прогнози), `45TeamsDemoSeeder` (8 профила, 6 заявки за екип с таймлайн, 28 заявки за анализ, 79 коментара, 16 файла в `storage/app/databackd-demo/teams/` и `public/user-uploads/databackd/analysis/`, проект „EKIP-5“ с 173 записа за време). Пускат се с `php artisan databackd:demo --only=PlanningDemoSeeder|TeamsDemoSeeder`.
