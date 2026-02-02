# A08 — Импорт (Excel/CSV, Google Sheets), история, интеграции, синхронизация

Одит на потока от край до край и UX за потребител без обучение. Парсването и форматите са одитирани от S11 (`docs/mvp/handoff/11-integrations-audit.md`) — тук не се повтарят.

## Какво е проверено

**Роли:** админ (`admin@demofinans.bg`), служител (`g.petrov@demofinans.bg`), клиент (`p.yordanov@balkan.bg` → 403 на всички екрани, както е замислено).

**Реални тестове през HTTP kernel-а (in-process, със сесия и бисквитки, като в браузър)** — скриптове и файлове в scratch папка извън проекта:
- `public/databackd/templates/fin-entries-template.{csv,xlsx}` — качване → съпоставяне → преглед → импорт → резултат → детайл → отмяна: 8/8 реда.
- Всичките 8 шаблона на счетоводните програми (`import-templates/*.csv`) — всички минават; „Бизнес навигатор · хронология“ дава 1 очаквана грешка (обратен запис с отрицателна сума, нарочно в примера).
- Гранични файлове: празен файл (0 байта), само заглавен ред, PDF с разширение .pdf и .csv, CSV с празни редове и грешни редове (невалидна дата, непозната сметка, отрицателна сума), 6000 реда (синхронна опашка, 2 s) и 6000 реда с грешки на редове 1201 и 5556 + празен ред през **реална опашка** (`database`) с проследяване на прогреса (33 % → 66 % → 100 %).
- Повторно качване на същия файл, файл с нестандартни заглавия (запомняне на съпоставянето), изтегляне на редовете с грешки.
- Google Sheets / CSV по URL (`csv_url` към локалния шаблон): изтегляне → съпоставяне → преглед → импорт със „Запомни“ → повторно изтегляне без „Запомни“ (предупреждение за дубликати) → „Синхронизирай сега“ (замества предишния импорт) → изтриване на източника.
- Всички тестови партиди, записи, източници и качени файлове са изтрити (няма осиротели `fin_entries`, няма нови разрези).

**Smoke** (`--ajax`, админ и служител): `databackd.data.import`, `.sources`, `.batches`, `.integrations`, `.sync` + детайл на партида с грешки, детайл на MT940 партида, `import/result`, `sources/result`, `errors-export` → All pages OK. Клиент → 403.

**Снимки** (десктоп + мобилен, `--scss=resources/scss/databackd/_import.scss`): импорт стъпка 1, съпоставяне (с банерите), резултат с грешки, детайл на партида, източници, история, интеграции, синхронизация.

## Намерени и поправени грешки

| # | Файл | Проблем | Поправка |
|---|---|---|---|
| 1 | `FinEntryImporter.php` (`buildTable`, `readCsvString`, `preview`, `import`) | Номерът на реда в грешките беше „позиция сред непразните редове + 2“ — при празни редове, ред `sep=` или заглавни редове на отчет (Микроинвест) сочеше грешен ред в Excel (известен проблем от handoff 02). | `buildTable()` връща `lines` (физическият ред на всеки ред с данни); `preview()`/`import()` ползват опция `lines` (нов `rowNumber()`). Проверено: грешки на редове 5, 6, 8 при празни редове; 1201 и 5556 при 6000 реда в опашка. |
| 2 | `ImportTemplates.php` (`apply`, `applyTrialBalance`, `applyJournal`) | При шаблон редовете се преизчисляват/филтрират и номерът сочеше реда в изчислената таблица (напр. „ред 14“ вместо 30 в примера на Бизнес навигатор). | Номерата на редовете минават през `locateHeader()` и двата layout-а; групирани аналитични редове взимат реда на първия. |
| 3 | `FinImportController::upload` | Празен файл → „трябва да бъде от тип xlsx…“ (подвеждащо); грешно разширение → две съобщения едно под друго. | Празен файл → „Файлът няма заглавен ред или не съдържа редове с данни“; съобщението за тип — само веднъж. |
| 4 | `FinImportController` + `import/index` | Помощният текст обещава 20 MB, а PHP може да позволява по-малко (локално `upload_max_filesize=2M`, `post_max_size=8M`) → файл над лимита пада с общо „не успя да се качи“ или (над `post_max_size`) с 413 преди контролера. | Реален лимит = min(20 MB, upload_max_filesize, post_max_size) в помощния текст, в правилото `max:` и в Dropify (`data-max-file-size`) → проверка в браузъра с ясен текст; `UPLOAD_ERR_INI_SIZE` дава „Файлът е по-голям от N MB…“. |
| 5 | `batches/show`, `batches/index` | Текстовете на SweetAlert се вмъкваха с `"@lang(...)"` в JS низ (кавичка в превода чупи скрипта); потвърждението не казваше колко записа ще се изтрият. | Общ `batches/_undo_script.blade.php` с `@json`; текстът казва броя записи и че справките/таблото ще се преизчислят; бутонът за потвърждение е червен, фокусът е на „Отказ“. |
| 6 | `FinImportBatchController::mappingRows` | Детайлът на партида показваше и служебните ключове на mapping (при банковите извлечения `format`, `file_sha256`… като „колони“). | Показват се само двойки „поле → колона“; за MT940/CAMT.053 — обяснение, че няма колони за съпоставяне. |
| 7 | `FinImportBatchController::index` | Филтърът „Източник“ в историята нямаше MT940/CAMT.053, въпреки че партидите им са в списъка. | Добавени. |
| 8 | `FinDataSourceController::preview` | Прегледът изчисляваше `preview()` два пъти на заявка. | Веднъж. |
| 9 | `integrations/index` | При грешка при създаване на токен — твърд текст „Error“; полето не се маркираше. | Преведен текст; полето получава `is-invalid` + `aria-invalid` и фокус. |

