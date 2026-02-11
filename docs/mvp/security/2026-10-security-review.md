# Протокол от преглед на сигурността — Anagami Finance View

| | |
|---|---|
| Дата | 28.10.2026 |
| Изпълнител | сесия S6 (фаза 2), оркестратор + 10 пакета (P1–P10) |
| Обхват | кодът на фаза 1 (сесии S1–S5: `app/**/Databackd/**`, `routes/databackd/**`, `resources/views/databackd/**`), конфигурацията, използваните зависимости, upstream кодът на AFV там, където е пряко достъпен за атака, и production (само четене) |
| Основание | проект по ПКИП 2021–2027, Етап 3 „Тестване за сигурност“ |
| Проверена версия | commit `015200c` + работно дърво на 28.10.2026 (вкл. новите модули на S8 „в движение“ — виж „Ограничения“) |
| Автоматизирана проверка | `php artisan databackd:check-security` — 83 проверки, 0 провалени (само на локалната демо база) |

## 1. Метод

1. **Инвентар.** 360 файла, добавени във фаза 1 (183 PHP/конфигурационни, останалите — views, преводи, документи), разпределени в 10 пакета: P1 изолация между компании · P2 роли и права · P3 инжекции · P4 файлове, импорт, експорт · P5 SSRF · P6 API · P7 конфигурация и тайни · P8 production · P9 автентикация · P10 зависимости.
2. **Статичен преглед** на всеки пакет (OWASP Top 10:2021 като класификация), включително `grep` по опасни конструкции (`DB::raw`, `{!! !!}`, `eval`, `withoutGlobalScope`, `->update($request->all())`, изходящи HTTP заявки).
3. **Динамични тестове без сървър** (заявки през ядрото на приложението, `docs/mvp/tools/smoke.php` и собствени скриптове) срещу локалната демо база `databackd_local`: ролева матрица route × роля (199 routes × 5 актьора), 172 заявки с чужди ID между две компании, 91 теста на API токена, хостилни URL за SSRF (188 проверки), хостилни формули и SQL низове, PoC за RCE през PDF. Всички временни данни са с маркер `S6-TMP-…` и са изтрити (проверено с `COUNT = 0`).
4. **Production — само четене:** 39 HTTP заявки (30 GET, 7 HEAD, 1 OPTIONS, 1 TRACE) и команди за четене по SSH. Не са правени опити за вход, brute force или промяна на сървъра.
5. **Зависимости:** `composer audit` (47 препоръки за 12 пакета), `npm audit --omit=dev` (6), с анализ дали уязвимият код се вика в проекта.
6. **Поправки:** критичните и високите находки са поправени в кода; средните и ниските — когато е просто. Файлове на други сесии (views, routes, `resources/js`, преводи) не са пипани — за тях има точни указания в „За S10“.

Подробните находки, сценариите за атака, „проверено без находки“ и рецептите за проверка са в приложенията `docs/mvp/security/findings/P1.md … P10.md`.

## 2. Обобщение

**122 находки** (някои са една и съща причина, открита от няколко пакета — вж. по-долу): **11 критични, 11 високи, 34 средни, 66 ниски.**

| Сериозност | Общо | Поправено | Поправено в репото + за S10 (production) | За S10 | Описано | Приета |
|---|---|---|---|---|---|---|
| **критична** | 11 | 3 | 8 | 0 | 0 | 0 |
| **висока** | 11 | 8 | 1 | 2 | 0 | 0 |
| **средна** | 34 | 16 | 3 | 13 | 1 | 1 |
| **ниска** | 66 | 22 | 0 | 17 | 14 | 13 |
| **Общо** | 122 | 49 | 12 | 32 | 15 | 14 |

По пакети:

| Пакет | Област | Критични | Високи | Средни | Ниски | Общо |
|---|---|---|---|---|---|---|
| P1 | Изолация между компании | 0 | 1 | 1 | 3 | 5 |
| P2 | Роли и права | 0 | 0 | 3 | 9 | 12 |
| P3 | Инжекции (SQL, XSS, формули) | 1 | 0 | 2 | 12 | 15 |
| P4 | Файлове, импорт и експорт | 2 | 1 | 1 | 2 | 6 |
| P5 | SSRF и външни URL | 1 | 3 | 3 | 4 | 11 |
| P6 | API | 0 | 1 | 9 | 6 | 16 |
| P7 | Конфигурация и тайни | 2 | 2 | 3 | 12 | 19 |
| P8 | Production (само четене) | 2 | 0 | 6 | 6 | 14 |
| P9 | Автентикация | 2 | 2 | 1 | 3 | 8 |
| P10 | Зависимости | 1 | 1 | 5 | 9 | 16 |

**Различните критични проблеми са 8** (останалите са повторно откриване от друг пакет):

