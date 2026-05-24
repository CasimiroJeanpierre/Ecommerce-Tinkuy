<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar conexión y controlador
require_once BASE_PATH . '/src/Core/db.php';
require_once BASE_PATH . '/src/Controllers/OrderController.php';

// Calidad (Seguridad): Verificamos que vengan de un pedido exitoso
$base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';
if (!isset($_SESSION['pedido_exitoso_id'])) {
    header('Location: ' . $base_url . '?page=index');
    exit;
}

$id_pedido = intval($_SESSION['pedido_exitoso_id']);
$id_usuario = $_SESSION['usuario_id'] ?? null;

// Limpiamos la variable de sesión para que no puedan recargar esta página
unset($_SESSION['pedido_exitoso_id']);

$pedido = null;
$detalles = [];
$mensaje_error = "";

// Intentamos obtener más información del pedido a través del controlador
try {
    $orderController = new OrderController($conn);
    if ($id_usuario !== null) {
        $res = $orderController->getOrderDetails($id_pedido, $id_usuario);
        $pedido = $res['pedido'] ?? null;
        $detalles = $res['detalles'] ?? [];
    }
} catch (Exception $e) {
    // No bloqueante: mostramos al menos el número de pedido
    $mensaje_error = $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>¡Gracias por tu compra! | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
</head>
<body>
 <?php include BASE_PATH . '/src/Views/components/navbar.php'; ?>

    <div class="container my-5">
        <div class="text-center p-5 border rounded shadow-sm" style="background-color: #f8f9fa;">
            <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
            <h1 class="mt-3">¡Gracias por tu compra!</h1>
            <p class="lead text-muted">Tu pedido ha sido procesado exitosamente.</p>
            <h4 class="fw-normal">Tu número de pedido es: <strong class="text-primary">#<?= htmlspecialchars($id_pedido) ?></strong></h4>
            <p>Hemos enviado una confirmación a tu correo electrónico (simulado).</p>
            <a href="?page=products" class="btn btn-primary mt-3"> <i class="bi bi-arrow-left"></i> Seguir comprando
            </a>
        </div>
    </div>

    <?php 
    // Ruta Footer Corregida
    include BASE_PATH . '/src/Views/components/footer.php'; 
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>