# A01 — езикови файлове (upstream `eng` + `bg`)

Обхват: `resources/lang/eng/{app,clan,email,messages,modules,permissions,superadmin}.php` и съответните `resources/lang/bg/*.php`. Файловете `databackd-*`, `ui-*` и останалите не са пипани.

## Какво е проверено

- Новите upstream ключове: база `37235f8` срещу `vendor/worksuite` — 219 нови ключа, 0 изтрити, 0 с променена стойност (същото като `langdiff.php`).
- Сливането на координатора в `eng/{app,email,modules}`: спрямо `HEAD` (`9f05fe3`) и `main` — всички наши (S7) стойности са запазени (0 разлики за ключовете от `main`), 0 изгубени ключа.
- Мястото на всеки нов ключ в `eng` (същото влагане) и съответното място в `bg`; стилът на `email.php` (`[ ]`) е запазен, в `bg` новите масиви са в стил `[ ]` както останалата част от файла.
- Употребата на всеки нов ключ в изгледите/контролерите (заглавие, колона, бутон, етикет, съобщение, вграден фрагмент), за да се избере регистърът по правилата на S7.
- Ключовете, които новият upstream код ползва (`__()`, `@lang`, `trans`, `trans_choice`, `$t` в `git diff 37235f8 vendor/worksuite -- resources/views app routes config Modules`, 402 ключа), спрямо всички `eng` файлове.
- Имената на правата и известията от новите upstream миграции (`view_deal_email`, `send_deal_email`, `manage_deal_email_template`, `late-clock-in`, `late-clock-in-manager-admin`) — всички имат превод.
- Сегментите на новите URL адреси в breadcrumb-а (`app.<сегмент>` / `app.menu.<сегмент>`).
- Рендиране (smoke, админ на „Демо Финанс Груп“, bg): `lead-forms.index`, `lead-forms.create`, `/account/lead-forms/2`, `invoice-settings?tab=reminder`, `lead-settings?tab=emailTemplates`, `payment-gateway-settings?tab=worldpay`, `attendance-settings`, `products/grid` — 200; новите текстове се показват на български, без английски остатъци от тези ключове.

## Ключове по файлове

| Файл | Нови upstream | eng нормализирани | bg добавени | Осиротели (eng+bg) |
|---|---:|---:|---:|---:|
| `app.php` | 16 | 3 | 18 | 2 (`image`, `menu.leadForms`) |
| `clan.php` | 16 | 10 | 16 | — |
| `email.php` | 16 | 10 | 16 | — |
| `messages.php` | 9 | 3 | 9 | — |
| `modules.php` | 152 | 28 | 160 | 8 (`invoiceSettings.*`) |
| `permissions.php` | 8 | 1 | 8 | — |
| `superadmin.php` | 2 | 0 | 4 (нов частичен файл: 2 нови + 2 за лимитите) | 1 (`maxClientsLimitReached`) |
| **Общо** | **219** | **55** | **231** (219 нови + 10 осиротели + 2 за лимитите) | **11** |

`bg/superadmin.php` не съществуваше и в `main` (супер админ зоната е на английски; `lang-audit` пропуска `superadmin.php` по подразбиране). По искане на координатора (A02/A12) е създаден **частичен** `bg/superadmin.php` само с текстовете, които виждат потребителите на компанията, и новите 6.0.19 ключове: `maxEmployeesLimitReached`, `maxClientsLimitReached`, `planRenew.subject`, `planRenew.text`. Всички останали `superadmin.*` ключове продължават да излизат на английски през `fallback_locale = eng` (проверено в Laravel: `__('superadmin.verificationExpireIn')` с локал `bg` дава английския текст, `__('superadmin.maxClientsLimitReached')` — българския).

Старите ключове не са пипани нито в `eng`, нито в `bg` (проверено програмно: 0 променени стойности, 0 изгубени ключа).

## Английски — нормализация на новите ключове

Ключове и placeholder-и не са сменяни. Промени (55):

