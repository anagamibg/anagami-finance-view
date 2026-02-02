# Handoff S4 → следващи сесии: Финансови коефициенти и консолидация

## Статус
**Готово.** Табло, детайл, сравнение, конструктор с жив преглед, правила и работна таблица за консолидация, експорт в Excel и демо данни работят. `databackd:check-formulas` (109 assert-а) и `databackd:check-consolidation` (25) минават; `php docs/mvp/tools/smoke.php databackd.analytics. --ajax` → All pages OK (вкл. `--url=` за детайл, консолидация, експорти). Екраните са проверени само чрез вътрешно рендериране — JS (Chart.js, drag & drop на правила, bootstrap-select, модал с елиминации) не е гледан в браузър.

## Какво е направено
**Таблици:** `fin_ratios`, `fin_consolidation_groups`, `fin_consolidation_group_entities`, `fin_consolidation_rules` (колони в `docs/mvp/features/04-analytics.md`). Модели `FinRatio`, `FinConsolidationGroup`, `FinConsolidationGroupEntity`, `FinConsolidationRule` (`HasCompany`).

**Service класове** (`App\Services\Databackd\Analytics\`):
- `FormulaEngine` — `__construct(?int $companyId=null)`; `validate(string, ?string $selfCode=null): string[]`; `variables(string): array`; `evaluate(string, Carbon $from, Carbon $to, array $filters=[], ?callable $resolver=null): ?float`; `explain(...): array{value,error,variables}`; `label(array): string`; `resolveAccounts(string $kind, string $arg): array`.
- `RatioCalculator` — `__construct(?FormulaEngine $engine=null, ?int $companyId=null)`; `calculate(FinRatio, Carbon, Carbon, array $filters=[], ?callable $resolver=null): ?float`; `series(...)`; `status(FinRatio, ?float)`; статични `periods`, `previousPeriod`, `format`.
- `SystemRatios` — `installFor(Company): int`, `installForCompanyId(int): int`, `definitions()`.
- `ConsolidationService` — `run(FinConsolidationGroup, Carbon, Carbon): array` (формат във PHPDoc), `eliminatedEntries(group, accountId, from, to)`, `ratioResolver(group, FormulaEngine): callable`, `resolver(...)` (по код на сметка; не се ползва от екраните), `minorityTotals(array)`, `emptyResult()`.

**Routes** (`databackd.analytics.*`): `ratios.index`, `ratio-detail.{show,export}`, `comparison.index`, `builder.*`, `consolidation-rules.*`, `consolidation.{index,eliminations,export}`. Меню файлове `30`…`34-analytics-*.blade.php`. Контролери в `Http/Controllers/Databackd/Analytics/`, експорти `RatiosExport` и `ConsolidationExport`, Request-и `StoreRatioRequest`, `UpdateRatioRequest`, `UpdateSystemRatioRequest`. Lang `databackd-analytics-{common,ratios,ratio-detail,builder,consolidation-rules,consolidation}.php`.

**Команди:** `databackd:check-formulas [--company=]`, `databackd:check-consolidation [--group= --from= --to= --show]`.

**Demo:** `database/seeders/Databackd/Demo/43AnalyticsDemoSeeder.php` (`php artisan databackd:demo --only=AnalyticsDemoSeeder`): 15 системни + 3 собствени коефициента, група „Демо Финанс Груп — консолидирано“ (3 юр. лица, Консулт 70%, 3 правила), корекции на баланса (`description='analytics demo adjust'`).

## Отклонения от промпта
- Добавени константи във формулите: `DAYS` и `ANNUAL` (365/DAYS) — за дни-коефициентите и годишно изразените ROA/ROE/оборот. `SUB[current_asset]` включва и `cash`, `receivable`, `inventory`.
- `fin_consolidation_group_entities` и `fin_consolidation_rules` имат и `company_id` (общото правило за мулти-тенант).
- Неконтролиращото участие е информативна колона и не се изважда от „Консолидирано“; отделен ред в баланса няма (`minorityTotals()`).
- Колоната „Група“ в сравнението на юр. лица е проста сума без елиминации (консолидираните стойности се виждат през филтъра „Консолидирано“ на таблото и детайла).
- Експортът на сравнението не използва консолидация.
- Резолверът за консолидираните коефициенти е нов метод `ratioResolver`, а не `resolver` (различен подпис).

## Не е направено / известни проблеми
- Демо данните на S1 натрупват пари: коефициентите „абсолютна/бърза ликвидност“, „брутен марж“, ROA и ROE са над целите, но са „добри“ (`higher_is_better`), затова повечето карти са зелени; жълти/червени са „Разходи за персонал/приходи“ и „Разходи за външни услуги/приходи“ в отделни периоди и консолидирано.
- Преклас. 609→602 в демо групата вдига консолидираните външни услуги до ~31% (червено) — умишлено показва ефекта на правилото.
- Подтип `interest` е общ за 621 (разход) и 721 (приход) — EBITDA ползва `ACC[621]`/`ACC[721]`. Сметкоплансът няма разход за данъци.
- Няма валутна конверсия извън ръчния `fx_rate`; без OAuth, без периоди извън календарните при консолидацията.

## Какво трябва да знае следващата сесия
- **Нова сметка 498** „Краткосрочни банкови заеми“ (liability, current_liability) е създадена от `AnalyticsDemoSeeder` само за демо компанията; не е в `ChartOfAccounts::ACCOUNTS`. `FinModelDemoSeeder` трие всички `source='demo'` записи — след него пусни наново `AnalyticsDemoSeeder`.
- На production `databackd:demo` отказва — seeder-ът се вика директно (виж „Production“).
- Не пипай формата на `ConsolidationService::run()` — екраните, експортът и `ratioResolver` зависят от него.
- Коефициентите се смятат в движение (без кеш); таблото е ~0,3–0,8 сек.

## Как да се провери
```bash
php artisan migrate --isolated
php artisan databackd:demo --only=AnalyticsDemoSeeder
php artisan databackd:check-formulas
php artisan databackd:check-consolidation
php -d memory_limit=1G docs/mvp/tools/smoke.php databackd.analytics. --ajax
php -d memory_limit=1G docs/mvp/tools/smoke.php - --url=/account/databackd/analytics/ratio/1 --url="/account/databackd/analytics/ratios?entity=group:<id>"
```

## Локални файлове извън git
`resources/lang/bg/databackd-analytics-{common,ratios,ratio-detail,builder,consolidation-rules,consolidation}.php` (`bg/` е в `.gitignore` → качват се ръчно на сървъра), `.env`, `.env.stage.backup`, `.claude/`, `Modules/`.

## Production (Anagami Finance View) — качено 2026-10-27
- Файловете на S4 (без `docs/`) са на сървъра (`rsync`, `ssh databackd`, `~/public_html`) + 6-те `resources/lang/bg/databackd-analytics-*.php`. Бекъп преди качването: `~/deploy-backup-databackd-mvp-s4-20261027/` (`fin-tables.sql.gz` — дъмп на `fin_*` таблиците на S1/S2, `s4-previous-files.tgz` — предишните версии на файловете). Връщане: разархивирай `s4-previous-files.tgz`, `php artisan optimize:clear && config:cache && route:cache`; новите таблици `fin_ratios`/`fin_consolidation_*` са празни за другите компании и не пречат.
- `php artisan migrate --force --isolated` (4 нови таблици), `optimize:clear`, `config:cache`, `route:cache` → 32 routes `databackd.analytics.*`; без сесия всички пренасочват към `/login`.
- Демо данните на S4 са само в „Демо Финанс Груп“ (company 17) — seeder-ът е викнат директно (`AnalyticsDemoSeeder::run(Company::find(17))`, защото `databackd:demo` отказва на не-`_local` база): 18 коефициента, 1 група, 405 корекционни записа. `databackd:check-formulas --company=17` → 109/109, `databackd:check-consolidation` → 25/25.
- Не е проверено вътре в логнат сесия на production (паролата е в `~/demo-passwords.txt`): само команди, route и HTTP проверки и лог.
