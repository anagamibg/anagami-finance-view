# Одит: видимост на модули и мъртви връзки – всички находки

Генерирано от одита на 2026-10-30 (13 агента по области + 2 независими проверяващи). Обобщение и план: [README.md](README.md). Пътищата са спрямо корена на репото; кодът и описанията на находките са на английски, както ги върнаха агентите.

## G01. Сигурност: действия без проверка на права (не са свързани с пакета) (13)

### F028 · висока · няма проверка на сървъра

**Role & permissions: deleteRole, resetPermissions, update (rename), permissions and customPermissions have no permission check, so any logged-in user can delete or reset roles, including the admin role**

- Елемент: `routes/web-settings.php:241` – показва се при: Routes role-permissions.delete_role / reset_permissions / permissions / custom_permissions / update are inside the 'auth' group only. The UI (resources/views/role-permissions/ajax/create.blade.php:74 and index.blade.php:72,123,168) is reachable only with manage_role_permission_setting == 'all'.
- Води към: POST account/settings/role-permission/deleteRole, POST role-permissions/reset-permissions, PUT role-permissions/{id}, POST role-permissions/permissions, POST role-permissions/customPermissions
- Проверка/отказ: app/Http/Controllers/RolePermissionController.php:30 constructor has no middleware; only index:39, create:66, store:75 and storeRole:197 check manage_role_permission_setting. deleteRole:227 runs Role::whereId($request->roleId)->delete() with no check and no protection for the admin/employee/client roles; resetPermissions:250 and update:282 are also unchecked.
- Сценарий: An employee or client of any company sends POST /account/settings/role-permission/deleteRole with roleId set to the company's admin role id (company-scoped through HasCompany).
- Ефект: The admin role is deleted, or the employee/client role permissions are reset or renamed, by a user with no role-permission rights (privilege destruction). permissions() also returns the full permission matrix to anyone.
- Поправка: Add constructor middleware abort_403(user()->permission('manage_role_permission_setting') != 'all'), and in deleteRole refuse roles named admin, employee or client.
- Проверка от втори агент: RolePermissionController.php:30-35 constructor has no middleware. deleteRole (:227-232) runs Role::whereId($request->roleId)->delete() with no check. resetPermissions (:250), update (:282-285), permissions (:143) and customPermissions (:239) are also unchecked; only index/create/store/storeRole check manage_role_permission_setting. The routes (web-settings.php:240-245) are in the 'auth' group only, and no AFV middleware restricts them. Security issue outside the module bug class.
- Намерено от: settings

### F029 · висока · няма проверка на сървъра

**Security settings update (global reCAPTCHA + artisan cache/optimize) has no access check; any company user can change the platform-wide setting**

- Елемент: `routes/web-settings.php:226` – показва се при: The reCAPTCHA tab is shown only when user()->permission('manage_superadmin_security_settings') == 'all' && user()->is_superadmin (resources/views/security-settings/index.blade.php:26). The update route itself is only behind 'auth'.
- Води към: PUT account/settings/security-settings/{id} (SecuritySettingController@update)
- Проверка/отказ: app/Http/Controllers/SecuritySettingController.php:20 middleware is a no-op. The check exists only on the index 'recaptcha' tab (line 41). update() at line 72 writes GlobalSetting::first() and runs Artisan optimize/route:clear/cache:clear without any check, and UpdateGoogleCaptchaSetting::authorize() returns true.
- Сценарий: Any logged-in employee or client of any tenant sends PUT /account/settings/security-settings/1 without google_recaptcha_status.
- Ефект: reCAPTCHA is disabled or replaced for the whole SaaS platform, and caches are flushed, by a user with no rights. This dead-ends nowhere visible but bypasses the super-admin-only gate.
- Поправка: Start update() and verify() with abort_403(!user()->is_superadmin || user()->permission('manage_superadmin_security_settings') != 'all').
- Проверка от втори агент: SecuritySettingController.php:20-22 middleware is a pass-through. update() (:72-120) writes GlobalSetting::first() reCAPTCHA fields and runs Artisan optimize/cache clear with no check. UpdateGoogleCaptchaSetting::authorize() returns true. The route web-settings.php:226 is behind 'auth' only. Security issue outside the module bug class.
- Намерено от: settings

### F032 · средна · няма проверка на сървъра

**Ticket settings sub-controllers (types, groups, channels, agents, reply templates, email sync) have no tickets-module check and no permission check**

- Елемент: `routes/web-settings.php:124` – показва се при: UI entry points are in the ticket forms (resources/views/tickets/ajax/create.blade.php:437,444,451,567, gated by manage_ticket_agent/type/channel/groups) and on the hidden Ticket settings page. The routes are only behind 'auth'.
- Води към: ticket-agents.*, ticket_agents.update_group, ticket-groups.*, ticketTypes.*, ticketChannels.*, ticket-email-settings.update, replyTemplates.* (routes/web-settings.php:124-137)
- Проверка/отказ: No checks in the constructors: app/Http/Controllers/TicketTypeController.php:13, TicketGroupController.php:13, TicketChannelController.php:13, TicketAgentController.php:16, TicketReplyTemplatesController.php:14, TicketEmailSettingController.php:12 (only TicketSettingController.php:25 checks tickets + manage_ticket_setting)
- Сценарий: Tickets is switched off for the role or not in the package (and config-hidden for everyone). Any employee or client calls POST /account/settings/ticketTypes, /ticket-agents, or PUT /ticket-email-settings/{id} directly.
- Ефект: Ticket types, groups, channels, agents and reply templates can be created/edited/deleted, and the company's ticket IMAP email settings overwritten, without the tickets module or any ticket permission.
- Поправка: Add constructor middleware abort_403(!user()->is_superadmin && !in_array('tickets', user_modules())), plus per-action manage_ticket_type/agent/channel/groups/setting checks.
- Проверка от втори агент: TicketTypeController.php:13-18, TicketGroupController.php:13-18, TicketChannelController.php:13-18, TicketAgentController.php:16-21, TicketReplyTemplatesController.php:14-19 and TicketEmailSettingController.php:12-17 contain no abort_403 at all. Their requests (e.g. StoreTicketType, StoreTicketGroup, StoreAgentGroup, StoreTemplate) authorize() return true. Only TicketSettingController.php:25 checks tickets + manage_ticket_setting.
- Намерено от: settings

### F033 · средна · няма проверка на сървъра

**LeaveTypeController (leave types CRUD) has no leaves-module or permission check**

- Елемент: `routes/web-settings.php:169` – показва се при: UI: the '+ Add' leave type button in resources/views/leaves/ajax/create.blade.php:74/440 and leaves/ajax/edit.blade.php:413 (leaves module + manage_leave_setting), and the hidden Leave settings page
- Води към: leaveType.create/store/edit/update/destroy
- Проверка/отказ: app/Http/Controllers/LeaveTypeController.php:17 constructor has no middleware; create:29, store:43, edit:100, update:116 and destroy:182 have no abort_403 (compare LeaveSettingController.php:23)
- Сценарий: Package 'Default' lacks leaves. Any admin, employee or client calls GET/POST/DELETE /account/settings/leaveType directly.
- Ефект: Leave types of a module that is not in the package can be created, changed or deleted, by any role.
- Поправка: Add constructor middleware abort_403(!(user()->permission('manage_leave_setting') == 'all' && in_array('leaves', user_modules()))).
- Проверка от втори агент: LeaveTypeController.php:17-22 constructor has no middleware and the file contains no abort_403. StoreLeaveType::authorize() returns true. Compare LeaveSettingController.php:22-23.
- Намерено от: settings

### F034 · средна · няма проверка на сървъра

**EmployeeShiftController (shift CRUD, set default, list) has no attendance-module or permission check**

- Елемент: `routes/web-settings.php:260` – показва се при: UI: the Attendance settings 'Shift' tab (hidden; gated by AttendanceSettingController.php:38). Also the employee dashboard 'view shifts' button calls route('employee-shifts.index') (resources/views/dashboard/employee/index.blade.php:780, dashboard area).
- Води към: employee-shifts.index/create/store/edit/update/destroy, employee-shifts.set_default
- Проверка/отказ: app/Http/Controllers/EmployeeShiftController.php:17 constructor has no middleware; no abort_403 in any action (ShiftRotationController.php:29 does check attendance + manage_attendance_setting)
- Сценарий: The attendance module is switched off for the role or not in a package; any employee or client sends POST /account/settings/employee-shifts.
- Ефект: Shifts can be created, edited, deleted or set as default by any user, even without the attendance module.
- Поправка: Add constructor middleware abort_403(!in_array('attendance', user_modules())), and require manage_attendance_setting == 'all' for create/store/edit/update/destroy/setDefaultShift.
- Проверка от втори агент: EmployeeShiftController.php:17-22 constructor has no middleware, the file has no abort_403, and StoreEmployeeShift::authorize() returns true. ShiftRotationController.php:28-30 does check attendance + manage_attendance_setting.
- Намерено от: settings

### F035 · средна · няма проверка на сървъра

**Offline payment method settings controller has no checks at all, and its index renders a non-existent view (500)**

- Елемент: `routes/web-settings.php:110` – показва се при: UI: Payment gateway credentials page (resources/views/payment-gateway-settings/index.blade.php:338,346,375), shown when manage_payment_setting == 'all'
- Води към: offline-payment-setting.index/create/store/edit/update/destroy
- Проверка/отказ: app/Http/Controllers/OfflinePaymentSettingController.php:14 constructor has no middleware, and StoreRequest/UpdateRequest authorize() return true. index() at line 29 returns view('payment-gateway-credentials.index'), which does not exist, and update() at line 100 returns Reply::redirect(route('offline-payment-setting.index')).
- Сценарий: Any employee or client of a company calls POST/PUT/DELETE /account/settings/offline-payment-setting directly. Anyone opening GET /account/settings/offline-payment-setting gets a 500.
- Ефект: Offline payment methods (shown to clients when paying invoices) can be added, changed or deleted by users without manage_payment_setting. The index URL dead-ends in a 500.
- Поправка: Add constructor middleware abort_403(user()->permission('manage_payment_setting') !== 'all') like PaymentGatewayCredentialController. In update() return Reply::success(__('messages.updateSuccess')), or make index() redirect to route('payment-gateway-settings.index').
- Проверка от втори агент: OfflinePaymentSettingController.php:14-19 has no checks, and its StoreRequest/UpdateRequest authorize() return true. index() (:26-30) returns view('payment-gateway-credentials.index'), and that directory does not exist. update() (:100) returns Reply::redirect(route('offline-payment-setting.index')). easyAjax follows redirects by default (public/js/main.js), so saving the edit modal (payment-gateway-settings/edit-offline-payment-modal.blade.php:55-67) lands the admin on a 500 page.
- Намерено от: settings

### F056 · средна · няма проверка на сървъра

**POST dashboard/widget/{type} has no admin check, so any employee or client can reset the company's dashboard widgets**

- Елемент: `routes/web.php:147` – показва се при: The cog/save button is shown only for admins (employee/index.blade.php:203, admin.blade.php:93) but the route is open to every authenticated company user
- Води към: DashboardController@widget (DashboardController.php:81-98)
- Проверка/отказ: Missing: no in_array('admin', user_roles()) check and no whitelist for $dashboardType; it sets status=0 for all widgets of that type, then status=1 only for posted names
- Сценарий: Any employee or client posts to /account/dashboard/widget/admin-dashboard with an empty body.
- Ефект: All overview widgets are switched off for the whole company (the same works for every dashboard type).
- Поправка: Start widget() with abort_403(!in_array('admin', user_roles())), and validate $dashboardType against the known dashboard types.
- Проверка от втори агент: DashboardController.php:81-98 widget() has no role check and no whitelist. Route web.php:147 is open to any authenticated company user. The cog is shown only to admins (employee/index.blade.php:203, admin.blade.php:93).
- Намерено от: dashboards

### F073 · средна · няма проверка на сървъра

**LeaveTypeController and EmployeeShiftController (settings) have neither module nor permission checks**

- Елемент: `routes/web-settings.php:169` – показва се при: Route::resource('leaveType', LeaveTypeController::class). Also :260-261 employee-shifts resource + set-default
- Води към: leaveType.*, employee-shifts.*
- Проверка/отказ: app/Http/Controllers/LeaveTypeController.php:17-22 and EmployeeShiftController.php:17-22 constructors only set titles. The StoreLeaveType/StoreEmployeeShift requests return authorize() true. Sibling controllers do check: LeaveSettingController.php:22-23 (manage_leave_setting + leaves), AttendanceSettingController.php:36-38 (manage_attendance_setting + attendance)
- Сценарий: Package Default (no leaves): any user, including the employee role, can create, edit or delete leave types and office shifts via /account/settings/leaveType and /account/settings/employee-shifts
- Ефект: Module settings writable without the module or permission
- Поправка: Copy the LeaveSettingController.php:22-23 and ShiftRotationController.php:28-30 middleware.
- Проверка от втори агент: Duplicate of F033 + F034: LeaveTypeController.php:17-22 and EmployeeShiftController.php:17-22 have no checks, StoreLeaveType/StoreEmployeeShift authorize() return true, and the routes are web-settings.php:169 and :260-261.
- Намерено от: hr

### F153 · средна · няма проверка на сървъра

**EstimateTemplateController has no module check, and no permission check on index/create/store/show/update**

- Елемент: `routes/web.php:956` – показва се при: Route::resource('estimate-template', EstimateTemplateController::class) inside the account group; the only UI entry is the config-hidden Estimates page (resources/views/estimates/index.blade.php:80)
- Води към: /account/estimate-template (index, create, store, show, update, download)
- Проверка/отказ: app/Http/Controllers/EstimateTemplateController.php:31-36 constructor has no middleware; index (38-42), create (49) and show (137) have no abort_403. EstimateController gates with 'estimates' at app/Http/Controllers/EstimateController.php:44.
- Сценарий: Any logged-in user (including client role, or a company whose package or role lacks estimates) opens /account/estimate-template by URL.
- Ефект: Estimate templates can be listed, created and viewed although the Estimates module is off or hidden, so the package can be bypassed by URL.
- Поправка: Add constructor middleware abort_403(!in_array('estimates', $this->user->modules)) plus add_estimates/view_estimates checks on index/create/store/show.
- Проверка от втори агент: EstimateTemplateController.php:31-35 constructor has no middleware, and index (:38-41) and create (:49) have no abort. The routes are at web.php:955-958, and EstimateController gates on estimates.
- Намерено от: invoices-sales

### F154 · средна · няма проверка на сървъра

**ProposalTemplateController's module gate is inverted and uses a non-existent module name ('contract'), so it never blocks**

- Елемент: `routes/web.php:692` – показва се при: Route::resource('proposal-template', ProposalTemplateController::class); the UI entry is only on the config-hidden Proposals page (resources/views/proposals/index.blade.php:115)
- Води към: /account/proposal-template (index/create/store/show/edit/update/destroy/download)
- Проверка/отказ: app/Http/Controllers/ProposalTemplateController.php:35 `abort_403(in_array('contract', $this->user->modules))`. 'contract' is not a module (the module is 'contracts'), and the logic is negated. Proposals are gated by leads: app/Http/Controllers/ProposalController.php:34.
- Сценарий: Company or role without the leads module (leads are also config-hidden) opens /account/proposal-template by URL with manage_proposal_template != none (admin).
- Ефект: Proposal templates of the hidden or disabled Leads module work in full by URL.
- Поправка: Replace with abort_403(!in_array('leads', $this->user->modules));
- Проверка от втори агент: ProposalTemplateController.php:34 is abort_403(in_array('contract', $this->user->modules)). That logic is inverted, and 'contract' is not a module name, so it never blocks. ProposalController.php:34 gates on leads. Routes at web.php:690-693.
- Намерено от: invoices-sales

### F192 · средна · няма проверка на сървъра

**Report chart, data and export endpoints have no permission or module check: any logged-in user, including a client, can read company-wide finance, expense, timelog and deal data**

- Елемент: `routes/web.php:891` – показва се при: POST finance-report-chart (web.php:891), expense-report-chart (:903), task-report-chart (:875), time-log-report-chart (:881), time-log-report-time (:886); GET project-wise-timelog/report/export (:884), deal-report/chart (:910), deal-report/profile (:911), deal-report/export (:912), lead-report/total (:908), leave-report/{id} (:899), leave-report/leave-quota/{id}/{year}/{month} (:898)
- Води към: FinanceReportController::financeChartData, ExpenseReportController::expenseChartData, TaskReportController::taskChartData, TimelogReportController::timelogChartData/totalTime/exportProjectWiseTimeLog, LeadReportController::profile/averageDealSizeReport/exportDealReport/totalContact, LeaveReportController::show/employeeLeaveQuota
- Проверка/отказ: Missing. FinanceReportController.php:65, ExpenseReportController.php:41, TaskReportController.php:44, TimelogReportController.php:47, :179, :250, LeadReportController.php:72, :119, :134, :365 (only canDataTableExport), LeaveReportController.php:38, :90 have no abort_403 on a view_*_report permission or on any module. Only the index pages check.
- Сценарий: Any package and any role. An employee or client with all report permissions = none (the default), or a company whose package lacks timelogs or leaves, POSTs to /account/finance-report-chart and gets payment totals per day, project and client.
- Ефект: The data behind hidden or forbidden reports can be read directly.
- Поправка: Repeat each index permission check (for example abort_403(user()->permission('view_finance_report') != 'all')) in every chart, total and export method, plus the module middleware from F191.
- Проверка от втори агент: FinanceReportController::financeChartData (:65), ExpenseReportController::expenseChartData (:41), TaskReportController::taskChartData (:44), TimelogReportController::timelogChartData (:47), totalTime (:179) and exportProjectWiseTimeLog (:250), LeadReportController::profile (:72) and totalContact (:119), and LeaveReportController::show (:38) and employeeLeaveQuota (:90) have no abort_403. Only the index methods check.
- Намерено от: crm-reports

### F193 · средна · няма проверка на сървъра

**Ticket settings sub-controllers, TicketFile, EventFile and KnowledgeBaseCategory controllers have no permission or module check at all**

- Елемент: `routes/web-settings.php:123` – показва се при: Routes ticket-agents*, agent-groups, ticket-groups, ticketTypes, ticketChannels, ticket-email-settings, replyTemplates* (web-settings.php:123-141); ticket-files (web.php:869-870); event-files and knowledgebase-category resources
- Води към: TicketAgentController, TicketGroupController, TicketTypeController, TicketChannelController, TicketReplyTemplatesController, TicketEmailSettingController::update, TicketFileController, EventFileController, KnowledgeBaseCategoryController
- Проверка/отказ: Missing. TicketAgentController.php:16, TicketGroupController.php:13, TicketTypeController.php:13, TicketChannelController.php:13, TicketReplyTemplatesController.php:14 and TicketEmailSettingController.php:12-19 contain no abort_403 and no module check. Their FormRequests return authorize() true (e.g. app/Http/Requests/TicketAgentGroups/StoreAgentGroup.php:15). TicketFileController.php:14-71, EventFileController.php:21-55 and KnowledgeBaseCategoryController.php:13-50 have none either. Compare TicketSettingController, which checks manage_ticket_setting && tickets.
- Сценарий: Any package and any role (employee or client). Tickets, events and the knowledgebase are config-hidden or switched off. The user POSTs/PUTs/DELETEs /account/settings/ticketTypes, /account/settings/ticket-email-settings/{id} (IMAP sync settings), /account/settings/ticket-agents, /account/knowledgebase-category/{id}, and so on.
- Ефект: Company ticket settings, email sync credentials, KB categories and ticket/event files can be created, changed or deleted by unauthorised users, also when the module is not in the package.
- Поправка: Add constructor middleware abort_403(!(user()->permission('manage_ticket_setting') == 'all' && in_array('tickets', user_modules()))) to the ticket settings sub-controllers, and module plus parent-record permission checks to the TicketFile, EventFile and KnowledgeBaseCategory controllers.
- Проверка от втори агент: TicketAgent, TicketGroup, TicketType, TicketChannel, TicketReplyTemplates, TicketEmailSetting, TicketFile, EventFile and KnowledgeBaseCategory controllers contain zero abort/modules references. StoreAgentGroup::authorize() returns true. Routes web-settings.php:123-141 are under 'auth' only (:65). Compare TicketSettingController.
- Намерено от: crm-reports

### F159 · ниска · няма проверка на сървъра

**InvoiceFilesController::store has no module or permission check**

- Елемент: `routes/web.php:365` – показва се при: Route::resource('invoice-files', ...); called from the invoice forms (resources/views/invoices/ajax/create.blade.php:1152)
- Води към: invoice-files.store
- Проверка/отказ: app/Http/Controllers/InvoiceFilesController.php:15-46: no constructor middleware and no abort_403 in store
- Сценарий: User whose role has invoices switched off posts to /account/invoice-files.
- Ефект: Files can be attached to any invoice id while the Invoices module is off for the role.
- Поправка: Add constructor middleware abort_403(!in_array('invoices', $this->user->modules)) and an add_invoices/edit_invoices check in store().
- Проверка от втори агент: InvoiceFilesController.php:15-46: the constructor has no middleware, and store() has no module or permission check. Routes at web.php:364-365.
- Намерено от: invoices-sales

## G02. Връзки към профил на служител (employees.show) без модул „Служители“ и за клиенти (18)

### F001 · висока · мъртва връзка (403/404)

**Sidebar brand dropdown: own name/avatar links to employees.show, which is 403 without the employees module**

- Елемент: `resources/views/sections/sidebar.blade.php:56` – показва се при: @if(in_array('client', user_roles())) href="{{ route('profile-settings.index') }}" @elseif (user()->is_superadmin) ... @else href="{{ route('employees.show', user()->id) }}" @endif  (no module check)
- Води към: employees.show (EmployeeController@show)
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules)) in constructor middleware
- Сценарий: Any admin or employee of a company on package "Default" (companies 4, 5, 6 lack the employees module), or any employee after the company admin switches 'employees' off for the employee role
- Ефект: Clicking your own name/avatar in the sidebar brand dropdown, the first link of the sidebar, opens a 403 page
- Поправка: sidebar.blade.php:56: @elseif (in_array('employees', user_modules())) href="{{ route('employees.show', user()->id) }}" @else href="{{ route('profile-settings.index') }}" @endif
- Проверка от втори агент: resources/views/sections/sidebar.blade.php:56 falls through to route('employees.show', user()->id) for every non-client, non-superadmin user with no module check. EmployeeController.php:76-77 constructor abort_403(!in_array('employees', $this->user->modules)). Default package has no employees module (CompanyObserver createModuleSettings sets status deactive), so admins and employees there get 403.
- Намерено от: sidebar, topbar-global

### F011 · висока · мъртва връзка (403/404)

**Private dashboard profile card links to employees.show (403 without the employees module)**

- Елемент: `resources/views/dashboard/employee/index.blade.php:258` – показва се при: @if (in_array('profile', $activeWidgets)) ... <a @if(!in_array('client', user_roles())) href="{{ route('employees.show', user()->id) }}" @endif> (the profile widget is on by default: app/Models/DashboardWidget.php:109)
- Води към: employees.show
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules))
- Сценарий: Any employee or admin (private dashboard) of a company on package Default (companies 4, 5, 6)
- Ефект: Clicking the profile card on the landing dashboard gives 403
- Поправка: <a @if(!in_array('client', user_roles())) href="{{ in_array('employees', user_modules()) ? route('employees.show', user()->id) : route('profile-settings.index') }}" @endif>
- Проверка от втори агент: dashboard/employee/index.blade.php:258 links the profile card to employees.show for every non-client. The profile widget is on by default (DashboardWidget.php:109) and is not in DashboardWidget::MODULE. DashboardController::index (:61-71) renders this dashboard for anyone with the employee role, admins included. EmployeeController.php:77 returns 403 without the employees module (package Default). Duplicate of F045/F063.
- Намерено от: sidebar

### F025 · висока · мъртва връзка (403/404)

**Shared employee avatar/name links (x-employee, x-employee-image, DataTable member avatars) always point to employees.show, ignoring the module and view_employee_menu**

- Елемент: `resources/views/components/employee.blade.php:18` – показва се при: href="{{ isset($disabledLink) ? 'javascript:;' : route('employees.show', [$user->id]) }}" (also line 30); employee-image.blade.php:2 unconditional; app/DataTables/TasksDataTable.php:156 and ProjectsDataTable.php:158 use !in_array('client', user_roles()) ? route('employees.show', ...)
- Води към: employees.show
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 (employees module) and :724-726 (abort_403 for another employee unless view_employee_menu == 'all')
- Сценарий: (a) Admin in a Default-package company (no employees module) clicks an assignee avatar in the task list, a project member or a task's "Assigned to". (b) An employee with default permissions (no view_employee_menu) clicks a colleague's avatar in any package.
- Ефект: 403 page from main lists across tasks, projects, timelogs, expenses and dashboard widgets (51 views + 13 DataTables).
- Поправка: In components/employee.blade.php compute $canLink = !isset($disabledLink) && in_array('employees', user_modules()) && (user()->permission('view_employee_menu') == 'all' || $user->id == user()->id), and use 'javascript:;' otherwise. Apply the same rule in employee-image.blade.php and the DataTables that build employees.show links.
- Проверка от втори агент: components/employee.blade.php:18,30 link to employees.show unless $disabledLink is set (class App\View\Components\Employee defaults it to null). employee-image.blade.php:2, TasksDataTable.php:156 and ProjectsDataTable.php:158,171 link for every non-client. EmployeeController.php:77 checks the employees module (missing on Default). :724-726 abort_403 for another user's profile unless view_employee_menu is 'all'. Note: isset() is true for false, so passing :disabledLink="false" disables the link.
- Намерено от: topbar-global, dashboards

### F045 · висока · мъртва връзка (403/404)

**Private dashboard profile card always links to employees.show, which returns 403 when the employees module is not in the package**

- Елемент: `resources/views/dashboard/employee/index.blade.php:258` – показва се при: @if (in_array('profile', $activeWidgets)) ... <a @if(!in_array('client', user_roles())) href="{{ route('employees.show', user()->id) }}" @endif > (the 'profile' widget is status 1 by default and is not in DashboardWidget::MODULE, so it is always active)
- Води към: employees.show (EmployeeController@show)
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:76-77 abort_403(!in_array('employees', $this->user->modules)) in constructor middleware
- Сценарий: Package 'Default' (companies 4, 5, 6), which lacks employees. The company admin also has the employee role, so /account/dashboard renders the private dashboard with the profile card at the top. The same happens on any package when the admin switches employees off for a role.
- Ефект: Clicking your own name/photo card on the landing dashboard opens a 403 page.
- Поправка: Output the href only when in_array('employees', user_modules()), otherwise link to profile-settings.index.
- Проверка от втори агент: Same lines as F011: dashboard/employee/index.blade.php:258 and EmployeeController.php:76-77. Duplicate of F011.
- Намерено от: dashboards

### F046 · висока · мъртва връзка (403/404)

**Overview tab 'Pending tasks' widget links each assignee avatar to employees.show without an employees-module check**

- Елемент: `resources/views/dashboard/ajax/overview.blade.php:228-236` – показва се при: @if (in_array('tasks', user_modules()) && in_array('overdue_tasks', $activeWidgets)) ... <a href="{{ route('employees.show', $item->id) }}"> for every $task->users
- Води към: employees.show
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:76-77 abort_403(!in_array('employees', $this->user->modules))
- Сценарий: Package 'Default' (has tasks, lacks employees). The admin opens Dashboard -> Advanced dashboard (default tab Overview); any task due this month has assignees.
- Ефект: The assignee avatars are clickable and lead to a 403 page.
- Поправка: overview.blade.php:230: href="{{ in_array('employees', user_modules()) ? route('employees.show', $item->id) : 'javascript:;' }}"
- Проверка от втори агент: dashboard/ajax/overview.blade.php:213 checks tasks + overdue_tasks, and :228-235 link every assignee to employees.show. EmployeeController.php:77 returns 403 on Default, which has tasks but no employees. Admins always reach the Overview tab (DashboardController.php:214-217).
- Намерено от: dashboards

### F062 · висока · мъртва връзка (403/404)

**Employee avatars/names everywhere link to employees.show, which 403s without the employees module or without view_employee_menu=all**

- Елемент: `resources/views/components/employee.blade.php:18` – показва се при: href="{{ isset($disabledLink) ? 'javascript:;' : route('employees.show', [$user->id]) }}" (same at :30; employee-image.blade.php:2 unconditional; components/cards/task-card.blade.php:64 unconditional; app/DataTables/TasksDataTable.php:156 `!in_array('client', user_roles()) ? route('employees.show', $member->id)`; ProjectsDataTable.php:158/171; ExpensesDataTable.php:95 via components.employee; ~70 usages in total)
- Води към: employees.show (EmployeeController@show)
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules)); EmployeeController.php:724-726 `if ($viewEmployeeMenuPermission != 'all' && != 4) abort_403($id != user()->id)`
- Сценарий: (a) Package Default (companies 4,5,6, no employees module): admin clicks the assignee avatar in Tasks, a Kanban card, Project members, the Expenses 'Employee' column -> 403. (b) Any package with employees: an employee-role user of a company created after the view_employee_menu migration (role default = none, PermissionRole::employeeRolePermissions has no view_employee_menu) clicks a colleague's avatar in the task list -> 403
- Ефект: 403 page from a normal list click
- Поправка: Central helper (module on && (view_employee_menu == 'all' || own id)) used by the components and DataTables; render 'javascript:;' otherwise. Do not pass false to disabledLink: isset(false) is true.
- Проверка от втори агент: Duplicate of F025: components/employee.blade.php:18,30, employee-image.blade.php:2, components/cards/task-card.blade.php:64, TasksDataTable.php:156, ProjectsDataTable.php:158,171 and ArchiveProjectsDataTable.php:74. EmployeeController.php:77 checks the module and :724-726 view_employee_menu.
- Намерено от: hr

### F063 · висока · мъртва връзка (403/404)

**Own-profile links (sidebar brand dropdown, private dashboard profile card) 403 when the package lacks employees**

- Елемент: `resources/views/sections/sidebar.blade.php:56` – показва се при: @if(in_array('client', user_roles())) href=profile-settings.index @elseif (user()->is_superadmin) ... @else href="{{ route('employees.show', user()->id) }}" (also resources/views/dashboard/employee/index.blade.php:258 `@if(!in_array('client', user_roles())) href="{{ route('employees.show', user()->id) }}"` inside the default-active 'profile' widget)
- Води към: employees.show
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules))
- Сценарий: Package Default (no employees): admin or employee opens the sidebar brand dropdown and clicks their name/avatar, or clicks the profile card on the private dashboard
- Ефект: 403 page
- Поправка: Fall back to route('profile-settings.index') when 'employees' is not in user_modules().
- Проверка от втори агент: Duplicate of F001 + F011: sidebar.blade.php:56 and dashboard/employee/index.blade.php:258 target employees.show, which EmployeeController.php:77 blocks without the employees module.
- Намерено от: hr

### F085 · висока · мъртва връзка (403/404)

**Client profile 'Added by' (and the client-note 'Assign to' avatars) link to employees.show on packages without the employees module**

- Елемент: `resources/views/clients/ajax/profile.blade.php:172 (<x-employee :user="$client->clientDetails->addedBy" />); resources/views/clients/notes/show.blade.php:16` – показва се при: profile.blade.php:167 @if(!is_null($client->clientDetails->added_by)); components/employee.blade.php:18 and :30 always link to route('employees.show') unless disabledLink is passed; notes/show.blade.php:16 links each member when $note->type == 1
- Води към: employees.show (EmployeeController@show)
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules))
- Сценарий: Package 'Default' (companies 4, 5, 6) lacks employees: the admin opens any client that has added_by set (set by the add form, clients/ajax/create.blade.php:230-240). Also any role with employees switched off, and the client role viewing a private note shared with them
- Ефект: The 'Added by' name/avatar in the client profile leads to a 403
- Поправка: Make components/employee.blade.php itself fall back to 'javascript:;' when 'employees' is not in user_modules(), or pass :disabledLink="in_array('employees', user_modules()) ? null : true". Wrap the notes/show.blade.php:16 <a> in the same check.
- Проверка от втори агент: clients/ajax/profile.blade.php:167-172 renders <x-employee :user=addedBy>, and components/employee.blade.php:18,30 link employees.show. clients/notes/show.blade.php:16 links unconditionally. EmployeeController.php:77 gives 403 without the employees module (Default). Caveat on the suggested fix: the component tests isset($disabledLink), so passing false still disables the link; pass true/null.
- Намерено от: clients

### F086 · висока · мъртва връзка (403/404)

**Client project discussion replies link names/avatars to clients.show / employees.show with no role, module or permission check**

- Елемент: `resources/views/discussions/replies/show.blade.php:29 and :34` – показва се при: Always: <a href="{{ route(($replyUser->hasRole('client') ? 'clients.show' : 'employees.show'), $replyUser->id) }}">
- Води към: clients.show / employees.show
- Проверка/отказ: ClientController.php:67 (clients module; never present for the client role) and :614 (view_clients); EmployeeController.php:77 (employees module, never in CLIENT_MODULES)
- Сценарий: Client role: view_project_discussions = all by default (PermissionRole.php:147), DiscussionController::show:96. Any client opening a project discussion. Also a default employee (view_clients none) clicking a client's reply, and the Default package (no employees) for employee replies
- Ефект: Clicking a reply author's name or avatar gives a 403
- Поправка: Compute the target per author: client author only when !in_array('client', user_roles()) && in_array('clients', user_modules()) && view_clients in [all,added,both]; employee author only when the employees module is on (and view_employee_menu == 'all' or it is the viewer). Otherwise render plain text.
- Проверка от втори агент: discussions/replies/show.blade.php:29 and :34 always link clients.show/employees.show. Clients have view_project_discussions=ALL (PermissionRole.php:146), and DiscussionController.php:96 lets them in. Neither clients nor employees is in CLIENT_MODULES, so ClientController.php:67 and EmployeeController.php:77 return 403 for clients. Default employees (view_clients none) also get 403 on client authors. Duplicate of F107.
- Намерено от: clients

### F106 · висока · мъртва връзка (403/404)

**Project member avatars and names link to employees.show when the employees module is off**

- Елемент: `app/DataTables/ProjectsDataTable.php:171` – показва се при: $route = !in_array('client', user_roles()) ? route('employees.show', ...) (ProjectsDataTable.php:158,171); ArchiveProjectsDataTable.php:75 unconditional; projects/ajax/members.blade.php:39 <x-employee :disabledLink="$isClient"> (components/employee.blade.php:17 links unless disabledLink); projects/milestone/show.blade.php:61, projects/notes/show.blade.php:17, project-templates/ajax/members.blade.php:27, project-templates/milestone/show.blade.php:62, project-templates/task/ajax/show.blade.php:64 unconditional
- Води към: employees.show (EmployeeController@show)
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:76-78 abort_403(!in_array('employees', $this->user->modules))
- Сценарий: Package Default (no employees): an admin clicks a member avatar in the Projects list or a member name in Project -> Members; a client with milestone or note access clicks an avatar in the milestone/note detail (client never has the employees module)
- Ефект: 403 page from the main projects list and the project members tab
- Поправка: Use 'javascript:;' in the DataTables and views when !in_array('employees', user_modules()), and make components/employee.blade.php check the module itself.
- Проверка от втори агент: Confirmed for ProjectsDataTable.php:158,171 (non-client), ArchiveProjectsDataTable.php:74 (unconditional) and projects/milestone/show.blade.php:61; EmployeeController.php:76-78 returns 403 on Default. Correction: projects/ajax/members.blade.php:39 is not affected. It passes :disabledLink="$isClient", and components/employee.blade.php:18 tests isset(), which is true for false, so the member links there are always disabled.
- Намерено от: projects

### F107 · висока · мъртва връзка (403/404)

**Client role: author names in project discussions link to clients.show or employees.show, both of which return 403 for clients**

- Елемент: `resources/views/discussions/replies/show.blade.php:29` – показва се при: Unconditional href route(($replyUser->hasRole('client') ? 'clients.show' : 'employees.show'), $replyUser->id) at lines 29 and 34; DiscussionDataTable.php:34 unconditional route('employees.show', $row->last_reply_by_id) in the Discussion tab list
- Води към: clients.show / employees.show
- Проверка/отказ: app/Http/Controllers/ClientController.php:67 abort_403(!in_array('clients', ...)) and EmployeeController.php:77 abort_403(!in_array('employees', ...)); neither module is in ModuleSetting::CLIENT_MODULES, so the client role never has them
- Сценарий: Any client (default view_project_discussions=all) opens Project -> Discussion, clicks the 'last reply by' name or opens a discussion and clicks an author. Admins and employees hit the same 403 when clients/employees modules are off
- Ефект: 403 page
- Поправка: Render plain names for client viewers and when the target module is not in user_modules().
- Проверка от втори агент: Duplicate of F086: discussions/replies/show.blade.php:29,34 link unconditionally. DiscussionDataTable.php:34 links last_reply_by to employees.show unconditionally. Clients never have the clients or employees modules (ModuleSetting.php:37-51).
- Намерено от: projects

### F120 · висока · мъртва връзка (403/404)

**Assignee avatars in the task list, Waiting-approval list and Recurring-task list link to employees.show without an employees module check**

- Елемент: `app/DataTables/TasksDataTable.php:156` – показва се при: $route = !in_array('client', user_roles()) ? route('employees.show', $member->id) : 'javascript:;'. The same unconditional link is at app/DataTables/WaitingForApprovalDataTable.php:126 and app/DataTables/RecurringTasksDataTable.php:159; those two do not even exclude clients.
- Води към: employees.show
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules))
- Сценарий: Package Default lacks employees. An admin or employee opens Tasks, a project's Tasks tab, Waiting approval or Recurring tasks and clicks an assignee avatar.
- Ефект: Opens a 403 page.
- Поправка: Use $route = (!in_array('client', user_roles()) && in_array('employees', user_modules())) ? route('employees.show', $member->id) : 'javascript:;' in all four DataTables.
- Проверка от втори агент: TasksDataTable.php:156 links to employees.show for every non-client. WaitingForApprovalDataTable.php:126, RecurringTasksDataTable.php:159 and ArchiveTasksDataTable.php:121 link unconditionally, clients included. EmployeeController.php:77 aborts without employees, which Default lacks.
- Намерено от: tasks-timelogs

### F121 · висока · мъртва връзка (403/404)

**Task detail assignee avatars and 'Assigned by' link to employees.show when the employees module is off**

- Елемент: `resources/views/tasks/ajax/show.blade.php:240` – показва се при: href="{{ $isClient ? 'javascript:;' : route('employees.show', $item->id) }}" (same at line 1355 in the all-members modal). Line 294: <x-employee :user="$task->createBy" :disabledLink="$isClient"/>. Only the client role is excluded.
- Води към: employees.show
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules))
- Сценарий: Package Default (no employees). An admin or employee opens any task and clicks an assignee or the 'Assigned by' name.
- Ефект: Opens a 403 page.
- Поправка: Link only when !in_array('client', user_roles()) && in_array('employees', user_modules()). Otherwise use 'javascript:;' / :disabledLink="true". Apply the same to recurring-task/ajax/show.blade.php:80,88.
- Проверка от втори агент: tasks/ajax/show.blade.php:240 and :1355 use $isClient ? 'javascript:;' : route('employees.show'). Line 288 sets $isClient = true : null, so x-employee at :294 links for staff (components/employee.blade.php:18,29 link unless the value is null). recurring-task/ajax/show.blade.php:80 links unconditionally. EmployeeController.php:77 aborts on Default.
- Намерено от: tasks-timelogs

### F122 · висока · мъртва връзка (403/404)

**Task board cards link assignee avatars to employees.show for every role, clients included**

- Елемент: `resources/views/components/cards/task-card.blade.php:64` – показва се при: <a href="{{ route('employees.show', $item->id) }}"> for each $task->users, with no role or module condition. The board is reachable from the Tasks view switcher (resources/views/tasks/index.blade.php:298) for every role.
- Води към: employees.show
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules)). 'employees' is never among a client's modules (ModuleSetting::CLIENT_MODULES).
- Сценарий: (a) A client on any package opens Task board and clicks an avatar. (b) An admin or employee of a Default-package company does the same.
- Ефект: Opens a 403 page.
- Поправка: Render href="{{ (!in_array('client', user_roles()) && in_array('employees', user_modules())) ? route('employees.show', $item->id) : 'javascript:;' }}".
- Проверка от втори агент: components/cards/task-card.blade.php:64 links route('employees.show') with no condition. It is used by taskboard/board_data.blade.php, and the taskboard (TaskBoardController, tasks module only) is reachable from tasks/index.blade.php:298 for every role. 'employees' is not in ModuleSetting::CLIENT_MODULES (ModuleSetting.php:37-51), and EmployeeController.php:77 returns 403.
- Намерено от: tasks-timelogs

### F123 · висока · мъртва връзка (403/404)

**Task History tab, sub-task avatars and @mentions link to employees.show, including for clients, who see these tabs by default**

- Елемент: `resources/views/tasks/ajax/history.blade.php:15` – показва се при: <a href="{{ route('employees.show', $activ->user_id) }}"> with no condition. Sub-task list: <x-employee-image :user="$subtask->assignedTo" /> (resources/views/tasks/sub_tasks/show.blade.php:23 -> components/employee-image.blade.php:2, unconditional link). Sub-task detail: <x-employee :user="$subTask->assignedTo"/> without disabledLink (tasks/sub_tasks/detail.blade.php:26). Mention links: TaskController.php:1094-1100 'link' => route('employees.show', ...). The History and Sub task tabs show for clients when task_settings history/sub_task = 'yes', which is the migration default.
- Води към: employees.show
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules))
- Сценарий: A client on any package opens a task and clicks a name in History or a sub-task assignee avatar. Same for an admin or employee of a Default-package company.
- Ефект: Opens a 403 page.
- Поправка: Guard each link with !in_array('client', user_roles()) && in_array('employees', user_modules()), and add the same guard inside components/employee-image.blade.php. Pass :disabledLink in sub_tasks/detail, and drop 'link' from the mention data when the guard fails.
- Проверка от втори агент: tasks/ajax/history.blade.php:15 links unconditionally. tasks/sub_tasks/show.blade.php:23 uses x-employee-image (components/employee-image.blade.php:2 always links). sub_tasks/detail.blade.php:26 uses x-employee without disabledLink. TaskController.php:1094-1100 builds mention links to employees.show. The client sees the History tab when task_settings.history='yes' (show.blade.php:424, the default per the migration). EmployeeController.php:77 returns 403.
- Намерено от: tasks-timelogs

### F164 · висока · мъртва връзка (403/404)

**Author/renewer links in the contract Discussion and Renewal-history tabs always point to employees.show (403 without the employees module or for clients, 404 for client authors)**

- Елемент: `resources/views/contracts/ajax/discussion.blade.php:52` – показва се при: none: <a href="{{ route('employees.show', $discussion->user->id) }}"> is shown for every discussion (also :59, contracts/discussions/show.blade.php:11 and :17, contracts/renew/renew_history.blade.php:9 and :15 for $history->renewedBy)
- Води към: employees.show (EmployeeController@show)
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules)); :725 abort_403($id != user()->id) when view_employee_menu != all; :746 abort(404) if !$this->employee->hasRole('employee'); :752 abort_403(in_array('client', user_roles()))
- Сценарий: Package 'Default' (companies 4,5,6, no employees module): the admin opens a contract, then Discussion or Renewal history, and clicks the author's name or avatar, which gives 403. Client role (contracts is a client module, add/view_contract_discussion = all by default): every author link gives 403. On any package, a comment written by a client links to employees.show of a client user, which gives 404.
- Ефект: Clickable names and avatars lead to a 403 or 404 page
- Поправка: Link only when in_array('employees', user_modules()) && !in_array('client', user_roles()) && $discussion->user->hasRole('employee'); otherwise render plain text (same for renewedBy).
- Проверка от втори агент: contracts/ajax/discussion.blade.php:52,59, contracts/discussions/show.blade.php:11,17 and contracts/renew/renew_history.blade.php:9,15 always link employees.show. EmployeeController.php:77 aborts without employees (Default, and always for clients). For a client author, :731 employeeDetail is null (500) and :746 returns 404.
- Намерено от: expenses-products-contracts

### F165 · висока · мъртва връзка (403/404)

**The Employee column and fields in expenses link to employees.show even when the employees module is not in the package**

- Елемент: `app/DataTables/ExpensesDataTable.php:95` – показва се при: always: editColumn('user_id', fn($row) => view('components.employee', ['user' => $row->user])); the component renders href=route('employees.show') unless $disabledLink is set (resources/views/components/employee.blade.php:18 and :29). Same at app/DataTables/RecurringExpensesDataTable.php:86, resources/views/expenses/ajax/show.blade.php:68 (employee) and :94 (approved by), resources/views/recurring-expenses/ajax/show.blade.php:76
- Води към: employees.show (EmployeeController@show)
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules)); also :725 abort_403 for another user's profile when view_employee_menu != all
- Сценарий: Package 'Default' (no employees, but expenses included): the admin opens Finance, then Expenses, and clicks the employee name or avatar in the list, or the 'Employee' or 'Approved by' value in the expense detail, which gives 403. An employee without view_employee_menu = all who clicks the approver gives 403 as well.
- Ефект: A 403 page from the main expense list and the expense detail panel
- Поправка: In components/employee.blade.php use $noLink = isset($disabledLink) || !in_array('employees', user_modules()); and use it for both hrefs. This fixes every caller.
- Проверка от втори агент: ExpensesDataTable.php:94 and RecurringExpensesDataTable.php:84-87 render components.employee without disabledLink. expenses/ajax/show.blade.php:68 and :94 use x-employee the same way. components/employee.blade.php:18,29 link employees.show unless disabledLink is set, and EmployeeController.php:77 returns 403 on Default (expenses in package, employees not).
- Намерено от: expenses-products-contracts

### F179 · висока · мъртва връзка (403/404)

**Expense report: employee names link to employees.show, which returns 403 on the Default package (no employees module)**

- Елемент: `app/DataTables/ExpenseReportDataTable.php:58` – показва се при: Sidebar: resources/views/sections/menu.blade.php:208 `isset($sidebarUserPermissions['view_expense_report']) && $sidebarUserPermissions['view_expense_report'] == 4 && ... && in_array('expenses', user_modules())`; each row: `->editColumn('user_id', fn => view('components.employee', ['user' => $row->user]))` with no disabledLink, and resources/views/components/employee.blade.php:18,30 links to `route('employees.show', [$user->id])` unless $disabledLink is set
- Води към: employees.show (/account/employees/{id})
- Проверка/отказ: app/Http/Controllers/EmployeeController.php constructor middleware: abort_403(!in_array('employees', $this->user->modules))
- Сценарий: Companies 4/5/6 on the Default package (reports and expenses included, employees missing), admin role (view_expense_report = all). Reports -> Expense report, click an employee name or avatar in the Employees column. The same happens in the Time log reports (TimeLogReportDataTable.php:43, TimeLogConsolidatedReportDataTable.php:43, TimeLogProjectwiseReportDataTable.php:34) when employees is switched off for a role that keeps timelogs.
- Ефект: The 403 'permission denied' page appears from a report in the main menu. The Employee filter (resources/views/reports/expense/index.blade.php:36-49) also lists employees although the package has no Employees module.
- Поправка: Same component-level fix as F165: in components/employee.blade.php treat the link as disabled when !in_array('employees', user_modules()).
- Проверка от втори агент: ExpenseReportDataTable.php:57-60 renders components.employee without disabledLink, and components/employee.blade.php:18,29 link employees.show. EmployeeController.php:77 aborts on Default (reports and expenses present, employees absent). The menu item is at menu.blade.php:208.
- Намерено от: crm-reports

## G03. Бутони „+ Добави“ (служител, клиент, отдел) и избор на отдел във формите (12)

### F064 · висока · мъртва връзка (403/404)

**'+ Add' employee button in task/project/recurring-task/project-template forms opens employees.create without checking the employees module**

- Елемент: `resources/views/tasks/ajax/create.blade.php:241` – показва се при: @if ($addEmployeePermission == 'all' || $addEmployeePermission == 'added') <button id="add-employee"> -> JS :967-970 $.easyAjax(route('employees.create')). Same in tasks/ajax/edit.blade.php:180 (+988), projects/ajax/create.blade.php:251 (+648), projects/ajax/edit.blade.php:270 and :303 (+628), recurring-task/ajax/create.blade.php:236 (+966), recurring-task/ajax/edit.blade.php:179 (+966). project-templates/task/ajax/create.blade.php:50-53 and edit.blade.php:64-67 render it with no condition at all
- Води към: employees.create
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 (employees module); :119-120 add_employees in all/added AND view_employee_menu == all
- Сценарий: Package Default: admin (add_employees=all) creates a task or project and clicks 'Add' next to the members/assignee select. Also any custom role with add_employees=all but view_employee_menu=none
- Ефект: Modal opens and the AJAX call fails with 403 (empty modal / error toast)
- Поправка: Use @if (in_array('employees', user_modules()) && in_array($addEmployeePermission, ['all','added']) && user()->permission('view_employee_menu') == 'all') for every #add-employee button, project-template forms included.
- Проверка от втори агент: tasks/ajax/create.blade.php:241-247 shows #add-employee for add_employees all/added with no module check, and :967 loads employees.create. projects/ajax/create.blade.php:251-256 does the same. project-templates/task/ajax/create.blade.php:50-53 has no condition at all. EmployeeController.php:77 (module) and :119-120 (add_employees + view_employee_menu) return 403. Admin on Default has add_employees=all.
- Намерено от: hr

### F105 · висока · мъртва връзка (403/404)

**The 'Add' employee button next to project members (and template task assignees) opens employees.create with no employees module check**

- Елемент: `resources/views/projects/ajax/create.blade.php:251` – показва се при: @if ($addEmployeePermission == 'all' || $addEmployeePermission == 'added') -> #add-employee loads route('employees.create') (create.blade.php:648-651); same in projects/ajax/edit.blade.php:270-275, 303-307 / 628-631. In project-templates/task/ajax/create.blade.php:50-53 / 183-186 and edit.blade.php:64-67 / 206-209 the button is shown with no condition at all
- Води към: employees.create (EmployeeController@create)
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:76-78 abort_403(!in_array('employees', $this->user->modules)) plus the add_employees permission inside create()
- Сценарий: Package Default (no employees): admin (add_employees=all) clicks Add next to 'Add Project Members' in the project form. In template task forms, any user with manage_project_template sees it even without add_employees
- Ефект: Modal opens and returns 403
- Поправка: Add && in_array('employees', user_modules()) (plus add_employees and view_employee_menu for the template forms).
- Проверка от втори агент: Duplicate of F064: projects/ajax/create.blade.php:251-256 (+648) and edit.blade.php:270/303 (+628) have no module check. project-templates/task/ajax/create.blade.php:50-53 and edit.blade.php:64-67 have no condition at all. EmployeeController.php:76-78 returns 403.
- Намерено от: projects

### F124 · висока · мъртва връзка (403/404)

**'Add' employee button in the task and recurring-task forms is shown without the employees module**

- Елемент: `resources/views/tasks/ajax/create.blade.php:241` – показва се при: @if ($addEmployeePermission == 'all' || $addEmployeePermission == 'added'), permission only. Same at tasks/ajax/edit.blade.php:180, recurring-task/ajax/create.blade.php:236 and recurring-task/ajax/edit.blade.php:179. user()->permission() does not depend on modules, so an admin keeps add_employees = all.
- Води към: employees.create (AJAX from the #add-employee handler, tasks/ajax/create.blade.php:970)
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 abort_403(!in_array('employees', $this->user->modules)), and :119-120 (add_employees and view_employee_menu)
- Сценарий: An admin of a Default-package company opens Add Task (or Edit Task / Add Recurring Task) and clicks 'Add' next to 'Assigned To'.
- Ефект: The modal opens with a 403 error.
- Поправка: Change the condition to in_array('employees', user_modules()) && ($addEmployeePermission == 'all' || $addEmployeePermission == 'added') in all four forms.
- Проверка от втори агент: tasks/ajax/create.blade.php:241, tasks/ajax/edit.blade.php:180, recurring-task/ajax/create.blade.php:236 and recurring-task/ajax/edit.blade.php:179 check only add_employees. User::permission() (User.php:996-1018) reads user_permissions with no module check. The #add-employee handler (create.blade.php:967-970) loads employees.create, which aborts at EmployeeController.php:77 and :119-120.
- Намерено от: tasks-timelogs

### F019 · средна · мъртва връзка (403/404)

**Quick-add "Add employee" ignores the package employee/client limits that CheckCompanyPackage enforces with 403**

- Елемент: `resources/views/sections/topbar.blade.php:162` – показва се при: @if (in_array('employees', user_modules()) && (add_employees_permission() == 'all' || add_employees_permission() == 'added'))
- Води към: employees.create (openRightModal)
- Проверка/отказ: app/Http/Middleware/CheckCompanyPackage.php:67-69 abort_403(!checkCompanyCanAddMoreEmployees(...) || !checkCompanyCanAddMoreClients(...)) for employees.create
- Сценарий: Admin of a company that has reached max_employees (or max_clients) of its package. Seeded packages allow 20-50 employees. The sidebar invite item (sidebar.blade.php:84) and employees/index.blade.php:179 already check checkCompanyCanAddMoreEmployees; the topbar does not.
- Ефект: The right modal shows "403 | Permission Denied".
- Поправка: topbar.blade.php:162: add && checkCompanyCanAddMoreEmployees(user()->company_id) && checkCompanyCanAddMoreClients(user()->company_id)
- Проверка от втори агент: topbar.blade.php:162 checks the employees module plus add_employees only. CheckCompanyPackage.php:67-69 abort_403 on employees.create when checkCompanyCanAddMoreEmployees/Clients fails. sidebar.blade.php:84 and employees/index.blade.php:179 already check the limit.
- Намерено от: topbar-global

### F067 · средна · мъртва връзка (403/404)

**Department multi-select in project create/edit/duplicate is shown without the employees module and fires departments.members AJAX, which 403s**

- Елемент: `resources/views/projects/ajax/create.blade.php:82` – показва се при: @if (!in_array('client', user_roles())) <select name="team_id[]" id="employee_department"> + onchange :670-676 $.easyAjax(route('departments.members', id)). Same in projects/ajax/edit.blade.php:79 (+690-696) and projects/duplicate-project.blade.php:109 (+265-271). The same AJAX also exists in event-calendar and recurring-event create/edit (events are config-hidden)
- Води към: departments.members (DepartmentController@getMembers, routes/web.php:237)
- Проверка/отказ: app/Http/Controllers/DepartmentController.php:23-26 abort_403(!in_array('employees', $this->user->modules))
- Сценарий: Package Default, or employees switched off for the admin role: the admin sees a 'Department' field in the project form. If departments exist (data from an earlier package or demo seed), picking one triggers a 403 and the member list is not refreshed
- Ефект: Field of a module outside the package plus a 403 AJAX error when it is used
- Поправка: Wrap the department column and its change handler in @if (in_array('employees', user_modules()) && !in_array('client', user_roles())) in create, edit and duplicate-project.
- Проверка от втори агент: projects/ajax/create.blade.php:82-96 shows the Department select for every non-client, and :670-680 call route('departments.members'). Edit and duplicate views have the same code. DepartmentController.php:23-24 abort_403 without employees (route web.php:237). Severity medium: the 403 needs existing departments, while the field itself always leaks. Duplicate of F104.
- Намерено от: hr

### F068 · средна · модул извън пакета се вижда

**HR 'Department' filters/selects shown in projects, project calendar, archive, project members and timelogs without the employees module**

- Елемент: `resources/views/projects/index.blade.php:136` – показва се при: Unconditional 'Department' filter fed by ProjectController.php:105 $this->departments = Team::all(). Also projects/archive.blade.php:116, projects/calendar.blade.php:128 (ProjectCalendarController.php:47), projects/project-member/create.blade.php:16 'Choose department' radio + :36-50 (ProjectMemberController.php:53), timelogs/index.blade.php:39-49 (TimelogController.php:60)
- Води към: Team (departments) data of the employees module
- Проверка/отказ: No module check. Departments are managed only through DepartmentController (employees module, DepartmentController.php:23-26)
- Сценарий: Package Default: admin opens Projects -> more filters, or the project 'Add member' modal, and sees Department filters/options of a module the package does not have
- Ефект: UI of a module outside the package visible (no 403)
- Поправка: Render these filters and the radio, and load Team::all(), only when in_array('employees', user_modules()).
- Проверка от втори агент: projects/index.blade.php:136-140 Department filter has no condition. timelogs/index.blade.php:38-48 has the same filter. projects/project-member/create.blade.php:16-17 has a 'Choose department' radio. Departments belong to the employees module (DepartmentController.php:23-24). No 403, only a module leak.
- Намерено от: hr

### F092 · средна · мъртва връзка (403/404)

**Client-change AJAX calls in invoice/estimate/recurring/order/contract forms hit ClientController, which is gated by the clients module**

- Елемент: `resources/views/invoices/ajax/create.blade.php:1291 and :1311; invoices/ajax/edit.blade.php:1019 and :1039; estimates/ajax/create.blade.php:788; estimates/ajax/edit.blade.php:510; recurring-invoices/ajax/create.blade.php:767 and :787; recurring-invoices/edit.blade.php:730; orders/ajax/create.blade.php:583 and :603; orders/ajax/edit.blade.php:436 and :456; orders/ajax/admin_create.blade.php:399; contracts/ajax/create.blade.php:386; contracts/ajax/edit.blade.php:320` – показва се при: Fired on $('#client_list_id').change / $('#client_id').change (contracts: on #project_id change). The client select (x-client-selection-dropdown) is always shown in these forms regardless of the clients module
- Води към: clients.project_list, clients.ajax_details, clients.client_details (routes/web.php:166-168)
- Проверка/отказ: ClientController.php:67 constructor middleware abort_403(!in_array('clients', $this->user->modules)) applies to projectList/ajaxDetails/clientDetails too
- Сценарий: The admin switches clients off for the employee or admin role while invoices/contracts stay on. A user creating an invoice or contract picks a client or project
- Ефект: easyAjax shows a 403 error; the project list and billing/shipping address (contracts: client list and contact fields) are not filled, so the form half-works
- Поправка: In ClientController's constructor middleware skip the module check for ['projectList','ajaxDetails','clientDetails'] (check $request->route()->getActionMethod()), or move them to a controller gated by the calling module.
- Проверка от втори агент: invoices/ajax/create.blade.php:1291 (and the listed estimate/order/contract views) call clients.project_list / ajax_details / client_details (routes web.php:166-168). These are ClientController actions behind its constructor middleware :67 abort_403 without clients. The client select is shown regardless of the clients module.
- Намерено от: clients

### F093 · средна · мъртва връзка (403/404)

**'+ Add' client button in the shared client dropdown ignores the clients module**

- Елемент: `resources/views/components/client-selection-dropdown.blade.php:19-26 (JS at :31)` – показва се при: @if ($addClientPermission == 'all' || $addClientPermission == 'added') with no in_array('clients', user_modules()). Used in invoices create/edit, estimates create, contracts create/edit, recurring-invoices create/edit, orders admin_create, estimate-requests create/edit (only projects create/edit hide the whole field when clients is off)
- Води към: clients.create?quick-form=1
- Проверка/отказ: ClientController.php:67 abort_403(!in_array('clients', $this->user->modules))
- Сценарий: The admin switches clients off for a role whose add_clients permission is still all/added, then opens the invoice/contract/estimate form
- Ефект: The 'Add' button next to Client opens a modal that 403s
- Поправка: @if (in_array('clients', user_modules()) && ($addClientPermission == 'all' || $addClientPermission == 'added'))
- Проверка от втори агент: components/client-selection-dropdown.blade.php:19-26 shows the Add client button for add_clients all|added with no module check, and :31 opens clients.create. ClientController.php:67 returns 403 without clients.
- Намерено от: clients

### F104 · средна · мъртва връзка (403/404)

**The Department select in the project create/edit/duplicate forms fires a departments.members AJAX call that returns 403 when the employees module is off**

- Елемент: `resources/views/projects/ajax/create.blade.php:82` – показва се при: @if (!in_array('client', user_roles())) shows the Department multiselect filled with Team::all() (ProjectController.php:277); on change, create.blade.php:670-696 calls route('departments.members'). Same in resources/views/projects/ajax/edit.blade.php:79 / 690-696 and resources/views/projects/duplicate-project.blade.php:109 / 265-271
- Води към: departments.members -> DepartmentController@getMembers (routes/web.php:237)
- Проверка/отказ: app/Http/Controllers/DepartmentController.php:23-25 abort_403(!in_array('employees', $this->user->modules))
- Сценарий: Package Default (no employees), or the employees module switched off for the employee role, while departments exist; an admin or employee adds or edits or duplicates a project and picks a department
- Ефект: The 'Department' field (a part of the employees module) is visible; picking a value gives a 403 error and the member list is not refreshed
- Поправка: Wrap the department field and its JS in @if (in_array('employees', user_modules()) && !in_array('client', user_roles())) in create, edit and duplicate-project.
- Проверка от втори агент: Duplicate of F067: projects/ajax/create.blade.php:82-96 plus :670-680 call departments.members, and DepartmentController.php:23-24 aborts without employees. Medium rather than high: the 403 needs existing departments, the visible field is the leak.
- Намерено от: projects

### F112 · средна · модул извън пакета се вижда

**Department filters and the 'choose department' option in project screens are visible without the employees module, including to clients**

- Елемент: `resources/views/projects/index.blade.php:136` – показва се при: Department filter not wrapped in any role or module check: projects/index.blade.php:136-148 (outside the client-only block 119-133), projects/calendar.blade.php:121-131, projects/archive.blade.php:115-124; 'Choose department' radio and team list in projects/project-member/create.blade.php:16,36-50 and project-templates/project-member/create.blade.php:16,37-48 (Team::all() from ProjectMemberController.php:53 / ProjectTemplateMemberController.php:46); exported 'Department' column ProjectsDataTable.php:633
- Води към: projects.index / project-calendar.index / projects.archive filters; project-members.store_group
- Проверка/отказ: none (ProjectController.php:105,1800 and ProjectCalendarController load Team::all() unconditionally)
- Сценарий: Client role (never has employees) opens Projects -> More filters and sees all company department names; package Default admin sees Department filters and can add members by department
- Ефект: Department data from the employees module shown where the module is not available
- Поправка: Wrap the department filters, the department radio and the project-form Department select in @if (in_array('employees', user_modules()) && !in_array('client', user_roles())), and load Team::all() only in that case.
- Проверка от втори агент: projects/index.blade.php:136-148 Department filter sits outside the client-only block (119-133). Data comes from Team::all() at ProjectController.php:105. calendar.blade.php:121 and archive.blade.php:115 do the same. project-member/create.blade.php:7 has a 'choose department' radio. There is also a worse case: the Add Project form projects/ajax/create.blade.php:82-96 shows a Department select to every non-client, and its change handler (:670-676) calls departments.members. DepartmentController.php:24 aborts 403 without employees, so this is a dead AJAX call on Default.
- Намерено от: projects

### F132 · средна · мъртва връзка (403/404)

**Project template task form: 'Add' employee and 'Add' category buttons have no permission or module check; the template task view links avatars to employees.show**

- Елемент: `resources/views/project-templates/task/ajax/create.blade.php:51` – показва се при: <button id="add-employee"> and <button id="create_task_category"> (line 31) are rendered unconditionally. Same in project-templates/task/ajax/edit.blade.php:65 and :33. The template task show view has an unconditional <a href="{{ route('employees.show', $item->id) }}"> (project-templates/task/ajax/show.blade.php:64).
- Води към: employees.create, taskCategory.create, employees.show
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:77 (employees module) and :119 (add_employees); app/Http/Controllers/TaskCategoryController.php:15-16 abort_403(!in_array(user()->permission('add_task_category'), ['all','added']))
- Сценарий: (a) An admin of a Default-package company (no employees) edits a project template task and clicks 'Add' next to Assigned To, or clicks an assignee avatar. (b) An employee with manage_project_template but without add_task_category clicks 'Add' next to Category.
- Ефект: A 403 modal or page.
- Поправка: Show add-employee only when in_array('employees', user_modules()) && add_employees is all/added, and create_task_category only when add_task_category is all/added (same in edit.blade.php). Link avatars only when the employees module is on.
- Проверка от втори агент: project-templates/task/ajax/create.blade.php:31 (create_task_category) and :51 (add-employee) are unconditional. Their handlers load taskCategory.create and employees.create. project-templates/task/ajax/show.blade.php:64 links employees.show. TaskCategoryController.php:15-16 requires add_task_category, and EmployeeController.php:77/:119 require the employees module and add_employees.
- Намерено от: tasks-timelogs

### F022 · ниска · мъртва връзка (403/404)

**Quick-add "Add employee" does not check view_employee_menu, which employees.create requires**

- Елемент: `resources/views/sections/topbar.blade.php:162` – показва се при: add_employees_permission() == 'all' || == 'added' (view_employee_menu not checked)
- Води към: employees.create
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:120 abort_403(!($viewEmployeeMenuPermission == 'all' || == 4))
- Сценарий: A custom or employee role given "Add employees: All" while the custom permission "View employee menu" stays None (the employee role default)
- Ефект: The "Add employee" quick-add opens "403 | Permission Denied".
- Поправка: topbar.blade.php:162: add && user()->permission('view_employee_menu') == 'all'
- Проверка от втори агент: topbar.blade.php:162 does not check view_employee_menu. EmployeeController.php:116-120 create() abort_403 unless view_employee_menu is 'all'. Companies created after the migration get view_employee_menu = none for the employee role (not in PermissionRole::employeeRolePermissions).
- Намерено от: topbar-global

## G04. Отчитане на време (timelogs) – липсва в пакет Default (21)

### F102 · висока · мъртва връзка (403/404)

**Start/pause/stop timer buttons in the project Tasks tab are shown even when the timelogs module is off**

- Елемент: `app/DataTables/TasksDataTable.php:845` – показва се при: short_code column (TasksDataTable.php:178,181) always appends $this->timer($row); timer() only checks `$row->boardColumn->slug == 'completed' || ... || is_null($row->is_task_user)`. $this->hasTimelogModule (line 44) is set but never used. Click handler resources/views/projects/ajax/tasks.blade.php:548 posts to route('timelogs.start_timer')
- Води към: timelogs.start_timer / timelogs.pause_timer / timelogs.resume_timer / timelogs.stop_timer (TimelogController)
- Проверка/отказ: app/Http/Controllers/TimelogController.php:46 abort_403(!in_array('timelogs', $this->user->modules))
- Сценарий: Company on package Default (no timelogs): an admin or employee assigned to a task opens Project -> Tasks tab (also the main Tasks list, same DataTable) and clicks the play icon under the task code
- Ефект: Start Timer icon shown on every open task; the click returns a 403 error from the AJAX call
- Поправка: First line of TasksDataTable::timer(): if (!$this->hasTimelogModule) { return null; } (same in RecurringTasks/WaitingForApproval DataTables).
- Проверка от втори агент: TasksDataTable.php:178,181 append $this->timer($row). timer() (:838-845) checks only the column slug and is_task_user, and $this->hasTimelogModule (:44) is never used. The click handlers tasks/index.blade.php:718-719 and projects/ajax/tasks.blade.php:548-549 post to timelogs.start_timer. TimelogController.php:45-46 abort_403 without timelogs (not in Default).
- Намерено от: projects

### F103 · висока · модул извън пакета се вижда

**Project overview shows a 'Hours logged' widget and chart without a timelogs module check**

- Елемент: `resources/views/projects/ajax/overview.blade.php:231` – показва се при: @if ($viewProjectTimelogPermission == 'all' && $viewTasksPermission != 'none') (lines 221, 231 widget; 272, 276 stacked chart 'hoursLogged'); no in_array('timelogs', user_modules())
- Води към: projects.show (default overview tab)
- Проверка/отказ: No module check anywhere: ProjectController.php:1016-1025 always computes hoursLogged / hoursBudgetChart
- Сценарий: Package Default (no timelogs): admin (all permissions), employee (view_project_timelogs=all, view_tasks=both) and client (view_project_timelogs=all, view_tasks=owned) open any project
- Ефект: Every project's landing page shows a 'Hours logged' stat card and a 'Hours logged vs allocated' chart for a module the company did not buy
- Поправка: Add in_array('timelogs', user_modules()) to the timelog conditions at overview.blade.php:214, :221, :231, :272 and :276.
- Проверка от втори агент: projects/ajax/overview.blade.php:214 and :221-236 show the 'Hours logged' widget, and :272-283 the hours chart, for view_project_timelogs == 'all' && view_tasks != none with no timelogs check. On Default an admin (all permissions) sees it on every project overview.
- Намерено от: projects

### F119 · висока · мъртва връзка (403/404)

**Tasks list shows Start/Pause/Stop timer buttons when the timelogs module is off; clicking them returns 403**

- Елемент: `app/DataTables/TasksDataTable.php:845` – показва се при: timer($row) is appended to the short_code column (lines 178/181) and returns the start-timer link whenever !($row->boardColumn->slug == 'completed' || ... == 'waiting_approval' || is_null($row->is_task_user)). It has no timelogs check: $this->hasTimelogModule (line 44) is computed but never used.
- Води към: timelogs.start_timer / timelogs.pause_timer / timelogs.resume_timer / timelogs.stopper_alert (routes/web.php:757-768). Fired from resources/views/tasks/index.blade.php:719, resources/views/projects/ajax/tasks.blade.php:549 and resources/views/employees/ajax/tasks.blade.php:270.
- Проверка/отказ: app/Http/Controllers/TimelogController.php:46 abort_403(!in_array('timelogs', $this->user->modules)) in constructor middleware
- Сценарий: Package Default (companies 4, 5, 6) has no timelogs. An admin or employee assigned to an open task opens Tasks (or a project's Tasks tab).
- Ефект: A play icon appears under every assigned task's code. Clicking it POSTs to start-timer and gets a 403 error. The recurring task's 'Tasks' tab (RecurringTaskController::tasks uses TasksDataTable) shows the same icon.
- Поправка: At the top of TasksDataTable::timer() add: if (!$this->hasTimelogModule) { return null; }
- Проверка от втори агент: TasksDataTable.php:172-178 appends $this->timer($row) to short_code. timer() at :838-860 returns the start/pause/stop links for any assigned (is_task_user, Task.php:342), not completed task. $this->hasTimelogModule (:44) is never used. The click handler at tasks/index.blade.php:718-719 posts to timelogs.start_timer, and TimelogController.php:46 aborts 403 without timelogs. Default has no timelogs.
- Намерено от: tasks-timelogs

### F140 · висока · модул извън пакета се вижда

**"Create Time Log Invoice" is gated on the projects module instead of timelogs, so Default-package companies (no timelogs) see and use time-log invoicing**

- Елемент: `resources/views/invoices/index.blade.php:141` – показва се при: @if ($addInvoicesPermission == 'all' && in_array('projects', user_modules()))
- Води към: invoices.create?type=timelog -> InvoiceController@create (view invoices.ajax.create-timelog-invoice) + AJAX invoices.fetch_timelogs
- Проверка/отказ: app/Http/Controllers/InvoiceController.php:174 only checks `request('type') == 'timelog' && in_array('projects', user_modules())`; InvoiceController::fetchTimelogs (app/Http/Controllers/InvoiceController.php:1503) has no timelogs check. The same button in the project Invoices tab is correctly gated: resources/views/projects/ajax/invoices.blade.php:23 `in_array('timelogs', user_modules())`
- Сценарий: Package "Default" (companies 4, 5, 6) includes projects and invoices but not timelogs. Company admin opens Finance -> Invoices.
- Ефект: The admin sees a "Create Time Log Invoice" button for a module that is not in the package. The page opens and imports approved project time logs into an invoice. Nothing 403s, so the package restriction is bypassed in the UI and in the backend.
- Поправка: Use in_array('timelogs', user_modules()) at invoices/index.blade.php:141 and InvoiceController.php:174, and add abort_403(!in_array('timelogs', user_modules())) at the top of fetchTimelogs().
- Проверка от втори агент: invoices/index.blade.php:141 gates 'Create Time Log Invoice' on the projects module. InvoiceController.php:174 uses the same projects check. fetchTimelogs() (:1503) has no check. The project tab correctly uses timelogs (projects/ajax/invoices.blade.php:23). Default has projects and invoices but no timelogs.
- Намерено от: invoices-sales

### F018 · средна · мъртва връзка (403/404)

**Running-timer clock in the topbar is gated on tasks, not timelogs; its stop/pause/resume buttons 403 once timelogs is off**

- Елемент: `resources/views/sections/timer_clock.blade.php:1` – показва се при: topbar.blade.php:42 @if(isset($selfActiveTimer)) include; timer_clock.blade.php:1 @if (!is_null($selfActiveTimer) && in_array('tasks', user_modules())). $selfActiveTimer is computed unconditionally in AccountBaseController.php:99.
- Води към: timelogs.stopper_alert (timer_clock.blade.php:113-119, layouts/app.blade.php:590-596), timelogs.pause_timer (app.blade.php:505-510), timelogs.resume_timer (app.blade.php:550-555), polling timelogs.timer_data (timer_clock.blade.php:84-110) and timelogs.check_project_time_limit every 30 s (timer_clock.blade.php:123-137)
- Проверка/отказ: app/Http/Controllers/TimelogController.php:46 abort_403(!in_array('timelogs', $this->user->modules))
- Сценарий: A user has a timer running when the company is moved Trial -> Default (no timelogs, TrialExpire cron), or when the admin switches Timelogs off for the employee role.
- Ефект: The ticking clock keeps showing on every page. Stop opens a 403 modal, pause/resume fail silently, and the timer can never be stopped by the user.
- Поправка: timer_clock.blade.php:1: @if (!is_null($selfActiveTimer) && in_array('timelogs', user_modules()) && in_array('tasks', user_modules())). Wrap the script block the same way.
- Проверка от втори агент: sections/timer_clock.blade.php:1 gates on in_array('tasks', user_modules()), not timelogs. $selfActiveTimer is computed unconditionally (AccountBaseController.php:99). The stop button calls timelogs.stopper_alert (:113-119), and the page polls timelogs.timer_data (:84-110) and check_project_time_limit (:123-137). TimelogController.php:46 abort_403 without timelogs. Only reachable after a running timer outlives the module (trial to Default, or a role toggle), but then it is permanent.
- Намерено от: topbar-global

### F054 · средна · мъртва връзка (403/404)

**'My active timer' card and the auto-stopped memo modal call timelog routes without a timelogs-module check**

- Елемент: `resources/views/dashboard/employee/index.blade.php:314` – показва се при: @if (!is_null($myActiveTimer) && in_array('tasks', user_modules())) with Pause/Stop/Resume buttons (lines 360-373) calling timelogs.pause_timer/stop_timer/resume_timer (handlers in layouts/app.blade.php:507ff). Also @if (!empty($autoStoppedTimelog)) auto-opens route('timelogs.auto_stopped_memo') on every load (lines 1055-1062).
- Води към: TimelogController pauseTimer/stopTimer/resumeTimer/autoStoppedMemoAlert/saveAutoStoppedMemo
- Проверка/отказ: app/Http/Controllers/TimelogController.php:45-46 abort_403(!in_array('timelogs', $this->user->modules)) in constructor middleware
- Сценарий: A user had a running or auto-stopped timer, then the company moved to package 'Default' (no timelogs) or the admin switched timelogs off for the role.
- Ефект: Timer buttons return 403. A modal that ends in 403 pops up on every dashboard load and cannot be cleared, because saving the memo is behind the same check.
- Поправка: Gate the card and the auto-stopped script on in_array('timelogs', user_modules()), and only query $myActiveTimer/$autoStoppedTimelog when timelogs is on.
- Проверка от втори агент: employee/index.blade.php:314 gates the active timer card on tasks only. :1055-1062 auto-open timelogs.auto_stopped_memo whenever $autoStoppedTimelog is set (EmployeeDashboard.php:644-648, no module check). TimelogController.php:45-46 abort_403 without timelogs. Only reachable after timelogs is turned off while a timer exists.
- Намерено от: dashboards

### F111 · средна · модул извън пакета се вижда

**Timelog-related fields and columns in project screens are shown without a timelogs module check**

- Елемент: `resources/views/projects/duplicate-project.blade.php:44` – показва се при: Unconditional: Duplicate modal 'Time Logs' copy checkbox (duplicate-project.blade.php:44-46, copied in ProjectController.php:2041); 'Manual time log' checkbox create.blade.php:329 / edit.blade.php:389 / project-templates/ajax/create.blade.php:63; 'Hours allocated' create.blade.php:303 / edit.blade.php:381; progress option 'project total time' create.blade.php:317 / edit.blade.php:347; 'Total hours' column in milestone detail projects/milestone/show.blade.php:43,68; member 'Hourly rate' column projects/ajax/members.blade.php:28 (view_project_hourly_rates only)
- Води към: projects.duplicate_project, projects.create/edit, milestones.show, projects.show?tab=members
- Проверка/отказ: none (no module check in these views or in ProjectController create/edit/storeDuplicateProject)
- Сценарий: Package Default (no timelogs): admin opens Add Project -> Other details, Duplicate on a project row, a milestone detail, or the Members tab
- Ефект: Timelog options and columns for a module the company did not buy
- Поправка: Wrap these fields, options and columns in @if (in_array('timelogs', user_modules())), and skip copying time logs in storeDuplicateProject when the module is off.
- Проверка от втори агент: Path correction: the forms are resources/views/projects/ajax/create.blade.php and ajax/edit.blade.php. None of these has a timelogs check: duplicate-project.blade.php:44-46 'Time Logs' copy checkbox; ajax/create.blade.php:303 hours_allocated, :317 project_total_time option, :329 manual_timelog; ajax/edit.blade.php:347, :381, :389; project-templates/ajax/create.blade.php:63; milestone/show.blade.php:43,68 'Total hours' column; ajax/members.blade.php:28 hourly rate (view_project_hourly_rates only). Default package has no timelogs.
- Намерено от: projects

### F125 · средна · мъртва връзка (403/404)

**A running timer stays visible in the topbar and on the employee dashboard after timelogs is switched off; its buttons and polling get 403**

- Елемент: `resources/views/sections/timer_clock.blade.php:1` – показва се при: @if (!is_null($selfActiveTimer) && in_array('tasks', user_modules())) checks 'tasks' instead of 'timelogs'. $selfActiveTimer is always loaded in app/Http/Controllers/AccountBaseController.php:99, and topbar.blade.php:41-44 includes the clock whenever it is set. Same gate on the dashboard 'My active timer' card: resources/views/dashboard/employee/index.blade.php:314. The dashboard also auto-opens the auto-stopped memo modal (dashboard/employee/index.blade.php:1058) whenever EmployeeDashboard.php:644 finds an auto-stopped log.
- Води към: timelogs.pause_timer / resume_timer / stopper_alert (layouts/app.blade.php:505-591), timelogs.timer_data (timer_clock:86, on every tab focus), timelogs.check_project_time_limit (timer_clock:125, every 30s), timelogs.auto_stopped_memo
- Проверка/отказ: app/Http/Controllers/TimelogController.php:46 abort_403(!in_array('timelogs', $this->user->modules))
- Сценарий: The company admin switches timelogs off for the employee role (or the company moves to the Default package) while an employee's timer is running, or after AutoStopTimer stopped one.
- Ефект: The clock keeps ticking on every page, but Pause, Stop and Resume all return 403, so the timer can never be stopped. Background AJAX keeps failing. The dashboard opens a 403 modal on every load.
- Поправка: Use in_array('timelogs', user_modules()) in timer_clock.blade.php:1 and dashboard/employee/index.blade.php:314. In AccountBaseController, load activeTimerCount/selfActiveTimer only with timelogs, and set autoStoppedTimelog in EmployeeDashboard only with timelogs.
- Проверка от втори агент: sections/timer_clock.blade.php:1 and dashboard/employee/index.blade.php:314 check 'tasks', not 'timelogs'. AccountBaseController.php:99 always loads selfActiveTimer, and topbar.blade.php:41-44 includes the clock. EmployeeDashboard.php:643-647 sets autoStoppedTimelog with no module check, and dashboard/employee/index.blade.php:1055-1060 opens the timelogs.auto_stopped_memo modal. TimelogController.php:46 returns 403 for all of these.
- Намерено от: tasks-timelogs

### F126 · средна · мъртва връзка (403/404)

**Recurring task list shows timer buttons when the timelogs module is off**

- Елемент: `app/DataTables/RecurringTasksDataTable.php:797` – показва се при: timer($row), called from the short_code column (lines 175/178), returns the start-timer link for assigned, not-completed tasks. $this->hasTimelogModule (line 43) is never used.
- Води към: timelogs.start_timer (resources/views/recurring-task/index.blade.php:666), pause/resume/stopper_alert
- Проверка/отказ: app/Http/Controllers/TimelogController.php:46 abort_403(!in_array('timelogs', $this->user->modules))
- Сценарий: An admin of a Default-package company (manage_recurring_task = all) is assigned to a recurring task and opens Tasks -> Recurring task.
- Ефект: A play icon is shown; clicking it gets a 403 error.
- Поправка: Add if (!$this->hasTimelogModule) { return null; } at the top of RecurringTasksDataTable::timer().
- Проверка от втори агент: RecurringTasksDataTable.php:172-178 appends timer(). timer() at :790-797 returns the start-timer link, and hasTimelogModule (:43) is unused. recurring-task/index.blade.php:666 posts to timelogs.start_timer, which TimelogController.php:46 rejects.
- Намерено от: tasks-timelogs

### F127 · средна · мъртва връзка (403/404)

**Archived project Tasks tab always shows the Timer column and the Hours Logged column**

- Елемент: `app/DataTables/ArchiveTasksDataTable.php:142` – показва се при: addColumn('timer') returns the start-timer, pause or stop buttons when $row->boardColumn->slug != 'completed' && !is_null($row->is_task_user). The columns at lines 505 (timer) and 511 (hoursLogged) are added unconditionally. Used by ProjectController::archivedTasks (ProjectController.php:1338) for a trashed project's Tasks tab.
- Води към: timelogs.start_timer via resources/views/projects/ajax/tasks.blade.php:548
- Проверка/отказ: app/Http/Controllers/TimelogController.php:46 abort_403(!in_array('timelogs', $this->user->modules))
- Сценарий: A Default-package company opens an archived project's Tasks tab while assigned to one of its tasks.
- Ефект: The timer button returns 403. The Hours Logged column from a module that is not in the package is shown.
- Поправка: Return '' from the timer column and add 'visible' => in_array('timelogs', user_modules()) to the timer and hoursLogged columns, as TasksDataTable::getColumns does.
- Проверка от втори агент: ArchiveTasksDataTable.php:142-150 addColumn('timer') returns start/pause/stop buttons. The columns at :505 (timer) and :511 (hoursLogged) are unconditional. ProjectController::archivedTasks (:1337) uses this table, and projects/ajax/tasks.blade.php:548-549 posts to timelogs.start_timer, which TimelogController.php:46 rejects.
- Намерено от: tasks-timelogs

### F128 · средна · мъртва връзка (403/404)

**Log Time form cannot load tasks or members when the tasks module is off for the role while timelogs is on**

- Елемент: `resources/views/timelogs/ajax/create.blade.php:166` – показва се при: On #project_id2 change the form calls route('tasks.project_tasks'), and on #task_id2 change it calls route('tasks.members') (line 186), always. Task is a required field (line 25). Same in timelogs/ajax/edit.blade.php:181/201. The time log list also links tasks.show (app/DataTables/TimeLogsDataTable.php:198,202), as does the active-timers modal (timelogs/ajax/active_timer.blade.php:154).
- Води към: tasks.project_tasks (TaskController::projectTasks :1410), tasks.members (:1439), tasks.show
- Проверка/отказ: app/Http/Controllers/TaskController.php:60 abort_403(!in_array('tasks', $this->user->modules))
- Сценарий: In Module settings the company admin switches Tasks off for the employee role but leaves Time Logs on.
- Ефект: Picking a project or task in Log Time returns 403, so time cannot be logged. Task links in the time log list open a 403 page.
- Поправка: Make TimelogController also require tasks (abort_403(!in_array('timelogs', ...) || !in_array('tasks', ...))) and hide the Time Logs menu without tasks, or serve these lists from TimelogController and render task links only with the tasks module.
- Проверка от втори агент: timelogs/ajax/create.blade.php:160-177 calls tasks.project_tasks and :180-186 calls tasks.members, both TaskController, gated at TaskController.php:60. TimeLogsDataTable.php:198,202 and timelogs/ajax/active_timer.blade.php:154 link tasks.show. TimelogController checks only timelogs (:46).
- Намерено от: tasks-timelogs

### F129 · средна · няма проверка на сървъра

**Start Timer 'Create new task' creates tasks even when the tasks module is off**

- Елемент: `resources/views/timelogs/ajax/timer.blade.php:31` – показва се при: @if ($addTaskPermission == 'all' || $addTaskPermission == 'added') shows the create_task checkbox, with no tasks module check. Same in timelogs/ajax/active_timer.blade.php:111.
- Води към: timelogs.start_timer -> TimelogController::startTimer, create_task branch
- Проверка/отказ: app/Http/Controllers/TimelogController.php:516 if ($request->has('create_task')) { $task = new Task(); ... $task->save(); }. The controller only checks the timelogs module (line 46); there is no 'tasks' module check and no add_tasks re-check.
- Сценарий: Tasks is off for the employee role, Time Logs is on. The employee opens the topbar timer -> Start Timer and ticks 'Create new task'.
- Ефект: New tasks are created through a module the role does not have.
- Поправка: Show the checkbox only when in_array('tasks', user_modules()) && add_tasks is all/added. In startTimer, abort_403 when create_task is sent without the tasks module or add_tasks.
- Проверка от втори агент: timelogs/ajax/timer.blade.php:32 shows 'Create new task' by add_tasks only. TimelogController.php:516-530 creates a Task when create_task is sent, and the controller only checks timelogs (:46).
- Намерено от: tasks-timelogs

### F213 · средна · модул извън пакета се вижда

**'Hours worked in <month>' (project time logs) card on the team request page in packages without the timelogs module**

- Елемент: `resources/views/databackd/teams/requests/show.blade.php:307` – показва се при: @if (!($isClient && $timeLogs === null)) - always shown to staff, and to the client once a project exists. $timeLogs = hoursThisMonth() (TeamRequestController.php:187, 527-550) reads ProjectTimeLog with no check of the 'timelogs' module.
- Води към: Time-log data of the linked project (timelogs module)
- Проверка/отказ: No check of in_array('timelogs', user_modules()) in TeamRequestController::show or in the view. Time logs cannot be recorded without the module (TimelogController is gated by the timelogs module).
- Сценарий: Companies 4, 5 and 6 on package 'Default' (lacks timelogs; has databackd_teams, since the migration gave every package all AFV modules). An admin or employee opens any team request.
- Ефект: Staff see a time-tracking card ('Time is tracked once ... a project is created') that belongs to a module not in the package. It always stays empty or 0.
- Поправка: Compute $timeLogs and render the HOURS card only when in_array('timelogs', user_modules()).
- Проверка от втори агент: teams/requests/show.blade.php:307 shows the hours card to staff always. TeamRequestController.php:187 and :527-548 read ProjectTimeLog with no timelogs check. Default lacks timelogs but has databackd_teams.
- Намерено от: databackd-analytics-planning-teams

### F023 · ниска · мъртва връзка (403/404)

**Topbar timer shows "Start timer" to users with manage_active_timelogs=all but add_timelogs=none**

- Елемент: `resources/views/sections/topbar.blade.php:117` – показва се при: Line 103: in_array('timelogs', user_modules()) && (add_timelogs_permission() == 'all' || == 'added' || manage_active_timelogs() == 'all'); line 113: @if ($activeTimerCount == 0) shows #start-timer-modal
- Води към: timelogs.show_timer (topbar.blade.php:276-280)
- Проверка/отказ: app/Http/Controllers/TimelogController.php:478 abort_403(!in_array($this->addTimelogPermission, ['all','added','owned','both']))
- Сценарий: A role with the custom permission "Manage active timelogs: All" and "Add timelogs: None", when no timer is currently running in the company
- Ефект: "Start timer" opens a modal with 403.
- Поправка: topbar.blade.php:113: @if ($activeTimerCount == 0 && in_array(add_timelogs_permission(), ['all', 'added']))
- Проверка от втори агент: topbar.blade.php:103 shows the timer box for manage_active_timelogs()=='all' even when add_timelogs is none. When $activeTimerCount == 0, :113-121 show #start-timer-modal, which loads timelogs.show_timer (:276-280). TimelogController.php:477-478 abort_403 unless add_timelogs is all/added/owned/both.
- Намерено от: topbar-global

### F037 · ниска · модул извън пакета се вижда

**Task settings show 'Time logs' and 'Hours logged' client-visibility options when the timelogs module is not in the package**

- Елемент: `resources/views/task-settings/index.blade.php:177` – показва се при: Unconditional inside the task settings page: line 177 (time_logs checkbox) and line 192 (hours_logged checkbox)
- Води към: task-settings.store
- Проверка/отказ: app/Http/Controllers/TaskSettingController.php:19 requires only tasks + manage_task_setting; no timelogs check
- Сценарий: Package 'Default' lacks timelogs. The admin opens Settings -> Task settings -> 'Section visible to client'.
- Ефект: Options belonging to the time-log module are visible and saved for a company without that module.
- Поправка: Wrap both checkboxes in @if (in_array('timelogs', user_modules())), and keep the stored values in store() when timelogs is off.
- Проверка от втори агент: task-settings/index.blade.php:177-178 (time_logs) and :192-193 (hours_logged) have no enclosing @if; the only @if in the file is at :78. TaskSettingController checks only tasks + manage_task_setting.
- Намерено от: settings

### F130 · ниска · модул извън пакета се вижда

**Public task page (Copy task link) always shows the Time Logs tab and Hours Logged, whatever the company package**

- Елемент: `resources/views/front/tasks/ajax/show.blade.php:140` – показва се при: The Time Logs x-tab-item and the Hours Logged row (line 200) have no module condition. The link is produced by 'Copy task link' in the task detail dropdown (resources/views/tasks/ajax/show.blade.php:154) for every admin and employee. HomeController::taskDetail (app/Http/Controllers/HomeController.php:506) maps view=time_logs to front.tasks.ajax.timelogs with no check.
- Води към: front.task_detail?view=time_logs
- Проверка/отказ: Missing. The public controller never checks whether the task's company has timelogs.
- Сценарий: A Default-package company (no timelogs) copies a task link and shares it.
- Ефект: The recipient sees a Time Logs tab and a 'Hours logged' figure for a module the company does not have.
- Поправка: In HomeController::taskDetail set $this->hasTimelogs = databackd_company_module($this->task->company_id, 'timelogs'). Hide the tab and the Hours logged row when false, and fall back from view=time_logs.
- Проверка от втори агент: front/tasks/ajax/show.blade.php:140-146 Time Logs tab and :196-202 Hours logged have no module condition. HomeController::taskDetail (:506) never checks the company's timelogs. The link is copied from tasks/ajax/show.blade.php:152-154. Low because a Default company cannot record time logs, so the tab is mostly empty.
- Намерено от: tasks-timelogs

### F131 · ниска · модул извън пакета се вижда

**Task settings show the 'Time Logs' and 'Hours Logged' client-visibility options without the timelogs module**

- Елемент: `resources/views/task-settings/index.blade.php:177` – показва се при: The 'Sections visible to client' checkboxes for time_logs (line 177) and hours_logged (line 192) are rendered unconditionally. 'Project' (line 103) also ignores the projects module.
- Води към: task-settings.index
- Проверка/отказ: app/Http/Controllers/TaskSettingController.php:19 checks only manage_task_setting and the 'tasks' module
- Сценарий: An admin of a Default-package company opens Settings -> Task settings.
- Ефект: Options for Time Logs, a module not in the package, are shown.
- Поправка: Wrap the time_logs and hours_logged checkboxes in @if (in_array('timelogs', user_modules())), and the project checkbox in @if (in_array('projects', user_modules())).
- Проверка от втори агент: task-settings/index.blade.php:177 (time_logs), :192 (hours_logged) and :103 (project) are unconditional. TaskSettingController.php:19 checks only manage_task_setting and tasks. This is a settings screen, so low.
- Намерено от: tasks-timelogs

### F135 · ниска · няма проверка на сървъра

**Dashboard week-timelog endpoint works without the timelogs module**

- Елемент: `routes/web.php:148` – показва се при: Route::post('dashboard/week-timelog', [DashboardController::class, 'weekTimelog']). The widget that calls it (dashboard/employee/widgets/week_timelog.blade.php:1) is hidden without timelogs, but the endpoint is not.
- Води към: dashboard.week_timelog
- Проверка/отказ: Missing. app/Http/Controllers/DashboardController.php:46-48 only blocks the super admin, and weekTimelog() (line 264) has no module check.
- Сценарий: An employee of a company without timelogs (Default package) POSTs /account/dashboard/week-timelog.
- Ефект: Returns the timelog widget HTML (own logged hours) from a module the package lacks.
- Поправка: Add abort_403(!in_array('timelogs', user_modules())); at the top of DashboardController::weekTimelog().
- Проверка от втори агент: routes/web.php:148 route. DashboardController.php:46-48 only blocks the super admin, and weekTimelog() (:264-279) has no module check. The widget is gated (week_timelog.blade.php:1). Low because it returns only the user's own logged time and only by direct POST.
- Намерено от: tasks-timelogs

### F136 · ниска · модул извън пакета се вижда

**Client's default task tab can render Time Logs content without the timelogs module**

- Елемент: `app/Http/Controllers/TaskController.php:1240` – показва се при: elseif ($this->taskSettings->time_logs == 'yes' && in_array('client', user_roles())) { $this->tab = 'tasks.ajax.timelogs'; } runs without in_array('timelogs', user_modules()). The view=time_logs case (line 1226) and the tab header (tasks/ajax/show.blade.php:406) are gated.
- Води към: tasks.show (default tab for clients)
- Проверка/отказ: Missing in the default branch; only the explicit view=time_logs case calls abort_403(!in_array('timelogs', user_modules())).
- Сценарий: Task settings for clients: files, sub_task and comments off, time_logs on (default yes). The company lacks timelogs (Default package) or the admin switched timelogs off for clients.
- Ефект: The client opens a task and sees the time log list as its first tab content, with no tab header.
- Поправка: Add && in_array('timelogs', user_modules()) to the elseif at TaskController.php:1239.
- Проверка от втори агент: TaskController.php:1239-1241: the client default branch sets tasks.ajax.timelogs with no timelogs check, while the explicit case at :1224-1226 aborts without the module.
- Намерено от: tasks-timelogs

### F138 · ниска · модул извън пакета се вижда

**Time log list filters show Department, Project and Invoice-generated without their modules**

- Елемент: `resources/views/timelogs/index.blade.php:39` – показва се при: The Department filter (lines 38-50, data from Team::all(), TimelogController.php:62), the Project filter (line 81) and the 'Invoice generated' filter (line 113) are rendered with no employees, projects or invoices check.
- Води към: timelogs.index
- Проверка/отказ: None in the view; TimelogController::index only checks timelogs and view_timelogs
- Сценарий: Employees, projects or invoices switched off for a role that keeps timelogs.
- Ефект: Filters from modules that are not available are shown.
- Поправка: Wrap each filter in @if (in_array('employees'|'projects'|'invoices', user_modules())).
- Проверка от втори агент: timelogs/index.blade.php:39-50 Department filter (Team::all()), :81 Project filter and :113 Invoice-generated filter have no employees, projects or invoices checks.
- Намерено от: tasks-timelogs

### F139 · ниска · модул извън пакета се вижда

**Timelog cron emails (tracker reminder, daily report) and auto-stop ignore whether the company package has timelogs**

- Елемент: `app/Console/Commands/SendTimeTracker.php:38` – показва се при: Company::active()->join('log_time_for', ...)->where('tracker_reminder', 1) with no module filter. SendDailyTimelogReport.php:30-45 only checks timelog_report, and AutoStopTimer.php:42 only joins log_time_for.
- Води към: TimeTrackerReminder email (link tasks.index), DailyTimeLogReport email, auto_stopped flag
- Проверка/отказ: Missing. No module_settings check for 'timelogs'.
- Сценарий: A company enabled the tracker reminder or daily timelog report in Time Log settings, then moved to the Default package (or the admin switched timelogs off).
- Ефект: Employees keep getting 'you have not logged time today' and daily timelog report emails for a module they no longer have.
- Поправка: Skip companies where !databackd_company_module($company->id, 'timelogs') in the three commands.
- Проверка от втори агент: SendTimeTracker.php:37-40 selects companies by tracker_reminder only. SendDailyTimelogReport.php:30-45 checks only the timelog_report setting. AutoStopTimer.php:41-42 only joins log_time_for. None checks module_settings for timelogs.
- Намерено от: tasks-timelogs

## G05. Табове и бутони в профил на клиент, проект и служител (26)

### F083 · висока · мъртва връзка (403/404)

**Client role: the client name in the Credit Notes list links to clients.show, which always returns 403 for clients**

- Елемент: `app/DataTables/CreditNotesDataTable.php:93 (renders components.client); link at resources/views/components/client.blade.php:35 and :57` – показва се при: CreditNotesDataTable.php:251 'name' column has no 'visible' key (shown to every role). In components/client.blade.php the link is only restricted inside @if(in_array('employee', user_roles()) && !in_array('admin', user_roles())); every other role (admin AND client) gets <a href="{{ route('clients.show', [$user->id]) }}"> unconditionally
- Води към: clients.show (ClientController@show)
- Проверка/отказ: app/Http/Controllers/ClientController.php:67 abort_403(!in_array('clients', $this->user->modules)) - 'clients' is not in ModuleSetting::CLIENT_MODULES (app/Models/ModuleSetting.php:37-51), so the client role never has it; :614 also requires view_clients all/added/both
- Сценарий: Any company, client role (view_invoices = owned by default, so Finance -> Credit notes is in the sidebar, menu.blade.php:404). The same component also links for admins when the admin role has 'clients' switched off, in the client columns of ContractsDataTable:157, OrdersDataTable:107, RecurringInvoicesDataTable:220, ArchiveProjectsDataTable:106 and TicketDataTable:181, none of which check the clients module
- Ефект: The client clicks their own name or avatar in the credit-notes table and gets a 403 page
- Поправка: In components/client.blade.php link only when !in_array('client', user_roles()) && in_array('clients', user_modules()) && in_array(user()->permission('view_clients'), ['all','added','both']), otherwise render the plain name. Also set 'visible' => !in_array('client', user_roles()) on the CreditNotes name column.
- Проверка от втори агент: CreditNotesDataTable.php:93-96 renders components.client for the name column, which has no 'visible' key (:251). components/client.blade.php:34-35 and :56-57 link clients.show for every non-employee viewer, the client role included. ClientController.php:67 checks the clients module, which is not in CLIENT_MODULES (ModuleSetting.php:37-51). Clients reach credit notes with the default view_invoices=OWNED (PermissionRole.php:179, CreditNoteController.php:42-44, menu.blade.php:404).
- Намерено от: clients

### F084 · висока · мъртва връзка (403/404)

**Project overview: the client name links to the client profile for employees who have no client permission**

- Елемент: `resources/views/projects/ajax/overview.blade.php:153` – показва се при: @if ((!is_null($project->client) && in_array('clients', user_modules())) || in_array('client', user_roles())) ... @if (!in_array('client', user_roles())) <a href="{{ route('clients.show', $project->client_id) }}"> (no view_clients check)
- Води към: clients.show
- Проверка/отказ: app/Http/Controllers/ClientController.php:614-616 abort_403(!($viewPermission == 'all' || added... || both...)) on view_clients
- Сценарий: Any package (projects + clients in all). Default employee role: view_projects = owned, view_clients not set = none. Employee opens a project they are a member of that has a client
- Ефект: Clicking the client's name on the project overview card gives a 403
- Поправка: Wrap the <a> in @if (in_array('clients', user_modules()) && in_array(user()->permission('view_clients'), ['all','added','both'])), otherwise print the plain name.
- Проверка от втори агент: projects/ajax/overview.blade.php:152-155 link clients.show for every non-client with no view_clients check. The card itself requires only the clients module, which employees have. ClientController.php:614-616 abort_403 unless view_clients is all/added/both, and the default employee has none.
- Намерено от: clients

### F027 · средна · мъртва връзка (403/404)

**Profile settings 'Documents' tab (employees module) is shown, and Add file / Download / Edit / Delete inside it return 403, when the employees module is off**

- Елемент: `resources/views/profile-settings/index.blade.php:42` – показва се при: @if ($viewClientDocumentPermission != 'none' || $viewDocumentPermission != 'none') (tab); inside resources/views/profile-settings/ajax/employee/index.blade.php:21 @if ($addDocumentPermission == 'all') -> route('employee-docs.create') line 113; download line 62, edit line 121, destroy line 154
- Води към: profile-settings.index?tab=documents -> employee-docs.create / employee-docs.download / employee-docs.edit / employee-docs.destroy
- Проверка/отказ: app/Http/Controllers/EmployeeDocController.php:20 abort_403(!in_array('employees', $this->user->modules)); app/Http/Controllers/ProfileSettingController.php:46-49 (documents branch) checks only view_documents and has no module check
- Сценарий: Package 'Default' (companies 4, 5, 6) lacks employees. The company admin has view_documents=all and add_documents=all (view_documents belongs to the employees module, app/Models/Module.php:216) and opens Settings -> Profile settings -> Documents. Same for any role whose employees module is switched off.
- Ефект: The HR 'Documents' tab of a module not in the package is visible. '+ Add file' opens a 403 modal; Download/Edit/Delete on existing files also return 403.
- Поправка: index.blade.php:41: @if ((in_array('client', user_roles()) && $viewClientDocumentPermission != 'none') || (!in_array('client', user_roles()) && in_array('employees', user_modules()) && $viewDocumentPermission != 'none')). ProfileSettingController::index documents else-branch: abort_403(!in_array('employees', user_modules()) || $viewDocumentPermission == 'none').
- Проверка от втори агент: profile-settings/index.blade.php:41-46 shows the Documents tab for view_documents != none with no module check. ProfileSettingController.php:46-49 checks only view_documents. Inside, profile-settings/ajax/employee/index.blade.php:20 (+ Add), :62, :113, :121 and :154 call employee-docs.*, and EmployeeDocController.php:18-19 abort_403 without the employees module (Default). Lowered to medium because it is a settings tab.
- Намерено от: settings

### F070 · средна · модул извън пакета се вижда

**Employee profile header counters (Open tasks, Projects, Hours logged, Tickets) are shown regardless of modules**

- Елемент: `resources/views/employees/ajax/profile.blade.php:105` – показва се при: @if ($showFullProfile) footer with app.openTasks (:110), app.menu.projects (:115), modules.employees.hoursLogged (:120, ProjectTimeLog sum from EmployeeController.php:795-800), app.menu.tickets (:125). None has an in_array(..., user_modules()) check
- Води към: tasks/projects/timelogs/tickets data
- Проверка/отказ: None. The tabs right next to it are module-gated (show.blade.php:56,62,84,108)
- Сценарий: Trial/Starter company where the admin switched timelogs (or tasks/projects/tickets) off for the admin or employee role: 'Hours logged' etc. still show on every employee profile. 'Tickets' is also config-hidden (menu.tickets)
- Ефект: Counters of switched-off modules visible
- Поправка: Wrap each <span> in @if (in_array('<module>', user_modules())), and Tickets also in !databackd_hidden('menu','tickets').
- Проверка от втори агент: employees/ajax/profile.blade.php:105-128 always show the Open tasks, Projects, Hours logged and Tickets counters under $showFullProfile, with no module checks. The tabs next to them are module-gated (show.blade.php:56,62,84,108).
- Намерено от: hr

### F074 · средна · няма проверка на сървъра

**Employee profile tab handlers projects/tasks/leaves/leaves-quota do not check their module**

- Елемент: `app/Http/Controllers/EmployeeController.php:813` – показва се при: switch ($tab): 'projects' -> projects() :1160, 'tasks' -> tasks() :1190, 'leaves' -> leaves() :1205, 'leaves-quota' :847, 'attendance' -> attendance() :1221
- Води към: employees.show?tab=projects|tasks|leaves|leaves-quota|attendance
- Проверка/отказ: projects() :1164, tasks() :1193, leaves() :1209 and attendance() :1224 check only permissions. 'leaves-quota' has no check. Only tickets() :1179, timelogs() :1243 and shifts :929 include in_array(module, user_modules())
- Сценарий: Trial company with leaves or projects switched off for a role: ?tab=leaves renders LeaveDataTable, ?tab=projects renders ProjectsDataTable, ?tab=leaves-quota shows quotas (the tabs are hidden, the URLs still work)
- Ефект: Module data reachable by URL
- Поправка: Add && in_array('<module>', user_modules()) to the abort_403 in projects()/tasks()/leaves()/attendance(), and abort_403(!in_array('leaves', user_modules())) in the leaves-quota case.
- Проверка от втори агент: EmployeeController.php:812-823 tab switch. projects() (:1160-1164), tasks() (:1190-1193) and leaves() (:1205-1209) check only permissions. attendance() (:1221-1224) checks only view_attendance != none. The leaves-quota case (:847) has no check. tickets() (:1179) and timelogs() check the module.
- Намерено от: hr

### F087 · средна · мъртва връзка (403/404)

**Client profile tabs Projects/Invoices/Estimates/Payments are gated only by module, but their handlers also require view_* permissions**

- Елемент: `resources/views/clients/show.blade.php:33-38 (projects), :40-45 (invoices), :47-52 (estimates), :59-64 (payments)` – показва се при: @if (in_array('projects'|'invoices'|'estimates'|'payments', user_modules())) only
- Води към: clients.show?tab=projects|invoices|estimates|payments
- Проверка/отказ: ClientController.php:833 projects() requires view_projects all|added; :850 invoices() and :880 estimates() require view_* != none; :865 payments() requires view_payments all|added
- Сценарий: An employee given view_clients (all/added, e.g. sales/account manager) keeping the other default employee permissions: view_projects = owned, view_invoices/view_estimates/view_payments = none
- Ефект: All four tabs are shown and each one gives a 403
- Поправка: Add the handler's permission to each tab condition, e.g. && in_array(user()->permission('view_projects'), ['all','added']).
- Проверка от втори агент: clients/show.blade.php:33, :40, :47 and :59 check modules only. ClientController.php:833 projects() requires view_projects all|added, :850 invoices() and :880 estimates() need view_* != none in the allowed set, and :865 payments() requires all|added.
- Намерено от: clients

### F088 · средна · модул извън пакета се вижда

**Client profile 'Credit notes' tab has no module or permission condition; it shows credit notes with invoices off and 403s with view_invoices none**

- Елемент: `resources/views/clients/show.blade.php:54-57` – показва се при: None: the <li> with ?tab=creditnotes is rendered unconditionally
- Води към: clients.show?tab=creditnotes -> ClientController::creditnotes
- Проверка/отказ: ClientController.php:894 abort_403($viewPermission == 'none') on view_invoices only, no 'invoices' module check; the row links go to CreditNoteController (CreditNoteController.php:35 abort_403(!in_array('invoices', ...)))
- Сценарий: (a) The admin switches the invoices module off for the admin or employee role: the tab still lists credit notes and its row links 403. (b) An employee with view_clients and the default view_invoices = none: the tab is shown and gives a 403
- Ефект: Invoice-module data visible after the module is switched off; a dead tab for users without invoice permission
- Поправка: Wrap it in @if (in_array('invoices', user_modules()) && user()->permission('view_invoices') != 'none'), and add an invoices-module abort_403 to creditnotes().
- Проверка от втори агент: clients/show.blade.php:54-57 credit-notes tab has no @if. ClientController.php:889-894 creditnotes() checks only view_invoices != none, with no module check. CreditNoteController.php:35 abort_403 without invoices for the row links.
- Намерено от: clients

### F089 · средна · модул извън пакета се вижда

**Client profile 'Tickets' tab ignores the tickets module; its page fires a tickets AJAX call that returns 403**

- Елемент: `resources/views/clients/show.blade.php:87-92; AJAX at resources/views/clients/ajax/tickets.blade.php:222 (called on load at :244); buttons at tickets.blade.php:9 (tickets.create) and :191 (ticket-form.index)` – показва се при: @if ($viewTicket == 'all' || $viewTicket == 'added') with no in_array('tickets', user_modules())
- Води към: clients.show?tab=tickets (ClientController::tickets, no module check), tickets.refresh_count, tickets.create, ticket-form.index
- Проверка/отказ: TicketController.php:35 abort_403(!in_array('tickets', $this->user->modules)); TicketCustomFormController.php:17-19 abort(403); ClientController.php:931 only checks view_clients
- Сценарий: The admin switches tickets off for the admin role (view_tickets stays 'all')
- Ефект: The tab lists the client's tickets; loading it gives an AJAX 403 error toast; 'Add ticket', 'Ticket form' and the row links give 403
- Поправка: Add && in_array('tickets', user_modules()) && !databackd_hidden('menu','tickets') to the tab, and abort_403(!in_array('tickets', user_modules())) in ClientController::tickets().
- Проверка от втори агент: clients/show.blade.php:87-92 gates the tickets tab only on view_tickets all|added. ClientController::tickets (:926-931) has no tickets-module check. The tab's tickets.refresh_count AJAX (clients/ajax/tickets.blade.php:222, called at :244) and the create/form buttons hit TicketController, which aborts at :35 without tickets.
- Намерено от: clients

### F090 · средна · модул извън пакета се вижда

**Orders tab condition has an operator-precedence bug: the orders module check only applies to view_order = 'all'**

- Елемент: `resources/views/clients/show.blade.php:94-100` – показва се при: @if (in_array('orders', user_modules()) && $viewClientOrder == 'all' || ($viewClientOrder == 'added' && $client->clientDetails->added_by == user()->id) || ($viewClientOrder == 'owned' && ...) || ($viewClientOrder == 'both' && ...))
- Води към: clients.show?tab=orders -> ClientController::orders; button orders.create (clients/ajax/orders.blade.php:11)
- Проверка/отказ: ClientController.php:1131 checks only view_order (no module check, so the list renders); OrderController.php:51 abort_403(!in_array('orders', $this->user->modules)) for create and the row links
- Сценарий: Package 'Default' (companies 4, 5, 6, no orders): an employee with view_clients and view_order = added/both opens a client they added
- Ефект: The Orders tab of a module missing from the package is shown and lists orders; 'Add order' and the row links give 403
- Поправка: @if (in_array('orders', user_modules()) && !databackd_hidden('menu','orders') && ($viewClientOrder == 'all' || (...) || (...) || (...)))
- Проверка от втори агент: clients/show.blade.php:94: `in_array('orders', user_modules()) && $viewClientOrder == 'all' || (...added...) || (...owned...) || (...both...)`. && binds tighter than ||, so the module check applies only to 'all'. ClientController.php:1127-1131 orders() checks only view_order, and OrderController.php:51 returns 403 without orders.
- Намерено от: clients

### F091 · средна · няма проверка на сървъра

**ClientController::show tab handlers never check the module of the tab, so ?tab= URLs bypass the package**

- Елемент: `app/Http/Controllers/ClientController.php:636-661 (switch on request('tab'))` – показва се при: Any user who can open the client profile (clients module + view_clients) and types ?tab=orders|projects|invoices|estimates|creditnotes|payments|tickets
- Води към: ClientController::projects/invoices/payments/estimates/creditnotes/orders/tickets (lines 828, 845, 860, 875, 889, 1127, 926)
- Проверка/отказ: Missing: only the 'clients' module (:67) and the view_* permission are checked; there is no in_array('<module>', user_modules())
- Сценарий: Package 'Default' (no orders): the admin (view_order = all) opens /account/clients/{id}?tab=orders and gets the full orders DataTable. Same for any tab whose module the admin switched off for a role
- Ефект: Data from modules missing from the package is reachable by URL; the tab content renders
- Поправка: Add abort_403(!in_array('<module>', user_modules())) in each handler (invoices for creditnotes).
- Проверка от втори агент: ClientController.php:636-661 tab switch. The handlers projects (:828), invoices (:845), payments (:860), estimates (:875), creditnotes (:889), tickets (:926) and orders (:1127) check only view_* permissions; the constructor (:67) checks only 'clients'.
- Намерено от: clients

### F095 · средна · мъртва връзка (403/404)

**Client profile 'Edit' dropdown and 'Approve' button are shown without the permission or role their actions require**

- Елемент: `resources/views/clients/ajax/profile.blade.php:50-51 (Edit); :11-13 with JS at :238 (Approve)` – показва се при: Edit: always rendered. Approve: @if (!$client->admin_approval) for any viewer
- Води към: clients.edit; clients.approve
- Проверка/отказ: ClientController.php:339 abort_403 unless edit_clients is all, or added/both and the user added the client; :992 abort_403(!in_array('admin', user_roles())). By contrast ClientsDataTable.php:58 and :62 do gate both actions
- Сценарий: An employee with view_clients = all and edit_clients = none (or added, opening a client someone else added); an employee opening a client that is pending approval
- Ефект: Clicking Edit opens a modal that 403s; clicking Approve and confirming returns a 403 error
- Поправка: Wrap Edit in the ClientsDataTable.php:62 permission condition and the Approve button in @if (in_array('admin', user_roles())).
- Проверка от втори агент: clients/ajax/profile.blade.php:50-51 renders Edit unconditionally. :4-17 render Approve whenever !admin_approval. ClientController.php:339 edit requires edit_clients all, or added/both plus added_by, and :992 approve requires the admin role.
- Намерено от: clients

### F097 · средна · модул извън пакета се вижда

**Clients list filters 'Project' and 'Contract type' and the profile 'Projects' pie chart ignore the projects/contracts modules**

- Елемент: `resources/views/clients/index.blade.php:98-111 (Project filter), :113-126 (Contract type filter); resources/views/clients/ajax/profile.blade.php:184-195 (Projects pie chart)` – показва се при: No condition; ClientController.php:89-90 always loads Project::all() and ContractType::all(). The pie chart sits outside any @if, while the Total projects widget next to it (profile.blade.php:78) and the invoices chart (:196) are gated
- Води към: n/a (UI content of the projects/contracts modules)
- Проверка/отказ: n/a (no 403; content of switched-off modules is shown)
- Сценарий: The admin switches projects or contracts off for a role (all prod packages contain both); a user opens Clients
- Ефект: Project names, contract types and a project-status chart are visible although the module is off
- Поправка: Wrap the filters in @if (in_array('projects'|'contracts', user_modules())) and the pie chart in @if (in_array('projects', user_modules())). Skip loading the data when the module is off.
- Проверка от втори агент: clients/index.blade.php:98-111 (Project filter) and :113-126 (Contract type) have no condition; ClientController.php:89-90 loads Project::all()/ContractType::all(). clients/ajax/profile.blade.php:184-195 renders the projects pie chart outside any module @if, while the invoices chart is gated (:196).
- Намерено от: clients

### F108 · средна · модул извън пакета се вижда

**Project 'Tickets' tab has no tickets module check and uses the wrong permission (view_project_note)**

- Елемент: `resources/views/projects/show.blade.php:161` – показва се при: @if ($viewNotePermission != 'none') where $viewNotePermission = user()->permission('view_project_note') (line 15); no in_array('tickets', user_modules())
- Води към: projects.show?tab=tickets -> ProjectController@tickets
- Проверка/отказ: app/Http/Controllers/ProjectController.php:1726-1733 checks only view_tickets in [all, added, owned, both] (no module check); rows and the 'Add Ticket' button (projects/ajax/tickets.blade.php:10-14) go to TicketController, which aborts at TicketController.php:35 when tickets is off
- Сценарий: (a) Company admin switches tickets off for the employee or client role: the tab still shows and lists tickets, but row links and Add Ticket return 403. (b) Admin sets employee view_tickets=none while view_project_note stays all (default): the tab is visible and clicking it returns 403
- Ефект: A Tickets tab on every project for a module that is off, and a 403 on the tab itself or its links
- Поправка: @if (in_array('tickets', user_modules()) && !databackd_hidden('menu','tickets') && user()->permission('view_tickets') != 'none'), and add a tickets-module abort_403 in ProjectController::tickets().
- Проверка от втори агент: projects/show.blade.php:161 gates the Tickets tab on $viewNotePermission (view_project_note, :15) with no tickets module or databackd_hidden check. ProjectController.php:1726-1733 checks only view_tickets, and TicketController aborts without tickets for the row and Add Ticket links. Tickets is also config-hidden, so the tab leaks the hidden feature on every project.
- Намерено от: projects

### F109 · средна · модул извън пакета се вижда

**Project overview Earnings/Spending, Expenses total and Profit widgets ignore the payments and expenses modules**

- Елемент: `resources/views/projects/ajax/overview.blade.php:240` – показва се при: @if ($viewPaymentPermission == 'all') (240, 255) and @if ($viewExpensePermission == 'all') (248); block condition at line 214, no user_modules() check
- Води към: projects.show (overview)
- Проверка/отказ: ProjectController.php:1031-1119 sums Payment/Expense with no module check
- Сценарий: The payments module switched off for the client role (client view_project_payments=all by default) or for admin/employee, or the expenses module switched off for the admin or employee role
- Ефект: Money totals from a disabled module are shown on the project landing page
- Поправка: Add && in_array('payments', user_modules()) / && in_array('expenses', user_modules()) to these widget conditions (and to the block condition at :214).
- Проверка от втори агент: projects/ajax/overview.blade.php:240 (earnings), :248 (expenses) and :255 (profit) check only view_project_payments/expenses == 'all', with no payments/expenses module check. ProjectController computes the sums unconditionally.
- Намерено от: projects

### F110 · средна · модул извън пакета се вижда

**Task-based project views (Gantt row action, Burndown tab, Tasks pie, template Tasks tab) stay visible when the tasks module is off**

- Елемент: `app/DataTables/ProjectsDataTable.php:91` – показва се при: 'View Gantt Chart' row action gated only by view_project_gantt_chart (line 91); show.blade.php:147 Burndown tab gated by view_project_burndown_chart only; overview.blade.php:201 Tasks pie gated by view_tasks != 'none'; project-templates/show.blade.php:24 Tasks tab unconditional
- Води към: projects.show?tab=gantt / ?tab=burndown-chart, projects.burndown
- Проверка/отказ: ProjectController.php:958-968 (gantt) and 995-999 / 1742-1746 (burndown) check no module; gantt task links go to tasks.show -> TaskController.php:60 aborts 403
- Сценарий: Tasks module switched off for the admin or employee role (show.blade.php:65 hides the Tasks/Taskboard/Gantt tabs, but these entry points remain)
- Ефект: Gantt chart, burndown chart and task-status pie of a disabled module; clicking a task in the gantt returns 403
- Поправка: Add in_array('tasks', user_modules()) to ProjectsDataTable.php:91, projects/show.blade.php:147, projects/ajax/overview.blade.php:201 and project-templates/show.blade.php:24. Add abort_403(!in_array('tasks', user_modules())) to the gantt and burndown-chart cases of ProjectController::show() and to ganttData()/burndown().
- Проверка от втори агент: app/DataTables/ProjectsDataTable.php:91 'View Gantt Chart' row action checks only viewGanttPermission (no tasks module), while projects/show.blade.php:65 puts the Gantt tab inside in_array('tasks', user_modules()). ProjectController.php:958-968 (gantt case) has no module check, and gantt_dhtml.blade.php:609 opens tasks.show, which aborts at TaskController.php:60. Burndown tab show.blade.php:147 has no tasks check. Tasks pie projects/ajax/overview.blade.php:201 checks only view_tasks != 'none'. project-templates/show.blade.php:24 Tasks tab is unconditional. Separately, the burndown tab shows for != 'none' but burndownChart() at ProjectController.php:1744-1746 requires 'all'.
- Намерено от: projects

### F114 · средна · мъртва връзка (403/404)

**Overview Action menu always shows the public Gantt / public Taskboard links, even when they are disabled for the project**

- Елемент: `resources/views/projects/ajax/overview.blade.php:64` – показва се при: Inside the edit-project permission block only; no check of $project->public_gantt_chart / public_taskboard (ProjectsDataTable.php:98 and 105 do check == 'enable')
- Води към: front.gantt / front.taskboard (HomeController@gantt / @taskboard)
- Проверка/отказ: app/Http/Controllers/HomeController.php:450 abort(403) when public_gantt_chart != 'enable'; HomeController.php:588 abort(403) when public_taskboard != 'enable'
- Сценарий: An admin sets 'View public gantt chart' or 'Public task board' to Disable in the project form, then opens Project overview -> Action
- Ефект: A link opens a 403 page in a new tab
- Поправка: Wrap each link in @if ($project->public_gantt_chart == 'enable') / @if ($project->public_taskboard == 'enable').
- Проверка от втори агент: projects/ajax/overview.blade.php:64-72 renders the public Gantt and Taskboard links inside the edit-permission block only. ProjectsDataTable.php:98,105 check == 'enable'. HomeController.php:450-453 and :588-591 abort(403) when the setting is disabled. The setting can be turned off at ProjectController.php:796-797.
- Намерено от: projects

### F115 · средна · мъртва връзка (403/404)

**Project Tasks tab is shown based on view_project_tasks, but the controller also requires view_tasks**

- Елемент: `resources/views/projects/show.blade.php:65` – показва се при: in_array('tasks', user_modules()) && ($viewTasksPermission == 'all' || ...) where $viewTasksPermission = view_project_tasks (line 10)
- Води към: projects.show?tab=tasks -> ProjectController@tasks
- Проверка/отказ: app/Http/Controllers/ProjectController.php:1324-1325 abort_403(!in_array(user()->permission('view_tasks'), ['all','added','owned','both'])) for non project-admins
- Сценарий: Admin sets employee or client view_tasks=none (to hide the Tasks menu) and leaves view_project_tasks=all (the default)
- Ефект: Tasks tab visible on every project; clicking it returns 403
- Поправка: Add && user()->permission('view_tasks') != 'none' (or check the project admin) to the Tasks tab condition in projects/show.blade.php:65.
- Проверка от втори агент: projects/show.blade.php:65 checks only view_project_tasks (line 10). ProjectController.php:1324-1325 (tasks) and :1343-1344 (archivedTasks) also abort unless view_tasks is in all/added/owned/both. The defaults (PermissionRole.php:69,152) are fine, so this needs view_tasks=none.
- Намерено от: projects

### F116 · средна · няма проверка на сървъра

**ProjectController::show() tab switch and the gantt-data/burndown endpoints serve other modules' data without module checks**

- Елемент: `app/Http/Controllers/ProjectController.php:937` – показва се при: switch ($tab) cases taskboard (950), tasks (954), gantt (958), invoices (969), estimates (971), timelogs (976), expenses (978), payments (985), burndown-chart (995), tickets (1009), orders (1011) only check permissions; ganttData() (1357) and burndown() (1625) check nothing
- Води към: /account/projects/{id}?tab=timelogs|orders|invoices|payments|expenses|estimates|tickets|tasks|gantt; projects.gantt_data; projects.burndown
- Проверка/отказ: Missing: only the constructor check ProjectController.php:78 (projects module) applies; the DataTables used (TimeLogsDataTable, OrdersDataTable, ExpensesDataTable, TicketDataTable...) have no module check either
- Сценарий: Package Default (no timelogs, no orders): admin opens /account/projects/5?tab=timelogs or ?tab=orders by URL (the tabs are hidden in show.blade.php but the cases still render). Same for any module switched off per role
- Ефект: Full timelog/order/invoice/expense lists of a module that is not in the package
- Поправка: In each tab case add abort_403(!in_array('<module>', user_modules())) (timelogs, orders, invoices, estimates, payments, expenses, tickets; tasks for tasks/taskboard/gantt/burndown-chart). Add the tasks check and a view permission check to ganttData()/burndown().
- Проверка от втори агент: ProjectController::show() switch (lines 937-1012) and the helpers invoices() :1429, estimates() :1411, timelogs() :1560, expenses() :1577, payments() :1592, orders() :2210, tickets() :1728 check only permissions. ganttData() :1357 and burndown() :1625 check nothing. The only gate is the constructor projects check at :78. TimeLogsDataTable, ExpensesDataTable and TicketDataTable have no module check.
- Намерено от: projects

### F133 · средна · модул извън пакета се вижда

**Project template 'Tasks' tab and template task screens remain when the tasks module is off**

- Елемент: `resources/views/project-templates/show.blade.php:24` – показва се при: <x-tab ... '?tab=tasks' :text="__('app.menu.tasks')"> is rendered with no tasks module check.
- Води към: project-template.show?tab=tasks, project-template-task.*
- Проверка/отказ: app/Http/Controllers/ProjectTemplateTaskController.php:25 checks only abort_403(!in_array('projects', $this->user->modules))
- Сценарий: The company admin switches Tasks off for a role that manages project templates (projects still on).
- Ефект: Users can view and create template tasks (with categories, labels and assignees) although the tasks module is off for them.
- Поправка: Wrap the Tasks tab in @if (in_array('tasks', user_modules())), and add the tasks module check to ProjectTemplateTaskController, ProjectTemplateSubTaskController and the template 'tasks' tab case.
- Проверка от втори агент: project-templates/show.blade.php:24-25 Tasks tab is rendered unconditionally. ProjectTemplateTaskController.php:25 checks only the projects module. Happens when tasks is switched off for a role that keeps projects.
- Намерено от: tasks-timelogs

### F160 · средна · модул извън пакета се вижда

**Client profile "Credit Notes" tab has no invoices-module check, and ClientController::creditnotes has none either**

- Елемент: `resources/views/clients/show.blade.php:54` – показва се при: <x-tab ...?tab=creditnotes> rendered unconditionally (the neighbouring Invoices/Estimates/Payments tabs check user_modules() at :40, :47, :59)
- Води към: clients.show?tab=creditnotes -> ClientController::creditnotes; row links creditnotes.show
- Проверка/отказ: app/Http/Controllers/ClientController.php:889-900 only `abort_403($viewPermission == 'none')`; creditnotes.show is gated at app/Http/Controllers/CreditNoteController.php:35 abort_403(!in_array('invoices', ...))
- Сценарий: Invoices switched off for a role that has clients (e.g. employee).
- Ефект: The user sees the Credit Notes tab and the client's credit notes (a disabled module). Opening one gives a 403.
- Поправка: Wrap the tab in @if (in_array('invoices', user_modules())) and add abort_403(!in_array('invoices', user_modules())) in ClientController::creditnotes().
- Проверка от втори агент: clients/show.blade.php:54-55 Credit Notes tab has no module check, while Invoices (:40), Estimates (:47) and Payments (:59) do. ClientController.php:889-900 checks only view_invoices != none. CreditNoteController gates on invoices.
- Намерено от: invoices-sales

### F161 · средна · модул извън пакета се вижда

**Client profile "Orders" tab: operator precedence applies the orders-module check only to the 'all' branch**

- Елемент: `resources/views/clients/show.blade.php:94` – показва се при: @if (in_array('orders', user_modules()) && $viewClientOrder == 'all' || ($viewClientOrder == 'added' && ...) || ($viewClientOrder == 'owned' && ...) || ($viewClientOrder == 'both' && ...)): && binds tighter than ||
- Води към: clients.show?tab=orders -> ClientController::orders; tab button orders.create (resources/views/clients/ajax/orders.blade.php:11)
- Проверка/отказ: ClientController::orders (app/Http/Controllers/ClientController.php:1127-1131) has no module check; orders.create is gated at app/Http/Controllers/OrderController.php:51
- Сценарий: Orders not in the package (Default) or switched off for the role, and a user with view_order = added/owned/both (custom employee role) opens a client they added.
- Ефект: The Orders tab of a missing module is shown and its list renders. "Create order" gives a 403.
- Поправка: Use @if (in_array('orders', user_modules()) && !databackd_hidden('menu', 'orders') && ($viewClientOrder == 'all' || ...)), and add the orders check in ClientController::orders().
- Проверка от втори агент: clients/show.blade.php:94 is in_array('orders', ...) && $viewClientOrder == 'all' || (...added...) || (...owned...) || (...both...). && binds tighter than ||, so the module check covers only the 'all' branch. ClientController.php:1127-1131 has no module check.
- Намерено от: invoices-sales

### F069 · ниска · мъртва връзка (403/404)

**Employee profile 'Appreciation' tab shown without checking view_appreciation; the tab handler 403s**

- Елемент: `resources/views/employees/show.blade.php:114` – показва се при: @if ($showFullProfile && !in_array('client', user_roles())). $viewAppreciationPermission is set at :18 but never used. Same tab bar is duplicated in employees/ajax/attendance.blade.php:125
- Води към: employees.show?tab=appreciation
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:840-842 abort_403(!in_array($viewAppreciationPermission, ['all','added','owned','both']))
- Сценарий: Role with view_employees all/owned (full profile) and view_appreciation = none, for example a manager role. Clicking the tab returns 403
- Ефект: AJAX tab fails with 403
- Поправка: Add && $viewAppreciationPermission != 'none' && !databackd_hidden('menu', 'appreciation') to the tab in both tab bars.
- Проверка от втори агент: employees/show.blade.php:114 shows the Appreciation tab on $showFullProfile only. $viewAppreciationPermission (:18) is unused. EmployeeController.php:840-842 abort_403 unless view_appreciation is all/added/owned/both. The default employee has OWNED, so this needs a custom role. menu.appreciation is also config-hidden.
- Намерено от: hr

### F081 · ниска · мъртва връзка (403/404)

**Employee profile 'Leaves' tab is shown on view_leave, but the handler requires view_leaves_taken = all**

- Елемент: `resources/views/employees/show.blade.php:74` – показва се при: @if (in_array('leaves', user_modules()) && ($viewLeavePermission == 'all' || ($viewLeavePermission == 'owned' || $viewLeavePermission == 'both') && $employee->id == user()->id ))
- Води към: employees.show?tab=leaves (EmployeeController@leaves)
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:1208-1209 abort_403(!in_array(user()->permission('view_leaves_taken'), ['all']))
- Сценарий: Custom role with view_leave all/owned/both and view_leaves_taken = none (the default employee role has ALL, PermissionRole.php:106) opens a profile and clicks Leaves
- Ефект: 403 page (tab is ajax=false)
- Поправка: Add && user()->permission('view_leaves_taken') == 'all' to the Leaves tab condition.
- Проверка от втори агент: employees/show.blade.php:74 shows the Leaves tab on view_leave. EmployeeController.php:1205-1209 leaves() abort_403 unless view_leaves_taken == 'all'. The default employee has ALL (PermissionRole.php:106), so this needs a custom role.
- Намерено от: hr

### F096 · ниска · мъртва връзка (403/404)

**Bulk actions on the clients list and the contacts tab require 'all' (contacts even use the wrong permission) but are offered to everyone**

- Елемент: `resources/views/clients/index.blade.php:185-198 (quick-action select: change-status, delete); resources/views/clients/ajax/contacts.blade.php:19-26` – показва се при: Always rendered; the row checkboxes (ClientsDataTable/ClientContactsDataTable 'check' column) are always present
- Води към: clients.apply_quick_action; client-contacts.apply_quick_action
- Проверка/отказ: ClientController.php:576 abort_403(delete_clients !== 'all'), :587 abort_403(edit_clients !== 'all'); ClientContactController.php:183 abort_403(user()->permission('delete_clients') !== 'all'), where it should check delete_client_contacts
- Сценарий: An employee with view_clients = added and edit/delete_clients = added (allowed values); or a user with delete_client_contacts = all but delete_clients = none
- Ефект: Selecting rows and pressing Apply returns a 403 error
- Поправка: Render the change-status/delete options only when edit_clients/delete_clients == 'all'. In ClientContactController::deleteRecords check delete_client_contacts, and gate that option the same way.
- Проверка от втори агент: clients/index.blade.php:185-198 and clients/ajax/contacts.blade.php:19-26 always render the bulk actions. ClientController.php:576 and :587 need delete_clients/edit_clients === 'all'. ClientContactController.php:183 checks delete_clients instead of delete_client_contacts. A permission mismatch, not a module leak.
- Намерено от: clients

### F099 · ниска · мъртва връзка (403/404)

**Client note 'Edit' is shown based on edit_client_note but ClientNoteController::edit checks view_client_note**

- Елемент: `app/DataTables/ClientNotesDataTable.php:55-60` – показва се при: $this->editClientNotePermission == 'all' || (added|both && user()->id == $row->added_by)
- Води към: client-notes.edit
- Проверка/отказ: ClientNoteController.php:163-167 $editClientNotePermission = user()->permission('view_client_note'); abort_403 unless that is all, or added/both and the user added the note
- Сценарий: A role with edit_client_note = all and view_client_note = owned (both allowed values)
- Ефект: The Edit action in the client Notes tab opens a modal that 403s
- Поправка: ClientNoteController::edit: $editClientNotePermission = user()->permission('edit_client_note');
- Проверка от втори агент: ClientNotesDataTable.php:55-60 shows Edit on editClientNotePermission. ClientNoteController.php:161-167 edit() reads user()->permission('view_client_note') for its abort_403.
- Намерено от: clients

### F137 · ниска · модул извън пакета се вижда

**Task screens show filters and fields of other modules: Client filter without clients, Milestones row without projects, leave warnings without leaves**

- Елемент: `resources/views/tasks/index.blade.php:104` – показва се при: The 'Client' filter (lines 103-118) has no in_array('clients', user_modules()); the Project and Milestone filters next to it are gated. The task detail 'Milestones' row (resources/views/tasks/ajax/show.blade.php:282) is ungated, while the form gates it (tasks/ajax/create.blade.php:302). The task form's tasks.checkLeaves calls (create.blade.php:640/675/708) and the edit leaveData (TaskController.php:870-883) read Leave records with no leaves module check (TaskController::checkLeaves :1475).
- Води към: tasks.index filters, tasks.show, tasks.checkLeaves
- Проверка/отказ: No module checks in the views or in TaskController::checkLeaves / leaves()
- Сценарий: Clients or projects switched off for a role, or a Default-package company (no leaves) with existing leave rows.
- Ефект: Filters, rows and 'employee is on leave' warnings from modules that are not available are shown.
- Поправка: Wrap the Client filter in in_array('clients', user_modules()) and the Milestones row in in_array('projects', user_modules()). Return early from checkLeaves()/leaves() unless in_array('leaves', user_modules()).
- Проверка от втори агент: tasks/index.blade.php:103-118 Client filter has no clients check, while the Project filter at :87 is gated. tasks/ajax/show.blade.php:282-283 Milestones row is ungated, while the form gates it (create.blade.php:302). TaskController::checkLeaves reads leaves with no module check. Low impact.
- Намерено от: tasks-timelogs

## G06. Полета и колони от други модули във финансовите форми и списъци (28)

### F101 · средна · мъртва връзка (403/404)

**Contracts list client column links to clients.show with no clients-module or view_clients check**

- Елемент: `app/DataTables/ContractsDataTable.php:163-171 ('client.name' column, inline <a href=route('clients.show')>)` – показва се при: Column visible when !in_array('client', user_roles()) (ContractsDataTable.php:327); no module/permission check on the link
- Води към: clients.show
- Проверка/отказ: ClientController.php:67 (clients module) and :614 (view_clients all/added/both)
- Сценарий: An employee given view_contract = all but default view_clients = none; or the admin with clients switched off for the admin role
- Ефект: The client name/avatar in the contracts list gives a 403
- Поправка: Render the <a> only when in_array('clients', user_modules()) && in_array(user()->permission('view_clients'), ['all','added','both']), otherwise plain text.
- Проверка от втори агент: ContractsDataTable.php:164-172 builds inline links to clients.show with no module or permission check. The column is visible to every non-client (:327). ClientController.php:67 checks the module and :614-616 view_clients.
- Намерено от: clients

### F141 · средна · модул извън пакета се вижда

**Payments list always shows the "Order #" column (orders module missing from the Default package and hidden by config); its links go to orders.show, which 403s**

- Елемент: `app/DataTables/PaymentsDataTable.php:328` – показва се при: Column defined unconditionally: `__('app.order') . '#' => ['data' => 'order_number', ...]`; cell link at app/DataTables/PaymentsDataTable.php:149 `route('orders.show', $row->order_id)`
- Води към: orders.show (OrderController@show)
- Проверка/отказ: app/Http/Controllers/OrderController.php:51 abort_403(!in_array('orders', $this->user->modules))
- Сценарий: Any Default-package company (no orders): admin or client opens Finance -> Payments. The same table is used on the client-profile and project Payments tabs. Trial/Starter companies see it too, although orders are hidden by config.
- Ефект: An Orders column is visible in the main Payments list for companies whose package has no Orders module. Where a payment has an order, the link 403s.
- Поправка: Add 'visible' => in_array('orders', user_modules()) && !databackd_hidden('menu', 'orders') and the same 'exportable' to the Order# column, and render plain text when the module is off.
- Проверка от втори агент: PaymentsDataTable.php:328 Order# column has no visible/exportable condition, and :147-152 links to orders.show. OrderController.php:51 aborts without orders, and orders is hidden by config. Medium, not high: on Default the column is mostly '--', because payments with orders cannot exist there.
- Намерено от: invoices-sales

### F142 · средна · мъртва връзка (403/404)

**Changing the currency in invoice/recurring-invoice (and expense) forms calls payments.account_list, which 403s when the payments module is off for the role**

- Елемент: `resources/views/invoices/ajax/create.blade.php:1739` – показва се при: Unconditional `$('#currency_id').change(...)` that sends $.easyAjax to `route('payments.account_list')`. Same at resources/views/invoices/ajax/edit.blade.php:1359, resources/views/recurring-invoices/ajax/create.blade.php:1212, resources/views/recurring-invoices/edit.blade.php:1110. Out of area: resources/views/expenses/ajax/create.blade.php:287, expenses/ajax/edit.blade.php:364, recurring-expenses/ajax/create.blade.php:402, recurring-expenses/ajax/edit.blade.php:475.
- Води към: payments.account_list (PaymentController@accountList)
- Проверка/отказ: app/Http/Controllers/PaymentController.php:34 constructor middleware abort_403(!in_array('payments', $this->user->modules)) covers every method, including accountList
- Сценарий: Company admin switches Payments off for a role (e.g. employee) and leaves Invoices or Expenses on. The user creates an invoice or expense and picks a different currency.
- Ефект: Error toast. The bank-account list and the exchange rate are not refreshed, so the user has to fix them by hand or saves wrong data.
- Поправка: Move accountList (bank accounts plus exchange rate by currency) to a route outside the payments gate (for example in BankAccountController or InvoiceController), or exempt accountList from the payments middleware with an invoices || payments || expenses check.
- Проверка от втори агент: invoices/ajax/create.blade.php:1739, invoices/ajax/edit.blade.php:1359, recurring-invoices/ajax/create.blade.php:1212, recurring-invoices/edit.blade.php:1110 and the four expense forms call route('payments.account_list') (web.php:699). The PaymentController.php:34 constructor aborts without payments.
- Намерено от: invoices-sales

### F143 · средна · модул извън пакета се вижда

**Invoice create/edit shows the "Received payment" block when payments is off: choosing Offline calls offline.methods (403), and saving still creates a Payment**

- Елемент: `resources/views/invoices/ajax/create.blade.php:920` – показва се при: Unconditional payment_status checkbox and gateway select (create.blade.php:915-970; edit at resources/views/invoices/ajax/edit.blade.php:625). Picking 'Offline' sends AJAX to route('offline.methods') at create.blade.php:1775 / edit.blade.php:1396.
- Води към: offline.methods (PaymentController@offlineMethods); InvoiceObserver creates a Payment row (app/Observers/InvoiceObserver.php:225)
- Проверка/отказ: app/Http/Controllers/PaymentController.php:34 abort_403(!in_array('payments', ...)); InvoiceObserver.php:225 creates the Payment with no module check
- Сценарий: Payments switched off for the role; invoices on.
- Ефект: The user sees the payments-recording UI. Choosing Offline gives an error toast and the required offline-method select stays hidden and empty. Ticking the box writes a completed Payment although the Payments module is off for that role.
- Поправка: Wrap the received-payment block in @if (in_array('payments', user_modules())) in create/edit, and create the Payment in InvoiceObserver only when the module is active.
- Проверка от втори агент: invoices/ajax/create.blade.php:915-970 shows the 'Received payment' block with no condition, and :1775 calls offline.methods (web.php:700, PaymentController gate :34). InvoiceObserver.php:225-232 creates a Payment with no module check. Same at edit.blade.php:625 and :1396.
- Намерено от: invoices-sales

### F144 · средна · мъртва връзка (403/404)

**Payment create/edit always shows a Project select; changing it calls projects.invoice_list, which 403s when projects is off**

- Елемент: `resources/views/payments/ajax/create.blade.php:18` – показва се при: `@else <x-forms.select fieldId="payment_project_id" ...>` with no projects-module check. On change it posts to route('projects.invoice_list') (create.blade.php:299; edit at resources/views/payments/ajax/edit.blade.php:11/305). The form is reached from the topbar "Add Payment" (resources/views/sections/topbar.blade.php:170), the Payments index and the invoice "Add payment" action.
- Води към: projects.invoice_list (ProjectController@invoiceList)
- Проверка/отказ: app/Http/Controllers/ProjectController.php:78 abort_403(!in_array('projects', $this->user->modules))
- Сценарий: Projects switched off for a role that has payments with add_payments all/added.
- Ефект: The project list is visible, and selecting a project gives an error toast. The invoice and bank lists are not filtered.
- Поправка: Wrap the project field in @if (in_array('projects', user_modules())), and move the invoice-by-project lookup out of ProjectController.
- Проверка от втори агент: payments/ajax/create.blade.php:17-26 Project select has no projects check, and :299 posts to projects.invoice_list (web.php:269, ProjectController). ProjectController.php:78 aborts without projects. Same at edit.blade.php:305.
- Намерено от: invoices-sales

### F145 · средна · мъртва връзка (403/404)

**Payments list Project column (and project Code) always visible; links go to projects.show, which 403s when projects is off (Invoice# column likewise links to invoices.show)**

- Елемент: `app/DataTables/PaymentsDataTable.php:323` – показва се при: Columns `modules.taskCode` (line 322) and `app.project` (line 323) have no 'visible' condition; cell link at PaymentsDataTable.php:92 `route('projects.show', $row->project_id)`; Invoice# cell link at :96 `route('invoices.show', ...)`
- Води към: projects.show / invoices.show
- Проверка/отказ: app/Http/Controllers/ProjectController.php:78 (projects module); app/Http/Controllers/InvoiceController.php:61 (invoices module)
- Сценарий: Projects (or invoices) switched off for a role that still has payments. Also shows on the client-profile Payments tab.
- Ефект: A Project column belonging to a disabled module is shown, and clicking a project name gives a 403 page.
- Поправка: Add 'visible' => in_array('projects', user_modules()), 'exportable' => in_array('projects', user_modules()) to the Code and Project columns, and render plain text instead of links when projects or invoices is off.
- Проверка от втори агент: PaymentsDataTable.php:322-323 Code and Project columns have no visible condition. :87-92 links to projects.show (ProjectController.php:78), and :94-96 links to invoices.show (InvoiceController module gate).
- Намерено от: invoices-sales

### F147 · средна · модул извън пакета се вижда

**Invoice edit and recurring-invoice create/edit forms show the Project select without a projects-module check (invoice create has the check)**

- Елемент: `resources/views/invoices/ajax/edit.blade.php:125` – показва се при: Project select rendered unconditionally (edit.blade.php:123-141); also resources/views/recurring-invoices/ajax/create.blade.php:57-80 and resources/views/recurring-invoices/edit.blade.php:66-84. Invoice create gates it at resources/views/invoices/ajax/create.blade.php:153 `@if(in_array('projects', user_modules()))`.
- Води към: n/a (form field, filled via clients.project_list)
- Проверка/отказ: missing
- Сценарий: Projects switched off for a role that can edit invoices or create recurring invoices.
- Ефект: The user sees and can set the Project of an invoice although the Projects module is off.
- Поправка: Wrap the PROJECT block in @if (in_array('projects', user_modules())) in the three forms.
- Проверка от втори агент: invoices/ajax/edit.blade.php:123-141, recurring-invoices/ajax/create.blade.php:57 and recurring-invoices/edit.blade.php:66 render the Project select with no condition. invoices/ajax/create.blade.php:153 gates it with in_array('projects', user_modules()).
- Намерено от: invoices-sales

### F148 · средна · модул извън пакета се вижда

**Bank account select in the invoice, recurring-invoice and payment forms is gated only by the link_*_bank_account permission, not by the bankaccount module**

- Елемент: `resources/views/invoices/ajax/create.blade.php:220` – показва се при: @if($linkInvoicePermission == 'all'). Same at invoices/ajax/edit.blade.php:158, recurring-invoices/ajax/create.blade.php:118, recurring-invoices/edit.blade.php:122; payments/ajax/create.blade.php:142 `@if($linkPaymentPermission == 'all')`
- Води към: n/a (lists bank accounts)
- Проверка/отказ: app/Http/Controllers/BankAccountController.php:26 abort_403(!in_array('bankaccount', ...)). Permissions are module-agnostic (User::permission), so the admin keeps 'all' after the module is switched off.
- Сценарий: Company admin switches the Bank Account module off for the admin or employee role in Settings -> Module settings.
- Ефект: The bank-account select with the company's bank accounts is still shown and linked to invoices and payments.
- Поправка: Prefix each condition with in_array('bankaccount', user_modules()) &&.
- Проверка от втори агент: invoices/ajax/create.blade.php:220, edit.blade.php:158, the recurring-invoice forms and payments/ajax/create.blade.php:142 check only the link_*_bank_account permission. BankAccountController.php:26 gates the module, and User::permission ignores modules.
- Намерено от: invoices-sales

### F149 · средна · мъртва връзка (403/404)

**"Add credit note" is shown on invoices without checking add_invoices; CreditNoteController@create requires add_invoices all/added**

- Елемент: `app/DataTables/InvoicesDataTable.php:215` – показва се при: `if ($this->packageAllowInvoice && $row->credit_note == 0 && $row->status != 'draft' && $row->status != 'canceled' && $row->status != 'unpaid' && !in_array('client', user_roles()))` with amountPaid() > 0 and no permission check. Also :217 (JS -> resources/views/invoices/index.blade.php:393), resources/views/invoices/ajax/show.blade.php:789/794, app/DataTables/RecurringInvoicesDataTable.php:169/173
- Води към: creditnotes.create?invoice={id}
- Проверка/отказ: app/Http/Controllers/CreditNoteController.php:63 abort_403(!in_array(user()->permission('add_invoices'), ['all', 'added']))
- Сценарий: Employee role with view_invoices = all (or added) and add_invoices = none (read-only accountant).
- Ефект: The user clicks "Add credit note" on a paid or partial invoice and gets a 403 page.
- Поправка: Add in_array($this->addInvoicesPermission, ['all', 'added']) to these DataTable conditions (load the permission in the RecurringInvoicesDataTable constructor), and in_array(user()->permission('add_invoices'), ['all','added']) in invoices/ajax/show.blade.php.
- Проверка от втори агент: InvoicesDataTable.php:211-218 'Add credit note' has no add_invoices check, and no enclosing permission block wraps it. invoices/ajax/show.blade.php:785-796 and RecurringInvoicesDataTable.php:169-174 behave the same. CreditNoteController.php:63 aborts unless add_invoices is all/added.
- Намерено от: invoices-sales

### F150 · средна · мъртва връзка (403/404)

**The recurring invoice's "Invoices" tab shows "Add payment" without an add_payments check**

- Елемент: `app/DataTables/RecurringInvoicesDataTable.php:103` – показва се при: `if ($row->status != 'paid' && $row->status != 'canceled' && in_array('payments', $this->user->modules) && !in_array('client', user_roles()) && $row->credit_note == 0 && $row->status != 'draft')`, with no add_payments check (InvoicesDataTable.php:149-152 has it)
- Води към: payments.create?invoice_id={id}
- Проверка/отказ: app/Http/Controllers/PaymentController.php:106-107 abort_403(!in_array(user()->permission('add_payments'), ['all', 'added']))
- Сценарий: Employee with view_invoices all/added and the payments module on, but add_payments = none, opens a recurring invoice -> Invoices tab.
- Ефект: "Add payment" opens a modal with a 403 error.
- Поправка: Add $addPaymentPermission == 'all' || ($addPaymentPermission == 'added' && $row->added_by == user()->id) to the condition, as InvoicesDataTable does.
- Проверка от втори агент: RecurringInvoicesDataTable.php:103 'Add payment' checks status, payments module and role, but not add_payments. PaymentController.php:105-106 aborts unless add_payments is all/added.
- Намерено от: invoices-sales

### F151 · средна · мъртва връзка (403/404)

**"Reminder settings" link in the invoice create/edit form leads to Finance settings, which need manage_finance_setting = all**

- Елемент: `resources/views/invoices/ajax/create.blade.php:875` – показва се при: Unconditional `<a href="{{ route('invoice-settings.index') }}?tab=reminder" target="_blank">`; same at resources/views/invoices/ajax/edit.blade.php:580
- Води към: invoice-settings.index?tab=reminder
- Проверка/отказ: app/Http/Controllers/InvoiceSettingController.php:29 abort_403(!(user()->permission('manage_finance_setting') == 'all' && ...))
- Сценарий: Employee role with add_invoices or edit_invoices but without manage_finance_setting.
- Ефект: Clicking the link opens a new tab with a 403 page.
- Поправка: Wrap the link in @if (user()->permission('manage_finance_setting') == 'all').
- Проверка от втори агент: invoices/ajax/create.blade.php:875 and edit.blade.php:580 always render the 'Reminder settings' link, and no outer condition wraps the block (:867). InvoiceSettingController.php:29 aborts unless manage_finance_setting == 'all'.
- Намерено от: invoices-sales

### F152 · средна · мъртва връзка (403/404)

**"Create Invoice" and "Add recurring invoice" stay visible when the clients module is off for the role, but the form is replaced by an "Enable Client Module" alert**

- Елемент: `resources/views/invoices/index.blade.php:129` – показва се при: @if ($addInvoicesPermission == 'all'); recurring: resources/views/recurring-invoices/index.blade.php:114 `@if ($addInvoicesPermission == 'all' || $addInvoicesPermission == 'added')`
- Води към: invoices.create / recurring-invoices.create (also invoices.edit and recurring-invoices.edit)
- Проверка/отказ: resources/views/invoices/ajax/create.blade.php:12 `@if ((!in_array('client', user_roles()) && !in_array('clients', user_modules())) ...)` renders only messages.enableClientModule; same guard at recurring-invoices/ajax/create.blade.php:20, invoices/ajax/edit.blade.php:12, recurring-invoices/edit.blade.php:39
- Сценарий: Company admin switches Clients off for the employee role; the employee still has invoices with add_invoices = all.
- Ефект: The button opens a page that only says "Enable Client Module to access this section", which the employee cannot do. Dead end.
- Поправка: Add (in_array('client', user_roles()) || in_array('clients', user_modules())) to the create, recurring, edit and duplicate entry points.
- Проверка от втори агент: invoices/index.blade.php:129 and recurring-invoices/index.blade.php:114 check only add_invoices. invoices/ajax/create.blade.php:12 and recurring-invoices/ajax/create.blade.php:20 render only the enableClientModule alert when a non-client lacks the clients module.
- Намерено от: invoices-sales

### F166 · средна · модул извън пакета се вижда

**Product form, list and detail show the orders-only fields 'Client can purchase' and 'Downloadable' when the orders module is not in the package (and orders is config-hidden)**

- Елемент: `resources/views/products/ajax/create.blade.php:132` – показва се при: unconditional: x-forms.checkbox 'purchase_allow' (:132) and 'downloadable' (:137) in create; products/ajax/edit.blade.php:134; ProductsDataTable.php:247 column 'purchaseAllow' visible => !in_array('client', user_roles()); products/index.blade.php:128 quick action 'change-purchase'; products/ajax/show.blade.php:322 allowed/not-allowed badge
- Води към: Orders feature: client cart via ProductController@addCartItem/cart, orders.*
- Проверка/отказ: Client purchasing only works with the orders module: ProductController.php:38 abort_403(!(in_array('orders', $this->user->modules) && add_order == 'all')) for clients; config/databackd.php:25 'orders' => true hides orders from the menu for everyone
- Сценарий: Package 'Default' (has products, lacks orders): the admin opens Products and sees the 'Client can purchase' column, the 'Client can purchase' bulk action, and the 'Client can purchase' and 'Downloadable' (file on purchase) checkboxes in the add and edit form. On other packages orders is hidden by config, so these are also hidden_leaks.
- Ефект: Controls for a module that the package does not include, or that is hidden on purpose
- Поправка: Wrap the two checkboxes, the badge and the quick-action option in @if (in_array('orders', user_modules()) && !databackd_hidden('menu', 'orders')), and use the same condition for 'visible' on the allow_purchase column.
- Проверка от втори агент: products/ajax/create.blade.php:132 (purchase_allow) and :137 (downloadable) are unconditional. ProductsDataTable.php:247 column is visible to non-clients, and products/index.blade.php:128 has a change-purchase quick action. Client purchasing needs orders (ProductController.php:38), and orders is config-hidden. Medium: these are product fields of a missing or hidden module, with no dead end.
- Намерено от: expenses-products-contracts

### F167 · средна · мъртва връзка (403/404)

**Contracts list: the Project column links to projects.show and the Client column links to clients.show with no module or permission check**

- Елемент: `app/DataTables/ContractsDataTable.php:118` – показва се при: if ($row->project_id != null) return '<a href="' . route('projects.show', $row->project_id) . '">'; the client column :166/:169 links route('clients.show') when 'visible' => !in_array('client', user_roles()) (:327)
- Води към: projects.show / clients.show
- Проверка/отказ: app/Http/Controllers/ProjectController.php:78 abort_403(!in_array('projects', $this->user->modules)); app/Http/Controllers/ClientController.php:67 abort_403(!in_array('clients', ...)) and :614 abort_403 unless view_clients all/added/both
- Сценарий: The company admin switches projects off for a role (or for the client role, where contracts stays on). Users of that role open Contracts and click a project name, which gives 403. An employee with view_contract = all but view_clients = none (the default for the employee role), or with the clients module off, clicks a client, which gives 403. The project column is also a module leak when projects is off.
- Ефект: A 403 page from the contracts list
- Поправка: Render the project as a link only when in_array('projects', user_modules()) and hide the column otherwise. For the client column, link only when in_array('clients', user_modules()) and view_clients is in all/added/both.
- Проверка от втори агент: ContractsDataTable.php:116-118 links projects.show with no module check. :164-169 links clients.show when the column is visible to non-clients (:327). ProjectController.php:78 checks the module. ClientController.php:67 checks the module, and :614-616 requires view_clients all/added/both (employee default is none).
- Намерено от: expenses-products-contracts

### F168 · средна · модул извън пакета се вижда

**Expense, recurring-expense and contract forms and filters always show a Project select (and the project of the record)**

- Елемент: `resources/views/expenses/ajax/create.blade.php:78` – показва се при: unconditional x-forms.select project_id fed by Project::all() (ExpenseController.php:119-124). Same in expenses/ajax/edit.blade.php:54, recurring-expenses/ajax/create.blade.php:76, recurring-expenses/ajax/edit.blade.php:77, contracts/ajax/create.blade.php:47, contracts/ajax/edit.blade.php:38; the 'Project' filter in expenses/index.blade.php:78 and recurring-expenses/index.blade.php:68; the 'Project' row in expenses/ajax/show.blade.php:41 and recurring-expenses/ajax/show.blade.php:51
- Води към: projects module data (Project::all / expenses.get_employee_projects / contracts.project_detail)
- Проверка/отказ: No in_array('projects', user_modules()) check anywhere in these views or in ExpenseController::create/index (:45, :119), RecurringExpenseController::create/index (:39, :58) or ContractController::create/edit (:121, :177)
- Сценарий: The company admin switches the projects module off for a role (or for admin) while expenses and contracts stay on. The expense and contract forms still list every project and let users assign them; the expense lists still filter by project.
- Ефект: Project selects, filters and values for a module that is switched off
- Поправка: Wrap each project field, filter and detail row in @if (in_array('projects', user_modules())), and load projects only in that case.
- Проверка от втори агент: There is no in_array('projects', ...) anywhere in expenses/ajax/create, edit, show, index, recurring-expenses/ajax/create/edit/index or contracts/ajax/create/edit (grep count 0). expenses/ajax/create.blade.php:78 renders the Project select.
- Намерено от: expenses-products-contracts

### F169 · средна · мъртва връзка (403/404)

**Expense and recurring-expense forms call payments.account_list on every currency or project change; it returns 403 when the payments module is off**

- Елемент: `resources/views/expenses/ajax/create.blade.php:287` – показва се при: $('body').on('change', '#currency, #project_id', ...) always fires $.easyAjax(route('payments.account_list')), with no permission or module condition. Same at expenses/ajax/edit.blade.php:364, recurring-expenses/ajax/create.blade.php:402, recurring-expenses/ajax/edit.blade.php:475
- Води към: payments.account_list (PaymentController@accountList)
- Проверка/отказ: app/Http/Controllers/PaymentController.php:34 abort_403(!in_array('payments', $this->user->modules)) in constructor middleware
- Сценарий: The company admin switches payments off for the employee role (a common setup: employees file expense claims but do not record payments). An employee adds an expense in a foreign currency or picks a project, gets a 'permission denied' error, and the exchange rate (and the bank account list) is not updated, so the expense can be saved with the company-currency rate.
- Ефект: A 403 error toast in the form and a stale exchange rate
- Поправка: Move accountList to a route not gated by payments (shared with F142), or fire the call only when in_array('payments', user_modules()).
- Проверка от втори агент: expenses/ajax/create.blade.php:287, edit.blade.php:364, recurring-expenses/ajax/create.blade.php:402 and edit.blade.php:475 call payments.account_list. PaymentController.php:34 aborts without payments. Same root cause as F142.
- Намерено от: expenses-products-contracts

### F170 · средна · модул извън пакета се вижда

**Bank account select in the expense and recurring-expense forms and the bank account row in expense details ignore the bankaccount module**

- Елемент: `resources/views/expenses/ajax/create.blade.php:119` – показва се при: @if($linkExpensePermission == 'all') shows the bank_account_id select (permission only). Same at expenses/ajax/edit.blade.php:149, recurring-expenses/ajax/create.blade.php:88, recurring-expenses/ajax/edit.blade.php:103; the bank row at expenses/ajax/show.blade.php:45-47 and recurring-expenses/ajax/show.blade.php:69-70 is unconditional
- Води към: bankaccount module (BankAccount records, bank transactions created on save)
- Проверка/отказ: BankAccountController.php:26 abort_403(!in_array('bankaccount', $this->user->modules)); the forms check only link_expense_bank_account (ExpenseController.php:104, RecurringExpenseController.php:77)
- Сценарий: The company admin switches bankaccount off for a role (or for admin) while expenses stays on. The form still lists the bank accounts, and saving still books a bank transaction.
- Ефект: Bank-account fields for a module that is switched off
- Поправка: Use @if ($linkExpensePermission == 'all' && in_array('bankaccount', user_modules())) and the same module check for the show rows.
- Проверка от втори агент: expenses/ajax/create.blade.php:119, edit.blade.php:149, recurring-expenses/ajax/create.blade.php:88 and edit.blade.php:103 check only link_expense_bank_account. expenses/ajax/show.blade.php:44-47 bank row is unconditional. BankAccountController.php:26 gates the module.
- Намерено от: expenses-products-contracts

### F171 · средна · мъртва връзка (403/404)

**Bank transaction detail 'View related payment / invoice / expense' links have no module or permission check**

- Елемент: `resources/views/bank-account/ajax/view-transaction.blade.php:43` – показва се при: @if(!is_null($bankTransaction->payment_id)) link route('payments.show'); :48 @if (!is_null($bankTransaction->invoice_id)) link route('invoices.show'); :52 @if (!is_null($bankTransaction->expense_id)) link route('expenses.show')
- Води към: payments.show / invoices.show / expenses.show
- Проверка/отказ: PaymentController.php:34 abort_403(!in_array('payments', ...)); InvoiceController.php:61 abort_403(!in_array('invoices', ...)); ExpenseController.php:33 abort_403(!in_array('expenses', ...)), plus their view_* permission checks in show()
- Сценарий: Bankaccount is on but payments, invoices or expenses are switched off for the role, or the user has view_bankaccount but not view_payments/view_invoices/view_expenses. The user opens a bank account, then a transaction, and clicks 'View related ...', which gives 403.
- Ефект: A 403 page or a 403 in the right modal
- Поправка: Render each link only when the module is in user_modules() and its view_* permission is not 'none'; otherwise show the plain label.
- Проверка от втори агент: bank-account/ajax/view-transaction.blade.php:41-53 links payments.show, invoices.show and expenses.show based only on the ids being present. These controllers gate their modules (PaymentController.php:34 etc.) and view permissions.
- Намерено от: expenses-products-contracts

### F172 · средна · мъртва връзка (403/404)

**Expenses bulk actions (Delete / Change status) check employee permissions instead of expense permissions**

- Елемент: `resources/views/expenses/index.blade.php:139` – показва се при: x-datatable.actions with 'change-status' and 'delete' is rendered for every user who can open the list, and the check column is always present (ExpensesDataTable.php:44). JS posts to expenses.apply_quick_action (:369); the project expenses tab does the same (projects/ajax/expenses.blade.php:257)
- Води към: expenses.apply_quick_action (ExpenseController@applyQuickAction)
- Проверка/отказ: app/Http/Controllers/ExpenseController.php:344 abort_403(user()->permission('delete_employees') != 'all'); :354 abort_403(user()->permission('edit_employees') != 'all')
- Сценарий: A role with delete_expenses/approve_expenses = all but no employee-management rights (for example an accountant role) selects expenses and applies Delete or Change status, which gives 403. The reverse also happens: a role with delete_employees = all can bulk-delete expenses it may not delete.
- Ефект: A 403 error after the visible bulk action
- Поправка: Use abort_403(user()->permission('delete_expenses') != 'all') and abort_403(user()->permission('approve_expenses') != 'all'), and show the options only for those permissions.
- Проверка от втори агент: ExpenseController.php:344 deleteRecords checks delete_employees, and :354 changeBulkStatus checks edit_employees. The quick actions in expenses/index.blade.php:139-145 are always rendered.
- Намерено от: expenses-products-contracts

### F173 · средна · мъртва връзка (403/404)

**The inline expense status dropdown is shown for approve_expenses added/owned/both, but changeStatus accepts only 'all'**

- Елемент: `app/DataTables/ExpensesDataTable.php:99` – показва се при: $this->approveExpensePermission != 'none' && (== 'all' || ('added' && added_by == me) || ('owned' && user_id == me) || ('both' && ...)) renders select.change-expense-status; the JS at expenses/index.blade.php:293 posts to expenses.change_status
- Води към: expenses.change_status (ExpenseController@changeStatus)
- Проверка/отказ: app/Http/Controllers/ExpenseController.php:55 abort_403(user()->permission('approve_expenses') != 'all')
- Сценарий: The admin sets approve_expenses = added, owned or both for a role (allowed values ALL_4_ADDED_1_OWNED_2_BOTH_3_NONE_5, Module.php:1383). Users of that role see a status dropdown on their rows, and changing it gives 403.
- Ефект: A 403 error toast from an editable control
- Поправка: Apply the same ownership rule in ExpenseController::changeStatus, or render the dropdown only when approve_expenses == 'all'.
- Проверка от втори агент: ExpensesDataTable.php:97-104 renders the status select for approve_expenses all/added/owned/both. ExpenseController.php:55 changeStatus aborts unless 'all'. The permission allows added/owned/both (Module.php:1383-1385).
- Намерено от: expenses-products-contracts

### F038 · ниска · модул извън пакета се вижда

**Finance settings show 'Show project on invoice' without the projects module and the Proposal prefix (leads module) without leads**

- Елемент: `resources/views/invoice-settings/ajax/general.blade.php:117` – показва се при: The show_project checkbox at general.blade.php:117 is unconditional; the proposal prefix block at resources/views/invoice-settings/ajax/prefix.blade.php:162-190 is unconditional, while the invoice/estimate/order blocks are wrapped in user_modules() checks (lines 3, 66, 130)
- Води към: invoice-settings.update / invoice_settings.update_prefix
- Проверка/отказ: app/Http/Controllers/InvoiceSettingController.php:29 page gate (invoices||estimates||orders||leads||payments). Proposals are gated by leads in ProposalController.php:34. UpdatePrefixSetting.php:48 always requires proposal_prefix.
- Сценарий: The admin switches projects or leads off for the admin role in Module settings (leads is also config-hidden for everyone), then opens Finance settings -> Invoice settings / Prefix settings.
- Ефект: Fields of modules the user does not have (projects, proposals) are visible, and proposal_prefix is even required on save.
- Поправка: Wrap them in @if (in_array('projects', user_modules())) and @if (in_array('leads', user_modules())), and make proposal_prefix required only when leads is on.
- Проверка от втори агент: invoice-settings/ajax/general.blade.php:116-119 show_project checkbox has no condition. prefix.blade.php:162-190 proposal block is outside the module @if blocks (:3-65 invoices, :66-128 estimates, :130-160 orders).
- Намерено от: settings

### F042 · ниска · мъртва връзка (403/404)

**Finance settings 'Add unit' and 'Add / delete payment details' are gated by manage_finance_setting but saving checks manage_project_category (a projects permission), so the save 403s; their other actions have no check at all**

- Елемент: `resources/views/invoice-settings/index.blade.php:72` – показва се при: Finance settings page shown when manage_finance_setting == 'all' && (invoices||estimates||orders||leads||payments) (components/setting-sidebar.blade.php:60). The buttons #addUnitType (line 72) and #add-payment-details (line 85) are unconditional; they save via invoice-settings/ajax/unit-type.blade.php:24 and payment-create.blade.php:49, and delete via payment.blade.php:81.
- Води към: unit-type.store, invoices-payment-details.store, invoices-payment-details.destroy
- Проверка/отказ: app/Http/Controllers/UnitTypeController.php:43-44 and app/Http/Controllers/InvoicePaymentDetailController.php:33-34 and :87-88 abort_403(!in_array(user()->permission('manage_project_category'), ['all','added'])). The rest (create/edit/update/setDefaultUnit) have no module or finance check.
- Сценарий: A custom role created with 'import from admin role' keeps manage_finance_setting=all, but its Projects 'More' permissions are set so that manage_project_category is none (or projects is otherwise not granted).
- Ефект: The user sees the Finance settings Units / Payment details tabs; Add unit and Add/Delete payment details return 403.
- Поправка: Use the InvoiceSettingController.php:28-29 middleware (manage_finance_setting == 'all' plus a finance module) in both controllers instead of manage_project_category.
- Проверка от втори агент: UnitTypeController.php:43-44 store and InvoicePaymentDetailController.php:34-35 (store) and :87-88 (destroy) check manage_project_category. Neither constructor (UnitTypeController.php:24-29, InvoicePaymentDetailController.php:14-19) has middleware, and the other actions are unchecked. The Finance settings page needs manage_finance_setting (InvoiceSettingController.php:28-29).
- Намерено от: settings

### F043 · ниска · модул извън пакета се вижда

**Payment gateway credentials settings stay visible when invoices, payments and orders are all off**

- Елемент: `resources/views/components/setting-sidebar.blade.php:54` – показва се при: @if (user()->permission('manage_payment_setting') == 'all')
- Води към: payment-gateway-settings.index
- Проверка/отказ: app/Http/Controllers/PaymentGatewayCredentialController.php:21 checks only manage_payment_setting; no module check
- Сценарий: The admin switches invoices and payments off for the admin role in Module settings (orders is absent on Default anyway).
- Ефект: Online payment gateway configuration for modules the role does not have is still in the settings sidebar.
- Поправка: Add && (in_array('invoices', user_modules()) || in_array('payments', user_modules()) || in_array('orders', user_modules())) to the sidebar and the same abort_403 in the controller middleware.
- Проверка от втори агент: components/setting-sidebar.blade.php:54-58 checks only manage_payment_setting. PaymentGatewayCredentialController has no module check.
- Намерено от: settings

### F146 · ниска · модул извън пакета се вижда

**Project filter shown on the Invoices, Recurring invoices, Payments and Credit notes lists regardless of the projects module**

- Елемент: `resources/views/invoices/index.blade.php:72` – показва се при: Project <select> inside x-filters.more-filter-box with no module check. Same at resources/views/recurring-invoices/index.blade.php:66, resources/views/payments/index.blade.php:63, resources/views/credit-notes/index.blade.php:63. Controllers always load projects (InvoiceController.php:72, PaymentController.php:47, RecurringInvoiceController.php:68).
- Води към: n/a (filter listing all project names)
- Проверка/отказ: missing; the Project column in InvoicesDataTable is already hidden by `in_array('projects', user_modules())` (app/DataTables/InvoicesDataTable.php:592)
- Сценарий: Projects switched off for a role (admin or employee) that has invoices/payments.
- Ефект: Every project name of the company is listed in a filter for a module the user does not have.
- Поправка: Wrap each project filter block in @if (in_array('projects', user_modules())).
- Проверка от втори агент: Project filters at invoices/index.blade.php:72, recurring-invoices/index.blade.php:66, payments/index.blade.php:63 and credit-notes/index.blade.php:63 have no projects check. These are filter lists only, so low.
- Намерено от: invoices-sales

### F155 · ниска · мъртва връзка (403/404)

**"Recurring Invoice" button on Invoices needs only add_invoices/manage_recurring_invoice = all, but the recurring index needs view_invoices all/added**

- Елемент: `resources/views/invoices/index.blade.php:135` – показва се при: @if ($addInvoicesPermission == 'all' || $manageRecurringInvoicesPermission == 'all')
- Води към: recurring-invoices.index
- Проверка/отказ: app/Http/Controllers/RecurringInvoiceController.php:55-56 abort_403(!in_array($viewPermission, ['all', 'added']))
- Сценарий: Custom role with view_invoices = owned/both (enough for the Invoices sidebar and list) and add_invoices = all or manage_recurring_invoice = all.
- Ефект: The button leads to a 403 page.
- Поправка: Add && in_array(user()->permission('view_invoices'), ['all', 'added']) to the button condition.
- Проверка от втори агент: invoices/index.blade.php:135 shows the Recurring button for add_invoices or manage_recurring_invoice == 'all'. RecurringInvoiceController.php:55-56 requires view_invoices all/added, while the Invoices menu allows any view_invoices != none (menu.blade.php:389).
- Намерено от: invoices-sales

### F158 · ниска · мъртва връзка (403/404)

**Invoice payment details (Finance settings -> Payment details) store/destroy check the projects permission manage_project_category**

- Елемент: `resources/views/invoice-settings/index.blade.php:85` – показва се при: Payment tab shown when in_array('invoices', user_modules()) (index.blade.php:47) for users who passed manage_finance_setting == 'all'; the add button calls invoices-payment-details.create (:147)
- Води към: invoices-payment-details.store / destroy
- Проверка/отказ: app/Http/Controllers/InvoicePaymentDetailController.php:34 and :87 abort_403(!in_array(user()->permission('manage_project_category'), ['all','added']))
- Сценарий: Custom role with manage_finance_setting = all but manage_project_category = none.
- Ефект: Saving or deleting a payment detail returns 403.
- Поправка: Check user()->permission('manage_finance_setting') == 'all' instead of manage_project_category.
- Проверка от втори агент: InvoicePaymentDetailController.php:34 (store) and :87 (destroy) check manage_project_category. The settings tab is shown to manage_finance_setting == 'all' users (InvoiceSettingController.php:29, invoice-settings/index.blade.php:47).
- Намерено от: invoices-sales

### F177 · ниска · мъртва връзка (403/404)

**Contract detail 'Copy contract' and 'Edit' ignore the clients-module condition used by the list, and end at an 'enable client module' alert**

- Елемент: `resources/views/contracts/ajax/summary.blade.php:169` – показва се при: @if ($addContractPermission == 'all' || == 'added') shows Copy (route contracts.create?id=); :187-199 shows Edit by edit_contract only. ContractTemplatesDataTable.php:50 'Create contract' is shown by add_contract only. ContractsDataTable.php:72 and :80 add in_array('clients', user_modules())
- Води към: contracts.create / contracts.edit
- Проверка/отказ: resources/views/contracts/ajax/create.blade.php:5 and contracts/ajax/edit.blade.php:6 render only an x-alert 'enableClientModule' with a Back button when 'clients' is not in user_modules()
- Сценарий: The clients module is switched off for the admin or employee role while contracts stays on. The user opens a contract and clicks Copy or Edit and gets only an error alert (the same actions are correctly hidden in the list).
- Ефект: A dead-end modal with an error message
- Поправка: Add in_array('clients', user_modules()) (non-client users) to these conditions.
- Проверка от втори агент: contracts/ajax/summary.blade.php:169 (Copy) and :187-199 (Edit) check only add_contract/edit_contract. ContractTemplatesDataTable.php:50 does the same. ContractsDataTable.php:72 adds in_array('clients', user_modules()). contracts/ajax/create.blade.php:5 and edit.blade.php:7 render only the enableClientModule alert.
- Намерено от: expenses-products-contracts

### F178 · ниска · мъртва връзка (403/404)

**Recurring expenses list shows Edit based on edit_expenses, but edit requires manage_recurring_expense = all**

- Елемент: `app/DataTables/RecurringExpensesDataTable.php:48` – показва се при: if ($this->editExpensePermission == 'all' || ($this->editExpensePermission == 'added' && $row->added_by == user()->id)) shows route('recurring-expenses.edit')
- Води към: recurring-expenses.edit (RecurringExpenseController@edit)
- Проверка/отказ: app/Http/Controllers/RecurringExpenseController.php:238-239 abort_403(!in_array(user()->permission('manage_recurring_expense'), ['all']))
- Сценарий: A role with edit_expenses = all or added and manage_recurring_expense = none opens /account/expenses/recurring-expenses (allowed by view_expenses, RecurringExpenseController.php:34-35; reachable by URL or by the bookmark of a recurring notification) and clicks Edit, which gives 403.
- Ефект: A 403 in the right modal
- Поправка: Show the Edit action only when user()->permission('manage_recurring_expense') == 'all'.
- Проверка от втори агент: RecurringExpensesDataTable.php:48 shows Edit by edit_expenses all/added. RecurringExpenseController.php:238-239 requires manage_recurring_expense 'all'. The index (:34-35) allows view_expenses.
- Намерено от: expenses-products-contracts

## G07. Меню, табла и глобално търсене (27)

### F002 · висока · мъртва връзка (403/404)

**"HR" accordion is shown empty for every default employee on Trial/Starter/Демо**

- Елемент: `resources/views/sections/menu.blade.php:287` – показва се при: !client && (in_array('employees', user_modules()) || ...) && ($sidebarUserPermissions['view_employees'] != 5 || $sidebarUserPermissions['view_leave'] != 5 || $sidebarUserPermissions['view_attendance'] != 5 || $sidebarUserPermissions['view_holiday'] != 5) && (...)
- Води към: HR group sub-items: employees.index, designations.index, departments.index (leaves/shifts/attendance/holidays/appreciations are config-hidden)
- Проверка/отказ: The sub-items need menu.blade.php:294 view_employees != 5 && view_employee_menu == 4, :318 view_designation == 4, :321 view_department == 4. Lines :297-331 hide leaves/shifts/attendance/holidays/appreciation via databackd_hidden (config/databackd.php:17-21). The default employee role (app/Models/PermissionRole.php:53-131) has view_leave=BOTH (:105), view_attendance=OWNED (:66) and view_holiday=OWNED (:118). It has no view_employees/view_designation/view_department, so sidebar_user_perms() maps them to 5. The group opens through view_leave while every item stays hidden. The x-menu-item header renders even with an empty slot (components/menu-item.blade.php:37-58)
- Сценарий: Plain employee with the default 'employee' role in a company on Trial/Starter/Демо (employees module on). Not on Default, where the module clause is false
- Ефект: The "Operations" section shows an "HR" group header that expands to nothing
- Поправка: Wrap the HR sub-items in ob_start()/ob_get_clean() as the Operations section does, and print <x-menu-item> only when the buffer contains a sub-menu link. Or rewrite :287 as the OR of the exact child conditions.
- Проверка от втори агент: menu.blade.php:287: the group opens when the employees module is on and any of view_employees/view_leave/view_attendance/view_holiday != 5. The default employee role (PermissionRole.php:66,105,118) has view_leave=BOTH, view_attendance=OWNED and view_holiday=OWNED. It has no view_employees, view_designation or view_department, so sidebar_user_perms() (start.php:885-887) sets them to 5. Sub-items :294 (needs view_employees!=5), :318/:321 (need ==4) are false. Leaves, shifts, attendance, holidays and appreciation are databackd_hidden (config/databackd.php:17-21). x-menu-item still prints the heading for an empty slot (components/menu-item.blade.php:38-56). No JS removes empty groups.
- Намерено от: sidebar

### F014 · висока · мъртва връзка (403/404)

**Global search: options are filtered by module only, so a default employee is offered Invoice/Estimate/Employee/Client/Lead and gets a 403**

- Елемент: `resources/views/search/index.blade.php:17` – показва се при: Lines 17-19 @if (in_array('invoices', user_modules())), 29-31 estimates, 36-38 employees, 39-41 clients, 42-44 leads; only !in_array('client', user_roles()) is checked, never the user's view_* permission
- Води към: search.store -> Reply::redirect to invoices.index / estimates.index / employees.index / clients.index / deals.index
- Проверка/отказ: InvoiceController.php:70 view_invoices in all/added/owned/both; EstimateController.php:52 view_estimates; EmployeeController.php:92-93 view_employees + view_employee_menu=='all'; ClientController.php:83 view_clients in all/added/both; DealController.php:60,73 view_deals. The default employee role (app/Models/PermissionRole.php:50-133) has none of these permissions, so they resolve to 'none'.
- Сценарий: Employee role with default permissions in a Trial/Starter/Демо company (module_settings for type 'employee' has invoices, estimates, employees, clients and leads active; CompanyObserver.php:1011-1015). The search icon sits in the topbar for every non-client user.
- Ефект: Employee opens Search, picks Invoice / Client / Employee / Estimate / Lead and gets a full-page 403.
- Поправка: Add the permission to each option, e.g. && in_array(user()->permission('view_invoices'), ['all','added','owned','both']); employee: view_employees != 'none' && view_employee_menu == 'all'; client: in ['all','added','both']; lead: view_deals != 'none'.
- Проверка от втори агент: search/index.blade.php:17-19, 29-31, 36-44 check only modules. SearchController.php:22-49 redirects to the index pages. InvoiceController.php:69-70, EstimateController.php:52, EmployeeController.php:92-93, ClientController.php:83 and DealController.php:73 abort_403 without view permission. The default employee role (PermissionRole.php:50-131) has no view_invoices, view_estimates, view_employees, view_clients or view_deals, so they resolve to none. The search icon is in the topbar for everyone (topbar.blade.php:83-89).
- Намерено от: topbar-global

### F047 · висока · модул извън пакета се вижда

**'Contract date' widget shows employees' HR contract end dates, gated by the client 'contracts' module instead of 'employees'**

- Елемент: `resources/views/dashboard/employee/widgets/contract.blade.php:1` – показва се при: @if (in_array('contract_date', $activeWidgets) && in_array('contracts', user_modules())); for admins it renders a card listing employee_details.contract_end_date with <x-employee> links (line 18). DashboardWidget::MODULE (app/Models/DashboardWidget.php:141) also maps 'contracts' => 'contract_date', so the package sync keeps it active.
- Води към: HR data (EmployeeDashboard.php:571-610) and employees.show via x-employee
- Проверка/отказ: app/Http/Controllers/EmployeeController.php:76-77 (employees module) for the links; the widget itself has no employees check
- Сценарий: Package 'Default' (has contracts, lacks employees): every company admin sees the card on the private dashboard. The left column renders because 'profile' is active.
- Ефект: An HR widget ('Дата на договора') is visible although employees is not in the package. If any employee has a contract_end_date, the employee name/avatar links to a 403 page; otherwise an empty 'no record' card is shown.
- Поправка: contract.blade.php:1: in_array('employees', user_modules()). DashboardWidget::MODULE: map the HR widgets (contract_date, probation_date, internship_date, notice_period_duration, work_anniversary, birthday) to 'employees'.
- Проверка от втори агент: dashboard/employee/widgets/contract.blade.php:1 gates on the contracts module. The admin branch :8-49 always renders the card, with <x-employee> links at :18, while the sibling probation widget gates on employees. DashboardWidget.php:141 maps contracts => contract_date, so the Default package (contracts on, employees off) keeps it active. Included unconditionally at employee/index.blade.php:406. Duplicate of F066.
- Намерено от: dashboards

### F065 · висока · мъртва връзка (403/404)

**HR sidebar section is shown (empty) to the default employee role: its condition ignores the per-item permissions**

- Елемент: `resources/views/sections/menu.blade.php:287` – показва се при: in_array('employees', user_modules()) || ... ) && ($sidebarUserPermissions['view_employees'] != 5 || $sidebarUserPermissions['view_leave'] != 5 || $sidebarUserPermissions['view_attendance'] != 5 || $sidebarUserPermissions['view_holiday'] != 5)
- Води към: HR accordion (menu.blade.php:294-330)
- Проверка/отказ: Child items need view_employees!=5 && view_employee_menu==4 (:294), view_designation==4 (:318), view_department==4 (:321). Leaves/shifts/attendance/holidays/appreciation are config-hidden (:297-330)
- Сценарий: Package Trial/Starter/Демо/Стартов, employee role with default permissions (view_employees=5, view_leave=3, view_attendance=2, view_holiday=2, view_designation/department=5): the 'HR' entry appears and opens to nothing
- Ефект: Empty menu group (dead end) in the main sidebar for every ordinary employee
- Поправка: Buffer the HR sub-items and print the group only when non-empty.
- Проверка от втори агент: Duplicate of F002: menu.blade.php:287 header condition versus the sub-item conditions at :294-330, with the default employee role permissions in PermissionRole.php:53-131.
- Намерено от: hr

### F066 · висока · модул извън пакета се вижда

**Private dashboard 'Contract date' widget (employee contract end dates, HR data) is gated by the contracts module instead of employees**

- Елемент: `resources/views/dashboard/employee/widgets/contract.blade.php:1` – показва се при: @if (in_array('contract_date', $activeWidgets) && in_array('contracts', user_modules())). The admin branch (:8-49) always renders the card, even when empty. The widget map in app/Models/DashboardWidget.php:141 is ['contracts' => 'contract_date'] and the widget is active by default (:126)
- Води към: EmployeeDetails.contract_end_date list with x-employee links (employees.show)
- Проверка/отказ: Data belongs to the employees module (EmployeeDashboard.php:608-610 filters EmployeeDetails). The links go to EmployeeController.php:77
- Сценарий: Package Default (contracts yes, employees no): every admin's private dashboard shows a 'Contract date' card (usually 'No record found'). Rows carried over from an earlier package link to a 403 profile
- Ефект: HR widget of a module outside the package visible on the main dashboard
- Поправка: Gate on employees and remap DashboardWidget::MODULE to 'employees' => 'contract_date'.
- Проверка от втори агент: Duplicate of F047: contract.blade.php:1 gates on contracts. Data comes from EmployeeDetails (EmployeeDashboard.php:586-610), and DashboardWidget.php:141 maps it to contracts.
- Намерено от: hr

### F003 · средна · мъртва връзка (403/404)

**Finance report link shown for added/owned/both, but the controller requires 'all'**

- Елемент: `resources/views/sections/menu.blade.php:185` – показва се при: $sidebarUserPermissions['view_finance_report'] != 5 && $sidebarUserPermissions['view_finance_report'] != 'none' && in_array('payments', user_modules())  (the outer Reports condition at :159 also uses != 5)
- Води към: finance-report.index (FinanceReportController@index)
- Проверка/отказ: app/Http/Controllers/FinanceReportController.php:48 abort_403(user()->permission('view_finance_report') != 'all'). The permission offers ALL_4_ADDED_1_OWNED_2_BOTH_3_NONE_5 (app/Models/Module.php:1486)
- Сценарий: Employee role with "View finance report" set to Added, Owned or Both (reports and payments on, which is every package)
- Ефект: Reports > Finance report is visible and leads to 403
- Поправка: menu.blade.php:185 and the outer condition at :159: use $sidebarUserPermissions['view_finance_report'] == 4
- Проверка от втори агент: menu.blade.php:185 (and :159) show Finance report for view_finance_report != 5. FinanceReportController.php:48 abort_403(user()->permission('view_finance_report') != 'all'). Module.php:1485-1487 lets the role editor assign Added, Owned or Both to that permission.
- Намерено от: sidebar

### F004 · средна · мъртва връзка (403/404)

**Dashboard > Advanced dashboard returns 403 for finance-only or ticket-only users (swapped calls in the controller default branch)**

- Елемент: `resources/views/sections/menu.blade.php:22` – показва се при: menu.blade.php:4-11: in_array('admin', user_roles()) || ... || $sidebarUserPermissions['view_ticket_dashboard'] == 4 || $sidebarUserPermissions['view_finance_dashboard'] == 4
- Води към: dashboard.advanced (DashboardController@advancedDashboard, no ?tab)
- Проверка/отказ: app/Http/Controllers/DashboardController.php:234-242: elseif view_finance_dashboard == 4 calls $this->ticketDashboard(), and else-if view_ticket_dashboard == 4 calls $this->financeDashboard(). app/Traits/TicketDashboard.php:25 abort_403($this->viewTicketDashboard !== 'all'); app/Traits/FinanceDashboard.php:29 abort_403($this->viewFinanceDashboard !== 'all')
- Сценарий: Employee role (e.g. accountant) with View finance dashboard = All and overview/project/client/HR dashboards = None. Same for ticket dashboard only
- Ефект: The sidebar shows the Dashboard accordion with "Advanced dashboard"; clicking it gives 403. With both finance and ticket permissions it renders the config-hidden ticket dashboard under the Finance tab
- Поправка: DashboardController.php:234-242: the finance branch sets activeTab 'finance' and calls $this->financeDashboard(); the ticket branch sets activeTab 'ticket' and calls $this->ticketDashboard(), only when !databackd_hidden('dashboard_tabs','ticket') && in_array('tickets', user_modules()).
- Проверка от втори агент: DashboardController.php:234-237: the view_finance_dashboard == 4 branch calls $this->ticketDashboard(). The :239-241 view_ticket_dashboard branch calls financeDashboard() with activeTab 'finance'. TicketDashboard.php:25 and FinanceDashboard.php:29 each abort_403 unless their own permission is 'all'. The sidebar shows the Advanced dashboard link for either permission (menu.blade.php:9-10, :22).
- Намерено от: sidebar

### F015 · средна · мъртва връзка (403/404)

**Global search: the "Task" option is gated on the tickets module instead of tasks**

- Елемент: `resources/views/search/index.blade.php:23` – показва се при: @if (in_array('tickets', user_modules())) <option value="task">
- Води към: tasks.index?search_keyword=...
- Проверка/отказ: app/Http/Controllers/TaskController.php:60 abort_403(!in_array('tasks', $this->user->modules))
- Сценарий: The company admin switches Tasks off for the client or employee role (Settings -> Module settings) while Tickets stays active (it is in every package, only hidden from the menu).
- Ефект: The user sees "Task" in the search module select; searching opens a 403. The reverse also happens: with tasks on and tickets off, the Task option disappears.
- Поправка: search/index.blade.php:23: @if (in_array('tasks', user_modules()) && user()->permission('view_tasks') != 'none')
- Проверка от втори агент: search/index.blade.php:23 gates the 'task' option on in_array('tickets', user_modules()). SearchController.php:33-34 redirects to tasks.index, and TaskController.php:60 abort_403(!in_array('tasks', $this->user->modules)). Duplicate of F082.
- Намерено от: topbar-global

### F048 · средна · мъртва връзка (403/404)

**advancedDashboard default branch swaps the finance and ticket dashboards, so the sidebar 'Advanced dashboard' returns 403**

- Елемент: `resources/views/sections/menu.blade.php:22` – показва се при: Sidebar shows the Advanced dashboard link when in_array('admin', user_roles()) || ... || $sidebarUserPermissions['view_ticket_dashboard'] == 4 || $sidebarUserPermissions['view_finance_dashboard'] == 4 (menu.blade.php:4-10)
- Води към: dashboard.advanced -> DashboardController@advancedDashboard default branch (DashboardController.php:234-242): view_finance_dashboard == 4 calls $this->ticketDashboard(); view_ticket_dashboard == 4 calls $this->financeDashboard() (both with activeTab 'finance')
- Проверка/отказ: app/Traits/TicketDashboard.php:25 abort_403($this->viewTicketDashboard !== 'all'); app/Traits/FinanceDashboard.php:29 abort_403($this->viewFinanceDashboard !== 'all')
- Сценарий: Employee role (e.g. an accountant) given only 'View finance dashboard: All' and no overview/project/client/hr dashboard permission. Or a role with only the ticket dashboard permission.
- Ефект: The sidebar Dashboard -> Advanced dashboard link opens a 403 page. If the role has both the finance and ticket permissions, it shows the config-hidden ticket dashboard instead of finance.
- Поправка: Swap the calls in the default branch as in F004, and skip the ticket branch when it is databackd_hidden or tickets is off.
- Проверка от втори агент: Duplicate of F004: DashboardController.php:234-242 swaps the calls, and TicketDashboard.php:25 / FinanceDashboard.php:29 abort_403.
- Намерено от: dashboards

### F049 · средна · скрита функция прозира

**Config-hidden project/client/hr/ticket dashboard tabs are still rendered via the default branch and ?tab=, with no hidden or module check**

- Елемент: `app/Http/Controllers/DashboardController.php:197-231` – показва се при: switch ($tab) { case 'project'/'client'/'hr'/'ticket' ... } with no databackd_hidden() or user_modules() check; the default branch (lines 219-231) renders projectDashboard()/clientDashboard()/hrDashboard() for a user whose first dashboard permission is that tab. The sidebar link (menu.blade.php:22) leads there.
- Води към: dashboard.ajax.project / client / hr / ticket
- Проверка/отказ: Only the view_*_dashboard permission in the traits (ProjectDashboard.php:28, ClientDashboard.php:34, HRDashboard.php:31, TicketDashboard.php:25). The tab bar hides them (admin.blade.php:53-68) but the controller does not.
- Сценарий: Employee role with only 'View HR dashboard: All' on package 'Default' (attendance present, employees missing): the sidebar Advanced dashboard shows the hidden HR tab. Its Birthday widget (resources/views/dashboard/ajax/hr.blade.php:159) has no employees-module check and its <x-employee> links lead to 403. Any admin can also open /account/dashboard-advanced?tab=ticket or ?tab=client directly.
- Ефект: Dashboards the product owner hid are visible. On Default the HR tab shows employee birthdays from a module not in the package, and their links lead to 403.
- Поправка: In advancedDashboard abort_403 (or fall back to overview) when databackd_hidden('dashboard_tabs', $tab) or the tab's module is off, and apply the same rule in the default chain. Add in_array('employees', user_modules()) to hr.blade.php:159.
- Проверка от втори агент: DashboardController.php:197-231: the cases 'project'/'client'/'hr'/'ticket' and the default chain have no databackd_hidden or module check; the traits check only permissions (HRDashboard.php:31). An admin can open ?tab=hr, ?tab=client or ?tab=ticket. dashboard/ajax/hr.blade.php:159 shows the birthday widget with no employees check. Overlaps F005.
- Намерено от: dashboards

### F050 · средна · модул извън пакета се вижда

**Private-dashboard widget settings (cog) list toggles for widgets of modules not in the package, and for config-hidden widgets**

- Елемент: `resources/views/dashboard/employee/index.blade.php:219-231` – показва се при: @if (in_array('admin', user_roles())) ... @foreach ($widgets as $widget) checkbox, where $widgets = DashboardWidget::where('dashboard_type','private-dashboard')->where('active',1) (EmployeeDashboard.php:530). 'active' is only synced for the 8 modules in DashboardWidget::MODULE (DashboardWidget.php:133-142), and only on package update (CompanyObserver.php:1059,1104-1138).
- Води към: dashboard.widget/private-dashboard
- Проверка/отказ: No module filtering, unlike the advanced dashboard, which filters through $widgetToModuleMap (resources/views/dashboard/admin.blade.php:116)
- Сценарий: Package 'Default' (no employees, timelogs, leaves): the admin opens the cog on the private dashboard.
- Ефект: Shows checkboxes for Birthday, Work anniversary, Notice period, Probation, Internship, Documents, Appreciation (employees). On companies never re-synced it also shows Week timelog and Leave. Config-hidden widgets also appear (Shift schedule, Notices, My calendar, Lead, Follow ups, Ticket, Work from home, Leave, Appreciation). Toggling them has no visible effect.
- Поправка: Filter the loop with a widget => module map plus databackd_hidden('dashboard_widgets', ...), and extend DashboardWidget::MODULE with the employees widgets.
- Проверка от втори агент: dashboard/employee/index.blade.php:219-231 loops over all $widgets for admins without a module filter. $widgets = DashboardWidget::where('active',1) (EmployeeDashboard.php:530). 'active' is synced only for the 8 DashboardWidget::MODULE entries (DashboardWidget.php:133-142, CompanyObserver.php:1104-1138). The advanced dashboard does filter by $widgetToModuleMap (admin.blade.php:110-114).
- Намерено от: dashboards

### F051 · средна · мъртва връзка (403/404)

**Finance tab cards jump to the income-vs-expense report and invoices without the target's module/permission checks; 'Total expenses' gated by invoices**

- Елемент: `resources/views/dashboard/ajax/finance.blade.php:44-74` – показва се при: @if (in_array('invoices', user_modules()) && (in_array('total_expenses', $activeWidgets) || in_array('total_earnings', $activeWidgets))); the click handler #totalEarnings (lines 173-181) goes to route('income-expense-report.index'). #totalPaidInvoices/#totalPendingInvoices/#totalPendingAmount (lines 159-201) go to invoices.index.
- Води към: income-expense-report.index; invoices.index
- Проверка/отказ: app/Http/Controllers/IncomeVsExpenseReportController.php:24 abort_403(user()->permission('view_income_expense_report') != 'all') (no reports/expenses module check); app/Http/Controllers/InvoiceController.php:70 requires view_invoices in all/added/owned/both. The sidebar shows the report only with reports + expenses + view_income_expense_report == 4 (resources/views/sections/menu.blade.php:159,190).
- Сценарий: (a) Employee role with view_finance_dashboard=all but no income/expense report or invoice permission: clicking the cards gives 403. (b) Admin switches expenses or reports off for the admin role: the 'Total expenses' amount still shows, and the card opens the income-vs-expense report of a module that is off.
- Ефект: 403 pages from finance dashboard cards, or expense figures and report pages leaking when expenses/reports are off.
- Поправка: Gate total_expenses with in_array('expenses', user_modules()). Make #totalEarnings clickable only with reports + expenses modules and view_income_expense_report == 'all', and the invoice cards only when view_invoices != 'none'.
- Проверка от втори агент: finance.blade.php:44 shows total_expenses with the invoices check only. #totalEarnings (:173-181) goes to income-expense-report.index (IncomeVsExpenseReportController.php:24 needs view_income_expense_report == 'all', no module check). The invoice cards (:159-201) go to invoices.index (InvoiceController.php:69-70). A finance-dashboard-only user has neither permission.
- Намерено от: dashboards

### F052 · средна · мъртва връзка (403/404)

**Overview tab counters for non-admin users link to list pages without checking the user's view_* permission**

- Елемент: `resources/views/dashboard/ajax/overview.blade.php:3-82` – показва се при: Each card is gated only by module + widget, e.g. @if (in_array('clients', user_modules()) && in_array('total_clients', $activeWidgets)) <a href="{{ route('clients.index') }}">; the same for employees, invoices, timelogs, tasks, attendance, tickets
- Води към: clients.index, invoices.index, timelogs.index, tasks.index, attendances.index, tickets.index, employees.index
- Проверка/отказ: app/Http/Controllers/ClientController.php:83 (view_clients in all/added/both, so 'owned' is refused), InvoiceController.php:70, TimelogController.php:55, TaskController.php:71, AttendanceController.php:80, TicketController.php:44, EmployeeController.php:92
- Сценарий: Employee role granted 'View overview dashboard: All' (the sidebar shows Advanced dashboard via menu.blade.php:5), but with view_clients/view_invoices none, which is typical for employees.
- Ефект: Overview cards such as Total clients or Unpaid invoices are clickable and open 403 pages.
- Поправка: Wrap each card's <a> in the matching $sidebarUserPermissions check (view_clients in [1,3,4], view_invoices != 5, ...), otherwise render the card without a link.
- Проверка от втори агент: overview.blade.php:3-82 cards check module + widget only. Their targets abort_403 on view permission (ClientController.php:83 needs all/added/both, InvoiceController.php:69-70, EmployeeController.php:92-93). Employees with view_overview_dashboard=all reach this tab (DashboardController.php:214).
- Намерено от: dashboards

### F053 · средна · мъртва връзка (403/404)

**Client dashboard cards ignore the client role's view_* permissions that the sidebar enforces**

- Елемент: `resources/views/dashboard/client/index.blade.php:10-66` – показва се при: @if (in_array('projects', user_modules())) -> projects.index; @if (in_array('contracts', user_modules())) -> contracts.index; @if (in_array('invoices', user_modules())) -> invoices.index (module only)
- Води към: projects.index, contracts.index, invoices.index
- Проверка/отказ: ProjectController.php:92 abort_403(!in_array($viewPermission, all/added/owned/both)); ContractController.php:45 abort_403(view_contract == 'none'); InvoiceController.php:70. The sidebar additionally requires view_* != 5/none (menu.blade.php:348, 351, 389).
- Сценарий: The company admin sets the client role's 'View projects' (or contracts/invoices) to None, so the sidebar hides it, but the client dashboard still shows the card.
- Ефект: The client clicks 'Total projects' / 'Contracts signed' / invoice counts and gets 403.
- Поправка: Add && $sidebarUserPermissions['view_projects'|'view_contract'|'view_invoices'] != 5 to each card condition.
- Проверка от втори агент: dashboard/client/index.blade.php:10, :28 and :38 check only modules. ProjectController.php:91-92, ContractController.php:45 and InvoiceController.php:69-70 abort_403 on the view permission, and the sidebar requires it (menu.blade.php:348,351,389).
- Намерено от: dashboards

### F055 · средна · няма проверка на сървъра

**Dashboard AJAX endpoints work regardless of module: lead-stage chart, private calendar feed, clock-in/out, week timelog**

- Елемент: `routes/web.php:148-155` – показва се при: Routes dashboard/week-timelog, dashboard/lead-data/{id}, attendances/clock-in-modal|store-clock-in|update-clock-in|show_clocked_hours, dashboard/private_calendar in the plain account group; the controller only blocks super admin (DashboardController.php:46-51)
- Води към: DashboardController@getLeadStage (line 508), @privateCalendar (line 301), @weekTimelog (line 264), EmployeeDashboard clockInModal/storeClockIn/updateClockIn/showClockedHours (EmployeeDashboard.php:665, 709, 842, 1081)
- Проверка/отказ: Missing: getLeadStage has no leads-module and no view_client_dashboard check. privateCalendar checks only permissions, never leaves/holidays/tickets/events/leads modules, and returns approved leaves of all employees for any view_leave != none (lines 450-475). Clock endpoints have no attendance check; weekTimelog has no timelogs check.
- Сценарий: Package 'Default' (no leaves/holidays/timelogs) or a module switched off for a role: an employee calls /account/dashboard/private_calendar?start=..&end=.. or /account/dashboard/lead-data/1 directly. The UI widgets that use them (my_calendar, client tab) are config-hidden.
- Ефект: Package/module restrictions can be bypassed by URL: leave and holiday data, lead pipeline stats and attendance clock-in work although the module is off.
- Поправка: Add module guards: getLeadStage abort_403(!in_array('leads', user_modules()) || user()->permission('view_client_dashboard') != 'all'); clock methods abort_403(!in_array('attendance', user_modules())); weekTimelog abort_403(!in_array('timelogs', user_modules())); privateCalendar only builds sections whose module is on.
- Проверка от втори агент: Routes web.php:148-155 are plain. DashboardController.php:45-50 blocks only the superadmin. getLeadStage (:508) has no leads/permission check. EmployeeDashboard clockInModal (:665), storeClockIn (:709), updateClockIn (:842) and showClockedHours (:1081) have no attendance check. weekTimelog (:264) has no timelogs check.
- Намерено от: dashboards

### F082 · средна · мъртва връзка (403/404)

**Global search modal: 'Task' option is gated by the tickets module instead of tasks**

- Елемент: `resources/views/search/index.blade.php:23` – показва се при: @if (in_array('tickets', user_modules())) <option value="task">
- Води към: tasks.index?search_keyword=... (SearchController.php:33-34)
- Проверка/отказ: app/Http/Controllers/TaskController.php:60 abort_403(!in_array('tasks', $this->user->modules))
- Сценарий: Role with tickets on and tasks off: choosing Task redirects to a 403. The 'Ticket' (:14) and 'Notice' (:20) options also stay visible although tickets and notices are config-hidden
- Ефект: 403 after a search
- Поправка: Use in_array('tasks', user_modules()) at :23 and add !databackd_hidden checks to the ticket and notice options.
- Проверка от втори агент: Duplicate of F015 (+F021): search/index.blade.php:23 gates Task on tickets, and TaskController.php:60 checks tasks. Ticket :14 and notice :20 ignore databackd_hidden.
- Намерено от: hr

### F094 · средна · мъртва връзка (403/404)

**Search modal offers 'Client' to users without client view permission**

- Елемент: `resources/views/search/index.blade.php:39-41 (opened from the topbar search icon, sections/topbar.blade.php:83-86)` – показва се при: @if (!in_array('client', user_roles())) ... @if (in_array('clients', user_modules())) <option value="client"> with no view_clients check
- Води към: SearchController@store case 'client' -> redirect to clients.index (SearchController.php:39-40)
- Проверка/отказ: ClientController.php:83 abort_403(!in_array($viewPermission, ['all', 'added', 'both'])) on view_clients
- Сценарий: Default employee role (view_clients = none) on any package; the employee picks 'Client' in the top-bar search
- Ефект: The search redirects to a 403 page
- Поправка: @if (in_array('clients', user_modules()) && in_array(user()->permission('view_clients'), ['all','added','both'])) (not just != 'none', since 'owned' is also refused).
- Проверка от втори агент: search/index.blade.php:39-41 checks the clients module only. SearchController.php:39-40 redirects to clients.index, and ClientController.php:83 requires view_clients all/added/both. The default employee has none. Part of F014.
- Намерено от: clients

### F180 · средна · мъртва връзка (403/404)

**Sidebar shows 'Finance report' for added/owned/both, but the controller requires 'all'**

- Елемент: `resources/views/sections/menu.blade.php:185` – показва се при: `$sidebarUserPermissions['view_finance_report'] != 5 && $sidebarUserPermissions['view_finance_report'] != 'none' && in_array('payments', user_modules())` (the parent at :159 also accepts `view_finance_report != 5`)
- Води към: finance-report.index
- Проверка/отказ: app/Http/Controllers/FinanceReportController.php:48 abort_403(user()->permission('view_finance_report') != 'all'). The permission is defined with Permission::ALL_4_ADDED_1_OWNED_2_BOTH_3_NONE_5 (app/Models/Module.php:1486), so the role editor offers Added/Owned/Both.
- Сценарий: Any package with reports and payments. The company admin sets 'View Finance Report' to Owned, Added or Both for the employee role (or a custom role).
- Ефект: Reports -> Finance report is visible and clicking it returns 403.
- Поправка: Use $sidebarUserPermissions['view_finance_report'] == 4 at menu.blade.php:185 and in the parent condition at :159.
- Проверка от втори агент: menu.blade.php:185 shows Finance report for view_finance_report != 5 (the parent at :159 also uses != 5). FinanceReportController.php:48 aborts unless 'all'. The permission allows added/owned/both (Module.php:1485-1487).
- Намерено от: crm-reports

### F181 · средна · мъртва връзка (403/404)

**Finance report shows a Project filter, a 'By project' chart and project/invoice links without checking the projects/invoices modules**

- Елемент: `resources/views/reports/finance/index.blade.php:72` – показва се при: Project filter (lines 72-84) and the 'By project' card (:184) are unconditional (only the Client filter at :57 checks `in_array('clients', user_modules())`). DataTable: app/DataTables/FinanceReportDataTable.php:36 `route('projects.show', ...)` and :44 `route('invoices.show', ...)`. The 'Top clients' card (:198) has no clients-module check either.
- Води към: projects.show / invoices.show
- Проверка/отказ: ProjectController and InvoiceController constructor middleware: abort_403(!in_array('projects'|'invoices', $this->user->modules)). FinanceReportController::index (line 54) loads Project::allProjects() regardless of the module.
- Сценарий: Any package. The admin switches projects (or invoices) off for a role that still has payments, reports and view_finance_report = all. The Expense report project filter (resources/views/reports/expense/index.blade.php:51-63, ExpenseReportController.php:34) has the same problem.
- Ефект: Project data and filters of a switched-off module stay visible, and clicking a project or invoice number in the transaction log returns 403.
- Поправка: Wrap the project filter and By-project card in @if (in_array('projects', user_modules())) and Top clients in the clients check. Render plain text in FinanceReportDataTable when projects or invoices is off.
- Проверка от втори агент: reports/finance/index.blade.php:72-84 Project filter and :182 By-project card have no projects check, and :199 Top clients has no clients check (only :58 checks clients). FinanceReportDataTable.php:36 links projects.show and :44 links invoices.show. FinanceReportController.php:54 loads projects unconditionally.
- Намерено от: crm-reports

### F182 · средна · мъртва връзка (403/404)

**Finance dashboard 'Total earnings / Total expenses' card links to the Income vs Expense report without checking that permission or the expenses/reports modules**

- Елемент: `resources/views/dashboard/ajax/finance.blade.php:44` – показва се при: `@if (in_array('invoices', user_modules()) && (in_array('total_expenses', $activeWidgets) || in_array('total_earnings', $activeWidgets)))`; click handler at :173 goes to `route('income-expense-report.index')`. The tab itself needs only `$viewFinanceDashboard == 'all'` (resources/views/dashboard/admin.blade.php:72).
- Води към: income-expense-report.index
- Проверка/отказ: app/Http/Controllers/IncomeVsExpenseReportController.php:24 abort_403(user()->permission('view_income_expense_report') != 'all'). There is no module check at all (constructor :16-20).
- Сценарий: Case 1: an employee or custom role has view_finance_dashboard = all but view_income_expense_report = none (the default for non-admin roles). Case 2: the admin switches expenses or reports off for a role.
- Ефект: Case 1: clicking the card opens a 403 page. Case 2: the card still shows 'Total expenses' and opens the full Income vs Expense report although the module is off.
- Поправка: Make the card clickable only when user()->permission('view_income_expense_report') == 'all' && in_array('reports', user_modules()) && in_array('expenses', user_modules()), and gate total_expenses on the expenses module.
- Проверка от втори агент: dashboard/ajax/finance.blade.php:44 shows the card with the invoices module only, and #totalEarnings (:173-178) goes to income-expense-report.index. IncomeVsExpenseReportController.php:24 aborts unless view_income_expense_report == 'all', and there is no module check (:16-20). The tab needs only view_finance_dashboard (admin.blade.php:72).
- Намерено от: crm-reports

### F185 · средна · мъртва връзка (403/404)

**Topbar search: the 'Task' option is gated by the tickets module instead of tasks**

- Елемент: `resources/views/search/index.blade.php:23` – показва се при: `@if (in_array('tickets', user_modules())) <option value="task">` (lines 23-25)
- Води към: search.store -> redirect to tasks.index (app/Http/Controllers/SearchController.php:35)
- Проверка/отказ: TaskController constructor middleware abort_403(!in_array('tasks', $this->user->modules))
- Сценарий: Any package. A role has tickets active (it is in every package and in CLIENT_MODULES) but the admin switched tasks off for that role. The search icon in the topbar (resources/views/sections/topbar.blade.php:85) is visible to every role.
- Ефект: Choosing 'Task' and searching ends on a 403 page. When tickets is off, task search is missing although tasks is on.
- Поправка: Change the guard at search/index.blade.php:23 to in_array('tasks', user_modules()).
- Проверка от втори агент: search/index.blade.php:23-25 gates the 'task' option with in_array('tickets', user_modules()). SearchController.php:34-35 redirects to tasks.index, and TaskController.php:60 aborts without tasks. The search icon is in topbar.blade.php:83-87.
- Намерено от: crm-reports

### F196 · средна · мъртва връзка (403/404)

**Advanced dashboard default tab: ticket and finance branches are swapped, so finance-only or ticket-only roles get a 403 from the sidebar link**

- Елемент: `resources/views/sections/menu.blade.php:10` – показва се при: The 'Advanced dashboard' sub-item is shown when `$sidebarUserPermissions['view_ticket_dashboard'] == 4 || ... || $sidebarUserPermissions['view_finance_dashboard'] == 4` (lines 4-11)
- Води към: dashboard.advanced (no ?tab)
- Проверка/отказ: app/Http/Controllers/DashboardController.php:234-236: the view_finance_dashboard == 4 branch calls $this->ticketDashboard(), which aborts with app/Traits/TicketDashboard.php:25 abort_403(view_ticket_dashboard !== 'all'). Lines 239-241: the view_ticket_dashboard == 4 branch calls $this->financeDashboard(), which aborts with app/Traits/FinanceDashboard.php:29.
- Сценарий: An employee or custom role has only the 'View Finance Dashboard' permission (all) and no overview, project, client or HR dashboard permissions. It clicks Dashboard -> Advanced dashboard.
- Ефект: A 403 appears instead of the Finance dashboard. A ticket-only role likewise gets 403, and the ticket dashboard is config-hidden anyway.
- Поправка: Call financeDashboard() in the finance branch (activeTab 'finance'). In the ticket branch call ticketDashboard() (activeTab 'ticket'), skipped when databackd_hidden('dashboard_tabs', 'ticket').
- Проверка от втори агент: DashboardController.php:234-237: the view_finance_dashboard == 4 branch calls ticketDashboard(), which aborts at TicketDashboard.php:25 unless view_ticket_dashboard is 'all'. :239-241: the ticket branch calls financeDashboard(), which aborts at FinanceDashboard.php:29. The sidebar shows Advanced dashboard for these roles (menu.blade.php:4-11).
- Намерено от: crm-reports

### F006 · ниска · мъртва връзка (403/404)

**Anagami Finance View "Reports" accordion can be shown with no items**

- Елемент: `resources/views/sections/menu.blade.php:159` – показва се при: in_array('reports', user_modules()) && ($sidebarUserPermissions['view_task_report'] == 4 || ['view_time_log_report'] == 4 || ... || ['view_leave_report'] != 5 || ['view_attendance_report'] == 4 || ['view_lead_report'] == 4 || ['view_sales_report'] == 4)
- Води към: Reports sub-items at :167-221
- Проверка/отказ: Sub-items: task report :167, weekly timesheet :178, leave :195, attendance :202 and deal :212 are databackd_hidden. Time log :173 needs timelogs, finance :185 needs payments, income/expense :190 and expense :208 need expenses, sales :218 needs invoices. The outer condition checks only 'reports' and counts the permissions of hidden items
- Сценарий: Employee role with View task report = All only. Or a company on package Default (no timelogs) where the role has View time log report = All and no finance/sales report permissions
- Ефект: The "Reports" group header in the Analysis section opens to nothing
- Поправка: Buffer the Reports sub-items and print the group only when the buffer contains a link, or build :159 as the OR of the exact per-item conditions including databackd_hidden and the module.
- Проверка от втори агент: menu.blade.php:159 checks only 'reports' plus OR'ed permissions, including those of hidden items (task report :166 report_tasks, leave :194, attendance :201, deal :210 are databackd_hidden). It also ignores the per-item modules (timelogs :173, payments :185, expenses :190/:207, invoices :217). With only view_task_report=all, or view_time_log_report=all on Default (no timelogs), the group header renders empty. Lowered because it needs a custom permission set and does not 403.
- Намерено от: sidebar

### F007 · ниска · мъртва връзка (403/404)

**"Work" and "Finance" accordions check modules and permissions separately and can be shown empty**

- Елемент: `resources/views/sections/menu.blade.php:371` – показва се при: (in_array('estimates'|'invoices'|'payments'|'expenses'|'bankaccount', user_modules())) && (view_estimates != 5 || view_invoices != 5 || view_payments != 5 || view_expenses != 5 || view_lead_proposals != 5 || view_bankaccount != 5); Work at :341 uses the same pattern
- Води към: Finance sub-items :379-419 / Work sub-items :348-360
- Проверка/отказ: Each sub-item needs its own module AND its own permission. Proposals :379 and estimates :384 are databackd_hidden but their permissions still open the group
- Сценарий: The company admin switches 'expenses' off for the employee role. A default employee (view_expenses=BOTH; view_invoices/view_payments none) still matches the Finance condition through invoices/payments modules plus view_expenses, but no item is visible. Work: package Default (no timelogs) with projects/tasks off for employees and contracts on
- Ефект: Empty "Finance" or "Work" group in the sidebar
- Поправка: Use the same buffer pattern for the Work and Finance groups (print only when non-empty), or OR the per-item conditions.
- Проверка от втори агент: menu.blade.php:371 (Finance) and :341 (Work) OR the modules and OR the permissions independently. Finance example: a default employee (view_expenses=BOTH, no invoice/payment permission) with expenses switched off for the employee role. invoices/payments modules plus view_expenses!=5 open the group, but :389-414 need module AND permission per item, so nothing renders. Work: contracts on, view_timelogs=3, projects/tasks off and timelogs not in Default gives an empty group too.
- Намерено от: sidebar

### F024 · ниска · мъртва връзка (403/404)

**"Create new" (+) dropdown is always rendered for non-clients, even when none of its items qualifies**

- Елемент: `resources/views/sections/topbar.blade.php:130` – показва се при: The whole <li> is rendered for every non-client (line 101); each item has its own condition (lines 138-184) and nothing guards the wrapper
- Води към: (empty dropdown)
- Проверка/отказ: n/a: none of the six item conditions holds
- Сценарий: Employee role with default permissions (only add_tasks=added; tickets is config-hidden) when the admin switched Tasks off for employees
- Ефект: The + icon opens an empty dropdown (dead end).
- Поправка: Compute the six item conditions in @php first and render the <li> only when at least one is true.
- Проверка от втори агент: topbar.blade.php:130-187 renders the + dropdown for every non-client. Each item has its own condition (:138-184), and quick_add.ticket is hidden. A default employee (only add_tasks=added) with tasks switched off for the employee role gets an empty dropdown.
- Намерено от: topbar-global

### F076 · ниска · мъртва връзка (403/404)

**Private dashboard 'You are on leave' / 'Holiday today' banners ignore the leaves/holidays modules and the config hiding**

- Елемент: `resources/views/dashboard/employee/index.blade.php:69` – показва се при: @if (!is_null($checkTodayLeave)) <a href="{{ route('leaves.show', ...) }}" class="openRightModal"> @elseif (!is_null($checkTodayHoliday)) <a href="{{ route('holidays.show', ...) }}"> (:69-87). Data computed without module checks in app/Traits/EmployeeDashboard.php:330-352
- Води към: leaves.show / holidays.show
- Проверка/отказ: LeaveController.php:39-42 abort_403(!in_array('leaves', ...)). HolidayController has no module check
- Сценарий: Company moved to Default (leaves/holidays dropped) or admin switched leaves off for the employee role while approved leaves exist: the banner shows and its link 403s (leave). The holiday link opens a module outside the package. Both features are config-hidden (menu.leaves, menu.holidays)
- Ефект: 403 in the right modal / leak of hidden modules on the dashboard
- Поправка: Compute $checkTodayLeave only when in_array('leaves', user_modules()) && !databackd_hidden('menu','leaves'), and $checkTodayHoliday only when holidays is on and not hidden.
- Проверка от втори агент: employee/index.blade.php:69-87 render the alerts from EmployeeDashboard.php:328-350, which has no module check. leaves.show gives 403 via LeaveController.php:40 when leaves is off. HolidayController has no module check. Lowered: it needs an approved leave or holiday today while leaves/holidays are hidden or off (overlaps F059).
- Намерено от: hr

### F190 · ниска · мъртва връзка (403/404)

**The sidebar 'Reports' parent can render with no children (empty accordion)**

- Елемент: `resources/views/sections/menu.blade.php:159` – показва се при: Parent: `in_array('reports', user_modules()) && (view_task_report == 4 || view_time_log_report == 4 || view_finance_report != 5 || view_leave_report != 5 || view_attendance_report == 4 || view_lead_report == 4 || ...)`. The children additionally require the module (tasks/timelogs/payments/expenses/leaves/attendance/leads/invoices) and are config-hidden for task, weekly timesheet, leave, attendance and deal reports (:167, :178, :195, :202, :212).
- Води към: (no target: empty menu)
- Проверка/отказ: The children's own guards plus databackd_hidden('menu','report_*').
- Сценарий: Example: the admin grants an employee role only 'View Leave Report' (Owned/All) or only 'View Task Report'. Another example: a Default-package role whose only report permission is for a report whose module is missing or hidden.
- Ефект: A 'Reports' entry appears in the sidebar and opens an empty dropdown.
- Поправка: Buffer the children (ob_start) and print the Reports parent only when at least one sub-menu item was rendered.
- Проверка от втори агент: menu.blade.php:159 parent checks reports plus any report permission. The children (:167-221) also require each report's module and several are config-hidden (:167, :178, :195, :202, :212). Nothing removes empty accordions, so a role whose only report is hidden or module-less gets an empty Reports group.
- Намерено от: crm-reports

## G08. Страници без проверка на модула на сървъра (пакетът се заобикаля по URL) (16)

### F009 · средна · няма проверка на сървъра

**Anagami Finance View report controllers behind the sidebar Reports items check neither 'reports' nor the source module**

- Елемент: `routes/web.php:885` – показва се при: Sidebar items require reports + timelogs (menu.blade.php:159/:173), payments (:185), expenses (:190/:208) or invoices (:218); the routes have no module middleware
- Води към: time-log-report.*, finance-report.*, income-expense-report.*, expense-report.*, sales-report.* (routes/web.php:881-917)
- Проверка/отказ: Constructors with no module check: app/Http/Controllers/TimelogReportController.php:23-28, FinanceReportController.php:40-44, IncomeVsExpenseReportController.php:16, ExpenseReportController.php:19, SalesReportController.php:13; only permission checks in the actions. CheckCompanyPackage (app/Http/Middleware/CheckCompanyPackage.php) does not check modules
- Сценарий: Admin of a company on package Default (no timelogs) opens /account/time-log-report; or any report URL after 'reports' is switched off for a role
- Ефект: The time-log report and the other reports open by URL although the module is not in the package or is switched off
- Поправка: Add constructor middleware abort_403(!in_array('reports', $this->user->modules) || !in_array('<timelogs|payments|expenses|expenses|invoices>', $this->user->modules)) to each report controller.
- Проверка от втори агент: Constructors TimelogReportController.php:23-28, FinanceReportController.php:40-44, IncomeVsExpenseReportController.php:16-20, ExpenseReportController.php:19-24 and SalesReportController.php:13-17 have no module middleware. The actions check only permissions (e.g. :32 view_time_log_report). Routes web.php:881-917 are plain, and CheckCompanyPackage checks only package validity and limits. An admin on Default (no timelogs) can open /account/time-log-report.
- Намерено от: sidebar

### F010 · средна · няма проверка на сървъра

**Holidays / Appreciations / Awards / Notices controllers have no module check (package bypass by URL)**

- Елемент: `routes/web.php:508` – показва се при: Sidebar items menu.blade.php:313 (holidays module), :325/:328 (appreciation, no module check at all), :514 (notices). All are currently config-hidden, but routes stay registered
- Води към: holidays.*, appreciations.*, awards.*, notices.* (routes/web.php:388, :396-406, :504-508)
- Проверка/отказ: app/Http/Controllers/HolidayController.php:25-29 (no 'holidays' check), AppreciationController.php:19 and AwardController.php:16 (appreciation permissions belong to the 'employees' module, app/Models/Module.php:146/:281), NoticeController.php:23 (no 'notices' check). Only permission checks in index
- Сценарий: Company on package Default (no holidays, no employees): admins and default employees (view_holiday=OWNED, view_appreciation=OWNED) open /account/holidays or /account/appreciations
- Ефект: Pages of modules not in the package work by URL; the appreciation sidebar item would also leak if un-hidden because :325 has no employees check
- Поправка: Constructor middleware abort_403(!in_array('holidays', $this->user->modules)) in HolidayController, 'employees' in AppreciationController/AwardController and 'notices' in NoticeController. Add in_array('employees', user_modules()) to menu.blade.php:325 and :328.
- Проверка от втори агент: HolidayController.php:25-29, AppreciationController.php:19-23, AwardController.php:16-20 and NoticeController.php:23-27 have no module middleware. index checks only view_holiday/view_appreciation/view_notice (HolidayController.php:33-34, AwardController.php:24-26). Routes web.php:388 (notices), :396-406 (appreciations/awards) and :504-508 (holidays) are open. Default lacks holidays and employees, and default employees have view_holiday=OWNED and view_appreciation=OWNED (PermissionRole.php:118,124).
- Намерено от: sidebar

### F026 · средна · няма проверка на сървъра

**NoticeController has no notices module check, so notices work by URL (and from bell links) when the module is off**

- Елемент: `routes/web.php:388` – показва се при: Route::resource('notices', NoticeController::class) in the account group (web.php:138) with no module middleware
- Води към: notices.index/create/store/show/edit/update/destroy
- Проверка/отказ: MISSING: app/Http/Controllers/NoticeController.php:23-27 constructor has no abort_403(!in_array('notices', $this->user->modules)); actions check only view_notice/add_notice permissions (lines 37, 51, 122...)
- Сценарий: Company admin switches Notices off for the employee or client role (or a package without notices). Users with view_notice=owned (the employee/client default) can still open /account/notices and the NewNotice/NoticeUpdate bell links.
- Ефект: The notices module is reachable although it is switched off.
- Поправка: Add $this->middleware(function ($request, $next) { abort_403(!in_array('notices', $this->user->modules)); return $next($request); }); in NoticeController::__construct.
- Проверка от втори агент: NoticeController.php:23-27 constructor has no module middleware. Actions check only view_notice/add_notice permissions. Routes web.php:507-508 (notices resource) are plain.
- Намерено от: topbar-global

### F036 · средна · няма проверка на сървъра

**Deal email templates controller checks only manage_deal_email_template, not the leads module**

- Елемент: `routes/web-settings.php:139` – показва се при: UI: the Lead settings 'email-templates' tab (LeadSettingController.php:23-31 requires leads) and the leads send-email modal (resources/views/leads/ajax/send-email.blade.php:109)
- Води към: deal-email-templates.* and deal-email-templates.fetch
- Проверка/отказ: app/Http/Controllers/DealEmailTemplateController.php:22-23 abort_403(user()->permission('manage_deal_email_template') == 'none'), with no in_array('leads', user_modules())
- Сценарий: The leads module is switched off for the role (and is config-hidden for everyone). A user with manage_deal_email_template calls /account/settings/deal-email-templates directly.
- Ефект: Deal email templates can be listed, created, edited and deleted while the leads module is off.
- Поправка: abort_403(user()->permission('manage_deal_email_template') == 'none' || !in_array('leads', user_modules()));
- Проверка от втори агент: DealEmailTemplateController.php:22-26 checks only manage_deal_email_template == 'none', with no leads module check. Routes web-settings.php:139 are behind 'auth' only.
- Намерено от: settings

### F071 · средна · няма проверка на сървъра

**HolidayController has no holidays module check**

- Елемент: `routes/web.php:504` – показва се при: Route::resource('holidays', HolidayController::class) + mark-holiday/table-view/apply-quick-action (:504-508)
- Води към: holidays.* (index, create, store, show, edit, update, destroy, markHoliday, tableView)
- Проверка/отказ: app/Http/Controllers/HolidayController.php:25-29 constructor only sets pageTitle. Actions check only view_holiday/add_holiday (:34, :95). There is no abort_403(!in_array('holidays', ...)), unlike LeaveController.php:39-42
- Сценарий: Package Default (holidays not in package): an admin with view_holiday=all opens /account/holidays (calendar, CRUD). Visible path: the private dashboard 'Holiday today' banner (dashboard/employee/index.blade.php:83) and the NewHoliday notification link (app/Notifications/NewHoliday.php:64)
- Ефект: Holiday module works fully although it is not in the package
- Поправка: Add constructor middleware abort_403(!in_array('holidays', $this->user->modules)).
- Проверка от втори агент: HolidayController.php:25-29 has no middleware, and index checks only view_holiday (:33-34). Routes web.php:504-508 are plain. The dashboard holiday alert (employee/index.blade.php:83) links holidays.show. Overlaps F010.
- Намерено от: hr

### F072 · средна · няма проверка на сървъра

**Employees-module sub-controllers without a module check: AppreciationController, AwardController, PromotionController, PassportController (Passport has no checks at all)**

- Елемент: `routes/web.php:394` – показва се при: appreciations + appreciations/awards groups (:394-406), Route::resource('promotions') (:196), Route::resource('passport') (:213)
- Води към: appreciations.*, awards.*, promotions.*, passport.*
- Проверка/отказ: AppreciationController.php:19-23 and AwardController.php:16-20: constructor without middleware (their permissions belong to the 'employees' module, migration 2022_09_01_060824 :71). PromotionController.php:22-25 checks only manage_increment_promotion. PassportController.php:12-15 extends Controller with no permission or module check in create/store/edit/update/destroy
- Сценарий: Package Default or employees switched off for a role: /account/appreciations, /account/appreciations/awards, /account/promotions/create?user_id=..., /account/passport/* keep working by URL. Passport store/update/destroy works for any logged-in user, clients included
- Ефект: Package bypass by URL. Passport records can be edited without any permission
- Поправка: Add abort_403(!in_array('employees', $this->user->modules)) middleware to all four. PassportController: extend AccountBaseController and enforce add/edit/delete_immigration as in EmployeeVisaController.
- Проверка от втори агент: AppreciationController.php:19-23 and AwardController.php:16-20 have no module middleware. PromotionController.php:22-25 checks only manage_increment_promotion. PassportController.php:12 extends Controller and create/store/edit/update/destroy (:15-90) have no permission or module check. Routes web.php:196, :213 and :394-406.
- Намерено от: hr

### F075 · средна · няма проверка на сървъра

**LeaveReportController and AttendanceReportController do not check the leaves/attendance (or reports) module**

- Елемент: `routes/web.php:896` – показва се при: leave-report routes :896-899, Route::resource('attendance-report') :901
- Води към: leave-report.*, attendance-report.index
- Проверка/отказ: app/Http/Controllers/LeaveReportController.php:18-22 and AttendanceReportController.php:11-15: constructors without middleware. Actions check only view_leave_report / view_attendance_report. The sidebar gates both with the modules and config-hides them (menu.blade.php:195-206)
- Сценарий: Package Default (no leaves): admin opens /account/leave-report or /account/leave-report/leave-quota by URL
- Ефект: Report of a module outside the package works
- Поправка: Add constructor middleware abort_403(!in_array('reports', $this->user->modules) || !in_array('leaves'|'attendance', $this->user->modules)).
- Проверка от втори агент: LeaveReportController.php:18-22 and AttendanceReportController.php:11-15 have no module middleware, and index checks only view_leave_report / view_attendance_report (:26-27, :19). Routes web.php:896-901 are plain.
- Намерено от: hr

### F098 · средна · няма проверка на сървъра

**Client notes, client docs and client category/subcategory controllers have no clients module check**

- Елемент: `app/Http/Controllers/ClientNoteController.php:23-25; ClientDocController.php:19-21; ClientCategoryController.php (no constructor); ClientSubCategoryController.php (no constructor); routes/web.php:180-193` – показва се при: Reachable by URL (/account/client-notes, /account/client-docs/*, /account/clientCategory/create, /account/getClientSubCategories/{id}) with the clients module off
- Води към: client-notes.*, client-docs.*, clientCategory.*, clientSubCategory.*, get_client_sub_categories
- Проверка/отказ: Missing: the constructor middleware is an empty pass-through; the category create/store actions do not even check manage_client_category / manage_client_subcategory
- Сценарий: Clients switched off for the admin/employee role; the user opens /account/client-notes?... or /account/clientCategory/create directly
- Ефект: Client notes/documents/categories of a switched-off module can still be listed, created and edited
- Поправка: Add abort_403(!in_array('client', user_roles()) && !in_array('clients', user_modules())) to ClientNote/ClientDoc middleware, and abort_403(!in_array('clients', user_modules())) plus the manage_client_category/subcategory checks to the category controllers.
- Проверка от втори агент: ClientNoteController.php:22-24 and ClientDocController.php:19-21 middleware are pass-throughs. ClientCategoryController has no constructor and its create/store have no check. Routes web.php:180-193 are plain.
- Намерено от: clients

### F117 · средна · няма проверка на сървъра

**Project sub-controllers have no projects module check (and getReplies has no permission check)**

- Елемент: `app/Http/Controllers/DiscussionController.php:17` – показва се при: Constructors without the module middleware: DiscussionController.php:17-21, ProjectLabelController.php:15-19, ProjectTemplateSubTaskController.php:16-21; no constructor at all in ProjectFileController, DiscussionReplyController, DiscussionFilesController, DiscussionCategoryController, ProjectRatingController, ProjectCategoryController, ProjectSubCategoryController, GanttLinkController; DiscussionReplyController.php:50 getReplies() returns discussion HTML with no permission check
- Води към: discussion.show, discussion-reply.get_replies, files.download, discussion_file.download, project-ratings.*, project-label.*, projectCategory.*, gantt_link.*, project-template-sub-task.*
- Проверка/отказ: Missing: siblings use abort_403(!in_array('projects', $this->user->modules)) (e.g. ProjectMemberController.php:24, ProjectNoteController.php:22)
- Сценарий: Company admin switches the projects module off for the employee or client role; such a user can still read discussions/replies and download project and discussion files by URL or AJAX
- Ефект: Project data is still readable and writable while the module is off
- Поправка: Add $this->middleware(function ($request, $next) { abort_403(!in_array('projects', $this->user->modules)); return $next($request); }); to these controllers, and the view_project_discussions check from DiscussionController::show to getReplies().
- Проверка от втори агент: DiscussionController.php:17-21, ProjectLabelController.php:15 and ProjectTemplateSubTaskController.php:16 have constructors without module middleware. ProjectFileController, DiscussionReplyController, DiscussionFilesController, DiscussionCategoryController, ProjectRatingController, ProjectCategoryController, ProjectSubCategoryController and GanttLinkController contain no 'modules' check at all. DiscussionReplyController.php:49-70 getReplies() has no permission check. ProjectMemberController.php:24 shows the pattern they lack.
- Намерено от: projects

### F134 · средна · няма проверка на сървъра

**Sub-task, task file, sub-task file, task label and task category controllers have no tasks module check**

- Елемент: `routes/web.php:472` – показва се при: Route::resource('task-label'), 'taskCategory' (473), 'task-files' (482), 'sub-tasks' (486) and 'sub-task-files' (490) sit in the plain tasks prefix group with no module middleware.
- Води към: task-label.*, taskCategory.*, task-files.*, sub-tasks.*, sub_tasks.change_status, sub-task-files.*
- Проверка/отказ: Missing. SubTaskController, SubTaskFileController, TaskFileController (constructor lines 15-20 only set titles), TaskLabelController and TaskCategoryController never check in_array('tasks', user_modules()), unlike TaskController.php:60, TaskCommentController.php:22 and TaskNoteController.php:20.
- Сценарий: Tasks is switched off for the employee role. The employee calls /account/tasks/sub-tasks, task-files or task-label directly (permissions such as add_sub_tasks are kept).
- Ефект: Sub-tasks, files, labels and categories of the tasks module can still be created, edited, downloaded and deleted.
- Поправка: Add constructor middleware abort_403(!in_array('tasks', $this->user->modules)) to these five controllers (or a route-group middleware). Note that TaskLabel/TaskCategory are also used by project templates.
- Проверка от втори агент: routes/web.php:472 (task-label), :473 (taskCategory), :482 (task-files), :486 (sub-tasks) and :490 (sub-task-files) are in a plain prefix group. SubTaskController, SubTaskFileController, TaskFileController, TaskLabelController and TaskCategoryController contain no module check, unlike TaskController.php:60.
- Намерено от: tasks-timelogs

### F174 · средна · няма проверка на сървъра

**ContractTemplateController, ContractTypeController and ContractFileController have no contracts module check**

- Елемент: `routes/web.php:806` – показва се при: Route::resource('contract-template', ...), and contracts/contractTypes and contracts/contractFiles (+ download) at routes/web.php:796-798, under plain auth middleware
- Води към: /account/contract-template, /account/contracts/contractTypes/*, /account/contracts/contractFiles/*
- Проверка/отказ: Missing: ContractTemplateController.php:18-22, ContractTypeController.php (no constructor) and ContractFileController.php:13-17 check only permissions, while ContractController.php:37, ContractRenewController.php:20 and ContractDiscussionController.php:20 abort_403(!in_array('contracts', $this->user->modules))
- Сценарий: Contracts is switched off for a role, or is not in the package. A user with manage_contract_template or add_contract_files can still list, create and edit contract templates, create contract types, and upload or download contract files by URL.
- Ефект: Contract features stay usable by URL while the module is off
- Поправка: Add $this->middleware(function ($request, $next) { abort_403(!in_array('contracts', $this->user->modules)); return $next($request); }); to the three controllers.
- Проверка от втори агент: ContractTemplateController (constructor :18), ContractTypeController (no constructor) and ContractFileController (constructor :13) contain no 'modules' check. ContractController.php:37 has it. Routes at web.php:796-798 and :805-807.
- Намерено от: expenses-products-contracts

### F175 · средна · няма проверка на сървъра

**ProductCategoryController, ProductSubCategoryController and ProductFileController have no products module check (and no permission check)**

- Елемент: `routes/web.php:352` – показва се при: Route::resource('productCategory'), get_product_sub_categories and productSubCategory (:353-354), product-files.* (:357-360)
- Води към: /account/productCategory, /account/productSubCategory, /account/getProductSubCategories/{id}, /account/product-files/*
- Проверка/отказ: Missing: ProductCategoryController and ProductSubCategoryController have no constructor; ProductFileController.php:17-22 sets only the title, while ProductController.php:38 and ProductResourceController.php:17 abort_403(!in_array('products', $this->user->modules))
- Сценарий: The products module is switched off for a role. A logged-in user can still create, rename or delete product categories and sub-categories, and upload, replace or download product images and files by URL.
- Ефект: Product data can be changed by URL while the module is off
- Поправка: Add constructor middleware abort_403(!in_array('products', $this->user->modules)) plus manage_product_category / manage_product_sub_category / edit_product checks.
- Проверка от втори агент: ProductCategoryController and ProductSubCategoryController have no constructor, and ProductFileController (:17) sets only a title. None has any abort_403 or permission check (grep empty). Routes at web.php:351-359. ProductController.php:38 and ProductResourceController gate on products.
- Намерено от: expenses-products-contracts

### F191 · средна · няма проверка на сървъра

**No Anagami Finance View report controller checks the 'reports' module or the report's underlying module**

- Елемент: `routes/web.php:875` – показва се при: Routes task-report*, time-log-report*, time-log-consolidated, project-wise-timelog, finance-report*, income-expense-report, leave-report*, attendance-report, expense-report*, deal-report/lead-report*, sales-report (web.php:875-917) have no route middleware; only the sidebar checks the modules.
- Води към: /account/time-log-report, /account/leave-report/leave-quota, /account/attendance-report, /account/finance-report, /account/sales-report, ...
- Проверка/отказ: Missing. The constructors only call parent::__construct(): TaskReportController.php:22, TimelogReportController.php:23, FinanceReportController.php:40, IncomeVsExpenseReportController.php:16, LeaveReportController.php:18, AttendanceReportController.php:11, ExpenseReportController.php:19, SalesReportController.php:13, LeadReportController.php:29. The index actions check only the view_*_report permission.
- Сценарий: Case 1: Default package (no timelogs or leaves), admin opens /account/time-log-report or /account/leave-report/leave-quota by URL. Case 2: the admin switches 'reports' off for a role that holds report permissions. Visible path: the Finance dashboard card (see the finance.blade.php:44 finding) opens the Income vs Expense report even when expenses or reports are off.
- Ефект: Reports of modules the package does not include, or that are switched off for the role, render normally.
- Поправка: Add constructor middleware to each report controller: abort_403(!in_array('reports', $this->user->modules) || !in_array('<report module>', $this->user->modules)).
- Проверка от втори агент: The report controllers (TaskReport, TimelogReport, FinanceReport, IncomeVsExpenseReport, LeaveReport, AttendanceReport, ExpenseReport, SalesReport, LeadReport) contain no module check (grep shows only lang keys). Routes web.php:875-917 have no module middleware. Only the sidebar checks modules.
- Намерено от: crm-reports

### F194 · средна · няма проверка на сървъра

**Notice board, client notes and lead/deal sub-controllers have no module check**

- Елемент: `app/Http/Controllers/NoticeController.php:23` – показва се при: Routes notices.* and client-notes.*, plus lead-notes, deal-notes, lead-files, deal emails and lead-category; all of them are reachable by URL
- Води към: /account/notices, /account/client-notes, lead/deal notes, files and emails
- Проверка/отказ: Missing module gate. NoticeController.php:23-27 (constructor without middleware; actions check only view_notice/add_notice); ClientNoteController.php:19-26 (empty middleware, no 'clients' check); LeadNoteController.php:17, DealNoteController.php:16, LeadFileController.php:22, DealEmailController.php:15 (permissions only, no 'leads' check). By contrast, Ticket, Deal, LeadContact, EventCalendar, KnowledgeBase and Message controllers do check the module.
- Сценарий: Case 1: the admin switches 'notices' off for the client or employee role (notices is in CLIENT_MODULES). The role keeps view_notice = owned (the default) and opens /account/notices, or follows a 'new notice' notification. Case 2: clients switched off for an employee role while it still opens /account/client-notes.
- Ефект: Pages of switched-off modules keep working.
- Поправка: Add constructor middleware abort_403(!in_array('notices'|'clients'|'leads', $this->user->modules)) to the respective controllers.
- Проверка от втори агент: NoticeController, NoticeFileController, ClientNoteController (empty middleware :23-25), LeadNoteController, DealNoteController, LeadFileController, DealEmailController and LeadCategoryController contain no module check. TicketController and DealController do have one.
- Намерено от: crm-reports

### F195 · средна · няма проверка на сървъра

**My Calendar (config-hidden) returns events, tickets, leaves and lead follow-ups with no module checks**

- Елемент: `app/Http/Controllers/MyCalendarController.php:34` – показва се при: Route my-calendar.index is reachable by URL. When type is empty or 'all', $calendar_type_array = ['task','events','tickets','leaves','follow_ups'] (lines 49-50), and each block (:63, :96, :133, :162, :191) checks only permissions.
- Води към: /account/my-calendar?start=..&end=..
- Проверка/отказ: Missing. The constructor middleware (:26-28) is empty and there is no in_array(<module>, user_modules()) before querying Event, Task, Ticket, Leave and DealFollowUp.
- Сценарий: Default package (no leaves or holidays) or a role with tickets or events switched off. The user opens /account/my-calendar by URL.
- Ефект: Leave, ticket, event and follow-up entries of modules outside the package or switched off are displayed.
- Поправка: Build $calendar_type_array only from types whose module (events, tasks, tickets, leaves, leads) is in user_modules(), and abort_403 when it is empty.
- Проверка от втори агент: MyCalendarController.php:26-28 middleware is empty. :48-50 includes all types for type null/all, and each block (:59, :92, :129, :158, :187) checks only a permission before querying Event, Task, Ticket, Leave and DealFollowUp.
- Намерено от: crm-reports

### F044 · ниска · няма проверка на сървъра

**App settings 'Client sign-up' tab is hidden without the clients module, but the controller serves and saves it anyway**

- Елемент: `resources/views/app-settings/index.blade.php:27` – показва се при: @if(!user()->is_superadmin && in_array('clients', user_modules()))
- Води към: app-settings.index?tab=client-signup-setting and app-settings.update?page=client-signup-setting
- Проверка/отказ: app/Http/Controllers/AppSettingController.php:49-51 (index case) checks only manage_app_setting; the update switch at line ~115 calls updateClientSignupSetting() with no clients-module check
- Сценарий: The clients module is switched off for the admin role; the admin opens /account/settings/app-settings?tab=client-signup-setting directly.
- Ефект: Client sign-up settings of a disabled module can be viewed and changed by URL.
- Поправка: Add abort_403(!in_array('clients', user_modules())) in both branches.
- Проверка от втори агент: app-settings/index.blade.php:27 hides the tab without clients. AppSettingController.php:49-51 (index case) checks only manage_app_setting, and :115-116 calls updateClientSignupSetting() with no module check.
- Намерено от: settings

## G09. Известия (камбанката и имейли по график) (3)

### F016 · висока · скрита функция прозира

**Bell notifications for trial/licence/plan events link to the billing pages, which are hidden and return 404**

- Елемент: `resources/views/notifications/superadmin/trial_license_exp.blade.php:1` – показва се при: :link="(user()->is_superadmin ? route('superadmin.super_admin_dashboard') : route('billing.index'))". The same link is in trial_license_exp_pre.blade.php:1, license_expire.blade.php:1, license_expire_pre.blade.php:1, company_updated_plan.blade.php:1, company_renewed_plan.blade.php:1 and offline_package_change_confirmation.blade.php:10. _notification_item.blade.php:17-23 picks these superadmin views for company users when isWorksuiteSaas().
- Води към: billing.index (/account/settings/billing)
- Проверка/отказ: routes/SuperAdmin/web.php:186 group middleware HideBillingPages (contains billing.index at :245); app/Http/Middleware/Databackd/HideBillingPages.php:19-21 abort(404) while config/databackd.php:77 hidden.pages.billing = true
- Сценарий: Every trial company: the daily trial-expire cron (app/Console/Kernel.php:179) notifies the first admin with TrialLicenseExpPre (TrialExpire.php:125) before expiry and TrialLicenseExp (TrialExpire.php:70) when moving the company to Default. Paid packages get LicenseExpire(Pre) from the licence-expire cron (LicenceExpire.php:76,96).
- Ефект: The company admin clicks the trial/licence notification in the bell and lands on a 404.
- Поправка: In each of these views: :link="user()->is_superadmin ? route('superadmin.super_admin_dashboard') : (databackd_hidden('pages', 'billing') ? route('dashboard') : route('billing.index'))"
- Проверка от втори агент: notifications/superadmin/trial_license_exp.blade.php:1 (and the sibling views) link non-superadmins to route('billing.index'). _notification_item.blade.php:17-22 picks these superadmin views for company users. billing.index is in the HideBillingPages group (routes/SuperAdmin/web.php:186-245), and HideBillingPages.php:19-21 abort(404) while config/databackd.php:77 pages.billing = true. TrialExpire.php:70,125 and LicenceExpire.php:76,96 notify company users via 'database' (TrialLicenseExp.php:29).
- Намерено от: topbar-global, databackd-analytics-planning-teams

### F017 · средна · мъртва връзка (403/404)

**Notification bell renders and links every unread notification without checking whether the target module is still active for the receiver**

- Елемент: `resources/views/notifications/_notification_item.blade.php:38` – показва се при: Every row of $user->unreadNotifications is rendered (user_notifications.blade.php:1, all_user_notifications.blade.php:10) and the view's route() is used as is. Example: client/new_payment.blade.php:1 :link="route('payments.show', ...)"; also all/leave_*.blade.php:6 leaves.show, all/new_order.blade.php:6 orders.show, all/new_user_via_link.blade.php:1 employees.show, client/new_client_task.blade.php:1 tasks.show, databackd/notifications/item.blade.php:2 data['url'] (teams / alerts). The notification classes store to 'database' regardless of the receiver's modules (e.g. NewTask::via line 40).
- Води към: payments.show, tasks.show, leaves.show, orders.show, employees.show, databackd.teams.requests.show, databackd.gaps.alerts.history, ...
- Проверка/отказ: PaymentController.php:34, TaskController.php:60, LeaveController.php:40, OrderController.php:51, EmployeeController.php:77 (abort_403 if the module is not in $this->user->modules); RequireModule.php:23-37 for AFV routes
- Сценарий: (a) Admin switches Payments or Tasks off for the client role: PaymentObserver.php:47-59 still sends NewPayment to the client, and TaskObserver.php:119-120 / :310-311 still send NewClientTask / TaskCompletedClient (gated only by allow_client_notification). (b) TrialExpire.php:62-64 moves a trial company to Default, which deactivates employees/leaves/orders/timelogs/holidays (CompanyObserver::updateModuleSettings). Unread leave/order/employee notifications from the trial stay in the bell. (c) Package changed to Стартов: unread FinAlertNotification links to alerts history, which now 403s.
- Ефект: The unread badge counts them, and clicking the item in the bell or on the All notifications page gives a 403.
- Поправка: In _notification_item.blade.php map the notification type to its module and render notification_fallback (no link) when the module is not in user_modules(). Apply the same filter to $unreadNotificationCount (AccountBaseController.php:123).
- Проверка от втори агент: _notification_item.blade.php:38-55 renders every unread notification with the link from its view, e.g. notifications/client/new_payment.blade.php:1 -> payments.show. PaymentObserver.php:46-58 notifies the client with no module check, and NewPayment::via (:38) always includes 'database'. PaymentController and the other targets abort_403 when the module is off. AccountBaseController.php:123 counts all unread notifications.
- Намерено от: topbar-global

### F207 · ниска · няма проверка на сървъра

**Scheduled saved-report e-mails keep going after databackd_reports is switched off for the creator's role**

- Елемент: `app/Console/Commands/Databackd/SendSavedReports.php:84` – показва се при: Checks only !databackd_company_module((int)$companyId, 'databackd_reports'), i.e. the admin-type module_settings row of the company (databackd_modules.php databackd_company_module).
- Води към: databackd:send-reports e-mail with the PDF and an "open" link to databackd.reports.saved.open (app/Mail/Databackd/SavedReportMail.php:37-38)
- Проверка/отказ: Missing: the creator's role module (employee row) is never checked; the open link then hits RequireModule:databackd_reports (routes/databackd/reports-saved.php:7) -> 403 for that employee
- Сценарий: An employee schedules a saved report; later the admin switches databackd_reports off for employees.
- Ефект: The employee (and the recipients) keep receiving report PDFs from a module that is off for the role; the "open" link in the e-mail gives a 403 page.
- Поправка: Before sending, skip the report when the creator's role type has databackd_reports inactive in module_settings.
- Проверка от втори агент: SendSavedReports.php:84 checks only databackd_company_module($companyId, 'databackd_reports'), the admin row (databackd_modules.php databackd_company_module). The creator's role is never checked. SavedReportMail.php:37-38 adds an open link that RequireModule would 403 for that employee.
- Намерено от: databackd-data-reports

## G10. Настройки, които изброяват модули извън пакета (5)

### F030 · средна · модул извън пакета се вижда

**Notification settings list email/Slack/push toggles for every module, including modules not in the package and config-hidden features**

- Елемент: `resources/views/notification-settings/ajax/email-setting.blade.php:167` – показва се при: @foreach ($emailSettings as $emailSetting), where $emailSettings = EmailNotificationSetting::all() (app/Http/Controllers/NotificationSettingController.php:33) with no module filter. The same loop is in slack-setting.blade.php:38 and push-notification-setting.blade.php:72.
- Води към: notifications.index (email / slack / push tabs) and the matching update
- Проверка/отказ: app/Http/Controllers/NotificationSettingController.php:23 checks only manage_notification_setting; no user_modules() filter anywhere
- Сценарий: Package 'Default' (no employees/leaves/holidays/orders): the admin opens Settings -> Notification settings and sees 'New Leave Application', 'Holiday Notification', 'Order Create/Update Notification', 'Birthday notification' and 'Employee Appreciation' (app/Models/EmailNotificationSetting.php:78,205,134,184,198). On every package it also shows the config-hidden tickets/leads/estimates/events/notices/messages notifications.
- Ефект: Toggles for modules the company does not have are visible and editable.
- Поправка: Filter $emailSettings in NotificationSettingController::index with a slug => module map against user_modules() (and databackd_hidden for the hidden menus).
- Проверка от втори агент: NotificationSettingController.php:33 $this->emailSettings = EmailNotificationSetting::all() with no module filter. email-setting.blade.php:167-174 lists every setting (e.g. 'New Leave Application', EmailNotificationSetting.php:78). The middleware (:23) checks only manage_notification_setting.
- Намерено от: settings

### F031 · средна · модул извън пакета се вижда

**Custom fields 'Module' selector offers every custom-field group, including modules not in the package (Employee, Order, Time Log...) and hidden ones**

- Елемент: `resources/views/custom-fields/create-custom-field-modal.blade.php:14` – показва се при: @foreach ($customFieldGroups as $item), where $customFieldGroups = CustomFieldGroup::all() (app/Http/Controllers/CustomFieldController.php:50)
- Води към: custom-fields.create / custom-fields.store
- Проверка/отказ: app/Http/Controllers/CustomFieldController.php:21 checks only manage_custom_field_setting; no module filter in create(), store() or index() (grouped list line 34-37)
- Сценарий: Package 'Default': the admin opens Settings -> Custom fields -> Add field; the Module select lists Employee, Order and Time Log (app/Models/CustomFieldGroup.php:35,44,47). On all packages it also lists the config-hidden Lead, Deal, Proposal, Estimate, Ticket and Event groups.
- Ефект: Admins can create custom fields for modules they don't have; the list advertises modules that are not in the package.
- Поправка: Filter CustomFieldGroup by a group-name => module map against user_modules() in create() and index(), and reject other groups in StoreCustomField.
- Проверка от втори агент: CustomFieldController.php:50 $this->customFieldGroups = CustomFieldGroup::all(). create-custom-field-modal.blade.php:13-15 lists every group. The middleware (:21) checks only manage_custom_field_setting.
- Намерено от: settings

### F040 · ниска · скрита функция прозира

**Module settings page lists toggles for config-hidden modules (leads, leaves, tickets, events, messages, notices, knowledge base, estimates, orders, attendance, holidays)**

- Елемент: `resources/views/module-settings/ajax/modules.blade.php:3` – показва се при: @foreach ($modulesData as $setting), where $modulesData = ModuleSetting::where('module_name','<>','settings')->where('module_name','not like','databackd\_%')->where('is_allowed',1) (app/Http/Controllers/ModuleSettingController.php:29)
- Води към: module-settings.index (admin/employee/client tabs), module-settings.update
- Проверка/отказ: The only filters are is_allowed and the settings/databackd_* exclusion. config/databackd.php:14-38 hides these modules' menus but is not consulted.
- Сценарий: An admin on Trial/Starter/Демо opens Settings -> Module settings.
- Ефект: Switches for features the product hides from everyone are shown. Toggling them has no visible effect, which is confusing.
- Поправка: Exclude module names mapped to databackd_hidden('menu', <key>) for non-superadmins.
- Проверка от втори агент: ModuleSettingController.php:29 filters only is_allowed, 'settings' and databackd_*. config/databackd.php:14-38 hidden menus are not consulted.
- Намерено от: settings

### F041 · ниска · скрита функция прозира

**Role & permissions matrix lists permissions of config-hidden modules and the hidden dashboard tabs (project/client/HR/ticket dashboard)**

- Елемент: `resources/views/role-permissions/ajax/permissions.blade.php:12` – показва се при: @foreach ($modulesData as $moduleData), where modulesData = Module::...->whereNotIn('module_name', $disabledModulesNames) (app/Http/Controllers/RolePermissionController.php:151,156). It filters only is_allowed=0; 'dashboards' is always enabled (line 168) and its custom permissions include view_project/client/hr/ticket_dashboard (app/Models/Module.php:1660-1690).
- Води към: role-permissions.permissions / role-permissions.custom_permissions
- Проверка/отказ: config/databackd.php:41-46 hides dashboard_tabs project/client/hr/ticket and :14-38 hides the leads/leaves/tickets/... menus; the permission list ignores both. view_hr_dashboard is listed even on Default, which has no employees module.
- Сценарий: An admin on any package opens Settings -> Roles & permissions -> Employee role.
- Ефект: Permissions for hidden features (and for the HR dashboard on packages without employees) are visible and editable.
- Поправка: In permissions()/customPermissions() skip modules whose menu is databackd_hidden, and skip dashboard permissions whose tab is hidden or whose module is off.
- Проверка от втори агент: RolePermissionController.php:146-158 filters modules only by is_allowed=0. :162-169 always add 'dashboards', whose custom permissions include view_project/client/hr/ticket_dashboard. Nothing checks databackd_hidden('dashboard_tabs' or 'menu').
- Намерено от: settings

### F198 · ниска · модул извън пакета се вижда

**Notification settings list email notifications of modules outside the package or config-hidden (leaves, leads, tickets, events, notices, ...)**

- Елемент: `resources/views/notification-settings/ajax/email-setting.blade.php:166` – показва се при: `@foreach ($emailSettings as $emailSetting)` where app/Http/Controllers/NotificationSettingController.php:33 loads `EmailNotificationSetting::all()` unfiltered (e.g. 'New Leave Application', 'Lead notification', 'New Notice Published', 'Event Notification': app/Models/EmailNotificationSetting.php:78, :125, :170, :219)
- Води към: Settings -> Notification settings
- Проверка/отказ: There is no module or databackd_hidden filter.
- Сценарий: Default package (no leaves) or any company where leads, tickets, events and notices are config-hidden. Admin opens Settings -> Notification settings.
- Ефект: Toggles for notifications of modules the company does not have or cannot see.
- Поправка: Map each setting slug to its module and filter $emailSettings by user_modules() and databackd_hidden('menu', ...).
- Проверка от втори агент: NotificationSettingController.php:32 loads EmailNotificationSetting::all(), and notification-settings/ajax/email-setting.blade.php:166 lists them all with no module or hidden filter.
- Намерено от: crm-reports

## G11. AFV модули (данни, справки, анализи, планиране, екипи) (22)

### F199 · висока · мъртва връзка (403/404)

**Chart of accounts page shows the "Chart template" button to employees, but the setup wizard it opens is admin-only (403)**

- Елемент: `resources/views/databackd/data/accounts/index.blade.php:45` – показва се при: @if ($chartTemplateUrl) with FinAccountController.php:75-76: $this->chartTemplateUrl = \Route::has('databackd.gaps.onboarding.index') ? route('databackd.gaps.onboarding.index', ['step' => 3]) : null; (no role check; the page itself only blocks clients, FinAccountController.php:48)
- Води към: databackd.gaps.onboarding.index?step=3 (/account/databackd/gaps/onboarding)
- Проверка/отказ: app/Http/Controllers/Databackd/Gaps/Onboarding/OnboardingController.php:37 abort_403(!in_array('admin', user_roles()))
- Сценарий: Any package that includes databackd_data (Default, Trial, Starter, Демо, Стартов), employee role with databackd_data active (the default). The employee opens Financial analysis -> Chart of accounts and clicks "Chart template".
- Ефект: An employee sees the button on the main Chart of accounts screen; clicking it gives a 403 page.
- Поправка: $this->chartTemplateUrl = \Route::has('databackd.gaps.onboarding.index') && in_array('admin', user_roles()) ? route('databackd.gaps.onboarding.index', ['step' => 3]) : null;
- Проверка от втори агент: FinAccountController.php:75-76 sets chartTemplateUrl by Route::has only. The page blocks only clients (:48), and accounts/index.blade.php:45-48 shows the button. OnboardingController.php:37 aborts unless admin. FinDashboardController.php:59 already adds in_array('admin', user_roles()).
- Намерено от: databackd-data-reports

### F208 · висока · мъртва връзка (403/404)

**Teams analysis "Open link" result points to staff-only report/analytics/planning screens, so the client gets 403**

- Елемент: `resources/views/databackd/teams/analysis/show.blade.php:112` – показва се при: @if ($safeLink) (shown to client and staff). The link is set from the staff select deliverable_route, whose options are AnalysisRequestController::availableLinkTargets() (AnalysisRequestController.php:533-543, Route::has only) from LINK_TARGETS (:32-43: reports pnl/cashflow/dashboard/saved, analytics ratios/comparison/consolidation, planning forecasts/budgets/variance), saved at :313-317.
- Води към: databackd.reports.pnl.index, .cashflow.index, .dashboard.index, .saved.index, databackd.analytics.*, databackd.planning.*
- Проверка/отказ: RequireModule on every target route (e.g. routes/databackd/reports-pnl.php:7). The client role has no databackd_reports/analytics/planning module rows (databackd_modules.php catalog), and each controller also does abort_403(in_array('client', user_roles())) (e.g. PnlReportController.php:22).
- Сценарий: Any company with databackd_teams. Staff deliver a client's analysis request by choosing "P&L" (or any other target) in the result select. For package "Стартов", staff are also offered the ratios/consolidation/budgets/forecasts/variance targets of modules not in the package.
- Ефект: The client opens their delivered analysis, clicks "Open link" and always gets a 403 page. On Стартов, staff can pick and open analytics/planning targets that also 403.
- Поправка: Remove the internal screens from the client-facing deliverable select (or offer them only for staff-internal notes), and filter LINK_TARGETS by the target module with databackd_module().
- Проверка от втори агент: AnalysisRequestController.php:32-43 LINK_TARGETS lists only internal reports, analytics and planning screens, and availableLinkTargets() (:531-542) checks only Route::has. store saves the chosen URL (:313-317), and teams/analysis/show.blade.php:112-115 shows 'Open link' to everyone. Every request has a client (store :174). The client has only the databackd_teams row (databackd_modules.php:20-25), so RequireModule returns 403.
- Намерено от: databackd-data-reports

### F210 · висока · мъртва връзка (403/404)

**Analysis request result 'Open report' button leads the client to AFV screens the client role can never open (403)**

- Елемент: `resources/views/databackd/teams/analysis/show.blade.php:112` – показва се при: @if ($safeLink) <a href="{{ $safeLink }}" ...>open_link</a> - shown to everyone, including the client. $safeLink is set from deliverable_link in AnalysisRequestController.php:262-263, and staff fill that link from the 'Saved report or forecast' select, which offers availableLinkTargets() (AnalysisRequestController.php:32-43, 532-543; only Route::has is checked).
- Води към: databackd.reports.saved/pnl/cashflow/dashboard.index, databackd.analytics.ratios/comparison/consolidation.index, databackd.planning.forecasts/budgets/variance.index
- Проверка/отказ: app/Http/Middleware/Databackd/RequireModule.php:22-29: databackd_module() is false for the client, because the client gets a module_settings row only for databackd_teams (app/Helper/databackd_modules.php:20-25), so the answer is 403 permissionDenied. The controllers also abort_403 for the client, e.g. app/Http/Controllers/Databackd/Reports/SavedReportController.php:36 and Analytics/RatioDashboardController.php:53.
- Сценарий: Any package and company. Staff deliver an analysis request and pick e.g. 'Saved reports' or 'P&L' in the result form. The client (role client, which has only Teams) opens the request and clicks 'Open report'.
- Ефект: The client, who is the person the result is for, gets a 403 page in a new tab instead of the report.
- Поправка: Same as F208: do not offer internal report, analytics or planning screens as a client deliverable link (the file download remains).
- Проверка от втори агент: Duplicate of F208. teams/analysis/show.blade.php:112 shows $safeLink to the client, and the link comes from the staff select (AnalysisRequestController.php:260, :313-317) over internal screens. RequireModule.php:22-29 returns 403 for the client, which has only databackd_teams.
- Намерено от: databackd-analytics-planning-teams

### F008 · средна · модул извън пакета се вижда

**"Margins" (invoice revenue by product) shown and working when invoices/products modules are off**

- Елемент: `resources/views/databackd/menu/25-gaps-margins.blade.php:1` – показва се при: databackd_module('databackd_reports') && (in_array('admin', user_roles()) || (user()->permission('view_invoices') === 'all' && user()->permission('view_product') === 'all'))
- Води към: databackd.gaps.margins.index (MarginReportController@index)
- Проверка/отказ: app/Http/Controllers/Databackd/Gaps/Margins/MarginReportController.php:21-44 checks only client role and permissions, not the invoices/products modules; the data comes from invoices (app/Services/Databackd/Gaps/Margins/MarginReport.php:14-18). Product links in resources/views/databackd/gaps/margins/index.blade.php:30-39 go to products.edit/show, which 403 when products is off (app/Http/Controllers/ProductController.php:38)
- Сценарий: Employee role with view_invoices=all and view_product=all, after the company admin switches 'invoices' or 'products' off for employees
- Ефект: The employee still sees Reports > Margins with invoice revenue and product costs from a switched-off module; the product links in the warnings give 403
- Поправка: Add && in_array('invoices', user_modules()) && in_array('products', user_modules()) to the menu file and to MarginReportController::canViewReport() (admin included). Set $canView/$canEdit* to false when products is not in user_modules().
- Проверка от втори агент: resources/views/databackd/menu/25-gaps-margins.blade.php:1 and MarginReportController.php:26-45 check only the role, view_invoices and view_product. Neither checks the invoices or products module, and routes/databackd/gaps-margins.php:8 applies only RequireModule:databackd_reports. The product links (margins/index.blade.php:29-39) go to products.edit/show, which ProductController.php:38 blocks with 403 when products is off for the role.
- Намерено от: sidebar

### F012 · средна · мъртва връзка (403/404)

**Teams analysis "Open link" result goes to AFV screens the client (and packages without the module) cannot open**

- Елемент: `resources/views/databackd/teams/analysis/show.blade.php:113` – показва се при: @if ($safeLink) <a href="{{ $safeLink }}">open_link</a> - shown to the client too; staff pick the link from $linkTargets (show.blade.php:135-140)
- Води към: databackd.reports.pnl/cashflow/dashboard/saved, analytics.ratios/comparison/consolidation, planning.forecasts/budgets/variance
- Проверка/отказ: app/Http/Controllers/Databackd/Teams/AnalysisRequestController.php:32-43 LINK_TARGETS and :532-543 availableLinkTargets() filter only by Route::has. The targets have RequireModule (e.g. routes/databackd/reports-pnl.php:7), which the client fails (client gets only databackd_teams), plus abort_403(in_array('client', user_roles())) in each controller (e.g. Reports/PnlReportController.php:22)
- Сценарий: Staff deliver an analysis with target "P&L" or "Ratios"; the client opens the analysis and clicks "Open link". On package Стартов (no analytics/planning) the ratios/forecasts/budgets targets are offered and 403 for staff too
- Ефект: A 403 page for the requesting client; module screens not in the package are offered as targets
- Поправка: Filter availableLinkTargets() by the target route's AFV module (databackd_module()). In show.blade.php render the link for a client only when it is not an internal /account/databackd path the client cannot open (e.g. only external http(s) URLs for clients).
- Проверка от втори агент: AnalysisRequestController.php:260-263 computes $safeLink for everyone (clients included); $linkTargets is empty only for clients. show.blade.php:112-116 renders 'Open link' to the client. availableLinkTargets() (:532-543) filters by Route::has only. The targets have RequireModule (e.g. routes/databackd/reports-pnl.php:7), which the client role fails, and abort_403(in_array('client', user_roles())) (Reports/PnlReportController.php:22). On Стартов the analytics/planning targets also fail RequireModule for staff.
- Намерено от: sidebar

### F013 · средна · мъртва връзка (403/404)

**Team request page shows the project link to every employee; ProjectController denies non-members and users with projects off**

- Елемент: `resources/views/databackd/teams/requests/show.blade.php:172` – показва се при: @if ($r->project_id && $r->project) ... @if ($canManage || !$isClient) <a href="{{ route('projects.show', $r->project_id) }}">
- Води към: projects.show
- Проверка/отказ: app/Http/Controllers/ProjectController.php:78 abort_403(!in_array('projects', $this->user->modules)) and :913-921 (view_projects 'owned' requires project membership). Employees see all team requests (TeamRequestController.php:438-447), but createProject adds only specialist users as members (:353-356)
- Сценарий: Default employee (view_projects=OWNED) who is not a specialist on the request, or any employee with projects switched off for the employee role
- Ефект: The project name link on the team request page leads to 403
- Поправка: show.blade.php:171: @if ($canManage || (!$isClient && in_array('projects', user_modules()) && (user()->permission('view_projects') == 'all' || $r->project->members->pluck('user_id')->contains(user()->id)))) link, else plain text.
- Проверка от втори агент: teams/requests/show.blade.php:171-172 links projects.show for every non-client. TeamRequestController::scopedQuery (:438-447) gives employees all requests, and show() (:165) has no further restriction. ProjectController.php:77 checks the projects module, and :913-921 deny view_projects=owned non-members of a non-public project. createProject sets public=0 (:346) and adds only specialist users (:353-356).
- Намерено от: sidebar

### F020 · средна · мъртва връзка (403/404)

**Breadcrumbs on every AFV page link to /account/databackd and /account/databackd/<group>, which have no route (404)**

- Елемент: `resources/views/components/app-title.blade.php:38` – показва се при: Every URL segment except 'account', the last one and NonClickableSegments gets <a href="{{ $segmentLink }}"> (lines 17-40); the component is rendered in topbar.blade.php:7 and layouts/app.blade.php:207
- Води към: /account/databackd, /account/databackd/data, /reports, /analytics, /planning, /teams, /gaps
- Проверка/отказ: No route exists: RouteServiceProvider.php:86-97 mounts routes/databackd/*.php under 'account', and those files only register deeper prefixes (e.g. databackd/reports/pnl, databackd/teams/requests). There is no fallback route.
- Сценарий: Any user (admin, employee, client on Teams) on any financial-analysis page, e.g. /account/databackd/reports/pnl or /account/databackd/teams/requests/5
- Ефект: Clicking the "AFV" or group crumb gives a 404.
- Поправка: Add cases 'AFV', 'data', 'reports', 'analytics', 'planning', 'teams' and 'gaps' to App\Enums\NonClickableSegments, or skip the <a> when no route matches $link.
- Проверка от втори агент: components/app-title.blade.php:17-40 links every middle URL segment that is not in NonClickableSegments (app/Enums/NonClickableSegments.php:9-15 has no AFV segments). The breadcrumb is rendered on every page (topbar.blade.php:7, layouts/app.blade.php:207). routes/databackd/*.php register only deeper prefixes (databackd/reports/pnl, databackd/teams/requests, ...). No route exists for /account/databackd or /account/databackd/<group>, and there is no fallback route, so the crumbs give 404.
- Намерено от: topbar-global

### F176 · средна · мъртва връзка (403/404)

**Margins report links to products.edit/show and shows product data without checking the products module**

- Елемент: `resources/views/databackd/gaps/margins/index.blade.php:36` – показва се при: $productUrl returns route('products.edit') if edit_product all/own, else route('products.show') if $canView (MarginReportController.php:63-67, permissions only); the menu resources/views/databackd/menu/25-gaps-margins.blade.php:1 checks databackd_reports + permissions only
- Води към: products.edit / products.show (ProductController)
- Проверка/отказ: app/Http/Controllers/ProductController.php:38 abort_403(!in_array('products', $this->user->modules)) for non-clients
- Сценарий: The company admin switches products off for the admin (or employee) role while databackd_reports stays on. The Margins report still lists products and their cost prices, and clicking a product in the 'missing cost price' warnings gives 403.
- Ефект: A 403 page from the report links, plus product data shown for a module that is off
- Поправка: Set the product link flags only when in_array('products', user_modules()), and gate the report and menu on the invoices and products modules (see F202).
- Проверка от втори агент: MarginReportController.php:61-66 sets canEditAll/canEditOwn/canView from permissions only. gaps/margins/index.blade.php:30-39 builds products.edit/products.show links. ProductController.php:38 aborts without products. The menu (25-gaps-margins.blade.php:1) checks only databackd_reports and permissions. Duplicate of F202.
- Намерено от: expenses-products-contracts

### F200 · средна · мъртва връзка (403/404)

**P&L amounts link to Financial entries even when the databackd_data module is off for the user**

- Елемент: `resources/views/databackd/reports/pnl/index.blade.php:153` – показва се при: @if ($link) where $link = $entryLink($row) (pnl/index.blade.php:17-18: $entriesUrl && $row['account_ids']) and PnlReportController.php:34: $this->entriesUrl = \Route::has('databackd.data.entries.index') ? route('databackd.data.entries.index') : null; (no databackd_module check). The note at pnl/index.blade.php:192-193 is gated the same way.
- Води към: databackd.data.entries.index?from=..&to=..&account_id=..
- Проверка/отказ: routes/databackd/data-entries.php:7 RequireModule:databackd_data -> RequireModule.php:24-31 abort 403
- Сценарий: The company admin switches databackd_data off for the employee role in Settings -> Module settings and keeps databackd_reports on (employees only read reports). The employee opens Reports -> P&L and clicks an amount.
- Ефект: Every P&L row amount is shown as a link with the title "open entries"; clicking it gives a 403 page.
- Поправка: $this->entriesUrl = \Route::has('databackd.data.entries.index') && databackd_module('databackd_data') ? route('databackd.data.entries.index') : null;
- Проверка от втори агент: PnlReportController.php:34 sets entriesUrl by Route::has only. pnl/index.blade.php:17-18 and :153-154 link the amounts. routes/databackd/data-entries.php:7 applies RequireModule:databackd_data, which returns 403 (RequireModule.php:24-38). FinDashboardController.php:58 adds databackd_module('databackd_data').
- Намерено от: databackd-data-reports

### F201 · средна · мъртва връзка (403/404)

**Data sources screens (databackd_integrations) link to import, import history and entries screens (databackd_data) with only a Route::has check**

- Елемент: `resources/views/databackd/data/sources/index.blade.php:78` – показва се при: @if (Route::has('databackd.data.import.index')) ("Upload file" button). Same pattern at sources/index.blade.php:146 (last batch -> databackd.data.batches.show), sources/result.blade.php:38 (errors export), :72 (history -> batches.show) and :77 (entries).
- Води към: databackd.data.import.index, databackd.data.batches.show, databackd.data.batches.errors-export, databackd.data.entries.index
- Проверка/отказ: routes/databackd/data-import.php:10, routes/databackd/data-batches.php:7, routes/databackd/data-entries.php:7: RequireModule:databackd_data (403). The sources routes themselves use databackd_integrations (routes/databackd/data-sources.php:7).
- Сценарий: databackd_integrations active and databackd_data off for the role (Module settings toggle for employees). IntegrationController.php:67-74/112-131 explicitly supports this combination by showing locked cards. The employee opens Data sources, imports a sheet, and clicks "Upload file", the batch link, "History" or "Entries".
- Ефект: Visible buttons and links on the Data sources page and the import result page lead to 403 pages.
- Поправка: Use Route::has(...) && databackd_module('databackd_data') in these conditions (or pass flags from the controller), and fall back to the sources.result link for the batch.
- Проверка от втори агент: sources/index.blade.php:78 and :146, and sources/result.blade.php:38, :72 and :77 check only Route::has. The targets use RequireModule:databackd_data (data-import.php:10, data-batches.php:7, data-entries.php:7), while the sources routes use databackd_integrations (data-sources.php:7). IntegrationController.php:67-74 supports the integrations-without-data combination.
- Намерено от: databackd-data-reports, databackd-analytics-planning-teams

### F202 · средна · модул извън пакета се вижда

**Margins report ignores the Anagami Finance View invoices and products modules, and its product links 403 when products is off**

- Елемент: `resources/views/databackd/menu/25-gaps-margins.blade.php:1` – показва се при: @if (databackd_module('databackd_reports') && (in_array('admin', user_roles()) || (user()->permission('view_invoices') === 'all' && user()->permission('view_product') === 'all'))); product links at gaps/margins/index.blade.php:30-39 (route('products.edit'/'products.show') by permission only), used at :102, :239, :263
- Води към: databackd.gaps.margins.index; products.edit / products.show
- Проверка/отказ: MarginReportController.php:30/37-44 checks only role and permissions, with no in_array('invoices'/'products', user_modules()); ProductController.php:38 abort_403(!in_array('products', $this->user->modules)) for non-clients
- Сценарий: The company admin switches the products (or invoices) module off for employees in Module settings; an employee keeps view_invoices=all and view_product=all (permissions do not change with module status, User::permission() reads only user_permissions). The same applies to admins if products/invoices is switched off for the admin role.
- Ефект: "Margins by products and services" stays in the sidebar and shows invoice revenue, credit notes, product cost prices and client names from a module that is off. Clicking a product name, the "set cost" chip or the "no cost" badge gives a 403 page.
- Поправка: Add in_array('invoices', user_modules()) && in_array('products', user_modules()) to canViewReport() (admin included) and to the menu condition, and build $productUrl only with the products module.
- Проверка от втори агент: menu/25-gaps-margins.blade.php:1 and MarginReportController.php:37-44 canViewReport() check role and permissions only, with no invoices or products module check. The product links at gaps/margins/index.blade.php:30-39 lead to ProductController.php:38, which returns 403.
- Намерено от: databackd-data-reports

### F203 · средна · няма проверка на сървъра

**"Sync from invoices and expenses" card and page work without checking the invoices/expenses modules (or view_invoices/view_expenses)**

- Елемент: `resources/views/databackd/data/integrations/index.blade.php:78` – показва се при: @if ($hasSync), where IntegrationController.php:35: $this->hasSync = Route::has('databackd.data.sync.run') && databackd_module('databackd_data'); the preview button is at integrations/index.blade.php:93-94
- Води към: databackd.data.sync.index / databackd.data.sync.run
- Проверка/отказ: Missing: FinSyncController.php:29 only abort_403(in_array('client', user_roles())); routes/databackd/data-sync.php:7 only RequireModule:databackd_data; InvoiceExpenseSync (plan() at :363-460) reads Invoice/Expense withoutGlobalScopes with no module or permission check
- Сценарий: invoices and/or expenses switched off for employees in Module settings while databackd_data and databackd_integrations stay on (all real packages include both Anagami Finance View modules, so this needs a role toggle).
- Ефект: The employee still sees the sync card on Integrations. The sync page (data/sync/index.blade.php:100-125) shows invoice and expense counts and revenue/expense totals, and the employee can run the sync that copies those documents into financial entries.
- Поправка: Add in_array('invoices', user_modules()) || in_array('expenses', user_modules()) to hasSync and to a FinSyncController middleware abort_403, and make InvoiceExpenseSync skip whichever source module is inactive.
- Проверка от втори агент: IntegrationController.php:35 hasSync requires only databackd_data. FinSyncController.php:24-31 blocks only clients, and index() (:37-45) runs InvoiceExpenseSync->preview() with no invoices or expenses module or permission check.
- Намерено от: databackd-data-reports

### F204 · средна · модул извън пакета се вижда

**Statutory statements offer consolidation groups and consolidated statements without the databackd_analytics module**

- Елемент: `resources/views/databackd/gaps/statutory/index.blade.php:71` – показва се при: @if (!empty($scopes['groups'])) <optgroup ... consolidation groups>, fed by StatutoryReport::scopes() (app/Services/Databackd/Gaps/Statutory/StatutoryReport.php:94-96), which lists every FinConsolidationGroup of the company. parseScope() falls back to the first group (StatutoryReport.php:127-128), and the report runs ConsolidationService (StatutoryReport.php:507).
- Води към: databackd.gaps.statutory.index?scope=group:<id> (+ excel/pdf/print)
- Проверка/отказ: Missing: no databackd_module('databackd_analytics') check in StatutoryController or StatutoryReport. Consolidation groups/rules belong to databackd_analytics (routes/databackd/analytics-consolidation*.php RequireModule:databackd_analytics).
- Сценарий: Company on package "Стартов" (no databackd_analytics) that created consolidation groups while on Trial/Демо; or databackd_analytics switched off for employees while admins keep it.
- Ефект: Users without the analytics module see the consolidation groups in the scope select and get consolidated balance sheets and income statements (screen, PDF, Excel, print). These come from a module that is not in their package or role.
- Поправка: Include and accept group scopes only when databackd_module('databackd_analytics') (pass a flag from StatutoryController into scopes()/parseScope()).
- Проверка от втори агент: StatutoryReport.php:94-96 scopes() lists every FinConsolidationGroup. parseScope() (:116-117, :128-129) accepts or falls back to groups, and :506-508 runs ConsolidationService. The route requires only databackd_reports (gaps-statutory.php:8), and nothing checks databackd_analytics. The groups optgroup is at statutory/index.blade.php:71.
- Намерено от: databackd-data-reports

### F209 · средна · мъртва връзка (403/404)

**Consolidation screens (databackd_analytics) link to FX rates and Legal entities (databackd_data) without a module check**

- Елемент: `resources/views/databackd/analytics/consolidation/index.blade.php:99` – показва се при: @if ($fxRatesUrl), where ConsolidationController.php:65: Route::has('databackd.gaps.fx-rates.index') ? route(...) : null. Also analytics/consolidation-rules/show.blade.php:67 @if ($entitiesUrl), where ConsolidationGroupController.php:103: \Route::has('databackd.data.entities.index') ? route(...) : null.
- Води към: databackd.gaps.fx-rates.index, databackd.data.entities.index
- Проверка/отказ: routes/databackd/gaps-fx-rates.php:8 and routes/databackd/data-dimensions.php:9 RequireModule:databackd_data (403)
- Сценарий: databackd_analytics on and databackd_data switched off for the role (Module settings toggle, e.g. employees who only analyse).
- Ефект: The FX-rates notice link on Consolidation and the "Manage legal entities" button on a consolidation group lead to 403 pages.
- Поправка: Add && databackd_module('databackd_data') to both URL assignments.
- Проверка от втори агент: ConsolidationController.php:65 fxRatesUrl and ConsolidationGroupController.php:103 entitiesUrl use Route::has only. The targets use RequireModule:databackd_data (gaps-fx-rates.php:9, data-dimensions.php:10). The views show them at analytics/consolidation/index.blade.php:99 and consolidation-rules/show.blade.php:67.
- Намерено от: databackd-data-reports

### F211 · средна · модул извън пакета се вижда

**Analysis result link-target select offers Analytics/Planning screens in packages without those modules**

- Елемент: `resources/views/databackd/teams/analysis/show.blade.php:135` – показва се при: @if ($linkTargets) ... @foreach ($linkTargets as $key => $url) - $linkTargets = availableLinkTargets() for staff (AnalysisRequestController.php:260). It checks only Route::has($routeName) (line 537), with no databackd_module() for each target.
- Води към: databackd.analytics.ratios.index, databackd.analytics.comparison.index, databackd.analytics.consolidation.index, databackd.planning.forecasts.index, databackd.planning.budgets.index, databackd.planning.variance.index
- Проверка/отказ: routes/databackd/analytics-ratios.php:6, analytics-ratio-detail.php:8, analytics-consolidation.php:7 (RequireModule:databackd_analytics) and planning-*.php:7 (RequireModule:databackd_planning). These answer 403 'module not in your package' (RequireModule.php:31-33).
- Сценарий: Company on package 'Стартов' (has databackd_teams, lacks databackd_analytics and databackd_planning). An admin or employee opens an analysis request.
- Ефект: Staff see 'Ratios', 'Comparison', 'Consolidation', 'Forecasts', 'Budgets' and 'Variance' as options. Saving one creates an 'Open report' button that answers 403 'not in your package' for staff, and 403 for the client.
- Поправка: Map each LINK_TARGETS key to its module and keep only keys where databackd_module($module) is true.
- Проверка от втори агент: AnalysisRequestController.php:531-542 availableLinkTargets() checks only Route::has, so on Стартов the ratios, comparison, consolidation, forecasts, budgets and variance targets are offered at teams/analysis/show.blade.php:135-140. Their routes need databackd_analytics/databackd_planning and return 403 'not in package'.
- Намерено от: databackd-analytics-planning-teams

### F212 · средна · мъртва връзка (403/404)

**Team request project link shown to every employee, but projects.show needs project membership (or the projects module)**

- Елемент: `resources/views/databackd/teams/requests/show.blade.php:171` – показва се при: @if ($r->project_id && $r->project) ... @if ($canManage || !$isClient) <a href="{{ route('projects.show', $r->project_id) }}"> - every non-client sees the link. For the admin there are also the 'Open project' / 'Create project' buttons at lines 37-46 (@if ($canManage)), with no projects-module check.
- Води към: projects.show (and databackd.teams.requests.create_project)
- Проверка/отказ: app/Http/Controllers/ProjectController.php:913-922: abort_403 unless view_projects == 'all', or 'owned' and the user is a project member. The default employee permission is view_projects = OWNED (app/Models/PermissionRole.php:54), and the project created by TeamRequestController::createProject (line 323) is public = 0, with only the assigned specialists as members. ProjectController.php:78: abort_403 without the 'projects' module. createProject itself has no projects-module check.
- Сценарий: Any package with databackd_teams. An employee who is not one of the specialists assigned to an active team request (e.g. an accountant) opens the request from the 'Team requests' menu and clicks the project name. Same for an admin when the company admin switched 'projects' off for the admin/employee role.
- Ефект: 403 page from a link in the request details card.
- Поправка: Link the project only when in_array('projects', user_modules()) && (user()->permission('view_projects') == 'all' || the user is a project member); otherwise show plain text. Guard createProject and the buttons with the projects module.
- Проверка от втори агент: teams/requests/show.blade.php:171-173 links projects.show for every non-client, and :37-46 shows the admin buttons. TeamRequestController::show (:163-165) shows any request to any employee (scopedQuery :438-446). createProject (:323-363) makes public=0 projects with only the specialists as members. ProjectController.php:913-922 aborts for a non-member with OWNED (the employee default), and :78 aborts without projects.
- Намерено от: databackd-analytics-planning-teams

### F214 · средна · модул извън пакета се вижда

**Statutory report (reports module) offers consolidation groups and runs the consolidation (analytics module) without checking databackd_analytics**

- Елемент: `resources/views/databackd/gaps/statutory/index.blade.php:71` – показва се при: @if (!empty($scopes['groups'])) <optgroup ...groups> - $scopes = StatutoryReport::scopes() (app/Services/Databackd/Gaps/Statutory/StatutoryReport.php:94-96) lists every FinConsolidationGroup of the company. parseScope() (lines 116-117) and the group scope (line 507, ConsolidationService) are used without any module check.
- Води към: Consolidation groups / consolidated statements (databackd_analytics feature) inside databackd.gaps.statutory.index, excel, pdf and print
- Проверка/отказ: routes/databackd/gaps-statutory.php:8 requires only databackd_reports. No databackd_module('databackd_analytics') check in StatutoryController or StatutoryReport.
- Сценарий: A company moved from Trial/Starter/Демо to package 'Стартов' (has databackd_reports, lacks databackd_analytics) after it created consolidation groups. It opens Annual report (НСС).
- Ефект: The 'consolidation group' scope and consolidated balance sheet / income statement (with eliminations), including the PDF and Excel exports, stay available although Consolidation is not in the package. The ?scope=group:<id> URL also works.
- Поправка: Same as F204: list and accept group scopes only when databackd_module('databackd_analytics').
- Проверка от втори агент: Duplicate of F204. StatutoryReport.php:94-96, :116-129 and :506-508 expose consolidation groups and run ConsolidationService under a route that requires only databackd_reports (gaps-statutory.php:8).
- Намерено от: databackd-analytics-planning-teams

### F215 · средна · мъртва връзка (403/404)

**Consolidation screens link to Legal entities and FX rates (data module) without checking databackd_data**

- Елемент: `resources/views/databackd/analytics/consolidation-rules/show.blade.php:67` – показва се при: @if ($entitiesUrl) 'Manage legal entities' - $entitiesUrl = Route::has('databackd.data.entities.index') ? route(...) (ConsolidationGroupController.php:103). Also consolidation/index.blade.php:99-100: @if ($fxRatesUrl), where $fxRatesUrl = Route::has('databackd.gaps.fx-rates.index') ? route(...) (ConsolidationController.php:65).
- Води към: databackd.data.entities.index, databackd.gaps.fx-rates.index
- Проверка/отказ: routes/databackd/data-dimensions.php:10 and routes/databackd/gaps-fx-rates.php:9: RequireModule:databackd_data gives 403 'not in your package'.
- Сценарий: A package with databackd_analytics but without databackd_data (not used in production today, but packages are configurable). The user opens a consolidation group with no entities, or a consolidation with a missing FX rate.
- Ефект: The 'Manage legal entities' button / 'FX rates' notice link answer 403.
- Поправка: Use Route::has(...) && databackd_module('databackd_data') for both URLs.
- Проверка от втори агент: Duplicate of F209. ConsolidationGroupController.php:103 and ConsolidationController.php:65 build the URLs with Route::has only, and the targets are behind RequireModule:databackd_data. The role-toggle scenario (data off for employees, analytics on) makes it medium rather than package-only low.
- Намерено от: databackd-analytics-planning-teams

### F217 · средна · модул извън пакета се вижда

**Invoice/expense sync card and preview show invoice and expense totals without checking the invoices/expenses modules**

- Елемент: `resources/views/databackd/data/integrations/index.blade.php:77` – показва се при: @if ($hasSync) - $hasSync = Route::has('databackd.data.sync.run') && databackd_module('databackd_data') (IntegrationController.php:35). The 'Preview' link leads to sync/index.blade.php:119-131, which lists counts and totals of paid invoices and approved expenses.
- Води към: databackd.data.sync.index / databackd.data.sync.run (data of the invoices and expenses modules)
- Проверка/отказ: FinSyncController.php:24-46 checks only for the client role. InvoiceExpenseSync::preview()/run() read Invoice/Expense directly (InvoiceExpenseSync.php:369-430). There is no in_array('invoices'/'expenses', user_modules()) check and no view_invoices/view_expenses permission check.
- Сценарий: The company admin switches the 'invoices' or 'expenses' module off for the employee role in Settings -> Module settings. An employee (who still has databackd_data and databackd_integrations) opens Integrations -> Preview.
- Ефект: The employee sees the number and totals of paid invoices and approved expenses, and can run the transfer, although the module is off for that role.
- Поправка: Same as F203: gate hasSync and FinSyncController on the invoices/expenses modules, and let InvoiceExpenseSync skip disabled sources.
- Проверка от втори агент: Duplicate of F203. IntegrationController.php:35 hasSync and FinSyncController.php:24-45 have no invoices or expenses module or permission check, and the preview shows invoice and expense totals.
- Намерено от: databackd-analytics-planning-teams

### F205 · ниска · модул извън пакета се вижда

**Report filter bars list Anagami Finance View clients regardless of the clients module**

- Елемент: `resources/views/databackd/reports/_filters.blade.php:127` – показва се при: @if ($has('client')), with $clients = User::whereIn('id', FinEntry::whereNotNull('client_id')...) at _filters.blade.php:34-36. Used by P&L, cash flow, counterparties, dashboard and builder. The margins filter does the same at gaps/margins/_filters.blade.php:50-55 (clients from invoices).
- Води към: Client select on the report screens (no navigation)
- Проверка/отказ: Missing: no in_array('clients', user_modules()) check; client_id is filled from invoices by InvoiceExpenseSync.php:283/418
- Сценарий: The clients module is switched off for employees in Module settings and entries have been synced from invoices; the employee opens any AFV report.
- Ефект: The employee sees a "Client" filter listing the Anagami Finance View client names from a module that is off for the role. Without synced data the filter is always shown, but empty.
- Поправка: Show the client option only when in_array('clients', user_modules()), and ignore client_id in ReportFilters otherwise.
- Проверка от втори агент: reports/_filters.blade.php:34-36 loads users referenced by FinEntry.client_id, and :127 renders the Client select with no clients module check. The names come from the company's own financial entries, so this is a low-impact leak.
- Намерено от: databackd-data-reports

### F206 · ниска · модул извън пакета се вижда

**Integrations page and onboarding show locked "not in your package" teaser cards for modules the user does not have**

- Елемент: `resources/views/databackd/data/integrations/index.blade.php:24` – показва се при: @if (!empty($card['locked'])), where IntegrationController.php:67-74 and :112-131 set 'locked' => !databackd_module('databackd_data') (Excel, CSV, the accounting-program cards and the bank statements card stay visible). Onboarding step 4 does the same: gaps/onboarding/_step-import.blade.php:33-40 shows the Google Sheets card with the badge sheets_not_in_package when !databackd_module('databackd_integrations').
- Води към: Cards without a link (no 403)
- Проверка/отказ: No dead end; the feature is shown although the module is off. The hint text badges.locked_hint says the Data module is not in the package even when only the role toggle is off; RequireModule.php:30-32 distinguishes the two cases.
- Сценарий: databackd_integrations on and databackd_data off for the role (or package); onboarding step 4 for a company without databackd_integrations.
- Ефект: Users see import features from a module that is not in their package or role, marked "Not in the package". This is an intentional S12 teaser, but it conflicts with the rule "must NOT be visible", and the wording is wrong for a role toggle.
- Поправка: Drop locked cards from activeCards()/platformCards() and the sheets choice when !$sheetsInPackage. If teasers are kept, use the permissionDenied wording when databackd_company_module() is true.
- Проверка от втори агент: IntegrationController.php:71-72 and :112-131 mark cards 'locked' => !databackd_module('databackd_data') but keep them, and integrations/index.blade.php:24-25 renders a locked badge. onboarding/_step-import.blade.php:38-39 shows sheets_not_in_package. These are deliberate S12 teasers with no dead end, but they conflict with the owner's must-not-be-visible rule.
- Намерено от: databackd-data-reports

### F216 · ниска · модул извън пакета се вижда

**Existing 'budget deviation' alert rules are still listed and evaluated when databackd_planning is not in the package**

- Елемент: `resources/views/databackd/gaps/alerts/index.blade.php:102` – показва се при: Every rule is listed. For type budget_deviation the budget name and scope are shown from $budgets = FinBudget::... in AlertRuleController::index (line 56), which has no planning check. Only the form hides the type (StoreAlertRuleRequest.php:157, AlertRuleController.php:159).
- Води към: Budget (databackd_planning) data in the alerts list, and budget alerts in the bell and e-mail
- Проверка/отказ: The AlertChecker budget_deviation case (app/Services/Databackd/Gaps/Alerts/AlertChecker.php:221, 296-358) and CheckAlerts.php:54 check only databackd_analytics, never databackd_planning.
- Сценарий: A company whose package was changed to one with databackd_analytics but without databackd_planning, after it created budget-deviation rules.
- Ефект: Budget names and deviations stay visible in the alerts list. The hourly check keeps firing 'budget deviation' notifications for a module the company no longer has.
- Поправка: In AlertRuleController::index mark or hide budget_deviation rules when !databackd_module('databackd_planning'). In AlertChecker, skip them when !databackd_company_module($companyId, 'databackd_planning').
- Проверка от втори агент: AlertRuleController.php:52-56 index loads all rules and budgets with no planning check. Only formView (:159-160) and StoreAlertRuleRequest gate budget_deviation on planning. CheckAlerts.php:54 checks only databackd_analytics, and AlertChecker.php:221 evaluates budget_deviation.
- Намерено от: databackd-analytics-planning-teams

## G12. Скрити от config/databackd.php функции, които прозират на други места (25)

### F183 · средна · скрита функция прозира

**Project page shows a 'Tickets' tab to all roles (config-hidden tickets); it is gated by the project-NOTE permission, with no tickets module check**

- Елемент: `resources/views/projects/show.blade.php:161` – показва се при: `@if ($viewNotePermission != 'none' )` where `$viewNotePermission = user()->permission('view_project_note')` (line 15). There is no in_array('tickets', user_modules()) and no databackd_hidden('menu','tickets').
- Води към: projects.show?tab=tickets -> ProjectController::tickets; the 'Add ticket' button (resources/views/projects/ajax/tickets.blade.php:10-14) goes to tickets.create
- Проверка/отказ: app/Http/Controllers/ProjectController.php:1730-1733 checks only view_tickets in [all, added, owned, both], with no module check. tickets.create: TicketController constructor abort_403(!in_array('tickets', $this->user->modules)).
- Сценарий: Every package. Admin, employee (view_project_note = ALL by default) and client (ALL) see 'Tickets' on every project page while the Tickets menu is config-hidden. If the admin turns tickets off for a role, the tab still lists tickets, and 'Add ticket' and the row actions return 403. A custom role with view_project_note != none but view_tickets = none gets a 403 when opening the tab.
- Ефект: The hidden Tickets feature is exposed in the main project UI. Depending on the role/module state, it ends on a 403.
- Поправка: @if (!databackd_hidden('menu', 'tickets') && in_array('tickets', user_modules()) && in_array(user()->permission('view_tickets'), ['all','added','owned','both'])), plus abort_403(!in_array('tickets', user_modules())) in ProjectController::tickets().
- Проверка от втори агент: projects/show.blade.php:161 shows the Tickets tab for view_project_note != 'none', with no tickets module or databackd_hidden check. ProjectController.php:1728-1733 checks only view_tickets. tickets.create is gated by the TicketController module check.
- Намерено от: crm-reports

### F184 · средна · скрита функция прозира

**Client profile shows a 'Tickets' tab for the admin (config-hidden), with no tickets module check; inside, 'Add ticket' returns 403 when the module is off**

- Елемент: `resources/views/clients/show.blade.php:87` – показва се при: `@if ($viewTicket == 'all' || $viewTicket == 'added')` (`$viewTicket = user()->permission('view_tickets')`, line 9). No in_array('tickets', user_modules()) and no databackd_hidden('menu','tickets').
- Води към: clients.show?tab=tickets -> ClientController::tickets; the 'Add ticket' button resources/views/clients/ajax/tickets.blade.php:8 goes to tickets.create; row actions go to tickets.show
- Проверка/отказ: app/Http/Controllers/ClientController.php:926-937 checks only view_clients, with no module check. tickets.create and tickets.show: TicketController constructor abort_403(!in_array('tickets', $this->user->modules)).
- Сценарий: Any package, admin role (view_tickets = all): every client profile has a Tickets tab although Tickets is hidden. If the admin turns tickets off for the admin role, the tab still shows ticket data, and 'Add ticket' and 'View' return 403.
- Ефект: The hidden module is visible on the client profile, with a possible 403.
- Поправка: Add !databackd_hidden('menu', 'tickets') && in_array('tickets', user_modules()) && to the tab condition, and the tickets module check in ClientController::tickets().
- Проверка от втори агент: clients/show.blade.php:87-89 shows the Tickets tab for view_tickets all/added, with no module or databackd_hidden check. ClientController.php:926-931 checks only view_clients.
- Намерено от: crm-reports

### F005 · ниска · скрита функция прозира

**Advanced dashboard link opens config-hidden project/client/HR/ticket dashboards when that is the only permission**

- Елемент: `resources/views/sections/menu.blade.php:6` – показва се при: $sidebarUserPermissions['view_project_dashboard'] == 4 || ['view_client_dashboard'] == 4 || ['view_hr_dashboard'] == 4 || ['view_ticket_dashboard'] == 4 (lines 6-9) show Advanced dashboard (:22)
- Води към: dashboard.advanced default branch
- Проверка/отказ: app/Http/Controllers/DashboardController.php:219-233 calls projectDashboard()/clientDashboard()/hrDashboard() with no databackd_hidden('dashboard_tabs', ...) or module check. The tabs are hidden in resources/views/dashboard/admin.blade.php:53-70 and config/databackd.php:41-46
- Сценарий: Employee whose only dashboard permission is View HR dashboard = All, e.g. on package Default where employees and leaves are not in the package; or project/client dashboard only
- Ефект: The sidebar link renders the hidden HR (or project/client) dashboard with employee and leave widgets, while no tab is visible
- Поправка: Count a tab in menu.blade.php:4-11 and in the DashboardController default chain only when !databackd_hidden('dashboard_tabs', <tab>) and its module is in user_modules() (the same rules as admin.blade.php:47-73).
- Проверка от втори агент: menu.blade.php:6-9 show Advanced dashboard when any project/client/hr/ticket dashboard permission == 4. DashboardController.php:219-233 then renders projectDashboard()/clientDashboard()/hrDashboard() with no databackd_hidden or module check. The tab bar hides these tabs (dashboard/admin.blade.php:52-68 via databackd_hidden('dashboard_tabs', ...)), so the hidden dashboard is shown with no tab.
- Намерено от: sidebar

### F021 · ниска · скрита функция прозира

**Global search offers config-hidden features: Ticket, Notice, Estimate, Lead**

- Елемент: `resources/views/search/index.blade.php:14` – показва се при: Lines 14-16 tickets, 20-22 notices, 29-31 estimates, 42-44 leads: only module checks, no databackd_hidden('menu', ...)
- Води към: tickets.index, notices.index, estimates.index, deals.index
- Проверка/отказ: config/databackd.php:16,23,26,30 hide these menus for everyone except the super admin; the pages themselves still open
- Сценарий: Any admin, employee or client in a package with these modules (all packages)
- Ефект: Users can reach hidden modules from the topbar search.
- Поправка: Add && !databackd_hidden('menu', 'tickets'|'notices'|'estimates'|'leads') to each option.
- Проверка от втори агент: search/index.blade.php:14-16 (ticket), 20-22 (notice), 29-31 (estimate) and 42-44 (lead) check only modules. config/databackd.php:16,23,26,30 hide these menus. The target pages still open.
- Намерено от: topbar-global

### F039 · ниска · скрита функция прозира

**Finance settings still show the Estimate settings tab and the estimate / estimate-request / order / proposal prefixes although estimates, orders and proposals are config-hidden**

- Елемент: `resources/views/invoice-settings/index.blade.php:59` – показва се при: @if (in_array('estimates', user_modules())) (tab, line 59). Prefix blocks in resources/views/invoice-settings/ajax/prefix.blade.php:66 (estimates), :130 (orders), :162 (proposals). None check databackd_hidden('menu', ...).
- Води към: invoice-settings.index?tab=estimate-setting, invoice_settings.update_estimate_setting, invoice_settings.update_prefix
- Проверка/отказ: config/databackd.php:22-25 hides menu.proposals, menu.estimates and menu.orders for everyone except the super admin; InvoiceSettingController.php:57 checks only the module
- Сценарий: Any company admin on Trial/Starter/Демо (estimates, orders) or Default (estimates) opens Settings -> Finance settings.
- Ефект: Settings for features that are deliberately invisible in the product are shown.
- Поправка: Add && !databackd_hidden('menu', 'estimates'|'orders'|'proposals') to the tab and the prefix blocks.
- Проверка от втори агент: invoice-settings/index.blade.php:59-63 (Estimate settings tab) and prefix.blade.php:66 (estimates), :130 (orders) and :162 (proposals) check modules only. config/databackd.php:22-25 hide menu.proposals, estimates and orders.
- Намерено от: settings

### F057 · ниска · скрита функция прозира

**Overview 'Pending follow up' widget shows the hidden leads/deals feature, and its agent avatars return 403 on Default**

- Елемент: `resources/views/dashboard/ajax/overview.blade.php:258-292` – показва се при: @if (in_array('leads', user_modules()) && in_array('pending_follow_up', $activeWidgets)); links deals.show (line 268) and <x-employee :user="$item->leadAgent->user"/> (line 277)
- Води към: deals.show (leads menu hidden: config/databackd.php:16 'leads' => true); employees.show
- Проверка/отказ: Hidden by config('databackd.hidden.menu.leads'); employees.show blocked by EmployeeController.php:76-77 on packages without employees
- Сценарий: Admin on package Default/Trial/Starter (leads in all of them) with overdue deal follow-ups opens Advanced dashboard -> Overview.
- Ефект: The config-hidden deals feature is reachable from the overview. On Default the agent name/avatar opens a 403 page.
- Поправка: Add && !databackd_hidden('menu', 'leads') to overview.blade.php:258, and map pending_follow_up => 'leads' in DashboardController.php:143.
- Проверка от втори агент: overview.blade.php:258 gates pending_follow_up on leads only (leads is in every package). It links deals.show (:268) and <x-employee> (:277), which gives 403 on Default. menu.leads is hidden (config/databackd.php:16). DashboardController.php:143 maps the widget to 'tasks'. Lowered because the 403 needs existing deals with agents, which is unlikely while leads is hidden.
- Намерено от: dashboards

### F058 · ниска · скрита функция прозира

**Overview widgets link to config-hidden Attendance, Tickets and Leaves (counters, open tickets, pending leaves with approve/reject)**

- Елемент: `resources/views/dashboard/ajax/overview.blade.php:63-82` – показва се при: Module-only gates: total_today_attendance -> attendances.index (63-72); total_unresolved_tickets -> tickets.index (74-82); settings_leaves -> leaves.show + approve/reject modals (105-163, JS 452-479); new_tickets -> tickets.show (165-211). No databackd_hidden check. The widget-settings dropdown (admin.blade.php:116) also lists them.
- Води към: attendances.index, tickets.index/show, leaves.show, leaves.show_approved_modal/show_reject_modal
- Проверка/отказ: config/databackd.php:19,26,17 hide the attendance, tickets and leaves menus; the routes still work
- Сценарий: Admin of a Trial/Starter/Демо company (all modules) or Default (attendance, tickets) opens the Advanced dashboard Overview.
- Ефект: Features removed from the menu are reachable and actionable from the overview (e.g. approving leaves, opening tickets).
- Поправка: Add && !databackd_hidden('menu', 'attendance'|'tickets'|'leaves') to these widget conditions and their checkboxes.
- Проверка от втори агент: overview.blade.php:63 (attendance), :74 (tickets), :105 (leaves) and :165 (tickets) check modules only. config/databackd.php:17,19,26 hide these menus.
- Намерено от: dashboards

### F059 · ниска · скрита функция прозира

**Private dashboard alerts, ticket counter and clock-in/out buttons expose hidden Leaves/Holidays/Tickets/Attendance (leave alert has no module check)**

- Елемент: `resources/views/dashboard/employee/index.blade.php:69-88` – показва се при: @if (!is_null($checkTodayLeave)) link leaves.show (73); @elseif (!is_null($checkTodayHoliday)) link holidays.show (83). Both are queried with no module check (EmployeeDashboard.php:330-353). Profile card: @if (isset($totalOpenTickets) && in_array('tickets', user_modules())) link tickets.index (297-306). Clock-in: @if (in_array('attendance', user_modules())) (186); Clock-out (198) has no module check at all.
- Води към: leaves.show, holidays.show, tickets.index, attendances.clock_in_modal / show_clocked_hours
- Проверка/отказ: Menus hidden by config/databackd.php:17,19,20,26. LeaveController.php:40 abort_403 when leaves is off (Default lacks leaves). HolidayController has no module check.
- Сценарий: Employee/admin on any package with an approved leave or holiday today, or a ticket agent. On Default, a leftover leave record leads to 403 via the leave alert.
- Ефект: Links and buttons to features removed from the menu are shown on the landing page. The leave alert can dead-end in 403 when leaves is not in the package.
- Поправка: Show the leave alert only with in_array('leaves', user_modules()) && !databackd_hidden('menu','leaves'), the holiday alert only with holidays on and not hidden, and the ticket counter only when !databackd_hidden('menu','tickets').
- Проверка от втори агент: employee/index.blade.php:69-88: the leave and holiday alerts come from unguarded queries (EmployeeDashboard.php:328-350), and LeaveController.php:40 returns 403 without leaves. Ticket counter :297 checks tickets but not the config hiding. The clock-in part is not a bug: LateClockInListener.php:22-26 states the dashboard clock-in is deliberately kept while attendance is hidden.
- Намерено от: dashboards

### F060 · ниска · скрита функция прозира

**Finance tab shows estimate and proposal overview charts although the Estimates and Proposals menus are hidden**

- Елемент: `resources/views/dashboard/ajax/finance.blade.php:100-122` – показва се при: @if (in_array('estimates', user_modules()) && in_array('estimate_overview', $activeWidgets)) and @if (in_array('leads', user_modules()) && in_array('proposal_overview', $activeWidgets)); no databackd_hidden check
- Води към: Estimate/proposal data (FinanceDashboard estimateOverviewChartData/proposalOverviewChartData)
- Проверка/отказ: config/databackd.php:22-23 hide the 'proposals' and 'estimates' menus
- Сценарий: Admin on any package opens Advanced dashboard -> Finance.
- Ефект: Charts for features the product owner hid are shown. There is no link, so it does not dead-end.
- Поправка: Add && !databackd_hidden('menu','estimates') and && !databackd_hidden('menu','proposals') to the two conditions and their checkboxes.
- Проверка от втори агент: finance.blade.php:100 (estimates) and :112 (leads/proposals) have no databackd_hidden check. config/databackd.php:22-23 hide them. There is no link.
- Намерено от: dashboards

### F061 · ниска · скрита функция прозира

**Client dashboard shows 'Unresolved tickets' card linking to the hidden Tickets feature**

- Елемент: `resources/views/dashboard/client/index.blade.php:19-26` – показва се при: @if (in_array('tickets', user_modules())) <a href="{{ route('tickets.index') }}">
- Води към: tickets.index
- Проверка/отказ: config/databackd.php:26 'tickets' => true hides the Tickets menu (menu.blade.php:446); the route is open to clients via TicketController
- Сценарий: Client user of a company whose package has tickets (Default, Trial, Starter) opens the dashboard.
- Ефект: The client sees and can open the ticket list, which is hidden everywhere else.
- Поправка: Add && !databackd_hidden('menu', 'tickets') && $sidebarUserPermissions['view_tickets'] != 5
- Проверка от втори агент: dashboard/client/index.blade.php:19-26 links tickets.index with the tickets check only. config/databackd.php:26 hides tickets (menu.blade.php:446).
- Намерено от: dashboards

### F077 · ниска · скрита функция прозира

**Employee profile still shows tabs for config-hidden features: Attendance, Leaves, Leaves quota, Shift roster, Appreciation, Tickets**

- Елемент: `resources/views/employees/show.blade.php:68` – показва се при: Attendance :68 (view_attendance + attendance module), Leaves + Leaves quota :74-82 (leaves module), Tickets :108 (view_tickets all + tickets), Appreciation :114 ($showFullProfile), Shift roster :120 (view_shift_roster all + attendance). No databackd_hidden() check. The tab bar is duplicated in resources/views/employees/ajax/attendance.blade.php:79-135
- Води към: attendances.*, leaves.*, employee-leaves, shifts.*, appreciations.*, tickets.*
- Проверка/отказ: config/databackd.php:17-26 hides menu.leaves, shifts, attendance, appreciation, tickets for everyone except the super admin
- Сценарий: Trial/Starter/Демо admin opens any employee profile: six tabs of hidden features with full CRUD (add leave, mark attendance, add appreciation, create ticket)
- Ефект: Hidden features reachable from the main UI
- Поправка: Add && !databackd_hidden('menu', '<key>') to each tab in both tab bars.
- Проверка от втори агент: employees/show.blade.php:68 (attendance), :74-81 (leaves, leaves-quota), :108 (tickets), :114 (appreciation) and :120 (shift roster) have no databackd_hidden check. config/databackd.php:17-26 hide these menus.
- Намерено от: hr

### F078 · ниска · скрита функция прозира

**Employee profile overview still shows widgets of hidden features (Late attendance, Leaves taken, Appreciation card, Tickets chart)**

- Елемент: `resources/views/employees/ajax/profile.blade.php:316` – показва се при: @if (in_array('attendance', user_modules())) lateAttendanceMark widget (:316-322). @if (in_array('leaves', user_modules())) leavesTaken widget (:323-327). Only 'remainingLeaves' got @unless (databackd_hidden('menu', 'leaves')) at :328. Appreciation card @if ($showFullProfile) (:266-279). Tickets pie chart @if (in_array('tickets', user_modules())) (:349-360)
- Води към: Attendance/Leave/Appreciation/Ticket data
- Проверка/отказ: config/databackd.php:17-26 (menu.attendance, leaves, appreciation, tickets hidden)
- Сценарий: Trial company admin opens any employee profile
- Ефект: Widgets of hidden modules visible. The AFV hiding at :328 covers only one of the four
- Поправка: Wrap these blocks in @unless (databackd_hidden('menu', 'attendance'|'leaves'|'appreciation'|'tickets')).
- Проверка от втори агент: employees/ajax/profile.blade.php:316-322 (attendance), :323-327 (leaves taken) and :349-360 (tickets) check modules only. :266-279 (appreciation) checks $showFullProfile only. Only remainingLeaves (:328) uses databackd_hidden.
- Намерено от: hr

### F079 · ниска · скрита функция прозира

**Advanced dashboard Overview still shows the 'Total today attendance' and 'Pending leaves' widgets (attendance/leaves are config-hidden)**

- Елемент: `resources/views/dashboard/ajax/overview.blade.php:62` – показва се при: @if (in_array('attendance', user_modules()) && in_array('total_today_attendance', $activeWidgets)) <a href="{{ route('attendances.index') }}"> (:62-70). @if (in_array('leaves', user_modules()) && in_array('settings_leaves', $activeWidgets)) with leaves.show + approve/reject actions (:104-140, JS :460/:475). Both widgets are active by default (DashboardWidget.php:45, :48)
- Води към: attendances.index, leaves.show, leaves.show_approved_modal / show_reject_modal
- Проверка/отказ: config/databackd.php:19 menu.attendance and :17 menu.leaves hidden
- Сценарий: Admin on any package (attendance is in every package, Default included) opens Dashboard -> Advanced -> Overview
- Ефект: Clickable entry into the hidden Attendance page. Approve/reject UI of hidden Leaves
- Поправка: Add !databackd_hidden('menu','attendance') / !databackd_hidden('menu','leaves') to the two widget conditions and their checkboxes.
- Проверка от втори агент: overview.blade.php:63-72 (attendance to attendances.index) and :105 (settings_leaves) check modules only. config/databackd.php:17,19 hide them. Overlaps F058.
- Намерено от: hr

### F100 · ниска · скрита функция прозира

**Client profile shows Estimates, Tickets, Orders and GDPR tabs although these features are config-hidden**

- Елемент: `resources/views/clients/show.blade.php:47-52 (estimates), :87-92 (tickets), :94-100 (orders), :102-107 (gdpr)` – показва се при: Module/permission checks only; no databackd_hidden() guard
- Води към: clients.show?tab=estimates|tickets|orders|gdpr (with buttons estimates.create, tickets.create, ticket-form.index, orders.create inside)
- Проверка/отказ: config/databackd.php:23 'estimates', :25 'orders', :26 'tickets', :29 'gdpr' hidden in 'menu' (menu.blade.php:384, :434, :446, :500), 'quick_add.ticket' (:87) and 'settings.gdpr_settings' (:68); the routes stay registered
- Сценарий: Every company on Trial/Starter/Демо/Стартов (estimates, tickets and orders are in the package; the admin has view_* = all): the admin opens any client. GDPR only appears where enable_gdpr was already 1
- Ефект: Hidden features reappear as client tabs with working list and create buttons
- Поправка: Add @unless(databackd_hidden('menu', 'estimates'|'tickets'|'orders'|'gdpr')) around each tab.
- Проверка от втори агент: clients/show.blade.php:47-52 (estimates), :87-92 (tickets), :94-100 (orders) and :102-107 (gdpr) have no databackd_hidden checks. config/databackd.php:23,25,26,29 hide these menus.
- Намерено от: clients

### F113 · ниска · мъртва връзка (403/404)

**Project overview 'Message' button opens messages.create with no messages module check (messages is also config-hidden)**

- Елемент: `resources/views/projects/ajax/overview.blade.php:177` – показва се при: @if ((in_array('admin', user_roles()) && $messageSetting->allow_client_admin == 'yes') || (in_array('employee', user_roles()) && $messageSetting->allow_client_employee == 'yes')); click handler line 470-475 opens route('messages.create')
- Води към: messages.create (MessageController)
- Проверка/отказ: app/Http/Controllers/MessageController.php:23 abort_403(!in_array('messages', $this->user->modules)); config/databackd.php hidden.menu.messages=true hides Messages from the sidebar
- Сценарий: Messages module switched off for the admin or employee role while the message setting allows client chat; also for every company, because Messages is hidden by config but this button stays
- Ефект: 403 inside the modal when the module is off; otherwise an entry into the hidden Messages feature
- Поправка: Prefix the condition at overview.blade.php:177 with !databackd_hidden('menu', 'messages') && in_array('messages', user_modules()) &&.
- Проверка от втори агент: projects/ajax/overview.blade.php:177-178 shows the button when allow_client_admin/employee == 'yes', and the handler at :470-474 opens messages.create. MessageController.php:23 aborts without messages. Both settings default to 'no' (CompanyObserver.php:580-581), and the Message settings page is config-hidden (settings.message_settings), so only companies that enabled them earlier see the button. That is why this is low, not medium.
- Намерено от: projects

### F118 · ниска · скрита функция прозира

**Project detail shows Estimates, Orders and Tickets tabs although those features are hidden by config**

- Елемент: `resources/views/projects/show.blade.php:90` – показва се при: Estimates tab (line 90) gated by the estimates module and permission, Orders tab (96) by the orders module and permission, Tickets tab (161) by view_project_note; none checks databackd_hidden()
- Води към: projects.show?tab=estimates / ?tab=orders / ?tab=tickets (and estimates.create, orders.create, tickets.create buttons inside)
- Проверка/отказ: config/databackd.php:23,25,26 hidden.menu.estimates/orders/tickets=true (sidebar hides them via menu.blade.php:384,434,446; HomeController.php:930,963 404 the public ticket pages), but the routes stay registered
- Сценарий: Any company on Trial/Starter/Демо (estimates and orders in the package) or any company (tickets): the project page shows these tabs to admins, employees and clients
- Ефект: Entry into features the product owner hid; they work from the project tabs
- Поправка: Add !databackd_hidden('menu', 'estimates'), !databackd_hidden('menu', 'orders') and !databackd_hidden('menu', 'tickets') to the three tab conditions.
- Проверка от втори агент: projects/show.blade.php:90 (estimates), :96 (orders) and :161 (tickets, gated by view_project_note) have no databackd_hidden check. config/databackd.php:23,25,26 hide estimates, orders and tickets, and the sidebar respects that at menu.blade.php:384,434,446.
- Намерено от: projects

### F156 · ниска · скрита функция прозира

**Invoices index shows the Subscriptions button when PayFast is active, although Subscriptions are config-hidden**

- Елемент: `resources/views/invoices/index.blade.php:151` – показва се при: @if (isset($paymentGateway) && $paymentGateway->payfast_status === 'active'), then the subscriptions.index link (:153) or client.subscriptions.index (:158); no databackd_hidden check
- Води към: subscriptions.index / client.subscriptions.index
- Проверка/отказ: config/databackd.php hidden.menu.subscriptions = true; the recurring index already respects it at resources/views/recurring-invoices/index.blade.php:123 `!databackd_hidden('menu', 'subscriptions')`
- Сценарий: Company enables the PayFast gateway; admin or client opens Finance -> Invoices.
- Ефект: A link to the hidden Subscriptions feature is visible.
- Поправка: Add && !databackd_hidden('menu', 'subscriptions') to the condition at invoices/index.blade.php:151.
- Проверка от втори агент: invoices/index.blade.php:151-162 shows the subscriptions links when payfast is active, with no databackd_hidden check. hidden.menu.subscriptions=true, and menu.blade.php:395 respects it.
- Намерено от: invoices-sales

### F157 · ниска · скрита функция прозира

**Finance sidebar group is shown because of config-hidden estimates/proposals and can render empty**

- Елемент: `resources/views/sections/menu.blade.php:371` – показва се при: Outer condition counts in_array('estimates', user_modules()) and view_estimates/view_lead_proposals != 5, but the Estimates and Proposals sub-items are removed by databackd_hidden (menu.blade.php:379, :384)
- Води към: Finance collapse menu
- Проверка/отказ: config/databackd.php hidden.menu.estimates / proposals = true
- Сценарий: Client role (CLIENT_MODULES include estimates) where the company admin switched invoices and payments off for clients, or an employee whose only finance access is estimates.
- Ефект: A "Finance" menu entry that expands to nothing.
- Поправка: Buffer the children (ob_start, as the AFV sections do) and print the Finance group only when at least one sub-menu item was rendered.
- Проверка от втори агент: menu.blade.php:371 ORs the modules and the permissions independently, including estimates and view_lead_proposals. The Estimates and Proposals children are hidden by databackd_hidden (:379, :384). The sidebar JS (sidebar.blade.php) never removes empty groups, so an empty Finance accordion can render.
- Намерено от: invoices-sales

### F162 · ниска · скрита функция прозира

**Client and project profile "Estimates" and "Orders" tabs are not hidden although estimates and orders are config-hidden**

- Елемент: `resources/views/clients/show.blade.php:47` – показва се при: @if (in_array('estimates', user_modules())); also clients/show.blade.php:94 (orders), resources/views/projects/show.blade.php:90 (estimates) and :96 (orders); none checks databackd_hidden
- Води към: clients.show?tab=estimates|orders, projects.show?tab=estimates|orders (with "Create Estimate" / "Create order" buttons)
- Проверка/отказ: config/databackd.php hidden.menu.estimates / orders = true (the sidebar hides them at resources/views/sections/menu.blade.php:384 and :434)
- Сценарий: Trial/Starter/Демо companies (estimates and orders in the package): admin opens a client or project.
- Ефект: The hidden Estimates and Orders features are reachable through the profile tabs, including their create forms.
- Поправка: Add !databackd_hidden('menu', 'estimates') / !databackd_hidden('menu', 'orders') to the four tab conditions.
- Проверка от втори агент: clients/show.blade.php:47 (estimates) and :94 (orders), projects/show.blade.php:90 and :96 check modules but not databackd_hidden. hidden.menu.estimates/orders=true.
- Намерено от: invoices-sales

### F163 · ниска · скрита функция прозира

**Finance settings show the "Estimate settings" tab although estimates are config-hidden**

- Елемент: `resources/views/invoice-settings/index.blade.php:59` – показва се при: @if (in_array('estimates', user_modules()))
- Води към: invoice-settings.index?tab=estimate-setting
- Проверка/отказ: config/databackd.php hidden.menu.estimates = true; InvoiceSettingController.php:57 only checks the module
- Сценарий: Trial/Starter companies (estimates in the package): admin opens Settings -> Finance settings.
- Ефект: A settings tab for the hidden Estimates feature is visible.
- Поправка: @if (in_array('estimates', user_modules()) && !databackd_hidden('menu', 'estimates'))
- Проверка от втори агент: invoice-settings/index.blade.php:59 shows the estimate-setting tab with only in_array('estimates', user_modules()). hidden.menu.estimates=true.
- Намерено от: invoices-sales

### F186 · ниска · скрита функция прозира

**Topbar search lists config-hidden modules (Ticket, the default option, plus Notice, Lead, Estimate)**

- Елемент: `resources/views/search/index.blade.php:14` – показва се при: Ticket :14 `in_array('tickets', user_modules())` (first, preselected option); Notice :20 `in_array('notices', ...)`; Estimate :29 `in_array('estimates', ...)`; Lead :42 `in_array('leads', ...)`. No databackd_hidden() checks.
- Води към: tickets.index / notices.index / estimates.index / deals.index (SearchController.php:27-44)
- Проверка/отказ: None for hidden items: the targets work when the module is active. databackd_hidden('menu','tickets'|'notices'|'leads'|'estimates') is true in config/databackd.php.
- Сценарий: Every package, every role (the client role has tickets and notices in CLIENT_MODULES). The user opens the search, types a keyword and presses Enter.
- Ефект: The search lands on the hidden Tickets list by default. Notice, Lead and Estimate also open hidden pages.
- Поправка: Add !databackd_hidden('menu', 'tickets'|'notices'|'estimates'|'leads') && to these options so the first visible option becomes the default.
- Проверка от втори агент: search/index.blade.php:14 (ticket, the first and default option), :20 (notice), :29 (estimate) and :42 (lead) have no databackd_hidden checks. All four are hidden in config/databackd.php.
- Намерено от: crm-reports

### F187 · ниска · скрита функция прозира

**Advanced dashboard widgets show config-hidden Tickets and Leads (and Estimates/Proposals on the Finance tab), with links into them**

- Елемент: `resources/views/dashboard/ajax/overview.blade.php:74` – показва се при: Overview: :74 `in_array('tickets', user_modules()) && in_array('total_unresolved_tickets', $activeWidgets)` links to tickets.index; :165 `new_tickets` links to tickets.show; :258 `in_array('leads', ...) && pending_follow_up` links to deals.show. Finance tab: dashboard/ajax/finance.blade.php:100 estimate_overview and :112 proposal_overview. All of these widgets default to status 1 (app/Models/DashboardWidget.php:46,49,51,75,76). The widget settings dropdown (resources/views/dashboard/admin.blade.php:114-116) also lists them.
- Води към: tickets.index, tickets.show, deals.show
- Проверка/отказ: None for hidden items: the targets work. databackd_hidden('menu','tickets'|'leads'|'estimates'|'proposals') is true, and only dashboard_tabs.ticket is hidden.
- Сценарий: Any package with tickets and leads active (Trial, Starter, Демо and Default), admin role. Advanced dashboard -> Overview (the default tab) and the Finance tab.
- Ефект: Ticket counters and tables plus a lead follow-up list are shown and clickable while those modules are hidden everywhere else.
- Поправка: Add !databackd_hidden('menu', 'tickets'|'leads'|'estimates'|'proposals') && to these widget conditions, and filter them out of the widget-settings list.
- Проверка от втори агент: dashboard/ajax/overview.blade.php:74, :165 (tickets) and :258 (leads follow-ups), and dashboard/ajax/finance.blade.php:100 (estimates) and :112 (proposals) check modules only. Only the dashboard tabs use databackd_hidden (admin.blade.php:53-67).
- Намерено от: crm-reports

### F188 · ниска · скрита функция прозира

**Client dashboard shows an 'Unresolved tickets' widget linking to the hidden Tickets module**

- Елемент: `resources/views/dashboard/client/index.blade.php:19` – показва се при: `@if (in_array('tickets', user_modules()))` linking to `route('tickets.index')` (line 21)
- Води към: tickets.index
- Проверка/отказ: None for hidden items: tickets is in ModuleSetting::CLIENT_MODULES, so the page opens. databackd_hidden('menu','tickets') is true.
- Сценарий: Every client user of any package (this is the client's landing page).
- Ефект: The client sees and can open the config-hidden Tickets module from the dashboard.
- Поправка: @if (in_array('tickets', user_modules()) && !databackd_hidden('menu', 'tickets'))
- Проверка от втори агент: dashboard/client/index.blade.php:19-21 shows the tickets widget linking tickets.index with only the module check. tickets is in CLIENT_MODULES and is config-hidden.
- Намерено от: crm-reports

### F189 · ниска · скрита функция прозира

**Employee profile shows a 'Tickets' tab (config-hidden)**

- Елемент: `resources/views/employees/show.blade.php:108` – показва се при: `@if ($viewTickets == 'all' && in_array('tickets', user_modules()))`, with no databackd_hidden check
- Води към: employees.show?tab=tickets
- Проверка/отказ: Works: the module check is the only check. databackd_hidden('menu','tickets') is true.
- Сценарий: Packages with employees (Trial, Starter, Демо), admin role.
- Ефект: The hidden Tickets feature is visible on employee profiles.
- Поправка: Add && !databackd_hidden('menu', 'tickets').
- Проверка от втори агент: employees/show.blade.php:108 is $viewTickets == 'all' && in_array('tickets', user_modules()), with no databackd_hidden check.
- Намерено от: crm-reports

### F197 · ниска · мъртва връзка (403/404)

**Project overview 'Message' button is not gated by the messages module or its hidden flag**

- Елемент: `resources/views/projects/ajax/overview.blade.php:177` – показва се при: `(in_array('admin', user_roles()) && $messageSetting->allow_client_admin == 'yes') || (in_array('employee', user_roles()) && $messageSetting->allow_client_employee == 'yes')`, with no messages module check and no databackd_hidden('menu','messages')
- Води към: messages.create (ajax modal, line 472)
- Проверка/отказ: MessageController constructor abort_403(!in_array('messages', $this->user->modules))
- Сценарий: A company whose message settings had allow_client_admin/allow_client_employee = 'yes' before the Message settings were hidden (the default is 'no' in CompanyObserver.php:580). The admin then switches messages off for the role.
- Ефект: The button opens a 403 modal. When messages is still on, it opens the config-hidden Messages feature.
- Поправка: Prefix the condition with !databackd_hidden('menu', 'messages') && in_array('messages', user_modules()) &&.
- Проверка от втори агент: Duplicate of F113. projects/ajax/overview.blade.php:177-178 has no messages module or databackd_hidden check, and the handler at :470-474 opens messages.create (MessageController.php:23). The settings default to 'no' (CompanyObserver.php:580-581).
- Намерено от: crm-reports

## Отхвърлени при проверката

- F080 Private dashboard Clock in / Clock out buttons expose the config-hidden Attendance module; Clock out is not module-gated – The clock-in button is deliberately kept: LateClockInListener.php:22-26 says 'the clock-in button on the dashboard still works' while attendance is hidden. The claimed 403 on clock-out is wrong. #clock-out (employee/index.blade.php:786-805) calls attendances.show_clocked_hours and attendances.update_clock_in, which are DashboardController routes (web.php:152-153) with no module check, not AttendanceController. Clock-out keeps working.
