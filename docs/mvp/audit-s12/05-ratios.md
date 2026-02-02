# S12 / A05 — Финансови коефициенти (табло, детайл, сравнение, конструктор)

## Какво е проверено
- **Екрани:** табло `databackd.analytics.ratios.index` (месец/тримесечие/година, юр. лице, консолидирана група), детайл `ratio-detail.show` (вкл. `entity=group:<id>`, несъществуващ id → 404), сравнение `comparison.index` (месец/тримесечие), конструктор `builder.{index,create,edit,targets}`, експорт `ratio-detail.export`.
- **Потоци (POST през HTTP kernel, като админ и служител):** преглед на формула (валидна, с грешка, с функции, с десетична запетая, с кирилица), запис на собствен коефициент, запис с невалидна формула и мин > макс (422), препратка към себе си (422), включване/изключване, изтриване, опит за редакция на системен (отказ), смяна на целите на системен и връщане. Тестовите записи са изтрити, целите върнати.
- **Роли:** админ и служител — всичко 200 (единственият FAIL е умишленият 404 за несъществуващ коефициент); клиент — 403 навсякъде (по дизайн, `abort_403` за клиент + модулът).
- **Математика** (сметкоплан `ChartOfAccounts`, данни от `FinancialDataService`): текуща/бърза/абсолютна ликвидност, нетен/брутен/EBITDA марж, ROA, ROE, задлъжнялост, дълг/капитал, обращаемост на вземания/задължения/запаси, оборот на активите, персонал/приходи — формулите са верни спрямо сметките (241 като контра, P&L — оборот, баланс — салдо към края, AVG — начало/край, ANNUAL/DAYS). Деление на 0 → „—“; периоди — календарни, предходен период със същата дължина.
- **FormulaEngine:** без `eval` (tokenizer + shunting-yard), лимити на дължина/токени/влагане/REF; приоритет, унарен минус, скоби, цикли и самопрепратки, непознати сметки/типове.
- **Цели/светофар:** `status()` за „по-високо е по-добре“ и „по-ниско е по-добре“ (в диапазона / по-добре / близо / извън).
- **Проверки:** `databackd:check-formulas` → **147/147** (бяха 109; +38 нови), `check-consolidation` 35/35, `check-forecasts` 81/81, `check-security` 83/83; smoke `databackd.analytics.` `--ajax` като 3-те роли; snapshot десктоп/мобилен/`--dark` на таблото, детайла (вкл. консолидирана група), сравнението, конструктора; автодовършването е пробвано с headless Playwright върху snapshot HTML-а (без JS грешки).

## Намерени и поправени грешки
1. **Числата с точка** (`1.60`, `12.4 %`, `+0.01`, компоненти `224 587.54`) — `RatioCalculator::format()` и ръчни `number_format(..., '.', ' ')` в контролерите. Нов `RatioCalculator::number()` по локала (bg `1 234,56`, en `1,234.56`, истински знак „−“, NBSP за хиляди и преди `%`/„дни“, без „−0,00“), `format()` → `1,60` / `12,4 %` / `45 дни`; `formatChange()` за промени (`+2,5 п.п.`). Ползва се навсякъде: карти, делти, сравнение, детайл (стойност, компоненти, изчисление), конструктор (преглед), а чрез `format()` и в текстовете на известията (`AlertText`) и Excel (текстова колона). Оста на графиката в детайла — `dbkChart.fmt.number` вместо `toFixed`.
2. **Отрицателен собствен капитал → „зелено“** (`SystemRatios.php`): загуба / отрицателен капитал даваше положителен ROE, а отрицателен дълг/капитал се броеше за „по-добре от целта“ (по-ниско е по-добре). Нова функция `POS(x)` (x > 0, иначе не се изчислява) в знаменателите на ROE, дълг/капитал, ROA и оборота на активите. `SystemRatios::syncForCompanyId()` обновява заключената част (формула, име, група, формат, описание) на съществуващите системни коефициенти — таблото я вика при всяко отваряне (идемпотентно, 1 заявка), целите/посоката/активността на клиента не се пипат.
3. **Линкът от картата към детайла губеше консолидираната група** (`RatioDashboardController::detailUrl`) — при `entity=group:<id>` детайлът се отваряше за „всички юр. лица“. Сега подава `entity` както е избран; подзаглавието на детайла показва „Консолидирано: <група>“.
4. **„В целевия диапазон“ при стойност извън диапазона** (напр. брутен марж 86 % при цел 30–60 %): статусът остава „добре“, но текстът е „По-добре от целта“ (`RatioCalculator::position()` / `statusLabel()`); обобщението на таблото и сравнението — „В целта или по-добре“.
5. **Грешките на FormulaEngine и имената на променливите бяха твърд български текст** (и в английския интерфейс) — изнесени в `databackd-analytics-builder.engine.*` (bg + eng).
6. **Позицията на грешката броеше байтове** — с кирилица преди грешката позицията беше грешна; сега броят е в знаци.
7. **„1,5“ даваше „Недопустим символ „,““** — сега ясно съобщение да се ползва точка (запетаята е позволена само между аргументите на MIN/MAX).
8. **Конструктор:** бутоните за функции не трябва да споделят клас с константите (вмъкваше `undefined`) — хванато при теста и оправено преди края; „Салдо/Оборот“ се пречупваше по средата на думата на мобилен.
9. **Имена/описания на системните коефициенти само на български** — модел `FinRatio`: accessor-и за `name`/`description` на системните чрез `databackd-analytics-ratios.system.<code>.*` (bg + eng), собствените показват въведения текст.
10. Пример „Задлъжнялост“ в помощта всъщност е дълг/капитал (преименуван, с `POS`), „Нетна маржа“ → „Нетен марж“ (терминология); датата на предходния период в детайла беше фиксирана `d.m.Y` — сега по формата на компанията.

