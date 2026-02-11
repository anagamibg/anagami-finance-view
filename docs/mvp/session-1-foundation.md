# Сесия 1 — Основа: локална среда, скриване на модули, финансов модел данни, демо данни

> Тази сесия се пуска **първа и сама**. S2–S5 започват едва след като тя е commit-ната.

Прочети `docs/mvp/COMMON-RULES.md`, `docs/mvp/00-gap-analysis.md` и `CLAUDE.md`. Ти си S1 — ти създаваш точките за разширение, описани в COMMON-RULES, и имаш право да пипаш споделените файлове (`menu.blade.php`, `RouteServiceProvider`, и т.н.). Пиши минимални, ясно маркирани промени в оригиналните AFV файлове (коментар `{{-- databackd --}}` / `// databackd`), защото upstream ъпдейти ги презаписват — добави ги и в `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` като нова customization „CUS_databackd_mvp“.

## Задача 1 — Локална среда
1. `.env` в момента сочи към stage БД. Копирай го като `.env.stage.backup` (не го трий). В `.env` смени само `DB_*` към локален MySQL (`mysql -u root` работи без парола): БД `databackd_local`, `APP_URL=http://127.0.0.1:8123`, `APP_DEBUG=true`. **`APP_ENV` остава `codecanyon`.** Mail driver → `log`.
2. Създай БД `databackd_local` (utf8mb4), `php artisan migrate`, `php artisan db:seed` (виж `DatabaseSeeder`: при `codecanyon` демо seeders не се пускат — това е ок, демо данните са в задача 5). Ако `key:generate` в seeder-а пипне `.env`, провери, че другите стойности са непокътнати.
3. Оправи `.claude/launch.json` — сочи към несъществуващата папка `databackd-v6.0.10`; да сочи към `artisan` в тази папка, порт 8123, php `/opt/homebrew/opt/php@8.3/bin/php` (провери, че съществува; иначе `php`).
4. Създай `docs/mvp/DEMO.md` с: как се стартира, URL, демо акаунти (супер админ + админ на демо компанията + 1 служител + 1 клиент). Паролите да са тестови, само за локално (напр. генерирани и записани в seeder-а и в DEMO.md).

## Задача 2 — Скриване на ненужните модули (само фронтенд)
1. `config/databackd.php`:
   ```php
   'hidden' => [ 'menu' => [...ключове...], 'dashboard_tabs' => [...], 'settings' => [...], 'quick_add' => [...] ],
   ```
   Списъкът какво се скрива е в `00-gap-analysis.md`, раздел 3. Всеки ключ да е лесен за включване/изключване.
2. Helper `databackd_hidden(string $group, string $key): bool` (нов файл `app/Helper/databackd.php`, регистриран в composer `autoload.files` → `composer dump-autoload`).
3. Обвий съответните елементи в `resources/views/sections/menu.blade.php` (и подменютата), табовете в `resources/views/dashboard/admin.blade.php`, пунктовете в `resources/views/sections/setting-sidebar.blade.php` / settings менюто, и бутоните за бързо добавяне в topbar-а (`sections/topbar.blade.php`), както и widget-ите на личното табло за скритите модули. Routes **не** се премахват.
4. Добави в `menu.blade.php` нов раздел **„Финансов анализ“** (икона `bar-chart-line`), разположен веднага след „Табло“, който include-ва всички файлове от `resources/views/databackd/menu/*.blade.php` по азбучен ред (обхождането на папката да е във View Composer или в `AppServiceProvider`, не в Blade). Ако няма файлове — разделът не се показва.

## Задача 3 — Точки за разширение
Реализирай точно описаното в таблицата „Точки за разширение“ в COMMON-RULES:
- Автоматично зареждане на `routes/databackd/*.php` в `RouteServiceProvider` (същата middleware група като `web.php` + `auth`, префикс `account`).
- `database/seeders/Databackd/DatabackdDemoSeeder.php` + artisan команда `databackd:demo` (`app/Console/Commands/DatabackdDemo.php`), която: (а) **отказва да работи**, ако `DB_DATABASE` не завършва на `_local`, освен с `--force`; (б) създава/намира демо компанията; (в) пуска базовия демо seeder + всички класове в `database/seeders/Databackd/Demo/` по азбучен ред; (г) е идемпотентна (може да се пуска многократно); (д) опция `--only=ClassName`.
- Празни папки с `.gitkeep`: `routes/databackd/`, `resources/views/databackd/menu/`, `database/seeders/Databackd/Demo/`, `app/Models/Databackd/`, `app/Http/Controllers/Databackd/`, `app/Services/Databackd/`.

