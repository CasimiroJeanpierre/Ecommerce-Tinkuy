<?php

/**
 * Modelo de ventas para el dashboard del vendedor.
 * Proporciona consultas sobre el historial de detalle_pedido
 * filtrando por vendedor y ventana de tiempo para los gráficos del dashboard.
 *
 * Métodos disponibles:
 *   getVentasVendedor($id_vendedor, $dias)   — Historial de ventas en los últimos N días
 *   getVentasPorDia($id_vendedor, $dias)     — Agrupado por día para el gráfico semanal
 *   getTopProductos($id_vendedor, $limite)   — Ranking de productos más vendidos
 *
 * Uso desde VendedorDashboardController:
 *   $venta = new Venta($conn);
 *   $ventas_semana = $venta->getVentasPorDia($_SESSION['usuario_id'], 7);
 */
class Venta {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Devuelve el historial de ventas del vendedor en los últimos N días, enriquecido
     * con el nombre del producto, nombre del cliente y nombre de la variante.
     * Los resultados están ordenados por fecha de creación descendente (más reciente primero).
     * Nota: Esta consulta referencia la columna 'fecha_creacion' en detalle_pedido; si la columna
     * no existe en el esquema actual, usar 'fecha_pedido' de la tabla 'pedidos' en su lugar.
     *
     * @param int $id_vendedor ID del vendedor autenticado
     * @param int $dias        Ventana de tiempo en días hacia atrás desde hoy (por defecto 30)
     * @return \mysqli_result  Resultado con filas de detalle_pedido enriquecidas
     */
    public function getVentasVendedor($id_vendedor, $dias = 30) {
        $stmt = $this->conn->prepare("
            SELECT v.*, p.nombre as producto, u.usuario as cliente,
                   p.precio, vp.nombre as variante
            FROM detalle_pedido v
            JOIN productos p ON v.id_producto = p.id_producto
            JOIN usuarios u ON v.id_cliente = u.id
            JOIN variantes_producto vp ON v.id_variante = vp.id_variante
            WHERE p.id_vendedor = ?
            AND v.fecha_creacion >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
            ORDER BY v.fecha_creacion DESC
        ");
        $stmt->bind_param("ii", $id_vendedor, $dias);
        $stmt->execute();
        return $stmt->get_result();
    }

    /**
     * Agrupa las ventas del vendedor por día en los últimos N días.
     * Devuelve una fila por día con total_ventas (número de ítems), unidades_vendidas
     * (suma de cantidades) y total_vendido (ingresos calculados como cantidad × precio).
     * Usado por VendedorDashboardController para generar los datos del gráfico de barras semanal.
     *
     * @param int $id_vendedor ID del vendedor autenticado
     * @param int $dias        Ventana de tiempo en días hacia atrás desde hoy (por defecto 30)
     * @return \mysqli_result  Resultado agrupado por fecha con total_ventas, unidades_vendidas, total_vendido
     */
    public function getVentasPorDia($id_vendedor, $dias = 30) {
        $stmt = $this->conn->prepare("
            SELECT DATE(v.fecha_creacion) as fecha,
                   COUNT(*) as total_ventas,
                   SUM(v.cantidad) as unidades_vendidas,
                   SUM(v.cantidad * p.precio) as total_vendido
            FROM detalle_pedido v
            JOIN productos p ON v.id_producto = p.id_producto
            WHERE p.id_vendedor = ?
            AND v.fecha_creacion >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
            GROUP BY DATE(v.fecha_creacion)
            ORDER BY fecha DESC
        ");
        $stmt->bind_param("ii", $id_vendedor, $dias);
        $stmt->execute();
        return $stmt->get_result();
    }

    /**
     * Devuelve el ranking de los N productos más vendidos del vendedor.
     * Para cada producto incluye: total_vendido (suma de cantidades), num_pedidos (pedidos distintos)
     * y promedio_por_pedido (unidades promedio por pedido).
     * El método se llama getProductosMasVendidos() aunque en el modelo de clase se documenta como getTopProductos().
     * Usado por VendedorDashboardController para el widget "Top 5 productos" del dashboard.
     *
     * @param int $id_vendedor ID del vendedor autenticado
     * @param int $limite      Número máximo de productos a incluir en el ranking (por defecto 5)
     * @return \mysqli_result  Resultado con id_producto, nombre, total_vendido, num_pedidos, promedio_por_pedido
     */
    public function getProductosMasVendidos($id_vendedor, $limite = 5) {
        $stmt = $this->conn->prepare("
            SELECT p.nombre, p.id_producto,
                   SUM(v.cantidad) as total_vendido,
                   COUNT(DISTINCT v.id_pedido) as num_pedidos,
                   AVG(v.cantidad) as promedio_por_pedido
            FROM detalle_pedido v
            JOIN productos p ON v.id_producto = p.id_producto
            WHERE p.id_vendedor = ?
            GROUP BY p.id_producto
            ORDER BY total_vendido DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $id_vendedor, $limite);
        $stmt->execute();
        return $stmt->get_result();
    }
}
