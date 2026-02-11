# A03 — PDF шаблони (обновяване 6.0.10 → 6.0.19)

Агент A03, 2026-10-28. Списък: `assign/A03-pdf.txt` (13 BOTH, 13 UP). Нови езикови файлове **не** са създадени (`ui-pdf.php` не трябваше — виж „Преводи“).

## Резултат накратко

- **CUS_000328 е запазен:** guard-ът `@if ($invoiceSetting->locale != 'bg')` е в **40 файла** (34 от `main` — всичките, без нито един загубен — плюс 6-те нови `*/pdf/invoice-6`). Проверка: `grep -rl "locale != 'bg'" resources/views` срещу `git grep -l "locale != 'bg'" main -- resources/views` → 0 липсващи.
- **S7 е запазен:** всеки ред, добавен от `3fb1674` в 36-те PDF файла (вкл. `contract-pdf`, `statement`), присъства в работното копие (програмна проверка ред по ред: 0 липсващи).
- **13-те BOTH файла са слети коректно:** наборът променени редове `37235f8→vendor/worksuite` = `main→HEAD` (upstream е приложен), и `37235f8→main` = `vendor/worksuite→HEAD` (нашето е запазено) — за всички 13. Работното копие на тези файлове не е пипано.
- **Шаблон 6 (6 изгледа):** добавен guard-ът на CUS_000328 (двуезичен PDF), ключовете на S7 като в invoice-5, ескейпнати текстовите полета; поправен шрифтът на `proposal-template/pdf/invoice-6` (кирилицата излизаше като „?“).
- **ar-php:** пакетът `khaled.alshamaa/ar-php` **го няма** нито в `vendor/` на работното копие, нито в оригиналния vendor на 6.0.19 (`~/Downloads/worksuite-saas-new-6.0.19/script/vendor`) — въпреки `composer.json`/`composer.lock`. Предпазителят в `pdfFixArabic()` (`class_exists`, вече добавен в `app/Helper/start.php`, не от мен) е задължителен. Кирилицата не се променя (проверено).
- **Реални PDF-и:** 54 файла в `$S/pdf-a03/` (фактури, кредитни известия, договор, плащане, банково извлечение през истинските маршрути; оферти/поръчки/предложения/шаблони — в паметта, защото в базата няма такива записи). Базата е непроменена (транзакция + rollback, 0 write заявки, `invoice_settings` проверени след това).

## Таблица по файлове

