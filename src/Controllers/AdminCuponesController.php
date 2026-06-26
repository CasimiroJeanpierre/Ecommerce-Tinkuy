<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();

/**
 * Controlador de administración de cupones/códigos promocionales.
 * Permite listar, crear, activar/desactivar y eliminar cupones de descuento.
 *
 * Reglas de negocio aplicadas:
 *   - El código de cupón solo puede contener letras mayúsculas y números sin espacios
 *   - El porcentaje de descuento debe estar entre 0.01 y 100
 *   - No se permiten dos cupones con el mismo código (unicidad verificada en BD)
 *   - La fecha de expiración es opcional; un cupón sin fecha no expira nunca
 *   - Solo admins pueden acceder (verificado en __construct con RBAC)
 *
 * Acciones disponibles:
 *   listar()       — GET: lista todos; POST con accion=crear_cupon: crea uno nuevo
 *   eliminar($id)  — DELETE lógico: elimina el registro de BD y redirige
 *   cambiarEstado($id, $estado) — Activa o desactiva el cupón sin eliminarlo
 */
class AdminCuponesController
{
    private $conn;

    /**
     * @param mysqli $conn Conexión activa a la base de datos
     */
    public function __construct($conn)
    {
        $this->conn = $conn;

        // Validación de sesión y rol (RBAC)
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

    /**
     * Lista todos los cupones ordenados por estado y fecha de creación.
     * En POST con accion='crear_cupon', procesa la creación: valida código,
     * porcentaje y unicidad antes de insertar en BD. En caso de éxito redirige
     * con mensaje flash; en caso de error retorna el mensaje en el array de vista.
     *
     * @return array{cupones: array, mensaje_error: string, mensaje_exito: string, base_url: string}
     */
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
            }
            if ($mensaje_error === '' && !preg_match('/^[A-Z0-9]+$/', $codigo)) {
                $mensaje_error = "El código del cupón solo puede contener letras y números sin espacios.";
            }
            if ($mensaje_error === '') {
                // Verificar que no exista otro cupón con el mismo código
                $stmt = $this->conn->prepare("SELECT id_cupon FROM cupones WHERE codigo = ?");
                $stmt->bind_param("s", $codigo);
                $stmt->execute();
                $num_rows = $stmt->get_result()->num_rows;
                $stmt->close();
                if ($num_rows > 0) {
                    $mensaje_error = "Error: El código '$codigo' ya está registrado.";
                }
            }
            if ($mensaje_error === '') {
                // Insertar en Base de Datos
                $stmt_insert = $this->conn->prepare("INSERT INTO cupones (codigo, porcentaje_descuento, fecha_expiracion, estado) VALUES (?, ?, ?, 'activo')");
                $stmt_insert->bind_param("sds", $codigo, $porcentaje, $fecha_expiracion);
                $ok = $stmt_insert->execute();
                $stmt_insert->close();
                if ($ok) {
                    $_SESSION['mensaje_exito'] = "Cupón '$codigo' creado exitosamente.";
                    header("Location: ?page=admin_cupones");
                    exit;
                }
                $mensaje_error = "Error al crear cupón en la BD: " . $this->conn->error;
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

    /**
     * Elimina permanentemente un cupón de la BD y redirige a la lista de cupones.
     * Establece mensaje flash de éxito o error en sesión según el resultado del DELETE.
     * Siempre termina con header()+exit (no retorna a quien la llama).
     *
     * @param int $id ID del cupón a eliminar (desde GET 'id' validado en el router)
     * @return void
     */
    public function eliminar($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM cupones WHERE id_cupon = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION[$ok ? 'mensaje_exito' : 'mensaje_error'] = $ok
            ? "Cupón eliminado correctamente."
            : "Error al eliminar el cupón.";
        header("Location: ?page=admin_cupones");
        exit;
    }

    /**
     * Cambia el estado de un cupón entre 'activo' e 'inactivo' sin eliminarlo.
     * Permite reactivar un cupón vencido o pausar uno activo temporalmente.
     * Siempre termina con header()+exit.
     *
     * @param int    $id     ID del cupón a modificar
     * @param string $estado Nuevo estado: 'activo' o 'inactivo'
     * @return void
     */
    public function cambiarEstado($id, $estado)
    {
        $stmt = $this->conn->prepare("UPDATE cupones SET estado = ? WHERE id_cupon = ?");
        $stmt->bind_param("si", $estado, $id);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION[$ok ? 'mensaje_exito' : 'mensaje_error'] = $ok
            ? "Estado actualizado a $estado."
            : "Error al actualizar estado.";
        header("Location: ?page=admin_cupones");
        exit;
    }
}
?>