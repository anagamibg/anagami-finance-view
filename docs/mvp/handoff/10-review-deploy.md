# Handoff S10: независимо код ревю, поправки, GitHub и production

## Статус
**Готово.** Фаза 2 (S6–S9 + поправките от ревюто) е в GitHub (`main`, `ca15366..c22724a`) и е деплойната на https://databackd.com на 2026-10-28 около 11:30 (EET). Миграциите и демо данните (seeders 46–49) за компания 17 са пуснати. Сайтът беше в maintenance mode около 3 минути. Ръчният преглед в браузър (т. „Какво остава“) още не е направен.

## Обхват
- База: `015200c` (S5, последният деплой от фаза 1) → `HEAD` = `c22724a`.
- Commit-и: S9 `3fbb462`, S8 `4a65de9` + `38399e4`, S6 `fc80691`, S7 `3fb1674`, S10 `c22724a` (поправки), + този docs commit.
- 940 файла в diff-а (292 нови, 637 променени, 11 изтрити — `public/backup-and-restore/**`, `public/check.php`); 2 миграции, seeders 46–49; без промени по `composer.json`/`vendor/`.
- Деплойнати 870 файла (`git diff --name-only --diff-filter=AMR 015200c..HEAD` без `docs/`, `CLAUDE.md`, `.gitignore`, `.env.*`, `tests/` + целите `resources/lang/{bg,eng}` + `public/mix-manifest.json` и компилираните assets): 179 нови, 690 променени, 1 непроменен.

## Baseline (преди поправките) и проверка след тях
`databackd:check-formulas` 109/109, `check-consolidation` 25/25 → **28/28** (3 нови случая), `check-forecasts` 69/69, `check-security` 83/83, `check-alerts --dry-run` без грешки, `lang-audit --strict` 0 критични, `php -l` на всички променени файлове. Smoke на `dashboard`, `databackd.`, `invoices`, `expenses`, `clients`, `contracts`, `projects` × админ / служител (`g.petrov@demofinans.bg`) / клиент (`p.yordanov@balkan.bg`) — FAIL-овете са само познатите от фаза 1 (`counterparties.show` 302, `dashboard.beam_auth` 401, `invoices.stripe_modal`/`committed_modal`/`get_client_company`/`invoices-payment-details` 500, `*.add_item`/`offline_payment_modal`/`projects.burndown` 404, `clients.gdpr_consent` 500, `private_calendar` 500 за клиент, 403 по права за служител/клиент). **Единствена нова разлика:** служителят получава 403 на `databackd.gaps.margins.*` (умишлено, виж A1).

## Ревюта
Две независими ревюта на `015200c..HEAD` (агенти без информация за очакванията и един за друг): **A** — коректност и сигурност, **B** — регресии и готовност за production. Всички находки са проверени в кода, преди да се поправят.

### Ревю A (коректност и сигурност)
| # | Находка | Сериозност | Решение |
|---|---|---|---|
| A1 | Справката „Маржове“ (`MarginReportController`) показва приходи по клиенти/продукти и себестойност на всеки с `databackd_reports`, без `view_invoices`/`view_product` | средна | Поправено: админ или `view_invoices = all` **и** `view_product = all` (контролер + меню `25-gaps-margins`) |
| A2 | Резервният курс от `currencies.exchange_rate` се **дели** вместо да се умножава (databackd: 1 единица валута = `rate` единици от валутата на компанията) | средна | Поправено на 4 места: `FinEntryImporter`, `BankStatementImporter`, `FinEntryController`, `FinEntryBulkController` |
| A3 | Консолидацията смесва валути, когато валутата на групата ≠ валутата на компанията (`amount_base` се показва като валута на групата) | средна | Поправено в `ConsolidationService` (конверсия през `FxRateService`, ръчният `fx_rate` е с предимство, липсващ курс → `fxMissing()`); при еднаква валута изходът е байт-идентичен |
| A4 | Потвърден импорт на припокриващо се извлечение на същата сметка удвоява крайното салдо | средна/ниска | Поправено: салдото не се добавя втори път (предупреждение в партидата и в прегледа) |
| A5 | Новите Excel експорти (маржове, ГФО по НСС) без защита от формули | ниска | Поправено с `NeutralizesFormulas` |
| A6 | Неуспешна смяна на парола (невалиден токен) пак сменя `remember_token` на жертвата | ниска | Поправено в `AuthHardening` (само при реална смяна на хеша); при смяна от профила токенът вече се сменя наистина |
| A7 | Текст на exception без escape в toastr (`BankImportController`), колони 191 знака режат на 255 | ниска | Поправено (+ същото в `FxRateController`, `AnalysisRequest*Controller`, `FinSyncController`) |
| A8 | `TrustProxies::$proxies = '*'` — IP лимитите могат да се заобиколят с `X-Forwarded-For` | средна, ако LiteSpeed не презаписва заглавието | **Отворено** (виж „Отворени въпроси“) |
| A9 | Проверката за `DOCTYPE` в CAMT се заобикаля с UTF-16 | ниска | Поправено (проверка след конверсия + `$dom->doctype`) |

