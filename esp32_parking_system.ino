#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <SPI.h>
#include <MFRC522.h>
#include <LiquidCrystal_I2C.h>

// WiFi credentials
const char* ssid = "TP-Link_F7E1";
const char* password = "85826021";

// Laravel API server
const char* serverURL = "http://192.168.0.101:8000"; // Thay bằng IP của máy Laravel

// RFID setup
#define RST_PIN 22
#define SS_PIN 21
MFRC522 mfrc522(SS_PIN, RST_PIN);

// LCD setup (I2C)
LiquidCrystal_I2C lcd(0x27, 16, 2); // Địa chỉ I2C có thể là 0x3F

// Servo/Relay pins cho barrier
#define ENTRY_BARRIER_PIN 18
#define EXIT_BARRIER_PIN 19

// IR sensors
#define ENTRY_IR_PIN 25
#define EXIT_IR_PIN 26

// LEDs
#define GREEN_LED_PIN 2
#define RED_LED_PIN 4

// Buzzer
#define BUZZER_PIN 5

// Mode selection
#define MODE_BUTTON_PIN 27
bool isEntryMode = true; // true = Entry, false = Exit

void setup() {
  Serial.begin(9600);
  
  // Initialize pins
  pinMode(ENTRY_BARRIER_PIN, OUTPUT);
  pinMode(EXIT_BARRIER_PIN, OUTPUT);
  pinMode(ENTRY_IR_PIN, INPUT);
  pinMode(EXIT_IR_PIN, INPUT);
  pinMode(GREEN_LED_PIN, OUTPUT);
  pinMode(RED_LED_PIN, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(MODE_BUTTON_PIN, INPUT_PULLUP);
  
  // Close barriers initially
  digitalWrite(ENTRY_BARRIER_PIN, LOW);
  digitalWrite(EXIT_BARRIER_PIN, LOW);
  digitalWrite(BUZZER_PIN, LOW);
  
  // Initialize SPI bus
  SPI.begin();
  mfrc522.PCD_Init();
  
  // Initialize LCD
  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0);
  lcd.print("Smart Parking");
  lcd.setCursor(0, 1);
  lcd.print("Starting...");
  
  // Connect to WiFi
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(1000);
    Serial.println("Connecting to WiFi...");
    lcd.setCursor(0, 1);
    lcd.print("Connecting WiFi.");
  }
  
  Serial.println("WiFi connected!");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
  
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("WiFi Connected");
  lcd.setCursor(0, 1);
  lcd.print(WiFi.localIP());
  delay(2000);
  
  updateDisplay();
}

void loop() {
  // Check mode button
  if (digitalRead(MODE_BUTTON_PIN) == LOW) {
    delay(200); // Debounce
    isEntryMode = !isEntryMode;
    updateDisplay();
    while (digitalRead(MODE_BUTTON_PIN) == LOW); // Wait for release
  }
  
  // Check for RFID card
  if (mfrc522.PICC_IsNewCardPresent() && mfrc522.PICC_ReadCardSerial()) {
    String rfidTag = "";
    for (byte i = 0; i < mfrc522.uid.size; i++) {
      rfidTag += String(mfrc522.uid.uidByte[i] < 0x10 ? "0" : "");
      rfidTag += String(mfrc522.uid.uidByte[i], HEX);
    }
    rfidTag.toUpperCase();
    
    Serial.println("RFID Tag: " + rfidTag);
    
    // Play beep sound when card is detected
    playBeep(1, 100); // 1 beep, 100ms duration
    
    if (isEntryMode) {
      handleEntry(rfidTag);
    } else {
      handleExit(rfidTag);
    }
    
    mfrc522.PICC_HaltA();
    delay(2000); // Prevent multiple reads
    updateDisplay();
  }
  
  delay(100);
}

