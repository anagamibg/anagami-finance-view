<?php

namespace App\Http\Controllers\Databackd\Teams;

use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;
use App\Models\Databackd\AnalysisRequest;
use App\Models\Databackd\SpecialistProfile;
use App\Models\Databackd\TeamRequest;
use App\Models\Databackd\TeamRequestMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Specialists directory: cards with role, seniority, rate and utilisation; profile editing for admins.
 */
class SpecialistController extends AccountBaseController
{

    /** Hours per week assumed for every open analysis request assigned to a specialist */
    const ANALYSIS_HOURS = 5;

    /** Analysis request statuses that count as open work */
    const OPEN_ANALYSIS_STATUSES = ['new', 'in_progress', 'review'];

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            // set here: the user's locale is applied by the parent middleware, before this one
            $this->pageTitle = __('databackd-teams-specialists.title');
            abort_403(in_array('client', user_roles()) || !(in_array('admin', user_roles()) || in_array('employee', user_roles())));
            return $next($request);
        });
    }

    /**
     * Utilisation of specialists: hours per week booked in active team requests plus
     * ANALYSIS_HOURS for each open analysis request assigned to the specialist's user.
     *
     * @param iterable<SpecialistProfile> $profiles
     * @return array<int, array{team_hours: int, analysis_count: int, hours: int, capacity: int, pct: float, status: string}>
     *         keyed by specialist profile id; pct is not capped, status is available|busy|unavailable
     */
    public static function utilisation(iterable $profiles): array
    {
        $profiles = collect($profiles);

        $teamHours = TeamRequestMember::query()
            ->whereIn('specialist_profile_id', $profiles->pluck('id'))
            ->whereIn('team_request_id', TeamRequest::where('status', 'active')->select('id'))
            ->selectRaw('specialist_profile_id, sum(hours_per_week) as hours')
            ->groupBy('specialist_profile_id')
            ->pluck('hours', 'specialist_profile_id');

        $analysisCounts = AnalysisRequest::query()
            ->whereIn('assigned_to', $profiles->pluck('user_id'))
            ->whereIn('status', self::OPEN_ANALYSIS_STATUSES)
            ->selectRaw('assigned_to, count(*) as cnt')
            ->groupBy('assigned_to')
            ->pluck('cnt', 'assigned_to');

        $result = [];

        foreach ($profiles as $profile) {
            $team = (int)($teamHours[$profile->id] ?? 0);
            $analysis = (int)($analysisCounts[$profile->user_id] ?? 0);
            $hours = $team + $analysis * self::ANALYSIS_HOURS;
            $capacity = (int)$profile->weekly_capacity_hours;
            $pct = $capacity > 0 ? round($hours / $capacity * 100, 1) : ($hours > 0 ? 100.0 : 0.0);

            $status = !$profile->is_available ? 'unavailable' : ($pct >= 100 ? 'busy' : 'available');

            $result[$profile->id] = [
                'team_hours' => $team,
                'analysis_count' => $analysis,
                'hours' => $hours,
                'capacity' => $capacity,
                'pct' => $pct,
                'status' => $status,
            ];
        }

        return $result;
    }

    public function index()
    {
        $this->specialists = SpecialistProfile::with('user')
            ->get()
            ->filter(fn($profile) => $profile->user)
            ->sortBy(fn($profile) => mb_strtolower($profile->user->name))
            ->values();

        $this->utilisation = self::utilisation($this->specialists);
        $this->canEdit = $this->isAdmin();
        $this->canAdd = $this->canEdit && $this->candidates()->isNotEmpty();
        $this->roles = SpecialistProfile::ROLES;
        $this->seniorities = SpecialistProfile::SENIORITIES;

        return view('databackd.teams.specialists.index', $this->data);
    }

    /**
     * Edit form (modal). AFV (S12): id 0 = new profile for an employee without one - there was no way to add
     * a specialist from the UI (only the demo seeder created profiles), so the Teams service could not be used
     * by a real company. Reuses the existing edit/update routes (routes/databackd/** belongs to another package).
     */
    public function edit($id)
    {
        abort_403(!$this->isAdmin());

        if ((int)$id === 0) {
            $this->candidates = $this->candidates();
            abort_if($this->candidates->isEmpty(), 404);

            $this->profile = new SpecialistProfile([
                'role' => 'financial_analyst', 'seniority' => 'mid', 'hourly_rate' => 0,
                'weekly_capacity_hours' => 40, 'is_available' => true, 'skills' => [],
            ]);
        }
        else {
            $this->profile = SpecialistProfile::with('user')->findOrFail($id);
        }

        $this->roles = SpecialistProfile::ROLES;
        $this->seniorities = SpecialistProfile::SENIORITIES;

        return view('databackd.teams.specialists.form', $this->data);
    }

    public function update(Request $request, $id)
    {
        abort_403(!$this->isAdmin());

        $isNew = (int)$id === 0;
        $profile = $isNew ? new SpecialistProfile() : SpecialistProfile::findOrFail($id);
        $candidateIds = $isNew ? $this->candidates()->pluck('id')->all() : [];

        $validator = Validator::make($request->all(), [
            'user_id' => $isNew ? ['required', 'integer', Rule::in($candidateIds)] : ['nullable'],
            'role' => ['required', Rule::in(SpecialistProfile::ROLES)],
            'seniority' => ['required', Rule::in(SpecialistProfile::SENIORITIES)],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'weekly_capacity_hours' => ['required', 'integer', 'min:1', 'max:80'],
            'languages' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'user_id' => __('databackd-teams-specialists.employee'),
            'role' => __('databackd-teams-specialists.role'),
            'seniority' => __('databackd-teams-specialists.seniority'),
            'hourly_rate' => __('databackd-teams-specialists.hourly_rate_label'),
            'weekly_capacity_hours' => __('databackd-teams-specialists.weekly_capacity'),
            'languages' => __('databackd-teams-specialists.languages_label'),
            'skills' => __('databackd-teams-specialists.skills_label'),
        ]);

        if ($validator->fails()) {
            return Reply::formErrors($validator);
        }

        $skills = collect(explode(',', (string)$request->skills))
            ->map(fn($skill) => trim($skill))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($isNew) {
            $profile->company_id = company()->id;
            $profile->user_id = (int)$request->user_id;
        }

        $profile->role = $request->role;
        $profile->seniority = $request->seniority;
        $profile->hourly_rate = $request->hourly_rate;
        $profile->weekly_capacity_hours = (int)$request->weekly_capacity_hours;
        $profile->languages = filled($request->languages) ? trim($request->languages) : null;
        $profile->skills = $skills;
        $profile->is_available = $request->boolean('is_available');
        $profile->save();

        return Reply::success(__($isNew ? 'databackd-teams-specialists.added' : 'databackd-teams-specialists.saved'));
    }

    /** Active employees of the company that have no specialist profile yet. */
    private function candidates()
    {
        return User::withRole('employee')
            ->where('users.status', 'active')
            ->whereNotIn('users.id', SpecialistProfile::query()->select('user_id'))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name']);
    }

    private function isAdmin(): bool
    {
        return in_array('admin', user_roles());
    }

}
