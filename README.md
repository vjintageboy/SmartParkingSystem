# Hệ Thống Quản Lý Bãi Xe Thông Minh (Smart Parking System)

Hệ thống quản lý bãi xe thông minh sử dụng Laravel backend và ESP32 với các cảm biến RFID, IR, LCD display và servo motor để tự động hóa việc ra vào bãi xe.

## 🚀 Tính Năng Chính

- **RFID Access Control**: Quản lý ra vào bằng thẻ RFID
- **Tự động tính phí**: Tính phí dựa trên thời gian đỗ xe (5,000đ/giờ)
- **Hiển thị LCD**: Thông báo trạng thái và hướng dẫn cho người dùng
- **Audio Feedback**: Buzzer phát âm thanh khi quẹt thẻ và thông báo trạng thái
- **API RESTful**: Backend Laravel cung cấp API cho ESP32
- **Real-time Status**: Theo dõi trạng thái bãi xe real-time
- **Dual Mode**: Chế độ Entry/Exit trên cùng một thiết bị ESP32

## 📋 Yêu Cầu Hệ Thống

### Backend (Laravel)
- PHP >= 8.1
- Composer
- SQLite/MySQL
- Laravel 11.x

### Hardware (ESP32)
- ESP32 Dev Board
- MFRC522 RFID Reader
- LCD 16x2 với I2C module
- 2x Servo Motors (cho barrier)
- 2x IR Sensors
- 2x LEDs (Green, Red)
- 1x Buzzer (Active/Passive)
- 1x Push Button
- Breadboard và dây kết nối

## 🛠️ Cài Đặt Backend (Laravel)

### 1. Clone Repository
```bash
git clone <repository-url>
cd parking-system
```

### 2. Cài đặt Dependencies
```bash
composer install
npm install
```

### 3. Configuration
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Database Setup
```bash
php artisan migrate
php artisan db:seed
```

### 5. Chạy Server
```bash
php artisan serve
```

Server sẽ chạy tại `http://127.0.0.1:8000`

## 🔧 Cài Đặt ESP32

