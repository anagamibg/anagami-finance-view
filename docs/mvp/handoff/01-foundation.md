# Handoff S1 → S2–S5: Основа

## Статус
**Готово.** Локална среда, скриване на модули, точки за разширение, финансов модел данни и демо данни работят. Страниците са проверени чрез вътрешно рендериране на заявки през Laravel kernel (по искане на потребителя не е стартиран локален сървър) — всички основни екрани връщат 200 и нямат грешки в лога.

## Какво е направено

### Локална среда
- `.env` → `DB_*` към локален MySQL `databackd_local` (root без парола), `APP_URL=http://127.0.0.1:8123`, `APP_DEBUG=true`, `MAIL_MAILER=log`. `APP_ENV` остава `codecanyon`. Оригиналът е в `.env.stage.backup` (съдържа тайни, не е в `.gitignore` → **не го добавяй с `git add`**).
- `vendor/` беше от Laravel 10; изпълнено е `composer install` по `composer.lock` (Laravel 12). PHP: `/opt/homebrew/opt/php@8.3/bin/php`.
- `.claude/launch.json` → конфигурация `databackd-local` (порт 8123).
- `docs/mvp/DEMO.md` — акаунти и команди.

### Скриване на модули — `config/databackd.php` → `hidden`
Групи: `menu`, `dashboard_tabs`, `dashboard_widgets` (допълнителна), `settings`, `quick_add`. Helper `databackd_hidden(string $group, string $key): bool` (`app/Helper/databackd.php`, в `composer.json` `autoload.files`). Всичко е маркирано с `databackd` в Blade-овете (виж `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` §4.4).

### Точки за разширение
- `routes/databackd/*.php` — автоматично, по азбучен ред; middleware `web, check-company-package, auto-logout, auth, multi-company-select, email_verified` (както `web.php`), префикс `account`.
- `resources/views/databackd/menu/*.blade.php` — раздел „Финансов анализ“ (`databackd.menu.financial_analysis`) се показва само ако има файлове и ролята не е клиент. Колекцията се подава от View Composer в `AppServiceProvider` като `$databackdMenuViews`.
- `database/seeders/Databackd/Demo/<NN><Name>.php` → клас `<Name>` (**префиксът не е част от името на класа**), метод `run(\App\Models\Company $company)`.
- Празни папки с `.gitkeep`: `routes/databackd`, `resources/views/databackd/menu`, `database/seeders/Databackd/Demo`, `app/Models/Databackd`, `app/Http/Controllers/Databackd`, `app/Services/Databackd`.

### Таблици (миграции `2026_10_27_1000*`)
Всички: `id, company_id (FK → companies), …, created_at, updated_at`.
- `fin_entities`: `name, eik, country(2), currency(3), parent_id, ownership_pct(5,2), is_active, sort`
- `fin_accounts`: `code, name, type enum(asset|liability|equity|revenue|expense), subtype enum(current_asset|non_current_asset|cash|receivable|inventory|current_liability|non_current_liability|cogs|payroll|opex|depreciation|interest|tax|other) null, parent_id, is_active`; unique `(company_id, code)`
- `fin_dimensions`: `type enum(region|client_segment|product_group|cost_center), name, code`
- `fin_entries`: `entity_id, account_id, entry_date, kind enum(flow|balance), amount(15,2 >0), currency, amount_base(15,2), region_id, client_segment_id, product_group_id, cost_center_id, client_id, counterparty_name, document_no, description, source enum(manual|excel|google_sheets|csv|api|invoice|expense|demo), import_batch_id, created_by`; индекси `(company_id, entry_date)`, `(company_id, account_id, entry_date)`, `(company_id, entity_id)`
- `fin_import_batches`: `source, file_name, source_url, rows_total, rows_imported, rows_failed, status, mapping json, errors json, created_by`

### Модели (`App\Models\Databackd\`, `HasCompany`, `$guarded=['id']`)
`FinEntity` (parent, children, entries), `FinAccount` (константи `TYPES`, `CONTRA_CODES=['241']`, `is_contra`), `FinDimension` (`TYPES`), `FinEntry` (`KINDS`, `SOURCES`; релации entity, account, region, clientSegment, productGroup, costCenter, client, importBatch), `FinImportBatch`.

