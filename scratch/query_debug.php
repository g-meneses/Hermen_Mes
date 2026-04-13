<?php
require_once __DIR__ . '/../config/database.php';
$db = getDB();

echo "--- DETALLE DOCUMENTO 51 ---\n";
$stmt = $db->prepare('SELECT * FROM documentos_inventario_detalle WHERE id_documento = 51');
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- DETALLE DOCUMENTO 126 ---\n";
$stmt = $db->prepare('SELECT * FROM documentos_inventario_detalle WHERE id_documento = 126');
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- ANALISIS DE SOLAPAMIENTO (Entregas nuevas con lotes viejos abiertos) ---\n";
$query = "
    SELECT 
        l.codigo_lote, l.fecha_inicio, l.id_documento_salida, d.fecha_documento
    FROM lote_wip l
    JOIN documentos_inventario d ON l.id_documento_salida = d.id_documento
    WHERE l.estado_lote = 'ACTIVO'
    ORDER BY l.fecha_inicio ASC
    LIMIT 20
";
print_r($db->query($query)->fetchAll());
