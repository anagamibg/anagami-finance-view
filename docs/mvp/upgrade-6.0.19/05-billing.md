# A05 — SaaS абонаменти, платежни шлюзове (Worldpay), супер админ (upgrade 6.0.19)

Worktree `ws-merge` (клон `upstream/worksuite-v6.0.19`), база `databackd_upgrade_local`. Списък: `assign/A05-billing.txt` (54 файла: 6 BOTH, 48 UP). Плюс две искания на координатора по време на работата (`front.invoice_payment_failed`, `offline-plan-change/ajax/show`).

## Резюме

- И шестте BOTH файла са слети смислово: нашите S1/S8/CUS_000329 редове и upstream промените (Worldpay, `GatewaySubscriptionService::cancelForCompany`) са налице, без загуби и дублирания.
- **S1 е запазено.** Новите маршрути `billing/worldpay`, `billing/worldpay/device-data` и `billing/worldpay/challenge` са в същата група `account/settings` с `HideBillingPages`. Като админ на компания всички GET страници за billing дават 404.
- Worldpay е безвреден без конфигурация. `worldpay-subscription-charge` излиза веднага, ако `worldpay_status != 'active'`. Двата webhook-а отказват заявката без таен ключ (400) или с непознат hash (404). Модалът за плащане дава 404, ако компанията няма включен Worldpay (това е добавено от A04).
- Поправени са 2 проблема в новия upstream код:
  - (а) Всеки можеше да блокира Worldpay плащането на чужда фактура или поръчка за 15 минути, ако знае id-то ѝ (DoS).
  - (б) Billing страницата пращаше „неуспешно плащане“ с **id на пакета** към маршрута за **фактура на компания**. В 6.0.10 това е записвало failed плащане по фактурата на чужда компания с това id; в 6.0.19 дава 404.
- Преведени са текстовете, които вижда компания или клиент. Добавен е 1 нов lang файл (`ui-billing`, 4 ключа).

## BOTH — три версии (`37235f8` / `main` / `vendor/worksuite` → файлът в `$W`)

Проверено е с `git diff vendor/worksuite -- f`: резултатът се различава от upstream точно с нашия diff (`git diff 37235f8 main -- f`).

| Файл | Наше (запазено) | Upstream (налице) |
|---|---|---|
| `app/Http/Controllers/SuperAdmin/BillingController.php` | CUS_000329: `withCount(['employees','clients','invoices',…])`, проверките `max_clients`/`max_invoices` при downgrade (**преди** upstream `cancelForCompany(..., 'stripe')`, т.е. първо лимитите, после отказът от шлюза); S8: `Module::databackdModules()` в списъка с функции и в таблицата с модули | `worldpay_status` в `paymentGatewatActive`; Worldpay данни в `selectPackage()`; `cancelSubscription()` → `GatewaySubscriptionService`; `cancelForCompany` във `freePlan`/`offlinePaymentSubmit`/razorpay. Махнатите `use Mollie…`/`MollieSubscription` не се ползват никъде (`php -l` OK) |
| `app/Http/Controllers/SuperAdmin/FrontendController.php` | S8 (3×) `databackdModules()`; редовете с `databackd_license_check()` (9f05fe3) — **не са пипани** | `loadLoginPage`: `\user() && isset(user()->company)` |
| `app/Http/Controllers/SuperAdmin/PackageController.php` | S8 (2×) `$this->databackdModules` | `cancelForCompany` в `updateSubscription()` |
| `app/Http/Controllers/SuperAdmin/PayFastController.php` | CUS_000329 проверките `max_clients`/`max_invoices` | `cancelForCompany` при успешно плащане и при mollie unsubscribe |
| `resources/views/super-admin/billing/package.blade.php` | S8: подзаглавието „Финансов анализ (AFV)“ (месечно и годишно) + `databackd_module_label/description` | условието „няма активен шлюз“ включва `worldpay_status` |
| `routes/SuperAdmin/web.php` | S1: `HideBillingPages` на групата `account/settings` (`// databackd`) | `use WorldpayController`; 3 маршрута `billing.worldpay*` **в скритата група**; `billing-worldpay-webhook/{hash}` извън нея (както другите webhooks) |

