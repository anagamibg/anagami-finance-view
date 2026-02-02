# 09 — UI/UX: дизайн система, сайдбар, страници, графики (S9)

## Цел
Интерфейсът да изглежда като модерен финансов SaaS: групиран сайдбар с ясно активно състояние, еднакви page header-и, карти, KPI, таблици и форми, четими графики с обща тема, добре и на 390 px, тъмна тема. Текстовете не са пипани (S7).

## Основа
| Част | Файл | Какво |
|---|---|---|
| Насоки | `docs/mvp/phase2/ui-guidelines.md` | Проучване (15 източника), палитра (светла/тъмна), типография, разстояния, KPI, графики, таблици, форми, състояния, сайдбар, мобилен/достъпност |
| Токени | `resources/scss/databackd/_tokens.scss` | CSS променливи `--dbk-*` на `:root`, тъмни стойности на `body.dark-theme` |
| Компоненти | `resources/scss/databackd/_components.scss` | Класове `dbk-*`: page header, card, grid, KPI, delta, chart, legend, segmented, tabs, filters, chip, badge, traffic, progress, table (sticky header/първа колона, `.num`, `.neg`, групови/обобщени редове), empty, skeleton, loader, stepper, dl |
| Blade | `resources/views/components/dbk/{page-header,card,kpi,empty,chart}.blade.php` | `<x-dbk.*>` обвивки на класовете |
| Графики | `resources/js/databackd-charts.js` → `public/js/databackd-charts.js` | Глобален `window.dbkChart`: тема за Chart.js 3.5 (шрифт, решетка, легенда, тъмен tooltip, анимация/`prefers-reduced-motion`), `bar/line/combo/donut/sparkline/create/empty/destroy/topN`, `fmt.money/number/percent/compact/axis` (`Intl.NumberFormat` bg-BG, EUR, интервал за хиляди и при 4 цифри), роли `revenue`(зелено)/`expense`(червено)/`profit`(синьо)/`cash`/`budget`/`forecast`, категорийна палитра във фиксиран ред (валидирана за далтонизъм) |
| Зареждане | `resources/views/layouts/app.blade.php` | `Chart.min.js` + `databackd-charts.js` глобално след `main.js`; `?v=filemtime` на `main.css`/`main.js`/`databackd-charts.js`; `<html lang>` = локала (досега беше твърдо `en`) |
| Build | `webpack.mix.js` | `.scripts(['resources/js/databackd-charts.js'], 'public/js/databackd-charts.js')`, `.disableNotifications()`; `main.scss` импортира токените, компонентите и 10 partial-а на пакетите |
| Преводи | `resources/lang/{eng,bg}/databackd-ui.php` | Заглавия на групите в сайдбара и общи текстове (`no_data`, `other`, `vs_previous`…) |

## Инструмент за визуална проверка без сървър
`php docs/mvp/tools/snapshot.php <url> [--email=] [--out=] [--only=desktop|mobile] [--viewport] [--wait=] [--dark] [--offline] [--scss=<partial>]` (описан и в `COMMON-RULES-2.md`).
- `snapshot.php` стартира `snapshot.js` (Node + Playwright от `kukie.io/kukie/node_modules/playwright`, headless Chromium). Браузърът отваря `http://127.0.0.1:8123/<url>`; нищо не слуша — всяка заявка се прихваща (`page.route`): файлове от `public/` от диска, всичко друго (страницата, DataTables/AJAX, POST) → дълго живеещ PHP worker (`snapshot.php --worker`), който рендира през Laravel HTTP kernel като избрания потребител (като `smoke.php`; CSRF е изключен само в worker-а).
- PNG 1440×900 и 390×844 (@2x) на цялата страница (до 6000 px; viewport-ът се разширява, за да обхване фиксирания сайдбар), HTML с `<base href="file://…/public/">`, списък с PHP exceptions и JS грешки. Преди снимката всички Chart.js инстанции се довършват (`stop()` + `update('none')`) — headless Chromium понякога пропуска кадри от анимацията.
- `--scss=` компилира partial с локалния `sass` и го инжектира след `main.css` (преглед на CSS без `npm`).

