<?php

namespace App\Http\Controllers\Databackd\Teams;

use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;
use App\Http\Requests\Databackd\Teams\AnalysisRequest\DeliverableAnalysisRequest;
use App\Http\Requests\Databackd\Teams\AnalysisRequest\MoveAnalysisRequest;
use App\Http\Requests\Databackd\Teams\AnalysisRequest\RateAnalysisRequest;
use App\Http\Requests\Databackd\Teams\AnalysisRequest\StoreAnalysisRequest;
use App\Http\Requests\Databackd\Teams\AnalysisRequest\UpdateAnalysisRequest;
use App\Models\Databackd\AnalysisRequest;
use App\Models\Databackd\AnalysisRequestComment;
use App\Models\Databackd\SpecialistProfile;
use App\Models\Databackd\TeamRequest;
use App\Models\User;
use App\Notifications\Databackd\AnalysisRequestCreated;
use App\Notifications\Databackd\AnalysisRequestStatusChanged;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Monthly analysis / report requests of the clients to the team: kanban + list, detail with result, rating and comments.
 * Clients see and use only their own requests; every staff-only action is guarded here (403 for clients).
 */
class AnalysisRequestController extends AccountBaseController
{

    /** Saved report / forecast screens a deliverable may point to (offered only when the route exists). */
    const LINK_TARGETS = [
        'saved_reports' => 'databackd.reports.saved.index',
        'pnl' => 'databackd.reports.pnl.index',
        'cashflow' => 'databackd.reports.cashflow.index',
        'dashboard' => 'databackd.reports.dashboard.index',
        'ratios' => 'databackd.analytics.ratios.index',
        'comparison' => 'databackd.analytics.comparison.index',
        'consolidation' => 'databackd.analytics.consolidation.index',
        'forecasts' => 'databackd.planning.forecasts.index',
        'budgets' => 'databackd.planning.budgets.index',
        'variance' => 'databackd.planning.variance.index',
    ];

    public function __construct()
    {
        parent::__construct();

        // translated in a middleware: the locale of the user is applied after the constructor ran
        $this->middleware(function ($request, $next) {
            $this->pageTitle = __('databackd-teams-analysis.title');

            return $next($request);
        });
    }

    // ---------------------------------------------------------------- list

