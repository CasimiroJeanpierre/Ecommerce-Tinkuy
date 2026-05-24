<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();

class AdminCuponesController
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;

        // Validación estricta de seguridad (RBAC)
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: " . (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php') . "?page=login");
            exit;
        }
        if (strtolower($_SESSION['rol'] ?? '') !== 'admin') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Requiere privilegios de administrador.";
            header("Location: " . (defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php') . "?page=index");
            exit;
        }
    }

    public function listar()
    {
        $mensaje_error = $_SESSION['mensaje_error'] ?? '';
        $mensaje_exito = $_SESSION['mensaje_exito'] ?? '';
        unset($_SESSION['mensaje_error'], $_SESSION['mensaje_exito']);

        // Procesar POST para crear un nuevo cupón
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear_cupon') {
            $codigo = strip_tags(strtoupper(trim($_POST['codigo'] ?? '')));
            $porcentaje = filter_var($_POST['porcentaje_descuento'] ?? 0, FILTER_VALIDATE_FLOAT);
            $fecha_expiracion = !empty($_POST['fecha_expiracion']) ? $_POST['fecha_expiracion'] : null;

            if (empty($codigo) || $porcentaje <= 0 || $porcentaje > 100) {
                $mensaje_error = "El código es requerido y el porcentaje debe estar entre 0.01 y 100.";
            } elseif (!preg_match('/^[A-Z0-9]+$/', $codigo)) {
                $mensaje_error = "El código del cupón solo puede contener letras y números sin espacios.";
            } else {
                // Verificar que no exista otro cupón con el mismo código
                $stmt = $this->conn->prepare("SELECT id_cupon FROM cupones WHERE codigo = ?");
                $stmt->bind_param("s", $codigo);
                $stmt->execute();

                if ($stmt->get_result()->num_rows > 0) {
                    $mensaje_error = "Error: El código '$codigo' ya está registrado.";
                } else {
                    // Insertar en Base de Datos
                    $stmt_insert = $this->conn->prepare("INSERT INTO cupones (codigo, porcentaje_descuento, fecha_expiracion, estado) VALUES (?, ?, ?, 'activo')");
                    $stmt_insert->bind_param("sds", $codigo, $porcentaje, $fecha_expiracion);

                    if ($stmt_insert->execute()) {
                        $_SESSION['mensaje_exito'] = "Cupón '$codigo' creado exitosamente.";
                        header("Location: ?page=admin_cupones");
                        exit;
                    } else {
                        $mensaje_error = "Error al crear cupón en la BD: " . $this->conn->error;
                    }
                }
                $stmt->close();
            }
        }

        // Obtener todos los cupones existentes
        $query = "SELECT * FROM cupones ORDER BY estado ASC, id_cupon DESC";
        $res = $this->conn->query($query);
        $cupones = $res->fetch_all(MYSQLI_ASSOC);

        return [
            'cupones' => $cupones,
            'mensaje_error' => $mensaje_error,
            'mensaje_exito' => $mensaje_exito,
            'base_url' => defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php'
        ];
    }

    public function eliminar($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM cupones WHERE id_cupon = ?");
        $stmt->bind_param("i", $id);
        $_SESSION[$stmt->execute() ? 'mensaje_exito' : 'mensaje_error'] = $stmt->execute() ? "Cupón eliminado correctamente." : "Error al eliminar el cupón.";
        header("Location: ?page=admin_cupones");
        exit;
    }

    public function cambiarEstado($id, $estado)
    {
        $stmt = $this->conn->prepare("UPDATE cupones SET estado = ? WHERE id_cupon = ?");
        $stmt->bind_param("si", $estado, $id);
        $_SESSION[$stmt->execute() ? 'mensaje_exito' : 'mensaje_error'] = $stmt->execute() ? "Estado actualizado a $estado." : "Error al actualizar estado.";
        header("Location: ?page=admin_cupones");
        exit;
    }
}
?>