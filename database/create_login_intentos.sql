-- ============================================================
-- Seguridad: Tabla para protección contra fuerza bruta
-- Ecommerce-Tinkuy
-- EJECUTAR en tinkuy_db ANTES de activar el módulo de seguridad
-- ============================================================

USE tinkuy_db;

CREATE TABLE IF NOT EXISTS login_intentos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ip            VARCHAR(45)  NOT NULL,
    usuario       VARCHAR(100) DEFAULT NULL,
    exitoso       TINYINT(1)   NOT NULL DEFAULT 0,
    fecha_intento DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_fecha      (ip, fecha_intento),
    INDEX idx_usuario_fecha (usuario, fecha_intento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Registro de intentos de login para protección anti fuerza bruta';

SELECT '✅ Tabla login_intentos creada correctamente.' AS Status;
