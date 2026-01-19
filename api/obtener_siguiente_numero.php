<?php
/**
 * API Centralizada para Obtener Siguiente Número de Documento
 * Sistema MES Hermen Ltda.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
// ob_clean(); // Comentado temporalmente para debug
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// ini_set('display_errors', 0); // Comentado para debug
error_reporting(E_ALL);

try {
    // Solo cargar database si no está ya cargada (cuando se llama directamente)
    if (!function_exists('getDB')) {
        require_once '../config/database.php';
    }

    // Solo verificar login si no se está incluyendo desde otro archivo
    if (!isset($db)) {
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            exit();
        }
        $db = getDB();
    }

    // Parámetros requeridos
    $tipoInventario = $_GET['tipo_inventario'] ?? null;
    $operacion = $_GET['operacion'] ?? null; // 'INGRESO' o 'SALIDA'
    $tipoMovimiento = $_GET['tipo_movimiento'] ?? null; // 'PRODUCCION', 'VENTA', etc.
    $modo = $_GET['modo'] ?? 'commit'; // 'preview' o 'commit'

    if (!$tipoInventario || !$operacion || !$tipoMovimiento) {
        echo json_encode([
            'success' => false,
            'message' => 'Parámetros faltantes: tipo_inventario, operacion, tipo_movimiento'
        ]);
        exit();
    }

    // Mapeo de tipos de inventario a códigos
    $codigosInventario = [
        '1' => 'MP',   // Materias Primas
        '2' => 'CAQ',  // Colorantes y Aux. Químicos
        '3' => 'EMP',  // Empaque
        '4' => 'ACC',  // Accesorios
        '6' => 'PT',   // Productos Terminados
        '7' => 'REP'   // Repuestos
    ];

    // Mapeo de tipos de movimiento a códigos
    $codigosMovimiento = [
        'PRODUCCION' => 'P',
        'VENTA' => 'V',
        'MUESTRAS' => 'M',
        'AJUSTE' => 'A',
        'AJUSTE_POS' => 'A',
        'AJUSTE_NEG' => 'A',
        'DEVOLUCION' => 'R',
        'DEVOLUCION_PROD' => 'R',
        'COMPRA' => 'C',
        'INICIAL' => 'I'
    ];

    $codigoInv = $codigosInventario[$tipoInventario] ?? 'INV';
    $codigoMov = $codigosMovimiento[$tipoMovimiento] ?? 'X';

    // Construir prefijo según operación
    if (strtoupper($operacion) === 'INGRESO') {
        $prefijo = "IN-{$codigoInv}-{$codigoMov}";
        $tipoDoc = 'INGRESO';
    } else {
        $prefijo = "OUT-{$codigoInv}-{$codigoMov}";
        $tipoDoc = 'SALIDA';
    }

    // Generar número usando la función centralizada
    $esPreview = (strtolower($modo) === 'preview');
    $numero = generarNumeroDocumento($db, $tipoDoc, $prefijo, $esPreview);

    ob_clean();
    echo json_encode([
        'success' => true,
        'numero' => $numero,
        'prefijo' => $prefijo,
        'modo' => $esPreview ? 'preview' : 'commit',
        'debug' => [
            'tipo_inventario' => $tipoInventario,
            'operacion' => $operacion,
            'tipo_movimiento' => $tipoMovimiento
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error en obtener_siguiente_numero.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error en obtener_siguiente_numero.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}


if (!function_exists('generarNumeroDocumento')) {
    function generarNumeroDocumento($db, $tipo, $prefijo, $esPreview = false)
    {
        $anio = date('Y');
        $mes = date('m');

        if ($esPreview) {
            // MODO PREVIEW: Solo leer, NO actualizar
            $stmt = $db->prepare("
                SELECT ultimo_numero FROM secuencias_documento 
                WHERE tipo_documento COLLATE utf8mb4_unicode_ci = ? 
                AND prefijo COLLATE utf8mb4_unicode_ci = ? 
                AND anio = ? 
                AND mes = ?
            ");
            $stmt->execute([$tipo, $prefijo, $anio, $mes]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Si existe, devolver siguiente sin actualizar
            // Si no existe, devolver 1 (será el primero cuando se confirme)
            $siguiente = $row ? ($row['ultimo_numero'] + 1) : 1;

        } else {
            // MODO COMMIT: Leer con lock y actualizar
            $stmt = $db->prepare("
                SELECT ultimo_numero FROM secuencias_documento 
                WHERE tipo_documento COLLATE utf8mb4_unicode_ci = ? 
                AND prefijo COLLATE utf8mb4_unicode_ci = ? 
                AND anio = ? 
                AND mes = ?
                FOR UPDATE
            ");
            $stmt->execute([$tipo, $prefijo, $anio, $mes]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $siguiente = $row['ultimo_numero'] + 1;
                $stmtUp = $db->prepare("
                    UPDATE secuencias_documento SET ultimo_numero = ?
                    WHERE tipo_documento = ? AND prefijo = ? AND anio = ? AND mes = ?
                ");
                $stmtUp->execute([$siguiente, $tipo, $prefijo, $anio, $mes]);
            } else {
                $siguiente = 1;
                $stmtIn = $db->prepare("
                    INSERT INTO secuencias_documento (tipo_documento, prefijo, anio, mes, ultimo_numero)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmtIn->execute([$tipo, $prefijo, $anio, $mes]);
            }
        }

        return $prefijo . '-' . $anio . $mes . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
    }
}

