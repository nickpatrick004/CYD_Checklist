#include "Input.h"
#include "Display.h"
#include "AppState.h"
#include "UI.h"
#include "Checklist.h"
#include "ApiClient.h"

XPT2046_Touchscreen ts(TOUCH_CS, TOUCH_IRQ);

void initTouch() {
  touchSPI.begin(TOUCH_CLK, TOUCH_MISO, TOUCH_MOSI, TOUCH_CS);
  ts.begin(touchSPI);
  ts.setRotation(1);
}

void handleTouch() {
  if (!ts.touched()) return;
  if (millis() - app.lastTouch < TOUCH_DEBOUNCE_MS) return;
  app.lastTouch = millis();

  TS_Point p = ts.getPoint();
  int x = map(p.x, 200, 3800, 0, 320);
  int y = map(p.y, 200, 3800, 0, 240);
  x = constrain(x, 0, 319);
  y = constrain(y, 0, 239);

  if (app.alertActive && !app.showingAlertDetail) {
    handleAlertTouch(x, y);
    return;
  }

  for (int i = 0; i < app.zoneCount; i++) {
    TouchZone z = app.zones[i];
    if (x >= z.x1 && x <= z.x2 && y >= z.y1 && y <= z.y2) {
      handleAction(z.action, z.id, z.completed);
      return;
    }
  }
}

void handleAction(const char* action, int id, bool completed) {
  if (strcmp(action, "toggle") == 0) {
    if (completed) unmarkItemComplete(id);
    else markItemComplete(id);
    return;
  }

  if (strcmp(action, "item_detail") == 0) {
    app.showingChecklistDetail = true;
    app.selectedChecklistIndex = id;
    app.showingMessages = false;
    drawChecklistDetailScreen(id);
    return;
  }

  if (strcmp(action, "message_detail") == 0) {
    app.showingMessageDetail = true;
    app.selectedMessageIndex = id;
    drawMessageDetailScreen(id);
    return;
  }

  if (strcmp(action, "messages") == 0) {
    app.showingMessages = true;
    app.showingMessageDetail = false;
    app.showingChecklistDetail = false;
    app.messageScrollOffset = 0;
    app.messagesOpenedAt = millis();
    app.pendingMarkMessagesRead = true;
    drawMessagesScreen();
    return;
  }

  if (strcmp(action, "messages_back") == 0) {
    app.showingMessageDetail = false;
    app.selectedMessageIndex = -1;
    drawMessagesScreen();
    return;
  }

  if (strcmp(action, "home") == 0) {
    app.showingMessages = false;
    app.showingMessageDetail = false;
    app.showingChecklistDetail = false;
    drawMainScreen();
    return;
  }

  if (strcmp(action, "alert_detail") == 0) {
    app.showingAlertDetail = true;
    drawAlertDetailScreen();
    return;
  }

  if (strcmp(action, "alert_back") == 0) {
    showAlert(app.activeAlertItemId, app.activeAlertTitle, app.activeAlertDetail);
    return;
  }

  if (strcmp(action, "chk_up") == 0) {
    if (app.checklistScrollOffset > 0) app.checklistScrollOffset--;
    drawMainScreen();
    return;
  }

  if (strcmp(action, "chk_down") == 0) {
    int maxOffset = max(0, app.checklistCount - 4);
    if (app.checklistScrollOffset < maxOffset) app.checklistScrollOffset++;
    drawMainScreen();
    return;
  }

  if (strcmp(action, "msg_up") == 0) {
    if (app.messageScrollOffset > 0) app.messageScrollOffset--;
    drawMessagesScreen();
    return;
  }

  if (strcmp(action, "msg_down") == 0) {
    int maxOffset = max(0, app.messageCount - 2);
    if (app.messageScrollOffset < maxOffset) app.messageScrollOffset++;
    drawMessagesScreen();
    return;
  }

  if (strcmp(action, "reply") == 0) {
    if (id >= 0 && id < quickReplyCount) sendMessage(quickReplies[id]);
    return;
  }

  if (strcmp(action, "sync") == 0) {
    syncWithServer();
    return;
  }
}

void handleAlertTouch(int x, int y) {
  for (int i = 0; i < app.zoneCount; i++) {
    TouchZone z = app.zones[i];
    if (x >= z.x1 && x <= z.x2 && y >= z.y1 && y <= z.y2) {
      handleAction(z.action, z.id, z.completed);
      return;
    }
  }

  if (x >= 20 && x <= 145 && y >= 180 && y <= 224) {
    app.alertActive = false;
    app.showingAlertDetail = false;
    markItemComplete(app.activeAlertItemId);
    return;
  }

  if (x >= 175 && x <= 305 && y >= 180 && y <= 224) {
    app.snoozedItemId = app.activeAlertItemId;
    app.snoozeUntilMinutes = app.currentMinutes + SNOOZE_MINUTES;
    if (app.snoozeUntilMinutes >= 1440) app.snoozeUntilMinutes -= 1440;
    app.alertActive = false;
    app.showingAlertDetail = false;
    drawMainScreen();
    return;
  }
}
