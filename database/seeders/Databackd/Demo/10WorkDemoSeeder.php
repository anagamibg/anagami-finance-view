<?php

namespace Database\Seeders\Databackd\Demo;

use App\Models\ClientDetails;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractSign;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectCategory;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\ProjectTimeLog;
use App\Models\Task;
use App\Models\TaskboardColumn;
use App\Models\TaskUser;
use App\Models\UniversalSearch;
use App\Models\User;
use App\Scopes\ActiveScope;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * AFV demo: contracts, projects, tasks and time logs (all in Bulgarian).
 * Idempotent: contracts are identified by contract_number, projects by project_short_code;
 * tasks / time logs / members / milestones are only created for projects that have none yet.
 */
class WorkDemoSeeder extends Seeder
{

    /** "Today" of the demo period (May 2025 - October 2026). */
    const TODAY = '2026-10-27';

    const CONTRACT_TYPES = [
        'Договор за счетоводно обслужване',
        'Договор за финансов анализ',
        'Рамков договор за услуги',
        'Договор за консултации',
        'Лицензионен договор',
    ];

    /** number, client company, type, subject, amount EUR, start, end, signed */
    const CONTRACTS = [
        ['ДФГ-2025-001', 'Балкан Трейд ЕООД', 0, 'Месечно счетоводно обслужване на фирма - 12 месеца', 3000, '2025-05-01', '2026-04-30', true],
        ['ДФГ-2025-002', 'Родопи Фуудс АД', 1, 'Месечен финансов анализ и управленски отчети', 5760, '2025-05-15', '2026-05-14', true],
        ['ДФГ-2025-003', 'Черно Море Логистик ООД', 2, 'Рамков договор за счетоводни и аналитични услуги', 8400, '2025-06-01', '2026-10-31', true],
        ['ДФГ-2025-004', 'Старопланински Мебели ЕООД', 0, 'Счетоводно обслужване и изготвяне на годишен финансов отчет', 1440, '2025-06-10', '2026-06-09', true],
        ['ДФГ-2025-005', 'Витоша Софтуер ООД', 4, 'Лиценз за платформата Anagami Finance View - 10 потребителя', 7080, '2025-07-01', '2026-10-31', true],
        ['ДФГ-2025-006', 'Тракия Инженеринг АД', 3, 'Консултации по бюджетиране и данъчно планиране', 6500, '2025-09-01', '2026-08-31', true],
        ['ДФГ-2025-007', 'Приморско Строй ЕООД', 0, 'Месечно счетоводно обслужване - строителна дейност', 3000, '2025-10-01', '2026-09-30', true],
        ['ДФГ-2025-008', 'Свети Георги Медика ЕООД', 1, 'Анализ на финансови коефициенти и парични потоци', 3600, '2025-11-15', '2026-10-31', true],
        ['ДФГ-2026-001', 'Нова Енергия България ООД', 2, 'Рамков договор за финансов анализ и отчетност', 12000, '2026-01-10', '2026-10-31', true],
        ['ДФГ-2026-002', 'Пирин Турист АД', 1, 'Сезонен финансов анализ на хотелски комплекс', 4200, '2026-03-01', '2026-10-31', false],
        ['ДФГ-2026-003', 'Дигитал Хъб Консулт ЕООД', 4, 'Лиценз за платформата Anagami Finance View - Бизнес', 3540, '2026-06-01', '2026-10-31', false],
        ['ДФГ-2026-004', 'Македония Фарма ЕООД', 3, 'Консултации по финансово планиране на фармацевтична верига', 2700, '2026-09-01', '2026-10-31', false],
    ];

    const CATEGORIES = ['Финансова отчетност', 'Бюджетиране и прогнози', 'Консолидация', 'Одит и оптимизация на разходи'];

