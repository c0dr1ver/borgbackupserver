CREATE TABLE virtual_storages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    user_id INT NOT NULL,
    quota_bytes BIGINT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

CREATE TABLE virtual_storage_repositories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    virtual_storage_id INT NOT NULL,
    repository_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (virtual_storage_id) REFERENCES virtual_storages(id) ON DELETE CASCADE,
    FOREIGN KEY (repository_id) REFERENCES repositories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_virtual_storage_repo (virtual_storage_id, repository_id),
    INDEX idx_repository_id (repository_id)
) ENGINE=InnoDB;
