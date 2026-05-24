<?php
session_start();
include 'db.php';

$carrito = $_SESSION['carrito'] ?? [];
?>

<main class="container my-5">
  <h1 class="mb-4 text-center text-dark">Carrito de Compras</h1>

  <?php if (empty($carrito)): ?>
    <div class="alert alert-info text-center">Tu carrito está vacío.</div>
  <?php else:
    $total = 0;
    foreach ($carrito as $id => $item):
      $subtotal = $item['precio'] * $item['cantidad'];
      $total += $subtotal;
      ?>
      <div class="row align-items-center mb-3 p-3 bg-light rounded shadow-sm">
        <div class="col-md-2 text-center">
          <img src="assets/img/<?= $item['imagen'] ?>" alt="<?= $item['nombre'] ?>" class="img-fluid rounded">
        </div>
        <div class="col-md-4">
          <h5><?= htmlspecialchars($item['nombre']) ?></h5>
          <p class="text-muted">Cantidad: <?= $item['cantidad'] ?></p>
        </div>
        <div class="col-md-2 text-center fw-bold fs-5 text-dark">S/ <?= number_format($item['precio'], 2) ?></div>
        <div class="col-md-2 text-center fw-bold text-success">Subtotal: S/ <?= number_format($subtotal, 2) ?></div>
        <div class="col-md-2 text-center">
          <a href="eliminar_carrito.php?id=<?= $id ?>" class="btn btn-danger"><i class="bi bi-trash"></i> Eliminar</a>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="text-end mt-4 border-top pt-3">
      <h4>Total: <span class="text-success">S/ <?= number_format($total, 2) ?></span></h4>
    </div>
  <?php endif; ?>
</main>