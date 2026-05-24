<?php
$host = "127.0.0.1";
$port = 3307; // Puerto por defecto de MySQL
$usuario = "root";
$password = "ybzz-vr20-d17y"; // Tu contraseña (está bien si es vacía en XAMPP)
$database = "tinkuy_db";

// Pasamos el puerto $port como el quinto parámetro
$conn = new mysqli($host, $usuario, $password, $database, $port);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>