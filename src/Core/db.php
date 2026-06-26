<?php
/**
 * Archivo de conexión a la base de datos MySQL (mysqli).
 * Crea la variable $conn disponible en todos los archivos que incluyen este módulo.
 * El puerto 3307 es el configurado en XAMPP para evitar conflictos con MySQL nativo.
 *
 * Variables disponibles tras incluir este archivo:
 *   $conn (mysqli) - Conexión activa a la BD tinkuy_db
 *
 * Uso:
 *   require_once BASE_PATH . '/src/Core/db.php';
 *   $resultado = $conn->query("SELECT ...");
 */
// En producción (Azure App Service) estas variables se configuran en
// Application Settings. En local XAMPP siguen usando los valores por defecto.
$host     = getenv('DB_HOST')     ?: '127.0.0.1';
$port     = (int)(getenv('DB_PORT') ?: 3307);
$usuario  = getenv('DB_USER')     ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_NAME')     ?: 'tinkuy_db';

// Si se proporciona un certificado SSL (Azure MySQL con SSL habilitado), usarlo.
$ssl_ca = getenv('MYSQL_SSL_CA') ?: '';

$conn = new mysqli();
if ($ssl_ca && file_exists($ssl_ca)) {
    $conn->ssl_set(null, null, $ssl_ca, null, null);
}
$conn->real_connect($host, $usuario, $password, $database, $port);

if ($conn->connect_error) {
    error_log("DB connection error: " . $conn->connect_error);
    http_response_code(500);
    die("Error interno del servidor. Por favor, inténtalo de nuevo más tarde.");
}
?>