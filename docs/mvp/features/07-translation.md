# S7 — Превод на приложението (BG / EN)

Български (`resources/lang/bg/`) и английски (`resources/lang/eng/`) са в git. Езикът на потребителя е `users.locale` (`bg` / `eng`), резервен език — `eng` (липсващ bg ключ се показва на английски, затова всеки ключ трябва да има и двете страни).

## Как е уредено
- **Lang файлове:** `resources/lang/{eng,bg}/*.php` (60 файла, 9 938 ключа). Приложението ползва `users.locale`; резервният език е `eng`, затова липсващ bg ключ се вижда на английски — всеки ключ има и двете страни (проверка: `lang-audit.php --strict`).
- **Новите ключове за твърд текст** са в `databackd-misc.php`, `ui-core.php`, `ui-shared.php`, `ui-misc.php`; съществуващите lang файлове на AFV (`app`, `modules`, `messages`, `email`, `validation`, `permissions`, `clan`, `cookie`, `placeholders`) са коригирани по място.
- **Заглавия на страници:** `layouts/app.blade.php` и `components/app-title.blade.php` превеждат `$pageTitle` с `__()`; контролерите на AFV го задават в middleware след `parent::__construct()`. Breadcrumb-ът превежда URL сегментите през `app.<сегмент>` / `app.menu.<сегмент>` (ключовете за сегментите на AFV са в `app.php`).
- **Формати:** дата/валута са настройки на компанията; `public/i18n/bg.json` е DataTables преводът; `moment.locale()` и `daterangeLocale` са в `layouts/app.blade.php`; графиките ползват `Intl` (`bg-BG`/`en-GB`).
- **Инструменти:** `docs/mvp/tools/lang-audit.php` (одит на lang), `docs/mvp/tools/hardcoded-text.php` (твърд текст), `docs/mvp/tools/smoke.php --dump=<папка>` (HTML + видим текст на страниците).

## Правила за текста

**Български**
- Главна буква само в началото на менюта, заглавия, бутони, етикети, табове и колони: „Финансов анализ“, „Финансови записи“, „Добави запис“, „Бюджет спрямо реално“. Не „Финансови Записи“, не „клиенти“.
- Собствени имена и съкращения — както са: ОПР, ДДС, ЕИК, НСС, API, Excel, CSV, PDF, Google Sheets, IBAN.
- Без буквален превод; счетоводна терминология по НСС (виж речника).
- Менюта и списъци — множествено число („Клиенти“, „Фактури“); действия — единствено число, глагол в повелително наклонение за бутони („Добави запис“, „Запази“, „Изтрий“), съществително за заглавия на форми („Нова фактура“, „Редакция на запис“).
- Обръщение „Вие“ в съобщенията, кратко и учтиво; без удивителни.
- Формати: дати `дд.мм.гггг`, числа `1 234,56 €`, месеци и дни на български в графики, DataTables и date picker.

**Английски**
- Title Case за менюта и заглавия („Budget vs Actual“, „Financial Records“), sentence case за бутони, етикети и съобщения („Add record“, „The record was saved.“).
- Всеки ключ в `bg` съществува в `eng` и обратно (`php docs/mvp/tools/lang-audit.php`).

## Речник BG / EN

### Меню и навигация
| EN | BG |
|---|---|
| Dashboard | Табло |
| Private dashboard | Лично табло |
| Advanced dashboard | Разширено табло |
| Financial analysis | Финансов анализ |
| Clients | Клиенти |
| HR | HR |
| Work | Работа |
| Finance | Финанси |
| Products | Продукти |
| Reports (standard reports menu) | Справки |
| Settings | Настройки |
| Help | Помощ |
| Employees | Служители |
| Leaves | Отпуски |
| Shift roster | График на смените |
| Attendance | Присъствие |
| Holidays | Празници |
| Designations | Длъжности |
| Departments | Отдели |
| Appreciations | Поощрения |
| Contracts | Договори |
| Projects | Проекти |
| Tasks | Задачи |
| Time logs | Отработено време |
| Proposals | Предложения |
| Estimates | Оферти |
| Invoices | Фактури |
| Payments | Плащания |
| Credit notes | Кредитни известия |
| Expenses | Разходи |
| Bank accounts | Банкови сметки |
| Orders | Поръчки |
| Tickets | Заявки за поддръжка |
| Events | Събития |
| Messages | Съобщения |
| Notice board | Табло за обяви |
| Knowledge base | База знания |
| Notes | Бележки |
| Leads | Потенциални клиенти |

### Счетоводни и финансови термини
| EN | BG |
|---|---|
| Revenue / Income | Приходи |
| Expenses / Costs | Разходи |
| Receivables | Вземания |
| Payables | Задължения |
| Counterparty | Контрагент |
| Profit and loss (P&L) | ОПР (отчет за приходите и разходите) |
| Balance (account balance) | Салдо |
| Turnover | Оборот |
| Cash flow | Парични потоци |
| Budget | Бюджет |
| Budget vs actual | Бюджет спрямо реално |
| Variance | Отклонение |
| Forecast | Прогноза |
| Scenario | Сценарий |
| VAT | ДДС |
| Company ID (EIK) | ЕИК |
| Chart of accounts | Сметкоплан |
| Account | Сметка |
| Financial record / entry | Финансов запис |
| Add record | Добави запис |
| Ratios | Показатели |
| Consolidation | Консолидация |
| Legal entity | Юридическо лице |
| Dimension | Разрез |
| Cost center | Разходен център |
| Gross margin | Брутен марж |
| Statutory report | Законов отчет (НСС) |
| Bank statement | Банково извлечение |
| Exchange rate | Валутен курс |
| Alert / threshold | Известие / праг |
| Package | Пакет |
| Saved reports | Запазени справки |
| Import / Export | Импорт / Експорт |
| Data source | Източник на данни |
| Reporting period | Период на отчитане |

