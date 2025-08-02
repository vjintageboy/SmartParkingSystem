<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Models\ParkingSession;
use Carbon\Carbon;

class ParkingController extends Controller
{
    // Hàm xử lý xe vào
    public function handleEntry(Request $request)
    {
        $rfidTag = $request->input('rfid_tag');
        $vehicle = Vehicle::where('rfid_tag', $rfidTag)->where('is_active', true)->first();

        if (!$vehicle) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Card'], 404);
        }

        // Kiểm tra xem xe đã ở trong bãi chưa
        $existingSession = ParkingSession::where('vehicle_id', $vehicle->id)->whereNull('time_out')->first();
        if ($existingSession) {
            return response()->json(['status' => 'error', 'message' => 'Vehicle already inside'], 400);
        }

        ParkingSession::create([
            'vehicle_id' => $vehicle->id,
            'time_in' => Carbon::now()
        ]);

        return response()->json(['status' => 'success', 'message' => 'Entry Granted']);
    }

    // Hàm xử lý xe ra
    public function handleExit(Request $request)
    {
        $rfidTag = $request->input('rfid_tag');
        $vehicle = Vehicle::where('rfid_tag', $rfidTag)->first();

        if (!$vehicle) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Card'], 404);
        }

        $session = ParkingSession::where('vehicle_id', $vehicle->id)->whereNull('time_out')->first();

        if (!$session) {
            return response()->json(['status' => 'error', 'message' => 'Vehicle not found inside'], 404);
        }

        $timeIn = new Carbon($session->time_in);
        $timeOut = Carbon::now();
        $durationInMinutes = $timeIn->diffInMinutes($timeOut);

        // Logic tính tiền: ví dụ 5000đ/giờ (làm tròn lên)
        $cost = ceil($durationInMinutes / 60) * 5000;

        $session->update([
            'time_out' => $timeOut,
            'cost' => $cost
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Exit Granted',
            'cost' => $cost,
            'duration_minutes' => $durationInMinutes
        ]);
    }

    // Hàm lấy trạng thái bãi xe
    public function getStatus()
    {
        $totalSpots = 100; // Tổng số chỗ của bãi xe
        $parkedCount = ParkingSession::whereNull('time_out')->count();
        $availableSpots = $totalSpots - $parkedCount;

        return response()->json([
            'total_spots' => $totalSpots,
            'parked_count' => $parkedCount,
            'available_spots' => $availableSpots
        ]);
    }
}