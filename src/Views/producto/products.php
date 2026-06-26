<?php
/**
 * Vista del catálogo de productos con filtros, búsqueda y ordenamiento.
 * Muestra el grid de productos disponibles con sidebar de categorías y
 * barra de búsqueda/ordenamiento en la parte superior.
 *
 * Variables esperadas (preparadas por Producto::getProductosFiltrados() + router):
 *   $productos_listados   (array)    - Productos filtrados:
 *                                       id_producto, nombre_producto, imagen_principal,
 *                                       precio_min, precio_max, nombre_categoria
 *   $categorias           (array)    - Árbol o lista de categorías para el filtro lateral
 *   $total_productos      (int)      - Cantidad de productos encontrados con los filtros activos
 *   $id_categoria_filtro  (int|null) - ID de categoría seleccionada (null = todas)
 *   $termino_busqueda     (string)   - Texto de búsqueda ingresado por el usuario
 *   $orden                (string)   - Criterio activo: 'nombre_asc'|'precio_asc'|'precio_desc'
 *   $filtros_activos      (bool)     - true si hay categoría o búsqueda activas (para botón "Limpiar")
 *   $base_url             (string)   - URL base del proyecto
 */
// src/Views/products.php
// Esta Vista espera que $productos_listados, $categorias, etc. ya existan.

