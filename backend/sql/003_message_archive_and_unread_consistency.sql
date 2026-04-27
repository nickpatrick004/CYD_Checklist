-- CYD Checklist migration: message archive/delete support and unread count consistency
-- Run once after 002_message_receipts_and_details.sql.

ALTER TABLE cyd_messages
  ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read,
  ADD COLUMN archived_at DATETIME NULL AFTER is_archived;

CREATE INDEX idx_cyd_messages_device_archived_created
  ON cyd_messages (device_id, is_archived, created_at);
