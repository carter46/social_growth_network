<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/notifications', [NotificationController::class, 'index']);
});
