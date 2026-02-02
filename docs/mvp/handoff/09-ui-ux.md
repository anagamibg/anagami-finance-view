# Handoff S9 → S7, S10: UI/UX

## Статус
**Готово.** Дизайн система (токени, компоненти `dbk-*`, `x-dbk.*`, глобален `dbkChart`), групиран сайдбар, всички AFV екрани и AFV таблата/модулите са по `docs/mvp/phase2/ui-guidelines.md`; `npm run prod` компилира. Проверено със `smoke.php` и със снимки от headless Chromium (`snapshot`), не с ръчно кликане в браузър.

## Какво е направено
Подробно: `docs/mvp/features/09-ui-ux.md`. Накратко:
- **Насоки:** `docs/mvp/phase2/ui-guidelines.md`; brief за агентите `docs/mvp/phase2/ui-agent-brief.md`.
- **Инструмент:** `docs/mvp/tools/snapshot.{php,js}` (PNG 1440/390 + HTML, без сървър, `--scss=` за преглед на partial без npm). Описан в `COMMON-RULES-2.md` §1.
- **Основа:** `resources/scss/databackd/_tokens.scss`, `_components.scss`, 10 partial-а `_{sidebar,layout,dashboard,reports,analytics,planning,teams,data,modules,mobile}.scss` (импортирани в края на `resources/scss/main.scss`); `resources/js/databackd-charts.js` (API в началото на файла); Blade `resources/views/components/dbk/*`; `resources/lang/{eng,bg}/databackd-ui.php`.
- **Глобално в `layouts/app.blade.php`:** `Chart.min.js` + `js/databackd-charts.js` на всяка страница; `?v=filemtime` на main.css/main.js/databackd-charts.js; `<html lang>` = локала.
- **Сайдбар** (`sections/menu|sidebar`, `components/menu-item|sub-menu-item`): секции Анализ/Операции/Администрация, подгрупи на „Финансов анализ“ по числовия префикс на `databackd/menu/NN-*.blade.php` (05–09 Преглед, 10–19 Данни, 20–29 Справки, 30–39 Анализи, 40–49 Планиране, 50–59 Екипи; **60+ няма заглавие на група** → попадат в последната видима група, виж по-долу).
- **Екрани:** всички view-та под `resources/views/databackd/{reports,analytics,planning,teams,data}/**` и `resources/views/dashboard/**` (нов partial `dashboard/_dbk-chart.blade.php` вместо Frappe компонентите).
- **Поправка:** Kanban на заявките за анализ — липсващ `jquery-ui.min.js` (drag & drop не работеше и на production).
- **Снимки:** `docs/mvp/phase2/ui-before/` и `ui-after/` (10 страници × десктоп/мобилен).

## Отклонения от промпта
- Не е добавен отделен компонент за breadcrumbs — те са в топбара (`x-app-title`), P1 ги стилизира (малък ред над заглавието).
- Цветовете приход/разход зелено/червено не минават прага за далтонисти (deutan ΔE 5.8) — затова навсякъде има легенда, фиксиран ред на сериите и tooltip; делтите са със стрелка и знак.
- Графиките с две оси (паричен поток на таблото и в справката) са разделени на две графики (правило „една ос Y“).
- `public/css/main.css` нарасна от ~400 KB на ~556 KB (минифициран) заради новите стилове.

## Не е направено / известни проблеми
- **4 файла не са в commit-а на S9**, защото съдържат и незавършената работа на S8 (шаблони за импорт, зависят от некомитнати класове/преводи на S8): `resources/views/databackd/data/import/{index,mapping,_mapping}.blade.php`, `resources/views/databackd/data/integrations/index.blade.php`. В тях са и визуалните промени на S9 (P8) — **S8 трябва да ги commit-не** заедно със своите (или S10, ако S8 не го е направил).
- Новите menu файлове на S8 с префикс 6x (напр. `61-gaps-bank-import`) нямат група в сайдбара → показват се под „Екипи“. Решение (S8/S10): префикс в 05–59 по смисъл или нова група в `sections/menu.blade.php` (масивът с групите е в началото на AFV блока, маркиран `databackd ui`).
- Редакторът на бюджет (`/planning/budget-editor/{id}/edit`) не маркира активен пункт в сайдбара (URL-ът не започва с линк от менюто).
- Коефициентите показват `1.60` (точка) — форматът идва от `RatioCalculator::format()` (S4), не от view-то.
- На мобилен филтрите на справките са дълъг списък (до 8 полета) — сгъване би изисквало нов текст и JS.
- Колоните в списъка „Финансови записи“ са стилизирани по позиция (`td:nth-child(5|7)`) — при промяна на колоните в `FinEntriesDataTable` да се добави `className` (`dbk-col-account`, `dbk-col-amount`).
- Статусите във фактурите (`InvoicesDataTable`) са точка + текст, не значка — за значка трябва промяна в PHP DataTable класа.
- Модалните форми (създаване на запис, сметка, разрез, юр. лице, правила) не са преработени — само общия вид на модалите (P9).
- Цветът на темата на компанията `#ed4040` с бял текст е 3.9:1 (под AA) за `.btn-primary` — настройка на тенанта, не е пипана.
- `CLAUDE.md` твърди, че `layouts/app.blade.php` съдържа preloader `try/catch` и инжектиране на `custom_css/custom_js` — **в локалното копие ги няма** (`custom_css` е само в `super-admin/layouts/*`). Не е променяно; S10 да провери сървърното копие преди да качи `layouts/app.blade.php`.

