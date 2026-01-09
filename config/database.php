<?php
/**
 * Configuración de Base de Datos
 * Sistema MES Hermen Ltda.
 * Versión: 2.1 - Collation forzada
 */

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'mes_hermen');
define('DB_USER', 'root');
define('DB_PASS', ''); // Cambia esto si tu XAMPP tiene password

// Configuración de la aplicación
define('SITE_URL', 'http://localhost/mes_hermen');
define('SITE_NAME', 'MES Hermen Ltda.');
define('TIMEZONE', 'America/La_Paz');

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Establecer zona horaria
date_default_timezone_set(TIMEZONE);

// Clase de conexión a base de datos
class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );

            // ⭐ FORZAR COLLATION EN CADA CONEXIÓN
            $this->connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->connection->exec("SET CHARACTER SET utf8mb4");
            $this->connection->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
            $this->connection->exec("SET collation_database = 'utf8mb4_unicode_ci'");
            $this->connection->exec("SET collation_server = 'utf8mb4_unicode_ci'");

            // ⭐ ESTO ES CRÍTICO - Eliminar strict mode que causa problemas
            $this->connection->exec("SET SESSION sql_mode = ''");

        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new Exception("No se puede deserializar singleton");
    }
}

function getDB()
{
    return Database::getInstance()->getConnection();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasRole($roles)
{
    if (!isLoggedIn())
        return false;

    if (is_array($roles)) {
        return in_array($_SESSION['user_role'], $roles);
    }
    return $_SESSION['user_role'] === $roles;
}

function redirect($url)
{
    header("Location: " . SITE_URL . "/" . $url);
    exit();
}

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

function jsonResponse($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
?>