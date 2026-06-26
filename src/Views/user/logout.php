<?php
/**
 * Controlador procedural de cierre de sesión del usuario (ruta legacy).
 * Destruye la sesión activa, limpia la cookie de sesión del navegador
 * y redirige a la página principal (index.php).
 *
 * @deprecated Usar la ruta ?page=logout del router MVC (public/index.php)
 *             que además limpia variables de sesión específicas del carrito.
 */
session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión para limpiarla del navegador también:
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente destruir la sesión
session_destroy();

// Redirigir al inicio
header("Location: index.php");
exit;
?>
