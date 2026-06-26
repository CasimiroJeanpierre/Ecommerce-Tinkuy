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
$password = getenv('DB_PASSWORD') ?: 'ybzz-vr20-d17y';
$database = getenv('DB_NAME')     ?: 'tinkuy_db';

// Crear conexión mysqli con puerto explícito como quinto parámetro
$conn = new mysqli($host, $usuario, $password, $database, $port);

if ($conn->connect_error) {
    error_log("DB connection error: " . $conn->connect_error);
    http_response_code(500);
    die("Error interno del servidor. Por favor, inténtalo de nuevo más tarde.");
}
?>