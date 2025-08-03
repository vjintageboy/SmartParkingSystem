#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ESP32Servo.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// ==== Cấu hình WiFi và API ====
const char* ssid = "TP-Link_F7E1";
const char* password = "85826021";
const char* serverURL = "http://192.168.0.102:8000"; // IP thực tế của máy chạy Laravel

// ==== Chân RFID (tránh SDA LCD) ====
#define RST_PIN 4
#define SS_PIN 5

// ==== Ngoại vi ====
#define BUZZER_PIN 2
#define BUTTON_PIN 27
#define SERVO_ENTRY 15
#define SERVO_EXIT 13

// ==== Cảm biến IR Slot ====
#define SLOT1_PIN 33
#define SLOT2_PIN 32
#define SLOT3_PIN 34

// ==== Cảm biến IR Entry/Exit ====
#define ENTRY_SENSOR 25
#define EXIT_SENSOR 26

// ==== Khai báo đối tượng ====
LiquidCrystal_I2C lcd(0x27, 16, 2);
MFRC522 mfrc522(SS_PIN, RST_PIN);
Servo servoEntry;
Servo servoExit;

bool modeEntry = true;
int lastButtonState = HIGH;

// Trạng thái slot (1 = trống, 0 = có xe)
bool slotStatus[3] = {1, 1, 1};
bool prevStatus[3] = {1, 1, 1};
int prevFreeSlots = -1;
unsigned long lastSlotUpdate = 0;
const unsigned long SLOT_UPDATE_INTERVAL = 5000; // Gửi cập nhật slot mỗi 5 giây

void setup() {
  Serial.begin(115200);
  SPI.begin();
  mfrc522.PCD_Init();

  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(BUTTON_PIN, INPUT_PULLUP);
  pinMode(SLOT1_PIN, INPUT);
  pinMode(SLOT2_PIN, INPUT);
  pinMode(SLOT3_PIN, INPUT);
  pinMode(ENTRY_SENSOR, INPUT);
  pinMode(EXIT_SENSOR, INPUT);

  servoEntry.attach(SERVO_ENTRY);
  servoExit.attach(SERVO_EXIT);
  servoEntry.write(0);
  servoExit.write(0);

  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0);
  lcd.print("Smart Parking");
  lcd.setCursor(0, 1);
  lcd.print("System Starting...");
  delay(2000);

  // Kết nối WiFi
  connectWiFi();

  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Mode: ENTRY");
  lcd.setCursor(0, 1);
  lcd.print("Waiting card...");
}

// ====== Chức năng WiFi và API ======
void connectWiFi() {
  Serial.println("=== WiFi CONNECTION START ===");
  Serial.print("SSID: ");
  Serial.println(ssid);
  Serial.print("Server URL: ");
  Serial.println(serverURL);
  
  WiFi.begin(ssid, password);
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Connecting WiFi");
  
  int dots = 0;
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
    lcd.setCursor(dots % 16, 1);
    lcd.print(".");
    dots++;
    if (dots > 30) { // Timeout sau 15 giây
      Serial.println();
      Serial.println("WiFi connection FAILED!");
      Serial.print("Final status: ");
      Serial.println(WiFi.status());
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("WiFi Failed!");
      delay(2000);
      return;
    }
  }
  
  Serial.println();
  Serial.println("WiFi connected successfully!");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());
  Serial.print("Signal strength: ");
  Serial.println(WiFi.RSSI());
  
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("WiFi Connected!");
  lcd.setCursor(0, 1);
  lcd.print(WiFi.localIP());
  delay(2000);
}

