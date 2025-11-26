<?php

namespace Database\Seeders\Databackd\Demo;

use App\Helper\Files;
use App\Models\ClientDetails;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Databackd\AnalysisRequest;
use App\Models\Databackd\AnalysisRequestComment;
use App\Models\Databackd\SpecialistProfile;
use App\Models\Databackd\TeamRequest;
use App\Models\Databackd\TeamRequestMember;
use App\Models\Databackd\TeamRequestStatusLog;
use App\Models\FileStorage;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMember;
use App\Models\ProjectTimeLog;
use App\Models\UniversalSearch;
use App\Models\User;
use App\Scopes\ActiveScope;
use App\Scopes\CompanyScope;
use App\Services\Databackd\Teams\PriceEstimator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

/**
 * AFV demo of the "Teams" package: specialist profiles, 6 team requests (with status timelines, assigned
 * members and one real AFV project with time logs) and 28 analysis requests with comments, ratings,
 * recurring chains and generated sample deliverables (PDF via dompdf, XLSX via maatwebsite/excel).
 *
 * Files: the generated sources live in storage/app/databackd-demo/teams and are copied to the AFV upload
 * folder public/user-uploads/databackd/analysis (AnalysisRequest::FILE_FOLDER), so deliverable_file / comment file
 * resolve exactly like uploads made through App\Helper\Files (folder + file name).
 *
 * Idempotent: all rows of the company in the six tables are removed first, together with the demo files
 * (found through deliverable_file / file columns) and the demo project (project_short_code EKIP-5).
 */
class TeamsDemoSeeder extends Seeder
{

    const TODAY = '2026-10-27';
    const NOW = '2026-10-27 11:30:00';
    const PROJECT_CODE = 'EKIP-5';
    const SOURCE_DIR = 'databackd-demo/teams';

    const SPECIALISTS = [
        // key => role, seniority, hourly rate EUR, weekly capacity, languages, skills, available
        'g.petrov' => ['financial_analyst', 'senior', 40, 40, 'Български, English, Deutsch', ['Финансово моделиране', 'Power BI', 'МСФО', 'Консолидация', 'Excel'], true],
        'm.dimitrova' => ['financial_analyst', 'mid', 30, 40, 'Български, English', ['Excel', 'Power BI', 'SQL', 'Бюджетиране'], true],
        'n.georgiev' => ['accountant', 'mid', 26, 40, 'Български, English', ['НСС', 'МСФО', 'Данъчно планиране', 'Excel'], true],
        'd.atanasova' => ['accountant', 'junior', 22, 40, 'Български', ['НСС', 'Excel', 'Данъчно планиране'], false],
        'b.marinov' => ['controller', 'senior', 38, 40, 'Български, English, Deutsch', ['Контролинг', 'Бюджетиране', 'Консолидация', 'Финансово моделиране', 'Excel'], true],
        'e.yankova' => ['business_analyst', 'mid', 32, 40, 'Български, English', ['Power BI', 'SQL', 'Финансово моделиране', 'Бизнес анализ'], true],
        's.popov' => ['team_lead', 'senior', 45, 40, 'Български, English, Deutsch', ['Управление на проекти', 'МСФО', 'Финансово моделиране', 'Бюджетиране', 'Консолидация'], true],
        'v.ninova' => ['data_analyst', 'junior', 18, 30, 'Български, English', ['Python', 'SQL', 'Excel', 'Power BI'], true],
    ];

    const NOTE_NEW = 'Заявката е подадена от клиента.';
    const NOTE_NEW_ADMIN = 'Заявката е въведена от управителя след разговор с клиента.';
    const NOTE_REVIEWING = 'Заявката е поета за преглед от екипа на „Демо Финанс Груп“.';
    const NOTE_PROPOSED = 'Изпратена е оферта с предложен състав на екипа и индикативна месечна цена.';
    const NOTE_ACTIVE = 'Клиентът потвърди офертата. Екипът е назначен и стартира работа.';

