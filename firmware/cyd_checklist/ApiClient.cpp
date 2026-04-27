#include <Arduino.h>
#include <WiFi.h>
#include <HTTPClient.h>

#include "ApiClient.h"
#include "AppState.h"
#include "UI.h"
#include "config.h"

#ifndef SYNC_URL
#define SYNC_URL API_BASE_URL "/sync.php?token=" DEVICE_TOKEN
#endif
#ifndef COMPLETE_URL
#define COMPLETE_URL API_BASE_URL "/complete_item.php"
#endif
#ifndef UNCOMPLETE_URL
#define UNCOMPLETE_URL API_BASE_URL "/uncomplete_item.php"
#endif
#ifndef SEND_MESSAGE_URL
#define SEND_MESSAGE_URL API_BASE_URL "/send_message.php"
#endif
#ifndef MARK_MESSAGES_READ_URL
#define MARK_MESSAGES_READ_URL API_BASE_URL "/mark_messages_read.php"
#endif

void updateWiFiStatus() {
  app.wifiConnected = (WiFi.status() == WL_CONNECTED);
}

void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  drawStatus("CONNECTING", "WiFi");

  int tries = 0;
  while (WiFi.status() != WL_CONNECTED && tries < 40) {
    delay(250);
    tries++;
  }

  updateWiFiStatus();

  if (app.wifiConnected) {
    drawStatus("WIFI ONLINE", WiFi.localIP().toString().c_str());
    delay(700);
  } else {
    drawStatus("WIFI FAILED", "Check config.h");
    delay(1200);
  }
}

void postItemState(const char* url, int itemId, const char* statusText) {
  updateWiFiStatus();
  if (!app.wifiConnected) {
    drawStatus("WIFI OFFLINE", "Could not update");
    delay(800);
    drawMainScreen();
    return;
  }

  drawStatus(statusText, "Sending update");

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");

  StaticJsonDocument<256> body;
  body["token"] = DEVICE_TOKEN;
  body["itemId"] = itemId;
  String json;
  serializeJson(body, json);

  int httpCode = http.POST(json);
  String payload = http.getString();
  http.end();

  Serial.print("POST item state HTTP: ");
  Serial.println(httpCode);
  Serial.println(payload);

  if (app.snoozedItemId == itemId) {
    app.snoozedItemId = -1;
    app.snoozeUntilMinutes = -1;
  }

  app.showingChecklistDetail = false;
  syncWithServer();
}

void sendMessage(const char* message) {
  updateWiFiStatus();
  if (!app.wifiConnected) {
    drawStatus("WIFI OFFLINE", "Message not sent");
    delay(800);
    drawMessagesScreen();
    return;
  }

  drawStatus("SENDING", message);

  HTTPClient http;
  http.begin(SEND_MESSAGE_URL);
  http.addHeader("Content-Type", "application/json");

  StaticJsonDocument<384> body;
  body["token"] = DEVICE_TOKEN;
  body["sender"] = "kid";
  body["message"] = message;
  body["summary"] = message;
  String json;
  serializeJson(body, json);

  int httpCode = http.POST(json);
  String payload = http.getString();
  http.end();

  Serial.print("POST message HTTP: ");
  Serial.println(httpCode);
  Serial.println(payload);

  if (httpCode == 200) drawStatus("MESSAGE SENT", message);
  else {
    char codeText[16];
    snprintf(codeText, sizeof(codeText), "%d", httpCode);
    drawStatus("SEND FAILED", codeText);
  }

  delay(800);
  syncWithServer();
  app.showingMessages = true;
  app.showingMessageDetail = false;
  drawMessagesScreen();
}

void markMessagesRead() {
  updateWiFiStatus();
  if (!app.wifiConnected || app.messageCount == 0) return;

  HTTPClient http;
  http.begin(MARK_MESSAGES_READ_URL);
  http.addHeader("Content-Type", "application/json");

  StaticJsonDocument<512> body;
  body["token"] = DEVICE_TOKEN;
  JsonArray ids = body.createNestedArray("messageIds");
  for (int i = 0; i < app.messageCount; i++) {
    if (!app.messageItems[i].isRead && strcmp(app.messageItems[i].sender, "parent") == 0) ids.add(app.messageItems[i].id);
  }

  if (ids.size() == 0) {
    http.end();
    app.pendingMarkMessagesRead = false;
    return;
  }

  String json;
  serializeJson(body, json);
  int httpCode = http.POST(json);
  String payload = http.getString();
  http.end();

  Serial.print("POST mark messages read HTTP: ");
  Serial.println(httpCode);
  Serial.println(payload);

  if (httpCode == 200) {
    for (int i = 0; i < app.messageCount; i++) {
      if (strcmp(app.messageItems[i].sender, "parent") == 0) app.messageItems[i].isRead = true;
    }
    app.unreadMessageCount = 0;
    app.pendingMarkMessagesRead = false;
    if (app.showingMessages && !app.showingMessageDetail) drawMessagesScreen();
  }
}

void serviceDeferredApiWork() {
  if (app.pendingMarkMessagesRead && millis() - app.messagesOpenedAt >= MARK_READ_DELAY_MS) markMessagesRead();
}

