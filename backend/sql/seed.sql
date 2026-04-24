INSERT INTO cyd_devices (device_name, device_token)
VALUES ('Bedroom CYD', 'CHANGE_THIS_DEVICE_TOKEN');

INSERT INTO cyd_checklist_items
(device_id, title, due_time, repeat_days, alert_enabled)
VALUES
(1, 'Brush teeth', '19:30:00', 'daily', 1),
(1, 'Put clothes in hamper', '19:45:00', 'daily', 1),
(1, 'Pack backpack', '20:00:00', 'mon,tue,wed,thu,fri', 1);