    /** The six team requests; log = [to_status, datetime, who (client|admin), note]. */
    const TEAMS = [
        [
            'client' => 'p.yordanov', 'by' => 'client',
            'title' => 'Външен финансов анализатор на непълно работно време',
            'size' => '1', 'roles' => ['financial_analyst' => 1], 'engagement' => 'hours_per_week', 'hours' => 10,
            'start' => '2026-06-01', 'months' => 6, 'status' => 'active', 'created' => '2026-04-20 10:12:00',
            'description' => 'Търсим финансов анализатор за около 10 часа седмично, който да изготвя месечните управленски отчети, да следи изпълнението на бюджета и да подготвя кратки анализи за управителя.',
            'members' => [['m.dimitrova', 'financial_analyst', 10]],
            'log' => [
                ['new', '2026-04-20 10:12:00', 'client', self::NOTE_NEW],
                ['reviewing', '2026-04-22 09:30:00', 'admin', self::NOTE_REVIEWING],
                ['proposed', '2026-04-28 15:05:00', 'admin', self::NOTE_PROPOSED],
                ['active', '2026-05-12 11:20:00', 'client', self::NOTE_ACTIVE],
            ],
        ],
        [
            'client' => 'i.stoycheva', 'by' => 'client',
            'title' => 'Екип за управленско отчитане и счетоводен контрол',
            'size' => '2', 'roles' => ['financial_analyst' => 1, 'accountant' => 1], 'engagement' => 'hours_per_week', 'hours' => 20,
            'start' => '2026-07-01', 'months' => 9, 'status' => 'active', 'created' => '2026-05-25 14:40:00',
            'description' => 'Необходими са двама специалисти (финансов анализатор и счетоводител) по 20 часа седмично за месечно управленско отчитане, сверки и подготовка на данните за консолидация на групата.',
            'members' => [['g.petrov', 'financial_analyst', 20], ['n.georgiev', 'accountant', 20]],
            'log' => [
                ['new', '2026-05-25 14:40:00', 'client', self::NOTE_NEW],
                ['reviewing', '2026-05-27 10:10:00', 'admin', self::NOTE_REVIEWING],
                ['proposed', '2026-06-03 16:25:00', 'admin', self::NOTE_PROPOSED],
                ['active', '2026-06-15 09:45:00', 'client', self::NOTE_ACTIVE],
            ],
        ],
        [
            'client' => 'k.rashkov', 'by' => 'admin',
            'title' => 'Екип за финансов контрол и консолидация – логистична група',
            'size' => '5', 'roles' => ['financial_analyst' => 1, 'controller' => 1, 'business_analyst' => 1, 'team_lead' => 1, 'data_analyst' => 1],
            'engagement' => 'full_time', 'hours' => 40,
            'start' => '2026-04-15', 'months' => 12, 'status' => 'active', 'created' => '2026-03-10 09:05:00',
            'description' => 'Изграждане на постоянен екип за финансов контрол на логистичната група: месечно отчитане, бюджетиране, анализ на рентабилността по маршрути и автоматизиране на отчетите с Power BI.',
            'members' => [
                ['s.popov', 'team_lead', 30], ['b.marinov', 'controller', 36], ['e.yankova', 'business_analyst', 36],
                ['m.dimitrova', 'financial_analyst', 28], ['v.ninova', 'data_analyst', 24],
            ],
            'project' => true,
            'log' => [
                ['new', '2026-03-10 09:05:00', 'admin', self::NOTE_NEW_ADMIN],
                ['reviewing', '2026-03-12 13:15:00', 'admin', self::NOTE_REVIEWING],
                ['proposed', '2026-03-20 17:00:00', 'admin', self::NOTE_PROPOSED],
                ['active', '2026-04-02 10:30:00', 'client', self::NOTE_ACTIVE],
            ],
        ],
        [
            'client' => 'z.radev', 'by' => 'client',
            'title' => 'Разширен екип за финансово планиране – земеделска група',
            'size' => '10', 'roles' => ['financial_analyst' => 3, 'accountant' => 3, 'controller' => 1, 'business_analyst' => 1, 'data_analyst' => 2],
            'engagement' => 'hours_per_week', 'hours' => 20,
            'start' => '2026-11-16', 'months' => 6, 'status' => 'proposed', 'created' => '2026-10-05 11:50:00',
            'description' => 'Сезонен проект за годишно планиране и бюджетиране на няколко стопанства, с месечно отчитане и прогнозиране на паричните потоци. Нужни са 10 души на по около 20 часа седмично.',
            'members' => [],
            'log' => [
                ['new', '2026-10-05 11:50:00', 'client', self::NOTE_NEW],
                ['reviewing', '2026-10-07 09:20:00', 'admin', self::NOTE_REVIEWING],
                ['proposed', '2026-10-15 14:35:00', 'admin', self::NOTE_PROPOSED],
            ],
        ],
        [
            'client' => 'l.slavov', 'by' => 'client',
            'title' => 'Финансов център за споделени услуги',
            'size' => '20', 'roles' => ['financial_analyst' => 6, 'accountant' => 5, 'controller' => 2, 'business_analyst' => 3, 'data_analyst' => 3, 'team_lead' => 1],
            'engagement' => 'full_time', 'hours' => 40,
            'start' => '2027-01-11', 'months' => 12, 'status' => 'reviewing', 'created' => '2026-10-19 10:25:00',
            'description' => 'Изнасяне на счетоводните и аналитичните дейности на инженерингова група към външен екип от 20 души на пълен работен ден, с единен процес за месечно приключване и отчитане.',
            'members' => [],
            'log' => [
                ['new', '2026-10-19 10:25:00', 'client', self::NOTE_NEW],
                ['reviewing', '2026-10-22 09:40:00', 'admin', self::NOTE_REVIEWING],
            ],
        ],
        [
            'client' => 'o.danchev', 'by' => 'client',
            'title' => 'Мащабен екип за финансова трансформация',
            'size' => '50', 'roles' => ['financial_analyst' => 15, 'accountant' => 12, 'controller' => 5, 'business_analyst' => 8, 'data_analyst' => 7, 'team_lead' => 3],
            'engagement' => 'full_time', 'hours' => 40,
            'start' => '2027-02-01', 'months' => 12, 'status' => 'new', 'created' => '2026-10-26 14:05:00',
            'description' => 'Проект за финансова трансформация на енергийна група: ново отчитане по МСФО, консолидация на 14 дружества, внедряване на бюджетен процес и аналитични табла. Очакваме предложение за екип от 50 души.',
            'members' => [],
            'log' => [
                ['new', '2026-10-26 14:05:00', 'client', self::NOTE_NEW],
            ],
        ],
    ];

    const BASE_REVENUE = ['p.yordanov' => 95000, 'i.stoycheva' => 310000, 'k.rashkov' => 180000, 't.baneva' => 60000, 'z.radev' => 140000];
    const CLIENT_SLUG = ['p.yordanov' => 'balkan', 'i.stoycheva' => 'rodopi', 'k.rashkov' => 'cherno', 't.baneva' => 'staroplaninski', 'z.radev' => 'dunav'];
    const TYPE_SLUG = ['report' => 'report', 'analysis' => 'analysis', 'ratio_review' => 'ratios', 'budget' => 'budget', 'forecast' => 'forecast', 'consolidation' => 'consolidation', 'other' => 'other'];

    const DESCRIPTIONS = [
        'report' => 'Моля за изготвяне на управленски отчет – приходи и разходи, баланс и ключови показатели, със сравнение с бюджета и с предходния период.',
        'analysis' => 'Необходим е аналитичен преглед с изводи и препоръки: основни тенденции, отклонения и фактори, които влияят на резултата.',
        'ratio_review' => 'Моля за преглед на финансовите коефициенти (ликвидност, задлъжнялост, рентабилност) с оценка спрямо отрасловите стойности и препоръки.',
        'budget' => 'Необходима е помощ при изготвяне на бюджет по пера и месеци, с отчитане на сезонността и очакваното развитие на пазара.',
        'forecast' => 'Моля за прогноза на показателите за посочения период – базов, оптимистичен и песимистичен сценарий.',
        'consolidation' => 'Консолидиране на отчетите на дружествата от групата – елиминиране на вътрешногруповите разчети и обороти.',
        'other' => 'Нестандартна заявка – допълнителните подробности са в коментарите. При нужда ще предоставим необходимите документи.',
    ];

