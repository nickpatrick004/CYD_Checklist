#include <Arduino.h>
#include "Display.h"
#include "Input.h"
#include "ApiClient.h"
#include "Checklist.h"
#include "UI.h"
#include "AppState.h"

void setup() {
  Serial.begin(115200);
  delay(800);

  initDisplay();
  initTouch();

  drawStatus("CYD CHECKLIST", "Starting display");
  connectWiFi();
  syncWithServer();
}

void loop() {
  handleTouch();
  serviceDeferredApiWork();

  if (!app.alertActive) checkAlerts();

  if (!app.alertActive && millis() - app.lastSync > SYNC_INTERVAL_MS) {
    app.lastSync = millis();
    syncWithServer();
  }
}
