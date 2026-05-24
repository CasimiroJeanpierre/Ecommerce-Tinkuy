<?php
// Llamar al controlador que prepara las variables para esta vista
require_once __DIR__ . '/../../Controllers/UserController.php';
// El controlador define: $base_url, $mensaje_error, $mensaje_exito, $seccion_activa,
// $datos_perfil, $direcciones, $tarjetas
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mi Perfil | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
</head>
<body>
    <?php include BASE_PATH . '/src/Views/components/navbar.php'; ?>

    <main class="container my-5">
        <h2 class="mb-4">Dashboard de Mi Cuenta</h2>

        <div class="row">
            <div class="col-md-3">
                <div class="list-group shadow-sm">
                    <a href="<?= $base_url ?>?page=mi_perfil&seccion=dashboard" class="list-group-item list-group-item-action <?= ($seccion_activa === 'dashboard') ? 'active' : '' ?>">
                        <i class="bi bi-speedometer"></i> Dashboard
                    </a>
                    <a href="<?= $base_url ?>?page=mi_perfil&seccion=perfil" class="list-group-item list-group-item-action <?= ($seccion_activa === 'perfil') ? 'active' : '' ?>">
                        <i class="bi bi-person-circle"></i> Mi Perfil
                    </a>
                    <a href="<?= $base_url ?>?page=mi_perfil&seccion=direcciones" class="list-group-item list-group-item-action <?= ($seccion_activa === 'direcciones') ? 'active' : '' ?>">
                        <i class="bi bi-geo-alt-fill"></i> Direcciones
                    </a>
                    <a href="<?= $base_url ?>?page=mi_perfil&seccion=pagos" class="list-group-item list-group-item-action <?= ($seccion_activa === 'pagos') ? 'active' : '' ?>">
                        <i class="bi bi-credit-card-fill"></i> Métodos de Pago
                    </a>
                    <a  href="<?= $base_url ?>?page=pedidos" class="list-group-item list-group-item-action">
                        <i class="bi bi-box-seam"></i> Mis Pedidos
                    </a>
                </div>
            </div>

            <div class="col-md-9">
                <?php if (!empty($mensaje_error)): ?>
                    <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($mensaje_error) ?></div>
                <?php endif; ?>
                <?php if (!empty($mensaje_exito)): ?>
                    <div class="alert alert-success shadow-sm"><?= htmlspecialchars($mensaje_exito) ?></div>
                <?php endif; ?>

                <?php if ($seccion_activa === 'dashboard'): ?>
                    <h3 class="border-bottom pb-2">Resumen de la Cuenta</h3>
                    <p>Bienvenido, <strong><?= htmlspecialchars($datos_perfil['nombre'] . " " . $datos_perfil['apellido']) ?></strong>. </p>
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <div class="col">
                            <div class="card bg-light shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-geo-alt-fill"></i> Direcciones</h5>
                                    <p class="card-text">Tienes <strong><?= count($direcciones) ?></strong> direcciones guardadas.</p>
                                    <a href="<?= $base_url ?>?page=mi_perfil&seccion=direcciones" class="btn btn-sm btn-outline-primary">Gestionar Direcciones</a>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card bg-light shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-credit-card-fill"></i> Pagos</h5>
                                    <p class="card-text">Tienes <strong><?= count($tarjetas) ?></strong> métodos de pago guardados.</p>
                                    <a href="<?= $base_url ?>?page=mi_perfil&seccion=pagos" class="btn btn-sm btn-outline-primary">Gestionar Pagos</a>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($seccion_activa === 'perfil'): ?>

                    <h3 class="border-bottom pb-2">Datos Personales</h3>
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <form method="POST" action="<?= $base_url ?>?page=mi_perfil&seccion=perfil">
                                <input type="hidden" name="accion" value="actualizar_perfil">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Correo Electrónico</label>
                                    <input type="email" class="form-control" id="email" value="<?= htmlspecialchars($datos_perfil['email']) ?>" disabled>
                                    <div class="form-text">El correo es tu usuario y no se puede cambiar aquí.</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="nombre" class="form-label">Nombre</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre"
                                               value="<?= htmlspecialchars($datos_perfil['nombre']) ?>" 
                                               required 
                                               minlength="2" 
                                               maxlength="50"
                                               pattern="[a-zA-Z\sñáéíóúÁÉÍÓÚ]+"
                                               title="Mín. 2, Máx. 50 caracteres. Solo letras y espacios. (IDs 66, 67, 68)">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="apellido" class="form-label">Apellido</label>
                                        <input type="text" class="form-control" id="apellido" name="apellido"
                                               value="<?= htmlspecialchars($datos_perfil['apellido']) ?>" 
                                               required
                                               minlength="2" 
                                               maxlength="50"
                                               pattern="[a-zA-Z\sñáéíóúÁÉÍÓÚ]+"
                                               title="Mín. 2, Máx. 50 caracteres. Solo letras y espacios. (IDs 66, 67, 68)">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="telefono" class="form-label">Teléfono (Opcional)</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono"
                                           value="<?= htmlspecialchars($datos_perfil['telefono']) ?>"
                                           pattern="[0-9]{9}"
                                           maxlength="9"
                                           title="Debe tener 9 dígitos (solo números). (IDs 36-39)">
                                    <div class="form-text">Ej: 987654321 (9 dígitos, solo números)</div>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Cambios</button>
                            </form>
                        </div>
                    </div>

                    <h3 class="border-bottom pb-2 mt-5">Cambiar Contraseña</h3>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form method="POST" action="<?= $base_url ?>?page=mi_perfil&seccion=perfil">
                                <input type="hidden" name="accion" value="cambiar_clave">
                                <div class="mb-3">
                                    <label for="clave_actual" class="form-label">Contraseña Actual</label>
                                    <input type="password" class="form-control" id="clave_actual" name="clave_actual" 
                                           required
                                           minlength="7"
                                           maxlength="30"
                                           title="Tu contraseña actual (mín. 7 caracteres). (ID 87)">
                                </div>
                                <div class="mb-3">
                                    <label for="clave_nueva" class="form-label">Contraseña Nueva</label>
                                    <input type="password" class="form-control" id="clave_nueva" name="clave_nueva" 
                                           required
                                           minlength="7"
                                           maxlength="30"
                                           aria-describedby="passwordHelpBlock">
                                    <div id="passwordHelpBlock" class="form-text">
                                        (IDs 19, 21-24) Debe tener 7-30 caracteres, 1 mayúscula y 1 carácter especial (ej. #, $, !).
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="clave_repetida" class="form-label">Repetir Contraseña Nueva</label>
                                    <input type="password" class="form-control" id="clave_repetida" name="clave_repetida" 
                                           required
                                           minlength="7"
                                           maxlength="30"
                                           title="Repite la nueva contraseña. (ID 85)">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-key-fill"></i> Cambiar Contraseña</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($seccion_activa === 'direcciones'): ?>
                    <h3 class="border-bottom pb-2">Mis Direcciones de Envío</h3>
                    <div class="card mb-4 shadow-sm">
                        <?php
                        $editar_id = isset($_GET['editar']) ? intval($_GET['editar']) : null;
                        $dir_edit = null;
                        if ($editar_id) {
                            foreach ($direcciones as $dd) {
                                if ($dd['id_direccion'] == $editar_id) { $dir_edit = $dd; break; }
                            }
                        }
                        ?>
                        <div class="card-header bg-primary text-white"><?= $dir_edit ? 'Editar Dirección' : 'Agregar Nueva Dirección' ?></div>
                        <div class="card-body">
                            <form method="POST" action="<?= $base_url ?>?page=mi_perfil&seccion=direcciones">
                                <?php if ($dir_edit): ?>
                                    <input type="hidden" name="accion" value="editar_direccion">
                                    <input type="hidden" name="id_direccion" value="<?= $dir_edit['id_direccion'] ?>">
                                <?php else: ?>
                                    <input type="hidden" name="accion" value="agregar_direccion">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label for="direccion" class="form-label">Dirección Completa</label>
                                    <input type="text" class="form-control" id="direccion" name="direccion" required
                                           placeholder="Calle, número, apto/interior"
                                           minlength="10"
                                           maxlength="100"
                                           value="<?= htmlspecialchars($dir_edit['direccion'] ?? '') ?>"
                                           title="Mín. 10, Máx. 100 caracteres. (IDs 60, 61)">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="ciudad" class="form-label">Ciudad</label>
                                        <input type="text" class="form-control" id="ciudad" name="ciudad" required
                                               minlength="2"
                                               maxlength="50"
                                               pattern="[a-zA-Z\sñáéíóúÁÉÍÓÚ]+"
                                               value="<?= htmlspecialchars($dir_edit['ciudad'] ?? '') ?>"
                                               title="Mín. 2, Máx. 50 caracteres. Solo letras. (IDs 66, 67, 68)">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="pais" class="form-label">País</label>
                                        <input type="text" class="form-control" id="pais" name="pais" required
                                               minlength="2"
                                               maxlength="50"
                                               pattern="[a-zA-Z\sñáéíóúÁÉÍÓÚ]+"
                                               value="<?= htmlspecialchars($dir_edit['pais'] ?? '') ?>"
                                               title="Mín. 2, Máx. 50 caracteres. Solo letras. (IDs 72, 73, 74)">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo_postal" class="form-label">Código Postal</label>
                                        <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" required
                                               minlength="4"
                                               maxlength="4"
                                               pattern="[0-9]{4}"
                                               value="<?= htmlspecialchars($dir_edit['codigo_postal'] ?? '') ?>"
                                               title="Debe tener 4 dígitos (solo números). (IDs 78, 80)">
                                    </div>
                                    <div class="col-md-6 mb-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="1" id="es_principal" name="es_principal" <?= (!empty($dir_edit['es_principal'])) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="es_principal"> (ID 83) Establecer como principal </label>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle-fill"></i> <?= $dir_edit ? 'Actualizar Dirección' : 'Guardar Dirección' ?></button>
                                <?php if ($dir_edit): ?>
                                    <a href="<?= $base_url ?>?page=mi_perfil&seccion=direcciones" class="btn btn-secondary ms-2">Cancelar</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    
                    <h4 class="mt-5 border-bottom pb-2">Direcciones Guardadas (<?= count($direcciones) ?>)</h4>
                    <?php if (empty($direcciones)): ?>
                        <div class="alert alert-info shadow-sm">Aún no tienes direcciones de envío guardadas.</div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($direcciones as $dir): ?>
                                <div class="list-group-item list-group-item-action <?= $dir['es_principal'] ? 'list-group-item-primary' : '' ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <i class="bi bi-house-door-fill"></i>
                                            <?= htmlspecialchars($dir['direccion']) ?>
                                            <?php if ($dir['es_principal']): ?>
                                                <span class="badge bg-primary ms-2">Principal</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted"><?= htmlspecialchars($dir['pais']) ?></small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($dir['ciudad']) ?>, C.P. <?= htmlspecialchars($dir['codigo_postal']) ?></p>
                                    <small>
                                        <form method="POST" action="<?= $base_url ?>?page=mi_perfil&seccion=direcciones" style="display:inline-block;">
                                            <input type="hidden" name="accion" value="establecer_principal">
                                            <input type="hidden" name="id_direccion" value="<?= $dir['id_direccion'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success">Principal</button>
                                        </form>
                                        <form method="POST" action="<?= $base_url ?>?page=mi_perfil&seccion=direcciones" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('¿Eliminar esta dirección? Esta acción no se puede deshacer.');">
                                            <input type="hidden" name="accion" value="eliminar_direccion">
                                            <input type="hidden" name="id_direccion" value="<?= $dir['id_direccion'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                        <a href="<?= $base_url ?>?page=mi_perfil&seccion=direcciones&editar=<?= $dir['id_direccion'] ?>" class="btn btn-sm btn-outline-secondary ms-2">Editar</a>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($seccion_activa === 'pagos'): ?>
                    <h3 class="border-bottom pb-2">Métodos de Pago Guardados</h3>
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-primary text-white">Agregar Nueva Tarjeta (Simulada)</div>
                        <div class="card-body">
                            <form method="POST" action="<?= $base_url ?>?page=mi_perfil&seccion=pagos">
                                <input type="hidden" name="accion" value="agregar_tarjeta">
                                <div class="mb-3">
                                    <label for="nombre_tarjeta" class="form-label">Nombre en la Tarjeta</label>
                                    <input type="text" class="form-control" id="nombre_tarjeta" name="nombre_tarjeta"
                                           required
                                           minlength="3"
                                           maxlength="50"
                                           pattern="[a-zA-Z\sñáéíóúÁÉÍÓÚ]+"
                                           title="Mín. 3, Máx. 50 caracteres. Solo letras. (IDs 40-45)">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="numero_tarjeta" class="form-label">Número de Tarjeta (Completo)</label>
                                        <input type="text" class="form-control" id="numero_tarjeta" name="numero_tarjeta"
                                               required placeholder="Solo se guardarán los últimos 4 dígitos"
                                               pattern="[0-9]{13,16}"
                                               maxlength="16"
                                               title="Entre 13 y 16 dígitos numéricos. (IDs 41, 42, 43)">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="expiracion" class="form-label">Fecha de Expiración (MM/AA)</label>
                                        <input type="text" class="form-control" id="expiracion" name="expiracion" required
                                               placeholder="MM/AA" 
                                               pattern="^(0[1-9]|1[0-2])\/\d{2}$"
                                               maxlength="5"
                                               title="Formato MM/AA (Ej: 12/25). (IDs 46, 47)">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="tipo_tarjeta" class="form-label">Tipo de Tarjeta</label>
                                    <select class="form-select" id="tipo_tarjeta" name="tipo_tarjeta">
                                        <option value="Visa">Visa</option>
                                        <option value="Mastercard">Mastercard</option>
                                        <option valueD="Amex">American Express</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle-fill"></i> Guardar Tarjeta</button>
                            </form>
                        </div>
                    </div>

                    <h4 class="mt-5 border-bottom pb-2">Tarjetas Guardadas (<?= count($tarjetas) ?>)</h4>
                    <?php if (empty($tarjetas)): ?>
                        <div class="alert alert-info shadow-sm">Aún no tienes métodos de pago guardados.</div>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($tarjetas as $tar): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center shadow-sm mb-2">
                                    <div class="ms-2 me-auto">
                                        <div class="fw-bold">
                                            <i class="bi bi-credit-card-fill"></i>
                                            <?= htmlspecialchars($tar['tipo']) ?> terminada en ****<?= htmlspecialchars($tar['ultimos_4_digitos']) ?>
                                        </div>
                                        <small class="text-muted">Nombre: <?= htmlspecialchars($tar['nombre_tarjeta']) ?> | Expira: <?= htmlspecialchars($tar['expiracion']) ?></small>
                                    </div>
                                    <div>
                                        <form method="POST" action="<?= $base_url ?>?page=mi_perfil&seccion=pagos" onsubmit="return confirm('¿Eliminar esta tarjeta?');">
                                            <input type="hidden" name="accion" value="eliminar_tarjeta">
                                            <input type="hidden" name="id_tarjeta" value="<?= $tar['id_tarjeta'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php 
    // Ruta Footer Corregida
    include BASE_PATH . '/src/Views/components/footer.php'; 
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function limitarEntrada(elemento, regex) {
            if (elemento) {
                elemento.addEventListener('keydown', function(event) {
                    const teclasPermitidas = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
                    if (teclasPermitidas.includes(event.key)) return;
                    if (event.key.length === 1 && !regex.test(event.key)) {
                        event.preventDefault(); // "No permitir la acción"
                    }
                });
            }
        }
        
        // Regex
        const regexLetrasEspacios = /^[a-zA-Z\sñáéíóúÁÉÍÓÚ]$/u;
        const regexNumeros = /^[0-9]$/;

        // Aplicar a "Mi Perfil"
        limitarEntrada(document.getElementById('nombre'), regexLetrasEspacios);
        limitarEntrada(document.getElementById('apellido'), regexLetrasEspacios);
        limitarEntrada(document.getElementById('telefono'), regexNumeros);

        // Aplicar a "Direcciones"
        limitarEntrada(document.getElementById('ciudad'), regexLetrasEspacios);
        limitarEntrada(document.getElementById('pais'), regexLetrasEspacios);
        limitarEntrada(document.getElementById('codigo_postal'), regexNumeros);

        // Aplicar a "Métodos de Pago"
        limitarEntrada(document.getElementById('nombre_tarjeta'), regexLetrasEspacios);
        limitarEntrada(document.getElementById('numero_tarjeta'), regexNumeros);
        
        // Un filtro especial para la fecha MM/AA
        const expInput = document.getElementById('expiracion');
        if (expInput) {
            expInput.addEventListener('keydown', function(event) {
                const teclasPermitidas = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
                if (teclasPermitidas.includes(event.key)) return;

                // Permitir solo números y el slash "/"
                if (event.key.length === 1 && !/^[0-9\/]$/.test(event.key)) {
                    event.preventDefault();
                }
            });
        }
    </script>
</body>
</html>