    /**
     * Analysis requests. k = key, parent = key of the previous instance of a recurring request,
     * file = pdf|xlsx for delivered / closed, rating = 1-5, link = route name of a saved report (optional).
     */
    const REQUESTS = [
        // Chain A: monthly P&L report of Балкан Трейд
        ['k' => 'A1', 'client' => 'p.yordanov', 'type' => 'report', 'title' => 'Месечен отчет ОПР – август 2026', 'status' => 'closed', 'by' => 'm.dimitrova', 'prio' => 'medium', 'rec' => 'monthly', 'parent' => null, 'from' => '2026-08-01', 'to' => '2026-08-31', 'due' => '2026-09-10', 'created' => '2026-09-02 09:15', 'delivered' => '2026-09-09 16:40', 'file' => 'pdf', 'rating' => 5, 'feedback' => 'Отчетът е ясен и навременен. Особено полезен е анализът на отклоненията спрямо бюджета.'],
        ['k' => 'A2', 'client' => 'p.yordanov', 'type' => 'report', 'title' => 'Месечен отчет ОПР – септември 2026', 'status' => 'delivered', 'by' => 'm.dimitrova', 'prio' => 'medium', 'rec' => 'monthly', 'parent' => 'A1', 'from' => '2026-09-01', 'to' => '2026-09-30', 'due' => '2026-10-09', 'created' => '2026-09-09 16:45', 'delivered' => '2026-10-08 15:20', 'file' => 'xlsx', 'rating' => 4, 'feedback' => 'Много добър отчет, но бихме искали разбивка по продуктови групи в следващия месец.', 'link' => 'databackd.planning.variance.index'],
        ['k' => 'A3', 'client' => 'p.yordanov', 'type' => 'report', 'title' => 'Месечен отчет ОПР – октомври 2026', 'status' => 'in_progress', 'by' => 'm.dimitrova', 'prio' => 'medium', 'rec' => 'monthly', 'parent' => 'A2', 'from' => '2026-10-01', 'to' => '2026-10-31', 'due' => '2026-11-09', 'created' => '2026-10-08 15:30'],
        // Chain B: monthly cash flow report of Родопи Фуудс
        ['k' => 'B1', 'client' => 'i.stoycheva', 'type' => 'report', 'title' => 'Месечен отчет за паричния поток – август 2026', 'status' => 'closed', 'by' => 'g.petrov', 'prio' => 'medium', 'rec' => 'monthly', 'parent' => null, 'from' => '2026-08-01', 'to' => '2026-08-31', 'due' => '2026-09-14', 'created' => '2026-09-03 10:00', 'delivered' => '2026-09-11 14:10', 'file' => 'xlsx', 'rating' => 4, 'feedback' => 'Информацията е точна. Препоръчваме да се добавят по-подробни коментари към паричните потоци.'],
        ['k' => 'B2', 'client' => 'i.stoycheva', 'type' => 'report', 'title' => 'Месечен отчет за паричния поток – септември 2026', 'status' => 'delivered', 'by' => 'g.petrov', 'prio' => 'medium', 'rec' => 'monthly', 'parent' => 'B1', 'from' => '2026-09-01', 'to' => '2026-09-30', 'due' => '2026-10-12', 'created' => '2026-09-11 14:15', 'delivered' => '2026-10-10 11:50', 'file' => 'pdf'],
        ['k' => 'B3', 'client' => 'i.stoycheva', 'type' => 'report', 'title' => 'Месечен отчет за паричния поток – октомври 2026', 'status' => 'new', 'by' => 'n.georgiev', 'prio' => 'low', 'rec' => 'monthly', 'parent' => 'B2', 'from' => '2026-10-01', 'to' => '2026-10-31', 'due' => '2026-11-10', 'created' => '2026-10-10 11:55'],
        // Chain C: quarterly liquidity ratios of Балкан Трейд
        ['k' => 'C1', 'client' => 'p.yordanov', 'type' => 'ratio_review', 'title' => 'Преглед на коефициенти за ликвидност Q2 2026', 'status' => 'closed', 'by' => 'b.marinov', 'prio' => 'medium', 'rec' => 'quarterly', 'parent' => null, 'from' => '2026-04-01', 'to' => '2026-06-30', 'due' => '2026-07-20', 'created' => '2026-07-06 09:30', 'delivered' => '2026-07-17 12:00', 'file' => 'pdf', 'rating' => 5, 'feedback' => 'Отлична работа! Коефициентите са добре обяснени и препоръките са приложими веднага.'],
        ['k' => 'C2', 'client' => 'p.yordanov', 'type' => 'ratio_review', 'title' => 'Преглед на коефициенти за ликвидност Q3 2026', 'status' => 'review', 'by' => 'b.marinov', 'prio' => 'high', 'rec' => 'quarterly', 'parent' => 'C1', 'from' => '2026-07-01', 'to' => '2026-09-30', 'due' => '2026-10-25', 'created' => '2026-07-17 12:05'],
        // Chain D: quarterly consolidation of Родопи Фуудс
        ['k' => 'D1', 'client' => 'i.stoycheva', 'type' => 'consolidation', 'title' => 'Консолидация за Q2 2026', 'status' => 'delivered', 'by' => 'g.petrov', 'prio' => 'high', 'rec' => 'quarterly', 'parent' => null, 'from' => '2026-04-01', 'to' => '2026-06-30', 'due' => '2026-07-31', 'created' => '2026-07-08 10:20', 'delivered' => '2026-07-29 17:05', 'file' => 'xlsx'],
        ['k' => 'D2', 'client' => 'i.stoycheva', 'type' => 'consolidation', 'title' => 'Консолидация за Q3 2026', 'status' => 'in_progress', 'by' => 'g.petrov', 'prio' => 'high', 'rec' => 'quarterly', 'parent' => 'D1', 'from' => '2026-07-01', 'to' => '2026-09-30', 'due' => '2026-10-30', 'created' => '2026-07-29 17:10'],
        // Chain E: monthly cost report of Черно Море Логистик
        ['k' => 'E1', 'client' => 'k.rashkov', 'type' => 'report', 'title' => 'Месечен отчет за разходите – септември 2026', 'status' => 'delivered', 'by' => 'b.marinov', 'prio' => 'medium', 'rec' => 'monthly', 'parent' => null, 'from' => '2026-09-01', 'to' => '2026-09-30', 'due' => '2026-10-12', 'created' => '2026-10-01 09:00', 'delivered' => '2026-10-09 16:20', 'file' => 'pdf'],
        ['k' => 'E2', 'client' => 'k.rashkov', 'type' => 'report', 'title' => 'Месечен отчет за разходите – октомври 2026', 'status' => 'in_progress', 'by' => 'b.marinov', 'prio' => 'medium', 'rec' => 'monthly', 'parent' => 'E1', 'from' => '2026-10-01', 'to' => '2026-10-31', 'due' => '2026-11-12', 'created' => '2026-10-09 16:25'],
        // Single requests of Балкан Трейд
        ['k' => 's1', 'client' => 'p.yordanov', 'type' => 'analysis', 'title' => 'Анализ на рентабилността по продуктови групи', 'status' => 'closed', 'by' => 'g.petrov', 'prio' => 'medium', 'from' => '2026-01-01', 'to' => '2026-06-30', 'due' => '2026-08-03', 'created' => '2026-07-13 09:00', 'delivered' => '2026-07-31 13:30', 'file' => 'pdf', 'rating' => 5, 'feedback' => 'Анализът ни помогна да преразгледаме ценовата политика на две продуктови групи. Благодарим!'],
        ['k' => 's2', 'client' => 'p.yordanov', 'type' => 'budget', 'title' => 'Бюджет 2026 – актуализация за второто полугодие', 'status' => 'delivered', 'by' => 'm.dimitrova', 'prio' => 'high', 'from' => '2026-07-01', 'to' => '2026-12-31', 'due' => '2026-06-30', 'created' => '2026-06-10 11:00', 'delivered' => '2026-06-26 15:00', 'file' => 'xlsx', 'link' => 'databackd.planning.budgets.index'],
        ['k' => 's3', 'client' => 'p.yordanov', 'type' => 'ratio_review', 'title' => 'Анализ на оборотния капитал и вземанията', 'status' => 'delivered', 'by' => 'm.dimitrova', 'prio' => 'medium', 'from' => '2026-01-01', 'to' => '2026-08-31', 'due' => '2026-10-02', 'created' => '2026-09-08 10:40', 'delivered' => '2026-10-01 14:00', 'file' => 'pdf', 'rating' => 4, 'feedback' => 'Полезен преглед на вземанията, получихме конкретни стъпки за подобряване на събираемостта.'],
        ['k' => 's4', 'client' => 'p.yordanov', 'type' => 'forecast', 'title' => 'Прогноза за паричния поток – 6 месеца', 'status' => 'review', 'by' => 'g.petrov', 'prio' => 'high', 'from' => '2026-11-01', 'to' => '2027-04-30', 'due' => '2026-10-30', 'created' => '2026-10-06 09:20'],
        ['k' => 's5', 'client' => 'p.yordanov', 'type' => 'budget', 'title' => 'Бюджет 2027 – първа версия', 'status' => 'in_progress', 'by' => 's.popov', 'prio' => 'high', 'from' => '2027-01-01', 'to' => '2027-12-31', 'due' => '2026-11-20', 'created' => '2026-10-12 10:10'],
        ['k' => 's6', 'client' => 'p.yordanov', 'type' => 'analysis', 'title' => 'Анализ на разходите за персонал', 'status' => 'in_progress', 'by' => 'e.yankova', 'prio' => 'high', 'from' => '2026-01-01', 'to' => '2026-09-30', 'due' => '2026-10-23', 'created' => '2026-09-28 13:00'],
        ['k' => 's7', 'client' => 'p.yordanov', 'type' => 'other', 'title' => 'Подготовка на данни за банков кредит', 'status' => 'new', 'by' => null, 'prio' => 'medium', 'from' => '2026-01-01', 'to' => '2026-09-30', 'due' => '2026-11-13', 'created' => '2026-10-26 16:45', 'desc' => 'Банката ни изисква финансови данни за кредитна заявка: отчети за последните 12 месеца, показатели за ликвидност и обслужване на дълга. Моля за помощ при подреждането на справките.'],
        // Single requests of Родопи Фуудс
        ['k' => 's8', 'client' => 'i.stoycheva', 'type' => 'analysis', 'title' => 'Анализ на сезонността на продажбите', 'status' => 'closed', 'by' => 'e.yankova', 'prio' => 'medium', 'from' => '2025-05-01', 'to' => '2026-03-31', 'due' => '2026-05-22', 'created' => '2026-04-14 09:30', 'delivered' => '2026-05-20 10:10', 'file' => 'pdf', 'rating' => 5, 'feedback' => 'Много задълбочен анализ, с който управителният съвет остана доволен. Препоръчваме екипа.'],
        ['k' => 's9', 'client' => 'i.stoycheva', 'type' => 'other', 'title' => 'Данъчно планиране – ефект от новите разпоредби', 'status' => 'closed', 'by' => 'd.atanasova', 'prio' => 'low', 'from' => '2026-01-01', 'to' => '2026-12-31', 'due' => '2026-05-06', 'created' => '2026-04-06 10:00', 'delivered' => '2026-05-04 16:00', 'file' => 'xlsx', 'rating' => 3, 'feedback' => 'Добра работа, но очаквахме повече детайл по ефектите върху ДДС.'],
        ['k' => 's10', 'client' => 'i.stoycheva', 'type' => 'forecast', 'title' => 'Прогноза за приходите до края на 2026', 'status' => 'delivered', 'by' => 'g.petrov', 'prio' => 'medium', 'from' => '2026-10-01', 'to' => '2026-12-31', 'due' => '2026-10-12', 'created' => '2026-09-14 09:00', 'delivered' => '2026-10-09 13:45', 'file' => 'xlsx', 'rating' => 4, 'feedback' => 'Прогнозата е реалистична. Бихме желали повече детайли за оптимистичния сценарий.', 'link' => 'databackd.planning.forecasts.index'],
        ['k' => 's11', 'client' => 'i.stoycheva', 'type' => 'analysis', 'title' => 'Анализ на марж по канали за дистрибуция', 'status' => 'review', 'by' => 'm.dimitrova', 'prio' => 'medium', 'from' => '2026-01-01', 'to' => '2026-09-30', 'due' => '2026-10-28', 'created' => '2026-10-01 11:30'],
        ['k' => 's12', 'client' => 'i.stoycheva', 'type' => 'ratio_review', 'title' => 'Преглед на показателите за рентабилност', 'status' => 'review', 'by' => 'b.marinov', 'prio' => 'high', 'from' => '2026-01-01', 'to' => '2026-09-30', 'due' => '2026-10-20', 'created' => '2026-09-30 09:45'],
        ['k' => 's13', 'client' => 'i.stoycheva', 'type' => 'report', 'title' => 'Отчет за разходите по центрове на отговорност', 'status' => 'in_progress', 'by' => 'n.georgiev', 'prio' => 'medium', 'from' => '2026-07-01', 'to' => '2026-09-30', 'due' => '2026-11-06', 'created' => '2026-10-14 10:00'],
        ['k' => 's14', 'client' => 'i.stoycheva', 'type' => 'analysis', 'title' => 'Анализ на ефекта от повишаване на цените', 'status' => 'new', 'by' => null, 'prio' => 'medium', 'from' => '2026-01-01', 'to' => '2026-09-30', 'due' => '2026-11-20', 'created' => '2026-10-25 15:10', 'desc' => 'Планираме повишаване на цените с 4–6% от януари. Моля за анализ на ценовата еластичност и очаквания ефект върху оборота и маржа по продуктови линии.'],
        // Other clients
        ['k' => 's15', 'client' => 't.baneva', 'type' => 'ratio_review', 'title' => 'Преглед на ликвидността и задълженията към доставчици', 'status' => 'new', 'by' => 'e.yankova', 'prio' => 'low', 'from' => '2026-01-01', 'to' => '2026-09-30', 'due' => '2026-11-12', 'created' => '2026-10-22 12:00'],
        ['k' => 's16', 'client' => 'z.radev', 'type' => 'report', 'title' => 'Месечен отчет за себестойността – септември 2026', 'status' => 'in_progress', 'by' => 'v.ninova', 'prio' => 'medium', 'from' => '2026-09-01', 'to' => '2026-09-30', 'due' => '2026-11-02', 'created' => '2026-10-19 09:30'],
    ];

