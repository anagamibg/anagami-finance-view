# Основа: финансов модел данни, скриване на модули, демо данни

Техническо описание (Етап 4 „Техническа документация“).

## 1. Финансов модел данни

Всички таблици са мулти-тенант (`company_id`, trait `HasCompany` → `CompanyScope`).

| Таблица | Предназначение | Ключови колони |
|---|---|---|
| `fin_entities` | Юридически лица / подразделения в компанията (за консолидация) | `name, eik, country, currency, parent_id, ownership_pct, is_active, sort` |
| `fin_accounts` | Сметкоплан (опростен НСС, класове 1–7) | `code, name, type (asset/liability/equity/revenue/expense), subtype, parent_id, is_active`; уникален `(company_id, code)` |
| `fin_dimensions` | Справочници за разрези | `type (region/client_segment/product_group/cost_center), name, code` |
| `fin_entries` | Универсален финансов запис | `entity_id, account_id, entry_date, kind (flow/balance), amount (>0), currency, amount_base, region_id, client_segment_id, product_group_id, cost_center_id, client_id, counterparty_name, document_no, description, source, import_batch_id, created_by` |
| `fin_import_batches` | Журнал на импортите | `source, file_name, source_url, rows_total/imported/failed, status, mapping (json), errors (json), created_by` |

- `amount` е винаги положително; знакът се определя от типа на сметката. `flow` = оборот за периода, `balance` = салдо към датата.
- Сметка 241 (амортизация) е контра-сметка (`FinAccount::CONTRA_CODES`) — приспада се от активите.
- Сметкоплан (29 сметки) се създава автоматично за всяка нова компания (`Company::created` в `AppServiceProvider`) и лениво при първо ползване на `FinancialDataService` (`ChartOfAccounts::ensureFor($companyId)`).

## 2. `FinancialDataService`

`App\Services\Databackd\FinancialDataService` — четене за всички екрани. `new FinancialDataService(?int $companyId = null)`; не зависи от `CompanyScope`, затова работи и от конзолата.

- `flows($from, $to, array $filters = []): Collection`
- `sumByAccountType($from, $to, $filters): array{revenue, expense, profit}`
- `monthlySeries($from, $to, ?string $groupBy = null, $filters): array` — редове `month, revenue, expense, profit` (+ `group_id, group_name` при `groupBy`: `region|client_segment|product_group|cost_center|entity|account`)
- `balanceAt($date, $accounts, $filters): float` — последното салдо ≤ дата за всяка двойка (юридическо лице, сметка); `$accounts` е код(ове), подтип или тип
- `pnl($from, $to, $filters): array` — ОПР по сметки + `totals`
- Филтри: `entity_ids, region_id, client_segment_id, product_group_id, cost_center_id, client_id`

## 3. Скриване на модули (само фронтенд)

`config/databackd.php` → `hidden` с групи `menu`, `dashboard_tabs`, `dashboard_widgets`, `settings`, `quick_add`. Helper `databackd_hidden($group, $key)` (`app/Helper/databackd.php`). Routes и код не се променят.

## 4. Точки за разширение

- `routes/databackd/*.php` — зареждат се автоматично (`web`, `check-company-package`, `auto-logout`, `auth`, `multi-company-select`, `email_verified`; префикс `/account`).
- `resources/views/databackd/menu/*.blade.php` — пунктове на раздел „Финансов анализ“ (View Composer на `sections.menu`; разделът е скрит, ако няма файлове).
- `database/seeders/Databackd/Demo/<NN><Name>.php` → клас `<Name>` — пускат се от `php artisan databackd:demo`.

## 5. Демо данни

`php artisan databackd:demo [--only=Клас] [--force]` — отказва да работи, ако `DB_DATABASE` не завършва на `_local`. Създава компания „Демо Финанс Груп“ (EUR, bg), потребители, клиенти, продукти и демо seeders за работа, фактури/разходи и финансовия модел. Генерирането е детерминистично (`mt_srand(2026)`) и идемпотентно.
