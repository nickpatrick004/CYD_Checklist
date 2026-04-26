#pragma once
#include <TFT_eSPI.h>
#include <SPI.h>

#define TOUCH_CS 33
#define TOUCH_IRQ 36
#define TOUCH_MOSI 32
#define TOUCH_MISO 39
#define TOUCH_CLK 25

extern TFT_eSPI tft;
extern SPIClass touchSPI;

void initDisplay();
void printTrimmedInline(const char* text, int maxChars);
