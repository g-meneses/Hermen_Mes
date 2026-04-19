-- =============================================================================
-- FASE 1: Stock Físico de Materia Prima en Planta WIP Tejido
-- Sistema MES Hermen Ltda.
-- Versión: 1.0 — 2026-04-14
-- =============================================================================
-- 
-- PROPÓSITO:
--   Añadir la capa de stock físico en planta sin romper el modelo legado.
--   El modelo de coexistencia es:
--     - Legado:  SAL-TEJ → saldo_disponible → FIFO consumo
--     - Nuevo:   SAL-TEJ → confirmar_transferencia_mp → wip_stock_mp → (Fase 2) consumo real
--
-- ESTRATEGIA ANTI DOBLE CONTEO:
--   Se agrega el flag `integrado_wip_nuevo_modelo` a documentos_inventario.
--   El motor FIFO legado ignorará documentos con ese flag = 1.
--   La confirmación de transferencia activa el flag atómicamente.
--
-- ORDEN DE EJECUCIÓN:
--   1. Ejecutar los CREATE TABLE (idempotentes con IF NOT EXISTS)
--   2. Ejecutar el ALTER TABLE (idempotente con verificación previa)
--   3. Ejecutar la migración inicial de wip_stock_mp
--   4. Verificar con las queries de validación al final
-- =============================================================================

-- ----------------------------------------------------------------------------
-- PASO 1: Tabla wip_stock_mp — Stock disponible por componente en planta
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wip_stock_mp` (
    `id_stock_wip`        INT(11)         NOT NULL AUTO_INCREMENT,
    `id_inventario`       INT(11)         NOT NULL COMMENT 'FK inventarios.id_inventario',
    `stock_disponible`    DECIMAL(12,4)   NOT NULL DEFAULT 0.0000
                          COMMENT 'Kg disponibles en planta pendientes de consumir',
    `stock_reservado`     DECIMAL(12,4)   NOT NULL DEFAULT 0.0000
                          COMMENT 'Kg asignados a lotes en proceso (uso futuro Fase 2)',
    `fecha_actualizacion` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_stock_wip`),
    UNIQUE KEY `uq_wip_inventario` (`id_inventario`),
    CONSTRAINT `fk_wip_stock_inventario`
        FOREIGN KEY (`id_inventario`) REFERENCES `inventarios` (`id_inventario`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stock físico de materia prima disponible en planta de tejido';

-- ----------------------------------------------------------------------------
-- PASO 2: Tabla wip_transferencias_mp — Historial de movimientos MP a planta
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wip_transferencias_mp` (
    `id_transferencia`   INT(11)         NOT NULL AUTO_INCREMENT,
    `id_documento_sal`   INT(11)         NOT NULL COMMENT 'FK documentos_inventario (SAL-TEJ)',
    `id_inventario`      INT(11)         NOT NULL COMMENT 'FK inventarios',
    `cantidad_kg`        DECIMAL(12,4)   NOT NULL COMMENT 'Kg transferidos/devueltos',
    `tipo`               ENUM('ENTRADA_PLANTA','DEVOLUCION_ALMACEN') NOT NULL
                         DEFAULT 'ENTRADA_PLANTA'
                         COMMENT 'ENTRADA_PLANTA = almacén→planta; DEVOLUCION_ALMACEN = inverso',
    `fecha_transferencia` DATE           NOT NULL COMMENT 'Fecha del SAL-TEJ origen',
    `usuario`            INT(11)         DEFAULT NULL COMMENT 'FK usuarios',
    `observaciones`      TEXT            DEFAULT NULL,
    `fecha_creacion`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_transferencia`),
    KEY `idx_wip_trans_documento`  (`id_documento_sal`),
    KEY `idx_wip_trans_inventario` (`id_inventario`),
    KEY `idx_wip_trans_tipo`       (`tipo`),
    CONSTRAINT `fk_wip_trans_documento`
        FOREIGN KEY (`id_documento_sal`) REFERENCES `documentos_inventario` (`id_documento`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT `fk_wip_trans_inventario`
        FOREIGN KEY (`id_inventario`) REFERENCES `inventarios` (`id_inventario`)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de transferencias de MP entre almacén y planta WIP';

-- ----------------------------------------------------------------------------
-- PASO 3: Flag anti doble conteo en documentos_inventario
--         Solo se agrega si no existe. Idempotente.
-- ----------------------------------------------------------------------------
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'documentos_inventario'
      AND COLUMN_NAME  = 'integrado_wip_nuevo_modelo'
);

