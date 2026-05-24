<?php
// src/Controllers/AdminController.php

require_once __DIR__ . '/../Core/Security.php';
require_once __DIR__ . '/../Core/validaciones.php';

class AdminController
{

    private $conn;

    // El constructor recibe la conexión a la BBDD desde el index.php
    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    /**
     * Muestra el dashboard (si está logueado)
     */
    public function dashboard()
    {

        // --- INICIO DE TU CÓDIGO DE SEGURIDAD (ADAPTADO) ---
        // La sesión ya está iniciada por index.php
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if ($_SESSION['rol'] !== 'admin') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Requiere privilegios de administrador.";
            header('Location: ?page=index');
            exit;
        }
        // --- FIN DE TU CÓDIGO DE SEGURIDAD ---

        $nombre_admin = $_SESSION['usuario'];

        // --- INICIO DE TU LÓGICA DE CONSULTAS (ADAPTADA) ---
        // Usamos '$this->conn' en lugar de '$conn'
        $stmt_pendientes = $this->conn->query("SELECT COUNT(*) AS total FROM pedidos WHERE id_estado_pedido = 1");
        $pedidos_pendientes = $stmt_pendientes->fetch_assoc()['total'];

        $stmt_usuarios = $this->conn->query("SELECT COUNT(*) AS total FROM usuarios");
        $total_usuarios = $stmt_usuarios->fetch_assoc()['total'];

        $stmt_productos = $this->conn->query("SELECT COUNT(*) AS total FROM productos");
        $total_productos = $stmt_productos->fetch_assoc()['total'];

        $stmt_ingresos = $this->conn->query("SELECT SUM(total_pedido) AS total FROM pedidos WHERE id_estado_pedido IN (2, 3, 4)");
        $ingresos_totales = $stmt_ingresos->fetch_assoc()['total'] ?? 0;

        // $conn->close() NO se pone aquí. Se cierra al final del index.php

