# Сесия 2 — Въвеждане на финансови данни и импорт (F3, F4, F5)

Прочети `docs/mvp/COMMON-RULES.md` (особено „Работа с до 10 паралелни агента“), `docs/mvp/00-gap-analysis.md`, **всички файлове в `docs/mvp/handoff/`** (задължително `01-foundation.md`) и `CLAUDE.md`. Ти си **S2** — оркестратор. Без сървър, без branch, без PR. S1 е готова (модели `FinEntity`, `FinAccount`, `FinDimension`, `FinEntry`, `FinImportBatch`, `FinancialDataService`). Паралелно работят S3, S4, S5 — пипай само своите файлове.

**Пространство на S2** (всичко ново тук е твое): route файлове `routes/databackd/data-*.php` и `routes/databackd/api/**`, route имена `databackd.data.*`, menu файлове `resources/views/databackd/menu/1N-data-*.blade.php`, views `resources/views/databackd/data/**`, `app/Http/Controllers/Databackd/Data/**`, `app/Http/Requests/Databackd/Data/**`, `app/DataTables/Databackd/Data/**`, `app/Imports/Databackd/**`, `app/Exports/Databackd/Data/**`, `app/Jobs/Databackd/**`, `app/Http/Middleware/Databackd/**`, `app/Services/Databackd/Data/**`, `app/Models/Databackd/{FinDataSource,FinApiToken}.php`, lang `resources/lang/{eng,bg}/databackd-data-*.php`, миграции `*_databackd_data_*.php`, seeders `database/seeders/Databackd/Demo/41DataDemoSeeder.php`, `public/databackd/templates/**`, `docs/mvp/{screenshots,features}/02-data.md`, `docs/mvp/handoff/02-data.md`.

## Фаза 0 — ти, преди агентите
1. Миграции + модели: `fin_data_sources` (company_id, type enum(google_sheets, csv_url, excel), name, url, sheet, mapping json, default_entity_id, last_synced_at, last_batch_id, created_by), `fin_api_tokens` (company_id, name, token_hash, last_four, last_used_at, revoked_at, created_by). Ако ти трябва колона в таблиците на S1 — нова миграция `*_databackd_data_*`, не редактирай старите. `php artisan migrate`.
2. **`App\Services\Databackd\Data\FinEntryImporter`** — ядрото, което ползват Excel, Google Sheets и API (пакети P5, P6, P8):
   - `readFile(UploadedFile|string $path): array` → `['headers' => [...], 'rows' => [[...], ...]]` (xlsx/xls/csv през maatwebsite/excel; csv с автоматичен разделител `,` `;` `\t`, UTF-8 и Windows-1251);
   - `readCsvString(string $csv): array` (същия формат);
   - `fields(): array` — полетата за mapping (`entry_date, account_code, amount, kind, entity, region, client_segment, product_group, cost_center, counterparty_name, document_no, description, currency`) с BG/EN етикети;
   - `guessMapping(array $headers): array` (BG и EN синоними, напр. „Дата“, „Сметка“, „Сума“, „Регион“…);
   - `validateRow(array $row, array $mapping, array $options): array` → `[normalizedData|null, errors[]]` (дати d.m.Y / Y-m-d / Excel serial, суми с `,` или `.`, сметка по код);
   - `import(array $rows, array $mapping, array $options): FinImportBatch` — `options`: `entity_id, source, file_name, source_url, create_missing_dimensions (bool), batch_id (за продължение)`; записва `fin_entries` с `import_batch_id` и попълва batch-а (rows_total/imported/failed, errors json, status).
   - `undo(FinImportBatch $batch): int`.
   Направи го работещо (не само сигнатури) и провери с малък CSV през `tinker`.
3. Lang файл `resources/lang/{eng,bg}/databackd-data-common.php` с общите думи (полетата от `fields()`, видове източници, статуси на импорт).

## Фаза 1 — пакети (пусни ги наведнъж, до 10 агента)

