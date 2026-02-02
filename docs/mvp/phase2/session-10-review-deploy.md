# Сесия 10 — Независимо код ревю, поправки, GitHub и production

Прочети `docs/mvp/phase2/COMMON-RULES-2.md` и всичко, което то изисква — **особено** handoff-ите 06–09 (секциите „За S10“) и паметта/бележките за production деплоя (`docs/mvp/handoff/*` и `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md`). Ти си **S10** — единствената сесия, която push-ва и деплойва. Пусни я **последна**, когато S6–S9 са commit-нали. Без локални сървъри.

## Фаза 0 — подготовка (ти)
1. Провери, че S6, S7, S8, S9 са commit-нали (`git log`, handoff-ите съществуват). Ако някоя липсва — спри и кажи на потребителя.
2. Определи обхвата: последният деплойнат commit (от handoff-ите/бележките за деплоя; във фаза 1 това е `015200c` + последващите docs commit-и) → `git diff --stat <base>..HEAD`, списък на файловете и миграциите.
3. Пълна локална проверка: `php artisan migrate --isolated` (чиста от предишни грешки), `databackd:check-formulas`, `check-consolidation`, `check-forecasts`, `check-security` (S6), `check-alerts --dry-run` (S8), smoke на всички префикси (`dashboard`, `databackd.`, `invoices`, `expenses`, `clients`, `contracts`, `projects`) с админ, служител и клиент → baseline.

## Фаза 1 — две независими код ревюта (паралелно, 2 агента)
Пусни **2 агента едновременно**, без да им казваш твоите очаквания или находките на другия:
- **Ревю A — коректност и сигурност:** бъгове, грешни изчисления, необработени грешки, N+1 заявки, изолация между компании, права, инжекции, файлове, тайни. Ползва skill `code-review` (ако е наличен) върху `<base>..HEAD`.
- **Ревю B — регресии и готовност за production:** миграции (идемпотентни ли са, какво правят върху реалните пакети/компании на production — особено модулите на S8), съвместимост с PHP 8.4 на сървъра, кеширан config/routes (няма `env()` извън config, няма closures в routes), липсващи преводи/assets, scheduler/cron, производителност на големите справки, upstream-merge рискове (`docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` обновен ли е).
Всеки връща списък находки: файл:ред, проблем, сценарий, сериозност, предложение. Без поправки.

## Фаза 2 — поправки (до 10 агента)
Обедини находките (премахни дублиращи се, отхвърли неверните след проверка). Раздели потвърдените по файлове на непресичащи се пакети и пусни агенти да ги поправят. После повтори проверките от Фаза 0 т. 3 — никакви нови FAIL. Ако нещо критично не може да се поправи — спри преди деплоя и попитай потребителя.

## Фаза 3 — GitHub
1. Commit на поправките (реална текуща дата, без AI attribution).
2. `git push origin main` (remote `https://github.com/anagami-accounting/databackd.com.git`). Без force push. Ако push-ът бъде отхвърлен (има нови commit-и в remote) — `git fetch` и покажи разликата на потребителя, не merge-вай сам.

## Фаза 4 — production деплой (`ssh databackd`, `~/public_html`, не е git checkout, няма composer)
Следвай реда: **разузнаване → бекъп → rsync → миграции → данни → кешове → проверка**.
1. Разузнаване: версии, свободно място, кои от файловете за деплой се различават на сървъра (`rsync -rlt --dry-run --itemize-changes --files-from=…`).
2. Бекъп: `~/deploy-backup-databackd-phase2-<дата>/` — текущите версии на всички файлове, които ще се презапишат, + dump на засегнатите таблици (`modules`, `packages`, `module_settings`, всички `fin_*`, новите таблици) + `.env`.
3. `rsync -rlt --files-from=<списък> ./ databackd:public_html/` — файловете от `<base>..HEAD` + компилираните assets (`public/css`, `public/js`, `mix-manifest.json`) + `resources/lang/bg/**` и `eng/**`. Никога `.env`, `.env.*`, `storage/`, `vendor/` (ако composer зависимости са променени — спри и попитай; нови `autoload.files` се добавят ръчно в `vendor/composer/autoload_files.php` и `autoload_static.php`).
4. `php artisan migrate --force` — преди това покажи на потребителя какво ще направят миграциите върху **реалните** пакети и компании (особено модулите на S8) и изчакай потвърждение.
5. Демо данни само за компания 17 „Демо Финанс Груп“: новите seeders се пускат директно през `tinker` (`require_once` на файла + `(new Seeder)->run(Company::find(17))`). **Никога** `databackd:demo` на production и никакви промени в други компании (компания 4 има реални потребители).
6. Кешове: `php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:clear` — **след** последния rsync.
7. Scheduler: провери cron за `schedule:run`; ако липсва — покажи на потребителя реда за crontab и го добави само след потвърждение.
8. Действия от S6 за production (headers, права, `.env` стойности) — покажи списъка и изпълни само потвърдените от потребителя. Смяна на демо паролата — само ако потребителят каже, и обнови `~/demo-passwords.txt` (не я показвай в чата).
9. Проверка: `curl -s -o /dev/null -w "%{http_code}"` за публичните страници и login; `tail` на `storage/logs/laravel.log` (нови грешки след деплоя); `php artisan route:list | wc -l`; при грешка — върни файловете от бекъпа и кешовете.
10. Помоли потребителя да влезе като `admin@demofinans.bg` и прегледай ключовите екрани (или пусни отново скрипта за снимки от `docs/mvp/tools/`/scratchpad, ако е наличен, като потребителят влезе в отворения прозорец).

## Край
`docs/mvp/handoff/10-review-deploy.md` (обхват, находки от ревютата и как са решени, какво е деплойнато, бекъп, как се връща, отворени въпроси), запис в `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` за новите customization-и, commit + push на документацията.