### Ревю B (регресии и production)
| # | Находка | Решение |
|---|---|---|
| B1 | Грешен ред на качване (`Kernel.php` преди middleware класовете, `databackd.php` преди `databackd_modules.php`, стар route/config кеш) = 500 навсякъде | Процедура: `artisan down` → `rsync --delay-updates` (всичко наведнъж) → `migrate` → кешове → `up` |
| B2 | Съветникът за първоначална настройка се показва на всички съществуващи компании | Прието (компании 4–6 нямат финансови данни — съветникът е уместен); виж „Отворени въпроси“ |
| B3 | Scheduler-ът за пръв път пуска `send-reports`/`recurring-analysis`/`check-alerts` — демо справките на компания 17 се пращат към `@demofinans.bg` (без DNS) | По решение на потребителя: `schedule = none` за 5-те справки с график на компания 17; dry-run на трите команди на production |
| B4 | `Secure` бисквитка без HTTP→HTTPS пренасочване; HSTS с `includeSubDomains` по подразбиране | Пренасочване в кореновия `.htaccess` (по решение на потребителя); `include_subdomains` по подразбиране `false` (и във fallback-а на `SecurityHeaders`) |
| B5 | Сървърни копия на `layouts/app.blade.php` и `bg/` може да имат ръчни правки | Проверено: `layouts/app.blade.php` на сървъра = `015200c`; `bg/`/`eng/` на сървъра нямат ключове извън HEAD (освен умишлено изтрития `notes.entries_link`); бекъп на цялото `lang/` |
| B6 | `2026_10_28_100000_create_databackd_gaps_tables` не може да се пусне повторно след частичен срив | Поправено: `hasTable`/`hasColumn` пазачи, ENUM ALTER само ако липсва `bank_statement` (на чиста база — същото поведение) |
| B7 | `module_in_package` е `VARCHAR(1000)` | Проверено: на production най-дългият е 282 знака |
| B(2) | `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` без раздели за S7 и S9 | Добавени 4.7 (S9), 4.8 (S7), 4.9 (S10); поправени твърденията за `custom_css`/preloader/„baked-in fixes“ — **не съществуват в git** (и в `CLAUDE.md`) |

### Остатъци от S6 (раздел 6.Б на протокола) — направени в S10
Мъртвият `<script type="text/php">` в `databackd/reports/export/pdf.blade.php`; `@json` в `data/import/{_mapping,result}` и в `components/{bar,pie}-chart`; `isRemoteEnabled = false` в `MarginPdf`/`StatutoryPdf`; изтрити `/test-broadcast`, `/debug-broadcast`; `sync-user-permissions` само за суперадмин; липсващите ключове `databackd-data-sources.errors.*` (`GoogleSheetsReader` минава само през тях); демо паролата е махната от `docs/` (вече е само в `DatabackdDemoSeeder::PASSWORD` и в `~/demo-passwords.txt` на сървъра); `git rm --cached .env.dev`; празен `APP_KEY` в `.env.example`.

## GitHub
`git push origin main` → `ca15366..c22724a` (fast-forward, без force; remote нямаше нови commit-и). Repo-то е private. Внимание: старите commit-и в историята още съдържат `.env.dev` и демо паролата (махнати са само от HEAD).
Не са commit-нати (не са на S10, бяха променени преди сесията): `docs/mvp/COMMON-RULES.md`, `docs/mvp/session-{2..5}-*.md`, `docs/mvp/phase2/{00-plan,session-6..10-*}.md`.

