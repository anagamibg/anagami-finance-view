# Сесия 4 — Финансови коефициенти и консолидация (F2, F7)

Прочети `docs/mvp/COMMON-RULES.md` (особено „Работа с до 10 паралелни агента“), `docs/mvp/00-gap-analysis.md`, **всички файлове в `docs/mvp/handoff/`** (задължително `01-foundation.md`) и `CLAUDE.md`. Ти си **S4** — оркестратор. Без сървър, без branch, без PR. S1 е готова (модели `FinEntity`, `FinAccount`, `FinDimension`, `FinEntry`, `FinancialDataService`; демо данни с 3 юридически лица и вътрешногрупови сделки). Паралелно работят S2, S3, S5 — пипай само своите файлове.

**Пространство на S4** (всичко ново тук е твое): route файлове `routes/databackd/analytics-*.php`, route имена `databackd.analytics.*`, menu файлове `resources/views/databackd/menu/3N-analytics-*.blade.php`, views `resources/views/databackd/analytics/**`, `app/Http/Controllers/Databackd/Analytics/**`, `app/Http/Requests/Databackd/Analytics/**`, `app/Models/Databackd/{FinRatio*,FinConsolidation*}.php`, `app/Services/Databackd/Analytics/**`, `app/Exports/Databackd/Analytics/**`, `app/Console/Commands/Databackd/{CheckFormulas,CheckConsolidation}.php`, lang `resources/lang/{eng,bg}/databackd-analytics-*.php`, миграции `*_databackd_analytics_*.php`, seeders `database/seeders/Databackd/Demo/43AnalyticsDemoSeeder.php`, `docs/mvp/{screenshots,features}/04-analytics.md`, `docs/mvp/handoff/04-analytics.md`.

**Тестове:** `phpunit` не е инсталиран (виж `CLAUDE.md`) и не се добавят пакети — проверките са artisan команди с assert-и (`databackd:check-formulas`, `databackd:check-consolidation`), които връщат exit code ≠ 0 при грешка.

## Фаза 0 — ти, преди агентите
1. Миграции + модели: `fin_ratios` (company_id, code, name, group enum(liquidity, profitability, leverage, efficiency, custom), formula, format enum(ratio, percent, days, currency), target_min, target_max, higher_is_better bool, description, is_system, is_active, sort); `fin_consolidation_groups` (company_id, name, base_currency, method enum(full, proportional), eliminate_intercompany bool, minority_interest bool, description); `fin_consolidation_group_entities` (group_id, entity_id, ownership_pct nullable, fx_rate nullable, include bool); `fin_consolidation_rules` (group_id, type enum(eliminate_counterparty, eliminate_account_pair, reclassify, exclude_account), params json, description, sort). `php artisan migrate`.
2. **`App\Services\Databackd\Analytics\FormulaEngine`** — работещ:
   - Формула = израз с променливи, `+ - * / ( )` и числа. Променливи: `ACC[501]` (салдо към края на периода), `ACC[50*]` (префикс), `FLOW[703]` / `FLOW[70*]` (оборот за периода), `TYPE[revenue]`, `TYPE[expense]`, `SUB[current_asset]`, `SUB[current_liability]`, `SUB[cash]`, `SUB[inventory]`, `SUB[receivable]`… (balance за балансовите, flow за приходи/разходи), `AVG(ACC[...])` (средно от началото и края на периода), `REF[code]` (друг коефициент).
   - **Без `eval`** — малък парсер (tokenizer + shunting-yard). Деление на 0 → `null`.
   - API: `validate(string $formula): array` (грешки на български), `variables(string $formula): array`, `evaluate(string $formula, Carbon $from, Carbon $to, array $filters = [], ?callable $resolver = null): ?float`, `explain(...)`: стойността на всяка променлива (за детайла „компоненти на формулата“). `$resolver` позволява да се подадат стойности отвън (консолидирани данни, тестове).