- **Имейли/известия за закъсняло пристигане** (`email` + `clan`, `lateClockIn` и `lateClockInManagerAdmin`): `Late Clock-In Notice` → `Late clock-in notice`, `Late Clock-In Alert` → `Late clock-in alert`, `Clock-In Time` → `Clock-in time`, `Shift Start Time` → `Shift start time`, `Minutes Late` → `Minutes late`, `Go To Dashboard` → `Go to dashboard`, `View Attendance` → `View attendance` (като останалите `subject`/`action` в `email.php`).
- **Етикети на полета** (sentence case): `Worldpay Status/Merchant Entity/API Username/API Password/Checkout ID/Webhook Secret` → `Worldpay status`, `Worldpay merchant entity`, `Worldpay API username`, `Worldpay API password`, `Worldpay checkout ID`, `Worldpay webhook secret`; `Allowed Working From` → `Allowed working from`; `Carry Forward Expires On` → `Carry forward expires on`; `Custom Interval` → `Custom interval`; `Form Slug` → `Form slug`; `Select Template` → `Select template`; `Merge Fields` → `Merge fields`; `Total Remaining` → `Total remaining`; `Include Payment Link` → `Include payment link`; `System (Automatic)` → `System (automatic)`.
- **Бутони/действия**: `Add Video / Document` → `Add video / document`, `Add Another Reminder Before Due Date` → `Add another reminder before due date`, `Save Changes` → `Save changes`, `Add Lead Form` → `Add lead form`, `Configure Fields` → `Configure fields`, `Copy Form Link` → `Copy form link`, `Send Email` → `Send email`, `View Email` → `View email`.
- **Известия в настройките** (`emailNotification`): `Late Clock In` → `Late clock-in`, `Late Clock In (Manager & Admin)` → `Late clock-in (manager & admin)`.
- **Заглавия на раздели** (Title Case): `Product description` → `Product Description`, `Videos & documents` → `Videos & Documents`, `Partner products` → `Partner Products`, `Share Direct link` → `Share Direct Link`.
- **Съобщения**: точка в края на `workFromTypeNotAllowed`, `autoClockInLocationNotAllowed`, `allowedWorkFromTooltip`, `deal.emailSentSuccess`; `webhook url in` → `webhook URL to` (`addWorldpayWebhookUrl`).
- **Права**: `Manage Google Meet category` → `Manage Google Meet Category` — `permissions.php` е изцяло в Title Case (S7 не го е променял), новите права следват файла.

Без промяна остават заглавията/табовете/колоните, които вече са в Title Case (`Reminder Settings`, `Global Reminder Settings`, `When to Send Reminders`, `Reminder History`, `Lead Forms`, `Email Templates`, `Template Used`, `Reminders Sent` — в същата таблица като `Due Date`/`Invoice Date`), вградените фрагменти с малка буква (`before due date`, `starting after`, `times`, `day(s)`) и съобщенията, които вече са правилни.

## Български — примери за превод

| Ключ | EN | BG |
|---|---|---|
| `app.menu.reminderSettings` | Reminder Settings | Настройки за напомняния |
| `app.menu.gridView` | Grid View | Мрежов изглед |
| `app.cc` / `app.body` | Cc / Body | Копие до / Съдържание |
| `app.addResource` | Add video / document | Добави видео / документ |
| `email.lateClockIn.subject` | Late clock-in notice | Известие за закъсняло пристигане |
| `email.lateClockIn.text` | You have been marked late for today's attendance. | Днешното Ви присъствие е отбелязано като закъсняло. |
| `email.lateClockInManagerAdmin.text` | :name has clocked in late. | :name отбеляза пристигане със закъснение. |
| `messages.workFromTypeNotAllowed` | This working from option is not allowed for clock-in or clock-out. | Този вариант за „Работа от“ не е разрешен при отбелязване на пристигане или излизане. |
| `messages.locationRequired` | Unable to determine your current location. … | Текущото Ви местоположение не може да бъде определено. Разрешете достъп до местоположението в браузъра и опитайте отново. |
| `modules.invoices.payWorldpay` | Pay via Worldpay | Плати чрез Worldpay |
| `modules.worldpay.challengeHelp` | Your bank needs you to confirm this payment. | Банката Ви изисква да потвърдите това плащане. |
| `modules.invoiceSettings.sendReminderAfterDueDate` | Send reminder after due date | Изпращай напомняне след срока за плащане |
| `modules.invoiceSettings.every` + `startingAfter` | Every … , starting after … | На всеки …, започвайки след … |
| `modules.invoiceSettings.untilInvoicePaid` | Until invoice is paid | До плащане на фактурата |
| `modules.leaves.carryForwardBalance` | Carried forward balance | Пренесен остатък |
| `modules.leaves.carryForwardLapsedDetail` | :lapsed lapsed of :total carried forward (:used used before expiry) | Изтекли :lapsed от :total пренесени (:used използвани преди изтичането) |
| `modules.leaves.monthlyAccrualHint` | Monthly · :count/mo | Месечно · :count/мес. |
| `modules.lead.leadForms` | Lead Forms | Формуляри за потенциални клиенти |
| `modules.lead.formSlug` | Form slug | Кратък адрес |
| `modules.deal.sendEmail` / `emailTemplates` | Send email / Email Templates | Изпрати имейл / Шаблони за имейли |
| `modules.deal.mergeFields` | Merge fields | Полета за заместване |
| `modules.recurringInvoice.days` | day(s) | ден(дни) |
| `permissions.view_deal_email` | View Deal Email | Преглед на имейли към сделки |
| `permissions.add_google_meetings` | Add Meetings | Добавяне на срещи в Google Meet |