## Какво е направено на production (`ssh databackd`, `~/public_html`)
1. **Разузнаване:** PHP 8.4.27 CLI (web 8.5), Laravel 12.61.0, 107 GB свободни; cron `*/5 * * * * php85 artisan schedule:run` вече съществуваше (нищо не е добавяно); преди деплоя `bootstrap/cache` нямаше кеширани config/routes. От файловете за презаписване на сървъра се различаваха от `015200c` само `public/css/main.css`, `public/js/{main,bootstrap}.js`.
2. **Бекъп:** `~/deploy-backup-databackd-phase2-20261028/` (`chmod 700`): `files/` (691-те файла преди презаписа), `lang/` (цялото `resources/lang`), `env.backup`, `bootstrap-cache/`, `db-affected-tables.sql.gz` (`modules`, `packages`, `module_settings`, `products`, `companies`, `currencies`, `migrations`, всички 15 `fin_*`), `rsync-dryrun.txt` (кои файлове са нови), `htaccess.root.backup`, `user-uploads-databackd/`, `removed-from-web/` (`check.php`, `backup-and-restore/`), `deploy-files.txt`, `server-backup.sh`, `prod-render.php`.
3. **Деплой:** `php artisan down --retry=60` → `rsync -rlt --delay-updates --files-from=deploy-files.txt ./ databackd:public_html/` (повторният dry-run: 0 разлики) → `php artisan migrate --force` (2 миграции, ~1,2 s) → `optimize:clear`, `config:cache`, `route:cache`, `view:clear` → `php artisan up`.
4. **Резултат от миграциите:** 6 реда `databackd_*` в `modules`; 6-те модула добавени в `module_in_package` на всички пакети (Default — формат обект по id, Trial/Starter/Демо — списък); 52 реда `module_settings` (4 компании × 13); `products.cost_price`; `bank_statement` в `fin_entries.source`; 6 нови празни таблици.
5. **Демо данни (само компания 17 „Демо Финанс Груп“):** seeders 46→47→48→49 през `tinker` (`require_once` + `(new …)->run(Company::find(17))`, с проверка на името на компанията): 18 продукта със себестойност, 528 курса, 2 банкови извлечения, 6 правила за известия, завършен onboarding; създаден е **глобален частен пакет „Стартов“ (id 7)**, без компании; компания 17 остава на пакет „Демо“ (6).
6. **Данни на компания 17:** `currencies` EUR (id 67) → `right_with_space`, хиляди = NBSP, десетични „,“ (сумите `1 234,56 €`; потребителите трябва да излязат и влязат отново); датите вече бяха `d.m.Y`. 5 запазени справки с график → `schedule = none`. **Компания 4 и останалите не са пипани** (SQL-ът на S7 за компания 4 не е пуснат).
7. **Действия от S6 (потвърдени от потребителя):** `public/check.php` и `public/backup-and-restore/` преместени в `removed-from-web/` (сега 404 / 301 без съдържание); 10-те демо файла на заявките за анализ преместени в частния диск `storage/app/databackd/databackd/analysis/` + `public/user-uploads/databackd/.htaccess` = `Require all denied`; HTTP→HTTPS пренасочване в кореновия `.htaccess` (LiteSpeed го пое след ~2 мин без рестарт; `http://databackd.com/login` → 301); `chmod 700 ~/deploy-backup-*`. Демо паролата **не** е сменяна (решение на потребителя).

## Проверка на production
- `curl`: `/login` 200, `/` и `/account/*` 302 към login, `http://` → 301 https, `/check-env` 404, `/log-viewer/api/files` 302 към login, API `GET` 405 / `POST` без токен 401, `/.env` 404, `public/check.php` 404, старият публичен път на файловете към заявките 404, `POST /login?x=-webhook` без токен → пренасочване към login (CSRF важи); заглавия `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Frame-Options`, `Strict-Transport-Security: max-age=31536000` (без поддомейни), `Content-Security-Policy-Report-Only`.
- `php artisan route:list`: 2390 маршрута; `databackd.gaps.*` налице. `schedule:list`: `recurring-analysis` 04:00 UTC, `send-reports` 05:00 UTC, `check-alerts` на час. Dry-run на сървъра: `check-alerts --company=17` — 4 правила, 0 нови известия; `recurring-analysis` — 0; `send-reports` — 0 след изключването на графиците.
- In-process рендер на сървъра (`prod-render.php`, само GET, като потребители на компания 17, без пароли; логиката на `smoke.php`): админ — 27 страници 200 (таблата, фактури, разходи, клиенти, договори, проекти, продукти, всички AFV екрани вкл. gaps, PDF на ОПР и маржове); служител — 200, маржове 403; клиент — табло и „Заявки за анализ“ 200, финансово табло 403.
- `storage/logs/laravel-2026-10-28.log`: след деплоя няма нови грешки от реални заявки. Записите `Undefined variable $currentRouteName` в 09:35 UTC са от първия пуск на проверочния скрипт (рендер в console режим — поправен, виж `prod-render.php`); `super-admin/register.blade.php:8` в 09:31/09:32 — от моите `curl /signup` (виж „Отворени въпроси“).