3. **`App\Services\Databackd\Analytics\RatioCalculator`** — `calculate(FinRatio $r, Carbon $from, Carbon $to, array $filters = []): ?float`, `series(FinRatio $r, Carbon $from, Carbon $to, string $periodicity, array $filters = []): array`, `status(FinRatio $r, ?float $value): string` (good|warning|bad|none).
4. **Интерфейс на консолидацията** — клас `App\Services\Databackd\Analytics\ConsolidationService` с PHPDoc и **фиксиран формат на резултата** (имплементацията е в P5): `run(FinConsolidationGroup $g, Carbon $from, Carbon $to): array` → `['entities' => [id => name], 'lines' => [['account_id','code','name','type', 'by_entity' => [id => float], 'eliminations' => float, 'adjustments' => float, 'minority' => float, 'consolidated' => float]], 'eliminated_entries' => [account_id => [entry_id, ...]], 'balance_check' => ['assets' => float, 'liabilities_equity' => float, 'difference' => float], 'pnl' => [...същите редове за приходи/разходи...], 'balance' => [...за балансовите...]]`. Временно върни празна структура в този формат, за да могат P6/P7 да работят паралелно.
5. Lang `resources/lang/{eng,bg}/databackd-analytics-common.php` (групи коефициенти, формати, статуси, методи и типове правила).

## Фаза 1 — пакети (пусни ги наведнъж)

| Пакет | Задача | Притежава (освен своите lang `databackd-analytics-<пакет>.php`) | Menu файл → пункт |
|---|---|---|---|
| P1 formulas | Задача 1 — системни коефициенти + проверки на двигателя | `Services/Databackd/Analytics/SystemRatios.php` (дефинициите + `installFor(Company $c)`), `Console/Commands/Databackd/CheckFormulas.php` (≥ 15 assert-а: оператори, приоритет, скоби, префикси, AVG, REF, деление на 0, невалидни формули) | — |
| P2 ratios board | Задача 2 — табло с коефициенти | `routes/databackd/analytics-ratios.php`, `Controllers/Databackd/Analytics/RatioDashboardController.php`, `views/databackd/analytics/ratios/**` | `30-analytics-ratios` → „Финансови коефициенти“ |
| P3 ratio detail | Задача 2 — детайл + сравнение на юр. лица + експорт | `routes/databackd/analytics-ratio-detail.php`, `Controllers/.../Analytics/{RatioDetail,RatioComparison}Controller.php`, `Exports/Databackd/Analytics/RatiosExport.php`, `views/databackd/analytics/ratio-detail/**`, `views/databackd/analytics/comparison/**` | `31-analytics-comparison` → „Сравнение на юр. лица“ |
| P4 ratio builder | Задача 3 | `routes/databackd/analytics-builder.php`, `Controllers/.../Analytics/RatioBuilderController.php`, `Requests/Databackd/Analytics/**`, `views/databackd/analytics/builder/**` | `32-analytics-builder` → „Конструктор на коефициенти“ |
| P5 consolidation engine | Задача 4 — изчисление | `Services/Databackd/Analytics/ConsolidationService.php` (заменя временната имплементация от Фаза 0, **без да сменя формата**), `Console/Commands/Databackd/CheckConsolidation.php` | — |
| P6 consolidation rules | Задача 4 — групи и правила (UI) | `routes/databackd/analytics-consolidation-rules.php`, `Controllers/.../Analytics/ConsolidationGroupController.php`, `views/databackd/analytics/consolidation-rules/**` | `34-analytics-consolidation-rules` → „Правила за консолидация“ |
| P7 consolidation worksheet | Задача 4 — работна таблица, елиминации, графика, експорт | `routes/databackd/analytics-consolidation.php`, `Controllers/.../Analytics/ConsolidationController.php`, `Exports/Databackd/Analytics/ConsolidationExport.php`, `views/databackd/analytics/consolidation/**` | `33-analytics-consolidation` → „Консолидация“ |
| P8 demo | Демо данни + реалистичност | `database/seeders/Databackd/Demo/43AnalyticsDemoSeeder.php` | — |

Бележки за brief-ите:
- P2/P3 ползват `RatioCalculator`; опцията „Консолидирано: <група>“ във филтъра на юр. лица се добавя в Фаза 2 (с `$resolver` към `ConsolidationService`), агентите оставят място в UI-а.
- P6 и P7 работят срещу формата от Фаза 0; P7 трябва да се справя и с празен резултат (до Фаза 2).
- P8 вика `SystemRatios::installFor()` от P1 — ако класът още го няма при теста, P8 създава коефициентите директно и го отбелязва в отчета.

## Задачи (текстът, който копираш в brief-ите)

### Задача 1 — Системни коефициенти
Seed за всяка компания (вкл. при първо отваряне на таблото, ако липсват): текуща ликвидност, бърза ликвидност, абсолютна ликвидност, нетен марж, брутен марж, EBITDA марж, ROA, ROE, коефициент на задлъжнялост, дълг/капитал, обращаемост на вземанията (дни), обращаемост на задълженията (дни), обращаемост на материалните запаси (дни), оборот на активите, разходи за персонал/приходи. Формули с променливите на `FormulaEngine`, описания и целеви диапазони на български, коректни `format` и `higher_is_better`.

