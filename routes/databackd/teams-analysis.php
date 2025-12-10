<?php

use App\Http\Controllers\Databackd\Teams\AnalysisRequestCommentController;
use App\Http\Controllers\Databackd\Teams\AnalysisRequestController;
use Illuminate\Support\Facades\Route;

// Loaded by RouteServiceProvider::mapDatabackdRoutes() with the "account" prefix and the web/auth middleware.
// Clients (role "client") see only their own requests; staff-only actions are guarded inside the controllers.
Route::prefix('databackd/teams/analysis')->middleware(\App\Http\Middleware\Databackd\RequireModule::class . ':databackd_teams')->name('databackd.teams.analysis.')->group(function () {
    Route::get('/', [AnalysisRequestController::class, 'index'])->name('index');
    Route::get('create', [AnalysisRequestController::class, 'create'])->name('create');
    Route::post('/', [AnalysisRequestController::class, 'store'])->name('store');

    Route::get('comments/{id}/download', [AnalysisRequestCommentController::class, 'download'])->whereNumber('id')->name('comment_download');

    Route::get('{id}', [AnalysisRequestController::class, 'show'])->whereNumber('id')->name('show');
    Route::get('{id}/edit', [AnalysisRequestController::class, 'edit'])->whereNumber('id')->name('edit');
    Route::put('{id}', [AnalysisRequestController::class, 'update'])->whereNumber('id')->name('update');
    Route::post('{id}/move', [AnalysisRequestController::class, 'move'])->whereNumber('id')->name('move');
    Route::post('{id}/deliverable', [AnalysisRequestController::class, 'deliverable'])->whereNumber('id')->name('deliverable');
    Route::get('{id}/download', [AnalysisRequestController::class, 'download'])->whereNumber('id')->name('download');
    Route::post('{id}/rate', [AnalysisRequestController::class, 'rate'])->whereNumber('id')->name('rate');
    Route::post('{id}/comment', [AnalysisRequestCommentController::class, 'store'])->whereNumber('id')->name('comment');
});
