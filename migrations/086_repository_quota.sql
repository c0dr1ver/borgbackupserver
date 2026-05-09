ALTER TABLE repositories
    ADD COLUMN quota_bytes BIGINT DEFAULT NULL AFTER size_bytes;
