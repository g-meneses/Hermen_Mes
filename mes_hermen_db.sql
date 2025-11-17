-- =====================================================
-- SISTEMA MES HERMEN LTDA - BASE DE DATOS
-- Módulo: TEJEDURÍA
-- =====================================================

CREATE DATABASE IF NOT EXISTS mes_hermen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mes_hermen;

-- =====================================================
-- CATEGORÍA 1: TABLAS MAESTRAS
-- =====================================================

-- TABLA: usuarios
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    codigo_usuario VARCHAR(20) UNIQUE NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('tejedor', 'revisor', 'tintorero', 'coordinador', 'gerencia', 'admin') NOT NULL,
    area VARCHAR(50),
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME NULL,
    INDEX idx_usuario (usuario),
    INDEX idx_rol (rol)
) ENGINE=InnoDB;

-- TABLA: maquinas
CREATE TABLE maquinas (
    id_maquina INT AUTO_INCREMENT PRIMARY KEY,
    numero_maquina VARCHAR(10) UNIQUE NOT NULL, -- M-01, M-02, etc.
    descripcion VARCHAR(100),
    diametro_pulgadas DECIMAL(3,1) DEFAULT 4.0,
    numero_agujas INT DEFAULT 400,
    estado ENUM('operativa', 'mantenimiento', 'inactiva', 'sin_asignacion', 'sin_repuestos') DEFAULT 'operativa',
    ubicacion VARCHAR(50),
    fecha_instalacion DATE,
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_numero (numero_maquina),
    INDEX idx_estado (estado)
) ENGINE=InnoDB;

-- TABLA: lineas_producto
CREATE TABLE lineas_producto (
    id_linea INT AUTO_INCREMENT PRIMARY KEY,
    codigo_linea VARCHAR(20) UNIQUE NOT NULL,
    nombre_linea VARCHAR(50) NOT NULL, -- LUJO, STRETCH, LYCRA 20, LYCRA 40, CAMISETAS
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    INDEX idx_codigo (codigo_linea)
) ENGINE=InnoDB;

-- TABLA: tipos_producto
CREATE TABLE tipos_producto (
    id_tipo_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_tipo VARCHAR(50) NOT NULL, -- PANTYHOSE, MEDIA PANTALÓN, CUERPO, MANGA, etc.
    categoria ENUM('directo', 'ensamblaje') NOT NULL, -- directo: va directo a teñir, ensamblaje: requiere costura
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

-- TABLA: disenos
CREATE TABLE disenos (
    id_diseno INT AUTO_INCREMENT PRIMARY KEY,
    nombre_diseno VARCHAR(50) NOT NULL, -- PUNTERA REFORZADA, SIN PUNTERA, NUDA, BASICO
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

-- TABLA: productos_tejidos (productos que salen de tejeduría)
CREATE TABLE productos_tejidos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    codigo_producto VARCHAR(30) UNIQUE NOT NULL,
    id_linea INT NOT NULL,
    id_tipo_producto INT NOT NULL,
    id_diseno INT NOT NULL,
    talla VARCHAR(20) NOT NULL, -- S, M, L, XL, TU, 4-6, etc.
    descripcion_completa VARCHAR(200),
    peso_promedio_docena DECIMAL(8,2), -- en gramos
    tiempo_estimado_docena INT, -- en minutos
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_linea) REFERENCES lineas_producto(id_linea),
    FOREIGN KEY (id_tipo_producto) REFERENCES tipos_producto(id_tipo_producto),
    FOREIGN KEY (id_diseno) REFERENCES disenos(id_diseno),
    INDEX idx_codigo (codigo_producto),
    INDEX idx_linea (id_linea)
) ENGINE=InnoDB;

-- TABLA: tipos_insumo
CREATE TABLE tipos_insumo (
    id_tipo_insumo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_tipo VARCHAR(50) NOT NULL, -- HILO, LYCRA, ELASTICO, etc.
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

-- TABLA: insumos (hilos y materiales)
CREATE TABLE insumos (
    id_insumo INT AUTO_INCREMENT PRIMARY KEY,
    codigo_insumo VARCHAR(30) UNIQUE NOT NULL,
    id_tipo_insumo INT NOT NULL,
    nombre_insumo VARCHAR(100) NOT NULL,
    descripcion TEXT,
    unidad_medida ENUM('gramos', 'kilogramos', 'metros', 'unidades') DEFAULT 'gramos',
    stock_minimo DECIMAL(10,2),
    stock_actual DECIMAL(10,2) DEFAULT 0,
    precio_unitario DECIMAL(10,2),
    proveedor VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_tipo_insumo) REFERENCES tipos_insumo(id_tipo_insumo),
    INDEX idx_codigo (codigo_insumo)
) ENGINE=InnoDB;

