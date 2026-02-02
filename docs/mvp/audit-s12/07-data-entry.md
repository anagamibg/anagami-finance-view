# S12 / A07 — Всеобхватно ръчно въвеждане (финансови записи, групово въвеждане, сметкоплан, разрези, юридически лица)

## Какво е проверено
- **Екрани:** `/account/databackd/data/entries` (списък, филтри, търсене, суми, масово изтриване, експорт, модал за създаване и редакция), `/data/bulk` (таблицата по сметки), `/data/accounts` (дървото, формата), `/data/dimensions` (разделите по тип, модалът), `/data/entities` (схемата `_node`, таблицата, модалът).
- **Роли:** админ, служител (`g.petrov`) — пълен достъп; клиент (`p.yordanov`) — 403 на всички GET и POST (`smoke.php --ajax` за 5-те префикса × 3 роли, плюс страниците за редакция с `--url=`).
- **Реални заявки през kernel-а** (скрипт в scratchpad, за админ и служител, всичко създадено е изтрито): създаване с „1 234,56“, редакция с „2.500,00“, USD без курс за датата → 422, USD с курс от „Валутни курсове“ → `amount_base = сума / курс`, салдо по приходна сметка → 422, „abc“ и „-5“ → 422, формата при „Запази и добави следващ“ помни стойностите, XSS в описанието (екранирано в JSON-а на списъка), изтриване, масово изтриване; групово въвеждане с `rows_json` (вкл. 601 реда), невалидни редове → брой + индекси, изтриване чрез празни суми; сметкоплан — смяна на код на стандартна сметка, подтип „Парични средства“ на приходна сметка, изтриване на стандартна/собствена сметка; юр. лица — непозната валута, цикъл, изтриване с данни; разрези — изтриване на стойност, ползвана в бюджет. Запис, прехвърлен от фактура: при редакция се сменят само сметката/разрезите/описанието, без проверка за курс. **32/32 PASS** (след допълнението — 37/37).
- **Headless Chromium (кликане/клавиатура, worker с поправения CSRF bypass):** грешка при blur под полето, фокус върху първото грешно поле (вкл. bootstrap-select), изчезване на грешката след поправка, Enter = „Запази и добави следващ“ с фокус върху сумата, забранено „Салдо“ при P&L сметка, 422 от сървъра → текст под валутата + фокус, мобилен модал без хоризонтален скрол; групово въвеждане — paste на колона (TAB/нов ред), paste „код ⇥ сума ⇥ бележка“, брой грешки над таблицата, Ctrl/⌘+S блокиран при грешка с фокус върху реда, Enter → следващ ред, запис. 18/20 — двата „FAIL“ са очаквани (конзолното съобщение за 422 и „2.000“ → 2,00, виж отворените въпроси). Няма JS грешки.
- **Снимки** десктоп + мобилен (`--scss=resources/scss/databackd/_data.scss`): `storage/app/snapshots/a07/{entries,bulk,accounts,dimensions,entities,entries-create}-{desktop,mobile}.png` — без PHP/JS грешки.
- `php artisan databackd:check-formulas` — 147/147 OK (изчисленията на справките не са пипани).

