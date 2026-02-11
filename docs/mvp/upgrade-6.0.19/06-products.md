# A06 — продукти: ресурси, мрежов изглед, избор на продукт, нов изглед + „Себестойност“ (upgrade 6.0.19)

Worktree `ws-merge` (клон `upstream/worksuite-v6.0.19`), база `databackd_upgrade_local`. Списък: `assign/A06-products.txt` (15 файла: 5 BOTH, 10 UP).

## Какво е проверено (по файлове)

### BOTH (сравнени три версии: `37235f8` / `main` / `vendor/worksuite` → резултат)

За `ProductController`, `Product`, `products/ajax/create` и `edit` `git diff vendor/worksuite HEAD` е точно нашият `git diff 37235f8 main`, а `git diff main HEAD` е точно upstream промяната: и двете страни са налице, нищо не е загубено. `show.blade.php` е ръчното сливане на координатора — проверено отделно.

| Файл | Наше (запазено) | Upstream (налице) |
|---|---|---|
| `app/Http/Controllers/ProductController.php` | S8: `cost_price` в `store` (`filled ? : null`) и в `update` (само ако формата го праща — `has('cost_price')`) | `grid()`, `syncProductResources()`, `show()` с `files/unit/resources` и `priceWithTax`, `edit()` с `resources`, `allProductOption()` през `Product::optionsForSelect()` |
| `app/Models/Product.php` | S8: `@property $cost_price`. `cost_price` не е в `$fillable` и няма cast — така е и в `main`: присвоява се изрично, а `decimal(15,2)` идва като низ | `optionsForSelect()`, `resources()`, поправка в `getImageUrlAttribute()` |
| `resources/views/products/ajax/create.blade.php` | S8: поле „Себестойност“ (`databackd-gaps-margins.cost_price.*`) | `@include('products.ajax.resources')` |
| `resources/views/products/ajax/edit.blade.php` | S8: поле „Себестойност“ със стойността | `@include('products.ajax.resources', ['existingResources' => $product->resources])` |
| `resources/views/products/ajax/show.blade.php` | S8: редът „Себестойност“ е пренесен в новата разметка (`pd-specs`) със `@unless ($isClient)`. Клиентът не го вижда — проверено с рендиране като клиент | изцяло нов изглед: галерия, значки, цена с ДДС, бутон „Добави в количката“ за клиент, „Видеоклипове и документи“ |

`app/Http/Requests/Product/{Store,Update}ProductRequest.php` не са в списъка ми и upstream не ги пипа. Проверих ги в резултата: правилото `cost_price` (`nullable|numeric|min:0|max:9999999999999`) и атрибутът за грешката са налице.

S7: `main` не пипа файловете от списъка ми. Има S7 промени в продуктовите изгледи само в `products/category/create` и `products/sub-category/create`, а те не са в diff-а на upstream. `ProductsDataTable` няма наши промени. Новите upstream текстове минават през `__()`. Новите upstream ключове ги превежда A01 — вече са на български.

### UP (прочетени като код)

- `app/Http/Controllers/ProductResourceController.php` (нов): `destroy` — `ProductResource::findOrFail` е с `CompanyScope` (`HasCompany`), продуктът също. Правото е `edit_product` (all/added + `added_by`). Файлът се трие от диска. Нямаше проверка за модула → добавена (виж по-долу).
- `app/Models/ProductResource.php` (нов): `$guarded = ['id']`, полетата се задават изрично, няма mass assignment от заявката. `link_url` за URL ресурс връщаше суровия `url` → добавен е филтър само за http(s).
- `app/DataTables/ProductsDataTable.php`: нова колона SKU (`e()`), търсене по SKU, махната дублирана колона. Клиентът вижда само `allow_purchase = 1`, както и в мрежата.
- `products/ajax/grid.blade.php`: всичко е през `{{ }}`. `grid()` има същата проверка за права и същите филтри като DataTable-а, `select` е без `cost_price`.
- `products/ajax/resources.blade.php`: редове за видео/документ (URL или файл). `accept` е само в браузъра. Сървърът не валидираше нищо → виж проблем 2.
- `products/ajax/select-option{,-script,-styles}.blade.php`: новият избор на продукт (снимка, SKU, цена) във фактури, оферти, поръчки, предложения, сделки и разходи. Ползва `bootstrap-select` с `sanitize: false` → виж проблем 5. Себестойност няма.
- `products/index.blade.php`: превключване таблица/мрежа (`localStorage`), копиране на SKU. Текстовете са през `__()`. `hardcoded-text` отчита само ред 550 (`{{ __('app.addToCart') }}` в JS — фалшив сигнал).
- `update-settings/partials/partner-products-promo.blade.php`: реклама на добавки на Froiden на `/account/update-settings`. Страницата е само за супер админ. Текстовете са през `modules.update.*` (A01 ги е превел). Логото се зарежда от `envato.froid.works`. Оставено е както е — виж „Решения“.

