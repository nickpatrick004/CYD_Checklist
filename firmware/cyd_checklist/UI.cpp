#include "UI.h"
#include "Display.h"
#include "Theme.h"

void addZone(int id, int x1, int y1, int x2, int y2, const char* action, bool completed) {
  if (app.zoneCount >= MAX_TOUCH_ZONES) return;
  app.zones[app.zoneCount].id = id;
  app.zones[app.zoneCount].x1 = x1;
  app.zones[app.zoneCount].y1 = y1;
  app.zones[app.zoneCount].x2 = x2;
  app.zones[app.zoneCount].y2 = y2;
  app.zones[app.zoneCount].completed = completed;
  strncpy(app.zones[app.zoneCount].action, action, sizeof(app.zones[app.zoneCount].action) - 1);
  app.zones[app.zoneCount].action[sizeof(app.zones[app.zoneCount].action) - 1] = '\0';
  app.zoneCount++;
}

void drawMainScreen() {
  app.zoneCount = 0;
  tft.fillScreen(C_BG);
  drawHeader("CHECKLIST", app.wifiConnected ? "WIFI OK" : "WIFI OFF");

  int done = 0;
  for (int i = 0; i < app.checklistCount; i++) if (app.checklistItems[i].completed) done++;
  drawProgress(done, app.checklistCount);

  int y = 68;
  for (int i = 0; i < app.checklistCount && i < 5; i++) {
    drawChecklistCard(app.checklistItems[i], 8, y, 304, 28);
    addZone(app.checklistItems[i].id, 8, y, 312, y + 28, "toggle", app.checklistItems[i].completed);
    y += 32;
  }

  drawFooterButton(8, 208, 98, 24, "SYNC", C_PANEL, C_TEXT);
  addZone(0, 8, 208, 106, 232, "sync");

  drawFooterButton(112, 208, 200, 24, "MESSAGES", C_ORANGE, TFT_BLACK);
  addZone(0, 112, 208, 312, 232, "messages");
}

void drawMessagesScreen() {
  app.zoneCount = 0;
  tft.fillScreen(C_BG);
  drawHeader("MESSAGES", "REPLY MODE");

  // Landscape CYD is 320x240. Keep the bottom footer reserved for BACK
  // so quick-reply buttons can never overlap it.
  const int footerY = 208;
  const int footerH = 24;
  const int replyTopY = 138;
  const int replyH = 24;
  const int replyGapY = 6;
  const int contentBottom = replyTopY - 6;

  int y = 42;
  for (int i = 0; i < app.messageCount && i < 2; i++) {
    if (y + 38 > contentBottom) break;
    drawMessageCard(app.messageItems[i], 8, y, 304, 38);
    y += 42;
  }

  if (app.messageCount == 0) {
    tft.setTextColor(C_MUTED, C_BG);
    tft.setTextSize(2);
    tft.setCursor(24, 72);
    tft.print("No messages yet");
  }

  // Quick replies sit in their own two-row area above the footer.
  // If more than four replies are configured, only the first four are shown
  // on this small screen to keep touch targets clean.
  int visibleReplies = min(quickReplyCount, 4);
  for (int i = 0; i < visibleReplies; i++) {
    int x = (i % 2 == 0) ? 8 : 164;
    int y2 = replyTopY + (i / 2) * (replyH + replyGapY);
    drawFooterButton(x, y2, 148, replyH, quickReplies[i], C_ORANGE, TFT_BLACK);
    addZone(i, x, y2, x + 148, y2 + replyH, "reply");
  }

  // Full-width BACK button in the reserved footer. Larger target, no overlap.
  drawFooterButton(8, footerY, 304, footerH, "BACK", C_PANEL, C_TEXT);
  addZone(0, 8, footerY, 312, footerY + footerH, "home");
}

void drawHeader(const char* title, const char* status) {
  tft.fillRoundRect(0, 0, 320, 34, 0, C_PANEL);
  tft.fillRect(0, 31, 320, 3, C_ORANGE);
  tft.setTextSize(2);
  tft.setTextColor(C_TEXT, C_PANEL);
  tft.setCursor(8, 8);
  tft.print(title);
  tft.setTextSize(1);
  tft.setTextColor(C_MUTED, C_PANEL);
  tft.setCursor(230, 7);
  tft.print(status);
  tft.setCursor(188, 20);
  tft.print(app.lastSyncText);
}