Файловете на S8/CUS_000329, които upstream не е пипал, са равни на `main`: `PackageDataTable`, `packages/ajax/{create,edit}`, `front/section/pricing-plan`, `saas/pricing-plan`, `billing/plan`, `companies/ajax/show`, `CheckCompanyPackage`, `HideBillingPages`, `Requests/SuperAdmin/Packages/{Store,Update}Request`, `PackageObserver`, `CompanyDataTable`, `SuperAdmin/InvoiceController` (S6, без `enable_php`).

Маршрутите (от `route:list`): всичките 31 маршрута `billing.*` под `account/settings` (вкл. 3-те `billing.worldpay*`) са с `HideBillingPages`.

## UP — прочетени като код

- **Worldpay за фактури и поръчки на компании** (`Payment/WorldpayController`, `Services/Worldpay/{Client,Support,Attempt,Recorder}`, `public-payment/worldpay/{index,three-ds-return}`, `payment-gateway-settings/{index,ajax/worldpay}`, `PaymentGatewayCredentialController`, `UpdateGatewayCredentials`, `PaymentGatewayCredentials` (casts `encrypted` за username/password/webhook_secret, `autoPayEnabled()`), `PaymentGatewayTrait::worldpaySet`):
  - Картата не минава през нас: Access Checkout дава `sessionHref`.
  - Сумата се взема от фактурата (`amountDue()`), не от заявката.
  - Заключването е в кеша (`Cache::add`, 15 мин).
  - URL-ите за действията (device data, challenge) са whitelist-нати до `try.access.worldpay.com`/`access.worldpay.com` (няма SSRF).
  - `threeDsReturn` само рендира изглед: `@json(reference)` + `postMessage` до `window.location.origin`. Изключението от CSRF за `worldpay/3ds-return` е безопасно и важи и през нашия `VerifyCsrfTokenPathOnly` (наследява `$except`).
- **Worldpay за SaaS абонамента** (`SuperAdmin/WorldpayController`, `WorldpaySubscriptionService`, `ChargeWorldpaySubscriptions`, `payment-method-show`, `super-admin/payment-gateway-settings/index`, `GlobalPaymentGatewayCredentials` (casts), `GlobalSubscription` (`worldpay_token` encrypted)):
  - Активирането е в `DB::transaction` + `lockForUpdate`.
  - Идемпотентност: `paidInvoice($reference)` + `Cache::add('worldpay_settle:…')`.
  - `continueAction` проверява, че `attempt.company_id` е на текущата компания.
- **Webhooks:**
  - `worldpay-webhook/{hash}` (компания) — `Company::where('hash')`, HMAC-SHA256 (`Event-Signature`, `hash_equals`) с тайния ключ на **компанията**. Празен ключ → 400. Собственикът на фактурата/поръчката от `transactionReference` трябва да е същата компания, иначе `Ignored`. Повторен webhook → `recordDecision('complete', …) = skip`.
  - `billing-worldpay-webhook/{hash}` (SaaS) — `hash_equals(global_setting()->hash)` + HMAC с глобалния ключ. Приема само референции `wss-…`. Повтор → `paidInvoice` → `skip`.
  - Двата webhook-а са без auth и са в CSRF изключенията (`*-webhook/*`).
- **Останалите шлюзове** (`Stripe/Razorpay/Paystack/PayFast/Authorize/PaypalIPN` webhooks, `MollieController`, `PaystackController`, `SuperAdminPaypalController`, `OfflinePlanChangeController`, `CompanyController`): upstream добавя `shouldStopRecurring()` + `cancel()` (спира отдалечения абонамент, ако компанията вече е на друг или безплатен пакет) и `CompanyRenewedPlan` при подновяване. Логиката е upstream, без нова повърхност за атака.
- **`GatewaySubscriptionService` / `MollieSubscriptionService`:** remote заявките са с timeout 8 s / connect 3 s. Грешките се логват и записът винаги се затваря локално (`ends_at=now`, `inactive`).
- **`LicenceExpire` / `TrialExpire`** (лиценз на **пакета** на компанията, не Envato): единствената промяна е `cancelForCompany($company->id)` преди старата смяна към пакета по подразбиране.
- **`CompanyRenewedPlan`** + `notifications/superadmin/company_renewed_plan`: праща се само на супер админите (`User::whereNull('company_id')`). Ключовете `superadmin.planRenew.*` съществуват (eng). Супер админ панелът не се превежда (S7).
- **`SubscriptionController`, `SubscriptionDataTable`, `ClientSubscriptionDataTable`:** `payfast_sub_status` → `linkedAutoPay()` (PayFast или Worldpay); достъпът е с `autoPayEnabled()`; `rotation_label` е преведен в модела (A02). Без активен PayFast/Worldpay → 403, както преди за PayFast.
- `FrontBaseController` (`AccountSetupRequest::isSetupAllowed()`), `public/img/worldpay.svg` (само `<text>`, без script/href), `public-payment/stripe/stripe-payment` (`$invoice->hash`) — OK.
- `tests/Unit/WorldpaySupportTest.php` — phpunit не е инсталиран (виж CLAUDE.md). Тестът е пуснат през минимален shim (`$S/a05/run-unit.php`): **9 теста, 41 проверки, 0 грешки**. `tests/Feature/WorldpayClientTest.php` не може да се пусне без phpunit. Неговият сценарий за грешка (`fromCredentials` без данни → `RuntimeException`) е проверен в tinker.