## Пакети (10 агента, паралелно)
| Пакет | Файлове | Основно |
|---|---|---|
| P1 сайдбар/топбар | `sections/{menu,sidebar}`, `components/{menu-item,sub-menu-item}`, `_sidebar.scss` | Секции Анализ/Операции/Администрация; подгрупи на „Финансов анализ“ по префикса на menu файла (05 Преглед, 10 Данни, 20 Справки, 30 Анализи, 40 Планиране, 50 Екипи) — заглавие само ако групата има видими пунктове; активно състояние (лента + фон, точка за подпункт, отворена група, longest-prefix съвпадение на URL); сгънат режим с tooltip/flyout; име на компанията на 2 реда; мобилно меню 300 px със затъмнен фон и Esc; клавиатура за групите |
| P2 layout | `components/{app-title,cards/*,filters/filter-box}`, `_layout.scss` | Фон, полета 24/16/12 px, карти (радиус 10, рамка, лека сянка), `x-cards.widget` със стойност в текстов цвят, filter bar, празни състояния (`x-cards.no-record` → `.dbk-empty`), preloader |
| P3 табла | `databackd/reports/dashboard/index`, `dashboard/**`, нов `dashboard/_dbk-chart.blade.php`, `_dashboard.scss` | KPI с `x-dbk.kpi` (стойност в текстов цвят, делта, sparkline), графики през `dbkChart` (combo, donut ≤6, хоризонтални ленти, паричен поток в две графики без втора ос); Frappe графиките на AFV таблата → `dbkChart` |
| P4 справки | `databackd/reports/**` (без dashboard), `_reports.scss` | Общ филтър (`.dbk-segmented` периоди), конструктор, ОПР, паричен поток (две графики вместо двойна ос), контрагенти, запазени — sticky header/първа колона, `.num/.neg`, зебра, групови/обобщени редове |
| P5 анализи | `databackd/analytics/**`, `_analytics.scss` | Карти на коефициентите (светофар + текст, gauge към целта, sparkline), детайл с коридор на целта, heatmap сравнение, консолидационна таблица със скрол и фиксирана колона, графики на приноса |
| P6 планиране | `databackd/planning/**`, `_planning.scss` | Списък бюджети, редактор като spreadsheet (sticky, групи, междинни суми, фокус на клетката), bullet графика бюджет vs реално, прогнози с коридор и сценарии |
| P7 екипи | `databackd/teams/**`, `_teams.scss` | Карти за размер на екипа, timeline на статусите, Kanban, карти на специалистите |
| P8 данни | `databackd/data/**`, `_data.scss` | Page header с 1 primary, KPI, stepper на импорта, интеграции като карти, дърво на сметкоплана, едноредов списък записи |
| P9 стандартни модули | `components/forms/label`, `_modules.scss` | DataTables списъци, бутони, полета (bootstrap-select/select2/datepicker), модали, табове, show страници — само CSS |
| P10 мобилен/достъпност | `_mobile.scss` | `:focus-visible` навсякъде, контраст AA на приглушения текст и значките, зони ≥40 px на ≤767 px, без хоризонтален скрол на страницата, модали на цял екран, `prefers-reduced-motion` |

## Поправки по пътя (извън чисто визуалното)
- `databackd/teams/analysis/index.blade.php` — `jquery-ui.min.js` не се зареждаше → drag & drop в Kanban не работеше (`$(...).sortable is not a function`). Добавен в `@push('scripts')`.
- `layouts/app.blade.php` — `<html lang>` от локала (беше `en`).
- Токени за контраст (по измерванията на P10): `--dbk-good #0b7a45`, `--dbk-warning #9a5b00`, `--dbk-accent-text #1a5fb8`.

## Преди / след
Снимки на 10 ключови страници (десктоп + мобилен): `docs/mvp/phase2/ui-before/` и `docs/mvp/phase2/ui-after/` (01 табло, 02 финансово табло, 03 записи, 04 конструктор, 05 ОПР, 06 коефициенти, 07 консолидация, 08 редактор на бюджет, 09 заявки за екип, 10 фактури; сайдбарът е на всяка). Снимките на пакетите — `storage/app/snapshots/p1…p10/` (локални, не са в git).

| Страница | Преди | След |
|---|---|---|
| Сайдбар | Плосък списък ~30 пункта, активният подпункт едва се вижда, име на компанията отрязано | Секции + подгрупи със заглавия, ясно активно състояние, името на 2 реда |
| Финансово табло | Стойностите на KPI в червено (цвета на темата) — и приходите; графики с локални цветове/формат; паричен поток с две оси | KPI в текстов цвят с делта и sparkline; зелено/червено/синьо по смисъл; формат `1 234,56 €`; паричният поток в две графики |
| Записи | Редове по 4–5 реда височина, бутони в различни стилове | Едноредови редове, суми вдясно, KPI плочки, 1 primary бутон |
| ОПР / паричен поток / конструктор | Таблиците се отрязват/скролират цялата страница | Скрол в картата, фиксиран header и първа колона, `.neg` в червено, зебра |
| Коефициенти | Карти само с цвят | Светофар + текст, gauge към целта, sparkline |
| Консолидация | Таблицата се отрязва | Хоризонтален скрол с фиксирана колона; графики на приноса |
| Редактор на бюджет | Обикновена таблица с полета | Spreadsheet: sticky месеци и сметки, групи, междинни суми |
| Заявки за екип | DataTable без контекст | KPI, бадж за статус, празно състояние |
| Фактури и др. модули | Стандартен AFV | Списък в карта, 44 px редове, меки статус значки, бутони/полета 38 px |

## Проверка
```bash
npm run prod
php -d memory_limit=2G docs/mvp/tools/smoke.php databackd. --ajax
php -d memory_limit=1G docs/mvp/tools/smoke.php - --url=/account/dashboard "--url=/account/dashboard-advanced?tab=finance" --url=/account/invoices --url=/account/clients
php docs/mvp/tools/snapshot.php /account/databackd/reports/dashboard
php artisan databackd:check-formulas && php artisan databackd:check-consolidation && php artisan databackd:check-forecasts
```
