<?php
/**
 * Vista del perfil del vendedor autenticado.
 * Permite al vendedor consultar y actualizar sus datos personales (nombre, apellidos,
 * teléfono, descripción de la tienda y foto de perfil) y ver sus métricas de rendimiento.
 *
 * Variables esperadas (provistas por VendedorController::actualizarPerfil()):
 *   $datos_perfil        (array)  - Datos del perfil: nombres, apellidos, email,
 *                                    telefono, descripcion_tienda, foto_perfil
 *   $estadisticas        (array)  - Métricas del vendedor: total_ventas, total_productos,
 *                                    ingresos_totales, productos_activos
 *   $nombre_vendedor_sesion (string) - Nombre de usuario del vendedor (desde $_SESSION)
 *   $base_url            (string) - URL base del proyecto
 *   $mensaje_error       (string) - Error de validación o fallo al guardar cambios
 *   $mensaje_exito       (string) - Confirmación tras actualizar el perfil correctamente
 */
// Vista MVC para Mi Perfil Vendedor
// Variables esperadas: $base_url, $mensaje_error, $mensaje_exito, $datos_perfil
// Si la vista se abre directamente, garantizamos las mínimas variables de sesión
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$base_url = $base_url ?? (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php');
$mensaje_error = $mensaje_error ?? ($_SESSION['mensaje_error'] ?? '');
$mensaje_exito = $mensaje_exito ?? ($_SESSION['mensaje_exito'] ?? '');
$datos_perfil = $datos_perfil ?? (isset($_SESSION['perfil']) ? $_SESSION['perfil'] : ['email' => '', 'nombre' => '', 'apellido' => '', 'telefono' => '']);
$nombre_vendedor_sesion = $nombre_vendedor_sesion ?? ($_SESSION['usuario'] ?? '');
// Limpiar mensajes de sesión si vinieron de ahí
unset($_SESSION['mensaje_error'], $_SESSION['mensaje_exito']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mi Perfil Vendedor | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
</head>
<body class="bg-light">

    <?php 
    $pagina_actual = 'perfil';
    require BASE_PATH . '/src/Views/components/navbar_vendedor.php';
    ?>

    <main class="container my-5">
        <h2 class="mb-4">Mi Perfil de Vendedor</h2>

        <div class="row">
            <!-- Menú Lateral Simple -->
            <div class="col-md-3">
                <div class="list-group shadow-sm mb-4">
                    <a href="<?= $base_url ?>?page=mi_perfil_vendedor" class="list-group-item list-group-item-action active" aria-current="true">
                        <i class="bi bi-person-circle me-2"></i> Datos Personales
                    </a>
                    <a href="<?= $base_url ?>?page=vendedor_envios" class="list-group-item list-group-item-action">
                        <i class="bi bi-truck me-2"></i> Gestión de Envíos
                    </a>
                    <a href="<?= $base_url ?>?page=vendedor_ventas" class="list-group-item list-group-item-action">
                        <i class="bi bi-graph-up me-2"></i> Historial de Ventas
                    </a>
                </div>
            </div>

            <!-- Contenido Principal -->
            <div class="col-md-9">
                <?php if (!empty($mensaje_error)): ?>
                    <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($mensaje_error) ?></div>
                <?php endif; ?>
                <?php if (!empty($mensaje_exito)): ?>
                    <div class="alert alert-success shadow-sm"><?= htmlspecialchars($mensaje_exito) ?></div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header">
                        Editar Datos Personales
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?= $base_url ?>?page=mi_perfil_vendedor">
                            <input type="hidden" name="accion" value="actualizar_perfil_vendedor">

                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Electrónico (Usuario)</label>
                                <input type="email" class="form-control" id="email"
                                       value="<?= htmlspecialchars($datos_perfil['email']) ?>" disabled readonly>
                                <div class="form-text">Este es tu correo de inicio de sesión y no se puede cambiar aquí.</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label">Nombre(s) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nombre" name="nombre"
                                           value="<?= htmlspecialchars($datos_perfil['nombre']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="apellido" class="form-label">Apellido(s) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="apellido" name="apellido"
                                           value="<?= htmlspecialchars($datos_perfil['apellido']) ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="telefono" class="form-label">Teléfono de Contacto (Opcional)</label>
                                <input type="tel" class="form-control" id="telefono" name="telefono"
                                       value="<?= htmlspecialchars($datos_perfil['telefono']) ?>"
                                       placeholder="+51 987654321" pattern="^\+?[0-9\s-]{7,15}$"
                                       title="Formato de teléfono no válido. Use solo números y opcionalmente '+', espacios o guiones.">
                                <div class="form-text">Ej: +51 987654321 o 987654321 (Máx 15 caracteres)</div>
                            </div>

                            <hr>
                            <h5 class="mt-4">Datos de tu Tienda (Opcional)</h5>
                            <div class="mb-3">
                                <label for="nombre_tienda" class="form-label">Nombre de tu Tienda</label>
                                <input type="text" class="form-control" id="nombre_tienda" name="nombre_tienda">
                            </div>
                             <div class="mb-3">
                                <label for="descripcion_tienda" class="form-label">Descripción Breve</label>
                                <textarea class="form-control" id="descripcion_tienda" name="descripcion_tienda" rows="3"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i> Guardar Cambios</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php
        // Incluir footer si lo tienes estandarizado
        // include '../../assets/component/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</body>
</html>