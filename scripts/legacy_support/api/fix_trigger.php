<?php
// Self-contained trigger fix using 127.0.0.1 to avoid CLI/localhost issues
$host = '127.0.0.1';
$db = 'mes_hermen';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    // Force unicode_ci for the connection
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected successfully to $host.<br>";

    // 1. Drop existing trigger
    $pdo->exec("DROP TRIGGER IF EXISTS trg_movimiento_codigo");
    echo "Trigger 'trg_movimiento_codigo' dropped.<br>";

    // 2. Recreate trigger with explicit collation
    $sql = "
    CREATE TRIGGER trg_movimiento_codigo BEFORE INSERT ON movimientos_inventario
    FOR EACH ROW
    BEGIN
        DECLARE siguiente_numero INT;
        DECLARE nuevo_codigo VARCHAR(50);
        DECLARE fecha_str VARCHAR(8);
        
        SET fecha_str = DATE_FORMAT(NEW.fecha_movimiento, '%Y%m%d');
        
        SELECT COALESCE(MAX(CAST(SUBSTRING(codigo_movimiento, -4) AS UNSIGNED)), 0) + 1 
        INTO siguiente_numero 
        FROM movimientos_inventario 
        WHERE codigo_movimiento COLLATE utf8mb4_unicode_ci LIKE CONCAT('MOV-', fecha_str, '%');
        
        SET nuevo_codigo = CONCAT('MOV-', fecha_str, '-', LPAD(siguiente_numero, 4, '0'));
        SET NEW.codigo_movimiento = nuevo_codigo;
    END
    ";

    $pdo->exec($sql);
    echo "Trigger 'trg_movimiento_codigo' created successfully with collation fix.<br>";
    echo "Done.";

} catch (\PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit(1);
}
