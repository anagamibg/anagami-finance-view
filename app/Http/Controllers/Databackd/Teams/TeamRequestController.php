<?php

namespace App\Http\Controllers\Databackd\Teams;

use App\DataTables\Databackd\Teams\TeamRequestsDataTable;
use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;
use App\Http\Requests\Databackd\Teams\TeamRequest\AssignMemberRequest;
use App\Http\Requests\Databackd\Teams\TeamRequest\ChangeStatusRequest;
use App\Http\Requests\Databackd\Teams\TeamRequest\SaveTeamRequestRequest;
use App\Models\Databackd\AnalysisRequest;
use App\Models\Databackd\SpecialistProfile;
use App\Models\Databackd\TeamRequest;
use App\Models\Databackd\TeamRequestMember;
use App\Models\Databackd\TeamRequestStatusLog;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectTimeLog;
use App\Models\User;
use App\Notifications\Databackd\TeamRequestCreated;
use App\Notifications\Databackd\TeamRequestStatusChanged;
use App\Services\Databackd\Teams\PriceEstimator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Team requests: a client orders a team of finance specialists (1, 2, 5, 10, 20, 50+ people).
 * Clients see only their own requests and no admin controls; the admin reviews the request,
 * changes its status, assigns specialists and creates a project.
 */
class TeamRequestController extends AccountBaseController
{

    public function __construct()
    {
        parent::__construct();

        // set here: the user's locale is applied by the parent middleware, before this one
        $this->middleware(function ($request, $next) {
            $this->pageTitle = __('databackd-teams-requests.title');

            return $next($request);
        });
    }

    // ------------------------------------------------------------------
    // Screens
    // ------------------------------------------------------------------

    public function index(TeamRequestsDataTable $dataTable)
    {
        if (!request()->ajax()) {
            $base = $this->scopedQuery();

            $this->totalRequests = (clone $base)->count();
            $this->activeRequests = (clone $base)->where('status', 'active')->count();
            $this->pendingRequests = (clone $base)->whereIn('status', ['new', 'reviewing', 'proposed'])->count();
            $this->activeValue = (float)(clone $base)->where('status', 'active')->sum('monthly_price_estimate');

            $this->statuses = TeamRequest::STATUSES;
            $this->sizes = TeamRequest::SIZES;
            $this->clients = $this->isClient() ? collect() : $this->clientOptions();
            $this->isClient = $this->isClient();
            $this->canManage = $this->canManage();
        }

        return $dataTable->render('databackd.teams.requests.index', $this->data);
    }

    public function create()
    {
        $this->pageTitle = __('databackd-teams-requests.create_title');
        $this->isClient = $this->isClient();
        $this->clients = $this->isClient() ? collect() : $this->clientOptions();
        $this->roleKeys = SpecialistProfile::ROLES;
        $this->sizes = ['1', '2', '5', '10', '20', '50'];
        $this->defaultRoles = ['financial_analyst' => 2, 'accountant' => 2, 'controller' => 1];
        $this->defaultSize = '5';
        $this->minStart = now()->addDays(7)->format(company()->date_format);

        return view('databackd.teams.requests.create', $this->data);
    }

    /**
     * Live price (AJAX, GET): roles[role]=count, engagement, hours_per_week (per person).
     */
    public function estimate(Request $request)
    {
        $estimator = new PriceEstimator(company()->id);
        $engagement = $request->input('engagement') === 'full_time' ? 'full_time' : 'hours_per_week';
        $hours = $engagement === 'full_time' ? PriceEstimator::FULL_TIME_HOURS : max(0, min(40, (int)$request->input('hours_per_week')));
        $roles = [];

        foreach ((array)$request->input('roles', []) as $role => $count) {
            if (in_array($role, SpecialistProfile::ROLES, true) && (int)$count > 0) {
                $roles[$role] = min(1000, (int)$count);
            }
        }

        $price = $estimator->estimate($roles, $engagement, $hours);
        $months = max(1, min(60, (int)$request->input('duration_months', 1)));
        $people = array_sum($roles);

        $breakdown = [];

        foreach ($roles as $role => $count) {
            $breakdown[] = [
                'role' => __('databackd-teams-common.role.' . $role),
                'count' => $count,
                'amount' => $estimator->estimate([$role => $count], $engagement, $hours),
            ];
        }

        return Reply::dataOnly([
            'status' => 'success',
            'price' => $price,
            'formatted' => number_format($price, 0, ',', ' '),
            'total' => round($price * $months, 2),
            'total_formatted' => number_format($price * $months, 0, ',', ' '),
            'people' => $people,
            'people_label' => trans_choice('databackd-teams-requests.people', $people, ['count' => $people]),
            'hours' => $hours,
            'breakdown' => $breakdown,
        ]);
    }

