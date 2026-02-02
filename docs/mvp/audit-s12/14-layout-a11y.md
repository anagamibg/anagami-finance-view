# S12 / A14 — общ layout, топбар, общи компоненти, мобилен, достъпност, тъмна тема, преводи (одит)

## Какво е проверено
- **Файлове:** `layouts/app.blade.php`, `sections/topbar.blade.php` (+ `sections/modals`, `sections/theme_css` — само прочетени), `components/{app-title,right-modal}`, `components/cards/{widget,no-record}`, `components/filters/{filter-box,more-filter-box}`, `components/forms/{label,button-primary,select,text}`, `components/dbk/{page-header,card,kpi,empty,chart}`, `resources/scss/databackd/{_tokens,_components,_layout,_modules,_mobile}.scss`, `public/vendor/froiden-helper/helper.js` (съобщения/toast — само прочетен).
- **Мобилен 390 px (`--only=mobile`), 12 страници:** табло, финансови записи, импорт, ОПР, конструктор (гъвкави справки), коефициенти, консолидация, бюджети, заявки за екип, интеграции, паричен поток, прогнози. Освен PNG-тата е мерен хоризонталният скрол (`scrollWidth` спрямо 390) и елементите, които излизат вдясно и се отрязват от `.main-container { overflow-x: clip }` (с копие на `snapshot.js` в scratchpad — инструментът в `docs/mvp/tools/` не е пипан).
- **Тъмна тема (`--dark`), 12 страници десктоп** + мобилен (записи, модал „Нова група“) + фактури (стандартен модул).
- **Модали:** модалът „Нова група“ (консолидация, `?create=1`) десктоп/мобилен/тъмен; дясният панел „Добави сметка“ (сметкоплан).
- **Клавиатура:** Tab от началото на страницата (skip link), Enter → фокус в съдържанието, Shift+Tab нагоре по дълга страница (интеграции) — фокусът не влиза под sticky топбара (top = 74 px при топбар 63 px).
- **Контраст:** изчислен за токените (светла/тъмна) и за цвета на темата.
- **Роли (smoke):** админ `databackd.` + `--ajax` — единствен FAIL `counterparties.show` без `?name=` (очакван); служител — само 403 по права (gaps margins/onboarding/alerts.create, както в handoff-ите); клиент — само 403 по права; core страници (табло, финансово табло, фактури, клиенти, проекти, разходи, задачи, профил) — 200; супер админ (табло, компании) — 200.

