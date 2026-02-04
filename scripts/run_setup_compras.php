<?php
/**
 * Script de instalación de tablas para el Módulo de Compras
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();

    echo "Iniciando configuración de base de datos para Módulo de Compras...\n";

    $sqlFile = __DIR__ . '/setup_compras.sql';

    if (!file_exists($sqlFile)) {
        die("Error: No se encuentra el archivo SQL en $sqlFile");
    }

    $sql = file_get_contents($sqlFile);

    // Ejecutar múltiples consultas
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

    try {
        $db->exec($sql);
        echo "¡Tablas creadas y actualizadas exitosamente!\n";

        // Verificar si las tablas existen
        $tables = [
            'solicitudes_compra',
            'ordenes_compra',
            'recepciones_compra',
            'flujos_aprobacion',
            'auditoria_compras'
        ];

        echo "\nVerificando tablas:\n";
        foreach ($tables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->fetch()) {
                echo "✅ $table existe\n";
            } else {
                echo "❌ $table NO existe\n";
            }
        }

    } catch (PDOException $e) {
        echo "Error ejecutando SQL: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Error general: " . $e->getMessage() . "\n";
}