    /** short code, name, client company, category index, start, deadline, status, budget, hours, member emails (local part), summary */
    const PROJECTS = [
        ['BTR', 'Внедряване на финансова отчетност – Балкан Трейд', 'Балкан Трейд ЕООД', 0, '2025-05-12', '2025-11-28', 'finished', 14500, 320,
            ['g.petrov', 'm.dimitrova', 'n.georgiev', 'd.atanasova', 'e.yankova'],
            'Изграждане на цялостна система за месечна финансова отчетност: сметкоплан, ОПР, баланс и ключови показатели за управителя.'],
        ['VSG', 'Консолидирана отчетност на групата – Витоша Софтуер', 'Витоша Софтуер ООД', 2, '2025-08-04', '2026-10-30', 'in progress', 21000, 480,
            ['g.petrov', 'b.marinov', 'e.yankova', 's.popov', 'm.dimitrova', 'n.georgiev'],
            'Консолидиране на финансовите отчети на три дружества от групата, елиминиране на вътрешногрупови сделки и общ управленски отчет.'],
        ['TBP', 'Бюджетиране и прогнозиране 2026 – Тракия Инженеринг', 'Тракия Инженеринг АД', 1, '2025-10-06', '2026-09-30', 'in progress', 12800, 260,
            ['m.dimitrova', 'b.marinov', 'e.yankova', 'd.atanasova'],
            'Годишен бюджет по центрове на разходи, тримесечни прогнози и мониторинг на отклоненията между бюджет и реално изпълнение.'],
        ['NEO', 'Финансов одит и оптимизация на разходите – Нова Енергия България', 'Нова Енергия България ООД', 3, '2026-03-02', '2026-10-30', 'on hold', 9600, 180,
            ['g.petrov', 'b.marinov', 'n.georgiev', 'v.ninova'],
            'Преглед на разходната структура, идентифициране на възможности за икономии и план за оптимизация на оперативните разходи.'],
    ];

    /** Task headings per project (index = PROJECTS index). */
    const TASKS = [
        [
            'Събиране на счетоводни данни и оборотни ведомости',
            'Изготвяне на сметкоплан по НСС',
            'Настройка на разрезите: регион, вид клиент, продуктова група',
            'Импорт на историческите данни от Excel',
            'Съгласуване на данните с главния счетоводител',
            'Изготвяне на отчет за приходите и разходите (ОПР)',
            'Изготвяне на баланс и паричен поток',
            'Изчисляване на ключови финансови коефициенти',
            'Табло за управителя - основни показатели',
            'Обучение на екипа на клиента',
            'Тестване на отчетите и коригиране на несъответствия',
            'Приемане на проекта и финален доклад',
        ],
        [
            'Определяне на обхвата на консолидацията',
            'Съпоставяне на сметкоплановете на дружествата',
            'Събиране на отчетите на „Витоша Софтуер АД“',
            'Събиране на отчетите на дъщерните дружества',
            'Идентифициране на вътрешногрупови сделки',
            'Елиминиране на вътрешногрупови приходи и разходи',
            'Изчисляване на малцинствено участие (70%)',
            'Консолидиран отчет за приходите и разходите',
            'Консолидиран баланс към края на тримесечието',
            'Сравнителен анализ по дружества',
            'Автоматизиране на месечната консолидация',
            'Ревю на консолидираните отчети с управителя',
            'Документиране на методологията',
        ],
        [
            'Анализ на изпълнението на бюджета за 2025 г.',
            'Събиране на входни данни от ръководителите на центрове',
            'Бюджет на приходите по продуктови групи',
            'Бюджет на разходите по центрове на разходи',
            'Бюджет на заплатите и осигуровките',
            'Прогноза за паричния поток за 12 месеца',
            'Сценариен анализ: оптимистичен и песимистичен',
            'Съгласуване на бюджета с ръководството',
            'Тримесечна актуализация на прогнозата - Q1',
            'Тримесечна актуализация на прогнозата - Q2',
            'Тримесечна актуализация на прогнозата - Q3',
            'Отчет за отклоненията: бюджет спрямо реално',
            'Препоръки за корекции на бюджета',
        ],
        [
            'Преглед на структурата на разходите за 2025 г.',
            'Анализ на разходите за външни услуги',
            'Сравнение с отрасловите показатели',
            'Идентифициране на дублиращи се договори',
            'Анализ на разходите за материали и енергия',
            'Предложения за преговори с доставчици',
            'План за оптимизация на оперативните разходи',
            'Изчисляване на очакваните икономии',
            'Междинен доклад към управителя',
        ],
    ];

