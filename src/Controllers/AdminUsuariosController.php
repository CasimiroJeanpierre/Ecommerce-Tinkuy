<?php
// src/Controllers/AdminUsuariosController.php

class AdminUsuariosController
{

    private $conn;

    // Definimos los roles de tu código
    private const ROL_ADMIN = 1;
    private const ROL_VENDEDOR = 2;
    private const ROL_CLIENTE = 3;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    /**
     * Muestra la lista de usuarios y maneja las acciones (Eliminar Cliente, Desactivar Vendedor)
     */
    public function listarUsuarios()
    {

        // 1. Seguridad
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

        // 2. Mensajes flash
        $mensaje_error = $_SESSION['mensaje_error'] ?? null;
        $mensaje_exito = $_SESSION['mensaje_exito'] ?? null;
        unset($_SESSION['mensaje_error'], $_SESSION['mensaje_exito']);

        // 3. Lógica de Acciones (GET)
        try {
            // --- ACCIÓN A: Eliminar Cliente (Tu lógica de usuarios.php) ---
            if (isset($_GET['eliminar_id'])) {
                $id_a_eliminar = (int) $_GET['eliminar_id'];
                if ($id_a_eliminar === $_SESSION['usuario_id']) {
                    $_SESSION['mensaje_error'] = "No puedes desactivar tu propia cuenta.";
                } else {
                    $stmt_check = $this->conn->prepare("SELECT id_rol FROM usuarios WHERE id_usuario = ?");
                    $stmt_check->bind_param("i", $id_a_eliminar);
                    $stmt_check->execute();
                    $usuario_check = $stmt_check->get_result()->fetch_assoc();

                    if (!$usuario_check || (int) $usuario_check['id_rol'] !== self::ROL_CLIENTE) {
                        $_SESSION['mensaje_error'] = "Solo se pueden desactivar clientes con esta acción.";
                    } else {
                        $stmt_del = $this->conn->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ?");
                        $stmt_del->bind_param("i", $id_a_eliminar);
                        $stmt_del->execute();
                        $_SESSION['mensaje_exito'] = "Cuenta de cliente desactivada correctamente (Baja Lógica).";
                    }
                }
                header('Location: ?page=admin_usuarios');
                exit;
            }

            // --- ACCIÓN B: Desactivar Vendedor (¡Tu nueva lógica!) ---
            if (isset($_GET['desactivar_id'])) {
                $id_a_desactivar = (int) $_GET['desactivar_id'];
                if ($id_a_desactivar === $_SESSION['usuario_id']) {
                    $_SESSION['mensaje_error'] = "No puedes desactivar tu propia cuenta.";
                } else {
                    $this->conn->begin_transaction();
                    try {
                        // 1. Desactivar al Vendedor
                        $rol_vendedor = self::ROL_VENDEDOR; // <-- Variable Creada
                        $stmt_user = $this->conn->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ? AND id_rol = ?");
                        $stmt_user->bind_param("ii", $id_a_desactivar, $rol_vendedor); // <-- ¡Variable USADA!
                        $stmt_user->execute();

                        // 2. Desactivar TODOS sus productos
                        $stmt_prods = $this->conn->prepare("UPDATE productos SET estado = 'inactivo' WHERE id_vendedor = ?");
                        $stmt_prods->bind_param("i", $id_a_desactivar);
                        $stmt_prods->execute();

                        $this->conn->commit();
                        $_SESSION['mensaje_exito'] = "Vendedor desactivado. Todos sus productos han sido ocultados.";
                    } catch (Exception $e) {
                        $this->conn->rollback();
                        $_SESSION['mensaje_error'] = "Error en la transacción: " . $e->getMessage();
                    }
                }
                header('Location: ?page=admin_usuarios');
                exit;
            }

            // --- ACCIÓN C: Reactivar Vendedor ---
            if (isset($_GET['reactivar_id'])) {
                $id_a_reactivar = (int) $_GET['reactivar_id'];

                $this->conn->begin_transaction();
                try {
                    // 1. Reactivar al Usuario (Ya sea Cliente o Vendedor)
                    $stmt_user = $this->conn->prepare("UPDATE usuarios SET estado = 'activo' WHERE id_usuario = ?");
                    $stmt_user->bind_param("i", $id_a_reactivar);
                    $stmt_user->execute();

                    // 2. Si es vendedor, reactivar TODOS sus productos automáticamente
                    $stmt_prods = $this->conn->prepare("UPDATE productos SET estado = 'activo' WHERE id_vendedor = ?");
                    $stmt_prods->bind_param("i", $id_a_reactivar);
                    $stmt_prods->execute();
                    $productos_reactivados = $stmt_prods->affected_rows;

                    $this->conn->commit();
                    $_SESSION['mensaje_exito'] = "Usuario reactivado exitosamente.";
                } catch (Exception $e) {
                    $this->conn->rollback();
                    $_SESSION['mensaje_error'] = "Error al reactivar vendedor: " . $e->getMessage();
                }
                header('Location: ?page=admin_usuarios');
                exit;
            }

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1451) {
                $_SESSION['mensaje_error'] = "No se puede eliminar: el cliente tiene pedidos asociados.";
            } else {
                $_SESSION['mensaje_error'] = "Error de BD: " . $e->getMessage();
            }
            header('Location: ?page=admin_usuarios');
            exit;
        }

