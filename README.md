# 🔌 SmartNode: Web-Controlled Plug

<p align="center">
  <img src="https://img.shields.io/badge/Hardware-WeMos%20D1%20(ESP8266)-00979D?style=for-the-badge&logo=arduino&logoColor=white" alt="Hardware">
  <img src="https://img.shields.io/badge/Firmware-C%2B%2B-00599C?style=for-the-badge&logo=c%2B%2B&logoColor=white" alt="C++">
  <img src="https://img.shields.io/badge/Backend-PHP%208-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/UI-Tailwind%20CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind">
</p>

<p align="center">
  <strong>Control your home appliances from anywhere in the world.</strong>
  <br />
  A self-hosted, firewall-bypassing IoT smart plug system with a sleek, app-like web interface.
</p>

---

## 🌟 Overview

**SmartNode** is a complete IoT solution for remotely controlling two electrical outlets. Unlike commercial smart plugs that rely on third-party clouds (like Tuya or eWeLink), this project is entirely **self-hosted**. 

By using a 1-second HTTPS polling architecture, the ESP8266 fetches its state directly from your PHP server. This means **no port-forwarding, no dynamic DNS, and no complex router configurations are required.** If the device has internet access, it works.

### ✨ Key Features
*   **📱 Modern Web App UI:** A responsive, Tailwind-powered interface with a glass-morphism design, PIN protection, and real-time AJAX polling.
*   **⏳ Automated Timers:** Set a countdown (15m, 30m, 60m, 90m) to automatically power off a plug.
*   **📅 Daily Scheduling:** Define hour-based schedules (e.g., `06:00 to 20:00`) for fully autonomous operation.
*   **🔍 "Find Me" Feature:** Clicking the *Find* button in the UI triggers a physical blinking LED on the specific plug, helping you identify it in a dark room.
*   **👨‍💻 Live Terminal:** A slide-up terminal panel in the web UI lets you monitor the raw sync data in real-time.
*   **🛡️ Fail-Safe Logic:** If the device loses WiFi, it enters an offline-safe mode and auto-reconnects.

---

## 🛠️ Hardware Requirements

*   **Microcontroller:** WeMos D1 (ESP8266 WiFi Core)
*   **Relays:** 2x 5V/3.3V Relay Modules (Safely mapped to avoid boot-clicking)
*   **Status LEDs:** 
    *   Boot/Search Mode LED
    *   WiFi Connected LED
    *   Server Sync LED
*   **Indicator LEDs:** 2x LEDs for identifying Plug 1 and Plug 2.

---

## 🧬 System Architecture

```mermaid
graph TD
    subgraph "Your House (No Port-Forwarding Needed)"
        ESP[WeMos D1 ESP8266]
        Relay1[Plug 1 Relay]
        Relay2[Plug 2 Relay]
        ESP -->|Controls| Relay1
        ESP -->|Controls| Relay2
    end

    subgraph "Web Server"
        PHP[index.php / API]
        TXT[(status.txt)]
        CRON[cron.php daemon]
        PHP <--> TXT
        CRON -->|Checks timers & offline status| TXT
    end

    subgraph "You (Anywhere)"
        UI[Web Interface / Phone]
    end

    ESP -->|1. HTTPS POST / Heartbeat (Every 1s)| PHP
    PHP -->|2. Returns JSON state| ESP
    UI <-->|3. AJAX Polling & Commands| PHP
    
    style ESP fill:#00979d,stroke:#333,color:#fff
    style PHP fill:#777bb4,stroke:#333,color:#fff
    style CRON fill:#4eaa25,stroke:#333,color:#fff
```

---

## 🚀 Setup & Installation

### Step 1: Server Configuration (PHP / Web Host)
1. Upload `index.php`, `cron.php`, and create an empty `status.txt` on your web server.
2. **Crucial Permission Fix:** The PHP script needs to read and write to the text file. Run the following via SSH (or change permissions via cPanel/FTP to `666` or `777`):
   ```bash
   chmod 666 status.txt
   # OR give ownership to the web server
   chown www-data:www-data status.txt
   ```
3. Open `index.php` and modify the default PIN if desired:
   ```php
   $password_protect = "2460"; // Change this
   ```
4. Open `cron.php` and ensure `$file_path` points to the **absolute path** of your `status.txt` on the server.

### Step 2: High-Frequency Cron Job (Server-side)
To process timers and schedules accurately, `cron.php` must run frequently. Standard cron only runs every minute. To run it every **15 seconds**, add the following 4 lines to your server's crontab (`crontab -e`):

```bash
* * * * * php /absolute/path/to/your/cron.php >/dev/null 2>&1
* * * * * sleep 15; php /absolute/path/to/your/cron.php >/dev/null 2>&1
* * * * * sleep 30; php /absolute/path/to/your/cron.php >/dev/null 2>&1
* * * * * sleep 45; php /absolute/path/to/your/cron.php >/dev/null 2>&1
```

### Step 3: Hardware Firmware (Arduino IDE)
1. Install the **ESP8266** board package in the Arduino IDE.
2. Install the **ArduinoJson** library via the Library Manager.
3. Open `smartnode.ino` and update the configuration section:
   ```cpp
   const char* ssid = "YOUR_WIFI_NAME";
   const char* password = "YOUR_WIFI_PASSWORD";
   const char* serverUrl = "https://YOURDOMAIN.COM/index.php";
   ```
4. Connect your WeMos D1 via USB and upload the code. *(Note: Ensure Pin D8 / GPIO0 is NOT grounded during boot, or the upload will fail).*

---

## 🖱️ Usage Guide

1. **Access the App:** Open the URL where you hosted `index.php` on your phone or PC.
2. **Log In:** Enter your 4-digit PIN
3. **Control:** 
   * Click the main power button to toggle the relay.
   * Click **Timer** to select an auto-shutoff time.
   * Click **Sched** to set daily active hours.
   * Click **Find** to make the physical device blink (useful if you forget which plug is which).
4. **Monitoring:** Click the "System Log" at the bottom to expand the live terminal and view raw data streams and sync health.

---

## ⚠️ Important Safety Notes

* **High Voltage Warning:** You are dealing with Relays that control mains AC voltage (110V/220V). Ensure proper isolation, use electrical tape, and house the WeMos D1 in a safe, non-conductive 3D-printed or plastic enclosure.
* **Insecure HTTPS Check:** The ESP8266 code uses `client.setInsecure();`. This allows it to connect to your HTTPS domain without verifying the SSL fingerprint, saving memory and preventing the device from breaking when your SSL certificate auto-renews.

---

## 📜 License

Distributed under the MIT License. Feel free to modify and adapt for your own home automation setups!

---
<p align="center">
  Connecting the physical world to the web. 🌐
</p>
