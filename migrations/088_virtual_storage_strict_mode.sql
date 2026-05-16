ALTER TABLE virtual_storages
    ADD COLUMN strict_mode TINYINT(1) NOT NULL DEFAULT 0 AFTER quota_bytes;
