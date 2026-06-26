<?php
/**
 * Controlador procedural de cierre de sesión del vendedor (ruta legacy).
 * Destruye la sesión activa y redirige al formulario de login principal.
 * Esta ruta era usada antes de migrar al router MVC centralizado.
 *
 * @deprecated Usar la ruta ?page=logout del router MVC (public/index.php)
 *             que incluye limpieza completa de cookie de sesión y carrito.
 */
session_start();
// Destruir sesión y redirigir al login del sistema
session_destroy();
header("Location: ../../login.php");
exit();