## Намерени проблеми и какво е променено

Всички промени са маркирани с `databackd (upgrade 6.0.19)`.

1. **Ресурсите никога не се виждаха (upstream бъг).** `ProductResource` няма observer, който да задава `company_id` → записът остава с `company_id = NULL` и `CompanyScope` го скрива. Последиците: не се показва на продукта, не излиза в редакцията, не може да се изтрие (404), а каченият файл остава на диска. Проверено в транзакция с rollback: 6 записа, през релацията 0. **Поправка:** `ProductController::syncProductResources()` → `$resource->company_id = $product->company_id`. След поправката: 6 от 6.
2. **Липсваше сървърна валидация на ресурсите.** Минаваше `javascript:alert(1)`, а `<input type=url>` на браузъра го приема → stored XSS в `<a href>` на страницата на продукта, която виждат и клиентите. Файлът можеше да е от всякакъв тип: `Files::validateUploadedFile` спира само опасните разширения. Размерът и броят редове не бяха ограничени. **Поправка:** нов `ProductController::validateProductResources()` се вика в `store`/`update` преди записа (422 във формата):
   - `type` е video|document, `source` е url|file, `title` е до 255 знака;
   - `url` е само `http(s)`, до 2000 знака (`exclude_if` при source=file);
   - `file` е с разширенията от `accept` на формата (pdf, doc(x), xls(x), ppt(x), txt, zip, rar, mp4, webm, mov, avi), до `global_setting()->allowed_file_size` MB;
   - до 50 реда.

   Допълнителна защита: `ProductResource::getLinkUrlAttribute()` връща `null` за не-http(s) URL, а `show` не показва такива ресурси. Тест: http(s) минава; `javascript:`, `data:` и „без source + javascript:“ дават 422; `.exe` и `.html` дават 422; скрито невалидно URL поле при source=file минава.
