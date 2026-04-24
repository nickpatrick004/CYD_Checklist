#include <ArduinoJson.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <TFT_eSPI.h>
#include <XPT2046_Touchscreen.h>
#include <SPI.h>

// ---------- USER SETTINGS ----------
const char* WIFI_SSID = "WIFI_SSID";
const char* WIFI_PASSWORD = "WIFI_PASSWORD";

const char* DEVICE_TOKEN = "DEVICE_TOKEN";

const char* SYNC_URL = "https://YOURDOMAIN.com/cyd/api/sync.php?token=DEVICE_TOKEN";
const char* COMPLETE_URL = "https://YOURDOMAIN.com/cyd/api/complete_item.php";
const char* UNCOMPLETE_URL = "https://YOURDOMAIN.com/cyd/api/uncomplete_item.php";
// -----------------------------------

TFT_eSPI tft = TFT_eSPI();

#define TOUCH_CS   33
#define TOUCH_IRQ  36
#define TOUCH_MOSI 32
#define TOUCH_MISO 39
#define TOUCH_CLK  25

SPIClass touchSPI = SPIClass(VSPI);
XPT2046_Touchscreen ts(TOUCH_CS, TOUCH_IRQ);

const unsigned long SYNC_INTERVAL_MS = 10000;
unsigned long lastSync = 0;

const int ALERT_WINDOW_MINUTES = 2;
const int SNOOZE_MINUTES = 5;

struct TouchItem {
  int itemId;
  int yTop;
  int yBottom;
  bool completed;
};

struct AlertItem {
  int itemId;
  char title[80];
  int dueMinutes;
  bool completed;
  bool alertEnabled;
};

TouchItem touchItems[10];
int touchItemCount = 0;

AlertItem alertItems[10];
int alertItemCount = 0;

bool alertActive = false;
int activeAlertItemId = -1;
char activeAlertTitle[80] = "";

int currentMinutes = -1;
int snoozedItemId = -1;
int snoozeUntilMinutes = -1;

void setup() {
  Serial.begin(115200);
  delay(1000);

  tft.init();
  pinMode(21, OUTPUT);
  digitalWrite(21, HIGH);
  tft.setRotation(1);
  tft.fillScreen(TFT_BLACK);

  touchSPI.begin(TOUCH_CLK, TOUCH_MISO, TOUCH_MOSI, TOUCH_CS);
  ts.begin(touchSPI);
  ts.setRotation(1);

  drawStatus("Starting...", "");

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  drawStatus("Connecting", "WiFi...");

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
  }

  drawStatus("WiFi OK", WiFi.localIP().toString().c_str());
  delay(800);

  syncWithServer();
}

void loop() {
  handleTouch();

  if (!alertActive) {
    checkAlerts();
  }

  if (!alertActive && millis() - lastSync > SYNC_INTERVAL_MS) {
    lastSync = millis();
    syncWithServer();
  }
}

void handleTouch() {
  if (!ts.touched()) return;

  TS_Point p = ts.getPoint();

  int screenX = map(p.x, 200, 3800, 0, 320);
  int screenY = map(p.y, 200, 3800, 0, 240);

  screenX = constrain(screenX, 0, 319);
  screenY = constrain(screenY, 0, 239);

  Serial.print("Touch X=");
  Serial.print(screenX);
  Serial.print(" Y=");
  Serial.println(screenY);

  if (alertActive) {
    handleAlertTouch(screenX, screenY);
    delay(300);
    return;
  }

  for (int i = 0; i < touchItemCount; i++) {
    if (screenY >= touchItems[i].yTop && screenY <= touchItems[i].yBottom) {
      if (touchItems[i].completed) {
        unmarkItemComplete(touchItems[i].itemId);
      } else {
        markItemComplete(touchItems[i].itemId);
      }
      break;
    }
  }

  delay(300);
}

void handleAlertTouch(int x, int y) {
  // DONE button
  if (x >= 20 && x <= 145 && y >= 175 && y <= 225) {
    alertActive = false;
    markItemComplete(activeAlertItemId);
    return;
  }

  // SNOOZE button
  if (x >= 175 && x <= 305 && y >= 175 && y <= 225) {
    snoozedItemId = activeAlertItemId;
    snoozeUntilMinutes = currentMinutes + SNOOZE_MINUTES;
    if (snoozeUntilMinutes >= 1440) snoozeUntilMinutes -= 1440;

    alertActive = false;
    syncWithServer();
    return;
  }
}