bool sendEntryRequest(String rfidTag) {
  Serial.println("=== ENTRY REQUEST START ===");
  Serial.print("RFID Tag: ");
  Serial.println(rfidTag);
  
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("ERROR: WiFi not connected!");
    Serial.print("WiFi status: ");
    Serial.println(WiFi.status());
    return false;
  }
  Serial.println("WiFi connected OK");
  
  HTTPClient http;
  String url = String(serverURL) + "/api/entry";
  Serial.print("API URL: ");
  Serial.println(url);
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  // Tạo JSON payload
  DynamicJsonDocument doc(1024);
  doc["rfid_tag"] = rfidTag;
  String jsonString;
  serializeJson(doc, jsonString);
  Serial.print("JSON Payload: ");
  Serial.println(jsonString);
  
  Serial.println("Sending HTTP POST...");
  int httpResponseCode = http.POST(jsonString);
  String response = http.getString();
  http.end();
  
  Serial.print("HTTP Response Code: ");
  Serial.println(httpResponseCode);
  Serial.print("Response Body: ");
  Serial.println(response);
  
  if (httpResponseCode == 200) {
    // Parse response
    DynamicJsonDocument responseDoc(1024);
    DeserializationError error = deserializeJson(responseDoc, response);
    
    if (error) {
      Serial.print("JSON Parse Error: ");
      Serial.println(error.c_str());
      return false;
    }
    
    String status = responseDoc["status"];
    Serial.print("API Status: ");
    Serial.println(status);
    
    if (status == "success") {
      Serial.println("Entry SUCCESS!");
      // Kiểm tra nếu là thẻ mới
      bool isNewCard = responseDoc["vehicle_info"]["is_new_card"];
      if (isNewCard) {
        Serial.println("New card registered!");
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Welcome New User");
        lcd.setCursor(0, 1);
        lcd.print("Card Registered!");
        delay(2000);
      }
      return true;
    } else {
      Serial.print("API Error - Status: ");
      Serial.println(status);
      String message = responseDoc["message"];
      Serial.print("Error Message: ");
      Serial.println(message);
    }
  } else {
    Serial.print("HTTP Error - Code: ");
    Serial.println(httpResponseCode);
    if (httpResponseCode > 0) {
      Serial.print("Error Response: ");
      Serial.println(response);
    }
  }
  
  Serial.println("=== ENTRY REQUEST FAILED ===");
  return false;
}

bool sendExitRequest(String rfidTag) {
  Serial.println("=== EXIT REQUEST START ===");
  Serial.print("RFID Tag: ");
  Serial.println(rfidTag);
  
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("ERROR: WiFi not connected!");
    Serial.print("WiFi status: ");
    Serial.println(WiFi.status());
    return false;
  }
  Serial.println("WiFi connected OK");
  
  HTTPClient http;
  String url = String(serverURL) + "/api/exit";
  Serial.print("API URL: ");
  Serial.println(url);
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  // Tạo JSON payload
  DynamicJsonDocument doc(1024);
  doc["rfid_tag"] = rfidTag;
  String jsonString;
  serializeJson(doc, jsonString);
  Serial.print("JSON Payload: ");
  Serial.println(jsonString);
  
  Serial.println("Sending HTTP POST...");
  int httpResponseCode = http.POST(jsonString);
  String response = http.getString();
  http.end();
  
  Serial.print("HTTP Response Code: ");
  Serial.println(httpResponseCode);
  Serial.print("Response Body: ");
  Serial.println(response);
  
  if (httpResponseCode == 200) {
    // Parse response để lấy cost
    DynamicJsonDocument responseDoc(1024);
    DeserializationError error = deserializeJson(responseDoc, response);
    
    if (error) {
      Serial.print("JSON Parse Error: ");
      Serial.println(error.c_str());
      return false;
    }
    
    String status = responseDoc["status"];
    Serial.print("API Status: ");
    Serial.println(status);
    
    if (status == "success") {
      Serial.println("Exit SUCCESS!");
      int cost = responseDoc["cost"];
      int duration = responseDoc["duration_minutes"];
      int freeMinutes = responseDoc["free_minutes"];
      int billableMinutes = responseDoc["billable_minutes"];
      
      Serial.print("Cost: ");
      Serial.println(cost);
      Serial.print("Duration: ");
      Serial.println(duration);
      
      // Hiển thị thông tin thanh toán chi tiết
      lcd.clear();
      lcd.setCursor(0, 0);
      if (cost == 0) {
        lcd.print("FREE PARKING!");
        lcd.setCursor(0, 1);
        lcd.print("Time: ");
        lcd.print(duration);
        lcd.print(" min");
      } else {
        lcd.print("Cost: ");
        lcd.print(cost);
        lcd.print(" VND");
        lcd.setCursor(0, 1);
        lcd.print("Time: ");
        lcd.print(duration);
        lcd.print("min");
      }
      delay(3000);
      
      // Hiển thị chi tiết phí
      if (freeMinutes > 0) {
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Free: ");
        lcd.print(freeMinutes);
        lcd.print("min");
        lcd.setCursor(0, 1);
        lcd.print("Paid: ");
        lcd.print(billableMinutes);
        lcd.print("min");
        delay(2000);
      }
      
      return true;
    } else {
      Serial.print("API Error - Status: ");
      Serial.println(status);
      String message = responseDoc["message"];
      Serial.print("Error Message: ");
      Serial.println(message);
    }
  } else {
    Serial.print("HTTP Error - Code: ");
    Serial.println(httpResponseCode);
    if (httpResponseCode > 0) {
      Serial.print("Error Response: ");
      Serial.println(response);
    }
  }
  
  Serial.println("=== EXIT REQUEST FAILED ===");
  return false;
}

