<?php
/**
 * Vista de edición de producto del panel de vendedor.
 * Permite al vendedor modificar nombre, descripción, categoría, imagen principal,
 * imágenes adicionales y variantes de sus propios productos.
 * El vendedor solo puede editar productos que le pertenecen (verificado en el controlador).
 *
 * Variables esperadas (provistas por VendedorController::editarProducto()):
 *   $producto              (array)  - Datos actuales del producto:
 *                                      id_producto, nombre_producto, descripcion,
 *                                      precio_base, id_categoria, imagen_principal, estado
 *   $categorias_jerarquia  (array)  - Árbol de categorías (padre → [hijos]) para selector anidado
 *   $variantes             (array)  - Variantes activas: [id_variante, talla, color, precio, stock]
 *   $imagenes              (array)  - Imágenes adicionales: [id_imagen, ruta_imagen]
 *   $nombre_vendedor       (string) - Nombre del vendedor autenticado (desde $_SESSION)
 *   $base_url              (string) - URL base del proyecto
 *   $mensaje_error         (string) - Mensaje de error tras operación fallida
 *   $mensaje_exito         (string) - Mensaje de confirmación tras operación exitosa
 */
// Vista de edición de producto (MVC)
// Espera que el controlador provea: $producto, $categorias_jerarquia, $variantes,
// $base_url, $mensaje_error, $mensaje_exito

