# Правила за агентите — обновяване до Anagami Finance View 6.0.19

Прочети изцяло, после `00-plan.md` (същата папка), `CLAUDE.md` и `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` (раздели 3 и 4 — списъкът на нашите къстомизации).

## Къде се работи

- **Само** в worktree-а `$W = /private/tmp/claude-501/-Users-tsvet-Documents-Projects-databackd-com/db6ca00b-fbd7-487e-96e1-2ae875b1a9df/scratchpad/ws-merge` (клон `upstream/worksuite-v6.0.19`). Основната папка `/Users/tsvet/Documents/Projects/databackd.com/databackd.com - app` **не се пипа** (там има чужди незаписани файлове).
- Пускай командите с абсолютни пътища или `cd "$W" && …` в същата команда.
- **Git — само четене** (`git diff`, `git show`, `git log`, `git blame`). Никакви `commit`, `add`, `checkout`, `restore`, `stash`, `reset`, `merge`, `rm`. Commit-ите прави координаторът.
- **Без production** (`ssh databackd` е забранен), без локални сървъри (`artisan serve`, `npm run watch`, нищо на порт), без `composer`, без `npm` (освен A10 за `npm run prod`), без кеширащи команди (`optimize`, `config:cache`, `route:cache`, `view:cache`, `optimize:clear`) — работят 12 агента едновременно в една папка.
- База: `.env` в `$W` сочи към `databackd_upgrade_local` (вече мигрирана до 6.0.19). Не пускай миграции (освен A12 на собствено копие), не трий данни.

## Три версии на всеки файл

- база 6.0.10: `37235f8`; наша версия: `main`; upstream 6.0.19: `vendor/worksuite`; резултат: файлът в `$W`.
- Нашите промени: `git diff 37235f8 main -- <файл>` и `git log --oneline 37235f8..main -- <файл>`; upstream промените: `git diff 37235f8 vendor/worksuite -- <файл>`.
- Списъкът ти: `$S/assign/<агент>.txt` (`$S = /private/tmp/claude-501/-Users-tsvet-Documents-Projects-databackd-com/db6ca00b-fbd7-487e-96e1-2ae875b1a9df/scratchpad`). `BOTH` = променен и от нас, и от upstream (вече слят — провери смислово!), `UP` = само upstream.

## Собственост на файлове

- Редактираш **само** файловете от своя списък, нови файлове в своята област и своите езикови файлове `resources/lang/eng/ui-<област>.php` + `resources/lang/bg/ui-<област>.php` (създай ги само ако трябват; същите ключове в двата, валиден PHP масив `return [ ... ];`).
- Upstream езиковите файлове (`app`, `modules`, `messages`, `email`, `clan`, `permissions`, `superadmin`, `placeholders`, `validation`, `auth`, … в `eng/` и `bg/`) са на **A01**. Ако ти трябва промяна там — опиши я в отчета.
- Нужна промяна в чужд файл → **не я прави**; опиши я точно (файл, ред, diff) в отчета под „Искания към други файлове“.
- **Никога** не пипай редовете с `databackd_license_check()` (превключвателят за лиценза — не добавяй purchase code/лиценз, не викай сървърите на Froiden).

## Какво трябва да оцелее (къстомизации)

Маркери в кода: `databackd`, `{{-- databackd --}}`, `databackd ui`, `databackd S6`, `databackd (S8)`, `databackd (S10)`, `WORKSUITESAAS` (CUS_000329). Подробно — `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` §3–4. Накратко:
- **CUS_000329** лимити на пакета (`max_clients`, `max_invoices`); **CUS_000328** guard `@if ($invoiceSetting->locale != 'bg')` в PDF; **CUS_000326** `CustomFieldsTrait`.
- **S1/S8** скриване по `config/databackd.php` (`databackd_hidden(...)`), модулите на пакетите `databackd_*`, „Себестойност“ на продуктите, scheduler-ът.
- **S6** сигурност: без `enable_php`, без `public/backup-and-restore` и `public/check.php`, без debug маршрути, `XssLinearMerge`, `VerifyCsrfTokenPathOnly`, `AuthHardening`, bindings в `UserAuth::validateLoginActiveDisabled()`, `{!! !!}` само за санитизиран HTML; ново поле със суров HTML трябва да е в `config/xss_ignore.php` (иска се от A11).
- **S7** превод: целият видим текст през `__()`/`@lang`; речник и правила за главните букви — `docs/mvp/features/07-translation.md` (английски: Title Case за менюта/заглавия, sentence case за бутони/съобщения/етикети; български: главна буква само в началото). Page title в контролер — ключ, не литерал.
- **S9** визия: класове `dbk-*`, графики само през `dbkChart`, `docs/mvp/phase2/ui-guidelines.md`.
- **S12** (числа по локала, валута на компанията, часова зона) — за AFV екраните; новият upstream код не бива да ги чупи.

## Какво правиш с новия upstream код

1. Чете се като код (не само diff): проверка за грешки при сливането — дублирани методи/use, загубени наши редове, upstream промяна, която обезсмисля наша (виж примера с log-viewer в `00-plan.md`).
2. Превод: твърд английски текст в новите/променените изгледи, JS, `Reply::*`, известия, имейли → `__()`. Ползвай съществуващите ключове (новите upstream ключове се превеждат от A01); липсващите — в своя `ui-<област>.php`.
3. Скрити модули: нов пункт в менюто/настройките/таблото/топбара за модул от `config/databackd.php` → трябва да се скрие по същия начин (ако файлът не е твой — искане в отчета).
4. Сигурност: нови публични маршрути, webhooks, суров HTML, качване на файлове, mass assignment, права (`user()->permission(...)`), multi-tenant scope (`CompanyScope`). Не отслабвай нищо от S6.
5. Не „подобрявай“ upstream код извън нужното за горното — минимални, маркирани промени (`// databackd (upgrade 6.0.19): …`, `{{-- databackd (upgrade 6.0.19) --}}`), за да е лесен следващият merge.

## Проверки

- `php -l` на всеки променен PHP файл.
- Рендиране без сървър: `cd "$W" && php docs/mvp/tools/smoke.php <route-prefix> [--ajax] [--email=...] [--url=/account/...] [--show]` (админ на „Демо Финанс Груп“ по подразбиране; служител `g.petrov@demofinans.bg`, клиент `p.yordanov@balkan.bg`). Визуално при нужда: `php docs/mvp/tools/snapshot.php <url> --out=storage/app/snapshots/<агент>-<име>` и разгледай PNG-то.
- Текст: `php docs/mvp/tools/hardcoded-text.php --list` (филтрирай до своите файлове); `php docs/mvp/tools/lang-audit.php`.
- Сравнявай с handoff-ите (`docs/mvp/handoff/*.md`) — без нови FAIL.

## Отчет

`docs/mvp/upgrade-6.0.19/<NN>-<област>.md` на български: какво е проверено (по файлове), какво е променено (списък), кои къстомизации са потвърдени, намерени проблеми, „Искания към други файлове“ (с точен diff), резултати от проверките, „За деплоя“ (миграции, настройки, команди, рискове). Финалното съобщение към координатора — до 15 реда + пътя на отчета.
