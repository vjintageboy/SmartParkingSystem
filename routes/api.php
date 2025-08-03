<?php
use App\Http\Controllers\Api\ParkingController;

Route::post('/entry', [ParkingController::class, 'handleEntry']);
Route::post('/exit', [ParkingController::class, 'handleExit']);
Route::get('/status', [ParkingController::class, 'getStatus']);
Route::post('/update-slots', [ParkingController::class, 'updateSlotStatus']);
Route::get('/slot-status', [ParkingController::class, 'getSlotStatus']);

// API để quản lý thông tin xe
Route::get('/vehicles', [ParkingController::class, 'getAllVehicles']);
Route::post('/vehicles/{rfid}/update', [ParkingController::class, 'updateVehicleInfo']);

// API real-time cho dashboard
Route::get('/parked-vehicles', [ParkingController::class, 'getParkedVehicles']);
Route::get('/recent-history', [ParkingController::class, 'getRecentHistory']);