SET @alter_sql = IF(
    @col_exists = 0,
    'ALTER TABLE documentos_inventario
     ADD COLUMN integrado_wip_nuevo_modelo TINYINT(1) NOT NULL DEFAULT 0
     COMMENT ''1 = ya fue confirmado a wip_stock_mp; el FIFO legado debe ignorar este documento''',
    'SELECT ''Columna integrado_wip_nuevo_modelo ya existe, no se modifica'' AS info'
);

PREPARE stmt FROM @alter_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice sobre el nuevo flag para que el FIFO no sea lento
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'documentos_inventario'
      AND INDEX_NAME   = 'idx_doc_integrado_wip'
);

SET @idx_sql = IF(
    @idx_exists = 0,
    'ALTER TABLE documentos_inventario ADD INDEX idx_doc_integrado_wip (integrado_wip_nuevo_modelo)',
    'SELECT ''Índice idx_doc_integrado_wip ya existe'' AS info'
);

PREPARE stmt2 FROM @idx_sql;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- =============================================================================
-- MIGRACIÓN INICIAL: Cargar wip_stock_mp con el estado actual del sistema
-- =============================================================================
-- IMPORTANTE:
--   Este bloque es SEGURO y REPETIBLE gracias al ON DUPLICATE KEY UPDATE.
--   Si se ejecuta dos veces, sobreescribe con el valor actual (no suma dos veces).
--   NO marca documentos con integrado_wip_nuevo_modelo = 1.
--   Los datos históricos siguen usando el modelo legado (saldo_disponible).
--   Solo nuevas confirmaciones manuales activarán el flag.
-- =============================================================================

INSERT INTO wip_stock_mp (id_inventario, stock_disponible, stock_reservado)
SELECT
    dd.id_inventario,
    ROUND(SUM(dd.saldo_disponible), 4)  AS stock_disponible,
    0.0000                               AS stock_reservado
FROM documentos_inventario_detalle dd
JOIN documentos_inventario d ON d.id_documento = dd.id_documento
WHERE
    d.tipo_documento = 'SALIDA'
    AND (
        d.tipo_consumo = 'TEJIDO'
        OR d.numero_documento LIKE 'SAL-TEJ%'
    )
    AND d.estado = 'CONFIRMADO'
    AND COALESCE(d.integrado_wip_nuevo_modelo, 0) = 0   -- solo documentos legado
    AND dd.saldo_disponible > 0
GROUP BY dd.id_inventario
HAVING ROUND(SUM(dd.saldo_disponible), 4) > 0
ON DUPLICATE KEY UPDATE
    stock_disponible    = VALUES(stock_disponible),
    stock_reservado     = 0.0000,
    fecha_actualizacion = CURRENT_TIMESTAMP;

-- =============================================================================
-- QUERIES DE VALIDACIÓN POST-MIGRACIÓN
-- =============================================================================

-- V1: Ver el stock WIP cargado
SELECT
    ws.id_inventario,
    i.codigo,
    i.nombre,
    u.abreviatura                             AS unidad,
    ROUND(ws.stock_disponible, 4)             AS stock_disponible_kg,
    ROUND(ws.stock_reservado, 4)              AS stock_reservado_kg,
    ws.fecha_actualizacion
FROM wip_stock_mp ws
JOIN inventarios i ON i.id_inventario = ws.id_inventario
JOIN unidades_medida u ON u.id_unidad = i.id_unidad
ORDER BY i.nombre;

-- V2: Cruzar contra saldo_disponible para verificar consistencia
SELECT
    i.nombre                                      AS componente,
    ROUND(ws.stock_disponible, 4)                 AS wip_stock_mp_kg,
    ROUND(SUM(dd.saldo_disponible), 4)            AS saldo_disponible_total_docs,
    ROUND(ws.stock_disponible - SUM(dd.saldo_disponible), 4) AS diferencia
FROM wip_stock_mp ws
JOIN inventarios i ON i.id_inventario = ws.id_inventario
JOIN documentos_inventario_detalle dd ON dd.id_inventario = ws.id_inventario
JOIN documentos_inventario d ON d.id_documento = dd.id_documento
WHERE d.tipo_documento = 'SALIDA'
  AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
  AND d.estado = 'CONFIRMADO'
  AND COALESCE(d.integrado_wip_nuevo_modelo, 0) = 0
  AND dd.saldo_disponible > 0
GROUP BY ws.id_inventario, i.nombre, ws.stock_disponible
ORDER BY ABS(diferencia) DESC;

-- V3: Documentos SAL-TEJ aún en modelo legado (candidatos futuros a confirmación)
SELECT
    d.id_documento,
    d.numero_documento,
    d.fecha_documento,
    d.estado,
    COALESCE(d.integrado_wip_nuevo_modelo, 0)     AS integrado_nuevo_modelo,
    COUNT(dd.id_detalle)                           AS lineas,
    ROUND(SUM(dd.saldo_disponible), 4)             AS saldo_total_kg
FROM documentos_inventario d
JOIN documentos_inventario_detalle dd ON dd.id_documento = d.id_documento
WHERE d.tipo_documento = 'SALIDA'
  AND (d.tipo_consumo = 'TEJIDO' OR d.numero_documento LIKE 'SAL-TEJ%')
  AND d.estado = 'CONFIRMADO'
  AND dd.saldo_disponible > 0
GROUP BY d.id_documento, d.numero_documento, d.fecha_documento, d.estado
ORDER BY d.fecha_documento DESC;
