<?php

namespace App\DataTables\Databackd\Teams;

use App\DataTables\BaseDataTable;
use App\Helper\Common;
use App\Models\Databackd\TeamRequest;
use Illuminate\Support\Str;
use Yajra\DataTables\Html\Column;

/**
 * List of team requests. A client sees only his own requests.
 */
class TeamRequestsDataTable extends BaseDataTable
{

    public function dataTable($query)
    {
        $datatables = datatables()->eloquent($query);

        $datatables->editColumn('title', function ($row) {
            $created = $row->created_at?->timezone($this->company->timezone)->translatedFormat($this->company->date_format);

            return '<a href="' . route('databackd.teams.requests.show', $row->id) . '" class="text-darkest-grey f-w-500" title="' . e($row->title) . '">'
                . e(Str::limit($row->title, 60)) . '</a>'
                . '<span class="text-lightest f-12 d-block">#' . $row->id . ($created ? ' &middot; ' . $created : '') . '</span>';
        });

        $datatables->addColumn('client_name', function ($row) {
            if (!$row->client_name) {
                return '--';
            }

            return '<span class="text-darkest-grey">' . e($row->client_name) . '</span>'
                . ($row->client_company ? '<span class="text-lightest f-12 d-block">' . e($row->client_company) . '</span>' : '');
        });

        $datatables->editColumn('team_size', function ($row) {
            $count = $row->team_size === 'custom' ? (int)$row->custom_size : (int)$row->team_size;
            $label = trans_choice('databackd-teams-requests.people', $count, ['count' => $count]);

            return '<span class="text-darkest-grey">' . e($label . ($row->team_size === '50' ? '+' : '')) . '</span>';
        });

        $datatables->editColumn('engagement', function ($row) {
            if ($row->engagement === 'full_time') {
                return '<span class="text-darkest-grey">' . __('databackd-teams-requests.full_time') . '</span>';
            }

            return '<span class="text-darkest-grey">' . __('databackd-teams-requests.hours_week', ['hours' => (int)$row->hours_per_week]) . '</span>';
        });

        $datatables->editColumn('status', fn($row) => view('databackd.teams.requests._status', ['status' => $row->status])->render());

        $datatables->editColumn('monthly_price_estimate', function ($row) {
            if ($row->monthly_price_estimate === null) {
                return '<span class="text-lightest">' . __('databackd-teams-requests.no_value') . '</span>';
            }

            return '<span class="f-w-500 text-darkest-grey">' . number_format((float)$row->monthly_price_estimate, 0, ',', ' ') . ' €</span>';
        });

        $datatables->editColumn('start_date', function ($row) {
            return $row->start_date
                ? '<span class="text-darkest-grey">' . $row->start_date->translatedFormat($this->company->date_format) . '</span>'
                : '<span class="text-lightest">' . __('databackd-teams-requests.no_value') . '</span>';
        });

        $datatables->addColumn('action', function ($row) {
            $action = '<div class="task_view"><div class="dropdown">
                <a class="task_view_more d-flex align-items-center justify-content-center dropdown-toggle" type="link"
                    id="dropdownMenuLink-' . $row->id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="icon-options-vertical icons"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink-' . $row->id . '" tabindex="0">';

            $action .= '<a href="' . route('databackd.teams.requests.show', $row->id) . '" class="dropdown-item"><i class="fa fa-eye mr-2"></i>' . __('databackd-teams-requests.actions.view') . '</a>';

            if (in_array('admin', user_roles())) {
                $action .= '<a class="dropdown-item delete-team-request" href="javascript:;" data-request-id="' . $row->id . '"><i class="fa fa-trash mr-2"></i>' . __('databackd-teams-requests.actions.delete') . '</a>';
            }

            return $action . '</div></div></div>';
        });

        $datatables->smart(false);
        \App\DataTables\Databackd\ColumnWhitelist::apply($datatables, $this->getColumns()); // S6/P3: search / order only by the declared columns
        $datatables->setRowId(fn($row) => 'row-' . $row->id);
        $datatables->rawColumns(['title', 'client_name', 'team_size', 'engagement', 'status', 'monthly_price_estimate', 'start_date', 'action']);

        return $datatables;
    }

    public function query()
    {
        $request = $this->request();

        $model = TeamRequest::query()
            ->leftJoin('users', 'users.id', '=', 'team_requests.client_id')
            ->leftJoin('client_details', 'client_details.user_id', '=', 'team_requests.client_id')
            ->select(
                'team_requests.id', 'team_requests.title', 'team_requests.client_id', 'team_requests.team_size', 'team_requests.custom_size',
                'team_requests.engagement', 'team_requests.hours_per_week', 'team_requests.status', 'team_requests.monthly_price_estimate',
                'team_requests.start_date', 'team_requests.created_at',
                'users.name as client_name', 'client_details.company_name as client_company'
            );

        // A client never sees requests of other clients
        if (in_array('client', user_roles())) {
            $model->where('team_requests.client_id', user()->id);
        }
        elseif ($request->client != 'all' && !is_null($request->client) && $request->client != '') {
            $model->where('team_requests.client_id', (int)$request->client);
        }

        if ($request->status != 'all' && !is_null($request->status) && $request->status != '') {
            $model->where('team_requests.status', $request->status);
        }

        if ($request->size != 'all' && !is_null($request->size) && $request->size != '') {
            $model->where('team_requests.team_size', $request->size);
        }

        if ($request->searchText != '') {
            $term = Common::safeString($request->searchText);

            $model->where(function ($query) use ($term) {
                $query->where('team_requests.title', 'like', '%' . $term . '%')
                    ->orWhere('users.name', 'like', '%' . $term . '%')
                    ->orWhere('client_details.company_name', 'like', '%' . $term . '%');
            });
        }

        return $model;
    }

    public function html()
    {
        return $this->setBuilder('team-requests-table', 0);
    }

    protected function getColumns()
    {
        return [
            __('databackd-teams-requests.columns.title') => ['data' => 'title', 'name' => 'team_requests.id', 'title' => __('databackd-teams-requests.columns.title')],
            __('databackd-teams-requests.columns.client') => ['data' => 'client_name', 'name' => 'users.name', 'title' => __('databackd-teams-requests.columns.client'), 'visible' => !in_array('client', user_roles())],
            __('databackd-teams-requests.columns.size') => ['data' => 'team_size', 'name' => 'team_requests.team_size', 'title' => __('databackd-teams-requests.columns.size')],
            __('databackd-teams-requests.columns.engagement') => ['data' => 'engagement', 'name' => 'team_requests.engagement', 'title' => __('databackd-teams-requests.columns.engagement')],
            __('databackd-teams-requests.columns.status') => ['data' => 'status', 'name' => 'team_requests.status', 'title' => __('databackd-teams-requests.columns.status')],
            __('databackd-teams-requests.columns.price') => ['data' => 'monthly_price_estimate', 'name' => 'team_requests.monthly_price_estimate', 'title' => __('databackd-teams-requests.columns.price'), 'className' => 'text-right'],
            __('databackd-teams-requests.columns.start') => ['data' => 'start_date', 'name' => 'team_requests.start_date', 'title' => __('databackd-teams-requests.columns.start')],
            Column::computed('action', __('databackd-teams-requests.columns.actions'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->addClass('text-right pr-20'),
        ];
    }

}
