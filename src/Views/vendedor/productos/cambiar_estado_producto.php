<?php
session_start();
include '../admin/db.php'; // Asegúrate que la ruta a db.php sea correcta

// --- Seguridad ---
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'vendedor') {
    header('Location: ../../login.php');
    exit;
}

$id_vendedor = $_SESSION['usuario_id'];
$id_producto = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$nuevo_estado = filter_input(INPUT_GET, 'estado', FILTER_SANITIZE_SPECIAL_CHARS); // 'activo' o 'inactivo'

// Validaciones básicas
if (!$id_producto || ($nuevo_estado !== 'activo' && $nuevo_estado !== 'inactivo')) {
    // Si falta el ID o el estado es inválido, redirigimos con error (o mostramos mensaje)
    $_SESSION['mensaje_error'] = "Datos inválidos para cambiar estado.";
    header('Location: productos.php');
    exit;
}

// --- Verificación de Permiso (MUY IMPORTANTE) ---
// Antes de cambiar el estado, asegurémonos que este producto le pertenece al vendedor logueado
$stmt_check = $conn->prepare("SELECT id_producto FROM productos WHERE id_producto = ? AND id_vendedor = ?");
$stmt_check->bind_param("ii", $id_producto, $id_vendedor);
$stmt_check->execute();
$resultado_check = $stmt_check->get_result();

if ($resultado_check->num_rows === 0) {
    // El producto no existe o no le pertenece a este vendedor
    $_SESSION['mensaje_error'] = "No tienes permiso para modificar este producto.";
    header('Location: productos.php');
    exit;
}

// --- Actualización del Estado ---
$stmt_update = $conn->prepare("UPDATE productos SET estado = ? WHERE id_producto = ?");
$stmt_update->bind_param("si", $nuevo_estado, $id_producto);

if ($stmt_update->execute()) {
    $_SESSION['mensaje_exito'] = "Estado del producto #$id_producto actualizado a '$nuevo_estado'.";
} else {
    $_SESSION['mensaje_error'] = "Error al actualizar el estado del producto.";
}

$stmt_update->close();
$stmt_check->close();
$conn->close();

// Redirigimos de vuelta a la lista de productos
header('Location: productos.php');
exit;
?>