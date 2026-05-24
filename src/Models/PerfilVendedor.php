<?php
class PerfilVendedor {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function getPerfil($id_vendedor) {
        $stmt = $this->conn->prepare("
            SELECT p.*, u.usuario, u.email 
            FROM usuarios u 
            LEFT JOIN perfiles p ON u.id = p.id_usuario 
            WHERE u.id = ? AND u.rol = 'vendedor'
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    public function actualizarPerfil($id_vendedor, $datos) {
        $this->conn->begin_transaction();
        
        try {
            // Actualizar datos básicos
            $stmt = $this->conn->prepare("
                INSERT INTO perfiles (id_usuario, nombres, apellidos, telefono)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    nombres = VALUES(nombres),
                    apellidos = VALUES(apellidos),
                    telefono = VALUES(telefono)
            ");
            $stmt->bind_param("isss", 
                $id_vendedor, 
                $datos['nombres'], 
                $datos['apellidos'], 
                $datos['telefono']
            );
            $stmt->execute();
            
            // Si hay más datos específicos de vendedor, actualizarlos aquí
            // Por ejemplo, datos bancarios, dirección de envío, etc.
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    public function getEstadisticas($id_vendedor) {
        // Obtener estadísticas generales del vendedor
        $stats = [
            'total_productos' => 0,
            'total_ventas' => 0,
            'calificacion_promedio' => 0,
            'fecha_registro' => null
        ];
        
        // Total productos activos
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total 
            FROM productos 
            WHERE id_vendedor = ? AND estado = 'activo'
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        $stats['total_productos'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // Total ventas realizadas
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT dp.id_pedido) as total
            FROM detalle_pedido dp
            JOIN productos p ON dp.id_producto = p.id_producto
            WHERE p.id_vendedor = ?
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        $stats['total_ventas'] = $stmt->get_result()->fetch_assoc()['total'];
        
        // Calificación promedio (si existe tabla de calificaciones)
        $stmt = $this->conn->prepare("
            SELECT AVG(calificacion) as promedio
            FROM calificaciones c
            JOIN productos p ON c.id_producto = p.id_producto
            WHERE p.id_vendedor = ?
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stats['calificacion_promedio'] = $result['promedio'] ?? 0;
        
        // Fecha de registro
        $stmt = $this->conn->prepare("
            SELECT fecha_registro
            FROM usuarios
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        $stats['fecha_registro'] = $stmt->get_result()->fetch_assoc()['fecha_registro'];
        
        return $stats;
    }
}