## Какво ще направят командите/услугите с 4-те абонамента на production (без шлюз)

- **Как се избират редовете.** Миграцията `2026_08_14_141200` (преглед — A12) и командата `billing:cancel-orphaned-subscriptions` избират `global_subscriptions` с `ends_at IS NULL AND gateway_name NOT IN ('offline')`. В SQL това **изключва и `NULL`**. Всички абонаменти, които приложението създава без платежен шлюз (`CompanyObserver`, `PackageController`, `CompanyController`, `LicenceExpire`, `TrialExpire`, офлайн смяна), са `offline` → **нищо не се пипа, няма отдалечени заявки**. Локално: 2 реда, и двата `offline` → `--list-all` и `--dry-run` показват 0 записа.
- **Ако на production има стар ред с шлюз** (от конверсията `2023_01_11_…` при 5.x). Записът се спира само ако `shouldStopRecurring()` е true: `inactive`, или пакетът на компанията е различен, или е безплатен/по подразбиране. Тогава:
  - `stripe` → обхождат се Cashier `subscriptions` на компанията (на production таблицата е празна, следователно няма заявки) и редът се затваря локално;
  - `paypal` → отдалечени заявки има само при попълнени PayPal ключове **и** записани agreement id-та; иначе само локално;
  - `razorpay`/`paystack`/`payfast`/`authorize`/`mollie` → заявка има само ако редът има transaction/customer id. С празни ключове отговорът е 401 → логва се; редът се затваря локално;
  - `worldpay`/друго → само локално.
- **Какво не се пипа.** `companies` (пакет, лиценз, статус) и `module_settings` остават същите — пакетът на компания 4 или 17 не може да спре.
- **SQL за проверка преди деплоя** (само четене; същата е в отчета на A12): `SELECT id, company_id, package_id, gateway_name, subscription_status, ends_at FROM global_subscriptions WHERE ends_at IS NULL AND gateway_name <> 'offline';` — очаквано 0 реда.
- **`worldpay-subscription-charge`** (Kernel, всеки ден 02:05) излиза на първия ред (`worldpay_status != 'active'`). Пуснат локално: изход 0, без изход на екрана.
- **`licence-expire` / `trial-expire`** (02:00 / 03:00) — за всяка компания с изтекъл лиценз вече се вика `cancelForCompany`. С `offline` абонаменти: 1 SELECT за mollie, 1 за останалите шлюзове, Cashier `subscriptions` (празна), 0 обновени реда; отдалечени заявки няма. Поведението след това е както в 6.0.10.

## Променено

