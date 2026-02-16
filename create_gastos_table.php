<?php
require_once 'config/database.php';
try {
    $db = getDB();

    // Crear tabla de gastos de importación
    $sql = "CREATE TABLE IF NOT EXISTS ordenes_compra_gastos (
        id_gasto INT AUTO_INCREMENT PRIMARY KEY,
        id_orden_compra INT NOT NULL,
        tipo_gasto VARCHAR(100) NOT NULL,
        descripcion TEXT,
        monto DECIMAL(15,2) NOT NULL,
        moneda VARCHAR(3) DEFAULT 'BOB',
        fecha_gasto DATE,
        numero_factura_gasto VARCHAR(50),
        creado_por INT,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_orden_compra),
        FOREIGN KEY (id_orden_compra) REFERENCES ordenes_compra(id_orden_compra) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $db->exec($sql);
    echo "Table 'ordenes_compra_gastos' created successfully";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage();
}
