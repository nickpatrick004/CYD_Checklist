#pragma once

// Copy this file to config.h and fill in real values.
// Never commit config.h.

// WiFi
#define WIFI_SSID "your_wifi_name"
#define WIFI_PASSWORD "your_wifi_password"

// API
#define API_BASE_URL "https://your-domain.com/cyd/api"
#define SYNC_URL API_BASE_URL "/sync.php?token=" DEVICE_TOKEN
#define COMPLETE_URL API_BASE_URL "/complete_item.php"
#define UNCOMPLETE_URL API_BASE_URL "/uncomplete_item.php"
#define SEND_MESSAGE_URL API_BASE_URL "/send_message.php"
#define MARK_MESSAGES_READ_URL API_BASE_URL "/mark_messages_read.php"

// Device auth
#define DEVICE_TOKEN "your_device_token"
