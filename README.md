# Anagami Finance View

> Частно хранилище за вътрешно ползване в Anagami. Не публикувайте кода извън организацията.

**AFV** е SaaS платформа за управление, обработка и анализ на финансовите данни на микро, малки и средни фирми: въвеждане и импорт на данни от различни източници, справки и табла, финансови коефициенти и консолидация, бюджети и прогнози, работа с екип от специалисти. Интерфейсът е на български и английски.

Приложението е изградено върху Laravel 12 (PHP 8.2+), Blade + Bootstrap 4 + jQuery и работи в многофирмен (SaaS) режим с панел за супер администратор, пакети и модули.

## Модули

Функционалностите на AFV са модули на SaaS пакетите и се включват по пакет:

| Модул | Какво включва |
|---|---|
| `databackd_data` | Финансов модел: юридически лица, сметкоплан, измерения, записи, групово въвеждане, история на импортите с отмяна, прехвърляне от фактури и разходи |
| `databackd_integrations` | Страница „Интеграции“: Excel (.xlsx/.xls), CSV (UTF-8, UTF-16, Windows-1251), Google Sheets, REST API с токени, готови шаблони за Микроинвест Делта Pro, Ажур L, Плюс Минус и Бизнес навигатор, банкови извлечения MT940 и CAMT.053 |
| `databackd_reports` | Табло, отчет за приходите и разходите, паричен поток, контрагенти, конструктор на справки, запазени и планирани справки, експорт |
| `databackd_analytics` | Финансови коефициенти, конструктор на коефициенти, консолидация и правила за консолидация, маржове |
| `databackd_planning` | Бюджети, редактор на бюджети, прогнози, анализ на отклоненията |
| `databackd_teams` | Специалисти, заявки и анализи от екипа |

Освен тях приложението има базови модули за проекти, задачи, клиенти, служители, фактури и разходи.

## Структура на кода

- `app/**/Databackd/**` — контролери, модели, услуги, заявки и задачи на AFV
- `routes/databackd/*.php` — маршрутите на модулите (зареждат се с префикс `account`); `routes/databackd/api/*` — REST API (`/api/databackd/...`)
- `resources/views/databackd/**` — изгледи; `resources/lang/{bg,eng}/databackd-*.php` — преводи
- `config/databackd.php`, `config/databackd-api.php` — настройки (скрити страници, лимити на API)
- `database/seeders/Databackd/**` — демо данни
- `docs/mvp/` — описание на функционалностите (`features/`), бележки за предаване и деплой (`handoff/`), преглед на сигурността (`security/`), инструменти (`tools/`)
- `docs/APP-UPDATE-AND-CUSTOMIZATIONS.md` — правила за обновяване на базовия (upstream) код и списък на локалните промени

## Локална среда

```bash
composer install
npm install
cp .env.example .env   # след това: php artisan key:generate и данни за базата
php artisan migrate
npm run dev            # еднократен build на CSS/JS (npm run watch / npm run prod)
php artisan serve
```

`APP_ENV` трябва да остане `codecanyon` — от него зависи четенето на настройките за поща и плащания от базата.

## Проверки

```bash
php artisan test
php docs/mvp/tools/smoke.php databackd.data.          # рендира всички страници с даден префикс на маршрута, без уеб сървър
php docs/mvp/tools/lang-audit.php --strict            # пълнота и качество на преводите BG/EN
```

## Деплой

Продукционният сървър не е git хранилище: файловете се качват с `rsync` по списък, след което се пускат миграциите и се обновяват кешовете (`php artisan optimize:clear && php artisan config:cache && php artisan route:cache`). Подробности и стъпки за връщане назад: `docs/mvp/handoff/10-review-deploy.md`.

## Използвани библиотеки в интерфейса

<ol>
    <li><strong>Bootstrap 4</strong> - <a href="https://getbootstrap.com/">https://getbootstrap.com/</a></li>
    <li><strong>Moment.js</strong> - <a href="https://momentjs.com/">https://momentjs.com/</a></li>
    <li><strong>Bootstrap Select</strong> - <a href="https://developer.snapappointments.com/bootstrap-select/">https://developer.snapappointments.com/bootstrap-select/</a></li>
    <li><strong>Datepicker</strong> - <a href="https://github.com/qodesmith/datepicker">https://github.com/qodesmith/datepicker</a></li>
    <li><strong>Fontawesome</strong> - <a href="https://fontawesome.com/">https://fontawesome.com/</a></li>
    <li><strong>Bootstrap Icons (менюта)</strong> - <a href="https://icons.getbootstrap.com/">https://icons.getbootstrap.com/</a></li>
    <li><strong>Dropify (качване на файлове)</strong> - <a href="https://github.com/JeremyFagis/dropify">https://github.com/JeremyFagis/dropify</a></li>
    <li><strong>sweetalert2 (съобщения и потвърждения)</strong> - <a href="https://sweetalert2.github.io/">https://sweetalert2.github.io/</a></li>
    <li><strong>Quilljs (редактор на текст)</strong> - <a href="https://quilljs.com/">https://quilljs.com/</a></li>
    <li><strong>Frappe Charts</strong> - <a href="https://frappe.io/charts">https://frappe.io/charts</a></li>
    <li><strong>Bootstrap MultiDatesPicker</strong> - <a href="https://github.com/uxsolutions/bootstrap-datepicker">https://github.com/uxsolutions/bootstrap-datepicker</a></li>
    <li><strong>Bootstrap Colorpicker</strong> - <a href="https://github.com/itsjavi/bootstrap-colorpicker">https://github.com/itsjavi/bootstrap-colorpicker</a></li>
    <li><strong>jQuery UI (подреждане с влачене)</strong> - <a href="https://jqueryui.com/">https://jqueryui.com/</a></li>
    <li><strong>Highlight JS (оцветяване на HTML)</strong> - <a href="https://github.com/highlightjs/highlight.js">https://github.com/highlightjs/highlight.js</a></li>
    <li><strong>Chart.js</strong> - <a href="https://www.chartjs.org/">https://www.chartjs.org/</a></li>
    <li><strong>Image Picker</strong> - <a href="https://rvera.github.io/image-picker/">https://rvera.github.io/image-picker/</a></li>
    <li><strong>Cropper.js</strong> - <a href="https://github.com/fengyuanchen/cropperjs">https://github.com/fengyuanchen/cropperjs</a></li>
</ol>
