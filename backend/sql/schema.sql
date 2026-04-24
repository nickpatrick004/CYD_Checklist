CREATE TABLE cyd_devices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  device_name VARCHAR(100) NOT NULL,
  device_token VARCHAR(128) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL
);

CREATE TABLE cyd_checklist_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  device_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  due_time TIME NULL,
  repeat_days VARCHAR(32) NULL,
  alert_enabled TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  FOREIGN KEY (device_id) REFERENCES cyd_devices(id)
);

CREATE TABLE cyd_item_completions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  device_id INT NOT NULL,
  completed_date DATE NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_daily_completion (item_id, completed_date),
  FOREIGN KEY (item_id) REFERENCES cyd_checklist_items(id),
  FOREIGN KEY (device_id) REFERENCES cyd_devices(id)
);

CREATE TABLE cyd_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  device_id INT NOT NULL,
  sender ENUM('parent','kid') NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (device_id) REFERENCES cyd_devices(id)
);

CREATE TABLE cyd_parent_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
