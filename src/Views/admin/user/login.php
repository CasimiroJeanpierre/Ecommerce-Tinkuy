<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Panel de Administración</h3>

                        <?php if (!empty($login_error)): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <svg class="bi flex-shrink-0 me-2" width="16" height="16" role="img"><use xlink:href="#exclamation-triangle-fill"/></svg>
                                <?= htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <!-- action vacío = misma URL; el router detecta admin_procesar_login -->
                        <form method="POST" action="?page=admin_procesar_login" autocomplete="off" novalidate>
                            <!-- Token CSRF: protección contra CSRF y ataques automatizados -->
                            <input type="hidden" name="csrf_token"
                                   value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="mb-3">
                                <label for="usuario" class="form-label">Usuario</label>
                                <input type="text"
                                       name="usuario"
                                       id="usuario"
                                       class="form-control"
                                       autocomplete="username"
                                       maxlength="20">
                            </div>

                            <div class="mb-3">
                                <label for="clave" class="form-label">Contraseña</label>
                                <input type="password"
                                       name="clave"
                                       id="clave"
                                       class="form-control"
                                       autocomplete="current-password"
                                       maxlength="20">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Iniciar Sesión</button>
                        </form>
                    </div>
                </div>
                <p class="text-center mt-3"><a href="?page=index">← Volver a la tienda</a></p>
            </div>
        </div>
    </div>
</body>
</html>
