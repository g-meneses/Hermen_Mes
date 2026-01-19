<?php
/**
 * Script de utilidad para corregir Kardex faltantes
 * Busca productos con stock > 0 pero sin movimientos en la tabla movimientos_inventario
 */

require_once '../config/database.php';

// Verificación básica de seguridad (solo administradores deberían ejecutar esto)
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Acceso denegado. Inicie sesión.");
}

$db = getDB();
$action = $_GET['action'] ?? 'list';

header('Content-Type: application/json');

try {
    if ($action === 'list') {
        // Listar productos con problemas
        // Un "problema" es: Stock > 0 en inventarios Y 0 registros en movimientos_inventario
        $sql = "
            SELECT 
                i.id_inventario, 
                i.codigo, 
                i.nombre, 
                i.stock_actual, 
                i.costo_unitario,
                ti.nombre as tipo_inventario,
                (SELECT COUNT(*) FROM movimientos_inventario m WHERE m.id_inventario = i.id_inventario) as total_movimientos
            FROM inventarios i
            JOIN tipos_inventario ti ON i.id_tipo_inventario = ti.id_tipo_inventario
            WHERE i.stock_actual > 0 
            AND i.activo = 1
            HAVING total_movimientos = 0
            ORDER BY i.nombre
        ";

        $stmt = $db->query($sql);
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'count' => count($productos),
            'productos' => $productos
        ]);

    } elseif ($action === 'fix') {
        // Reparar un producto específico o todos
        $idInventario = $_GET['id_inventario'] ?? null;

        if (!$idInventario) {
            echo json_encode(['success' => false, 'message' => 'ID de inventario requerido']);
            exit;
        }

        // Obtener datos del producto
        $stmt = $db->prepare("SELECT * FROM inventarios WHERE id_inventario = ?");
        $stmt->execute([$idInventario]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            exit;
        }

        if ($producto['stock_actual'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'El producto no tiene stock, no se requiere corrección']);
            exit;
        }

        // Verificar si ya tiene movimientos (doble check)
        $stmtCheck = $db->prepare("SELECT COUNT(*) as total FROM movimientos_inventario WHERE id_inventario = ?");
        $stmtCheck->execute([$idInventario]);
        $count = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];

        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => 'El producto ya tiene movimientos registrados']);
            exit;
        }

        // Generar código de movimiento (función local simplificada)
        $fecha = date('Y-m-d H:i:s');
        $codigoMov = 'MOV-' . date('Ymd') . '-FIX-' . $idInventario;

        // Insertar movimiento inicial
        $stmtIns = $db->prepare("
            INSERT INTO movimientos_inventario (
                id_inventario, fecha_movimiento, tipo_movimiento, 
                codigo_movimiento, documento_tipo, documento_numero, 
                cantidad, costo_unitario, costo_total,
                stock_anterior, stock_posterior,
                costo_promedio_anterior, costo_promedio_posterior,
                estado, creado_por
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?)
        ");

        $costo = $producto['costo_unitario'];
        $total = $producto['stock_actual'] * $costo;

        $stmtIns->execute([
            $idInventario,
            $fecha,
            'ENTRADA_ALMACEN',
            $codigoMov,
            'INVENTARIO INICIAL',
            'FIX-' . str_pad($idInventario, 6, '0', STR_PAD_LEFT),
            $producto['stock_actual'],
            $costo,
            $total,
            0,                          // Stock anterior
            $producto['stock_actual'],  // Stock posterior
            0,                          // CPP anterior
            $costo,                     // CPP posterior
            $_SESSION['user_id'] ?? 1
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Movimiento inicial generado correctamente',
            'datos' => [
                'producto' => $producto['nombre'],
                'stock' => $producto['stock_actual'],
                'costo' => $costo
            ]
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>