## Намерени и поправени грешки
| # | Къде | Проблем | Поправка |
|---|---|---|---|
| 1 | `FinEntryController::toBase()`, `FinEntryBulkController::baseAmount()` | Ръчните и груповите записи **не ползваха курсовете по дата** от „Валутни курсове“ (S8), а импортът ги ползва; груповото въвеждане нямаше и фиксирания курс BGN/EUR. Валута без курс се записваше 1:1 в базовата валута (тихо грешни справки). | Двата пътя смятат с `FinEntryImporter::toBase($amount, $currency, $date)` (нов `FinEntryController::convertToBase()`); нов `hasRate()` — запис във валута без курс за датата се отказва с текст под полето „Валута“ (в груповото въвеждане — съобщение). S10/A2 (умножение по `exchange_rate`) се запазва, защото е част от `toBase()`. |
| 2 | Формата за запис, `loadFormData()` | Списъкът с валути беше само `currencies` на Anagami Finance View + базовата (локално — само EUR), т.е. USD/GBP… с курсове не можеха да се въведат ръчно. | `FinEntryController::currencyCodes()`: базовата, BGN/EUR, валутите на фирмата и всички с курс в `fin_fx_rates`. |
| 3 | `StoreFinEntry` | Салдо по приходна/разходна сметка се приемаше, но **никоя справка не го показва** (P&L чете само `flow`). | Грешка под „Вид“; в UI опцията „Салдо“ се забранява за P&L сметки. |
| 4 | `StoreFinEntry` | Неактивна сметка/юр. лице се приемаха (формата ги крие, но заявката не). Сумата беше `type=number` — „1 234,56“ и paste от Excel не минаваха. Сума до 9 999 999 999 999 може да препълни `amount_base` decimal(15,2) след курса → SQL 500. | Неактивни — само ако вече са на редактирания запис; сумата е текстово поле, нормализира се (`normalizeAmount()`, правилата на груповото въвеждане); лимит 999 999 999 999,99 + проверка след превръщането. |
| 5 | `FinEntryBulkController::store`, `bulk/index` | Редовете се пращаха като отделни полета (5 на ред) → над **`max_input_vars` = 1000** (≈200 реда) PHP тихо изпуска останалите. | Редовете се пращат в едно поле `rows_json` (старият формат `rows[]` още се приема); лимит 3000 реда. |
| 6 | Групово въвеждане | След смяна на валутата на юр. лице (напр. BGN → EUR от 2026) старите месеци се показваха със заглавие „EUR“ и при запис се преименуваха в EUR със същите суми. | `gridCurrency()`: ако записите на месеца са в една друга валута, таблицата се показва и записва в нея. |
| 7 | Списък записи, карта „Обща сума“ | Сума на всички приходи + разходи + салда — число без смисъл. | „Резултат (приходи − разходи)“ по филтрираните обороти (`totals.total`, същия ключ). |
| 8 | Сметкоплан, изтриване | Стандартна НСС сметка (напр. 609) можеше да се изтрие или да ѝ се смени кодът, но `ChartOfAccounts::ensureFor()` я създава отново при всяка справка; синхронизацията и ГФО търсят по код. Сметка, ползвана в бюджет / правило за контрагенти, се изтриваше и оставяше висящи редове. | Стандартните сметки не се трият (бутонът е скрит, сървърът отказва с „деактивирайте“), кодът им е само за четене (и валидация); изтриването проверява `fin_budget_lines` и `fin_counterparty_mappings`. Значка „НСС“ в дървото. |
| 9 | `SaveFinAccountRequest` | Подтип без връзка с типа (напр. „Парични средства“ на приходна сметка) — справките го игнорират. `authorize()` връщаше `true`. | `FinAccountController::SUBTYPES_BY_TYPE` + валидация; в UI се предлагат само подтиповете на типа; `authorize()` отказва на клиент. |
| 10 | Разрези, изтриване | Стойност, ползвана в редове на бюджет, се изтриваше. | Проверка на `fin_budget_lines` (регион/продуктова група/разходен център). |
| 11 | Юр. лица, изтриване | Проверяваше само записи и дъщерни; бюджети, прогнози, правила за известия, извлечения, източници, правила за контрагенти оставаха висящи, а членството в консолидационни групи се изтриваше каскадно без предупреждение. | `otherUsage()` — отказ със списък къде се ползва. |
| 12 | Юр. лица, валута | Свободен текст от 3 букви; лице в непозната валута → всички записи 1:1. | Падащ списък с валутите с курс; валидация `in:`. |
| 13 | `entries/index` | Търсене при всеки клавиш (заявка на всеки символ); Enter в търсачката презареждаше страницата. | Debounce 350 ms, submit се спира. |
| 14 | Модалите (сметка/разрез/юр. лице) | Двоен клик/Enter → двойна заявка (бутонът се блокира едва след старта, Enter не минава през него). | Флаг `busy` до края на заявката. |

