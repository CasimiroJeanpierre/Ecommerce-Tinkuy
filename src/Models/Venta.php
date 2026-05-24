<?php
class Venta {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
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