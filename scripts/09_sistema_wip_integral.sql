-- ==========================================================
-- SCRIPT DE MIGRACIÓN: SISTEMA WIP INTEGRAL (FASE 1)
-- FIFO + CONSUMO PENDIENTE + CONCILIACIÓN
-- ==========================================================

START TRANSACTION;

-- 1. AGREGAR CAMPO DE SALDO DISPONIBLE EN DETALLES DE INVENTARIO
-- Este campo permitirá al motor FIFO saber cuánto hilo queda de cada entrega.
ALTER TABLE documentos_inventario_detalle
    ADD COLUMN IF NOT EXISTS saldo_disponible DECIMAL(12,4) DEFAULT NULL 
    AFTER cantidad;

-- 2. TABLA: consumos_wip_detalle (Trazabilidad Granular)
CREATE TABLE IF NOT EXISTS consumos_wip_detalle (
    id_consumo INT AUTO_INCREMENT PRIMARY KEY,
    id_lote_wip INT NOT NULL,
    id_documento_inventario INT NOT NULL COMMENT 'FK a documentos_inventario (SAL-TEJ)',
    id_documento_detalle INT NOT NULL COMMENT 'FK a documentos_inventario_detalle',
    id_inventario INT NOT NULL,
    cantidad_consumida DECIMAL(12,4) NOT NULL,
    costo_unitario_origen DECIMAL(12,4) NOT NULL,
    fecha_consumo DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_registro INT NULL,
    KEY idx_lote (id_lote_wip),
    KEY idx_documento (id_documento_inventario),
    KEY idx_inventario (id_inventario),
    CONSTRAINT fk_consumo_lote FOREIGN KEY (id_lote_wip) REFERENCES lote_wip(id_lote_wip) ON DELETE CASCADE,
    CONSTRAINT fk_consumo_doc FOREIGN KEY (id_documento_inventario) REFERENCES documentos_inventario(id_documento),
    CONSTRAINT fk_consumo_det FOREIGN KEY (id_documento_detalle) REFERENCES documentos_inventario_detalle(id_detalle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Trazabilidad granular de qué lote consumió de qué documento de salida';

-- 3. TABLA: consumos_wip_pendientes (Gestión de Faltantes)
CREATE TABLE IF NOT EXISTS consumos_wip_pendientes (
    id_pendiente INT AUTO_INCREMENT PRIMARY KEY,
    id_lote_wip INT NOT NULL,
    id_inventario INT NOT NULL,
    cantidad_requerida DECIMAL(12,4) NOT NULL,
    cantidad_consumida DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    cantidad_pendiente DECIMAL(12,4) NOT NULL,
    estado ENUM('PENDIENTE', 'REGULARIZADO') NOT NULL DEFAULT 'PENDIENTE',
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_registro INT NULL,
    fecha_regularizacion DATETIME NULL,
    usuario_regularizacion INT NULL,
    observacion TEXT NULL,
    KEY idx_lote_pendiente (id_lote_wip),
    KEY idx_estado (estado),
    CONSTRAINT fk_pendiente_lote FOREIGN KEY (id_lote_wip) REFERENCES lote_wip(id_lote_wip) ON DELETE CASCADE,
    CONSTRAINT fk_pendiente_inv FOREIGN KEY (id_inventario) REFERENCES inventarios(id_inventario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Registro de faltantes teóricos detectados durante el registro de producción';

-- 4. TABLAS DE CONCILIACIÓN WIP (Auditoría Física)
CREATE TABLE IF NOT EXISTS auditorias_wip_tejido (
    id_auditoria INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    id_area INT NOT NULL,
    estado ENUM('BORRADOR', 'CONFIRMADO', 'ANULADO') NOT NULL DEFAULT 'BORRADOR',
    usuario_registro INT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observacion TEXT NULL,
    KEY idx_fecha (fecha),
    CONSTRAINT fk_auditoria_area FOREIGN KEY (id_area) REFERENCES areas_produccion(id_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Cabecera de auditorías físicas de hilos en planta';

CREATE TABLE IF NOT EXISTS auditorias_wip_tejido_detalle (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_auditoria INT NOT NULL,
    id_inventario INT NOT NULL,
    saldo_sistema DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Suma de saldos_disponibles en sistema',
    saldo_fisico DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    diferencia DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    tipo_diferencia ENUM('SOBRANTE', 'FALTANTE', 'SIN_DIFERENCIA') NOT NULL,
    accion_ejecutada VARCHAR(100) NULL COMMENT 'Ajuste WIP (+/-), Merma, etc.',
    usuario_aprobacion INT NULL,
    fecha_aprobacion DATETIME NULL,
    observacion TEXT NULL,
    CONSTRAINT fk_aud_det_cab FOREIGN KEY (id_auditoria) REFERENCES auditorias_wip_tejido(id_auditoria) ON DELETE CASCADE,
    CONSTRAINT fk_aud_det_inv FOREIGN KEY (id_inventario) REFERENCES inventarios(id_inventario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Detalle de diferencias físicas detectadas';

-- 5. INICIALIZACIÓN DE SALDO_DISPONIBLE (Criterio Conservador)
-- Inicializamos el saldo con la cantidad original únicamente para documentos SAL-TEJ.
UPDATE documentos_inventario_detalle dd
JOIN documentos_inventario d ON d.id_documento = dd.id_documento
SET dd.saldo_disponible = dd.cantidad
WHERE d.tipo_documento = 'SALIDA' 
  AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
  AND d.estado = 'CONFIRMADO';

COMMIT;
