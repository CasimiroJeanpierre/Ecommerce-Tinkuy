<?php
/**
 * Vista del dashboard principal del panel del vendedor.
 * Muestra métricas propias del vendedor: productos, stock, ventas y envíos pendientes,
 * junto con un gráfico de ventas semanal y el ranking de los 5 productos más vendidos.
 *
 * Variables esperadas (preparadas por VendedorDashboardController o VendedorController):
 *   $id_vendedor          (int)           - ID del vendedor autenticado (desde $_SESSION)
 *   $nombre_vendedor      (string)        - Nombre del vendedor autenticado
 *   $envios_pendientes    (int)           - Artículos en estado 'Procesando' (pendientes de envío)
 *   $total_productos      (int)           - Productos activos del vendedor
 *   $total_stock          (int)           - Unidades totales en stock activo sumadas por variante
 *   $total_ventas         (int)           - Artículos vendidos (estados enviado/entregado)
 *   $json_labels          (string)        - JSON array con etiquetas de días (últimos 7 días)
 *   $json_data            (string)        - JSON array con ingresos diarios para Chart.js
 *   $top_5_productos      (mysqli_result) - Los 5 productos más vendidos del vendedor
 *   $productos_sin_ventas (mysqli_result) - Productos activos del vendedor sin ninguna venta
 *   $base_url             (string)        - URL base del proyecto
 */
// Vista del dashboard del vendedor (MVC)
// Si el controlador no ha preparado las variables (por ejemplo cuando la vista
// se abre directamente), lo incluimos como fallback.
if (!isset($envios_pendientes)) {
    require_once __DIR__ . '/../../Controllers/VendedorDashboardController.php';
}

// Aseguramos base_url para construir enlaces. Si index.php ya lo definió, lo reutilizamos.
$base_url = $base_url ?? (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Vendedor | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script> 

    <style>
        body {
            background-color: #f0f2f5; /* Un gris más suave */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Fuente más moderna */
        }
        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
        }
        .card-kpi {
            border: none; /* Quitamos borde */
            border-radius: 0.8rem; /* Más redondeado */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); /* Sombra suave para efecto flotante */
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; /* Transición suave */
            background-color: #fff; /* Fondo blanco */
        }
        .card-kpi:hover {
            transform: translateY(-5px); /* Elevar al pasar el mouse */
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12); /* Sombra más pronunciada */
        }
        .card-kpi .card-body i {
            /* Colores más vibrantes para los iconos */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 3.5rem; /* Iconos un poco más grandes */
        }
         /* Icono específico para Envíos Pendientes */
        .card-kpi.envios-pendientes .card-body i {
             background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%); /* Naranja a rojo */
             -webkit-background-clip: text;
             -webkit-text-fill-color: transparent;
            color: #333;
        }
        .card-kpi .card-text {
            color: #6c757d;
            font-size: 0.95rem;
        }
        .card-kpi .card-footer {
             background-color: transparent !important; /* Footer transparente */
             border-top: none; /* Sin línea arriba del footer */
             padding-top: 0;
        }
        .chart-container {
            position: relative;
            /* height: 300px; /* Altura fija más pequeña para el gráfico */
            max-height: 350px; /* Máxima altura */
            width: 100%;
            margin: auto;
        }
        .card-lista {
             border: none;
             border-radius: 0.8rem;
             box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
             background-color: #fff;
        }
        .list-group-item {
            border-left: none;
            border-right: none;
            padding: 0.8rem 1rem;
        }
        .list-group-item:first-child { border-top: none; border-top-left-radius: 0.8rem; border-top-right-radius: 0.8rem;}
        .list-group-item:last-child { border-bottom: none; border-bottom-left-radius: 0.8rem; border-bottom-right-radius: 0.8rem;}

        /* Navbar más moderna */
        .navbar {
             box-shadow: 0 2px 4px rgba(0,0,0,.08);
             padding-top: 0.8rem;
             padding-bottom: 0.8rem;
        }
        .navbar .nav-link {
            font-weight: 500;
            color: rgba(255,255,255,0.8);
            transition: color 0.2s;
        }
        .navbar .nav-link:hover, .navbar .nav-link.active {
            color: #fff;
        }
    </style>
    </head>
