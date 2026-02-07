<?php
/**
 * Permisos de Inventario - Archivo Include
 * Incluir después de header.php en todos los módulos de inventario
 * 
 * Uso: require_once 'includes/permisos_inventario.php';
 * 
 * Variables disponibles después de incluir:
 * - $permisos: Array completo de permisos
 * - $puedeIngreso, $puedeSalida, $puedeCrear, $puedeEditar: booleanos de acciones
 * - $verCostos, $verValores, $verKardex: booleanos de visualización
 */

// === PERMISOS DE INVENTARIO ===
$permisos = $_SESSION['permisos_inventario'] ?? [
    'tiene_restricciones' => true,
    'puede_ingresos' => false,
    'puede_salidas' => false,
    'puede_ajustes' => false,
    'puede_transferencias' => false,
    'puede_crear_items' => false,
    'puede_editar_items' => false,
    'puede_eliminar_items' => false,
    'ver_costos' => false,
    'ver_valores_totales' => false,
    'ver_kardex' => false,
    'ver_reportes' => false
];

// Extraer permisos a variables individuales para facilitar uso
$puedeIngreso = $permisos['puede_ingresos'] ?? false;
$puedeSalida = $permisos['puede_salidas'] ?? false;
$puedeAjuste = $permisos['puede_ajustes'] ?? false;
$puedeTransferencia = $permisos['puede_transferencias'] ?? false;
$puedeCrear = $permisos['puede_crear_items'] ?? false;
$puedeEditar = $permisos['puede_editar_items'] ?? false;
$puedeEliminar = $permisos['puede_eliminar_items'] ?? false;
$verCostos = $permisos['ver_costos'] ?? false;
$verValores = $permisos['ver_valores_totales'] ?? false;
$verKardex = $permisos['ver_kardex'] ?? false;
$verReportes = $permisos['ver_reportes'] ?? false;

// Determinar si tiene restricciones (para mostrar mensajes de info)
$tieneRestricciones = $permisos['tiene_restricciones'] ?? true;
?>