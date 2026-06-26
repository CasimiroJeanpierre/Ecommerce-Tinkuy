<?php
/**
 * Vista de gestión de cupones/códigos promocionales (panel de administración).
 * Muestra la lista de cupones con opciones para crear nuevos vía modal,
 * activar/desactivar cupones existentes y eliminarlos permanentemente.
 * El formulario de creación valida el código (solo mayúsculas y números)
 * y el porcentaje de descuento (0.01–100) antes de enviar al controlador.
 *
 * Variables esperadas (preparadas por AdminCuponesController::listar()):
 *   $cupones       (array)  - Lista de cupones: id_cupon, codigo, porcentaje_descuento,
 *                              fecha_expiracion (null = sin expiración), estado ('activo'|'inactivo')
 *   $mensaje_error (string) - Error al crear (código duplicado, formato inválido) o eliminar
 *   $mensaje_exito (string) - Confirmación tras crear, activar/desactivar o eliminar
 *   $base_url      (string) - URL base del proyecto para construir los enlaces de acción
 */
$base_url = $base_url ?? (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cupones | Administrador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= defined('PUBLIC_URL') ? PUBLIC_URL : '/Ecommerce-Tinkuy/public' ?>/css/style.css">
</head>
<body class="bg-light">
    <?php include BASE_PATH . '/src/Views/components/navbar.php'; ?>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-ticket-perforated"></i> Gestión de Códigos Promocionales</h2>
            <!-- Botón para abrir el modal de creación -->
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#crearCuponModal">
                <i class="bi bi-plus-circle"></i> Nuevo Cupón
            </button>
        </div>

        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($mensaje_error) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($mensaje_exito)): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($mensaje_exito) ?></div>
        <?php endif; ?>

        <!-- Tabla de Cupones -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Código</th>
                                <th>Descuento</th>
                                <th>Expiración</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cupones)): ?>
                                <tr><td colspan="6" class="text-center py-4">No hay cupones registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cupones as $c): ?>
                                    <?php $expirado = (!empty($c['fecha_expiracion']) && strtotime($c['fecha_expiracion']) < time()); ?>
                                    <tr class="<?= $expirado ? 'table-secondary text-muted' : '' ?>">
                                        <td><?= $c['id_cupon'] ?></td>
                                        <td><span class="badge bg-primary fs-6"><?= htmlspecialchars($c['codigo']) ?></span></td>
                                        <td><strong><?= number_format($c['porcentaje_descuento'], 0) ?>%</strong></td>
                                        <td>
                                            <?= empty($c['fecha_expiracion']) ? '<em>Sin expiración</em>' : date('d/m/Y H:i', strtotime($c['fecha_expiracion'])) ?>
                                            <?php if ($expirado): ?> <span class="badge bg-danger ms-1">Expirado</span> <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c['estado'] === 'activo'): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($c['estado'] === 'activo'): ?>
                                                <a href="<?= $base_url ?>?page=admin_estado_cupon&id=<?= $c['id_cupon'] ?>&estado=inactivo" class="btn btn-sm btn-outline-warning" title="Desactivar">
                                                    <i class="bi bi-pause-circle"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= $base_url ?>?page=admin_estado_cupon&id=<?= $c['id_cupon'] ?>&estado=activo" class="btn btn-sm btn-outline-success" title="Activar">
                                                    <i class="bi bi-play-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= $base_url ?>?page=admin_eliminar_cupon&id=<?= $c['id_cupon'] ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('¿Seguro que deseas eliminar el cupón <?= htmlspecialchars($c['codigo']) ?>?');" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </a>
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

    <!-- Modal para Crear Cupón -->
    <div class="modal fade" id="crearCuponModal" tabindex="-1" aria-labelledby="crearCuponModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= $base_url ?>?page=admin_cupones" method="POST">
                    <input type="hidden" name="accion" value="crear_cupon">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="crearCuponModalLabel">Crear Nuevo Cupón</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="codigo" class="form-label">Código del Cupón (Ej: FIESTAS20) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control text-uppercase" id="codigo" name="codigo" required pattern="[A-Za-z0-9]+" title="Solo letras y números sin espacios">
                        </div>
                        <div class="mb-3">
                            <label for="porcentaje_descuento" class="form-label">Porcentaje de Descuento (%) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="porcentaje_descuento" name="porcentaje_descuento" min="1" max="100" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="fecha_expiracion" class="form-label">Fecha de Expiración (Opcional)</label>
                            <input type="datetime-local" class="form-control" id="fecha_expiracion" name="fecha_expiracion">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Cupón</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>