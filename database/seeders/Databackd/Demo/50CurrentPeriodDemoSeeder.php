<?php

namespace Database\Seeders\Databackd\Demo;

use App\Helper\NumberFormat;
use App\Models\Company;
use App\Models\InvoiceSetting;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AFV demo: tops the demo company up to today.
 *
 * The base demo seeders stop at a fixed date (FinanceDocsDemoSeeder::TODAY = 2026-10-27, the financial model at the end
 * of October 2026), so every screen that opens on the current month (advanced dashboard, finance report, time log
 * report...) is empty once the calendar moves on. This seeder is additive: for each kind of data it starts the day after
 * the newest demo row and fills up to today (or --until):
 *  - invoices (+ items, payments) with the same hash prefix as FinanceDocsDemoSeeder, so that seeder's cleanup still owns them;
 *  - payments of older open invoices that fall due in the period;
 *  - expenses (monthly fixed costs on their usual day + a few random ones) in the demo categories;
 *  - bank transactions appended to the running balance of the demo bank accounts;
 *  - time logs of the open (doing / to do) tasks, on working days;
 *  - financial model flows (source = demo): the same days of the previous month, scaled by seasonality.
 * Re-running on the same day adds nothing; running it next month fills the gap again.
 */
class CurrentPeriodDemoSeeder extends Seeder
{

    public ?string $until = null;

    protected int $cid;
    protected ?int $adminId;
    protected Carbon $today;
    protected array $created = ['invoices' => 0, 'payments' => 0, 'expenses' => 0, 'time_logs' => 0, 'fin_entries' => 0];

    public function run(Company $company)
    {
        $this->cid = $company->id;
        $this->today = Carbon::parse($this->until ?: now()->toDateString())->startOfDay();
        $this->adminId = DB::table('users')->where('company_id', $this->cid)->where('email', 'admin@demofinans.bg')->value('id')
            ?? DB::table('users')->where('company_id', $this->cid)->orderBy('id')->value('id');

        mt_srand(crc32($this->cid . '-' . $this->today->toDateString()));

        $accounts = DB::table('bank_accounts')->where('company_id', $this->cid)
            ->whereIn('bank_name', array_column(FinanceDocsDemoSeeder::BANKS, 0))->orderBy('id')->pluck('id')->all();

        if (count($accounts) < 2) {
            $this->command?->error('CurrentPeriodDemoSeeder: run FinanceDocsDemoSeeder first.');

            return;
        }

        DB::beginTransaction();

        $tx = [];
        $tx = array_merge($tx, $this->invoices($accounts));
        $tx = array_merge($tx, $this->expenses($accounts));
        $this->bankTransactions($tx);
        $this->tasks();
        $this->documentExpiries();
        $this->timeLogs();
        $this->personalDashboard();
        $this->finEntries();

        DB::commit();

        $this->command?->info('Current period (' . $this->today->toDateString() . '): ' . json_encode($this->created));
    }

    /* ------------------------------------------------------------------ invoices + payments */

