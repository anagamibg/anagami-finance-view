<?php

use App\Http\Controllers\Databackd\Teams\SpecialistController;
use Illuminate\Support\Facades\Route;

// Loaded by RouteServiceProvider::mapDatabackdRoutes() with the "account" prefix and the web/auth middleware.
Route::prefix('databackd/teams/specialists')->middleware(\App\Http\Middleware\Databackd\RequireModule::class . ':databackd_teams')->name('databackd.teams.specialists.')->group(function () {
    Route::get('/', [SpecialistController::class, 'index'])->name('index');
    Route::get('{id}/edit', [SpecialistController::class, 'edit'])->whereNumber('id')->name('edit');
    Route::put('{id}', [SpecialistController::class, 'update'])->whereNumber('id')->name('update');
});
