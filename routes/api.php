<?php
use App\Http\Controllers\Api\ParkingController;

Route::post('/entry', [ParkingController::class, 'handleEntry']);
Route::post('/exit', [ParkingController::class, 'handleExit']);
Route::get('/status', [ParkingController::class, 'getStatus']);