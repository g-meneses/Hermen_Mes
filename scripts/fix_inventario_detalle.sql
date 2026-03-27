-- STEP 1: Saneamiento de datos (Asignar IDs únicos a los ceros)
-- ---------------------------------------------------------
USE mes_hermen;

-- 1. Crear una columna temporal auto-incremental
ALTER TABLE documentos_inventario_detalle ADD COLUMN tmp_id INT AUTO_INCREMENT PRIMARY KEY;

-- 2. Copiar los IDs generados por el auto-incremental a la columna id_detalle
UPDATE documentos_inventario_detalle SET id_detalle = tmp_id;

-- 3. Eliminar la columna temporal (y su primary key asociada)
ALTER TABLE documentos_inventario_detalle DROP COLUMN tmp_id;

-- STEP 2: Corrección Estructural e Índices
-- ---------------------------------------------------------

-- 1. Definir id_detalle como Primary Key con AUTO_INCREMENT
ALTER TABLE documentos_inventario_detalle 
    MODIFY COLUMN id_detalle INT(11) NOT NULL AUTO_INCREMENT,
    ADD PRIMARY KEY (id_detalle);

-- 2. Añadir índices para mejorar el rendimiento de búsquedas y reportes
ALTER TABLE documentos_inventario_detalle 
    ADD INDEX idx_documento (id_documento),
    ADD INDEX idx_inventario (id_inventario),
    ADD INDEX idx_detalle_origen (id_detalle_origen);

-- 3. Verificación final
DESCRIBE documentos_inventario_detalle;
SELECT id_detalle, id_documento, id_inventario FROM documentos_inventario_detalle LIMIT 5;
