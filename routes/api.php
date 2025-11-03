<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserDetailController;
use Illuminate\Support\Facades\Route;

Route::get('/user-details', [UserDetailController::class, 'index']);
Route::post('/user-details', [UserDetailController::class, 'store']);
Route::put('/user-details/{id}', [UserDetailController::class, 'update']);

Route::post('/order', [OrderController::class, 'store']);