## Намерени и поправени грешки
| # | Файл | Проблем | Поправка |
|---|---|---|---|
| 1 | `layouts/app.blade.php` (след `sections.theme_css`) + `_modules.scss` | Цветът на темата `#ed4040` с бял текст е 3.88:1 (под AA) — бутони primary, значката с известия, `.text-primary`, активна страница в пагинацията, активен `.dropdown-item` | В layout-а се изчислява `--dbk-primary` = същият нюанс, затъмнен (×0.97 на стъпка) докато белият текст стигне 4.5:1 (за `#ed4040` → `#d73a3a`, 4.61:1); ако цветът минава, остава същият. В `_modules.scss` `body:not(.dark-theme) .btn-primary …` ползва `var(--dbk-primary, var(--header_color))` (специфичността бие `!important` правилата от `theme_css`). **Настройката на тенанта не се променя.** Тъмната тема запазва своя светъл primary. |
| 2 | `layouts/app.blade.php` | `document.loading` не се задаваше → froiden-helper показва английско „Loading...“ на бутона при изпращане, в blockUI и в празния модал | `document.loading = @json(__('app.loading'))` („Зареждане...“) |
| 3 | `layouts/app.blade.php` | Preloader-ът остава завинаги над страницата, ако `init()` хвърли грешка или `window.load` не дойде (описано в CLAUDE.md като изгубена поправка) | `try/catch` около `init()` + `setTimeout(…, 10000)` резервно скриване |
| 4 | `layouts/app.blade.php` | `<html lang="eng">` за английски — невалиден BCP 47 таг (екранните четци не сменят гласа) | `eng` → `en` (dbkChart продължава да разпознава `en`) |
| 5 | `layouts/app.blade.php` | Няма skip link (WCAG 2.4.1), няма `main` landmark | `<a class="dbk-skip-link">` първи в `<body>` → фокусира `#fullscreen` (`role="main"`, `tabindex="-1"`); обикновен `#` линк не местеше фокуса в Chrome — добавен click handler |
| 6 | `layouts/app.blade.php`, `_mobile.scss` | WCAG 2.4.11: при Shift+Tab/скрол фокусираният елемент може да е под sticky топбара (+ sticky филтър лентата на десктоп) | `--dbk-sticky-top` (измерва топбар + sticky `.filter-box`), `html { scroll-padding-top }` + `focusin` корекция само за клавиатурен фокус (`:focus-visible`), без елементи във fixed слоеве/модали/панели |
| 7 | `layouts/app.blade.php` | Модалите `#myModal*` нямат достъпно име (`aria-labelledby` не може — id `modelHeading` се повтаря 3 пъти) | при `shown.bs.modal` → `aria-label` = текста на `.modal-title` |
| 8 | `layouts/app.blade.php` | `prefers-reduced-motion` не спираше jQuery анимациите (fadeOut на preloader-а, slide, анимиран скрол) | `$.fx.off = true` при reduce |
| 9 | `layouts/app.blade.php` | На мобилен филтър лентата (до 8 полета) стоеше **над** заглавието на страницата | `x-app-title` (мобилното копие) е преди `@yield('filter-section')`; на ≥ 992 px нищо не се променя (заглавието е в топбара) |
| 10 | `sections/topbar.blade.php` | Хамбургерът е `div onclick` — не се достига с клавиатура, няма име; иконите търсене/бележки/таймер/създай/известия/изход нямат достъпно име (title е на `li`); dropdown тригерите са `<a>` без `href` → не са в Tab реда; `aria-labelledby="dropdownMenuLink"` сочи несъществуващ id; `tabindex="0"` на самите менюта | `role="button" tabindex="0" aria-label aria-controls` + Enter/Space на хамбургера; `aria-label` на всички икони (известията — „N непрочетени известия“); `href="javascript:;" role="button"` на тригерите; верни `aria-labelledby`; махнат `tabindex` на менютата |
| 11 | `components/filters/more-filter-box.blade.php` | „Филтри“ (`<a onclick>` без href) не се отваря с клавиатура | `href="javascript:;" role="button" aria-controls="more_filter"` |
| 12 | `components/right-modal.blade.php` | Бутонът × на десния панел — `<a>` без href и без име | `href="javascript:;" role="button" aria-label="Затвори"` |
| 13 | `components/dbk/kpi.blade.php`, `cards/widget.blade.php`, `forms/label.blade.php` | Иконката „?“ с обяснение работи само при hover (недостъпна от клавиатура/тъч) | `tabindex="0" role="img" aria-label="…"`, `data-trigger="hover focus"`; стрелката на делтата `aria-hidden` |
| 14 | `_components.scss` (`.dbk-page-actions`) | ОПР на 390 px: групата Excel/PDF/Печат е по-широка от екрана → отрязан текст „Експорт в Exc…“ (10 px извън екрана, скрити от clip); същата група има в паричен поток, контрагенти и конструктор (там етикетите са по-къси и се побират) | ≤ 575 px `.btn-group` в page header-а се пренася на редове, бутоните са отделни (gap 8, радиус) |
| 15 | `_layout.scss` | Страниците с `<x-dbk.page-header>` показваха заглавието **два пъти** на мобилен (мобилното копие + h1) | `:has()` — остава само редът с breadcrumbs над h1 (не и когато между тях има филтър лента) |
| 16 | `_layout.scss` | Филтърът показва стойностите в Title Case („Всички Юридически Лица“, „Октомври 2026“) — legacy `text-transform: capitalize` в `custom.scss` | `text-transform: none` за избраната стойност във филтър лентата; същото за select2 (`_modules.scss`) |
| 17 | `_layout.scss` | Тъмна тема ≤ 767 px: вертикални линии по филтрите (legacy `dark-theme.scss` налага `border-*` с `!important`) | премахнати в мобилния изглед на `.dbk-layout-filters`; етикетите на филтрите — една колона 112 px (дългите се пренасят) |
| 18 | `_tokens.scss` | `--dbk-accent #1d6fd6` е 4.44:1 върху фона на страницата `#f2f4f7` (линкове извън карти) | `#1a66c9` (5.0:1 на фона, 5.5:1 на бяло, 4.86:1 на `--dbk-accent-soft`); фокус пръстенът — същият нюанс |
| 19 | `_components.scss` | В `.dbk-card/.dbk-filters/.dbk-kpi` фокусът беше само пръстен 25 % (~1.5:1) | + 2 px outline в акцентния цвят (както глобалното правило в `_mobile.scss`) |

