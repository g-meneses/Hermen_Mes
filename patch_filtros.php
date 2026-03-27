<?php
$files = [
    'c:/xampp/htdocs/mes_hermen/modules/inventarios/js/materias_primas_dinamico.js' => 1,
    'c:/xampp/htdocs/mes_hermen/modules/inventarios/js/colorantes_quimicos_dinamico.js' => 2,
    'c:/xampp/htdocs/mes_hermen/modules/inventarios/js/empaque_dinamico.js' => 3,
    'c:/xampp/htdocs/mes_hermen/modules/inventarios/js/accesorios_dinamico.js' => 4,
    'c:/xampp/htdocs/mes_hermen/modules/inventarios/js/productos_terminados_dinamico.js' => 6,
    'c:/xampp/htdocs/mes_hermen/modules/inventarios/js/repuestos_dinamico.js' => 7
];

foreach ($files as $file => $default_id) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Replace the exact lines that have the hardcoded or missing tipo_id
    $pattern = '/\$\{BASE_URL_API\}\/centro_inventarios\.php\?action=categorias&tipo_id=[0-9]*/';
    $replacement = '${BASE_URL_API}/centro_inventarios.php?action=categorias&tipo_id=${window.TIPO_INVENTARIO_ID || ' . $default_id . '}';
    
    $new_content = preg_replace($pattern, $replacement, $content);
    
    file_put_contents($file, $new_content);
    echo "Patched: " . basename($file) . "\n";
}
echo "OK patching config completed\n";
?>