// ====== Chức năng ======
void switchMode() {
  modeEntry = !modeEntry;
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Mode: ");
  lcd.print(modeEntry ? "ENTRY" : "EXIT");
  lcd.setCursor(0, 1);
  lcd.print("Waiting card...");
  Serial.println(modeEntry ? "Mode switched: ENTRY" : "Mode switched: EXIT");
}

void updateSlots() {
  slotStatus[0] = digitalRead(SLOT1_PIN);
  slotStatus[1] = digitalRead(SLOT2_PIN);
  slotStatus[2] = digitalRead(SLOT3_PIN);
}

int getFreeSlotCount() {
  int count = 0;
  for (int i = 0; i < 3; i++) if (slotStatus[i] == HIGH) count++;
  return count;
}

// Gửi thông tin slot realtime lên server
void sendSlotStatusToServer() {
  if (WiFi.status() != WL_CONNECTED) return;
  
  HTTPClient http;
  String url = String(serverURL) + "/api/update-slots";
  
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  
  // Tạo JSON với trạng thái từng slot
  DynamicJsonDocument doc(1024);
  JsonArray slotsArray = doc.createNestedArray("slots");
  for (int i = 0; i < 3; i++) {
    slotsArray.add(slotStatus[i] == HIGH ? 1 : 0); // 1 = trống, 0 = có xe
  }
  doc["free_slots"] = getFreeSlotCount();
  doc["timestamp"] = millis();
  
  String jsonString;
  serializeJson(doc, jsonString);
  
  Serial.println("=== SENDING SLOT STATUS ===");
  Serial.print("Slots: [");
  for (int i = 0; i < 3; i++) {
    Serial.print(slotStatus[i] == HIGH ? "1" : "0");
    if (i < 2) Serial.print(",");
  }
  Serial.println("]");
  Serial.print("Free count: ");
  Serial.println(getFreeSlotCount());
  Serial.print("JSON: ");
  Serial.println(jsonString);
  
  int httpResponseCode = http.POST(jsonString);
  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.print("Server response: ");
    Serial.println(response);
  } else {
    Serial.print("HTTP Error: ");
    Serial.println(httpResponseCode);
  }
  
  http.end();
  Serial.println("=== SLOT STATUS SENT ===");
}

void displaySlotsSmart() {
  int freeSlots = getFreeSlotCount();
  bool changed = (freeSlots != prevFreeSlots);
  for (int i = 0; i < 3; i++) if (slotStatus[i] != prevStatus[i]) changed = true;

  if (changed) {
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Free: ");
    lcd.print(freeSlots);
    lcd.setCursor(0, 1);
    lcd.print("S1:");
    lcd.print(slotStatus[0]);
    lcd.print(" S2:");
    lcd.print(slotStatus[1]);
    lcd.print(" S3:");
    lcd.print(slotStatus[2]);

    prevFreeSlots = freeSlots;
    for (int i = 0; i < 3; i++) prevStatus[i] = slotStatus[i];
  }
}

void openBarrier() {
  if (modeEntry) servoEntry.write(90);
  else servoExit.write(90);
}

void closeBarrier() {
  if (modeEntry) servoEntry.write(0);
  else servoExit.write(0);
}

void waitForCarToPass() {
  unsigned long startTime = millis();
  int sensorPin = modeEntry ? ENTRY_SENSOR : EXIT_SENSOR;
  bool detected = false;

  // Chờ xe đến (đúng cảm biến tương ứng)
  while (millis() - startTime < 5000) {
    if (digitalRead(sensorPin) == LOW) { // xe che cảm biến đúng hướng
      detected = true;
      break;
    }
    delay(10);
  }

  // Chờ xe đi qua hết cảm biến đúng hướng
  if (detected) {
    while (digitalRead(sensorPin) == LOW) {
      delay(20);
    }
    delay(2000);
  }

  // Sau tối đa 5 giây, đóng barrier kể cả không thấy xe (tránh mở mãi)
  closeBarrier();
}


