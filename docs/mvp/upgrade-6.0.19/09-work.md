# A09 — задачи, отчетено време, обяви, съобщения, файлове (обновяване 6.0.10 → 6.0.19)

Обхват: 43 файла от `assign/A09-work.txt` (9 BOTH, 34 UP). Нов езиков файл `ui-work.php` **не е нужен** — всички текстове са с вече съществуващи ключове.

## 1. BOTH файлове — сравнение на трите версии (база `37235f8`, `main`, `vendor/worksuite`, резултат в `$W`)

Всичките 9 са слети коректно: резултатът = upstream + нашата промяна, нищо не е загубено, няма дублирани редове.

| Файл | Наше (S6/S7) | Upstream 6.0.19 | Резултат |
|---|---|---|---|
| `app/Http/Controllers/TimelogController.php` | S7: `'Invalid date or time format.'` → `__('ui-core.messages.invalid_date_time')` | `calculateTime()` пренаписан (try/catch, `status: incomplete`), `stopTimer` записва след затваряне на паузата, нови `autoStoppedMemoAlert()` / `saveAutoStoppedMemo()`, `absDiff*` с `round()` | ✅ нашият `__()` е в новия `catch` блок; другото е upstream |
| `app/DataTables/NoticeBoardDataTable.php` | S7: `Str::ucfirst(__('app.to'))` | `e($row->heading)` | ✅ и двете |
| `app/Notifications/DailyTimeLogReport.php` | **S6: махнат `enable_php`** | `loadView` → `pdfLoadHtml(...)` | ✅ `enable_php` го няма (`grep` по `app/`, `config/dompdf.php` = `false`, `databackd:check-security --only=static` 26/26); `timelog-report.blade.php` няма `<script type="text/php">`; `pdfLoadHtml()` слага `DejaVu Sans` (кирилицата е ОК) |
| `resources/views/gantt.blade.php` | S7: `ui-misc.http.{403,404,500}` | `route('front.gantt_data', $project->hash)` | ✅ и двете. Бележка: изгледът не се рендира от никой контролер (публичният gantt е `gantt_dhtml`) |
| `resources/views/messages/create.blade.php` | S7: `aria-label="{{ __('app.close') }}"` | Dropzone `successmultiple` + `queuecomplete` (`uploadMultiple: true` е зададен) | ✅ |
| `resources/views/notices/ajax/edit.blade.php` | S7: `ui-misc.common.notify` | `{!! clean_editor($notice->description) !!}` | ✅ |
| `resources/views/tasks/ajax/create.blade.php` | S7: 6 × `ui-core.tasks.duplicate_*` | `$projectRequired` (+ `$this->taskSetting` в `TaskController::create`) | ✅ `($taskSetting->project_required ?? 'no')` не гърми и когато изгледът се ползва без `$taskSetting` |
| `resources/views/tasks/ajax/show.blade.php` | S7: `app.inactive`, `ui-core.tasks.view_all_members`, `trans_choice('ui-core.tasks.people_assigned')`, `app.close` | `loggedMinutes()`, `data-time-id`, `window.runTaskClock` | ✅ |
| `resources/views/timelogs/ajax/active_timer.blade.php` | S7: `app.close` | `$myActiveTimer->hours`, `loggedMinutes()` | ✅ |

## 2. UP файлове — прочетени като код