    const OUTCOMES = [
        'Резултатът се предоставя на клиента в електронен вид и се съгласува преди финализиране.',
        'Данните са проверени спрямо първичните счетоводни документи.',
        'Работата се извършва по утвърдената методология на „Демо Финанс Груп“.',
        'Изготвя се кратко резюме с изводи и препоръки за управителя.',
        'Резултатът се качва в платформата и е достъпен за екипа на клиента.',
    ];

    const MEMOS = [
        'Подготовка и обработка на данните',
        'Работа по финансовия модел',
        'Сверка със счетоводните данни',
        'Анализ на отклоненията',
        'Изготвяне на справки и таблици',
        'Съгласуване с клиента',
        'Преглед и корекции',
        'Изготвяне на доклад',
        'Среща с екипа на клиента',
        'Настройка на показателите',
    ];

    protected int $companyId;
    protected int $adminId;
    protected int $currencyId;
    protected array $clients = [];   // company_name => user_id
    protected array $employees = []; // email local part => [id, hourly_rate]
    protected array $boards = [];    // slug => id

    public function run(Company $company)
    {
        $this->companyId = $company->id;
        $this->resolveReferences();

        $this->contracts();
        $this->projects();
    }

    protected function resolveReferences(): void
    {
        $this->adminId = (int)User::withoutGlobalScopes([CompanyScope::class, ActiveScope::class])
            ->where('company_id', $this->companyId)->where('email', 'admin@demofinans.bg')->value('id');

        $this->currencyId = (int)Currency::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->companyId)->where('currency_code', 'EUR')->value('id');

