-- =====================================================
-- Telegram PIX Bot - Schema MySQL 8
-- Sistema de revenda com cobrança via PIX (chave configurável)
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS telegram_pix_bot
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE telegram_pix_bot;

-- -----------------------------------------------------
-- Tabela de administradores do painel
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Configurações gerais do sistema
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Usuários do Telegram
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  telegram_id BIGINT UNSIGNED NOT NULL UNIQUE,
  username VARCHAR(100) NULL,
  first_name VARCHAR(150) NULL,
  last_name VARCHAR(150) NULL,
  phone VARCHAR(20) NULL,
  document VARCHAR(20) NULL COMMENT 'CPF sem formatação',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_telegram_id (telegram_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Produtos / Planos para revenda
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  active TINYINT(1) DEFAULT 1,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Pedidos / Transações
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  custom_amount DECIMAL(10,2) NULL COMMENT 'Valor personalizado quando product_id é NULL',
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255) NOT NULL,
  status ENUM('pending','paid','expired','cancelled','failed') DEFAULT 'pending',
  gateway_transaction_id VARCHAR(100) NULL COMMENT 'ID externo opcional',
  external_transaction_id VARCHAR(100) NOT NULL COMMENT 'ID gerado pela nossa aplicação',
  pix_copy_paste TEXT NULL,
  qrcode_base64 LONGTEXT NULL,
  qrcode_url VARCHAR(500) NULL,
  paid_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  INDEX idx_status (status),
  INDEX idx_external_id (external_transaction_id),
  INDEX idx_gateway_id (gateway_transaction_id),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Logs de webhook (útil para debug)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS webhook_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payload JSON,
  headers JSON,
  processed TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -----------------------------------------------------
-- Dados iniciais
-- -----------------------------------------------------

-- Admin padrão (senha: admin123) - ALTERE IMEDIATAMENTE
INSERT INTO admins (name, email, password) VALUES
('Administrador', 'admin@localhost.com', '$2y$10$XUuqRWgbEiwjUFsiO4POrOE1UUGVYIreHHONLMjZAW1bC7tzMsQB6');

-- Configurações padrão
INSERT INTO settings (setting_key, setting_value) VALUES
('bot_token', ''),
('pix_key', ''),
('pix_key_type', 'ALEATORIA'),
('pix_holder_name', ''),
('welcome_message', 'Olá, {name}! 👋\n\nBem-vindo ao nosso sistema de pagamentos.\nEscolha uma opção abaixo:'),
('welcome_image', ''),
('paid_message', '✅ *Pagamento confirmado!*\n\nValor: R$ {amount}\nPedido: #{order_id}\n\nObrigado pela compra!'),
('paid_image', ''),
('support_username', ''),
('min_custom_amount', '5.00'),
('max_custom_amount', '5000.00'),
('bot_internal_url', 'http://127.0.0.1:3000'),
('admin_secret', '');

-- Produtos de exemplo
INSERT INTO products (name, description, price, sort_order) VALUES
('Plano Básico', 'Acesso básico por 30 dias', 19.90, 1),
('Plano Premium', 'Acesso completo por 30 dias', 49.90, 2),
('Plano VIP', 'Acesso VIP + suporte prioritário por 30 dias', 99.90, 3);

SET FOREIGN_KEY_CHECKS = 1;
