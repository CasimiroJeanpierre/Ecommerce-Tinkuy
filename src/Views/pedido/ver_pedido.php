<?php
require_once BASE_PATH . '/src/Core/db.php';
require_once BASE_PATH . '/src/Controllers/OrderController.php';

// Control de acceso (Seguridad)
$base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : '/Ecommerce-Tinkuy';
if (!isset($_SESSION['usuario_id'])) {
    header("Location: " . $base_url . "?page=login");
    exit;
}

// Validar que se proporcionó un ID de pedido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: " . $base_url . "?page=pedidos");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$id_pedido = $_GET['id'];
$mensaje_error = "";

// Inicializar el controlador y obtener los detalles del pedido
$orderController = new OrderController($conn);
try {
    $resultado = $orderController->getOrderDetails($id_pedido, $id_usuario);
    $pedido = $resultado['pedido'];
    $detalles = $resultado['detalles'];
} catch (Exception $e) {
    $mensaje_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle del Pedido | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <?php include BASE_PATH . '/src/Views/components/navbar.php'; ?>

    <div class="container my-5">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?page=pedidos">Mis Pedidos</a></li>
                <li class="breadcrumb-item active">Pedido #<?= htmlspecialchars($pedido['id_pedido'] ?? '') ?></li>
            </ol>
        </nav>

        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($mensaje_error) ?></div>
        <?php elseif (isset($pedido)): ?>
            <div class="row">
                <!-- Información del Pedido -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0"><i class="bi bi-info-circle"></i> Información del Pedido</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Pedido #:</strong> <?= htmlspecialchars($pedido['id_pedido']) ?></p>
                            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></p>
                            <p><strong>Estado:</strong>
                                <span class="badge <?= $orderController->getOrderStatusClass($pedido['nombre_estado']) ?>">
                                    <?= htmlspecialchars($pedido['nombre_estado']) ?>
                                </span>
                            </p>
                            <p><strong>Total:</strong> S/ <?= number_format($pedido['total_pedido'], 2) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Dirección de Envío -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-info text-dark">
                            <h5 class="card-title mb-0"><i class="bi bi-geo-alt"></i> Dirección de Envío</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($pedido['direccion'])): ?>
                                <p><?= htmlspecialchars($pedido['direccion']) ?></p>
                                <p><?= htmlspecialchars($pedido['ciudad']) ?></p>
                                <p>CP: <?= htmlspecialchars($pedido['codigo_postal']) ?></p>
                            <?php else: ?>
                                <p class="text-muted">No se registró dirección de envío</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Detalles de Productos -->
                <div class="col-12 mt-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="card-title mb-0"><i class="bi bi-box"></i> Productos del Pedido</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Precio Unitario</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detalles as $detalle):
                                            // Normalizar campos posibles según esquema
                                            $nombreProducto = $detalle['nombre_producto'] ?? $detalle['nombre'] ?? $detalle['producto_nombre'] ?? 'Producto';

                                            // Definir la imagen final limpiando espacios (para evitar que se rompa por \r\n de la BD)
                                            $img_principal = trim($detalle['imagen_url'] ?? $detalle['imagen_principal'] ?? $detalle['imagen'] ?? '');
                                            $imagen = $img_principal ?: 'default.png';

                                            $cantidad = isset($detalle['cantidad']) ? (int) $detalle['cantidad'] : (int) ($detalle['cant'] ?? 0);
                                            $precioUnitario = $detalle['precio_unitario'] ?? $detalle['precio_historico'] ?? $detalle['precio'] ?? 0.0;
                                            $subtotal = $cantidad * $precioUnitario;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($imagen)): ?>
                                                            <img src="<?= $project_root ?>/public/img/productos/<?= htmlspecialchars($imagen) ?>"
                                                                alt="<?= htmlspecialchars($nombreProducto) ?>"
                                                                class="img-thumbnail me-2"
                                                                style="width: 50px; height: 50px; object-fit: cover;">
                                                        <?php endif; ?>
                                                        <?= htmlspecialchars($nombreProducto) ?>
                                                    </div>
                                                </td>
                                                <td><?= $cantidad ?></td>
                                                <td>S/ <?= number_format($precioUnitario, 2) ?></td>
                                                <td>S/ <?= number_format($subtotal, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                            <td><strong>S/ <?= number_format($pedido['total_pedido'], 2) ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>