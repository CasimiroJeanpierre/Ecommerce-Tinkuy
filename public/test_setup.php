<?php
// Test rápido: DB + Composer vendor
echo "<h1>Test de Setup - Ecommerce-Tinkuy</h1>";

// 1. Test vendor/autoload
echo "<h2>1. Test Composer Autoload</h2>";
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) {
    require $autoload_path;
    echo "✓ vendor/autoload.php cargado correctamente<br>";
} else {
    echo "✗ ERROR: No se encuentra vendor/autoload.php<br>";
    exit;
}

// 2. Test PHPMailer
echo "<h2>2. Test PHPMailer</h2>";
if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
    echo "✓ PHPMailer disponible (versión instalada correctamente)<br>";
} else {
    echo "✗ ERROR: PHPMailer no está disponible<br>";
}

// 3. Test Conexión DB
echo "<h2>3. Test Conexión Base de Datos</h2>";
$host = "localhost";
$port = 3306;
$usuario = "root";
$password = "";
$database = "tinkuy_db";

try {
    $conn = new mysqli($host, $usuario, $password, $database, $port);
    
    if ($conn->connect_error) {
        echo "✗ ERROR de conexión: " . $conn->connect_error . "<br>";
    } else {
        echo "✓ Conexión exitosa a la base de datos 'tinkuy_db'<br>";
        echo "✓ Servidor: " . $conn->host_info . "<br>";
        
        // Test query simple
        $result = $conn->query("SELECT DATABASE() as db");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "✓ Base de datos activa: " . $row['db'] . "<br>";
        }
        
        // Listar tablas
        $result = $conn->query("SHOW TABLES");
        if ($result && $result->num_rows > 0) {
            echo "✓ Tablas encontradas: " . $result->num_rows . "<br>";
            echo "<ul>";
            while ($row = $result->fetch_array()) {
                echo "<li>" . $row[0] . "</li>";
            }
            echo "</ul>";
        } else {
            echo "⚠ No se encontraron tablas (base de datos vacía)<br>";
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "✗ Excepción: " . $e->getMessage() . "<br>";
}

// 4. Test PHP info
echo "<h2>4. Info PHP</h2>";
echo "✓ Versión PHP: " . phpversion() . "<br>";
echo "✓ Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

echo "<hr>";
echo "<p><strong>Todo OK!</strong> Puedes eliminar este archivo (public/test_setup.php) cuando termines las pruebas.</p>";
?>
