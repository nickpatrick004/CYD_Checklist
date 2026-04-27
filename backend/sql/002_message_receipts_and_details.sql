-- CYD Checklist migration: message read receipts and detail text
-- Run once after backend/sql/schema.sql has already been installed.

ALTER TABLE cyd_checklist_items
  ADD COLUMN detail_text TEXT NULL AFTER title;

ALTER TABLE cyd_messages
  ADD COLUMN summary VARCHAR(255) NULL AFTER sender,
  ADD COLUMN detail_text TEXT NULL AFTER message;

CREATE TABLE IF NOT EXISTS cyd_message_reads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  reader_device_id INT NOT NULL,
  read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_message_reader (message_id, reader_device_id),
  KEY idx_reader_device (reader_device_id),
  CONSTRAINT fk_cyd_message_reads_message
    FOREIGN KEY (message_id) REFERENCES cyd_messages(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_cyd_message_reads_device
    FOREIGN KEY (reader_device_id) REFERENCES cyd_devices(id)
    ON DELETE CASCADE
);

-- Preserve old single-device read state as per-device receipts.
INSERT IGNORE INTO cyd_message_reads (message_id, reader_device_id, read_at)
SELECT id, device_id, COALESCE(created_at, NOW())
FROM cyd_messages
WHERE is_read = 1;
