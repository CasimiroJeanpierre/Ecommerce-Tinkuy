<?php
// Esta vista delega a la implementación principal en src/Views/pedido/ver_pedido.php
// para mantener una sola versión consistente de la página de detalle de pedido.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once BASE_PATH . '/src/Views/pedido/ver_pedido.php';
exit;
