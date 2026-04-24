# CYD Checklist

A Cheap Yellow Display (CYD) checklist and messaging system for a child’s room.

The parent side runs on a PHP/MySQL web host. The CYD acts as the child-side touchscreen device.

## Features

- Parent web dashboard
- Add checklist items remotely
- Timed checklist reminders
- Tap checklist items complete/incomplete on the CYD
- Parent-to-child messages
- Child-to-parent messages
- Device status page
- PHP + MySQL backend
- ESP32/CYD firmware

## Repository Structure

```text
backend/
  api/
  includes/
  sql/
  checklist.php
  index.php
  logout.php
  messages.php
  status.php

firmware/
  cyd_checklist.ino
