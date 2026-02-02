# S6 — Сигурност: технически документ

Документация на мерките за сигурност, добавени след прегледа от октомври 2026 (Етап 3 „Тестване за сигурност“ → доказателство `docs/mvp/security/2026-10-security-review.md`; Етап 4 „Техническа документация“). Handoff: `docs/mvp/handoff/06-security.md`.

## 1. Модел на заплахите

| Актьор | Какво може законно | Какво не бива да може |
|---|---|---|
| Гост | страниците за вход/регистрация, публичните формове | всичко останало; логове, `/update-database`, restore инструменти |
| Клиент (`client`) | заявки за екип и за анализ — само свои | финансови данни, справки, импорт, интеграции, чужди заявки (дори на друг клиент в същата компания) |
| Служител (`employee`) | финансовите екрани на компанията според пакета | API токени, правила за аларми, чужди компании |
| Админ на компания (`admin`) | всичко в своята компания | други компании, vendor маршрути, логове |
| Суперадмин | управление на компании/пакети | финансовите данни на компаниите (AFV екраните го отказват, защото няма `company()`) |
| Интегрираща система | `POST /api/databackd/fin-entries` с токен на една компания | всичко друго; записи в друга компания |

Тенантът е `company_id`. Защитата има три нива: **(1)** глобален `CompanyScope` на всички 27 модела `App\Models\Databackd\*`; **(2)** проверки на роля/модул в контролерите и `RequireModule`; **(3)** изричен `company_id` във всеки код без сесия (API, опашки, команди, PDF/Excel класове, `DB::table()` заявки).

## 2. Стек от middleware (`app/Http/Kernel.php`)

| Клас | Къде | Какво прави |
|---|---|---|
| `SecurityHeaders` | глобален | заглавията от `config/security-headers.php` върху всеки отговор (вкл. 401/404/500 и API) |
| `FinApiGate` | глобален | само за `api/databackd/*`: изисква HTTPS (при `APP_URL=https`), позволява само `POST` (иначе 405), `Content-Type: application/json` (иначе 415), лимит на тялото (413) **преди** да се чете тялото; маха CORS заглавията |
| `RestrictVendorMaintenanceRoutes` | глобален + `web` | `/update-database`, `/clear-cache`, `/refresh-cache`, `/admin/update-version/*` → 404, освен за суперадмин; `/check-env` → 404 за всички |
| `XssLinearMerge` | глобален | замества `Froiden\Envato\Middleware\XSS` — същото чистене на входа, но с една `merge()` вместо квадратична |
| `VerifyCsrfTokenPathOnly` | `web` | CSRF; изключенията (`*-webhook` …) се сравняват само по път, не по пълния URL с query string |
| `AuthHardening` | `web` | виж § 7 |
| `FinApiTokenAuth` | маршрут на API | токен → компания, rate limit, одит |
| `RequireModule` / `RequireCompanyModule` | AFV routes (S8) | модул във включения пакет; `abort_if(!company(), 403)` |

## 3. Изолация между компании
- Всички модели: `HasCompany` + колона `company_id` (проверява се от `databackd:check-security`).
- Контролерите ползват `findOrFail($id)` на модели със scope (няма route model binding към чужд запис); правилата `exists`/`unique` са с `company_id`.
- Имена в заглавията/имейлите на справките (`ReportFilters::summary()`) се четат само с `where('company_id', …)`; `ReportFilters::fromArray(array $q, ?int $companyId = null)` носи компанията в код без сесия (`databackd:send-reports`, `SavedReportMail`).
- `FinEntryImporter` чете базовата валута на компанията с изричен `company_id` (важно при `queue:work` под сесията на друга компания).
- Суперадмин без компания → 403 на всички AFV екрани.

## 4. Входни данни и инжекции
- **SQL:** няма суров SQL с потребителски вход. Динамичните части (`periodicity`, колони на групиране/сортиране) минават през whitelist: `PeriodBuckets::sqlExpression()` хвърля `InvalidArgumentException` за непозволена колона, нормализира `periodicity`; `ReportBuilder::normalizeConfig()` и `ReportFilters` връщат стойност по подразбиране при непознат вход (включително JSON конфигурацията на запазените справки — „втора инжекция“). DataTables (`FinEntriesDataTable`, `FinImportBatchesDataTable`, `TeamRequestsDataTable`) ограничават търсенето и сортирането до декларираните колони (`ColumnWhitelist`).
- **Формули (`FormulaEngine`)**: собствен парсер, без `eval`. Лимити: дължина 1000 знака, 400 токена, 30 променливи, вложеност 20, дълбочина на препратки между коефициенти 8, 400 изчисления; `INF`/`NAN` → `null`; съобщенията за грешка не връщат сурова формула. Проверката е и при запис, и при изчисление.
- **XSS:** изходът се екранира (`{{ }}`, `e()`); в PHP кода (DataTables колони, `Reply::error`, имейл известия) данните от потребителя минават през `e()`. Глобалният Froiden XSS filter (`strip_tags` за POST/PUT) е допълнителна, не основна защита.
- **Валидация:** валута `alpha:ascii|size:3`; `deliverable_link` не приема `//host` и `/\host`; `config` на запазена справка ≤ 20 000 знака.

