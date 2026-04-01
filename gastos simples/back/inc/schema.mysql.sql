CREATE DATABASE IF NOT EXISTS gastos_simples
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_spanish_ci;

USE gastos_simples;


-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL DEFAULT 
CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
)   ENGINE=InnoDB
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_spanish_ci;


-- Tabla principal
CREATE TABLE IF NOT EXISTS expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    concept VARCHAR(180) NOT NULL,
    category VARCHAR(60) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT 
CURRENT_TIMESTAMP,
    CONSTRAINT fk_expenses_users
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    -- Índices
    INDEX idx_expenses_date (user_id, `date`),
    INDEX idx_expenses_category(user_id, category)
)   ENGINE=InnoDB 
    DEFAULT CHARSET=utf8mb4
    COLLATE=utf8mb4_spanish_ci;

-- amount > 0 se interpreta como gasto; amount < 0 como ingreso.
-- Índices para busquedas por mes y categoría

