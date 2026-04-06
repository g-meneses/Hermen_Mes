SET @schema_name = DATABASE();

ALTER TABLE turnos
    ADD COLUMN IF NOT EXISTS codigo VARCHAR(10) NULL AFTER id_turno;

SET @sql_turnos_nombre = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'turnos'
          AND COLUMN_NAME = 'nombre_turno'
    ),
    'ALTER TABLE turnos CHANGE COLUMN nombre_turno nombre VARCHAR(50) NOT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql_turnos_nombre;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_turnos_pk = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'turnos'
          AND CONSTRAINT_TYPE = 'PRIMARY KEY'
    ),
    'SELECT 1',
    'ALTER TABLE turnos ADD PRIMARY KEY (id_turno)'
);
PREPARE stmt FROM @sql_turnos_pk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE turnos
    MODIFY COLUMN id_turno INT NOT NULL AUTO_INCREMENT;

UPDATE turnos
SET codigo = CASE id_turno
        WHEN 1 THEN '6-14'
        WHEN 2 THEN '14-22'
        WHEN 3 THEN '22-6'
        ELSE codigo
    END,
    nombre = CASE id_turno
        WHEN 1 THEN 'Turno Matutino'
        WHEN 2 THEN 'Turno Vespertino'
        WHEN 3 THEN 'Turno Nocturno'
        ELSE nombre
    END,
    hora_inicio = CASE id_turno
        WHEN 1 THEN '06:00:00'
        WHEN 2 THEN '14:00:00'
        WHEN 3 THEN '22:00:00'
        ELSE hora_inicio
    END,
    hora_fin = CASE id_turno
        WHEN 1 THEN '14:00:00'
        WHEN 2 THEN '22:00:00'
        WHEN 3 THEN '06:00:00'
        ELSE hora_fin
    END,
    activo = 1
WHERE id_turno IN (1, 2, 3);

ALTER TABLE documentos_inventario
    ADD COLUMN IF NOT EXISTS tipo_consumo ENUM('TEJIDO', 'VAPORIZADO', 'COSTURA', 'TENIDO', 'EMPAQUE') DEFAULT NULL
    COMMENT 'Para SALIDAS: especifica etapa donde se consume material. FASE 0: siempre TEJIDO (en documentos SAL-TEJ)';

ALTER TABLE documentos_inventario
    ADD KEY IF NOT EXISTS idx_tipo_consumo (tipo_consumo);

ALTER TABLE lote_wip
    ADD COLUMN IF NOT EXISTS id_maquina INT NULL COMMENT 'Maquina que produjo (M01-M60)' AFTER id_producto,
    ADD COLUMN IF NOT EXISTS id_turno INT NULL COMMENT 'Turno (1=6-14, 2=14-22, 3=22-6)' AFTER id_maquina,
    ADD COLUMN IF NOT EXISTS id_documento_salida INT NULL COMMENT 'FK documentos_inventario (documento SAL-TEJ)' AFTER id_documento_consumo;

UPDATE lote_wip
SET id_documento_salida = id_documento_consumo
WHERE id_documento_salida IS NULL;

ALTER TABLE lote_wip
    ADD KEY IF NOT EXISTS idx_maquina (id_maquina),
    ADD KEY IF NOT EXISTS idx_turno (id_turno),
    ADD KEY IF NOT EXISTS idx_documento_salida (id_documento_salida);

SET @sql_drop_old_lote_fk = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'lote_wip'
          AND CONSTRAINT_NAME = 'fk_lote_wip_doc'
    ),
    'ALTER TABLE lote_wip DROP FOREIGN KEY fk_lote_wip_doc',
    'SELECT 1'
);
PREPARE stmt FROM @sql_drop_old_lote_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_drop_old_lote_unique = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name
          AND TABLE_NAME = 'lote_wip'
          AND INDEX_NAME = 'uk_lote_wip_codigo'
    ),
    'ALTER TABLE lote_wip DROP INDEX uk_lote_wip_codigo',
    'SELECT 1'
);
PREPARE stmt FROM @sql_drop_old_lote_unique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE lote_wip
    ADD UNIQUE KEY IF NOT EXISTS uk_codigo_lote (codigo_lote);

ALTER TABLE lote_wip
    MODIFY COLUMN id_documento_salida INT NOT NULL;

ALTER TABLE lote_wip
    ADD CONSTRAINT fk_lote_wip_documento_salida
    FOREIGN KEY (id_documento_salida) REFERENCES documentos_inventario(id_documento)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE movimientos_wip
    MODIFY COLUMN tipo_movimiento ENUM(
        'CREACION',
        'TRANSFERENCIA',
        'CONSUMO',
        'AJUSTE',
        'CIERRE',
        'ANULACION',
        'CREACION_EN_TEJIDO',
        'TRANSFERENCIA_ETAPA',
        'AJUSTE_INTERNO',
        'RECHAZO_MERMA',
        'CIERRE_A_PT'
    ) NOT NULL;

UPDATE movimientos_wip SET tipo_movimiento = 'CREACION_EN_TEJIDO' WHERE tipo_movimiento = 'CREACION';
UPDATE movimientos_wip SET tipo_movimiento = 'TRANSFERENCIA_ETAPA' WHERE tipo_movimiento = 'TRANSFERENCIA';
UPDATE movimientos_wip SET tipo_movimiento = 'AJUSTE_INTERNO' WHERE tipo_movimiento = 'AJUSTE';
UPDATE movimientos_wip SET tipo_movimiento = 'CIERRE_A_PT' WHERE tipo_movimiento = 'CIERRE';

ALTER TABLE movimientos_wip
    MODIFY COLUMN tipo_movimiento ENUM(
        'CREACION_EN_TEJIDO',
        'TRANSFERENCIA_ETAPA',
        'AJUSTE_INTERNO',
        'RECHAZO_MERMA',
        'CIERRE_A_PT'
    ) NOT NULL
    COMMENT 'CREACION_EN_TEJIDO: lote nace cuando se registra produccion (no traslado desde almacen)';

CREATE TABLE IF NOT EXISTS planillas_tejido (
    id_planilla INT AUTO_INCREMENT PRIMARY KEY,
    id_documento_salida INT NOT NULL,
    fecha_inicio DATE NULL COMMENT 'Lunes de la semana',
    fecha_fin DATE NULL COMMENT 'Viernes o sabado',
    detalles_json JSON NULL COMMENT 'Backup: lotes cargados en formato JSON',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    registrado_por INT NULL COMMENT 'user_id de quien registro',
    observaciones TEXT NULL,
    activo TINYINT DEFAULT 1,
    KEY idx_documento (id_documento_salida),
    KEY idx_fecha_registro (fecha_registro),
    CONSTRAINT fk_planillas_tejido_documento
        FOREIGN KEY (id_documento_salida) REFERENCES documentos_inventario(id_documento)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Auditoria: que planillas se cargaron, cuando, por quien, que datos';
