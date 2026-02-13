<?php
/**
 * API de Catálogos Móviles
 * Sistema ERP Hermen Ltda.
 * 
 * Catálogos para sincronización offline de la app móvil
 */

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../../config/database.php';

try {
    $db = getDB();
    $action = $_GET['action'] ?? 'all';

    switch ($action) {

        // =====================================================
        // USUARIOS - Para validación offline de PIN
        // =====================================================
        case 'usuarios':
            $stmt = $db->query("
                SELECT 
                    id_usuario AS id,
                    codigo_usuario AS codigo,
                    nombre_completo AS nombre,
                    pin,
                    rol,
                    area
                FROM usuarios 
                WHERE estado = 'activo' AND pin IS NOT NULL AND pin != ''
                ORDER BY nombre_completo
            ");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode([
                'success' => true,
                'usuarios' => $usuarios,
                'total' => count($usuarios),
                'version' => date('Y-m-d H:i:s')
            ]);
            break;

        // =====================================================
        // PRODUCTOS - Con stock referencial
        // =====================================================
        case 'productos':
            $tipo = $_GET['tipo'] ?? null; // Filtrar por tipo de inventario

            $sql = "
                SELECT 
                    i.id_inventario AS id,
                    i.codigo,
                    i.nombre,
                    i.stock_actual AS stock,
                    i.stock_minimo,
                    um.abreviatura AS unidad,
                    um.nombre AS unidad_nombre,
                    ti.codigo AS tipo_codigo,
                    ti.nombre AS tipo_nombre,
                    c.nombre AS categoria,
                    s.nombre AS subcategoria
                FROM inventarios i
                LEFT JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                LEFT JOIN tipos_inventario ti ON i.id_tipo_inventario = ti.id_tipo_inventario
                LEFT JOIN categorias_inventario c ON i.id_categoria = c.id_categoria
                LEFT JOIN subcategorias_inventario s ON i.id_subcategoria = s.id_subcategoria
                WHERE i.activo = 1
            ";

            $params = [];
            if ($tipo) {
                $sql .= " AND i.id_tipo_inventario = ?";
                $params[] = $tipo;
            }

            $sql .= " ORDER BY i.nombre";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode([
                'success' => true,
                'productos' => $productos,
                'total' => count($productos),
                'version' => date('Y-m-d H:i:s')
            ]);
            break;

        // =====================================================
        // ÁREAS DE PRODUCCIÓN
        // =====================================================
        case 'areas':
            $stmt = $db->query("
                SELECT
                    id_area AS id,
                    codigo,
                    nombre,
                    descripcion
                FROM areas_produccion
                WHERE activo = 1
                  AND nombre IN ('Tejeduría', 'Costura', 'Teñido', 'Empaque')
                ORDER BY nombre
            ");
            $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode([
                'success' => true,
                'areas' => $areas,
                'total' => count($areas)
            ]);
            break;

        // =====================================================
        // TIPOS DE SALIDA
        // =====================================================
        case 'tipos_salida':
            $tipos = [
                ['id' => 'PRODUCCION', 'nombre' => 'Producción', 'icono' => 'fa-industry', 'color' => '#3498db'],
                ['id' => 'MUESTRA', 'nombre' => 'Muestra', 'icono' => 'fa-flask', 'color' => '#9b59b6']
            ];

            ob_clean();
            echo json_encode([
                'success' => true,
                'tipos' => $tipos
            ]);
            break;

        // =====================================================
        // TIPOS DE INVENTARIO
        // =====================================================
        case 'tipos_inventario':
            $stmt = $db->query("
                SELECT 
                    id_tipo_inventario AS id,
                    codigo,
                    nombre,
                    icono,
                    color
                FROM tipos_inventario 
                WHERE activo = 1
                ORDER BY orden
            ");
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            ob_clean();
            echo json_encode([
                'success' => true,
                'tipos' => $tipos
            ]);
            break;

        // =====================================================
        // TODOS LOS CATÁLOGOS (para sync inicial)
        // =====================================================
        case 'all':
            // Usuarios
            $stmtU = $db->query("
                SELECT id_usuario AS id, codigo_usuario AS codigo, nombre_completo AS nombre, pin, rol, area
                FROM usuarios WHERE estado = 'activo' AND pin IS NOT NULL
            ");
            $usuarios = $stmtU->fetchAll(PDO::FETCH_ASSOC);

            // Productos (limitado para carga inicial)
            $stmtP = $db->query("
                SELECT i.id_inventario AS id, i.codigo, i.nombre, i.stock_actual AS stock,
                       um.abreviatura AS unidad, ti.codigo AS tipo_codigo, i.id_tipo_inventario
                FROM inventarios i
                LEFT JOIN unidades_medida um ON i.id_unidad = um.id_unidad
                LEFT JOIN tipos_inventario ti ON i.id_tipo_inventario = ti.id_tipo_inventario
                WHERE i.activo = 1
                ORDER BY i.nombre
            ");
            $productos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

            // Áreas
            $stmtA = $db->query("SELECT id_area AS id, codigo, nombre FROM areas_produccion WHERE activo = 1 AND nombre IN ('Tejeduría', 'Costura', 'Teñido', 'Empaque')");
            $areas = $stmtA->fetchAll(PDO::FETCH_ASSOC);

            // Tipos de inventario
            $stmtTI = $db->query("
                SELECT id_tipo_inventario AS id, codigo, nombre, icono, color
                FROM tipos_inventario 
                WHERE activo = 1
                ORDER BY orden
            ");
            $tipos_inventario = $stmtTI->fetchAll(PDO::FETCH_ASSOC);

            // Asignar iconos y colores por defecto si no están definidos
            foreach ($tipos_inventario as &$ti) {
                if (empty($ti['icono'])) {
                    switch ($ti['codigo']) {
                        case 'MP':
                            $ti['icono'] = 'fa-boxes';
                            $ti['color'] = '#3498db';
                            break;
                        case 'CAQ':
                            $ti['icono'] = 'fa-flask';
                            $ti['color'] = '#9b59b6';
                            break;
                        case 'EMP':
                            $ti['icono'] = 'fa-box';
                            $ti['color'] = '#27ae60';
                            break;
                        case 'REP':
                            $ti['icono'] = 'fa-tools';
                            $ti['color'] = '#f39c12';
                            break;
                        case 'ACC':
                            $ti['icono'] = 'fa-tags';
                            $ti['color'] = '#e74c3c';
                            break;
                        case 'PP':
                            $ti['icono'] = 'fa-industry';
                            $ti['color'] = '#1abc9c';
                            break;
                        case 'PT':
                            $ti['icono'] = 'fa-check-circle';
                            $ti['color'] = '#2ecc71';
                            break;
                        default:
                            $ti['icono'] = 'fa-cube';
                            $ti['color'] = '#95a5a6';
                            break;
                    }
                }
            }

            // Tipos de salida
            $tipos_salida = [
                ['id' => 'PRODUCCION', 'nombre' => 'Producción', 'icono' => 'fa-industry', 'color' => '#3498db'],
                ['id' => 'MUESTRA', 'nombre' => 'Muestra', 'icono' => 'fa-flask', 'color' => '#9b59b6']
            ];

            ob_clean();
            echo json_encode([
                'success' => true,
                'catalogs' => [
                    'usuarios' => $usuarios,
                    'productos' => $productos,
                    'areas' => $areas,
                    'tipos_inventario' => $tipos_inventario,
                    'tipos_salida' => $tipos_salida
                ],
                'version' => date('Y-m-d H:i:s'),
                'totals' => [
                    'usuarios' => count($usuarios),
                    'productos' => count($productos),
                    'areas' => count($areas),
                    'tipos_inventario' => count($tipos_inventario)
                ]
            ]);
            break;

        default:
            throw new Exception('Acción no válida', 400);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>