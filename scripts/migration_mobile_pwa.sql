-- =====================================================
-- Migración: PWA Mobile Salidas de Inventario
-- Sistema MES Hermen Ltda.
-- Fecha: 2026-02-08
-- =====================================================

-- 1. Agregar campo PIN a usuarios (4 dígitos)
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS pin VARCHAR(4) DEFAULT NULL;

-- 2. Crear índice para búsqueda rápida por PIN
ALTER TABLE usuarios ADD INDEX idx_usuarios_pin (pin);

-- 3. Tabla principal para salidas móviles
CREATE TABLE IF NOT EXISTS salidas_moviles (
    id_salida_movil INT AUTO_INCREMENT PRIMARY KEY,
    uuid_local VARCHAR(36) NOT NULL UNIQUE COMMENT 'UUID generado en el dispositivo',
    tipo_salida ENUM('PRODUCCION','CONSUMO_INTERNO','MUESTRA','MERMA','AJUSTE') NOT NULL,
    id_area_destino INT NOT NULL COMMENT 'FK a areas_produccion',
    observaciones TEXT,
    usuario_entrega INT NOT NULL COMMENT 'Usuario que entrega el material',
    usuario_recibe INT NOT NULL COMMENT 'Usuario que recibe y firma con PIN',
    fecha_hora_local DATETIME NOT NULL COMMENT 'Fecha/hora registrada en el dispositivo',
    fecha_sincronizada DATETIME DEFAULT NULL COMMENT 'Fecha/hora de sincronización con servidor',
    estado_sync ENUM('PENDIENTE_SYNC','SINCRONIZADA','RECHAZADA','OBSERVADA') DEFAULT 'PENDIENTE_SYNC',
    motivo_rechazo TEXT COMMENT 'Motivo si fue rechazada u observada',
    id_documento_generado INT DEFAULT NULL COMMENT 'ID del documento de inventario generado al sincronizar',
    dispositivo_info VARCHAR(255) COMMENT 'Info del dispositivo (User-Agent)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_salidas_moviles_estado (estado_sync),
    INDEX idx_salidas_moviles_fecha (fecha_hora_local),
    INDEX idx_salidas_moviles_usuario (usuario_entrega),
    CONSTRAINT fk_salidas_moviles_area FOREIGN KEY (id_area_destino) REFERENCES areas_produccion(id_area) ON DELETE RESTRICT,
    CONSTRAINT fk_salidas_moviles_entrega FOREIGN KEY (usuario_entrega) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_salidas_moviles_recibe FOREIGN KEY (usuario_recibe) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Detalle de productos en salida móvil
CREATE TABLE IF NOT EXISTS salidas_moviles_detalle (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_salida_movil INT NOT NULL,
    id_inventario INT NOT NULL COMMENT 'FK a inventarios',
    cantidad DECIMAL(12,4) NOT NULL,
    stock_referencial DECIMAL(12,4) DEFAULT NULL COMMENT 'Stock al momento del registro (referencial)',
    observaciones VARCHAR(255) DEFAULT NULL,
    INDEX idx_detalle_salida (id_salida_movil),
    INDEX idx_detalle_inventario (id_inventario),
    CONSTRAINT fk_detalle_salida FOREIGN KEY (id_salida_movil) REFERENCES salidas_moviles(id_salida_movil) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_inventario FOREIGN KEY (id_inventario) REFERENCES inventarios(id_inventario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Insertar áreas de producción básicas si no existen
INSERT IGNORE INTO areas_produccion (codigo, nombre, descripcion, activo) VALUES
('CORTE', 'Corte', 'Área de corte de tela', 1),
('COSTURA', 'Costura', 'Área de costura', 1),
('TINTORERIA', 'Tintorería', 'Área de teñido', 1),
('VAPORIZADO', 'Vaporizado', 'Área de vaporizado', 1),
('TEJIDO', 'Tejido', 'Área de tejeduría', 1),
('ALMACEN', 'Almacén', 'Almacén general', 1),
('ACABADO', 'Acabado', 'Área de acabado y empaque', 1);

-- 6. Asignar PINs de ejemplo a usuarios existentes (cambiar en producción)
-- UPDATE usuarios SET pin = LPAD(FLOOR(RAND() * 10000), 4, '0') WHERE pin IS NULL;

-- Verificación
SELECT 'Migración completada exitosamente' AS resultado;
