-- Módulo de Compras - Esquema de Base de Datos
-- Fecha: 2026-02-02

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Modificar tabla Proveedores (Existente)
-- Asegurar que tenga Primary Key y AUTO_INCREMENT
ALTER TABLE proveedores MODIFY COLUMN id_proveedor INT AUTO_INCREMENT PRIMARY KEY;

ALTER TABLE proveedores
    ADD COLUMN IF NOT EXISTS tipo_contribuyente ENUM('PERSONA_NATURAL', 'JURIDICA', 'EXTRANJERO'),
    ADD COLUMN IF NOT EXISTS regimen_tributario VARCHAR(50),
    ADD COLUMN IF NOT EXISTS limite_credito DECIMAL(15,2),
    ADD COLUMN IF NOT EXISTS descuento_general DECIMAL(5,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS categoria_proveedor ENUM('MATERIAS_PRIMAS', 'INSUMOS', 'REPUESTOS', 'SERVICIOS', 'MULTIPLE'),
    ADD COLUMN IF NOT EXISTS es_preferente BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS rating_precio DECIMAL(3,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rating_cumplimiento DECIMAL(3,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rating_calidad DECIMAL(3,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS rating_general DECIMAL(3,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS motivo_bloqueo TEXT,
    ADD COLUMN IF NOT EXISTS modificado_por INT,
    ADD COLUMN IF NOT EXISTS fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Índices para proveedores
CREATE INDEX IF NOT EXISTS idx_categoria_prov ON proveedores(categoria_proveedor);
CREATE INDEX IF NOT EXISTS idx_rating_prov ON proveedores(rating_general);

-- 2. Solicitudes de Compra
CREATE TABLE IF NOT EXISTS solicitudes_compra (
    id_solicitud INT PRIMARY KEY AUTO_INCREMENT,
    numero_solicitud VARCHAR(20) UNIQUE NOT NULL,
    fecha_solicitud DATETIME NOT NULL,
    id_usuario_solicitante INT NOT NULL,
    area_solicitante VARCHAR(100),
    centro_costo VARCHAR(50),
    prioridad ENUM('BAJA', 'NORMAL', 'ALTA', 'URGENTE') DEFAULT 'NORMAL',
    tipo_compra ENUM('REPOSICION', 'PRODUCCION', 'PROYECTO', 'URGENTE', 'OTRO'),
    motivo TEXT NOT NULL,
    observaciones TEXT,
    id_tipo_inventario INT,
    id_almacen INT,
    moneda VARCHAR(3) DEFAULT 'BOB',
    monto_estimado DECIMAL(15,2),
    estado ENUM('BORRADOR', 'PENDIENTE', 'EN_APROBACION', 'APROBADA', 'RECHAZADA', 'OBSERVADA', 'CANCELADA') DEFAULT 'BORRADOR',
    requiere_aprobacion BOOLEAN DEFAULT TRUE,
    nivel_aprobacion_actual INT DEFAULT 0,
    convertida_oc BOOLEAN DEFAULT FALSE,
    fecha_conversion DATETIME,
    creado_por INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_por INT,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_estado (estado),
    INDEX idx_solicitante (id_usuario_solicitante),
    INDEX idx_fecha (fecha_solicitud),
    INDEX idx_prioridad (prioridad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Detalle de Solicitudes
CREATE TABLE IF NOT EXISTS solicitudes_compra_detalle (
    id_detalle INT PRIMARY KEY AUTO_INCREMENT,
    id_solicitud INT NOT NULL,
    numero_linea INT NOT NULL,
    id_producto INT,
    id_tipo_inventario INT NOT NULL,
    codigo_producto VARCHAR(50),
    descripcion_producto TEXT NOT NULL,
    cantidad_solicitada DECIMAL(15,4) NOT NULL,
    id_unidad_medida INT,
    unidad_medida VARCHAR(20),
    especificaciones TEXT,
    precio_estimado DECIMAL(15,4),
    subtotal_estimado DECIMAL(15,2),
    id_proveedor_sugerido INT,
    cantidad_aprobada DECIMAL(15,4),
    cantidad_ordenada DECIMAL(15,4) DEFAULT 0,
    FOREIGN KEY (id_solicitud) REFERENCES solicitudes_compra(id_solicitud) ON DELETE CASCADE,
    INDEX idx_solicitud (id_solicitud),
    INDEX idx_producto (id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Órdenes de Compra
CREATE TABLE IF NOT EXISTS ordenes_compra (
    id_orden_compra INT PRIMARY KEY AUTO_INCREMENT,
    numero_orden VARCHAR(20) UNIQUE NOT NULL,
    fecha_orden DATETIME NOT NULL,
    id_proveedor INT NOT NULL,
    nombre_proveedor VARCHAR(200),
    nit_proveedor VARCHAR(20),
    id_solicitud INT,
    numero_solicitud VARCHAR(20),
    id_comprador INT NOT NULL,
    moneda VARCHAR(3) DEFAULT 'BOB',
    tipo_cambio DECIMAL(10,4) DEFAULT 1,
    condicion_pago ENUM('CONTADO', 'CREDITO_15', 'CREDITO_30', 'CREDITO_45', 'CREDITO_60', 'CREDITO_90'),
    dias_credito INT DEFAULT 0,
    fecha_entrega_estimada DATE,
    fecha_entrega_requerida DATE,
    lugar_entrega TEXT,
    id_almacen_destino INT,
    subtotal DECIMAL(15,2) NOT NULL,
    descuento_general DECIMAL(15,2) DEFAULT 0,
    flete DECIMAL(15,2) DEFAULT 0,
    seguro DECIMAL(15,2) DEFAULT 0,
    otros_gastos DECIMAL(15,2) DEFAULT 0,
    base_imponible DECIMAL(15,2),
    impuestos DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) NOT NULL,
    observaciones TEXT,
    terminos_condiciones TEXT,
    estado ENUM('BORRADOR', 'EMITIDA', 'CONFIRMADA', 'EN_RECEPCION', 'RECIBIDA_PARCIAL', 'RECIBIDA_TOTAL', 'CERRADA', 'CANCELADA') DEFAULT 'BORRADOR',
    recepcion_parcial BOOLEAN DEFAULT TRUE,
    porcentaje_recibido DECIMAL(5,2) DEFAULT 0,
    fecha_cierre DATETIME,
    cerrada_con_diferencias BOOLEAN DEFAULT FALSE,
    motivo_cierre TEXT,
    creado_por INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_por INT,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor),
    INDEX idx_proveedor (id_proveedor),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha_orden),
    INDEX idx_solicitud (id_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Detalle de Órdenes de Compra
CREATE TABLE IF NOT EXISTS ordenes_compra_detalle (
    id_detalle_oc INT PRIMARY KEY AUTO_INCREMENT,
    id_orden_compra INT NOT NULL,
    numero_linea INT NOT NULL,
    id_producto INT,
    id_tipo_inventario INT NOT NULL,
    codigo_producto VARCHAR(50),
    descripcion_producto TEXT NOT NULL,
    cantidad_ordenada DECIMAL(15,4) NOT NULL,
    cantidad_recibida DECIMAL(15,4) DEFAULT 0,
    cantidad_pendiente DECIMAL(15,4),
    id_unidad_medida INT,
    unidad_medida VARCHAR(20),
    precio_unitario DECIMAL(15,4) NOT NULL,
    descuento_linea DECIMAL(15,2) DEFAULT 0,
    subtotal_linea DECIMAL(15,2) NOT NULL,
    impuesto_linea DECIMAL(15,2) DEFAULT 0,
    total_linea DECIMAL(15,2) NOT NULL,
    especificaciones TEXT,
    id_detalle_solicitud INT,
    estado_recepcion ENUM('PENDIENTE', 'PARCIAL', 'COMPLETA') DEFAULT 'PENDIENTE',
    FOREIGN KEY (id_orden_compra) REFERENCES ordenes_compra(id_orden_compra) ON DELETE CASCADE,
    INDEX idx_orden (id_orden_compra),
    INDEX idx_producto (id_producto),
    INDEX idx_estado (estado_recepcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Recepciones de Compra
CREATE TABLE IF NOT EXISTS recepciones_compra (
    id_recepcion INT PRIMARY KEY AUTO_INCREMENT,
    numero_recepcion VARCHAR(20) UNIQUE NOT NULL,
    fecha_recepcion DATETIME NOT NULL,
    id_orden_compra INT NOT NULL,
    numero_orden VARCHAR(20),
    id_proveedor INT NOT NULL,
    nombre_proveedor VARCHAR(200),
    numero_guia_remision VARCHAR(50),
    numero_factura VARCHAR(50),
    fecha_factura DATE,
    id_almacen INT NOT NULL,
    id_usuario_recibe INT NOT NULL,
    tipo_recepcion ENUM('TOTAL', 'PARCIAL') DEFAULT 'PARCIAL',
    inspeccion_calidad BOOLEAN DEFAULT FALSE,
    resultado_inspeccion ENUM('APROBADO', 'RECHAZADO', 'OBSERVADO'),
    observaciones_calidad TEXT,
    observaciones TEXT,
    estado ENUM('BORRADOR', 'CONFIRMADA', 'PROCESADA', 'ANULADA') DEFAULT 'BORRADOR',
    inventario_actualizado BOOLEAN DEFAULT FALSE,
    fecha_actualizacion_inventario DATETIME,
    creado_por INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_por INT,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_orden_compra) REFERENCES ordenes_compra(id_orden_compra),
    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor),
    INDEX idx_orden (id_orden_compra),
    INDEX idx_fecha (fecha_recepcion),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Detalle de Recepciones
CREATE TABLE IF NOT EXISTS recepciones_compra_detalle (
    id_detalle_recepcion INT PRIMARY KEY AUTO_INCREMENT,
    id_recepcion INT NOT NULL,
    id_detalle_oc INT NOT NULL,
    numero_linea INT NOT NULL,
    id_producto INT,
    id_tipo_inventario INT NOT NULL,
    codigo_producto VARCHAR(50),
    descripcion_producto TEXT,
    cantidad_ordenada DECIMAL(15,4),
    cantidad_recibida DECIMAL(15,4) NOT NULL,
    cantidad_aceptada DECIMAL(15,4),
    cantidad_rechazada DECIMAL(15,4) DEFAULT 0,
    id_unidad_medida INT,
    numero_lote VARCHAR(50),
    fecha_vencimiento DATE,
    numero_serie VARCHAR(50),
    ubicacion_almacen VARCHAR(50),
    estado_calidad ENUM('APROBADO', 'RECHAZADO', 'OBSERVADO') DEFAULT 'APROBADO',
    observaciones_calidad TEXT,
    diferencia_cantidad DECIMAL(15,4),
    motivo_diferencia TEXT,
    FOREIGN KEY (id_recepcion) REFERENCES recepciones_compra(id_recepcion) ON DELETE CASCADE,
    FOREIGN KEY (id_detalle_oc) REFERENCES ordenes_compra_detalle(id_detalle_oc),
    INDEX idx_recepcion (id_recepcion),
    INDEX idx_producto (id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Sistema de Aprobaciones - Flujos
CREATE TABLE IF NOT EXISTS flujos_aprobacion (
    id_flujo INT PRIMARY KEY AUTO_INCREMENT,
    nombre_flujo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    tipo_documento ENUM('SOLICITUD_COMPRA', 'ORDEN_COMPRA'),
    monto_minimo DECIMAL(15,2),
    monto_maximo DECIMAL(15,2),
    id_tipo_inventario INT,
    area VARCHAR(100),
    prioridad ENUM('BAJA', 'NORMAL', 'ALTA', 'URGENTE'),
    niveles_aprobacion INT NOT NULL,
    aprobacion_paralela BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE,
    orden_prioridad INT DEFAULT 0,
    INDEX idx_tipo (tipo_documento),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS niveles_aprobacion (
    id_nivel INT PRIMARY KEY AUTO_INCREMENT,
    id_flujo INT NOT NULL,
    nivel INT NOT NULL,
    nombre_nivel VARCHAR(100),
    id_rol INT,
    id_usuario INT,
    requiere_todos BOOLEAN DEFAULT FALSE,
    tiempo_limite_horas INT,
    FOREIGN KEY (id_flujo) REFERENCES flujos_aprobacion(id_flujo) ON DELETE CASCADE,
    INDEX idx_flujo (id_flujo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aprobaciones (
    id_aprobacion INT PRIMARY KEY AUTO_INCREMENT,
    tipo_documento ENUM('SOLICITUD_COMPRA', 'ORDEN_COMPRA'),
    id_documento INT NOT NULL,
    id_flujo INT,
    nivel_actual INT,
    id_usuario_aprobador INT NOT NULL,
    fecha_aprobacion DATETIME NOT NULL,
    accion ENUM('APROBADO', 'RECHAZADO', 'OBSERVADO', 'DEVUELTO') NOT NULL,
    comentarios TEXT,
    tiempo_respuesta_horas DECIMAL(10,2),
    INDEX idx_documento (tipo_documento, id_documento),
    INDEX idx_usuario (id_usuario_aprobador)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Auditoría
CREATE TABLE IF NOT EXISTS auditoria_compras (
    id_auditoria BIGINT PRIMARY KEY AUTO_INCREMENT,
    tipo_documento ENUM('SOLICITUD', 'ORDEN', 'RECEPCION', 'PROVEEDOR'),
    id_documento INT NOT NULL,
    numero_documento VARCHAR(20),
    accion ENUM('CREAR', 'MODIFICAR', 'ELIMINAR', 'APROBAR', 'RECHAZAR', 'CANCELAR', 'CERRAR') NOT NULL,
    tabla_afectada VARCHAR(100),
    id_usuario INT NOT NULL,
    nombre_usuario VARCHAR(200),
    ip_address VARCHAR(45),
    datos_anteriores JSON,
    datos_nuevos JSON,
    fecha_accion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_documento (tipo_documento, id_documento),
    INDEX idx_usuario (id_usuario),
    INDEX idx_fecha (fecha_accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Relación Compras-Inventarios
CREATE TABLE IF NOT EXISTS compras_inventario_relacion (
    id_relacion INT PRIMARY KEY AUTO_INCREMENT,
    id_orden_compra INT NOT NULL,
    id_recepcion INT,
    id_tipo_inventario INT NOT NULL,
    id_producto INT NOT NULL,
    id_movimiento_inventario INT,
    cantidad DECIMAL(15,4) NOT NULL,
    fecha_relacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_orden_compra) REFERENCES ordenes_compra(id_orden_compra),
    FOREIGN KEY (id_recepcion) REFERENCES recepciones_compra(id_recepcion),
    INDEX idx_orden (id_orden_compra),
    INDEX idx_inventario (id_tipo_inventario, id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