### Сервизи (`App\Services\Databackd\`)
- `ChartOfAccounts::ensureFor(int $companyId): int` — 29 сметки по НСС (101, 121, 151, 201, 203, 204, 241, 302, 304, 401, 411, 421, 453, 454, 455, 501, 503, 601, 602, 603, 604, 605, 609, 621, 702, 703, 704, 709, 721). Вика се от `Company::created` (регистрирано в `AppServiceProvider`) и от конструктора на `FinancialDataService`.
- `FinancialDataService` — `__construct(?int $companyId = null)`:
  - `query(array $filters = []): Builder`
  - `flows($from, $to, array $filters = []): Collection<FinEntry>`
  - `sumByAccountType($from, $to, array $filters = []): array{revenue, expense, profit}`
  - `monthlySeries($from, $to, ?string $groupBy = null, array $filters = []): array` — редове `month('YYYY-MM'), revenue, expense, profit`; при `groupBy` (`region|client_segment|product_group|cost_center|entity|account`) и `group_id, group_name`
  - `balanceAt($date, $accounts, array $filters = []): float` — `$accounts`: код(ове), подтип или тип; контра сметките се приспадат
  - `pnl($from, $to, array $filters = []): array{revenue: [...], expense: [...], totals}`
  - `$filters`: `entity_ids, region_id, client_segment_id, product_group_id, cost_center_id, client_id`

### Команда и демо данни
`php artisan databackd:demo [--force] [--only=Клас]` (`app/Console/Commands/DatabackdDemo.php`) — отказва, ако `DB_DATABASE` не завършва на `_local`. Пуска `Database\Seeders\Databackd\DatabackdDemoSeeder` (компания, потребители, отдели, 15 клиента, 20 продукта) и после всеки файл от `Demo/`:
- `10WorkDemoSeeder` — 12 договора, 4 проекта, 47 задачи, 118 timelog-а
- `20FinanceDocsDemoSeeder` — 2 банкови сметки, 200 фактури, 203 плащания, 5 кредитни известия, 250 разхода, 395 банкови транзакции
- `30FinModelDemoSeeder` — 3 юридически лица, 18 разреза, 3 654 `fin_entries` (приходи 3.17 млн, разходи 2.63 млн EUR; баланс = пасиви + капитал точно; 150 вътрешногрупови сделки)

Демо компанията е **„Демо Финанс Груп“** (търси се по име; локално `company_id = 4`, на production е нова компания със собствен номер), `bg`, **само EUR**, пакет „Демо“ (копие на Medium с високи лимити — иначе CUS_000329 пренасочва към billing). Акаунти — `docs/mvp/DEMO.md`.

## Отклонения от промпта
- Имената на файловете в `Demo/` започват с число → не са PSR-4; командата ги `require_once`-ва и взема класа без числовия префикс. `--only=` приема и двете форми.
- Routes middleware включва и `multi-company-select`, `email_verified` (като `web.php`).
- Добавена група `dashboard_widgets` в конфига (лично табло).
- Валутите на демо компанията са само EUR (изтрити USD/GBP/INR). Номерът на компанията не е фиксиран (търси се по име). Супер админ се създава само на `_local` база; паролата се задава с `--password=`.
- `DatabackdDemoSeeder::PASSWORD` в `database/seeders/Databackd/DatabackdDemoSeeder.php` — паролата по подразбиране за всички демо акаунти (само локално; на production — `--password`, виж `~/demo-passwords.txt` на сървъра).
- Не е стартиран `php artisan serve` (по изрично искане) — проверката е чрез вътрешно рендериране на заявки.

## Не е направено / известни проблеми
- Раздел „Финансов анализ“ е празен (скрит) до добавянето на файлове в `resources/views/databackd/menu/` от S2–S5.
- Клиентският акаунт няма достъп до `/account/invoices` (403) — права на ролята `client`; не е проучвано.
- Разходите във `fin_entries` нямат регион → `monthlySeries(..., 'region')` връща ги в група `group_id = null`; регионите носят само приходи.
- `20FinanceDocsDemoSeeder` пише с query builder без observers (фактури, плащания, банкови транзакции) → няма записи в `universal_searches`.
- `10WorkDemoSeeder` ползва фиксирана „днес“ = 2026-10-27; проектът NEO (на пауза) показва ~89% напредък.
- Системните колони на таскбоарда („To Do“, „Doing“, „Completed“) остават английски (системни настройки на AFV).
- Преводите на меню на български са от `resources/lang/bg/` (някои са стари, напр. „Обозначаване“ за длъжност).

