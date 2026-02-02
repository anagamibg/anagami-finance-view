# S12 / A01 — сайдбар и навигация

## Какво е проверено
- **Екрани (snapshot, десктоп 1440 + мобилен 390, светла и `--dark`, с `--scss=resources/scss/databackd/_sidebar.scss`):**
  `/account/databackd/reports/dashboard`, `/data/sources`, `/planning/budget-editor/26/edit`, `/analytics/ratios` (dark), `/teams/requests`, `/teams/analysis`, `/account/invoices` (Anagami Finance View група „Финанси“). PNG-та: `storage/app/snapshots/a01/*`.
- **Роли:** админ (`admin@demofinans.bg`) — всички групи; служител (`g.petrov@…`) — „Табло“ е директен линк, групите на AFV се виждат по модулите на пакета; клиент (`p.yordanov@…`) — само група „Екипи“ с 2 пункта (`config('databackd.client_menu')`), без празни групи.
- **Интерактивно (Playwright върху инструмента на snapshot):** отваряне/затваряне на групи и запомняне след презареждане; филтър (съвпадения, празен резултат, Esc, Enter, ↓/↑); Ctrl/⌘+K от сгънат режим (разгъва сайдбара и после го връща); flyout на група в сгънат режим; мобилно меню с филтър и Esc. Без JS грешки в конзолата.
- **Smoke** `smoke.php databackd. --ajax` като трите роли: няма нови FAIL от сайдбара. Наличните FAIL са чужди: непреведени ключове на текущата работа по „Данни“ (`databackd-data-*.…form_ux`, `keys_hint`, `cannot_delete_title`…), очакваният 302 на `counterparties.show`, 403 за клиента/служителя по права и пакет.

## Намерени и поправени проблеми
| # | Проблем | Причина | Поправка |
|---|---|---|---|
| 1 | Един акордеон „Финансов анализ“ с ~30 подпункта и 6 малки заглавия; дълъг скрол | Всички AFV пунктове бяха в един `x-menu-item` | `sections/menu.blade.php` (блок „AFV“): директни линкове **Финансово табло** (и **Начална настройка** докато съветникът е видим) + сгъваеми групи от 1-во ниво **Данни, Финансови справки, Анализи, Планиране, Екипи**, всяка с Bootstrap иконка (table, clipboard-data, graph-up-arrow, calendar-check, person-workspace). Подредбата е масив `$dbkMenuLayout` в началото на блока; непознати нови menu файлове отиват в група по числовия префикс (<20 данни … ≥50 екипи). Група без видими пунктове не се печата (буфериране на всеки include). |
| 2 | Групите не помнят състоянието; само една може да е отворена | `main.js toggleItem()` е акордеон и пренаписва `className` на всички `li.accordionItem` | Новите групи са `li.dbk-sb-group` (**не** `accordionItem`, за да не ги затваря/нулира `toggleItem()`), бутон `button.dbk-sb-group-toggle[aria-expanded][aria-controls]`. Състоянието е в `localStorage['dbk-sb-groups']` (try/catch); възстановява се от inline скрипт веднага след менюто (без примигване). Групата на текущата страница е винаги отворена. Legacy групите (Табло, Справки, HR, Работа, Финанси…) не са пипани. |
| 3 | „Редакторът на бюджет не маркира активен пункт“ (handoff 09) и други подстраници | Активният пункт се търси по префикс на URL, а `/planning/budget-editor/{id}/edit`, `/analytics/ratio/{x}`, `/data/sync` нямат линк-префикс в менюто | `components/sub-menu-item.blade.php`: атрибут `data-dbk-match="<път> …"` (допълнителни пътища, спрямо корена) + сървърно активно състояние по префикс за AFV пунктовете (show/edit/create страници). Използвано в `40-planning-budgets` (budget-editor), `30-analytics-ratios` (ratio/{x}), `15-data-integrations` (data/sync — отваря се от „Интеграции“). Скриптът в сайдбара взима предвид `data-dbk-match` при „най-дълъг префикс“. Проверено: budget editor → „Планиране › Бюджети“ активен, групата отворена. |
| 4 | Етикети на 2 реда | Дълги menu ключове | Само menu ключовете (заглавията на страниците са непроменени): „Google Sheets и източници“ → **Google Sheets** (eng „Google Sheets“), „Маржове по продукти и услуги“ → **Маржове по продукти** („Product Margins“), „Сравнение на юридически лица“ → **Сравнение на юр. лица**, „Конструктор на показатели“ → **Собствени показатели** („Custom Ratios“), „Първоначална настройка“ → **Начална настройка** (прогресът `3/5` вече е значка, не част от текста). Измерено в браузъра: нито един пункт не е на 2 реда. |
| 5 | Две „Справки“ в един раздел | Групата на AFV и Anagami Finance View „Справки“ (отчети за време, отпуски, продажби…) стоят една до друга | Групата на AFV е **„Финансови справки“** / „Financial Reports“ (`databackd-ui.menu_groups.reports`). |
| 6 | Филтърът/търсенето в менюто липсваше | — | Поле „Търсене в менюто“ над менюто (`sections/sidebar.blade.php`), Ctrl/⌘+K от всяка страница (на мобилен отваря менюто, в сгънат режим временно разгъва сайдбара и го връща при излизане от него). Филтрира всички пунктове (вкл. Anagami Finance View), показва групите със съвпадения отворени (съвпадение в името на групата показва всичките ѝ пунктове), скрива празните раздели, „Няма пункт с такова име“; Esc изчиства, второ Esc излиза (и затваря мобилното меню); Enter отваря първото съвпадение; ↓ от полето и ↑/↓ между видимите пунктове. Скриването е с атрибути (`data-dbk-hidden`, `data-dbk-filter-open`), не с класове — legacy `li` класовете остават точно `accordionItem openIt|closeIt`. |
| 7 | Mobile: полето би увеличавало страницата в iOS | font-size < 16 px | 16 px и 40 px височина под 992 px; без подсказката за клавиш на мобилен. |

