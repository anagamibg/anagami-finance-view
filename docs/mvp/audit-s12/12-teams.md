# A12 — услуга „Екипи“ (заявки за екип, заявки за анализ, специалисти)

Одит по `00-rules.md`, 2026-10-28. Без миграции, без `npm run`, без commit.

## Какво е проверено
- **Роли:** клиент `p.yordanov@balkan.bg` (#11), втори клиент `i.stoycheva@rodopi.bg` (#12, за IDOR), админ `admin@demofinans.bg`, служител/специалист `g.petrov@demofinans.bg`.
- **Заявки за екип:** списък (DataTable, филтри, KPI), форма за създаване (размери 1/2/5/10/20/50+/друг, роли, часове/пълно време, начало, срок, жива цена), детайл (KPI, данни, timeline, специалисти, часове), смяна на статус, назначаване, създаване на проект, изтриване, известия.
- **`PriceEstimator`:** проверена математика — средна ставка по роля × часове × 4,33 (напр. 2×35 + 2×24 + 38 = 156 €/ч × 10 ч × 4,33 = 6 754,80 €; пълно работно време 156 × 40 × 4,33 = 27 019,20 €), резервни ставки, ограничения на входа.
- **Заявки за анализ:** Kanban (jquery-ui sortable) и списък, създаване от клиент, прикачени файлове (частен диск `databackd_private`, достъп само за собственика, 404 за чужд клиент), коментари (XSS), смяна на статус, резултат (файл/линк), оценка със звезди, `databackd:recurring-analysis --dry-run` (0 за създаване).
- **Специалисти:** карти, заетост, филтри, модал за редакция, права (клиент 403, служител без редакция).
- **Реални POST тестове** (временен скрипт през Laravel kernel, 39 проверки, всички PASS, записите и известията изтрити след теста): виж „Проверка“.
- **Снимки** (`storage/app/snapshots/a12/`): клиент — нова заявка за екип (десктоп+мобилен), заявка с оферта (#32 като клиента ѝ, мобилен), заявка за анализ (#134), нова заявка за анализ, списък с заявки (мобилен); админ — заявка #32, Kanban (десктоп+мобилен), специалисти.

## Намерени и поправени грешки
1. **Критично — клиентът не можеше да изпрати заявка за екип** (`resources/views/databackd/teams/requests/create.blade.php`, JS `collectRoles/usedRoles`). bootstrap-select копира класовете на `<select>` върху обвиващия го `<div>`, затова `.find('.role-select')` намираше `div`-а (`val()` = ""): броячът показваше „Разпределени 0 от 5“, цената „0 €“, а при изпращане ролите липсваха → винаги грешка „Добавете поне една роля“. Поправено със `select.role-select`. (Грешката е от S5/S9 и е и на production.)
2. **Грешките за ролите и размера не се показваха** (`create.blade.php`) — няма поле с име `roles`/`team_size`, froiden-helper не намираше къде да покаже текста. Ролите и размерът са обвити във `.form-group` (`id="roles"`).
3. **Смяна на статус без правила** (`TeamRequestController::changeStatus`) — админът можеше да мести заявка от всеки статус във всеки (напр. „Приключена“ → „Нова“). Добавени `TeamRequest::TRANSITIONS` (new→reviewing/proposed/rejected; reviewing→proposed/rejected; proposed→active/reviewing/rejected; active→paused/completed; paused→active/completed; completed — краен; rejected→reviewing), проверка в контролера (`messages.status_not_allowed`) и само позволените статуси в падащото меню.
4. **Kanban/падащото меню на статуса третираха всеки успешен ход като грешка** (`AnalysisRequestController::move`) — `Reply::successWithData(..., ['status' => 'delivered'])` презаписваше `status: success`; Kanban презареждаше страницата след всяко местене, а в детайла смяната не обновяваше екрана. Ключът е преименуван на `analysis_status`.
5. **Хиляди без интервал в разбивката на цената** (`create.blade.php`, `fmt`) — `Intl bg-BG` не групира 4-цифрени числа („3031 €“). Сега „3 031 €“. Основата „5 × 10 ч/седм.“ → „5 души × 10 ч/седм.“ (`people_label` от `estimate`).
6. **Специалист не може да се добави от интерфейса** (`SpecialistController`) — профили имаше само от демо seeder-а, т.е. реална компания не може да ползва услугата (оценката пада на резервните ставки, назначаване — „Няма свободни профили“). Добавено „Добави специалист“ (модал за служител без профил) през съществуващите маршрути с `id = 0` (`GET specialists/0/edit`, `PUT specialists/0`), без нов route.
7. **Имейли през непотвърден SMTP** (`TeamRequestCreated`, `TeamRequestStatusChanged`) — за разлика от известията за анализ, тези пращаха mail и при непотвърден SMTP (в sync опашка това бави/чупи заявката). Добавено `TeamRequestCreated::smtpUsable()`.
8. **Валидиране:** начало на заявка за екип в миналото (за клиент; служител може да въведе съществуващ ангажимент); краен срок на нова заявка за анализ в миналото; `estimate` ограничаваше часовете до 60 при максимум 40 във формата.
9. **Линк на резултата** (`analysis/show`) — рендира се само път `/…` или `http(s)://` (`$safeLink`), с `rel="noopener noreferrer"`; старите редове не са били валидирани.
10. **Текстове в JS** (`requests/index`) — `"@lang(...)"` в JS низове → `@json(__())` (кавичка в превода чупеше скрипта). Цената на специалиста беше със символа на валутата на компанията, а ставките са в EUR (форма и `PriceEstimator`) — сега винаги „€“.

## UX подобрения
- **Обяснение на услугата:** карта „Как работи услугата“ (4 стъпки: заявка → оферта → приемане → месечни задачи) до цената във формата за екип; „Как работи“ във формата за заявка за анализ.
- **Оферта и решение на клиента:** при „Изпратено предложение“ админът задава месечната цена по офертата (попълнена с оценката; пише се в историята „Оферта: X € на месец“). Клиентът вижда карта „Какво следва“ според статуса с бутони „Приемам офертата“ / „Отказвам офертата“ / „Оттегли заявката“ (`TeamRequest::CLIENT_TRANSITIONS`, коментар по желание, известие към админите), а при активна заявка — „Нова заявка за анализ“ (с предварително избран екип). Етикетът на цената: „Ориентировъчна цена на месец“ (≈, без стотинки) → „Цена на месец по офертата“ (точна).
- **Timeline на заявка за анализ:** стъпки Нова → В работа → За преглед → Предадена → Затворена + изречение какво следва (отделно за клиент и за екипа).
- **Изходни данни при създаване:** клиентът прикачва файл (Excel и т.н., до 20 MB) директно към новата заявка — записва се като първи коментар (частен диск). Подсказка за размер/видимост и при коментарите.
- **Празни състояния с действие:** заявки за анализ без нито една заявка — обяснение + „Нова заявка“ вместо 5 празни колони; филтриран празен списък — „Изчисти филтрите“; специалисти — обяснение + „Добави специалист“.
- **Достъпност/мобилен:** карти за размер на екипа с `role="button"`, `tabindex`, `aria-pressed`, Enter/Space, видим фокус; `aria-label` на бутоните ±, селекта и броя в ролите; звездите за оценка са бутони 44×44 px с `aria-pressed`/етикет „N от 5“ (бяха `<i>` само с мишка); етикети към textarea на коментар и отзив; при клиент без проект скрита картата „Отработени часове“ (показваше „—“ и техническо обяснение); KPI „Ангажираност“ с кратка стойност (на телефон се режеше).

## Нови SCSS partial-и и lang ключове
- SCSS: само `resources/scss/databackd/_teams.scss` (вече импортиран): `.dbk-teams-layout.is-single`, `:focus-visible` на картите за размер, `.an-rate-stars .an-star`, нов блок „S12: client guidance“ — `.dbk-teams-next*`, `.dbk-teams-howto`, `.dbk-teams-steps`, `.dbk-teams-progress-card`, `.dbk-teams-stepper`, `.dbk-teams-step-hint`. Нужен е `npm run prod` от координатора.
- `databackd-teams-requests` (bg+eng): `form.count_down/count_up`, `show.price` (нов текст), `show.price_offer`, `status.offer_price/offer_price_help/offer_log`, `messages.status_not_allowed`, `validation.start_past`, `notify.client_status_text`, секции `howto.*`, `next.*`, `client_action.*`; `people` с форма за 0.
- `databackd-teams-analysis` (bg+eng): `empty.text_client`, `form.file/file_help/attachment_comment`, `comments.file_help`, `rating.star`, `validation.due_past`, секции `howto.*`, `status_hint.*`.
- `databackd-teams-specialists` (bg+eng): `add, add_title, employee, employee_placeholder, hourly_rate_help, added, empty_text`.
- Ключовете bg/eng съвпадат (проверено програмно).

## Миграции
Няма.

## Нужни промени извън моята област
- **Сайдбар (чужда област, в процес):** на снимките подменюто на „Финансов анализ“ е счупено (групите „Данни/…/Екипи“ като сиви ленти, подпунктите в един ред, поле „Търсене в менюто“) — вероятно незавършена промяна по `sections/menu|sidebar` + некомпилиран CSS на друг агент. Не е от A12.
- `resources/views/components/dbk/kpi.blade.php` (S9): етикетът на KPI се пренася по средата на думата при 2 карти на ред на телефон („Продължителнос/т“) — `overflow-wrap: anywhere` → `normal` + `hyphens: auto`, или по-малка иконка на ≤575 px.
- `routes/databackd/teams-specialists.php` (по желание, вместо `id = 0`): `Route::get('create', [SpecialistController::class, 'edit'])->defaults('id', 0)->name('create'); Route::post('/', …)` — не е задължително, текущото решение работи.
- `app/Helper/Files.php` / `AnalysisRequest::storeUpload()` хвърлят английски текстове за грешка при качване („You are not allowed to upload files with extension…“) — показват се в toastr; да минат през `__()` (S7/S6).

## Отворени въпроси / неправено
- Офертата се пази в `monthly_price_estimate` (без миграция); оригиналната автоматична оценка остава само в историята, ако админът е сменил цената. Ако е нужна отделна колона `offer_monthly_price` — миграция в следваща сесия.
- Клиентът приема офертата директно в статус „Активна“ (стартът на екипа е по датата на заявката); ако бизнесът иска междинен статус „Приета“, трябва нов статус.
- Kanban drag & drop на телефон не работи (jquery-ui без touch); на мобилен статусът се сменя от падащото меню в детайла — достатъчно.
- Файловете към коментари се свалят като `attachment-<id>.<ext>` (оригиналното име не се пази — изисква колона).
- Не е проверено в жив браузър с кликане (само рендиране/снимки и POST през kernel): модалът „Добави специалист“, SweetAlert-ът с цена на офертата, бутоните на клиента.
- Демо: на production демо заявката #32 (клиент z.radev) е „Изпратено предложение“ — клиентът вече ще вижда бутоните за приемане/отказ.

## Проверка
- `php -l` на всички променени PHP файлове — OK.
- `smoke.php databackd.teams. --ajax` като админ — всички 200; като служител — 403 само за редакция/добавяне на специалист (очаквано); като клиент — 403 за „Специалисти“ и редакция, 404 за чужди заявки/файлове (очаквано, както в handoff 05/06). Допълнителни URL: `/requests/{29,30,32}`, `/analysis/{134,141}`, `/analysis/create?team_request_id=29`, `/specialists/{41,0}/edit`, изтегляния на файлове.
- POST тестове (39 PASS): сбор на ролите ≠ размер → 422; начало в миналото → 422; клиентът не може да подмени `client_id`; математика на цената; клиент активира без оферта → отказ; чужда заявка → 404; служител смяна на статус → 403; new→completed и active→new → отказ; оферта с цена → клиентът е известен; клиентът приема → активна, админите известени; оттегляне; админ за клиент с „друг брой“ 7; служител като клиент → 422; анализ: срок в миналото, чужд екип → 422; създаване с файл → коментар с файл на частния диск, сваляне 200 / чужд клиент 404; коментар с `<script>` и заглавие с `<img onerror>` се escape-ват; клиент move → 403; оценка преди предаване → отказ; служител → „Предадена“ (`status: success`); `javascript:` линк → 422; оценка 5 / 6 → 422; добавяне на специалист (служител 403, дубликат 422, админ OK, умения без дубликати). Всички тестови записи (заявки, логове, коментари, файлове, профил, известия) са изтрити.
- `php artisan databackd:recurring-analysis --dry-run` — 0 за създаване (без промени по командата).

## Пълен списък с променени/нови файлове
- `app/Http/Controllers/Databackd/Teams/TeamRequestController.php`
- `app/Http/Controllers/Databackd/Teams/AnalysisRequestController.php`
- `app/Http/Controllers/Databackd/Teams/SpecialistController.php`
- `app/Models/Databackd/TeamRequest.php`
- `app/Http/Requests/Databackd/Teams/TeamRequest/ChangeStatusRequest.php`
- `app/Http/Requests/Databackd/Teams/TeamRequest/SaveTeamRequestRequest.php`
- `app/Http/Requests/Databackd/Teams/AnalysisRequest/StoreAnalysisRequest.php`
- `app/Notifications/Databackd/TeamRequestCreated.php`
- `app/Notifications/Databackd/TeamRequestStatusChanged.php`
- `resources/views/databackd/teams/requests/{create,index,show}.blade.php`
- `resources/views/databackd/teams/analysis/{form,index,show}.blade.php`
- `resources/views/databackd/teams/specialists/{form,index}.blade.php`
- `resources/scss/databackd/_teams.scss`
- `resources/lang/{bg,eng}/databackd-teams-{requests,analysis,specialists}.php`
- нов: `docs/mvp/audit-s12/12-teams.md`
