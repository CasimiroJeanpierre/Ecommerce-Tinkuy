<?php $project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : '/Ecommerce-Tinkuy'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Productos - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* (Tu CSS está perfecto) */
        body { background-color: #f8f9fa; }
        .sidebar { width: 260px; height: 100vh; position: fixed; top: 0; left: 0; background-color: #212529; padding-top: 1rem; }
        .sidebar .nav-link { color: #adb5bd; font-size: 1rem; margin-bottom: 0.5rem; }
        .sidebar .nav-link i { margin-right: 0.8rem; }
        .sidebar .nav-link.active { background-color: #dc3545; color: #fff; }
        .sidebar .nav-link:hover { background-color: #343a40; color: #fff; }
        .main-content { margin-left: 260px; padding: 2.5rem; width: calc(100% - 260px); }
        .user-dropdown .dropdown-toggle { color: #fff; }
        .user-dropdown .dropdown-menu { border-radius: 0.5rem; }
        .img-thumb { width: 60px; height: 60px; object-fit: cover; }
        .tabla-variantes { width: 100%; font-size: 0.8rem; border-collapse: collapse; }
        .tabla-variantes th, .tabla-variantes td { padding: 4px 6px; border: none; }
        .tabla-variantes tr:nth-child(even) { background-color: #f0f0f0; }
        .variante-inactiva { opacity: 0.6; }
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
            <li><a href="?page=admin_pedidos" class="nav-link"><i class="bi bi-list-check"></i> Pedidos</a></li>
            <li><a href="?page=admin_productos" class="nav-link active" aria-current="page"><i class="bi bi-box-seam-fill"></i> Productos</a></li>
            <li><a href="?page=admin_usuarios" class="nav-link"><i class="bi bi-people-fill"></i> Usuarios</a></li>
                        <li><a href="?page=admin_mensajes" class="nav-link"><i class="bi bi-envelope-fill"></i> Mensajes</a></li>
            <li><a href="?page=admin_reportes" class="nav-link"><i class="bi bi-graph-up"></i> Reportes</a></li>
            
            <li class="nav-item mt-3 pt-3 border-top">
                <a href="?page=index" class="nav-link">
                    <i class="bi bi-globe"></i> Ver Tienda
                </a>
            </li>
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
            <h2>Gestión de Productos (Todos)</h2>
            </div>

        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($mensaje_error) ?></div>
        <?php endif; ?>
        <?php if (!empty($mensaje_exito)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle table-hover">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Producto</th>
                                <th scope="col">Vendedor</th>
                                <th scope="col">Estado Prod.</th>
                                <th scope="col">Categoría</th>
                                <th scope="col" style="min-width: 280px;">Variantes (Talla / Color / Precio / Stock / Estado)</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productos)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No hay productos en la tienda.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= $project_root ?>/public/img/productos/<?= htmlspecialchars($producto['imagen_principal']) ?>" alt="<?= htmlspecialchars($producto['nombre_producto']) ?>" class="rounded me-3 img-thumb">
                                                <strong><?= htmlspecialchars($producto['nombre_producto']) ?></strong>
                                            </div>
                                        </td>
                                        <td><small><?= htmlspecialchars($producto['nombre_vendedor']) ?></small></td>
                                        <td>
                                            <?php 
                                            $badge_class = $producto['estado_producto'] === 'activo' ? 'bg-success' : 'bg-danger';
                                            ?>
                                            <span class="badge <?= $badge_class ?>"><?= ucfirst($producto['estado_producto']) ?></span>
                                        </td>
                                        <td><small><?= htmlspecialchars($producto['nombre_categoria']) ?></small></td>
                                        <td>
                                            <?php 
                                            $variantes = json_decode($producto['variantes_json'], true);
                                            
                                            if (empty($variantes) || empty($variantes[0]['id_variante'])) { 
                                                echo '<small class="text-muted">Sin variantes.</small>';
                                            } else {
                                                echo '<table class="tabla-variantes">';
                                                foreach ($variantes as $v) {
                                                    $row_class = $v['estado'] === 'inactivo' ? 'variante-inactiva' : '';
                                                    $badge_var_class = $v['estado'] === 'activo' ? 'bg-secondary' : 'bg-warning text-dark';
                                                    echo sprintf(
                                                        '<tr class="%s"><td style="width: 35px;">%s</td><td style="width: 70px;">%s</td><td class="text-end" style="width: 70px;"><strong>S/ %.2f</strong></td><td class="text-end" style="width: 60px;">(%d)</td><td class="text-center" style="width: 65px;"><span class="badge rounded-pill %s">%s</span></td></tr>',
                                                        $row_class,
                                                        htmlspecialchars($v['talla']),
                                                        htmlspecialchars($v['color']),
                                                        $v['precio'],
                                                        $v['stock'],
                                                        $badge_var_class,
                                                        $v['estado']
                                                    );
                                                }
                                                echo '</table>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="?page=admin_editar_producto&id=<?= $producto['id_producto'] ?>" class="btn btn-sm btn-outline-primary" title="Editar detalles y variantes"> 
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            
                                            <?php if ($producto['estado_producto'] === 'activo'): ?>
                                                <a href="?page=admin_productos&cambiar_estado_id=<?= $producto['id_producto'] ?>&estado=activo" 
                                                   class="btn btn-sm btn-outline-warning" 
                                                   title="Desactivar producto (Ocultar de la tienda)" 
                                                   onclick="return confirm('¿Desactivar este producto? Será invisible hasta que se active manualmente.')"> 
                                                    <i class="bi bi-eye-slash"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?page=admin_productos&cambiar_estado_id=<?= $producto['id_producto'] ?>&estado=inactivo" 
                                                   class="btn btn-sm btn-outline-success" 
                                                   title="Activar producto (Hacer visible en la tienda)" 
                                                   onclick="return confirm('¿Activar este producto? Esto lo hará visible en la tienda.')"> 
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>