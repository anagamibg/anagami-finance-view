<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Http\Requests\TemplateTasks\SubTaskStoreRequest;
use App\Models\ProjectTemplateSubTask;
use App\Traits\ProjectProgress;
use Illuminate\Http\Request;

class ProjectTemplateSubTaskController extends AccountBaseController
{

    use ProjectProgress;

    public function __construct()
    {
        parent::__construct();
        $this->pageIcon = 'icon-layers';
        $this->pageTitle = 'app.menu.projectTemplateSubTask';
        // databackd: had no check; same gate as ProjectTemplateTaskController
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('projects', $this->user->modules) || !in_array('tasks', $this->user->modules));

            return $next($request);
        });
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $this->taskID = $request->task_id;

        return view('admin.project-template.sub-task.create-edit', $this->data);
    }

    /**
     * @param SubTaskStoreRequest $request
     * @return array
     */
    public function store(SubTaskStoreRequest $request)
    {
        ProjectTemplateSubTask::firstOrCreate([
            'title' => $request->title,
            'project_template_task_id' => $request->task_id,
        ]);
        return Reply::success(__('messages.recordSaved'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        ProjectTemplateSubTask::destroy($id);
        return Reply::success(__('messages.deleteSuccess'));
    }

}
