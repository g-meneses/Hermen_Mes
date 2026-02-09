<?php
/**
 * Script para ejecutar migración de PWA Mobile
 * Sistema MES Hermen Ltda.
 */

require_once __DIR__ . '/../config/database.php';

echo "<h2>🚀 Migración PWA Mobile - Salidas de Inventario</h2>";
echo "<pre>";

try {
    $db = getDB();

    // 1. Agregar campo PIN a usuarios
    echo "1. Agregando campo PIN a usuarios...\n";
    try {
        $db->exec("ALTER TABLE usuarios ADD COLUMN pin VARCHAR(4) DEFAULT NULL");
        echo "   ✅ Campo PIN agregado\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⏭️ Campo PIN ya existe\n";
        } else {
            throw $e;
        }
    }

    // 2. Crear tabla salidas_moviles
    echo "\n2. Creando tabla salidas_moviles...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS salidas_moviles (
            id_salida_movil INT AUTO_INCREMENT PRIMARY KEY,
            uuid_local VARCHAR(36) NOT NULL UNIQUE,
            tipo_salida ENUM('PRODUCCION','CONSUMO_INTERNO','MUESTRA','MERMA','AJUSTE') NOT NULL,
            id_area_destino INT NOT NULL,
            observaciones TEXT,
            usuario_entrega INT NOT NULL,
            usuario_recibe INT NOT NULL,
            fecha_hora_local DATETIME NOT NULL,
            fecha_sincronizada DATETIME DEFAULT NULL,
            estado_sync ENUM('PENDIENTE_SYNC','SINCRONIZADA','RECHAZADA','OBSERVADA') DEFAULT 'PENDIENTE_SYNC',
            motivo_rechazo TEXT,
            id_documento_generado INT DEFAULT NULL,
            dispositivo_info VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_salidas_moviles_estado (estado_sync),
            INDEX idx_salidas_moviles_fecha (fecha_hora_local)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ Tabla salidas_moviles creada\n";

    // 3. Crear tabla salidas_moviles_detalle
    echo "\n3. Creando tabla salidas_moviles_detalle...\n";
    $db->exec("
        CREATE TABLE IF NOT EXISTS salidas_moviles_detalle (
            id_detalle INT AUTO_INCREMENT PRIMARY KEY,
            id_salida_movil INT NOT NULL,
            id_inventario INT NOT NULL,
            cantidad DECIMAL(12,4) NOT NULL,
            stock_referencial DECIMAL(12,4) DEFAULT NULL,
            observaciones VARCHAR(255) DEFAULT NULL,
            INDEX idx_detalle_salida (id_salida_movil),
            CONSTRAINT fk_detalle_salida FOREIGN KEY (id_salida_movil) 
                REFERENCES salidas_moviles(id_salida_movil) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✅ Tabla salidas_moviles_detalle creada\n";

    // 4. Verificar áreas de producción
    echo "\n4. Verificando áreas de producción...\n";
    $stmt = $db->query("SELECT COUNT(*) FROM areas_produccion");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        echo "   Insertando áreas básicas...\n";
        $db->exec("
            INSERT INTO areas_produccion (codigo, nombre, descripcion, activo) VALUES
            ('CORTE', 'Corte', 'Área de corte de tela', 1),
            ('COSTURA', 'Costura', 'Área de costura', 1),
            ('TINTORERIA', 'Tintorería', 'Área de teñido', 1),
            ('VAPORIZADO', 'Vaporizado', 'Área de vaporizado', 1),
            ('TEJIDO', 'Tejido', 'Área de tejeduría', 1),
            ('ALMACEN', 'Almacén', 'Almacén general', 1),
            ('ACABADO', 'Acabado', 'Área de acabado y empaque', 1)
        ");
        echo "   ✅ Áreas insertadas\n";
    } else {
        echo "   ⏭️ Ya existen $count áreas de producción\n";
    }

    // 5. Asignar PINs a usuarios que no tienen
    echo "\n5. Verificando PINs de usuarios...\n";
    $stmt = $db->query("SELECT COUNT(*) FROM usuarios WHERE pin IS NULL OR pin = ''");
    $sinPin = $stmt->fetchColumn();

    if ($sinPin > 0) {
        echo "   ⚠️ Hay $sinPin usuarios sin PIN asignado\n";
        echo "   Asignando PINs aleatorios...\n";

        $stmt = $db->query("SELECT id_usuario, nombre_completo FROM usuarios WHERE pin IS NULL OR pin = ''");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($usuarios as $u) {
            $pin = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            $db->prepare("UPDATE usuarios SET pin = ? WHERE id_usuario = ?")->execute([$pin, $u['id_usuario']]);
            echo "   • {$u['nombre_completo']}: PIN = $pin\n";
        }
        echo "   ✅ PINs asignados\n";
    } else {
        echo "   ✅ Todos los usuarios tienen PIN\n";
    }

    // 6. Mostrar resumen
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
    echo str_repeat("=", 50) . "\n";

    // Listar PINs actuales
    echo "\n📋 PINs de usuarios:\n";
    $stmt = $db->query("SELECT codigo_usuario, nombre_completo, pin, rol FROM usuarios WHERE estado = 'activo' ORDER BY nombre_completo");
    while ($row = $stmt->fetch()) {
        echo "   • [{$row['codigo_usuario']}] {$row['nombre_completo']} - PIN: {$row['pin']} ({$row['rol']})\n";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>