## Как се връща назад
```bash
ssh databackd; cd ~/public_html; B=~/deploy-backup-databackd-phase2-20261028
php artisan down
# 1. данни (по избор, ако миграциите трябва да се върнат):
php artisan migrate:rollback --step=2 --force        # down() на двете миграции (S8: маха модулите от пакетите и module_settings; gaps таблиците)
#    или пълно възстановяване на засегнатите таблици:  zcat $B/db-affected-tables.sql.gz | mysql <db>   (ще изтрие и новите редове от след деплоя)
# 2. файлове: старите версии обратно, новите (от rsync-dryrun.txt, редове '<f+++') — изтрити
rsync -a $B/files/ ./ ; rm -rf resources/lang && cp -a $B/lang resources/lang
grep '^<f+++' $B/rsync-dryrun.txt | awk '{print $2}' | xargs rm -f
cp -p $B/htaccess.root.backup .htaccess
# 3. кешове
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:clear
php artisan up
```
`check.php`/`backup-and-restore/` и файловете на заявките **не** бива да се връщат в `public/`.

## Отворени въпроси (за собственика)
1. **Ротация на тайните** (S6, P8-02): паролата на базата и `APP_KEY` не са сменени, а `.env` беше публичен 01.10–27.10. Смяната на `APP_KEY` прекъсва сесиите и криптираните стойности — нужно е решение и прозорец.
2. **2FA** за 2-мата суперадмини и 4-мата админи — 0 от 6 (ръчно от профилите).
3. **Демо паролата** на компания 17 е публичната от историята на git — по решение на потребителя не е сменена в S10.
4. **`TrustProxies = '*'`** (A8): да се провери дали LiteSpeed презаписва `X-Forwarded-For`; ако не — `$proxies` да е адресът на реалния прокси (или `null`).
5. **`/signup` дава 500** — съществуващ проблем (регистрацията е затворена, а за езика няма запис в `SignUpSetting` → `$signUpMessage` е `null`; `CompanyRegisterController` и view-то не са променяни). Решение: текст на съобщението в суперадмин (настройките за регистрация на суперадмин — ресурс `sign-up-setting` и `signup-setting-translation/{lang}` в `routes/SuperAdmin/web.php`) или guard `?->message` във view-то.
6. **Компания 4 „Тест ЕООД“** (реални потребители): формат на валутата от S7 не е приложен (сумите там остават `€1,234.56`); съветникът за първоначална настройка ще се вижда там и в 5, 6.
7. **Пакет „Стартов“** (частен, глобален, от seeder 49) — да се реши дали да остане в суперадмин панела.
8. `~/public_html_5.4.6_old` (стар екземпляр с `.env` и дъмп) — архивиране и изтриване; CSP е само Report-Only; зависимостите от P10 (phpspreadsheet, symfony/http-foundation, dompdf…) не са ъпдейтвани; служителите имат пълен достъп до финансовите модули (P2-05, прието).
9. `https://databackd.com/public/backup-and-restore/` връща безкраен 301 (празна директория в кеша на LiteSpeed ↔ премахване на наклонената черта от Laravel) — без съдържание, безвредно.

## Какво остава (ръчен преглед в браузър)
Вход като `admin@demofinans.bg` (паролата е в `~/demo-passwords.txt`; излез и влез отново заради формата на валутата): сайдбарът (групи „Финансов анализ“), Финансово табло + банерът на съветника, „Маржове“, „ГФО по НСС“, „Известия и прагове“, „Банкови извлечения“ (качване на пример от `public/databackd/templates/bank-statements/` → преглед → запис → отмяна), „Валутни курсове“, автосейв в редактора на бюджет, PDF на фактура, сумите `1 234,56 €`; суперадмин → „Пакети“ → редакция (блок „Финансов анализ“). Списъкът на S8 (`08-packages-gaps.md`, т. 7) остава в сила — JS поведението не е кликано в жив браузър.

## Локални файлове извън git
`.env`, `.env.dev` (вече не е в git), `.env.stage.backup`, `.claude/`, `Modules/`, `public/mix-manifest.json`. Скриптовете на S10 (`server-backup.sh`, `prod-render.php`) са копирани в бекъп папката на сървъра.
