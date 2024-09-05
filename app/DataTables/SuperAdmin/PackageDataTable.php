<?php

namespace App\DataTables\SuperAdmin;

use App\DataTables\BaseDataTable;
use App\Models\Module;
use App\Models\SuperAdmin\Package;
use App\Models\SuperAdmin\PackageSetting;
use Yajra\DataTables\Html\Column;

class PackageDataTable extends BaseDataTable
{

    private $editPackagesPermission;
    private $deletePackagesPermission;

    public function __construct()
    {
        parent::__construct();

        $this->editPackagesPermission = user()->permission('edit_packages');
        $this->deletePackagesPermission = user()->permission('delete_packages');
    }

    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {

        $modulesAll = Module::where('module_name', '<>', 'settings')
            ->where('module_name', '<>', 'dashboards')
            ->where('module_name', '<>', 'restApi')
            ->whereNotIn('module_name', Module::disabledModuleArray())
            ->get();

        // AFV (S8): hidden from the Module queries above by SuperAdminModuleScope
        $databackdModules = Module::databackdModules()->get();

        $packageSetting = PackageSetting::first();

        return datatables()
            ->eloquent($query)
            ->addColumn('action', function ($row) {

                if ($this->editPackagesPermission == 'all' || $this->deletePackagesPermission == 'all' ) {
                    $action = '<div class="task_view">

                    <div class="dropdown">
                        <a class="task_view_more d-flex align-items-center justify-content-center dropdown-toggle" type="link"
                            id="dropdownMenuLink-' . $row->id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="icon-options-vertical icons"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink-' . $row->id . '" tabindex="0">';

                    if ($this->editPackagesPermission == 'all') {
                        $action .= '<a class="dropdown-item openRightModal" href="' . route('superadmin.packages.edit', $row->id) . '" >
                            <i class="fa fa-edit mr-2"></i>
                            ' . trans('app.edit') . '
                        </a>';
                    }

                    if ($row->default == 'no' && $this->deletePackagesPermission == 'all') {
                        $action .= '<a class="dropdown-item delete-table-row" href="javascript:;" data-toggle="tooltip"  data-order-id="' . $row->id . '">
                            <i class="fa fa-trash mr-2"></i>
                            ' . trans('app.delete') . '
                        </a>';

                    }


                        $action .= '</div>
                        </div>
                    </div>';

                    return $action;
                }

                return '';

            })
            ->editColumn('monthly_price', function ($row) {
                return ($row->default === 'no' && $row->monthly_status == '1' && !$row->is_free) ? global_currency_format($row->monthly_price, $row->currency_id) : '--';
            })
            ->editColumn('annual_price', function ($row) {
                return ($row->default === 'no' && $row->annual_status == '1' && !$row->is_free) ? global_currency_format($row->annual_price, $row->currency_id) : '--';
            })
            ->editColumn('price', function ($row) {
                return ($row->default === 'lifetime' && $row->annual_status == '0' && !$row->is_free) ? global_currency_format($row->price, $row->currency_id) : '--';
            })
            // databackd: name + one line of compact badges (default / trial / recommended / private)
            ->editColumn('name', function ($row) use ($packageSetting) {
                $badges = [];

                if ($row->default == 'yes') {
                    $badges[] = '<span class="dbk-badge is-info no-dot" data-toggle="tooltip" data-original-title="' . e(__('superadmin.packages.defaultMessage')) . '">' . __('databackd-packages.list.default') . '</span>';
                }

                if ($row->default == 'trial') {
                    $active = $packageSetting && $packageSetting->status == 'active';
                    $badges[] = '<span class="dbk-badge ' . ($active ? 'is-good' : 'is-bad') . '" data-toggle="tooltip" data-original-title="' . e(__('superadmin.packages.trialMessage')) . '">'
                        . __('databackd-packages.list.trial') . ($active ? ' · ' . __('databackd-packages.list.trial_days', ['days' => $packageSetting->no_of_days]) : ' · ' . __('app.inactive')) . '</span>';
                }

                if ($row->is_recommended) {
                    $badges[] = '<span class="dbk-badge is-warning no-dot"><i class="bi bi-star"></i>' . __('databackd-packages.list.recommended') . '</span>';
                }

                if ($row->is_private) {
                    $badges[] = '<span class="dbk-badge no-dot"><i class="bi bi-lock"></i>' . __('databackd-packages.list.private') . '</span>';
                }

                return '<div class="dbk-pkg-name">' . e($row->name) . '</div>'
                    . ($badges ? '<div class="dbk-pkg-badges">' . implode('', $badges) . '</div>' : '');
            })
            // databackd: the monthly / annual / lifetime prices of the package in one cell (same conditions as the columns above)
            ->addColumn('price_summary', function ($row) {
                if ($row->is_free) {
                    return '<span class="dbk-badge is-good no-dot">' . __('databackd-packages.list.free') . '</span>';
                }

                $lines = [];

                if ($row->default === 'no' && $row->monthly_status == '1') {
                    $lines[] = [$row->monthly_price, __('databackd-packages.list.per_month')];
                }

                if ($row->default === 'no' && $row->annual_status == '1') {
                    $lines[] = [$row->annual_price, __('databackd-packages.list.per_year')];
                }

                if ($row->default === 'lifetime' && $row->annual_status == '0') {
                    $lines[] = [$row->price, __('databackd-packages.list.lifetime')];
                }

                if (!$lines) {
                    return '<span class="text-lightest">—</span>';
                }

                return implode('', array_map(fn($line) => '<div class="dbk-pkg-price"><span>' . global_currency_format($line[0], $row->currency_id) . '</span> <small>' . $line[1] . '</small></div>', $lines));
            })
            // databackd: employees / clients / invoices / storage limits as a compact list
            ->addColumn('limits', function ($row) {
                $number = fn($value) => ((int)$value === -1) ? __('databackd-packages.list.unlimited') : number_format((float)$value, 0, ',', ' ');
                $storage = ($row->max_storage_size == -1) ? __('databackd-packages.list.unlimited') : $row->max_storage_size . ' ' . strtoupper($row->storage_unit);
                $rows = [
                    __('app.menu.employees') => $number($row->max_employees),
                    __('app.menu.clients') => $number($row->max_clients),
                    __('app.menu.invoices') => $number($row->max_invoices),
                    __('databackd-packages.list.storage') => $storage,
                ];

                return '<dl class="dbk-pkg-limits">' . implode('', array_map(fn($label, $value) => '<dt>' . e($label) . '</dt><dd>' . e($value) . '</dd>', array_keys($rows), $rows)) . '</dl>';
            })
            ->setRowClass(function ($row) use ($packageSetting) {
                if ($row->default == 'trial') {

                    return ($packageSetting->status == 'active' && !user()->dark_theme) ? 'bg-light-grey' : '';
                }
            })
            ->editColumn('max_storage_size', function ($row) {
                if ($row->max_storage_size == -1) {
                    return __('superadmin.unlimited');
                }

                return $row->max_storage_size . ' (' . strtoupper($row->storage_unit) . ')';
            })

            // databackd: plain text - the column is kept only for exports (the table shows the "limits" column)
            ->editColumn('max_employees', function ($row) {
                return __('app.menu.employees') . ': ' . $row->max_employees . ', ' . __('app.menu.clients') . ': ' . $row->max_clients . ', ' . __('app.menu.invoices') . ': ' . $row->max_invoices;
            })
            // databackd: a summary ("all modules" or "X of N" + the missing ones) instead of N checkmarks; the full list is collapsible
            ->editColumn('module_in_package', function ($row) use ($modulesAll) {
                $modules = (array) json_decode($row->module_in_package, true);
                $included = $modulesAll->filter(fn($module) => in_array($module->module_name, $modules));
                $missing = $modulesAll->reject(fn($module) => in_array($module->module_name, $modules));
                $label = fn($module) => e(__('modules.module.' . $module->module_name));

                if ($included->isEmpty()) {
                    return '<span class="dbk-badge is-bad">' . __('databackd-packages.list.no_modules') . '</span>';
                }

                if ($missing->isEmpty()) {
                    $html = '<span class="dbk-badge is-good">' . __('databackd-packages.list.all_modules', ['count' => $modulesAll->count()]) . '</span>';
                }
                else {
                    $html = '<span class="dbk-badge is-warning">' . __('databackd-packages.list.of_modules', ['count' => $included->count(), 'total' => $modulesAll->count()]) . '</span>'
                        . '<div class="dbk-pkg-chips"><span class="dbk-pkg-chips-label">' . __('databackd-packages.list.without') . '</span>'
                        . $missing->map(fn($module) => '<span class="dbk-chip dbk-pkg-off"><i class="fa fa-times"></i>' . $label($module) . '</span>')->implode('')
                        . '</div>';
                }

                return $html . '<details class="dbk-pkg-details"><summary>' . __('databackd-packages.list.show_included', ['count' => $included->count()]) . '</summary>'
                    . '<div class="dbk-pkg-chips">' . $included->map(fn($module) => '<span class="dbk-chip">' . $label($module) . '</span>')->implode('') . '</div></details>';
            })
            // AFV (S8): the "Financial analysis" modules of the package (✓/✗ like the module_in_package column)
            ->addColumn('databackd_modules', function ($row) use ($databackdModules) {
                $modules = (array) json_decode($row->module_in_package, true);
                $string = '';

                foreach ($databackdModules as $module) {
                    $on = in_array($module->module_name, $modules);
                    $string .= '<span class="dbk-chip ' . ($on ? 'dbk-pkg-on' : 'dbk-pkg-off') . '" title="' . e(databackd_module_description($module->module_name)) . '">'
                        . '<i class="fa ' . ($on ? 'fa-check' : 'fa-times') . '"></i>' . e(databackd_module_label($module->module_name)) . '</span>';
                }

                return '<div class="dbk-pkg-chips dbk-pkg-chips-stack">' . $string . '</div>';
            })
            ->rawColumns(['action', 'module_in_package', 'databackd_modules', 'name', 'price_summary', 'limits']);
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\SuperAdmin\Package $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(Package $model)
    {
        return $model->newQuery()->where(function ($query) {
            if (request()->has('searchText')) {
                $search_term = request()->get('searchText');
                $query->where('name', 'LIKE', '%' . $search_term . '%')->orWhere('description', 'LIKE', '%' . $search_term . '%');
            }
        });
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        return $this->setBuilder('package-table', 1)
            ->parameters([
                'initComplete' => 'function () {
                   window.LaravelDataTables["package-table"].buttons().container()
                    .appendTo("#table-actions");
                }',
                'fnDrawCallback' => 'function( oSettings ) {
                     $(".fa-question-circle").popover();
                    $("body").tooltip({
                        selector: \'[data-toggle="tooltip"]\'
                    });
                }',
            ]);
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        return [
            '#' => ['data' => 'id', 'name' => 'id', 'visible' => false],
            'sort' => ['data' => 'sort', 'name' => 'sort', 'visible' => false, 'exportable' => false],
            // databackd: package | price | limits | modules | financial analysis; the separate price/limit columns stay for exports only
            __('databackd-packages.list.package') => ['data' => 'name', 'name' => 'name', 'title' => __('databackd-packages.list.package'), 'className' => 'dbk-pkg-col-name'],
            __('databackd-packages.list.price') => ['data' => 'price_summary', 'name' => 'monthly_price', 'title' => __('databackd-packages.list.price'), 'searchable' => false, 'exportable' => false, 'printable' => false, 'className' => 'dbk-pkg-col-price'],
            __('superadmin.monthly_price') => ['data' => 'monthly_price', 'name' => 'monthly_price', 'title' => __('superadmin.monthly_price'), 'visible' => false],
            __('superadmin.annual_price') => ['data' => 'annual_price', 'name' => 'annual_price', 'title' => __('superadmin.annual_price'), 'visible' => false],
            __('superadmin.price') => ['data' => 'price', 'name' => 'price', 'title' => __('superadmin.lifetime_price'), 'visible' => false],
            __('databackd-packages.list.limits') => ['data' => 'limits', 'name' => 'max_employees', 'title' => __('databackd-packages.list.limits'), 'searchable' => false, 'exportable' => false, 'printable' => false, 'className' => 'dbk-pkg-col-limits'],
            __('superadmin.fileStorage') => ['data' => 'max_storage_size', 'name' => 'max_storage_size', 'title' => __('superadmin.fileStorage'), 'visible' => false],
            __('superadmin.max') . ' ' . __('app.menu.employees') => ['data' => 'max_employees', 'name' => 'max_employees', 'title' => __('superadmin.max'), 'visible' => false],
            __('superadmin.module_in_package') => ['data' => 'module_in_package', 'name' => 'module_in_package', 'title' => __('databackd-packages.list.modules'), 'orderable' => false, 'className' => 'dbk-pkg-col-modules'],
            // AFV (S8)
            __('databackd-packages.column') => ['data' => 'databackd_modules', 'name' => 'databackd_modules', 'title' => __('databackd-packages.column'), 'orderable' => false, 'searchable' => false, 'exportable' => false, 'printable' => false, 'className' => 'dbk-pkg-col-fin'],
            Column::computed('action', __('app.action'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(50)
                ->addClass('text-center pr-20')
        ];
    }

}