## 5. Файлове, импорт и експорт
- **Импорт:** `mimes:xlsx,xls,csv,txt`, ≤ 20 MB, ≤ 50 000 реда, ≤ 300 колони, ≤ 20 листа (за xlsx/xls — `listWorksheetInfo()` преди пълното зареждане, за csv — ранно прекъсване). Резултат от `FinEntryImporter::validateRow()` винаги е в границите на колоните (сума ≤ 999 999 999 999.99).
- **Файлове към заявки за анализ:** диск `databackd_private` (`storage/app/databackd`, извън `public/`), име `uuid.ext`, допълнително забранени `svg/html/htm`, сваляне само през контролера с проверка на собственост, `Content-Disposition: attachment` + `X-Content-Type-Options: nosniff`. `AnalysisRequest::resolveDownload()` пробва първо новия диск, после старата публична папка (файлове, качени преди поправката).
- **Експорти:** trait `App\Exports\Databackd\Concerns\NeutralizesFormulas` (`neutralizeFormula(mixed): mixed`, `neutralizeRow(array): array`) слага `'` пред низове, започващи с `= + - @ TAB CR`; числата и датите не се пипат. Ползва се във всички 8 `*Export` класа и в CSV на грешките от импорта.
- **PDF (dompdf):** `enable_php = false` (никъде в кода), `isRemoteEnabled = false`; номерацията на страниците на справката се рисува с `Canvas::page_text()`.