## UX подобрения

- **Повторно качване / дубликати** (досега тихо удвояваше сумите):
  - същият файл (SHA-256) вече импортиран и неотменен → жълто предупреждение в стъпка 2 с дата, № и брой записи + линк към импорта и съвет „първо отменете стария импорт“;
  - в живия преглед: „N от първите M валидни реда вече съществуват в записите (същата дата, сметка, сума и юридическо лице)“ + от кой импорт са (до 3 линка) — хваща и същите данни в друг файл/Google Sheet. За запомнен източник с „Запомни“ (който заменя стария импорт) предупреждението не се показва.
- **Запомняне на съпоставянето:** файл със същите колони като предишен импорт (Excel/CSV) получава съпоставянето от тогава — с банер „Използвано е съпоставянето от предишния импорт… (импорт № N)“. Работи и за изтегления файл с грешките (двете допълнителни колони в края се игнорират).
- **Грешни редове за поправка:** при импорт от файл/Google Sheets към грешката се пазят клетките на реда (до 60 колони × 255 знака, до 500 грешки). „Изтегли редовете с грешки“ (на резултата от импорта, на резултата от Google Sheets и в детайла) дава CSV с оригиналните колони + „Ред във файла“ + „Причини“ — поправя се и се импортира отново. Числата не се префиксват с `'`; текстът остава защитен от CSV injection. Старите партиди/API — старият формат (ред + причини).
- **Отмяна направо от резултата** на импорта (същото потвърждение).
- **Google Sheets:** „Синхронизирай сега“ иска потвърждение с последствието („записите от последния импорт (N) ще бъдат заменени…“); грешка в URL полето се показва под полето (`is-invalid`, `aria-invalid`); бутонът за изтриване има `aria-label` с името на източника.
- **Drag & drop:** видимо състояние `.is-dragover` (Dropify няма свое), червена рамка при грешен файл; съобщения на Dropify за размер/тип на български.
- **Мобилен (390 px):** таблицата за съпоставяне става карти (име на колона, примерни стойности, падащо меню на цяла ширина, 40 px) — преди менюто беше извън екрана; запазените източници също са карти, „Синхронизирай сега“ е видим.
- Детайл на партида: до „Колона A“ се вижда и името на колоната от файла.

Проверено и наред (без промени): стъпките (stepper) и блокирането на бутоните при заявка (`easyAjax disableButton`), защита от двоен импорт (session + `Cache::add`), прогрес и автоматичен worker при опашка, празни състояния (история, източници, синхронизация, грешки), шаблоните за сваляне и списъкът с колони, токени (само админ, показва се веднъж, отнемане с потвърждение), документация на API.

## Нови SCSS partial-и и lang ключове