| Файл | Промяна |
|---|---|
| `app/Http/Controllers/Payment/WorldpayController.php` | `respond()`: при резултат, който не е успех или „в процес“, заключването се освобождава (`WorldpayAttempt::release`). Преди резултат `ignore` (напр. Worldpay грешка за невалиден `session_href`) го оставяше 15 мин → всеки можеше да блокира Worldpay плащането на всяка фактура/поръчка по id. Нов `payerMessage()`: платецът вижда преведеното `modules.worldpay.errorMessage`; само текстът за отказ от Worldpay (`refusalDescription`) минава както е, а кодовете (`refused`, `fraudHighRisk`…) и вътрешните английски текстове на `WorldpayRecorder` — не. Маркер `// databackd (upgrade 6.0.19)` |
| `app/Http/Controllers/SuperAdmin/WorldpayController.php` | същото освобождаване на заключването (компанията не можеше да опита пак 15 мин); маркирано |
| `app/Services/Worldpay/WorldpayClient.php` | двата текста на `RuntimeException` (стигат до `Reply::error`) → `__('ui-billing.worldpay.*')`; маркирано. `WorldpaySupport` не е пипан — unit тестът е без Laravel |
| `resources/views/public-payment/worldpay/index.blade.php` | `aria-label` → `__('app.close')` (както другите public-payment модали), `title` на скрития iframe и placeholder-ът `MM/YY` → `ui-billing`; `{{-- databackd (upgrade 6.0.19) --}}` |
| `resources/views/super-admin/billing/payment-method-show.blade.php` | Worldpay: `title` на iframe + `MM/YY` → `ui-billing` (тривиално; останалият стар английски текст в супер-админ изгледите е съзнателно непреведен от S7). **Razorpay `payment.failed`:** махнат е `POST front.invoice_payment_failed` с `invoiceId = $package->id` — показва се грешката от шлюза (`Swal`, резерва `messages.paymentFailed`). Маркирано |
| `resources/views/super-admin/offline-plan-change/ajax/show.blade.php` | (искане на координатора / A10) `x-cards.data-row` за статуса: `html="true"`. От 6.0.19 компонентът escape-ва `$value`. `$status` е само наш статичен HTML: `<i class="fa fa-circle … text-{light-green\|red\|yellow}">` по enum `verified/pending/rejected` + `__('superadmin.offlineRequestStatus.*')`. Минава през `clean_editor()`, което запазва `<i class>` (проверено). Потребителското `description` остава escape-нато. Маркирано. В другите супер-админ изгледи няма `data-row` с HTML |
| `resources/lang/eng/ui-billing.php`, `resources/lang/bg/ui-billing.php` (нови) | `worldpay.device_data_frame` (Card verification / Проверка на картата), `expiry_placeholder` (MM/YY / ММ/ГГ), `credentials_incomplete`, `action_url_rejected` |

## Потвърдени къстомизации

S1 (`HideBillingPages` + скритите маршрути, вкл. Worldpay; `CheckCompanyPackage` = main), S8 (блокът „Финансов анализ (AFV)“ в PackageController/BillingController/FrontendController/PackageDataTable/packages ajax/pricing/billing plan+package — `/pricing` го показва), CUS_000329 (`max_clients`/`max_invoices` във формите, Requests, BillingController, PayFastController), S6 (няма `enable_php`; нов `{!! !!}` в моите upstream изгледи няма; `databackd:check-security`: 83 passed, 0 failed), лицензът (`databackd_license_check()` в FrontendController — не е пипан).

## Намерени проблеми, които не са поправени (upstream; Worldpay не се ползва на production)

1. `Payment/WorldpayController` хваща само `RuntimeException`. Timeout от Worldpay (`ConnectionException`) → 500 и заключването остава 15 мин. `SuperAdmin/WorldpayController` хваща и `\Throwable`. Същото за `continueAction()`, ако втората стъпка хвърли грешка. Засяга само честния платец; в момента не може да се предизвика нарочно.
2. Публичните `worldpay-public/{id}*` са по пореден id, без throttle. Всяка заявка към компания с активен Worldpay прави изходяща заявка с нейните данни за достъп. Както Paystack/Authorize.
3. Платежните endpoints на billing (`SuperAdmin/WorldpayController::pay`, Mollie, Paystack, Stripe…) нямат проверка за роля „админ“ — може всеки потребител на компанията. Сега ги покрива `HideBillingPages` (404). **Ако billing някога се покаже — да се добави проверка за админ.**
4. CUS_000329: съобщенията за downgrade (`You can't downgrade package because your clients length is …`) са твърд английски (стар код, скрити страници).
5. Модалът показва сумата с `number_format(…, 2)` (12.50, не 12,50) — както другите public-payment модали; извън екраните на S12.

## Искания към други файлове

