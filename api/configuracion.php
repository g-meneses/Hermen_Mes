<?php
// api/configuracion.php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $db = getDB();
    $action = $_GET['action'] ?? 'get';

    // Solo permitir edición a Administradores (o Gerentes) - Opcional según jerarquía del usuario.
    // Asumiremos que tienen acceso al módulo de configuración

    if ($action === 'get') {
        $stmt = $db->query("SELECT * FROM configuracion_sistema ORDER BY id");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $config = [];
        foreach ($data as $row) {
            $config[$row['llave']] = [
                'valor' => $row['valor'],
                'descripcion' => $row['descripcion'],
                'tipo' => $row['tipo_dato']
            ];
        }

        echo json_encode(['success' => true, 'config' => $config]);
    } else if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || empty($data)) {
            throw new Exception("No hay datos para actualizar");
        }

        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE configuracion_sistema SET valor = ? WHERE llave = ?");

        foreach ($data as $llave => $valor) {
            // Validar si es número en caso de ser IVA
            if ($llave === 'impuesto_iva' && !is_numeric($valor)) {
                throw new Exception("El valor del IVA debe ser un número decimal (ej. 0.13)");
            }
            $stmt->execute([$valor, $llave]);
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Configuraciones actualizadas exitosamente']);
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error de configuración: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
