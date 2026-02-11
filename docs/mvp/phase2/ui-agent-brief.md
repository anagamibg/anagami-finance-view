# S9 — общ brief за агентите на пакетите (P1–P10)

Проект: Anagami Finance View (AFV (Ð±Ð°Ð·Ð°: Froiden) 6, Laravel 12, Blade + Bootstrap 4 + jQuery, Chart.js 3.5). Папка: `/Users/tsvet/Documents/Projects/databackd.com/databackd.com - app`. UI е на български. Цел на S9: интерфейсът да изглежда като модерен финансов SaaS — подреден сайдбар, ясна йерархия, еднакви карти/таблици/форми, красиви и четими графики, добре и на мобилен.

## Прочети преди да започнеш
1. `docs/mvp/phase2/ui-guidelines.md` — **задължителните** правила (палитра, типография, KPI, графики, таблици, мобилен).
2. `resources/scss/databackd/_tokens.scss` (CSS променливи `--dbk-*`, вкл. тъмна тема `body.dark-theme`) и `resources/scss/databackd/_components.scss` (готови класове: `dbk-page-header`, `dbk-card` (+`-header/-title/-subtitle/-actions/-body/-footer`), `dbk-grid` + `dbk-span-N`, `dbk-kpi-grid`, `dbk-kpi`, `dbk-delta.is-good|is-bad`, `dbk-chart` (+`--xs|sm|lg|xl`), `dbk-chart-empty`, `dbk-legend`, `dbk-segmented`, `dbk-tabs`, `dbk-filters` (+`-row`, `dbk-filter`, `-actions`), `dbk-chip`, `dbk-badge.is-good|is-warning|is-bad|is-info`, `dbk-traffic`, `dbk-progress`, `dbk-table-wrap` (+`--scroll`, `is-flush`), `dbk-table` (+`--compact|--striped|--sticky|--sticky-first`, клетки `.num`, `.neg`, `.pos`, редове `.dbk-row-group`, `.dbk-row-total`), `dbk-empty`, `dbk-skeleton`, `dbk-loader`, `dbk-stepper` (`li.is-active|is-done`), `dbk-dl`, `dbk-num`, `dbk-text-good|bad|muted`).
3. Blade компоненти (`resources/views/components/dbk/`): `<x-dbk.page-header :title :subtitle>` (+ `<x-slot name="actions">`), `<x-dbk.card :title :subtitle body-class>` (+ слот `actions`, `footer`), `<x-dbk.kpi :label :value icon tone change change-unit good hint info spark-id value-id>`, `<x-dbk.empty icon :title :text>`, `<x-dbk.chart id size label>`. Прочети ги — кратки са.
4. `resources/js/databackd-charts.js` — глобален `dbkChart` (вече се зарежда на всяка страница от `layouts/app` заедно с `Chart.min.js`): `dbkChart.bar(el, {labels, datasets:[{label, data, role:'revenue'|'expense'|'profit'|'cash'|'budget'|'forecast'|'neutral' или color}], stacked, horizontal, unit:'money'|'number'|'percent'|'ratio'|fn, legend, onClick, options})`, `line(el, {labels, datasets, area, unit})` (dataset: `dashed`, `area`, `points`, `fill`), `combo(el, {labels, bars, lines, unit})`, `donut(el, {labels, data, colors, unit, center:{value,label}})`, `sparkline(el, {data, labels, role|color, type:'line'|'bar'})`, `create(el, chartJsConfig)` (произволен Chart.js config с темата), `empty(el, text)`, `destroy(el)`, `topN(items, n)`, `color(i)`, `role(name)`, `alpha(hex,a)`, `fmt.money|number|percent|compact|compactMoney|signed|axis`. Всеки helper заменя графика, която вече е на същия canvas, и сам показва празно състояние при липса на данни (`emptyText`).
5. Handoff-ите на сесиите, които са писали твоите екрани: `docs/mvp/handoff/0*.md` (само релевантните).

