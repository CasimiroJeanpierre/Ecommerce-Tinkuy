<?php
// Controlador para páginas de usuario: mi_perfil y acciones relacionadas
if (session_status() === PHP_SESSION_NONE)
    session_start();

require_once __DIR__ . '/../Core/db.php';
require_once __DIR__ . '/../Core/validaciones.php';

// Base URL público del proyecto (usar en vistas para links)
$base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';

$mensaje_error = "";
$mensaje_exito = "";

// Control de acceso
if (!isset($_SESSION['usuario_id'])) {
    header("Location: " . $base_url . "?page=login");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$seccion_activa = $_GET['seccion'] ?? 'dashboard';
$accion = $_POST['accion'] ?? null;

// --- E. eliminar direccion ---
if ($seccion_activa === 'direcciones' && $accion === 'eliminar_direccion') {
    $id_direccion_eliminar = intval($_POST['id_direccion'] ?? 0);
    if ($id_direccion_eliminar <= 0) {
        $mensaje_error = "ID de dirección inválido.";
    } else {
        try {
            // Verificar propiedad
            $stmt_check = $conn->prepare("SELECT id_direccion, es_principal FROM direcciones WHERE id_direccion = ? AND id_usuario = ?");
            $stmt_check->bind_param("ii", $id_direccion_eliminar, $id_usuario);
            $stmt_check->execute();
            $res = $stmt_check->get_result()->fetch_assoc();
            if (!$res) {
                $mensaje_error = "Dirección no encontrada o no autorizada.";
            } else {
                // Si es principal, al eliminar no hacemos reasignación automática aquí (opcional)
                $stmt_del = $conn->prepare("DELETE FROM direcciones WHERE id_direccion = ? AND id_usuario = ?");
                $stmt_del->bind_param("ii", $id_direccion_eliminar, $id_usuario);
                $stmt_del->execute();
                $mensaje_exito = "Dirección eliminada correctamente.";
            }
        } catch (mysqli_sql_exception $e) {
            $mensaje_error = "Error al eliminar la dirección: " . $e->getMessage();
        }
    }
}

// --- F. establecer direccion principal ---
if ($seccion_activa === 'direcciones' && $accion === 'establecer_principal') {
    $id_direccion_principal = intval($_POST['id_direccion'] ?? 0);
    if ($id_direccion_principal <= 0) {
        $mensaje_error = "ID de dirección inválido.";
    } else {
        try {
            // Desactivar otras y activar esta
            $stmt0 = $conn->prepare("UPDATE direcciones SET es_principal = 0 WHERE id_usuario = ?");
            $stmt0->bind_param("i", $id_usuario);
            $stmt0->execute();

            $stmt1 = $conn->prepare("UPDATE direcciones SET es_principal = 1 WHERE id_direccion = ? AND id_usuario = ?");
            $stmt1->bind_param("ii", $id_direccion_principal, $id_usuario);
            $stmt1->execute();
            $mensaje_exito = "Dirección establecida como principal.";
        } catch (mysqli_sql_exception $e) {
            $mensaje_error = "Error al establecer principal: " . $e->getMessage();
        }
    }
}

// --- G. editar direccion ---
if ($seccion_activa === 'direcciones' && $accion === 'editar_direccion') {
    $id_direccion_editar = intval($_POST['id_direccion'] ?? 0);
    $direccion = strip_tags(trim($_POST['direccion'] ?? ''));
    $ciudad = strip_tags(trim($_POST['ciudad'] ?? ''));
    $pais = strip_tags(trim($_POST['pais'] ?? ''));
    $codigo_postal = strip_tags(trim($_POST['codigo_postal'] ?? ''));

    if ($id_direccion_editar <= 0) {
        $mensaje_error = "ID de dirección inválido.";
    } elseif (empty($direccion) || empty($ciudad) || empty($pais) || empty($codigo_postal)) {
        $mensaje_error = "Todos los campos son obligatorios.";
    } elseif (!preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_\/#]+$/u', $direccion)) {
        $mensaje_error = "La dirección contiene caracteres inválidos.";
    } elseif (!preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $ciudad) || !preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $pais)) {
        $mensaje_error = "Ciudad y País solo pueden contener letras y espacios.";
    } elseif (!preg_match('/^[0-9]{4}$/', $codigo_postal)) {
        $mensaje_error = "El código postal debe tener 4 dígitos.";
    } else {
        try {
            $stmt_up = $conn->prepare("UPDATE direcciones SET direccion = ?, ciudad = ?, pais = ?, codigo_postal = ? WHERE id_direccion = ? AND id_usuario = ?");
            $stmt_up->bind_param("sssiii", $direccion, $ciudad, $pais, $codigo_postal, $id_direccion_editar, $id_usuario);
            $stmt_up->execute();
            $mensaje_exito = "Dirección actualizada correctamente.";
        } catch (mysqli_sql_exception $e) {
            $mensaje_error = "Error al actualizar la dirección: " . $e->getMessage();
        }
    }
}