**„Табло“ със стрелка:** при админ (или права `view_*_dashboard == 4`) „Табло“ е група с два пункта — „Лично табло“ (`/account/dashboard`) и „Разширено табло“ (`/account/dashboard-advanced`, табове проекти/клиенти/HR/тикети/финанси). Стрелката е коректна и е оставена (същият шеврон като новите групи); при служител/клиент е директен линк без стрелка.

## UX подобрения (по ui-guidelines §10 и §12)
- Максимум 2 нива; родителят на група е бутон, не линк; иконки 16 px, еднакъв стил.
- „Данни“: ежедневни действия (Финансови записи, Групово въвеждане, Импорт на данни, Google Sheets, Банкови извлечения, История на импортите), после малко подзаглавие **„Настройки“** и рядко ползваните (Сметкоплан, Разрези, Юридически лица, Валутни курсове, Интеграции) — без 3-то ниво.
- „Финансови справки“: Гъвкави справки, ОПР, Парични потоци, Справка по контрагенти, Маржове по продукти, Годишни отчети (НСС), Запазени справки (запазените — накрая).
- „Анализи“: Финансови показатели, Сравнение на юр. лица, Собствени показатели, Консолидация, Правила за консолидация, Известия и прагове.
- Активен пункт: сървърно + клиентско уточнение (най-дълъг префикс), `aria-current="page"`, групата отворена и маркирана, скрол до пункта при зареждане (вече съществуваше, сега и за новите групи).
- Сгънат режим: иконки + tooltip за директните линкове, flyout със заглавие и пунктовете за групите (същият механизъм, разширен за `li.dbk-sb-group`); полето за търсене е скрито в rail.
- Целият раздел „Анализ“ при админ се побира в 900 px височина без скрол (преди — ~30 реда).

## SCSS partial-и и lang ключове
- **SCSS:** само `resources/scss/databackd/_sidebar.scss` (вече импортиран в `main.scss`) — групи `dbk-sb-group*`, бутон-заглавие, филтър `.dbk-sb-filter*`, състояния при филтриране, rail/flyout и мобилни правила. Нужен е `npm run prod` от координатора.
- **JS:** без нов файл — скриптът остава inline в `sections/sidebar.blade.php` (както досега) + малък inline скрипт в края на AFV блока в `sections/menu.blade.php` (възстановяване на отворените групи преди първото изрисуване). Нищо за добавяне в `webpack.mix.js`/`layouts/app`.
- **Нови ключове (bg + eng):** `databackd-ui.menu_groups.data_settings` („Настройки“/„Settings“), `databackd-ui.menu_filter.{placeholder,label,clear,empty,shortcut_hint}`.
- **Променени ключове:** `databackd-ui.menu_groups.reports` („Финансови справки“/„Financial Reports“); menu ключове: `databackd-data-sources.menu` (bg+eng), `databackd-gaps-margins.menu` (bg+eng), `databackd-analytics-ratio-detail.menu_comparison` (bg), `databackd-analytics-builder.menu` (bg+eng), `databackd-gaps-onboarding.menu` (bg). `databackd-ui.menu_groups.overview` и `databackd-gaps-onboarding.menu_progress` вече не се ползват (оставени).

