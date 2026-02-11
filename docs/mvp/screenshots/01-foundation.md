# Скрийншоти — S1 Основа

Вход: `admin@demofinans.bg` с паролата по подразбиране на `databackd:demo` (`DatabackdDemoSeeder::PASSWORD`) на `http://127.0.0.1:8123/login` (сървърът се стартира ръчно — вижте `docs/mvp/DEMO.md`). Всички екрани са на български, валута EUR.

| # | Екран | URL | Преди снимката | Доказва |
|---|---|---|---|---|
| 1 | Лично табло | `/account/dashboard` | — | R6, R9 (лесна употреба; скрити ненужни модули) |
| 2 | Разширено табло (само „Общ преглед“ и „Финанси“) | `/account/dashboard/advanced` | таб „Финанси“, период май 2025 – окт 2026 | F8 (основа), R8 |
| 3 | Странично меню (без скритите модули) | всяка страница | разгънати „Работа“, „Финанси“, „Отчети“ | R9 |
| 4 | Клиенти (контрагенти) | `/account/clients` | — | R2 |
| 5 | Фактури | `/account/invoices` | период май 2025 – окт 2026 | F3 (основа), R2 |
| 6 | Плащания | `/account/payments` | — | F3 |
| 7 | Разходи | `/account/expenses` | — | F3 |
| 8 | Договори | `/account/contracts` | — | R2 |
| 9 | Проекти и задачи | `/account/projects`, `/account/tasks` | — | R4 (основа) |
| 10 | Служители, отдели, длъжности | `/account/employees`, `/account/departments`, `/account/designations` | — | R4 (екип специалисти) |
| 11 | Банкови сметки | `/account/bankaccounts` | — | F3 |
| 12 | Валути (само EUR) | `/account/settings/currency-settings` | — | Валута по подразбиране EUR |
| 13 | Отчети → Приходи vs. разходи | `/account/reports/income-expense-report` | период май 2025 – окт 2026 | F1 (основа) |

Раздел „Финансов анализ“ в менюто се показва, когато сесиите S2–S5 добавят файлове в `resources/views/databackd/menu/` (при S1 е скрит).