void loop() {
  // 1️⃣ Xử lý nút chuyển chế độ
  int reading = digitalRead(BUTTON_PIN);
  if (reading == LOW && lastButtonState == HIGH) {
    switchMode();
    delay(300);
  }
  lastButtonState = reading;

  // 2️⃣ Cập nhật và hiển thị slot realtime
  updateSlots();
  displaySlotsSmart();
  
  // 3️⃣ Gửi trạng thái slot lên server định kỳ hoặc khi có thay đổi
  bool slotChanged = false;
  for (int i = 0; i < 3; i++) {
    if (slotStatus[i] != prevStatus[i]) {
      slotChanged = true;
      break;
    }
  }
  
  // Gửi khi có thay đổi hoặc mỗi 5 giây
  if (slotChanged || (millis() - lastSlotUpdate > SLOT_UPDATE_INTERVAL)) {
    sendSlotStatusToServer();
    lastSlotUpdate = millis();
  }

  // 4️⃣ Đọc thẻ RFID
  if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
    String uidString = "";
    for (byte i = 0; i < mfrc522.uid.size; i++) {
      if (mfrc522.uid.uidByte[i] < 0x10) uidString += "0";
      uidString += String(mfrc522.uid.uidByte[i], HEX);
    }
    uidString.toUpperCase();

    Serial.println("===============================");
    Serial.print("RFID Card Detected! UID: ");
    Serial.println(uidString);
    Serial.print("Current Mode: ");
    Serial.println(modeEntry ? "ENTRY" : "EXIT");
    Serial.print("WiFi Status: ");
    Serial.println(WiFi.status() == WL_CONNECTED ? "Connected" : "Disconnected");
    if (WiFi.status() == WL_CONNECTED) {
      Serial.print("IP: ");
      Serial.println(WiFi.localIP());
    }
    Serial.println("===============================");
    
    tone(BUZZER_PIN, 2000, 200);

    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print(modeEntry ? "ENTRY" : "EXIT");
    lcd.setCursor(0, 1);
    lcd.print("Checking...");

    bool apiResult = false;
    
    if (modeEntry) {
      // Kiểm tra slot trống trước khi gửi API
      int freeSlots = getFreeSlotCount();
      Serial.print("Free slots available: ");
      Serial.println(freeSlots);
      
      if (freeSlots == 0) {
        Serial.println("No free slots available!");
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("No Slot Free!");
        tone(BUZZER_PIN, 1000, 800);
        delay(2000);
      } else {
        Serial.println("Free slots available, sending entry request...");
        // Gửi entry request đến Laravel
        apiResult = sendEntryRequest(uidString);
      }
    } else {
      Serial.println("Sending exit request...");
      // Gửi exit request đến Laravel
      apiResult = sendExitRequest(uidString);
    }

    Serial.print("API Result: ");
    Serial.println(apiResult ? "SUCCESS" : "FAILED");

    if (apiResult) {
      // API thành công - mở barrier
      Serial.println("Opening barrier...");
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print(modeEntry ? "Entry Granted" : "Exit Granted");
      lcd.setCursor(0, 1);
      lcd.print("UID: ");
      lcd.print(uidString.substring(0, 8));
      
      openBarrier();
      Serial.println("Waiting for car to pass...");
      waitForCarToPass();
      Serial.println("Barrier closed.");
      tone(BUZZER_PIN, 1500, 300);
    } else {
      // API thất bại hoặc không có WiFi
      Serial.println("Access DENIED!");
      lcd.clear();
      lcd.setCursor(0, 0);
      if (WiFi.status() != WL_CONNECTED) {
        Serial.println("Reason: WiFi not connected");
        lcd.print("WiFi Error!");
      } else {
        Serial.println("Reason: API returned error or invalid response");
        lcd.print("Access Denied!");
      }
      lcd.setCursor(0, 1);
      lcd.print("Invalid Card");
      tone(BUZZER_PIN, 800, 1000);
      delay(2000);
    }

    delay(1000);
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Mode: ");
    lcd.print(modeEntry ? "ENTRY" : "EXIT");
    lcd.setCursor(0, 1);
    lcd.print("Waiting card...");
    
    mfrc522.PICC_HaltA();
    Serial.println("Card processing completed.");
    Serial.println("===============================");
  }
}
