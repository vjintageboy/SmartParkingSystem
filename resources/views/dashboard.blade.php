@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Database Stats -->
        <div class="bg-green-500 text-white rounded-lg shadow-lg p-6">
            <h5 class="text-lg font-medium mb-2">Chỗ Trống (DB)</h5>
            <p class="text-3xl font-bold" id="available-spots">{{ $availableSpots }}</p>
        </div>
        <div class="bg-red-500 text-white rounded-lg shadow-lg p-6">
            <h5 class="text-lg font-medium mb-2">Xe Đang Gửi (DB)</h5>
            <p class="text-3xl font-bold" id="parked-count">{{ $parkedCount }}</p>
        </div>
        
        <!-- Real-time Sensor Stats -->
        <div class="bg-blue-500 text-white rounded-lg shadow-lg p-6">
            <h5 class="text-lg font-medium mb-2">Chỗ Trống (Realtime)</h5>
            <p class="text-3xl font-bold" id="realtime-free">-</p>
            <p class="text-xs mt-1" id="esp32-status">Đang kết nối...</p>
        </div>
        <div class="bg-purple-500 text-white rounded-lg shadow-lg p-6">
            <h5 class="text-lg font-medium mb-2">Tổng Số Chỗ</h5>
            <p class="text-3xl font-bold">{{ $totalSpots }}</p>
        </div>
    </div>

    <!-- Real-time Slot Status -->
    <div class="bg-white rounded-lg shadow-lg mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h4 class="text-xl font-semibold text-gray-900">Trạng Thái Slot Thực Tế</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="w-20 h-20 mx-auto rounded-lg flex items-center justify-center text-white font-bold text-lg" id="slot-1">
                        <span>Slot 1</span>
                    </div>
                    <p class="mt-2 text-sm" id="slot-1-status">Đang tải...</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 mx-auto rounded-lg flex items-center justify-center text-white font-bold text-lg" id="slot-2">
                        <span>Slot 2</span>
                    </div>
                    <p class="mt-2 text-sm" id="slot-2-status">Đang tải...</p>
                </div>
                <div class="text-center">
                    <div class="w-20 h-20 mx-auto rounded-lg flex items-center justify-center text-white font-bold text-lg" id="slot-3">
                        <span>Slot 3</span>
                    </div>
                    <p class="mt-2 text-sm" id="slot-3-status">Đang tải...</p>
                </div>
            </div>
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
                    <tbody class="bg-white divide-y divide-gray-200" id="parked-vehicles-table">
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
                    <tbody class="bg-white divide-y divide-gray-200" id="history-table">
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
    // Hàm cập nhật trạng thái tổng quan
    async function updateStatus() {
        try {
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

    // Hàm cập nhật danh sách xe đang trong bãi
    async function updateParkedVehicles() {
        try {
            const response = await fetch('/api/parked-vehicles');
            const data = await response.json();
            
            if (data.status === 'success') {
                const tableBody = document.getElementById('parked-vehicles-table');
                const currentCount = data.parked_vehicles.length;
                const previousCount = window.previousParkedCount || currentCount;
                
                if (data.parked_vehicles.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">Không có xe nào trong bãi.</td>
                        </tr>
                    `;
                } else {
                    tableBody.innerHTML = data.parked_vehicles.map(vehicle => `
                        <tr class="hover:bg-gray-50 transition-colors duration-300">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <code class="bg-gray-100 px-2 py-1 rounded text-sm">${vehicle.rfid_tag}</code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${vehicle.license_plate}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${vehicle.time_in}
                                <div class="text-xs text-blue-600">${vehicle.duration}</div>
                            </td>
                        </tr>
                    `).join('');
                }
                
                // Notify về thay đổi
                if (currentCount > previousCount) {
                    showNotification('🚗 Xe mới vào bãi!', 'success');
                    playSound('entry');
                } else if (currentCount < previousCount) {
                    showNotification('🚙 Xe ra khỏi bãi!', 'info');
                    playSound('exit');
                }
                
                window.previousParkedCount = currentCount;
                console.log('Parked vehicles updated!');
            }
        } catch (error) {
            console.error('Failed to fetch parked vehicles:', error);
        }
    }

    // Hàm cập nhật lịch sử xe ra vào
    async function updateHistory() {
        try {
            const response = await fetch('/api/recent-history');
            const data = await response.json();
            
            if (data.status === 'success') {
                const tableBody = document.getElementById('history-table');
                
                if (data.history.length === 0) {
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">Chưa có lịch sử.</td>
                        </tr>
                    `;
                } else {
                    tableBody.innerHTML = data.history.map(session => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <code class="bg-gray-100 px-2 py-1 rounded text-sm">${session.rfid_tag}</code>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${session.time_in}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${session.time_out}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">${session.formatted_cost}</td>
                        </tr>
                    `).join('');
                }
                
                console.log('History updated!');
            }
        } catch (error) {
            console.error('Failed to fetch history:', error);
        }
    }

    // Hàm cập nhật tất cả dữ liệu dashboard
    async function updateDashboard() {
        await updateStatus();
        await updateParkedVehicles();
        await updateHistory();
        await updateSlotStatus(); // Thêm cập nhật slot realtime
    }

    // Hàm cập nhật trạng thái slot realtime
    async function updateSlotStatus() {
        try {
            const response = await fetch('/api/slot-status');
            const data = await response.json();
            
            if (data.status === 'success') {
                // Cập nhật số liệu realtime
                document.getElementById('realtime-free').innerText = data.free_slots_realtime;
                
                // Cập nhật trạng thái ESP32
                const statusElement = document.getElementById('esp32-status');
                if (data.esp32_online) {
                    statusElement.innerText = 'ESP32 Online';
                    statusElement.className = 'text-xs mt-1 text-green-200';
                } else {
                    statusElement.innerText = 'ESP32 Offline';
                    statusElement.className = 'text-xs mt-1 text-red-200';
                }
                
                // Cập nhật từng slot
                for (let i = 0; i < 3; i++) {
                    const slotElement = document.getElementById(`slot-${i + 1}`);
                    const statusElement = document.getElementById(`slot-${i + 1}-status`);
                    
                    if (data.slots[i] === 1) {
                        // Slot trống
                        slotElement.className = 'w-20 h-20 mx-auto rounded-lg flex items-center justify-center text-white font-bold text-lg bg-green-500';
                        statusElement.innerText = 'Trống';
                        statusElement.className = 'mt-2 text-sm text-green-600';
                    } else {
                        // Slot có xe
                        slotElement.className = 'w-20 h-20 mx-auto rounded-lg flex items-center justify-center text-white font-bold text-lg bg-red-500';
                        statusElement.innerText = 'Có xe';
                        statusElement.className = 'mt-2 text-sm text-red-600';
                    }
                }
                
                console.log('Slot status updated!', data);
            }
        } catch (error) {
            console.error('Failed to fetch slot status:', error);
            
            // Hiển thị lỗi kết nối
            document.getElementById('realtime-free').innerText = '?';
            document.getElementById('esp32-status').innerText = 'Lỗi kết nối';
            document.getElementById('esp32-status').className = 'text-xs mt-1 text-red-200';
        }
    }    // Cập nhật ngay khi load trang
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Dashboard real-time updates started!');
    });

    // Cứ 3 giây cập nhật một lần (nhanh hơn để thấy real-time)
    setInterval(updateDashboard, 3000);

    // Thêm visual indicator để biết đang cập nhật
    let isUpdating = false;
    const originalUpdateDashboard = updateDashboard;
    updateDashboard = async function() {
        if (isUpdating) return;
        
        isUpdating = true;
        // Thêm loading indicator
        document.title = '🔄 Dashboard Bãi Xe';
        
        try {
            await originalUpdateDashboard();
        } finally {
            isUpdating = false;
            document.title = '📱 Dashboard Bãi Xe';
        }
    };

    // Notification system
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existing = document.querySelector('.notification');
        if (existing) existing.remove();
        
        const notification = document.createElement('div');
        notification.className = `notification fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 ease-in-out translate-x-full`;
        
        const colors = {
            success: 'bg-green-500 text-white',
            info: 'bg-blue-500 text-white',
            warning: 'bg-yellow-500 text-white',
            error: 'bg-red-500 text-white'
        };
        
        notification.className += ` ${colors[type] || colors.info}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            notification.style.transform = 'translateX(full)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    // Sound system (optional)
    function playSound(type) {
        try {
            // Create audio context for beep sounds
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            // Different frequencies for different events
            const frequencies = {
                entry: 800,  // Higher pitch for entry
                exit: 400    // Lower pitch for exit
            };
            
            oscillator.frequency.setValueAtTime(frequencies[type] || 600, audioContext.currentTime);
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (error) {
            // Sound not critical, just log error
            console.log('Sound not available:', error);
        }
    }

    // Initialize on page load
    window.previousParkedCount = {{ $parkedCount }};
</script>
@endpush