// --- H. eliminar tarjeta ---
if ($seccion_activa === 'pagos' && $accion === 'eliminar_tarjeta') {
    $id_tarjeta_eliminar = intval($_POST['id_tarjeta'] ?? 0);
    if ($id_tarjeta_eliminar <= 0) {
        $mensaje_error = "ID de tarjeta inválido.";
    } else {
        try {
            $stmt_check = $conn->prepare("SELECT id_tarjeta FROM tarjetas_usuario WHERE id_tarjeta = ? AND id_usuario = ?");
            $stmt_check->bind_param("ii", $id_tarjeta_eliminar, $id_usuario);
            $stmt_check->execute();
            $res = $stmt_check->get_result()->fetch_assoc();
            if (!$res) {
                $mensaje_error = "Tarjeta no encontrada o no autorizada.";
            } else {
                $stmt_del = $conn->prepare("DELETE FROM tarjetas_usuario WHERE id_tarjeta = ? AND id_usuario = ?");
                $stmt_del->bind_param("ii", $id_tarjeta_eliminar, $id_usuario);
                $stmt_del->execute();
                $mensaje_exito = "Tarjeta eliminada correctamente.";
            }
        } catch (mysqli_sql_exception $e) {
            $mensaje_error = "Error al eliminar la tarjeta: " . $e->getMessage();
        }
    }
}

// --- A. actualizar perfil ---
if ($seccion_activa === 'perfil' && $accion === 'actualizar_perfil') {
    $nombre = strip_tags(trim($_POST['nombre'] ?? ''));
    $apellido = strip_tags(trim($_POST['apellido'] ?? ''));
    $telefono = strip_tags(trim($_POST['telefono'] ?? ''));

    if (empty($nombre) || empty($apellido)) {
        $mensaje_error = "Error (ID 69): El nombre y apellido son obligatorios.";
    } elseif (strlen($nombre) < 2 || strlen($apellido) < 2) {
        $mensaje_error = "Error (ID 66/126): El nombre y apellido deben tener al menos 2 caracteres.";
    } elseif (strlen($nombre) > 50 || strlen($apellido) > 50) {
        $mensaje_error = "Error (ID 67/127): El nombre y apellido no deben exceder los 50 caracteres.";
    } elseif (!preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $nombre) || !preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $apellido)) {
        $mensaje_error = "Error (ID 68): El nombre y apellido solo pueden contener letras y espacios.";
    } elseif (!empty($telefono) && !preg_match('/^[0-9]{9}$/', $telefono)) {
        $mensaje_error = "Error (ID 36-39): El teléfono debe tener 9 dígitos y contener solo números.";
    } else {
        try {
            $stmt_update = $conn->prepare("\n                INSERT INTO perfiles (id_usuario, nombres, apellidos, telefono)\n                VALUES (?, ?, ?, ?)\n                ON DUPLICATE KEY UPDATE nombres = VALUES(nombres), apellidos = VALUES(apellidos), telefono = VALUES(telefono)\n            ");
            $telefono_a_insertar = !empty($telefono) ? $telefono : NULL;
            $stmt_update->bind_param("isss", $id_usuario, $nombre, $apellido, $telefono_a_insertar);
            $stmt_update->execute();
            $mensaje_exito = "Perfil actualizado con éxito.";
            $_SESSION['nombre_usuario'] = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
            $_SESSION['apellido_usuario'] = htmlspecialchars($apellido, ENT_QUOTES, 'UTF-8');
        } catch (mysqli_sql_exception $e) {
            $mensaje_error = "Error al actualizar el perfil: " . $e->getMessage();
        }
    }
}