## UX подобрения (ui-guidelines §12)
- **Общ скрипт `entries/_form-ux.blade.php`** (`window.dbkFormUx`), ползван от 4-те форми: валидация при напускане на полето (не докато се пише), текст до полето (`role=alert`, `aria-invalid`, `aria-describedby`), грешката изчезва веднага след поправка; проверка преди изпращане с фокус на първото грешно поле; след 422 от сървъра фокусът отива на първата грешка (froiden само скролва страницата, не модала); Enter в едноредово поле записва, Ctrl/⌘+Enter — навсякъде.
- **Бързо въвеждане на запис:** в модала за нов запис Enter = „Запази и добави следващ“, Ctrl+Enter = „Запази и затвори“ (подсказка до бутоните). „Запази и добави следващ“ помни дата, юр. лице, сметка, вид, валута и 4-те разреза (не сумата/контрагента/документа/описанието), показва бележка и фокусира сумата.
- **Групово въвеждане:** paste от Excel/Google Sheets (колона суми надолу от текущия ред; или 2–3 колони „код ⇥ сума ⇥ бележка“ по кода на сметката; съобщение колко са поставени/пропуснати), десетичен знак „,“ и „.“, интервал/точка за хиляди; Enter/↓ и Shift+Enter/↑ между редовете, Ctrl/⌘+S; брой грешки над таблицата + бутон „Към първата грешка“, текст на грешката в клетката (не само червен ред и `title`), без маркиране докато се пише; защита от двойно изпращане.
- **Потвърждения с последствието:** изтриване на запис („ще изчезне от справките“; за запис от фактура/разход — „ще бъде добавен отново при следващата синхронизация“), масово изтриване с броя, сметка с името ѝ; разрез/юр. лице със записи или дъщерни — вместо „Сигурни ли сте?“ и после грешка, веднага обяснение защо не може.
- **Празни състояния с действие:** списък записи („Добави запис“ + „Импорт на данни“), разделите на разрезите („Добави“).
- Сметкоплан: бутон „Стандартен сметкоплан“ (стъпка 3 на съветника — шаблон пълен/микро), `aria-label` на иконните бутони (сметки, разрези, юр. лица).
- **handoff 09:** колоните на списъка се стилизират по `className` (`dbk-col-account`, `dbk-col-amount`, `dbk-col-counterparty`) в `FinEntriesDataTable`, не по `td:nth-child`.

## Нови SCSS partial-и и lang ключове
- SCSS: само `resources/scss/databackd/_data.scss` (без нов partial) — `#fin-entries-table td.dbk-col-*`, `.dbk-empty-actions`, `.dbk-data-keys`, `.bulk-row-error`. Нужен е `npm run prod` от координатора.
- Lang (bg + eng, паритет проверен):
  - `databackd-data-common`: `form_ux.{required,amount}`
  - `databackd-data-entries`: `totals.result`; `form.{repeated_note,kind_pnl_hint,keys_hint,keys_hint_create}`; `messages.{account_inactive,entity_inactive,kind_pnl_flow,no_rate,amount_too_large,confirm_delete_synced}`; променени текстове: `form.placeholder_amount`, `messages.confirm_delete`, `messages.confirm_delete_many` (вече с `:count`).
  - `databackd-data-bulk`: `keys_hint`, `buttons.first_error`, `messages.{invalid_rows_count,too_many_rows,pasted,paste_unknown,paste_overflow}`.
  - `databackd-data-accounts`: `chart_template`, `standard_badge`, `standard_note`, `form.code_standard`, `validation.{subtype_type,standard_code}`, `messages.{standard_delete,in_use}` (in_use допълнен след A10), `usage.{budget_lines,counterparty_rules}`; променен `messages.delete_text` (с `:account`).
  - `databackd-data-dimensions`: `validation_entity_name_taken`, `cannot_delete_title`, `dimension_in_budgets`, `entity_in_use`, `validation_currency_rate`, `usage.{budgets,forecasts,consolidation_groups,alert_rules,bank_statements,data_sources,counterparty_rules}`; променен `currency_help`.

