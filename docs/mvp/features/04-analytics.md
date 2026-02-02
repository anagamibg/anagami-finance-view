# Техническа документация — Финансови коефициенти и консолидация (S4)

## Таблици (миграции `2026_10_27_4000*`, `2026_10_27_4001*`)
Всички с `company_id` (FK към `companies`) и `HasCompany` в модела.
- `fin_ratios`: `code (unique с company), name, group enum(liquidity|profitability|leverage|efficiency|custom), formula, format enum(ratio|percent|days|currency), target_min, target_max, higher_is_better, description, is_system, is_active, sort` → `FinRatio`.
- `fin_consolidation_groups`: `name, base_currency, method enum(full|proportional), eliminate_intercompany, minority_interest, description` → `FinConsolidationGroup`.
- `fin_consolidation_group_entities`: `group_id, entity_id, ownership_pct (null = от юр. лицето), fx_rate (null = 1), include` (unique group+entity) → `FinConsolidationGroupEntity`.
- `fin_consolidation_rules`: `group_id, type, params json, description, sort` → `FinConsolidationRule`. Параметри: `eliminate_counterparty {accounts?}`, `eliminate_account_pair {pairs:[[A,B],…]}`, `reclassify {from,to}`, `exclude_account {accounts:[…]}`.

## Двигател на формулите: `App\Services\Databackd\Analytics\FormulaEngine`
Без `eval`: tokenizer + shunting-yard. Операции `+ - * / ( )`, унарен минус. Променливи: `ACC[501]`, `ACC[50*]` (салдо към края; за приходни/разходни сметки — оборот), `FLOW[703]`/`FLOW[70*]` (оборот), `TYPE[asset|liability|equity|revenue|expense]`, `SUB[…]` (`current_asset` включва и cash, receivable, inventory), `AVG(ACC|SUB|TYPE[…])` (средно от салдото в началото и края), `REF[код]` (друг коефициент; цикли се отхвърлят), константи `DAYS` и `ANNUAL` (365/DAYS). Деление на 0 → `null`.
API: `validate($formula, $selfCode)`, `variables`, `evaluate(…, $filters, $resolver)`, `explain` (стойност на всяка променлива), `label`, `resolveAccounts`. `$resolver(kind, arg, avg, from, to, filters): ?float` подменя стойностите на променливите (ползва се за консолидирани данни).
Данните идват от `FinancialDataService` (S1); стойностите на променливите се кешират в инстанцията.

## `RatioCalculator`
`calculate`, `series` (month|quarter|year), `status` (good|warning|bad|none: в диапазона = good; излизане в благоприятната посока според `higher_is_better` = good; извън целта до 15% от границата = warning, иначе bad), статични `periods`, `previousPeriod`, `format`.
`SystemRatios` — 15 системни коефициента (ликвидност 3, рентабилност 5, задлъжнялост 2, ефективност 5), `installFor(Company)` / `installForCompanyId(int)` идемпотентно; не презаписва редактирани цели.

## Консолидация: `ConsolidationService::run(group, from, to)`
1. По юр. лице: P&L сметки — оборот за периода; балансови — последното салдо към края (като `balanceAt`); × `fx_rate`; при пропорционален метод × % участие.
2. Елиминации (само при `eliminate_intercompany`), по `sort`: записи с `counterparty_name` = друго включено юр. лице (по контрагент или по двойки сметки). Колоната е отрицателна; всеки запис се елиминира най-много веднъж (`eliminated_entries[account_id]`). 411↔401: демо данните имат вътрешногрупови салда (`ВГ-САЛДО-…`), иначе салдото се извежда от вътрешногруповите продажби от месеца на баланса.
3. Корекции: `reclassify` (само между сметки от един тип) и `exclude_account`.
4. Неконтролиращо участие (пълен метод, участие < 100%): `minority` = Σ по юр. лица `by_entity × (100−%)/100`; информативна колона, не се изважда от `consolidated`; `minorityTotals()` дава дела в капитала и печалбата.
5. `consolidated = Σ by_entity + eliminations + adjustments`; `balance_check` активи (с контра 241) срещу пасиви + капитал.
Други методи: `eliminatedEntries()` (модалът), `ratioResolver()` (консолидирани коефициенти), `emptyResult()`.

## Routes (под `/account/databackd/analytics/`, имена `databackd.analytics.*`)
| Пакет | Routes |
|---|---|
| Табло | `ratios.index` — `period_type`, `period`, `entity=all|id|group:id` |
| Детайл / сравнение / експорт | `ratio-detail.{show,export}`, `comparison.index` |
| Конструктор | `builder.{index,create,store,edit,update,destroy,toggle,preview,targets,targets.update}` |
| Правила | `consolidation-rules.{index,create,store,show,edit,update,destroy,entities.update,quick-intercompany,rules.*}` |
| Работна таблица | `consolidation.{index,eliminations,export}` |

## Артисан проверки
`databackd:check-formulas` (109 assert-а) и `databackd:check-consolidation [--group= --from= --to= --show]` (25 assert-а); exit code ≠ 0 при грешка.

## Демо данни: `43AnalyticsDemoSeeder`
Системните и три собствени коефициента; групата „Демо Финанс Груп — консолидирано“ с три правила. Коригира балансите на S1 с записи `description='analytics demo adjust'` (краткосрочен банков кредит на нова сметка 498 срещу дълготрайни активи, вътрешногрупови салда 411/401, начални салда 30.04.2025) и 6 разходни записа за октомври 2026 (`АНК-…`), за да са коефициентите в реалистични диапазони.