- **A04 / координатор — `resources/views/super-admin/packages/stripe-payment.blade.php:127`** (не е в нито един списък): `route('front.invoice_payment_failed', [$company->id])`. Маршрутът вече е `{hash}` на фактура → 404. Изгледът е **мъртъв**: никой контролер не го рендира (`grep -rn "packages.stripe-payment\|super-admin.packages.stripe-payment" app resources` → 0; billing ползва `super-admin.billing.ajax.stripe-payment`, в който такова извикване няма). Предложение: да се остави (или да се изтрие отделно); ако се поправя — същото като при Razorpay по-горе (без POST към маршрута на фактурата). Останалите извиквания на `front.invoice_payment_failed`/`front.invoice_download` (`invoice.blade.php`, `invoices/stripe/stripe-payment`, `public-payment/stripe/stripe-payment`) вече са с `$invoice->hash`.
- **A02 (по желание) — `resources/views/invoices/index.blade.php:151`:** бутонът „Абонаменти“ е само при `$paymentGateway->payfast_status === 'active'`. Менюто и `SubscriptionController` вече ползват `autoPayEnabled()` (PayFast или Worldpay):
  ```diff
  -                @if (isset($paymentGateway) && $paymentGateway->payfast_status === 'active')
  +                @if (isset($paymentGateway) && $paymentGateway->autoPayEnabled())
  ```
- **A11 (по желание, CSP е Report-Only) — `config/security-headers.php`:** за Worldpay би имало само отчети. Ако се иска чисто:
  - `script-src` += `https://try.access.worldpay.com`, `https://access.worldpay.com`;
  - `frame-src` += същите + `https://centinelapistag.cardinalcommerce.com`, `https://centinelapi.cardinalcommerce.com`;
  - `form-action` += двата хоста на Cardinal.
- **A01 (стар пропуск, не е от ъпгрейда):** `resources/lang/bg/superadmin.php` не съществува. Имейлите до **компании** за пакета (`LicenseExpire`, `LicenseExpirePre`, `TrialLicenseExp`, `TrialLicenseExpPre`, `CompanyUpdatedPlan` — `superadmin.{licenseExpire,licenseExpirePre,trialLicenseExp,trialLicenseExpPre,planUpdate}.*`) остават на английски за bg компании. `licence-expire` се пуска всеки ден. При желание: частичен `bg/superadmin.php` само с тези ключове (`lang-audit` пропуска `superadmin.php` без `--with-superadmin`). Ключовете `modules.worldpay.*`, `modules.payments.worldpay*`, `app.worldpay`, `messages.addWorldpayWebhookUrl` вече са преведени от A01.
- **A13 / координатор — `docs/mvp/tools/smoke.php` с префикс `superadmin.`:** пуска **всички** GET маршрути. Сред тях са `superadmin.superadmin.refresh-cache` (`/account/refresh-cache`) и `superadmin.superadmin.clear-cache` (`/account/clearCache`), които викат `optimize:clear` + `cache:clear` + `cache()->flush()`. Освен тях `dashboard.universal_bundle_alert_dismiss`, `notify.admin` и `billing.paystack.callback` (изходяща заявка към api.paystack.co, 401).
  - **При първото ми пускане (по задание) това се изпълни 4 пъти** (обикновено + ajax).
  - `optimize` **не** е пуснат: `bootstrap/cache/config.php` липсва, затова `refreshCache()` минава по клона без `optimize`. Config/route кеш **не е създаден** (`ls bootstrap/cache` — само `*_module.php`, `packages.php`, `services.php`).
  - Изтрити са само компилираните изгледи и app кешът. Те се създават наново сами, но паралелно работещи smoke-ове на други агенти може да са видели временна грешка.
  - Пускането спря на `superadmin.settings.custom-module-settings.index` [ajax] с PHP Fatal „Cannot redeclare function moveUniversalToFront()“ (функция, декларирана в изглед и рендирана два пъти в един процес — артефакт на smoke в един процес).
  - Препоръка: в smoke.php да се пропускат `*refresh-cache`, `*clear-cache`, `*dismiss*`, `notify.admin`, `*callback*`; дотогава — само по-тесни префикси (така продължих).

## Проверки