| Файл | Вид | Guard (CUS_000328) | S7 (`__()`/`@lang`) | Upstream промяна | Резултат |
|---|---|---|---|---|---|
| `bank-account/pdf/statement.blade.php` | BOTH | — (и в `main` няма) | ✅ 6 реда (`ui-shared.pdf.*`) | шрифт Verdana → `"DejaVu Sans"` | ✅ слято; **upstream поправя кирилицата**: в `main` извлечението излизаше с Helvetica и „?“ вместо кирилица (A/B рендер), сега DejaVu |
| `contracts/contract-pdf.blade.php` | BOTH | — | ✅ 5 реда | `$font` по подразбиране `DejaVu Sans` (+ar/fa/ur), `!important` | ✅ рендер en/bg, DejaVu, 0 „?“ |
| `credit-notes/pdf/invoice-5.blade.php` | BOTH | ✅ (ред 716) | ✅ 24 реда | `.word-break`: `break-all` → `normal` + `overflow-wrap` | ✅ CSS е в `<head>` → важи и за BG копието |
| `credit-notes/pdf/invoice-recurring.blade.php` | BOTH | ✅ | ✅ 28 реда | шрифт → DejaVu | ✅ компилира се (не се ползва от контролер — изисква `$invoice`) |
| `estimates/pdf/invoice-1.blade.php` | BOTH | ✅ | ✅ 11 | шрифт → DejaVu | ✅ рендер en/bg |
| `estimates/pdf/invoice-2.blade.php` | BOTH | ✅ | ✅ 9 | шрифт → DejaVu | ✅ рендер en/bg |
| `estimates/pdf/invoice-3.blade.php` | BOTH | ✅ | ✅ 13 | шрифт → DejaVu | ✅ рендер en/bg |
| `estimates/pdf/invoice-5.blade.php` | BOTH | ✅ | ✅ 12 | `.word-break` | ✅ рендер en/bg |
| `invoices/pdf/invoice-5.blade.php` | BOTH | ✅ (ред 807) | ✅ 30 | `.word-break` (+ премахнат дублиран блок `break-all`) | ✅ рендер en (4 стр.: EN + BG) / bg (2 стр.) |
| `invoices/pdf/invoice-recurring.blade.php` | BOTH | — (и в `main` няма) | ✅ 13 | шрифт → DejaVu | ✅ компилира се (не се ползва — изисква `$defaultAddress`) |
| `orders/pdf/invoice-5.blade.php` | BOTH | ✅ (само около „Друга информация“, както в `main`) | ✅ 4 | `.word-break`; поправен „s<td“ (грешка в заглавието на таблицата) | ✅ рендер en/bg |
| `proposal-template/pdf/invoice-5.blade.php` | BOTH | ✅ | ✅ 6 | шрифт, `.word-break` | ✅ рендер en/bg |
| `proposals/pdf/invoice-5.blade.php` | BOTH | ✅ | ✅ 8 | `.word-break` (+ премахнат дублиран блок) | ✅ рендер en/bg |
| `invoices/pdf/invoice-6.blade.php` | UP (нов) | ➕ добавен | ➕ 11 замени | нов шаблон | **променен**, рендер 609/610/611 en+bg |
| `credit-notes/pdf/invoice-6.blade.php` | UP (нов) | ➕ | ➕ 5 | нов | **променен**, рендер 16 en+bg |
| `estimates/pdf/invoice-6.blade.php` | UP (нов) | ➕ | ➕ 6 | нов | **променен**, рендер (в паметта) en+bg, + с банери |
| `orders/pdf/invoice-6.blade.php` | UP (нов) | ➕ (пълно BG копие — виж „Решения“) | ➕ 4 | нов | **променен**, рендер en+bg |
| `proposals/pdf/invoice-6.blade.php` | UP (нов) | ➕ | ➕ 3 | нов | **променен**, рендер en+bg |
| `proposal-template/pdf/invoice-6.blade.php` | UP (нов) | ➕ | ➕ 2 | нов | **променен** (+ шрифт), рендер en+bg |
| `invoices/pdf/partials/template-6-{banners,footer,styles}.blade.php` | UP (нови) | — (споделени, извън цикъла) | няма видим текст | нови | без промяна; банерите са с `{{ }}` (URL на качено изображение, не HTML) |
| `invoices/pdf/invoice_pdf_css.blade.php`, `estimates/pdf/estimate_pdf_css.blade.php` | UP | — | — | шрифт по подразбиране Verdana → `DejaVu Sans` (+ar/fa/ur), `!important` | без промяна — добро за кирилицата |
| `public/img/invoice-template/6.png` | UP | — | — | нова миниатюра (1,5 KB, с вграден английски текст „Template 6“; 1–5 са истински прегледи) | без промяна (козметично) |
| `tests/Unit/PdfFixArabicTest.php` | UP | — | — | нов тест | без промяна; виж „ar-php“ |

Непипнати от upstream и от мен, проверени само с компилация: `{invoices,estimates,orders,proposals,credit-notes}/pdf/invoice-{1..4}`, `proposals/pdf/invoice-recurring`, `super-admin/invoices/pdf/invoice-{1..5}`, `payments/ajax/pdf` (рендер на плащане 610 en/bg — ОК).

## Какво е променено (6 файла, всички маркирани `databackd (upgrade 6.0.19)`)

`resources/views/{invoices,credit-notes,estimates,orders,proposals,proposal-template}/pdf/invoice-6.blade.php`:

1. **CUS_000328 — двуезичен PDF.** Същото поведение като invoice-5: документът, после (когато езикът на PDF-а не е `bg`) нова страница с `App::setLocale('bg')` и същият документ на български. Вместо копиран body (както е в invoice-5) — един цикъл около тялото, за да може следващ upstream merge да приложи промените по шаблон 6 веднъж и за двата езика:
   ```blade
   {{-- databackd (upgrade 6.0.19): CUS_000328 dual-language PDF, as in invoice-5 ... --}}
   @foreach ($invoiceSetting->locale != 'bg' ? ['', 'bg'] : [''] as $dbkPdfCopy)
   @if ($dbkPdfCopy == 'bg')
   <div class="page_break"></div>
   @php App::setLocale('bg'); @endphp
   @endif
   @include('invoices.pdf.partials.template-6-banners')
   ... тялото на upstream без промяна на отстъпите ...
   @endforeach
   {{-- databackd (upgrade 6.0.19): end of CUS_000328 --}}
   @include('invoices.pdf.partials.template-6-footer')
   ```
   Горният банер се повтаря в началото на BG копието (като логото в invoice-5); долният е `position: fixed` и е извън цикъла (на всяка страница). Известният бъг (първият блок е на езика на текущия locale, не на `$invoiceSetting->locale`) **не е поправян** — поведението е идентично с invoice-5.
2. **S7 ключове — същите като в invoice-5 на същия вид документ:** `modules.invoices.amount` → `ui-shared.pdf.amount`, `modules.invoices.subTotal` → `ui-shared.pdf.subtotal` („Данъчна основа“), `app.gstIn` → `ui-shared.pdf.tax_id` („ЕИК / ДДС №“), `app.credit-note` → `ui-shared.pdf.credit_note`, `app.estimate` → `ui-shared.pdf.estimate`, `modules.invoices.totalPayment`/`netPayable` → `ui-shared.pdf.total_payment`/`net_payable`, `modules.invoiceSettings.authorisedSignatory` → `ui-shared.pdf.authorised_signatory`, `modules.invoices.price` (таблицата с плащанията) → `ui-shared.pdf.line_amount`, „Total Due“ (`modules.invoices.total` + `modules.invoices.due`) → `ui-shared.pdf.total_due` („Дължима сума“ — ключът на S7 от invoice-1..4; комбинацията от invoice-5 дава „Общо Дължимо“ с главна буква по средата).
   Получателят: upstream `@lang('app.to'),` → `@lang('ui-shared.pdf.billed_to'):` — `app.to` след S7 е разделител на интервал („до“ с малка буква), в PDF-а излизаше „до,“; сега „Получател:“ / „Billed to:“ (като в invoice-5).
   Ключовете, които upstream ползва, но не е дефинирал (`app.image`, `modules.invoiceSettings.{subject,quotationGreeting,quotationIntro}`), вече са добавени от A01 в upstream езиковите файлове → не съм ги сменял и `ui-pdf.php` не е нужен.
3. **Ескейпване (S6):** `{!! nl2br($x) !!}` → `{!! nl2br(e($x)) !!}` за текстовите полета: адрес на клиента/контакта, бележка (`note` е в `config/xss_ignore.php`, т.е. не се почиства при запис), `invoice_terms`, `estimate_terms`, `other_info`. Проверено: `<script>x</script>` в адреса и `<b>…</b>` в бележката се печатат като текст, новите редове остават. Описанията от редактора (`description`, `item_summary`) остават `pdfStripTags()` — както в invoice-5 и upstream (при запис 6.0.19 ги чисти с HTMLPurifier). `template_header`/`template_footer` са качени изображения (името на файла в БД, URL през `asset_url_local_s3`) и вече са с `{{ }}`.
4. **`proposal-template/pdf/invoice-6`:** добавен `@includeIf('invoices.pdf.invoice_pdf_css')` (както в другите 5 изгледа на шаблон 6). Без него dompdf ползва Times и кирилицата излиза като „?“ (рендер преди/след: `?????? ??` → „Шаблон за предложение“, `pdffonts`: Times-Roman → `DejaVuSans` subset). Забележка: `ProposalTemplateController::download` е с твърдо `proposal-template.pdf.invoice-5`, така че този изглед засега не се вика (upstream).

## Решения за преглед

- **Поръчки:** в `orders/pdf/invoice-5` (наследено от `5138520`) guard-ът обхваща **само** блока „Друга информация“ (персонализираните полета) — т.е. при `bg` те изобщо не се показват, а при друг език се показват на нова страница на български. В `orders/pdf/invoice-6` няма блок с персонализирани полета, затова „същият модел“ би дал празна страница; направено е пълно BG копие като при останалите документи (намерението на CUS_000328 — EN + BG). Ако трябва да е 1:1 с invoice-5 на поръчките — махни цикъла от този файл.
- Поздравът и въведението на шаблон 6 („Уважаеми господине/госпожо,“, „По-долу са посочени подробностите за артикулите.“) се показват при всички документи, вкл. фактури и кредитни известия — upstream дизайн, оставен (въпрос от A01, т. 2).