| Пакет | Задача | Притежава (освен своите lang `databackd-data-<пакет>.php`) | Menu файл → пункт |
|---|---|---|---|
| P1 entries | Задача 1А | `routes/databackd/data-entries.php`, `Controllers/Databackd/Data/FinEntryController.php`, `Requests/Databackd/Data/FinEntry/**`, `DataTables/Databackd/Data/FinEntriesDataTable.php`, `Exports/Databackd/Data/FinEntriesExport.php`, `views/databackd/data/entries/**` | `10-data-entries` → „Финансови записи“ |
| P2 bulk | Задача 1Б | `routes/databackd/data-bulk.php`, `Controllers/.../Data/FinEntryBulkController.php`, `views/databackd/data/bulk/**` | `11-data-bulk` → „Множествено въвеждане“ |
| P3 accounts | Задача 2 — сметкоплан | `routes/databackd/data-accounts.php`, `Controllers/.../Data/FinAccountController.php`, `Requests/.../Data/FinAccount/**`, `views/databackd/data/accounts/**` | `16-data-accounts` → „Сметкоплан“ |
| P4 dimensions | Задача 2 — разрези + юр. лица | `routes/databackd/data-dimensions.php`, `Controllers/.../Data/{FinDimension,FinEntity}Controller.php`, `Requests/.../Data/{FinDimension,FinEntity}/**`, `views/databackd/data/{dimensions,entities}/**` | `17-data-dimensions` → „Разрези“, „Юридически лица“ |
| P5 import | Задача 3 | `routes/databackd/data-import.php`, `Controllers/.../Data/FinImportController.php`, `Jobs/Databackd/ImportFinEntriesJob.php`, `views/databackd/data/import/**` (вкл. споделения partial за mapping `_mapping.blade.php`), `public/databackd/templates/**` | `12-data-import` → „Импорт на данни“ |
| P6 sheets | Задача 4 | `routes/databackd/data-sources.php`, `Controllers/.../Data/FinDataSourceController.php`, `Services/Databackd/Data/GoogleSheetsReader.php`, `views/databackd/data/sources/**` | `13-data-sources` → „Google Sheets и източници“ |
| P7 history | Задача 3 — история на импортите + отмяна | `routes/databackd/data-batches.php`, `Controllers/.../Data/FinImportBatchController.php`, `DataTables/Databackd/Data/FinImportBatchesDataTable.php`, `views/databackd/data/batches/**` | `14-data-batches` → „История на импортите“ |
| P8 integrations | Задача 5 — страница „Интеграции“, API токени, REST endpoint | `routes/databackd/data-integrations.php`, `Controllers/.../Data/{Integration,FinApiToken,Api/FinEntryApi}Controller.php`, `Middleware/Databackd/FinApiTokenAuth.php`, `views/databackd/data/integrations/**` | `15-data-integrations` → „Интеграции“ |
| P9 sync | Задача 5 — „Прехвърли от фактури и разходи“ | `routes/databackd/data-sync.php`, `Controllers/.../Data/FinSyncController.php`, `Services/Databackd/Data/InvoiceExpenseSync.php`, `views/databackd/data/sync/**` | — (бутонът е в страницата на P8, показва се с `Route::has('databackd.data.sync.run')`) |
| P10 demo | Демо данни | `database/seeders/Databackd/Demo/41DataDemoSeeder.php`, `storage/app/databackd-demo/**` (примерни файлове) | — |

Бележки за brief-ите:
- P6 ползва `_mapping.blade.php` на P5 чрез `@include` — дай на P6 точното име на partial-а и променливите му (`$headers`, `$rows`, `$fields`, `$mapping`); ако P6 приключи преди P5, да направи собствен временен mapping, който в Фаза 2 се заменя.
- **API route:** loader-ът на S1 зарежда всичко в `routes/databackd/*.php` с префикс `account` и web/auth middleware — не става за API. Затова **в Фаза 0** (ти, преди агентите) добави в `app/Providers/RouteServiceProvider.php` минимално зареждане на `routes/databackd/api/*.php` с middleware `api` и префикс `api` (без `auth`), маркирано с `// databackd`, и го впиши в `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` до другите AFV промени. Това е единственото място, където S2 пипа файл на S1 (другите сесии не го пипат). P8 притежава `routes/databackd/api/fin-entries.php` с `Route::prefix('databackd')->middleware(FinApiTokenAuth::class)` → `POST /api/databackd/fin-entries`.

## Задачи (текстът, който копираш в brief-ите)

### Задача 1А — Финансови записи (F3 „всеобхватно въвеждане“)
- Списък (yajra DataTable, стил като `expenses`): дата, юр. лице, сметка (код + име), вид (оборот/салдо), сума, регион, сегмент, продуктова група, контрагент, източник (badge: ръчно/Excel/Google Sheets/API/демо). Филтри: период (daterange), юр. лице, тип сметка, сметка, разрези, източник. Сума с общ тотал под таблицата.
- Добавяне/редакция в дясна модална форма (`x-right-modal` като при разходите): всички полета, Select2 за сметка и разрези, валидация чрез FormRequest.
- Изтриване (единично и масово), експорт в Excel (maatwebsite/excel).

### Задача 1Б — Множествено въвеждане
Екран с editable grid (HTML таблица + jQuery): избор на юр. лице и месец, редове = сметки (групирани по приходи/разходи/баланс), колони = сума + разрез по избор; въвеждаш суми и записваш наведнъж (AJAX). Предварително попълва вече въведените стойности за месеца. Това е ключово за демото: показва, че клиентът въвежда „според нуждите си“.