    protected function invoices(array $accounts): array
    {
        $tx = [];
        $prefix = FinanceDocsDemoSeeder::HASH_PREFIX;
        $setting = InvoiceSetting::withoutGlobalScopes()->where('company_id', $this->cid)->first();
        $currencyId = DB::table('companies')->where('id', $this->cid)->value('currency_id');
        $vat = DB::table('taxes')->where('company_id', $this->cid)->where('tax_name', 'ДДС')->first();
        $clientIds = DB::table('client_details')->where('company_id', $this->cid)->where('gst_number', 'like', '2000000%')->orderBy('gst_number')->pluck('user_id')->all();
        $products = DB::table('products')->where('company_id', $this->cid)->where('sku', 'like', 'DFG-%')->orderBy('sku')->get();
        $units = DB::table('unit_types')->where('company_id', $this->cid)->pluck('unit_type', 'id')->all();
        $methods = DB::table('offline_payment_methods')->where('company_id', $this->cid)->pluck('id', 'name')->all();
        $projectsByClient = DB::table('projects')->where('company_id', $this->cid)->whereNotNull('client_id')->pluck('id', 'client_id')->all();

        if (!$setting || !$vat || empty($clientIds) || $products->isEmpty()) {
            return [];
        }

        $last = DB::table('invoices')->where('company_id', $this->cid)->where('hash', 'like', $prefix . '%')->max('issue_date');
        $from = $last ? Carbon::parse($last)->addDay() : $this->today->copy()->startOfMonth();
        $transfer = $methods['Банков превод'] ?? null;
        $cash = $methods['В брой'] ?? null;

        // 1. collect open invoices of the earlier periods (paid 10 - 40 days after issue)
        $open = DB::table('invoices')->where('company_id', $this->cid)->where('hash', 'like', $prefix . '%')
            ->whereIn('status', ['unpaid', 'partial'])->where('issue_date', '<', $from->toDateString())->get();

        foreach ($open as $inv) {
            $payDay = Carbon::parse($inv->issue_date)->addDays(mt_rand(10, 40));

            if ($payDay->lt($from) || $payDay->gt($this->today) || $payDay->isWeekend() || mt_rand(1, 100) > 70) {
                continue;
            }

            $amount = round((float)$inv->due_amount, 2);
            $tx[] = $this->payment($inv->id, $inv->invoice_number, $amount, $payDay, $currencyId, $accounts, $transfer, $cash, ' - окончателно плащане');
            DB::table('invoices')->where('id', $inv->id)->update(['status' => 'paid', 'due_amount' => 0, 'updated_at' => $payDay->toDateTimeString()]);
        }

        // 2. new invoices: about one per working day
        $next = (int)DB::table('invoices')->where('company_id', $this->cid)->max(DB::raw('CAST(original_invoice_number AS UNSIGNED)')) + 1;
        $rate = (float)$vat->rate_percent;

        for ($day = $from->copy(); $day->lte($this->today); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            $count = [0, 1, 1, 1, 2][mt_rand(0, 4)];

            for ($k = 0; $k < $count; $k++) {
                $client = $clientIds[mt_rand(0, count($clientIds) - 1)];
                $lines = [];

                $keys = range(0, $products->count() - 1);
                shuffle($keys);

                foreach (array_slice($keys, 0, mt_rand(1, 3)) as $idx) {
                    $product = $products[$idx];
                    $unit = $units[$product->unit_id] ?? 'Бр.';
                    $lines[] = ['product' => $product, 'qty' => match ($unit) { 'Месец' => mt_rand(1, 3), 'Час' => mt_rand(6, 30), default => mt_rand(1, 2) }];
                }

                $subTotal = round(array_sum(array_map(fn($l) => round($l['qty'] * (float)$l['product']->price, 2), $lines)), 2);
                $total = round($subTotal * (1 + $rate / 100), 2);
                $age = $day->diffInDays($this->today);
                $r = mt_rand(1, 100);
                $status = $age <= 2 && $r <= 35 ? 'draft' : ($age > 7 && $r <= 45 ? 'paid' : ($age > 7 && $r <= 55 ? 'partial' : 'unpaid'));
                $number = NumberFormat::invoice($next, $setting);
                $due = $day->copy()->addDays((int)($setting->due_after ?: 15));
                $at = $day->copy()->setTime(10, 0)->toDateTimeString();

                $invoiceId = DB::table('invoices')->insertGetId([
                    'company_id' => $this->cid, 'client_id' => $client, 'project_id' => $projectsByClient[$client] ?? null,
                    'invoice_number' => $number,
                    'original_invoice_number' => str_pad((string)$next, (int)$setting->invoice_digit, '0', STR_PAD_LEFT),
                    'issue_date' => $day->toDateString(), 'due_date' => $due->toDateString(),
                    'sub_total' => $subTotal, 'discount' => 0, 'discount_type' => 'percent', 'total' => $total,
                    'currency_id' => $currencyId, 'default_currency_id' => $currencyId, 'exchange_rate' => 1,
                    'status' => $status, 'recurring' => 'no',
                    'note' => $status === 'draft' ? null : 'Плащане по банков път в срок до ' . $due->format('d.m.Y') . ' г.',
                    'show_shipping_address' => 'no', 'send_status' => $status === 'draft' ? 0 : 1, 'due_amount' => $total,
                    'created_by' => $this->adminId, 'added_by' => $this->adminId,
                    'hash' => $prefix . md5($this->cid . '-' . $number), 'calculate_tax' => 'after_discount',
                    'bank_account_id' => $accounts[0], 'created_at' => $at, 'updated_at' => $at,
                ]);

                foreach ($lines as $order => $line) {
                    DB::table('invoice_items')->insert([
                        'invoice_id' => $invoiceId, 'item_name' => $line['product']->name, 'item_summary' => '', 'type' => 'item',
                        'quantity' => $line['qty'], 'unit_price' => (float)$line['product']->price,
                        'amount' => round($line['qty'] * (float)$line['product']->price, 2), 'taxes' => json_encode([(string)$vat->id]),
                        'unit_id' => $line['product']->unit_id, 'product_id' => $line['product']->id, 'field_order' => $order + 1,
                        'created_at' => $at, 'updated_at' => $at,
                    ]);
                }

                if (in_array($status, ['paid', 'partial'], true)) {
                    $amount = $status === 'paid' ? $total : round($total * mt_rand(30, 70) / 100, 0);
                    $payDay = $day->copy()->addDays(mt_rand(1, max(1, min(10, $age))));
                    $payDay = $payDay->gt($this->today) ? $this->today->copy() : $payDay;
                    $tx[] = $this->payment($invoiceId, $number, $amount, $payDay, $currencyId, $accounts, $transfer, $cash, $status === 'partial' ? ' - частично плащане' : '');
                    DB::table('invoices')->where('id', $invoiceId)->update(['due_amount' => round($total - $amount, 2)]);
                }

                $this->created['invoices']++;
                $next++;
            }
        }

        return array_values(array_filter($tx));
    }

