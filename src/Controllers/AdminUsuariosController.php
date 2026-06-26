<?php
// src/Controllers/AdminUsuariosController.php

/**
 * Controlador de administración de usuarios del sistema.
 * Permite listar, desactivar (baja lógica), reactivar y crear usuarios con roles Admin o Vendedor.
 *
 * Reglas de negocio aplicadas:
 *   - Un administrador no puede desactivar su propia cuenta
 *   - Solo se pueden desactivar clientes con la acción 'eliminar'; para vendedores se usa 'desactivar'
 *   - Al desactivar un vendedor, se ocultan automáticamente todos sus productos (transacción)
 *   - Al reactivar un usuario, se reactivan automáticamente sus productos si era vendedor
 *   - La creación de nuevos usuarios admite roles Admin (1) y Vendedor (2) únicamente
 */
class AdminUsuariosController
{

    private $conn;

    private const ROL_ADMIN    = 1;
    private const ROL_VENDEDOR = 2;
    private const ROL_CLIENTE  = 3;

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
     * Si no hay sesión activa redirige a login; si el rol no es 'admin' establece
     * mensaje de error en sesión y redirige al index.
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
     * Desactiva la cuenta de un cliente (baja lógica — no elimina el registro).
     * Verifica que el usuario exista y tenga rol ROL_CLIENTE (3) antes de actualizar.
     * Un administrador no puede desactivar su propia cuenta.
     *
     * @param int $id_a_eliminar ID del usuario a desactivar
     * @return void
     */
    private function desactivarCliente(int $id_a_eliminar): void
    {
        if ($id_a_eliminar === $_SESSION['usuario_id']) {
            $_SESSION['mensaje_error'] = "No puedes desactivar tu propia cuenta.";
            return;
        }

        $stmt_check = $this->conn->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = ?");
        $stmt_check->bind_param("i", $id_a_eliminar);
        $stmt_check->execute();
        $usuario_check = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if (!$usuario_check || (int) $usuario_check['id_rol'] !== self::ROL_CLIENTE) {
            $_SESSION['mensaje_error'] = "Solo se pueden desactivar clientes con esta acción.";
            return;
        }

        $stmt = $this->conn->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ?");
        $stmt->bind_param("i", $id_a_eliminar);
        $stmt->execute();
        $stmt->close();
        $_SESSION['mensaje_exito'] = "Cuenta de cliente desactivada correctamente (Baja Lógica).";
    }

    /**
     * Desactiva un vendedor y oculta todos sus productos activos en una sola transacción.
     * Usa transacción para garantizar consistencia: si falla algún UPDATE, revierte ambos.
     * Un administrador no puede desactivar su propia cuenta de vendedor.
     *
     * @param int $id_a_desactivar ID del vendedor a desactivar
     * @return void
     */
    private function desactivarVendedor(int $id_a_desactivar): void
    {
        if ($id_a_desactivar === $_SESSION['usuario_id']) {
            $_SESSION['mensaje_error'] = "No puedes desactivar tu propia cuenta.";
            return;
        }

        $this->conn->begin_transaction();
        try {
            $rol_vendedor = self::ROL_VENDEDOR;
            $stmt_user = $this->conn->prepare(
                "UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ? AND id_rol = ?"
            );
            $stmt_user->bind_param("ii", $id_a_desactivar, $rol_vendedor);
            $stmt_user->execute();
            $stmt_user->close();

            $stmt_prods = $this->conn->prepare(
                "UPDATE productos SET estado = 'inactivo' WHERE id_vendedor = ?"
            );
            $stmt_prods->bind_param("i", $id_a_desactivar);
            $stmt_prods->execute();
            $stmt_prods->close();

            $this->conn->commit();
            $_SESSION['mensaje_exito'] = "Vendedor desactivado. Todos sus productos han sido ocultados.";
        } catch (Exception $e) {
            $this->conn->rollback();
            $_SESSION['mensaje_error'] = "Error en la transacción: " . $e->getMessage();
        }
    }