// --- B. cambiar contraseña ---
if ($seccion_activa === 'perfil' && $accion === 'cambiar_clave') {
    $clave_actual = $_POST['clave_actual'] ?? '';
    $clave_nueva = $_POST['clave_nueva'] ?? '';
    $clave_repetida = $_POST['clave_repetida'] ?? '';

    if (empty($clave_actual) || empty($clave_nueva) || empty($clave_repetida)) {
        $mensaje_error = "Error (ID 25/87): Todos los campos de contraseña son obligatorios.";
    } elseif ($clave_nueva !== $clave_repetida) {
        $mensaje_error = "Error (ID 85): La nueva contraseña y su repetición no coinciden.";
    } elseif (strlen($clave_nueva) < 7) {
        $mensaje_error = "Error (ID 21/99): La nueva contraseña debe tener mínimo 7 caracteres.";
    } elseif (strlen($clave_nueva) > 30) {
        $mensaje_error = "Error (ID 22/100): La nueva contraseña debe tener máximo 30 caracteres.";
    } elseif (!preg_match('/[A-Z]/', $clave_nueva)) {
        $mensaje_error = "Error (ID 23): La nueva contraseña debe contener al menos una mayúscula.";
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $clave_nueva)) {
        $mensaje_error = "Error (ID 24): La nueva contraseña debe contener al menos un carácter especial.";
    } else {
        try {
            $stmt_check = $conn->prepare("SELECT clave_hash FROM usuarios WHERE id_usuario = ?");
            $stmt_check->bind_param("i", $id_usuario);
            $stmt_check->execute();
            $stmt_check->bind_result($clave_hash_db);

            if ($stmt_check->fetch() && password_verify($clave_actual, $clave_hash_db)) {
                $stmt_check->close();
                $nueva_clave_hash = password_hash($clave_nueva, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE usuarios SET clave_hash = ? WHERE id_usuario = ?");
                $stmt_update->bind_param("si", $nueva_clave_hash, $id_usuario);
                $stmt_update->execute();
                $mensaje_exito = "Contraseña actualizada con éxito.";
                session_regenerate_id(true); // Prevenir Session Fixation tras cambiar la clave
            } else {
                $mensaje_error = "La contraseña actual es incorrecta.";
            }
        } catch (mysqli_sql_exception $e) {
            $mensaje_error = "Error al cambiar la contraseña: " . $e->getMessage();
        }
    }
}

// --- C. agregar direccion ---
if ($seccion_activa === 'direcciones' && $accion === 'agregar_direccion') {
    $direccion = strip_tags(trim($_POST['direccion'] ?? ''));
    $ciudad = strip_tags(trim($_POST['ciudad'] ?? ''));
    $pais = strip_tags(trim($_POST['pais'] ?? ''));
    $codigo_postal = strip_tags(trim($_POST['codigo_postal'] ?? ''));
    $es_principal = isset($_POST['es_principal']) ? 1 : 0;

    if (empty($direccion) || empty($ciudad) || empty($pais) || empty($codigo_postal)) {
        $mensaje_error = "Error (ID 63/69/75/81): Todos los campos de la dirección son obligatorios.";
    } elseif (strlen($direccion) < 10) {
        $mensaje_error = "Error (ID 60): La dirección debe tener al menos 10 caracteres.";
    } elseif (strlen($direccion) > 100) {
        $mensaje_error = "Error (ID 61): La dirección no debe exceder los 100 caracteres.";
    } elseif (!preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_\/#]+$/u', $direccion)) {
        $mensaje_error = "Error (ID 62): La dirección contiene caracteres inválidos.";
    } elseif (strlen($ciudad) < 2 || strlen($pais) < 2) {
        $mensaje_error = "Error (ID 66/72): Ciudad y País deben tener al menos 2 caracteres.";
    } elseif (strlen($ciudad) > 50 || strlen($pais) > 50) {
        $mensaje_error = "Error (ID 67/73): Ciudad y País no deben exceder los 50 caracteres.";
    } elseif (!preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $ciudad) || !preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $pais)) {
        $mensaje_error = "Error (ID 68/74): Ciudad y País solo pueden contener letras y espacios.";
    } elseif (!preg_match('/^[0-9]{4}$/', $codigo_postal)) {
        $mensaje_error = "Error (ID 78-80): El código postal debe tener 4 dígitos y contener solo números.";
    } else {
        try {
            $stmt = $conn->prepare("\n                INSERT INTO direcciones (id_usuario, direccion, ciudad, pais, codigo_postal, es_principal)\n                VALUES (?, ?, ?, ?, ?, ?)\n            ");
            $stmt->bind_param("issssi", $id_usuario, $direccion, $ciudad, $pais, $codigo_postal, $es_principal);
            $stmt->execute();
            $mensaje_exito = "Dirección guardada con éxito.";

            if ($es_principal) {
                $last_id = $conn->insert_id;
                $stmt_update = $conn->prepare("UPDATE direcciones SET es_principal = 0 WHERE id_usuario = ? AND id_direccion != ?");
                $stmt_update->bind_param("ii", $id_usuario, $last_id);
                $stmt_update->execute();
            }
        } catch (mysqli_sql_exception $e) {
            $mensaje_error = "Error al guardar la dirección: " . $e->getMessage();
        }
    }
}