## Миграции
Няма.

## Промени в публични методи (за другите агенти)
- `FinEntryController`: нови `public static baseCurrency()`, `convertToBase(float, string, string $date)`, `hasRate(string, string $date)`, `currencyCodes(?string $current)`; премахнат `protected toBase()`.
- `FinEntriesDataTable`: JSON `totals.total` вече е резултатът (приходи − разходи по оборотите), не сумата на всичко; ключовете са същите. `filteredQuery()` не е пипан.
- `StoreFinEntry`: нова `public static normalizeAmount(string): ?string`, константа `MAX_AMOUNT`.
- `FinAccountController`: нови `SUBTYPES_BY_TYPE`, `public static standardCodes()`.
- Модели — без промени.

## Нужни промени извън моята област
1. **`docs/mvp/tools/snapshot.php` ~ред 170** (S9 инструмент): worker-ът изключва `App\Http\Middleware\VerifyCsrfToken`, а в `web` групата от S6 е `App\Http\Middleware\Databackd\VerifyCsrfTokenPathOnly` → всеки POST от страница в снимка завършва с TokenMismatch (пренасочване към вход). Поправка: `$app->bind(App\Http\Middleware\Databackd\VerifyCsrfTokenPathOnly::class, fn() => new class { … })` (или bind на двата класа).
2. **`app/Http/Middleware/Databackd/RequireModule.php`** (S8): за клиент на `databackd.data.*` отказът е „Модулът „Въвеждане и импорт“ не е включен във Вашия пакет…“ — подвеждащо (пакетът го включва, клиентът просто няма достъп). Предложение: при роля `client` → обикновен 403 („Нямате достъп“) преди проверката на пакета.
3. Глобален стил (S9, `_components.scss`): froiden-helper слага `.is-invalid` и на `.bootstrap-select`, и на скрития `select` → около падащите списъци с грешка се рисува втора червена рамка с иконка (вижда се на всички форми). Предложение: `.bootstrap-select.is-invalid + …` / `select.select-picker.is-invalid { background-image: none }` или скриване на рамката на вътрешния елемент.
4. На снимките сайдбарът е разбит (групите „Данни“, „Анализи“… като широки сиви ленти с подпунктите на един ред) — това е незавършена работа на агента за менюто, не от тази област.
5. `InvoiceExpenseSync` (не е мой): изтрит ръчно запис от фактура се създава отново при следващата синхронизация — сега потвърждението го казва; ако трябва „изтрий завинаги“, нужен е списък с изключени документи в синхронизацията.

## Отворени въпроси / неправено
- „2.000“ (една точка, 3 цифри) се чете като 2,00 — така е и на сървъра, и в импорта (за една стойност не може да се познае); при BG Excel стойностите идват като „2 000“ или „2000“, затова не е сменено.
- Смесени валути в един месец в груповото въвеждане: таблицата е във валутата на юр. лице и при запис променените редове се записват в нея.
- Кодът на собствена (нестандартна) сметка със записи може да се променя — импортите по код ще търсят новия.
- Приходна/разходна сметка с вече записани „салда“ (от импорт с изричен вид) — не са пипани; импортът (не мой) още приема `balance` за P&L сметки.
- `FinEntriesExport` (не мой) не е преглеждан извън smoke-а.
- Колона „Разходен център“ вече има в списъка (handoff 02 е остарял в тази точка).

