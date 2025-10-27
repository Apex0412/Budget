CREATE DATABASE IF NOT EXISTS finanses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE finanses;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fio VARCHAR(255) NOT NULL,
  position VARCHAR(255) DEFAULT NULL,
  department VARCHAR(255) DEFAULT NULL,
  login VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin','viewer') NOT NULL DEFAULT 'user',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  theme VARCHAR(16) DEFAULT 'light',
  locale VARCHAR(8) DEFAULT 'ru',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE requests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  author_id INT NOT NULL,
  status ENUM('draft','submitted','returned','approved','rejected','in_progress','purchased') NOT NULL DEFAULT 'draft',
  priority ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal',
  justification TEXT NOT NULL,
  basis VARCHAR(500) NOT NULL DEFAULT 'Муниципальное задание и Правила благоустройства',
  service_objects JSON NULL,
  deadline_date DATE NULL,
  pdf_generated TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE INDEX idx_requests_status ON requests(status);
CREATE INDEX idx_requests_priority ON requests(priority);
CREATE INDEX idx_requests_deadline ON requests(deadline_date);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) DEFAULT NULL,
  name VARCHAR(255) NOT NULL,
  category_id INT NULL,
  unit_id INT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE INDEX idx_materials_category ON materials(category_id);

CREATE TABLE request_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT NOT NULL,
  category_id INT NULL,
  material_id INT NULL,
  unit_id INT NULL,
  qty DECIMAL(12,2) NOT NULL DEFAULT 0,
  assignment VARCHAR(255) DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  current_stock DECIMAL(12,2) DEFAULT 0,
  required_qty DECIMAL(12,2) DEFAULT 0,
  purchase_qty DECIMAL(12,2) DEFAULT 0,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE SET NULL,
  FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE request_files (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT NOT NULL,
  path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(64) NOT NULL,
  entity VARCHAR(64) NULL,
  entity_id BIGINT NULL,
  meta JSON NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE INDEX idx_audit_action ON audit_log(action);
