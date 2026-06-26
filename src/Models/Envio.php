<?php

/**
 * Modelo de envíos para el panel del vendedor.
 * Consulta y actualiza el estado de ítems de detalle_pedido relacionados
 * con el proceso de envío. Gestiona la transición de estado 'Procesando' → 'Enviado'.
 *
 * Métodos disponibles:
 *   getEnviosPendientes($id_vendedor)              — Ítems con estado 2 (pendiente de envío)
 *   registrarEnvio($id_detalle, $id_empresa, $num) — Actualiza empresa + número de seguimiento
 *   getEmpresasEnvio()                             — Lista las empresas de logística disponibles
 *
 * Uso desde EnviosController:
 *   $envio = new Envio($conn);
 *   $pendientes = $envio->getEnviosPendientes($_SESSION['usuario_id']);
 */
class Envio {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Devuelve los ítems de detalle_pedido con estado 2 (pendiente de envío) del vendedor.
     * Enriquece cada fila con nombre del producto, total del pedido, fecha del pedido,
     * nombre del cliente y nombre del estado desde estados_detalle.
     * Los resultados se ordenan por fecha_pedido ASC (los más antiguos primero, para atender en orden).
     * Nota: La consulta referencia id_estado_detalle = 2 como "pendiente"; verificar que coincide
     * con la tabla estados_detalle del esquema actual.
     *
     * @param int $id_vendedor ID del vendedor autenticado
     * @return \mysqli_result Filas de detalle_pedido con campos extendidos de producto, cliente y estado
     */
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

    /**
     * Actualiza el estado de un ítem de detalle_pedido verificando propiedad (anti-IDOR).
     * Paso 1: SELECT con JOIN para verificar que el ítem y su producto pertenecen al vendedor.
     *         Si no se encuentra, lanza Exception y no ejecuta el UPDATE.
     * Paso 2: UPDATE que asigna el nuevo estado y actualiza fecha_actualizacion a CURRENT_TIMESTAMP.
     * Usado por EnviosController para registrar envíos; el estado destino típico es 3 (Enviado).
     *
     * @param int $id_detalle   ID de la fila en detalle_pedido a modificar
     * @param int $nuevo_estado Nuevo ID de estado (ej. 3 = Enviado, 4 = Entregado)
     * @param int $id_vendedor  ID del vendedor autenticado (verificación anti-IDOR)
     * @return bool             true si el UPDATE afectó al menos una fila, false si no
     * @throws \Exception Si el ítem no se encontró o no pertenece al vendedor indicado
     */
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

    /**
     * Devuelve todos los estados de detalle_pedido que están marcados como activos.
     * Ordena por el campo 'orden' para respetar la secuencia del flujo de estados.
     * Nota: Este método no está en uso en el sistema MVC actual (el sistema usa IDs de estado
     * directamente); se conserva para retrocompatibilidad con el módulo legacy de envíos.
     *
     * @return \mysqli_result Filas de la tabla estados_detalle donde activo = 1, ordenadas por 'orden'
     */
    public function getEstadosDisponibles() {
        return $this->conn->query("
            SELECT * FROM estados_detalle
            WHERE activo = 1
            ORDER BY orden
        ");
    }
}