void handleEntry(String rfidTag) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Processing...");
  
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(String(serverURL) + "/api/entry");
    http.addHeader("Content-Type", "application/json");
    
    // Create JSON payload
    DynamicJsonDocument doc(1024);
    doc["rfid_tag"] = rfidTag;
    String jsonString;
    serializeJson(doc, jsonString);
    
    int httpResponseCode = http.POST(jsonString);
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.println("Response: " + response);
      
      // Parse response
      DynamicJsonDocument responseDoc(1024);
      deserializeJson(responseDoc, response);
      
      String status = responseDoc["status"];
      String message = responseDoc["message"];
      
      lcd.clear();
      lcd.setCursor(0, 0);
      
      if (status == "success") {
        lcd.print("Entry Granted");
        lcd.setCursor(0, 1);
        lcd.print("Welcome!");
        
        // Open entry barrier
        openBarrier(ENTRY_BARRIER_PIN);
        blinkLED(GREEN_LED_PIN, 3);
        playBeep(2, 200); // Success: 2 beeps
      } else {
        lcd.print("Access Denied");
        lcd.setCursor(0, 1);
        lcd.print(message.substring(0, 16)); // Limit to LCD width
        blinkLED(RED_LED_PIN, 3);
        playBeep(3, 100); // Error: 3 short beeps
      }
    } else {
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("Connection Error");
      blinkLED(RED_LED_PIN, 5);
      playBeep(5, 50); // Connection error: 5 fast beeps
    }
    
    http.end();
  }
  
  delay(3000);
}

void handleExit(String rfidTag) {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Processing...");
  
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(String(serverURL) + "/api/exit");
    http.addHeader("Content-Type", "application/json");
    
    // Create JSON payload
    DynamicJsonDocument doc(1024);
    doc["rfid_tag"] = rfidTag;
    String jsonString;
    serializeJson(doc, jsonString);
    
    int httpResponseCode = http.POST(jsonString);
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.println("Response: " + response);
      
      // Parse response
      DynamicJsonDocument responseDoc(1024);
      deserializeJson(responseDoc, response);
      
      String status = responseDoc["status"];
      String message = responseDoc["message"];
      
      lcd.clear();
      lcd.setCursor(0, 0);
      
      if (status == "success") {
        int cost = responseDoc["cost"];
        int duration = responseDoc["duration_minutes"];
        
        lcd.print("Exit Granted");
        lcd.setCursor(0, 1);
        lcd.print("Cost: " + String(cost) + "VND");
        
        // Open exit barrier
        openBarrier(EXIT_BARRIER_PIN);
        blinkLED(GREEN_LED_PIN, 3);
        playBeep(2, 200); // Success: 2 beeps
        
        delay(2000);
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("Duration: " + String(duration) + "min");
        lcd.setCursor(0, 1);
        lcd.print("Thank you!");
      } else {
        lcd.print("Error");
        lcd.setCursor(0, 1);
        lcd.print(message.substring(0, 16));
        blinkLED(RED_LED_PIN, 3);
        playBeep(3, 100); // Error: 3 short beeps
      }
    } else {
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("Connection Error");
      blinkLED(RED_LED_PIN, 5);
      playBeep(5, 50); // Connection error: 5 fast beeps
    }
    
    http.end();
  }
  
  delay(3000);
}

void openBarrier(int barrierPin) {
  digitalWrite(barrierPin, HIGH); // Open barrier
  delay(5000); // Keep open for 5 seconds
  digitalWrite(barrierPin, LOW);  // Close barrier
}

void blinkLED(int ledPin, int times) {
  for (int i = 0; i < times; i++) {
    digitalWrite(ledPin, HIGH);
    delay(200);
    digitalWrite(ledPin, LOW);
    delay(200);
  }
}

void playBeep(int times, int duration) {
  for (int i = 0; i < times; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(duration);
    digitalWrite(BUZZER_PIN, LOW);
    delay(100); // Short pause between beeps
  }
}

void updateDisplay() {
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("Smart Parking");
  lcd.setCursor(0, 1);
  if (isEntryMode) {
    lcd.print("Mode: ENTRY");
  } else {
    lcd.print("Mode: EXIT");
  }
}

// Function to get parking status (optional)
void getParkingStatus() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(String(serverURL) + "/api/status");
    
    int httpResponseCode = http.GET();
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.println("Status: " + response);
      
      DynamicJsonDocument doc(1024);
      deserializeJson(doc, response);
      
      int totalSpots = doc["total_spots"];
      int parkedCount = doc["parked_count"];
      int availableSpots = doc["available_spots"];
      
      Serial.printf("Total: %d, Parked: %d, Available: %d\n", 
                   totalSpots, parkedCount, availableSpots);
    }
    
    http.end();
  }
}