// --- DEFINICIÓN DE RUTAS ---
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : "/Ecommerce-Tinkuy";
$base_url = defined('PUBLIC_URL') ? PUBLIC_URL : ($project_root . "/public");
$controller_url = defined('BASE_URL') ? BASE_URL : ($base_url . "/index.php"); // El "Cerebro"
$pagina_actual = 'productos'; // Para el navbar
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuestros Productos | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" integrity="sha384-Ay26V7L8bsJTsX9Sxclnvsn+hkdiwRnrjZJXqKmkIDobPgIIWBOVguEcQQLDuhfN" crossorigin="anonymous">
    
    <link rel="stylesheet" href="<?= $base_url ?>/css/style.css">
    
     <style>
         .product-card-img { height: 200px; object-fit: cover; }
         .product-card-title a { color: inherit; text-decoration: none; }
         .product-card-title a:hover { color: #0d6efd; }
     </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    
    <?php 
    // Ruta Navbar Corregida
    include BASE_PATH . '/src/Views/components/navbar.php'; 
    ?>

    <main class="container my-4 flex-grow-1">
        <h1 class="mb-3">Nuestros Productos</h1>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form action="<?= $controller_url ?>" method="GET" id="filter-form">
                    <input type="hidden" name="page" value="products"> <div class="row g-2 align-items-end">
                        <div class="col-lg-4 col-md-6">
                            <label for="buscar" class="form-label small">Buscar por nombre</label>
                            <input type="text" class="form-control form-control-sm" id="buscar" name="buscar" placeholder="Ej: Chompa Alpaca" value="<?= htmlspecialchars($termino_busqueda) ?>">
                        </div>
                        <div class="col-lg-3 col-md-6">
                             <label for="categoria" class="form-label small">Filtrar por categoría</label>
                             <select name="categoria" id="categoria" class="form-select form-select-sm">
                                 <option value="" <?= ($id_categoria_filtro === null) ? 'selected' : '' ?>>-- Todas las categorías --</option>
                                 <?php
                                    // Lógica de Vista para armar el <select>
                                    $categorias_padre = array_filter($categorias, fn($cat) => is_null($cat['id_categoria_padre']));
                                    $categorias_hijas = array_filter($categorias, fn($cat) => !is_null($cat['id_categoria_padre']));
                                    
                                    foreach ($categorias_padre as $cat_padre):
                                        $selected_padre = ($id_categoria_filtro == $cat_padre['id_categoria']) ? 'selected' : '';
                                        echo '<optgroup label="' . htmlspecialchars($cat_padre['nombre_categoria']) . '">';
                                        echo '<option value="' . $cat_padre['id_categoria'] . '" ' . $selected_padre . '>' . htmlspecialchars($cat_padre['nombre_categoria']) . ' (Todo)</option>';
                                        
                                        foreach ($categorias_hijas as $cat_hija):
                                            if ($cat_hija['id_categoria_padre'] == $cat_padre['id_categoria']):
                                                $selected_hija = ($id_categoria_filtro == $cat_hija['id_categoria']) ? 'selected' : '';
                                                echo '<option value="' . $cat_hija['id_categoria'] . '" ' . $selected_hija . '>&nbsp;&nbsp;&nbsp;' . htmlspecialchars($cat_hija['nombre_categoria']) . '</option>';
                                            endif;
                                        endforeach;
                                        echo '</optgroup>';
                                    endforeach;
                                 ?>
                             </select>
                        </div>
                        <div class="col-lg-3 col-md-6">
                             <label for="orden" class="form-label small">Ordenar por</label>
                             <select name="orden" id="orden" class="form-select form-select-sm">
                                 <option value="nombre_asc" <?= ($orden == 'nombre_asc') ? 'selected' : '' ?>>Nombre (A-Z)</option>
                                 <option value="precio_asc" <?= ($orden == 'precio_asc') ? 'selected' : '' ?>>Precio (Menor)</option>
                                 <option value="precio_desc" <?= ($orden == 'precio_desc') ? 'selected' : '' ?>>Precio (Mayor)</option>
                             </select>
                        </div>
                        <div class="col-lg-2 col-md-6 d-flex align-items-end">
                             <button class="btn btn-primary btn-sm w-100 me-1" type="submit">
                                 <i class="bi bi-funnel-fill"></i> Filtrar
                             </button>
                             <?php if ($filtros_activos): ?>
                                 <a href="<?= $controller_url ?>?page=products" class="btn btn-outline-secondary btn-sm w-auto" title="Quitar filtros">
                                     <i class="bi bi-x-lg"></i>
                                 </a>
                             <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($productos_listados)): ?>
            <div class="alert alert-info text-center shadow-sm">
                 <i class="bi bi-info-circle me-2"></i>No se encontraron productos activos que coincidan.
                 <?php if ($filtros_activos): ?>
                     Intenta <a href="<?= $controller_url ?>?page=products" class="alert-link">restablecer los filtros</a>.
                 <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-muted text-end mb-2"><small>Mostrando <?= count($productos_listados) ?> de <?= $total_productos ?> productos activos</small></p>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php foreach ($productos_listados as $prod): ?>
                    <div class="col d-flex align-items-stretch">
                        <div class="card h-100 shadow-sm product-card border-0 overflow-hidden">
                            
                            <a href="<?= $controller_url ?>?page=producto&id=<?= $prod['id_producto'] ?>">
                                <img src="<?= $project_root ?>/public/img/productos/<?= htmlspecialchars($prod['imagen_principal'] ?: 'default.png') ?>" class="card-img-top product-card-img" alt="<?= htmlspecialchars($prod['nombre_producto']) ?>">
                            </a>
                            
                            <div class="card-body d-flex flex-column pb-2">
                                <h5 class="card-title product-card-title mb-1 fs-6">
                                    <a href="<?= $controller_url ?>?page=producto&id=<?= $prod['id_producto'] ?>" class="text-decoration-none text-dark stretched-link">
                                        <?= htmlspecialchars(mb_strimwidth($prod['nombre_producto'], 0, 50, "...")) ?>
                                    </a>
                                </h5>
                                <p class="card-text small text-muted mb-2"><?= htmlspecialchars($prod['nombre_categoria']) ?></p>
                                <p class="card-text fw-bold text-primary mt-auto mb-0 product-card-price fs-5">
                                    <?php /* Lógica de precio (Esto es de Vista, está bien aquí) */
                                        if (!is_null($prod['min_precio']) && !is_null($prod['max_precio']) && $prod['min_precio'] != $prod['max_precio']) { echo 'S/ ' . number_format($prod['min_precio'], 2) . ' - S/ ' . number_format($prod['max_precio'], 2); }
                                        elseif (!is_null($prod['min_precio'])) { echo 'S/ ' . number_format($prod['min_precio'], 2); }
                                        else { echo '<span class="text-muted small">Consultar</span>'; }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php endif; ?>
    </main>

    <?php 
    // Ruta Footer Corregida
    include BASE_PATH . '/src/Views/components/footer.php'; 
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    </body>
</html>