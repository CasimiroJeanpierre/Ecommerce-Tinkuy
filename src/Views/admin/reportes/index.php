<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Admin Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .sidebar { width: 260px; height: 100vh; position: fixed; top: 0; left: 0; background-color: #212529; padding-top: 1rem; }
        .sidebar .nav-link { color: #adb5bd; font-size: 1rem; margin-bottom: 0.5rem; }
        .sidebar .nav-link i { margin-right: 0.8rem; }
        .sidebar .nav-link.active { background-color: #dc3545; color: #fff; }
        .sidebar .nav-link:hover { background-color: #343a40; color: #fff; }
        .main-content { margin-left: 260px; padding: 2.5rem; width: calc(100% - 260px); }
        .user-dropdown .dropdown-toggle { color: #fff; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 1.5rem; }
        .stat-badge { font-size: 1.5rem; font-weight: bold; }
        .table-responsive { max-height: 600px; overflow-y: auto; }
        .export-buttons .btn { margin: 0 5px; }
        .alert-custom { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar d-flex flex-column p-3 text-white">
        <a href="?page=admin_dashboard" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <i class="bi bi-shop-window fs-4 me-2"></i>
            <span class="fs-4">Admin Tinkuy</span>
        </a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="?page=admin_dashboard" class="nav-link">
                    <i class="bi bi-grid-fill"></i>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="?page=admin_pedidos" class="nav-link">
                    <i class="bi bi-list-check"></i>
                    Pedidos
                </a>
            </li>
            <li>
                <a href="?page=admin_productos" class="nav-link">
                    <i class="bi bi-box-seam-fill"></i>
                    Productos
                </a>
            </li>
            <li>
                <a href="?page=admin_usuarios" class="nav-link">
                    <i class="bi bi-people-fill"></i>
                    Usuarios
                                <a href="?page=admin_mensajes" class="nav-link">
                                    <i class="bi bi-envelope-fill"></i>
                                    Mensajes
                                </a>
                            </li>
                            <li>
                </a>
            </li>
            <li>
                <a href="?page=admin_reportes" class="nav-link active" aria-current="page">
                    <i class="bi bi-graph-up"></i>
                    Reportes
                </a>
            </li>
            
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
                <i class="bi bi-person-circle fs-4 me-2"></i>
                <strong><?= htmlspecialchars($nombre_admin) ?></strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="?page=logout">Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-2">📊 Reportes Administrativos</h1>
                <p class="text-muted">Genera y exporta reportes detallados de tu negocio</p>
            </div>
        </div>

        <?php if (isset($_SESSION['mensaje_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['mensaje_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensaje_error']); ?>
        <?php endif; ?>

        <!-- Formulario de Generación de Reportes -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-sliders me-2"></i>Configurar Reporte</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="?page=admin_reportes_generar" id="formReporte">
                    <div class="row">
                        <!-- Tipo de Reporte -->
                        <div class="col-md-4 mb-3">
                            <label for="tipo_reporte" class="form-label fw-bold">
                                <i class="bi bi-file-earmark-bar-graph text-primary me-1"></i>
                                Tipo de Reporte
                            </label>
                            <select class="form-select" id="tipo_reporte" name="tipo_reporte" required>
                                <option value="ventas" <?= (isset($tipo_reporte) && $tipo_reporte === 'ventas') ? 'selected' : '' ?>>
                                    💰 Ventas
                                </option>
                                <option value="productos" <?= (isset($tipo_reporte) && $tipo_reporte === 'productos') ? 'selected' : '' ?>>
                                    📦 Productos
                                </option>
                                <option value="vendedores" <?= (isset($tipo_reporte) && $tipo_reporte === 'vendedores') ? 'selected' : '' ?>>
                                    👥 Vendedores
                                </option>
                            </select>
                            <small class="text-muted">Selecciona el tipo de análisis</small>
                        </div>

                        <!-- Fecha Inicio -->
                        <div class="col-md-3 mb-3">
                            <label for="fecha_inicio" class="form-label fw-bold">
                                <i class="bi bi-calendar-event text-success me-1"></i>
                                Fecha Inicio
                            </label>
                            <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" 
                                   value="<?= $fecha_inicio ?? date('Y-m-d', strtotime('-30 days')) ?>" required>
                        </div>

                        <!-- Fecha Fin -->
                        <div class="col-md-3 mb-3">
                            <label for="fecha_fin" class="form-label fw-bold">
                                <i class="bi bi-calendar-check text-danger me-1"></i>
                                Fecha Fin
                            </label>
                            <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" 
                                   value="<?= $fecha_fin ?? date('Y-m-d') ?>" required>
                        </div>

                        <!-- Formato de Exportación -->
                        <div class="col-md-2 mb-3">
                            <label for="formato" class="form-label fw-bold">
                                <i class="bi bi-download text-info me-1"></i>
                                Formato
                            </label>
                            <select class="form-select" id="formato" name="formato" required>
                                <option value="vista">👁️ Ver en Pantalla</option>
                                <option value="excel">📊 Excel (CSV)</option>
                                <option value="pdf">📄 PDF</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-play-circle me-2"></i>Generar Reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Información de Reportes -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-start border-primary border-4">
                    <div class="card-body">
                        <h6 class="text-primary"><i class="bi bi-currency-dollar"></i> Reporte de Ventas</h6>
                        <p class="small mb-0">Análisis de pedidos, ingresos, métodos de pago y estados.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-start border-success border-4">
                    <div class="card-body">
                        <h6 class="text-success"><i class="bi bi-box-seam"></i> Reporte de Productos</h6>
                        <p class="small mb-0">Stock, ventas por producto, categorías y rendimiento.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-start border-warning border-4">
                    <div class="card-body">
                        <h6 class="text-warning"><i class="bi bi-people"></i> Reporte de Vendedores</h6>
                        <p class="small mb-0">Ranking, ingresos, productos y tasa de entregas.</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($reporte_data) && !empty($reporte_data)): ?>
            <!-- Resultados del Reporte -->
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clipboard-data me-2"></i>
                        Resultados: Reporte de <?= ucfirst($tipo_reporte) ?>
                    </h5>
                    <div class="export-buttons">
                        <a href="?page=admin_reportes_generar&tipo_reporte=<?= $tipo_reporte ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&formato=excel" 
                           class="btn btn-light btn-sm">
                            <i class="bi bi-file-earmark-excel"></i> Excel
                        </a>
                        <a href="?page=admin_reportes_generar&tipo_reporte=<?= $tipo_reporte ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&formato=pdf" 
                           class="btn btn-light btn-sm">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Estadísticas Generales -->
                    <div class="row mb-4">
                        <?php 
                        $stats = $reporte_data['estadisticas'];
                        $iconos = [
                            'ventas' => ['total_pedidos' => 'cart-check', 'total_ingresos' => 'currency-dollar', 'total_unidades' => 'box', 'ticket_promedio' => 'receipt'],
                            'productos' => ['total_productos' => 'box-seam', 'stock_total' => 'boxes', 'unidades_vendidas' => 'bag-check', 'ingresos_totales' => 'cash-stack'],
                            'vendedores' => ['total_vendedores' => 'people', 'vendedores_activos' => 'person-check', 'ingresos_totales' => 'currency-exchange', 'productos_totales' => 'grid-3x3-gap']
                        ];
                        $tipo_icons = $iconos[$tipo_reporte] ?? [];
                        $contador = 0;
                        foreach ($stats as $key => $value):
                            if (is_array($value)) continue;
                            $icon = $tipo_icons[$key] ?? 'info-circle';
                            $label = ucfirst(str_replace('_', ' ', $key));
                            $contador++;
                            if ($contador > 4) break;
                        ?>
                            <div class="col-md-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <i class="bi bi-<?= $icon ?> text-primary" style="font-size: 2rem;"></i>
                                        <h3 class="stat-badge text-dark mt-2"><?= is_numeric($value) ? number_format($value, 2) : $value ?></h3>
                                        <p class="text-muted small mb-0"><?= $label ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Tabla de Datos -->
                    <div class="table-responsive">
                        <?php if (!empty($reporte_data['datos'])): ?>
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <?php foreach (array_keys($reporte_data['datos'][0]) as $header): ?>
                                            <th><?= ucfirst(str_replace('_', ' ', $header)) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reporte_data['datos'] as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $cell): ?>
                                                <td><?= htmlspecialchars($cell) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                No hay datos disponibles para el período seleccionado.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación de fechas
        document.getElementById('formReporte').addEventListener('submit', function(e) {
            const inicio = new Date(document.getElementById('fecha_inicio').value);
            const fin = new Date(document.getElementById('fecha_fin').value);
            
            if (inicio > fin) {
                e.preventDefault();
                alert('La fecha de inicio no puede ser mayor a la fecha fin.');
            }
        });

        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