## За S7 (превод)
- Нов lang файл `databackd-ui.php` (eng + bg) — заглавия на групите в сайдбара, `no_data`, `other`, `vs_previous`, `loading`, `scroll_hint`. `dbkChart` има твърди „Няма данни за избрания период“ / „Други“ (bg) и английски вариант по `<html lang>` — може да ги подаваш през опцията `emptyText` или `dbkChart.topN(items, n, otherLabel)`.
- Иконните бутони в „Запазени справки“ имат текста в `title` + `.sr-only`.
- Видими на английски заглавия от контролерите (`pageTitle` в конструктора преди локала): `CounterpartyReportController`, `SavedReportController`, `TeamRequestController`, `SpecialistController`, „Financial dashboard“, „Budget editor“, „Financial ratios“. Липсващи bg ключове: `databackd-teams-requests.title`, `databackd-teams-specialists.title`.
- Пунктове в сайдбара с малка буква (`клиенти`, `работа`) — от bg преводите на AFV.

## За S10: какво да се деплойне
**Файлове (всички от commit-а на S9 + компилираните):**
- `public/css/main.css`, `public/css/main.css.map`, `public/js/main.js`, `public/js/main.js.map` (ако е генериран), `public/js/bootstrap.js`, `public/js/bootstrap.js.map`, **`public/js/databackd-charts.js`** (нов), `public/mix-manifest.json` (в `.gitignore` — качи го ръчно; приложението не го чете, `mix()` не се ползва).
- `resources/views/layouts/app.blade.php`, `resources/views/sections/{menu,sidebar}.blade.php`, `resources/views/components/**` (вкл. новата папка `components/dbk/`), `resources/views/dashboard/**`, `resources/views/databackd/{reports,analytics,planning,teams,data}/**`.
- `resources/lang/eng/databackd-ui.php` и **`resources/lang/bg/databackd-ui.php`** (bg е в `.gitignore` — качи ръчно).
- `resources/scss/**`, `resources/js/databackd-charts.js`, `webpack.mix.js` — само изходници (не са нужни на сървъра, но не пречат).
- `docs/` не се качва.

**Миграции / seeders:** няма.
**Команди след деплой:** `php artisan view:clear` (нови Blade компоненти `x-dbk.*`); кешовете на config/routes не са засегнати. Браузърите взимат новите assets заради `?v=filemtime` — rsync да запази/обнови mtime на `public/css/main.css`, `public/js/main.js`, `public/js/databackd-charts.js`.
**Рискове:** (1) `main.css` се прекомпилира изцяло — сравнението с предишния build (dev режим) показа само whitespace разлики, но провери визуално логин страницата и супер админ панела (не са снимани); (2) `Chart.min.js` вече се зарежда на всяка страница (+~200 KB, кешира се); страници извън AFV, които го включват повторно, продължават да работят (`dbkChart` прилага темата при всяко извикване); (3) тъмната тема е проверена само на няколко екрана.

## Какво трябва да знае следващата сесия
- Нов екран → `<x-dbk.page-header>`, `<x-dbk.kpi>`, `.dbk-card`, `.dbk-table` (+ `.num`, `.neg`), `<x-dbk.empty>`, графики само с `dbkChart.*`; **не** включвай `Chart.min.js` във view-то. Стилове — в съответния partial `resources/scss/databackd/_*.scss`, после `npm run prod`.
- Не пипай `li` класовете `accordionItem openIt|closeIt` в менюто — `main.js toggleItem()` сравнява целия `className`.
- Снимка на екран без сървър: `php docs/mvp/tools/snapshot.php <url>` → `storage/app/snapshots/…`.

## Как да се провери
```bash
npm ci && npm run prod
php -d memory_limit=2G docs/mvp/tools/smoke.php databackd. --ajax          # единствените FAIL: counterparties.show без ?name= (очакван), преводите camt053/mt940 на S8
php -d memory_limit=1G docs/mvp/tools/smoke.php - --url=/account/dashboard "--url=/account/dashboard-advanced?tab=finance" --url=/account/invoices --url=/account/clients --url=/account/projects --url=/account/expenses
php artisan databackd:check-formulas && php artisan databackd:check-consolidation && php artisan databackd:check-forecasts
php docs/mvp/tools/snapshot.php /account/databackd/reports/dashboard --dark
```
Служител/клиент: 403 на модулите на AFV (фактури, клиенти…) и на модулите извън пакета са отказ по права/пакет в контролерите, не от S9.

## Локални файлове извън git
`resources/lang/bg/databackd-ui.php`, `public/mix-manifest.json`, `node_modules/` (след `npm ci`), `storage/app/snapshots/**` (снимките на пакетите P1–P10), `.env`, `.env.stage.backup`, `.claude/`, `Modules/`.