## Миграции
Няма.

## Нужни промени извън моята област
1. `resources/lang/bg/databackd-gaps-onboarding.php` ключ `…intro` (и eng): „…по-късно от менюто „Финансов анализ““ — такъв пункт вече няма. Предложение: „…по-късно от менюто вляво (раздел „Анализ“)“ / „…later from the menu on the left (Analysis section)“.
2. `docs/mvp/phase2/ui-guidelines.md` §10: „Групи … „Финансов анализ“ → Преглед (табло), Данни, Справки…“ да се обнови на новата структура: директен линк „Финансово табло“ + групи Данни / Финансови справки / Анализи / Планиране / Екипи от 1-во ниво, запомнени, с филтър (Ctrl/⌘+K).
3. Забелязано при снимките: в сгънат режим горе вляво се вижда непреведен ключ `d-misc.skip_to_content` (skip link от текущата работа на друг агент в `layouts/app`) — да се провери ключът/скриването му (`sr-only-focusable`).
4. (Съществуващо, не от S12) `layouts/app.blade.php` добавя `sidebar-toggled` по `localStorage['mini-sidebar']` и на мобилен; `main.js resiz()` го маха само при resize под 769 px. Ако на устройство < 992 px е записано „yes“, мобилното меню е тясно. Поправка: в inline скрипта на `layouts/app` да се добавя класът само при `window.innerWidth >= 992`.

## Отворени въпроси / неправено
- Legacy групите на Anagami Finance View остават акордеон (една отворена) — промяна би изисквала `main.js`.
- Етикетите в eng са проверени по дължина в кода (по-къси от bg), не със снимка на английски.
- Пунктът „Начална настройка“ (директен линк със значка `done/total`) не е виждан на снимка — в демо компанията съветникът е скрит; кодът ползва същия `x-menu-item` (`count`).
- Супер админ: полето за търсене се показва и в неговото меню (същият общ скрипт); не е снимано.

## Пълен списък с променени/нови файлове
- `resources/views/sections/menu.blade.php` — нов AFV блок (директни линкове + групи + inline възстановяване на състоянието)
- `resources/views/sections/sidebar.blade.php` — поле за филтър, празно състояние, нов inline скрипт (активно състояние с `data-dbk-match`, групи, филтър, Ctrl/⌘+K, клавиатура, flyout)
- `resources/views/components/sub-menu-item.blade.php` — `$attributes` върху линка, сървърно активно състояние по префикс + `data-dbk-match`
- `resources/views/databackd/menu/05-reports-dashboard.blade.php` — директен линк с иконка
- `resources/views/databackd/menu/06-gaps-onboarding.blade.php` — директен линк със значка за прогреса
- `resources/views/databackd/menu/15-data-integrations.blade.php`, `30-analytics-ratios.blade.php`, `40-planning-budgets.blade.php` — `data-dbk-match`
- `resources/scss/databackd/_sidebar.scss`
- `resources/lang/bg/databackd-ui.php`, `resources/lang/eng/databackd-ui.php`
- `resources/lang/bg/databackd-data-sources.php`, `resources/lang/eng/databackd-data-sources.php` (само `menu`)
- `resources/lang/bg/databackd-gaps-margins.php`, `resources/lang/eng/databackd-gaps-margins.php` (само `menu`)
- `resources/lang/bg/databackd-analytics-builder.php`, `resources/lang/eng/databackd-analytics-builder.php` (само `menu`)
- `resources/lang/bg/databackd-analytics-ratio-detail.php` (само `menu_comparison`)
- `resources/lang/bg/databackd-gaps-onboarding.php` (само `menu`)
- `docs/mvp/audit-s12/01-sidebar.md` (този доклад)

Непроменени: `components/menu-item.blade.php`, `AppServiceProvider.php`, `app/Helper/databackd.php`.
