<?php
/**
 * Vista de detalle de un producto individual.
 * Muestra la galería de imágenes, descripción, selector de variante (talla/color),
 * precio actualizado por variante vía JS y botón de añadir al carrito.
 * Si el producto no existe o no está activo, muestra un mensaje de error.
 *
 * Variables esperadas (preparadas por public/index.php case 'producto'):
 *   $producto    (array|null) - Datos del producto:
 *                                id_producto, nombre_producto, descripcion, imagen_principal,
 *                                imagenes_adicionales (array de rutas), nombre_categoria, estado
 *   $variantes   (array)      - Variantes activas: id_variante, talla, color, precio, stock
 *                                (vacío si producto inactivo o sin variantes con stock)
 *   $base_url    (string)     - URL base del proyecto
 */
// src/Views/producto.php
// Esta Vista espera que todas las variables ($producto, $variantes, etc.)
// ya existan (definidas por el Controlador public/index.php).

// --- DEFINICIÓN DE RUTAS (¡La parte que faltaba!) ---
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : "/Ecommerce-Tinkuy";
$base_url = defined('PUBLIC_URL') ? PUBLIC_URL : ($project_root . "/public");
$controller_url = defined('BASE_URL') ? BASE_URL : ($base_url . "/index.php"); // El "Cerebro"
$pagina_actual = 'producto'; // Para el navbar
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($producto['nombre_producto']); ?> | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-Ay26V7L8bsJTsX9Sxclnvsn+hkdiwRnrjZJXqKmkIDobPgIIWBOVguEcQQLDuhfN" crossorigin="anonymous" />
    <style>
        #producto-imagen-principal {
            max-height: 500px;
            object-fit: contain;
            transition: opacity 0.3s ease-in-out;
        }

        select:disabled {
            background-color: #e9ecef;
            opacity: 0.7;
        }

        /* Estilos para la galería tipo e-commerce (MercadoLibre/Amazon) */
        .thumbnail-gallery {
            -ms-overflow-style: none;
            /* IE y Edge */
            scrollbar-width: none;
            /* Firefox */
        }

        .thumbnail-gallery::-webkit-scrollbar {
            display: none;
            /* Chrome, Safari y Opera */
        }

        .thumb-item {
            width: 60px;
            height: 60px;
            min-width: 60px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 6px;
            transition: all 0.2s;
            opacity: 0.6;
        }

        .thumb-item:hover,
        .thumb-item.active-thumb {
            border-color: #0d6efd;
            opacity: 1;
        }

        #btn-prev-img:hover,
        #btn-next-img:hover {
            opacity: 1 !important;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">

    <?php include BASE_PATH . '/src/Views/components/navbar.php'; ?>

    <div class="container my-5 flex-grow-1">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="d-flex flex-column flex-md-row gap-3">
                    <!-- Galería de miniaturas (Izquierda en PC, Abajo en móvil) -->
                    <div class="d-flex flex-row flex-md-column gap-2 order-2 order-md-1 thumbnail-gallery"
                        style="overflow-x: auto; max-height: 500px;">
                        <img src="<?= $ruta_base_principal . $imagen_mostrada_inicial ?>"
                            class="thumb-item active-thumb shadow-sm">
                        <?php if (!empty($producto['imagenes_adicionales'])): ?>
                            <?php foreach ($producto['imagenes_adicionales'] as $img_adic): ?>
                                <img src="<?= $ruta_base_principal . htmlspecialchars(trim($img_adic)) ?>"
                                    class="thumb-item shadow-sm">
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Imagen Principal (Derecha en PC, Arriba en móvil) -->
                    <div class="flex-grow-1 order-1 order-md-2 text-center bg-white rounded shadow-sm d-flex align-items-center justify-content-center p-2 position-relative"
                        style="border: 1px solid #dee2e6;">

                        <?php if (!empty($producto['imagenes_adicionales'])): ?>
                            <button id="btn-prev-img"
                                class="btn btn-dark position-absolute start-0 top-50 translate-middle-y ms-2 rounded-circle"
                                style="opacity: 0.6; z-index: 10;">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                        <?php endif; ?>

                        <img id="producto-imagen-principal"
                            src="<?= $ruta_base_principal . $imagen_mostrada_inicial; ?>" class="img-fluid rounded"
                            style="width: 100%; max-height: 500px; object-fit: contain;"
                            alt="Imagen principal de <?= htmlspecialchars($producto['nombre_producto']); ?>">

                        <?php if (!empty($producto['imagenes_adicionales'])): ?>
                            <button id="btn-next-img"
                                class="btn btn-dark position-absolute end-0 top-50 translate-middle-y me-2 rounded-circle"
                                style="opacity: 0.6; z-index: 10;">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= $controller_url ?>?page=products">Productos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?= htmlspecialchars($producto['nombre_categoria']); ?>
                        </li>
                    </ol>
                </nav>

                <h2 class="mb-3"><?= htmlspecialchars($producto['nombre_producto']); ?></h2>
                <p class="lead text-muted mb-3"><?= nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
                <h3 id="precio-producto" class="my-3 text-primary fw-bold">
                    <?php if (empty($variantes)): ?>
                        <span class="text-danger">Agotado</span>
                    <?php else: ?>
                        Selecciona Talla/Color
                    <?php endif; ?>
                </h3>
                <small id="stock-producto" class="text-muted d-block mb-3"></small>
                <hr>

                <form id="form-carrito" action="<?= $controller_url ?>?page=agregar_carrito" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::generarCSRF()) ?>">
                    <input type="hidden" name="id_producto" value="<?= $id_producto; ?>">

                    <div class="mb-3">
                        <label for="select-variante" class="form-label"><strong>Selecciona Talla y
                                Color:</strong></label>
                        <select class="form-select form-select-lg" id="select-variante" name="id_variante" required
                            <?= empty($variantes) ? 'disabled' : '' ?>>
                            <option value="" selected disabled>
                                <?= empty($variantes) ? 'Producto Agotado' : 'Elige una opción...' ?>
                            </option>
                            <?php foreach ($variantes as $variante): ?>
                                <option value="<?= $variante['id_variante']; ?>"
                                    data-precio="<?= htmlspecialchars($variante['precio']); ?>"
                                    data-stock="<?= htmlspecialchars($variante['stock']); ?>">
                                    <?= htmlspecialchars($variante['talla'] . ' - ' . $variante['color']); ?>
                                    (<?= $variante['stock'] ?> disp.)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="cantidad" class="form-label"><strong>Cantidad:</strong></label>
                        <input type="number" class="form-control" id="cantidad" name="cantidad" value="1" min="1"
                            max="1" required disabled>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg" id="btn-agregar-carrito" disabled>
                            <i class="bi bi-cart-plus me-2"></i> Agregar al Carrito
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include BASE_PATH . '/src/Views/components/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

    <script>
        // Pasamos las variantes (definidas por el Controlador) a JavaScript
        const variantes = <?php echo $variantes_json; ?>;
        const selectVariante = document.getElementById('select-variante');
        const precioElemento = document.getElementById('precio-producto');
        const stockElemento = document.getElementById('stock-producto');
        const cantidadInput = document.getElementById('cantidad');
        const botonAgregar = document.getElementById('btn-agregar-carrito');
        const imagenPrincipalEl = document.getElementById('producto-imagen-principal');

        selectVariante.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const idSeleccionado = selectedOption.value;
            const precioSeleccionado = selectedOption.getAttribute('data-precio');
            const stockSeleccionado = selectedOption.getAttribute('data-stock');

            if (idSeleccionado && precioSeleccionado && stockSeleccionado) {
                precioElemento.textContent = 'S/ ' + parseFloat(precioSeleccionado).toFixed(2);
                stockElemento.textContent = 'Stock disponible: ' + stockSeleccionado;

                cantidadInput.max = stockSeleccionado;
                if (parseInt(cantidadInput.value) > parseInt(stockSeleccionado)) {
                    cantidadInput.value = 1;
                }
                cantidadInput.disabled = false;
                botonAgregar.disabled = false;

            } else {
                precioElemento.textContent = 'Selecciona Talla/Color';
                stockElemento.textContent = '';
                cantidadInput.value = 1;
                cantidadInput.max = 1;
                cantidadInput.disabled = true;
                botonAgregar.disabled = true;
            }
        });

        cantidadInput.addEventListener('change', function () {
            const maxStock = parseInt(this.max);
            if (parseInt(this.value) > maxStock) {
                this.value = maxStock;
            }
            if (parseInt(this.value) < 1) {
                this.value = 1;
            }
        });

        // Lógica de la galería de imágenes (Solo Click) y Navegación
        const thumbnails = document.querySelectorAll('.thumb-item');
        const btnPrev = document.getElementById('btn-prev-img');
        const btnNext = document.getElementById('btn-next-img');
        let currentImageIndex = 0;

        function updateMainImage(index) {
            // Si se pasa del límite, volver al inicio/final
            if (index < 0) index = thumbnails.length - 1;
            if (index >= thumbnails.length) index = 0;
            currentImageIndex = index;

            const selectedThumb = thumbnails[currentImageIndex];
            imagenPrincipalEl.src = selectedThumb.src;

            thumbnails.forEach(t => t.classList.remove('active-thumb'));
            selectedThumb.classList.add('active-thumb');

            // Auto-scroll para miniaturas ocultas
            selectedThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
        }

        thumbnails.forEach((thumb, index) => {
            thumb.addEventListener('click', () => updateMainImage(index));
        });

        if (btnPrev) {
            btnPrev.addEventListener('click', () => updateMainImage(currentImageIndex - 1));
        }

        if (btnNext) {
            btnNext.addEventListener('click', () => updateMainImage(currentImageIndex + 1));
        }
    </script>
</body>

</html>