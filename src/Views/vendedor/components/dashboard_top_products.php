<?php
// Vista parcial para el listado de productos top del dashboard
?>
<div class="card mb-4">
    <div class="card-header py-3">
        <h5 class="mb-0">Top Productos</h5>
    </div>
    <div class="card-body">
        <?php if ($resultado_top && $resultado_top->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-2">
                    <thead>
                        <tr>
                            <th scope="col">Producto</th>
                            <th scope="col">Stock</th>
                            <th scope="col">Ventas (30d)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($producto = $resultado_top->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($producto['nombre']) ?></td>
                                <td><?= number_format($producto['stock_total'], 0) ?></td>
                                <td><?= number_format($producto['ventas_30d'], 0) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-center">
                <a href="<?= $base_url ?>?page=vendedor_productos" class="btn btn-sm btn-outline-secondary">Revisar mi inventario completo</a>
            </div>
        <?php else: ?>
            <p class="text-muted text-center mb-0">Aún no tienes productos registrados.</p>
        <?php endif; ?>
    </div>
</div>