-- TABLA: producto_insumos (BOM - Bill of Materials / Receta)
CREATE TABLE producto_insumos (
    id_producto_insumo INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    id_insumo INT NOT NULL,
    cantidad_por_docena DECIMAL(10,3) NOT NULL, -- cantidad en gramos u otra unidad
    es_principal BOOLEAN DEFAULT FALSE, -- para identificar el hilo principal
    observaciones TEXT,
    FOREIGN KEY (id_producto) REFERENCES productos_tejidos(id_producto) ON DELETE CASCADE,
    FOREIGN KEY (id_insumo) REFERENCES insumos(id_insumo),
    UNIQUE KEY unique_producto_insumo (id_producto, id_insumo)
) ENGINE=InnoDB;

-- =====================================================
-- CATEGORÍA 2: PLANIFICACIÓN
-- =====================================================

-- TABLA: planes_semanales
CREATE TABLE planes_semanales (
    id_plan INT AUTO_INCREMENT PRIMARY KEY,
    codigo_plan VARCHAR(20) UNIQUE NOT NULL, -- 2025-W15
    semana INT NOT NULL,
    anio INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    estado ENUM('borrador', 'aprobado', 'en_proceso', 'completado', 'cancelado') DEFAULT 'borrador',
    observaciones TEXT,
    usuario_creacion INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion DATETIME,
    FOREIGN KEY (usuario_creacion) REFERENCES usuarios(id_usuario),
    INDEX idx_codigo (codigo_plan),
    INDEX idx_fecha (fecha_inicio, fecha_fin)
) ENGINE=InnoDB;

-- TABLA: lotes_produccion
CREATE TABLE lotes_produccion (
    id_lote INT AUTO_INCREMENT PRIMARY KEY,
    codigo_lote VARCHAR(30) UNIQUE NOT NULL, -- 2025-W15-L001
    id_plan INT NOT NULL,
    dia_programado DATE NOT NULL,
    id_producto INT NOT NULL,
    talla VARCHAR(20) NOT NULL,
    color VARCHAR(50) NOT NULL,
    cantidad_docenas INT NOT NULL,
    cantidad_unidades INT DEFAULT 0,
    estado ENUM('programado', 'en_tejido', 'en_proceso', 'completado', 'cancelado') DEFAULT 'programado',
    prioridad ENUM('baja', 'media', 'alta', 'urgente') DEFAULT 'media',
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_plan) REFERENCES planes_semanales(id_plan) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos_tejidos(id_producto),
    INDEX idx_codigo (codigo_lote),
    INDEX idx_dia (dia_programado),
    INDEX idx_estado (estado)
) ENGINE=InnoDB;

-- TABLA: plan_generico_tejido (para el flujo genérico de máquinas)
CREATE TABLE plan_generico_tejido (
    id_plan_generico INT AUTO_INCREMENT PRIMARY KEY,
    codigo_plan_generico VARCHAR(30) UNIQUE NOT NULL,
    fecha_vigencia_inicio DATE NOT NULL,
    fecha_vigencia_fin DATE,
    estado ENUM('vigente', 'historico') DEFAULT 'vigente',
    observaciones TEXT,
    usuario_creacion INT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion DATETIME,
    FOREIGN KEY (usuario_creacion) REFERENCES usuarios(id_usuario),
    INDEX idx_vigencia (fecha_vigencia_inicio, fecha_vigencia_fin)
) ENGINE=InnoDB;

-- TABLA: detalle_plan_generico (asignación máquina-producto)
CREATE TABLE detalle_plan_generico (
    id_detalle_generico INT AUTO_INCREMENT PRIMARY KEY,
    id_plan_generico INT NOT NULL,
    id_maquina INT NOT NULL,
    id_producto INT NOT NULL,
    accion ENUM('mantener', 'cambiar', 'parar') NOT NULL,
    producto_nuevo INT, -- si acción es 'cambiar', referencia al nuevo producto
    cantidad_objetivo_docenas INT,
    observaciones TEXT,
    FOREIGN KEY (id_plan_generico) REFERENCES plan_generico_tejido(id_plan_generico) ON DELETE CASCADE,
    FOREIGN KEY (id_maquina) REFERENCES maquinas(id_maquina),
    FOREIGN KEY (id_producto) REFERENCES productos_tejidos(id_producto),
    FOREIGN KEY (producto_nuevo) REFERENCES productos_tejidos(id_producto),
    UNIQUE KEY unique_plan_maquina (id_plan_generico, id_maquina)
) ENGINE=InnoDB;