### 1. Cài đặt Arduino IDE
- Tải và cài đặt [Arduino IDE](https://www.arduino.cc/en/software)
- Thêm ESP32 board package:
  - File → Preferences → Additional Board Manager URLs
  - Thêm: `https://dl.espressif.com/dl/package_esp32_index.json`
  - Tools → Board → Boards Manager → Tìm "ESP32" và cài đặt

### 2. Cài đặt Libraries
Trong Arduino IDE, vào Library Manager (Ctrl+Shift+I) và cài đặt:
- **WiFi** (Built-in với ESP32)
- **HTTPClient** (Built-in với ESP32)
- **ArduinoJson** by Benoit Blanchon
- **MFRC522** by GithubCommunity
- **LiquidCrystal I2C** by Frank de Brabander

### 3. Sơ Đồ Kết Nối

#### RFID Module (MFRC522)
```
ESP32    →    MFRC522
3.3V     →    3.3V
GND      →    GND
Pin 21   →    SDA/SS
Pin 22   →    RST
Pin 23   →    MOSI
Pin 19   →    MISO
Pin 18   →    SCK
```

#### LCD 16x2 với I2C
```
ESP32    →    LCD I2C
3.3V     →    VCC
GND      →    GND
Pin 21   →    SDA
Pin 22   →    SCL
```

#### Servo Motors (Barriers)
```
ESP32    →    Servo Entry    →    Servo Exit
5V       →    VCC           →    VCC
GND      →    GND           →    GND
Pin 18   →    Signal        →    
Pin 19   →                 →    Signal
```

#### IR Sensors
```
ESP32    →    IR Entry    →    IR Exit
3.3V     →    VCC        →    VCC
GND      →    GND        →    GND
Pin 25   →    OUT        →    
Pin 26   →               →    OUT
```

#### LEDs, Buzzer và Button
```
ESP32    →    Component
Pin 2    →    Green LED (+) → GND via 220Ω resistor
Pin 4    →    Red LED (+) → GND via 220Ω resistor
Pin 5    →    Buzzer (+) → GND
Pin 27   →    Button → GND (với pull-up internal)
```

### 4. Cấu Hình Code ESP32

Mở file `esp32_parking_system.ino` và cập nhật:

```cpp
// WiFi credentials
const char* ssid = "TEN_WIFI_CUA_BAN";
const char* password = "MAT_KHAU_WIFI";

// Laravel API server - Thay bằng IP của máy chạy Laravel
const char* serverURL = "http://192.168.1.100:8000";

// LCD I2C address - Có thể là 0x27 hoặc 0x3F
LiquidCrystal_I2C lcd(0x27, 16, 2);
```

### 5. Upload Code
- Kết nối ESP32 với máy tính qua USB
- Chọn board: ESP32 Dev Module
- Chọn Port tương ứng
- Click Upload

## 📡 API Endpoints

### 1. Vehicle Entry
```http
POST /api/entry
Content-Type: application/json

{
    "rfid_tag": "A1B2C3D4"
}
```

**Response Success:**
```json
{
    "status": "success",
    "message": "Entry Granted"
}
```

**Response Error:**
```json
{
    "status": "error",
    "message": "Invalid Card"
}
```

### 2. Vehicle Exit
```http
POST /api/exit
Content-Type: application/json

{
    "rfid_tag": "A1B2C3D4"
}
```

**Response Success:**
```json
{
    "status": "success",
    "message": "Exit Granted",
    "cost": 10000,
    "duration_minutes": 125
}
```

### 3. Parking Status
```http
GET /api/status
```

**Response:**
```json
{
    "total_spots": 100,
    "parked_count": 15,
    "available_spots": 85
}
```

## 🧪 Testing API

### Sử dụng Script Test
```bash
# Test đầy đủ
chmod +x test_api.sh
./test_api.sh

# Test đơn giản với jq formatting
chmod +x api_test_simple.sh
./api_test_simple.sh
```

### Test Manual với cURL
```bash
# Check status
curl -X GET http://127.0.0.1:8000/api/status

# Vehicle entry
curl -X POST http://127.0.0.1:8000/api/entry \
  -H "Content-Type: application/json" \
  -d '{"rfid_tag":"A1B2C3D4"}'

# Vehicle exit
curl -X POST http://127.0.0.1:8000/api/exit \
  -H "Content-Type: application/json" \
  -d '{"rfid_tag":"A1B2C3D4"}'
```

## 🗄️ Database Structure

### Vehicles Table
```sql
CREATE TABLE vehicles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    rfid_tag VARCHAR(255) UNIQUE NOT NULL,
    license_plate VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Parking Sessions Table
```sql
CREATE TABLE parking_sessions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id BIGINT,
    time_in TIMESTAMP NOT NULL,
    time_out TIMESTAMP NULL,
    cost DECIMAL(10,2) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
);
```

## 🔄 Workflow Hệ Thống

### Entry Process
1. ESP32 đọc thẻ RFID (1 tiếng beep xác nhận)
2. Gửi API request đến Laravel `/api/entry`
3. Laravel kiểm tra thẻ trong database
4. Nếu valid: Tạo parking session, trả về success
5. ESP32 nhận response, mở barrier, hiển thị thông báo (2 tiếng beep dài - thành công)

### Exit Process
1. ESP32 đọc thẻ RFID trong chế độ Exit (1 tiếng beep xác nhận)
2. Gửi API request đến Laravel `/api/exit`
3. Laravel tìm active session, tính thời gian và phí
4. Cập nhật session với thời gian ra và cost
5. ESP32 nhận response, hiển thị phí, mở barrier (2 tiếng beep dài - thành công)

### Audio Feedback System
- **Quẹt thẻ**: 1 tiếng beep ngắn (100ms) - xác nhận đọc thẻ
- **Thành công**: 2 tiếng beep dài (200ms) - entry/exit granted
- **Lỗi thẻ**: 3 tiếng beep ngắn (100ms) - thẻ không hợp lệ
- **Lỗi kết nối**: 5 tiếng beep nhanh (50ms) - không kết nối được server

## 🚨 Troubleshooting

### ESP32 không kết nối WiFi
- Kiểm tra SSID và password
- Đảm bảo ESP32 trong phạm vi WiFi
- Kiểm tra Serial Monitor để debug

### LCD không hiển thị
- Kiểm tra địa chỉ I2C (thử 0x27 hoặc 0x3F)
- Kiểm tra kết nối SDA/SCL
- Chạy I2C scanner để tìm địa chỉ

### RFID không đọc được
- Kiểm tra kết nối SPI
- Đảm bảo thẻ ở gần reader (< 3cm)
- Kiểm tra power supply ổn định

### API trả về lỗi
- Kiểm tra Laravel server đang chạy
- Verify database có data mẫu
- Kiểm tra IP address trong ESP32 code

## 📦 Sample Data

Hệ thống đi kèm với sample data:
```sql
INSERT INTO vehicles (rfid_tag, license_plate, is_active) VALUES
('A1B2C3D4', '29A-12345', true),
('E5F6G7H8', '30B-67890', true),
('I9J0K1L2', '31C-24680', false);
```

## 🔧 Customization

### Thay đổi giá tiền
Trong `ParkingController.php`:
```php
// Thay đổi từ 5000đ/giờ thành giá khác
$cost = ceil($durationInMinutes / 60) * 10000; // 10,000đ/giờ
```

### Thay đổi tổng số chỗ đỗ
```php
$totalSpots = 50; // Thay đổi từ 100 thành 50
```

## 📄 License

Dự án này được phát hành dưới [MIT License](https://opensource.org/licenses/MIT).

## 👥 Contributors

- Developer: [Your Name]
- Hardware Setup: ESP32 + RFID + LCD + Sensors
- Backend: Laravel 11 + SQLite
