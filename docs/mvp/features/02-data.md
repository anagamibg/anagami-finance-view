# Техническа документация — Въвеждане на финансови данни и импорт (S2)

## Таблици (миграции `2026_10_27_2000*`, плюс `fin_*` на S1)
- `fin_data_sources`: `company_id, type enum(google_sheets|csv_url|excel), name, url, sheet, mapping json, default_entity_id, last_synced_at, last_batch_id, created_by`
- `fin_api_tokens`: `company_id, name, token_hash (sha256, unique), last_four, last_used_at, revoked_at, created_by` — токенът в чист вид (`dbk_` + 40 символа) се показва само при създаване.
- Използват се и `fin_entries`, `fin_import_batches` (статуси `pending|processing|completed|partial|failed|undone`), `fin_accounts`, `fin_dimensions`, `fin_entities` от S1.

## Ядро: `App\Services\Databackd\Data\FinEntryImporter`
Общо за Excel, CSV, Google Sheets и API. Четене (`readFile`, `readCsvString` — автоматичен разделител, UTF-8/Windows-1251), `fields()`, `guessMapping()` (BG/EN синоними), `validateRow()` (дати d.m.Y/Y-m-d/Excel serial, суми със `,` или `.`, сметка по код, разрези по име), `preview()`, `import()` (на части с `batch_id`/`row_offset`), `importRecords()` (за API), `undo()`. Mapping е `[fieldId => columnIndex]`.

## Routes (всички под `/account/databackd/data/`, имена `databackd.data.*`)
| Пакет | Routes |
|---|---|
| Записи | `entries.{index,create,store,edit,update,destroy,apply_quick_action,export}` (филтри: период, юр. лице, тип/сметка, разрези, източник; `?batch_id=`, `?account_id=`) |
| Множествено въвеждане | `bulk.{index,load,store}` |
| Сметкоплан | `accounts.{index,create,store,edit,update,toggle,destroy}` |
| Разрези / юр. лица | `dimensions.*`, `entities.*` |
| Импорт Excel/CSV | `import.{index,upload,mapping,preview,run,progress,result,template}`; `ImportFinEntriesJob` (части по 500 реда, опашка `databackd-import`) |
| Google Sheets / източници | `sources.{index,fetch,mapping,preview,import,result,store,sync,destroy}`; `GoogleSheetsReader` (CSV export `…/export?format=csv&gid=`, без OAuth) |
| История | `batches.{index,show,errors-export,undo}` |
| Интеграции | `integrations.index`, `tokens.{store,destroy}` |
| Синхронизация | `sync.{index,run}` (`InvoiceExpenseSync`, идемпотентно по `document_no` `INV-…`/`EXP-<id>`) |
| REST API | `POST /api/databackd/fin-entries` (`databackd.api.fin-entries.store`), `Authorization: Bearer <token>` през `FinApiTokenAuth`, до 1000 записа, отговор 200/207 `{batch_id,status,imported,failed,errors:[{row,messages}]}`, 60 заявки/мин на токен |

`routes/databackd/api/*.php` се зарежда от `RouteServiceProvider::mapDatabackdApiRoutes()` (middleware `api`, префикс `api`, без auth).

## Как работи импортът
1. Файлът се пази в `storage/app/databackd-imports/<company>/`. 2. Mapping се познава автоматично, преглед на първите 10 реда. 3. `run` създава batch и пуска верига от части; прогресът се чете от `fin_import_batches`. Резултатът показва импортирани/грешни редове с причини; историята позволява отмяна (изтрива записите на batch-а).
