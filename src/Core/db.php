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
$host = "127.0.0.1";
$port = 3307; // Puerto XAMPP (diferente al 3306 predeterminado para evitar conflictos)
$usuario = "root";
$password = "ybzz-vr20-d17y";
$database = "tinkuy_db";

// Crear conexión mysqli con puerto explícito como quinto parámetro
$conn = new mysqli($host, $usuario, $password, $database, $port);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>