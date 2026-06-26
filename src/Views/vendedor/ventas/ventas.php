<?php
/**
 * Vista del historial de ventas completadas del vendedor autenticado.
 * Muestra únicamente los ítems de pedido con estado 'Enviado' (3) o 'Entregado' (4),
 * junto con el total de ingresos acumulados. Los pedidos pendientes no aparecen aquí.
 *
 * Variables esperadas (provistas por VentasController):
 *   $items_vendidos  (array)  - Ítems vendidos: id_pedido, fecha_pedido, nombre_producto,
 *                                talla, color, cantidad, precio_historico, subtotal,
 *                                id_estado_detalle, numero_seguimiento, nombre_empresa
 *   $total_ingresos  (float)  - Suma acumulada de subtotales de todos los ítems
 *   $nombre_vendedor (string) - Nombre del vendedor autenticado (desde $_SESSION)
 *   $base_url        (string) - URL base del proyecto
 */
// Verificamos que haya un usuario logueado y sea vendedor
$base_url = $base_url ?? (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php');
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'vendedor') {
    header('Location: ' . $base_url . '?page=login');
    exit;
}

$nombre_vendedor = $_SESSION['usuario'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Historial de Ventas - Panel Vendedor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
</head>
<body class="bg-light">
    <?php 
    // Incluir navbar compartido (aseguramos variables mínimas)
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $base_url = $base_url ?? (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php');
    $pagina_actual = 'ventas';
    require BASE_PATH . '/src/Views/components/navbar_vendedor.php';
    ?>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Mi Historial de Ventas</h2>
                <p class="text-muted">Aquí están todos los artículos que has enviado o han sido entregados.</p>
            </div>
            <button class="btn btn-primary" disabled>
                <i class="bi bi-download me-2"></i> Exportar CSV (Próximamente)
            </button>
        </div>

        <div class="card bg-success text-white mb-4 shadow-sm">
            <div class="card-body text-center">
                <h5 class="card-title">Ingresos Totales (Enviados/Entregados)</h5>
                <h1 class="display-4">S/ <?= number_format($total_ingresos, 2) ?></h1>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Pedido #</th>
                                <th scope="col">Fecha</th>
                                <th scope="col">Producto Vendido</th>
                                <th scope="col">Cant.</th>
                                <th scope="col">Total Ítem</th>
                                <th scope="col">Seguimiento</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items_vendidos)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted p-4">
                                        <i class="bi bi-box-seam" style="font-size: 2rem;"></i>
                                        <h5 class="mt-2">Sin ventas completadas</h5>
                                        Aún no has enviado ningún producto.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items_vendidos as $item): ?>
                                    <tr>
                                        <td><strong>#<?= $item['id_pedido'] ?></strong></td>
                                        <td><?= date('d/m/Y', strtotime($item['fecha_pedido'])) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['nombre_producto']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                (<?= htmlspecialchars($item['talla']) ?> / <?= htmlspecialchars($item['color']) ?>)
                                            </Ssmall>
                                        </td>
                                        <td><?= $item['cantidad'] ?></td>
                                        <td><strong>S/ <?= number_format($item['subtotal'], 2) ?></strong></td>
                                        <td>
                                            <small>
                                                <?= htmlspecialchars($item['nombre_empresa'] ?? 'N/A') ?><br>
                                                <?= htmlspecialchars($item['numero_seguimiento'] ?? 'N/A') ?>
                                            </small>
                                        </td>
                                        <td><?= VentasController::obtenerNombreEstado($item['id_estado_detalle']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>