3. **Себестойността изтичаше към клиентите като JSON (от S8 насам).** `/account/invoices/product-category/null` няма проверка за роля (стига модулът „Фактури“, а клиентите го имат). Endpoint-ът връща всички продукти с всички колони. Smoke като `p.yordanov@balkan.bg`: 200 и `"cost_price":"110.00"` … В JSON-а беше и `download_file_url` на платения файл за изтегляне. **Поправка:** `Product::$hidden = ['created_at', 'updated_at', 'pivot', 'cost_price', 'downloadable_file', 'download_file_url']`. Първите три са `$hidden` по подразбиране на `ApiModel`. Това покрива и всеки друг JSON с продукти (`OrderController::getclients`, `Estimate/Proposal/CreditNote…`). Достъпът до атрибута не се засяга: форма, `show`, справката за маржовете, поръчките. Няма JS, който да чете тези полета от JSON (проверено с grep). След поправката smoke-ът като клиент е 200 и `cost_price` го няма.
4. **Новият `show` показваше платения файл за изтегляне на клиента.** В 6.0.10 и в `main` той беше скрит от клиента; клиентът го получава от завършена поръчка (`orders/ajax/show`, `status == 'completed'`). **Поправка:** `&& !$isClient` на двете места (бутона и резервния ред в „Видеоклипове и документи“).
5. **XSS в новия избор на продукт.** Стойностите в `data-content` бяха escape-нати веднъж: браузърът декодира атрибута, а `bootstrap-select` (`sanitize: false` в `initProductSelectPicker`) го вмъква като HTML. Така име `<img onerror=…>` се изпълнява. Такова име влиза през Excel импорта (`ImportProductJob` не прави `strip_tags`) или от стари данни. В JS варианта (`buildProductSelectOption`, данни от `invoices/product-category`) `data-tokens`/`data-content` не escape-ваха `"`: име `x" data-content="&lt;img …&gt;` минава през `strip_tags` от формата за продукт и инжектира атрибут. **Поправка:** `select-option.blade.php` — двойно `e()` на стойностите в `data-content` (име, SKU, цена, URL на снимката). `select-option-script.blade.php` — `escapeAttr()` (HTML + `"`) за `value`, `data-tokens` и `data-content`. Видимият текст е същият, проверено в smoke.
6. `ProductsDataTable` — колоната „Име“ беше сурова (`rawColumns`) → `e($row->name)`, както новата колона SKU (името от Excel импорта).
7. **Количката — изтриване на чужди редове (съществуващо от 6.0.10).** `order_cart` няма `CompanyScope`, а `products/remove-cart-item/{id}` изтриваше произволен ред по id или произволна количка по client id, включително от друга компания. **Поправка:** `all_data` → `abort_403($id != user()->id)`; единичен ред → `OrderCart::where('client_id', user()->id)->findOrFail($id)`. Всички извиквания в UI (`products/index`, `ajax/cart`, `ajax/empty_cart`) пращат своите id. Тест: чужд ред и чужда количка са блокирани, собственият ред се трие (200).
8. `ProductResourceController` — нямаше проверка за модула → конструктор с `abort_403(!in_array('products', $this->user->modules))`, както в `ProductController`.
9. **`show` като цяла страница** (`/account/products/{id}`, не в модала): inline скриптът се изпълнява преди `main.js`. `.tooltip()` хвърляше `$(...).tooltip is not a function` и обработчиците за изтриване и миниатюри не се закачаха (snapshot: `pageerror`). **Поправка:** IIFE → `$(function () {…})`. След поправката няма грешки в snapshot-а.
10. **S7/S9 визия в новите изгледи:**
    - `.pd-badge` имаше `text-transform: capitalize` („Счетоводни Услуги“) → махнат;
    - етикетите в `pd-specs` бяха `text-uppercase` (ALL CAPS, забранено в `ui-guidelines`) → махнат;
    - **тъмната тема беше нечетима** — бели карти със светъл текст (snapshot `--dark`). Цветовете в `<style>` на `show`, `grid`, `resources`, `select-option-styles` минават през `var(--dbk-surface|surface-2|border|text|text-2|text-muted|warning-soft, <upstream стойност>)`. Светлата тема е практически същата, тъмната е четима (snapshot `a06-product-show-dark`).
11. `resources.blade.php`: `app.file` е „Файлове“ (мн.ч.), а `placeholders.title` е „Напр. Управител“ → нов `resources/lang/{eng,bg}/ui-products.php` (`resources.file` = File/Файл, `resources.title_placeholder` = e.g. Installation guide / Напр. Ръководство за инсталиране).
12. `ProductController::edit()` — заглавието беше `__('app.update') . ' ' . __('app.menu.products')` („Актуализирай Продукти“, главна буква по средата) → `__('app.menu.editProducts')` („Редакция на продукти“, същото като заглавието на формата).

Променени файлове: `app/Http/Controllers/{ProductController,ProductResourceController}.php`, `app/Models/{Product,ProductResource}.php`, `app/DataTables/ProductsDataTable.php`, `resources/views/products/ajax/{show,grid,resources,select-option,select-option-script,select-option-styles}.blade.php`, нов `resources/lang/{eng,bg}/ui-products.php`. Не са пипани: `products/index.blade.php`, `products/ajax/{create,edit}.blade.php` (S8 полето е налице), `update-settings/partials/partner-products-promo.blade.php`.