    protected function payment(int $invoiceId, string $number, float $amount, Carbon $day, $currencyId, array $accounts, $transfer, $cash, string $suffix): ?array
    {
        $isCash = mt_rand(1, 100) > 90;
        $account = $isCash ? null : (mt_rand(1, 100) <= 68 ? $accounts[0] : $accounts[1]);
        $at = $day->copy()->setTime(mt_rand(9, 17), mt_rand(0, 59))->toDateTimeString();

        $paymentId = DB::table('payments')->insertGetId([
            'company_id' => $this->cid, 'invoice_id' => $invoiceId, 'amount' => $amount, 'gateway' => 'Offline',
            'currency_id' => $currencyId, 'default_currency_id' => $currencyId, 'exchange_rate' => 1, 'status' => 'complete',
            'paid_on' => $at, 'remarks' => ($isCash ? 'Плащане в брой' : 'Банков превод') . ' по фактура ' . $number . $suffix,
            'offline_method_id' => $isCash ? $cash : $transfer, 'bank_account_id' => $account, 'added_by' => $this->adminId,
            'created_at' => $at, 'updated_at' => $at,
        ]);
        $this->created['payments']++;

        return $account ? ['account' => $account, 'date' => $day->toDateString(), 'type' => 'Cr', 'amount' => $amount, 'payment_id' => $paymentId,
            'invoice_id' => $invoiceId, 'expense_id' => null, 'relation' => 'payment', 'related' => (string)$paymentId, 'title' => 'payment-credited'] : null;
    }

    /* ------------------------------------------------------------------ expenses */

