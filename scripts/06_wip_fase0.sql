START TRANSACTION;

CREATE TABLE IF NOT EXISTS bom_productos (
  id_bom INT NOT NULL AUTO_INCREMENT,
  id_producto INT NOT NULL,
  codigo_bom VARCHAR(30) NOT NULL,
  version_bom INT NOT NULL DEFAULT 1,
  estado ENUM('BORRADOR','ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  merma_pct DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  observaciones TEXT DEFAULT NULL,
  fecha_vigencia_desde DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_vigencia_hasta DATETIME DEFAULT NULL,
  creado_por INT DEFAULT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_bom),
  UNIQUE KEY uk_bom_codigo (codigo_bom),
  KEY idx_bom_producto_estado (id_producto, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bom_productos_detalle (
  id_bom_detalle INT NOT NULL AUTO_INCREMENT,
  id_bom INT NOT NULL,
  id_inventario INT NOT NULL,
  gramos_por_docena DECIMAL(12,4) NOT NULL,
  porcentaje_componente DECIMAL(6,3) DEFAULT NULL,
  merma_pct DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  es_principal TINYINT(1) NOT NULL DEFAULT 0,
  orden_visual INT NOT NULL DEFAULT 0,
  observaciones VARCHAR(255) DEFAULT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_bom_detalle),
  UNIQUE KEY uk_bom_detalle_item (id_bom, id_inventario),
  KEY idx_bom_detalle_bom (id_bom),
  KEY idx_bom_detalle_inventario (id_inventario),
  CONSTRAINT fk_bom_detalle_bom FOREIGN KEY (id_bom) REFERENCES bom_productos(id_bom) ON DELETE CASCADE,
  CONSTRAINT fk_bom_detalle_inventario FOREIGN KEY (id_inventario) REFERENCES inventarios(id_inventario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lote_wip (
  id_lote_wip INT NOT NULL AUTO_INCREMENT,
  codigo_lote VARCHAR(30) NOT NULL,
  id_producto INT NOT NULL,
  id_linea_produccion INT DEFAULT NULL,
  cantidad_docenas INT NOT NULL DEFAULT 0,
  cantidad_unidades INT NOT NULL DEFAULT 0,
  cantidad_base_unidades INT NOT NULL,
  id_area_actual INT NOT NULL,
  estado_lote ENUM('ACTIVO','PAUSADO','CERRADO','ANULADO') NOT NULL DEFAULT 'ACTIVO',
  costo_mp_acumulado DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  costo_unitario_promedio DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
  id_documento_consumo INT NOT NULL,
  referencia_externa VARCHAR(50) NOT NULL,
  fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por INT DEFAULT NULL,
  PRIMARY KEY (id_lote_wip),
  UNIQUE KEY uk_lote_wip_codigo (codigo_lote),
  UNIQUE KEY uk_lote_wip_ref (referencia_externa),
  KEY idx_lote_wip_producto (id_producto),
  KEY idx_lote_wip_area_estado (id_area_actual, estado_lote),
  KEY idx_lote_wip_doc (id_documento_consumo),
  CONSTRAINT fk_lote_wip_area FOREIGN KEY (id_area_actual) REFERENCES areas_produccion(id_area),
  CONSTRAINT fk_lote_wip_doc FOREIGN KEY (id_documento_consumo) REFERENCES documentos_inventario(id_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimientos_wip (
  id_movimiento INT NOT NULL AUTO_INCREMENT,
  id_lote_wip INT NOT NULL,
  tipo_movimiento ENUM('CREACION','TRANSFERENCIA','CONSUMO','AJUSTE','CIERRE','ANULACION') NOT NULL,
  cantidad_docenas INT NOT NULL DEFAULT 0,
  cantidad_unidades INT NOT NULL DEFAULT 0,
  id_area_origen INT DEFAULT NULL,
  id_area_destino INT DEFAULT NULL,
  id_documento_inventario INT DEFAULT NULL,
  referencia_externa VARCHAR(50) DEFAULT NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario INT DEFAULT NULL,
  observaciones VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id_movimiento),
  KEY idx_mov_wip_lote (id_lote_wip, fecha),
  KEY idx_mov_wip_doc (id_documento_inventario),
  CONSTRAINT fk_mov_wip_lote FOREIGN KEY (id_lote_wip) REFERENCES lote_wip(id_lote_wip) ON DELETE CASCADE,
  CONSTRAINT fk_mov_wip_area_origen FOREIGN KEY (id_area_origen) REFERENCES areas_produccion(id_area),
  CONSTRAINT fk_mov_wip_area_destino FOREIGN KEY (id_area_destino) REFERENCES areas_produccion(id_area),
  CONSTRAINT fk_mov_wip_doc FOREIGN KEY (id_documento_inventario) REFERENCES documentos_inventario(id_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO inv_doc_subtipos (id_tipo, nombre, codigo, activo)
SELECT 2, 'Consumo en Producción', 'WIP-C', 1
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1
  FROM inv_doc_subtipos
  WHERE id_tipo = 2 AND codigo = 'WIP-C'
);

COMMIT;