- `php -l` — всички 44 PHP файла от списъка + новите lang файлове: OK. Blade → PHP компилация + `php -l` за 5-те пипнати/нови изгледа: OK.
- `smoke.php superadmin. --ajax --email=superadmin@databackd.local`: 55 OK / 48 FAIL до фаталната грешка (виж горе).
  - FAIL-овете са стари и не са от ъпгрейда: upstream не е пипал тези файлове. Това са resource маршрути без метод (`create`/`index`/`lang`/`authSetting` … BadMethodCall), `front-settings/*/{lang?}` без език (`language_code` on null), `workspaces`/`stop_impersonate` като супер админ, пренасочвания.
  - Останалото — с тесни префикси. 200 OK: `superadmin.packages.*` (+ `packages/1/edit`), `companies.*` (+ `companies/4`, `/4/edit`), `superadmin-invoices`, `offline-plan`, `super_admin_dashboard`, `global-payment-gateway-settings` (всички 10 таба, вкл. `?tab=worldpay`), `global-invoice-settings`, `download-settings`, `superadmin-permissions`, `global-custom-fields`, `support-tickets`, `faqs`, `global-currency-settings`.
  - „invalid JSON“ при ajax на `companies.index`/`superadmin-invoices.index` е артефакт на smoke: `json_decode` към обект не приема ключ `"\u0000*\u0000value"`; като масив се чете (DataTables в браузъра — OK).
- Като админ на компания (`admin@demofinans.bg`):
  - `smoke.php billing. --ajax` + `select-package/3`, `paypal/3/monthly`, `invoice-download/1` → **всички 404** (S1);
  - `payment-gateway-settings` (index + 11 таба, вкл. `worldpay`) → 200;
  - `subscriptions.*` и `client.subscriptions.*` (клиент) → 403 (няма PayFast/Worldpay; очаквано).
- Публични: `worldpay/3ds-return` 200; `worldpay-webhook/abc` (GET) 200 (JSON „only POST“); `invoice-worldpay/worldpay-modal?id=609` → 404 (Worldpay не е включен, A04). Модалът с активен Worldpay, рендиран директно с тестови данни (bg): всичко е на български („Плащане чрез Worldpay“, „Дължима сума“, „Номер на картата“, „Плати сега“, „Затвори“…), placeholder `ММ/ГГ`.
- `respond()` с Worldpay грешка (tinker, без запис в БД): отговор „Възникна проблем с плащането…“; заключването е свободно след това. `payerMessage`: `Card refused` минава; `refused` / `Payment could not be completed.` / `Invoice not found…` → преведеният общ текст.
- `hardcoded-text.php --list` за моите файлове: от новия код не остава нищо. Остават само стари SA попадения (`aria-label="Close"`, `title="Choose Plan"`, `'Enter Your Card Details'` в `payment-method-show`, коментар в `package`, `CompanyController:570`, `FrontendController:433 'Login Page'`) — супер админ, S7 съзнателно.
- `lang-audit.php`: `ui-billing.php` — 0 критични, 2 предупреждения `latin` (марката Worldpay).
- `billing:cancel-orphaned-subscriptions --list-all` / `--dry-run` → 0; `worldpay-subscription-charge` → изход 0, без нищо.

## За деплоя

- Миграции: моите файлове не носят миграции. Ползват колоните от `2026_10_22_000001_add_worldpay_payment_gateway`, `2026_10_23_000001_add_worldpay_to_global_billing` и данните от `2026_08_14_141200` (A12). Преди `migrate` — SQL проверката по-горе.
- Нови файлове за качване: `resources/lang/{eng,bg}/ui-billing.php`, `app/Services/Worldpay/*`, `app/Services/SuperAdmin/*`, двете команди `CancelOrphaned*`, `ChargeWorldpaySubscriptions`, `Payment/WorldpayController`, `SuperAdmin/WorldpayController`, `CompanyRenewedPlan` + изгледа, `public-payment/worldpay/*`, `payment-gateway-settings/ajax/worldpay`, `public/img/worldpay.svg`. Без `tests/` (не се деплойват).
- Настройки: няма нужда от нищо. Worldpay остава `deactive` (миграциите го създават изключен). `WORLDPAY_*` в `.env` не са нужни.
- **Encrypted полета:** `worldpay_username/password/webhook_secret` и `global_subscriptions.worldpay_token` са `encrypted` с `APP_KEY`. При бъдещата ротация на `APP_KEY` (отворена задача от S6) стават нечетими. Сега са празни.
- Риск: нисък. Billing остава скрит. Worldpay кодът не прави нищо без конфигурация. `licence-expire`/`trial-expire` викат `cancelForCompany` — без отдалечени заявки за `offline` абонаменти.