1. **RCE през PDF на справките** — `enable_php` + име на фирма във view-то (`P3-01` = `P4-01` = `P5-11`). Всеки админ на компания можеше да изпълни PHP код на сървъра. Поправено.
2. **RCE през бележка във фактура/оферта/договор** — `enable_php` на още 15 места в upstream контролерите (`P10-01`). Поправено (оркестратор).
3. **Файлове на заявките за анализ — публично достъпни по URL без проверка на собственост** (`P4-02`). Поправено за нови файлове; съществуващите се преместват при деплой.
4. **Log Viewer API без вход** — списък, четене, сваляне и ИЗТРИВАНЕ на логове от анонимен посетител (`P7-01`). Поправено в конфигурацията.
5. **Аварийният restore инструмент `public/backup-and-restore/` без работеща защита** — обърната проверка за вход; може да изпълнява `.sql` срещу базата и да разархивира архиви в корена (`P7-04` = `P8-01`). **Потвърдено достъпен на production.** Папката е изтрита от репото; на сървъра трябва да се изтрие.
6. **`.env` е бил публично изтеглим на production от 01.10 до 27.10.2026** (`P8-02`): 285 успешни отговора към 153 различни IP. `APP_KEY` и данните за базата са компрометирани. Достъпът е затворен на 27.10; остава ротация на тайните.
7. **Заобикаляне на паролата чрез имейл-2FA** — `check-code`/`resend-code` без автентикация и лимит (`P9-01`). Поправено.
8. **Превземане на акаунт чрез `choose-workspace`** — всеки автентикиран потребител (вкл. клиент) можеше да се логне като произволен друг, вкл. суперадмин (`P9-02`). Поправено.

Допълнително 11 високи: IDOR между компании в заглавията на справките (P1-01); SSRF защитата се изключваше от недоверен сигнал, редиректи без проверка, DNS rebinding (P5-01…03); неавтентикиран DoS през глобалния XSS middleware (P6-02); достъп без вход до vendor маршрути за миграции/кеш (P7-02); SQL injection в `UserAuth` чрез имейл с кавички (P9-03); слаба парола за демо акаунтите при деплой (P9-04); формулна инжекция в 8 експорта (P4-03); уязвим PhpSpreadsheet при импорт (P10-02); недостатъчна защита на корена на уеб сървъра (P7-07).

**Какво е силно (проверено без находки):** всичките 27 модела имат `HasCompany` и `company_id`; всички `exists`/`unique` правила са с `company_id`; няма суров SQL с потребителски вход (проверено динамично — враждебен текст се вижда само в bindings); няма `eval`/`unserialize`/shell в кода на AFV; клиентската роля получава 403 на всички финансови екрани; суперадминът без компания е спрян навсякъде; токените на API-то са CSPRNG, пазят се само като SHA-256 хеш и се показват веднъж; `company_id` на API записите идва само от токена; Laravel `TokenGuard` (критичен advisory) не се ползва.

## 3. Спешно за production

Тези неща не трябва да чакат цялото деплойване. Те са описани подробно в раздел 6.

1. **Изтрийте `~/public_html/backup-and-restore/` и `~/public_html/check.php`** (достъпни без вход днес — потвърдено от P8 с една GET заявка). Локално папката е изтрита, но `rsync` не трие файлове на сървъра.
2. **Сменете паролата на базата данни и `APP_KEY`** и прегледайте базата за злоупотреба за периода 01.10–27.10 (виж раздел 6, т. 2 — смяната на `APP_KEY` има странични ефекти).
3. **Деплойнете `config/log-viewer.php`** (иначе логовете остават достъпни анонимно) и пресъздайте кеша на конфигурацията.
4. Включете **пренасочване HTTP → HTTPS и HSTS** (днес `http://databackd.com/login` връща 200 с форма към `http://`).
5. Включете **2FA на 2-мата суперадмини и 4-мата админи** (днес 0 от 6).

## 4. Какво е поправено в кода (локално, още не е на production)

| Област | Поправка | Находки |
|---|---|---|
| Изолация | Имена в заглавията на справките/имейлите само от текущата компания; валута на импортера с изричен `company_id`; `RequireModule` спира потребител без компания | P1-01…03 |
| Права | Проверка на роля за `assigned_to`/`client_id` на заявките за анализ; CSRF изключенията се сравняват само по път (не по `?x=-webhook`) | P2-01, P2-02 |
| Инжекции | Whitelist на колоните в DataTables (`ColumnWhitelist`), на `periodicity`/колони в `PeriodBuckets`/`ReportBuilder`; лимити на `FormulaEngine` (дължина 1000, токени 400, вложеност 20, рекурсия 8); `e()` в имейли и toastr съобщения; валидация на валута и `deliverable_link` | P3-02…12 |
| Файлове | Частен диск `databackd_private`, UUID имена, `attachment` + `nosniff`; неутрализиране на `= + - @ TAB CR` във всички 8 Excel експорта (`NeutralizesFormulas`); лимити 50 000 реда/300 колони/20 листа при импорт; `mimes` при качване; dompdf без `enable_php` и без remote fetch | P4-01…06 |
| SSRF | `GoogleSheetsReader` е пренаписан: само `https://docs.google.com`, порт 443, без IP литерали, A+AAAA резолвиране, „закованo“ IP (`CURLOPT_RESOLVE`), ръчни редиректи (макс. 3, всеки валидиран), лимит 10 MB / 100 000 реда / 20 s, rate limit на „Свали“ и „Синхронизирай“, локален режим само по `APP_URL` | P5-01…10 |
| API | `FinApiGate` (HTTPS, метод, размер, без CORS), rate limit на откази/токен, одит лог без токена, валидация на структурата и границите на сумите, токен на деактивирана компания не пише, без stack trace; `XssLinearMerge` вместо квадратичния Froiden XSS | P6-01…09 |
| Конфигурация | `SecurityHeaders` (nosniff, Referrer-Policy, Permissions-Policy, X-Frame-Options SAMEORIGIN, CSP само Report-Only, HSTS само по HTTPS); бисквитката `secure` следва `APP_URL`; Log Viewer зад `auth` + `super-admin`; vendor маршрутите (`/update-database`, `/clear-cache`, …) само за суперадмин; `/check-env` скрит; `.gitignore` покрива `.env.*`, бекъпи, дъмпове, ключове | P7-01…10 |
| Автентикация | `AuthHardening`: 2FA кодът е вързан към сесията и с лимит; `choose-workspace` проверява собствеността; еднакъв отговор при reset на парола; ротация на `remember_token`; bcrypt 10 → 12; `databackd:demo` изисква силна парола извън `_local` | P9-01, 02, 04…07 |
| Upstream (оркестратор) | `UserAuth::validateLoginActiveDisabled()` с bindings вместо конкатенация (SQL injection); `enable_php` премахнат от 15 места (12 файла); изтрити `public/backup-and-restore/` и `public/check.php` | P9-03, P10-01, P7-04, P8-01, P8-11 |