    protected function expenses(array $accounts): array
    {
        $tx = [];
        $categories = DB::table('expenses_category')->where('company_id', $this->cid)->whereIn('category_name', FinanceDocsDemoSeeder::CATEGORIES)->pluck('id', 'category_name')->all();

        if (empty($categories)) {
            return [];
        }

        $last = DB::table('expenses')->where('company_id', $this->cid)->whereIn('category_id', $categories)->max('purchase_date');
        $from = $last ? Carbon::parse($last)->addDay() : $this->today->copy()->startOfMonth();
        $currencyId = DB::table('companies')->where('id', $this->cid)->value('currency_id');
        $employeeIds = DB::table('employee_details')->where('company_id', $this->cid)->where('user_id', '!=', $this->adminId)->pluck('user_id')->all();
        $monthsSinceStart = fn(Carbon $d) => Carbon::parse(FinanceDocsDemoSeeder::START)->diffInMonths($d);
        $rows = [];

        for ($m = $from->copy()->startOfMonth(); $m->lte($this->today); $m->addMonth()) {
            $label = FinanceDocsDemoSeeder::MONTHS_BG[$m->month - 1] . ' ' . $m->year;
            $growth = 1 + 0.06 * $monthsSinceStart($m) / 12;
            $fixed = [
                [3, 'Наем на офис, бул. България 81 - ' . $label, 'Мегастрой Имоти ЕООД', 'Наем и офис', round(1850 * 1.03, 2)],
                [7, 'Облачни услуги и офис пакет - ' . $label, 'Майкрософт Ирландия Оперейшънс Лимитид', 'Софтуер и абонаменти', round(185 * $growth, 2)],
                [10, 'Интернет и мобилни услуги - ' . $label, 'А1 България ЕАД', 'Комунални услуги', (float)mt_rand(105, 140)],
                [12, 'Електроенергия и вода - ' . $label, 'Енерго-Про Продажби АД', 'Комунални услуги', (float)mt_rand(300, 430)],
                [20, 'Осигуровки и данъци върху доходите - ' . $label, 'НАП и НОИ', 'Заплати и осигуровки', round(2600 * $growth, 2)],
                [25, 'Работни заплати - ' . $label, 'Служители на Демо Финанс Груп АД', 'Заплати и осигуровки', round(7400 * $growth, 2)],
            ];

            foreach ($fixed as [$d, $item, $supplier, $category, $price]) {
                $date = $m->copy()->day(min($d, $m->daysInMonth));

                if ($date->gte($from) && $date->lte($this->today)) {
                    $rows[] = [$item, $supplier, $category, $price, $date, true];
                }
            }
        }

        $flat = [];
        foreach (FinanceDocsDemoSeeder::RANDOM_EXPENSES as $category => $items) {
            foreach ($items as $item) {
                $flat[] = [$category, $item];
            }
        }

        for ($day = $from->copy(); $day->lte($this->today); $day->addDay()) {
            if (!$day->isWeekend() && mt_rand(1, 100) <= 45) {
                [$category, [$item, $supplier, $min, $max]] = $flat[mt_rand(0, count($flat) - 1)];
                $rows[] = [$item . ' - ' . FinanceDocsDemoSeeder::MONTHS_BG[$day->month - 1] . ' ' . $day->year, $supplier, $category, round(mt_rand($min * 100, $max * 100) / 100, 2), $day->copy(), false];
            }
        }

        usort($rows, fn($a, $b) => $a[4] <=> $b[4]);

        foreach ($rows as [$item, $supplier, $category, $price, $date, $fixed]) {
            if (!isset($categories[$category])) {
                continue;
            }

            $age = $date->diffInDays($this->today);
            $status = $fixed || ($age > 3 && mt_rand(1, 100) <= 75) ? 'approved' : (mt_rand(1, 100) <= 90 ? 'pending' : 'rejected');
            $bank = $status === 'approved' ? ($fixed || mt_rand(1, 100) <= 65 ? $accounts[0] : $accounts[1]) : null;
            $at = $date->copy()->setTime(mt_rand(9, 17), mt_rand(0, 59))->toDateTimeString();

            $id = DB::table('expenses')->insertGetId([
                'company_id' => $this->cid, 'item_name' => $item, 'purchase_date' => $date->toDateString(), 'purchase_from' => $supplier,
                'price' => $price, 'currency_id' => $currencyId, 'default_currency_id' => $currencyId, 'exchange_rate' => 1,
                'user_id' => $fixed || empty($employeeIds) ? $this->adminId : $employeeIds[mt_rand(0, count($employeeIds) - 1)],
                'status' => $status, 'can_claim' => 0, 'category_id' => $categories[$category], 'created_by' => $this->adminId,
                'description' => 'Разход за ' . mb_strtolower($category) . ': ' . $item . '. Доставчик: ' . $supplier . '.',
                'added_by' => $this->adminId, 'approver_id' => $status === 'approved' ? $this->adminId : null,
                'bank_account_id' => $bank, 'created_at' => $at, 'updated_at' => $at,
            ]);
            $this->created['expenses']++;

            if ($bank) {
                $tx[] = ['account' => $bank, 'date' => $date->toDateString(), 'type' => 'Dr', 'amount' => $price, 'payment_id' => null,
                    'invoice_id' => null, 'expense_id' => $id, 'relation' => 'expense', 'related' => $item, 'title' => 'expense-added'];
            }
        }

        return $tx;
    }