## Задача 4 — Ядро на финансовия модел (споделено от S2–S5)
Миграции + модели (`app/Models/Databackd/`, `HasCompany`):
- `fin_entities` — юридически лица/поделения в рамките на компанията (за консолидация): `company_id, name, eik, country, currency (EUR), parent_id nullable, ownership_pct (100), is_active, sort`.
- `fin_accounts` — сметкоплан: `company_id, code, name, type enum(asset, liability, equity, revenue, expense), subtype nullable (current_asset, non_current_asset, cash, receivable, inventory, current_liability, non_current_liability, cogs, payroll, opex, depreciation, interest, tax, other), parent_id nullable, is_active`. Seeder за опростен сметкоплан по НСС (гр. 1–7): 101, 121, 151, 201, 203, 204, 241, 302, 304, 401, 411, 421, 453, 454, 455, 501, 503, 601, 602, 603, 604, 605, 609, 621, 702, 703, 704, 709, 721 — с коректни type/subtype. Създава се автоматично за всяка компания (вкл. нова) — чрез observer или при първо отваряне.
- `fin_dimensions` — справочници за разрези: `company_id, type enum(region, client_segment, product_group, cost_center), name, code`. Демо: региони (София, Пловдив, Варна, Бургас, ЕС — Германия, ЕС — Румъния), сегменти (Микро, Малки, Средни, Публичен сектор), продуктови групи (Счетоводни услуги, Финансов анализ, Консултации, Софтуерни лицензи, Обучения), центрове на разходи (Администрация, Продажби, Екипи).
- `fin_entries` — универсален финансов запис: `company_id, entity_id, account_id, entry_date, kind enum(flow, balance)` (flow = оборот/транзакция за периода; balance = салдо по сметката към датата), `amount decimal(15,2)` (винаги положително; знакът се определя от типа на сметката), `currency, amount_base, region_id, client_segment_id, product_group_id, cost_center_id` (nullable FK към fin_dimensions), `client_id nullable` (към съществуващите клиенти), `counterparty_name, document_no, description, source enum(manual, excel, google_sheets, csv, api, invoice, expense, demo), import_batch_id nullable, created_by`. Индекси по `(company_id, entry_date)`, `(company_id, account_id, entry_date)`.
- `fin_import_batches` — `company_id, source, file_name, source_url, rows_total, rows_imported, rows_failed, status, mapping json, errors json, created_by`.
- `App\Services\Databackd\FinancialDataService` — четене, което всички ползват: `flows(from, to, filters)`, `sumByAccountType(from, to, filters)` → revenue/expense/profit, `monthlySeries(from, to, groupBy = null, filters)`, `balanceAt(date, accountCodes|subtype, filters)` (последното салдо ≤ дата), `pnl(from, to, filters)` (ОПР по сметки). `filters`: `entity_ids, region_id, client_segment_id, product_group_id, cost_center_id, client_id`. Ще бъде импортиран от S2–S5 — направи сигнатурите ясни и документирани (PHPDoc).

## Задача 5 — Демо данни (базови)
Команда `php artisan databackd:demo` трябва да създаде:
1. Демо компания **„Демо Финанс Груп“** (през нормалния механизъм за създаване на компания в SaaS, за да се създадат роли, настройки и т.н.), локал **bg**, валута **EUR**, часова зона Europe/Sofia. Админ, 8 служители-специалисти (финансов анализатор, счетоводител, контролер, бизнес анализатор…), 2 потребителя-клиенти. Отдели и длъжности на български.
2. Данни в съществуващите (видими) модули, за да не са празни: 15 клиента-контрагента (измислени БГ фирми, ЕИК-ове от вида `2000000NN`), 20 продукта/услуги с цени, 12 договора, 4 проекта с по 8–15 задачи и timelogs, ~200 фактури за 18-те месеца (смесени статуси: платени/неплатени/просрочени), съответните плащания, ~250 разхода в категории, 2 банкови сметки, няколко кредитни известия. Използвай съществуващите seeders/factories (`ProjectSeeder`, `ExpenseSeeder`, `ProductTableSeeder`…) където става, но с български текстове.
3. Финансов модел: 3 юридически лица — „Демо Финанс Груп АД“ (майка), „Демо Търговия ЕООД“ (100%), „Демо Консулт ООД“ (70%). За всяко: месечни `flow` записи по приходни/разходни сметки за 18 месеца с реалистична сезонност и растеж (~5–8% годишно), разпределени по регион/сегмент/продуктова група/център на разходи, плюс `balance` записи в края на всеки месец за балансовите сметки (активи = пасиви + капитал да се връзва приблизително). Включи и няколко вътрешногрупови сделки (между майката и дъщерните, `counterparty_name` = другото лице) — S4 ще ги елиминира при консолидация.
4. Обем: стремеж към 3 000–6 000 `fin_entries`. Генерирането да е детерминистично (`mt_srand(2026)`).

## Свършено, когато
- `php artisan serve --port=8123` → вход като демо админ → менюто показва само нужните модули + „Финансов анализ“ (засега празен/скрит); таблото, клиенти, фактури, разходи, договори, проекти — пълни с данни, на български.
- `php artisan databackd:demo` минава два пъти поред без грешки и без дублиране.
- Скриването се включва/изключва от `config/databackd.php`.
- Изпълнени са стъпките „Край на сесията“ от COMMON-RULES (`docs/mvp/screenshots/01-foundation.md`, `docs/mvp/features/01-foundation.md` и **`docs/mvp/handoff/01-foundation.md`** — S2–S5 разчитат на него за модела данни, `FinancialDataService`, точките за разширение и демо акаунтите). Commit — включи и цялата папка `docs/mvp/` (промптовете на S2–S5).
