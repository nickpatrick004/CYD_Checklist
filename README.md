# CYD Checklist

A Cheap Yellow Display (CYD) checklist and messaging system for a child’s room.

The parent side runs on a PHP/MySQL web host. The CYD acts as the child-side touchscreen device.

## Features

- Parent web dashboard
- Add checklist items remotely
- Timed checklist reminders
- Tap checklist items complete/incomplete on the CYD
- Parent-to-child messages
- Child-to-parent messages
- Device status page
- PHP + MySQL backend
- ESP32/CYD firmware

## Repository Structure

```text
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

```

Backend Setup
Upload the contents of backend/ to your web host.

Example:
```
public_html/cyd/
Create a MySQL database in cPanel.
Import:
backend/sql/schema.sql
Optional: import seed data:
backend/sql/seed.sql
```

Copy:
```
backend/includes/config.example.php
```
to:
```
backend/includes/config.php
```
Edit config.php with your real database credentials.

Do not commit config.php.

Create Parent Password

Edit:
```
backend/make_password.php
```
Set:
```
$password = 'YourNewPassword';
```
Upload it, open it in a browser, and copy the generated hash.

Insert parent user:
```
INSERT INTO cyd_parent_users (username, password_hash)
VALUES ('parent', 'PASTE_HASH_HERE');
```
Then delete:

make_password.php
Create Device

In MySQL:
```
INSERT INTO cyd_devices (device_name, device_token)
VALUES ('Bedroom CYD', 'CHANGE_THIS_DEVICE_TOKEN');
```
Use the same token in the firmware.

Firmware Setup

Install Arduino libraries:
```
ArduinoJson by Benoit Blanchon
TFT_eSPI
XPT2046_Touchscreen
```
Edit the top of:

firmware/cyd_checklist.ino

Set:
```
WIFI_SSID
WIFI_PASSWORD
DEVICE_TOKEN
SYNC_URL
COMPLETE_URL
UNCOMPLETE_URL
```
Then upload to the CYD.

Security Notes
Use HTTPS.
Do not expose config.php.
Delete make_password.php after use.
Do not commit Wi-Fi passwords, database passwords, or device tokens.
Remove error_log files before committing.

