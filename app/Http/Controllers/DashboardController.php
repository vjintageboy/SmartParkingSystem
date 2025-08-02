<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ParkingSession;

class DashboardController extends Controller
{
    public function index()
    {
        // Định nghĩa tổng số chỗ trong bãi xe
        $totalSpots = 4; 

        // Lấy các xe đang ở trong bãi
        $parkedVehicles = ParkingSession::whereNull('time_out')->with('vehicle')->latest('time_in')->get();

        // Đếm số xe đang trong bãi
        $parkedCount = $parkedVehicles->count();

        // Tính số chỗ trống
        $availableSpots = $totalSpots - $parkedCount;

        // Lấy lịch sử 10 xe vừa rời bãi gần nhất
        $history = ParkingSession::whereNotNull('time_out')->with('vehicle')->latest('time_out')->take(4)->get();

        // Trả về view cùng với dữ liệu
        return view('dashboard', compact('totalSpots', 'parkedCount', 'availableSpots', 'parkedVehicles', 'history'));
    }
}