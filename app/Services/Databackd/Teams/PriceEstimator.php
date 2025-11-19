<?php

namespace App\Services\Databackd\Teams;

use App\Models\Databackd\SpecialistProfile;
use App\Scopes\CompanyScope;

/**
 * Indicative monthly price of a team: sum over roles of (count x average hourly rate x hours per week x 4.33).
 */
class PriceEstimator
{

    /** Fallback hourly rates (EUR) when the company has no specialist of the role. */
    const DEFAULT_RATES = [
        'financial_analyst' => 25,
        'accountant' => 20,
        'controller' => 30,
        'business_analyst' => 28,
        'data_analyst' => 30,
        'team_lead' => 35,
    ];

    const FULL_TIME_HOURS = 40;
    const WEEKS_PER_MONTH = 4.33;

    protected ?int $companyId;

    public function __construct(?int $companyId = null)
    {
        $this->companyId = $companyId ?? (company()->id ?? null);
    }

    /**
     * @param array<string, int> $roles role => number of people (SpecialistProfile::ROLES)
     * @param string $engagement hours_per_week|full_time
     * @param int|null $hoursPerWeek hours per week PER PERSON for hours_per_week (ignored for full_time = 40)
     * @return float EUR per month, rounded to 2 decimals
     */
    public function estimate(array $roles, string $engagement, ?int $hoursPerWeek): float
    {
        $hours = $engagement === 'full_time' ? self::FULL_TIME_HOURS : max(0, (int)$hoursPerWeek);
        $rates = $this->averageRates();
        $total = 0.0;

        foreach ($roles as $role => $count) {
            $rate = $rates[$role] ?? self::DEFAULT_RATES[$role] ?? 25;
            $total += max(0, (int)$count) * $rate * $hours * self::WEEKS_PER_MONTH;
        }

        return round($total, 2);
    }

    /** @return array<string, float> role => average hourly rate of the company's specialists (profiles with a rate > 0) */
    public function averageRates(): array
    {
        if (!$this->companyId) {
            return [];
        }

        return SpecialistProfile::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->companyId)
            ->where('hourly_rate', '>', 0)
            ->groupBy('role')
            ->selectRaw('role, AVG(hourly_rate) as rate')
            ->pluck('rate', 'role')
            ->map(fn($r) => round((float)$r, 2))
            ->all();
    }

}