## Задача на всеки пакет
- Приложи насоките на своите екрани: page header, карти, KPI, таблици, филтри, празни състояния, мобилен.
- **Всички графики през `dbkChart`** — пренапиши `new Chart(...)` / Frappe графиките в своите view-та към helper-ите; махни локалните `<script src="…Chart.min.js">` (вече са глобални), локалните палитри и форматиращи функции.
- **Текстовете не ги пипаш** (S7 ги превежда и уеднаквява главните букви след нас): не сменяй, не добавяй и не трий `__()`/`@lang` ключове и твърди текстове; можеш да местиш съществуващи текстове в нова разметка. Ако абсолютно ти трябва нов текст — ползвай `__('databackd-ui.*')` (виж `resources/lang/eng/databackd-ui.php`) или го опиши в отчета.
- **Не пипай логиката:** контролери, routes, заявки, JS поведение (AJAX, форми, събития, id-та, `name=` атрибути, data-атрибути, които JS чете) остават същите. Сменяш разметка, класове, стилове и кода на графиките.
- Стилове: само в своя partial `resources/scss/databackd/_<пакет>.scss` (вече е включен в `main.scss`). Ползвай CSS променливите `--dbk-*`, не hex. Без SCSS променливи/миксини от Bootstrap (partial-ът се компилира и самостоятелно); media queries с числа (`max-width: 575.98px`, `767.98px`, `991.98px`, `1199.98px`). Inline `@push('styles')` в своите view-та е позволен само за специфични за страницата неща — предпочитай partial-а. Префикс за нови класове: `dbk-<пакет>-…`.
- `_components.scss`, `_tokens.scss`, `databackd-charts.js` и `components/dbk/*` са **на S9 (оркестратора)** — не ги редактирай; ако ти липсва нещо там, опиши го в отчета (може да го заобиколиш временно в своя partial).

## Как виждаш резултата (без сървър, без npm)
```bash
php docs/mvp/tools/snapshot.php <url> --scss=resources/scss/databackd/_<пакет>.scss --out=<твоят scratchpad>/<име>
```
→ `<име>-desktop.png` (1440 px, цялата страница) и `<име>-mobile.png` (390 px) + списък с PHP/JS грешки. **Read на PNG-тата** — гледай ги. Опции: `--only=desktop|mobile`, `--viewport` (само видимата част), `--email=` (служител `g.petrov@demofinans.bg`, клиент `p.yordanov@balkan.bg`), `--dark` (тъмна тема). `--scss=` компилира partial-а ти и го инжектира (без `npm`); `main.css` вече съдържа токените и компонентите. Направи снимки **преди** (в началото) и **след** на своите основни страници и ги сравни.

Проверка, че нищо не е счупено: `php -d memory_limit=1G docs/mvp/tools/smoke.php <prefix> --ajax [--url=...]` + `php -l` не е нужен за Blade, но `php artisan view:cache` не пускай; вместо това smoke рендира view-тата.

## Правила за агенти (от COMMON-RULES, дословно с корекциите на фаза 2)
> - Пипаш само файловете от своя списък. Ако ти трябва промяна извън него (нова колона, метод в споделен service, чужд файл) — **не я прави**; опиши я точно във финалния си отчет.
> - Не пускай: сървъри, `migrate*`, `composer`, `npm`/`npx mix`, `config:cache`/`optimize*`/`cache:clear`/`view:clear`, `git add/commit/stash/checkout/reset`. Не редактирай `.env`.
> - Можеш да пускаш: `php -l <файл>`, `php artisan route:list --name=<prefix>`, `php artisan tinker --execute="..."` (само четене), `php docs/mvp/tools/smoke.php ...`, `php docs/mvp/tools/snapshot.php ...`, `node_modules/.bin/sass` за проверка на partial-а.
> - UI текстове — не се пипат (виж по-горе).
> - Копирай стила от насоките и ползвай компонентите `x-dbk.*` и класовете `dbk-*`.
> - Свършил си, когато smoke проверката за твоите routes минава (без нови FAIL), снимките „след“ изглеждат по насоките на десктоп и мобилен, и няма нови JS грешки в изхода на snapshot.
> - Финален отчет (кратък): променени файлове; какво е подобрено по екрани; пътищата до снимките „преди/след“ (2–4 най-показателни); резултат от smoke; какво не е направено; нужни промени извън твоите файлове (точно: файл, какво, защо).

Работиш паралелно с другите 9 пакета в същата папка (и с други сесии — S6 сигурност, S8 пакети). Не пипай чужди файлове, дори да виждаш проблем в тях — опиши го.
