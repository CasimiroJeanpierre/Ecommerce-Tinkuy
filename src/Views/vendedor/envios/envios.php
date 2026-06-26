<?php
/**
 * Vista de gestión de envíos del vendedor autenticado.
 * Muestra los artículos de pedido con estado 'Procesando' que requieren ser enviados.
 * El vendedor selecciona la empresa de envío, introduce el número de seguimiento
 * y registra el envío, lo que cambia el estado del ítem a 'Enviado'.
 *
 * Variables esperadas (provistas por VendedorController o EnviosController):
 *   $envios_pendientes (array)  - Ítems a enviar: id_detalle, id_pedido, nombre_producto,
 *                                  talla, color, cantidad, nombre_comprador, email_comprador,
 *                                  direccion_envio, ciudad, codigo_postal
 *   $empresas_envio    (array)  - Empresas de logística: [id_empresa_envio, nombre_empresa]
 *   $nombre_vendedor   (string) - Nombre del vendedor autenticado (desde $_SESSION)
 *   $base_url          (string) - URL base del proyecto
 *   $mensaje_error     (string) - Mensaje de error tras operación fallida
 *   $mensaje_exito     (string) - Confirmación tras registrar un envío exitosamente
 */
// Verificamos que haya un usuario logueado y sea vendedor
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'vendedor') {
    header('Location: ' . $base_url . '?page=login');
    exit;
}

$nombre_vendedor = $_SESSION['usuario'];
$mensaje_error = $_SESSION['mensaje_error'] ?? "";
$mensaje_exito = $_SESSION['mensaje_exito'] ?? "";

// Limpiar mensajes después de mostrarlos
unset($_SESSION['mensaje_error'], $_SESSION['mensaje_exito']);
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : '/Ecommerce-Tinkuy';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Envíos - Panel Vendedor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $project_root ?>/public/css/style.css">
</head>

<body class="bg-light">

    <?php
    $pagina_actual = 'envios';
    require BASE_PATH . '/src/Views/components/navbar_vendedor.php';
    ?>

    <div class="container my-5">
        <h2 class="mb-4">Gestión de Envíos Pendientes</h2>
        <p class="text-muted">Aquí aparecen todos los productos que has vendido y que están pagados, listos para que los
            envíes.</p>

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
                                <th scope="col">Pedido #</th>
                                <th scope="col">Producto a Enviar</th>
                                <th scope="col">Cantidad</th>
                                <th scope="col">Cliente</th>
                                <th scope="col">Dirección de Envío</th>
                                <th scope="col">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items_pendientes)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted p-4">
                                        <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                                        <h5 class="mt-2">¡Todo al día!</h5>
                                        No tienes envíos pendientes.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items_pendientes as $item): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= $item['id_pedido'] ?></strong>
                                            <br>
                                            <small><?= date('d/m/Y', strtotime($item['fecha_pedido'])) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['nombre_producto']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                (<?= htmlspecialchars($item['talla']) ?> /
                                                <?= htmlspecialchars($item['color']) ?>)
                                                </Ssmall>
                                        </td>
                                        <td><strong><?= $item['cantidad'] ?></strong></td>
                                        <td><?= htmlspecialchars($item['cliente_nombres'] . ' ' . $item['cliente_apellidos']) ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?= htmlspecialchars($item['direccion']) ?><br>
                                                <?= htmlspecialchars($item['ciudad']) ?>, <?= htmlspecialchars($item['pais']) ?>
                                                (CP: <?= htmlspecialchars($item['codigo_postal']) ?>)
                                            </small>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-success btn-registrar-envio"
                                                data-bs-toggle="modal" data-bs-target="#modalEnvio"
                                                data-id-detalle="<?= $item['id_detalle'] ?>"
                                                data-producto-nombre="<?= htmlspecialchars($item['nombre_producto']) ?>"
                                                title="Registrar Envío">
                                                <i class="bi bi-truck"></i> Registrar Envío
                                            </button>
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


    <div class="modal fade" id="modalEnvio" tabindex="-1" aria-labelledby="modalEnvioLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEnvioLabel">Registrar Envío</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?= $base_url ?>?page=vendedor_envios" method="POST">
                    <div class="modal-body">
                        <p>Vas a registrar el envío para el ítem: <strong id="modal-producto-nombre"></strong></p>

                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::generarCSRF(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="accion" value="registrar_envio">
                        <input type="hidden" name="id_detalle_envio" id="id_detalle_envio" value="">

                        <div class="mb-3">
                            <label for="id_empresa_envio" class="form-label">Empresa de Envío</label>
                            <select class="form-select" id="id_empresa_envio" name="id_empresa_envio" required>
                                <option value="" disabled selected>-- Seleccione una empresa --</option>
                                <?php foreach ($empresas_envio as $empresa): ?>
                                    <option value="<?= $empresa['id_empresa_envio'] ?>">
                                        <?= htmlspecialchars($empresa['nombre_empresa']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="numero_seguimiento" class="form-label">Número de Seguimiento (Tracking)</label>
                            <input type="text" class="form-control" id="numero_seguimiento" name="numero_seguimiento"
                                required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Envío</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEnvio = document.getElementById('modalEnvio');

            modalEnvio.addEventListener('show.bs.modal', function (event) {
                // Botón que activó el modal
                var button = event.relatedTarget;

                // Extraer datos de los atributos data-*
                var idDetalle = button.getAttribute('data-id-detalle');
                var nombreProducto = button.getAttribute('data-producto-nombre');

                // Actualizar el input oculto en el formulario del modal
                var inputIdDetalle = modalEnvio.querySelector('#id_detalle_envio');
                inputIdDetalle.value = idDetalle;

                // Actualizar el título y el texto del modal
                var modalTitle = modalEnvio.querySelector('.modal-title');
                modalTitle.textContent = 'Registrar Envío para Ítem #' + idDetalle;

                var modalProductName = modalEnvio.querySelector('#modal-producto-nombre');
                modalProductName.textContent = nombreProducto;
            });
        });
    </script>
</body>

</html>