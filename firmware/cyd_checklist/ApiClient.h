#pragma once
#include <ArduinoJson.h>

void connectWiFi();
void updateWiFiStatus();
void syncWithServer();
void loadState(JsonDocument& doc);
void postItemState(const char* url, int itemId, const char* statusText);
void sendMessage(const char* message);
void markMessagesRead();
void serviceDeferredApiWork();