    /**
     * Reactiva un usuario (cliente o vendedor) y sus productos en una sola transacción.
     * Ejecuta dos UPDATEs: primero el usuario, luego sus productos (el de productos afecta
     * solo a vendedores; en clientes la segunda consulta no actualiza filas pero no falla).
     *
     * @param int $id_a_reactivar ID del usuario a reactivar
     * @return void
     */
    private function reactivarUsuario(int $id_a_reactivar): void
    {
        $this->conn->begin_transaction();
        try {
            $stmt_user = $this->conn->prepare(
                "UPDATE usuarios SET estado = 'activo' WHERE id_usuario = ?"
            );
            $stmt_user->bind_param("i", $id_a_reactivar);
            $stmt_user->execute();
            $stmt_user->close();

            // Si es vendedor, reactivar sus productos automáticamente
            $stmt_prods = $this->conn->prepare(
                "UPDATE productos SET estado = 'activo' WHERE id_vendedor = ?"
            );
            $stmt_prods->bind_param("i", $id_a_reactivar);
            $stmt_prods->execute();
            $stmt_prods->close();

            $this->conn->commit();
            $_SESSION['mensaje_exito'] = "Usuario reactivado exitosamente.";
        } catch (Exception $e) {
            $this->conn->rollback();
            $_SESSION['mensaje_error'] = "Error al reactivar usuario: " . $e->getMessage();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos públicos (acciones del controlador)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Muestra la lista de todos los usuarios del sistema y despacha las acciones GET.
     * Acciones GET soportadas: eliminar_id (desactivar cliente), desactivar_id (desactivar vendedor),
     * reactivar_id (reactivar cualquier usuario). Todas redirigen al mismo método tras ejecutar.
     * La consulta ORDER BY ordena primero por rol y luego por fecha de registro descendente.
     *
     * @return array{nombre_admin: string, usuarios: array, mensaje_error: string|null,
     *               mensaje_exito: string|null, id_usuario_actual: int}
     */
    public function listarUsuarios(): array
    {
        $this->requerirAdmin();
        $nombre_admin = $_SESSION['usuario'];

        $mensaje_error = $_SESSION['mensaje_error'] ?? null;
        $mensaje_exito = $_SESSION['mensaje_exito'] ?? null;
        unset($_SESSION['mensaje_error'], $_SESSION['mensaje_exito']);

        try {
            if (isset($_GET['eliminar_id'])) {
                $this->desactivarCliente((int) $_GET['eliminar_id']);
                header('Location: ?page=admin_usuarios');
                exit;
            }

            if (isset($_GET['desactivar_id'])) {
                $this->desactivarVendedor((int) $_GET['desactivar_id']);
                header('Location: ?page=admin_usuarios');
                exit;
            }

            if (isset($_GET['reactivar_id'])) {
                $this->reactivarUsuario((int) $_GET['reactivar_id']);
                header('Location: ?page=admin_usuarios');
                exit;
            }

        } catch (mysqli_sql_exception $e) {
            $msg = ($e->getCode() == 1451)
                ? "No se puede eliminar: el cliente tiene pedidos asociados."
                : "Error de BD: " . $e->getMessage();
            $_SESSION['mensaje_error'] = $msg;
            header('Location: ?page=admin_usuarios');
            exit;
        }

        $query_usuarios = "SELECT u.id_usuario, u.usuario, u.email, u.fecha_registro, u.id_rol,
                                  r.nombre_rol, p.nombres, p.apellidos, u.estado
                           FROM usuarios u
                           JOIN roles r ON u.id_rol = r.id_rol
                           LEFT JOIN perfiles p ON u.id_usuario = p.id_usuario
                           ORDER BY u.id_rol, u.fecha_registro DESC";

        $usuarios = $this->conn->query($query_usuarios)->fetch_all(MYSQLI_ASSOC);

        return [
            'nombre_admin'      => $nombre_admin,
            'usuarios'          => $usuarios,
            'mensaje_error'     => $mensaje_error,
            'mensaje_exito'     => $mensaje_exito,
            'id_usuario_actual' => $_SESSION['usuario_id'],
        ];
    }

    /**
     * Muestra y procesa el formulario para crear un nuevo usuario (admin o vendedor).
     * En POST: valida formato de todos los campos, verifica unicidad de usuario/email,
     * hace hash de la contraseña con password_hash y hace INSERT en usuarios + perfiles.
     * En caso de usuario/email duplicado captura error MySQL 1062 y muestra mensaje claro.
     *
     * @return array{nombre_admin: string, mensaje_error: string, mensaje_exito: string,
     *               roles: array, base_url: string, post_data: array}
     */
    public function crearUsuario(): array
    {
        $this->requerirAdmin();
        $nombre_admin  = $_SESSION['usuario'];
        $mensaje_error = "";
        $mensaje_exito = "";
        $post_data     = $_POST;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario   = strip_tags(trim($_POST['usuario'] ?? ''));
            $email     = strip_tags(trim($_POST['email'] ?? ''));
            $clave     = $_POST['clave'] ?? '';
            $nombres   = strip_tags(trim($_POST['nombres'] ?? ''));
            $apellidos = strip_tags(trim($_POST['apellidos'] ?? ''));
            $telefono  = strip_tags(trim($_POST['telefono'] ?? ''));
            $id_rol    = (int) ($_POST['id_rol'] ?? 0);

            $roles_permitidos = [self::ROL_ADMIN, self::ROL_VENDEDOR];
            $nombre_rol       = match ($id_rol) { 1 => 'Admin', 2 => 'Vendedor', default => 'Inválido' };

            if (!in_array($id_rol, $roles_permitidos)) {
                $mensaje_error = "Rol no válido.";
            } else {
                $mensaje_error =
                    validarUsuario($usuario) ??
                    validarEmail($email) ??
                    validarClave($clave) ??
                    validarNombre($nombres, 'El nombre') ??
                    validarNombre($apellidos, 'El apellido') ??
                    validarTelefono($telefono);
            }

            try {
                if ($mensaje_error !== null && $mensaje_error !== '') {
                    throw new Exception($mensaje_error);
                }

                $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
                $this->conn->begin_transaction();

                $stmt_usuario = $this->conn->prepare(
                    "INSERT INTO usuarios (id_rol, usuario, email, clave_hash) VALUES (?, ?, ?, ?)"
                );
                $stmt_usuario->bind_param("isss", $id_rol, $usuario, $email, $clave_hash);
                $stmt_usuario->execute();
                $nuevo_usuario_id = $this->conn->insert_id;
                $stmt_usuario->close();

                $stmt_perfil = $this->conn->prepare(
                    "INSERT INTO perfiles (id_usuario, nombres, apellidos, telefono) VALUES (?, ?, ?, ?)"
                );
                $telefono_a_insertar = $telefono ?: null;
                $stmt_perfil->bind_param("isss", $nuevo_usuario_id, $nombres, $apellidos, $telefono_a_insertar);
                $stmt_perfil->execute();
                $stmt_perfil->close();

                $this->conn->commit();
                $mensaje_exito = "Usuario '{$usuario}' creado exitosamente con rol {$nombre_rol}.";
                $post_data     = [];

            } catch (mysqli_sql_exception $e) {
                $this->conn->rollback();
                $mensaje_error = ($e->getCode() == 1062)
                    ? "El usuario o email ya existe."
                    : "Error al crear usuario: " . $e->getMessage();
            } catch (Exception $e) {
                $mensaje_error = $e->getMessage();
            }
        }

        return compact('nombre_admin', 'mensaje_error', 'mensaje_exito', 'post_data');
    }
}
?>
