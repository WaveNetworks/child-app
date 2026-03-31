-- Migration 1.2: Add childapp_user_prefs table for tier storage

CREATE TABLE IF NOT EXISTS childapp_user_prefs (
    user_id INT UNSIGNED NOT NULL,
    pref_key VARCHAR(100) NOT NULL,
    pref_value TEXT NULL,
    updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, pref_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
