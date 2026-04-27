#include "Checklist.h"
#include "AppState.h"
#include "ApiClient.h"
#include "UI.h"
#include "config.h"

void markItemComplete(int itemId) {
  postItemState(COMPLETE_URL, itemId, "MARKING DONE");
}

void unmarkItemComplete(int itemId) {
  postItemState(UNCOMPLETE_URL, itemId, "UNDOING DONE");
}

void checkAlerts() {
  if (app.currentMinutes < 0) return;

  for (int i = 0; i < app.alertItemCount; i++) {
    if (!app.alertItems[i].alertEnabled) continue;
    if (app.alertItems[i].completed) continue;
    if (app.alertItems[i].dueMinutes < 0) continue;

    if (app.snoozedItemId == app.alertItems[i].itemId && app.currentMinutes < app.snoozeUntilMinutes) continue;

    bool insideDueWindow = app.currentMinutes >= app.alertItems[i].dueMinutes &&
                           app.currentMinutes <= app.alertItems[i].dueMinutes + ALERT_WINDOW_MINUTES;
    bool snoozeExpired = app.snoozedItemId == app.alertItems[i].itemId && app.currentMinutes >= app.snoozeUntilMinutes;

    if (insideDueWindow || snoozeExpired) {
      showAlert(app.alertItems[i].itemId, app.alertItems[i].title, app.alertItems[i].detail);
      return;
    }
  }
}
