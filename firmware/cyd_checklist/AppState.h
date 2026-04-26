#pragma once
#include <Arduino.h>

const unsigned long SYNC_INTERVAL_MS = 10000;
const unsigned long TOUCH_DEBOUNCE_MS = 260;
const int ALERT_WINDOW_MINUTES = 2;
const int SNOOZE_MINUTES = 5;

const int MAX_TOUCH_ZONES = 18;
const int MAX_CHECKLIST_ITEMS = 10;
const int MAX_ALERT_ITEMS = 10;
const int MAX_MESSAGES = 5;

struct TouchZone {
  int id;
  int x1;
  int y1;
  int x2;
  int y2;
  char action[18];
  bool completed;
};

struct AlertItem {
  int itemId;
  char title[80];
  int dueMinutes;
  bool completed;
  bool alertEnabled;
};

struct ChecklistItem {
  int id;
  char title[80];
  char dueTime[8];
  bool completed;
  bool alertEnabled;
};

struct MessageItem {
  char sender[16];
  char message[96];
};

struct AppState {
  TouchZone zones[MAX_TOUCH_ZONES];
  int zoneCount = 0;

  ChecklistItem checklistItems[MAX_CHECKLIST_ITEMS];
  int checklistCount = 0;
  int checklistScrollOffset = 0;

  AlertItem alertItems[MAX_ALERT_ITEMS];
  int alertItemCount = 0;

  MessageItem messageItems[MAX_MESSAGES];
  int messageCount = 0;
  int messageScrollOffset = 0;
  int seenMessageCount = 0;

  bool alertActive = false;
  int activeAlertItemId = -1;
  char activeAlertTitle[80] = "";

  int currentMinutes = -1;
  int snoozedItemId = -1;
  int snoozeUntilMinutes = -1;

  unsigned long lastSync = 0;
  unsigned long lastTouch = 0;
  char lastSyncText[32] = "Not synced";

  bool wifiConnected = false;
  bool showingMessages = false;
};

extern AppState app;

extern const char* quickReplies[];
extern const int quickReplyCount;

int timeToMinutes(const char* timeStr);
int parseMinutesFromServerTime(const char* serverTime);
void formatServerDateTime(const char* serverTime, char* out, size_t outSize);
int parseDigits(const char* str, int start, int len);
