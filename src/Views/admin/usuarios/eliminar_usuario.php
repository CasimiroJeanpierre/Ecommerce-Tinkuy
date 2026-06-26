<?php
/**
 * Controlador procedural de eliminación/desactivación de usuarios (admin).
 * Recibe el ID de usuario a desactivar vía GET, verifica permisos de admin,
 * valida el ID y ejecuta la baja lógica (UPDATE estado='inactivo').
 * No elimina físicamente el registro para preservar el historial de pedidos.
 *
 * Parámetros GET:
 *   id (int) - ID del usuario a desactivar
 *
 * Seguridad:
 *   Verifica $_SESSION['rol'] === 'admin'; redirige al login si no es admin.
 *   Valida el ID con FILTER_VALIDATE_INT antes de ejecutar la consulta.
 *
 * @deprecated Usar AdminUsuariosController::desactivarCliente() a través del router MVC
 *             (?page=admin_usuarios&eliminar_id=N) en lugar de esta ruta directa legacy.
 */
session_start();
include 'db.php';

// === Definir roles (IDs de tu BD) ===
define('ROL_ADMIN', 1);
define('ROL_VENDEDOR', 2);
define('ROL_CLIENTE', 3);

// --- INICIO DE CALIDAD (SEGURIDAD ISO 25010) ---
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    // Si no es admin o no está logueado, lo redirigimos
    header('Location: ../../login.php');
    exit;
}

// 1. Validamos el ID
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['mensaje_error'] = "ID de usuario no válido.";
    header('Location: usuarios.php');
    exit;
}
$id_usuario_a_eliminar = (int) $_GET['id'];

// 2. Seguridad Crítica: Un admin NO puede eliminarse a sí mismo.
if ($id_usuario_a_eliminar === $_SESSION['usuario_id']) {
    $_SESSION['mensaje_error'] = "Error: No puedes eliminar tu propia cuenta de administrador.";
    header('Location: usuarios.php');
    exit;
}
// --- FIN DE CALIDAD (SEGURIDAD) ---


// ======================================================
// 3. VALIDACIÓN CLAVE: SOLO SE PUEDE ELIMINAR CLIENTES (ROL 3)
// ======================================================
try {
    $stmt_check = $conn->prepare("SELECT id_rol, nombre_rol FROM usuarios u JOIN roles r ON u.id_rol = r.id_rol WHERE u.id_usuario = ?");
    $stmt_check->bind_param("i", $id_usuario_a_eliminar);
    $stmt_check->execute();
    $usuario_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    // Si no encuentra el usuario o su rol no es CLIENTE
    if (!$usuario_check) {
        throw new Exception("El usuario no se encontró o ya fue eliminado.");
    }

    if ((int) $usuario_check['id_rol'] !== ROL_CLIENTE) {
        $_SESSION['mensaje_error'] = "ERROR: Solo se puede ELIMINAR usuarios con rol 'Cliente'. Este usuario es '{$usuario_check['nombre_rol']}'.";
        header('Location: usuarios.php');
        exit;
    }

} catch (Exception $e) {
    $_SESSION['mensaje_error'] = "Error de validación de rol: " . $e->getMessage();
    header('Location: usuarios.php');
    exit;
}

// ======================================================
// 4. ELIMINACIÓN (SOLO SI PASÓ LA VALIDACIÓN DE ROL)
// ======================================================
try {
    $stmt = $conn->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario_a_eliminar);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $_SESSION['mensaje_exito'] = "Usuario cliente ID #$id_usuario_a_eliminar desactivado correctamente. ✅";
    } else {
        // Esto solo ocurre si el usuario existía al inicio pero se borró entretanto
        throw new Exception("El usuario no se encontró o no se pudo eliminar.");
    }

    $stmt->close();

} catch (mysqli_sql_exception $e) {

    // Código 1451: Error de llave foránea (RESTRICT)
    if ($e->getCode() == 1451) {
        $_SESSION['mensaje_error'] = "ERROR: No se puede eliminar el cliente ID #$id_usuario_a_eliminar porque tiene registros asociados (ej. pedidos, calificaciones, etc.). ❌";
    } else {
        $_SESSION['mensaje_error'] = "Error de base de datos: " . $e->getMessage();
    }
} finally {
    // 5. Redirigimos de vuelta a la lista
    header('Location: usuarios.php');
    exit;
}
?>