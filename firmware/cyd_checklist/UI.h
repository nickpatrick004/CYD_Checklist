#pragma once
#include "AppState.h"

void drawMainScreen();
void drawMessagesScreen();
void drawStatus(const char* line1, const char* line2);
void drawHeader(const char* title, const char* status);
void drawProgress(int done, int total);
void drawChecklistCard(const ChecklistItem& item, int x, int y, int w, int h);
void drawMessageCard(const MessageItem& msg, int x, int y, int w, int h);
void drawFooterButton(int x, int y, int w, int h, const char* label, uint16_t bg, uint16_t fg);
void addZone(int id, int x1, int y1, int x2, int y2, const char* action, bool completed = false);
void showAlert(int itemId, const char* title);
