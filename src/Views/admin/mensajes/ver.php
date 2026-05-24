<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Mensaje - Admin Tinkuy</title>
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
        .mensaje-detalle { background-color: #fff; border-radius: 8px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .mensaje-header { border-bottom: 2px solid #dee2e6; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .mensaje-body { min-height: 200px; padding: 1.5rem; background-color: #f8f9fa; border-radius: 6px; }
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
        <div class="mb-4">
            <a href="?page=admin_mensajes" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Mensajes
            </a>
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

        <?php if ($mensaje): ?>
            <div class="mensaje-detalle">
                <div class="mensaje-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h2 class="mb-2">
                                <i class="bi bi-envelope-open text-primary me-2"></i>
                                <?= htmlspecialchars($mensaje['asunto']) ?>
                            </h2>
                            <div class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                Recibido: <?= date('d/m/Y H:i:s', strtotime($mensaje['fecha_envio'])) ?>
                            </div>
                        </div>
                        <span class="badge badge-<?= $mensaje['estado'] ?> fs-5">
                            <?= ucfirst($mensaje['estado']) ?>
                        </span>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <i class="bi bi-person-circle"></i> Remitente
                                </h6>
                                <p class="card-text mb-0 fs-5">
                                    <?= htmlspecialchars($mensaje['nombre']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <i class="bi bi-envelope"></i> Correo Electrónico
                                </h6>
                                <p class="card-text mb-0 fs-5">
                                    <a href="mailto:<?= htmlspecialchars($mensaje['email']) ?>">
                                        <?= htmlspecialchars($mensaje['email']) ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h5 class="mb-3">
                        <i class="bi bi-chat-left-text"></i> Mensaje
                    </h5>
                    <div class="mensaje-body">
                        <?= nl2br(htmlspecialchars($mensaje['mensaje'])) ?>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="mailto:<?= htmlspecialchars($mensaje['email']) ?>?subject=Re: <?= urlencode($mensaje['asunto']) ?>" 
                       class="btn btn-primary">
                        <i class="bi bi-reply-fill"></i> Responder por Email
                    </a>

                    <?php if ($mensaje['estado'] !== 'respondido'): ?>
                        <a href="?page=admin_ver_mensaje&id=<?= $mensaje['id_mensaje'] ?>&marcar_respondido=1" 
                           class="btn btn-success"
                           onclick="return confirm('¿Marcar este mensaje como respondido?')">
                            <i class="bi bi-check-circle"></i> Marcar como Respondido
                        </a>
                    <?php else: ?>
                        <a href="?page=admin_ver_mensaje&id=<?= $mensaje['id_mensaje'] ?>&marcar_pendiente=1" 
                           class="btn btn-warning"
                           onclick="return confirm('¿Marcar este mensaje como pendiente?')">
                            <i class="bi bi-exclamation-circle"></i> Marcar como Pendiente
                        </a>
                    <?php endif; ?>

                    <?php if ($mensaje['estado'] !== 'archivado'): ?>
                        <a href="?page=admin_ver_mensaje&id=<?= $mensaje['id_mensaje'] ?>&archivar=1" 
                           class="btn btn-secondary"
                           onclick="return confirm('¿Archivar este mensaje?')">
                            <i class="bi bi-archive"></i> Archivar
                        </a>
                    <?php endif; ?>

                    <a href="?page=admin_ver_mensaje&id=<?= $mensaje['id_mensaje'] ?>&eliminar=1" 
                       class="btn btn-danger"
                       onclick="return confirm('¿Eliminar permanentemente este mensaje?')">
                        <i class="bi bi-trash"></i> Eliminar
                    </a>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                El mensaje no se encuentra disponible o no existe.
            </div>
        <?php endif; ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
