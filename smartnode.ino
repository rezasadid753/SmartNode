#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>

// --- CONFIGURATION ---
const char* ssid = "WIFINAME";
const char* password = "WIFIPASS";
const char* serverUrl = "https://YOURDOMAIN.COM/index.php";

// --- PIN DEFINITIONS (WeMos D1 R1) ---

// 1. RELAYS (Must use SAFE pins to stop clicking)
const int PIN_P1_RELAY = 5;
const int PIN_P2_RELAY = 14;

// 2. STATUS LIGHTS
const int PIN_BOOT_LED = 1;
const int PIN_WIFI_LED = 16;
const int PIN_SERV_LED = 3;

// 3. INDICATOR LEDS
const int PIN_P1_LED   = 12;
const int PIN_P2_LED   = 4;

// --- GLOBALS ---
unsigned long lastRequestTime = 0;
const unsigned long requestInterval = 1000; // 1 Second
unsigned long lastBlinkTime = 0;
bool blinkState = false;

// Variables
bool plug1_on = false;
bool plug2_on = false;
bool plug1_show = false;
bool plug2_show = false;

void setup() {
  Serial.begin(115200);

  // 1. Initialize Pins
  // WARNING: D8 (GPIO0) must NOT be grounded during boot or upload will fail.
  pinMode(PIN_BOOT_LED, OUTPUT); 
  pinMode(PIN_WIFI_LED, OUTPUT); 
  pinMode(PIN_SERV_LED, OUTPUT); 
  pinMode(PIN_P1_RELAY, OUTPUT); 
  pinMode(PIN_P1_LED,   OUTPUT); 
  pinMode(PIN_P2_RELAY, OUTPUT); 
  pinMode(PIN_P2_LED,   OUTPUT); 

  // Reset all to LOW
  digitalWrite(PIN_WIFI_LED, LOW);
  digitalWrite(PIN_SERV_LED, LOW);
  digitalWrite(PIN_P1_RELAY, LOW);
  digitalWrite(PIN_P1_LED,   LOW);
  digitalWrite(PIN_P2_RELAY, LOW);
  digitalWrite(PIN_P2_LED,   LOW);

  // 2. Turn ON Boot LED (D6) immediately
  digitalWrite(PIN_BOOT_LED, HIGH);

  // 3. Connect WiFi
  Serial.println("\nConnecting to WiFi...");
  WiFi.begin(ssid, password);
  
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi Connected!");

  // 4. WiFi Connected Behavior:
  // Turn OFF Boot LED (D6)
  digitalWrite(PIN_BOOT_LED, LOW);
  // Turn ON WiFi LED (D5)
  digitalWrite(PIN_WIFI_LED, HIGH);
}

void loop() {
  unsigned long currentMillis = millis();

  // --- 1. BLINK TIMER (500ms) ---
  if (currentMillis - lastBlinkTime >= 500) {
    lastBlinkTime = currentMillis;
    blinkState = !blinkState;
  }

  // --- 2. CHECK CONNECTION ---
  if (WiFi.status() == WL_CONNECTED) {
    // Ensure WiFi LED is ON, Boot LED is OFF
    digitalWrite(PIN_WIFI_LED, HIGH);
    digitalWrite(PIN_BOOT_LED, LOW);

    // --- 3. SERVER SYNC (Every 1s) ---
    if (currentMillis - lastRequestTime >= requestInterval) {
      lastRequestTime = currentMillis;
      
      WiFiClientSecure client;
      client.setInsecure(); // Ignore SSL Cert
      HTTPClient http;

      if (http.begin(client, serverUrl)) {
        http.addHeader("Content-Type", "application/json");
        
        // POST to update lastreached
        int httpCode = http.POST("{\"action\":\"device_heartbeat\"}");

        if (httpCode == HTTP_CODE_OK) {
          digitalWrite(PIN_SERV_LED, HIGH); // D4 ON (Sync Success)
          
          String payload = http.getString();
          JsonDocument doc;
          DeserializationError error = deserializeJson(doc, payload);

          if (!error) {
             const char* p1 = doc["plug1"];
             const char* p2 = doc["plug2"];
             const char* s1 = doc["plug1_show"];
             const char* s2 = doc["plug2_show"];

             if(p1) plug1_on = (strcmp(p1, "on") == 0);
             if(p2) plug2_on = (strcmp(p2, "on") == 0);
             if(s1) plug1_show = (strcmp(s1, "yes") == 0);
             if(s2) plug2_show = (strcmp(s2, "yes") == 0);
          }
        } else {
          digitalWrite(PIN_SERV_LED, LOW); // Sync Failed
          Serial.print("HTTP Err: "); Serial.println(httpCode);
        }
        http.end();
      } else {
        digitalWrite(PIN_SERV_LED, LOW); // Sync Failed
      }
    }
  } else {
    // WiFi Lost Behavior
    digitalWrite(PIN_WIFI_LED, LOW); // D5 OFF
    digitalWrite(PIN_SERV_LED, LOW); // D4 OFF
    digitalWrite(PIN_BOOT_LED, HIGH); // D6 ON (Back to Boot/Search mode)
    
    WiFi.reconnect();
  }

  // --- 4. OUTPUT LOGIC ---

  // Plug 1 (Relay D10)
  digitalWrite(PIN_P1_RELAY, plug1_on ? HIGH : LOW);

  // Plug 1 (LED D11)
  if (plug1_show) {
    digitalWrite(PIN_P1_LED, blinkState ? HIGH : LOW);
  } else {
    digitalWrite(PIN_P1_LED, plug1_on ? HIGH : LOW);
  }

  // Plug 2 (Relay D8)
  digitalWrite(PIN_P2_RELAY, plug2_on ? HIGH : LOW);

  // Plug 2 (LED D9)
  if (plug2_show) {
    digitalWrite(PIN_P2_LED, blinkState ? HIGH : LOW);
  } else {
    digitalWrite(PIN_P2_LED, plug2_on ? HIGH : LOW);
  }
}