    public function store(SaveTeamRequestRequest $request)
    {
        $roles = $request->cleanRoles();
        $engagement = $request->engagement;
        $hours = $engagement === 'full_time' ? PriceEstimator::FULL_TIME_HOURS : (int)$request->hours_per_week;
        $clientId = $this->isClient() ? user()->id : (int)$request->client_id;

        $teamRequest = DB::transaction(function () use ($request, $roles, $engagement, $hours, $clientId) {
            $teamRequest = new TeamRequest();
            $teamRequest->company_id = company()->id;
            $teamRequest->client_id = $clientId;
            $teamRequest->title = trim($request->title);
            $teamRequest->team_size = $request->team_size;
            $teamRequest->custom_size = $request->team_size === 'custom' ? (int)$request->custom_size : null;
            $teamRequest->roles = $roles;
            $teamRequest->engagement = $engagement;
            $teamRequest->hours_per_week = $hours;
            $teamRequest->start_date = companyToYmd($request->start_date);
            $teamRequest->duration_months = (int)$request->duration_months;
            $teamRequest->description = trim($request->description);
            $teamRequest->status = 'new';
            $teamRequest->monthly_price_estimate = (new PriceEstimator(company()->id))->estimate($roles, $engagement, $hours);
            $teamRequest->created_by = user()->id;
            $teamRequest->save();

            $this->writeLog($teamRequest, null, 'new', null);

            return $teamRequest;
        });

        $this->notifyAdmins($teamRequest);

        return Reply::redirect(route('databackd.teams.requests.show', $teamRequest->id), __('databackd-teams-requests.messages.created'));
    }

    public function show($id)
    {
        $teamRequest = $this->scopedQuery()->with(['client', 'project'])->findOrFail($id);

        $this->teamRequest = $teamRequest;
        $this->pageTitle = $teamRequest->title;
        $this->isClient = $this->isClient();
        $this->canManage = $this->canManage();

        $this->logs = $teamRequest->statusLogs()->with('user')->get();
        $this->creator = $teamRequest->created_by ? User::withoutGlobalScopes()->find($teamRequest->created_by) : null;
        $this->members = $teamRequest->members()->with('specialist.user')->get();
        $this->load = $this->specialistLoad($this->members->pluck('specialist_profile_id')->all());
        $this->assignedHours = (int)$this->members->sum('hours_per_week');
        $this->rolesRows = collect($teamRequest->roles ?: [])->filter(fn($c) => (int)$c > 0);
        $this->endDate = $teamRequest->start_date && $teamRequest->duration_months
            ? $teamRequest->start_date->copy()->addMonthsNoOverflow($teamRequest->duration_months)
            : null;
        $this->totalPrice = $teamRequest->monthly_price_estimate !== null
            ? $teamRequest->monthly_price_estimate * max(1, (int)$teamRequest->duration_months)
            : null;

        $this->timeMonthLabel = __('databackd-teams-common.months.' . now()->month) . ' ' . now()->year;
        // audit 2026-10-30 (G04): project time logs only with the timelogs module
        $this->timeLogs = in_array('timelogs', user_modules()) ? $this->hoursThisMonth($teamRequest) : null;

        $this->isOffer = in_array($teamRequest->status, TeamRequest::OFFER_STATUSES, true);
        $this->clientActions = $this->isClient ? $teamRequest->allowedTransitions(true) : [];

        if ($this->canManage) {
            $this->statusOptions = $teamRequest->allowedTransitions();
            $this->canCreateProject = $teamRequest->status === 'active' && !$teamRequest->project_id;
            $this->specialistOptions = $this->specialistOptions($this->members->pluck('specialist_profile_id')->all());
            $this->roleKeys = SpecialistProfile::ROLES;
        }

        return view('databackd.teams.requests.show', $this->data);
    }

    // ------------------------------------------------------------------
    // Admin actions
    // ------------------------------------------------------------------

