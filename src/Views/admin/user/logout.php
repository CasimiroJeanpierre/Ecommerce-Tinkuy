<?php
/**
 * Controlador procedural de cierre de sesión (ruta legacy del panel admin).
 * Destruye la sesión activa y redirige al formulario de login de admin.
 *
 * @deprecated Usar la ruta ?page=logout del router MVC (public/index.php)
 *             que destruye la sesión con limpieza completa de cookie y datos.
 */
session_start();
// Destruir la sesión y redirigir al login del panel admin
session_destroy();
header("Location: login.php");
exit();
