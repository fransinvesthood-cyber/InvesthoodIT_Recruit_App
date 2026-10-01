CREATE TABLE IF NOT EXISTS supervisor_activity_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supervisor_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description VARCHAR(500) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sv_activity_supervisor (supervisor_id),
    KEY idx_sv_activity_action (action),
    KEY idx_sv_activity_created (supervisor_id, created_at)
);