        // --- DEVOLVEMOS LOS DATOS ---
        // Devolvemos un array con todas las variables que la Vista necesita.
        return [
            'nombre_admin' => $nombre_admin,
            'pedidos_pendientes' => $pedidos_pendientes,
            'total_usuarios' => $total_usuarios,
            'total_productos' => $total_productos,
            'ingresos_totales' => $ingresos_totales
        ];

    }
    /**
     * Prepara los datos para la página de Pedidos
     */
    public function pedidos()
    {

        // --- INICIO DE TU CÓDIGO DE SEGURIDAD (ADAPTADO) ---
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if ($_SESSION['rol'] !== 'admin') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Requiere privilegios de administrador.";
            header('Location: ?page=index');
            exit;
        }
        // --- FIN DE TU CÓDIGO DE SEGURIDAD ---

        $nombre_admin = $_SESSION['usuario'];

        // --- INICIO DE TU LÓGICA DE CONSULTAS (ADAPTADA) ---

        // Filtros
        $filtro_estado = $_GET['estado'] ?? '';

        $sql = "SELECT 
                    p.id_pedido, p.fecha_pedido, p.total_pedido,
                    e.nombre_estado, e.id_estado,
                    CONCAT(pr.nombres, ' ', pr.apellidos) AS nombre_cliente
                FROM 
                    pedidos AS p
                JOIN 
                    estados_pedido AS e ON p.id_estado_pedido = e.id_estado
                JOIN 
                    usuarios AS u ON p.id_usuario = u.id_usuario
                JOIN 
                    perfiles AS pr ON u.id_usuario = pr.id_usuario
                ";

        // Aplicar filtro si existe
        if (!empty($filtro_estado) && is_numeric($filtro_estado)) {
            // Usamos una consulta preparada simple para seguridad
            $stmt = $this->conn->prepare($sql . " WHERE p.id_estado_pedido = ? ORDER BY p.fecha_pedido DESC");
            $stmt->bind_param("i", $filtro_estado);
            $stmt->execute();
            $result_pedidos = $stmt->get_result();
        } else {
            // Sin filtro
            $result_pedidos = $this->conn->query($sql . " ORDER BY p.fecha_pedido DESC");
        }

        // NO cerramos la conexión aquí ($conn->close())

        // --- DEVOLVEMOS LOS DATOS ---
        return [
            'nombre_admin' => $nombre_admin,
            'result_pedidos' => $result_pedidos,
            'filtro_estado' => $filtro_estado
        ];
    }
    /**
     * Muestra y procesa el Detalle de un Pedido Específico
     */
    public function verPedido($id_pedido)
    {

        // --- INICIO DE TU CÓDIGO DE SEGURIDAD ---
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if ($_SESSION['rol'] !== 'admin') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Requiere privilegios de administrador.";
            header('Location: ?page=index');
            exit;
        }

        $nombre_admin = $_SESSION['usuario'];
        $mensaje_alerta = '';
        $tipo_alerta = '';

        // --- LÓGICA DE ACCIÓN (POST) - (Fiabilidad ISO 25010) ---
        // (Movemos tu lógica de CANCELAR aquí)
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion'])) {

            if ($_POST['accion'] == 'cancelar_pedido' && $_POST['id_pedido'] == $id_pedido) {

                // Usamos '$this->conn'
                $stmt_check_pedido = $this->conn->prepare("SELECT id_estado_pedido FROM pedidos WHERE id_pedido = ?");
                $stmt_check_pedido->bind_param("i", $id_pedido);
                $stmt_check_pedido->execute();
                $estado_pedido = $stmt_check_pedido->get_result()->fetch_assoc()['id_estado_pedido'];

                $stmt_check_items = $this->conn->prepare("SELECT id_estado_detalle, id_variante, cantidad FROM detalle_pedido WHERE id_pedido = ?");
                $stmt_check_items->bind_param("i", $id_pedido);
                $stmt_check_items->execute();
                $items_a_reponer = $stmt_check_items->get_result();

                $puede_cancelar = ($estado_pedido == 2); // Solo si está 'Pagado'
                $items_para_stock = [];

                foreach ($items_a_reponer as $item) {
                    if ($item['id_estado_detalle'] == 3 || $item['id_estado_detalle'] == 4) { // 3=Enviado, 4=Entregado
                        $puede_cancelar = false;
                        break;
                    }
                    $items_para_stock[] = $item;
                }

                if ($puede_cancelar) {
                    $this->conn->begin_transaction();
                    try {
                        $stmt_cancel = $this->conn->prepare("UPDATE pedidos SET id_estado_pedido = 5 WHERE id_pedido = ?");
                        $stmt_cancel->bind_param("i", $id_pedido);
                        $stmt_cancel->execute();

                        $stmt_reponer = $this->conn->prepare("UPDATE variantes_producto SET stock = stock + ? WHERE id_variante = ?");
                        foreach ($items_para_stock as $item) {
                            $stmt_reponer->bind_param("ii", $item['cantidad'], $item['id_variante']);
                            $stmt_reponer->execute();
                        }

                        $this->conn->commit();
                        $mensaje_alerta = "¡Pedido #" . $id_pedido . " cancelado con éxito! El stock ha sido repuesto.";
                        $tipo_alerta = 'success';

                    } catch (Exception $e) {
                        $this->conn->rollback();
                        $mensaje_alerta = "Error al cancelar el pedido: " . $e->getMessage();
                        $tipo_alerta = 'danger';
                    }
                } else {
                    $mensaje_alerta = "No se puede cancelar el pedido. Uno o más productos ya fueron enviados por el vendedor.";
                    $tipo_alerta = 'danger';
                }
            }
        }
        // --- FIN LÓGICA DE ACCIÓN ---

        // --- LÓGICA DE VISUALIZACIÓN (GET) ---
        // (Movemos tu lógica de 'GET' aquí)

        // 1. Consulta Maestra
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
            header('Location: ?page=admin_pedidos'); // Redirigimos a la lista
            exit;
        }

        // 2. Consulta de Detalle
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
        $detalles_pedido = $stmt_detalles->get_result(); // Dejamos esto como un resultado para el 'while' en la vista

        $permite_cancelacion_admin = ($pedido['id_estado'] == 2);
        // NO cerramos la conexión aquí

        // --- DEVOLVEMOS LOS DATOS ---
        return [
            'nombre_admin' => $nombre_admin,
            'pedido' => $pedido,
            'detalles_pedido' => $detalles_pedido,
            'permite_cancelacion_admin' => $permite_cancelacion_admin,
            'mensaje_alerta' => $mensaje_alerta,
            'tipo_alerta' => $tipo_alerta,
            'id_pedido' => $id_pedido // Pasamos el ID para el formulario
        ];
    }

}
?>