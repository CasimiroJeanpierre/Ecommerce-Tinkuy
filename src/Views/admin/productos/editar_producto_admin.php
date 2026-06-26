<?php
/**
 * Vista de edición de producto (panel de administración).
 * Permite modificar todos los campos del producto: nombre, descripción, categoría,
 * imagen principal, imágenes adicionales, variantes y estado.
 * El admin puede editar cualquier producto del sistema sin restricción de vendedor.
 *
 * Variables esperadas (provistas por AdminProductosController::editar()):
 *   $producto        (array)  - Datos actuales del producto:
 *                                id_producto, nombre_producto, descripcion, precio_base,
 *                                id_categoria, imagen_principal, estado, id_vendedor
 *   $categorias      (array)  - Lista de categorías: [id_categoria, nombre_categoria]
 *   $variantes       (array)  - Variantes activas: [id_variante, talla, color, precio, stock, estado]
 *   $imagenes        (array)  - Imágenes adicionales: [id_imagen, ruta_imagen]
 *   $nombre_admin    (string) - Nombre del administrador autenticado (desde $_SESSION)
 *   $base_url        (string) - URL base del proyecto
 *   $mensaje_error   (string) - Mensaje de error tras operación fallida (subida, validación, BD)
 *   $mensaje_exito   (string) - Mensaje de confirmación tras operación exitosa
 */
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : '/Ecommerce-Tinkuy'; ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto #<?= $id_producto ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* (Tu CSS está perfecto) */
        body {
            background-color: #f8f9fa;
        }

        .sidebar {
            width: 260px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #212529;
            padding-top: 1rem;
        }

        .sidebar .nav-link {
            color: #adb5bd;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .sidebar .nav-link i {
            margin-right: 0.8rem;
        }

        .sidebar .nav-link.active {
            background-color: #dc3545;
            color: #fff;
        }

        .sidebar .nav-link:hover {
            background-color: #343a40;
            color: #fff;
        }

        .main-content {
            margin-left: 260px;
            padding: 2.5rem;
            width: calc(100% - 260px);
        }

        .variante-inactiva {
            opacity: 0.6;
            background-color: #f8f9fa;
        }

        .img-variante-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
        }

        .img-variante-placeholder {
            width: 40px;
            height: 40px;
            background-color: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: .25rem;
        }
    </style>
</head>

