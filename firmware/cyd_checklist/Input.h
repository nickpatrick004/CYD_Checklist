#pragma once
#include <XPT2046_Touchscreen.h>

extern XPT2046_Touchscreen ts;

void initTouch();
void handleTouch();
void handleAction(const char* action, int id, bool completed);
void handleAlertTouch(int x, int y);
