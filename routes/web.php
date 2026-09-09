<?php

use App\Http\Controllers\AvatarController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/users', [UserController::class, 'index']);
Route::get('/order', [OrderController::class, 'checkout']);
Route::get('/avatar', [AvatarController::class, 'upload']);
Route::get('/video', [VideoController::class, 'upload']);
