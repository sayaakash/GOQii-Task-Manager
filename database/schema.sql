CREATE DATABASE IF NOT EXISTS goqii_task_manager
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE goqii_task_manager;

CREATE TABLE IF NOT EXISTS roles (
  id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(20) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id TINYINT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_users_email (email), KEY idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  owner_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  status ENUM('todo','in-progress','done') NOT NULL DEFAULT 'todo',
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  due_date DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_tasks_owner_status_due (owner_id,status,due_date), KEY idx_tasks_due_date (due_date),
  CONSTRAINT fk_tasks_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO roles (name) VALUES ('admin'),('user');

-- Example administrator promotion after registering the intended account:
-- UPDATE users SET role_id=(SELECT id FROM roles WHERE name='admin') WHERE email='admin@example.com';

-- Common filtered user query. The owner/status/due composite index supports the ownership
-- predicate and optional status filter, with due date ordering. Confirm on realistic data:
-- EXPLAIN SELECT id,title,status,priority,due_date FROM tasks
-- WHERE owner_id=1 AND status='todo' ORDER BY (due_date IS NULL),due_date,updated_at DESC LIMIT 10;
-- Expected: idx_tasks_owner_status_due selected; rows estimate narrowed by owner_id/status.
-- Admin-wide status queries use the status column's ENUM statistics and may scan tasks;
-- add a dedicated (status,due_date) index only if production EXPLAIN/slow logs show need.
