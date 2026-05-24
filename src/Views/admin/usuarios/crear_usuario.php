<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Usuario - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* (Tu CSS va aquí) */
body { background:#f8f9fa; }
.sidebar { width:260px; position:fixed; height:100vh; top:0; left:0; background:#212529; padding-top:1rem; color:white; }
.sidebar .nav-link { color:#adb5bd; }
.sidebar .nav-link.active { background:#dc3545; color:#fff; }
.main-content { margin-left:260px; padding:2.5rem; }
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
        <li><a href="?page=admin_usuarios" class="nav-link active" aria-current="page"><i class="bi bi-people-fill"></i> Usuarios</a></li>
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
        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle fs-4 me-2"></i><strong><?= htmlspecialchars($nombre_admin) ?></strong>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark shadow">
            <li><a class="dropdown-item" href="?page=logout">Cerrar Sesión</a></li>
        </ul>
    </div>
</div>

<main class="main-content">
    <h2 class="mb-4">Crear Nuevo Usuario (Admin/Vendedor)</h2>

    <?php if($mensaje_error): ?><div class="alert alert-danger"><?= htmlspecialchars($mensaje_error) ?></div><?php endif; ?>
    <?php if($mensaje_exito): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div><?php endif; ?>

    <div class="card shadow-sm p-4">
        <form method="POST" action="?page=admin_crear_usuario" novalidate>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="usuario" class="form-control" required
                           pattern="^[a-zA-Z0-9_-]{4,15}$"
                           title="4-15 caracteres. Letras, números, guiones o guiones bajos."
                           value="<?= htmlspecialchars($post_data['usuario'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($post_data['email'] ?? '') ?>">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="clave" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rol</label>
                    <select name="id_rol" class="form-select" required>
                        <option value="" disabled selected>Seleccione el Rol</option>
                        <option value="1" <?= (($post_data['id_rol'] ?? 0) == 1) ? 'selected' : '' ?>>Admin</option>
                        <option value="2" <?= (($post_data['id_rol'] ?? 0) == 2) ? 'selected' : '' ?>>Vendedor</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nombres</label>
                    <input type="text" name="nombres" class="form-control" required
                           pattern="^[a-zA-Z\sñáéíóúÁÉÍÓÚ]{3,50}$"
                           value="<?= htmlspecialchars($post_data['nombres'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Apellidos</label>
                    <input type="text" name="apellidos" class="form-control" required
                           pattern="^[a-zA-Z\sñáéíóúÁÉÍÓÚ]{3,50}$"
                           value="<?= htmlspecialchars($post_data['apellidos'] ?? '') ?>">
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">Teléfono (Opcional, 9 dígitos)</label>
                    <input type="tel" name="telefono" class="form-control"
                           pattern="^\d{9}$" maxlength="9" inputmode="numeric"
                           value="<?= htmlspecialchars($post_data['telefono'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-success"><i class="bi bi-person-check"></i> Crear Usuario</button>
            <a href="?page=admin_usuarios" class="btn btn-secondary">Volver</a>
        </form>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>