    public function index()
    {
        $isClient = $this->isClient();
        $view = request('view');
        $view = in_array($view, ['kanban', 'list'], true) ? $view : ($isClient ? 'list' : 'kanban');

        $filters = [
            'client' => $isClient ? '' : (string)request('client', ''),
            'assignee' => (string)request('assignee', ''),
            'type' => (string)request('type', ''),
            'month' => (string)request('month', ''),
        ];

        if (!in_array($filters['type'], AnalysisRequest::TYPES, true)) {
            $filters['type'] = '';
        }

        $month = null;

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $filters['month'])) {
            $month = Carbon::createFromFormat('Y-m-d', $filters['month'] . '-01')->startOfDay();
        }
        else {
            $filters['month'] = '';
        }

        $query = $this->scopedQuery()->with(['client', 'assignee']);

        if ($filters['client'] !== '') {
            $query->where('client_id', (int)$filters['client']);
        }

        if ($filters['assignee'] !== '') {
            $query->where('assigned_to', (int)$filters['assignee']);
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if ($month) {
            $from = $month->copy()->startOfMonth()->toDateString();
            $to = $month->copy()->endOfMonth()->toDateString();
            $query->where(function ($q) use ($from, $to) {
                $q->whereBetween('due_date', [$from, $to])
                    ->orWhereBetween('period_from', [$from, $to]);
            });
        }

        if ($view === 'kanban') {
            $items = $query->orderByRaw('due_date is null')->orderBy('due_date')->orderByDesc('id')->get();
            $this->columns = $items->groupBy('status');
            $this->items = null;
        }
        else {
            $this->items = $query->orderByDesc('id')->paginate(20)->withQueryString();
            $this->columns = collect();
        }

        $this->view = $view;
        $this->filters = $filters;
        $this->isClient = $isClient;
        $this->canMove = !$isClient;
        $this->stats = $this->stats();
        $this->clients = $isClient ? collect() : $this->clientOptions();
        $this->specialists = $this->specialistOptions();
        $this->months = $this->monthOptions();
        $this->hasAny = $this->scopedQuery()->exists();

        return view('databackd.teams.analysis.index', $this->data);
    }

    // ---------------------------------------------------------------- create / store / edit / update

    public function create()
    {
        $this->analysis = new AnalysisRequest([
            'type' => 'report', 'priority' => 'medium', 'recurrence' => 'none',
            'due_date' => now()->addDays(14),
            'period_from' => now()->subMonth()->startOfMonth(), 'period_to' => now()->subMonth()->endOfMonth(),
        ]);

        if (!$this->isClient() && request()->filled('client_id')) {
            $this->analysis->client_id = (int)request('client_id');
        }

        // AFV (S12): preselect the team request - from the "New analysis request" button of a team request,
        // or the client's only active team (validated again on store)
        $teamQuery = TeamRequest::query()->when($this->isClient(), fn($q) => $q->where('client_id', user()->id));

        $team = null;

        if (request()->filled('team_request_id')) {
            $team = (clone $teamQuery)->find((int)request('team_request_id'));
        }
        elseif ($this->isClient()) {
            $active = (clone $teamQuery)->where('status', 'active')->limit(2)->get(['id', 'client_id']);
            $team = $active->count() === 1 ? $active->first() : null;
        }

        if ($team) {
            $this->analysis->team_request_id = $team->id;
            $this->analysis->client_id = $this->analysis->client_id ?: $team->client_id;
        }

        $this->loadFormData();
        $this->pageTitle = __('databackd-teams-analysis.new_request');

        return view('databackd.teams.analysis.form', $this->data);
    }

    public function store(StoreAnalysisRequest $request)
    {
        $analysis = new AnalysisRequest();
        $analysis->company_id = company()->id;
        $analysis->client_id = $this->isClient() ? user()->id : (int)$request->client_id;
        $analysis->created_by = user()->id;
        $analysis->status = 'new';
        $this->fill($analysis, $request);

        // AFV (S12): source data attached to the new request is kept as its first comment (same private disk and
        // access rules as every other comment attachment)
        $storedFile = null;

        if ($request->hasFile('file')) {
            try {
                $storedFile = AnalysisRequest::storeUpload($request->file('file'));
            } catch (\Throwable $e) {
                return Reply::error(e(Str::limit($e->getMessage(), 200)));
            }
        }

        $analysis->save();

        if ($storedFile) {
            $comment = new AnalysisRequestComment();
            $comment->company_id = $analysis->company_id;
            $comment->analysis_request_id = $analysis->id;
            $comment->user_id = user()->id;
            $comment->comment = __('databackd-teams-analysis.form.attachment_comment');
            $comment->file = $storedFile;
            $comment->save();
        }

        $this->notifyCreated($analysis);

        return Reply::successWithData(__('databackd-teams-analysis.messages.created'), [
            'redirectUrl' => route('databackd.teams.analysis.show', $analysis->id),
        ]);
    }

    public function edit($id)
    {
        abort_403($this->isClient());

        $this->analysis = AnalysisRequest::findOrFail($id);
        $this->loadFormData();
        $this->pageTitle = __('databackd-teams-analysis.edit_request');

        return view('databackd.teams.analysis.form', $this->data);
    }

    public function update(UpdateAnalysisRequest $request, $id)
    {
        $analysis = AnalysisRequest::findOrFail($id);

        if ($request->filled('client_id')) {
            $analysis->client_id = (int)$request->client_id;
        }

        $this->fill($analysis, $request);

        if ($request->has('assigned_to')) {
            $analysis->assigned_to = $request->filled('assigned_to') ? (int)$request->assigned_to : null;
        }

        $analysis->save();

        return Reply::successWithData(__('databackd-teams-analysis.messages.updated'), [
            'redirectUrl' => route('databackd.teams.analysis.show', $analysis->id),
        ]);
    }

    // ---------------------------------------------------------------- detail

    public function show($id)
    {
        $analysis = $this->findForUser($id);
        $analysis->load(['client', 'assignee', 'teamRequest', 'parent', 'comments.user']);

        $isClient = $this->isClient();

        $this->analysis = $analysis;
        $this->isClient = $isClient;
        $this->isStaff = !$isClient;
        $this->canRate = $isClient && in_array($analysis->status, ['delivered', 'closed'], true);
        $this->isOverdue = $this->isOverdue($analysis);
        $this->child = AnalysisRequest::where('parent_id', $analysis->id)->first(['id', 'title']);
        $this->teamRequestUrl = $analysis->team_request_id && Route::has('databackd.teams.requests.show')
            ? route('databackd.teams.requests.show', $analysis->team_request_id) : null;
        $this->specialists = $isClient ? collect() : $this->specialistOptions($analysis->assigned_to);
        $this->linkTargets = $isClient ? [] : $this->availableLinkTargets();
        // AFV (S12): render only an app path or an http(s) URL (old rows were never validated)
        $link = (string)$analysis->deliverable_link;
        $this->safeLink = ($link !== '' && ((str_starts_with($link, '/') && !str_starts_with($link, '//')) || preg_match('#^https?://#i', $link))) ? $link : null;

        // audit 2026-10-30 (G11): an app path points to a staff screen (reports/analytics/planning) that the client role never opens (403)
        if ($isClient && $this->safeLink !== null && str_starts_with($this->safeLink, '/')) {
            $this->safeLink = null;
        }
        $this->pageTitle = $analysis->title;

        return view('databackd.teams.analysis.show', $this->data);
    }

    // ---------------------------------------------------------------- status

    /**
     * Status change (kanban drag & drop and the status dropdown), staff only.
     */
    public function move(MoveAnalysisRequest $request, $id)
    {
        $analysis = AnalysisRequest::with('client')->findOrFail($id);

        $this->applyStatus($analysis, $request->status);

        // AFV (S12): not "status" - successWithData() merges the data over the response, so "status" => "delivered"
        // replaced "success" and the kanban / status dropdown treated every move as a failure (reload, no feedback)
        return Reply::successWithData(__('databackd-teams-analysis.messages.status_changed'), [
            'analysis_status' => $analysis->status,
            'label' => __('databackd-teams-common.analysis_status.' . $analysis->status),
        ]);
    }

    // ---------------------------------------------------------------- result

    /**
     * Upload the result file and / or set the link to a saved report (staff only).
     */
    public function deliverable(DeliverableAnalysisRequest $request, $id)
    {
        $analysis = AnalysisRequest::with('client')->findOrFail($id);

        if ($request->hasFile('deliverable_file')) {
            try {
                // S6/P4: stored on the private disk (AnalysisRequest::PRIVATE_DISK), not public/user-uploads -
                // see AnalysisRequest::storeUpload() for why.
                $stored = AnalysisRequest::storeUpload($request->file('deliverable_file'));
            } catch (\Throwable $e) {
                return Reply::error(e(Str::limit($e->getMessage(), 200))); // AFV (S10): escaped - the toastr renders HTML
            }

            if ($analysis->deliverable_file) {
                AnalysisRequest::deleteUpload($analysis->deliverable_file);
            }

            $analysis->deliverable_file = $stored;
        }

        $target = (string)$request->input('deliverable_route', '');
        $targets = $this->availableLinkTargets();

        if ($target !== '' && isset($targets[$target])) {
            $analysis->deliverable_link = $targets[$target];
        }
        elseif ($request->has('deliverable_link')) {
            $link = trim((string)$request->input('deliverable_link'));
            $analysis->deliverable_link = $link !== '' ? $link : null;
        }

        $analysis->save();

        if ($request->boolean('mark_delivered') && !in_array($analysis->status, ['delivered', 'closed'], true)) {
            $this->applyStatus($analysis, 'delivered');
        }

        return Reply::successWithData(__('databackd-teams-analysis.messages.deliverable_saved'), [
            'redirectUrl' => route('databackd.teams.analysis.show', $analysis->id),
        ]);
    }

    public function download($id)
    {
        $analysis = $this->findForUser($id);

        abort_if(!$analysis->deliverable_file, 404);

        // S6/P4: findForUser() above already checked ownership (404 for another client); resolveDownload() only
        // now reads the file, from the private disk first and the legacy public path as a fallback.
        $ext = pathinfo($analysis->deliverable_file, PATHINFO_EXTENSION);
        $base = Str::slug($analysis->title) ?: 'analysis-' . $analysis->id;
        $downloadName = $base . ($ext !== '' ? '.' . $ext : '');

        return AnalysisRequest::resolveDownload($analysis->deliverable_file, $downloadName);
    }

    // ---------------------------------------------------------------- rating

    public function rate(RateAnalysisRequest $request, $id)
    {
        $analysis = $this->findForUser($id);

        if (!in_array($analysis->status, ['delivered', 'closed'], true)) {
            return Reply::error(__('databackd-teams-analysis.messages.rate_not_allowed'));
        }

        $analysis->client_rating = (int)$request->client_rating;
        $analysis->client_feedback = $request->filled('client_feedback') ? trim($request->client_feedback) : null;
        $analysis->save();

        return Reply::successWithData(__('databackd-teams-analysis.messages.rated'), [
            'redirectUrl' => route('databackd.teams.analysis.show', $analysis->id),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    private function isClient(): bool
    {
        return in_array('client', user_roles());
    }

    /**
     * Requests the current user may see: staff everything of the company, a client only own ones.
     */
    private function scopedQuery()
    {
        $query = AnalysisRequest::query();

        if ($this->isClient()) {
            $query->where('client_id', user()->id);
        }

        return $query;
    }

    /**
     * 404 for requests of other clients (not 403, so the existence of the id is not revealed).
     */
    private function findForUser($id): AnalysisRequest
    {
        return $this->scopedQuery()->findOrFail($id);
    }

    private function isOverdue(AnalysisRequest $analysis): bool
    {
        return $analysis->due_date
            && !in_array($analysis->status, ['delivered', 'closed'], true)
            && $analysis->due_date->lt(today());
    }

    private function fill(AnalysisRequest $analysis, $request): void
    {
        foreach (['type', 'priority', 'recurrence'] as $field) {
            if ($request->filled($field)) {
                $analysis->{$field} = $request->input($field);
            }
        }

        if ($request->has('title')) {
            $analysis->title = trim($request->title);
        }

        if ($request->has('description')) {
            $analysis->description = $request->filled('description') ? trim($request->description) : null;
        }

        foreach (['period_from', 'period_to', 'due_date'] as $field) {
            if ($request->has($field)) {
                $analysis->{$field} = $request->filled($field)
                    ? Carbon::createFromFormat(company()->date_format, $request->input($field))->toDateString()
                    : null;
            }
        }

        if ($request->has('team_request_id')) {
            $analysis->team_request_id = $request->filled('team_request_id') ? (int)$request->team_request_id : null;
        }
    }

    /**
     * Set the status, keep delivered_at in sync and tell the client. Returns true when the status really changed.
     */
    private function applyStatus(AnalysisRequest $analysis, string $status): bool
    {
        $previous = $analysis->status;

        if ($previous === $status) {
            return false;
        }

        $analysis->status = $status;

        if ($status === 'delivered') {
            $analysis->delivered_at = now();
        }
        elseif (in_array($status, ['new', 'in_progress', 'review'], true)) {
            $analysis->delivered_at = null;
        }

        $analysis->save();

        try {
            if ($analysis->client) {
                $analysis->client->notify(new AnalysisRequestStatusChanged($analysis, $previous));
            }
        } catch (\Throwable $e) {
            Log::warning('Analysis request status notification failed: ' . $e->getMessage());
        }

        return true;
    }

    private function notifyCreated(AnalysisRequest $analysis): void
    {
        try {
            $recipients = User::allAdmins(company()->id)->where('id', '!=', user()->id);
        } catch (\Throwable $e) {
            Log::warning('Analysis request admins lookup failed: ' . $e->getMessage());

            return;
        }

        foreach ($recipients as $admin) {
            try {
                $admin->notify(new AnalysisRequestCreated($analysis));
            } catch (\Throwable $e) {
                Log::warning('Analysis request created notification failed: ' . $e->getMessage());
            }
        }
    }

    private function loadFormData(): void
    {
        $isClient = $this->isClient();

        $this->isClient = $isClient;
        $this->isEdit = $this->analysis->exists;
        $this->clients = $isClient ? collect() : $this->clientOptions();

        $teamRequests = TeamRequest::query()->orderByDesc('id');

        if ($isClient) {
            $teamRequests->where('client_id', user()->id);
        }

        $this->teamRequests = $teamRequests->get(['id', 'title', 'client_id']);
    }

    /**
     * Clients of the company (for the staff client picker / filter).
     */
    private function clientOptions()
    {
        return User::withRole('client')->orderBy('users.name')->get(['users.id', 'users.name']);
    }

    /**
     * Possible assignees: the specialists (users with a specialist profile), or all employees when there are no profiles.
     */
    private function specialistOptions(?int $alsoId = null)
    {
        $ids = SpecialistProfile::query()->pluck('user_id')->all();

        if ($alsoId) {
            $ids[] = $alsoId;
        }

        if ($ids) {
            return User::whereIn('users.id', array_unique($ids))->orderBy('users.name')->get(['users.id', 'users.name', 'users.image', 'users.email']);
        }

        return User::withRole('employee')->orderBy('users.name')->get(['users.id', 'users.name', 'users.image', 'users.email']);
    }

    /**
     * [key => route url] of the saved report / forecast screens that exist in this installation.
     */
    private function availableLinkTargets(): array
    {
        $targets = [];

        foreach (self::LINK_TARGETS as $key => $routeName) {
            // audit 2026-10-30 (G11): only screens whose package module the company has (AFV.<reports|analytics|planning>.*)
            $module = 'databackd_' . explode('.', $routeName)[1];

            if (Route::has($routeName) && databackd_module($module)) {
                $targets[$key] = route($routeName, [], false);
            }
        }

        return $targets;
    }

    /**
     * Last 18 months + the next 2 (value Y-m => label) for the month filter.
     */
    private function monthOptions(): array
    {
        $options = [];
        $cursor = now()->startOfMonth()->addMonths(2);

        for ($i = 0; $i < 21; $i++) {
            $options[$cursor->format('Y-m')] = __('databackd-teams-common.months.' . $cursor->month) . ' ' . $cursor->year;
            $cursor->subMonth();
        }

        return $options;
    }

    /**
     * KPI strip of the list page (respects the client scope, ignores the filters).
     */
    private function stats(): array
    {
        $base = fn() => $this->scopedQuery();

        $ratedCount = $base()->whereNotNull('client_rating')->count();

        return [
            'open' => $base()->whereIn('status', ['new', 'in_progress', 'review'])->count(),
            'overdue' => $base()->whereIn('status', ['new', 'in_progress', 'review'])->whereDate('due_date', '<', today())->count(),
            'delivered_month' => $base()->whereIn('status', ['delivered', 'closed'])
                ->whereBetween('delivered_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'avg_rating' => $ratedCount ? round((float)$base()->whereNotNull('client_rating')->avg('client_rating'), 1) : null,
        ];
    }

}
