#include "Display.h"
#include "Theme.h"

TFT_eSPI tft = TFT_eSPI();
SPIClass touchSPI = SPIClass(VSPI);

void initDisplay() {
  tft.init();
  pinMode(21, OUTPUT);
  digitalWrite(21, HIGH);
  tft.setRotation(1);
  tft.fillScreen(C_BG);
}

void printTrimmedInline(const char* text, int maxChars) {
  if (!text) return;
  int len = strlen(text);
  if (len <= maxChars) {
    tft.print(text);
    return;
  }
  for (int i = 0; i < maxChars - 3; i++) tft.print(text[i]);
  tft.print("...");
}