## Къстомизации — потвърдени

- **S8 „Себестойност“:**
  - контролерът (store/update), заявките, моделът, изгледите create/edit/show са налице;
  - запис без полето пази стойността, запис с `cost_price=80` я сменя — тест на `update()` в транзакция с rollback;
  - справката „Маржове по продукти и услуги“ (`databackd.gaps.margins.{index,excel,pdf}`) е 200 като админ: „Себестойност“, „Брутен марж“, „Липсва себестойност за 2 от 20 продукта“; като клиент е 403. `MarginReport` чете `cost_price` като атрибут (`->get([... 'cost_price' ...])`), затова `$hidden` не го засяга;
  - клиентът не вижда себестойността никъде: show, grid, DataTable JSON, `products/options`, `invoices/product-category` — проверено (т. „Резултати“).
- **S6:** нищо не е отслабено. Нови сурови `{!! !!}` в моите файлове има само `{!! $product->description !!}` в `show`. Описанието минава през `trim_editor()` → `EditorSanitizer` (HTMLPurifier, 6.0.19), а `description` вече е в `config/xss_ignore.php`. `{!! $addToCart !!}` в `grid` е собствен низ.
- **S1/S8 скриване:** няма нов модул или пункт в менюто. Ресурсите са част от модула „Продукти“.

## Не е променено — за знание

- При изтриване на продукт редовете в `product_resources` се трият каскадно (FK), но качените файлове остават в `user-uploads/product-resources/` (upstream). Файловете на ресурсите са публични по неотгатваемо име — както снимките и файла за изтегляне на продукта.
- `products/options` (`allProductOption`) няма проверка за права — както в 6.0.10. Връща имена и цени (без себестойност).
- `addCartItem` не проверява `allow_purchase`, `show()` не проверява `allow_purchase` за клиент (по id) — съществуващо от 6.0.10.
- В мрежата бутонът „⋮“ се показва и без право за редакция/изтриване (празно меню) — upstream, козметично.
- Значката „Разрешено“ в `show` за админ не казва какво е разрешено (покупката) — формулировка на upstream.
- Описанието в create/edit е обикновен `textarea` и показва `<p>` от старите записи — upstream.
- В демо данните `p.yordanov@balkan.bg` няма `add_order`/`view_product`, затова продуктите му дават 403 (по данни, не по код). `g.petrov@demofinans.bg` също няма права за продукти. Клиентският изглед е тестван с временно дадени права в транзакция с rollback.
- `products.cart` за админ е 403 — по дизайн (`abort_403(!in_array('client', user_roles()))`), както в 6.0.10.

## Решения за координатора

- **Промото на Froiden** (`partner-products-promo`, само за супер админ, лого от `envato.froid.works`). Не е модул от `config/databackd.php` и го оставих. Ако не се иска — заграждане на `@include` в `update-settings/index.blade.php` (A10) с ключ в `config/databackd.php` (A11). Същото са отбелязали A01 и A10.

## Искания към други файлове

1. **A01 — `resources/lang/{eng,bg}/app.php`:** `hsnSac` е `'Hsn/Sac'` и в двата файла. Досега в `show` се виждаше като „HSN/SAC“ заради `text-uppercase`, който махнах (S9). Предложение (съкращение — както е):
   ```diff
   -    'hsnSac' => 'Hsn/Sac',
   +    'hsnSac' => 'HSN/SAC',
   ```
   (същото и в `bg/app.php`; ключът се ползва и във формите create/edit.)
2. **A01 — `resources/lang/{eng,bg}/validation.php`:** липсва `extensions` (правило от Laravel 11+; иначе грешката е суровият ключ `validation.extensions`). В моя код съм подал изрично съобщение (`validation.mimes`), затова не е блокиращо:
   ```diff
   +    'extensions' => 'The :attribute must have one of the following extensions: :values.',
   ```
   ```diff
   +    'extensions' => 'Файлът в полето „:attribute“ трябва да е с едно от разширенията: :values.',
   ```