Решения по речника: Clock in → „пристигане“ (както `modules.attendance.clock_in`); Due date при фактурите → „срок за плащане“; Working from → „Работа от“ в кавички „“; Lead form → „формуляр за потенциални клиенти“ (както съществуващия `modules.lead.leadForm`); link → „връзка“; Leave allocation → „полагаем отпуск“, carry forward → „пренасяне/пренесен“, lapse/expire → „изтичане/изтекли“; обръщение „Ви/Вашата“ с главна буква. Worldpay, PayFast, Google Meet, Anagami Finance View, Froiden и `Event-Signature` не се превеждат. Новите стойности не съдържат ASCII кавички/апострофи (част от тях влизат в JS низове в двойни кавички).

## Осиротели ключове (ползвани от upstream, недефинирани никъде)

Добавени в `eng` (маркирани с `// databackd (upgrade 6.0.19): …`) и в `bg`:

| Ключ | Ползва се в | EN | BG |
|---|---|---|---|
| `app.image` | `*/pdf/invoice-6.blade.php` (колона) | Image | Изображение |
| `modules.invoiceSettings.subject` | `*/pdf/invoice-6.blade.php` | Subject | Относно |
| `modules.invoiceSettings.quotationGreeting` | `*/pdf/invoice-6.blade.php` | Dear Sir or Madam, | Уважаеми господине/госпожо, |
| `modules.invoiceSettings.quotationIntro` | `*/pdf/invoice-6.blade.php` | Please find the details of the items below. | По-долу са посочени подробностите за артикулите. |
| `modules.invoiceSettings.templateCustomParts` | `invoice-settings/ajax/template` (upstream) | Template 6 Header and Footer | Горна и долна част на шаблон 6 |
| `modules.invoiceSettings.templateHeader` / `templateFooter` | същото | Header image / Footer image | Изображение в горната / долната част |
| `modules.invoiceSettings.templateHeaderInfo` / `templateFooterInfo` | същото | (текстът на A02 от `ui-invoices.template.*`) | (същият превод, `WEBP` вместо `WebP`) |
| `app.menu.leadForms` | breadcrumb на `/account/lead-forms/{id}` (беше „Lead Forms“ на bg) | Lead Forms | Формуляри за потенциални клиенти |
| `superadmin.maxClientsLimitReached` | `ClientController` (CUS_000329, наш ключ, липсващ и в `main`) | виж „Искания на координатора“ | виж там |

PDF-ите на шаблон 6 се ползват от фактури, оферти, кредитни известия, поръчки, предложения и шаблони за предложения (6 изгледа). След добавянето повторната проверка дава 0 липсващи ключа от 402.

## Искания на координатора (от A02 и A12)

1. **`superadmin.maxClientsLimitReached`** (CUS_000329, `ClientController::store`/`update` в `main`) — не съществуваше никъде (показваше се суровият ключ). Добавен в `eng/superadmin.php` след `maxEmployeesLimitReached`, маркиран `// databackd (CUS_000329)`: „You have reached the maximum number of clients allowed in your plan. Please upgrade your plan to add more clients.“; в `bg/superadmin.php`: „Достигнахте максималния брой клиенти, разрешен от Вашия план. Надградете плана, за да добавяте още клиенти.“ В текущия worktree `ClientController` (A07) вече ползва `ui-crm.clients.maxClientsReached` — и двата ключа работят; връщането към `superadmin.maxClientsLimitReached` би намалило diff-а спрямо `main`, но не е задължително.
2. **`superadmin.maxEmployeesLimitReached`** — съществува в `eng/superadmin.php` (upstream, ред 481); bg липсваше, защото нямаше `bg/superadmin.php`. Добавен: „Достигнахте максималния брой служители, разрешен от Вашия план. Надградете плана, за да добавяте още служители.“ (ползва се от `EmployeeController` и `UserObserver`).
3. **A12** — потвърдено, част от 219-те ключа: `permissions.{view_deal_email,send_deal_email,manage_deal_email_template}` имат bg („Преглед на имейли към сделки“, „Изпращане на имейли към сделки“, „Управление на шаблони за имейли към сделки“); `modules.emailNotification.late-clock-in` / `late-clock-in-manager-admin` имат bg („Закъсняло пристигане“, „Закъсняло пристигане (ръководител и администратор)“) и английският е в sentence case („Late clock-in“, „Late clock-in (manager & admin)“). Английските имена на правата остават в Title Case („View Deal Email“ …) — целият `permissions.php` (500+ ключа, показват се и като `h6` заглавия в матрицата с права) е в Title Case и S7 не го е променял; A12 също не иска промяна там. Ако се реши sentence case за правата, това трябва да е за целия файл наведнъж.