-- =====================================================
-- CATEGORÍA 3: FLUJO A - PRODUCCIÓN GENÉRICA (TEJIDO)
-- =====================================================

-- TABLA: turnos
CREATE TABLE turnos (
    id_turno INT AUTO_INCREMENT PRIMARY KEY,
    nombre_turno VARCHAR(50) NOT NULL, -- Mañana, Tarde, Noche
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    activo BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

-- TABLA: produccion_tejeduria (cabecera del lote de producción de un turno)
CREATE TABLE produccion_tejeduria (
    id_produccion INT AUTO_INCREMENT PRIMARY KEY,
    codigo_lote_turno VARCHAR(30) UNIQUE NOT NULL, -- 031125-1 (DDMMYY-turno)
    fecha_produccion DATE NOT NULL,
    id_turno INT NOT NULL,
    id_tejedor INT NOT NULL,
    id_tecnico INT,
    hora_inicio TIME,
    hora_fin TIME,
    estado ENUM('en_proceso', 'completado', 'pausado') DEFAULT 'en_proceso',
    observaciones TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_turno) REFERENCES turnos(id_turno),
    FOREIGN KEY (id_tejedor) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_tecnico) REFERENCES usuarios(id_usuario),
    INDEX idx_fecha (fecha_produccion),
    INDEX idx_codigo (codigo_lote_turno)
) ENGINE=InnoDB;

-- TABLA: detalle_produccion_tejeduria (producción por máquina en el turno)
CREATE TABLE detalle_produccion_tejeduria (
    id_detalle_produccion INT AUTO_INCREMENT PRIMARY KEY,
    id_produccion INT NOT NULL,
    id_maquina INT NOT NULL,
    id_producto INT NOT NULL,
    docenas_producidas INT NOT NULL DEFAULT 0,
    unidades_producidas INT NOT NULL DEFAULT 0,
    total_unidades_calculado INT GENERATED ALWAYS AS (docenas_producidas * 12 + unidades_producidas) STORED,
    calidad ENUM('primera', 'segunda', 'defecto') DEFAULT 'primera',
    kilos_producidos DECIMAL(10,2),
    observaciones TEXT,
    FOREIGN KEY (id_produccion) REFERENCES produccion_tejeduria(id_produccion) ON DELETE CASCADE,
    FOREIGN KEY (id_maquina) REFERENCES maquinas(id_maquina),
    FOREIGN KEY (id_producto) REFERENCES productos_tejidos(id_producto),
    INDEX idx_produccion (id_produccion),
    INDEX idx_maquina (id_maquina)
) ENGINE=InnoDB;

-- TABLA: inventario_intermedio (stock entre tejido y lote)
CREATE TABLE inventario_intermedio (
    id_inventario INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    talla VARCHAR(20) NOT NULL,
    
    -- Inventario en TEJIDO (producido pero no revisado)
    stock_tejido_docenas INT DEFAULT 0,
    stock_tejido_unidades INT DEFAULT 0,
    
    -- Inventario VAPORIZADO (revisado y vaporizado, listo para asignar a lote)
    stock_vaporizado_docenas INT DEFAULT 0,
    stock_vaporizado_unidades INT DEFAULT 0,
    
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES productos_tejidos(id_producto),
    UNIQUE KEY unique_producto_talla (id_producto, talla),
    INDEX idx_producto (id_producto)
) ENGINE=InnoDB;

-- TABLA: movimientos_inventario (auditoría de movimientos)
CREATE TABLE movimientos_inventario (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_inventario INT NOT NULL,
    tipo_movimiento ENUM('entrada_tejido', 'salida_tejido', 'entrada_vaporizado', 'salida_vaporizado') NOT NULL,
    docenas INT NOT NULL,
    unidades INT NOT NULL,
    id_usuario INT NOT NULL,
    referencia VARCHAR(100), -- código de lote o producción
    observaciones TEXT,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_inventario) REFERENCES inventario_intermedio(id_inventario),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    INDEX idx_fecha (fecha_movimiento),
    INDEX idx_tipo (tipo_movimiento)
) ENGINE=InnoDB;

-- TABLA: colores (catálogo de colores para teñido)
CREATE TABLE colores (
    id_color INT AUTO_INCREMENT PRIMARY KEY,
    codigo_color VARCHAR(20) UNIQUE NOT NULL,
    nombre_color VARCHAR(50) NOT NULL,
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    INDEX idx_codigo (codigo_color)
) ENGINE=InnoDB;

