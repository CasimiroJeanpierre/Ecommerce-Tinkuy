<?php
/**
 * Controlador procedural de eliminación de ítem del carrito (ruta legacy).
 * Recibe el id_variante por GET y lo elimina de $_SESSION['carrito'].
 * Redirige a cart.php (ruta relativa legacy) tras la operación.
 *
 * Parámetros GET:
 *   id (int) - ID de la variante a eliminar del carrito de sesión
 *
 * @deprecated Usar la ruta ?page=eliminar_carrito del router MVC (public/index.php)
 *             en lugar de este archivo accedido directamente.
 */
session_start();
// Eliminar la variante del carrito de sesión si existe
if (isset($_GET['id']) && isset($_SESSION['carrito'][$_GET['id']])) {
    unset($_SESSION['carrito'][$_GET['id']]);
}
header("Location: cart.php");
exit();
