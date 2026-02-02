# Handoff S7 → S10: превод на приложението (BG / EN)

## Статус
**Готово.** Всички текстове в интерфейса (без супер админ панела, който е по желание) са на български и английски; `php docs/mvp/tools/lang-audit.php --strict` — 0 критични (0 липсващи ключове, 0 несъответствия на плейсхолдъри, 0 забранени термини, 0 малки букви/латиница в ключове на менюта и заглавия). Остават предупреждения, оправдани поотделно (марки, технически токени, продължения на изречение след плейсхолдър). Локален commit, без push/деплой.

## Какво е направено
Подробно и речникът BG/EN: `docs/mvp/features/07-translation.md`. Накратко:
- **`bg` е в git** (`.gitignore` пази другите езици изключени); `CLAUDE.md` и `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` са обновени (при upstream merge `bg/` не се презаписва). Production `bg/` беше подмножество на локалния — нищо не липсваше локално; обединено е локално + новите `databackd-*` файлове.
- **Lang файлове:** 60 файла × 2 езика, 9 938 ключа във всеки; bg е допълнен (≈380 липсващи ключа: `app`, `messages`, `modules`, `email`, `permissions`; нови `clan.php`, `cookie.php`), поправени са малките букви (сайдбар: „Клиенти, HR, Работа, Проекти…“), Title Case, буквалните преводи (Помощ, Длъжности, Разходи, Кредитни известия, Банкови сметки, Лично табло, Разширено табло, Справки) и обръщението „Вие“. eng: Title Case за менюта/заглавия, sentence case за бутони/съобщения; поправени правописни грешки и плейсхолдъри (напр. липсващ `:dimension` в eng).
- **Твърд текст → `__()`:** ≈400 views, 68 PHP файла (контролери, DataTables, Notifications, услуги), 4 нови lang файла (`databackd-misc`, `ui-core`, `ui-shared`, `ui-misc`). `php docs/mvp/tools/hardcoded-text.php` остава само с фалшиви попадения (код, JS шаблони с `@lang`, марки) и с `super-admin/`.
- **Заглавия на страници на AFV** (`pageTitle` в конструктора излизаше на английски): 39 контролера го задават в `$this->middleware(...)` след `parent::__construct()`; `components/app-title.blade.php` превежда `$pageTitle`; breadcrumb ключовете за URL сегментите на AFV са в `app.php` (изтрит е workaround-ът `registerBreadcrumbLabel()` и мъртвият блок `databackd-ui.breadcrumb`).
- **PDF шаблони:** български етикети (Получател, Доставчик, Данъчна основа, ЕИК/ДДС №, Дължима сума…) през `ui-shared.pdf.*`; логиката и guard-ът `locale != 'bg'` (CUS_000328) са непроменени.
- **Формати:** `public/i18n/bg.json` (DataTables) поправен; `moment.locale()` се задава в `layouts/app.blade.php`; `app.to` = „до“ (разделител в daterangepicker); Carbon дава български месеци/дни; графиките ползват `Intl` `bg-BG` (`1 234,56 €`).
- **Инструменти:** `docs/mvp/tools/lang-audit.php`, `docs/mvp/tools/hardcoded-text.php`, `smoke.php --dump=<папка>` (HTML + видим текст на всяка страница).
- Приведени към NSS/речника термините на Финансов анализ: „показатели“ (не „коефициенти“), „Парични потоци“, „Бюджет спрямо реално“, „Разходен център“, „Годишни отчети (НСС)“.

## Отклонения от промпта
- Новите ключове за твърд текст не са в `app.php`/`modules.php`, а в 4 нови файла (по един на пакет), за да няма конфликти при паралелна работа.
- `modules.php` (2 382 ключа) е записан наново от скрипт: стойностите са сменени, но **форматирането на `eng/modules.php`, `eng/email.php` и `eng/validation.php` се различава от оригинала** (само стил) — при upstream merge на тези файлове очаквай голям diff; сравнявай по ключове (`lang-audit.php`).
- Не е правен `npm run prod`: в `resources/js/**` няма видим текст за превод (`databackd-charts.js` ползва `<html lang>` и вече е двуезичен).
- Файловете в `Modules/*/Resources/lang` (LanguagePack bg) са преведени локално, но `Modules/` е извън git.