## Резултат от проверките

- `php -l` — 14 файла (`eng` 7, `bg` 7): без грешки.
- `php docs/mvp/tools/lang-audit.php`: `Files: 66, eng keys: 10703, bg keys: 10703`, `Severity: {"info":524,"warn":715}` — **0 критични** (преди: 217 `missing_in_bg` в моите файлове). Броят файлове расте заради новите `ui-*` на другите агенти.
- `php docs/mvp/tools/lang-audit.php --strict` — изход 0.
- `--type=missing_in_bg,missing_in_eng,placeholder_mismatch,banned_term` — 0.
- `--with-superadmin --file=superadmin` — 561 `missing_in_bg` (известно изключение: `bg/superadmin.php` е частичен, останалото е на английски чрез fallback; преди беше 1 `missing_file_bg`). Без placeholder/banned/latin проблеми в 4-те bg ключа.
- Нови предупреждения в моите файлове (само warn/info): 21 `bg_latin` и 16 `bg_title_case` — собствени имена (Worldpay, Google Meet, Froiden, envato.froid.works, Checkout ID, Event-Signature, Anagami Finance View); 10 `bg_lowercase_start` — вградени фрагменти, които продължават изречение (`преди срока за плащане`, `започвайки след`, `пъти`, `напомняния` след число, `ден(дни)` …); 3 `eng_case_label` (info) — заглавия в Title Case (`Template 6 Header and Footer`, `Share Direct Link`, `Manage Google Meet Category`).
- eng/bg: еднакъв набор ключове в 6-те пълни upstream файла (0/0 разлики); `superadmin.php` — частичен bg (4 ключа), 0 ключа в bg, които ги няма в eng.

## Искания към други агенти

1. **A02 (invoices)** — `resources/views/invoice-settings/ajax/template.blade.php`: upstream ключовете `modules.invoiceSettings.template{CustomParts,Header,HeaderInfo,Footer,FooterInfo}` вече съществуват със същите текстове като `ui-invoices.template.*`. Не е задължително, но за по-малък diff при следващ merge изгледът може да се върне към upstream вида и `ui-invoices.template.*` да се махне. Ако остане както е — няма проблем.
2. **A03 (pdf)** — шаблон 6 вече има всички ключове (`app.image`, `modules.invoiceSettings.{subject,quotationGreeting,quotationIntro}`); промяна в изгледите не е нужна. За преценка: upstream показва поздрав „Уважаеми господине/госпожо,“ и въвеждащ ред и при фактури/кредитни известия — ако не е желано за bg, това е в изгледите.
3. **Координатор / A13 (инструменти)** — по желание, за по-малко шум в `lang-audit` (`docs/mvp/tools/lang-audit.php`, константата `LATIN_OK`):
   ```diff
   -    'play', 'store', 'testflight', 'onesignal', 'beams', 'cvv', 'ses', 'env', 'envato', 'codecanyon', 'laravel', 'whatsapp',
   +    'play', 'store', 'testflight', 'onesignal', 'beams', 'cvv', 'ses', 'env', 'envato', 'codecanyon', 'laravel', 'whatsapp',
   +    'worldpay', 'meet', 'webp', 'froiden', 'checkout',
   ```
4. **A06 / A10** — `update-settings/partials/partner-products-promo.blade.php` (включен от `update-settings/index.blade.php`) рекламира платени добавки на Froiden (`envato.froid.works`). Текстовете са преведени, но за AFV вероятно трябва да се скрие.

## За деплоя

- Само езикови файлове; без миграции и настройки. Да се качат 14 файла: `resources/lang/eng/{app,clan,email,messages,modules,permissions,superadmin}.php` и `resources/lang/bg/{app,clan,email,messages,modules,permissions,superadmin}.php` (`bg/superadmin.php` е **нов** файл — да не се пропусне при rsync/commit).
- Помощните скриптове (`$S/a01/apply.php`, `data.php`, `verify.php`, `orphans.php`) са в scratchpad-а, не в репото.
