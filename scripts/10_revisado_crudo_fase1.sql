-- ============================================================
-- Script 10: Base técnica Revisado Crudo - Fase 1 + 2
-- MES Hermen Ltda.
-- Fecha: 2026-04-19  |  Fase 2: 2026-04-20
-- ============================================================
-- Ejecutar DESPUÉS de: 09_sistema_wip_integral.sql
-- Las sentencias DDL causan COMMIT implícito en MySQL;
-- cada bloque es idempotente (IF NOT EXISTS / WHERE NOT EXISTS).
-- ============================================================

-- ------------------------------------------------------------
-- 1. Extender ENUM tipo_movimiento en movimientos_wip
--    Conserva los valores del script 08 y agrega REVISION_CRUDO.
-- ------------------------------------------------------------
ALTER TABLE movimientos_wip
    MODIFY COLUMN tipo_movimiento ENUM(
        'CREACION_EN_TEJIDO',
        'TRANSFERENCIA_ETAPA',
        'AJUSTE_INTERNO',
        'RECHAZO_MERMA',
        'CIERRE_A_PT',
        'REVISION_CRUDO'
    ) NOT NULL
    COMMENT 'REVISION_CRUDO: evento de revisado de tela cruda';

-- ------------------------------------------------------------
-- 2. Agregar campos de revisión al lote WIP (idempotente vía SP)
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_rc_add_fields;

DELIMITER //
CREATE PROCEDURE sp_rc_add_fields()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'lote_wip'
          AND COLUMN_NAME  = 'pendiente_revision_unidades'
    ) THEN
        ALTER TABLE lote_wip
            ADD COLUMN pendiente_revision_unidades INT NOT NULL DEFAULT 0
            COMMENT 'Unidades aún pendientes de revisado crudo'
            AFTER cantidad_base_unidades;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'lote_wip'
          AND COLUMN_NAME  = 'estado_revision'
    ) THEN
        ALTER TABLE lote_wip
            ADD COLUMN estado_revision VARCHAR(50) NULL DEFAULT NULL
            COMMENT 'NULL=sin revisión | REVISION_PARCIAL | REVISION_COMPLETA'
            AFTER pendiente_revision_unidades;
    END IF;

    -- motivo_derivacion: identifica lotes hijos creados por revisado crudo
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'lote_wip'
          AND COLUMN_NAME  = 'motivo_derivacion'
    ) THEN
        ALTER TABLE lote_wip
            ADD COLUMN motivo_derivacion VARCHAR(50) NULL DEFAULT NULL
            COMMENT 'REVISION_APTA | REVISION_OBSERVADA — indica origen del lote derivado'
            AFTER estado_revision;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'lote_wip'
          AND INDEX_NAME   = 'idx_lote_wip_revision'
    ) THEN
        ALTER TABLE lote_wip
            ADD KEY idx_lote_wip_revision (estado_revision, estado_lote);
    END IF;
END //
DELIMITER ;

CALL sp_rc_add_fields();
DROP PROCEDURE IF EXISTS sp_rc_add_fields;

-- ------------------------------------------------------------
-- 3. Áreas de producción para el módulo
-- ------------------------------------------------------------
INSERT INTO areas_produccion (codigo, nombre, descripcion, activo)
SELECT 'REVISADO_CRUDO', 'Revisado Crudo',
       'Revisión de tela cruda antes del siguiente proceso', 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM areas_produccion WHERE codigo = 'REVISADO_CRUDO'
);

-- Área de segregación para lotes observados en revisado crudo
INSERT INTO areas_produccion (codigo, nombre, descripcion, activo)
SELECT 'OBSERVADOS_RC', 'Observados - Revisado Crudo',
       'Lotes con defectos observados en revisado crudo, pendientes de decisión', 1
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM areas_produccion WHERE codigo = 'OBSERVADOS_RC'
);

-- ------------------------------------------------------------
-- 4. Tabla cabecera: revisado_crudo_registros
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS revisado_crudo_registros (
    id_registro         INT          NOT NULL AUTO_INCREMENT,
    fecha               DATE         NOT NULL,
    id_turno            INT          NULL,
    id_revisadora       INT          NOT NULL
                                     COMMENT 'FK usuarios - revisadora de planta',
    id_supervisor       INT          NULL
                                     COMMENT 'FK usuarios - supervisor (opcional)',
    mesa                VARCHAR(100) NULL,
    observacion_general TEXT         NULL,
    estado              ENUM('BORRADOR','CONFIRMADO','ANULADO')
                                     NOT NULL DEFAULT 'BORRADOR',
    creado_por          INT          NOT NULL,
    fecha_creacion      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME     NULL     DEFAULT NULL
                                     ON UPDATE CURRENT_TIMESTAMP,
    fecha_confirmacion  DATETIME     NULL     DEFAULT NULL,
    PRIMARY KEY (id_registro),
    KEY idx_rcr_fecha      (fecha),
    KEY idx_rcr_estado     (estado),
    KEY idx_rcr_revisadora (id_revisadora),
    CONSTRAINT fk_rcr_revisadora FOREIGN KEY (id_revisadora)
        REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_rcr_supervisor FOREIGN KEY (id_supervisor)
        REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_rcr_creado_por FOREIGN KEY (creado_por)
        REFERENCES usuarios (id_usuario),
    CONSTRAINT fk_rcr_turno FOREIGN KEY (id_turno)
        REFERENCES turnos (id_turno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cabecera de registros de revisado crudo';

-- ------------------------------------------------------------
-- 5. Tabla detalle: revisado_crudo_registro_detalle
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS revisado_crudo_registro_detalle (
    id_detalle                           INT        NOT NULL AUTO_INCREMENT,
    id_registro                          INT        NOT NULL,
    id_lote_wip                          INT        NOT NULL,
    id_producto                          INT        NOT NULL,
    pendiente_inicial_unidades           INT        NOT NULL DEFAULT 0
                                                    COMMENT 'Saldo pendiente al momento de revisar',
    cantidad_apta_unidades               INT        NOT NULL DEFAULT 0,
    cantidad_observada_unidades          INT        NOT NULL DEFAULT 0,
    cantidad_merma_unidades              INT        NOT NULL DEFAULT 0,
    cantidad_pendiente_restante_unidades INT        NOT NULL DEFAULT 0
                                                    COMMENT 'Remanente que permanece en el lote padre',
    id_area_destino_apta                 INT        NULL
                                                    COMMENT 'Área destino prevista para la parte apta (Fase 2)',
    requiere_split                       TINYINT(1) NOT NULL DEFAULT 0
                                                    COMMENT '1 si hay apta u observada para segregar en Fase 2',
    estado_resultado                     VARCHAR(50) NULL
                                                    COMMENT 'APTA | OBSERVADA | MIXTA | MERMA_TOTAL',
    observacion_lote                     TEXT       NULL,
    orden_visual                         INT        NOT NULL DEFAULT 0,
    PRIMARY KEY (id_detalle),
    KEY idx_rcrd_registro (id_registro),
    KEY idx_rcrd_lote     (id_lote_wip),
    KEY idx_rcrd_producto (id_producto),
    CONSTRAINT fk_rcrd_registro FOREIGN KEY (id_registro)
        REFERENCES revisado_crudo_registros (id_registro) ON DELETE CASCADE,
    CONSTRAINT fk_rcrd_lote FOREIGN KEY (id_lote_wip)
        REFERENCES lote_wip (id_lote_wip),
    CONSTRAINT fk_rcrd_area_destino FOREIGN KEY (id_area_destino_apta)
        REFERENCES areas_produccion (id_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Detalle por lote del registro de revisado crudo';