        $this->clients = ClientDetails::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->companyId)->pluck('user_id', 'company_name')->all();

        $users = User::withoutGlobalScopes([CompanyScope::class, ActiveScope::class])
            ->join('employee_details', 'employee_details.user_id', '=', 'users.id')
            ->where('users.company_id', $this->companyId)
            ->select('users.id', 'users.email', 'employee_details.hourly_rate')
            ->get();

        foreach ($users as $user) {
            $this->employees[explode('@', $user->email)[0]] = [(int)$user->id, (float)$user->hourly_rate];
        }

        $this->boards = TaskboardColumn::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->companyId)->pluck('id', 'slug')->all();
    }

    protected function contracts(): void
    {
        $typeIds = [];

        foreach (self::CONTRACT_TYPES as $name) {
            $type = ContractType::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $this->companyId)->where('name', $name)->first();

            if (!$type) {
                $type = new ContractType();
                $type->company_id = $this->companyId;
                $type->name = $name;
                $type->save();
            }

            $typeIds[] = $type->id;
        }

        foreach (self::CONTRACTS as [$number, $clientName, $typeIndex, $subject, $amount, $start, $end, $signed]) {
            $exists = Contract::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $this->companyId)->where('contract_number', $number)->exists();

            if ($exists || !isset($this->clients[$clientName])) {
                continue;
            }

            $contract = new Contract();
            $contract->company_id = $this->companyId;
            $contract->contract_number = $number;
            $contract->client_id = $this->clients[$clientName];
            $contract->contract_type_id = $typeIds[$typeIndex];
            $contract->subject = $subject;
            $contract->contract_name = $subject;
            $contract->amount = $amount;
            $contract->original_amount = $amount;
            $contract->start_date = $start;
            $contract->original_start_date = $start;
            $contract->end_date = $end;
            $contract->original_end_date = $end;
            $contract->currency_id = $this->currencyId;
            $contract->added_by = $this->adminId;
            $contract->sign_date = $start;
            $contract->description = 'Договор между „Демо Финанс Груп“ АД и ' . $clientName . ' с предмет: ' . mb_strtolower(mb_substr($subject, 0, 1)) . mb_substr($subject, 1) . '.';
            $contract->contract_detail = '<h3>Предмет на договора</h3><p>ИЗПЪЛНИТЕЛЯТ се задължава да предоставя на ВЪЗЛОЖИТЕЛЯ услугите по настоящия договор: '
                . e($subject) . '.</p><h3>Цена и плащане</h3><p>Общата стойност на договора е ' . number_format($amount, 2, ',', ' ')
                . ' EUR без ДДС. Плащанията се извършват по банков път в срок до 10 дни от издаването на фактура.</p><h3>Срок</h3><p>Договорът влиза в сила на '
                . Carbon::parse($start)->format('d.m.Y') . ' г. и е със срок на действие до ' . Carbon::parse($end)->format('d.m.Y')
                . ' г.</p><h3>Поверителност</h3><p>Страните се задължават да не разкриват информация, получена във връзка с изпълнението на договора.</p>';
            $contract->save();

            if ($signed) {
                $client = User::withoutGlobalScopes([CompanyScope::class, ActiveScope::class])->find($contract->client_id);

                $sign = new ContractSign();
                $sign->company_id = $this->companyId;
                $sign->contract_id = $contract->id;
                $sign->full_name = $client->name;
                $sign->email = $client->email;
                $sign->place = 'София';
                $sign->date = Carbon::parse($start)->toDateTimeString();
                $sign->signature = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='120' height='40'><path d='M5 30C20 5 30 40 50 20S90 10 115 25' stroke='navy' fill='none'/></svg>";
                $sign->save();
            }
        }
    }

    protected function projects(): void
    {
        $categoryIds = [];

        foreach (self::CATEGORIES as $name) {
            $category = ProjectCategory::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $this->companyId)->where('category_name', $name)->first();

            if (!$category) {
                $category = new ProjectCategory();
                $category->company_id = $this->companyId;
                $category->category_name = $name;
                $category->save();
            }

            $categoryIds[] = $category->id;
        }

        foreach (self::PROJECTS as $index => [$code, $name, $clientName, $catIndex, $start, $deadline, $status, $budget, $hours, $memberKeys, $summary]) {
            if (!isset($this->clients[$clientName])) {
                continue;
            }

            $project = Project::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $this->companyId)->where('project_short_code', $code)->first();

            if (!$project) {
                $project = new Project();
                $project->company_id = $this->companyId;
                $project->project_name = $name;
                $project->project_short_code = $code;
                $project->project_summary = '<p>' . $summary . '</p>';
                $project->notes = '<p>Проектът се изпълнява от екипа на „Демо Финанс Груп“ по месечен график с отчитане на времето.</p>';
                $project->project_admin = $this->adminId;
                $project->client_id = $this->clients[$clientName];
                $project->category_id = $categoryIds[$catIndex];
                $project->start_date = $start;
                $project->deadline = $deadline;
                $project->status = $status;
                $project->project_budget = $budget;
                $project->hours_allocated = $hours;
                $project->currency_id = $this->currencyId;
                $project->completion_percent = $status === 'finished' ? 100 : 0;
                $project->calculate_task_progress = 'task_completion';
                $project->manual_timelog = 'enable';
                $project->client_view_task = 'enable';
                $project->allow_client_notification = 'disable';
                $project->public = 0;
                $project->added_by = $this->adminId;
                $project->save();

                $this->activity($project->id, $project->project_name . ' е добавен като нов проект.');

                $search = new UniversalSearch();
                $search->searchable_id = $project->id;
                $search->company_id = $this->companyId;
                $search->title = $project->project_name;
                $search->route_name = 'projects.show';
                $search->save();
            }

            $this->members($project, $memberKeys);
            $this->milestones($project, $start, $deadline, $status);

            if (!Task::withoutGlobalScope(CompanyScope::class)->where('project_id', $project->id)->exists()) {
                $this->tasks($project, $index, $memberKeys);
            }

            if (!ProjectTimeLog::withoutGlobalScope(CompanyScope::class)->where('project_id', $project->id)->exists()) {
                $this->timeLogs($project);
            }

            $this->updateProgress($project);
        }
    }

    protected function members(Project $project, array $memberKeys): void
    {
        if (ProjectMember::where('project_id', $project->id)->exists()) {
            return;
        }

        foreach ($memberKeys as $key) {
            if (!isset($this->employees[$key])) {
                continue;
            }

            $member = new ProjectMember();
            $member->user_id = $this->employees[$key][0];
            $member->project_id = $project->id;
            $member->hourly_rate = $this->employees[$key][1];
            $member->added_by = $this->adminId;
            $member->last_updated_by = $this->adminId;
            $member->save();
        }

        $this->activity($project->id, 'Нови членове са добавени към проекта.');
    }

    protected function milestones(Project $project, string $start, string $deadline, string $status): void
    {
        if (ProjectMilestone::withoutGlobalScope(CompanyScope::class)->where('project_id', $project->id)->exists()) {
            return;
        }

        $titles = [
            ['Старт на проекта и събиране на данни', 'Определяне на обхвата, достъпи и първоначално събиране на счетоводните данни.'],
            ['Изграждане на модела и първи отчети', 'Настройка на разрезите и подготовка на първите работни версии на отчетите.'],
            ['Приключване и предаване на резултатите', 'Финални отчети, обучение на екипа на клиента и предаване на документацията.'],
        ];

        $from = Carbon::parse($start);
        $span = max(30, $from->diffInDays(Carbon::parse($deadline)));
        $today = Carbon::parse(self::TODAY);

        foreach ($titles as $i => [$title, $text]) {
            $mStart = $from->copy()->addDays((int)($span * $i / 3));
            $mEnd = $from->copy()->addDays((int)($span * ($i + 1) / 3) - 1);

            $milestone = new ProjectMilestone();
            $milestone->company_id = $this->companyId;
            $milestone->project_id = $project->id;
            $milestone->currency_id = $this->currencyId;
            $milestone->milestone_title = $title;
            $milestone->summary = $text;
            $milestone->start_date = $mStart->toDateString();
            $milestone->end_date = $mEnd->toDateString();
            $milestone->status = ($status === 'finished' || $mEnd->lt($today)) ? 'complete' : 'incomplete';
            $milestone->cost = round($project->project_budget / 3, 2);
            $milestone->save();
        }
    }

    protected function tasks(Project $project, int $projectIndex, array $memberKeys): void
    {
        $today = Carbon::parse(self::TODAY);
        $headings = self::TASKS[$projectIndex];
        $count = count($headings);
        $start = Carbon::parse($project->start_date);
        $deadline = Carbon::parse($project->deadline);
        $window = max(20, $start->diffInDays($deadline) - 14);
        $assignees = array_values(array_filter(array_map(fn($k) => $this->employees[$k][0] ?? null, $memberKeys)));
        $priorities = ['high', 'medium', 'medium', 'low'];

        foreach ($headings as $i => $heading) {
            $taskStart = $start->copy()->addDays((int)($window * $i / $count));
            $taskDue = $taskStart->copy()->addDays(mt_rand(7, 24));

            if ($taskDue->gt($deadline)) {
                $taskDue = $deadline->copy();
            }

            if ($project->status === 'finished' || ($taskDue->lt($today) && mt_rand(1, 100) <= 85)) {
                $slug = 'completed';
            }
            elseif ($taskStart->gt($today)) {
                $slug = $project->status === 'on hold' ? 'incomplete' : 'to_do';
            }
            elseif ($project->status === 'on hold') {
                $slug = mt_rand(0, 1) ? 'incomplete' : 'doing';
            }
            else {
                $slug = ['doing', 'doing', 'to_do', 'incomplete'][mt_rand(0, 3)];
            }

            $done = $slug === 'completed';

            $task = new Task();
            $task->company_id = $this->companyId;
            $task->project_id = $project->id;
            $task->heading = $heading;
            $task->description = '<p>' . e($heading) . '. ' . self::OUTCOMES[$i % count(self::OUTCOMES)] . '</p>';
            $task->start_date = $taskStart->toDateTimeString();
            $task->due_date = $taskDue->toDateTimeString();
            $task->priority = $priorities[$i % 4];
            $task->status = $done ? 'completed' : 'incomplete';
            $task->board_column_id = $this->boards[$slug];
            $task->column_priority = $i + 1;
            $task->completed_on = $done ? $taskDue->copy()->subDays(mt_rand(0, 3))->toDateTimeString() : null;
            $task->estimate_hours = mt_rand(6, 40);
            $task->estimate_minutes = 0;
            $task->billable = 1;
            $task->created_by = $this->adminId;
            $task->added_by = $this->adminId;
            $task->save();

            $task->task_short_code = $project->project_short_code . '-' . $task->id;
            $task->saveQuietly();

            TaskUser::create(['user_id' => $assignees[$i % count($assignees)], 'task_id' => $task->id]);

            $search = new UniversalSearch();
            $search->searchable_id = $task->id;
            $search->company_id = $this->companyId;
            $search->title = $task->heading;
            $search->route_name = 'tasks.show';
            $search->save();

            $this->activity($project->id, 'Нова задача „' . $heading . '“ е добавена към проекта.');
        }
    }

    protected function timeLogs(Project $project): void
    {
        $today = Carbon::parse(self::TODAY);
        $tasks = Task::withoutGlobalScope(CompanyScope::class)->where('project_id', $project->id)->with('users')->get();

        foreach ($tasks as $task) {
            $slug = array_search($task->board_column_id, $this->boards);

            if (!in_array($slug, ['completed', 'doing'], true)) {
                continue;
            }

            $user = $task->users->first();

            if (!$user) {
                continue;
            }

            $rate = (int)round($this->rateOf($user->id));
            $from = Carbon::parse($task->start_date)->startOfDay();
            $to = Carbon::parse($task->due_date)->startOfDay();

            if ($to->gt($today)) {
                $to = $today->copy();
            }

            $days = max(1, $from->diffInDays($to));
            $logs = $slug === 'completed' ? mt_rand(2, 4) : mt_rand(1, 3);

            for ($n = 0; $n < $logs; $n++) {
                $day = $from->copy()->addDays(mt_rand(0, $days));

                while ($day->isWeekend()) {
                    $day->addDay();
                }

                if ($day->gt($today)) {
                    $day = $today->copy();
                    while ($day->isWeekend()) {
                        $day->subDay();
                    }
                }

                $minutes = mt_rand(6, 30) * 15; // 1.5h - 7.5h
                $startTime = $day->copy()->setTime(mt_rand(8, 10), [0, 15, 30, 45][mt_rand(0, 3)]);

                $log = new ProjectTimeLog();
                $log->company_id = $this->companyId;
                $log->project_id = $project->id;
                $log->task_id = $task->id;
                $log->user_id = $user->id;
                $log->start_time = $startTime->toDateTimeString();
                $log->end_time = $startTime->copy()->addMinutes($minutes)->toDateTimeString();
                $log->total_hours = (string)intdiv($minutes, 60);
                $log->total_minutes = (string)$minutes;
                $log->total_break_minutes = '0';
                $log->hourly_rate = $rate;
                $log->earnings = round($minutes / 60 * $rate, 2);
                $log->memo = self::MEMOS[mt_rand(0, count(self::MEMOS) - 1)];
                $log->approved = 1;
                $log->added_by = $this->adminId;
                $log->save();
            }
        }
    }

    protected function rateOf(int $userId): float
    {
        foreach ($this->employees as [$id, $rate]) {
            if ($id === $userId) {
                return $rate;
            }
        }

        return 0;
    }

    protected function updateProgress(Project $project): void
    {
        $total = Task::withoutGlobalScope(CompanyScope::class)->where('project_id', $project->id)->count();

        if ($total === 0) {
            return;
        }

        $done = Task::withoutGlobalScope(CompanyScope::class)->where('project_id', $project->id)->where('status', 'completed')->count();
        $project->completion_percent = (int)round($done / $total * 100);
        $project->saveQuietly();
    }

    protected function activity(int $projectId, string $text): void
    {
        $activity = new ProjectActivity();
        $activity->project_id = $projectId;
        $activity->activity = $text;
        $activity->save();
    }

}
