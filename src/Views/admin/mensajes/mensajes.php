<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes de Contacto - Admin Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { width: 260px; height: 100vh; position: fixed; top: 0; left: 0; background-color: #212529; padding-top: 1rem; }
        .sidebar .nav-link { color: #adb5bd; font-size: 1rem; margin-bottom: 0.5rem; }
        .sidebar .nav-link i { margin-right: 0.8rem; }
        .sidebar .nav-link.active { background-color: #dc3545; color: #fff; }
        .sidebar .nav-link:hover { background-color: #343a40; color: #fff; }
        .main-content { margin-left: 260px; padding: 2.5rem; width: calc(100% - 260px); }
        .user-dropdown .dropdown-toggle { color: #fff; }
        .mensaje-card { transition: all 0.2s; cursor: pointer; }
        .mensaje-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .mensaje-no-leido { border-left: 4px solid #dc3545; background-color: #fff3cd; }
        .badge-pendiente { background-color: #ffc107; }
        .badge-respondido { background-color: #28a745; }
        .badge-archivado { background-color: #6c757d; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column p-3 text-white">
        <a href="?page=admin_dashboard" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <i class="bi bi-shop-window fs-4 me-2"></i>
            <span class="fs-4">Admin Tinkuy</span>
        </a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="?page=admin_dashboard" class="nav-link">
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
                </a>
            </li>
            <li>
                <a href="?page=admin_mensajes" class="nav-link active" aria-current="page">
                    <i class="bi bi-envelope-fill"></i>
                    Mensajes
                    <?php if ($estadisticas['no_leidos'] > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $estadisticas['no_leidos'] ?></span>
                    <?php endif; ?>
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

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-2">📧 Mensajes de Contacto</h1>
                <p class="text-muted">Gestiona los mensajes recibidos de los clientes</p>
            </div>
        </div>

        <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['mensaje_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['mensaje_exito'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['mensaje_exito']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje_exito']); ?>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-envelope-fill text-primary" style="font-size: 2.5rem;"></i>
                        <h3 class="mt-2"><?= $estadisticas['total'] ?></h3>
                        <p class="text-muted mb-0">Total Mensajes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-exclamation-circle text-warning" style="font-size: 2.5rem;"></i>
                        <h3 class="mt-2"><?= $estadisticas['pendientes'] ?></h3>
                        <p class="text-muted mb-0">Pendientes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-check-circle text-success" style="font-size: 2.5rem;"></i>
                        <h3 class="mt-2"><?= $estadisticas['respondidos'] ?></h3>
                        <p class="text-muted mb-0">Respondidos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-envelope-open text-danger" style="font-size: 2.5rem;"></i>
                        <h3 class="mt-2"><?= $estadisticas['no_leidos'] ?></h3>
                        <p class="text-muted mb-0">No Leídos</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="btn-group" role="group">
                    <a href="?page=admin_mensajes&filtro=todos" 
                       class="btn btn-<?= $filtro_estado === 'todos' ? 'primary' : 'outline-primary' ?>">
                        Todos (<?= $estadisticas['total'] ?>)
                    </a>
                    <a href="?page=admin_mensajes&filtro=pendiente" 
                       class="btn btn-<?= $filtro_estado === 'pendiente' ? 'warning' : 'outline-warning' ?>">
                        Pendientes (<?= $estadisticas['pendientes'] ?>)
                    </a>
                    <a href="?page=admin_mensajes&filtro=respondido" 
                       class="btn btn-<?= $filtro_estado === 'respondido' ? 'success' : 'outline-success' ?>">
                        Respondidos (<?= $estadisticas['respondidos'] ?>)
                    </a>
                    <a href="?page=admin_mensajes&filtro=archivado" 
                       class="btn btn-<?= $filtro_estado === 'archivado' ? 'secondary' : 'outline-secondary' ?>">
                        Archivados
                    </a>
                </div>
            </div>
        </div>

        <!-- Lista de Mensajes -->
        <?php if (empty($mensajes)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                No hay mensajes <?= $filtro_estado !== 'todos' ? 'con estado "' . $filtro_estado . '"' : '' ?>.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($mensajes as $msg): ?>
                    <div class="col-md-12 mb-3">
                        <div class="card mensaje-card <?= $msg['leido'] == 0 ? 'mensaje-no-leido' : '' ?>"
                             onclick="window.location.href='?page=admin_ver_mensaje&id=<?= $msg['id_mensaje'] ?>'">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1">
                                            <?php if ($msg['leido'] == 0): ?>
                                                <i class="bi bi-envelope-fill text-danger me-2"></i>
                                            <?php else: ?>
                                                <i class="bi bi-envelope-open text-muted me-2"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($msg['asunto']) ?>
                                        </h5>
                                        <p class="text-muted mb-2">
                                            <i class="bi bi-person-circle me-1"></i>
                                            <strong><?= htmlspecialchars($msg['nombre']) ?></strong>
                                            <i class="bi bi-envelope ms-3 me-1"></i>
                                            <?= htmlspecialchars($msg['email']) ?>
                                            <i class="bi bi-clock ms-3 me-1"></i>
                                            <?= date('d/m/Y H:i', strtotime($msg['fecha_envio'])) ?>
                                        </p>
                                        <p class="card-text text-truncate mb-0" style="max-width: 80%;">
                                            <?= htmlspecialchars(substr($msg['mensaje'], 0, 150)) ?>...
                                        </p>
                                    </div>
                                    <div class="d-flex flex-column align-items-end">
                                        <span class="badge badge-<?= $msg['estado'] ?> mb-2">
                                            <?= ucfirst($msg['estado']) ?>
                                        </span>
                                        <div class="btn-group btn-group-sm" role="group" onclick="event.stopPropagation();">
                                            <a href="?page=admin_ver_mensaje&id=<?= $msg['id_mensaje'] ?>" 
                                               class="btn btn-outline-primary" title="Ver Detalle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($msg['estado'] !== 'respondido'): ?>
                                                <a href="?page=admin_mensajes&marcar_respondido=<?= $msg['id_mensaje'] ?>" 
                                                   class="btn btn-outline-success" title="Marcar Respondido"
                                                   onclick="return confirm('¿Marcar como respondido?')">
                                                    <i class="bi bi-check-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="?page=admin_mensajes&eliminar=<?= $msg['id_mensaje'] ?>" 
                                               class="btn btn-outline-danger" title="Eliminar"
                                               onclick="return confirm('¿Eliminar este mensaje?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