    /** Comments with an attached file: request key => [position, file name, kind, text]. */
    const COMMENT_FILES = [
        's4' => [1, 'cash-inputs-2026-10-balkan.xlsx', 'xlsx', 'Прилагам актуалните данни за очакваните постъпления и плащания през следващите месеци.'],
        's11' => [2, 'draft-margin-channels-2026-10-rodopi.pdf', 'pdf', 'Прикачвам предварителния вариант на анализа за Ваш преглед.'],
        's13' => [1, 'cost-centers-2026-q3-rodopi.xlsx', 'xlsx', 'Качвам списъка с центровете на отговорност и разпределението на общите разходи.'],
    ];

    const STAFF_TEXTS = [
        'start' => [
            'Здравейте, поехме заявката. Ще изготвим първия вариант до няколко работни дни.',
            'Започнахме работа по заявката. Ще Ви уведомим, ако са ни необходими допълнителни данни.',
        ],
        'received' => ['Заявката е получена и разпределена. Ще започнем работа до края на седмицата.'],
        'update' => [
            'Данните са обработени и сверени със счетоводната система. Работим по изводите и препоръките.',
            'Първата част е готова, довършваме анализа на отклоненията.',
        ],
        'review' => ['Готова е предварителната версия на резултата. Моля за Вашия преглед и коментари.'],
        'delivered' => [
            'Окончателният резултат е качен към заявката. Оставаме на разположение за въпроси.',
            'Готово – файлът е наличен за изтегляне. Ако трябват корекции, пишете ни тук.',
        ],
        'answer' => ['Разбира се – добавихме разбивката във файла. Моля, изтеглете го отново.'],
    ];

