<?php
// Vista de productos (ahora en MVC): espera que el controlador provea
// $productos (array), $nombre_vendedor, $base_url.
// Evitar iniciar sesión o incluir DB aquí: lo gestiona el controlador.

$productos = $productos ?? [];
$nombre_vendedor = $nombre_vendedor ?? ($_SESSION['usuario'] ?? '');
$base_url = $base_url ?? (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php');
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : '/Ecommerce-Tinkuy';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Productos - Panel Vendedor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $project_root ?>/public/css/style.css">
    <style>
        .producto-inactivo {
            opacity: 0.6;
            background-color: #f8f9fa;
            /* Gris claro */
        }

        .producto-inactivo:hover {
            /* Evitar que se aclare al pasar el mouse */
            opacity: 0.7;
        }

        .variante-list small {
            /* Mejorar legibilidad de variantes */
            display: block;
            margin-bottom: 2px;
        }
    </style>
</head>

<body>

    <?php
    $pagina_actual = 'productos';
    require BASE_PATH . '/src/Views/components/navbar_vendedor.php';
    ?>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Mis Productos (Activos e Inactivos)</h2>
            <a href="<?= $base_url ?>?page=vendedor_agregar_producto" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Agregar Nuevo Producto
            </a>
        </div>

        <?php if (isset($_SESSION['mensaje_exito'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['mensaje_exito']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje_exito']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['mensaje_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje_error']); ?>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Producto</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Categoría</th>
                                <th scope="col">Variantes Activas</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productos)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Aún no has agregado ningún producto.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): ?>
                                    <tr class="<?= ($producto['estado'] === 'inactivo') ? 'producto-inactivo' : '' ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $project_root ?>/public/img/productos/<?= htmlspecialchars($producto['imagen_principal'] ?: 'default.png') ?>"
                                                    alt="Imagen de <?= htmlspecialchars($producto['nombre_producto']) ?>"
                                                    style="width: 60px; height: 60px; object-fit: cover;" class="rounded me-3">
                                                <strong><?= htmlspecialchars($producto['nombre_producto']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($producto['estado'] === 'activo'): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($producto['nombre_categoria']) ?></td>
                                        <td class="variante-list">
                                            <?php
                                            $variantes = json_decode($producto['variantes_json'], true);
                                            $has_active_variants = false;
                                            if (!empty($variantes[0])) {
                                                // No usamos <ul> para ahorrar espacio vertical
                                                foreach ($variantes as $v) {
                                                    if (isset($v['estado_variante']) && $v['estado_variante'] === 'activo') {
                                                        $has_active_variants = true;
                                                        $stock = (int) ($v['stock'] ?? 0);

                                                        $alerta_stock = '';
                                                        if ($stock === 0) {
                                                            $alerta_stock = ' <span class="badge bg-danger ms-1" title="Sin inventario">Agotado</span>';
                                                        } elseif ($stock <= 5) {
                                                            $alerta_stock = ' <span class="badge bg-warning text-dark ms-1" title="Pocas unidades disponibles">¡Bajo!</span>';
                                                        }

                                                        echo sprintf(
                                                            '<small>%s / %s | <strong>S/ %.2f</strong> | Stock: %d%s</small>', // Formato más compacto
                                                            htmlspecialchars($v['talla'] ?? '-'),
                                                            htmlspecialchars($v['color'] ?? '-'),
                                                            $v['precio'] ?? 0.00,
                                                            $stock,
                                                            $alerta_stock
                                                        );
                                                    }
                                                }
                                                if (!$has_active_variants && $producto['estado'] === 'activo') { // Solo mostrar si el producto está activo
                                                    echo '<small class="text-danger">Ninguna variante activa.</small>';
                                                } elseif (empty($variantes)) { // Manejar el caso []
                                                    echo '<small class="text-muted">Sin variantes.</small>';
                                                }
                                            } else {
                                                echo '<small class="text-muted">Sin variantes creadas.</small>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="<?= $base_url ?>?page=vendedor_editar_producto&id=<?= $producto['id_producto'] ?>"
                                                class="btn btn-sm btn-outline-primary" title="Editar Producto y Variantes">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($producto['estado'] === 'activo'): ?>
                                                <a href="<?= $base_url ?>?page=vendedor_cambiar_estado&id=<?= $producto['id_producto'] ?>&estado=inactivo"
                                                    class="btn btn-sm btn-outline-warning" title="Desactivar Producto"
                                                    onclick="return confirm('¿Desactivar este producto? No será visible para clientes.');">
                                                    <i class="bi bi-eye-slash"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= $base_url ?>?page=vendedor_cambiar_estado&id=<?= $producto['id_producto'] ?>&estado=activo"
                                                    class="btn btn-sm btn-outline-success" title="Activar Producto"
                                                    onclick="return confirm('¿Reactivar este producto?');">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
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