### Задача 2 — Финансови коефициенти (F2)
- **Табло**: филтър за период (месец/тримесечие/година, избор на конкретен период) и юр. лице. Карти по групи (Ликвидност, Рентабилност, Задлъжнялост, Ефективност, Собствени); всяка карта: стойност (форматирана според `format`), светофар спрямо целевия диапазон, изменение спрямо предходния период, sparkline за последните 12 месеца, tooltip с формулата и описанието.
- **Детайл** (клик на карта): графика за 18 месеца с коридор на целевия диапазон, таблица с компонентите на формулата (`FormulaEngine::explain`) за избрания период.
- **Сравнение на юр. лица**: таблица коефициенти × юр. лица (+ колона за група), оцветяване по статус.
- Експорт в Excel (таблото и сравнението).

### Задача 3 — Конструктор на коефициенти („пластични, според нуждите“)
- Списък на коефициентите (системни — само активиране/деактивиране и целеви стойности; собствени — пълен CRUD).
- Форма: име, група, формат, целеви диапазон, по-високо е по-добре, описание, **редактор на формулата**: текстово поле + бутони/падащи менюта за вмъкване на сметка (Select2 по сметкоплана → `ACC[...]`/`FLOW[...]`), тип/подтип, `AVG`, `REF`, оператори; **жив преглед** (AJAX, debounce) на резултата за избран период и юр. лице, с грешките от `FormulaEngine::validate`.

### Задача 4 — Консолидация (F7)
- **Правила** — „клиентът задава принципа, на който данните се обединяват“: CRUD на групи; за група — юр. лица (включено, % участие override, валутен курс, ако не е EUR), метод (пълна / пропорционална по % участие), елиминиране на вътрешногрупови сделки (по контрагент = друго юр. лице в групата, или по двойки сметки 411↔401, 702/703↔602), рекласификации (сметка → сметка), изключване на сметки, неконтролиращо участие да/не. Правилата са подредени (sort) и имат описание.
- **Изчисление** (`ConsolidationService::run`): за всяко юр. лице — суми по сметки (flow за периода за приходи/разходи, balance към края за балансовите), × валутен курс, × % при пропорционален метод; елиминации по правилата (записват се и id-тата на елиминираните записи); рекласификации и изключвания в колона „Корекции“; неконтролиращо участие при пълен метод с участие < 100%; проверка дали балансът се връзва. `databackd:check-consolidation` — assert-и върху демо групата (елиминациите ≠ 0, консолидирани приходи < сбора на юр. лица, разлика в баланса в допустим праг).
- **Работна таблица**: избор на група и период → два таба: консолидиран ОПР и консолидиран баланс (активи / пасиви + капитал, ред за проверката) с колони: всяко юр. лице, „Елиминации“, „Корекции“, „Неконтролиращо участие“, „Консолидирано“; клик на сума в „Елиминации“ → модал с елиминираните записи. Графика: принос на всяко юр. лице към приходите и печалбата. Експорт в Excel.

### Демо данни (P8, `43AnalyticsDemoSeeder`)
Системните коефициенти + 3 собствени („Разходи за външни услуги / приходи“, „Приходи на служител“, „Покритие на заплатите с наличности (месеци)“) с целеви стойности; 1 консолидационна група „Демо Финанс Груп — консолидирано“ с трите юр. лица (Консулт 70%), правила за елиминиране и 1 рекласификация. Провери, че стойностите излизат реалистични (текуща ликвидност 1.2–2.5, нетен марж 5–20%, задлъжнялост 0.3–0.7 и т.н.) — ако не, добави корекционни `balance` записи в този seeder (`source=demo`, `description='analytics demo adjust'`), без да пипаш seeder-а на S1.

## Фаза 2 и край
Виж COMMON-RULES. Свържи „Консолидирано: <група>“ в филтрите на коефициентите. Пусни `php artisan databackd:check-formulas` и `php artisan databackd:check-consolidation` (трябва да минат), `php artisan databackd:demo --only=...`, smoke: `php docs/mvp/tools/smoke.php databackd.analytics. --ajax` + `--url=` за детайл на коефициент и за консолидацията на демо групата. Всички системни коефициенти трябва да дават числа (не „—“).

Скрийншот списъкът да включва: табло с коефициенти, детайл на коефициент, сравнение на юр. лица, конструктор с жив преглед (валидна и невалидна формула), правила за консолидация, работна таблица (ОПР и баланс), модал с елиминации, графика на приноса.
