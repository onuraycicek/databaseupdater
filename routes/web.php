<?php

use Illuminate\Support\Facades\Route;
use Onuraycicek\DatabaseUpdater\Http\Controllers\DatabaseUpdaterController;

Route::group(['middleware' => ['web']], function () {
		Route::get('/databaseupdater', [DatabaseUpdaterController::class, 'index'])->name('databaseupdater');
});