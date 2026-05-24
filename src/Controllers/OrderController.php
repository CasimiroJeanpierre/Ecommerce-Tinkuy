<?php
class OrderController
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getUserOrders($id_usuario)
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    p.id_pedido, 
                    p.fecha_pedido, 
                    p.total_pedido, 
                    e.nombre_estado
                FROM pedidos AS p
                JOIN estados_pedido AS e ON p.id_estado_pedido = e.id_estado
                WHERE p.id_usuario = ?
                ORDER BY p.fecha_pedido DESC
            ");
            $stmt->bind_param("i", $id_usuario);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (mysqli_sql_exception $e) {
            throw new Exception("Error al obtener los pedidos: " . $e->getMessage());
        }
    }

    public function getOrderDetails($id_pedido, $id_usuario)
    {
        try {
            // Primero verificamos que el pedido pertenezca al usuario
            // Ajuste: usar 'perfiles' para nombres del usuario y 'usuarios.id_usuario' como FK
            $stmt = $this->conn->prepare("
                SELECT p.*, e.nombre_estado, u.email, pf.nombres AS nombre_usuario, pf.apellidos AS apellido_usuario,
                       d.direccion, d.ciudad, d.codigo_postal
                FROM pedidos p
                JOIN estados_pedido e ON p.id_estado_pedido = e.id_estado
                JOIN usuarios u ON p.id_usuario = u.id_usuario
                LEFT JOIN perfiles pf ON u.id_usuario = pf.id_usuario
                LEFT JOIN direcciones d ON p.id_direccion_envio = d.id_direccion
                WHERE p.id_pedido = ? AND p.id_usuario = ?
            ");
            $stmt->bind_param("ii", $id_pedido, $id_usuario);
            $stmt->execute();
            $pedido = $stmt->get_result()->fetch_assoc();

            if (!$pedido) {
                throw new Exception("No tienes acceso a este pedido.");
            }

            // Obtenemos los detalles de los productos del pedido
            // Obtener detalles desde 'detalle_pedido' y datos de variantes/productos
            $stmt = $this->conn->prepare("
                SELECT dp.*, v.id_variante, v.precio AS precio_unitario, pr.nombre_producto, pr.imagen_principal AS imagen_url
                FROM detalle_pedido dp
                JOIN variantes_producto v ON dp.id_variante = v.id_variante
                JOIN productos pr ON v.id_producto = pr.id_producto
                WHERE dp.id_pedido = ?
            ");
            $stmt->bind_param("i", $id_pedido);
            $stmt->execute();
            $detalles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            return [
                'pedido' => $pedido,
                'detalles' => $detalles
            ];
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getOrderStatusClass($estado)
    {
        switch ($estado) {
            case 'Pagado':
                return 'bg-success';
            case 'Pendiente de Pago':
                return 'bg-warning text-dark';
            case 'Enviado':
                return 'bg-info text-dark';
            case 'Entregado':
                return 'bg-primary';
            case 'Cancelado':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    }
}