- **Нов** `resources/scss/databackd/_import.scss` — координаторът да го импортира в `main.scss` след `_data.scss`. (Ползва `:has()` за мобилния изглед на две таблици — без него таблицата остава с хоризонтален скрол, т.е. нищо не се чупи.)
- `databackd-data-import.php` (bg+eng): `upload.too_big`, `upload.wrong_type`, `mapping.remembered`, `mapping.same_file`, `mapping.same_file_link`, `preview.duplicates`, `preview.duplicates_batches`, `preview.duplicates_batch`, `preview.duplicates_manual`, `result.errors_download`, `result.errors_download_help`, `result.undo`, `messages.file_too_big`; `upload.file_help` вече е с `:size`.
- `databackd-data-batches.php`: `undo.confirm_text_count`, `meta.mapping_bank`, `csv.file_row`; `actions.export_errors` → „Изтегли редовете с грешки (CSV)“.
- `databackd-data-sources.php`: `list.sync_confirm_title`, `list.sync_confirm_replace`, `list.sync_confirm_new`.
- `databackd-data-integrations.php`: `tokens.failed`.

## Миграции

Няма. Служебните данни на партидата се пазят в JSON колоната `fin_import_batches.mapping` под ключове с `_` (`_headers`, `_headers_sig`, `_file_sha256`) — същият подход като `file_sha256` на банковия импорт (S8). Кодът, който показва mapping-а, ги пропуска; API-то (`FinEntryApiController`) не е засегнато (`importRecords()` не ги записва, отговорът на API строи грешките си без `values`).

## Нужни промени извън моята област

1. `resources/scss/main.scss` — `@import 'databackd/import';` (след `databackd/data`), после `npm run prod`.
2. **Production `php.ini`**: `upload_max_filesize` ≥ 20M и `post_max_size` ≥ 21M, иначе екранът честно ще показва по-малкия лимит (локално е 2 MB). Само четене — да се провери от координатора/S10.
3. `.dbk-stepper` (`_components.scss`) — на 390 px третата стъпка излиза извън картата (вижда се „Им… рез…“); предложение: на ≤575 px да се показва само номерът за неактивните стъпки или `overflow-x:auto`.
4. На снимките сайдбарът е с разпаднати подменюта (текстът на подпунктовете е на един ред под пунктовете) — вероятно незавършена работа на агента по сайдбара/компилирани стилове; не е от моята област.
5. Глобалният exception handler: `PostTooLargeException` (413) при AJAX да връща `Reply::error` с текст за размера — сега клиентската проверка го предотвратява, но без JS (или при голям POST от друго място) потребителят вижда общо съобщение.

## Отворени въпроси / неправено

- Запомнянето на съпоставянето е по точно съвпадение на заглавията (същият ред на колоните); при шаблон на счетоводна програма не се прилага (шаблонът сам попълва).
- Проверката за дубликати гледа първите 50 валидни реда на файла (бързо, една заявка) — не целия файл.
- Сесията изтича → в стъпка 2 името на файла е техническото (uuid); хешът за „същия файл“ вече се изчислява наново, така че предупреждението остава.
- Google Sheets: без OAuth и без планирана синхронизация (както в S11); синхронизацията от фактури/разходи не е пипана (одитирана от S11).
- „Създай липсващите разрези“ остава винаги включено при „Синхронизирай сега“ (поведение от S2).

## Допълнение: забележки на A13 (`13-api-access.md`, „Нужни промени извън моята област“, т. 1–3)

