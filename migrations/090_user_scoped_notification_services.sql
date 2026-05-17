ALTER TABLE notification_services
    ADD COLUMN user_id INT DEFAULT NULL AFTER id,
    ADD INDEX idx_user_id (user_id),
    ADD CONSTRAINT fk_notification_services_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