// --- D. agregar tarjeta ---
if ($seccion_activa === 'pagos' && $accion === 'agregar_tarjeta') {
    $nombre_tarjeta = strip_tags(trim($_POST['nombre_tarjeta'] ?? ''));
    $numero_tarjeta = strip_tags(trim($_POST['numero_tarjeta'] ?? ''));
    $expiracion = strip_tags(trim($_POST['expiracion'] ?? ''));
    $tipo = strip_tags(trim($_POST['tipo_tarjeta'] ?? 'Visa'));

    if (empty($nombre_tarjeta) || empty($numero_tarjeta) || empty($expiracion)) {
        $mensaje_error = "Error (ID 45/49): Todos los campos de la tarjeta son obligatorios.";
    } elseif (strlen($nombre_tarjeta) < 3) {
        $mensaje_error = "Error (ID 41/106): El nombre en la tarjeta debe tener al menos 3 caracteres.";
    } elseif (strlen($nombre_tarjeta) > 50) {
        $mensaje_error = "Error (ID 41/107): El nombre en la tarjeta no debe exceder los 50 caracteres.";
    } elseif (!preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $nombre_tarjeta)) {
        $mensaje_error = "Error (ID 42): El nombre en la tarjeta solo debe contener letras y espacios.";
    } elseif (!preg_match('/^[0-9]{13,16}$/', $numero_tarjeta)) {
        $mensaje_error = "Error (ID 41/42): El número de tarjeta debe tener entre 13 y 16 dígitos numéricos.";
    } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiracion)) {
        $mensaje_error = "Error (ID 46): El formato de expiración debe ser MM/AA.";
    } else {
        $ultimos_4_digitos = substr($numero_tarjeta, -4);
        try {
            $stmt = $conn->prepare("\n                INSERT INTO tarjetas_usuario (id_usuario, nombre_tarjeta, ultimos_4_digitos, expiracion, tipo)\n                VALUES (?, ?, ?, ?, ?)\n            ");
            $stmt->bind_param("issss", $id_usuario, $nombre_tarjeta, $ultimos_4_digitos, $expiracion, $tipo);
            $stmt->execute();
            $mensaje_exito = "Tarjeta simulada agregada con éxito.";
        } catch (mysqli_sql_exception $e) {
            $mensaje_error = "Error al guardar la tarjeta: " . $e->getMessage();
        }
    }
}

// Recuperar datos frescos
$datos_perfil = ['nombre' => '', 'apellido' => '', 'email' => '', 'telefono' => ''];
$stmt_data = $conn->prepare("SELECT u.email, p.nombres, p.apellidos, p.telefono FROM usuarios u LEFT JOIN perfiles p ON u.id_usuario = p.id_usuario WHERE u.id_usuario = ?");
$stmt_data->bind_param("i", $id_usuario);
$stmt_data->execute();
$resultado_data = $stmt_data->get_result();
if ($fila = $resultado_data->fetch_assoc()) {
    $datos_perfil['nombre'] = $fila['nombres'] ?? '';
    $datos_perfil['apellido'] = $fila['apellidos'] ?? '';
    $datos_perfil['email'] = $fila['email'];
    $datos_perfil['telefono'] = $fila['telefono'] ?? '';
}

$direcciones = [];
$stmt_dir = $conn->prepare("SELECT id_direccion, direccion, ciudad, pais, codigo_postal, es_principal FROM direcciones WHERE id_usuario = ? ORDER BY es_principal DESC, id_direccion DESC");
$stmt_dir->bind_param("i", $id_usuario);
$stmt_dir->execute();
$direcciones = $stmt_dir->get_result()->fetch_all(MYSQLI_ASSOC);

$tarjetas = [];
$stmt_tar = $conn->prepare("SELECT id_tarjeta, nombre_tarjeta, ultimos_4_digitos, expiracion, tipo FROM tarjetas_usuario WHERE id_usuario = ?");
$stmt_tar->bind_param("i", $id_usuario);
$stmt_tar->execute();
$tarjetas = $stmt_tar->get_result()->fetch_all(MYSQLI_ASSOC);

// NOTA: no cerramos $conn aquí; public/index.php maneja el cierre centralizado

?>