-- =====================================================
-- DATOS INICIALES
-- =====================================================

-- Insertar turnos
INSERT INTO turnos (nombre_turno, hora_inicio, hora_fin) VALUES
('Mañana', '06:00:00', '14:00:00'),
('Tarde', '14:00:00', '22:00:00'),
('Noche', '22:00:00', '06:00:00');

-- Insertar líneas de producto
INSERT INTO lineas_producto (codigo_linea, nombre_linea, descripcion) VALUES
('LUJO', 'LUJO', 'Productos de hilo de poliamida con torsión y sin texturizar'),
('STRETCH', 'STRETCH', 'Productos de hilo texturizado de poliamida'),
('LYCRA20', 'LYCRA 20', 'Productos con Lycra denier 20'),
('LYCRA40', 'LYCRA 40', 'Productos con Lycra denier 40'),
('CAMISETAS', 'CAMISETAS', 'Camisetas de poliamida para diferentes edades');

-- Insertar tipos de producto
INSERT INTO tipos_producto (nombre_tipo, categoria, descripcion) VALUES
('PANTYHOSE', 'ensamblaje', 'Pantymedias que requieren ensamblaje de piernas y parche'),
('MEDIA SOPORTE', 'directo', 'Medias de soporte que solo requieren costura de puntera'),
('MEDIA PANTALÓN', 'directo', 'Medias tipo pantalón que solo requieren costura de puntera'),
('MEDIA SOCKET', 'directo', 'Medias tipo socket'),
('COBERTOR DE PIE', 'directo', 'Cobertor de pie'),
('CUERPO', 'ensamblaje', 'Cuerpo de camiseta (pecho y espalda)'),
('MANGA', 'ensamblaje', 'Manga de camiseta'),
('RIBETE', 'ensamblaje', 'Ribete para cuello de camiseta'),
('CLASICO', 'ensamblaje', 'Camiseta clásica completa');

-- Insertar diseños
INSERT INTO disenos (nombre_diseno, descripcion) VALUES
('PUNTERA REFORZADA', 'Con refuerzo en la puntera'),
('SIN PUNTERA', 'Sin puntera reforzada'),
('NUDA', 'Diseño nudo/transparente'),
('BASICO', 'Diseño básico estándar');

-- Insertar tipos de insumo
INSERT INTO tipos_insumo (nombre_tipo, descripcion) VALUES
('HILO POLIAMIDA', 'Hilos de poliamida en diferentes deniers'),
('LYCRA', 'Fibra elástica Lycra'),
('ELASTICO', 'Elásticos para pretina'),
('ALGODON', 'Algodón para parches');

-- Insertar usuario administrador por defecto
-- Password: admin123 (debes cambiarla en producción)
INSERT INTO usuarios (codigo_usuario, nombre_completo, usuario, password, rol, area) VALUES
('ADMIN001', 'Administrador del Sistema', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'SISTEMAS');

-- Insertar algunas máquinas de ejemplo
INSERT INTO maquinas (numero_maquina, descripcion, estado, ubicacion) VALUES
('M-01', 'Máquina Circular 4" 400 agujas', 'operativa', 'ZONA A'),
('M-02', 'Máquina Circular 4" 400 agujas', 'operativa', 'ZONA A'),
('M-03', 'Máquina Circular 4" 400 agujas', 'operativa', 'ZONA A'),
('M-04', 'Máquina Circular 4" 400 agujas', 'operativa', 'ZONA A'),
('M-05', 'Máquina Circular 4" 400 agujas', 'operativa', 'ZONA A'),
('M-06', 'Máquina Circular 4" 400 agujas', 'mantenimiento', 'ZONA B'),
('M-07', 'Máquina Circular 4" 400 agujas', 'operativa', 'ZONA B'),
('M-08', 'Máquina Circular 4" 400 agujas', 'operativa', 'ZONA B'),
('M-09', 'Máquina Circular 4" 400 agujas', 'operativa', 'ZONA B'),
('M-10', 'Máquina Circular 4" 400 agujas', 'operativa', 'ZONA B');

-- Insertar colores comunes
INSERT INTO colores (codigo_color, nombre_color) VALUES
('NEGRO', 'Negro'),
('COGNAC', 'Coñac'),
('BEIGE', 'Beige'),
('BLANCO', 'Blanco'),
('GRIS', 'Gris'),
('AZUL', 'Azul'),
('VERDE', 'Verde');
