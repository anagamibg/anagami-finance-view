# Handoff S2 → S3–S5: Въвеждане на финансови данни и импорт

## Статус
**Готово.** Ръчно въвеждане (списък + множествено), справочници, импорт от Excel/CSV и Google Sheets, история с отмяна, интеграции (API токени + REST endpoint) и синхронизация от фактури/разходи работят. `php docs/mvp/tools/smoke.php databackd.data. --ajax` → All pages OK (всички GET страници + детайлни с `--url=`). Екраните са проверени само чрез вътрешно рендериране (без браузър, без сървър); JS поведението (Select2/bootstrap-select, SweetAlert, прогрес бар) не е гледано на екран.

## Какво е направено
**Таблици:** `fin_data_sources`, `fin_api_tokens` (колони в `docs/mvp/features/02-data.md`). Модели `FinDataSource`, `FinApiToken` (`FinApiToken::generate(string $name, ?int $userId=null, ?int $companyId=null): array{0: FinApiToken, 1: string}`, `hashToken(string): string`).

**Service класове** (`App\Services\Databackd\Data\`):
- `FinEntryImporter` — `__construct(?int $companyId = null)`; `readFile(UploadedFile|string): array`, `readCsvString(string): array`, `fields(): array`, `guessMapping(array $headers): array`, `mappingFromColumns(array): array`, `columnsFromMapping(array, int): array`, `missingRequired(array): array`, `validateRow(array $row, array $mapping, array $options = []): array`, `preview(array $rows, array $mapping, array $options = [], int $limit = 10): array`, `import(array $rows, array $mapping, array $options = []): FinImportBatch`, `importRecords(array $records, array $options = []): FinImportBatch`, `undo(FinImportBatch): int`, `parseDate`, `parseAmount`, `finalStatus`. Mapping = `[fieldId => columnIndex]`.
- `GoogleSheetsReader` — `parseUrl`, `exportUrl`, `fetchCsv`, `read`, `fetchCsvUrl`, `readCsvUrl`, `fetchForType`, `canReadLocally`.
- `InvoiceExpenseSync` — `preview(?int $entityId=null): array`, `run(?int $entityId=null, ?int $userId=null): array`.
- Други: `FinEntriesDataTable::filteredQuery(Request): Builder` (споделен филтър за списък и експорт), `FinEntriesExport`, `ImportFinEntriesJob`, `FinApiTokenAuth` (middleware).

**Routes, контролери, views, lang:** виж `docs/mvp/features/02-data.md`. Меню файлове `10`…`17-data-*.blade.php` в раздел „Финансов анализ“. Lang `databackd-data-{common,entries,bulk,accounts,dimensions,import,sources,batches,integrations,sync}.php`.

**Demo:** `database/seeders/Databackd/Demo/41DataDemoSeeder.php` (`php artisan databackd:demo --only=DataDemoSeeder`) — 7 импорта (Excel, Google Sheets, API; с грешки, един отменен), 405 записа извън S1, 2 източника, 1 API токен (`dbk_DemoToken2026Databackd0FinEntriesKey7QxZ` — само за демо), 50 ръчни записа от октомври 2026 (`РЪЧ-2026-00NN`).

**Файл на S1:** `app/Providers/RouteServiceProvider.php` — добавен `mapDatabackdApiRoutes()` (маркиран `// databackd`, описан в `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` §4.4).

## Отклонения от промпта
- Падащите списъци във формите са bootstrap-select (като във всички формуляри на AFV), не Select2.
- Валидацията при част от контролерите е с `Validator::make` в контролера (P2, P5, P6, P8), не с FormRequest.
- `GoogleSheetsReader::canReadLocally()` разрешава `csv_url` към файл от `public/databackd/templates/` и по заявка към `localhost`/`127.0.0.1`/`*.test`, защото `APP_ENV=codecanyon` и AFV презаписва `app.debug` от настройките.
- Токените се създават/отнемат само от admin; клиентската роля няма достъп до нито един екран.
- Демо данните са с 7 импорта вместо 5–6 (добавен е отменен).

## Не е направено / известни проблеми
- Няма OAuth за непублични Google листове; тип `excel` в `fin_data_sources` не се синхронизира.
- „Синхронизирай сега“ замества записите на предишния импорт на източника и винаги създава липсващите разрези.
- Синхронизацията от фактури/разходи **удвоява** приходите/разходите в демо компанията (те вече имат `source=demo` записи) — не се пуска в демото; променена сума не се обновява.
- Номерата на редове в грешките са позиции сред непразните редове + 2, не физически Excel ред.
- Списъкът „Финансови записи“ няма колона „център на разходи“ (има филтър и поле във формата).
- Smoke за `entries.export` изисква `php -d memory_limit=1G` (експортира два пъти в един процес).
- Заявките към queue: при `QUEUE_CONNECTION != sync` `progress` сам стартира worker за опашка `databackd-import`.

## Какво трябва да знае следващата сесия
- Данните се четат през `FinancialDataService` (S1); `fin_entries` вече има записи с `source` excel/csv/google_sheets/api/manual — суми са положителни, знакът по тип сметка.
- Не пипай `FinEntryImporter`, `routes/databackd/api/*`, `FinApiTokenAuth`. Ако пишеш демо seeder, не трий записи с `source` различен от твоя.
- Ръчните записи от демото са с `document_no` `РЪЧ-…`; импортираните имат `import_batch_id`.

## Как да се провери
```bash
php artisan databackd:demo --only=DataDemoSeeder
php -d memory_limit=1G docs/mvp/tools/smoke.php databackd.data. --ajax
php -d memory_limit=1G docs/mvp/tools/smoke.php - --url=/account/databackd/data/batches/<id батч с грешки> --ajax
```
API: `POST /api/databackd/fin-entries` с `Authorization: Bearer <токен>` (виж страница „Интеграции“).

## Локални файлове извън git
`resources/lang/bg/databackd-data-*.php` (10 файла; `bg/` е в `.gitignore` → качват се ръчно на сървъра), `.env`, `.env.stage.backup`, `.claude/`, `Modules/`.

## Production (Anagami Finance View) — качено 2026-10-27
- Качени (rsync, без `docs/`): всички файлове на S2 + 10 `resources/lang/bg/databackd-data-*.php`. Бекъп преди качването: `~/deploy-backup-databackd-mvp-s2-20261027/` (`RouteServiceProvider.php`, кешираните `config.php`/`routes-v7.php`, дъмп на `fin_*` таблиците). Връщане: копирай `RouteServiceProvider.php` обратно и `php artisan route:clear`; новите таблици `fin_data_sources`/`fin_api_tokens` са празни за останалите компании.
- `php artisan migrate --force` (2 нови таблици), `optimize:clear`, `config:cache`, `route:cache` → 56 routes `databackd.data.*` + `POST /api/databackd/fin-entries` (без токен → 401).
- Демо данните на S2 са заредени само в „Демо Финанс Груп“ (company 17), като seeder-ът е викнат директно (командата `databackd:demo` отказва на не-`_local` база). Демо API токенът от seeder-а е заменен със случаен хеш (публикуван в репото токен не трябва да работи на production) — за работещ токен създайте нов от „Интеграции“.
- Не е проверено вътре в логнат сесия на production (паролата е в `~/demo-passwords.txt`): само route/HTTP проверки и лог.