    const CLIENT_TEXTS = [
        'reply' => [
            'Благодаря! Ако е необходимо, мога да предоставя допълнителни справки от счетоводната система.',
            'Моля да включите и сравнение със същия период на миналата година.',
            'Добре, очакваме първия вариант. Срокът е важен за предстоящото заседание на управителния съвет.',
        ],
        'question' => [
            'Може ли да добавите разбивка по месеци за отделните разходни пера?',
            'Бихте ли добавили и кратко резюме на първата страница за управителя?',
        ],
        'ok' => ['Прегледах резултата – изглежда добре, благодаря за бързата работа.'],
        'thanks' => [
            'Получихме го, благодаря! Ще го използваме на заседанието.',
            'Благодаря за бързата реакция, всичко е точно така, както го очаквахме.',
        ],
    ];

    const MEMOS = [
        'Месечно отчитане и сверка на данните',
        'Работа по финансовия модел',
        'Анализ на отклоненията от бюджета',
        'Подготовка на справки и таблици',
        'Съгласуване с екипа на клиента',
        'Консолидация и контрол на данните',
        'Изготвяне на доклад към управителя',
    ];

    protected int $companyId;
    protected int $adminId;
    protected int $currencyId;
    /** @var array<string,int> email local part => user id */
    protected array $users = [];
    /** @var array<int,string> user id => display name */
    protected array $names = [];
    /** @var array<int,string> client user id => company name */
    protected array $clientCompanies = [];
    /** @var array<string,SpecialistProfile> */
    protected array $profiles = [];
    /** @var array<string,array{id:int,active:?Carbon}> client key => active team request */
    protected array $activeTeams = [];
    /** @var string[] file names stored in the upload folder by this run */
    protected array $storedFiles = [];

    public function run(Company $company)
    {
        $this->companyId = $company->id;
        $this->resolveReferences();

        $this->cleanup();

        DB::transaction(function () {
            $this->profiles();
            $this->teams();
            $this->analysisRequests();
        });

        if ($this->command) {
            $count = fn(string $table, string $where = '') => DB::table($table)->where('company_id', $this->companyId)
                ->when($where !== '', fn($q) => $q->whereRaw($where))->count();

            $this->command->info('Teams demo: ' . $count('specialist_profiles') . ' specialist profiles, '
                . $count('team_requests') . ' team requests (' . $count('team_request_members') . ' members, ' . $count('team_request_status_logs') . ' log rows), '
                . $count('analysis_requests') . ' analysis requests (' . $count('analysis_request_comments') . ' comments, '
                . count($this->storedFiles) . ' files), project ' . self::PROJECT_CODE . ' with '
                . DB::table('project_time_logs')->where('company_id', $this->companyId)->whereIn('project_id', $this->demoProjectIds())->count() . ' time logs.');
        }
    }

    // ---------------------------------------------------------------------------------------------
    // References and cleanup
    // ---------------------------------------------------------------------------------------------

