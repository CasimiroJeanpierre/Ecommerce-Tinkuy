<?php
/**
 * Controlador del dashboard del vendedor (procedural).
 * Verifica la sesión, ejecuta las consultas de métricas del vendedor
 * (envíos pendientes, total productos, stock total, ventas, gráfico semanal,
 * top 5 más vendidos y productos sin ventas) y expone las variables para la vista.
 *
 * Variables que expone al scope de la vista:
 *   $id_vendedor       (int)    - ID del vendedor en sesión
 *   $nombre_vendedor   (string) - Usuario del vendedor
 *   $envios_pendientes (int)    - Artículos pendientes de envío
 *   $total_productos   (int)    - Productos activos del vendedor
 *   $total_stock       (int)    - Unidades totales en stock activo
 *   $total_ventas      (int)    - Artículos vendidos (estados enviado/entregado)
 *   $json_labels       (string) - JSON con etiquetas de días para el gráfico
 *   $json_data         (string) - JSON con totales diarios para el gráfico
 *   $top_5_productos   (mysqli_result) - Cursor con los 5 productos más vendidos
 *   $productos_sin_ventas (mysqli_result) - Cursor con productos activos sin ventas
 */
// Controlador sencillo para el dashboard del vendedor
if (session_status() === PHP_SESSION_NONE)
    session_start();

require_once __DIR__ . '/../Core/db.php';
$base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . $base_url . '?page=login');
    exit;
}
if ($_SESSION['rol'] !== 'vendedor') {
    $_SESSION['mensaje_error'] = "Acceso denegado: Esta sección es exclusiva para vendedores.";
    header('Location: ' . $base_url . '?page=index');
    exit;
}
$id_vendedor = $_SESSION['usuario_id'];
$nombre_vendedor = $_SESSION['usuario'];

// --- Extraer mensajes flash ---
$mensaje_error = $_SESSION['mensaje_error'] ?? null;
$mensaje_exito = $_SESSION['mensaje_exito'] ?? null;
unset($_SESSION['mensaje_error'], $_SESSION['mensaje_exito']);

// --- Consultas SQL (SIN CAMBIOS) ---
// 1. Envíos pendientes
$stmt_pendientes = $conn->prepare("SELECT COUNT(dp.id_detalle) AS total FROM detalle_pedido dp JOIN variantes_producto vp ON dp.id_variante = vp.id_variante JOIN productos p ON vp.id_producto = p.id_producto WHERE p.id_vendedor = ? AND dp.id_estado_detalle = 2");
$stmt_pendientes->bind_param("i", $id_vendedor);
$stmt_pendientes->execute();
$envios_pendientes = $stmt_pendientes->get_result()->fetch_assoc()['total'];
$stmt_pendientes->close();

// 2. Total productos
$stmt_productos = $conn->prepare("SELECT COUNT(*) AS total FROM productos WHERE id_vendedor = ? AND estado = 'activo'"); // Solo activos
$stmt_productos->bind_param("i", $id_vendedor);
$stmt_productos->execute();
$total_productos = $stmt_productos->get_result()->fetch_assoc()['total'];
$stmt_productos->close();

// 3. Stock total
$stmt_stock = $conn->prepare("SELECT SUM(vp.stock) AS total FROM variantes_producto vp JOIN productos p ON vp.id_producto = p.id_producto WHERE p.id_vendedor = ? AND p.estado = 'activo' AND vp.estado = 'activo'"); // Solo activos
$stmt_stock->bind_param("i", $id_vendedor);
$stmt_stock->execute();
$total_stock = $stmt_stock->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_stock->close();

// 4. Ventas totales (Artículos)
$stmt_ventas = $conn->prepare("SELECT COUNT(dp.id_detalle) AS total FROM detalle_pedido dp JOIN variantes_producto vp ON dp.id_variante = vp.id_variante JOIN productos p ON vp.id_producto = p.id_producto WHERE p.id_vendedor = ? AND dp.id_estado_detalle IN (3, 4)"); // Enviados o Entregados
$stmt_ventas->bind_param("i", $id_vendedor);
$stmt_ventas->execute();
$total_ventas = $stmt_ventas->get_result()->fetch_assoc()['total'];
$stmt_ventas->close();

// --- Lógica Gráfico (SIN CAMBIOS) ---
$query_grafico = $conn->prepare("SELECT DATE(pe.fecha_pedido) AS dia, SUM(dp.cantidad * dp.precio_historico) AS total_dia FROM detalle_pedido dp JOIN pedidos pe ON dp.id_pedido = pe.id_pedido JOIN variantes_producto vp ON dp.id_variante = vp.id_variante JOIN productos p ON vp.id_producto = p.id_producto WHERE p.id_vendedor = ? AND dp.id_estado_detalle IN (2, 3, 4) AND pe.fecha_pedido >= CURDATE() - INTERVAL 6 DAY GROUP BY dia ORDER BY dia ASC");
$query_grafico->bind_param("i", $id_vendedor);
$query_grafico->execute();
$resultado_grafico = $query_grafico->get_result();
$ventas_raw = [];
while ($fila = $resultado_grafico->fetch_assoc()) {
    $ventas_raw[$fila['dia']] = $fila['total_dia'];
}
$labels_grafico = [];
$data_grafico = [];
for ($i = 6; $i >= 0; $i--) {
    $dia_actual = date('Y-m-d', strtotime("-$i days"));
    $labels_grafico[] = date('d/m', strtotime($dia_actual));
    $data_grafico[] = $ventas_raw[$dia_actual] ?? 0;
}
$json_labels = json_encode($labels_grafico);
$json_data = json_encode($data_grafico);
$query_grafico->close();

// --- Lógica Top 5 Más Vendidos (SIN CAMBIOS) ---
$query_top_5 = $conn->prepare("SELECT p.nombre_producto, SUM(dp.cantidad) AS total_vendido FROM detalle_pedido dp JOIN variantes_producto vp ON dp.id_variante = vp.id_variante JOIN productos p ON vp.id_producto = p.id_producto WHERE p.id_vendedor = ? AND dp.id_estado_detalle IN (3, 4) GROUP BY p.id_producto, p.nombre_producto ORDER BY total_vendido DESC LIMIT 5");
$query_top_5->bind_param("i", $id_vendedor);
$query_top_5->execute();
$top_5_productos = $query_top_5->get_result();
// No cerramos aquí, lo usamos en el HTML

// --- Lógica Productos Sin Ventas (SIN CAMBIOS) ---
$query_sin_ventas = $conn->prepare("SELECT p.nombre_producto FROM productos p LEFT JOIN (SELECT DISTINCT vp.id_producto FROM detalle_pedido dp JOIN variantes_producto vp ON dp.id_variante = vp.id_variante WHERE dp.id_estado_detalle IN (2, 3, 4)) AS vendidos ON p.id_producto = vendidos.id_producto WHERE p.id_vendedor = ? AND p.estado = 'activo' AND vendidos.id_producto IS NULL LIMIT 5"); // Solo activos
$query_sin_ventas->bind_param("i", $id_vendedor);
$query_sin_ventas->execute();
$productos_sin_ventas = $query_sin_ventas->get_result();
// No cerramos aquí, lo usamos en el HTML

// No cerramos la conexión aquí: la cierra el punto de entrada (public/index.php)
?>