- **Отчетено време (общо за всички):** upstream изнася изчислението в `ProjectTimeLog::loggedMinutes()/loggedSeconds()/completedBreakMinutes()/formatHoursMinutes()` и го ползва в `TimeLog*DataTable` (4), `TasksDataTable`, `RecurringTasksDataTable`, `ArchiveTasksDataTable`, `WaitingForApprovalDataTable`, `ProjectTimeLogsExport`, `ProjectwiseTimeLogExport`, `TimelogReportController`, `ProjectProgress`, `ProjectTimelogObserver`, `front/tasks/ajax/show`. Проверено, че методите съществуват и няма останали стари променливи (`$breakMinutes` в изгледите вече не се ползва). Без нов твърд текст (`formatHoursMinutes` е през `__('app.hrs')`/`__('app.mins')`).
- **`TasksDataTable` / `RecurringTasksDataTable`:** колоната „Изчаква одобрение“ се търси веднъж (`$waitingApprovalId`), `fnDrawCallback` е опростен — без текст.
- **`AutoStopTimer`:** записва `auto_stopped = 1`, затваря отворената пауза, `$admin?->id`. Заявките са с изричен `company_id` (конзолен код). Командата е в scheduler-а (`everyThirtyMinutes`).
- **Бележка за авто-спрения таймер** (`autoStoppedMemoAlert`, `saveAutoStoppedMemo`, `SaveAutoStoppedMemo`, `timelogs/auto-stopped-memo`, `EmployeeDashboard::$autoStoppedTimelog`): **права — само собственият таймер** (`abort_403($timeLog->user_id != user()->id || !$timeLog->auto_stopped)` и в двата метода, `findOrFail` е под `CompanyScope`, контролерът изисква модул `timelogs`). Проверено с реални заявки през HTTP kernel-а в транзакция (rollback): собственикът — 200; админ/друг потребител — 403; запис за чужд таймер — 403; празна бележка — 422; след запис `auto_stopped = 0` и повторното отваряне е 403. `memo` минава през `XssLinearMerge` (strip_tags). Поведение за деплоя: след авто-стоп служителят вижда модала на таблото при всяко влизане, докато не запише бележка.
- **Обяви:** `strip_tags($request->heading)` (store/update), `heading` `max:255`, `e()` в `NewNotice`/`NoticeUpdate`, `strip_tags` на описанието в Slack. Проверено с реален запис през `NoticeController::store` (известията — `Notification::fake()`, транзакция с rollback): заглавие `Обява <b>важна</b> & "тест"` → записва се `Обява важна & "тест"`, в таблицата е `&amp; &quot;` (екранирано веднъж, не двойно).
- **`EditorSanitizer` (нов HTMLPurifier) + нашият `XssLinearMerge`:** в моите файлове — `trim_editor()` в `NoticeController` (2) и `TaskController` (3), `clean_editor()` в `notices/ajax/edit`, плюс `x-cards.data-row html` (show изгледите). `description` е в `config/xss_ignore.php`, т.е. `XssLinearMerge` не го пипа и HTMLPurifier е единственият филтър — **не се чупи**: удебелен/курсив/подчертан/зачеркнат, `ql-align-*`, `ql-indent-*`, `ql-size-*`, цвят, заглавия, цитат, връзки (`rel=noopener`), изображения, YouTube iframe се запазват; `<script>`, `onerror`, `onclick`, `javascript:` се махат; резултатът е идемпотентен (`clean_editor(saved) === saved`). **Две загуби от upstream** — виж „Намерени проблеми“ 1 и 2.
- **Съобщения:** `MessageFileController::store` проверява `message_id` и сетва `company_id`; `UserchatFileObserver` — fallback за `company_id`; `messages/index` — CSS + Dropzone събития. Без нов текст.
- **`EmployeeDashboard`:** `$autoStoppedTimelog` + нова валидация на координатите при отбелязване на присъствие (`AttendanceLocation`, `AttendanceWorkFrom` — съществуват); махнат `info($this->totalTime)`. Ключовете `messages.invalidLocationCoordinates`, `messages.workFromTypeNotAllowed` вече са и в `bg/` (A01).
- **`FileController` / `ImageController`:** upstream добавя `Common::assertSafeStoragePath()` (без `..`, абсолютни пътища, `C:`) и `basename()` в `getImage`; `cropper` е с `auth` (`$element` се извежда с `{{ }}` в JS низ — безопасно). Допълнителни проблеми и поправки — виж т. 3.
- **`public/monitor/installers/manifest.json`:** `{"version": "1.0.0", "released_at": "2026-05-13", "platforms": []}` — **без външни линкове**, нищо в ядрото не го чете (за модула Monitor, който не е инсталиран); `.gitignore` вече изключва инсталаторите (`*.dmg/.deb/.exe`). Публичен статичен файл без чувствителни данни — може да остане.
- **`tests/Unit/MonitorScreenshotDayBoundsTest.php`:** ползва `Modules\Monitor\...` (не е инсталиран) → би дал грешка при `php artisan test`; добавен е skip (т. 3).
- **Скрити модули:** обявите/съобщенията остават скрити от менюто и таблото по `config/databackd.php` (`menu.notices/messages`, `dashboard_widgets.notices`); новият upstream код в моите файлове не добавя пункт в менюто/таблото/настройките.

## 3. Какво е променено (7 файла в 6 точки, всичко маркирано `databackd (upgrade 6.0.19)`)