## UX подобрения
- **Табло:** под стойността на всяка карта — изречение на обикновен език как да се чете стойността („На всяко 1 € задължения с падеж до година фирмата има 1,60 € в пари, вземания и запаси“; при липсваща стойност — защо). Tooltip-ът вече не показва суровата формула, а описание, „как да се чете“, цел и посока. Техническият код (`current_ratio`) е махнат от картите и сравнението (остава в детайла и конструктора). Сгъваем панел „Как да четем показателите“ (цветове, стрелка, лента на целта, детайл, връзка към целите) — помни състоянието, по подразбиране затворен на мобилен.
- **Детайл:** лента „Как да се чете“ над KPI-тата; когато стойността липсва — кои компоненти са 0 или отрицателни (вместо общото „деление на нула или липсващи данни“); статусите по юр. лица с „По-добре от целта“.
- **Конструктор:** автодовършване във формулата (след `ACC[`/`FLOW[` — сметките, търсене по код или име, напр. „кас“ → 501; след `TYPE[`/`SUB[`/`REF[` — типове/подтипове/коефициенти; дума → променливи, функции, константи; стрелки + Enter/Tab, Esc, клик; ARIA combobox/listbox); бутони `POS( ) ABS( ) MIN( ) MAX( )` (курсорът влиза в скобите); при „—“ в прегледа — кои компоненти са 0; помощ с функциите и десетичната точка; пример ROE с `POS`; подсказка, че процентните цели се въвеждат като процент (5 = 5 %).
- **FormulaEngine:** нови функции `ABS(x)`, `MIN(a, b, …)`, `MAX(a, b, …)`, `POS(x)` (null се предава нагоре); граматиката проверява брой аргументи, празен аргумент, запетая извън функция, функция без скоби.

## Нови SCSS partial-и и lang ключове
- SCSS: само добавки в края на `resources/scss/databackd/_analytics.scss` (секция „ratios dashboard: plain-language help (S12 / A05)“): `.dbk-analytics-ratio-info`, `-ratio-read`, `-help*`, `.dbk-analytics-read` (+ `.is-warning`), `.dbk-analytics-ac*`. Нов partial няма. **Нужен е `npm run prod`** (координаторът).
- Lang (bg + eng, ключовете съвпадат):
  - `databackd-analytics-ratios`: `how_to_read`, `click_for_detail`, `not_calculated`, `help.*`, `system.<15 кода>.{name,description,read}`; променен текст `summary.good`.
  - `databackd-analytics-ratio-detail`: `detail.null_zero`, `detail.null_negative`; променен текст `comparison.summary_title`.
  - `databackd-analytics-builder`: `engine.*` (+ `engine.labels.*`), `autocomplete`, `form.autocomplete_help`, `editor.functions`, `editor.func_{pos,abs,min,max}`, `preview.null_zero`, `help.syntax` (+3 реда), `help.examples.roe`; променени `form.target_help`, `help.intro`, `help.examples.margin/debt`.
  - `databackd-analytics-common` (споделен с A06 — само добавени): `statuses.better`, `pp_unit`.

