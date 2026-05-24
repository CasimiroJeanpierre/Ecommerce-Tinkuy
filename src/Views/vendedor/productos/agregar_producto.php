<?php
// Vista de agregar producto (MVC)
// Espera que el controlador provea: $categorias, $base_url, $mensaje_error, $mensaje_exito

$categorias = $categorias ?? [];
$base_url = $base_url ?? (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php');
$mensaje_error = $mensaje_error ?? '';
$mensaje_exito = $mensaje_exito ?? '';

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Producto - Panel Vendedor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= defined('PUBLIC_URL') ? PUBLIC_URL : '/Ecommerce-Tinkuy/public' ?>/css/style.css">
</head>

<body>
    <?php
    $pagina_actual = 'productos';
    require BASE_PATH . '/src/Views/components/navbar_vendedor.php';
    ?>
    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Agregar Nuevo Producto</h2>
            <a href="<?= $base_url ?>?page=vendedor_productos" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Productos
            </a>
        </div>

        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($mensaje_error) ?></div>
        <?php endif; ?>

        <?php if (!empty($mensaje_exito)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['mensaje_error']) ?></div>
            <?php unset($_SESSION['mensaje_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['mensaje_exito'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['mensaje_exito']) ?></div>
            <?php unset($_SESSION['mensaje_exito']); ?>
        <?php endif; ?>

        <form action="<?= $base_url ?>?page=vendedor_agregar_producto" method="POST" enctype="multipart/form-data"
            class="row g-4">
            <!-- Información General -->
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Información del Producto</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="nombre_producto" class="form-label">Nombre del Producto <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre_producto" name="nombre_producto"
                                required>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="id_categoria" class="form-label">Categoría <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="id_categoria" name="id_categoria" required>
                                <option value="">-- Selecciona una categoría --</option>
                                <?php
                                $current_group = null;
                                foreach ($categorias as $cat) {
                                    if ($cat['id_categoria_padre'] === null) {
                                        if ($current_group !== null)
                                            echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($cat['nombre_categoria']) . '">';
                                        $current_group = $cat['id_categoria'];
                                    } elseif ($cat['id_categoria_padre'] === $current_group) {
                                        echo '<option value="' . $cat['id_categoria'] . '">&nbsp;&nbsp;&nbsp;' .
                                            htmlspecialchars($cat['nombre_categoria']) . '</option>';
                                    } elseif ($cat['id_categoria_padre'] !== null && $cat['id_categoria_padre'] !== $current_group) {
                                        if ($current_group !== null) {
                                            echo '</optgroup>';
                                            $current_group = null;
                                        }
                                        echo '<option value="' . $cat['id_categoria'] . '">' .
                                            htmlspecialchars($cat['nombre_padre'] ?? '??') . ' / ' .
                                            htmlspecialchars($cat['nombre_categoria']) . ' (Sub)</option>';
                                    }
                                }
                                if ($current_group !== null)
                                    echo '</optgroup>';
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="imagen_principal" class="form-label">Imagen Principal <span
                                    class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="imagen_principal" name="imagen_principal"
                                accept="image/*" required>
                            <small class="text-muted">Formatos: JPG, PNG, GIF, WebP. Máximo 2MB.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Imágenes Adicionales (Selecciona hasta 9 imágenes a la
                                vez)</label>
                            <input type="file" class="form-control mb-2" name="imagenes_adicionales[]"
                                accept="image/png, image/jpeg, image/webp, image/gif" multiple>
                            <small class="text-muted">Puedes mantener presionado CTRL para seleccionar múltiples
                                archivos.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Variantes -->
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Variantes</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="agregarVariante()">
                            <i class="bi bi-plus-circle"></i> Agregar
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="checkProductoSinVariantes">
                                <label class="form-check-label" for="checkProductoSinVariantes">
                                    <strong>Sin variantes</strong>
                                </label>
                                <small class="text-muted d-block">Automáticamente usa Talla "Única" y Color
                                    "Estándar"</small>
                            </div>
                        </div>
                        <hr>
                        <div id="variantes-container">
                            <!-- Las variantes se agregarán aquí dinámicamente -->
                        </div>
                        <p class="text-muted small mb-0" id="no-variantes">No hay variantes agregadas</p>
                    </div>
                </div>
            </div>

            <div class="col-12 text-end">
                <hr>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Crear Producto
                </button>
            </div>
        </form>
    </div>

    <script>
        let varianteCount = 0;

        function agregarVariante() {
            varianteCount++;
            document.getElementById('no-variantes').style.display = 'none';

            const varianteHtml = `
                <div class="border rounded p-3 mb-3" id="variante-${varianteCount}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Variante #${varianteCount}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarVariante(${varianteCount})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="text" class="form-control form-control-sm talla-input" name="variantes[${varianteCount}][talla]" 
                                   placeholder="Talla (Vacío = Única)">
                        </div>
                        <div class="col-6">
                            <input type="text" class="form-control form-control-sm color-input" name="variantes[${varianteCount}][color]" 
                                   placeholder="Color (Vacío = Estándar)">
                        </div>
                        <div class="col-6">
                            <input type="number" step="0.01" class="form-control form-control-sm" name="variantes[${varianteCount}][precio]" 
                                   placeholder="Precio" required>
                        </div>
                        <div class="col-6">
                            <input type="number" class="form-control form-control-sm" name="variantes[${varianteCount}][stock]" 
                                   placeholder="Stock" required>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('variantes-container').insertAdjacentHTML('beforeend', varianteHtml);
        }

        function eliminarVariante(id) {
            const elemento = document.getElementById(`variante-${id}`);
            if (elemento) {
                elemento.remove();
                if (document.getElementById('variantes-container').children.length === 0) {
                    document.getElementById('no-variantes').style.display = 'block';
                }
            }
        }

        // Agregar una variante por defecto
        window.addEventListener('load', () => {
            agregarVariante();
        });

        // Auto-rellenar talla y color cuando se marca el checkbox
        document.getElementById('checkProductoSinVariantes').addEventListener('change', function () {
            const tallaInputs = document.querySelectorAll('.talla-input');
            const colorInputs = document.querySelectorAll('.color-input');

            if (this.checked) {
                // Checkbox marcado: auto-rellenar y bloquear campos
                tallaInputs.forEach(input => {
                    input.value = 'Única';
                    input.readOnly = true;
                    input.classList.add('bg-light');
                });
                colorInputs.forEach(input => {
                    input.value = 'Estándar';
                    input.readOnly = true;
                    input.classList.add('bg-light');
                });
            } else {
                // Checkbox desmarcado: limpiar y desbloquear campos
                tallaInputs.forEach(input => {
                    input.value = '';
                    input.readOnly = false;
                    input.classList.remove('bg-light');
                });
                colorInputs.forEach(input => {
                    input.value = '';
                    input.readOnly = false;
                    input.classList.remove('bg-light');
                });
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>





<!--<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Producto - Panel Vendedor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
</head>
<body>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Panel Vendedor</a> <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="productos.php">Mis Productos</a></li> <li class="nav-item"><a class="nav-link active" href="agregar_producto.php">Agregar Producto</a></li> </ul>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="../../logout.php">Cerrar Sesión</a></li> </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h2>Agregar Nuevo Producto</h2>
                <p>Crea el producto general y su primera variante (talla, color, precio y stock).</p>
                
                <div class="card shadow-sm">
                    <div class="card-body">
                        
                        <?php if (!empty($mensaje_error)): ?>
                            <div class="alert alert-danger alert-error-animated"><?= htmlspecialchars($mensaje_error) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($mensaje_exito)): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" novalidate>
                            
                            <h5 class="mt-3">1. Información General del Producto</h5>
                            <hr>
                            <div class="mb-3">
                                <label for="nombre_producto" class="form-label">Nombre del Producto</label>
                                <input type="text" class="form-control" id="nombre_producto" name="nombre_producto" required>
                            </div>
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="id_categoria" class="form-label">Categoría</label>
                                    <select class="form-select" id="id_categoria" name="id_categoria" required>
                                        <option value="" disabled selected>Elige una categoría...</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="imagen_principal" class="form-label">Imagen Principal</label>
                                    <input type="file" class="form-control" id="imagen_principal" name="imagen_principal" accept="image/jpeg, image/png, image/webp" required>
                                </div>
                            </div>

                            <h5 class="mt-4">2. Primera Variante (Inventario)</h5>
                            <p class="text-muted">Crearás el producto con esta primera variante. Luego podrás agregar más.</p>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="checkProductoSinVariantes">
                                    <label class="form-check-label" for="checkProductoSinVariantes">
                                        <strong>🎨 Producto sin variantes</strong> (artesanía, instrumento, objeto único)
                                    </label>
                                    <small class="text-muted d-block ms-4">Marca esta casilla si tu producto no tiene tallas ni colores específicos</small>
                                </div>
                            </div>
                            
                            <hr>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="talla" class="form-label">Talla</label>
                                    <input type="text" class="form-control" id="talla" name="talla" placeholder="Ej: M, L, Única">
                                    <small class="text-muted">Dejar vacío si es talla única.</small>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="color" class="form-label">Color</label>
                                    <input type="text" class="form-control" id="color" name="color" placeholder="Ej: Rojo, Azul, Multicolor">
                                    <small class="text-muted">Dejar vacío si es color natural/estándar.</small>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="precio" class="form-label">Precio (S/)</label>
                                    <input type="number" step="0.01" class="form-control" id="precio" name="precio" placeholder="Ej: 150.00" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="stock" class="form-label">Stock (Cantidad)</label>
                                    <input type="number" class="form-control" id="stock" name="stock" placeholder="Ej: 10" required>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-plus-circle"></i> Crear Producto y Variante
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-rellenar talla y color cuando se marca el checkbox
        document.getElementById('checkProductoSinVariantes').addEventListener('change', function() {
            const tallaInput = document.getElementById('talla');
            const colorInput = document.getElementById('color');
            
            if (this.checked) {
                tallaInput.value = 'Única';
                colorInput.value = 'Estándar';
                tallaInput.readOnly = true;
                colorInput.readOnly = true;
                tallaInput.classList.add('bg-light');
                colorInput.classList.add('bg-light');
            } else {
                tallaInput.value = '';
                colorInput.value = '';
                tallaInput.readOnly = false;
                colorInput.readOnly = false;
                tallaInput.classList.remove('bg-light');
                colorInput.classList.remove('bg-light');
            }
        });
    </script>
</body>
</html>-->