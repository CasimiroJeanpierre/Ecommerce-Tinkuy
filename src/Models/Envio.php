<?php
class Envio {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getEnviosPendientes($id_vendedor) {
        $stmt = $this->conn->prepare("
            SELECT dp.*, p.nombre as producto, pe.total, pe.fecha_pedido, 
                   u.usuario as cliente, ed.nombre as estado
            FROM detalle_pedido dp
            JOIN pedidos pe ON dp.id_pedido = pe.id_pedido
            JOIN productos p ON dp.id_producto = p.id_producto
            JOIN usuarios u ON pe.id_usuario = u.id
            JOIN estados_detalle ed ON dp.id_estado_detalle = ed.id_estado
            WHERE p.id_vendedor = ? AND dp.id_estado_detalle = 2
            ORDER BY pe.fecha_pedido DESC
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    public function actualizarEstado($id_detalle, $nuevo_estado, $id_vendedor) {
        // Verificar propiedad del envío
        $stmt = $this->conn->prepare("
            SELECT dp.* FROM detalle_pedido dp
            JOIN productos p ON dp.id_producto = p.id_producto
            WHERE dp.id_detalle = ? AND p.id_vendedor = ?
        ");
        $stmt->bind_param("ii", $id_detalle, $id_vendedor);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception("Envío no encontrado o no tienes permiso para actualizarlo");
        }
        
        // Actualizar estado
        $stmt = $this->conn->prepare("
            UPDATE detalle_pedido 
            SET id_estado_detalle = ?, 
                fecha_actualizacion = CURRENT_TIMESTAMP 
            WHERE id_detalle = ?
        ");
        $stmt->bind_param("ii", $nuevo_estado, $id_detalle);
        return $stmt->execute();
    }
    
    public function getEstadosDisponibles() {
        return $this->conn->query("
            SELECT * FROM estados_detalle 
            WHERE activo = 1 
            ORDER BY orden
        ");
    }
}