## Не е направено / известни проблеми
- **Супер админ панелът** (`resources/views/super-admin/**`, `app/Http/Controllers/SuperAdmin/**`, `app/Notifications/SuperAdmin/**`, `eng/superadmin.php` няма bg) — по желание, не е правен.
- **Данни, не lang:** формат на валутата и датата са настройки на компанията (`companies`, `currencies`). Без тях сумите в ядрото излизат „€2,028.00“, не „2 028,00 €“. Локалното демо (компания 4) е променено; **на production трябва същото за компания 17 (и 4)** — виж „За S10“.
- **Демо данни и seed на английски:** `analytics demo adjust` (маркер на seeder 43), мерна единица „Pcs“, роля „App Administrator“, колони на таблото със задачи („Doing“, „Waiting Approval“, „Completed“), `invoice_terms` „Thank you for your business.“ — превод само при показване или seed на bg.
- **Двуезичен PDF (CUS_000328):** известният бъг (рендира BG два пъти вместо EN + BG) не е пипан: първият блок ползва езика на потребителя, а не `$invoiceSetting->locale`.
- Дните в таблото при `eng` излизат на български (`company->locale`); `GlobalSetting::getDaysOfWeek()` дава „вто, сря, чет“ (Carbon) — ако се предпочита „пн вт ср чт пт сб нд“, смени там.
- Английски текстове в машинни/технически изходи, които съзнателно не са превеждани: JSON отговорите на публичния `Api/FinEntryApiController`, вътрешни `InvalidArgumentException`, `CustomModuleController`/webhook съобщения, инсталатор и лицензиране на CodeCanyon (`vendor/installer/*`, `froiden-envato/install_message`…), updater (`update_script`, `update_module`).
- Пре-съществуващи FAIL в smoke (не са от превода): `dashboard.beam_auth` (401), `databackd.reports.counterparties.show` без `?name=` (302), `private_calendar` за клиент (500, `DashboardController:314`), `*.create` без метод в контролера (gdpr, app-settings, profile-settings…), `timelogs.export` без параметри, `invoices.committed_modal` (липсва `Modules\Purchase`).
- Неизползвани/странни ключове не са чистени (изтрити са само `databackd-reports-pnl.notes.entries_link` и `databackd-ui.breadcrumb`).

## Какво трябва да знае следващата сесия
- Ключ в `bg` без съответен в `eng` (и обратно) е критичен за `lang-audit.php --strict`; пускай го преди всеки commit, който пипа `resources/lang`.
- Липсващ bg ключ се вижда на английски (резервният език е `eng`) — затова одитът е задължителен.
- Заглавие на страница = ключ-низ или `__()` в middleware след `parent::__construct()`, не `__()` в конструктора.
- `app.menu.proposal`/`app.menu.bankaccount` са единствено число (етикети в формуляри); менюто ползва `app.proposals`/`app.bankaccounts`.
- Речник и правила: `docs/mvp/features/07-translation.md` — ползвай го и за бъдещи преводи.

## Как да се провери
```bash
php docs/mvp/tools/lang-audit.php --strict                                 # 0 критични
php docs/mvp/tools/hardcoded-text.php                                      # само фалшиви попадения + super-admin
php -d error_reporting=0 -d memory_limit=2G docs/mvp/tools/smoke.php databackd. --ajax   # единствен FAIL: counterparties.show
php -d error_reporting=0 docs/mvp/tools/smoke.php dashboard --dump=/tmp/dump               # /tmp/dump/dashboard.txt = сайдбарът
php artisan databackd:check-formulas && php artisan databackd:check-consolidation && php artisan databackd:check-forecasts
```
Очакван сайдбар (админ): Табло (Лично табло, Разширено табло), Финансов анализ (Преглед, Данни, Справки, Анализи, Планиране, Екипи), Справки, Клиенти, HR (Служители, Длъжности, Отдели…), Работа (Договори, Проекти, Задачи, Отработено време), Финанси (Фактури, Плащания, Кредитни известия, Разходи, Банкови сметки), Продукти, Настройки, Помощ.
Визуално (PNG в scratchpad на S7, не са в git): сайдбар и 6 страници (Табло, Финансово табло, Финансови записи, Показатели, Бюджети, Фактури) на BG и EN — `php docs/mvp/tools/snapshot.php <url> --only=desktop --viewport`; езикът се сменя с `users.locale` (`bg`/`eng`) на демо админа; той е върнат на `bg`.