| # | Файл | Проблем | Поправка |
|---|---|---|---|
| 10 | `IntegrationController.php` (`activeCards`, `platformCards`), `integrations/index.blade.php` | Без модула `databackd_data` в пакета картите Excel, CSV, четирите счетоводни програми и „Банкови извлечения“ водеха към 403. | `databackd_module('databackd_data')`. Без модула картите остават видими, но без линк, със значка „Не е в пакета“ и обяснение („Модулът „Данни“ не е включен във вашия пакет…“). Google Sheets и API са към модула на самата страница (`databackd_integrations`). Проверено in-process с изключен модул (кешът на модулите е само локален за процеса): 7 заключени карти, 0 линка към импорта и банковия импорт; с модула — 0 заключени, 7 линка. |
| 11 | `integrations/index.blade.php`, `databackd-data-integrations.php` (`docs.*`), `IntegrationController::index` (`$apiLimits`) | Документацията на API-то не съвпадаше напълно с кода. | Сверено с `FinApiGate`, `FinApiTokenAuth`, `FinEntryApiController`, `RouteServiceProvider::configureRateLimiting` и `config/databackd-api.php`. Добавено: **405** (`Allow: POST`); **403** и при заявка без HTTPS; **422** и при над `max_new_dimensions` (200) нови стойности на разрези и при Idempotency-Key с друго тяло или друг query string; **429** и при `failed_per_ip_per_minute` (10) неуспешни опита от един IP и при 60 заявки/мин от един IP (`throttle:api`). Също: Idempotency-Key важи само за токена, с който е изпратен; `create_missing_dimensions` може да е и в тялото `{"entries": [...], "create_missing_dimensions": true}`; ограничения на полетата (текст до 2000 знака; контрагент и описание се записват до 191, номерът на документа до 60). Числата идват от конфигурацията. |
| 12 | `FinEntryImporter.php` (`validateRow`, нов `baseFactor()`) | Валута без известен курс (напр. `XXX`, `JPY`) се записваше с `amount_base = amount`, т.е. тихо 1:1 спрямо базовата валута. | При импорт (файл, Google Sheets, API) такъв ред вече е грешка: „Няма курс за :currency към :date. Добавете курса във „Валутни курсове“ или посочете сумата в базовата валута на фирмата.“ `toBase()` пази досегашното поведение за другите си извикващи (ръчно въвеждане, синхронизация от фактури), за да не се чупят те. Тест: EUR/BGN/USD → импортирани; XXX, JPY → грешки на редове 3 и 6. |
| 13 | `FinEntryImporter::recordStructureErrors()` | Грешките за структурата на API записите бяха само на английски. | Минават през `__('databackd-data-import.row_errors.*')` (bg+eng). Тест през `importRecords()`: всички 4 вида съобщения са на български, номерата на редовете са непроменени. |

Нови lang ключове (bg+eng):
- `databackd-data-import.row_errors.{currency_no_rate, record_not_object, field_type, field_too_long, record_empty}`;
- `databackd-data-integrations.docs.{code_405, option_create_body, fields_limits}` и `badges.{locked, locked_hint}`;
- променени: `docs.code_403`, `docs.code_422` (с `:dimensions`), `docs.code_429` (с `:failed_ip` и `:per_ip`), `docs.idempotency_text`.

Smoke след допълнението: `databackd.data.integrations`, `.import`, `.batches`, `.sources` `--ajax` като админ и служител → All pages OK. Тестовите партиди са изтрити.

Допълнително променени файлове: `app/Http/Controllers/Databackd/Data/IntegrationController.php` (иначе вече изброените: `FinEntryImporter.php`, `integrations/index.blade.php`, `resources/lang/{bg,eng}/databackd-data-{import,integrations}.php`).

## Пълен списък с променени/нови файлове

Променени:
- `app/Services/Databackd/Data/FinEntryImporter.php`
- `app/Services/Databackd/Data/ImportTemplates.php`
- `app/Http/Controllers/Databackd/Data/FinImportController.php`
- `app/Http/Controllers/Databackd/Data/FinDataSourceController.php`
- `app/Http/Controllers/Databackd/Data/FinImportBatchController.php`
- `app/Http/Controllers/Databackd/Data/IntegrationController.php`
- `app/DataTables/Databackd/Data/FinImportBatchesDataTable.php` (само `data-entries` на „Отмени импорта“)
- `resources/views/databackd/data/import/index.blade.php`
- `resources/views/databackd/data/import/mapping.blade.php`
- `resources/views/databackd/data/import/_preview.blade.php`
- `resources/views/databackd/data/import/result.blade.php`
- `resources/views/databackd/data/batches/index.blade.php`
- `resources/views/databackd/data/batches/show.blade.php`
- `resources/views/databackd/data/sources/index.blade.php`
- `resources/views/databackd/data/sources/result.blade.php`
- `resources/views/databackd/data/integrations/index.blade.php`
- `resources/lang/bg/databackd-data-import.php`, `resources/lang/eng/databackd-data-import.php`
- `resources/lang/bg/databackd-data-batches.php`, `resources/lang/eng/databackd-data-batches.php`
- `resources/lang/bg/databackd-data-sources.php`, `resources/lang/eng/databackd-data-sources.php`
- `resources/lang/bg/databackd-data-integrations.php`, `resources/lang/eng/databackd-data-integrations.php`

Нови:
- `resources/views/databackd/data/batches/_undo_script.blade.php`
- `resources/scss/databackd/_import.scss`
- `docs/mvp/audit-s12/08-import.md`
