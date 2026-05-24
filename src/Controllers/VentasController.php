<?php

class VentasController {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function listarVentasCompletadas($id_vendedor) {
        $query = "
            SELECT 
                dp.id_detalle,
                pe.id_pedido,
                pe.fecha_pedido,
                p.nombre_producto,
                vp.talla,
                vp.color,
                dp.cantidad,
                dp.precio_historico,
                (dp.cantidad * dp.precio_historico) AS subtotal,
                dp.id_estado_detalle,
                dp.numero_seguimiento,
                emp.nombre_empresa
            FROM 
                detalle_pedido AS dp
            JOIN 
                variantes_producto AS vp ON dp.id_variante = vp.id_variante
            JOIN 
                productos AS p ON vp.id_producto = p.id_producto
            JOIN 
                pedidos AS pe ON dp.id_pedido = pe.id_pedido
            LEFT JOIN
                empresas_envio AS emp ON dp.id_empresa_envio = emp.id_empresa_envio
            WHERE 
                p.id_vendedor = ?
                AND dp.id_estado_detalle IN (3, 4)
            ORDER BY
                pe.fecha_pedido DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function calcularTotalIngresos($items_vendidos) {
        $total = 0;
        foreach ($items_vendidos as $item) {
            $total += $item['subtotal'];
        }
        return $total;
    }
    
    public static function obtenerNombreEstado($id) {
        if ($id == 3) return '<span class="badge bg-info">Enviado</span>';
        if ($id == 4) return '<span class="badge bg-success">Entregado</span>';
        return '<span class="badge bg-secondary">Desconocido</span>';
    }
}