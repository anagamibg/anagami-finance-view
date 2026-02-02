# Демо среда (локално)

Само за локална разработка и скрийншоти. Паролите са тестови и **не бива** да се ползват никъде другаде.

## Подготовка (еднократно)

```bash
# PHP 8.3 (php@8.3 от Homebrew), MySQL root без парола
composer install
mysql -u root -e "CREATE DATABASE IF NOT EXISTS databackd_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate
php artisan db:seed          # системни данни (при APP_ENV=codecanyon не пуска демо seeders)
php artisan databackd:demo   # демо компания + данни (безопасно за повторно пускане)
```

`.env` е насочен към `databackd_local` (`APP_URL=http://127.0.0.1:8123`, `APP_DEBUG=true`, `MAIL_MAILER=log`). `APP_ENV` остава `codecanyon`. Оригиналният `.env` към stage е запазен като `.env.stage.backup` (извън git — съдържа тайни, не го качвай).

## Стартиране на сървъра

Сървърът се стартира ръчно, когато е нужен (портове: S1 = 8123, S2 = 8124, S3 = 8125, S4 = 8126, S5 = 8127):

```bash
php artisan serve --port=8123
```

или от Claude Code — конфигурацията `databackd-local` в `.claude/launch.json`.

URL: <http://127.0.0.1:8123/login>

## Демо акаунти

Парола на всички: паролата по подразбиране на `php artisan databackd:demo` (локално; константата `DatabackdDemoSeeder::PASSWORD` в `database/seeders/Databackd/DatabackdDemoSeeder.php`, ползвана от `app/Console/Commands/DatabackdDemo.php`). На production демо акаунтите са с друга, силна парола — в `~/demo-passwords.txt` на сървъра.

| Роля | Имейл | Забележка |
|---|---|---|
| Супер админ | `superadmin@databackd.local` | Управление на компании и пакети (`/account/super-admin-dashboard`) |
| Админ на демо компанията | `admin@demofinans.bg` | Александър Георгиев Тодоров, „Демо Финанс Груп“ |
| Служител | `g.petrov@demofinans.bg` | Георги Петров, старши финансов анализатор |
| Клиент | `p.yordanov@balkan.bg` | Балкан Трейд ЕООД |
| Клиент | `i.stoycheva@rodopi.bg` | Родопи Фуудс АД |

Останалите служители (`m.dimitrova@`, `n.georgiev@`, `d.atanasova@`, `b.marinov@`, `e.yankova@`, `s.popov@`, `v.ninova@` — `@demofinans.bg`) и клиенти ползват същата парола.

## Демо компания

- **„Демо Финанс Груп“** (намира се по име; номерът се определя от базата — локално `company_id = 4`, на сървъра — нова компания със собствен номер), локал `bg`, валута **само EUR**, часова зона Europe/Sofia, формат на датата `дд.мм.гггг`.
- Период на данните: **май 2025 – октомври 2026** (18 месеца).
- Юридически лица за консолидация: „Демо Финанс Груп АД“ (майка), „Демо Търговия ЕООД“ (100%), „Демо Консулт ООД“ (70%).

## Команди

```bash
php artisan databackd:demo                         # всичко (идемпотентно)
php artisan databackd:demo --only=FinModelDemoSeeder   # само един seeder от database/seeders/Databackd/Demo/
php artisan databackd:demo --force                 # разрешава пускане и когато DB_DATABASE не завършва на _local
php artisan databackd:demo --force --password='...' # парола на демо акаунтите (за сървър — задължително силна, различна от тестовата)
```

Супер админът `superadmin@databackd.local` се създава само при база, чието име завършва на `_local`. Командата отказва да работи, ако `DB_DATABASE` не завършва на `_local` (без `--force`).

## Скриване / показване на модули

`config/databackd.php` → `hidden` (`menu`, `dashboard_tabs`, `dashboard_widgets`, `settings`, `quick_add`). Сменете `true` с `false` и изпълнете `php artisan config:clear`, за да върнете модул.
