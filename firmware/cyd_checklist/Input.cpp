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

  if (app.alertActive) {
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

  if (strcmp(action, "messages") == 0) {
    app.showingMessages = true;
    drawMessagesScreen();
    return;
  }

  if (strcmp(action, "home") == 0) {
    app.showingMessages = false;
    drawMainScreen();
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
  if (x >= 20 && x <= 145 && y >= 180 && y <= 224) {
    app.alertActive = false;
    markItemComplete(app.activeAlertItemId);
    return;
  }

  if (x >= 175 && x <= 305 && y >= 180 && y <= 224) {
    app.snoozedItemId = app.activeAlertItemId;
    app.snoozeUntilMinutes = app.currentMinutes + SNOOZE_MINUTES;
    if (app.snoozeUntilMinutes >= 1440) app.snoozeUntilMinutes -= 1440;
    app.alertActive = false;
    drawMainScreen();
    return;
  }
}
