<?php

namespace App\Console\Commands\Databackd;

use App\Models\Databackd\AnalysisRequest;
use App\Scopes\CompanyScope;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Creates the next instance of delivered / closed recurring analysis requests (monthly / quarterly).
 *
 * Manual run:  php artisan databackd:recurring-analysis [--dry-run]
 * Idempotent: a request that already has a child (parent_id = its id) is skipped.
 */
class RecurringAnalysis extends Command
{

    protected $signature = 'databackd:recurring-analysis {--dry-run : Show what would be created, write nothing}';

    protected $description = 'Create the next instance of delivered or closed recurring analysis requests';

    private const MONTHS = ['monthly' => 1, 'quarterly' => 3];

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');
        $today = today();

        $sources = AnalysisRequest::withoutGlobalScope(CompanyScope::class)
            ->whereIn('status', ['delivered', 'closed'])
            ->whereIn('recurrence', array_keys(self::MONTHS))
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('analysis_requests as children')
                    ->whereColumn('children.parent_id', 'analysis_requests.id');
            })
            ->orderBy('id')
            ->get();

        $created = 0;

        foreach ($sources as $source) {
            // AFV (S8): only companies whose package includes the Teams service
            if (!databackd_company_module((int)$source->company_id, 'databackd_teams')) {
                continue;
            }

            $months = self::MONTHS[$source->recurrence];

            $periodFrom = $source->period_from ? $this->shift($source->period_from, $months) : null;
            $periodTo = $source->period_to ? $this->shift($source->period_to, $months, $source->period_to->isSameDay($source->period_to->copy()->endOfMonth())) : null;

            // never create an instance that is overdue from the first day: due date >= today + 7 days
            $base = $source->due_date ?? $source->period_to ?? $today;
            $due = $this->shift($base, $months);
            $minimum = $today->copy()->addDays(7);

            if ($due->lt($minimum)) {
                $due = $minimum;
            }

            $this->line(sprintf(
                '%s#%d "%s" (%s, company %d) -> %s..%s, due %s',
                $dryRun ? '[dry-run] ' : '',
                $source->id,
                $source->title,
                $source->recurrence,
                $source->company_id,
                $periodFrom?->toDateString() ?? '-',
                $periodTo?->toDateString() ?? '-',
                $due->toDateString()
            ));

            if (!$dryRun) {
                AnalysisRequest::withoutGlobalScope(CompanyScope::class)->create([
                    'company_id' => $source->company_id,
                    'team_request_id' => $source->team_request_id,
                    'client_id' => $source->client_id,
                    'type' => $source->type,
                    'title' => $source->title,
                    'description' => $source->description,
                    'period_from' => $periodFrom?->toDateString(),
                    'period_to' => $periodTo?->toDateString(),
                    'due_date' => $due->toDateString(),
                    'priority' => $source->priority,
                    'recurrence' => $source->recurrence,
                    'parent_id' => $source->id,
                    'status' => 'new',
                    'assigned_to' => $source->assigned_to,
                    'created_by' => $source->created_by,
                ]);
            }

            $created++;
        }

        $this->info(($dryRun ? '[dry-run] would create ' : 'Created ') . $created . ' request(s).');

        return self::SUCCESS;
    }

    private function shift(Carbon $date, int $months, bool $endOfMonth = false): Carbon
    {
        $shifted = $date->copy()->addMonthsNoOverflow($months);

        return $endOfMonth ? $shifted->endOfMonth()->startOfDay() : $shifted->startOfDay();
    }

}