## Какво трябва да знае следващата сесия
- Чети данни през `FinancialDataService` (работи и без логнат потребител; тенантът е в конструктора). Нужни ти методи → собствен клас в `app/Services/Databackd/`, не пипай този.
- `amount` е винаги положителна; знакът следва типа на сметката. `241` е контра сметка. Балансите са `kind='balance'` — за периоди ползвай `balanceAt`, не сумирай ги.
- `CompanyScope` важи за `fin_*` модели. В seeders/конзола — `withoutGlobalScope(CompanyScope::class)`.
- Вътрешногрупови сделки: `counterparty_name` е точно името на другото юридическо лице, `document_no` `ВГ-ГГГГ-НННН`, описание „Вътрешногрупова сделка“; сметки 703 (продавач) и 602 (купувач), еднакви суми/дати.
- Не пипай: `config/databackd.php`, `menu.blade.php`, `RouteServiceProvider`, `AppServiceProvider`, `DatabackdDemoSeeder`, `DatabackdDemo`. Нови seeder-и — само в `Demo/` (идемпотентни).
- Не пускай `migrate:fresh` и др.; `.env.stage.backup` не се commit-ва.
- Проверка без сървър: `php docs/mvp/tools/smoke.php - --ajax --url=/account/...`. В S1 е добавена корекция (`APP_RUNNING_IN_CONSOLE=false` и работна папка `public/`) — без нея всяка HTML страница даваше фалшива грешка `Undefined variable $currentRouteName` / липсващ `version.txt`. Резултат за страниците на S1: всички OK.

## Как да се провери
```bash
php artisan migrate && php artisan db:seed
php artisan databackd:demo            # два пъти → същите бройки
php artisan databackd:demo --only=FinModelDemoSeeder
php artisan route:list --path=account/dashboard
```
Вход: `docs/mvp/DEMO.md`. След `php artisan serve --port=8123` → `/account/dashboard`, `/account/dashboard-advanced?tab=finance`, `/account/invoices`, `/account/expenses`, `/account/contracts`, `/account/projects`.

## Локални файлове извън git
`.env` (и `.env.stage.backup`), `.claude/launch.json`, `resources/lang/bg/databackd.php` (bg е в `.gitignore` → да се качи ръчно на сървъра; същото съдържание като `eng/`, но с „Финансов анализ“), `vendor/`, БД `databackd_local`, `Modules/`.

## Production (Anagami Finance View) — качено 2026-10-27
- Сървърът (`ssh databackd`, `~/public_html`) не е git checkout; файловете се качват с `rsync`. Качени са 34 файла от S1 + `resources/lang/bg/databackd.php`. `docs/` не се качва.
- На сървъра няма `composer` → записът за `app/Helper/databackd.php` е добавен на ръка във `vendor/composer/autoload_files.php` и `autoload_static.php` (същото прави `composer dump-autoload`; при следващ `composer install` ще се презапише коректно от `composer.json`).
- Миграции: `php artisan migrate --force` (само 5-те `fin_*`). Кешовете са изчистени и презаредени (`config:cache`, `route:cache`) — на сървъра конфигурацията е кеширана, затова след всяка промяна в `config/databackd.php` трябва `php artisan config:clear && php artisan config:cache`.
- Скриването на модулите е **глобално** за всички компании на сървъра.
- Демо компанията е **нова компания** „Демо Финанс Груп“ (на сървъра `company_id = 17`); компания 4 („Тест ЕООД“) и останалите не са пипани. Създаден е частен пакет „Демо“ (копие на Medium с високи лимити). Супер админ не е създаван.
- Парола на демо акаунтите: случайна, в `~/demo-passwords.txt` на сървъра (права 600). Акаунтите са като в `docs/mvp/DEMO.md`.
- Бекъп преди качването: `~/deploy-backup-databackd-mvp-20261027/` (оригиналните 8 файла, двата autoload файла, `db.sql.gz`). Връщане: копирай файловете обратно и `php artisan config:cache`; новите таблици `fin_*` са празни за другите компании и не пречат.