    /** Appends the new transactions to the running balance of each bank account. */
    protected function bankTransactions(array $tx): void
    {
        foreach (array_unique(array_column($tx, 'account')) as $accountId) {
            $items = array_values(array_filter($tx, fn($t) => $t['account'] === $accountId));
            usort($items, fn($a, $b) => [$a['date'], $a['type'] === 'Dr' ? 1 : 0] <=> [$b['date'], $b['type'] === 'Dr' ? 1 : 0]);
            $balance = (float)DB::table('bank_accounts')->where('id', $accountId)->value('bank_balance');

            foreach ($items as $t) {
                $balance += $t['type'] === 'Cr' ? $t['amount'] : -$t['amount'];
                DB::table('bank_transactions')->insert([
                    'company_id' => $this->cid, 'bank_account_id' => $accountId, 'payment_id' => $t['payment_id'], 'invoice_id' => $t['invoice_id'],
                    'expense_id' => $t['expense_id'], 'amount' => $t['amount'], 'type' => $t['type'], 'added_by' => $this->adminId, 'memo' => null,
                    'transaction_relation' => $t['relation'], 'transaction_related_to' => $t['related'], 'title' => $t['title'],
                    'transaction_date' => $t['date'], 'bank_balance' => round($balance, 2),
                    'created_at' => $t['date'] . ' 12:00:00', 'updated_at' => $t['date'] . ' 12:00:00',
                ]);
            }

            DB::table('bank_accounts')->where('id', $accountId)->update(['bank_balance' => round($balance, 2)]);
        }
    }

    /* ------------------------------------------------------------------ tasks */

    const TASKS = [
        'Месечно приключване и сверка на салдата', 'Изготвяне на ОПР и баланс за управителя', 'Анализ на отклоненията от бюджета',
        'Прогноза на паричните потоци за следващото тримесечие', 'Сверка на вземанията от клиенти', 'Преглед на разходите за външни услуги',
        'Актуализация на финансовите показатели', 'Подготовка на консолидиран отчет на групата', 'Среща с клиента за резултатите от месеца',
        'Импорт на оборотната ведомост и проверка на грешките',
    ];

    /** Tasks of the active projects for every month of the period (6 per month): past ones mostly done, the rest open. */
    protected function tasks(): void
    {
        $projects = DB::table('projects')->where('company_id', $this->cid)->where('status', 'in progress')->whereNull('deleted_at')->get();
        $cols = DB::table('taskboard_columns')->where('company_id', $this->cid)->pluck('id', 'slug')->all();

        if ($projects->isEmpty() || !isset($cols['completed'], $cols['doing'], $cols['to_do'])) {
            return;
        }

        // an "in progress" project with a deadline in the past looks odd on the demo
        DB::table('projects')->whereIn('id', $projects->pluck('id'))->where('deadline', '<', $this->today->toDateString())
            ->update(['deadline' => $this->today->copy()->addMonths(2)->endOfMonth()->toDateString()]);

        $category = DB::table('task_category')->where('company_id', $this->cid)->value('id');
        $from = $this->today->copy()->subMonth()->startOfMonth(); // previous + current month

        for ($m = $from->copy(); $m->lte($this->today); $m->addMonth()) {
            $marker = 'dfgcur-' . $m->format('Y-m') . '-';

            if (DB::table('tasks')->where('company_id', $this->cid)->where('hash', 'like', $marker . '%')->exists()) {
                continue;
            }

            for ($n = 0; $n < 6; $n++) {
                $project = $projects[$n % $projects->count()];
                $members = DB::table('project_members')->where('project_id', $project->id)->pluck('user_id')->all() ?: [$this->adminId];
                $start = $m->copy()->day(mt_rand(1, 18));
                $due = $start->copy()->addDays(mt_rand(5, 12));
                $past = $due->lt($this->today);
                $slug = $past ? (mt_rand(1, 100) <= 80 ? 'completed' : 'doing') : ($start->lte($this->today) ? 'doing' : 'to_do');

                // the dashboard lists open tasks due between the 1st of the month and today: keep two of them there
                if ($n < 2 && $m->isSameMonth($this->today) && $this->today->day > 2) {
                    $start = $m->copy()->day(1);
                    $due = $m->copy()->day(mt_rand(2, $this->today->day));
                    $slug = 'doing';
                }
                $seq = (int)DB::table('tasks')->where('project_id', $project->id)->count() + 1;
                $at = $start->copy()->setTime(9, 0)->toDateTimeString();
                $heading = self::TASKS[($n + $m->month) % count(self::TASKS)] . ' – ' . FinanceDocsDemoSeeder::MONTHS_BG[$m->month - 1] . ' ' . $m->year;

                $taskId = DB::table('tasks')->insertGetId([
                    'task_short_code' => ($project->project_short_code ?: 'TSK') . '-' . $seq, 'company_id' => $this->cid,
                    'heading' => $heading, 'description' => '<p>' . $heading . '. Резултатът се съгласува с клиента и се качва в проекта.</p>',
                    'start_date' => $start->toDateString(), 'due_date' => $due->toDateString(), 'project_id' => $project->id,
                    'task_category_id' => $category, 'priority' => ['low', 'medium', 'high'][mt_rand(0, 2)], 'status' => $slug === 'completed' ? 'completed' : 'incomplete',
                    'board_column_id' => $cols[$slug], 'column_priority' => 0,
                    'completed_on' => $slug === 'completed' ? $due->copy()->subDays(mt_rand(0, 2))->toDateTimeString() : null,
                    'created_by' => $this->adminId, 'is_private' => 0, 'billable' => 1, 'estimate_hours' => mt_rand(4, 16), 'estimate_minutes' => 0,
                    'added_by' => $this->adminId, 'last_updated_by' => $this->adminId, 'hash' => $marker . md5($project->id . '-' . $n),
                    'repeat' => 0, 'created_at' => $at, 'updated_at' => $at,
                ]);

                DB::table('task_users')->insert(['task_id' => $taskId, 'user_id' => $members[mt_rand(0, count($members) - 1)], 'created_at' => $at, 'updated_at' => $at]);
                $this->created['tasks'] = ($this->created['tasks'] ?? 0) + 1;
            }
        }
    }

