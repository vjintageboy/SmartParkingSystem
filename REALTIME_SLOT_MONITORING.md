# 🔴 Real-Time Slot Monitoring System

## 🎯 Vấn Đề Được Giải Quyết

Trước đây hệ thống chỉ theo dõi xe qua **database** (RFID entries/exits), nhưng không biết xe thực sự đỗ ở đâu. Giờ đây có **cảm biến IR slot** để theo dõi thực tế.

### 📊 Database vs 🔴 Realtime Sensors

| Aspect | Database (RFID) | Sensors (IR) |
|--------|----------------|--------------|
| **Tracking** | Logical entries/exits | Physical presence |
| **Data Source** | RFID card scans | IR slot sensors |
| **Accuracy** | Who entered/exited | What slots occupied |
| **Limitations** | Can't detect illegal parking | Can't identify vehicles |

## 🚀 New Features

### 1. **Dual Statistics Display**
Dashboard hiện hiển thị cả hai loại thông tin:
- **Database Stats**: Xe đã check-in qua RFID
- **Realtime Stats**: Xe thực tế đang đỗ (từ cảm biến)

### 2. **Individual Slot Status**
Hiển thị từng slot với:
- 🟢 **Green**: Slot trống (IR sensor = HIGH)
- 🔴 **Red**: Slot có xe (IR sensor = LOW)
- Real-time visual feedback

### 3. **ESP32 Connection Status**
- ✅ **Online**: ESP32 gửi data < 30 giây
- ❌ **Offline**: Không nhận data > 30 giây

## 🔧 Technical Implementation

### ESP32 Code Changes

```cpp
// Gửi slot status mỗi 5 giây hoặc khi có thay đổi
void sendSlotStatusToServer() {
    DynamicJsonDocument doc(1024);
    JsonArray slotsArray = doc.createNestedArray("slots");
    for (int i = 0; i < 3; i++) {
        slotsArray.add(slotStatus[i] == HIGH ? 1 : 0);
    }
    doc["free_slots"] = getFreeSlotCount();
    doc["timestamp"] = millis();
    
    // POST to /api/update-slots
}
```

### Laravel API Endpoints

```php
// POST /api/update-slots - ESP32 gửi trạng thái slot
public function updateSlotStatus(Request $request) {
    $slots = $request->input('slots'); // [1,0,1]
    cache(['slot_status' => $slots], now()->addSeconds(30));
}

// GET /api/slot-status - Dashboard lấy trạng thái
public function getSlotStatus() {
    return [
        'slots' => cache('slot_status', [1,1,1]),
        'free_slots_realtime' => $freeSlots,
        'esp32_online' => $isOnline
    ];
}
```

### Dashboard JavaScript

```javascript
// Cập nhật slot status mỗi 3 giây
async function updateSlotStatus() {
    const data = await fetch('/api/slot-status');
    
    // Cập nhật số liệu realtime
    document.getElementById('realtime-free').innerText = data.free_slots_realtime;
    
    // Cập nhật màu từng slot
    for (let i = 0; i < 3; i++) {
        const slotElement = document.getElementById(`slot-${i + 1}`);
        slotElement.className = data.slots[i] === 1 
            ? 'bg-green-500' // Trống
            : 'bg-red-500';  // Có xe
    }
}
```

## 🎮 Use Cases

### Case 1: **Xe Đỗ Trái Phép**
- **Database**: 0 xe (không ai check-in)
- **Sensors**: 1 xe (có người đỗ trái phép)
- **Action**: Phát hiện vi phạm

### Case 2: **Xe Chạy Trốn**
- **Database**: 2 xe (đã check-in)
- **Sensors**: 1 xe (1 xe bỏ chạy)
- **Action**: Phát hiện theft/evasion

### Case 3: **Normal Operation**
- **Database**: 2 xe
- **Sensors**: 2 xe
- **Status**: ✅ All good

## 📈 Business Benefits

### 🔍 **Detection Capabilities**
- Illegal parking detection
- Payment evasion detection
- Overcapacity situations
- System integrity verification

### 📊 **Improved Analytics**
- Physical vs logical occupancy
- Peak usage patterns by slot
- System reliability metrics
- Revenue leakage prevention

### 👥 **User Experience**
- Accurate slot availability
- Visual slot status
- Real-time updates
- Better space utilization

## 🧪 Testing

### Manual Testing
```bash
# Test ESP32 slot updates
./test_slot_realtime.sh

# Demo database vs realtime difference
./demo_database_vs_realtime.sh
```

### Expected Results
1. Dashboard shows both database and realtime stats
2. Slot colors change based on sensor data
3. ESP32 status indicates connection health
4. Discrepancies highlight potential issues

## 🔄 Data Flow

```
ESP32 IR Sensors → HTTP POST /api/update-slots → Laravel Cache → Dashboard API → Real-time UI Updates
     ↓                                                                              ↑
Slot Detection                                                            Every 3 seconds
   (Physical)                                                               Auto-refresh
```

## 🎯 Future Enhancements

1. **Alert System**: Notify when database ≠ sensors
2. **Historical Analysis**: Track patterns over time
3. **Slot Reservation**: Allow pre-booking specific slots
4. **Mobile App**: Real-time slot view for customers
5. **Camera Integration**: Visual verification of slots

---

**Result**: Hệ thống giờ đây có thể phân biệt giữa **logical state** (database) và **physical state** (sensors), giúp phát hiện các vấn đề như đỗ xe trái phép, chạy trốn không thanh toán, và đảm bảo tính chính xác của dữ liệu.