void checkAlerts() {
  if (currentMinutes < 0) return;

  for (int i = 0; i < alertItemCount; i++) {
    if (!alertItems[i].alertEnabled) continue;
    if (alertItems[i].completed) continue;
    if (alertItems[i].dueMinutes < 0) continue;

    if (snoozedItemId == alertItems[i].itemId) {
      if (currentMinutes < snoozeUntilMinutes) continue;
    }

    bool insideDueWindow =
      currentMinutes >= alertItems[i].dueMinutes &&
      currentMinutes <= alertItems[i].dueMinutes + ALERT_WINDOW_MINUTES;

    bool snoozeExpired =
      snoozedItemId == alertItems[i].itemId &&
      currentMinutes >= snoozeUntilMinutes;

    if (insideDueWindow || snoozeExpired) {
      showAlert(alertItems[i].itemId, alertItems[i].title);
      return;
    }
  }
}

void showAlert(int itemId, const char* title) {
  alertActive = true;
  activeAlertItemId = itemId;

  strncpy(activeAlertTitle, title, sizeof(activeAlertTitle) - 1);
  activeAlertTitle[sizeof(activeAlertTitle) - 1] = '\0';

  tft.fillScreen(TFT_RED);

  tft.setTextColor(TFT_WHITE, TFT_RED);
  tft.setTextSize(3);
  tft.setCursor(20, 25);
  tft.println("Reminder");

  tft.setTextSize(2);
  tft.setCursor(20, 80);
  printTrimmed(activeAlertTitle, 22);

  tft.fillRoundRect(20, 175, 125, 50, 8, TFT_GREEN);
  tft.setTextColor(TFT_BLACK, TFT_GREEN);
  tft.setTextSize(2);
  tft.setCursor(55, 192);
  tft.println("DONE");

  tft.fillRoundRect(175, 175, 130, 50, 8, TFT_YELLOW);
  tft.setTextColor(TFT_BLACK, TFT_YELLOW);
  tft.setCursor(200, 192);
  tft.println("SNOOZE");
}

void markItemComplete(int itemId) {
  postItemState(COMPLETE_URL, itemId, "Marking done");
}

void unmarkItemComplete(int itemId) {
  postItemState(UNCOMPLETE_URL, itemId, "Undoing done");
}

void postItemState(const char* url, int itemId, const char* statusText) {
  if (WiFi.status() != WL_CONNECTED) return;

  drawStatus(statusText, "");

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");

  String body = "{";
  body += "\"token\":\"";
  body += DEVICE_TOKEN;
  body += "\",";
  body += "\"itemId\":";
  body += itemId;
  body += "}";

  int httpCode = http.POST(body);
  String payload = http.getString();
  http.end();

  Serial.print("POST HTTP: ");
  Serial.println(httpCode);
  Serial.println(payload);

  if (snoozedItemId == itemId) {
    snoozedItemId = -1;
    snoozeUntilMinutes = -1;
  }

  syncWithServer();
}

void syncWithServer() {
  if (WiFi.status() != WL_CONNECTED) {
    drawStatus("WiFi lost", "");
    return;
  }

  HTTPClient http;
  http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);
  http.begin(SYNC_URL);

  int httpCode = http.GET();

  if (httpCode != 200) {
    drawStatus("HTTP Error", String(httpCode).c_str());
    http.end();
    return;
  }

  String payload = http.getString();
  http.end();

  StaticJsonDocument<8192> doc;
  DeserializationError error = deserializeJson(doc, payload);

  if (error) {
    drawStatus("JSON Error", error.c_str());
    return;
  }

  if (!(doc["ok"] | false)) {
    drawStatus("Server Error", doc["error"] | "");
    return;
  }

  renderScreen(doc);
}