### Задача 2 — Справочници
- **Сметкоплан**: дървовиден списък по групи (1–7), добавяне/редакция/деактивиране на сметки, тип и подтип; брояч на записите по сметка.
- **Разрези**: табове „Региони“, „Сегменти клиенти“, „Продуктови групи“, „Центрове на разходи“ — CRUD.
- **Юридически лица**: CRUD, майка/дъщерно, % участие, валута; дървовидно показване на групата.

### Задача 3 — Импорт от Excel / CSV (F4)
Съветник в 3 стъпки (образец: `ExpenseController::importStore/importProcess`, `app/Imports/ExpenseImport.php`, `app/Jobs/ImportExpenseJob.php`, `resources/views/import/process-form.blade.php`, `ImportController` за прогреса). Ползва `FinEntryImporter` от Фаза 0.
1. Качване на `.xlsx/.xls/.csv` (Dropify) + избор на юр. лице по подразбиране.
2. **Mapping на колони** — за всяка колона от файла избор към поле; автоматично разпознаване (`guessMapping`). Преглед на първите 10 реда с валидация на ред (зелено/червено). Опция „създай липсващи разрези автоматично“.
3. Импорт с прогрес бар (batch jobs, `ImportFinEntriesJob`) → резултат: импортирани / грешни редове с причина.
- Шаблони за сваляне: `public/databackd/templates/fin-entries-template.xlsx` и `.csv` с примерни редове на български.
- **История на импортите** (отделен пакет P7): списък на batch-овете (дата, източник, файл/URL, редове общо/успешни/грешни, статус, потребител), детайл с грешките, бутон „Отмени импорта“ (`FinEntryImporter::undo`).

### Задача 4 — Импорт от Google Sheets (F4)
- Форма: URL на Google Sheet (споделен „всеки с линка“) + лист (gid). `GoogleSheetsReader` превръща URL-а в CSV export (`https://docs.google.com/spreadsheets/d/{id}/export?format=csv&gid={gid}`), сваля го с `Http::get` и подава резултата в същия mapping/импорт (`FinEntryImporter::readCsvString` → mapping → `import`, `source=google_sheets`). Без OAuth за MVP. Ясна грешка, ако листът не е публичен.
- „Запомни източника“ → запис в `fin_data_sources` с mapping-а; списък на източниците с бутон „Синхронизирай сега“ (повтаря импорта със запазения mapping) и дата на последна синхронизация.
- За демото без интернет: в local среда позволи и URL към файл в `public/databackd/templates/` (`csv_url`).

### Задача 5 — Интеграции и други източници (F5)
- Страница „Интеграции“ — карти (grid) с източници: Excel ✅, CSV ✅, Google Sheets ✅, **REST API** ✅, и „Планирани“ (badge „скоро“): Microinvest, Ajur, Plus Minus, Бизнес навигатор, Salesforce, QuickBooks, Xero, банкови извлечения (MT940/CAMT). Всяка активна карта води към съответния екран.
- **API токени**: генериране (показва се веднъж), списък (име, последни 4 символа, последна употреба), отнемане. Примерна `curl` заявка на страницата.
- **REST endpoint**: `POST /api/databackd/fin-entries` (JSON масив от записи с полетата от `FinEntryImporter::fields()`), автентикация `Authorization: Bearer <token>` през `FinApiTokenAuth`; връща брой записани и грешки по ред; `source=api`.
- **Синхронизация от съществуващите модули** (пакет P9): бутон „Прехвърли от фактури и разходи“ — създава `fin_entries` (`source=invoice|expense`) от платени фактури (→ 703/702 според продукта) и разходи (→ 602/609 според категорията); идемпотентно (не дублира при повторно пускане); показва резултат.

### Демо данни (P10, `41DataDemoSeeder`)
5–6 записа в историята на импортите (успешни и с грешки, Excel + Google Sheets + API) със съответните `fin_entries`, 2 запазени източника (единият към демо CSV в `public/databackd/templates/`), 1 API токен, ~50 ръчно въведени записа (`source=manual`) от последния месец.

## Фаза 2 и край
Виж COMMON-RULES. Smoke: `php docs/mvp/tools/smoke.php databackd.data. --ajax` + `--url=` за детайлни страници (напр. batch с грешки). Провери end-to-end през `tinker`: импорт на шаблона с `FinEntryImporter`, CSV URL импорт, и POST към API endpoint-а без сървър — `app(\Illuminate\Contracts\Http\Kernel::class)->handle(\Illuminate\Http\Request::create('/api/databackd/fin-entries', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer <token>', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode([...])))->getContent()`.

Скрийншот списъкът да включва: списък записи с филтри, множествено въвеждане, всяка стъпка от импорт съветника (вкл. mapping), резултат от импорт, Google Sheets форма и списък с източници, история на импортите + детайл с грешки, страница Интеграции, API токени, сметкоплан, разрези, юридически лица.