void drawProgress(int done, int total) {
  int x = 8;
  int y = 42;
  int w = 304;
  int h = 18;
  tft.fillRoundRect(x, y, w, h, 5, C_CARD);
  int fillW = total > 0 ? (w - 4) * done / total : 0;
  tft.fillRoundRect(x + 2, y + 2, fillW, h - 4, 4, C_ORANGE);
  tft.setTextSize(1);
  tft.setTextColor(C_TEXT, C_CARD);
  tft.setCursor(x + 10, y + 5);
  tft.print(done);
  tft.print("/");
  tft.print(total);
  tft.print(" COMPLETE");
}

void drawChecklistCard(const ChecklistItem& item, int x, int y, int w, int h) {
  uint16_t stripe = item.completed ? C_GREEN : C_ORANGE;
  tft.fillRoundRect(x, y, w, h, 6, C_CARD);
  tft.fillRoundRect(x, y, 6, h, 4, stripe);
  tft.drawRoundRect(x, y, w, h, 6, C_LINE);

  tft.setTextSize(2);
  tft.setTextColor(item.completed ? C_GREEN : C_TEXT, C_CARD);
  tft.setCursor(x + 14, y + 7);
  tft.print(item.completed ? "X" : " ");
  tft.drawRect(x + 12, y + 6, 16, 16, item.completed ? C_GREEN : C_MUTED);

  tft.setTextColor(C_TEXT, C_CARD);
  tft.setCursor(x + 38, y + 7);
  printTrimmedInline(item.title, 18);

  if (strlen(item.dueTime) >= 5) {
    tft.setTextSize(1);
    tft.setTextColor(C_MUTED, C_CARD);
    tft.setCursor(x + w - 42, y + 10);
    tft.print(item.dueTime);
  }
}

void drawMessageCard(const MessageItem& msg, int x, int y, int w, int h) {
  tft.fillRoundRect(x, y, w, h, 6, C_CARD);
  tft.drawRoundRect(x, y, w, h, 6, C_LINE);
  tft.setTextSize(1);
  tft.setTextColor(C_ORANGE, C_CARD);
  tft.setCursor(x + 8, y + 6);
  tft.print(strcmp(msg.sender, "kid") == 0 ? "Bryson" : msg.sender);
  tft.print(":");
  tft.setTextColor(C_TEXT, C_CARD);
  tft.setCursor(x + 8, y + 20);
  printTrimmedInline(msg.message, 38);
}

void drawFooterButton(int x, int y, int w, int h, const char* label, uint16_t bg, uint16_t fg) {
  tft.fillRoundRect(x, y, w, h, 6, bg);
  tft.drawRoundRect(x, y, w, h, 6, C_LINE);
  tft.setTextSize(1);
  tft.setTextColor(fg, bg);
  int textX = x + max(4, (w - (int)strlen(label) * 6) / 2);
  tft.setCursor(textX, y + 8);
  tft.print(label);
}

void drawStatus(const char* line1, const char* line2) {
  tft.fillScreen(C_BG);
  tft.fillRoundRect(16, 42, 288, 132, 10, C_CARD);
  tft.drawRoundRect(16, 42, 288, 132, 10, C_ORANGE);
  tft.setTextSize(2);
  tft.setTextColor(C_ORANGE, C_CARD);
  tft.setCursor(28, 64);
  tft.print(line1);
  if (line2 && strlen(line2) > 0) {
    tft.setTextSize(1);
    tft.setTextColor(C_TEXT, C_CARD);
    tft.setCursor(28, 104);
    printTrimmedInline(line2, 38);
  }
}

void showAlert(int itemId, const char* title) {
  app.alertActive = true;
  app.activeAlertItemId = itemId;
  strncpy(app.activeAlertTitle, title, sizeof(app.activeAlertTitle) - 1);
  app.activeAlertTitle[sizeof(app.activeAlertTitle) - 1] = '\0';

  tft.fillScreen(C_BG);
  tft.fillRoundRect(10, 16, 300, 150, 12, C_CARD);
  tft.drawRoundRect(10, 16, 300, 150, 12, C_ORANGE);
  tft.fillRect(10, 16, 300, 8, C_ORANGE);

  tft.setTextSize(3);
  tft.setTextColor(C_ORANGE, C_CARD);
  tft.setCursor(28, 42);
  tft.print("REMINDER");

  tft.setTextSize(2);
  tft.setTextColor(C_TEXT, C_CARD);
  tft.setCursor(28, 92);
  printTrimmedInline(app.activeAlertTitle, 22);

  drawFooterButton(20, 180, 125, 44, "DONE", C_GREEN, TFT_BLACK);
  drawFooterButton(175, 180, 130, 44, "SNOOZE", C_YELLOW, TFT_BLACK);
}