    /**
     * Status change. Admin: along TeamRequest::TRANSITIONS, with an optional monthly price when sending the proposal.
     * Client (own requests only, 404 otherwise): accept / decline the proposal or withdraw the request.
     */
    public function changeStatus(ChangeStatusRequest $request, $id)
    {
        $isClient = $this->isClient();
        abort_403(!$isClient && !$this->canManage());

        $this->scopedQuery()->findOrFail($id); // 404 for requests outside the user's scope
        $to = $request->status;

        // AFV (S12 review): the row stays locked until the change is saved, so a client "accept" and an admin change
        // cannot both pass canMoveTo() on the same old status
        DB::beginTransaction();
        $teamRequest = $this->scopedQuery()->lockForUpdate()->findOrFail($id);

        // a client accepts / declines exactly the offer shown on the page - not one the admin changed after it was opened
        if ($isClient && $request->filled('seen_version') && (int)$request->seen_version !== (int)optional($teamRequest->updated_at)->timestamp) {
            DB::rollBack();

            return Reply::error(__('databackd-teams-requests.messages.changed_meanwhile'));
        }

        if ($teamRequest->status === $to) {
            DB::rollBack();

            return Reply::error(__('databackd-teams-requests.messages.status_same'));
        }

        // AFV (S12): no jumps such as completed -> new or a client activating a request without a proposal
        if (!$teamRequest->canMoveTo($to, $isClient)) {
            DB::rollBack();

            return Reply::error(__('databackd-teams-requests.messages.status_not_allowed', [
                'from' => __('databackd-teams-common.team_status.' . $teamRequest->status),
                'to' => __('databackd-teams-common.team_status.' . $to),
            ]));
        }

        $note = trim((string)$request->note) !== '' ? trim($request->note) : null;
        $from = $teamRequest->status;

        if ($isClient) {
            $action = $from === 'proposed' ? ($to === 'active' ? 'accepted' : 'declined') : 'withdrawn';
            $prefix = __('databackd-teams-requests.client_action.log_' . $action);
            $note = $note ? $prefix . ' ' . $note : $prefix;
        }
        elseif ($to === 'proposed' && $request->filled('offer_price')) {
            $offer = round((float)$request->offer_price, 2);
            $teamRequest->monthly_price_estimate = $offer;
            $offerLine = __('databackd-teams-requests.status.offer_log', ['price' => number_format($offer, 2, ',', ' ')]);
            $note = $note ? $offerLine . "\n" . $note : $offerLine;
        }

        try {
            $this->writeLog($teamRequest, $from, $to, $note);
            $teamRequest->status = $to;
            $teamRequest->save();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if ($isClient) {
            $this->notifyAdminsOfClientAction($teamRequest, $note);
        }
        else {
            $this->notifyClient($teamRequest, $note);
        }

        return Reply::success(__($isClient ? 'databackd-teams-requests.client_action.done_' . $action : 'databackd-teams-requests.messages.status_changed'));
    }

    /** Add (or update) / remove a specialist of the request. */
    public function assign(AssignMemberRequest $request, $id)
    {
        $this->adminOnly();
        $teamRequest = TeamRequest::findOrFail($id);
        $profile = SpecialistProfile::with('user')->find($request->specialist_profile_id);

        if (!$profile) {
            return Reply::error(__('databackd-teams-requests.messages.specialist_missing'));
        }

        if ($request->action === 'remove') {
            TeamRequestMember::where('team_request_id', $teamRequest->id)
                ->where('specialist_profile_id', $profile->id)
                ->delete();

            if ($teamRequest->project_id && $profile->user_id) {
                ProjectMember::where('project_id', $teamRequest->project_id)->where('user_id', $profile->user_id)->delete();
            }

            return Reply::success(__('databackd-teams-requests.messages.removed'));
        }

        TeamRequestMember::updateOrCreate(
            ['team_request_id' => $teamRequest->id, 'specialist_profile_id' => $profile->id],
            [
                'company_id' => company()->id,
                'role' => $request->filled('role') ? $request->role : $profile->role,
                'hours_per_week' => (int)$request->hours_per_week,
            ]
        );

        // The project already exists: the new specialist joins its team
        if ($teamRequest->project_id && $profile->user_id) {
            $this->addProjectMember($teamRequest->project_id, $profile);
        }

        return Reply::success(__('databackd-teams-requests.messages.assigned'));
    }

    /** Create a AFV project for an active request; assigned specialists become project members. */
    public function createProject($id)
    {
        $this->adminOnly();
        $teamRequest = TeamRequest::with('members.specialist')->findOrFail($id);

        if ($teamRequest->status !== 'active' || $teamRequest->project_id) {
            return Reply::error(__('databackd-teams-requests.messages.project_not_allowed'));
        }

        $project = DB::transaction(function () use ($teamRequest) {
            $start = $teamRequest->start_date ?? now();

            $project = new Project();
            $project->company_id = company()->id;
            $project->project_name = $teamRequest->title;
            $project->project_summary = '<p>' . e($teamRequest->description ?: __('databackd-teams-requests.project.summary', ['id' => $teamRequest->id])) . '</p>';
            $project->project_admin = user()->id;
            $project->client_id = $teamRequest->client_id;
            $project->start_date = $start->format('Y-m-d');
            $project->deadline = $start->copy()->addMonthsNoOverflow(max(1, (int)$teamRequest->duration_months))->format('Y-m-d');
            $project->status = 'in progress';
            $project->completion_percent = 0;
            $project->public = 0;
            $project->currency_id = company()->currency_id;
            $project->manual_timelog = 'enable';
            $project->client_view_task = 'enable';
            $project->allow_client_notification = 'disable';
            $project->calculate_task_progress = 'task_completion';
            $project->save();

            foreach ($teamRequest->members as $member) {
                if ($member->specialist?->user_id) {
                    $this->addProjectMember($project->id, $member->specialist);
                }
            }

            $teamRequest->project_id = $project->id;
            $teamRequest->save();

            return $project;
        });

        return Reply::redirect(route('projects.show', $project->id), __('databackd-teams-requests.messages.project_created'));
    }

    public function destroy($id)
    {
        $this->adminOnly();
        $teamRequest = TeamRequest::findOrFail($id);

        DB::transaction(function () use ($teamRequest) {
            // Members and status logs are removed by the foreign keys; analysis requests are only detached
            AnalysisRequest::where('team_request_id', $teamRequest->id)->update(['team_request_id' => null]);
            $teamRequest->delete();
        });

        return Reply::success(__('databackd-teams-requests.messages.deleted'));
    }

    // ------------------------------------------------------------------
    // Public helpers (also usable by other screens)
    // ------------------------------------------------------------------

    /**
     * Current load of specialists: sum of hours_per_week of their assignments in ACTIVE requests
     * divided by weekly_capacity_hours.
     *
     * @param int[]|null $profileIds limit to these profiles (null = all of the company)
     * @return array<int, array{hours: int, capacity: int, percent: int}> specialist_profile_id => load
     */
    public function specialistLoad(?array $profileIds = null): array
    {
        $hours = TeamRequestMember::query()
            ->join('team_requests', 'team_requests.id', '=', 'team_request_members.team_request_id')
            ->where('team_requests.status', 'active')
            ->when($profileIds !== null, fn($q) => $q->whereIn('team_request_members.specialist_profile_id', $profileIds))
            ->groupBy('team_request_members.specialist_profile_id')
            ->selectRaw('team_request_members.specialist_profile_id as profile_id, SUM(team_request_members.hours_per_week) as hours')
            ->pluck('hours', 'profile_id');

        $profiles = SpecialistProfile::query()
            ->when($profileIds !== null, fn($q) => $q->whereIn('id', $profileIds))
            ->get(['id', 'weekly_capacity_hours']);

        $result = [];

        foreach ($profiles as $profile) {
            $capacity = max(1, (int)$profile->weekly_capacity_hours ?: 40);
            $used = (int)($hours[$profile->id] ?? 0);
            $result[$profile->id] = ['hours' => $used, 'capacity' => $capacity, 'percent' => (int)round($used / $capacity * 100)];
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    protected function isClient(): bool
    {
        return in_array('client', user_roles());
    }

    protected function canManage(): bool
    {
        return in_array('admin', user_roles());
    }

    protected function adminOnly(): void
    {
        abort_403(!$this->canManage());
    }

    /** Requests visible to the current user: a client sees only his own. */
    protected function scopedQuery()
    {
        $query = TeamRequest::query();

        if ($this->isClient()) {
            $query->where('team_requests.client_id', user()->id);
        }

        return $query;
    }

    protected function clientOptions()
    {
        return User::allClients();
    }

    protected function writeLog(TeamRequest $teamRequest, ?string $from, string $to, ?string $note): void
    {
        $log = new TeamRequestStatusLog();
        $log->company_id = $teamRequest->company_id;
        $log->team_request_id = $teamRequest->id;
        $log->from_status = $from;
        $log->to_status = $to;
        $log->note = $note;
        $log->user_id = user()->id ?? null;
        $log->created_at = now();
        $log->save();
    }

    protected function addProjectMember(int $projectId, SpecialistProfile $profile): void
    {
        if (!$profile->user_id || ProjectMember::where('project_id', $projectId)->where('user_id', $profile->user_id)->exists()) {
            return;
        }

        $member = new ProjectMember();
        $member->user_id = $profile->user_id;
        $member->project_id = $projectId;
        $member->hourly_rate = (float)$profile->hourly_rate;
        $member->save();
    }

    /** New request: notify all admins of the company (never fail the request because of a notification error). */
    protected function notifyAdmins(TeamRequest $teamRequest): void
    {
        try {
            $admins = User::allAdmins(company()->id)->reject(fn($admin) => $admin->id === user()->id);

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new TeamRequestCreated($teamRequest));
            }
        } catch (\Throwable $e) {
            Log::warning('Team request #' . $teamRequest->id . ': admin notification failed - ' . $e->getMessage());
        }
    }

    /** AFV (S12): the client accepted / declined the proposal or withdrew the request - tell the admins. */
    protected function notifyAdminsOfClientAction(TeamRequest $teamRequest, ?string $note): void
    {
        try {
            $admins = User::allAdmins(company()->id)->reject(fn($admin) => $admin->id === user()->id);

            if ($admins->isNotEmpty()) {
                Notification::send($admins, new TeamRequestStatusChanged($teamRequest, $note, true));
            }
        } catch (\Throwable $e) {
            Log::warning('Team request #' . $teamRequest->id . ': admin notification failed - ' . $e->getMessage());
        }
    }

    /** Status changed: notify the client. */
    protected function notifyClient(TeamRequest $teamRequest, ?string $note): void
    {
        try {
            $client = User::withoutGlobalScopes()->find($teamRequest->client_id);

            if ($client) {
                $client->notify(new TeamRequestStatusChanged($teamRequest, $note));
            }
        } catch (\Throwable $e) {
            Log::warning('Team request #' . $teamRequest->id . ': client notification failed - ' . $e->getMessage());
        }
    }

    /**
     * Hours worked this month on the linked project (null without a project).
     *
     * @return array{total: float, users: array<int, array{name: string, hours: float}>}|null
     */
    protected function hoursThisMonth(TeamRequest $teamRequest): ?array
    {
        if (!$teamRequest->project_id) {
            return null;
        }

        $rows = ProjectTimeLog::query()
            ->where('project_id', $teamRequest->project_id)
            ->whereBetween('start_time', [now()->startOfMonth(), now()->endOfMonth()])
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(CAST(COALESCE(total_minutes, 0) AS UNSIGNED)) as minutes')
            ->pluck('minutes', 'user_id');

        $names = User::withoutGlobalScopes()->whereIn('id', $rows->keys())->pluck('name', 'id');

        $users = $rows->map(fn($minutes, $userId) => ['name' => $names[$userId] ?? '#' . $userId, 'hours' => round($minutes / 60, 1)])
            ->sortByDesc('hours')
            ->values()
            ->all();

        return ['total' => round($rows->sum() / 60, 1), 'users' => $users];
    }

    /**
     * Specialists that can still be assigned, with role and load for the select.
     *
     * @param int[] $excludeIds profiles already in the request
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function specialistOptions(array $excludeIds)
    {
        $load = $this->specialistLoad();

        return SpecialistProfile::with('user')
            ->whereNotIn('id', $excludeIds)
            ->get()
            ->filter(fn($profile) => $profile->user)
            ->sortBy(fn($profile) => $profile->user->name)
            ->map(fn($profile) => [
                'id' => $profile->id,
                'name' => $profile->user->name,
                'role' => $profile->role,
                'seniority' => $profile->seniority,
                'available' => (bool)$profile->is_available,
                'percent' => $load[$profile->id]['percent'] ?? 0,
                'free_hours' => max(0, ($load[$profile->id]['capacity'] ?? 40) - ($load[$profile->id]['hours'] ?? 0)),
            ])
            ->values();
    }

}
