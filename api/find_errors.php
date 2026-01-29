<?php
/**
 * Script para encontrar líneas con comentarios mal formateados
 */

$file = 'centro_inventarios.php';
$lines = file($file);
$errors = [];

foreach ($lines as $num => $line) {
    $lineNum = $num + 1;
    $trimmed = trim($line);

    // Buscar líneas que tienen texto suelto sin comentario
    // Patrón: línea que no empieza con //, /*, *, o código válido
    if (
        !empty($trimmed) &&
        !preg_match('/^(\/\/|\/\*|\*|public|private|protected|function|class|if|else|for|while|switch|case|return|echo|print|\$|{|}|;|\)|try|catch|throw|namespace|use)/', $trimmed) &&
        preg_match('/^[a-záéíóúñ]+\s+[a-záéíóúñ]+/i', $trimmed)
    ) {
        $errors[] = "Línea $lineNum: $trimmed";
    }
}

echo "Errores encontrados:\n";
echo "===================\n\n";
foreach ($errors as $error) {
    echo $error . "\n";
}

echo "\n\nTotal: " . count($errors) . " posibles errores\n";
