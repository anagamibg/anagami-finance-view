# AFV — UI насоки (S9)

Кратки, задължителни правила за всички екрани на AFV (Blade + Bootstrap 4 + jQuery + Chart.js 3.5). Основата е в кода:
`resources/scss/databackd/_tokens.scss` (CSS променливи), `_components.scss` (класове `dbk-*`), `resources/js/databackd-charts.js` (глобален `dbkChart`), Blade компоненти `resources/views/components/dbk/*`.

## 0. Източници (проучване, 2024–2026)
- Навигация в SaaS: [saasui.design — sidebar UX patterns](https://www.saasui.design/blog/saas-sidebar-navigation-ux-patterns), [Lollypop — SaaS navigation menu design (2025)](https://lollypop.design/blog/2025/december/saas-navigation-menu-design/?p=22188), [Webstacks — SaaS navigation](https://webstacks.com/blog/saas-navigation-menu), [UX Planet — sidebar case study](https://uxplanet.org/case-study-research-sidebar-navigation-b41272026c6d)
- Финансови табла и KPI: [Eleken — financial dashboard examples (2026)](https://www.eleken.co/blog-posts/financial-dashboard-examples), [SetProduct — dashboard UI design (2026)](https://setproduct.com/blog/dashboard-ui-design), [FusionCharts — dashboard best practices](https://www.fusioncharts.com/blog/dashboard-design-best-practices/)
- Таблици: [Shopify Polaris — data table](https://polaris.shopify.com/components/tables/data-table), [Maersk DS — table](https://designsystem.maersk.com/components/table/), [Visa DS — table usage](https://design.visa.com/components/table/usage/), [uxcel — tables best practices](https://uxcel.com/lessons/tables-best-practices-356)
- Графики и достъпност: [Square — accessible colors for data viz](https://developer.squareup.com/blog/accessible-colors-for-data-visualization/), [Visa DS — chart accessibility](https://design.visa.com/data-visualization/charts/bar-chart/accessibility/), skill `dataviz` (валидатор на палитри, CVD ΔE)
- Състояния: [PatternFly — skeleton](https://www.patternfly.org/components/skeleton/design-guidelines), [Frappe UI — chart states](https://ui.frappe.io/docs/charts/states)
- Мобилен: [Sizzy — responsive tables](https://sizzy.co/blog/responsive-tables), [Oracle Analytics — responsive dashboards](https://blogs.oracle.com/analytics/dashboard-responsive-best-practices-with-oracle-analytics)

Изводи, приложени по-долу: групиран сайдбар по задачи с ясно активно състояние; KPI най-горе → тенденции → разбивки → таблици; цветът носи смисъл, не украса; числата вдясно с табличен шрифт; фиксиран header и първа колона; празните състояния предлагат действие; на мобилен — една колона, по-малко серии, хоризонтален скрол за таблиците.

## 1. Палитра (CSS променливи, светла / тъмна тема)
Тъмната тема на AFV е `body.dark-theme` — променливите се предефинират там. Акцентът на бутоните идва от темата на компанията (`--header_color`), затова не го пипаме; данните никога не се оцветяват с акцента.

| Роля | Променлива | Светла | Тъмна |
|---|---|---|---|
| Фон на страницата | `--dbk-bg` | `#f2f4f7` | `#181c34` |
| Повърхност (карта) | `--dbk-surface` | `#ffffff` | `#1f2440` |
| Повърхност 2 (header на таблица, hover) | `--dbk-surface-2` | `#f7f9fb` | `#262c4b` |
| Рамка | `--dbk-border` | `#e4e9f0` | `#363d61` |
| Рамка силна | `--dbk-border-strong` | `#cfd7e2` | `#4b4e69` |
| Текст основен | `--dbk-text` | `#1d2633` | `#e6e8ef` |
| Текст вторичен | `--dbk-text-2` | `#4f5b6b` | `#b9bfcf` |
| Текст приглушен (≥4.5:1 на бяло) | `--dbk-text-muted` | `#66717f` | `#9aa1b5` |
| Акцент (линкове, фокус, активно) | `--dbk-accent` | `#1d6fd6` | `#5c9cf0` |
| Акцент фон | `--dbk-accent-soft` | `#e8f1fc` | `#24345c` |
| Акцент като текст върху акцент фон | `--dbk-accent-text` | `#1a5fb8` | `#8ab8f5` |
| Добро / положително | `--dbk-good` | `#0b7a45` | `#3ccf86` |
| Внимание | `--dbk-warning` | `#9a5b00` | `#f2b13d` |
| Лошо / отрицателно | `--dbk-bad` | `#c9302c` | `#ff7a72` |
| Фон добро / внимание / лошо | `--dbk-good-soft`, `--dbk-warning-soft`, `--dbk-bad-soft` | `#e7f6ee` / `#fdf3e1` / `#fdecec` | `#183a2c` / `#3d3018` / `#45222a` |

Текстови цветове (good/bad) са тъмни стъпки — текстът минава WCAG AA (≥4.5:1). Серийните цветове по-долу са за **маркери** (ленти, линии, сегменти), не за текст.

### Серии в графиките (`dbkChart.colors`)
| Семантика | Променлива | Светла | Тъмна |
|---|---|---|---|
| Приход | `--dbk-revenue` | `#16a06a` | `#1fb87b` |
| Разход | `--dbk-expense` | `#e34948` | `#ef6b6a` |
| Печалба / нето | `--dbk-profit` | `#2a78d6` | `#3987e5` |
| Парични средства / салдо | `--dbk-cash` | `#6250d6` | `#9085e9` |
| Бюджет / план | `--dbk-budget` | `#9aa5b4` | `#6f7891` |
| Прогноза | `--dbk-forecast` | `#eb6834` | `#d95926` |

Категорийна палитра (разрези, юр. лица, продуктови групи), **в този фиксиран ред, без цикъл**: `#2a78d6` синьо, `#eb6834` оранжево, `#1baf7a` аква, `#eda100` жълто, `#e87ba4` магента, `#008300` зелено, `#6250d6` виолетово, `#e34948` червено (тъмна: `#3987e5 #d95926 #199e70 #c98500 #d55181 #008300 #9085e9 #e66767`). Валидирано с `dataviz/validate_palette.js` (съседни двойки CVD ΔE ≥ 8). 9-та и следващи категории → „Други“ (`dbkChart.topN`).

**Приход/разход зелено/червено** е изискване на продукта, но двойката е под прага за далтонисти (deutan ΔE 5.8). Затова: (1) винаги легенда, (2) винаги един и същ ред — приход, разход, печалба, (3) tooltip с имената, (4) делтите в KPI имат стрелка и знак, не само цвят.

## 2. Типография
- Шрифт: системният стек на приложението (`"Helvetica Neue", Helvetica, Arial, sans-serif`). Числа: `font-variant-numeric: tabular-nums` (клас `dbk-num`).
- Скала: заглавие на страница 20/28 semibold; заглавие на карта 15/22 semibold; основен текст 14/20; помощен 13/18; етикети/оси 12/16; KPI стойност 24/30 semibold (мобилен 20).
- Главни букви — като изречение („Приходи по региони“), без ALL CAPS освен малките етикети на групи в сайдбара (12 px, разредени).

## 3. Разстояния, радиуси, сенки
- Мрежа 4 px: `--dbk-space-1..8` = 4, 8, 12, 16, 20, 24, 32, 40.
- Поле на съдържанието: 24 px десктоп, 16 px ≤ 991 px, 12 px ≤ 575 px. Разстояние между карти: 16 px (`mb-3` / `.dbk-grid`).
- Радиус: `--dbk-radius-sm` 6 px (бутони, полета), `--dbk-radius` 10 px (карти), `--dbk-radius-lg` 14 px (модали/големи панели), pill 999 px (значки).
- Сенки: `--dbk-shadow-sm` `0 1px 2px rgba(16,24,40,.06)` (карти), `--dbk-shadow` `0 4px 12px rgba(16,24,40,.08)` (hover/падащи), `--dbk-shadow-lg` (модали). Карта = рамка 1 px + `shadow-sm`, никога двете силни.

## 4. Подредба на страница
1. **Page header** (`<x-dbk.page-header>`): заглавие + 1 ред описание вляво; основни действия вдясно (най-много 1 primary бутон + вторични). На мобилен действията падат под заглавието.
2. **Филтри** в една карта/лента над съдържанието (`.dbk-filters`); бързите периоди — сегментиран контрол (`.dbk-segmented`).
3. **KPI ред** (`.dbk-kpi-grid`): 4 на ред ≥1200 px, 2 на ред ≥576 px, 1 (или 2 компактни) на мобилен.
4. **Тенденции** (широка графика 8/12 + странична 4/12), после **разбивки**, най-отдолу **таблици**.
5. Всяка карта има заглавие (`.dbk-card-title`), по желание подзаглавие и действия вдясно.

## 5. KPI карти (`<x-dbk.kpi>` / `.dbk-kpi`)
- Етикет (13 px, вторичен) + малка иконка вдясно (Bootstrap Icons/FA, приглушен цвят в кръгъл фон 32 px).
- Стойност (24 px, основен текст — **не** червена/акцентна).
- Промяна: стрелка + знак + стойност (`+12,4 %`, за маржове `п.п.`), зелено ако е „добре“ (зависи от метриката: ръст на разходите е лошо), червено ако е „зле“, сиво при 0/няма данни; до нея „спрямо предх. период“.
- Sparkline по желание (`dbkChart.sparkline`, 32 px височина, без оси, цвят по семантика).

## 6. Графики (`dbkChart`)
- **Само през `dbkChart`** — тема, формат и tooltip са общи. `Chart.min.js` и `databackd-charts.js` се зареждат глобално от `layouts/app` — **не** ги включвай отново във view-тата.
- Тип по задача: тенденция във времето → линия/колони; сравнение приход-разход по месеци → групирани колони + линия за печалба (`combo`); структура (≤6 части) → donut, иначе хоризонтални ленти; класиране → хоризонтални ленти, сортирани; бюджет vs реално → bullet/variance ленти; коридор на прогноза → линия + запълнена област; heatmap → таблица с фон по скала.
- **Без втора ос Y.** Две мерки с различен мащаб → две графики.
- Оси: Y с хиляди с интервал (`250 000`), без валута по оста (валутата е в заглавието/tooltip); за големи стойности — компактно (`1,2 млн.`). Решетка само хоризонтална, бледа (`--dbk-grid`). X без решетка.
- Колони: радиус 4 px, макс. дебелина 28 px, отстояние между групи. Линии 2 px, без точки (точка при hover). Области — 10–15 % прозрачност.
- Легенда: отдолу, кръгли маркери 8 px; за 1 серия — без легенда. ≥2 серии — винаги легенда.
- Tooltip: тъмен фон, заглавие = периодът, редове „● Приходи: 1 234,56 €“; режим `index` (всички серии за периода).
- Формат: `Intl.NumberFormat('bg-BG', {style:'currency', currency:'EUR'})` → `1 234,56 €`; проценти `12,4 %`; винаги интервал за хиляди (вкл. 4-цифрени).
- Празни данни: `dbkChart.empty(canvas, текст)` — сив текст + иконка в рамката на графиката, не празни оси.
- Височини: `.dbk-chart` 300 px; `--sm` 220; `--lg` 360; мобилен −20 %.
- Responsive: `maintainAspectRatio:false`, контейнерът задава височината; анимация 400 ms (изключена при `prefers-reduced-motion`).

## 7. Таблици (`.dbk-table`)
- Обвивка `.dbk-table-wrap` (хоризонтален скрол, рамка, радиус). Header: фон `--dbk-surface-2`, 12 px, вторичен текст, **sticky** (`.dbk-table--sticky` + max-height на обвивката).
- Числа: `.num` → вдясно, `tabular-nums`, `white-space:nowrap`; отрицателни `.neg` (червено) — знак „−“ остава. Заглавието на числова колона също вдясно.
- Редове 44 px (компактно `.dbk-table--compact` 36 px); зебра `.dbk-table--striped` за широки финансови таблици; hover фон.
- Фиксирана първа колона при широки таблици: `.dbk-table--sticky-first` (консолидация, бюджет, конструктор).
- Обобщени редове `.dbk-row-total` (удебелено, горна рамка), групови редове `.dbk-row-group` (фон surface-2).
- DataTables (yajra) наследяват същия вид чрез стилизиране на `.dataTable`.

## 8. Форми и филтри
- Етикет над полето (13 px, вторичен), поле 38 px, радиус 6 px, фокус пръстен `0 0 0 3px var(--dbk-accent-soft)` + рамка акцент.
- Филтри в един ред (wrap), „Приложи“ primary, „Изчисти“ link/secondary. Активните филтри се виждат.
- Валидация: червена рамка + текст под полето.

## 9. Състояния
- Празно (`<x-dbk.empty>` / `.dbk-empty`): иконка 40 px в кръг, заглавие, 1 изречение, бутон за следващо действие.
- Зареждане: skeleton (`.dbk-skeleton`) за карти/графики с известна форма; spinner (`.dbk-loader`) за неизвестна; без индикатор под ~300 ms.
- Грешка в една графика не чупи страницата — показва се в нейната карта.

## 10. Сайдбар
- Секция „Анализ“ (S12): директен линк „Финансово табло“ (+ „Начална настройка“ с прогрес, докато съветникът е активен), после сгъваеми групи от 1-во ниво с иконка — Данни (ежедневни действия, после подзаглавие „Настройки“), Финансови справки, Анализи, Планиране, Екипи. Групите са `li.dbk-sb-group` (не `accordionItem`), помнят състоянието си в `localStorage['dbk-sb-groups']`; подредбата е в `$dbkMenuLayout` в `sections/menu.blade.php`. Филтър на менюто (Ctrl/⌘+K).
- Активен пункт: фон `rgba(255,255,255,.08)` + лента 3 px вляво в акцентния цвят + бял текст; активен подпункт — бял текст + точка.
- Иконки 16 px, еднакъв стил (Bootstrap Icons). Сгънат режим: само иконки + tooltip с името.
- Име на компания: до 2 реда, без отрязване по средата на дума; пълното име в tooltip.

## 11. Мобилен (390 px) и достъпност
- Една колона; KPI по 2 на ред (компактни) или 1; графики с ≤6 категории/серии, легенда отдолу.
- Таблици: хоризонтален скрол в обвивката (никога скрол на цялата страница), фиксирана първа колона.
- Зони за клик ≥ 40×40 px; видим фокус (`:focus-visible` пръстен); контраст текст ≥ 4.5:1, маркери/иконки ≥ 3:1.
- Цветът никога не е единственият носител на смисъл (стрелка/знак/етикет до него).

## 12. Допълнение S12 (одит по формуляра, 2026-10)
Източници: [ServiceTitan Anvil — side nav](https://anvil.servicetitan.com/docs/web/components/side-nav/design), [Visa DS — vertical navigation](https://design.visa.com/components/vertical-navigation/usage/), [saasui.design — accordion UX](https://www.saasui.design/blog/saas-accordion-collapsible-ux-patterns), [Lollypop — SaaS navigation (2025)](https://lollypop.design/blog/2025/december/saas-navigation-menu-design/), [Baymard — inline validation](https://baymard.com/blog/inline-form-validation), [CMS DS — inline error](https://design.cms.gov/components/inline-error), [WFP Bridge — form design](https://bridge.designsystem.wfp.org/how-tos/form-design), [AdminLTE — fintech dashboards 2026](https://adminlte.io/blog/fintech-dashboard-design-examples/), [Hyperbots — dashboard UX](https://www.hyperbots.com/glossary/dashboard-ux-design).

- **Сайдбар:** плитка йерархия (най-много 2 нива); групите са сгъваеми и **помнят** отвореното състояние между страниците; активният пункт винаги се вижда (групата му е отворена, скролнат в изгледа); родителят не е линк; търсене/филтър в менюто (Ctrl/⌘+K); етикети на пунктовете — кратки, на един ред.
- **Табла:** малко основни метрики горе; обобщение отделно от детайлния анализ; подчертават се само съществените отклонения.
- **Празни състояния:** обясняват защо е празно, успокояват, ако всичко е наред, и предлагат действие само ако има такова (напр. „Импортирай данни“); при първо влизане — насочват към настройката.
- **Форми:** етикет, програмно свързан с полето; валидация при напускане на полето (blur), не докато се пише; грешката е текст до полето (не само цвят), фокусът отива на първото грешно поле; при таблични форми (групово въвеждане, бюджет) — брой грешки над таблицата; бутонът за запис се блокира по време на заявката (без двойно изпращане).
- **Таблици:** табличен шрифт за числа, цвят само за състояние, компактни редове; сортиране/филтър запазени в URL, където е възможно.
- **Действия с риск** (изтриване, отмяна на импорт, презапис): потвърждение с ясно описание на последствието.
- **Зони за клик** ≥ 40–44 px на мобилен; WCAG 2.2 AA (3.3.1, 3.3.2, 3.3.3; 2.4.11 фокусът не се скрива зад sticky елементи).
