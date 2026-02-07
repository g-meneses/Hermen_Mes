<?php
/**
 * Script de migración: Sistema de Permisos de Inventario
 * Ejecutar este script para crear la tabla de permisos y usuarios de prueba
 * 
 * USO: php scripts/setup_permisos_inventario.php
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Configuración de Sistema de Permisos de Inventario ===\n\n";

try {
    $db = getDB();

    // ========== 1. CREAR TABLA DE PERMISOS ==========
    echo "1. Creando tabla permisos_inventario...\n";

    $sql = "CREATE TABLE IF NOT EXISTS permisos_inventario (
        id_permiso INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        
        -- Permisos de acciones
        puede_ingresos TINYINT(1) DEFAULT 0 COMMENT 'Puede realizar ingresos de inventario',
        puede_salidas TINYINT(1) DEFAULT 0 COMMENT 'Puede realizar salidas de inventario',
        puede_ajustes TINYINT(1) DEFAULT 0 COMMENT 'Puede realizar ajustes de inventario',
        puede_transferencias TINYINT(1) DEFAULT 0 COMMENT 'Puede realizar transferencias',
        puede_crear_items TINYINT(1) DEFAULT 0 COMMENT 'Puede crear nuevos items',
        puede_editar_items TINYINT(1) DEFAULT 0 COMMENT 'Puede editar items existentes',
        puede_eliminar_items TINYINT(1) DEFAULT 0 COMMENT 'Puede eliminar items',
        
        -- Permisos de visualización
        ver_costos TINYINT(1) DEFAULT 0 COMMENT 'Puede ver costos unitarios',
        ver_valores_totales TINYINT(1) DEFAULT 0 COMMENT 'Puede ver valores totales del inventario',
        ver_kardex TINYINT(1) DEFAULT 0 COMMENT 'Puede ver kardex detallado',
        ver_reportes TINYINT(1) DEFAULT 0 COMMENT 'Puede acceder a reportes',
        
        -- Restricción por tipo de inventario (NULL = todos, o lista separada por comas: '1,2,3')
        tipos_inventario_permitidos VARCHAR(100) DEFAULT NULL COMMENT 'IDs de tipos permitidos, NULL=todos',
        
        -- Auditoría
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        UNIQUE KEY uk_usuario (id_usuario),
        INDEX idx_usuario (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
    COMMENT='Permisos granulares para el módulo de inventarios'";

    $db->exec($sql);
    echo "   ✓ Tabla creada exitosamente\n\n";

    // ========== 2. AGREGAR NUEVO ROL A USUARIOS ==========
    echo "2. Verificando roles de usuario...\n";

    // Verificar si ya existe el rol 'operador_inv'
    $checkRol = $db->query("SHOW COLUMNS FROM usuarios LIKE 'rol'")->fetch(PDO::FETCH_ASSOC);
    $enumValues = $checkRol['Type'];

    if (strpos($enumValues, 'operador_inv') === false) {
        echo "   Agregando nuevo rol 'operador_inv'...\n";
        $db->exec("ALTER TABLE usuarios MODIFY COLUMN rol 
            ENUM('tejedor','revisor','tintorero','coordinador','gerencia','admin','operador_inv') 
            NOT NULL");
        echo "   ✓ Rol agregado\n\n";
    } else {
        echo "   ✓ Rol 'operador_inv' ya existe\n\n";
    }

    // ========== 3. CREAR USUARIOS DE PRUEBA ==========
    echo "3. Creando usuarios de prueba...\n";

    $password_hash = password_hash('test123', PASSWORD_DEFAULT);

    // Usuario para salidas
    $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE usuario = ?");
    $stmt->execute(['test_salidas']);
    $existeSalidas = $stmt->fetch();

    if (!$existeSalidas) {
        $db->exec("INSERT INTO usuarios (codigo_usuario, nombre_completo, usuario, password, rol, area, estado)
            VALUES ('TEST-SAL', 'Usuario Prueba Salidas', 'test_salidas', '$password_hash', 'operador_inv', 'Almacén', 'activo')");
        echo "   ✓ Usuario 'test_salidas' creado (contraseña: test123)\n";
        $id_test_salidas = $db->lastInsertId();
    } else {
        echo "   → Usuario 'test_salidas' ya existe\n";
        $id_test_salidas = $existeSalidas['id_usuario'];
    }

    // Usuario para ingresos
    $stmt->execute(['test_ingresos']);
    $existeIngresos = $stmt->fetch();

    if (!$existeIngresos) {
        $db->exec("INSERT INTO usuarios (codigo_usuario, nombre_completo, usuario, password, rol, area, estado)
            VALUES ('TEST-ING', 'Usuario Prueba Ingresos', 'test_ingresos', '$password_hash', 'operador_inv', 'Almacén', 'activo')");
        echo "   ✓ Usuario 'test_ingresos' creado (contraseña: test123)\n";
        $id_test_ingresos = $db->lastInsertId();
    } else {
        echo "   → Usuario 'test_ingresos' ya existe\n";
        $id_test_ingresos = $existeIngresos['id_usuario'];
    }

    echo "\n";

    // ========== 4. ASIGNAR PERMISOS ==========
    echo "4. Configurando permisos de inventario...\n";

    // Permisos para test_salidas: SOLO salidas, sin ver costos
    $db->exec("INSERT INTO permisos_inventario 
        (id_usuario, puede_salidas, puede_ingresos, puede_ajustes, puede_crear_items, 
         ver_costos, ver_valores_totales, ver_kardex, ver_reportes, activo)
        VALUES ($id_test_salidas, 1, 0, 0, 0, 0, 0, 0, 0, 1)
        ON DUPLICATE KEY UPDATE 
            puede_salidas = 1, puede_ingresos = 0, puede_ajustes = 0,
            ver_costos = 0, ver_valores_totales = 0");
    echo "   ✓ Permisos asignados a 'test_salidas': Solo salidas, sin ver costos\n";

    // Permisos para test_ingresos: SOLO ingresos, sin ver costos
    $db->exec("INSERT INTO permisos_inventario 
        (id_usuario, puede_ingresos, puede_salidas, puede_ajustes, puede_crear_items, 
         ver_costos, ver_valores_totales, ver_kardex, ver_reportes, activo)
        VALUES ($id_test_ingresos, 1, 0, 0, 0, 0, 0, 0, 0, 1)
        ON DUPLICATE KEY UPDATE 
            puede_ingresos = 1, puede_salidas = 0, puede_ajustes = 0,
            ver_costos = 0, ver_valores_totales = 0");
    echo "   ✓ Permisos asignados a 'test_ingresos': Solo ingresos, sin ver costos\n";

    echo "\n=== ✓ Configuración completada exitosamente ===\n\n";

    echo "RESUMEN DE USUARIOS CREADOS:\n";
    echo "┌─────────────────┬──────────────┬────────────────────────────────────┐\n";
    echo "│ Usuario         │ Contraseña   │ Permisos                           │\n";
    echo "├─────────────────┼──────────────┼────────────────────────────────────┤\n";
    echo "│ test_salidas    │ test123      │ Solo salidas, sin ver costos       │\n";
    echo "│ test_ingresos   │ test123      │ Solo ingresos, sin ver costos      │\n";
    echo "└─────────────────┴──────────────┴────────────────────────────────────┘\n";

} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>