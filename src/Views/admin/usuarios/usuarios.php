<?php
/**
 * Vista de gestión de usuarios del panel de administración.
 * Muestra la lista completa de usuarios del sistema ordenados por rol y fecha de registro.
 * Permite al admin desactivar clientes (baja lógica), desactivar/reactivar vendedores
 * y acceder al formulario de creación de nuevos usuarios.
 *
 * Variables esperadas (provistas por AdminUsuariosController::listarUsuarios()):
 *   $usuarios          (array)  - Lista de usuarios: id_usuario, usuario, email, fecha_registro,
 *                                  id_rol, nombre_rol, nombres, apellidos, estado
 *   $nombre_admin      (string) - Nombre de usuario del administrador autenticado
 *   $mensaje_error     (string) - Error de la última operación (GET eliminar/desactivar/reactivar)
 *   $mensaje_exito     (string) - Confirmación de la última operación exitosa
 *   $id_usuario_actual (int)    - ID del admin autenticado (para ocultar botón de auto-desactivación)
 *
 * Acciones disponibles mediante GET:
 *   ?eliminar_id=N    → Desactiva un cliente (baja lógica)
 *   ?desactivar_id=N  → Desactiva un vendedor y oculta sus productos
 *   ?reactivar_id=N   → Reactiva cualquier usuario y sus productos
 *
 * Seguridad: Los botones de acción se ocultan para el usuario actual (no puede auto-desactivarse).
 */
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* (Tu CSS va aquí) */
        body {
            background: #f8f9fa;
        }

        .sidebar {
            width: 260px;
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            background: #212529;
            padding-top: 1rem;
            color: white;
        }

        .sidebar .nav-link {
            color: #adb5bd;
        }

        .sidebar .nav-link.active {
            background: #dc3545;
            color: #fff;
        }

        .sidebar .nav-link:hover {
            background: #343a40;
            color: #fff;
        }

        .main-content {
            margin-left: 260px;
            padding: 2.5rem;
        }

        .user-dropdown .dropdown-toggle {
            color: #fff;
        }

        .badge {
            font-size: 0.9rem;
        }

        .user-inactivo {
            opacity: 0.6;
        }
    </style>
</head>

<body>

    <div class="sidebar d-flex flex-column p-3 text-white">
        <a href="?page=admin_dashboard" class="d-flex align-items-center mb-3 text-white text-decoration-none">
            <i class="bi bi-shop-window fs-4 me-2"></i><span class="fs-4">Admin Tinkuy</span>
        </a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li><a href="?page=admin_dashboard" class="nav-link"><i class="bi bi-grid-fill"></i> Dashboard</a></li>
            <li><a href="?page=admin_pedidos" class="nav-link"><i class="bi bi-list-check"></i> Pedidos</a></li>
            <li><a href="?page=admin_productos" class="nav-link"><i class="bi bi-box-seam-fill"></i> Productos</a></li>
            <li><a href="?page=admin_usuarios" class="nav-link active" aria-current="page"><i
                        class="bi bi-people-fill"></i> Usuarios</a></li>
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
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
                data-bs-toggle="dropdown">
                <i class="bi bi-person-circle fs-4 me-2"></i><strong><?= htmlspecialchars($nombre_admin) ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow">
                <li><a class="dropdown-item" href="?page=logout">Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Gestión de Usuarios</h1>
            <a href="?page=admin_crear_usuario" class="btn btn-primary">
                <i class="bi bi-person-plus-fill"></i> Agregar Usuario
            </a>
        </div>

        <?php if ($mensaje_error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($mensaje_error) ?></div><?php endif; ?>
        <?php if ($mensaje_exito): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div><?php endif; ?>

        <div class="card p-4 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Registro</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No se han encontrado usuarios.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario):
                                $clase_rol = 'bg-secondary';
                                if ($usuario['nombre_rol'] === 'admin')
                                    $clase_rol = 'bg-danger';
                                if ($usuario['nombre_rol'] === 'vendedor')
                                    $clase_rol = 'bg-warning text-dark';
                                if ($usuario['nombre_rol'] === 'cliente')
                                    $clase_rol = 'bg-info text-dark';
                                $id = $usuario['id_usuario'];
                                $rol_id = (int) $usuario['id_rol'];
                                $estado = $usuario['estado'] ?? 'activo'; // Asumimos 'activo' si no está definido
                                ?>
                                <tr class="<?= $estado === 'inactivo' ? 'user-inactivo' : '' ?>">
                                    <td><strong><?= $id ?></strong></td>
                                    <td><?= htmlspecialchars($usuario['usuario']) ?></td>
                                    <td><?= htmlspecialchars($usuario['nombres'] . ' ' . $usuario['apellidos']) ?></td>
                                    <td><?= htmlspecialchars($usuario['email']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($usuario['fecha_registro'])) ?></td>
                                    <td><span
                                            class="badge <?= $clase_rol ?>"><?= htmlspecialchars($usuario['nombre_rol']) ?></span>
                                    </td>
                                    <td><span
                                            class="badge bg-<?= $estado === 'activo' ? 'success' : 'secondary' ?>"><?= ucfirst($estado) ?></span>
                                    </td>

                                    <td>
                                        <?php if ($id_usuario_actual === $id): // Es el mismo admin ?>
                                            <button class="btn btn-sm btn-secondary" disabled><i class="bi bi-person-fill-lock"></i>
                                                Yo</button>

                                        <?php elseif ($rol_id === 3 && $estado === 'activo'): // Es Cliente Activo ?>
                                            <a href="?page=admin_usuarios&eliminar_id=<?= $id ?>" class="btn btn-sm btn-danger"
                                                onclick="return confirm('¿Seguro de DESACTIVAR al cliente <?= htmlspecialchars($usuario['usuario']) ?>?');">
                                                <i class="bi bi-person-x-fill"></i> Desactivar
                                            </a>

                                        <?php elseif ($rol_id === 3 && $estado === 'inactivo'): // Es Cliente Inactivo ?>
                                            <a href="?page=admin_usuarios&reactivar_id=<?= $id ?>" class="btn btn-sm btn-success"
                                                onclick="return confirm('¿Seguro de REACTIVAR al cliente <?= htmlspecialchars($usuario['usuario']) ?>?');">
                                                <i class="bi bi-person-check-fill"></i> Reactivar
                                            </a>

                                        <?php elseif ($rol_id === 2 && $estado === 'activo'): // Es Vendedor Activo ?>
                                            <a href="?page=admin_usuarios&desactivar_id=<?= $id ?>" class="btn btn-sm btn-warning"
                                                onclick="return confirm('¿DESACTIVAR a <?= htmlspecialchars($usuario['usuario']) ?>? Sus productos se ocultarán.');">
                                                <i class="bi bi-person-x-fill"></i> Desactivar
                                            </a>

                                        <?php elseif ($rol_id === 2 && $estado === 'inactivo'): // Es Vendedor Inactivo ?>
                                            <a href="?page=admin_usuarios&reactivar_id=<?= $id ?>" class="btn btn-sm btn-success"
                                                onclick="return confirm('¿REACTIVAR a <?= htmlspecialchars($usuario['usuario']) ?>?');">
                                                <i class="bi bi-person-check-fill"></i> Reactivar
                                            </a>

                                        <?php else: // Otro Admin ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>Admin</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>