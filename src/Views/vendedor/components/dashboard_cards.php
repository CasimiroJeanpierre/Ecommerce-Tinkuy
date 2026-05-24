<?php
// Vista parcial para las tarjetas KPI del dashboard vendedor
?>
<div class="row g-4 mb-4">
    <!-- Envíos Pendientes -->
    <div class="col-12 col-md-6 col-lg-3">
         <div class="card h-100">
              <div class="card-body">
                  <h6 class="card-subtitle mb-2 text-muted"><i class="bi bi-truck me-2"></i>Envíos Pendientes</h6>
                  <h2 class="card-title mb-3"><?= $envios_pendientes ?></h2>
                  <a href="<?= $base_url ?>?page=vendedor_envios" class="btn btn-sm <?= ($envios_pendientes > 0) ? 'btn-warning' : 'btn-outline-secondary disabled'; ?> w-100">
                      <?= ($envios_pendientes > 0) ? 'Gestionar Envíos' : 'Sin Envíos Pendientes' ?>
                  </a>
              </div>
         </div>
    </div>

    <!-- Total de Productos -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
             <div class="card-body">
                 <h6 class="card-subtitle mb-2 text-muted"><i class="bi bi-box-seam me-2"></i>Total Productos</h6>
                 <h2 class="card-title mb-3"><?= $total_productos ?></h2>
                 <a href="<?= $base_url ?>?page=vendedor_productos" class="btn btn-sm btn-outline-success w-100">Gestionar Productos</a>
             </div>
        </div>
    </div>

    <!-- Stock Total -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
             <div class="card-body">
                 <h6 class="card-subtitle mb-2 text-muted"><i class="bi bi-boxes me-2"></i>Stock Total</h6>
                 <h2 class="card-title mb-3"><?= number_format($total_stock, 0) ?></h2>
                 <div class="text-muted small">Total de unidades en inventario</div>
             </div>
        </div>
    </div>

    <!-- Ventas Totales -->
    <div class="col-12 col-md-6 col-lg-3">
        <div class="card h-100">
             <div class="card-body">
                 <h6 class="card-subtitle mb-2 text-muted"><i class="bi bi-cart-check me-2"></i>Ventas (30 días)</h6>
                 <h2 class="card-title mb-3"><?= $total_ventas ?></h2>
                 <div class="text-muted small">Total de artículos vendidos</div>
             </div>
        </div>
    </div>
</div>