        // 4. Lógica de Visualización (GET)
        $query_usuarios = "SELECT u.id_usuario, u.usuario, u.email, u.fecha_registro, u.id_rol, r.nombre_rol, p.nombres, p.apellidos, u.estado 
                           FROM usuarios u 
                           JOIN roles r ON u.id_rol = r.id_rol 
                           LEFT JOIN perfiles p ON u.id_usuario=p.id_usuario 
                           ORDER BY u.id_rol, u.fecha_registro DESC";
        $resultado_usuarios = $this->conn->query($query_usuarios);
        $usuarios = $resultado_usuarios->fetch_all(MYSQLI_ASSOC);

        // 5. Devolver datos
        return [
            'nombre_admin' => $nombre_admin,
            'usuarios' => $usuarios,
            'mensaje_error' => $mensaje_error,
            'mensaje_exito' => $mensaje_exito,
            'id_usuario_actual' => $_SESSION['usuario_id'] // Para la lógica de "Yo"
        ];
    }

    /**
     * Muestra y procesa la página "Crear Usuario"
     */
    public function crearUsuario()
    {
        // 1. Seguridad
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
        $mensaje_error = "";
        $mensaje_exito = "";
        $post_data = $_POST; // Para repoblar el formulario

        // 2. Lógica POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = strip_tags(trim($_POST['usuario'] ?? ''));
            $email = strip_tags(trim($_POST['email'] ?? ''));
            $clave = $_POST['clave'] ?? '';
            $nombres = strip_tags(trim($_POST['nombres'] ?? ''));
            $apellidos = strip_tags(trim($_POST['apellidos'] ?? ''));
            $telefono = strip_tags(trim($_POST['telefono'] ?? ''));
            $id_rol = (int) ($_POST['id_rol'] ?? 0);

            $roles_permitidos = [self::ROL_ADMIN, self::ROL_VENDEDOR];
            $nombre_rol = match ($id_rol) { 1 => 'Admin', 2 => 'Vendedor', default => 'Inválido'};

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

                $stmt_usuario = $this->conn->prepare("INSERT INTO usuarios (id_rol, usuario, email, clave_hash) VALUES (?, ?, ?, ?)");
                $stmt_usuario->bind_param("isss", $id_rol, $usuario, $email, $clave_hash);
                $stmt_usuario->execute();
                $nuevo_usuario_id = $this->conn->insert_id;

                $stmt_perfil = $this->conn->prepare("INSERT INTO perfiles (id_usuario, nombres, apellidos, telefono) VALUES (?, ?, ?, ?)");
                $telefono_a_insertar = $telefono ?: NULL;
                $stmt_perfil->bind_param("isss", $nuevo_usuario_id, $nombres, $apellidos, $telefono_a_insertar);
                $stmt_perfil->execute();

                $this->conn->commit();
                $mensaje_exito = "✅ Usuario '$usuario' creado exitosamente con rol $nombre_rol!";
                $post_data = []; // Limpiar formulario

            } catch (mysqli_sql_exception $e) {
                $this->conn->rollback();
                if ($e->getCode() == 1062) {
                    $mensaje_error = "El usuario o email ya existe.";
                } else {
                    $mensaje_error = "Error al crear usuario: " . $e->getMessage();
                }
            } catch (Exception $e) {
                $mensaje_error = $e->getMessage();
            }
        }

        // 3. Devolver datos (para la vista/formulario)
        return [
            'nombre_admin' => $nombre_admin,
            'mensaje_error' => $mensaje_error,
            'mensaje_exito' => $mensaje_exito,
            'post_data' => $post_data // Para repoblar el formulario
        ];
    }
}
?>