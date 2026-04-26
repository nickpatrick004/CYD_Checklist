#include "UI.h"
#include "Display.h"
#include "Theme.h"

static int clampInt(int value, int low, int high) {
  if (value < low) return low;
  if (value > high) return high;
  return value;
}

static int unreadMessageCount() {
  int unread = app.messageCount - app.seenMessageCount;
  if (unread < 0) unread = 0;
  if (unread > app.messageCount) unread = app.messageCount;
  return unread;
}

static void drawScrollBar(int x, int y, int h, int total, int visible, int offset) {
  tft.fillRoundRect(x, y, 10, h, 4, C_CARD);
  tft.drawRoundRect(x, y, 10, h, 4, C_LINE);

  if (total <= visible) {
    tft.fillRoundRect(x + 2, y + 2, 6, h - 4, 3, C_MUTED);
    return;
  }

  int thumbH = max(18, (h - 4) * visible / total);
  int maxOffset = total - visible;
  int travel = h - 4 - thumbH;
  int thumbY = y + 2 + (maxOffset > 0 ? travel * offset / maxOffset : 0);
  tft.fillRoundRect(x + 2, thumbY, 6, thumbH, 3, C_ORANGE);
}

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

  const int contentTop = 68;
  const int contentBottom = 202;
  const int cardX = 8;
  const int cardW = 288;
  const int cardH = 28;
  const int cardGap = 4;
  const int visibleChecklistItems = 4;
  const int scrollX = 302;
  const int scrollY = contentTop;
  const int scrollH = contentBottom - contentTop;

  int maxOffset = max(0, app.checklistCount - visibleChecklistItems);
  app.checklistScrollOffset = clampInt(app.checklistScrollOffset, 0, maxOffset);

  int y = contentTop;
  for (int row = 0; row < visibleChecklistItems; row++) {
    int i = app.checklistScrollOffset + row;
    if (i >= app.checklistCount) break;
    drawChecklistCard(app.checklistItems[i], cardX, y, cardW, cardH);
    addZone(app.checklistItems[i].id, cardX, y, cardX + cardW, y + cardH, "toggle", app.checklistItems[i].completed);
    y += cardH + cardGap;
  }

  if (app.checklistCount == 0) {
    tft.setTextColor(C_MUTED, C_BG);
    tft.setTextSize(2);
    tft.setCursor(24, 106);
    tft.print("No checklist items");
  }

  drawScrollBar(scrollX, scrollY, scrollH, app.checklistCount, visibleChecklistItems, app.checklistScrollOffset);
  if (app.checklistCount > visibleChecklistItems) {
    addZone(0, scrollX - 6, scrollY, 319, scrollY + scrollH / 2, "chk_up");
    addZone(0, scrollX - 6, scrollY + scrollH / 2, 319, scrollY + scrollH, "chk_down");
  }

  drawFooterButton(8, 208, 98, 24, "SYNC", C_PANEL, C_TEXT);
  addZone(0, 8, 208, 106, 232, "sync");

  drawFooterButton(112, 208, 200, 24, "MESSAGES", C_ORANGE, TFT_BLACK);
  int unread = unreadMessageCount();
  if (unread > 0) {
    char badge[12];
    snprintf(badge, sizeof(badge), "%d NEW", unread);
    tft.fillRoundRect(254, 211, 52, 18, 6, C_YELLOW);
    tft.drawRoundRect(254, 211, 52, 18, 6, C_LINE);
    tft.setTextSize(1);
    tft.setTextColor(TFT_BLACK, C_YELLOW);
    tft.setCursor(262, 217);
    tft.print(badge);
  }
  addZone(0, 112, 208, 312, 232, "messages");
}

void drawMessagesScreen() {
  app.zoneCount = 0;
  app.seenMessageCount = app.messageCount;
  tft.fillScreen(C_BG);
  drawHeader("MESSAGES", "REPLY MODE");

  const int footerY = 208;
  const int footerH = 24;
  const int replyTopY = 138;
  const int replyH = 24;
  const int replyGapY = 6;
  const int contentTop = 42;
  const int contentBottom = replyTopY - 6;
  const int cardX = 8;
  const int cardW = 288;
  const int scrollX = 302;
  const int scrollY = contentTop;
  const int scrollH = contentBottom - contentTop;
  const int visibleMessages = 2;

  int maxOffset = max(0, app.messageCount - visibleMessages);
  app.messageScrollOffset = clampInt(app.messageScrollOffset, 0, maxOffset);

  int y = contentTop;
  for (int row = 0; row < visibleMessages; row++) {
    int i = app.messageScrollOffset + row;
    if (i >= app.messageCount) break;
    drawMessageCard(app.messageItems[i], cardX, y, cardW, 38);
    y += 42;
  }

  if (app.messageCount == 0) {
    tft.setTextColor(C_MUTED, C_BG);
    tft.setTextSize(2);
    tft.setCursor(24, 72);
    tft.print("No messages yet");
  }

  drawScrollBar(scrollX, scrollY, scrollH, app.messageCount, visibleMessages, app.messageScrollOffset);
  if (app.messageCount > visibleMessages) {
    addZone(0, scrollX - 6, scrollY, 319, scrollY + scrollH / 2, "msg_up");
    addZone(0, scrollX - 6, scrollY + scrollH / 2, 319, scrollY + scrollH, "msg_down");
  }

  int visibleReplies = min(quickReplyCount, 4);
  for (int i = 0; i < visibleReplies; i++) {
    int x = (i % 2 == 0) ? 8 : 164;
    int y2 = replyTopY + (i / 2) * (replyH + replyGapY);
    drawFooterButton(x, y2, 148, replyH, quickReplies[i], C_ORANGE, TFT_BLACK);
    addZone(i, x, y2, x + 148, y2 + replyH, "reply");
  }

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
  printTrimmedInline(msg.message, 36);
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
  tft.fillRoundRect(10, 16, 284, 150, 12, C_CARD);
  tft.drawRoundRect(10, 16, 284, 150, 12, C_ORANGE);
  tft.fillRect(10, 16, 284, 8, C_ORANGE);

  tft.setTextSize(3);
  tft.setTextColor(C_ORANGE, C_CARD);
  tft.setCursor(28, 42);
  tft.print("REMINDER");

  tft.setTextSize(2);
  tft.setTextColor(C_TEXT, C_CARD);
  tft.setCursor(28, 92);
  printTrimmedInline(app.activeAlertTitle, 20);

  // Right-side visual rail keeps the alert content clear of the edge and
  // matches the messages screen layout.
  tft.fillRoundRect(302, 22, 10, 138, 4, C_CARD);
  tft.drawRoundRect(302, 22, 10, 138, 4, C_LINE);
  tft.fillRoundRect(304, 24, 6, 34, 3, C_ORANGE);

  drawFooterButton(20, 180, 125, 44, "DONE", C_GREEN, TFT_BLACK);
  drawFooterButton(175, 180, 130, 44, "SNOOZE", C_YELLOW, TFT_BLACK);
}