## Локални файлове извън git
`Modules/LanguagePack/Resources/lang/bg/{app,messages}.php` (преведени; `Modules/` е gitignored), `storage/app/snapshots/**`, `.env`, `.env.stage.backup`, `.claude/`.

## За S10: какво да се деплойне
**Файлове (всички от commit-а на S7; пълен списък: `git show --stat HEAD`):**
- **Целият `resources/lang/bg/**` (60 файла) и `resources/lang/eng/**`** — `bg/` вече е в git, но **на production `bg/` е на ръка и по-стар (45 файла)**: качи цялата папка, не само променените (`rsync -rlt resources/lang/bg/ databackd:public_html/resources/lang/bg/`; същото за `eng/`). Нови: `databackd-misc.php`, `ui-core.php`, `ui-shared.php`, `ui-misc.php`, `bg/clan.php`, `bg/cookie.php`.
- `resources/views/**` (≈315 файла: layouts, sections, components, PDF, имейли, всички feature views, `databackd/**`), `app/Http/Controllers/**` (вкл. 39 AFV), `app/DataTables/{ProjectsDataTable,KnowledgeBaseDataTable,NoticeBoardDataTable}.php`, `app/Notifications/**`, `app/Services/Databackd/Data/{FinEntryImporter,GoogleSheetsReader}.php`, `public/i18n/bg.json`.
- Не се деплойват: `docs/**`, `CLAUDE.md`, `.gitignore`.
- **Миграции / seeders:** няма.
- **Файлове на други сесии, които този commit разчита, че са качени:** `app-title.blade.php`, `layouts/app.blade.php` и сайдбара са и на S9 (S7 ги променя след S9) — деплойвай ги от HEAD, не от по-стара версия; `layouts/app.blade.php` на сървъра може да е различен (CLAUDE.md твърди, че съдържа `custom_css/custom_js` и preloader `try/catch`, а локалното копие ги няма — провери сървърното преди да го презапишеш).

**Данни на production (след backup; само за компании 17 и 4, не пипай чужди):**
```sql
-- формат на валутата (разделител на хиляди = NBSP U+00A0) и дати (локалното демо вече има d.m.Y — провери първо със SELECT); след промяна — излизане и повторен вход (currency_format_setting() е в сесията)
UPDATE currencies SET currency_position='right_with_space', thousand_separator=CONVERT(CHAR(194,160) USING utf8mb4), decimal_separator=',', no_of_decimal=2 WHERE company_id IN (17,4) AND currency_code='EUR';
UPDATE companies SET date_format='d.m.Y', date_picker_format='dd.mm.yyyy', moment_format='DD.MM.YYYY', time_format='H:i', timezone='Europe/Sofia', locale='bg' WHERE id IN (17,4);
```
(Имената на колоните са проверени в локалната схема. Потребителите трябва да са с `users.locale='bg'`.)

**Команди след деплой:**
```bash
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:clear
```
(Преводите не се кешират, но компилираните views — да; `route:clear && route:cache` след последния rsync.)

**Проверка на production:** влез като админ на демо компанията (17) и (4): сайдбарът, „Финансов анализ → Финансово табло“, фактура PDF, дати `дд.мм.гггг`, суми `1 234,56 €`.

**Рискове:** (а) `bg/` на сървъра се презаписва изцяло — ако там има ръчни правки, които липсват в git (production беше подмножество на локалното към 2026-10-28), ще се загубят; снеми копие (`cp -a resources/lang/bg ~/lang-bg-backup-<дата>`) преди rsync; (б) формат на валута/дата е данни на компания, не код — без SQL по-горе сумите остават „€1,234.56“; (в) `modules.php`, `email.php`, `validation.php` са пренаписани — стойности на ключове, ползвани в динамични ключове (`modules.invoices.<статус>`, `modules.credit-notes.<статус>`), са сменени (статусите в PDF), провери един PDF на фактура и един на кредитно известие; (г) `app.close` вече е „Затвори“, `app.all` „Всички“, `app.to` „до“ — тези ключове се ползват масово; (д) `eng/*` са променени (sentence case на бутони/съобщения) — видимо за потребители на английски.
