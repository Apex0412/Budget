SET NAMES utf8mb4;
SET time_zone = '+03:00';

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fio VARCHAR(255) NOT NULL,
  position VARCHAR(255) DEFAULT NULL,
  department VARCHAR(255) DEFAULT NULL,
  login VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS requests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  author_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  status ENUM('draft','submitted','returned','approved','rejected','in_progress','purchased') NOT NULL DEFAULT 'submitted',
  justification TEXT NOT NULL,
  basis TEXT DEFAULT 'Муниципальное задание и Правила благоустройства',
  service_objects TEXT DEFAULT NULL,
  period_label VARCHAR(255) DEFAULT NULL,
  priority ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal',
  deadline_date DATE DEFAULT NULL,
  pdf_generated TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT NOT NULL,
  category VARCHAR(255) NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  unit VARCHAR(64) NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  purpose VARCHAR(255) DEFAULT NULL,
  features VARCHAR(255) DEFAULT NULL,
  stock_qty DECIMAL(12,2) DEFAULT NULL,
  need_qty DECIMAL(12,2) DEFAULT NULL,
  purchase_qty DECIMAL(12,2) DEFAULT NULL,
  distribution JSON DEFAULT NULL,
  note VARCHAR(500) DEFAULT NULL,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(64) NOT NULL,
  entity VARCHAR(64) NULL,
  entity_id BIGINT NULL,
  meta JSON NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_files (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(128) DEFAULT NULL,
  size BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
  INDEX idx_request_files_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) UNIQUE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(64) UNIQUE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category_id INT NULL,
  unit_id INT NULL,
  description VARCHAR(500) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL,
  UNIQUE KEY unique_material (name, category_id, unit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name) VALUES
  ('Спецодежда') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO categories (name) VALUES
  ('Инструмент') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO categories (name) VALUES
  ('Материалы') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO categories (name) VALUES
  ('ДИП') ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO units (name) VALUES
  ('шт.') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('компл.') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('упак.') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('пара') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('м') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('м²') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('м³') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('л') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('кг') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('т') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('рулон') ON DUPLICATE KEY UPDATE name = VALUES(name);
INSERT INTO units (name) VALUES
  ('час') ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Куртка утеплённая зимняя', c.id, u.id, 'Комплект с утеплителем до -30°C', 1
FROM categories c, units u
WHERE c.name = 'Спецодежда' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Брюки утеплённые зимние', c.id, u.id, 'Для наружных работ, влагозащита', 1
FROM categories c, units u
WHERE c.name = 'Спецодежда' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Перчатки диэлектрические', c.id, u.id, 'Защита до 1000 В, ГОСТ', 1
FROM categories c, units u
WHERE c.name = 'Спецодежда' AND u.name = 'пара'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Каска защитная ударопрочная', c.id, u.id, 'Регулируемый обхват, вентиляция', 1
FROM categories c, units u
WHERE c.name = 'Спецодежда' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Респиратор FFP3', c.id, u.id, 'Фильтр класса P3, многоразовый', 1
FROM categories c, units u
WHERE c.name = 'Спецодежда' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Перчатки нитриловые усиленные', c.id, u.id, 'Устойчивы к химреагентам, 50 пар', 1
FROM categories c, units u
WHERE c.name = 'Спецодежда' AND u.name = 'упак.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Бензопила профессиональная', c.id, u.id, 'Шина 45 см, комплект цепей', 1
FROM categories c, units u
WHERE c.name = 'Инструмент' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Отбойный молоток электрический', c.id, u.id, 'Мощность 1700 Вт, кейс и пики', 1
FROM categories c, units u
WHERE c.name = 'Инструмент' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Удлинитель силовой 30 м', c.id, u.id, '3 розетки, IP44', 1
FROM categories c, units u
WHERE c.name = 'Инструмент' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Лопата штыковая усиленная', c.id, u.id, 'Стальная, черенок фиберглас', 1
FROM categories c, units u
WHERE c.name = 'Инструмент' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Щебень фракция 20-40', c.id, u.id, 'Для дорожных оснований', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'т'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Песок мытый карьерный', c.id, u.id, 'Просеянный, влажность <5%', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'т'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Бетон М300 B22.5', c.id, u.id, 'С поставкой автобетоносмесителем', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'м³'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Кирпич керамический рядовой', c.id, u.id, 'ГОСТ 530-2012, полнотелый', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Плитка тротуарная 300x300', c.id, u.id, 'Вибропрессованная, цвет серая', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'м²'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Грунтовка глубокого проникновения', c.id, u.id, 'Канистра 10 л', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'л'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Краска фасадная акриловая', c.id, u.id, 'Ведро 15 л, цвет белый', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'л'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Масло моторное 5W-40', c.id, u.id, 'Полусинтетика, канистра 4 л', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'л'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Комплект ламп LED 20 Вт', c.id, u.id, 'Энергоэффективные, 10 шт.', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'компл.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Дорожный знак 1.23', c.id, u.id, 'Отражающая плёнка тип 2', 1
FROM categories c, units u
WHERE c.name = 'ДИП' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Секция металлического ограждения', c.id, u.id, 'Порошковая окраска, высота 1.5 м', 1
FROM categories c, units u
WHERE c.name = 'ДИП' AND u.name = 'шт.'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Дорожная краска белая', c.id, u.id, 'Быстросохнущая, ведро 20 л', 1
FROM categories c, units u
WHERE c.name = 'ДИП' AND u.name = 'л'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Геотекстиль 150 г/м²', c.id, u.id, 'Рулон 2x50 м', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'рулон'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Соль техническая противогололёдная', c.id, u.id, 'Фасовка по 50 кг', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'т'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Пескосоль смесь 70/30', c.id, u.id, 'Для обработки дорог', 1
FROM categories c, units u
WHERE c.name = 'Материалы' AND u.name = 'т'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);

INSERT INTO materials (name, category_id, unit_id, description, is_active)
SELECT 'Укладка резинового покрытия', c.id, u.id, 'Работы по монтажу спортивного покрытия', 1
FROM categories c, units u
WHERE c.name = 'ДИП' AND u.name = 'м²'
ON DUPLICATE KEY UPDATE description = VALUES(description), is_active = VALUES(is_active);