    /* ------------------------------------------------------------------ employee documents with expiry dates */

    /** The dashboards list the documents expiring in the current calendar year; keeps about 7 of them for the year of $today. */
    protected function documentExpiries(): void
    {
        $prefix = 'DFG-DOC-' . $this->today->year . '-';

        if (DB::table('employee_document_expiries')->where('company_id', $this->cid)->where('document_number', 'like', $prefix . '%')->exists()) {
            return;
        }

        $users = DB::table('employee_details')->where('company_id', $this->cid)->orderBy('id')->pluck('user_id')->all();

        if (empty($users)) {
            return;
        }

        $docs = [ // [name, days from today (negative = already expired), validity in years, alert days]
            ['Лична карта', 9, 10, 30], ['Медицинско свидетелство за работа', 18, 1, 30], ['Срочен трудов договор', 25, 1, 30],
            ['Сертификат ACCA – членство', 34, 1, 45], ['Застраховка „Професионална отговорност“', 47, 1, 30],
            ['Свидетелство за управление на МПС', -6, 10, 30], ['Удостоверение за обучение по ЗБУТ', 3, 2, 15],
        ];

        foreach ($docs as $i => [$name, $days, $years, $alert]) {
            $expiry = $this->today->copy()->addDays($days);

            if ($expiry->year !== $this->today->year) {
                $expiry = $this->today->copy()->endOfYear()->subDays($i);
            }

            DB::table('employee_document_expiries')->insert([
                'company_id' => $this->cid, 'user_id' => $users[$i % count($users)], 'document_name' => $name,
                'document_number' => $prefix . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT),
                'issue_date' => $expiry->copy()->subYears($years)->toDateString(), 'expiry_date' => $expiry->toDateString(),
                'alert_before_days' => $alert, 'alert_enabled' => 1, 'added_by' => $this->adminId, 'last_updated_by' => $this->adminId,
                'created_at' => $this->today->toDateTimeString(), 'updated_at' => $this->today->toDateTimeString(),
            ]);
            $this->created['documents'] = ($this->created['documents'] ?? 0) + 1;
        }
    }

    /* ------------------------------------------------------------------ personal dashboard */

    /**
     * The widgets of the personal dashboard: upcoming birthdays, today's work anniversary, notice period / probation /
     * internship / contract end dates, and for the demo admin own projects, open tasks and the hours of this week.
     * Only empty employee fields are filled, so values edited by hand stay.
     */
    protected function personalDashboard(): void
    {
        $employees = DB::table('employee_details')->where('company_id', $this->cid)->orderBy('id')->get();
        $t = $this->today;

        foreach ($employees as $i => $e) {
            $set = [];

            if (!$e->date_of_birth) {
                // every other one has the birthday in the coming weeks, the rest earlier in the year
                $offset = [6, -40, 15, -120, 27, -200, 44, -75, 63][$i % 9];
                $set['date_of_birth'] = $t->copy()->addDays($offset)->setYear(1984 + ($i * 3) % 14)->toDateString();
            }

            if (!$e->employment_type) {
                $set['employment_type'] = match ($i) { 4 => 'internship', 6, 7 => 'on_contract', default => 'full_time' };
            }

            $dates = match ($i) {
                3 => ['notice_period_start_date' => $t->copy()->subDays(10), 'notice_period_end_date' => $t->copy()->addDays(20)],
                4 => ['internship_end_date' => $t->copy()->addDays(48)],
                5 => ['probation_end_date' => $t->copy()->addDays(18)],
                6 => ['contract_end_date' => $t->copy()->addDays(35)],
                7 => ['contract_end_date' => $t->copy()->addDays(95), 'probation_end_date' => $t->copy()->addDays(40)],
                default => [],
            };

            foreach ($dates as $column => $value) {
                if (!$e->{$column}) {
                    $set[$column] = $value->toDateString();
                }
            }

            if ($set) {
                DB::table('employee_details')->where('id', $e->id)->update($set);
            }
        }

        // work anniversary today: the newest employee joined on this day two years ago
        $last = $employees->last();

        if ($last && !$employees->contains(fn($e) => $e->joining_date && Carbon::parse($e->joining_date)->format('m-d') === $t->format('m-d'))) {
            DB::table('employee_details')->where('id', $last->id)->update(['joining_date' => $t->copy()->subYears(2)->toDateString()]);
        }

        // the demo admin works on the active projects too
        $projects = DB::table('projects')->where('company_id', $this->cid)->where('status', 'in progress')->whereNull('deleted_at')->pluck('id');

        foreach ($projects as $projectId) {
            if (!DB::table('project_members')->where('project_id', $projectId)->where('user_id', $this->adminId)->exists()) {
                $rate = (float)(DB::table('project_members')->where('project_id', $projectId)->max('hourly_rate') ?: 45);
                DB::table('project_members')->insert(['project_id' => $projectId, 'user_id' => $this->adminId, 'hourly_rate' => $rate,
                    'added_by' => $this->adminId, 'created_at' => $t->toDateTimeString(), 'updated_at' => $t->toDateTimeString()]);
            }
        }

        $openTasks = DB::table('tasks')->where('company_id', $this->cid)->where('hash', 'like', 'dfgcur-%')->where('status', 'incomplete')
            ->whereNull('deleted_at')->orderByDesc('due_date')->limit(4)->get(['id', 'project_id', 'start_date']);

        foreach ($openTasks as $task) {
            if (!DB::table('task_users')->where('task_id', $task->id)->where('user_id', $this->adminId)->exists()) {
                DB::table('task_users')->insert(['task_id' => $task->id, 'user_id' => $this->adminId, 'created_at' => $t->toDateTimeString(), 'updated_at' => $t->toDateTimeString()]);
            }
        }

        // hours of the current week (Monday .. today)
        $week = $t->copy()->startOfWeek();

        if ($openTasks->isNotEmpty() && !DB::table('project_time_logs')->where('user_id', $this->adminId)->where('start_time', '>=', $week->toDateTimeString())->exists()) {
            for ($day = $week->copy(); $day->lte($t); $day->addDay()) {
                if ($day->isWeekend()) {
                    continue;
                }

                $task = $openTasks[mt_rand(0, $openTasks->count() - 1)];
                $minutes = mt_rand(10, 26) * 15;
                $start = $day->copy()->setTime(9, 0);
                $end = $start->copy()->addMinutes($minutes);
                DB::table('project_time_logs')->insert([
                    'company_id' => $this->cid, 'project_id' => $task->project_id, 'task_id' => $task->id, 'user_id' => $this->adminId,
                    'start_time' => $start->toDateTimeString(), 'end_time' => $end->toDateTimeString(),
                    'total_hours' => (string)intdiv($minutes, 60), 'total_minutes' => (string)$minutes, 'total_break_minutes' => '0',
                    'hourly_rate' => 45, 'earnings' => round($minutes / 60 * 45, 2), 'memo' => 'Преглед на резултатите и съгласуване с екипа',
                    'approved' => 1, 'added_by' => $this->adminId, 'created_at' => $end->toDateTimeString(), 'updated_at' => $end->toDateTimeString(),
                ]);
                $this->created['time_logs']++;
            }
        }
    }

    /* ------------------------------------------------------------------ time logs */

    protected function timeLogs(): void
    {
        $last = DB::table('project_time_logs')->where('company_id', $this->cid)->max('start_time');
        $from = $last ? Carbon::parse($last)->addDay()->startOfDay() : $this->today->copy()->startOfMonth();
        $doing = DB::table('taskboard_columns')->where('company_id', $this->cid)->where('slug', 'doing')->value('id');
        $tasks = DB::table('tasks')->where('company_id', $this->cid)->whereNotNull('project_id')->whereNull('deleted_at')
            ->where(fn($q) => $q->where('board_column_id', $doing)->orWhere('hash', 'like', 'dfgcur-%'))
            ->get(['id', 'project_id', 'start_date', 'completed_on']);
        $memos = ['Анализ на данните и подготовка на справки', 'Работа по задачата и съгласуване с клиента', 'Сверка на салдата и обороти', 'Подготовка на отчет за управителя', 'Среща с клиента и уточняване на изискванията'];

        if ($tasks->isEmpty()) {
            return;
        }

        $assignees = DB::table('task_users')->whereIn('task_id', $tasks->pluck('id'))->get()->groupBy('task_id');

        for ($day = $from->copy(); $day->lte($this->today); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            foreach ($tasks as $task) {
                if (Carbon::parse($task->start_date)->gt($day) || ($task->completed_on && Carbon::parse($task->completed_on)->startOfDay()->lt($day))) {
                    continue;
                }

                foreach ($assignees[$task->id] ?? [] as $a) {
                    if (mt_rand(1, 100) > 55) {
                        continue;
                    }

                    $rate = (int)(DB::table('project_time_logs')->where('company_id', $this->cid)->where('user_id', $a->user_id)->orderByDesc('id')->value('hourly_rate') ?: 30);
                    $minutes = mt_rand(4, 24) * 15;
                    $start = $day->copy()->setTime(mt_rand(8, 11), [0, 15, 30, 45][mt_rand(0, 3)]);
                    $end = $start->copy()->addMinutes($minutes);
                    $at = $end->toDateTimeString();

                    DB::table('project_time_logs')->insert([
                        'company_id' => $this->cid, 'project_id' => $task->project_id, 'task_id' => $task->id, 'user_id' => $a->user_id,
                        'start_time' => $start->toDateTimeString(), 'end_time' => $end->toDateTimeString(),
                        'total_hours' => (string)intdiv($minutes, 60), 'total_minutes' => (string)$minutes, 'total_break_minutes' => '0',
                        'hourly_rate' => $rate, 'earnings' => round($minutes / 60 * $rate, 2), 'memo' => $memos[mt_rand(0, count($memos) - 1)],
                        'approved' => 1, 'added_by' => $this->adminId, 'created_at' => $at, 'updated_at' => $at,
                    ]);
                    $this->created['time_logs']++;
                }
            }
        }
    }

    /* ------------------------------------------------------------------ financial model */

    /** Flows of the new days = the same days of the previous month (seasonally scaled, ±6 %). Balances stay month-end. */
    protected function finEntries(): void
    {
        $last = DB::table('fin_entries')->where('company_id', $this->cid)->where('source', 'demo')->where('kind', 'flow')->max('entry_date');

        if (!$last) {
            return;
        }

        $season = FinanceDocsDemoSeeder::SEASON;

        for ($day = Carbon::parse($last)->addDay(); $day->lte($this->today); $day->addDay()) {
            $src = $day->copy()->subMonthNoOverflow();
            $factor = $season[$day->month] / $season[$src->month];
            $rows = [];

            foreach (DB::table('fin_entries')->where('company_id', $this->cid)->where('source', 'demo')->where('kind', 'flow')->whereDate('entry_date', $src->toDateString())->get() as $e) {
                $row = (array)$e;
                unset($row['id']);
                $k = $factor * mt_rand(94, 106) / 100;
                $row['amount'] = round((float)$e->amount * $k, 2);
                $row['amount_base'] = round((float)$e->amount_base * $k, 2);
                $row['entry_date'] = $day->toDateString();
                $row['description'] = $e->description ? str_replace(
                    [FinanceDocsDemoSeeder::MONTHS_BG[$src->month - 1] . ' ' . $src->year, $src->format('Y-m')],
                    [FinanceDocsDemoSeeder::MONTHS_BG[$day->month - 1] . ' ' . $day->year, $day->format('Y-m')], $e->description) : null;
                $row['document_no'] = $e->document_no ? str_replace($src->format('Y-m'), $day->format('Y-m'), $e->document_no) : null;
                $row['created_at'] = $row['updated_at'] = $day->toDateString() . ' 18:00:00';
                $rows[] = $row;
            }

            foreach (array_chunk($rows, 300) as $chunk) {
                DB::table('fin_entries')->insert($chunk);
            }

            $this->created['fin_entries'] += count($rows);
        }
    }

}
