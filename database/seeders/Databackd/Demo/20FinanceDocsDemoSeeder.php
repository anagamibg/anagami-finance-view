<?php

namespace Database\Seeders\Databackd\Demo;

use App\Helper\NumberFormat;
use App\Models\Company;
use App\Models\InvoiceSetting;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * AFV demo: bank accounts, invoices (+ payments, credit notes) and expenses of the demo company,
 * May 2025 - October 2026, everything in Bulgarian and in EUR.
 *
 * Rows are written with the query builder (the app's observers/mutators rely on a logged in user and company()),
 * so every derived value (invoice totals, status, due_amount, bank transactions, bank balances) is computed here.
 * Idempotent: the rows created by this seeder are identified (bank accounts by bank name, invoices by hash prefix,
 * payments / credit notes through their invoices, expenses by category), removed and created again.
 */
class FinanceDocsDemoSeeder extends Seeder
{

    const TODAY = '2026-10-27';
    const START = '2025-05-01';
    const HASH_PREFIX = 'dfgdemo';
    const INVOICE_COUNT = 200;
    const EXPENSE_COUNT = 250;

    const MONTHS_BG = ['януари', 'февруари', 'март', 'април', 'май', 'юни', 'юли', 'август', 'септември', 'октомври', 'ноември', 'декември'];

    /** Seasonality of the invoicing by calendar month (1..12). */
    const SEASON = [1 => 0.90, 2 => 0.95, 3 => 1.20, 4 => 1.10, 5 => 1.00, 6 => 1.00, 7 => 0.80, 8 => 0.70, 9 => 1.05, 10 => 1.10, 11 => 1.05, 12 => 0.95];

    const BANKS = [
        ['УниКредит Булбанк', 'Основна разплащателна сметка', 'UNCR', '7000', '15', '23456789', '+359 2 9269 999'],
        ['ДСК Банк', 'Сметка за постъпления от клиенти', 'STSA', '9300', '10', '00123456', '+359 2 9391 000'],
    ];

    const CATEGORIES = [
        'Наем и офис', 'Заплати и осигуровки', 'Софтуер и абонаменти', 'Маркетинг и реклама', 'Транспорт и командировки',
        'Външни услуги', 'Обучения', 'Комунални услуги', 'Банкови такси',
    ];

    /** category => [[item, supplier, min, max], ...] for the random (non recurring) expenses */
    const RANDOM_EXPENSES = [
        'Маркетинг и реклама' => [
            ['Google Ads кампания', 'Гугъл Ирландия Лимитид', 150, 900],
            ['Реклама във Facebook и LinkedIn', 'Мета Платформс Ирландия', 120, 700],
            ['Печатни материали и брошури', 'Принт Експрес ООД', 90, 480],
            ['Участие в бизнес конференция', 'Българска стопанска камара', 250, 1400],
        ],
        'Транспорт и командировки' => [
            ['Командировка при клиент', 'Лукойл България ЕООД', 60, 380],
            ['Железопътни билети', 'БДЖ - Пътнически превози ЕООД', 25, 140],
            ['Гориво за служебен автомобил', 'ОМВ България ООД', 70, 260],
            ['Такси и градски транспорт', 'ОК Супертранс ЕООД', 15, 90],
        ],
        'Външни услуги' => [
            ['Правна консултация по договор', 'Адвокатско дружество Иванов и партньори', 300, 1200],
            ['Одиторска услуга', 'Одит Експерт ООД', 600, 2500],
            ['Превод на документи', 'Лингва Превод ЕООД', 80, 420],
            ['Външен експерт - анализ на данни', 'Дата Скилс ЕООД', 400, 1900],
        ],
        'Обучения' => [
            ['Курс по МСФО за специалисти', 'Институт на дипломираните експерт-счетоводители', 180, 650],
            ['Обучение по бизнес анализи', 'Академика Обучения ООД', 140, 520],
            ['Семинар по данъчно облагане', 'Национална агенция за приходите - обучителен център', 90, 320],
        ],
        'Софтуер и абонаменти' => [
            ['Лиценз за счетоводен софтуер', 'Софтуерни решения ЕАД', 180, 720],
            ['Абонамент за инструмент за визуализация', 'Бизнес Тех България ООД', 90, 420],
            ['Домейн и хостинг', 'Хостинг България ЕООД', 40, 190],
        ],
        'Наем и офис' => [
            ['Канцеларски материали', 'Офис 1 Суперстор ЕООД', 40, 260],
            ['Почистване на офиса', 'Клийн Сервиз ЕООД', 120, 240],
            ['Офис обзавеждане и техника', 'Мебели Ком ЕООД', 150, 1100],
        ],
        'Банкови такси' => [
            ['Банкови такси и комисиони', 'УниКредит Булбанк АД', 18, 85],
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    protected array $tx = [];

    public function run(Company $company)
    {
        $cid = $company->id;
        $now = now();
        $today = Carbon::parse(self::TODAY);

        $setting = InvoiceSetting::withoutGlobalScopes()->where('company_id', $cid)->first();
        $currencyId = $company->currency_id;
        $adminId = $this->adminId($cid);
        $employeeIds = DB::table('employee_details')->where('company_id', $cid)->where('user_id', '!=', $adminId)->pluck('user_id')->all();
        $clientIds = DB::table('client_details')->where('company_id', $cid)->where('gst_number', 'like', '2000000%')->orderBy('gst_number')->pluck('user_id')->all();
        $products = DB::table('products')->where('company_id', $cid)->where('sku', 'like', 'DFG-%')->orderBy('sku')->get();
        $units = DB::table('unit_types')->where('company_id', $cid)->pluck('unit_type', 'id')->all();
        $vat = DB::table('taxes')->where('company_id', $cid)->where('tax_name', 'ДДС')->first();

        if (!$setting || empty($clientIds) || $products->isEmpty() || !$vat) {
            $this->command?->error('FinanceDocsDemoSeeder: run the base demo seeder first (clients, products, VAT are missing).');

            return;
        }

        DB::beginTransaction();

        $this->cleanup($cid);

        $accounts = $this->bankAccounts($cid, $currencyId, $adminId);
        $methods = $this->offlineMethods($cid);

        $invoices = $this->invoices($cid, $setting, $currencyId, $adminId, $clientIds, $products, $units, $vat, $accounts, $methods, $today, $now);
        $this->creditNotes($cid, $setting, $currencyId, $adminId, $invoices, $products, $vat, $today);
        $this->expenses($cid, $currencyId, $adminId, $employeeIds, $accounts, $today);
        $this->bankTransactions($cid, $accounts, $adminId);

        DB::commit();

        $this->command?->info(sprintf(
            'Finance docs: %d invoices, %d payments, %d credit notes, %d expenses, %d bank transactions.',
            DB::table('invoices')->where('company_id', $cid)->where('hash', 'like', self::HASH_PREFIX . '%')->count(),
            DB::table('payments')->where('company_id', $cid)->whereIn('invoice_id', DB::table('invoices')->where('company_id', $cid)->where('hash', 'like', self::HASH_PREFIX . '%')->pluck('id'))->count(),
            DB::table('credit_notes')->where('company_id', $cid)->count(),
            DB::table('expenses')->where('company_id', $cid)->count(),
            DB::table('bank_transactions')->where('company_id', $cid)->count()
        ));
    }

    /* ------------------------------------------------------------------ cleanup */

    protected function cleanup(int $cid): void
    {
        $accountIds = DB::table('bank_accounts')->where('company_id', $cid)->whereIn('bank_name', array_column(self::BANKS, 0))->pluck('id')->all();
        $invoiceIds = DB::table('invoices')->where('company_id', $cid)->where('hash', 'like', self::HASH_PREFIX . '%')->pluck('id')->all();
        $categoryIds = DB::table('expenses_category')->where('company_id', $cid)->whereIn('category_name', self::CATEGORIES)->pluck('id')->all();
        $expenseIds = DB::table('expenses')->where('company_id', $cid)->whereIn('category_id', $categoryIds)->pluck('id')->all();
        $paymentIds = DB::table('payments')->whereIn('invoice_id', $invoiceIds)->pluck('id')->all();
        $creditNoteIds = DB::table('credit_notes')->whereIn('invoice_id', $invoiceIds)->pluck('id')->all();

        DB::table('bank_transactions')->where(function ($q) use ($accountIds, $invoiceIds, $expenseIds, $paymentIds) {
            $q->whereIn('bank_account_id', $accountIds)->orWhereIn('invoice_id', $invoiceIds)
                ->orWhereIn('expense_id', $expenseIds)->orWhereIn('payment_id', $paymentIds);
        })->delete();
        DB::table('payments')->whereIn('id', $paymentIds)->delete();
        DB::table('credit_note_items')->whereIn('credit_note_id', $creditNoteIds)->delete();
        DB::table('credit_notes')->whereIn('id', $creditNoteIds)->delete();
        DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds)->delete();
        DB::table('invoices')->whereIn('id', $invoiceIds)->delete();
        DB::table('expenses')->whereIn('id', $expenseIds)->delete();
        DB::table('bank_accounts')->whereIn('id', $accountIds)->delete();
    }

    /* ------------------------------------------------------------------ bank accounts / methods */

    protected function adminId(int $cid): ?int
    {
        return DB::table('users')->where('company_id', $cid)->where('email', 'admin@demofinans.bg')->value('id')
            ?? DB::table('users')->where('company_id', $cid)->orderBy('id')->value('id');
    }

    /** @return array<int, int> bank account ids, in the order of self::BANKS */
    protected function bankAccounts(int $cid, int $currencyId, ?int $adminId): array
    {
        $ids = [];

        foreach (self::BANKS as [$bank, $name, $code, $branch, $type, $number, $phone]) {
            $ids[] = DB::table('bank_accounts')->insertGetId([
                'company_id' => $cid,
                'type' => 'bank',
                'bank_name' => $bank,
                'account_name' => $name,
                'account_number' => $this->iban('BG', $code . $branch . $type . $number),
                'account_type' => 'current',
                'currency_id' => $currencyId,
                'contact_number' => $phone,
                'opening_balance' => 0,
                'bank_balance' => 0,
                'status' => 1,
                'added_by' => $adminId,
                'created_at' => self::START . ' 09:00:00',
                'updated_at' => self::START . ' 09:00:00',
            ]);
        }

        return $ids;
    }

    /** @return array{transfer: int, cash: int} */
    protected function offlineMethods(int $cid): array
    {
        $result = [];

        foreach (['transfer' => ['Банков превод', 'Плащане по банков път към сметката на фирмата.'], 'cash' => ['В брой', 'Плащане в брой в офиса на фирмата.']] as $key => [$name, $description]) {
            $id = DB::table('offline_payment_methods')->where('company_id', $cid)->where('name', $name)->value('id');

            $result[$key] = $id ?: DB::table('offline_payment_methods')->insertGetId([
                'company_id' => $cid, 'name' => $name, 'description' => $description, 'status' => 'yes',
                'created_at' => self::START . ' 09:00:00', 'updated_at' => self::START . ' 09:00:00',
            ]);
        }

        return $result;
    }

    protected function iban(string $country, string $bban): string
    {
        $numeric = '';

        foreach (str_split($bban . $country . '00') as $ch) {
            $numeric .= ctype_alpha($ch) ? (string)(ord($ch) - 55) : $ch;
        }

        $mod = 0;

        foreach (str_split($numeric) as $digit) {
            $mod = ($mod * 10 + (int)$digit) % 97;
        }

        return $country . str_pad((string)(98 - $mod), 2, '0', STR_PAD_LEFT) . $bban;
    }

    /* ------------------------------------------------------------------ invoices + payments */

    /**
     * @return array<int, array<string, mixed>> created invoices (id, number, client, issue, items, status, total)
     */
    protected function invoices(int $cid, $setting, int $currencyId, ?int $adminId, array $clientIds, $products, array $units, $vat, array $accounts, array $methods, Carbon $today, Carbon $now): array
    {
        // number of invoices per month: growth ~6% a year x seasonality
        $months = [];

        for ($m = Carbon::parse(self::START)->startOfMonth(), $i = 0; $m->lte($today); $m->addMonth(), $i++) {
            $months[] = ['date' => $m->copy(), 'weight' => (1 + 0.06 * $i / 12) * self::SEASON[$m->month]];
        }

        $sum = array_sum(array_column($months, 'weight'));
        $counts = [];

        foreach ($months as $i => $month) {
            $counts[$i] = (int)round(self::INVOICE_COUNT * $month['weight'] / $sum);
        }

        // plan every invoice (date, client, lines) first, so the numbers follow the issue dates
        $plan = [];

        foreach ($months as $i => $month) {
            $lastDay = $month['date']->copy()->endOfMonth()->day;
            $maxDay = $month['date']->isSameMonth($today) ? min($lastDay, 25) : min($lastDay, 28);

            for ($k = 0; $k < $counts[$i]; $k++) {
                $issue = $month['date']->copy()->day(mt_rand(1, $maxDay));
                $lines = [];
                $lineCount = [1, 2, 2, 2, 3, 3, 3, 3, 4][mt_rand(0, 8)];

                foreach ($this->pick($products->count(), $lineCount) as $idx) {
                    $product = $products[$idx];
                    $unit = $units[$product->unit_id] ?? 'Бр.';
                    $qty = match ($unit) {
                        'Месец' => mt_rand(1, 5),
                        'Час' => mt_rand(6, 36),
                        default => mt_rand(1, 2),
                    };
                    $lines[] = ['product' => $product, 'qty' => $qty];
                }

                $plan[] = ['issue' => $issue, 'client' => $clientIds[$this->weightedIndex(count($clientIds))], 'lines' => $lines];
            }
        }

        usort($plan, fn($a, $b) => $a['issue'] <=> $b['issue']);

        // the 4 newest invoices (at most 14 days old) stay drafts
        $draftKeys = array_slice(array_keys(array_filter($plan, fn($p) => $p['issue']->diffInDays($today) <= 14)), -4);

        $next = $this->nextNumber('invoices', 'original_invoice_number', $cid);
        $created = [];
        $rate = (float)$vat->rate_percent;

        foreach ($plan as $key => $p) {
            $issue = $p['issue'];
            $due = $issue->copy()->addDays((int)($setting->due_after ?: 15));
            $age = $issue->diffInDays($today);

            $subTotal = 0.0;
            foreach ($p['lines'] as $line) {
                $subTotal += round($line['qty'] * (float)$line['product']->price, 2);
            }
            $subTotal = round($subTotal, 2);
            $total = round($subTotal * (1 + $rate / 100), 2);

            // status
            $r = mt_rand(1, 100);

            if (in_array($key, $draftKeys, true)) {
                $status = 'draft';
            } elseif ($age > 45) {
                $status = $r <= 88 ? 'paid' : ($r <= 92 ? 'partial' : 'unpaid');
            } elseif ($age > 15) {
                $status = $r <= 60 ? 'paid' : ($r <= 70 ? 'partial' : 'unpaid');
            } else {
                $status = $r <= 25 ? 'paid' : ($r <= 30 ? 'partial' : 'unpaid');
            }

            $number = NumberFormat::invoice($next, $setting);

            $invoiceId = DB::table('invoices')->insertGetId([
                'company_id' => $cid,
                'client_id' => $p['client'],
                'invoice_number' => $number,
                'original_invoice_number' => str_pad((string)$next, (int)$setting->invoice_digit, '0', STR_PAD_LEFT),
                'issue_date' => $issue->toDateString(),
                'due_date' => $due->toDateString(),
                'sub_total' => $subTotal,
                'discount' => 0,
                'discount_type' => 'percent',
                'total' => $total,
                'currency_id' => $currencyId,
                'default_currency_id' => $currencyId,
                'exchange_rate' => 1,
                'status' => $status,
                'recurring' => 'no',
                'note' => $status === 'draft' ? null : 'Плащане по банков път в срок до ' . $due->format('d.m.Y') . ' г.',
                'show_shipping_address' => 'no',
                'send_status' => $status === 'draft' ? 0 : 1,
                'due_amount' => $total,
                'created_by' => $adminId,
                'added_by' => $adminId,
                'hash' => self::HASH_PREFIX . md5($cid . '-' . $number),
                'calculate_tax' => 'after_discount',
                'bank_account_id' => $accounts[0],
                'created_at' => $issue->copy()->setTime(10, 0)->toDateTimeString(),
                'updated_at' => $issue->copy()->setTime(10, 0)->toDateTimeString(),
            ]);

            foreach ($p['lines'] as $order => $line) {
                DB::table('invoice_items')->insert([
                    'invoice_id' => $invoiceId,
                    'item_name' => $line['product']->name,
                    'item_summary' => '',
                    'type' => 'item',
                    'quantity' => $line['qty'],
                    'unit_price' => (float)$line['product']->price,
                    'amount' => round($line['qty'] * (float)$line['product']->price, 2),
                    'taxes' => json_encode([(string)$vat->id]),
                    'unit_id' => $line['product']->unit_id,
                    'product_id' => $line['product']->id,
                    'field_order' => $order + 1,
                    'created_at' => $issue->copy()->setTime(10, 0)->toDateTimeString(),
                    'updated_at' => $issue->copy()->setTime(10, 0)->toDateTimeString(),
                ]);
            }

            $paid = $this->payments($cid, $invoiceId, $number, $status, $total, $issue, $today, $currencyId, $adminId, $accounts, $methods);

            if ($status !== 'draft') {
                DB::table('invoices')->where('id', $invoiceId)->update(['due_amount' => round($total - $paid, 2)]);
            }

            $created[] = ['id' => $invoiceId, 'number' => $number, 'client' => $p['client'], 'issue' => $issue, 'lines' => $p['lines'], 'status' => $status, 'total' => $total];
            $next++;
        }

        return $created;
    }

    /** Creates the payments of an invoice and returns the paid amount. */
    protected function payments(int $cid, int $invoiceId, string $number, string $status, float $total, Carbon $issue, Carbon $today, int $currencyId, ?int $adminId, array $accounts, array $methods): float
    {
        if (!in_array($status, ['paid', 'partial'], true)) {
            return 0.0;
        }

        if ($status === 'partial') {
            $parts = [round($total * mt_rand(30, 70) / 100, 0)];
        } elseif ($total > 400 && mt_rand(1, 100) <= 12) {
            $first = round($total * mt_rand(40, 60) / 100, 0);
            $parts = [$first, round($total - $first, 2)];
        } else {
            $parts = [$total];
        }

        $paidOn = $issue->copy()->addDays(mt_rand(1, 22));
        $paidTotal = 0.0;

        foreach ($parts as $n => $amount) {
            if ($paidOn->gt($today)) {
                $paidOn = $today->copy();
            }

            $roll = mt_rand(1, 100);
            $cash = $roll > 90;
            $accountId = $cash ? null : ($roll <= 68 ? $accounts[0] : $accounts[1]);
            $at = $paidOn->copy()->setTime(mt_rand(9, 17), mt_rand(0, 59))->toDateTimeString();

            $paymentId = DB::table('payments')->insertGetId([
                'company_id' => $cid,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'gateway' => 'Offline',
                'currency_id' => $currencyId,
                'default_currency_id' => $currencyId,
                'exchange_rate' => 1,
                'status' => 'complete',
                'paid_on' => $at,
                'remarks' => ($cash ? 'Плащане в брой' : 'Банков превод') . ' по фактура ' . $number . (count($parts) > 1 ? ($n === 0 ? ' - първа вноска' : ' - остатък') : ($status === 'partial' ? ' - частично плащане' : '')),
                'offline_method_id' => $cash ? $methods['cash'] : $methods['transfer'],
                'bank_account_id' => $accountId,
                'added_by' => $adminId,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            if ($accountId) {
                $this->tx[] = ['account' => $accountId, 'date' => $paidOn->toDateString(), 'type' => 'Cr', 'amount' => $amount,
                    'payment_id' => $paymentId, 'invoice_id' => $invoiceId, 'expense_id' => null, 'relation' => 'payment',
                    'related' => (string)$paymentId, 'title' => 'payment-credited'];
            }

            $paidTotal += $amount;
            $paidOn = $paidOn->copy()->addDays(mt_rand(5, 20));
        }

        return round($paidTotal, 2);
    }

    /* ------------------------------------------------------------------ credit notes */

    protected function creditNotes(int $cid, $setting, int $currencyId, ?int $adminId, array $invoices, $products, $vat, Carbon $today): void
    {
        $candidates = array_values(array_filter($invoices, fn($i) => $i['status'] === 'paid' && $i['issue']->lt(Carbon::parse('2026-08-01'))));

        if (count($candidates) < 5) {
            return;
        }

        $next = $this->nextNumber('credit_notes', 'original_credit_note_number', $cid);
        $reasons = [
            'Корекция на цена по договореност с клиента',
            'Отстъпка за лоялност',
            'Частичен отказ от услугата',
            'Коригирано количество - отчетени по-малко часове',
            'Рекламация по качеството на изготвения отчет',
        ];
        $rate = (float)$vat->rate_percent;
        $step = intdiv(count($candidates), 5);

        for ($k = 0; $k < 5; $k++) {
            $invoice = $candidates[$k * $step + mt_rand(0, max(0, $step - 1))];
            $line = $invoice['lines'][0];
            $unitPrice = (float)$line['product']->price;
            $issue = $invoice['issue']->copy()->addDays(mt_rand(12, 40));
            $total = round($unitPrice * (1 + $rate / 100), 2);
            $number = NumberFormat::creditNote($next, $setting);

            $id = DB::table('credit_notes')->insertGetId([
                'company_id' => $cid,
                'client_id' => $invoice['client'],
                'cn_number' => $number,
                'original_credit_note_number' => str_pad((string)$next, (int)$setting->credit_note_digit, '0', STR_PAD_LEFT),
                'invoice_id' => $invoice['id'],
                'issue_date' => $issue->toDateString(),
                'due_date' => $issue->copy()->addDays(15)->toDateString(),
                'discount' => 0,
                'discount_type' => 'percent',
                'sub_total' => $unitPrice,
                'total' => $total,
                'adjustment_amount' => 0,
                'currency_id' => $currencyId,
                'status' => 'open',
                'recurring' => 'no',
                'note' => $reasons[$k] . ' (фактура ' . $invoice['number'] . ').',
                'added_by' => $adminId,
                'calculate_tax' => 'after_discount',
                'created_at' => $issue->copy()->setTime(11, 0)->toDateTimeString(),
                'updated_at' => $issue->copy()->setTime(11, 0)->toDateTimeString(),
            ]);

            DB::table('credit_note_items')->insert([
                'credit_note_id' => $id,
                'item_name' => $line['product']->name,
                'type' => 'item',
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'amount' => $unitPrice,
                'taxes' => json_encode([(string)$vat->id]),
                'item_summary' => $reasons[$k],
                'unit_id' => $line['product']->unit_id,
                'product_id' => $line['product']->id,
                'created_at' => $issue->copy()->setTime(11, 0)->toDateTimeString(),
                'updated_at' => $issue->copy()->setTime(11, 0)->toDateTimeString(),
            ]);

            DB::table('invoices')->where('id', $invoice['id'])->update(['credit_note' => 1]);
            $next++;
        }
    }

    /* ------------------------------------------------------------------ expenses */

    protected function expenses(int $cid, int $currencyId, ?int $adminId, array $employeeIds, array $accounts, Carbon $today): void
    {
        $categories = [];

        foreach (self::CATEGORIES as $name) {
            $categories[$name] = DB::table('expenses_category')->where('company_id', $cid)->where('category_name', $name)->value('id')
                ?: DB::table('expenses_category')->insertGetId([
                    'company_id' => $cid, 'category_name' => $name, 'added_by' => $adminId,
                    'created_at' => self::START . ' 09:00:00', 'updated_at' => self::START . ' 09:00:00',
                ]);
        }

        $rows = [];

        // recurring monthly expenses
        for ($m = Carbon::parse(self::START)->startOfMonth(), $i = 0; $m->lte($today); $m->addMonth(), $i++) {
            $label = self::MONTHS_BG[$m->month - 1] . ' ' . $m->year;
            $growth = 1 + 0.06 * $i / 12;
            $limit = $m->isSameMonth($today) ? 26 : 28;
            $day = fn(int $d) => $m->copy()->day(min($d, $limit));

            $rows[] = ['Наем на офис, бул. България 81 - ' . $label, 'Мегастрой Имоти ЕООД', 'Наем и офис', round(1850 * ($m->gte(Carbon::parse('2026-01-01')) ? 1.03 : 1), 2), $day(3), true];
            $rows[] = ['Работни заплати - ' . $label, 'Служители на Демо Финанс Груп АД', 'Заплати и осигуровки', round(7400 * $growth, 2), $day(25), true];
            $rows[] = ['Осигуровки и данъци върху доходите - ' . $label, 'НАП и НОИ', 'Заплати и осигуровки', round(2600 * $growth, 2), $day(20), true];
            $rows[] = ['Електроенергия и вода - ' . $label, 'Енерго-Про Продажби АД', 'Комунални услуги', round(mt_rand(300, 430), 2), $day(12), true];
            $rows[] = ['Интернет и мобилни услуги - ' . $label, 'А1 България ЕАД', 'Комунални услуги', round(mt_rand(105, 140), 2), $day(10), true];
            $rows[] = ['Облачни услуги и офис пакет - ' . $label, 'Майкрософт Ирландия Оперейшънс Лимитид', 'Софтуер и абонаменти', round(185 * $growth, 2), $day(7), true];
        }

        // random expenses up to the target count, spread over the months
        $flat = [];

        foreach (self::RANDOM_EXPENSES as $category => $items) {
            foreach ($items as $item) {
                $flat[] = [$category, $item];
            }
        }

        $start = Carbon::parse(self::START);
        $span = $start->diffInDays($today);

        while (count($rows) < self::EXPENSE_COUNT) {
            [$category, [$item, $supplier, $min, $max]] = $flat[mt_rand(0, count($flat) - 1)];
            $date = $start->copy()->addDays(mt_rand(0, $span));
            $rows[] = [$item . ' - ' . self::MONTHS_BG[$date->month - 1] . ' ' . $date->year, $supplier, $category, round(mt_rand($min * 100, $max * 100) / 100, 2), $date, false];
        }

        usort($rows, fn($a, $b) => $a[4] <=> $b[4]);

        foreach ($rows as [$item, $supplier, $category, $price, $date, $fixed]) {
            $age = $date->diffInDays($today);
            $r = mt_rand(1, 100);

            if ($age > 20) {
                $status = $r <= 93 ? 'approved' : ($r <= 97 ? 'rejected' : 'pending');
            } else {
                $status = $r <= 40 ? 'approved' : ($r <= 95 ? 'pending' : 'rejected');
            }

            if ($fixed && $status !== 'approved' && $age > 5) {
                $status = 'approved';
            }

            $bank = null;

            if ($status === 'approved') {
                $roll = mt_rand(1, 100);
                $bank = $fixed ? $accounts[0] : ($roll <= 65 ? $accounts[0] : ($roll <= 85 ? $accounts[1] : null));
            }

            $at = $date->copy()->setTime(mt_rand(9, 17), mt_rand(0, 59))->toDateTimeString();

            $id = DB::table('expenses')->insertGetId([
                'company_id' => $cid,
                'item_name' => $item,
                'purchase_date' => $date->toDateString(),
                'purchase_from' => $supplier,
                'price' => $price,
                'currency_id' => $currencyId,
                'default_currency_id' => $currencyId,
                'exchange_rate' => 1,
                'user_id' => $fixed || empty($employeeIds) ? $adminId : $employeeIds[mt_rand(0, count($employeeIds) - 1)],
                'status' => $status,
                'can_claim' => 0,
                'category_id' => $categories[$category],
                'created_by' => $adminId,
                'description' => 'Разход за ' . mb_strtolower($category) . ': ' . $item . '. Доставчик: ' . $supplier . '.',
                'added_by' => $adminId,
                'approver_id' => $status === 'approved' ? $adminId : null,
                'bank_account_id' => $bank,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            if ($bank) {
                $this->tx[] = ['account' => $bank, 'date' => $date->toDateString(), 'type' => 'Dr', 'amount' => $price,
                    'payment_id' => null, 'invoice_id' => null, 'expense_id' => $id, 'relation' => 'expense',
                    'related' => $item, 'title' => 'expense-added'];
            }
        }
    }

    /* ------------------------------------------------------------------ bank transactions */

    /** Writes the opening + running balance transactions and the final balances of the two accounts. */
    protected function bankTransactions(int $cid, array $accounts, ?int $adminId): void
    {
        foreach ($accounts as $accountId) {
            $items = array_values(array_filter($this->tx, fn($t) => $t['account'] === $accountId));
            usort($items, fn($a, $b) => [$a['date'], $a['type'] === 'Dr' ? 1 : 0] <=> [$b['date'], $b['type'] === 'Dr' ? 1 : 0]);

            $running = 0.0;
            $min = 0.0;

            foreach ($items as $t) {
                $running += $t['type'] === 'Cr' ? $t['amount'] : -$t['amount'];
                $min = min($min, $running);
            }

            // opening balance high enough that the account never goes negative
            $opening = max(8000, ceil((-$min + 4000) / 1000) * 1000);
            $balance = $opening;
            $rows = [[
                'company_id' => $cid, 'bank_account_id' => $accountId, 'payment_id' => null, 'invoice_id' => null, 'expense_id' => null,
                'amount' => $opening, 'type' => 'Cr', 'added_by' => $adminId, 'memo' => 'Начално салдо',
                'transaction_relation' => 'bank', 'transaction_related_to' => null, 'title' => 'bank-account-created',
                'transaction_date' => self::START, 'bank_balance' => $opening,
                'created_at' => self::START . ' 09:00:00', 'updated_at' => self::START . ' 09:00:00',
            ]];

            foreach ($items as $t) {
                $balance += $t['type'] === 'Cr' ? $t['amount'] : -$t['amount'];
                $rows[] = [
                    'company_id' => $cid, 'bank_account_id' => $accountId, 'payment_id' => $t['payment_id'], 'invoice_id' => $t['invoice_id'],
                    'expense_id' => $t['expense_id'], 'amount' => $t['amount'], 'type' => $t['type'], 'added_by' => $adminId, 'memo' => null,
                    'transaction_relation' => $t['relation'], 'transaction_related_to' => $t['related'], 'title' => $t['title'],
                    'transaction_date' => $t['date'], 'bank_balance' => round($balance, 2),
                    'created_at' => $t['date'] . ' 12:00:00', 'updated_at' => $t['date'] . ' 12:00:00',
                ];
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('bank_transactions')->insert($chunk);
            }

            DB::table('bank_accounts')->where('id', $accountId)->update(['opening_balance' => $opening, 'bank_balance' => round($balance, 2)]);
        }
    }

    /* ------------------------------------------------------------------ helpers */

    protected function nextNumber(string $table, string $column, int $cid): int
    {
        return (int)DB::table($table)->where('company_id', $cid)->max(DB::raw("CAST($column AS UNSIGNED)")) + 1;
    }

    /** @return int[] distinct random indexes */
    protected function pick(int $total, int $count): array
    {
        $indexes = [];

        while (count($indexes) < min($count, $total)) {
            $indexes[mt_rand(0, $total - 1)] = true;
        }

        return array_keys($indexes);
    }

    /** Index in [0, $total) with a bias to the first (bigger) clients. */
    protected function weightedIndex(int $total): int
    {
        $weights = [];

        for ($i = 0; $i < $total; $i++) {
            $weights[$i] = max(1, $total - $i);
        }

        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $i => $w) {
            $roll -= $w;

            if ($roll <= 0) {
                return $i;
            }
        }

        return 0;
    }

}
