<?php
/**
 * Vista de detalle de un pedido (panel de administración).
 * Muestra la cabecera completa del pedido (cliente, dirección, estado, totales) y
 * todas sus líneas de detalle. El admin puede cambiar el estado de cada línea
 * individualmente (pendiente → enviado → entregado) y asignar empresa de envío.
 *
 * Variables esperadas (provistas por AdminController::verPedido()):
 *   $pedido           (array)  - Cabecera del pedido:
 *                                 id_pedido, fecha_pedido, total_pedido, nombre_estado,
 *                                 email, nombres, apellidos, direccion, ciudad, codigo_postal
 *   $detalles         (array)  - Líneas del pedido:
 *                                 nombre_producto, talla, color, cantidad, precio_historico,
 *                                 subtotal, id_estado_detalle, numero_seguimiento, nombre_empresa
 *   $estados_detalle  (array)  - Estados disponibles para el select de cambio de estado
 *   $empresas_envio   (array)  - Empresas de envío disponibles para asignar
 *   $nombre_admin     (string) - Nombre del administrador autenticado (desde $_SESSION)
 *   $base_url         (string) - URL base del proyecto
 */
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : '/Ecommerce-Tinkuy';
$base_path_img = $project_root . '/public/img/productos/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Pedido #<?= $id_pedido ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* (Tu CSS) */
        body { background-color: #f8f9fa; }
        .sidebar {
            width: 260px; height: 100vh; position: fixed; top: 0; left: 0;
            background-color: #212529; padding-top: 1rem;
        }
        .sidebar .nav-link { color: #adb5bd; font-size: 1rem; margin-bottom: 0.5rem; }
        .sidebar .nav-link i { margin-right: 0.8rem; }
        .sidebar .nav-link.active { background-color: #dc3545; color: #fff; }
        .sidebar .nav-link:hover { background-color: #343a40; color: #fff; }
        .main-content { margin-left: 260px; padding: 2.5rem; width: calc(100% - 260px); }
        .user-dropdown .dropdown-toggle { color: #fff; }
        .user-dropdown .dropdown-menu { border-radius: 0.5rem; }
        .badge-estado { font-size: 0.9em; padding: 0.5em 0.75em; }
        .img-producto-tabla { width: 50px; height: 50px; object-fit: cover; border-radius: 0.25rem; }
    </style>
</head>
<body>

    <div class="sidebar d-flex flex-column p-3 text-white">
        <a href="?page=admin_dashboard" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <i class="bi bi-shop-window fs-4 me-2"></i>
            <span class="fs-4">Admin Tinkuy</span>
        </a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li><a href="?page=admin_dashboard" class="nav-link"><i class="bi bi-grid-fill"></i> Dashboard</a></li>
            <li><a href="?page=admin_pedidos" class="nav-link active" aria-current="page"><i class="bi bi-list-check"></i> Pedidos</a></li>
            <li><a href="?page=admin_productos" class="nav-link"><i class="bi bi-box-seam-fill"></i> Productos</a></li>
            <li><a href="?page=admin_usuarios" class="nav-link"><i class="bi bi-people-fill"></i> Usuarios</a></li>
                        <li><a href="?page=admin_mensajes" class="nav-link"><i class="bi bi-envelope-fill"></i> Mensajes</a></li>
            <li><a href="?page=admin_reportes" class="nav-link"><i class="bi bi-graph-up"></i> Reportes</a></li>
        </ul>
        <hr>
        <div class="dropdown user-dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle fs-4 me-2"></i>
                <strong><?= htmlspecialchars($nombre_admin) ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="?page=logout">Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Detalle del Pedido #<?= $id_pedido ?></h1>
            <div>
                <?php if ($permite_cancelacion_admin): ?>
                    <form method="POST" action="?page=admin_ver_pedido&id=<?= $id_pedido ?>" onsubmit="return confirm('¿Estás seguro de que deseas cancelar este pedido? Esta acción repondrá el stock y no se puede deshacer.');" style="display: inline;">
                        <input type="hidden" name="id_pedido" value="<?= $id_pedido ?>">
                        <input type="hidden" name="accion" value="cancelar_pedido">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle-fill me-1"></i> Cancelar Pedido
                        </button>
                    </form>
                <?php endif; ?>
                <a href="?page=admin_pedidos" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver a Pedidos
                </a>
            </div>
        </div>

        <?php if (!empty($mensaje_alerta)): ?>
            <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mensaje_alerta) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-lg-4"></div>
            <div class="col-lg-4"></div>
            <div class="col-lg-4"></div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header">
                <h5><i class="bi bi-box-seam me-2"></i>Productos en este Pedido</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th>Vendedor</th>
                                <th>Cantidad</th>
                                <th>Precio Histórico</th>
                                <th>Subtotal</th>
                                <th>Estado del Ítem</th>
                                <th>Info de Envío</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $detalles_pedido->fetch_assoc()) : ?>
                                <?php
                                // --- Lógica de Estado de Ítem (Perfecta) ---
                                $estado_item_texto = 'Desconocido';
                                $estado_item_color = 'secondary';
                                if ($item['id_estado_detalle'] == 2) {
                                    $estado_item_texto = 'Pagado (Vendedor debe enviar)';
                                    $estado_item_color = 'primary';
                                } else if ($item['id_estado_detalle'] == 3) {
                                    $estado_item_texto = 'Enviado';
                                    $estado_item_color = 'info text-dark';
                                    if ($pedido['id_estado'] == 5) {
                                        $estado_item_texto = 'Enviado (Cancelación Fallida)';
                                        $estado_item_color = 'danger';
                                    }
                                } else if ($item['id_estado_detalle'] == 4) {
                                    $estado_item_texto = 'Entregado';
                                    $estado_item_color = 'success';
                                }
                                if ($pedido['id_estado'] == 5 && $item['id_estado_detalle'] == 2) {
                                       $estado_item_texto = 'Cancelado';
                                       $estado_item_color = 'danger';
                                }

                                // --- LÓGICA DE IMAGEN (CORREGIDA) ---
                                $imagen_src = '';
                                // Esta es la ruta base a tus imágenes públicas
                                $base_path_img = $base_path_img ?? ($project_root . '/public/img/productos/'); 

                                if (!empty($item['imagen_variante'])) {
                                    $imagen_src = $base_path_img . 'variantes/' . htmlspecialchars($item['imagen_variante']);
                                } else if (!empty($item['imagen_principal'])) {
                                    $imagen_src = $base_path_img . 'productos/' . htmlspecialchars($item['imagen_principal']);
                                } else {
                                    $imagen_src = $base_path_img . 'placeholder.png'; // Fallback
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= $imagen_src ?>" class="img-producto-tabla me-2" alt="Imagen del producto">
                                            <div>
                                                <strong><?= htmlspecialchars($item['nombre_producto']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($item['talla']) ?> / <?= htmlspecialchars($item['color']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <i class="bi bi-person-badge"></i> <?= htmlspecialchars($item['nombre_vendedor']) ?>
                                    </td>
                                    <td>x <?= $item['cantidad'] ?></td>
                                    <td>S/ <?= number_format($item['precio_historico'], 2) ?></td>
                                    <td><strong>S/ <?= number_format($item['precio_historico'] * $item['cantidad'], 2) ?></strong></td>
                                    <td>
                                        <span class="badge rounded-pill bg-<?= $estado_item_color ?>">
                                            <?= $estado_item_texto ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['nombre_empresa'])) : ?>
                                            <strong><?= htmlspecialchars($item['nombre_empresa']) ?>:</strong>
                                            <br>
                                            <span class="font-monospace"><?= htmlspecialchars($item['numero_seguimiento']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">---</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>