# Обновяване на Anagami Finance View 6.0.10 → 6.0.19 (2026-10-28)

## Проучване (резултат)

- Източник: `~/Downloads/worksuite-saas-new-6.0.19/script` (`public/version.txt` = 6.0.19). Текущата база е 6.0.10.
- В git има чист baseline на 6.0.10 — `37235f8` („vendor: Anagami Finance View 6.0.10 clean baseline“). Върху него е записан чист 6.0.19 в клона `vendor/worksuite` (`ae7d718`, без IDE артефакта `storage/framework/lsp-*.php`). Така merge-ът е истински 3-way: база `37235f8`, наши = `main` (`13be49c`), upstream = `ae7d718`.
- Upstream 6.0.10 → 6.0.19 засяга **416 файла** (119 нови, 297 променени, 0 изтрити): Worldpay (абонаменти и плащания), имейли от сделки + шаблони, нов модул „Форми за потенциални клиенти“, напомняния за фактури (преди/след падеж, история), шаблон 6 за PDF, ресурси към продукти + нов изглед на продукта, отпуски (неограничени, прехвърляне с изтичане, история на квотата), закъснение при отчитане на присъствие, авто-спрян таймер с бележка, HTMLPurifier (`EditorSanitizer`), 18 нови миграции + 1 променена, 219 нови езикови ключа.
- Upstream включва и поправки по сигурността, които се припокриват с нашите S6/S10: публичните линкове с `{hash}` вместо `{id}`, `throttle:two-factor`, `auth` на `cropper` и `sync-user-permissions`, премахнати debug маршрути, Gate `viewLogViewer`, `require-auth.php` в backup-and-restore.
- Наши промени спрямо 6.0.10: 101 файла са променени и от двете страни; 78 са слети автоматично, 23 бяха в конфликт.
- Production: `vendor/` не е в git и няма composer — vendor се качва изцяло; на практика production vendor вече съвпада с vendor-а на 6.0.19 освен 4 autoload файла (`ezyang/htmlpurifier` е и в двата; `khaled.alshamaa/ar-php` е в `composer.lock`, но липсва и в upstream vendor пакета, и на сървъра — затова `pdfFixArabic()` е с `class_exists` предпазител). Нито една от новите таблици не съществува на сървъра, `subscriptions` е празна (преименуването `name` → `type` е безопасно).

### Лиценз

Двете места с `isLegal()` (входът във `FortifyServiceProvider` и публичната страница за вход в `SuperAdmin/FrontendController`) веднъж дневно изпращат purchase code, домейн и **версията** към сървъра на Froiden. След 6.0.19 отговорът може да е отказ → пренасочване към `/verify-purchase` и блокиран вход. По изискване („оставаме изключени от проверката за лиценза, няма да добавяме ключ“) е добавен превключвател `config('databackd.license_check')` (`DATABACKD_LICENSE_CHECK`, по подразбиране `false`) — `9f05fe3`. Съхраненият purchase code на сървъра **не се пипа**, нов ключ не се добавя. Ръчният merge не минава през лицензирания auto-updater.

## Какво е направено преди агентите

1. `vendor/worksuite` (`ae7d718`) — чист 6.0.19.
2. Клон `upstream/worksuite-v6.0.19` от `main`, `git merge vendor/worksuite` → `4f42ef7`. Решени конфликти: `.env.dev` и `public/backup-and-restore/*` остават изтрити; `config/log-viewer.php` — `super-admin` остава и на UI, и на API (upstream го беше махнал в полза на Gate — auto-merge-ът мълчаливо го махаше и от UI-а); routes — upstream `auth` + нашите коментари; `ClientController::store` — upstream проверка на правата + лимитът на пакета (CUS_000329); `lang/eng/{app,email,modules}` — нашите стойности, новите upstream ключове (проверено програмно: 0 липсващи, 0 променени наши стойности); `sections/menu` — скриването на AFV около новите upstream условия; `sections/topbar` — нашият aria-label; `products/ajax/show` — новият upstream дизайн + реда „Себестойност“ (S8); `package-lock.json`, `public/css/main.css` — upstream (прекомпилират се).
3. `9f05fe3` — превключвателят за лиценза.
4. Работна среда: worktree със собствен `vendor/` (6.0.19 + `composer dump-autoload`), `node_modules` (symlink), тестова база `databackd_upgrade_local` (копие на `databackd_local`, мигрирана до 6.0.19).

## Разпределение (до 15 агента)

Вълна 1 — 12 агента паралелно, всеки притежава своя списък файлове (`assign/<агент>.txt`): за файловете, променени и от двете страни — проверка, че нашите къстомизации (S1–S12) и upstream промените са слети смислово; за upstream файловете — превод (S7), скриване на модули (S1/S8), сигурност (S6), визия (S9).

