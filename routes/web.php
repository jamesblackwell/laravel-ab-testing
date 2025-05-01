<?php

use Illuminate\Support\Facades\Route;
use Quizgecko\AbTesting\Http\Controllers\AbTestingAdminController;

Route::get('/admin/ab', [AbTestingAdminController::class, 'index'])->name('ab-testing.admin.index');