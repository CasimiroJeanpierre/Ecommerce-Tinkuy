<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* (Tu CSS está perfecto, lo dejo igual) */
        body { background-color: #f8f9fa; }
        .sidebar { width: 260px; height: 100vh; position: fixed; top: 0; left: 0; background-color: #212529; padding-top: 1rem; }
        .sidebar .nav-link { color: #adb5bd; font-size: 1rem; margin-bottom: 0.5rem; }
        .sidebar .nav-link i { margin-right: 0.8rem; }
        .sidebar .nav-link.active { background-color: #dc3545; color: #fff; }
        .sidebar .nav-link:hover { background-color: #343a40; color: #fff; }
        .main-content { margin-left: 260px; padding: 2.5rem; width: calc(100% - 260px); }
        .user-dropdown .dropdown-toggle { color: #fff; }
        .user-dropdown .dropdown-menu { border-radius: 0.5rem; }
        .stat-card-icon { font-size: 3rem; }
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
            <li class="nav-item">
                <a href="?page=admin_dashboard" class="nav-link active" aria-current="page">
                    <i class="bi bi-grid-fill"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="?page=admin_pedidos" class="nav-link">
                    <i class="bi bi-list-check"></i>
                    Pedidos
                </a>
            </li>
            <li>
                <a href="?page=admin_productos" class="nav-link">
                    <i class="bi bi-box-seam-fill"></i>
                    Productos
                </a>
            </li>
            <li>
                <a href="?page=admin_usuarios" class="nav-link">
                    <i class="bi bi-people-fill"></i>
                    Usuarios
                                <a href="?page=admin_mensajes" class="nav-link">
                                    <i class="bi bi-envelope-fill"></i>
                                    Mensajes
                                </a>
                            </li>
                            <li>
                </a>
            </li>
            <li>
                <a href="?page=admin_reportes" class="nav-link">
                    <i class="bi bi-graph-up"></i>
                    Reportes
                </a>
            </li>
            
            <li class="nav-item mt-3 pt-3 border-top">
                <a href="?page=index" class="nav-link">
                    <i class="bi bi-globe"></i> Ver Tienda
                </a>
            </li>
        </ul>
        <hr>
        <div class="dropdown user-dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
                id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle fs-4 me-2"></i>
                <strong><?= htmlspecialchars($nombre_admin) ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="?page=logout">Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>

    <main class="main-content">
        <h1 class="mb-4">Bienvenido, <?= htmlspecialchars($nombre_admin) ?> 👋</h1>
        <p class="lead text-muted mb-4">Resumen general de la tienda Tinkuy.</p>

        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="card shadow-sm text-center h-100 border-warning border-2">
                    <div class="card-body">
                        <i class="bi bi-clock-history stat-card-icon text-warning"></i>
                        <h3 class="card-title mt-3"><?= $pedidos_pendientes ?></h3>
                        <p class="card-text text-muted">Pedidos Pendientes</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <a href="?page=admin_pedidos" class="btn btn-sm btn-outline-warning">Gestionar</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card shadow-sm text-center h-100 border-primary border-2">
                    <div class="card-body">
                        <i class="bi bi-people-fill stat-card-icon text-primary"></i>
                        <h3 class="card-title mt-3"><?= $total_usuarios ?></h3>
                        <p class="card-text text-muted">Usuarios Registrados</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <a href="?page=admin_usuarios" class="btn btn-sm btn-outline-primary">Gestionar</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card shadow-sm text-center h-100 border-info border-2">
                    <div class="card-body">
                        <i class="bi bi-box-seam stat-card-icon text-info"></i>
                        <h3 class="card-title mt-3"><?= $total_productos ?></h3>
                        <p class="card-text text-muted">Productos Totales</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <a href="?page=admin_productos" class="btn btn-sm btn-outline-info">Gestionar</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card shadow-sm text-center h-100 border-success border-2">
                    <div class="card-body">
                        <i class="bi bi-cash-coin stat-card-icon text-success"></i>
                        <h3 class="card-title mt-3">S/ <?= number_format($ingresos_totales, 2) ?></h3>
                        <p class="card-text text-muted">Ingresos Totales</p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3">
                        <a href="?page=admin_pedidos" class="btn btn-sm btn-outline-success">Ver Pedidos</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-4 mt-5 shadow-sm">
            <h4>Acciones Rápidas:</h4>
            <div class="list-group list-group-flush mt-2">
                <a href="?page=admin_pedidos" class="list-group-item list-group-item-action"><i
                        class="bi bi-list-check me-2"></i>Ver y gestionar pedidos de clientes</a>
                <a href="?page=admin_productos" class="list-group-item list-group-item-action"><i
                        class="bi bi-box-seam me-2"></i>Administrar todos los productos de la tienda</a>
                <a href="?page=admin_usuarios" class="list-group-item list-group-item-action"><i
                        class="bi bi-people-fill me-2"></i>Gestionar cuentas de usuario (clientes, vendedores,
                    admins)</a>
                <a href="?page=admin_crear_usuario" class="list-group-item list-group-item-action"><i
                        class="bi bi-person-plus-fill me-2"></i>Crear una nueva cuenta de usuario (vendedor o admin)</a>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>