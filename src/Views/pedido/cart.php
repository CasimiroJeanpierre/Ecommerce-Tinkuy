<?php
// src/Views/cart.php
// Esta Vista espera que $carrito_items y $total_general ya existan
// (porque el Controlador 'public/index.php' ya los creó).

// --- DEFINICIÓN DE RUTAS ---
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : "/Ecommerce-Tinkuy";
$base_url = defined('PUBLIC_URL') ? PUBLIC_URL : ($project_root . "/public");
$controller_url = defined('BASE_URL') ? BASE_URL : ($base_url . "/index.php"); // El "Cerebro"
$pagina_actual = 'carrito'; // Para el navbar
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mi Carrito | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $base_url ?>/css/style.css">
</head>

<body class="d-flex flex-column min-vh-100">

    <?php
    include BASE_PATH . '/src/Views/components/navbar.php';
    ?>

    <main class="flex-grow-1">
        <div class="container my-5">
            <h1 class="text-center mb-4">Mi Carrito de Compras</h1>

            <?php if (isset($_SESSION['mensaje_exito'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['mensaje_exito']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['mensaje_exito']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['mensaje_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['mensaje_error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['mensaje_error']); ?>
            <?php endif; ?>

            <?php if (empty($carrito_items)): ?>
                <div class="text-center p-5 border rounded shadow-sm bg-white">
                    <i class="bi bi-cart-x" style="font-size: 4rem; color: #6c757d;"></i>
                    <h3 class="mt-3">Tu carrito está vacío</h3>
                    <p class="text-muted">Parece que aún no has agregado nada.</p>
                    <a href="<?= $controller_url ?>?page=products" class="btn btn-primary mt-2">
                        <i class="bi bi-shop me-1"></i> Ir a la tienda
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="table-responsive shadow-sm bg-white rounded">
                            <form action="<?= $controller_url ?>?page=actualizar_carrito" method="POST">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col" colspan="2">Producto</th>
                                            <th scope="col">Precio</th>
                                            <th scope="col">Cantidad</th>
                                            <th scope="col">Subtotal</th>
                                            <th scope="col"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($carrito_items as $item): ?>
                                            <tr>
                                                <td style="width: 100px;">
                                                    <img src="<?= $project_root ?>/public/img/productos/<?= htmlspecialchars($item['imagen_final']) ?>"
                                                        alt="<?= htmlspecialchars($item['nombre']) ?>" class="img-fluid rounded"
                                                        style="width: 80px; height: 80px; object-fit: cover;">
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        Talla: <?= htmlspecialchars($item['talla']) ?> |
                                                        Color: <?= htmlspecialchars($item['color']) ?>
                                                    </small>
                                                </td>
                                                <td>S/ <?= number_format($item['precio'], 2) ?></td>
                                                <td style="width: 100px;">
                                                    <input type="number" name="cantidades[<?= $item['id_variante'] ?>]"
                                                        class="form-control form-control-sm text-center input-cantidad-auto"
                                                        value="<?= htmlspecialchars($item['cantidad']) ?>" min="1"
                                                        max="<?= $item['stock'] ?>">
                                                    <?php if ($item['stock'] <= 5): ?>
                                                        <small class="text-danger d-block text-center mt-1"
                                                            style="font-size: 0.75rem;">
                                                            ¡Quedan <?= $item['stock'] ?>!
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><strong>S/ <?= number_format($item['subtotal'], 2) ?></strong></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-eliminar"
                                                        title="Eliminar" data-bs-toggle="modal"
                                                        data-bs-target="#confirmDeleteModal"
                                                        data-id-variante="<?= $item['id_variante'] ?>"
                                                        data-nombre-producto="<?= htmlspecialchars($item['nombre']) ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </form>
                        </div>
                        <div class="text-end mt-3">
                            <a href="<?= $controller_url ?>?page=vaciar_carrito" class="btn btn-outline-danger"
                                onclick="return confirm('¿Estás seguro de vaciar todo el carrito?');">
                                <i class="bi bi-trash"></i> Vaciar Carrito Completo
                            </a>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0 sticky-top" style="top: 80px;">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Resumen del Pedido</h5>

                                <!-- Formulario de Cupón de Descuento -->
                                <form action="<?= $controller_url ?>?page=aplicar_cupon" method="POST" class="mb-3">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="cupon"
                                            placeholder="Código (ej: TINKUY10)" required
                                            value="<?= htmlspecialchars($_SESSION['cupon']['codigo'] ?? '') ?>">
                                        <button class="btn btn-outline-primary" type="submit">Aplicar</button>
                                    </div>
                                    <?php if (isset($_SESSION['cupon'])): ?>
                                        <small class="text-success d-block mt-1"><i class="bi bi-check-circle"></i> Cupón
                                            aplicado (<?= ($_SESSION['cupon']['descuento'] * 100) ?>% desc.)</small>
                                    <?php endif; ?>
                                </form>

                                <hr>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal</span>
                                    <strong>S/ <?= number_format($total_general, 2) ?></strong>
                                </div>
                                <?php if ($descuento_aplicado > 0): ?>
                                    <div class="d-flex justify-content-between mb-2 text-danger">
                                        <span>Descuento (<?= ($_SESSION['cupon']['descuento'] * 100) ?>%)</span>
                                        <strong>- S/ <?= number_format($descuento_aplicado, 2) ?></strong>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Envío</span>
                                    <span class="text-success">GRATIS</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between h4 mb-4">
                                    <strong>Total</strong>
                                    <strong>S/
                                        <?= number_format(isset($total_con_descuento) && $total_con_descuento > 0 ? $total_con_descuento : $total_general, 2) ?></strong>
                                </div>
                                <div class="d-grid">
                                    <a href="<?= $controller_url ?>?page=pago" class="btn btn-success btn-lg">
                                        <i class="bi bi-shield-check me-2"></i> Proceder al Pago
                                    </a>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="<?= $controller_url ?>?page=products"
                                        class="link-secondary text-decoration-none">
                                        <i class="bi bi-arrow-left-short"></i> Seguir comprando
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php
    include BASE_PATH . '/src/Views/components/footer.php';
    ?>

    <!-- 🗑 Modal de confirmación de eliminación -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLabel">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ¿Seguro que deseas eliminar <strong id="modalProductName">...</strong> del carrito?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a id="btnConfirmDelete" href="#" class="btn btn-danger">Eliminar</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ⏳ Overlay de carga al actualizar cantidad -->
    <div id="loading-overlay" class="d-none position-fixed w-100 h-100 top-0 start-0 bg-white bg-opacity-75 align-items-center justify-content-center" style="z-index: 9999;">
        <div class="text-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status"></div>
            <div class="mt-2 fw-bold text-primary">Actualizando carrito...</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var deleteModal = document.getElementById('confirmDeleteModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function (event) {
                    var button = event.relatedTarget;
                    var idVariante = button.getAttribute('data-id-variante');
                    var nombreProducto = button.getAttribute('data-nombre-producto');

                    var deleteUrl = "<?= $controller_url ?>?page=eliminar_carrito&id=" + idVariante;

                    var modalProductName = deleteModal.querySelector('#modalProductName');
                    var modalConfirmButton = deleteModal.querySelector('#btnConfirmDelete');

                    modalProductName.textContent = nombreProducto;
                    modalConfirmButton.setAttribute('href', deleteUrl);
                });
            }

            // Actualización automática al cambiar la cantidad
            const inputsCantidad = document.querySelectorAll('.input-cantidad-auto');
            const formActualizar = document.querySelector('form[action*="actualizar_carrito"]');
            const loadingOverlay = document.getElementById('loading-overlay');

            inputsCantidad.forEach(input => {
                input.addEventListener('change', function () {
                    loadingOverlay.classList.remove('d-none');
                    loadingOverlay.classList.add('d-flex');
                    formActualizar.submit();
                });
            });
        });
    </script>
</body>

</html>