## Рендер (без сървър, без промяна на базата)

Скрипт: `$S/pdf-a03/render.php` (`$S` = scratchpad). Зарежда приложението като `smoke.php`, влиза като админ на „Демо Финанс Груп“ (компания 4), отказва, ако базата не е `*_local`; всичко е в транзакция с `rollBack()`, `InvoiceSetting::saving` хвърля изключение, отчита всяка write заявка (0); cache/session — `array`. Шаблонът и езикът се сменят **в паметта** (Eloquent `retrieved`). Фактури и кредитни известия минават през истинския маршрут (`/account/invoices/download/{id}` → `InvoiceController::download` → `domPdfObjectForDownload`, `/account/creditnotes/download/{id}`); договор, плащане и банково извлечение — също през маршрутите. Оферти, поръчки, предложения и шаблони за предложения няма в базата → незаписани модели, построени от фактура 610, `view(...)->render()` + `pdfLoadHtml()` като в контролерите.

| Документ | invoice-5 en | invoice-5 bg | invoice-6 en | invoice-6 bg |
|---|---|---|---|---|
| Фактура 610 (кредитно известие, 2 плащания) | 4 стр. (EN+BG) | 2 стр. | 4 стр. (EN+BG) | 2 стр. |
| Фактура 609 (неплатена) / 611 (частично) | — | — | 2 / 4 стр. | 1 / 2 стр. |
| Кредитно известие 16 | 2 стр. | 1 стр. | 2 стр. | 1 стр. |
| Оферта / поръчка / предложение (в паметта) | 2 / 2 / 2 | 1 / 1 / 1 | 2 / 2 / 2 | 1 / 1 / 1 |
| Шаблон за предложение (в паметта) | 2 | 1 | 2 | 1 |