<body>

    <div class="sidebar d-flex flex-column p-3 text-white">
        <a href="?page=admin_dashboard"
            class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <i class="bi bi-shop-window fs-4 me-2"></i> <span class="fs-4">Admin Tinkuy</span>
        </a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li><a href="?page=admin_dashboard" class="nav-link"><i class="bi bi-grid-fill"></i> Dashboard</a></li>
            <li><a href="?page=admin_pedidos" class="nav-link"><i class="bi bi-list-check"></i> Pedidos</a></li>
            <li><a href="?page=admin_productos" class="nav-link active" aria-current="page"><i
                        class="bi bi-box-seam-fill"></i> Productos</a></li>
            <li><a href="?page=admin_usuarios" class="nav-link"><i class="bi bi-people-fill"></i> Usuarios</a></li>
            <li><a href="?page=admin_mensajes" class="nav-link"><i class="bi bi-envelope-fill"></i> Mensajes</a></li>
            <li><a href="?page=admin_reportes" class="nav-link"><i class="bi bi-graph-up"></i> Reportes</a></li>

            <li class="nav-item mt-3 pt-3 border-top">
                <a href="?page=index" class="nav-link">
                    <i class="bi bi-globe"></i> Ver Tienda
                </a>
            </li>
        </ul>
        <hr>
        <div class="dropdown user-dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
                id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle fs-4 me-2"></i> <strong><?= htmlspecialchars($nombre_admin) ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="?page=logout">Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>

    <main class="main-content">
        <h2>Editando: <?= htmlspecialchars($producto['nombre_producto']) ?></h2>

        <a href="?page=admin_productos" class="btn btn-sm btn-outline-secondary mb-3">
            <i class="bi bi-arrow-left"></i> Volver a Productos
        </a>

        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($mensaje_error) ?></div> <?php endif; ?>
        <?php if (!empty($mensaje_exito)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div> <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">Información General</div>
                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong>Estado Actual:</strong>
                                <span
                                    class="badge fs-6 bg-<?= $producto['estado'] === 'activo' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($producto['estado']) ?>
                                </span>
                            </div>
                            <form action="?page=admin_editar_producto&id=<?= $id_producto ?>" method="POST"
                                class="d-inline">
                                <input type="hidden" name="accion" value="cambiar_estado_producto">
                                <?php if ($producto['estado'] === 'activo'): ?>
                                    <input type="hidden" name="estado" value="inactivo">
                                    <button type="submit" class="btn btn-sm btn-warning"
                                        onclick="return confirm('¿Desactivar este producto? No se mostrará en la tienda.')">
                                        <i class="bi bi-eye-slash-fill"></i> Desactivar Producto
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="estado" value="activo">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="bi bi-eye-fill"></i> Activar Producto
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                        <hr>

                        <!-- Formularios ocultos de eliminación (fuera del formulario principal) -->
                        <?php if (!empty($imagenes_adicionales)): ?>
                            <?php foreach ($imagenes_adicionales as $img): ?>
                                <form id="delete-img-<?= $img['id_imagen'] ?>"
                                    action="?page=admin_editar_producto&id=<?= $id_producto ?>" method="POST"
                                    style="display: none;">
                                    <input type="hidden" name="accion" value="eliminar_imagen">
                                    <input type="hidden" name="id_imagen" value="<?= $img['id_imagen'] ?>">
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <form action="?page=admin_editar_producto&id=<?= $id_producto ?>" method="POST"
                            enctype="multipart/form-data">
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
                                    <?php // (Tu lógica de categorías está perfecta)
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
                        <form action="?page=admin_editar_producto&id=<?= $id_producto ?>" method="POST"
                            enctype="multipart/form-data">
                            <input type="hidden" name="accion" value="agregar_variante">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="producto_unico_admin"
                                        onchange="toggleVariantesAdmin()">
                                    <label class="form-check-label" for="producto_unico_admin">
                                        Este producto es de talla y color único (Ej: Artesanía, Instrumento)
                                    </label>
                                </div>
                            </div>
                            <div class="row">
                                <div id="campo_talla_admin" class="col-md-6 mb-3"><label for="talla"
                                        class="form-label">Talla</label><input type="text" class="form-control"
                                        id="talla_admin" name="talla" placeholder="Ej: M"><small
                                        class="text-muted">Vacío = Única</small></div>
                                <div id="campo_color_admin" class="col-md-6 mb-3"><label for="color"
                                        class="form-label">Color</label><input type="text" class="form-control"
                                        id="color_admin" name="color" placeholder="Ej: Rojo"><small
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
                    <div class="card-header">Variantes Existentes</div>
                    <div class="card-body">
                        <form action="?page=admin_editar_producto&id=<?= $id_producto ?>" method="POST">
                            <input type="hidden" name="accion" value="actualizar_variantes">
                            <?php if (empty($variantes)): ?>
                                <p class="text-muted">Sin variantes.</p>
                            <?php else: ?>
                                <?php foreach ($variantes as $v): ?>
                                    <div
                                        class="border rounded p-3 mb-3 <?= ($v['estado'] === 'inactivo') ? 'variante-inactiva' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 ms-1"> <?= htmlspecialchars($v['talla']) ?> /
                                                    <?= htmlspecialchars($v['color']) ?>
                                                </h6>
                                            </div>
                                            <span
                                                class="badge bg-<?= ($v['estado'] === 'inactivo') ? 'secondary' : 'success' ?>"><?= ucfirst($v['estado']) ?></span>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-6"><label class="form-label small">Precio</label><input
                                                    type="number" step="0.01" class="form-control form-control-sm"
                                                    name="variantes[<?= $v['id_variante'] ?>][precio]"
                                                    value="<?= htmlspecialchars($v['precio']) ?>" <?= ($v['estado'] === 'inactivo') ? 'disabled' : '' ?>></div>
                                            <div class="col-6"><label class="form-label small">Stock</label><input type="number"
                                                    class="form-control form-control-sm"
                                                    name="variantes[<?= $v['id_variante'] ?>][stock]"
                                                    value="<?= htmlspecialchars($v['stock']) ?>" <?= ($v['estado'] === 'inactivo') ? 'disabled' : '' ?>></div>
                                        </div>
                                        <?php if ($v['estado'] === 'activo'): ?>
                                            <div class="form-check mt-2"> <input class="form-check-input" type="checkbox"
                                                    name="variantes[<?= $v['id_variante'] ?>][desactivar]"
                                                    id="desactivar_<?= $v['id_variante'] ?>"> <label
                                                    class="form-check-label small text-warning"
                                                    for="desactivar_<?= $v['id_variante'] ?>"> Marcar para Desactivar </label>
                                            </div>
                                        <?php else: ?>
                                            <a href="?page=admin_editar_producto&id=<?= $id_producto ?>&reactivar_variante_id=<?= $v['id_variante'] ?>"
                                                class="btn btn-sm btn-outline-success mt-2"
                                                onclick="return confirm('¿Reactivar esta variante?')"> <i class="bi bi-eye"></i>
                                                Reactivar </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <button type="submit" class="btn btn-warning w-100"
                                    onclick="return confirm('¿Guardar cambios y desactivar variantes marcadas?')"> <i
                                        class="bi bi-save"></i> Actualizar Lista </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        function toggleVariantesAdmin() {
            const checkbox = document.getElementById('producto_unico_admin');
            const campoTalla = document.getElementById('campo_talla_admin');
            const campoColor = document.getElementById('campo_color_admin');
            const inputTalla = document.getElementById('talla_admin');
            const inputColor = document.getElementById('color_admin');

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
</body>

</html>