void syncWithServer() {
  updateWiFiStatus();
  if (!app.wifiConnected) {
    drawStatus("WIFI LOST", "Reconnecting");
    connectWiFi();
    updateWiFiStatus();
    if (!app.wifiConnected) return;
  }

  HTTPClient http;
  http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);
  http.begin(SYNC_URL);
  int httpCode = http.GET();

  if (httpCode != 200) {
    char codeText[16];
    snprintf(codeText, sizeof(codeText), "%d", httpCode);
    drawStatus("HTTP ERROR", codeText);
    http.end();
    delay(900);
    drawMainScreen();
    return;
  }

  String payload = http.getString();
  http.end();

  StaticJsonDocument<12288> doc;
  DeserializationError error = deserializeJson(doc, payload);

  if (error) {
    drawStatus("JSON ERROR", error.c_str());
    delay(1000);
    drawMainScreen();
    return;
  }

  if (!(doc["ok"] | false)) {
    drawStatus("SERVER ERROR", doc["error"] | "Unknown");
    delay(1000);
    drawMainScreen();
    return;
  }

  loadState(doc);
  updateWiFiStatus();

  if (app.showingMessageDetail && app.selectedMessageIndex >= 0) drawMessageDetailScreen(app.selectedMessageIndex);
  else if (app.showingChecklistDetail && app.selectedChecklistIndex >= 0) drawChecklistDetailScreen(app.selectedChecklistIndex);
  else if (app.showingMessages) drawMessagesScreen();
  else drawMainScreen();
}

void loadState(JsonDocument& doc) {
  app.checklistCount = 0;
  app.alertItemCount = 0;
  app.messageCount = 0;
  app.unreadMessageCount = doc["unreadMessageCount"] | 0;

  const char* serverTime = doc["serverTime"] | "";
  app.currentMinutes = parseMinutesFromServerTime(serverTime);
  formatServerDateTime(serverTime, app.lastSyncText, sizeof(app.lastSyncText));

  JsonArray checklist = doc["checklist"];
  for (JsonObject item : checklist) {
    if (app.checklistCount >= MAX_CHECKLIST_ITEMS) break;

    ChecklistItem& ci = app.checklistItems[app.checklistCount];
    ci.id = item["id"] | -1;
    ci.completed = item["completedToday"] | false;
    ci.alertEnabled = item["alertEnabled"] | false;
    strncpy(ci.title, item["title"] | "", sizeof(ci.title) - 1);
    ci.title[sizeof(ci.title) - 1] = '\0';
    strncpy(ci.detail, item["detail"] | "", sizeof(ci.detail) - 1);
    ci.detail[sizeof(ci.detail) - 1] = '\0';
    strncpy(ci.dueTime, item["dueTime"] | "", sizeof(ci.dueTime) - 1);
    ci.dueTime[sizeof(ci.dueTime) - 1] = '\0';

    if (app.alertItemCount < MAX_ALERT_ITEMS) {
      AlertItem& ai = app.alertItems[app.alertItemCount];
      ai.itemId = ci.id;
      strncpy(ai.title, ci.title, sizeof(ai.title) - 1);
      ai.title[sizeof(ai.title) - 1] = '\0';
      strncpy(ai.detail, ci.detail, sizeof(ai.detail) - 1);
      ai.detail[sizeof(ai.detail) - 1] = '\0';
      ai.dueMinutes = timeToMinutes(ci.dueTime);
      ai.completed = ci.completed;
      ai.alertEnabled = ci.alertEnabled;
      app.alertItemCount++;
    }

    app.checklistCount++;
  }

  JsonArray messages = doc["messages"];
  for (JsonObject msg : messages) {
    if (app.messageCount >= MAX_MESSAGES) break;

    MessageItem& mi = app.messageItems[app.messageCount];
    mi.id = msg["id"] | -1;
    mi.isRead = msg["isRead"] | false;
    strncpy(mi.sender, msg["sender"] | "", sizeof(mi.sender) - 1);
    mi.sender[sizeof(mi.sender) - 1] = '\0';
    strncpy(mi.summary, msg["summary"] | msg["message"] | "", sizeof(mi.summary) - 1);
    mi.summary[sizeof(mi.summary) - 1] = '\0';
    strncpy(mi.message, msg["message"] | "", sizeof(mi.message) - 1);
    mi.message[sizeof(mi.message) - 1] = '\0';
    strncpy(mi.detail, msg["detail"] | "", sizeof(mi.detail) - 1);
    mi.detail[sizeof(mi.detail) - 1] = '\0';
    strncpy(mi.createdAt, msg["createdAt"] | "", sizeof(mi.createdAt) - 1);
    mi.createdAt[sizeof(mi.createdAt) - 1] = '\0';
    app.messageCount++;
  }

  int maxChecklistOffset = max(0, app.checklistCount - 4);
  if (app.checklistScrollOffset > maxChecklistOffset) app.checklistScrollOffset = maxChecklistOffset;

  int maxMessageOffset = max(0, app.messageCount - 2);
  if (app.messageScrollOffset > maxMessageOffset) app.messageScrollOffset = maxMessageOffset;
  if (app.selectedMessageIndex >= app.messageCount) app.selectedMessageIndex = -1;
  if (app.selectedChecklistIndex >= app.checklistCount) app.selectedChecklistIndex = -1;
}