1. `resources/views/timelogs/auto-stopped-memo.blade.php` — `aria-label="Close"` → `{{ __('app.close') }}` (S7).
2. `resources/views/timelogs/ajax/create.blade.php`, `edit.blade.php` — upstream промени реда с общото време; `" Hrs " / " Mins"` → `__('app.hrs')` / `__('app.mins')` (рендира се „5 ч 30 мин“; проверено в изхода).
3. `app/Http/Controllers/FileController.php` — **сигурност**: публичният `file/{type}/{path}` приема само папките и разширенията на маскираните изображения от `HasMaskImage` (`app-logo`, `avatar`, `favicon`, `login-background`, `offline-method`, `invoice-template`, `front/homepage-background`, `front/seo-detail`; png/jpg/jpeg/gif/webp/bmp/ico). Причина: `Common::encryptDecrypt()` приема и стария ключ `worksuite`/`froiden` (еднакъв за всички инсталации), така че всеки може да подправи `{path}`; на S3 маршрутът подписваше временен URL за **произволен обект в bucket-а** (файлове на други компании). При локален диск рискът е малък (пренасочва към `user-uploads`, което и без това е публично). Тест: валиден път с новия и стария ключ → 302; `project-files/...pdf`, `message-files/...png`, `../../.env`, `app-logo/dump.sql` → 404.
4. `app/Http/Controllers/ImageController.php` — **сигурност**: `store` (качване от редактора) валидира `image` като изображение (`mimes:jpg,jpeg,png,gif,webp,bmp`); преди всеки разрешен тип файл (pdf, xlsx…) отиваше в публичната `quill-images`. Клиентът и без това праща само изображения. Тест: `.txt` → 422, нищо не е записано.
5. `app/Http/Controllers/MessageFileController.php` — **сигурност (IDOR, съществуваше и в 6.0.10)**: `store` — файлове само към собственото съобщение (`user_one`); `destroy` — само качилият файла (UI-ят показва „Изтрий“ само на него); `download` — само двамата участници в разговора (`md5(id)` се отгатва). Тест: изтриване от трети потребител и от получателя → 403.
6. `tests/Unit/MonitorScreenshotDayBoundsTest.php` — `markTestSkipped`, ако `Modules\Monitor` липсва.

## 4. Намерени проблеми

1. **Upstream регресия — споменаванията (@) се губят при редакция.** `EditorSanitizer` позволява `span[class|style]` и маха `data-id`/`data-value`/`data-denotation-char`. При създаване ID-тата се вземат от DOM-а на редактора (работи), но при редакция `tasks/ajax/edit` чете `$('#description span[data-id]')` от вече пречистения запис → `mention_user_ids` е празно → `TaskObserver::updating()` прави `mentionUser()->sync('')` → споменатите губят връзката (и правото да виждат задачата през `mentionTask`, `TaskController:1128`). Засяга също коментари и бележки със споменавания. → искане към A11 (т. 5.1).
2. **Upstream — блоковете с код (`<pre class="ql-syntax">`) стават обикновен текст** (`pre` липсва в списъка). → искане към A11 (т. 5.1).
3. **Пре-съществуващо, не от обновяването:** `data-content="{!! ... !!}"` в 17 изгледа (вкл. моите `tasks/ajax/create:145`, `tasks/ajax/show:311`) — име на проект/етикет с `"` излиза от атрибута (`XssLinearMerge` маха таговете, но не кавичките). Не е пипано (общ шаблон в чужди файлове) — за R2.
4. **Локална среда:** MySQL 9.6 няма `MD5()` → всички `whereRaw('md5(id) = ?')` (25 контролера, вкл. `message_file.download`) дават 500 **само локално**; проверката в `download` е потвърдена по кода. На production да се провери версията на MySQL/MariaDB (там `MD5()` съществува в 8.x и MariaDB).
5. Демо служителят `g.petrov@demofinans.bg` няма нито един ред в `user_permissions` → 403 на `tasks.index`, `tasks.create`, `timelogs.index`, `timelogs.create` и т.н. — от данните, не от кода (същото е и в `databackd_local`).

## 5. Искания към други файлове

1. **A11 — `app/Helper/EditorSanitizer.php`** (проверено в отделен скрипт с HTMLPurifier от `vendor/`): да се пазят атрибутите на споменаванията и блоковете с код:
   ```diff
   -            'span[class|style]',
   +            'span[class|style|data-id|data-value|data-index|data-denotation-char|data-link]', // databackd (upgrade 6.0.19): Quill mentions
   +            'pre[class|spellcheck]',                                                         // databackd (upgrade 6.0.19): Quill code block
   @@
            $config->set('Cache.SerializerPath', $cachePath);
   +        // databackd (upgrade 6.0.19): HTMLPurifier does not know data-* / spellcheck - declare them (cached by ID/Rev)
   +        $config->set('HTML.DefinitionID', 'databackd-editor');
   +        $config->set('HTML.DefinitionRev', 1);
   +        if ($def = $config->maybeGetRawHTMLDefinition()) {
   +            foreach (['data-id' => 'Number', 'data-value' => 'Text', 'data-index' => 'Text', 'data-denotation-char' => 'Text', 'data-link' => 'URI'] as $attr => $type) {
   +                $def->addAttribute('span', $attr, $type);
   +            }
   +            $def->addAttribute('pre', 'spellcheck', 'Enum#true,false');
   +        }
   ```
   Резултат от пробата: `<span class="mention" data-denotation-char="@" data-id="5" data-value="Иван">@Иван</span>` и `<pre class="ql-syntax" spellcheck="false">` се запазват; `data-id="5 or 1"`, `data-link="javascript:…"` и `onclick` се махат.