<body>

    <?php 
    $pagina_actual = 'dashboard';
    require BASE_PATH . '/src/Views/components/navbar_vendedor.php';
    ?>

    <div class="container mt-4 mb-5">
        <h1 class="mb-4 display-6">Bienvenido, <?= htmlspecialchars($nombre_vendedor) ?> 👋</h1>

        <?php if ($envios_pendientes > 0): ?>
            <div class="alert alert-warning d-flex align-items-center shadow-sm border-0 mb-4" role="alert" style="border-radius: 0.8rem;">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-warning"></i>
                <div>
                    <strong>¡Acción Requerida!</strong> Tienes <strong><?= $envios_pendientes ?></strong> envío(s) pendiente(s).
                    <a href="<?= $base_url ?>?page=vendedor_envios" class="alert-link fw-bold">Gestionar ahora <i class="bi bi-arrow-right-short"></i></a>.
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success d-flex align-items-center shadow-sm border-0 mb-4" role="alert" style="border-radius: 0.8rem;">
                 <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                 <div>
                     <strong>¡Todo al día!</strong> No tienes envíos pendientes. ¡Sigue así!
                 </div>
             </div>
        <?php endif; ?>

        <div class="row g-4">
             <div class="col-md-6 col-lg-3">
                 <div class="card card-kpi envios-pendientes h-100">
                     <div class="card-body text-center">
                         <i class="bi bi-clock-history"></i>
                         <h3 class="card-title"><?= $envios_pendientes ?></h3>
                         <p class="card-text text-muted">Envíos Pendientes</p>
                     </div>
                     <div class="card-footer">
                          <a href="<?= $base_url ?>?page=vendedor_envios" class="btn btn-sm <?= ($envios_pendientes > 0) ? 'btn-warning' : 'btn-outline-secondary disabled'; ?> w-100">
                             <?= ($envios_pendientes > 0) ? 'Gestionar' : 'Ninguno'; ?>
                          </a>
                     </div>
                 </div>
             </div>
             <div class="col-md-6 col-lg-3">
                 <div class="card card-kpi h-100">
                     <div class="card-body text-center">
                         <i class="bi bi-check2-circle"></i>
                         <h3 class="card-title"><?= $total_ventas ?></h3>
                         <p class="card-text text-muted">Artículos Enviados/Entregados</p>
                     </div>
                      <div class="card-footer">
                           <a href="<?= $base_url ?>?page=vendedor_ventas" class="btn btn-sm btn-outline-primary w-100">Ver Historial</a>
                      </div>
                 </div>
             </div>
             <div class="col-md-6 col-lg-3">
                 <div class="card card-kpi h-100">
                     <div class="card-body text-center">
                         <i class="bi bi-box-seam"></i>
                         <h3 class="card-title"><?= $total_productos ?></h3>
                         <p class="card-text text-muted">Productos Activos</p>
                     </div>
                      <div class="card-footer">
                           <a href="<?= $base_url ?>?page=vendedor_productos" class="btn btn-sm btn-outline-success w-100">Gestionar Productos</a>
                      </div>
                 </div>
             </div>
             <div class="col-md-6 col-lg-3">
                 <div class="card card-kpi h-100">
                     <div class="card-body text-center">
                         <i class="bi bi-archive"></i>
                         <h3 class="card-title"><?= $total_stock ?></h3>
                         <p class="card-text text-muted">Unidades en Stock</p>
                     </div>
                 </div>
             </div>
        </div>

        <div class="card card-lista mt-4">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <h5 class="mb-0 text-dark"><i class="bi bi-graph-up me-2 text-success"></i>Ventas (S/) Últimos 7 Días</h5>
            </div>
            <div class="card-body pt-2">
                <div class="chart-container">
                    <canvas id="graficoVentas"></canvas>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-md-6">
                <div class="card card-lista h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h5 class="mb-0 text-dark">
                           <i class="bi bi-star-fill me-2 text-warning"></i>Top 5 Productos Vendidos
                        </h5>
                    </div>
                    <div class="card-body pt-2">
                        <ul class="list-group list-group-flush">
                            <?php if ($top_5_productos->num_rows > 0): ?>
                                <?php $rank = 1; while($prod = $top_5_productos->fetch_assoc()): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-bold me-2"><?= $rank++ ?>.</span> <?= htmlspecialchars($prod['nombre_producto']) ?>
                                        </div>
                                        <span class="badge bg-success rounded-pill"><?= $prod['total_vendido'] ?></span>
                                    </li>
                                <?php endwhile; $top_5_productos->close(); ?>
                            <?php else: ?>
                                <li class="list-group-item text-muted text-center py-3">Aún no hay suficientes datos de ventas.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-lista h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                         <h5 class="mb-0 text-dark">
                            <i class="bi bi-search me-2 text-danger"></i>Productos Sin Ventas Recientes
                         </h5>
                    </div>
                    <div class="card-body pt-2">
                        <ul class="list-group list-group-flush">
                             <?php if ($productos_sin_ventas->num_rows > 0): ?>
                                <?php while($prod = $productos_sin_ventas->fetch_assoc()): ?>
                                    <li class="list-group-item">
                                        <?= htmlspecialchars($prod['nombre_producto']) ?>
                                    </li>
                                <?php endwhile; $productos_sin_ventas->close(); ?>
                             <?php else: ?>
                                <li class="list-group-item text-muted text-center py-3">¡Todos tus productos activos se han vendido!</li>
                             <?php endif; ?>
                        </ul>
                    </div>
                     <div class="card-footer text-center">
                         <a href="<?= $base_url ?>?page=vendedor_productos" class="btn btn-sm btn-outline-secondary">Revisar mi inventario completo</a>
                     </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script>
        const labels = <?php echo $json_labels; ?>;
        const data = <?php echo $json_data; ?>;
        const ctx = document.getElementById('graficoVentas').getContext('2d');
        const graficoVentas = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Ventas (S/)',
                    data: data,
                    backgroundColor: 'rgba(25, 135, 84, 0.1)', // Más suave
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3 // Curva un poco más pronunciada
                }]
            },
            options: {
                 maintainAspectRatio: false, // <-- IMPORTANTE para controlar altura con CSS
                 scales: {
                     y: {
                         beginAtZero: true,
                         ticks: {
                             callback: function(value) { return 'S/ ' + value.toFixed(2); }
                         }
                     }
                 },
                 plugins: {
                     legend: { display: false }, // Ocultar leyenda para más espacio
                     tooltip: {
                         callbacks: {
                             label: function(context) {
                                 let label = context.dataset.label || '';
                                 if (label) { label += ': '; }
                                 if (context.parsed.y !== null) {
                                     label += 'S/ ' + context.parsed.y.toFixed(2);
                                 }
                                 return label;
                             }
                         }
                     }
                 }
            }
        });
    </script>
</body>
</html>