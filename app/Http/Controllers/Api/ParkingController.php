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
        
        // Kiểm tra chỗ trống trước
        $totalSpots = 3; // Chỉ có 3 chỗ cho demo
        $occupiedSpots = ParkingSession::whereNull('time_out')->count();
        
        if ($occupiedSpots >= $totalSpots) {
            return response()->json(['status' => 'error', 'message' => 'Parking Full'], 400);
        }
        
        // Tìm hoặc tạo vehicle mới với thẻ RFID này
        $vehicle = Vehicle::firstOrCreate(
            ['rfid_tag' => $rfidTag],
            [
                'license_plate' => 'Unknown-' . substr($rfidTag, -4), // Tạm thời dùng 4 ký tự cuối
                'owner_name' => 'Guest User',
                'phone_number' => 'N/A',
                'is_active' => true
            ]
        );

        // Kiểm tra xem xe đã ở trong bãi chưa
        $existingSession = ParkingSession::where('vehicle_id', $vehicle->id)->whereNull('time_out')->first();
        if ($existingSession) {
            return response()->json(['status' => 'error', 'message' => 'Vehicle already inside'], 400);
        }

        ParkingSession::create([
            'vehicle_id' => $vehicle->id,
            'time_in' => Carbon::now()
        ]);

        return response()->json([
            'status' => 'success', 
            'message' => 'Entry Granted',
            'vehicle_info' => [
                'rfid_tag' => $vehicle->rfid_tag,
                'license_plate' => $vehicle->license_plate,
                'is_new_card' => $vehicle->wasRecentlyCreated
            ]
        ]);
    }

    // Hàm xử lý xe ra
    public function handleExit(Request $request)
    {
        $rfidTag = $request->input('rfid_tag');
        $vehicle = Vehicle::where('rfid_tag', $rfidTag)->first();

        if (!$vehicle) {
            return response()->json(['status' => 'error', 'message' => 'Card not found'], 404);
        }

        $session = ParkingSession::where('vehicle_id', $vehicle->id)->whereNull('time_out')->first();

        if (!$session) {
            return response()->json(['status' => 'error', 'message' => 'Vehicle not found inside'], 404);
        }

        $timeIn = new Carbon($session->time_in);
        $timeOut = Carbon::now();
        $durationInMinutes = $timeIn->diffInMinutes($timeOut);

        // Logic tính tiền: 
        // - 15 phút đầu miễn phí
        // - Sau đó 5000đ/giờ (làm tròn lên)
        $billableMinutes = max(0, $durationInMinutes - 15);
        $cost = ceil($billableMinutes / 60) * 5000;

        $session->update([
            'time_out' => $timeOut,
            'cost' => $cost
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Exit Granted',
            'cost' => $cost,
            'duration_minutes' => $durationInMinutes,
            'free_minutes' => min(15, $durationInMinutes),
            'billable_minutes' => $billableMinutes
        ]);
    }

    // Hàm lấy trạng thái bãi xe
    public function getStatus()
    {
        $totalSpots = 3; // Tổng số chỗ của bãi xe (demo)
        $parkedCount = ParkingSession::whereNull('time_out')->count();
        $availableSpots = $totalSpots - $parkedCount;

        return response()->json([
            'total_spots' => $totalSpots,
            'parked_count' => $parkedCount,
            'available_spots' => $availableSpots
        ]);
    }

    // Hàm cập nhật trạng thái slot từ ESP32
    public function updateSlotStatus(Request $request)
    {  
        $slots = $request->input('slots'); // Array [1,0,1] - 1=trống, 0=có xe
        $freeSlots = $request->input('free_slots', 0);
        $timestamp = time(); // Luôn sử dụng thời gian server hiện tại
        
        // Lưu vào cache với thời gian hết hạn 30 giây
        cache(['slot_status' => $slots], now()->addSeconds(30));
        cache(['free_slots_realtime' => $freeSlots], now()->addSeconds(30));
        cache(['slot_last_update' => $timestamp], now()->addSeconds(30));
        
        \Log::info('Slot status updated', [
            'slots' => $slots,
            'free_slots' => $freeSlots,
            'server_timestamp' => $timestamp,
            'esp32_timestamp' => $request->input('timestamp', 'not_provided')
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Slot status updated',
            'slots' => $slots,
            'free_slots' => $freeSlots
        ]);
    }

    // API lấy trạng thái slot realtime từ ESP32
    public function getSlotStatus()
    {
        $slots = cache('slot_status', [1,1,1]); // Default: all empty
        $freeSlots = cache('free_slots_realtime', 3);
        $lastUpdate = cache('slot_last_update', 0);
        $isOnline = $lastUpdate > 0 && (time() - $lastUpdate) < 30; // 30 seconds timeout
        
        return response()->json([
            'status' => 'success',
            'slots' => $slots,
            'free_slots_realtime' => $freeSlots,
            'occupied_slots_realtime' => 3 - $freeSlots,
            'last_update' => $lastUpdate,
            'esp32_online' => $isOnline,
            'slot_details' => [
                'slot_1' => $slots[0] ?? 1,
                'slot_2' => $slots[1] ?? 1, 
                'slot_3' => $slots[2] ?? 1
            ]
        ]);
    }

    // Lấy danh sách tất cả xe đã đăng ký
    public function getAllVehicles()
    {
        $vehicles = Vehicle::with(['parkingSessions' => function($query) {
            $query->whereNull('time_out'); // Xe đang trong bãi
        }])->paginate(50);

        return response()->json([
            'status' => 'success',
            'vehicles' => $vehicles
        ]);
    }

    // API lấy danh sách xe đang trong bãi (cho dashboard real-time)
    public function getParkedVehicles()
    {
        $parkedVehicles = ParkingSession::with('vehicle')
            ->whereNull('time_out')
            ->orderBy('time_in', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'parked_vehicles' => $parkedVehicles->map(function($session) {
                return [
                    'rfid_tag' => $session->vehicle->rfid_tag,
                    'license_plate' => $session->vehicle->license_plate ?? 'N/A',
                    'time_in' => $session->time_in->format('Y-m-d H:i:s'),
                    'duration' => $session->time_in->diffForHumans(),
                ];
            })
        ]);
    }

    // API lấy lịch sử xe ra vào gần đây (cho dashboard real-time)
    public function getRecentHistory()
    {
        $history = ParkingSession::with('vehicle')
            ->whereNotNull('time_out')
            ->orderBy('time_out', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'history' => $history->map(function($session) {
                return [
                    'rfid_tag' => $session->vehicle->rfid_tag,
                    'time_in' => $session->time_in->format('Y-m-d H:i:s'),
                    'time_out' => $session->time_out->format('Y-m-d H:i:s'),
                    'cost' => $session->cost,
                    'formatted_cost' => number_format($session->cost, 0, ',', '.')
                ];
            })
        ]);
    }

    // Cập nhật thông tin xe
    public function updateVehicleInfo(Request $request, $rfid)
    {
        $vehicle = Vehicle::where('rfid_tag', $rfid)->first();
        
        if (!$vehicle) {
            return response()->json(['status' => 'error', 'message' => 'Vehicle not found'], 404);
        }

        $vehicle->update([
            'license_plate' => $request->input('license_plate', $vehicle->license_plate),
            'owner_name' => $request->input('owner_name', $vehicle->owner_name),
            'phone_number' => $request->input('phone_number', $vehicle->phone_number),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Vehicle information updated',
            'vehicle' => $vehicle
        ]);
    }
}