## Допълнение (по искане на координатора)
1. **Уникално име на юр. лице (от A06, консолидация).** Правилото `Rule::unique('fin_entities', 'name')->where('company_id', …)` (с `ignore` на текущия запис при редакция) вече съществуваше в `StoreFinEntity`/`UpdateFinEntity`, но (а) проверяваше суровия вход, докато се записва изрязаното име — „ ACME “ минаваше покрай „ACME“, и (б) съобщението беше общо. Сега `prepareForValidation()` изрязва и свива интервалите в името преди проверката (`FinEntityController::fill()` записва същото), а съобщението е нов ключ `databackd-data-dimensions.validation_entity_name_taken` (bg+eng, с името: „Вече има юридическо лице с име „…“ в тази фирма…“). Съществуващите дубликати в базата не са пипани; при редакция на дубликат системата ще поиска различно име.
2. **Сметка с бюджетни редове (от A10).** Покрито от поправка 8: `FinAccountController::destroy()` → `otherUsage()` отказва при редове в `fin_budget_lines` (и `fin_counterparty_mappings`) на компанията. Текстът `messages.in_use` е допълнен с „Изчистете сумите ѝ там или деактивирайте сметката“ — еквивалент на предложения от A10 ключ `has_budget_lines`, който не е добавян отделно.
3. **Проверка след промяната на A08 във `FinEntryImporter`** (файлът не е пипан): `php -l` на `FinEntryImporter`, `FinEntryController`, `FinEntryBulkController`, `StoreFinEntry` — OK; kernel тестовете са пуснати отново — **35/35 + 2/2 PASS** (нови: дублирано име с интервали → 422 с името, редакция със собственото име → OK, преименуване на името на друго лице → 422, сметка с бюджетен ред → отказ, след изчистване на реда → изтрита). Ръчният и груповият запис работят; `smoke.php databackd.data.entities --ajax` — OK. Тестовите данни са изтрити (0 остатъка).

Допълнително променени файлове: `app/Http/Requests/Databackd/Data/FinEntity/{StoreFinEntity,UpdateFinEntity}.php`, `app/Http/Controllers/Databackd/Data/FinEntityController.php`, `resources/lang/{bg,eng}/databackd-data-{dimensions,accounts}.php` (всички вече са в списъка по-горе).

## Пълен списък с променени/нови файлове
**Нов:**
- `resources/views/databackd/data/entries/_form-ux.blade.php`

**Променени:**
- `app/Http/Controllers/Databackd/Data/FinEntryController.php`
- `app/Http/Controllers/Databackd/Data/FinEntryBulkController.php`
- `app/Http/Controllers/Databackd/Data/FinAccountController.php`
- `app/Http/Controllers/Databackd/Data/FinDimensionController.php`
- `app/Http/Controllers/Databackd/Data/FinEntityController.php`
- `app/DataTables/Databackd/Data/FinEntriesDataTable.php`
- `app/Http/Requests/Databackd/Data/FinEntry/StoreFinEntry.php`
- `app/Http/Requests/Databackd/Data/FinAccount/SaveFinAccountRequest.php`
- `app/Http/Requests/Databackd/Data/FinEntity/StoreFinEntity.php`
- `app/Http/Requests/Databackd/Data/FinEntity/UpdateFinEntity.php`
- `resources/views/databackd/data/entries/index.blade.php`
- `resources/views/databackd/data/entries/ajax/form.blade.php`
- `resources/views/databackd/data/bulk/index.blade.php`
- `resources/views/databackd/data/accounts/index.blade.php`
- `resources/views/databackd/data/accounts/form.blade.php`
- `resources/views/databackd/data/dimensions/index.blade.php`
- `resources/views/databackd/data/dimensions/form.blade.php`
- `resources/views/databackd/data/entities/index.blade.php`
- `resources/views/databackd/data/entities/form.blade.php`
- `resources/scss/databackd/_data.scss`
- `resources/lang/bg/databackd-data-{entries,bulk,accounts,dimensions,common}.php`
- `resources/lang/eng/databackd-data-{entries,bulk,accounts,dimensions,common}.php`
- `docs/mvp/audit-s12/07-data-entry.md` (този доклад)
