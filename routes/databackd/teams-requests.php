<?php

use App\Http\Controllers\Databackd\Teams\TeamRequestController;
use Illuminate\Support\Facades\Route;

// Loaded by RouteServiceProvider::mapDatabackdRoutes() with the "account" prefix and the web/auth middleware.
// Clients may open these routes too (they see only their own requests); admin-only actions are checked in the controller.
Route::prefix('databackd/teams/requests')->middleware(\App\Http\Middleware\Databackd\RequireModule::class . ':databackd_teams')->name('databackd.teams.requests.')->group(function () {
    Route::get('estimate', [TeamRequestController::class, 'estimate'])->name('estimate');
    Route::get('create', [TeamRequestController::class, 'create'])->name('create');
    Route::get('/', [TeamRequestController::class, 'index'])->name('index');
    Route::post('/', [TeamRequestController::class, 'store'])->name('store');
    Route::get('{id}', [TeamRequestController::class, 'show'])->whereNumber('id')->name('show');
    Route::post('{id}/status', [TeamRequestController::class, 'changeStatus'])->whereNumber('id')->name('status');
    Route::post('{id}/assign', [TeamRequestController::class, 'assign'])->whereNumber('id')->name('assign');
    Route::post('{id}/create-project', [TeamRequestController::class, 'createProject'])->whereNumber('id')->name('create_project');
    Route::delete('{id}', [TeamRequestController::class, 'destroy'])->whereNumber('id')->name('destroy');
});
