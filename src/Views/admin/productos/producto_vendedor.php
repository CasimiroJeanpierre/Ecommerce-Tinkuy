<?php
session_start();
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

// Obtener productos del vendedor logueado
$id_vendedor = $_SESSION['id'];
$resultado = $conn->query("SELECT * FROM productos WHERE id_vendedor = $id_vendedor ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Productos | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <h2 class="mb-4">Mis Productos</h2>

        <a href="agregar_producto.php" class="btn btn-success mb-3">+ Agregar Producto</a>

        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Precio</th>
                    <th>Imagen</th>
                    <th>Categoría</th>
                    <th>Stock</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= $row['nombre'] ?></td>
                        <td><?= $row['descripcion'] ?></td>
                        <td>S/ <?= number_format($row['precio'], 2) ?></td>
                        <td><img src="../../assets/img/<?= $row['imagen'] ?>" width="50" alt=""></td>
                        <td><?= $row['categoria'] ?></td>
                        <td><?= $row['stock'] ?></td>
                        <td>
                            <a href="editar_producto.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                            <a href="eliminar_producto.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este producto?')">Eliminar</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <a href="dashboard.php" class="btn btn-secondary mt-3">← Volver al panel</a>
    </div>
</body>
</html>
