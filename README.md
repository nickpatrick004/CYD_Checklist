# CYD Checklist

A touchscreen checklist and messaging system built for the Cheap Yellow Display (ESP32).

Parent manages tasks and messages from a web interface.  
Child interacts with the checklist and alerts on the CYD device.

---

## Features

- Remote checklist management (web)
- Tap to complete/uncomplete tasks (CYD)
- Timed alerts with snooze
- Parent ↔ child messaging
- Device status tracking
- PHP + MySQL backend
- ESP32 (CYD) firmware

---

## Hardware

Tested on:

- ESP32-2432S028 (Cheap Yellow Display)
- ST7789 display
- XPT2046 touch controller

---

## Repository Structure

backend/
  api/
  includes/
  sql/
  checklist.php
  index.php
  logout.php
  messages.php
  status.php

firmware/
  cyd_checklist.ino

README.md
LICENSE

---

## Quick Start

1. Upload /backend to your web host
2. Import /backend/sql/schema.sql into MySQL
3. Copy config.example.php → config.php
4. Generate password using make_password.php
5. Insert parent user into DB
6. Add device + token
7. Flash firmware with same token

---

## Backend Setup

### 1. Upload files

Upload everything inside backend/ to your host:

public_html/cyd/

---

### 2. Create database

In cPanel:

- Create MySQL database
- Create user
- Assign ALL PRIVILEGES

---

### 3. Import schema

Import:

backend/sql/schema.sql

Optional:

backend/sql/seed.sql

---

### 4. Configure database connection

Copy:

backend/includes/config.example.php

to:

backend/includes/config.php

Edit:

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');

---

### 5. Set timezone (important for alerts)

At the top of sync.php:

date_default_timezone_set('America/Chicago');

Adjust for your location.

---

## Create Parent Login

### 1. Generate password hash

Edit:

backend/make_password.php

$password = 'YourNewPassword';

Open in browser:

https://yourdomain.com/cyd/make_password.php

Copy the output hash.

---

### 2. Insert user

INSERT INTO cyd_parent_users (username, password_hash)
VALUES ('parent', 'PASTE_HASH_HERE');

---

### 3. Delete helper file

DELETE backend/make_password.php

---

## Create Device

INSERT INTO cyd_devices (device_name, device_token)
VALUES ('Bryson CYD', 'CHANGE_THIS_DEVICE_TOKEN');

Use the same token in firmware.

---

## Firmware Setup

### Install libraries

In Arduino IDE:

- ArduinoJson (Benoit Blanchon)
- TFT_eSPI
- XPT2046_Touchscreen

---

### Configure TFT_eSPI

In User_Setup.h:

#define ST7789_DRIVER
#define TFT_WIDTH  240
#define TFT_HEIGHT 320

#define TFT_MOSI 13
#define TFT_SCLK 14
#define TFT_CS   15
#define TFT_DC   2
#define TFT_RST  -1

#define TFT_BL   21
#define TFT_RGB_ORDER TFT_BGR
#define TFT_INVERSION_OFF

#define LOAD_GLCD

---

### Edit firmware

Open:

firmware/cyd_checklist.ino

Set:

const char* WIFI_SSID = "YOUR_WIFI_NAME";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

const char* DEVICE_TOKEN = "YOUR_DEVICE_TOKEN";

const char* SYNC_URL = "https://yourdomain.com/cyd/api/sync.php?token=YOUR_DEVICE_TOKEN";
const char* COMPLETE_URL = "https://yourdomain.com/cyd/api/complete_item.php";
const char* UNCOMPLETE_URL = "https://yourdomain.com/cyd/api/uncomplete_item.php";

Upload to the CYD.

---

## Usage

### Parent

Go to:

https://yourdomain.com/cyd/

- Add checklist items
- Send messages
- Monitor device status

---

### Child (CYD)

- Tap item → mark complete/uncomplete
- Alerts appear at due time
- Tap DONE or SNOOZE
- Messages appear at bottom

---

## Alert Behavior

- Triggers at scheduled time (±2 min window)
- Stays on screen until user action
- Snooze = 5 minutes
- Uses server time (set via PHP timezone)

---

## Security Notes

- Use HTTPS
- Do not commit config.php
- Do not commit passwords or tokens
- Delete make_password.php after use
- Remove error_log files before committing

---

## .gitignore

backend/includes/config.php
backend/error_log
backend/api/error_log
*.log
.DS_Store

---

## Future Improvements

- Multiple children/devices
- Scrollable UI
- Editable checklist items
- Mobile-friendly dashboard
- Push notifications

---

## License

MIT License