## UX подобрения
- **Модали на мобилен (≤ 575 px) на цял екран** (`_mobile.scss` §6): заглавието и бутоните са sticky, тялото скролира; малките потвърждения (`.modal-sm`, bootbox) остават карти. Старото правило в `_modules.scss` (margin 8 px) е махнато.
- **Модални/панелни форми — обща визия** (`_modules.scss`): `div.add-client.bg-white.rounded` (стандартната форма в дясния панел и на страница, 92 view-та) → карта с рамка/радиус 10/лека сянка, заглавие 15 px semibold вместо 21 px light; звездичката за задължително поле е на едно място и с `--dbk-bad` (5.3:1) за текстови полета и `x-forms.select`.
- Skip link — видим само при фокус, бял с подчертан текст (`_layout.scss`).
- Toast/SweetAlert съобщенията (froiden-helper) са с иконка **и** текст — не само цвят (проверено, без промяна).

## Нови SCSS partial-и и lang ключове
- Нови partial-и: **няма** (промените са в `_tokens`, `_components`, `_layout`, `_modules`, `_mobile` — вече импортирани в `main.scss`). Нужен е `npm run prod` (координаторът).
- Lang (`resources/lang/{bg,eng}/databackd-misc.php`): `skip_to_content` („Към основното съдържание“ / „Skip to main content“), `open_menu` („Отвори менюто“ / „Open menu“). Останалите използвани ключове съществуват (`app.search`, `app.menu.stickyNotes`, `modules.timeLogs.startTimer`, `app.createNew`, `app.newNotifications`, `ui-shared.unread_notifications`, `app.logout`, `app.loading`, `ui-shared.close`).

## Миграции
Няма.

## Одит на преводите
- `php docs/mvp/tools/lang-audit.php --strict` → **exit 1**, 1 critical: `databackd-ui.php :: menu_filter.shortcut_hint` (bg_latin — новият филтър на менюто, A01). Липсващи ключове eng↔bg: 0. Предупреждения (warn 439/660) — главни/малки букви по файлове (таблицата в изхода на инструмента; най-много в `permissions.php`, `modules.php`, `app.php`; при databackd: `databackd-data-dimensions` 21, `databackd-data-entries` 19, `databackd-gaps-alerts` 17, `databackd-data-integrations` 13, `databackd-reports-common` 13). `databackd-misc.php` — само 1 warn (`sheets.url_not_allowed` съдържа „https, docs.google.com“ — коректно).
- `php docs/mvp/tools/hardcoded-text.php --pkg=P7,P9 --list` (AFV view-та + layouts/sections/components): 35 попадения, реални са малко:
  - `databackd/analytics/builder/form.blade.php:248,250,256` — „DAYS“, „ANNUAL“, „AVG(...)“ (имена на функции във формулите — по-скоро умишлено; за A-области да решат);
  - `databackd/teams/analysis/show.blade.php:148` — `placeholder="https://… / /account/…"` (технически пример, ок);
  - `components/pie-chart.blade.php:47` — „Chart.js Pie Chart“ е `title.display:false`, не се вижда;
  - останалите са фалшиви (PHP условия в `menu.blade.php`/`setting-sidebar`, имена Google Play/App Store, JS шаблони).
- Допълнително търсене на кирилица извън `__()` в databackd/layout/components view-тата: 0 (само регулярни изрази за „лв.“).
- Статично сканиране на ключовете `__('…')` в AFV view-тата/контролерите срещу bg+eng: липсват само динамични префикси (`…func_`, `…trend_`, `…dimension_`, `…log_` — сглобяват се в кода, не са грешки). По време на одита „databackd-data-entries.totals.result“ се показа като ключ на екрана — ключът вече е добавен от собственика на областта.

