<?php

use Illuminate\Support\Facades\Route;
use Quizgecko\AbTesting\Http\Controllers\AbTestingAdminController;

// The prefix is applied in the Service Provider based on the config file
Route::get('/', [AbTestingAdminController::class, 'index'])->name('ab-testing.admin.index');
Route::get('/debug', [AbTestingAdminController::class, 'debug'])->name('ab-testing.debug');