### Общи действия
| EN | BG |
|---|---|
| Add / Create | Добави / Създай |
| Save | Запази |
| Cancel | Отказ |
| Edit | Редактирай |
| Delete | Изтрий |
| Search | Търси |
| Filter | Филтър |
| Apply | Приложи |
| Reset | Изчисти |
| Download | Изтегли |
| Back | Назад |
| Close | Затвори |
| Actions | Действия |
| Status | Статус |
| Description | Описание |
| Name | Име |
| Date | Дата |
| Amount | Сума |
| Total | Общо |
| Yes / No | Да / Не |

## Допълнения към речника (S7)

Решения, взети по време на превода. Когато английският термин се ползва в различно значение, значението е в скоби.

### Финансов анализ
| EN | BG |
|---|---|
| Ratios | Показатели (не „коефициенти“; изключение: формат на стойност „Коефициент“) |
| Ratio builder | Конструктор на показатели |
| Flexible reports (report builder) | Гъвкави справки |
| Cash flow | Парични потоци |
| Bulk entry | Групово въвеждане |
| Parent entity | Дружество майка |
| Ownership % | Дял на участие |
| Consolidation group | Група за консолидация |
| Mapping (columns) | Съпоставяне |
| Revoke (token) | Анулирай |
| No breakdown | Без разрез |
| Severity | Важност |
| Statutory reports | Годишни отчети (НСС) |
| Data analyst | Анализатор на данни |
| Micro companies | Микропредприятия |
| Delivered (analysis) | Предадена |
| Gross / net margin | Брутен / нетен марж |
| Profit margin | Марж на печалбата |
| Corporate tax | Данък върху печалбата |
| Subtotal (PDF) | Данъчна основа |
| Total due | Дължима сума |
| Billed to / Billed from (PDF) | Получател / Доставчик |
| Authorised signatory | Упълномощено лице |
| Applied credits | Приложени кредитни известия |
| Company ID / VAT No. | ЕИК / ДДС № |
| Due date | Срок за плащане |
| Item | Артикул |
| Withdraw / Deposit (statement) | Теглене / Внасяне |

### HR, работа и продажби
| EN | BG |
|---|---|
| Clock in / out | Пристигане / Излизане |
| Leave (absence) | Отпуск |
| Timesheet | Отчет за времето |
| Time log | Отработено време |
| Payroll | Ведомост / Работни заплати |
| Notice period | Срок на предизвестие |
| Probation | Изпитателен срок |
| Increment / Promotion / Demotion | Увеличение / Повишение / Понижение |
| Reporting to | Пряк ръководител |
| Emergency contact | Контакт при спешност |
| Unit type | Мерна единица |
| Deal | Сделка |
| Pipeline | Фуния на продажбите |
| Follow up | Последващо действие |
| Milestone | Етап |
| Notice | Обява |
| Sub task | Подзадача |
| Gateway | Платежен шлюз |
| Offline payment | Плащане извън системата |
| Billing | Фактуриране |
| Permissions | Права |
| Removal request (GDPR) | Заявка за изтриване на данни |
| GDPR | ОРЗД |
| Recruiter / Inventory / Burndown | Рекрутер / Наличности / Диаграма на изгарянето |
| Sort code | Банков код |
| Purchase code | Код за покупка |

### Правила, които се повтарят
- `app.to` е „до“ (малка буква): ползва се по средата на израз („EUR до BGN“, „01.01.2026 до 31.01.2026“); за заглавие на колона/етикет — `Str::ucfirst(__('app.to'))`.
- `app.hrs` = „ч“, `app.mins` = „мин“ (кодът ги долепва към числото).
- `app.menu.teams` = „Отдел“ (HR); разделът „Екипи“ на Финансов анализ е `app.teams`. Известията за повишение слагат `mb_strtolower(__('app.menu.teams'))` след името на отдела.
- Единствено число за етикети във формуляри (`app.menu.proposal`, `app.menu.bankaccount`), множествено за менюта (`app.proposals`, `app.bankaccounts`).
- Атрибутите във `validation.php` са с главна буква („Полето „Сума“ е задължително.“).
- Заглавие на страница, зададено в контролер: ключ-низ (`'app.menu.contracts'`) или `__()` в `$this->middleware(...)` след `parent::__construct()`; в самия конструктор `__()` излиза на английски, защото локалът на потребителя още не е приложен.

## Нови lang файлове
| Файл | Съдържание |
|---|---|
| `databackd-misc.php` | видим текст в `databackd` views/услуги (aria-label, грешки при импорт) |
| `ui-core.php` | твърд текст във views/контролери на ядрото (таблото, проекти, задачи, банкови сметки) |
| `ui-shared.php` | layout/sections/components, PDF етикети (`pdf.*`), известия за изтичане на документи |
| `ui-misc.php` | останалите views (календари, настройки, gantt, custom-modules, update) |

## Проверка
```bash
php docs/mvp/tools/lang-audit.php --strict                          # липсващи ключове, плейсхолдъри, забранени термини, малка буква в менюта
php docs/mvp/tools/lang-audit.php --file=<част от името> --list     # подробности
php docs/mvp/tools/hardcoded-text.php --list                        # видим текст извън __()
php docs/mvp/tools/smoke.php dashboard --dump=<папка>               # видимия текст на страницата (<име>.txt)
```
