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

// PHP 8.1+ changed default mysqli error reporting to throw exceptions.
// Revert to pre-8.1 behaviour: errors return false, not exceptions.
mysqli_report(MYSQLI_REPORT_OFF);

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

// One-time schema fix: TiDB imports from phpMyAdmin dumps may miss the
// AUTO_INCREMENT MODIFY that comes after data inserts. Idempotent — safe to run each boot.
@$conn->query("ALTER TABLE `login_intentos` MODIFY `id` INT NOT NULL AUTO_INCREMENT");

// Image filename fixes: generated filenames uploaded via admin were lost on container
// restart. Map them to committed equivalents. Idempotent — no-ops once already corrected.
@$conn->query("UPDATE `productos` SET `imagen_principal` = 'chompa-alpaca-principal.png' WHERE `imagen_principal` = 'chompa_alpaca_1.jpg'");
@$conn->query("UPDATE `productos` SET `imagen_principal` = 'gorro.png'                   WHERE `imagen_principal` = 'gorro_andino_1.jpg'");
@$conn->query("UPDATE `productos` SET `imagen_principal` = 'manta-cuque__a-principal.png' WHERE `imagen_principal` = 'manta_cusco_1.jpg'");
@$conn->query("UPDATE `productos` SET `imagen_principal` = 'ojotas.png'                  WHERE `imagen_principal` = 'producto_1764429190.png'");
@$conn->query("UPDATE `productos` SET `imagen_principal` = 'poncho.png'                  WHERE `imagen_principal` = 'producto_1764430653.jpg'");
@$conn->query("UPDATE `productos` SET `imagen_principal` = 'quena.png'                   WHERE `imagen_principal` = 'producto_1764437053.png'");
@$conn->query("UPDATE `productos` SET `imagen_principal` = 'cinturon-principal.png'      WHERE `imagen_principal` = 'producto_1764437719.png'");
@$conn->query("UPDATE `productos` SET `imagen_principal` = 'zampolla-principal.png'      WHERE `imagen_principal` = 'producto_1764439324.png'");
@$conn->query("UPDATE `productos` SET `imagen_principal` = 'chalina-principal.png'       WHERE `imagen_principal` = 'producto_1764464104.png'");
// Variant image fixes (code prepends variantes/ when rendering, so store filename only)
@$conn->query("UPDATE `variantes_producto` SET `imagen_variante` = 'chompa-artesanal2.png' WHERE `imagen_variante` = 'chompa_alpaca_rojo_s.jpg'");
@$conn->query("UPDATE `variantes_producto` SET `imagen_variante` = 'chompa-artesanal3.png' WHERE `imagen_variante` = 'chompa_alpaca_rojo_m.jpg'");
@$conn->query("UPDATE `variantes_producto` SET `imagen_variante` = NULL                    WHERE `imagen_variante` = 'chompa_alpaca_azul_l.jpg'");
@$conn->query("UPDATE `variantes_producto` SET `imagen_variante` = 'gorro-artesanal-unixes.png' WHERE `imagen_variante` IN ('gorro_multicolor.jpg','gorro_verde.jpg')");
@$conn->query("UPDATE `variantes_producto` SET `imagen_variante` = NULL WHERE `imagen_variante` IN ('manta_natural_g.jpg','manta_natural_p.jpg')");
?>