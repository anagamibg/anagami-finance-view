<?php

namespace App\Http\Controllers;

use App\Models\GanttLink;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GanttLinkController extends Controller
{

    public function __construct()
    {
        // AFV (audit 2026-10-30, G08): the module check its sibling controllers have (no package bypass by URL)
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('projects', user_modules()));

            return $next($request);
        });
    }

    public function store(Request $request)
    {
        $link = new GanttLink();
 
        $link->type = $request->type;
        $link->project_id = $request->project;
        $link->source = $request->source;
        $link->target = $request->target;
 
        $link->save();
 
        return response()->json([
            'action' => 'inserted',
            'tid' => $link->id
        ]);
    }

    public function update($id, Request $request)
    {
        $link = GanttLink::find($id);
 
        $link->type = $request->type;
        $link->source = $request->source;
        $link->target = $request->target;
 
        $link->save();
 
        return response()->json([
            'action' => 'updated'
        ]);
    }
 
    public function destroy($id)
    {
        $link = GanttLink::find($id);
        $link->delete();
 
        return response()->json([
            'action' => 'deleted'
        ]);
    }

    public function taskUpdateController()
    {
        Task::where('id', request()->id)->update(['start_date' => Carbon::parse(request()->start_date), 'due_date' => Carbon::parse(request()->end_date)->subDay()]);
        return response()->json([
            'action' => 'updated'
        ]);
    }
    
}
