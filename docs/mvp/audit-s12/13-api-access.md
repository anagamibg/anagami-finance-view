# S12 / A13 — REST API, пакетни модули, права по роли, мултитенантност

Дата: 2026-10-28. База: `databackd_local` (демо компания „Демо Финанс Груп“ = **#4**, чужда компания = **#1 „Anagami Finance View“** — без потребители, 29 сметки). Всички проверки са през Laravel kernel-а в процеса (без сървър). Всички тестови записи (токени, чужди редове, импорти, изключени модули, неактивна компания) са правени **в една DB транзакция, която се връща назад** — в базата не остава нищо. Скриптовете (не са в репото): `scratchpad/a13/{probe,api,api2,api3,api4,pkg}.php`.

## Какво е проверено

### 1. REST API (`POST /api/databackd/fin-entries`)
~80 реални заявки с тестови токени (на #4 и на #1):

| Област | Резултат |
|---|---|
| Съхранение на токена | само SHA-256 (64 hex) + `last_four`; plain токенът го няма в базата и в `storage/logs/databackd-api-*.log` ✔ |
| Сравнение | търсене по уникален индекс на хеша + `hash_equals()`; формат `dbk_` + 32–64 знака, иначе 401 без заявка към базата ✔ (40 случайни знака ≈ 238 бита — SHA-256 без pepper е достатъчен) |
| 401 | без токен / непознат / анулиран / грешен формат / токен в `?api_token=` или `X-Api-Key` → 401, еднакво тяло, `WWW-Authenticate: Bearer` (с `error="invalid_token"`, когато е подаден) ✔; схемата `bearer` с малки букви се приема (RFC) ✔ |
| Метод / транспорт | GET/PUT/DELETE → 405 + `Allow: POST`; OPTIONS → 204 без CORS заглавки; `text/plain` → 415; `Content-Length` > 2 MiB → 413 (преди парсване); реално тяло 2,5 MiB → 413; JSON дълбочина > 16 → 422 ✔ |
| Валидация | невалиден JSON / празен масив / обект без записи → 422; 1001 записа → 422; 201 нови стойности на измерения → 422; частичен импорт → 207 с `errors[].row` = позиция в масива (1-базирана) ✔; запис-низ, масив в поле, сума „1 234,56“, отрицателна/нула, описание 5000 знака → коректни грешки по ред ✔ |
| Подменени полета | `company_id`, `source`, `created_by`, `import_batch_id` в тялото се игнорират — записът е с компанията на токена, `source=api` ✔ |
| Мултитенантност | токен на #1 с юридическо лице / сметка, които има само #4 → „Unknown …“; с общ код на сметка + `company_id=4` → записва се в #1; 0 промени в #4; `create_missing_dimensions` създава измерението в #1 ✔ |
| Idempotency-Key | повтор (с/без кавички) → същият отговор, 1 запис; друго тяло или друг query → 422; същият ключ от друг токен → независим (ключът е на токен); празен / 300 знака / не-ASCII → 400; отхвърлена (422) заявка не „изгаря“ ключа; заключен ключ → 409 ✔ |
| Лимити | 61-ва заявка на токен за минута → 429 + `Retry-After`; бюджет записи/час → 429; 11-и неуспешен опит от IP → 429 (и за валиден токен от същия IP) ✔; **допълнително** `throttle:api` от групата `api` — 60/мин **на IP** (за всички токени заедно) |
| 403 | неактивна компания → 403; модулът `databackd_integrations` изключен → 403; `APP_URL` https + заявка по http → 403 ✔ |
| 5xx | `FinApiExceptionHandler`: без съобщение и trace ✔ (виж поправка 2) |
| Пагинация / филтри | **няма** — API-то е само за запис (един POST). Няма GET за партиди/записи; клиентът не може да провери какво е записано освен по отговора. (Предложение, не е правено.) |

### 2. Пакетни модули
- `php artisan route:list --name=databackd --json`: **199 маршрута**, всички с `RequireModule:<модул>` (data 74, analytics 43, reports 27, teams 24, planning 18, integrations 12) + API маршрутът с `RequireCompanyModule:databackd_integrations`. **Маршрут без middleware няма.** Извън `routes/databackd/**` няма AFV контролери.
- Менюто: картата `databackd_menu_module()` съвпада с middleware-а на маршрута на всеки от 31-те файла в `resources/views/databackd/menu/` (всяко меню е в същия модул като маршрута си); `@if databackd_module(...)` е и във всеки файл.
- Всеки от 6-те модула изключен за #4 (в `module_settings`, savepoint → rollback) — всеки маршрут на модула (всички методи, със собствени id-та) като админ, служител и клиент: **597 заявки, всички 403**. API при изключени интеграции → 403. Менюто (табло, финансово табло, записи, интеграции) **няма връзки** към изключения модул — с едно изключение (виж „Нужни промени“ т. 1).
- Изключване **през пакета** (`Package::save()` без `databackd_planning`): `PackageObserver` → `module_settings` = 0 / `deactive`, кешът `user_modules_*` се чисти → 403 веднага, менюто без „Планиране“ ✔. След теста `module_settings` е непроменен (0 реда с `is_allowed=0`).

### 3. Права и мултитенантност (IDOR)
- **Всички 85 параметризирани маршрута** (GET/PUT/POST/DELETE) с id на запис от компания #1 (клонирани редове от 22 таблици, с маркер в името) като **админ и служител**: 170 проби → всички 403/404/422, маркерът не се появява в нито един отговор, нито един чужд ред не е променен/изтрит. Вложено: собствена група за консолидация + чуждо правило → 404 ✔.
- Контрола: същите GET маршрути със собствени id → не 403/404 (освен двете счупени view-та, виж „Отворени“).
- Чужди id-та в **query string** (`entity`, `entity_ids[]`, `group`, `budget`, `ratio`, `source`, `batch`, `region_id` … 40 параметъра) на всички 77 GET страници без параметри, HTML + AJAX: 154 проби, без изтичане и без 5xx ✔.
- Валидациите на id-та в телата на формите (`exists`) са с `where('company_id', …)` навсякъде (статичен преглед) ✔.
- Клиент A срещу клиент B (същата компания): заявка за екип (show/delete/status/assign/create-project), сваляне на файл към коментар, `move`/`deliverable`/`download` на анализ → 403/404; списъците (HTML и AJAX) не показват записите на B ✔. (`check-security` покрива и `analysis.show/edit/update/comment/rate`.)
- **CSRF:** всичките 89 POST/PUT/DELETE заявки без токен → `TokenMismatchException` (пренасочване към вход), 0 промени в 19 таблици ✔. Единственият GET+POST маршрут е `analytics.builder.preview` (само преглед на формула).
- **Роли:** клиентът вижда в менюто само `teams/requests` и `teams/analysis` (= `config('databackd.client_menu')`); по собствени id-та — 403 на всичко извън своите анализи/заявки. Служителят: всичко без `gaps.margins.*`, `gaps.onboarding`, `gaps.alerts.create` и управлението на API токени (умишлено).

### 4. `check-security` и smoke
- `php artisan databackd:check-security`: **83/83** преди и след поправките.
- Smoke `databackd. --ajax` (виж таблицата в края): само познатите FAIL-ове от `10-review-deploy.md` / `06-security.md`.

## Намерени и поправени грешки

1. **API връщаше HTTP 500 при `Origin`/`Referer` от собствения домейн** — `routes/databackd/api/fin-entries.php`. Причина: групата `api` съдържа `Sanctum\EnsureFrontendRequestsAreStateful`; при `Origin`/`Referer` от „stateful“ домейн (по подразбиране `APP_URL` хоста, `localhost`, `127.0.0.1`) той пуска сесия + CSRF проверка → `TokenMismatchException` → 500 „Server error.“. Възпроизведено: `fetch()` от страницата „Интеграции“ или `curl -e https://databackd.com/...` → 500. Поправка: `->withoutMiddleware([EnsureFrontendRequestsAreStateful::class])` на групата — API-то е напълно stateless. Проверено: същата заявка → нормален отговор (422/200).
2. **`FinApiExceptionHandler` превръщаше всички не-HTTP изключения в 500** — `app/Services/Databackd/Data/FinApiExceptionHandler.php:21`. Поправка: първо `prepareException(mapException($e))` на фреймуърка (TokenMismatch → 419, ModelNotFound → 404, AuthorizationException → 403, лоша заявка → 400), `AuthenticationException` → 401 с `WWW-Authenticate`, `ValidationException` → 422; телата остават общи (без съобщение/trace). Проверено с 6 вида изключения.
3. **Подвеждащо 403 съобщение за клиент/служител** — `app/Http/Middleware/Databackd/RequireModule.php`. Клиент, който отваря финансов екран, получаваше „Модулът … не е включен във Вашия пакет. Свържете се с администратора, за да смените пакета“, макар модулът да е в пакета (просто ролята няма достъп). Поправка: ако компанията има модула (`databackd_company_module()`), отговорът е общото `messages.permissionDenied` („Достъпът е отказан.“); съобщението за пакета остава само когато модулът наистина липсва в пакета. Статусът е същият (403).

## UX подобрения
Т. 3 по-горе. Друго в моята област няма (API-то няма UI; документацията е на A08 — разликите са в „Нужни промени“).

## Нови SCSS partial-и и lang ключове
Няма. Използван е съществуващият ключ `messages.permissionDenied`.

## Миграции
Няма.

## Нужни промени извън моята област

1. **(A08) `app/Http/Controllers/Databackd/Data/IntegrationController.php:64-65` и `:103-117`** — страницата „Интеграции“ (модул `databackd_integrations`) показва карти Excel/CSV, счетоводните програми и „Банкови извлечения“, които водят към `databackd.data.import.index` / `databackd.gaps.bank-import.index` (модул `databackd_data`). Без `databackd_data` в пакета картите водят към 403. Поправка: `$link = fn(string $route, string $module = 'databackd_data') => Route::has($route) && databackd_module($module) ? route($route) : null;` (за `google_sheets` — модул `databackd_integrations`), а в `platformCards()` условията да станат `Route::has(...) && databackd_module('databackd_data')`. (Бутонът за синхронизация вече го прави — ред 35.)
2. **(A08) Документацията на API (`resources/lang/{bg,eng}/databackd-data-integrations.php`, `docs.*`, и списъкът с кодове в `resources/views/databackd/data/integrations/index.blade.php`) не съвпада напълно с поведението:**
   - липсва **405** (друг метод, `Allow: POST`) — нов ред `code_405`;
   - **403** е и „заявката не е по HTTPS“ (`HTTPS is required for this API.`) — да се добави към `code_403`;
   - **422** е и „повече от 200 нови стойности на измерения в една заявка“ (с `create_missing_dimensions`) и „Idempotency-Key с друг query string“ — към `code_422`;
   - **429** е и „10 неуспешни опита за вход от един IP за минута“ и общият лимит „60 заявки в минута от един IP“ (`throttle:api`, за всички токени заедно) — към `code_429`;
   - `create_missing_dimensions` се приема и като поле в JSON обекта (`{"entries": [...], "create_missing_dimensions": true}`), не само в query;
   - Idempotency-Key важи **за конкретния токен**; ограничения на полетата (напр. описание ≤ 2000 знака) — в колоната „Подсказка“.
3. **(A02/данни) `app/Services/Databackd/Data/FinEntryImporter.php`:**
   - `parseCurrency()` (≈ред 1400) приема всеки 3-буквен код; непозната валута (напр. `XXX`) се записва с `amount_base = amount` (без курс) — тихо грешна стойност в справките. Предложение: ако `toBase()` не намери курс за валута ≠ базовата, редът да е грешка („Няма курс за :currency към :date“) вместо резервното „без конверсия“.
   - `recordStructureErrors()` (≈редове 938–955) връща английски текстове („The record must be a JSON object…“, „Field … must be a string or a number.“, „… is too long (max 2000 characters)“), докато останалите грешки са на езика на компанията — да минат през `__()` (`databackd-data-common.errors.*`).
4. **(S6/координатор) `app/Http/Middleware/TrustProxies.php:15` `$proxies = '*'`** (известно като A8 в `10-review-deploy.md`): потвърдено, че блокирането по IP след 10 неуспешни опита се заобикаля с `X-Forwarded-For: <друг IP>` (11-ият опит с XFF → 401 вместо 429), а HTTPS проверката — с `X-Forwarded-Proto: https`. Да стане адресът на реалния прокси (LiteSpeed) или `null`. Практическият риск е нисък (токенът не може да се налучка), но лимитът е безсмислен.
5. **(S6, по избор) API токените нямат срок и не зависят от създателя си** — токенът работи и след като `created_by` е деактивиран/изтрит. Предложение (вече описано в `P6.md`): `expires_at` и анулиране на токените на потребител при деактивиране.

## Отворени въпроси / неправено
- `config/databackd_modules.php` не съществува — каталогът на модулите е `databackd_modules_catalog()` в `app/Helper/databackd_modules.php` (зарежда се от `app/Helper/databackd.php` и `routes/databackd/00-modules.php`); не е променян.
- По време на одита два view файла на други агенти бяха с Blade синтактична грешка (`Unclosed '['`) и даваха 500: `resources/views/databackd/teams/requests/show.blade.php` (ред ~694) и `resources/views/databackd/gaps/fx-rates/form.blade.php` — редактирани в същия момент от други агенти (A05/A0x). При повторната проверка в края и двете са 200.
- `analytics.builder.edit` (системен показател), `planning.budgets.show`, `planning.forecasts.show`, `reports.saved.open` отговарят с 302 за всички роли (пренасочване към редактора/справката) — не е проблем с правата.
- `FinApiGate` регистрира `FinApiExceptionHandler` като singleton до края на процеса — при PHP-FPM е за една заявка; при Octane/дълъг worker би останал и за уеб заявките. Днес не се ползва — само бележка.
- Тестовете не са запазени като artisan команда (`check-security` е на S6); IDOR пробата за всичките 85 маршрута е в `scratchpad/a13/probe.php` — може да се пренесе в `databackd:check-security` (група `isolation`) от собственика на командата.
- Записите в одит лога на API (`storage/logs/databackd-api-2026-10-28.log`) от тестовете остават (локален лог, без токени).
- Snapshot-и не са правени — няма визуални промени (само текстът на 403 страницата за клиент/служител).

## Smoke / проверки (след поправките)
| Проверка | Преди | След |
|---|---|---|
| `databackd:check-security` | 83/83 | 83/83 |
| smoke `databackd. --ajax`, админ | 154 OK, 2 FAIL (`reports.counterparties.show` 302 ×2 — познато) | същото |
| smoke, служител `g.petrov@demofinans.bg` | 144 OK, 12 FAIL: `counterparties.show` 302 ×2 + 403 на `gaps.alerts.create`, `gaps.onboarding.index`, `gaps.margins.{index,excel,pdf}` ×2 (умишлено, `10-review-deploy.md`/A1) | същото |
| smoke, клиент `p.yordanov@balkan.bg` | 10 OK (`teams.analysis.{index,create}`, `teams.requests.{index,create,estimate}`), 146 × 403 (очаквано) | същото; текстът на 403 вече е „Достъпът е отказан.“ |
| IDOR проба (85 маршрута × админ/служител + 154 query проби + клиент A/B) | 0 изтичания | 0 изтичания; контролите 30/30 |
| API проба (~80 заявки) | 1 нов бъг (500 при stateful Origin) | поправен; останалото без промяна |
| `teams/requests/29`, `gaps/fx-rates/{id}/edit` (чужди view-та, временно 500 по време на одита) | 500 | 200 при повторната проверка |

Няма нови FAIL спрямо handoff-ите. `php -l` на трите променени PHP файла — без грешки.

## Пълен списък с променени/нови файлове
- `routes/databackd/api/fin-entries.php` (променен)
- `app/Services/Databackd/Data/FinApiExceptionHandler.php` (променен)
- `app/Http/Middleware/Databackd/RequireModule.php` (променен)
- `docs/mvp/audit-s12/13-api-access.md` (нов)