## Миграции
Няма. Данни: при първо отваряне на таблото (или `SystemRatios::syncForCompanyId`) формулите на `roa`, `roe`, `debt_to_equity`, `asset_turnover` във `fin_ratios` се обновяват с `POS(...)`; стойностите при положителен капитал/активи не се променят.

## Нужни промени извън моята област
1. **Breadcrumb „Ratio“** на детайла (`/account/databackd/analytics/ratio/{id}`): `resources/views/components/app-title.blade.php` превежда сегментите с `app.<camel>`, а ключ `ratio` липсва. Добави в `resources/lang/bg/app.php`: `'ratio' => 'Показател',` и в `resources/lang/eng/app.php`: `'ratio' => 'Ratio',`.
2. `app/Exports/Databackd/Analytics/RatiosExport.php` не е пипан; текстовата колона вече идва локализирана от `RatioCalculator::format()`, а името на системния коефициент — преведено чрез accessor-а. Ако статусът в Excel трябва да различава „По-добре от целта“ — в `cell()`/`mark()` да се ползва `$this->calc->statusLabel()`.
3. Видяно при snapshot-ите (чужди файлове, в процес на работа на друг агент): сайдбарът се рендира счупено (групите като сиви бутони с текст на един ред), а линкът „Към основното съдържание“ се вижда постоянно най-горе на мобилен — вероятно чакат `npm run prod`/довършване.

## Отворени въпроси / неправено
- `TYPE[equity]` = 101 + 121 (няма сметка за текущата печалба), затова собственият капитал в ROE/дълг-капитал не включва резултата от текущата година — ограничение на сметкоплана (S1), не на формулата.
- Брутният марж ползва само 601 като себестойност (няма отчетна стойност на продадените стоки) — при търговски фирми ще е надценен; описанието го казва.
- Служителите могат да създават/трият коефициенти (общото правило в AFV е „клиентът — не“); ако трябва само админ — `abort_403` в `RatioBuilderController` за store/update/destroy/toggle/targets.
- Тестът на автодовършването е с Playwright върху snapshot HTML (без AJAX); на живо в браузър не е гледано.

## Пълен списък с променени/нови файлове
Променени:
- `app/Services/Databackd/Analytics/FormulaEngine.php`
- `app/Services/Databackd/Analytics/RatioCalculator.php`
- `app/Services/Databackd/Analytics/SystemRatios.php`
- `app/Models/Databackd/FinRatio.php`
- `app/Http/Controllers/Databackd/Analytics/RatioDashboardController.php`
- `app/Http/Controllers/Databackd/Analytics/RatioDetailController.php`
- `app/Http/Controllers/Databackd/Analytics/RatioBuilderController.php`
- `app/Console/Commands/Databackd/CheckFormulas.php` (нова секция със 38 проверки — самопроверката на моята област)
- `resources/views/databackd/analytics/ratios/index.blade.php`
- `resources/views/databackd/analytics/ratio-detail/show.blade.php`
- `resources/views/databackd/analytics/comparison/index.blade.php`
- `resources/views/databackd/analytics/builder/form.blade.php`
- `resources/scss/databackd/_analytics.scss`
- `resources/lang/bg/databackd-analytics-ratios.php`, `resources/lang/eng/databackd-analytics-ratios.php`
- `resources/lang/bg/databackd-analytics-ratio-detail.php`, `resources/lang/eng/databackd-analytics-ratio-detail.php`
- `resources/lang/bg/databackd-analytics-builder.php`, `resources/lang/eng/databackd-analytics-builder.php`
- `resources/lang/bg/databackd-analytics-common.php`, `resources/lang/eng/databackd-analytics-common.php` (само нови ключове)

Нови:
- `docs/mvp/audit-s12/05-ratios.md`
