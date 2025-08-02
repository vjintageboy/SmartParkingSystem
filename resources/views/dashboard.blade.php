@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-green-500 text-white rounded-lg shadow-lg p-6">
            <h5 class="text-lg font-medium mb-2">Chỗ Trống</h5>
            <p class="text-3xl font-bold" id="available-spots">{{ $availableSpots }}</p>
        </div>
        <div class="bg-red-500 text-white rounded-lg shadow-lg p-6">
            <h5 class="text-lg font-medium mb-2">Xe Đang Gửi</h5>
            <p class="text-3xl font-bold" id="parked-count">{{ $parkedCount }}</p>
        </div>
        <div class="bg-yellow-400 text-gray-900 rounded-lg shadow-lg p-6">
            <h5 class="text-lg font-medium mb-2">Tổng Số Chỗ</h5>
            <p class="text-3xl font-bold">{{ $totalSpots }}</p>
        </div>
    </div>

    <!-- Parked Vehicles Table -->
    <div class="bg-white rounded-lg shadow-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h4 class="text-xl font-semibold text-gray-900">Xe đang trong bãi</h4>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã Thẻ RFID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Biển Số Xe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thời Gian Vào</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($parkedVehicles as $session)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <code class="bg-gray-100 px-2 py-1 rounded text-sm">{{ $session->vehicle->rfid_tag }}</code>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $session->vehicle->license_plate ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $session->time_in }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-gray-500">Không có xe nào trong bãi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- History Table -->
    <div class="bg-white rounded-lg shadow-lg mt-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h4 class="text-xl font-semibold text-gray-900">Lịch sử xe ra vào gần đây</h4>
        </div>
        <div class="p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã Thẻ RFID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thời Gian Vào</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thời Gian Ra</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chi Phí (VND)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                         @foreach ($history as $session)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <code class="bg-gray-100 px-2 py-1 rounded text-sm">{{ $session->vehicle->rfid_tag }}</code>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $session->time_in }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $session->time_out }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">{{ number_format($session->cost, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Hàm cập nhật trạng thái
    async function updateStatus() {
        try {
            // Gọi API /status mà ESP32 cũng đang dùng
            const response = await fetch('/api/status');
            const data = await response.json();

            // Cập nhật các con số trên giao diện
            document.getElementById('available-spots').innerText = data.available_spots;
            document.getElementById('parked-count').innerText = data.parked_count;

            console.log('Status updated!');
        } catch (error) {
            console.error('Failed to fetch status:', error);
        }
    }

    // Cứ 5 giây lại gọi hàm updateStatus một lần
    setInterval(updateStatus, 5000);
</script>
@endpush