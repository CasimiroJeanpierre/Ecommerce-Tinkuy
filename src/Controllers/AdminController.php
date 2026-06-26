<?php
// src/Controllers/AdminController.php

require_once __DIR__ . '/../Core/Security.php';
require_once __DIR__ . '/../Core/validaciones.php';

/**
 * Controlador principal del panel de administración.
 * Gestiona el dashboard con métricas de negocio, el listado de pedidos con filtro por estado
 * y el detalle de cada pedido con posibilidad de cancelación (con reposición de stock).
 *
 * Patrón de uso desde el router (public/index.php):
 *   $controller = new AdminController($conn);
 *   $datos      = $controller->metodo(...);
 *   extract($datos);
 *   require BASE_PATH . '/src/Views/admin/...';
 *
 * Seguridad:
 *   - Todos los métodos públicos llaman a requerirAdmin() antes de operar
 *   - La cancelación de pedidos usa transacción para reponer stock atómicamente
 *   - Solo se puede cancelar un pedido en estado "Pagado" (id 2) sin ítems enviados/entregados
 */
class AdminController
{

    private $conn;

    /**
     * @param mysqli $db_connection Conexión activa a la base de datos
     */
    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados auxiliares
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verifica que la sesión corresponda a un administrador activo.
     * Si no hay sesión, redirige a login. Si el rol no es 'admin', establece
     * mensaje de error en sesión y redirige al index público.
     *
     * @return void
     */
    private function requerirAdmin(): void
    {
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if ($_SESSION['rol'] !== 'admin') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Requiere privilegios de administrador.";
            header('Location: ?page=index');
            exit;
        }
    }

    /**
     * Cancela un pedido y repone el stock de las variantes involucradas.
     * Solo cancela si el pedido está en estado "Pagado" (id 2) y ningún ítem
     * fue enviado (estado 3) o entregado (estado 4).
     *
     * @param int $id_pedido ID del pedido a cancelar
     * @return array{mensaje: string, tipo: string} Resultado con mensaje y tipo de alerta
     */
    private function cancelarPedido(int $id_pedido): array
    {
        $stmt_check_pedido = $this->conn->prepare(
            "SELECT id_estado_pedido FROM pedidos WHERE id_pedido = ?"
        );
        $stmt_check_pedido->bind_param("i", $id_pedido);
        $stmt_check_pedido->execute();
        $estado_pedido = $stmt_check_pedido->get_result()->fetch_assoc()['id_estado_pedido'];
        $stmt_check_pedido->close();

        $stmt_check_items = $this->conn->prepare(
            "SELECT id_estado_detalle, id_variante, cantidad FROM detalle_pedido WHERE id_pedido = ?"
        );
        $stmt_check_items->bind_param("i", $id_pedido);
        $stmt_check_items->execute();
        $items_result = $stmt_check_items->get_result();
        $stmt_check_items->close();

        // Solo se puede cancelar si está en estado "Pagado"
        $puede_cancelar   = ($estado_pedido == 2);
        $items_para_stock = [];

        foreach ($items_result as $item) {
            // Estados 3=Enviado, 4=Entregado bloquean la cancelación
            if ($item['id_estado_detalle'] == 3 || $item['id_estado_detalle'] == 4) {
                $puede_cancelar = false;
                break;
            }
            $items_para_stock[] = $item;
        }

        if (!$puede_cancelar) {
            return [
                'mensaje' => "No se puede cancelar el pedido. Uno o más productos ya fueron enviados por el vendedor.",
                'tipo'    => 'danger',
            ];
        }

        $this->conn->begin_transaction();
        try {
            $stmt_cancel = $this->conn->prepare(
                "UPDATE pedidos SET id_estado_pedido = 5 WHERE id_pedido = ?"
            );
            $stmt_cancel->bind_param("i", $id_pedido);
            $stmt_cancel->execute();

            $stmt_reponer = $this->conn->prepare(
                "UPDATE variantes_producto SET stock = stock + ? WHERE id_variante = ?"
            );
            foreach ($items_para_stock as $item) {
                $stmt_reponer->bind_param("ii", $item['cantidad'], $item['id_variante']);
                $stmt_reponer->execute();
            }

            $this->conn->commit();
            return [
                'mensaje' => "¡Pedido #{$id_pedido} cancelado con éxito! El stock ha sido repuesto.",
                'tipo'    => 'success',
            ];
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'mensaje' => "Error al cancelar el pedido: " . $e->getMessage(),
                'tipo'    => 'danger',
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos públicos (acciones del controlador)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Construye las métricas del dashboard administrativo con 4 consultas independientes.
     * Las consultas son COUNT simples sobre pedidos, usuarios y productos, más SUM del total
     * de pedidos en estados activos (Pagado=2, Enviado=3, Entregado=4).
     *
     * @return array{nombre_admin: string, pedidos_pendientes: int, total_usuarios: int, total_productos: int, ingresos_totales: float}
     */
    public function dashboard(): array
    {
        $this->requerirAdmin();
        $nombre_admin = $_SESSION['usuario'];

        $pedidos_pendientes = $this->conn
            ->query("SELECT COUNT(*) AS total FROM pedidos WHERE id_estado_pedido = 1")
            ->fetch_assoc()['total'];

        $total_usuarios = $this->conn
            ->query("SELECT COUNT(*) AS total FROM usuarios")
            ->fetch_assoc()['total'];

        $total_productos = $this->conn
            ->query("SELECT COUNT(*) AS total FROM productos")
            ->fetch_assoc()['total'];

        $ingresos_totales = $this->conn
            ->query("SELECT SUM(total_pedido) AS total FROM pedidos WHERE id_estado_pedido IN (2, 3, 4)")
            ->fetch_assoc()['total'] ?? 0;

        return compact('nombre_admin', 'pedidos_pendientes', 'total_usuarios', 'total_productos', 'ingresos_totales');
    }

    /**
     * Lista todos los pedidos o los filtra por estado usando el parámetro GET 'estado'.
     * Realiza JOIN con estados_pedido, usuarios y perfiles para mostrar nombre del cliente.
     * Si el filtro no es numérico, devuelve todos los pedidos sin filtro.
     *
     * @return array{nombre_admin: string, result_pedidos: \mysqli_result, filtro_estado: string}
     */
    public function pedidos(): array
    {
        $this->requerirAdmin();
        $nombre_admin  = $_SESSION['usuario'];
        $filtro_estado = $_GET['estado'] ?? '';

        $sql = "SELECT
                    p.id_pedido, p.fecha_pedido, p.total_pedido,
                    e.nombre_estado, e.id_estado,
                    CONCAT(pr.nombres, ' ', pr.apellidos) AS nombre_cliente
                FROM pedidos AS p
                JOIN estados_pedido AS e ON p.id_estado_pedido = e.id_estado
                JOIN usuarios AS u ON p.id_usuario = u.id_usuario
                JOIN perfiles AS pr ON u.id_usuario = pr.id_usuario";

        if (!empty($filtro_estado) && is_numeric($filtro_estado)) {
            $stmt = $this->conn->prepare($sql . " WHERE p.id_estado_pedido = ? ORDER BY p.fecha_pedido DESC");
            $stmt->bind_param("i", $filtro_estado);
            $stmt->execute();
            $result_pedidos = $stmt->get_result();
        } else {
            $result_pedidos = $this->conn->query($sql . " ORDER BY p.fecha_pedido DESC");
        }

        return compact('nombre_admin', 'result_pedidos', 'filtro_estado');
    }

    /**
     * Muestra el detalle completo de un pedido y procesa su cancelación si se solicita via POST.
     * Realiza 3 consultas: cabecera del pedido (con datos de cliente y dirección), líneas de detalle,
     * y empresas de envío disponibles para el dropdown de cambio de estado.
     * La cancelación solo es posible si el pedido está en estado "Pagado" (id 2) y sin ítems enviados.
     *
     * @param int $id_pedido ID del pedido a mostrar (validado previamente en el router)
     * @return array{nombre_admin: string, pedido: array|null, detalles: array,
     *               empresas_envio: array, mensaje_alerta: string, tipo_alerta: string}
     */
    public function verPedido(int $id_pedido): array
    {
        $this->requerirAdmin();
        $nombre_admin  = $_SESSION['usuario'];
        $mensaje_alerta = '';
        $tipo_alerta    = '';

        // Procesar cancelación desde el formulario POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['accion'])
            && $_POST['accion'] === 'cancelar_pedido'
            && (int) $_POST['id_pedido'] === $id_pedido
        ) {
            $resultado      = $this->cancelarPedido($id_pedido);
            $mensaje_alerta = $resultado['mensaje'];
            $tipo_alerta    = $resultado['tipo'];
        }

        // Consulta principal del pedido
        $sql_pedido = "SELECT
                            p.id_pedido, p.fecha_pedido, p.total_pedido,
                            e.nombre_estado, e.id_estado,
                            CONCAT(pr.nombres, ' ', pr.apellidos) AS nombre_cliente,
                            pr.telefono, u.email,
                            d.direccion, d.ciudad, d.pais, d.codigo_postal
                        FROM pedidos AS p
                        JOIN estados_pedido AS e ON p.id_estado_pedido = e.id_estado
                        JOIN usuarios AS u ON p.id_usuario = u.id_usuario
                        JOIN perfiles AS pr ON u.id_usuario = pr.id_usuario
                        JOIN direcciones AS d ON p.id_direccion_envio = d.id_direccion
                        WHERE p.id_pedido = ?";

        $stmt_pedido = $this->conn->prepare($sql_pedido);
        $stmt_pedido->bind_param("i", $id_pedido);
        $stmt_pedido->execute();
        $pedido = $stmt_pedido->get_result()->fetch_assoc();

        if (!$pedido) {
            $_SESSION['mensaje_error'] = "Pedido no encontrado.";
            header('Location: ?page=admin_pedidos');
            exit;
        }

        // Consulta del detalle de ítems del pedido
        $sql_detalles = "SELECT
                            dp.id_detalle, dp.cantidad, dp.precio_historico, dp.id_estado_detalle, dp.numero_seguimiento,
                            prod.nombre_producto, prod.imagen_principal,
                            vp.imagen_variante, vp.talla, vp.color,
                            vendedor_perfil.nombres AS nombre_vendedor,
                            ee.nombre_empresa
                        FROM detalle_pedido AS dp
                        JOIN variantes_producto AS vp ON dp.id_variante = vp.id_variante
                        JOIN productos AS prod ON vp.id_producto = prod.id_producto
                        JOIN usuarios AS vendedor_user ON prod.id_vendedor = vendedor_user.id_usuario
                        JOIN perfiles AS vendedor_perfil ON vendedor_user.id_usuario = vendedor_perfil.id_usuario
                        LEFT JOIN empresas_envio AS ee ON dp.id_empresa_envio = ee.id_empresa_envio
                        WHERE dp.id_pedido = ?";

        $stmt_detalles = $this->conn->prepare($sql_detalles);
        $stmt_detalles->bind_param("i", $id_pedido);
        $stmt_detalles->execute();
        $detalles_pedido = $stmt_detalles->get_result();

        $permite_cancelacion_admin = ($pedido['id_estado'] == 2);

        return compact(
            'nombre_admin', 'pedido', 'detalles_pedido',
            'permite_cancelacion_admin', 'mensaje_alerta', 'tipo_alerta', 'id_pedido'
        );
    }

}
?>