3. **A02 (по желание) — `app/Http/Controllers/InvoiceController.php::productCategory()`:** endpoint-ът е достъпен за клиенти (стига модулът `invoices`) и им дава целия каталог, включително продуктите с `allow_purchase = 0` и цените. Себестойността вече я няма (`Product::$hidden`). Ползват го само формите за фактури/оферти (не за клиенти). Предложение:
   ```diff
        public function productCategory(Request $request)
        {
   +        abort_403(in_array('client', user_roles())); // databackd (upgrade 6.0.19): staff-only (invoice/estimate forms)
   ```

## Резултати от проверките

- `php -l`: `ProductController`, `ProductResourceController`, `Product`, `ProductResource`, `ProductsDataTable`, `ui-products.php` (eng/bg) — без грешки.
- `smoke.php products. --ajax --url=/account/products/1 --url=/account/products/1/edit --url=/account/products/3 --url=/account/invoices/product-category/null` (админ): всичко е 200 освен `products.cart` 403 (само за клиент, по дизайн). Заглавието на редакцията е „Редакция на продукти“. „Себестойност“ се вижда в show, create и edit.
- Служител `g.petrov@demofinans.bg`: 403 навсякъде освен `options`/`empty-cart`. Няма права за продукти в демо данните; мрежата отговаря като списъка.
- Клиент `p.yordanov@balkan.bg` (`smoke`): продуктите са 403 по права. `invoices/product-category/null` е 200 и **без** `cost_price` (преди поправката — с). С временни `add_order`/`view_product` = all, ресурс `javascript:` + `https:` и файл за изтегляне (транзакция с rollback):
  - show (страница и ajax), grid, DataTable JSON, `products/options`, `/account/products` — 200;
  - няма „Себестойност“, „72,00“, `cost_price`, `javascript:` и името на файла за изтегляне;
  - https ресурсът се показва.
- Справката за маржовете: админ 200 (index/excel/pdf), клиент 403.
- Тестове в транзакция с rollback (`scratchpad/a06/*.php`; след тях `product_resources` = 0, `cost_price` = 72.00):
  - `syncProductResources` (company_id, link_url);
  - `validateProductResources` (13 случая);
  - `update()` end-to-end (себестойност, ресурси, 422, `ProductResourceController::destroy`, JSON);
  - `removeCartItem`.
- Snapshot: `storage/app/snapshots/a06-product-show-{desktop,mobile}.png`, `a06-product-show-dark-desktop.png`, `a06-product-edit-desktop.png`, `a06-product-create-desktop.png`. Новият show е на български, без JS грешки, „Себестойност 72,00 €“ се вижда за админ, тъмната тема е четима.
- `hardcoded-text.php --list` за моите файлове: 2 сигнала, и двата фалшиви — `products/index.blade.php:550` (вече е `__()` в JS) и `alt="Froiden"` (марка).
- `lang-audit.php --file=ui-products --list`: 0 проблема (65 файла, 10 702 ключа на език).

## За деплоя

- Миграции от мен няма. `product_resources` идва от upstream миграцията (A12). `products.cost_price` не се пипа.
- Нов lang файл: `resources/lang/{eng,bg}/ui-products.php` — да влезе в rsync-а (`bg/` е в git).
- `public/user-uploads/product-resources/` се създава при първото качване (както `products/`). Няма нужда от `.htaccess`.
- Рискове:
  - **`Product::$hidden`** маха `cost_price`, `downloadable_file`, `download_file_url` (и както досега `created_at`/`updated_at`) от всеки JSON с продукти. Проверено е, че няма JS, който да ги чете. Ако някой бъдещ екран ги иска в JSON — `makeVisible()` там;
  - новата валидация на ресурсите е строга: само http(s), списък с разширения, лимит по размер от настройките на приложението.
- Кешове: обичайните след деплоя (`view:clear` заради променените Blade файлове).
