<?php
/**
 * API para Categorías de Inventario
 * ERP Hermen Ltda.
 */
ob_start();
ob_clean();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no iniciada']);
    exit;
}

$db = getDB();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_tipos':
            // Obtener todos los tipos de inventario activos
            $stmt = $db->query("
                SELECT id_tipo_inventario, codigo, nombre, icono, color 
                FROM tipos_inventario 
                WHERE activo = 1 
                ORDER BY orden
            ");
            $tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'tipos' => $tipos]);
            break;

        case 'get_categorias':
            // Obtener categorías, opcionalmente filtradas por tipo
            $tipoId = $_GET['id_tipo'] ?? null;

            $sql = "SELECT id_categoria, nombre, id_tipo_inventario 
                    FROM categorias_inventario 
                    WHERE activo = 1";
            if ($tipoId) {
                $sql .= " AND id_tipo_inventario = :tipoId";
            }
            $sql .= " ORDER BY nombre";

            $stmt = $db->prepare($sql);
            if ($tipoId) {
                $stmt->bindValue(':tipoId', $tipoId);
            }
            $stmt->execute();
            $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'categorias' => $categorias]);
            break;

        case 'get_subcategorias':
            // Obtener subcategorías de una categoría específica
            $catId = $_GET['id_categoria'] ?? null;

            if (!$catId) {
                echo json_encode(['success' => true, 'subcategorias' => []]);
                break;
            }

            $stmt = $db->prepare("
                SELECT id_subcategoria, nombre 
                FROM subcategorias_inventario 
                WHERE id_categoria = :catId AND activo = 1 
                ORDER BY nombre
            ");
            $stmt->bindValue(':catId', $catId);
            $stmt->execute();
            $subcategorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'subcategorias' => $subcategorias]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>