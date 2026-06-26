<?php
/**
 * Vista de la página principal (home) de la tienda Tinkuy.
 * Muestra el hero banner, una vitrina con los productos destacados más recientes,
 * secciones de categorías y llamadas a la acción (CTA) hacia el catálogo.
 *
 * Variables esperadas (preparadas por public/index.php case 'index'):
 *   $productos_destacados (array)  - Hasta 3 productos activos con stock:
 *                                     id_producto, nombre_producto, imagen_principal,
 *                                     precio_min, nombre_categoria
 *   $base_url             (string) - URL base del proyecto para enlaces y assets
 */
// src/Views/index.php
// Esta Vista espera que la variable $productos_destacados ya exista.

// --- DEFINICIÓN DE RUTAS ---
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : "/Ecommerce-Tinkuy";
$base_url = defined('PUBLIC_URL') ? PUBLIC_URL : ($project_root . "/public"); // Para rutas HTML (css, img, js)
$controller_url = defined('BASE_URL') ? BASE_URL : ($base_url . "/index.php"); // El "Cerebro"
$pagina_actual = 'index'; // Para el navbar
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tienda online de productos artesanales...">
    <meta name="keywords" content="artesanías, alpaca, productos tradicionales...">
    <title>Inicio | Tinkuy</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-Ay26V7L8bsJTsX9Sxclnvsn+hkdiwRnrjZJXqKmkIDobPgIIWBOVguEcQQLDuhfN" crossorigin="anonymous">

    <link rel="stylesheet" href="<?= $base_url ?>/css/style.css">
</head>

<body class="d-flex flex-column min-vh-100">

    <?php
    // RUTA NAVBAR CORREGIDA
    include BASE_PATH . '/src/Views/components/navbar.php';
    ?>

    <div class="flex-grow-1">

        <!-- Contenedor para Mensajes de Alerta -->
        <div class="container mt-3">
            <?php if (!empty($mensaje_error)): ?>
                <div class="alert alert-danger text-center shadow-sm alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($mensaje_error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($mensaje_exito)): ?>
                <div class="alert alert-success text-center shadow-sm alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($mensaje_exito) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>

        <div id="mainCarousel" class="carousel slide mt-4" data-bs-ride="carousel">
            <div class="carousel-inner">

                <div class="carousel-item active">
                    <img src="<?= $project_root ?>/public/img/banner1.png" class="d-block w-100" alt="Banner 1"
                        loading="lazy">
                </div>
                <div class="carousel-item">
                    <img src="<?= $project_root ?>/public/img/banner3.png" class="d-block w-100" alt="Banner 2"
                        loading="lazy">
                </div>
                <div class="carousel-item">
                    <img src="<?= $project_root ?>/public/img/banner3.png" class="d-block w-100" alt="Banner 3"
                        loading="lazy">
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Anterior</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Siguiente</span>
            </button>
        </div>

        <section class="container my-5">
            <h2 class="text-center mb-4">Más Vendidos</h2>
            <div class="row">

                <?php if (empty($productos_destacados)): ?>
                    <div class="col-12">
                        <p class="text-center text-muted">No hay productos destacados en este momento.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($productos_destacados as $producto): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100 shadow-sm">

                                <img src="<?= $project_root ?>/public/img/productos/<?= htmlspecialchars($producto['imagen_principal']) ?>"
                                    class="card-img-top producto-img"
                                    alt="<?= htmlspecialchars($producto['nombre_producto']) ?>">

                                <div class="card-body text-center">
                                    <h5 class="card-title"><?= htmlspecialchars($producto['nombre_producto']) ?></h5>
                                    <p class="card-text"><?= htmlspecialchars(substr($producto['descripcion'], 0, 100)) ?>...
                                    </p>
                                    <p class="card-text fw-bold">
                                        Desde S/ <?= number_format($producto['precio_minimo'], 2) ?>
                                    </p>

                                    <a href="<?= $controller_url ?>?page=producto&id=<?= $producto['id_producto'] ?>"
                                        class="btn btn-dark w-100">
                                        <i class="bi bi-box-arrow-in-right"></i> Ver más
                                    </a>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </section>
    </div>

    <?php
    // RUTA FOOTER CORREGIDA
    include BASE_PATH . '/src/Views/components/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous" defer></script>

</body>

</html>