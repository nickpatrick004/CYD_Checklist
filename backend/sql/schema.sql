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
  detail_text TEXT NULL,
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
  summary VARCHAR(255) NULL,
  message TEXT NOT NULL,
  detail_text TEXT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  is_archived TINYINT(1) NOT NULL DEFAULT 0,
  archived_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cyd_messages_device_archived_created (device_id, is_archived, created_at),
  FOREIGN KEY (device_id) REFERENCES cyd_devices(id)
);

CREATE TABLE cyd_message_reads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  reader_device_id INT NOT NULL,
  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_message_reader (message_id, reader_device_id),
  KEY idx_reader_device (reader_device_id),
  FOREIGN KEY (message_id) REFERENCES cyd_messages(id) ON DELETE CASCADE,
  FOREIGN KEY (reader_device_id) REFERENCES cyd_devices(id) ON DELETE CASCADE
);

CREATE TABLE cyd_parent_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