$producto = $producto ?? null;
$categorias_jerarquia = $categorias_jerarquia ?? [];
$variantes = $variantes ?? [];
$base_url = $base_url ?? (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php');
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : '/Ecommerce-Tinkuy';
$mensaje_error = $mensaje_error ?? '';
$mensaje_exito = $mensaje_exito ?? '';
$id_producto = $producto['id_producto'] ?? 0;

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Panel Vendedor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $project_root ?>/public/css/style.css">
    <style>
        .variante-inactiva {
            opacity: 0.6;
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <?php
    if (session_status() !== PHP_SESSION_ACTIVE)
        session_start();
    $base_url = $base_url ?? (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php');
    $pagina_actual = 'productos';
    require BASE_PATH . '/src/Views/components/navbar_vendedor.php';
    ?>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Editando: <?= htmlspecialchars($producto['nombre_producto']) ?></h2>
                <p class="text-muted mb-0">
                    <small>
                        <i class="bi bi-tag"></i> <?= htmlspecialchars($producto['nombre_categoria']) ?> |
                        <i class="bi bi-box"></i> <?= count($variantes) ?> variantes
                    </small>
                </p>
            </div>
            <a href="<?= $base_url ?>?page=vendedor_productos" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Productos
            </a>
        </div>

        <?php if (!empty($mensaje_error) || isset($_SESSION['mensaje_error'])): ?>
            <div class="card border-danger mb-4">
                <div class="card-header bg-danger text-white">
                    <i class="bi bi-exclamation-triangle-fill"></i> Error
                </div>
                <div class="card-body text-danger">
                    <?php if (!empty($mensaje_error)): ?>
                        <p class="mb-0"><?= htmlspecialchars($mensaje_error) ?></p>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['mensaje_error'])): ?>
                        <p class="mb-0"><?= htmlspecialchars($_SESSION['mensaje_error']) ?></p>
                        <?php unset($_SESSION['mensaje_error']); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje_exito) || isset($_SESSION['mensaje_exito'])): ?>
            <div class="card border-success mb-4">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-check-circle-fill"></i> Éxito
                </div>
                <div class="card-body text-success">
                    <?php if (!empty($mensaje_exito)): ?>
                        <p class="mb-0"><?= htmlspecialchars($mensaje_exito) ?></p>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['mensaje_exito'])): ?>
                        <p class="mb-0"><?= htmlspecialchars($_SESSION['mensaje_exito']) ?></p>
                        <?php unset($_SESSION['mensaje_exito']); ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">Información General</div>
                    <div class="card-body">

                        <!-- Formularios ocultos de eliminación (fuera del formulario principal) -->
                        <?php if (!empty($imagenes_adicionales)): ?>
                            <?php foreach ($imagenes_adicionales as $img): ?>
                                <form id="delete-img-<?= $img['id_imagen'] ?>"
                                    action="<?= $base_url ?>?page=vendedor_editar_producto&id=<?= $id_producto ?>" method="POST"
                                    style="display: none;">
                                    <input type="hidden" name="accion" value="eliminar_imagen">
                                    <input type="hidden" name="id_imagen" value="<?= $img['id_imagen'] ?>">
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <form action="<?= $base_url ?>?page=vendedor_editar_producto&id=<?= $id_producto ?>"
                            method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="accion" value="actualizar_producto">
                            <div class="mb-3"><label for="nombre_producto" class="form-label">Nombre</label><input
                                    type="text" class="form-control" id="nombre_producto" name="nombre_producto"
                                    value="<?= htmlspecialchars($producto['nombre_producto']) ?>" required></div>
                            <div class="mb-3"><label for="descripcion" class="form-label">Descripción</label><textarea
                                    class="form-control" id="descripcion" name="descripcion"
                                    rows="4"><?= htmlspecialchars($producto['descripcion']) ?></textarea></div>
                            <div class="mb-3">
                                <label for="id_categoria" class="form-label">Categoría</label>
                                <select class="form-select" id="id_categoria" name="id_categoria" required>
                                    <option value="" disabled>-- Selecciona --</option>
                                    <?php /* Lógica PHP para optgroup (sin cambios) */
                                    $current_group = null;
                                    foreach ($categorias_jerarquia as $cat) {
                                        if ($cat['id_categoria_padre'] === null) {
                                            if ($current_group !== null)
                                                echo '</optgroup>';
                                            echo '<optgroup label="' . htmlspecialchars($cat['nombre_categoria']) . '">';
                                            $current_group = $cat['id_categoria'];
                                        } elseif ($cat['id_categoria_padre'] === $current_group) {
                                            $selected = ($cat['id_categoria'] == $producto['id_categoria']) ? 'selected' : '';
                                            echo '<option value="' . $cat['id_categoria'] . '" ' . $selected . '>&nbsp;&nbsp;&nbsp;' . htmlspecialchars($cat['nombre_categoria']) . '</option>';
                                        } elseif ($cat['id_categoria_padre'] !== null && $cat['id_categoria_padre'] !== $current_group) {
                                            if ($current_group !== null) {
                                                echo '</optgroup>';
                                                $current_group = null;
                                            }
                                            $selected = ($cat['id_categoria'] == $producto['id_categoria']) ? 'selected' : '';
                                            echo '<option value="' . $cat['id_categoria'] . '" ' . $selected . '>' . htmlspecialchars($cat['nombre_padre'] ?? '??') . ' / ' . htmlspecialchars($cat['nombre_categoria']) . ' (Sub)</option>';
                                        }
                                    }
                                    if ($current_group !== null)
                                        echo '</optgroup>';
                                    ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Cambiar Imagen Principal (Opcional)</label>
                                    <input class="form-control mb-3" type="file" name="imagen_principal"
                                        accept="image/png, image/jpeg, image/webp" title="Imagen Principal">

                                    <label class="form-label">Añadir Imágenes Adicionales (Máximo 10 en total)</label>
                                    <input type="file" class="form-control mb-2" name="imagenes_adicionales[]"
                                        accept="image/png, image/jpeg, image/webp, image/gif" multiple>
                                    <small class="text-muted mb-3 d-block">Puedes mantener presionado CTRL para subir
                                        varias a la vez.</small>

                                    <label class="form-label mt-3">Galería Actual:</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if (!empty($imagenes_adicionales)): ?>
                                            <?php foreach ($imagenes_adicionales as $img): ?>
                                                <div class="position-relative">
                                                    <img src="<?= $project_root ?>/public/img/productos/<?= htmlspecialchars(trim($img['ruta_imagen'])) ?>"
                                                        class="img-thumbnail"
                                                        style="width: 80px; height: 80px; object-fit: cover;">
                                                    <button type="submit" form="delete-img-<?= $img['id_imagen'] ?>"
                                                        class="btn btn-danger btn-sm p-0 position-absolute top-0 end-0 m-1"
                                                        style="width: 20px; height: 20px; line-height: 1;" title="Eliminar"
                                                        onclick="return confirm('¿Eliminar esta imagen de la galería?')"><i
                                                            class="bi bi-x"></i></button>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted small">No hay imágenes adicionales.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar Cambios Generales</button>
                        </form>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header">Agregar Nueva Variante</div>
                    <div class="card-body">
                        <form action="<?= $base_url ?>?page=vendedor_editar_producto&id=<?= $id_producto ?>"
                            method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="accion" value="agregar_variante">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="producto_unico_edit"
                                        onchange="toggleVariantesEdit()">
                                    <label class="form-check-label" for="producto_unico_edit">
                                        Este producto es de talla y color único (Ej: Artesanía, Instrumento)
                                    </label>
                                </div>
                            </div>
                            <div class="row">
                                <div id="campo_talla_edit" class="col-md-6 mb-3"><label for="talla"
                                        class="form-label">Talla</label><input type="text" class="form-control"
                                        id="talla_edit" name="talla" placeholder="Ej: M"><small class="text-muted">Vacío
                                        = Única</small></div>
                                <div id="campo_color_edit" class="col-md-6 mb-3"><label for="color"
                                        class="form-label">Color</label><input type="text" class="form-control"
                                        id="color_edit" name="color" placeholder="Ej: Rojo"><small
                                        class="text-muted">Vacío = Estándar</small></div>
                                <div class="col-md-6 mb-3"><label for="precio" class="form-label">Precio (S/) <span
                                            class="text-danger">*</span></label><input type="number" step="0.01"
                                        class="form-control" id="precio" name="precio" placeholder="150.00" required>
                                </div>
                                <div class="col-md-6 mb-3"><label for="stock" class="form-label">Stock <span
                                            class="text-danger">*</span></label><input type="number"
                                        class="form-control" id="stock" name="stock" placeholder="10" required></div>
                            </div>
                            <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle"></i> Agregar
                                Variante</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Variantes Existentes</h5>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary active" data-bs-toggle="button"
                                id="mostrarActivas">
                                <i class="bi bi-eye"></i> Activas
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="button"
                                id="mostrarInactivas">
                                <i class="bi bi-eye-slash"></i> Inactivas
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form action="<?= $base_url ?>?page=vendedor_editar_producto&id=<?= $id_producto ?>"
                            method="POST">
                            <input type="hidden" name="accion" value="actualizar_variantes">
                            <?php if (empty($variantes)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-box text-muted" style="font-size: 2rem;"></i>
                                    <p class="text-muted mt-2">No hay variantes agregadas todavía.</p>
                                </div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php
                                    $variantes_activas = array_filter($variantes, function ($v) {
                                        return $v['estado'] === 'activo';
                                    });
                                    $variantes_inactivas = array_filter($variantes, function ($v) {
                                        return $v['estado'] === 'inactivo';
                                    });
                                    ?>

                                    <!-- Variantes Activas -->
                                    <?php foreach ($variantes_activas as $v): ?>
                                        <div class="col-md-6 variante-card activa">
                                            <div class="card h-100 border-success">
                                                <div
                                                    class="card-header bg-success bg-opacity-10 d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0">
                                                        <?= htmlspecialchars($v['talla']) ?> /
                                                        <?= htmlspecialchars($v['color']) ?>
                                                    </h6>
                                                    <span class="badge bg-success">Activa</span>
                                                </div>
                                                <div class="card-body">
                                                    <div class="d-flex mb-3">
                                                        <div>
                                                            <div class="mb-2">
                                                                <label class="form-label small mb-1">Precio (S/)</label>
                                                                <input type="number" step="0.01"
                                                                    class="form-control form-control-sm"
                                                                    name="variantes[<?= $v['id_variante'] ?>][precio]"
                                                                    value="<?= htmlspecialchars($v['precio']) ?>">
                                                            </div>
                                                            <div>
                                                                <label class="form-label small mb-1">Stock</label>
                                                                <input type="number" class="form-control form-control-sm"
                                                                    name="variantes[<?= $v['id_variante'] ?>][stock]"
                                                                    value="<?= htmlspecialchars($v['stock']) ?>">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="variantes[<?= $v['id_variante'] ?>][desactivar]"
                                                            id="desactivar_<?= $v['id_variante'] ?>">
                                                        <label class="form-check-label small text-danger"
                                                            for="desactivar_<?= $v['id_variante'] ?>">
                                                            <i class="bi bi-eye-slash"></i> Desactivar variante
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Variantes Inactivas -->
                                    <?php foreach ($variantes_inactivas as $v): ?>
                                        <div class="col-md-6 variante-card inactiva" style="display: none;">
                                            <div class="card h-100 border-secondary">
                                                <div
                                                    class="card-header bg-secondary bg-opacity-10 d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0">
                                                        <?= htmlspecialchars($v['talla']) ?> /
                                                        <?= htmlspecialchars($v['color']) ?>
                                                    </h6>
                                                    <span class="badge bg-secondary">Inactiva</span>
                                                </div>
                                                <div class="card-body">
                                                    <div class="d-flex mb-3">
                                                        <div class="flex-grow-1">
                                                            <p class="mb-1 small">Último precio: S/
                                                                <?= htmlspecialchars($v['precio']) ?>
                                                            </p>
                                                            <p class="mb-0 small">Último stock:
                                                                <?= htmlspecialchars($v['stock']) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <a href="<?= $base_url ?>?page=vendedor_cambiar_estado_variante&id_producto=<?= $id_producto ?>&id_variante=<?= $v['id_variante'] ?>&estado=activo"
                                                        class="btn btn-success btn-sm w-100"
                                                        onclick="return confirm('¿Reactivar esta variante? Podrás actualizar su precio y stock después de reactivarla.')">
                                                        <i class="bi bi-arrow-clockwise"></i> Reactivar Variante
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <hr class="my-4">

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1"
                                        onclick="return confirm('¿Guardar los cambios en las variantes activas?')">
                                        <i class="bi bi-save"></i> Guardar Cambios
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const mostrarActivas = document.getElementById('mostrarActivas');
                            const mostrarInactivas = document.getElementById('mostrarInactivas');
                            const variantesActivas = document.querySelectorAll('.variante-card.activa');
                            const variantesInactivas = document.querySelectorAll('.variante-card.inactiva');

                            mostrarActivas.addEventListener('click', function () {
                                variantesActivas.forEach(v => v.style.display = this.classList.contains('active') ? 'block' : 'none');
                            });

                            mostrarInactivas.addEventListener('click', function () {
                                variantesInactivas.forEach(v => v.style.display = this.classList.contains('active') ? 'block' : 'none');
                            });

                            // Mostrar activas por defecto
                            mostrarActivas.click();
                        });

                        function toggleVariantesEdit() {
                            const checkbox = document.getElementById('producto_unico_edit');
                            const campoTalla = document.getElementById('campo_talla_edit');
                            const campoColor = document.getElementById('campo_color_edit');
                            const inputTalla = document.getElementById('talla_edit');
                            const inputColor = document.getElementById('color_edit');

                            if (checkbox.checked) {
                                // Ocultar campos y establecer valores por defecto
                                campoTalla.style.display = 'none';
                                campoColor.style.display = 'none';
                                inputTalla.value = '';
                                inputColor.value = '';
                                inputTalla.disabled = true;
                                inputColor.disabled = true;
                            } else {
                                // Mostrar campos y habilitar
                                campoTalla.style.display = 'block';
                                campoColor.style.display = 'block';
                                inputTalla.disabled = false;
                                inputColor.disabled = false;
                            }
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</body>

</html>