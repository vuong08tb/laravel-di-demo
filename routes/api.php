<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/send-notification', [NotificationController::class, 'sendNotification']);