## Мобилен 390 px — по страници
Хоризонтален скрол на страницата: **няма** на нито една от 12-те (scrollWidth = 390). Отрязано съдържание (скрито от `overflow-x: clip`): само ОПР (групата бутони — поправено, #14) и 3 px от `.row` в интеграции/прогнози (отрицателен margin на реда > полето 12 px — колоните са с padding 15, съдържанието не се реже; без промяна).

| Страница | Проблем | Чий е |
|---|---|---|
| Всички с филтър лента | филтрите бяха над заглавието; Title Case; линии в тъмна тема | поправено (#9, #16, #17) |
| Финансово табло, ОПР, конструктор, паричен поток | заглавието два пъти | поправено (#15) |
| ОПР | Excel/PDF/Печат отрязани | поправено (#14) |
| Справки (табло, ОПР, конструктор, паричен поток) | филтрите са дълъг списък от 6–8 полета преди съдържанието (вече има бутон „Филтри“ с брояч — сгъването да е по подразбиране на ≤ 575 px) | A-справки (`_reports.scss`, `reports/_filters`) |
| Коефициенти | „Експорт в Excel“ е primary, а „Сравнение“/„Конструктор“ — вторични; трите бутона са един под друг | A-анализи (`analytics/ratios/index.blade.php:112` → `link-secondary`) |
| Консолидация | 2 primary бутона („Експорт в Excel“ в header-а и „Покажи“ във филтъра) | A-анализи (`analytics/consolidation/index.blade.php:20` → `link-secondary`) |
| Импорт | 2 primary („Шаблон за Excel (.xlsx)“ в страничната карта и „Продължи към колоните“) | A-данни (`data/import/index.blade.php:105` → `link-secondary`) |
| Заявки за екип | таблицата на мобилен — клетките се пренасят на 3–4 реда (клиент, ангажимент); по-добре карти/скрити колони | A-екипи (`_teams.scss`, DataTable) |
| Записи | двата реда KPI плочки (4 карти една под друга) отнемат ~1 екран преди таблицата — 2 на ред | A-данни (`_data.scss`) |

## Тъмна тема
12 AFV страници (десктоп) + записи и модал (мобилен) + фактури: фонове, карти, таблици, KPI, графики, значки и бутони са четими; primary е светъл (`#d5d8df`, 11.7:1). Поправено: линиите по филтрите на мобилен (#17). Забележки извън моята област: бутонът „Мобилно приложение“ в долната част на сайдбара е тъмносин текст върху син фон (нисък контраст) — A01.

## Нужни промени извън моята област
1. **`npm run prod`** (координаторът) — всичко в SCSS е само в изходниците; без компилация не се виждат: тъмният primary, модалите на цял екран, skip link стиловете, мобилните поправки.
2. **A01 (сайдбар):** по време на одита (14:48) `sections/menu.blade.php` е с нова разметка, а `public/css/main.css` е от 09:30 → на десктоп подпунктовете на „Финансов анализ“ излизат като нестилизиран текст (вижда се на всички снимки) — ще се оправи с `npm run prod`, ако `_sidebar.scss` е готов. `lang-audit --strict` пада заради `databackd-ui.menu_filter.shortcut_hint` (bg_latin). Ако A01 иска значката с броя в менюто да ползва безопасния цвят: `.menu-item-count { background-color: var(--dbk-primary, var(--header_color)) !important }` в `_sidebar.scss` (само за `body:not(.dark-theme)`).
3. **`public/vendor/froiden-helper/helper.js`** (общ vendor, не е на агент): ред 81 и 85 — твърди английски съобщения „A server side error occurred…“ / „Connection timed out!…“ → да се подават през глобална променлива (както `document.loading`, напр. `document.serverError`) и да се зададат в layout-а; toast-ът изчезва след 3000 ms (`timer: 3000`, ред ~113) — за `type === 'error'` да е по-дълъг (≥ 8000 ms) или без таймер с бутон за затваряне (WCAG 2.2.1).
4. **`sections/modals.blade.php`** (не е изрично в моя списък — не е пипан): трите модала имат еднакъв `id="modelHeading"`. Ако JS се смени да ползва клас (`.modal-title`), могат да получат уникални id + `aria-labelledby`; дотогава името се задава от layout-а (`aria-label`, #7).
5. Страницно-специфичните точки от таблицата „Мобилен“ (A-справки, A-анализи, A-данни, A-екипи).

## Отворени въпроси / неправено
- На десктоп заглавието е и в топбара, и в `<x-dbk.page-header>` (h1) — решение на S9; не е променяно.
- `text-transform: none` на филтрите/select2: ако някой стар модул показва непреведени enum стойности с малка буква (напр. `pending`), сега ще се виждат така, както са в данните (преди — с главна).
- Не е тествано на реално устройство/екранен четец — само headless Chromium (снимки + `document.activeElement`).
- Снимки (локални, не са в git): `storage/app/snapshots/a14/{before-mobile,after-mobile,dark,misc}/`. Снимките „след“ са със `--scss=` на петте partial-а (без `npm run prod`).

## Пълен списък с променени/нови файлове
- `resources/views/layouts/app.blade.php`
- `resources/views/sections/topbar.blade.php`
- `resources/views/components/filters/more-filter-box.blade.php`
- `resources/views/components/right-modal.blade.php`
- `resources/views/components/dbk/kpi.blade.php`
- `resources/views/components/cards/widget.blade.php`
- `resources/views/components/forms/label.blade.php`
- `resources/scss/databackd/_tokens.scss`
- `resources/scss/databackd/_components.scss`
- `resources/scss/databackd/_layout.scss`
- `resources/scss/databackd/_modules.scss`
- `resources/scss/databackd/_mobile.scss`
- `resources/lang/eng/databackd-misc.php`
- `resources/lang/bg/databackd-misc.php`
- `docs/mvp/audit-s12/14-layout-a11y.md` (нов, този доклад)