void renderScreen(JsonDocument& doc) {
  tft.fillScreen(TFT_BLACK);
  touchItemCount = 0;
  alertItemCount = 0;

  const char* serverTime = doc["serverTime"] | "";
  currentMinutes = parseMinutesFromServerTime(serverTime);

  int y = 5;

  tft.setTextSize(2);
  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  tft.setCursor(5, y);
  tft.println("CYD Checklist");

  y += 22;

  char formattedDateTime[40];
  formatServerDateTime(serverTime, formattedDateTime, sizeof(formattedDateTime));

  tft.setTextSize(1);
  tft.setTextColor(TFT_DARKGREY, TFT_BLACK);
  tft.setCursor(5, y);
  tft.println(formattedDateTime);

  y += 15;
  tft.drawLine(0, y, 320, y, TFT_DARKGREY);
  y += 5;

  tft.setTextSize(2);
  tft.setTextColor(TFT_YELLOW, TFT_BLACK);
  tft.setCursor(5, y);
  tft.println("Checklist");

  y += 22;

  JsonArray checklist = doc["checklist"];

  for (JsonObject item : checklist) {
    if (y > 150) break;

    int itemId = item["id"];
    bool done = item["completedToday"];
    const char* title = item["title"] | "";
    const char* dueTime = item["dueTime"] | "";
    bool alertEnabled = item["alertEnabled"] | false;

    if (touchItemCount < 10) {
      touchItems[touchItemCount].itemId = itemId;
      touchItems[touchItemCount].yTop = y - 2;
      touchItems[touchItemCount].yBottom = y + 20;
      touchItems[touchItemCount].completed = done;
      touchItemCount++;
    }

    if (alertItemCount < 10) {
      alertItems[alertItemCount].itemId = itemId;
      strncpy(alertItems[alertItemCount].title, title, sizeof(alertItems[alertItemCount].title) - 1);
      alertItems[alertItemCount].title[sizeof(alertItems[alertItemCount].title) - 1] = '\0';
      alertItems[alertItemCount].dueMinutes = timeToMinutes(dueTime);
      alertItems[alertItemCount].completed = done;
      alertItems[alertItemCount].alertEnabled = alertEnabled;
      alertItemCount++;
    }

    tft.setTextSize(2);
    tft.setCursor(5, y);

    if (done) {
      tft.setTextColor(TFT_GREEN, TFT_BLACK);
      tft.print("[X] ");
    } else {
      tft.setTextColor(TFT_RED, TFT_BLACK);
      tft.print("[ ] ");
    }

    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    printTrimmed(title, 18);

    y += 22;
  }

  y += 5;
  tft.drawLine(0, y, 320, y, TFT_DARKGREY);
  y += 5;

  tft.setTextSize(2);
  tft.setTextColor(TFT_CYAN, TFT_BLACK);
  tft.setCursor(5, y);
  tft.println("Messages");

  y += 22;

  JsonArray messages = doc["messages"];
  int count = 0;

  for (JsonObject msg : messages) {
    if (count >= 3 || y > 230) break;

    const char* sender = msg["sender"] | "";
    const char* displaySender = strcmp(sender, "kid") == 0 ? "Bryson" : sender;
    const char* message = msg["message"] | "";

    tft.setTextSize(1);
    tft.setCursor(5, y);

    tft.setTextColor(TFT_CYAN, TFT_BLACK);
    tft.print(displaySender);
    tft.print(": ");

    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    printTrimmed(message, 34);

    y += 16;
    count++;
  }
}

int timeToMinutes(const char* timeStr) {
  if (!timeStr || strlen(timeStr) < 5) return -1;

  int hour = (timeStr[0] - '0') * 10 + (timeStr[1] - '0');
  int minute = (timeStr[3] - '0') * 10 + (timeStr[4] - '0');

  if (hour < 0 || hour > 23 || minute < 0 || minute > 59) return -1;

  return hour * 60 + minute;
}

int parseMinutesFromServerTime(const char* serverTime) {
  if (!serverTime || strlen(serverTime) < 16) return -1;
  return timeToMinutes(serverTime + 11);
}

void formatServerDateTime(const char* serverTime, char* out, size_t outSize) {
  if (!serverTime || strlen(serverTime) < 16) {
    snprintf(out, outSize, "");
    return;
  }

  int year = parseTwoOrFourDigits(serverTime, 0, 4);
  int month = parseTwoOrFourDigits(serverTime, 5, 2);
  int day = parseTwoOrFourDigits(serverTime, 8, 2);
  int hour24 = parseTwoOrFourDigits(serverTime, 11, 2);
  int minute = parseTwoOrFourDigits(serverTime, 14, 2);

  const char* ampm = hour24 >= 12 ? "PM" : "AM";
  int hour12 = hour24 % 12;
  if (hour12 == 0) hour12 = 12;

  snprintf(
    out,
    outSize,
    "%02d-%02d-%04d  %d:%02d %s",
    month,
    day,
    year,
    hour12,
    minute,
    ampm
  );
}

int parseTwoOrFourDigits(const char* str, int start, int len) {
  int value = 0;

  for (int i = 0; i < len; i++) {
    char c = str[start + i];
    if (c < '0' || c > '9') return 0;
    value = value * 10 + (c - '0');
  }

  return value;
}

void drawStatus(const char* line1, const char* line2) {
  tft.fillScreen(TFT_BLACK);

  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  tft.setTextSize(2);

  tft.setCursor(10, 40);
  tft.println(line1);

  if (strlen(line2) > 0) {
    tft.setCursor(10, 75);
    tft.println(line2);
  }
}

void printTrimmed(const char* text, int maxChars) {
  if (!text) return;

  int len = strlen(text);

  if (len <= maxChars) {
    tft.println(text);
    return;
  }

  for (int i = 0; i < maxChars - 3; i++) {
    tft.print(text[i]);
  }

  tft.println("...");
}