| # | Агент | Област |
|---|---|---|
| A01 | lang | `resources/lang/{eng,bg}` upstream файлове: 219 нови ключа → английски по правилата на S7 + български превод, `lang-audit` |
| A02 | invoices | фактури, повтарящи се фактури, настройки на фактурите (напомняния, шаблон 6), напомняния (команда, услуга, известия) |
| A03 | pdf | всички PDF шаблони (вкл. новия шаблон 6) — guard-ът на CUS_000328 + `__()` от S7 |
| A04 | sales | оферти, предложения, договори, кредитни известия, поръчки, банкови сметки, плащания, разходи, публичните страници (`HomeController`, `PublicUrlController`) |
| A05 | billing | SaaS абонаменти и платежни шлюзове (Worldpay), супер админ, `routes/SuperAdmin/web.php` |
| A06 | products | продукти (ресурси, мрежа, нов изглед) + „Себестойност“ (S8) |
| A07 | crm | потенциални клиенти, сделки (имейли, шаблони), форми, клиенти |
| A08 | hr | присъствие, отпуски, служители, смени |
| A09 | work | задачи, отчетено време (авто-спрян таймер), обяви, съобщения, файлове |
| A10 | ui | сайдбар/топбар/layout/компоненти/табло/известия, SCSS + `npm run prod` |
| A11 | core | helpers, providers, middleware, модели на ядрото, config, routes, лицензът (проверка), цялостен преглед по сигурността |
| A12 | db | миграциите — съвместимост с нашата схема и пакетните модули (S8), тест на отделна база |

Вълна 2 — A13 regression: smoke на цялото приложение (админ/служител/клиент), `databackd:check-*`, `lang-audit --strict`, `hardcoded-text`, snapshot-и.

Вълна 3 — R1 и R2: независимо код ревю на целия клон (R1 — запазени къстомизации и коректност; R2 — сигурност и готовност за деплой).

После: поправки → commit-и → `main` (fast-forward) → push в GitHub → деплой (backup → `down` → rsync на файловете + целия `vendor/` → `migrate --force` → кешове → `up` → проверка).

## Правила за агентите

`docs/mvp/upgrade-6.0.19/AGENT-RULES.md`. Отчетите — `docs/mvp/upgrade-6.0.19/<NN>-<област>.md`.

## Деплой на production (2026-10-28, 20:02–20:04)

- GitHub: `main` → `663a6e5` (fast-forward от `13be49c`), нов клон `vendor/worksuite` (`ae7d718`, чистите upstream версии за следващия merge).
- Проверки преди деплоя (само четене): MariaDB 11.8; `APP_KEY` не е празен/примерен (по заредената конфигурация); напомнянията за фактури изключени във всички 4 компании; 0 `global_subscriptions` за затваряне; 0 празни `hash`; 0 записан XSS в бележки/продукти; 0 `data:` картинки/таблици в старото съдържание; `app_debug = 0`; няма `froiden-verify/`; сървърните копия на всичките 301 презаписвани файла = `main` (никакви ръчни правки).
- Backup: `~/deploy-backup-databackd-upgrade-6019-20261028/` (chmod 700) — 305 файла, `vendor/composer`, `resources/lang`, `bootstrap/cache`, `.env`, `.htaccess`, пълен dump `db-full.sql.gz`, списъци `deploy-files.txt`, `new-files.txt` (119 нови), `pretend.sql`, `migrate.log`.
- Ред: `artisan down` → rsync `--delay-updates` на 420 файла + 4 файла `vendor/composer/*` (проверено с checksum: 0 разлики) → `mkdir storage/app/htmlpurifier` → `migrate:status` (точно 18 Pending) → `migrate --force` (18/18) → `optimize:clear`, `config:cache`, `route:cache`, `view:clear` → `up`. Престой ~1 мин 16 с.
- След деплоя: 383 миграции; напомнянията остават изключени; `license_check=false`, `app.debug=false`, `version.txt`=6.0.19; ключовите страници = baseline отпреди деплоя; 70/70 AFV страници 200 (1 очакван redirect) + новите (продукти grid, табове напомняния/шаблон); служител и клиент на компания 17 — 200; отвън: `/login` 200, `verify-purchase`/`purchase-verified`/`hide-review-modal`/`down`/`up`/`check-env`/`lead-form`/`ticket-form`/`sync-user-permissions`/`check.php`/`.env` → 404, `/log-viewer` → login, HTTP → HTTPS 301; в `laravel.log` няма записи след деплоя.
- Връщане: с новия код още на сървъра `php artisan migrate:rollback --force` (връща 18-те), после файловете от `files/`, `vendor-composer/`, `lang/`, триене по `new-files.txt`, кешовете; пълно — от `db-full.sql.gz`.
- Локално: основната папка е на `663a6e5`, `composer dump-autoload`, `databackd_local` мигрирана (backup преди това в scratchpad на сесията).