2. **A11 — `app/Helper/Common.php`** (за сведение): fallback-ът към стария общ ключ трябва да остане (старите `quill-image/{…}` в записаните описания са криптирани с него); след ограничението в `FileController` той не дава достъп до чужди файлове. `ImageController::getImage` е ограничен до `quill-images/` с `basename()`.
3. **A04 — `app/Http/Controllers/HomeController.php:451`** (пре-съществуващо, по желание): `abort_403('Public gantt chart is disabled for this project.')` — твърд английски текст на публична страница.
4. **A13** — smoke базата: да се отчетат т. 4.4 и 4.5 като известни (не нови FAIL).

## 6. Проверки

- `php -l` — всички 30 PHP файла от списъка (без Blade): без грешки.
- `smoke.php` (админ `admin@demofinans.bg`):
  - `tasks. --ajax` — 12 OK, 2 FAIL `tasks.clientDetail` без `?id=` (404, очаквано; с `?id=1` — 200).
  - `timelogs. --ajax` — 16 OK, 2 FAIL `timelogs.export` без параметри (известно от handoff 07).
  - `notices.`, `messages.` — всичко OK; `--url`: `tasks/1`, `tasks/1/edit`, `tasks/create?duplicate_task=1`, `timelogs/create`, `timelogs/1/edit`, `show-active-timer`, `stopper-alert/1`, `projects/1?tab={gantt,tasks,timelogs}`, `time-log-report`, `time-log-consolidated-report`, `time-log-project-wise-report`, `time-log-weekly-report`, `notices`, `messages`, `messages/create`, `dashboard`, публичните `/task/{hash}` и `/gantt-chart/{hash}` — всичко 200 (+ ajax).
- `smoke.php` като служител `g.petrov@demofinans.bg`: `messages.` 3/3 OK; `dashboard`, `show-active-timer`, `messages`, `messages/create` — 200; `tasks.`/`timelogs.`/`notices.` — 403 по данни (т. 4.5) + известните `clientDetail`/`export`.
- Собствени скриптове в `/private/tmp/claude-501/-Users-tsvet-Documents-Projects-databackd-com/db6ca00b-fbd7-487e-96e1-2ae875b1a9df/scratchpad/a09/` (транзакция + rollback, известия `fake()`, пускат се с `php <скрипт> "$W"`): `memo_test.php` (11/12 — единственият FAIL е `MD5()` на MySQL 9.6), `notice_test.php` (6/7 — FAIL е загубеният `<pre>`, т. 4.2), `file_test.php` (7/7), `sanitize_test.php` (XssLinearMerge + HTMLPurifier).
- `hardcoded-text.php --list` за моите файлове: остават само фалшиви попадения (условия `@if (... user_roles())`, `GitLab`, JS с `${response.message}`); `aria-label="Close"` е поправен. Ключовете от моите файлове съществуват в `eng/` и `bg/` (собствена проверка).
- `databackd:check-security --only=static` — 26/26 OK.

## 7. За деплоя

- Миграция `2026_08_10_131500_add_auto_stopped_to_project_time_logs_table` (колона `auto_stopped`) трябва да е пусната преди първото `auto-stop-timer` (scheduler, на 30 мин) — иначе командата и `EmployeeDashboard` гърмят.
- `storage/app/htmlpurifier/` трябва да може да се записва от PHP (кеш на HTMLPurifier, `EditorSanitizer` го създава с `@mkdir`); `vendor/ezyang/htmlpurifier` идва с целия `vendor/`.
- Ново поведение: служител с авто-спрян таймер вижда модал „Добавяне на бележка“ на таблото, докато не я запише.
- Ако някога се мине на S3 за файловете — ограничението в `FileController` е задължително (т. 3.3); при нови модули, ползващи `HasMaskImage` с друга папка, да се добави в `MASKED_IMAGE_DIRS`.
- Без кеширащи команди тук; на production — обичайните кешове след деплоя.
