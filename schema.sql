CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
 id TINYINT UNSIGNED PRIMARY KEY,
 schema_version VARCHAR(20) NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS missions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL,
 objective TEXT NOT NULL,
 suite VARCHAR(32) NOT NULL,
 config_json LONGTEXT NOT NULL,
 config_hash CHAR(64) NOT NULL,
 status ENUM('queued','running','completed','failed') NOT NULL DEFAULT 'queued',
 error_text TEXT NULL,
 created_at DATETIME NOT NULL,
 started_at DATETIME NULL,
 completed_at DATETIME NULL,
 INDEX(status), INDEX(config_hash),
 CONSTRAINT fk_mission_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 mission_id BIGINT UNSIGNED NOT NULL,
 config_hash CHAR(64) NOT NULL,
 split_hash CHAR(64) NOT NULL,
 split_manifest_json LONGTEXT NOT NULL,
 software_version VARCHAR(32) NOT NULL,
 provider_model VARCHAR(80) NOT NULL,
 code_manifest_hash CHAR(64) NOT NULL,
 evidence_root_sha256 CHAR(64) NULL,
 status ENUM('running','completed','failed') NOT NULL,
 analysis_json LONGTEXT NULL,
 analysis_sha256 CHAR(64) NULL,
 usage_json LONGTEXT NULL,
 usage_sha256 CHAR(64) NULL,
 error_text TEXT NULL,
 started_at DATETIME NOT NULL,
 completed_at DATETIME NULL,
 CONSTRAINT fk_run_mission FOREIGN KEY(mission_id) REFERENCES missions(id) ON DELETE CASCADE,
 INDEX(mission_id), INDEX(status), INDEX(config_hash), INDEX(split_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS condition_results (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 run_id BIGINT UNSIGNED NOT NULL,
 condition_name VARCHAR(80) NOT NULL,
 seed INT NOT NULL,
 result_sha256 CHAR(64) NOT NULL,
 result_json LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_result_run FOREIGN KEY(run_id) REFERENCES runs(id) ON DELETE CASCADE,
 UNIQUE KEY uq_run_condition_seed(run_id,condition_name,seed),
 INDEX(run_id), INDEX(condition_name), INDEX(seed), INDEX(result_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS run_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 run_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(64) NOT NULL,
 event_sha256 CHAR(64) NOT NULL,
 event_json LONGTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_event_run FOREIGN KEY(run_id) REFERENCES runs(id) ON DELETE CASCADE,
 INDEX(run_id), INDEX(event_type), INDEX(event_sha256)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
