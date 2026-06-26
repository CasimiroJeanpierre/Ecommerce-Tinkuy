<?php

require_once BASE_PATH . '/src/Models/Mensaje.php';

/**
 * Controlador de mensajes de contacto del panel de administración.
 * Gestiona la visualización, filtrado y cambio de estado de los mensajes
 * enviados desde el formulario público de contacto.
 *
 * Métodos disponibles:
 *   listar()     — Lista mensajes con filtro por estado; despacha acciones GET
 *   ver($id)     — Detalle de un mensaje; marca automáticamente como leído (leido=1)
 *
 * Acciones GET procesadas en listar():
 *   marcar_respondido=N → estado='respondido', leido=1
 *   archivar=N          → estado='archivado'
 *   eliminar=N          → DELETE permanente del mensaje
 *
 * Uso desde el router (public/index.php):
 *   $ctrl = new MensajesController($conn);
 *   extract($ctrl->listar()); include VIEW_PATH . '/admin/mensajes/mensajes.php';
 */
class MensajesController
{
    private $conn;
    private $modeloMensaje;

    /**
     * @param mysqli $conn Conexión activa a la base de datos
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->modeloMensaje = new Mensaje();
    }

    /**
     * Lista todos los mensajes con filtro opcional por estado.
     * Despacha acciones GET (marcar, archivar, eliminar) antes de listar.
     *
     * @return array{mensajes: array, estadisticas: array, filtro_estado: string, nombre_admin: string, base_url: string}
     */
    public function listar()
    {
        // Verificar admin
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if ($_SESSION['rol'] !== 'admin') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Requiere privilegios de administrador.";
            header('Location: ?page=index');
            exit;
        }

        // Obtener filtro
        $filtro_estado = $_GET['filtro'] ?? 'todos';

        // Manejar acciones (marcar como leído, cambiar estado, eliminar)
        $this->procesarAcciones();

        // Obtener mensajes
        $mensajes = $this->modeloMensaje->obtenerTodosMensajes($this->conn, $filtro_estado);
        $estadisticas = $this->modeloMensaje->contarMensajes($this->conn);

        // Variables para la vista
        $nombre_admin = $_SESSION['usuario'] ?? 'Admin';
        $base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';

        return [
            'mensajes' => $mensajes,
            'estadisticas' => $estadisticas,
            'filtro_estado' => $filtro_estado,
            'nombre_admin' => $nombre_admin,
            'base_url' => $base_url
        ];
    }

    /**
     * Muestra el detalle de un mensaje y lo marca como leído automáticamente.
     * Redirige a admin_mensajes si el ID no existe.
     *
     * @param int $id_mensaje ID del mensaje a visualizar
     * @return array{mensaje: array, nombre_admin: string, base_url: string}
     */
    public function ver($id_mensaje)
    {
        // Verificar admin
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if ($_SESSION['rol'] !== 'admin') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Requiere privilegios de administrador.";
            header('Location: ?page=index');
            exit;
        }

        // Marcar como leído automáticamente
        $this->modeloMensaje->marcarComoLeido($this->conn, $id_mensaje);

        // Obtener mensaje específico
        $stmt = $this->conn->prepare("SELECT * FROM mensajes_contacto WHERE id_mensaje = ?");
        $stmt->bind_param("i", $id_mensaje);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $mensaje = $resultado->fetch_assoc();

        if (!$mensaje) {
            $_SESSION['mensaje_error'] = "Mensaje no encontrado.";
            header('Location: ?page=admin_mensajes');
            exit;
        }

        $nombre_admin = $_SESSION['usuario'] ?? 'Admin';
        $base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';

        return [
            'mensaje' => $mensaje,
            'nombre_admin' => $nombre_admin,
            'base_url' => $base_url
        ];
    }

    /**
     * Despacha acciones GET sobre un mensaje: marcar_respondido, marcar_pendiente,
     * archivar y eliminar. Redirige tras cada acción.
     *
     * @return void
     */
    private function procesarAcciones()
    {
        // Marcar como respondido
        if (isset($_GET['marcar_respondido'])) {
            $id = (int) $_GET['marcar_respondido'];
            if ($this->modeloMensaje->cambiarEstado($this->conn, $id, 'respondido')) {
                $_SESSION['mensaje_exito'] = "Mensaje marcado como respondido.";
            }
            header('Location: ?page=admin_mensajes');
            exit;
        }

        // Marcar como pendiente
        if (isset($_GET['marcar_pendiente'])) {
            $id = (int) $_GET['marcar_pendiente'];
            if ($this->modeloMensaje->cambiarEstado($this->conn, $id, 'pendiente')) {
                $_SESSION['mensaje_exito'] = "Mensaje marcado como pendiente.";
            }
            header('Location: ?page=admin_mensajes');
            exit;
        }

        // Archivar
        if (isset($_GET['archivar'])) {
            $id = (int) $_GET['archivar'];
            if ($this->modeloMensaje->cambiarEstado($this->conn, $id, 'archivado')) {
                $_SESSION['mensaje_exito'] = "Mensaje archivado.";
            }
            header('Location: ?page=admin_mensajes');
            exit;
        }

        // Eliminar
        if (isset($_GET['eliminar'])) {
            $id = (int) $_GET['eliminar'];
            if ($this->modeloMensaje->eliminarMensaje($this->conn, $id)) {
                $_SESSION['mensaje_exito'] = "Mensaje eliminado.";
            } else {
                $_SESSION['mensaje_error'] = "Error al eliminar mensaje.";
            }
            header('Location: ?page=admin_mensajes');
            exit;
        }
    }
}
