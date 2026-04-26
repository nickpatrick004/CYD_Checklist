#include "AppState.h"

AppState app;

const char* quickReplies[] = { "OK", "Done", "Need help", "On my way" };
const int quickReplyCount = 4;

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
    snprintf(out, outSize, "No time");
    return;
  }

  int month = parseDigits(serverTime, 5, 2);
  int day = parseDigits(serverTime, 8, 2);
  int hour24 = parseDigits(serverTime, 11, 2);
  int minute = parseDigits(serverTime, 14, 2);
  const char* ampm = hour24 >= 12 ? "PM" : "AM";
  int hour12 = hour24 % 12;
  if (hour12 == 0) hour12 = 12;

  snprintf(out, outSize, "%02d/%02d %d:%02d %s", month, day, hour12, minute, ampm);
}

int parseDigits(const char* str, int start, int len) {
  int value = 0;
  for (int i = 0; i < len; i++) {
    char c = str[start + i];
    if (c < '0' || c > '9') return 0;
    value = value * 10 + (c - '0');
  }
  return value;
}