    protected function resolveReferences(): void
    {
        $users = User::withoutGlobalScopes([CompanyScope::class, ActiveScope::class])
            ->where('company_id', $this->companyId)->get(['id', 'name', 'email']);

        foreach ($users as $user) {
            $this->users[explode('@', $user->email)[0]] = (int)$user->id;
            $this->names[(int)$user->id] = $user->name;
        }

        $this->adminId = $this->users['admin'] ?? 0;
        $this->currencyId = (int)Currency::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->companyId)->where('currency_code', 'EUR')->value('id');
        $this->clientCompanies = ClientDetails::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->companyId)->pluck('company_name', 'user_id')->all();

        $keys = array_merge(array_keys(self::SPECIALISTS), array_unique(array_column(self::TEAMS, 'client')), array_unique(array_column(self::REQUESTS, 'client')));

        foreach ($keys as $key) {
            if (!isset($this->users[$key])) {
                throw new \RuntimeException("TeamsDemoSeeder needs the S1 demo user '$key' - run the base demo seeders first.");
            }
        }

        if (!$this->adminId) {
            throw new \RuntimeException('TeamsDemoSeeder needs the demo admin user.');
        }
    }

    protected function demoProjectIds(): array
    {
        return DB::table('projects')->where('company_id', $this->companyId)
            ->where('project_short_code', self::PROJECT_CODE)->pluck('id')->all();
    }

    protected function cleanup(): void
    {
        $id = $this->companyId;

        // Demo files (found through the rows that reference them) + their file_storage records
        $files = AnalysisRequest::withoutGlobalScope(CompanyScope::class)->where('company_id', $id)
            ->whereNotNull('deliverable_file')->pluck('deliverable_file')
            ->merge(AnalysisRequestComment::withoutGlobalScope(CompanyScope::class)->where('company_id', $id)
                ->whereNotNull('file')->pluck('file'))
            ->unique()->values()->all();

        foreach ($files as $file) {
            $path = public_path(Files::UPLOAD_FOLDER . '/' . AnalysisRequest::FILE_FOLDER . '/' . $file);

            if (File::exists($path)) {
                File::delete($path);
            }
        }

        if ($files) {
            FileStorage::withoutGlobalScope(CompanyScope::class)->where('path', AnalysisRequest::FILE_FOLDER)
                ->whereIn('filename', $files)->delete();
        }

        AnalysisRequestComment::withoutGlobalScope(CompanyScope::class)->where('company_id', $id)->delete();
        AnalysisRequest::withoutGlobalScope(CompanyScope::class)->where('company_id', $id)->delete();
        TeamRequestStatusLog::withoutGlobalScope(CompanyScope::class)->where('company_id', $id)->delete();
        TeamRequestMember::withoutGlobalScope(CompanyScope::class)->where('company_id', $id)->delete();
        TeamRequest::withoutGlobalScope(CompanyScope::class)->where('company_id', $id)->delete();
        SpecialistProfile::withoutGlobalScope(CompanyScope::class)->where('company_id', $id)->delete();

        // Demo project of the 5-person team (marker: project_short_code)
        $projectIds = $this->demoProjectIds();

        if ($projectIds) {
            DB::table('project_time_logs')->whereIn('project_id', $projectIds)->delete();
            DB::table('project_members')->whereIn('project_id', $projectIds)->delete();
            DB::table('project_activity')->whereIn('project_id', $projectIds)->delete();
            DB::table('universal_search')->where('company_id', $id)->where('route_name', 'projects.show')
                ->whereIn('searchable_id', $projectIds)->delete();
            DB::table('projects')->whereIn('id', $projectIds)->delete();
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Specialist profiles
    // ---------------------------------------------------------------------------------------------

    protected function profiles(): void
    {
        foreach (self::SPECIALISTS as $key => [$role, $seniority, $rate, $capacity, $languages, $skills, $available]) {
            $profile = new SpecialistProfile();
            $profile->forceFill([
                'company_id' => $this->companyId,
                'user_id' => $this->users[$key],
                'role' => $role,
                'seniority' => $seniority,
                'hourly_rate' => $rate,
                'weekly_capacity_hours' => $capacity,
                'languages' => $languages,
                'skills' => $skills,
                'is_available' => $available,
                'created_at' => '2026-03-02 09:00:00',
                'updated_at' => '2026-03-02 09:00:00',
            ])->save();

            $this->profiles[$key] = $profile;
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Team requests
    // ---------------------------------------------------------------------------------------------

    protected function teams(): void
    {
        $estimator = new PriceEstimator($this->companyId);

        foreach (self::TEAMS as $data) {
            $clientId = $this->users[$data['client']];
            $createdBy = $data['by'] === 'admin' ? $this->adminId : $clientId;
            $lastLog = end($data['log']);

            $team = new TeamRequest();
            $team->forceFill([
                'company_id' => $this->companyId,
                'client_id' => $clientId,
                'title' => $data['title'],
                'team_size' => $data['size'],
                'custom_size' => null,
                'roles' => $data['roles'],
                'engagement' => $data['engagement'],
                'hours_per_week' => $data['hours'],
                'start_date' => $data['start'],
                'duration_months' => $data['months'],
                'description' => $data['description'],
                'status' => $data['status'],
                'monthly_price_estimate' => $estimator->estimate($data['roles'], $data['engagement'], $data['hours']),
                'created_by' => $createdBy,
                'created_at' => $data['created'],
                'updated_at' => $lastLog[1],
            ])->save();

            $previous = null;
            $activeAt = null;

            foreach ($data['log'] as [$to, $at, $who, $note]) {
                $log = new TeamRequestStatusLog();
                $log->forceFill([
                    'company_id' => $this->companyId,
                    'team_request_id' => $team->id,
                    'from_status' => $previous,
                    'to_status' => $to,
                    'note' => $note,
                    'user_id' => $who === 'admin' ? $this->adminId : $clientId,
                    'created_at' => $at,
                ])->save();

                $previous = $to;

                if ($to === 'active') {
                    $activeAt = Carbon::parse($at);
                }
            }

            foreach ($data['members'] as [$key, $role, $hours]) {
                $member = new TeamRequestMember();
                $member->forceFill([
                    'company_id' => $this->companyId,
                    'team_request_id' => $team->id,
                    'specialist_profile_id' => $this->profiles[$key]->id,
                    'role' => $role,
                    'hours_per_week' => $hours,
                    'created_at' => $activeAt?->toDateTimeString() ?? $lastLog[1],
                    'updated_at' => $activeAt?->toDateTimeString() ?? $lastLog[1],
                ])->save();
            }

            if ($data['status'] === 'active') {
                $this->activeTeams[$data['client']] = ['id' => $team->id, 'active' => $activeAt];
            }

            if (!empty($data['project'])) {
                $project = $this->createProject($team, $data);
                $team->forceFill(['project_id' => $project->id])->saveQuietly();
            }
        }
    }

    protected function createProject(TeamRequest $team, array $data): Project
    {
        $project = new Project();
        $project->company_id = $this->companyId;
        $project->project_name = 'Екип за финансов контрол – ' . ($this->clientCompanies[$team->client_id] ?? 'клиент');
        $project->project_short_code = self::PROJECT_CODE;
        $project->project_summary = '<p>Постоянен екип от 5 специалисти за финансов контрол, бюджетиране и консолидация на групата.</p>';
        $project->notes = '<p>Проектът е създаден от заявка за екип. Отработените часове по специалисти се отчитат в модула „Време“.</p>';
        $project->project_admin = $this->users['s.popov'];
        $project->client_id = $team->client_id;
        $project->start_date = $data['start'];
        $project->deadline = Carbon::parse($data['start'])->addMonths($data['months'])->subDay()->toDateString();
        $project->status = 'in progress';
        $project->project_budget = round((float)$team->monthly_price_estimate * $data['months'], 2);
        $project->hours_allocated = (float)array_sum(array_column($data['members'], 2)) * 4.33 * $data['months'];
        $project->currency_id = $this->currencyId;
        $project->completion_percent = 55;
        $project->calculate_task_progress = 'project_total_time';
        $project->manual_timelog = 'enable';
        $project->client_view_task = 'enable';
        $project->allow_client_notification = 'disable';
        $project->public = 0;
        $project->added_by = $this->adminId;
        $project->save();

        $activity = new ProjectActivity();
        $activity->project_id = $project->id;
        $activity->activity = $project->project_name . ' е добавен като нов проект.';
        $activity->save();

        $search = new UniversalSearch();
        $search->searchable_id = $project->id;
        $search->company_id = $this->companyId;
        $search->title = $project->project_name;
        $search->route_name = 'projects.show';
        $search->save();

        foreach ($data['members'] as $index => [$key, $role, $hours]) {
            $member = new ProjectMember();
            $member->user_id = $this->users[$key];
            $member->project_id = $project->id;
            $member->hourly_rate = self::SPECIALISTS[$key][2];
            $member->added_by = $this->adminId;
            $member->last_updated_by = $this->adminId;
            $member->save();
        }

        $this->timeLogs($project, $data['members']);

        return $project;
    }

    /** Daily time logs of the assigned specialists for September and October 2026 (a few days off per person). */
    protected function timeLogs(Project $project, array $members): void
    {
        $until = Carbon::parse(self::TODAY)->subDay()->startOfDay();
        $rows = [];

        foreach ($members as $index => [$key, $role, $weeklyHours]) {
            $rate = (int)round(self::SPECIALISTS[$key][2]);
            $minutes = (int)(round($weeklyHours / 5 * 4) * 15); // hours per day rounded to 15 minutes
            $day = Carbon::parse('2026-09-01');
            $n = 0;

            while ($day->lte($until)) {
                $n++;

                // weekends, Independence Day (22.09) and one off day in about nine
                if (!$day->isWeekend() && $day->format('m-d') !== '09-22' && ($n + $index * 2) % 9 !== 0) {
                    $start = $day->copy()->setTime(8 + ($index % 2), $index * 15 % 60);
                    $rows[] = [
                        'company_id' => $this->companyId,
                        'project_id' => $project->id,
                        'task_id' => null,
                        'user_id' => $this->users[$key],
                        'start_time' => $start->toDateTimeString(),
                        'end_time' => $start->copy()->addMinutes($minutes)->toDateTimeString(),
                        'memo' => self::MEMOS[($n + $index) % count(self::MEMOS)],
                        'total_hours' => (string)intdiv($minutes, 60),
                        'total_minutes' => (string)$minutes,
                        'total_break_minutes' => '0',
                        'hourly_rate' => $rate,
                        'earnings' => round($minutes / 60 * $rate, 2),
                        'approved' => 1,
                        'added_by' => $this->adminId,
                        'created_at' => $start->copy()->addMinutes($minutes)->toDateTimeString(),
                        'updated_at' => $start->copy()->addMinutes($minutes)->toDateTimeString(),
                    ];
                }

                $day->addDay();
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('project_time_logs')->insert($chunk);
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Analysis requests
    // ---------------------------------------------------------------------------------------------

    protected function analysisRequests(): void
    {
        File::ensureDirectoryExists(storage_path('app/' . self::SOURCE_DIR));
        Files::createDirectoryIfNotExist(AnalysisRequest::FILE_FOLDER);

        $now = Carbon::parse(self::NOW);
        $ids = [];
        $usedNames = [];

        foreach (self::REQUESTS as $index => $r) {
            $clientId = $this->users[$r['client']];
            $assigneeId = $r['by'] ? $this->users[$r['by']] : null;
            $created = Carbon::parse($r['created']);
            $delivered = isset($r['delivered']) ? Carbon::parse($r['delivered']) : null;
            $status = $r['status'];

            // Team request of the client that was already active when the request was made
            $team = $this->activeTeams[$r['client']] ?? null;
            $teamId = $team && $team['active'] && $created->gte($team['active']) ? $team['id'] : null;

            // Time of the last activity on the request
            if ($delivered) {
                $end = $delivered->copy();
            } else {
                $days = ['new' => 1, 'in_progress' => 6, 'review' => 8][$status] ?? 3;
                $end = $created->copy()->addDays($days);

                if ($end->gt($now)) {
                    $end = $now->copy();
                }
            }

            // Deliverable
            $file = null;

            if (isset($r['file'])) {
                $file = $this->uniqueName(self::TYPE_SLUG[$r['type']] . '-' . substr($r['to'], 0, 7) . '-' . self::CLIENT_SLUG[$r['client']], $r['file'], $usedNames);
                $this->storeFile($file, $r['file'], $r, $assigneeId);
            }

            $link = null;

            if (!empty($r['link']) && Route::has($r['link'])) {
                $link = route($r['link'], [], false);
            }

            $comments = $this->buildComments($index, $r, $created, $end, $delivered, $clientId, $assigneeId);
            $lastComment = $comments ? end($comments)['at'] : $created;

            if ($status === 'closed') {
                $updated = $delivered->copy()->addDays(2);
            } elseif ($delivered) {
                $updated = $lastComment->gt($delivered) ? $lastComment->copy() : $delivered->copy();
            } else {
                $updated = $lastComment->copy();
            }

            if ($updated->gt($now)) {
                $updated = $now->copy();
            }

            $request = new AnalysisRequest();
            $request->forceFill([
                'company_id' => $this->companyId,
                'team_request_id' => $teamId,
                'client_id' => $clientId,
                'type' => $r['type'],
                'title' => $r['title'],
                'description' => $r['desc'] ?? self::DESCRIPTIONS[$r['type']],
                'period_from' => $r['from'],
                'period_to' => $r['to'],
                'due_date' => $r['due'],
                'priority' => $r['prio'],
                'recurrence' => $r['rec'] ?? 'none',
                'parent_id' => !empty($r['parent']) ? $ids[$r['parent']] : null,
                'status' => $status,
                'assigned_to' => $assigneeId,
                'deliverable_file' => $file,
                'deliverable_link' => $link,
                'client_rating' => $r['rating'] ?? null,
                'client_feedback' => $r['feedback'] ?? null,
                'delivered_at' => $delivered?->toDateTimeString(),
                // follow-up instances of a recurring request are created by the system (admin)
                'created_by' => !empty($r['parent']) ? $this->adminId : $clientId,
                'created_at' => $created->toDateTimeString(),
                'updated_at' => $updated->toDateTimeString(),
            ])->save();

            $ids[$r['k']] = $request->id;

            foreach ($comments as $c) {
                $comment = new AnalysisRequestComment();
                $comment->forceFill([
                    'company_id' => $this->companyId,
                    'analysis_request_id' => $request->id,
                    'user_id' => $c['user'],
                    'comment' => $c['text'],
                    'file' => $c['file'],
                    'created_at' => $c['at']->toDateTimeString(),
                    'updated_at' => $c['at']->toDateTimeString(),
                ])->save();
            }
        }
    }

    /**
     * 0-4 comments, staff and client alternating (staff first), spread between creation and the last activity.
     *
     * @return array<int,array{user:int,text:string,file:?string,at:Carbon}>
     */
    protected function buildComments(int $index, array $r, Carbon $created, Carbon $end, ?Carbon $delivered, int $clientId, ?int $assigneeId): array
    {
        $staffId = $assigneeId ?? $this->adminId;
        $pick = fn(array $pool) => $pool[$index % count($pool)];
        $s = self::STAFF_TEXTS;
        $c = self::CLIENT_TEXTS;
        $extra = $index % 2 === 0;

        switch ($r['status']) {
            case 'new':
                $seq = $assigneeId ? [$pick($s['received'])] : [];
                break;
            case 'in_progress':
                $seq = [$pick($s['start']), $pick($c['reply'])];
                if ($extra) {
                    $seq[] = $pick($s['update']);
                }
                break;
            case 'review':
                $seq = [$pick($s['start']), $pick($c['reply']), $pick($s['review'])];
                if ($extra) {
                    $seq[] = $pick($c['ok']);
                }
                break;
            default: // delivered, closed
                $seq = $extra
                    ? [$pick($s['start']), $pick($c['question']), $pick($s['answer']) . ' ' . $pick($s['delivered']), $pick($c['thanks'])]
                    : [$pick($s['start']), $pick($c['reply']), $pick($s['delivered'])];
        }

        $n = count($seq);
        $span = max(60, $end->diffInMinutes($created, true));
        $comments = [];

        foreach ($seq as $i => $text) {
            $isClient = $i % 2 === 1;

            if ($delivered && $i >= 2) {
                // the delivery comment comes right after delivered_at, the closing thanks a few hours later
                $at = $delivered->copy()->addMinutes($i === 2 ? 5 : 190);
            } else {
                $fraction = $delivered ? [0.15, 0.45][$i] : ($i + 1) / ($n + 1);
                $at = $created->copy()->addMinutes((int)round($span * $fraction));
            }

            if ($at->gt(Carbon::parse(self::NOW))) {
                $at = Carbon::parse(self::NOW)->subMinutes(10 * ($n - $i));
            }

            $file = null;

            if (isset(self::COMMENT_FILES[$r['k']]) && self::COMMENT_FILES[$r['k']][0] === $i) {
                [, $fileName, $kind, $fileText] = self::COMMENT_FILES[$r['k']];
                $text = $fileText;
                $file = $fileName;
                $this->storeFile($fileName, $kind, $r, $isClient ? null : $assigneeId, $isClient);
            }

            $comments[] = ['user' => $isClient ? $clientId : $staffId, 'text' => $text, 'file' => $file, 'at' => $at];
        }

        return $comments;
    }

    // ---------------------------------------------------------------------------------------------
    // Sample files
    // ---------------------------------------------------------------------------------------------

    protected function uniqueName(string $base, string $ext, array &$used): string
    {
        $name = $base . '.' . $ext;
        $n = 2;

        while (isset($used[$name])) {
            $name = $base . '-' . $n++ . '.' . $ext;
        }

        $used[$name] = true;

        return $name;
    }

    /** Generates the sample file in storage/app/databackd-demo/teams and copies it to the AFV upload folder. */
    protected function storeFile(string $name, string $kind, array $r, ?int $authorId, bool $fromClient = false): void
    {
        $source = storage_path('app/' . self::SOURCE_DIR . '/' . $name);
        $author = $fromClient ? ($this->clientCompanies[$this->users[$r['client']]] ?? '') : ($this->names[$authorId ?? $this->adminId] ?? '');
        [$headers, $rows] = $this->reportTable($r['k'] . $name, $r);

        if ($kind === 'pdf') {
            File::put($source, $this->pdf($r, $author, $headers, $rows, $fromClient));
        } else {
            File::put($source, $this->xlsx($headers, $rows));
        }

        $target = public_path(Files::UPLOAD_FOLDER . '/' . AnalysisRequest::FILE_FOLDER . '/' . $name);
        File::copy($source, $target);
        Files::saveFileInfo($name, AnalysisRequest::FILE_FOLDER, $this->companyId);
        $this->storedFiles[] = $name;
    }

    /** Plausible figures: monthly rows (multi-month periods) or P&L lines (single month). */
    protected function reportTable(string $seed, array $r): array
    {
        $base = self::BASE_REVENUE[$r['client']] ?? 100000;
        $rnd = fn(int $i) => (crc32($seed . '#' . $i) % 1000) / 1000;
        $from = Carbon::parse($r['from'])->startOfMonth();
        $to = Carbon::parse($r['to'])->startOfMonth();

        if ($from->equalTo($to)) {
            $revenue = (int)round($base * (1 + $rnd(0) * 0.08), -2);
            $lines = [
                ['Приходи от продажби', $revenue],
                ['Себестойност на продажбите', (int)round($revenue * (0.40 + $rnd(1) * 0.04), -2)],
                ['Разходи за персонал', (int)round($revenue * (0.20 + $rnd(2) * 0.03), -2)],
                ['Външни услуги', (int)round($revenue * (0.08 + $rnd(3) * 0.02), -2)],
                ['Амортизации и други', (int)round($revenue * (0.03 + $rnd(4) * 0.01), -2)],
            ];
            $rows = [];
            $net = [0, 0];

            foreach ($lines as $i => [$label, $actual]) {
                $budget = (int)round($actual * (0.94 + $rnd(10 + $i) * 0.12), -2);
                $rows[] = [$label, $actual, $budget, $actual - $budget];
                $sign = $i === 0 ? 1 : -1;
                $net[0] += $sign * $actual;
                $net[1] += $sign * $budget;
            }

            $rows[] = ['Нетен резултат', $net[0], $net[1], $net[0] - $net[1]];

            return [['Позиция', 'Факт (EUR)', 'Бюджет (EUR)', 'Отклонение (EUR)'], $rows];
        }

        // multi-month period: at most the last 6 months
        $months = [];
        for ($m = $to->copy(); $m->gte($from) && count($months) < 6; $m->subMonth()) {
            array_unshift($months, $m->copy());
        }

        $rows = [];
        $trend = 0;

        foreach ($months as $i => $month) {
            $trend += 0.012 + $rnd($i) * 0.01;
            $revenue = (int)round($base * (1 + $trend + ($rnd(20 + $i) - 0.5) * 0.06), -2);
            $expenses = (int)round($revenue * (0.76 + $rnd(40 + $i) * 0.08), -2);
            $result = $revenue - $expenses;
            $rows[] = [$month->format('m.Y'), $revenue, $expenses, $result, round($result / $revenue * 100, 1)];
        }

        return [['Месец', 'Приходи (EUR)', 'Разходи (EUR)', 'Резултат (EUR)', 'Марж (%)'], $rows];
    }

    protected function money($value): string
    {
        return is_float($value) && floor($value) != $value ? number_format($value, 1, ',', ' ') : number_format($value, 0, ',', ' ');
    }

    protected function pdf(array $r, string $author, array $headers, array $rows, bool $fromClient): string
    {
        $esc = fn(string $v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $client = $this->clientCompanies[$this->users[$r['client']]] ?? '';
        $period = Carbon::parse($r['from'])->format('d.m.Y') . ' – ' . Carbon::parse($r['to'])->format('d.m.Y');
        $authorLabel = $fromClient ? 'Предоставил' : 'Изготвил';

        $head = '';
        foreach ($headers as $i => $h) {
            $head .= '<th>' . $esc($h) . '</th>';
        }

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach ($row as $i => $cell) {
                $body .= '<td>' . ($i === 0 ? $esc((string)$cell) : $this->money($cell)) . '</td>';
            }
            $body .= '</tr>';
        }

        // summary bullets from the table
        $bullets = [];
        if (count($headers) === 5) {
            $revenue = array_sum(array_column($rows, 1));
            $expenses = array_sum(array_column($rows, 2));
            $bullets[] = 'Общо приходи за периода: ' . $this->money($revenue) . ' EUR.';
            $bullets[] = 'Общо разходи за периода: ' . $this->money($expenses) . ' EUR.';
            $bullets[] = 'Резултат: ' . $this->money($revenue - $expenses) . ' EUR (марж ' . $this->money(round(($revenue - $expenses) / max(1, $revenue) * 100, 1)) . '%).';
        } else {
            $last = end($rows);
            $bullets[] = 'Приходи за месеца: ' . $this->money($rows[0][1]) . ' EUR.';
            $bullets[] = 'Нетен резултат: ' . $this->money($last[1]) . ' EUR при бюджет ' . $this->money($last[2]) . ' EUR.';
        }
        $bullets[] = 'Препоръка: да се следят разходите за външни услуги и срокът за събиране на вземанията.';

        $li = '';
        foreach ($bullets as $b) {
            $li .= '<li>' . $esc($b) . '</li>';
        }

        $html = '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:"DejaVu Sans",sans-serif;font-size:11px;color:#222}'
            . 'h1{font-size:18px;color:#1d4e89;margin-bottom:4px}h3{font-size:13px;margin-top:18px}'
            . '.muted{color:#777}table{border-collapse:collapse;width:100%;margin-top:8px}'
            . 'th,td{border:1px solid #ccd3dc;padding:5px 7px;text-align:right}th{background:#eef2f7}'
            . 'td:first-child,th:first-child{text-align:left}li{margin-bottom:4px}'
            . '</style></head><body>'
            . '<h1>' . $esc($r['title']) . '</h1>'
            . '<p class="muted">Клиент: ' . $esc($client) . ' &nbsp;|&nbsp; Период: ' . $esc($period) . ' &nbsp;|&nbsp; ' . $authorLabel . ': ' . $esc($author) . '</p>'
            . '<h3>Резюме</h3><ul>' . $li . '</ul>'
            . '<h3>Данни</h3><table><tr>' . $head . '</tr>' . $body . '</table>'
            . '<p class="muted" style="margin-top:24px">Демо Финанс Груп – документ с примерни данни за демонстрационни цели.</p>'
            . '</body></html>';

        return Pdf::loadHTML($html)->setPaper('a4')->setOption(['enable_font_subsetting' => true])->output();
    }

    protected function xlsx(array $headers, array $rows): string
    {
        $export = new class($headers, $rows) implements FromArray, WithHeadings, WithTitle, ShouldAutoSize {

            public function __construct(protected array $headings, protected array $rows)
            {
            }

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }

            public function title(): string
            {
                return 'Данни';
            }

        };

        return Excel::raw($export, ExcelWriter::XLSX);
    }

}
