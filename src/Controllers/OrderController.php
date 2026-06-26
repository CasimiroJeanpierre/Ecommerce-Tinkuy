<?php

/**
 * Controlador de pedidos para el panel del usuario comprador.
 * Consulta el historial de pedidos del usuario y el detalle de cada pedido,
 * aplicando verificación de propiedad (anti-IDOR) en todas las consultas.
 *
 * Métodos disponibles:
 *   getUserOrders($id_usuario)          — Lista todos los pedidos del usuario
 *   getOrderDetails($id_pedido, $id)    — Cabecera + líneas de detalle con verificación
 *   getOrderStatusClass($estado)        — Clase CSS Bootstrap para el badge de estado
 *
 * Seguridad:
 *   getOrderDetails() incluye id_usuario en el WHERE de la consulta principal para
 *   garantizar que un usuario no pueda ver pedidos ajenos modificando el parámetro id.
 *
 * Nota: Este controlador no verifica la sesión internamente; el router debe asegurarse
 * de que solo usuarios autenticados tengan acceso a las rutas 'pedidos' y 'ver_pedido'.
 */
class OrderController
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Devuelve el historial completo de pedidos del usuario ordenados por fecha descendente.
     * Hace JOIN a estados_pedido para obtener el nombre del estado en lugar del id numérico,
     * lo que permite a la vista renderizar el badge de estado directamente sin mapeos adicionales.
     * La consulta filtra solo por id_usuario, por lo que incluye pedidos de todos los estados
     * (pendiente, pagado, enviado, entregado, cancelado).
     * Cualquier error de BD se captura y relanza como Exception con mensaje descriptivo para
     * que el router pueda mostrarlo al usuario sin exponer detalles del esquema.
     *
     * @param int $id_usuario ID del usuario autenticado (FK → usuarios.id_usuario)
     * @return array<int, array<string, mixed>> Filas con id_pedido, fecha_pedido, total_pedido,
     *         nombre_estado; ordenadas de más reciente a más antiguo
     * @throws \Exception Si la consulta a la base de datos falla
     */
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

    /**
     * Devuelve la cabecera del pedido (con dirección y estado) y sus líneas de detalle.
     * La primera consulta incluye WHERE id_pedido = ? AND id_usuario = ? para garantizar
     * que el usuario solo pueda ver sus propios pedidos (anti-IDOR); si no se encuentra,
     * lanza Exception con un mensaje genérico que no revela si el pedido existe para otro usuario.
     * La segunda consulta obtiene las líneas de detalle_pedido con nombre del producto, imagen
     * y precio_unitario desde variantes_producto para renderizar la vista de detalle de pedido.
     * Ambas consultas usan prepared statements para prevenir inyección SQL.
     *
     * @param int $id_pedido  ID del pedido a consultar
     * @param int $id_usuario ID del usuario autenticado en sesión (verificación anti-IDOR)
     * @return array{pedido: array<string, mixed>, detalles: array<int, array<string, mixed>>}
     *         pedido: cabecera con estado, dirección, email; detalles: líneas con producto e imagen
     * @throws \Exception Si el pedido no existe, no pertenece al usuario, o la BD falla
     */
    public function getOrderDetails($id_pedido, $id_usuario)
    {
        try {
            // Verificar propiedad y obtener cabecera del pedido
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

            // Obtener líneas de detalle con variante y producto
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
                'pedido'   => $pedido,
                'detalles' => $detalles
            ];
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Mapea el nombre de un estado de pedido a la clase CSS de Bootstrap badge correspondiente.
     * Los estados reconocidos son: 'Pagado' (verde), 'Pendiente de Pago' (amarillo oscuro),
     * 'Enviado' (azul claro), 'Entregado' (azul primario), 'Cancelado' (rojo).
     * Cualquier estado no reconocido (o nuevo estado agregado sin actualizar este método)
     * recibe la clase 'bg-secondary' (gris) como fallback seguro sin exception.
     * El texto-dark se agrega en fondos claros (warning, info) para garantizar contraste WCAG.
     *
     * @param string $estado Nombre del estado tal como viene de estados_pedido.nombre_estado
     * @return string Clase(s) CSS de Bootstrap para el badge (p.ej. 'bg-success', 'bg-warning text-dark')
     */
    public function getOrderStatusClass($estado)
    {
        switch ($estado) {
            case 'Pagado':            return 'bg-success';
            case 'Pendiente de Pago': return 'bg-warning text-dark';
            case 'Enviado':           return 'bg-info text-dark';
            case 'Entregado':         return 'bg-primary';
            case 'Cancelado':         return 'bg-danger';
            default:                  return 'bg-secondary';
        }
    }
}