## 6. Външни URL (SSRF)
`GoogleSheetsReader::assertSafeUrl(string $url, bool $resolve = true, bool $redirect = false): string` — единствена входна точка за всеки URL към външна система:
- схема `https`, порт 443, хост точно `docs.google.com` (при редирект и `*.googleusercontent.com`); отхвърля `user:pass@`, IP литерали във всякаква нотация, `\`, не-ASCII и контролни знаци, неканоничен хост (trailing dot, главни букви се нормализират);
- резолвира A и AAAA, всички адреси трябва да са публични (`isPublicIp()` — не loopback/private/link-local/metadata/CGNAT/multicast/IPv4-mapped), връзката е „заковала“ към проверените IP-та (`CURLOPT_RESOLVE`), реалният peer се сверява с `CURLINFO_PRIMARY_IP`;
- редиректите се следват ръчно (≤ 3), всеки хоп се валидира наново; редирект към страница за вход на Google = „таблицата не е публична“;
- timeout 5 s на връзка / 20 s общо, ≤ 10 MB (след декомпресия), ≤ 100 000 реда; HTML/JSON/XML/ZIP отговори се отхвърлят;
- „Локален режим“ (четене на шаблоните от диска) се определя само от хоста на `APP_URL`, не от `app.debug`, `Host` или `APP_ENV`;
- rate limit: 12 заявки за 5 минути на потребител и 60 на час на компания (`FinDataSourceController`); изтегляне на курсове от ЕЦБ: 10 на час на компания, без редиректи.

## 7. Автентикация (`AuthHardening`)
- `check-code` / `resend-code` (вход с код по имейл): работят само ако в сесията има успешно преминала парола (`login.id`); `check-code` ≤ 5 опита за 600 s, `resend-code` ≤ 1 за 60 s и ≤ 5 за 900 s; 403 без challenge.
- `choose-workspace`: избраният `user_id`/`company_id` трябва да принадлежи на `UserAuth` на логнатия потребител (преди — всеки потребител можеше да стане всеки).
- `forgot-password`: един и същ отговор за известен и неизвестен имейл; смяна/reset на парола ротира `remember_token`. Login: Fortify limiter 5/мин. по имейл+IP. bcrypt `rounds = 12`.
- `databackd:demo` извън `*_local` изисква `--password` ≥ 12 знака, различна от публичната.

## 8. API токени (`POST /api/databackd/fin-entries`)
Токен `dbk_` + `Str::random(40)`; в базата е само SHA-256 хеш (`token_hash`, unique) и последните 4 знака; показва се веднъж при създаване; приема се само в `Authorization: Bearer` (не в query, тяло, `X-Api-Key`). `company_id`, `source`, `created_by`, `import_batch_id` идват от токена, не от тялото. Лимити в `config/databackd-api.php`: тяло 2 MiB, 1000 записа на заявка, 200 нови стойности на разрези на заявка, 60 заявки/мин. на токен, 10 неуспешни/мин. на IP, 50 000 записа/час на токен. Отнет токен, токен на деактивирана компания, не-низови/дълги credentials → 401/403 без изключение. Одит: `storage/logs/databackd-api-<дата>.log` (`import`, `auth_failed`, `ip_blocked`, `token_created`, `token_revoked`), никога токенът. Токените се създават и отнемат само от админ (`FinApiTokenController`, одитирани).

## 9. Заглавия и конфигурация (`config/security-headers.php`)
`X-Content-Type-Options: nosniff` · `Referrer-Policy: strict-origin-when-cross-origin` · `Permissions-Policy: camera=(), microphone=(), geolocation=(self), payment=(self), usb=()` · `X-Frame-Options: SAMEORIGIN` (публичните формове за вграждане са изключени) · `Content-Security-Policy-Report-Only` (не е блокираща) · `Strict-Transport-Security` само по HTTPS при `APP_URL=https`. Променливи: `SECURITY_HEADERS_ENABLED`, `SECURITY_X_FRAME_OPTIONS`, `SECURITY_REFERRER_POLICY`, `SECURITY_PERMISSIONS_POLICY`, `SECURITY_HSTS_ENABLED|MAX_AGE|INCLUDE_SUBDOMAINS|PRELOAD`, `SECURITY_CSP_REPORT_ONLY`, `SECURITY_CSP_REPORT_URI`, `SECURITY_RESTRICT_VENDOR_ROUTES`, `DATABACKD_API_REQUIRE_HTTPS`. Бисквитка на сесията: `http_only`, `same_site=lax`, `secure` при `APP_URL=https`. Log Viewer API: `web, auth, super-admin` + `AuthorizeLogViewer`.

## 10. Проверка
`php artisan databackd:check-security [--only=static,pure,isolation,roles,api,http,auth] [--show]` — 83 проверки (static 26 · pure 14 · isolation 10 · roles 6 · api 14 · http 8 · auth 5):
- **static** — всички модели с `HasCompany`; няма `enable_php=true`, `backup-and-restore`, суров mass assignment, `unguard`, `eval`; сесийна бисквитка, bcrypt, `.gitignore`; регистрирани middleware; всички експорти с `NeutralizesFormulas`; частният диск.
- **pure** — 20 враждебни URL за SSRF + приети Google URL-и, `isPublicIp`, враждебни формули, whitelist-и на SQL, неутрализиране на формули.
- **isolation** — в транзакция създава записи във втората компания и проверява със заявки като админ и служител (15 GET/PUT/POST/DELETE към чужди ID), че са отказани и не са променени; клиент срещу клиент; **контролни проби**, че същите URL за собствен запис достигат до контролера.
- **roles** — от 78-те GET екрана на AFV без параметри клиентът получава 403 на всички освен на „Teams“ (заявки за екип и за анализ); суперадминът без компания — 403 на всички; служителят не управлява токени; гостът не стига до екраните; контролни проби за админ и служител.
- **api** — липсващ/невалиден/отнет токен, не-низови credentials, токен извън `Authorization`, методи, `Content-Type`, размер, брой записи, валиден запис със игнорирани чужди полета, токен на друга компания, CORS.
- **http** — заглавията на `/login`, middleware на vendor маршрутите, Log Viewer за гост, CSRF (и с `?x=-webhook`).
- **auth** — `check-code`/`resend-code` без challenge, `choose-workspace` към чужда идентичност, еднакъв отговор на `forgot-password`.

Командата отказва да работи извън `*_local` база, върти всичко в транзакция и не оставя данни. Допълнителни автоматизирани проверки: `databackd:check-formulas`, `check-consolidation`, `check-forecasts` и `docs/mvp/tools/smoke.php` (виж handoff). Подробните рецепти на пакетите: `docs/mvp/security/findings/P1.md … P10.md`.

## 11. Известни ограничения
Виж `docs/mvp/security/2026-10-security-review.md` § 6 „Какво остава“ и § 8 „Ограничения“: production още не е обновен; зависимостите не са ъпдейтвани; CSP е само Report-Only; финансовите модули не ползват системата за права на AFV (всеки служител има пълен достъп); views/routes/преводи с указани поправки са на други сесии.
