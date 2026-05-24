<?php

require_once BASE_PATH . '/src/Models/Mensaje.php';

/**
 * Controlador de Mensajes de Contacto (Admin)
 * Gestiona la visualización y administración de mensajes recibidos
 */
class MensajesController
{
    private $conn;
    private $modeloMensaje;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->modeloMensaje = new Mensaje();
    }

    /**
     * Listar todos los mensajes
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
     * Ver detalle de un mensaje
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
     * Procesar acciones (marcar, eliminar, cambiar estado)
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