- Шрифтове: `SUBAA*+DejaVuSans(-Bold)` (subsetting от S12 работи; в invoice-2/3/4 dompdf вгражда и Times-Roman за служебен елемент), 0 „?“ в `pdftotext` на всички PDF-и, 24–38 KB на PDF, пик на паметта 74–92 MB (CLI с целия kernel). Фактура 610 invoice-1..4, кредитно известие 16 invoice-1..4, оферта invoice-1/2/3/5, договор 1 en/bg, плащане 610 en/bg — ОК.
- Текстът на BG копието: „Получател:“, „Относно: Фактура (INV#010) / Кредитно известие CN#001“, „Данъчна основа“, „Дължима сума“, „Частично платена“, „В очакване“; на латиница остават само данни (условията на компанията „Thank you for your business.“, имейли, номера). Ключове без превод в PDF-ите: 0.
- PNG (`$S/pdf-a03/png/`) разгледани: invoice-5/6 en (стр. 1 и 3 — BG копието започва на нова страница с горния банер), кредитно известие, предложение, оферта; с тестов банер (`A03_BANNER`) горният банер се повтаря в началото на BG копието, долният е на всяка страница.
- **ar-php / `pdfFixArabic`:** `class_exists('ArPHP\I18N\Arabic')` = false; за всеки рендериран HTML `pdfFixArabic($html) === $html` (кирилица + латиница, вкл. `&#1060;`) — непроменен; арабски текст също се връща непроменен (без фатална грешка) благодарение на предпазителя в `start.php`.
- **Банково извлечение, сметка 7 (340 транзакции, 01.01.2025–31.12.2026):** с `memory_limit=128M` → `Allowed memory size … exhausted` в dompdf `Cellmap`; с 512M → 12 стр., пик 150 MB. **Не е регресия:** версията от `main` (A/B с `prependLocation`) е 158 MB и е с Helvetica (кирилица „?“). Сметка 8 (55 транзакции) и тримесечие на сметка 7 — ОК.
- Компилация на Blade (`compileString` + `php -l`, в scratchpad, без `view:cache`): всичките 51 PDF изгледа — ОК.
- `php docs/mvp/tools/hardcoded-text.php --list`: 0 находки в PDF файловете.

## Намерени проблеми (не са поправяни — извън обхвата или upstream)

1. **ar-php липсва във vendor** (и в оригиналния 6.0.19). Без предпазителя в `pdfFixArabic()` всеки PDF с арабски символ (напр. име на продукт) щеше да дава 500. Upstream тестът `tests/Unit/PdfFixArabicTest.php` ще пада на 2 от 4 теста, докато пакетът не е инсталиран (`vendor/bin/phpunit` и без това липсва). `00-plan.md` твърди, че новият vendor „има khaled.alshamaa/ar-php“ — неточно.
2. **`pdfLoadHtml()` → `defaultFont = DejaVu Sans` не действа** за HTML без `font-family`: началният `font-family` в dompdf е `serif` (известно семейство → Times), а `defaultFont` се ползва само за непознато семейство. Всички PDF изгледи задават шрифт (единственото изключение — `proposal-template/pdf/invoice-6` — е поправено), но твърдението в `02-invoices.md` („задава DejaVu Sans за всички PDF“) не е вярно за нови изгледи без шрифт.
3. **Шаблон 6, долен банер + документ на повече от една страница:** долният банер е `position: fixed; bottom: 0` при `@page { margin: 0 }`, а резервираното място (110px) е само в края на тялото → на междинните страници редове от таблицата минават под банера (видяно с висок тестов банер). Upstream дизайн; възможна поправка в `template-6-styles` е `@page` долно поле при наличен footer, но без реални банери не е пипано.
4. `bank-account/pdf/statement` показва `account_type` (`current`) и името на валутата (`Euros`) без превод — така е и в `main`.
5. Локално `resources/lang/en/` (неследена, в `.gitignore`, стара upstream версия от 03.2025) има приоритет пред `eng/` за езика `en` (`__('modules.invoiceSettings.invoiceTerms', [], 'en')` = „Terms and Conditions“, а `eng` = „Terms and conditions“). Ако тази папка съществува и на production, английските поправки на S7 не се виждат там. Не засяга `bg`.

## Искания към други файлове

- **A11 (`app/Helper/start.php`)** — да се **запази** предпазителят в `pdfFixArabic()` (вече е в работното копие, ред ~1329):
  ```php
  // databackd (upgrade 6.0.19): ar-php is not installed (not in the upstream vendor bundle) - skip the Arabic shaping instead of a fatal error
  if (!class_exists(\ArPHP\I18N\Arabic::class)) {
      return $html;
  }
  ```
  По желание (т. 2 по-горе): в `pdfLoadHtml()` вместо/освен `defaultFont` да се подава `<style>body{font-family:"DejaVu Sans",sans-serif}</style>` пред HTML-а — не е нужно за сегашните изгледи.
- **A04 (`app/Http/Controllers/BankAccountController.php`, `domPdfObjectForDownload`, по желание — съществуващ проблем, S12 памет):**
  ```diff
       public function domPdfObjectForDownload($request)
       {
  +        // databackd (upgrade 6.0.19): a long statement (340 rows) needs ~150 MB in dompdf (S12, same as the report PDFs)
  +        \App\Exports\Databackd\Reports\PivotPdf::raiseMemoryLimit();
  +
           $startDate = companyToDateString($request->startDate);
  ```
- **Координатор / деплой:** решение за ar-php — или `composer require khaled.alshamaa/ar-php` в локалния vendor преди rsync на целия `vendor/` (арабският ще се оформя правилно и тестът ще минава), или оставаме с предпазителя (арабският се печата неоформен, както преди 6.0.19).

## За деплоя

- Миграции/настройки: няма от моя страна. Файлове: 6-те `*/pdf/invoice-6.blade.php` (+ upstream partials, CSS, `public/img/invoice-template/6.png`). Изгледите се компилират наново от кешовете в стъпката на деплоя.
- Ако компания избере шаблон 6 с банери: качванията са в `public/user-uploads/invoice-template/` (вж. A02) — да не се трие при rsync.
- Проверка след деплоя: фактура PDF на демо компанията с шаблон 5 (както досега) и, ако се включи шаблон 6, едно сваляне на фактура и кредитно известие (2 страници при език ≠ bg, втората на български; кирилицата без „?“).
- Рискове: дълго банково извлечение и 128M `memory_limit` (съществуващо); арабски текст в PDF — без оформяне, но без грешка (при запазен предпазител).