## 5. Таблица на находките

Легенда за статус: **поправено** — в кода (локално, чака деплой); **за S10** — изисква действие на сървъра или в чужд файл; **описано** — не е поправено, обосновано в приложението; **приета** — приет риск с обосновка. Пълният сценарий за атака и поправката на всяка находка са в `findings/P<N>.md`.

| ID | Област | Файл | OWASP | Сериозност | Статус |
|---|---|---|---|---|---|
| P1-01 | Справки (P&L, Cash flow, Counterparties, Pivot print/PDF/Excel), планирани PDF и имейли на зап… | `app/Services/Databackd/Reports/ReportFilters.php:214` | A01 | висока | поправено |
| P1-02 | Импорт на данни: FinEntryImporter в опашка, FinImportController::progress() | `app/Services/Databackd/Data/FinEntryImporter.php:1012` | A04 | средна | поправено |
| P1-03 | Всички екрани на routes/databackd/ (middleware RequireModule) | `app/Http/Middleware/Databackd/RequireModule.php:21` | A01 | ниска | поправено |
| P1-04 | JOIN-ове към fin_accounts, fin_entities, fin_dimensions, users, client_details без company_id… | `DataTables/Databackd/Data/FinEntriesDataTable.php:44-49` | A01 | ниска | приета |
| P1-05 | Seeders и dev команди с withoutGlobalScope / избор на компания по име | `database/seeders/Databackd/DatabackdDemoSeeder.php:123` | A04 | ниска | приета |
| P2-01 | CSRF / всички web routes (в т.ч. всички AFV. POST/PUT/DELETE) | `app/Http/Middleware/VerifyCsrfToken.php:14-22` | A01 | средна | поправено + за S10 |
| P2-02 | Заявки за анализ (Teams) — client_id и assigned_to | `app/Http/Requests/Databackd/Teams/AnalysisRequest/UpdateAnalysisReque…` | A01 | средна | поправено |
| P2-03 | Директория „Специалисти“ — почасови ставки | `resources/views/databackd/teams/specialists/index.blade.php:~100-105` | A01 | ниска | за S10 |
| P2-04 | Супер админ на AFV. | `app/Http/Middleware/Databackd/RequireModule.php` | A01 | ниска | описано |
| P2-05 | Служител: обхват на правата във финансовите модули | `app/Http/Controllers/Databackd/{Data,Reports,Analytics,Planning}/**` | A01 | средна | приета |
| P2-06 | Модел на проверките за роля | `Databackd` | A01 | ниска | описано |
| P2-07 | „Скрити“ модули (menu ≠ защита) | `config/databackd.php` | A01 | ниска | описано |
| P2-08 | Роли и потребител в сесията | `app/Helper/start.php:89-109` | A01 | ниска | описано |
| P2-09 | Запазени справки — получатели | `app/Http/Controllers/Databackd/Reports/SavedReportController.php:~56-…` | A01 | ниска | описано |
| P2-10 | Статусни преходи на заявки за анализ | `AnalysisRequestController.php:~243` | A04 | ниска | приета |
| P2-11 | Списък „Заявки за анализ“ — клиентът вижда имената на специалистите | `AnalysisRequestController.php:~138` | A01 | ниска | приета |
| P2-12 | Масово присвояване — латентен риск | `app/Models/Databackd/*.php` | A04 | ниска | приета |
| P3-01 | PDF на справката (dompdf) | `app/Exports/Databackd/Reports/PivotPdf.php:24` | A03 | критична | поправено + за S10 |
| P3-02 | Ratio Builder — FormulaEngine без ограничения | `app/Services/Databackd/Analytics/FormulaEngine.php` | A04 | средна | поправено |
| P3-03 | FormulaEngine — съобщения за грешка | `FormulaEngine.php` | A03 | ниска | поправено |
| P3-04 | DataTables — търсене/сортиране по произволна колона | `app/DataTables/Databackd/Teams/TeamRequestsDataTable.php` | A01 | средна | поправено |
| P3-05 | PeriodBuckets::sqlExpression() — вход към суров SQL | `app/Services/Databackd/Reports/PeriodBuckets.php:56` | A03 | ниска | поправено |
| P3-06 | Уведомления по имейл — AnalysisRequest | `app/Notifications/Databackd/AnalysisRequestCreated.php:23` | A03 | ниска | поправено |
| P3-07 | Съобщения към toastr / form errors със стойности от потребител | `RatioBuilderController.php:170` | A03 | ниска | поправено |
| P3-08 | Таблица „Записи“ — валута в суров HTML | `app/DataTables/Databackd/Data/FinEntriesDataTable.php` | A03 | ниска | поправено |
| P3-09 | „Резултат от анализ“ — линк | `app/Http/Requests/Databackd/Teams/AnalysisRequest/DeliverableAnalysis…` | A01 | ниска | поправено |
| P3-10 | Параметри на DataTables — невалидни дати | `FinImportBatchesDataTable::query()` | A04 | ниска | поправено |
| P3-11 | Запазени справки — размер на config | `app/Http/Controllers/Databackd/Reports/SavedReportController.php:67` | A04 | ниска | поправено |
| P3-12 | Графики Frappe (x-bar-chart, x-pie-chart) — етикети в JS | `resources/views/components/bar-chart.blade.php:7,13,43,58,62,90` | A03 | ниска | за S10 |
| P3-13 | Хигиена в Blade — {!! json_encode() !!} | `resources/views/databackd/data/import/_mapping.blade.php:109-111` | A03 | ниска | за S10 |
| P3-14 | Глобален XSS middleware и config/xss_ignore.php | `app/Http/Middleware/Databackd/XssLinearMerge.php` | A03 | ниска | приета |
| P3-15 | Модул Gaps (чужд, в разработка) — наблюдения | `app/Services/Databackd/Gaps/BankImport/Camt053Parser.php:42` | A05 | ниска | описано |
| P4-01 | PDF на конструктора на справки (dompdf) | `app/Exports/Databackd/Reports/PivotPdf.php:24` | A03 | критична | поправено |
| P4-02 | Файлове към заявки за анализ (deliverable_file, коментарни прикачени файлове) | `app/Http/Controllers/Databackd/Teams/AnalysisRequestController.php:25…` | A01 | критична | поправено + за S10 |
| P4-03 | CSV/Excel formula injection при всички експорти с потребителски текст | `app/Exports/Databackd/Data/FinEntriesExport.php:map()` | A03 | висока | поправено |
| P4-04 | Импорт на Excel/CSV — DoS с много редове/колони/листове | `app/Services/Databackd/Data/FinEntryImporter.php:readFile()` | A04 | средна | поправено |
| P4-05 | dompdf конфигурация — ненужно отворен remote fetch (SSRF повърхност) | `app/Exports/Databackd/Reports/PivotPdf.php` | A05 | ниска | поправено |
| P4-06 | Импорт на Excel/CSV — валидация само по разширение | `app/Http/Controllers/Databackd/Data/FinImportController.php:upload()` | — | ниска | поправено |
| P5-01 | SSRF защитата се изключва по недоверен сигнал | `app/Services/Databackd/Data/GoogleSheetsReader.php` | A10 | висока | поправено |
| P5-02 | Редиректи без проверка | `GoogleSheetsReader.php` | A10 | висока | поправено |
| P5-03 | DNS rebinding / TOCTOU и IPv6 дупки в проверката | `GoogleSheetsReader.php` | A10 | висока | поправено |
| P5-04 | Без allowlist на схема/хост/порт; parseUrl приема всеки хост | `GoogleSheetsReader.php` | A10 | средна | поправено |
| P5-05 | Без лимит на размера/редовете → DoS | `GoogleSheetsReader.php` | A04 | средна | поправено |
| P5-06 | Без лимит на честотата на „Свали“ / „Синхронизирай сега“ | `app/Http/Controllers/Databackd/Data/FinDataSourceController.php:33-62` | A04 | средна | поправено |
| P5-07 | Отговорът се визуализира без проверка на Content-Type | `GoogleSheetsReader.php` | A10 | ниска | поправено |
| P5-08 | Липсва валидация при запис; URL от stash/записан източник не се проверява повторно | `FinDataSourceController.php` | A10 | ниска | поправено |
| P5-09 | localTemplatePath() приема всяко име на файл; хостът в http://host/databackd/templates/x се иг… | `GoogleSheetsReader.php` | A05 | ниска | поправено |
| P5-10 | ECB курсове: редиректи и честота | `app/Services/Databackd/Gaps/FxRates/FxRateService.php:195-199` | A10 | ниска | поправено |
| P5-11 | Кръстосана (собственик P4): RCE чрез company_name в PDF на pivot справката | `resources/views/databackd/reports/export/pdf.blade.php:11-24` | A03 | критична | поправено + за S10 |
| P6-01 | Обработка на грешки на api/ | `vendor/froiden/laravel-rest-api/src/Handlers/ApiExceptionHandler.php:…` | A05 | средна | поправено + за S10 |
| P6-02 | Глобален XSS middleware (всички POST/PUT) | `vendor/froiden/envato/src/Middleware/XSS.php` | A04 | висока | поправено |
| P6-03 | Валидиране на запис | `FinEntryImporter.php` | A03 | средна | поправено |
| P6-04 | Диапазон на сумите и дължини | `FinEntryImporter.php` | A04 | средна | поправено |
| P6-05 | Размер/тип на тялото, спам | `FinEntryApiController::store()` | A04 | средна | поправено |
| P6-06 | Автентикация: ограничаване, откази, одит | `FinApiTokenAuth.php` | A07 | средна | поправено |
| P6-07 | Деактивирана компания | `FinApiTokenAuth.php` | A01 | средна | поправено |
| P6-08 | CORS | `config/cors.php` | A05 | ниска | поправено |
| P6-09 | Транспорт | `FinApiGate.php` | A02 | средна | поправено + за S10 |
| P6-10 | Доверени прокси | `app/Http/Middleware/TrustProxies.php:15` | A05 | средна | за S10 |
| P6-11 | Живот на токена | `create_databackd_data_api_tokens_table` | A07 | ниска | описано |
| P6-12 | Дублиране / идемпотентност | `FinEntryApiController` | A04 | ниска | описано |
| P6-13 | Фиксиран демо токен в хранилището | `database/seeders/Databackd/Demo/41DataDemoSeeder.php` | A07 | ниска | за S10 |
| P6-14 | Текстове от API без strip_tags | `FinApiGate` | A03 | ниска | приета |
| P6-15 | Версия на framework | `composer.lock` | A06 | средна | за S10 |
| P6-16 | Информация за токените и версиониране | `IntegrationController::index()` | A04 | ниска | приета |
| P7-01 | Log Viewer API без автентикация | `config/log-viewer.php:90` | A01 | критична | поправено + за S10 |
| P7-02 | Неавтентикирани vendor maintenance routes | `vendor/froiden/envato/src/routes.php:12-35` | A01 | висока | поправено |
| P7-03 | /check-env — изтичане на информация | `vendor/froiden/envato/src/routes.php:38` | A01 | средна | поправено |
| P7-04 | public/backup-and-restore/ — аварийна restore програма без вход | `public/backup-and-restore/restore/restore-backup.php:2051-2056` | A01 | критична | репо: поправено (папката е изтрита); production: за S10 (СП… |
| P7-05 | Cookie Secure флаг зависи от автоматика | `config/session.php:171` | A02 | ниска | поправено |
| P7-06 | Липсват защитни HTTP заглавия | `app/Http/Middleware/Databackd/SecurityHeaders.php` | A05 | средна | поправено |
| P7-07 | Корен на уеб сървъра = корен на проекта; корен .htaccess не е в repo | `.remove-public-htaccess` | A05 | висока | за S10 |
| P7-08 | Sentry изпраща SQL bindings | `config/sentry.php:21` | A09 | ниска | поправено |
| P7-09 | SMTP без проверка на TLS сертификата | `config/mail.php:47` | A02 | ниска | поправено |
| P7-10 | .gitignore — пропуски (бекъпи, .env., ключове) | `.gitignore` | A05 | ниска | поправено |
| P7-11 | Tracked .env.dev + .env.example с примерни тайни (общ APP_KEY) | `.env.dev:8,60` | A02 | средна | за S10 |
| P7-12 | Демо парола и демо API токен в repo | `database/seeders/Databackd/DatabackdDemoSeeder.php:37` | A07 | ниска | приета |
| P7-13 | app.debug се презаписва от БД; framework 12.61.0 | `app/Http/Controllers/Controller.php:84-86` | A05 | ниска | описано |
| P7-14 | CORS: отразява всеки Origin с Allow-Credentials: true | `app/Http/Middleware/ForceCorsForApi.php:25-27,49-51` | A05 | ниска | описано |
| P7-15 | Неавтентикирани debug/maintenance routes в routes | `routes/web.php:959-977` | A05 | ниска | за S10 |
| P7-16 | Файловете в public/user-uploads се сервират без nosniff/CSP | `public/user-uploads/.htaccess` | A05 | ниска | за S10 |
| P7-17 | „Запази App Settings“ трие сесиите на всички потребители на всички компании (при database драй… | `app/Http/Controllers/AppSettingController.php:181` | A04 | ниска | описано |
| P7-18 | DB::prohibitDestructiveCommands(isProduction()) никога не е активно | `app/Providers/AppServiceProvider.php:44` | A05 | ниска | приета |
| P7-19 | Vendor „kill switch“ /down/{hash}, /up/{hash} и public/check.php | `vendor/froiden/envato/src/Traits/AppBoot.php:351-374` | A05 | ниска | приета |
| P8-01 | Production, уеб корен | `public/backup-and-restore/restore/restore-backup.php:2051-2057` | A01 | критична | репо: поправено (папката е изтрита); production: за S10 (СП… |
| P8-02 | Production, уеб корен | `.htaccess` | A05 | критична | поправено + за S10 |
| P8-03 | Production, транспорт | `.htaccess` | A02 | средна | за S10 |
| P8-04 | Production, заглавия | `public/.htaccess` | A05 | средна | за S10 |
| P8-05 | Production, акаунти | `user_auths` | A07 | средна | за S10 |
| P8-06 | Production, backup папки | `~/deploy-backup-databackd-mvp-s2/s4/s5-20261027/**` | A05 | средна | за S10 |
| P8-07 | Production, стар код | `~/public_html_5.4.6_old/` | A05 | средна | за S10 |
| P8-08 | Production, качени файлове | `public/user-uploads/databackd/analysis/**` | A01 | средна | за S10 |
| P8-09 | Production, PHP | `X-Powered-By: PHP/8.5.12` | A05 | ниска | за S10 |
| P8-10 | Production, PHP лимити | `~/public_html/php.ini` | A05 | ниска | за S10 |
| P8-11 | Production, остатъци в уеб корена | `public/check.php` | A05 | ниска | за S10 |
| P8-12 | Production, API | `GET/HEAD /api/databackd/fin-entries` | A05 | ниска | за S10 |
| P8-13 | Хостинг, слушащи портове | `ss -ltn` | A05 | ниска | за S10 |
| P8-14 | Production, дублирани входове | `www.databackd.com` | A05 | ниска | за S10 |
| P9-01 | Неавтентикирани, нелимитирани endpoints за имейл-2FA код (check-code, resend-code) — пълен byp… | `routes/web-public.php:77-79` | A07 | критична | поправено |
| P9-02 | choose-workspace логва автентикиран потребител като ПРОИЗВОЛЕН друг UserAuth (вкл. суперадмин/… | `routes/SuperAdmin/web.php:56` | A01 | критична | поправено |
| P9-03 | SQL injection чрез RFC quoted-string локална част на имейла ("a\"b"@x.test) в ръчно конкатенир… | `app/Models/UserAuth.php:142-146` | A03 | висока | поправено (оркестратор) |
| P9-04 | databackd:demo не изисквал силна --password при пускане извън _local база — публичната демо па… | `app/Console/Commands/DatabackdDemo.php:24-34` | A07 | висока | поправено + за S10 |
| P9-05 | Смяна/нулиране на паролата не прекратява активни сесии на други устройства (няма AuthenticateS… | `app/Actions/Fortify/UpdateUserPassword.php` | A07 | средна | частично поправено |
| P9-06 | User enumeration в „забравена парола“ — различен отговор/код за съществуващ срещу несъществува… | `vendor/laravel/fortify/src/Http/Responses/{Successful,Failed}Password…` | A07 | ниска | поправено |
| P9-07 | bcrypt rounds = 10 (Laravel-ският текущ подразбиращ се е 12) | `config/hashing.php:13` | A02 | ниска | поправено |
| P9-08 | Impersonation на компания (companies.login_as_company) няма запис в одит лог (само session, бе… | `app/Http/Controllers/SuperAdmin/CompanyController.php:561-582` | A09 | ниска | описано |
| P10-01 | dompdf enable_php + суров HTML | `app/Http/Controllers/InvoiceController.php:610,709` | A03 | критична | поправено (оркестратор) + за S10 |
| P10-02 | phpoffice/phpspreadsheet 1.30.5 (импорт на XLSX/XLS) | `app/Services/Databackd/Data/FinEntryImporter.php:100` | A06 | висока | за S10 |
| P10-03 | symfony/http-foundation 7.4.13 | `vendor/symfony/http-foundation/HeaderBag.php:150-151` | A06 | средна | за S10 |
| P10-04 | league/commonmark 2.8.2 | `vendor/laravel/framework/src/Illuminate/Mail/Markdown.php:191-205` | A06 | средна | за S10 |
| P10-05 | dompdf/dompdf 3.1.5 + chroot | `config/dompdf.php:81` | A06 | средна | за S10 |
| P10-06 | laravel/framework 12.61.0 | `composer.lock` | A06 | средна | за S10 |
| P10-07 | guzzlehttp/guzzle 7.10.5 / guzzlehttp/psr7 2.10.4 | `app/Services/Databackd/Gaps/FxRates/FxRateService.php:196` | A06 | ниска | за S10 |
| P10-08 | npm: quill 1.3.7 (+ quill-mention, quill-emoji) | `webpack.mix.js:31-34` | A03 | средна | описано |
| P10-09 | pcinaglia/laraupdater (неподдържан самообновител) | `vendor/pcinaglia/laraupdater/src/Http/routes.php:7-11` | A05 | ниска | за S10 |
| P10-10 | Вендорени JS библиотеки извън npm | `public/vendor/jquery/jquery.min.js` | A06 | ниска | описано |
| P10-11 | mtdowling/jmespath.php 2.8.0 (CVE „критична“ по CVSS) | `vendor/mtdowling/jmespath.php/src/Env.php:42-47` | A06 | ниска | за S10 |
| P10-12 | maatwebsite/excel 3.1.69 (CVE „висока“ по CVSS) | `Excel::store` | A06 | ниска | за S10 |
| P10-13 | phpseclib/phpseclib 3.0.52, league/flysystem 3.34.0 | `vendor/laravel/socialite/src/Two/FacebookProvider.php` | A06 | ниска | за S10 |
| P10-14 | symfony/yaml 7.4.13 (dev-only) | `composer.lock` | A06 | ниска | приета |
| P10-15 | npm: moment (node_modules), clean-css / emoji-data-css | `node_modules/moment` | A06 | ниска | приета |
| P10-16 | Хигиена на зависимостите (lock, частни хранилища, dev-пакети, PHP 8.5, неизползвани/изоставени) | `composer.json` | A06 | ниска | описано |

## 6. Какво остава и препоръки за production (S10 или човек)

Подредено по приоритет. Командите са дадени в `findings/P8.md` (раздел „За S10“), `P7.md`, `P6.md`, `P4.md`, `P9.md`, `P10.md`.

**А. Веднага (човек или S10 със съгласието на собственика):**
1. `rm -rf ~/public_html/backup-and-restore ~/public_html/check.php`; `Require all denied` за тези пътища в `.htaccess`. (P7-04, P8-01, P8-11)
2. **Ротация на тайните след изтичането на `.env`** (P8-02): нова парола на DB потребителя; нов `APP_KEY`. Внимание: смяната на `APP_KEY` прави нечитаеми криптираните стойности в базата (напр. `two_factor_secret`, записани пароли за пощата/гейтуей) и прекъсва всички сесии — преди смяна да се инвентаризират криптираните колони и да се подготви повторно въвеждане. Да се провери и дали `APP_KEY` на production не съвпада с примерния ключ от `.env.dev`/`.env.example` (P7-11; командата сравнява префикс на sha256 и не печата ключа). Прегледайте `users`/`user_auths`/`companies`/`failed_jobs` за необичайни промени от периода 01.10–27.10 и логовете за достъп.
3. Деплой на файловете на S6 (виж handoff `06-security.md`), след това `optimize:clear && config:cache && route:cache` (иначе `config/log-viewer.php`, `session.php`, `security-headers.php` не влизат в сила).
4. **HTTPS:** пренасочване на `http://` и `www.`, HSTS (`max-age=31536000`; `includeSubDomains` само след проверка на поддомейните), коренов `.htaccess` с блокиране на dotfiles, `storage/`, `vendor/`, `docs/`, `*.sql|*.log|*.bak|*.backup`, `composer.*`, `artisan` (P7-07, P8-03/04/14; LiteSpeed не презарежда `.htaccess` без рестарт). Ако уеб коренът е `public_html` (а не `public_html/public`), да се премести на `public/`.
5. **2FA** за всички `superadmin` и `admin` акаунти (P8-05); reCAPTCHA за вход, ако е уместно.
6. Преместване на съществуващите файлове от `public/user-uploads/databackd/analysis/*` в диска `databackd_private` (`storage/app/databackd`) и `Require all denied` за `user-uploads/databackd/` (P4-02, P8-08).
7. Права и остатъци: `chmod 700` на `~/deploy-backup-*` (в тях са дъмпове и кеширана конфигурация с тайни); решение за `~/public_html_5.4.6_old` (стар екземпляр с `.env` и дъмп 6,8 MB, групово четим) — архивиране извън уеб достъп и изтриване (P8-06/07).
8. Демо акаунтите на production да не ползват публичната парола по подразбиране (`DatabackdDemoSeeder::PASSWORD`); демо API токенът от репото да не съществува в `fin_api_tokens` (SQL в `P6.md`). **Преди `git push` към GitHub** (S10): да се махне паролата от `docs/mvp/DEMO.md` и handoff-ите, `git rm --cached .env.dev`, празен `APP_KEY` в `.env.example` (P7-10/11, P9-04, P6-13).

**Б. Файлове на други сесии (не са пипани от S6):**
- `resources/views/databackd/reports/export/pdf.blade.php:11-23` — изтрийте мъртвия блок `<script type="text/php">` (P3-01, P4-01, P5-11).
- `resources/views/databackd/data/import/_mapping.blade.php:109-111`, `result.blade.php:142` — `{!! json_encode(X) !!}` → `@json(X)`; `resources/views/components/bar-chart.blade.php` (редове 7, 13, 43, 58, 62, 90) и `pie-chart.blade.php` (19, 32) — `@json(e($label))`, `@json($color)` (P3-13).
- `resources/views/databackd/teams/specialists/index.blade.php` — блокът със ставката да е в `@if ($canEdit)` (P2-03).
- `routes/web.php:959-977` — изтрийте `/test-broadcast`, `/debug-broadcast`; `routes/web-public.php:137` — `/sync-user-permissions` зад `auth` (P7).
- `resources/lang/**` (S7) — нови ключове: `databackd-data-sources.errors.{url_not_allowed,redirect_blocked,too_many_redirects,too_large,too_many_rows,rate_limited}`, `databackd-gaps-fx-rates.errors.rate_limited`, `databackd-gaps-bank-import.attributes` (липсата му дава 500 на `bank-import` POST).
- S8: файлове на S8, редактирани от S6 — `RequireModule.php` (`abort_if(!company(), 403)`), `FxRateService.php` (без редиректи), `FxRateController.php` (rate limit), `StoreAlertRuleRequest.php` (`e()`); Gaps PDF класовете `MarginPdf`/`StatutoryPdf` задават `isRemoteEnabled = true` (да се стесни както в `Reports/*Pdf`); `Camt053Parser` проверява `DOCTYPE` само върху сурови байтове (UTF-16 го заобикаля; външни ентити не се зареждат); Gaps контролерите връщат `Reply::error($e->getMessage())` без `e()`.

**В. Зависимости (P10; не са ъпдейтвани в тази сесия):**
1. Премахнат `enable_php` — **направено**. 2. `phpoffice/phpspreadsheet` 1.30.5 → 1.30.7 и `maatwebsite/excel` → 3.1.70 (импорт на качени XLSX/XLS: XXE/DoS). 3. `symfony/http-foundation` 7.4.13 → 7.4.21 (неавтентикиран ReDoS през `Cache-Control`). 4. `league/commonmark` → 2.10.3, `dompdf/dompdf` → 3.1.6 и стесняване на `chroot` в `config/dompdf.php`. 5. `laravel/framework` 12.61.0 → 12.69.3 като отделна стъпка с пълна регресия (критичният advisory за `TokenGuard` не засяга проекта — гардовете са `session`). Блокери: `composer update` пада с `Permission denied (publickey)` за частните Froiden хранилища; production няма `composer` (rsync на засегнатите `vendor/` директории + `installed.json|php` и autoload файловете); web SAPI е PHP 8.5.12, а `phpspreadsheet 1.30.x` декларира `<8.5.0` — тестът да е през реална уеб заявка. Quill 1.3.7 (XSS, няма поправена 1.x) не се инициализира в нито един изглед на AFV — за S9.

**Г. Решения на собственика:**
- Финансовите модули не ползват системата за права на databackd: всеки служител има пълен достъп до импорт, сметкоплан, консолидация, бюджети (вкл. одобряване) и източници (P2-05, приета). Препоръка: одобряване на бюджет и `undo` на импорт само за админ — по един ред в `BudgetController::approve` и `FinImportBatchController::undo`.
- Content-Security-Policy е само Report-Only и без колектор; да се премине към enforcing след няколко седмици наблюдение (необходим е route за `/csp-report`, който е на S8).
- `TrustProxies` с `'*'` позволява подправяне на IP през `X-Forwarded-For` (rate limit по IP) — да се ограничи до адресите на реалния прокси (P6-10).

## 7. Проверки

| Проверка | Резултат |
|---|---|
| `php artisan databackd:check-security` | **83 от 83** (static 26, pure 14, isolation 10, roles 6, api 14, http 8, auth 5 — по групи) |
| `php docs/mvp/tools/smoke.php databackd. --ajax` (админ) | 154 OK, 2 очаквани (`counterparties.show` без `?name=` пренасочва към списъка) |
| `smoke.php databackd. --email=g.petrov@demofinans.bg` (служител) | 75 OK; очаквани: `counterparties.show`, `gaps.alerts.create` и `gaps.onboarding.index` (само за админ) |
| `smoke.php databackd. --email=p.yordanov@balkan.bg` (клиент) | 5 OK (екраните на Teams); останалите 73 са очакван 403 |
| `databackd:check-formulas` / `check-consolidation` / `check-forecasts` | 109/109 · 25/25 · 69/69 |
| Скриптове на пакетите (извън репото) | P1: 172 заявки на роля към чужди ID; P5: 188 проверки; P6: 91 проверки (преди поправките 21 провала, след тях 0) |

`databackd:check-security` е само за локална база (`*_local`), като всичко се пише в транзакция, която се връща назад. Съдържа контролни проби (собствен запис на демо компанията НЕ трябва да връща 403/404), за да е ясно, че 404-тата в тестовете за чужди ID означават реална защита, а не счупен тест. **След всеки деплой и след добавяне на нови routes (S8) командата да се пуска наново.**

## 8. Ограничения на прегледа

- **Production** е проверен само отвън (HTTP) и по SSH за четене. Не са проверени: външната достъпност на портовете (3306, 7080 слушат на `0.0.0.0`), реалният `php.ini` на уеб SAPI, съдържанието на `.env`/логовете, коренният `.htaccess` (не е в репото), паролите на демо акаунтите, `storage/installed`.
- **Модулът „Gaps“ на S8** е добавян паралелно по време на прегледа: P1, P2 и P3 са го прегледали в момента на пускането, но routes и файлове, добавени след това, не са покрити. Рецептите в `P1.md` и `P2.md` и командата `databackd:check-security` трябва да се повторят върху финалния му вид.
- **JavaScript в истински браузър** не е изпълняван (XSS е проверен по изходния HTML/JSON и по прочит на JS sink-овете).
- **Реален несинхронен queue worker** и реален IPv6 не са пробвани; жив `eval` PoC срещу dompdf е възпроизведен от P3 в изолиран скрипт.
- **Upstream Ð±Ð°Ð·Ð°ÑÐ° (Froiden)** е прегледан само там, където е достъпен през пътищата на AFV или е откриван от пакетите (auth, vendor маршрути, PDF контролери). Пълен одит на ~240 модела и контролерите на AFV не е част от обхвата.
- Нямаше достъп до база с advisories за самия `laravel/framework` извън `composer audit`.

## 9. Инциденти по време на прегледа (прозрачност)

- P8 направи 39 заявки към production и 3 команди `artisan tinker` със `SELECT`/`SHOW` по SSH; следи: записи в access log, ~2 `ERROR` в `laravel.log` (GET към API, което връща 500 в старата версия), няколко сесийни файла и една PHP сесия от `restore_auth`. Нищо не е променено на сървъра.
- P7 при тест извика `/clear-cache` и `/refresh-cache` локално като суперадмин; това създаде `bootstrap/cache/config.php` и `events.php`, които бяха изтрити веднага. Проверено: кешът не съществува.
- Втори пълен пуск на ролевата матрица на P2 изпълни празни POST към още недовършени ендпойнти на S8 в `databackd_local` (добавени 24 курса на ЕЦБ, изтрити; 5 съществуващи курса презаписани с актуалните; нулиран `completed_at` на `fin_onboarding`). Само локална база; матрицата вече прескача такива ендпойнти.
- Агенти редактираха няколко файла на S8 (виж 6.Б) с малки точни `Edit`-и; тези промени са в работното дърво на S8 и ще влязат в неговия commit.

## 10. Приложения

`docs/mvp/security/findings/P1.md … P10.md` — по пакет: таблица по шаблона (ID, област, файл:ред, OWASP, сериозност, сценарий, поправка, статус), „Проверено без находки“, „Рецепта за проверка“, „За S10“.

