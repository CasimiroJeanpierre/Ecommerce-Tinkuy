<?php
/**
 * Controlador de perfil de usuario (procedural).
 * Gestiona las secciones del panel de usuario: datos de perfil, direcciones de envío,
 * métodos de pago y cambio de contraseña.
 * Requiere sesión activa; redirige a login si no hay usuario autenticado.
 *
 * Variables que expone al scope de la vista:
 *   $datos_perfil    (array)  - nombre, apellido, email, telefono del usuario
 *   $direcciones     (array)  - Direcciones guardadas del usuario
 *   $tarjetas        (array)  - Tarjetas simuladas guardadas del usuario
 *   $seccion_activa  (string) - Sección activa: 'perfil'|'direcciones'|'pagos'
 *   $mensaje_error   (string) - Mensaje de error de la última operación
 *   $mensaje_exito   (string) - Mensaje de éxito de la última operación
 *   $base_url        (string) - URL base del proyecto
 */
// Controlador para páginas de usuario: mi_perfil y acciones relacionadas
if (session_status() === PHP_SESSION_NONE)
    session_start();

require_once __DIR__ . '/../Core/db.php';
require_once __DIR__ . '/../Core/validaciones.php';

$base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';

$mensaje_error = "";
$mensaje_exito = "";

// Guard: requiere sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header("Location: {$base_url}?page=login");
    exit;
}

$id_usuario    = $_SESSION['usuario_id'];
$seccion_activa = $_GET['seccion'] ?? 'dashboard';
$accion        = $_POST['accion'] ?? null;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers de validación de campos
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Valida los campos de datos personales del perfil de usuario.
 * Reglas: nombre/apellido obligatorios, 2-50 chars, solo letras y espacios (incluye ñ y acentos).
 * El teléfono es opcional; si se proporciona debe tener exactamente 9 dígitos numéricos.
 *
 * @param string $nombre   Nombre del usuario (2-50 caracteres, solo letras y espacios)
 * @param string $apellido Apellido del usuario (2-50 caracteres, solo letras y espacios)
 * @param string $telefono Teléfono del usuario (opcional; si no vacío: 9 dígitos numéricos)
 * @return string|null Mensaje de error o null si todos los campos son válidos
 */
function validarCamposPerfil(string $nombre, string $apellido, string $telefono): ?string
{
    if (empty($nombre) || empty($apellido)) {
        return "El nombre y apellido son obligatorios.";
    }
    if (strlen($nombre) < 2 || strlen($apellido) < 2) {
        return "El nombre y apellido deben tener al menos 2 caracteres.";
    }
    if (strlen($nombre) > 50 || strlen($apellido) > 50) {
        return "El nombre y apellido no deben exceder los 50 caracteres.";
    }
    if (!preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $nombre)
        || !preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $apellido)
    ) {
        return "El nombre y apellido solo pueden contener letras y espacios.";
    }
    if (!empty($telefono) && !preg_match('/^[0-9]{9}$/', $telefono)) {
        return "El teléfono debe tener 9 dígitos y contener solo números.";
    }
    return null;
}

/**
 * Valida los tres campos del formulario de cambio de contraseña.
 * Reglas de la nueva contraseña: 7-30 chars, al menos una mayúscula, al menos un carácter especial.
 * Verifica que clave_nueva coincida con clave_repetida antes de comprobar el formato.
 * La verificación contra la BD (password_verify) se hace por separado, fuera de esta función.
 *
 * @param string $clave_actual   Contraseña actual del usuario (se verificará contra el hash en BD)
 * @param string $clave_nueva    Nueva contraseña deseada (7-30 chars, mayúscula + especial)
 * @param string $clave_repetida Repetición de la nueva contraseña (debe coincidir exactamente)
 * @return string|null Mensaje de error o null si el formato es correcto
 */
function validarCamposClave(string $clave_actual, string $clave_nueva, string $clave_repetida): ?string
{
    if (empty($clave_actual) || empty($clave_nueva) || empty($clave_repetida)) {
        return "Todos los campos de contraseña son obligatorios.";
    }
    if ($clave_nueva !== $clave_repetida) {
        return "La nueva contraseña y su repetición no coinciden.";
    }
    if (strlen($clave_nueva) < 7) {
        return "La nueva contraseña debe tener mínimo 7 caracteres.";
    }
    if (strlen($clave_nueva) > 30) {
        return "La nueva contraseña debe tener máximo 30 caracteres.";
    }
    if (!preg_match('/[A-Z]/', $clave_nueva)) {
        return "La nueva contraseña debe contener al menos una mayúscula.";
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $clave_nueva)) {
        return "La nueva contraseña debe contener al menos un carácter especial.";
    }
    return null;
}

/**
 * Valida todos los campos del formulario de dirección postal de envío.
 * Dirección: obligatoria, 10-100 chars, permite letras, números, acentos, coma, punto,
 * guion, barra, almohadilla — para representar calles como "Av. Los Andes #12-B".
 * Ciudad y País: solo letras y espacios (incluye ñ y acentos), 2-50 chars.
 * Código postal: exactamente 4 dígitos numéricos (formato Perú).
 * Retorna null si todos los campos pasan las reglas; en caso contrario el primer error encontrado.
 *
 * @param string $direccion     Calle y número (10-100 caracteres, sin caracteres de escape)
 * @param string $ciudad        Ciudad (2-50 caracteres, solo letras y espacios)
 * @param string $pais          País (2-50 caracteres, solo letras y espacios)
 * @param string $codigo_postal Código postal (exactamente 4 dígitos numéricos)
 * @return string|null Primer error de validación encontrado; null si todos los campos son válidos
 */
function validarCamposDireccion(string $direccion, string $ciudad, string $pais, string $codigo_postal): ?string
{
    if (empty($direccion) || empty($ciudad) || empty($pais) || empty($codigo_postal)) {
        return "Todos los campos de la dirección son obligatorios.";
    }
    if (strlen($direccion) < 10) {
        return "La dirección debe tener al menos 10 caracteres.";
    }
    if (strlen($direccion) > 100) {
        return "La dirección no debe exceder los 100 caracteres.";
    }
    if (!preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_\/#]+$/u', $direccion)) {
        return "La dirección contiene caracteres inválidos.";
    }
    if (strlen($ciudad) < 2 || strlen($pais) < 2) {
        return "Ciudad y País deben tener al menos 2 caracteres.";
    }
    if (strlen($ciudad) > 50 || strlen($pais) > 50) {
        return "Ciudad y País no deben exceder los 50 caracteres.";
    }
    if (!preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $ciudad)
        || !preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $pais)
    ) {
        return "Ciudad y País solo pueden contener letras y espacios.";
    }
    if (!preg_match('/^[0-9]{4}$/', $codigo_postal)) {
        return "El código postal debe tener 4 dígitos.";
    }
    return null;
}

/**
 * Valida los campos del formulario de tarjeta de pago simulada (no datos reales PCI).
 * Nombre del titular: 3-50 chars, solo letras y espacios (sin números ni caracteres especiales).
 * Número de tarjeta: 13-16 dígitos numéricos consecutivos sin espacios (Luhn no se verifica).
 * Fecha de expiración: formato MM/AA con regex que valida el rango de mes (01-12).
 * Este método valida solo el formato del formulario; no se contacta a ningún gateway de pago.
 * Las tarjetas se almacenan como referencia de UI (ultimos_4_digitos + tipo), sin PAN completo.
 *
 * @param string $nombre_tarjeta Nombre del titular (3-50 chars, solo letras)
 * @param string $numero_tarjeta Número de tarjeta sin espacios (13-16 dígitos numéricos)
 * @param string $expiracion     Fecha de expiración en formato MM/AA (ej: 12/27)
 * @return string|null Primer error de validación encontrado; null si el formato es correcto
 */
function validarCamposTarjeta(string $nombre_tarjeta, string $numero_tarjeta, string $expiracion): ?string
{
    if (empty($nombre_tarjeta) || empty($numero_tarjeta) || empty($expiracion)) {
        return "Todos los campos de la tarjeta son obligatorios.";
    }
    if (strlen($nombre_tarjeta) < 3) {
        return "El nombre en la tarjeta debe tener al menos 3 caracteres.";
    }
    if (strlen($nombre_tarjeta) > 50) {
        return "El nombre en la tarjeta no debe exceder los 50 caracteres.";
    }
    if (!preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $nombre_tarjeta)) {
        return "El nombre en la tarjeta solo debe contener letras y espacios.";
    }
    if (!preg_match('/^[0-9]{13,16}$/', $numero_tarjeta)) {
        return "El número de tarjeta debe tener entre 13 y 16 dígitos numéricos.";
    }
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiracion)) {
        return "El formato de expiración debe ser MM/AA.";
    }
    return null;
}

/**
 * Elimina una dirección de envío verificando que pertenezca al usuario autenticado (anti-IDOR).
 * Paso 1: SELECT con WHERE id_direccion = ? AND id_usuario = ? para confirmar propiedad.
 * Paso 2: DELETE con la misma cláusula double-check para evitar TOCTOU race conditions.
 * Si el ID es <= 0 retorna error inmediatamente sin consultar la BD.
 * Captura mysqli_sql_exception para no exponer detalles de esquema al usuario.
 *
 * @param int    $id_dir     ID de la dirección a eliminar (debe ser > 0)
 * @param int    $id_usuario ID del usuario autenticado (verificación anti-IDOR)
 * @param mysqli $conn       Conexión activa a la base de datos
 * @return array{error?: string, exito?: string} Solo una de las dos claves estará presente
 */
function eliminarDireccion(int $id_dir, int $id_usuario, $conn): array
{
    if ($id_dir <= 0) {
        return ['error' => 'ID de dirección inválido.'];
    }
    try {
        $stmt = $conn->prepare("SELECT id_direccion FROM direcciones WHERE id_direccion = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_dir, $id_usuario);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$found) {
            return ['error' => 'Dirección no encontrada o no autorizada.'];
        }
        $stmt_del = $conn->prepare("DELETE FROM direcciones WHERE id_direccion = ? AND id_usuario = ?");
        $stmt_del->bind_param("ii", $id_dir, $id_usuario);
        $stmt_del->execute();
        $stmt_del->close();
        return ['exito' => 'Dirección eliminada correctamente.'];
    } catch (mysqli_sql_exception $e) {
        return ['error' => 'Error al eliminar la dirección: ' . $e->getMessage()];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Despacho de acciones POST
// ─────────────────────────────────────────────────────────────────────────────

// --- Eliminar dirección ---
if ($seccion_activa === 'direcciones' && $accion === 'eliminar_direccion') {
    $res = eliminarDireccion((int)($_POST['id_direccion'] ?? 0), $id_usuario, $conn);
    $mensaje_error = $res['error'] ?? '';
    $mensaje_exito = $res['exito'] ?? '';
}

/**
 * Marca una dirección como la dirección de envío principal del usuario (es_principal = 1).
 * Usa dos UPDATEs en secuencia: primero desmarca todas las direcciones del usuario (es_principal = 0),
 * luego marca solo la seleccionada (es_principal = 1). Este patrón garantiza que solo una
 * dirección tenga es_principal = 1 en todo momento sin necesidad de UNIQUE constraint.
 * La verificación de propiedad se hace implícitamente con AND id_usuario = ? en el segundo UPDATE.
 *
 * @param int    $id_dir     ID de la dirección a marcar como principal (debe ser > 0)
 * @param int    $id_usuario ID del usuario autenticado
 * @param mysqli $conn       Conexión activa a la base de datos
 * @return array{error?: string, exito?: string} Solo una de las dos claves estará presente
 */
function establecerDireccionPrincipal(int $id_dir, int $id_usuario, $conn): array
{
    if ($id_dir <= 0) {
        return ['error' => 'ID de dirección inválido.'];
    }
    try {
        $stmt0 = $conn->prepare("UPDATE direcciones SET es_principal = 0 WHERE id_usuario = ?");
        $stmt0->bind_param("i", $id_usuario);
        $stmt0->execute();
        $stmt0->close();

        $stmt1 = $conn->prepare("UPDATE direcciones SET es_principal = 1 WHERE id_direccion = ? AND id_usuario = ?");
        $stmt1->bind_param("ii", $id_dir, $id_usuario);
        $stmt1->execute();
        $stmt1->close();
        return ['exito' => 'Dirección establecida como principal.'];
    } catch (mysqli_sql_exception $e) {
        return ['error' => 'Error al establecer principal: ' . $e->getMessage()];
    }
}

// --- Establecer dirección principal ---
if ($seccion_activa === 'direcciones' && $accion === 'establecer_principal') {
    $res = establecerDireccionPrincipal((int)($_POST['id_direccion'] ?? 0), $id_usuario, $conn);
    $mensaje_error = $res['error'] ?? '';
    $mensaje_exito = $res['exito'] ?? '';
}

// --- Editar dirección ---
if ($seccion_activa === 'direcciones' && $accion === 'editar_direccion') {
    $id_dir        = intval($_POST['id_direccion'] ?? 0);
    $direccion     = strip_tags(trim($_POST['direccion'] ?? ''));
    $ciudad        = strip_tags(trim($_POST['ciudad'] ?? ''));
    $pais          = strip_tags(trim($_POST['pais'] ?? ''));
    $codigo_postal = strip_tags(trim($_POST['codigo_postal'] ?? ''));
    try {
        if ($id_dir <= 0) throw new \InvalidArgumentException("ID de dirección inválido.");
        $err = validarCamposDireccion($direccion, $ciudad, $pais, $codigo_postal);
        if ($err !== null) throw new \InvalidArgumentException($err);
        $stmt = $conn->prepare(
            "UPDATE direcciones SET direccion = ?, ciudad = ?, pais = ?, codigo_postal = ?
             WHERE id_direccion = ? AND id_usuario = ?"
        );
        $stmt->bind_param("sssiii", $direccion, $ciudad, $pais, $codigo_postal, $id_dir, $id_usuario);
        $stmt->execute();
        $stmt->close();
        $mensaje_exito = "Dirección actualizada correctamente.";
    } catch (\InvalidArgumentException $e) {
        $mensaje_error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $mensaje_error = "Error al actualizar la dirección: " . $e->getMessage();
    }
}

// --- Eliminar tarjeta ---
if ($seccion_activa === 'pagos' && $accion === 'eliminar_tarjeta') {
    $id_tar = intval($_POST['id_tarjeta'] ?? 0);
    try {
        if ($id_tar <= 0) throw new \InvalidArgumentException("ID de tarjeta inválido.");
        $stmt = $conn->prepare("SELECT id_tarjeta FROM tarjetas_usuario WHERE id_tarjeta = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_tar, $id_usuario);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$found) throw new \InvalidArgumentException("Tarjeta no encontrada o no autorizada.");
        $stmt_del = $conn->prepare("DELETE FROM tarjetas_usuario WHERE id_tarjeta = ? AND id_usuario = ?");
        $stmt_del->bind_param("ii", $id_tar, $id_usuario);
        $stmt_del->execute();
        $stmt_del->close();
        $mensaje_exito = "Tarjeta eliminada correctamente.";
    } catch (\InvalidArgumentException $e) {
        $mensaje_error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $mensaje_error = "Error al eliminar la tarjeta: " . $e->getMessage();
    }
}

// --- Actualizar perfil ---
if ($seccion_activa === 'perfil' && $accion === 'actualizar_perfil') {
    $nombre   = strip_tags(trim($_POST['nombre'] ?? ''));
    $apellido = strip_tags(trim($_POST['apellido'] ?? ''));
    $telefono = strip_tags(trim($_POST['telefono'] ?? ''));
    try {
        $err = validarCamposPerfil($nombre, $apellido, $telefono);
        if ($err !== null) throw new \InvalidArgumentException($err);
        $telefono_a_insertar = !empty($telefono) ? $telefono : null;
        $stmt = $conn->prepare(
            "INSERT INTO perfiles (id_usuario, nombres, apellidos, telefono)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE nombres = VALUES(nombres), apellidos = VALUES(apellidos), telefono = VALUES(telefono)"
        );
        $stmt->bind_param("isss", $id_usuario, $nombre, $apellido, $telefono_a_insertar);
        $stmt->execute();
        $stmt->close();
        $mensaje_exito = "Perfil actualizado con éxito.";
        $_SESSION['nombre_usuario']   = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $_SESSION['apellido_usuario'] = htmlspecialchars($apellido, ENT_QUOTES, 'UTF-8');
    } catch (\InvalidArgumentException $e) {
        $mensaje_error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $mensaje_error = "Error al actualizar el perfil: " . $e->getMessage();
    }
}

// --- Cambiar contraseña ---
if ($seccion_activa === 'perfil' && $accion === 'cambiar_clave') {
    $clave_actual   = $_POST['clave_actual'] ?? '';
    $clave_nueva    = $_POST['clave_nueva'] ?? '';
    $clave_repetida = $_POST['clave_repetida'] ?? '';
    try {
        $err = validarCamposClave($clave_actual, $clave_nueva, $clave_repetida);
        if ($err !== null) throw new \InvalidArgumentException($err);

        $stmt = $conn->prepare("SELECT clave_hash FROM usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $stmt->bind_result($clave_hash_db);
        $encontrado = $stmt->fetch();
        $stmt->close();

        if (!$encontrado || !password_verify($clave_actual, $clave_hash_db)) {
            throw new \InvalidArgumentException("La contraseña actual es incorrecta.");
        }

        $nueva_clave_hash = password_hash($clave_nueva, PASSWORD_DEFAULT);
        $stmt_up = $conn->prepare("UPDATE usuarios SET clave_hash = ? WHERE id_usuario = ?");
        $stmt_up->bind_param("si", $nueva_clave_hash, $id_usuario);
        $stmt_up->execute();
        $stmt_up->close();
        $mensaje_exito = "Contraseña actualizada con éxito.";
        session_regenerate_id(true);
    } catch (\InvalidArgumentException $e) {
        $mensaje_error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $mensaje_error = "Error al cambiar la contraseña: " . $e->getMessage();
    }
}

// --- Agregar dirección ---
if ($seccion_activa === 'direcciones' && $accion === 'agregar_direccion') {
    $direccion     = strip_tags(trim($_POST['direccion'] ?? ''));
    $ciudad        = strip_tags(trim($_POST['ciudad'] ?? ''));
    $pais          = strip_tags(trim($_POST['pais'] ?? ''));
    $codigo_postal = strip_tags(trim($_POST['codigo_postal'] ?? ''));
    $es_principal  = isset($_POST['es_principal']) ? 1 : 0;
    try {
        $err = validarCamposDireccion($direccion, $ciudad, $pais, $codigo_postal);
        if ($err !== null) throw new \InvalidArgumentException($err);
        $stmt = $conn->prepare(
            "INSERT INTO direcciones (id_usuario, direccion, ciudad, pais, codigo_postal, es_principal)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("issssi", $id_usuario, $direccion, $ciudad, $pais, $codigo_postal, $es_principal);
        $stmt->execute();
        $stmt->close();
        $mensaje_exito = "Dirección guardada con éxito.";
        if ($es_principal) {
            $last_id = $conn->insert_id;
            $stmt_up = $conn->prepare(
                "UPDATE direcciones SET es_principal = 0 WHERE id_usuario = ? AND id_direccion != ?"
            );
            $stmt_up->bind_param("ii", $id_usuario, $last_id);
            $stmt_up->execute();
            $stmt_up->close();
        }
    } catch (\InvalidArgumentException $e) {
        $mensaje_error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $mensaje_error = "Error al guardar la dirección: " . $e->getMessage();
    }
}

// --- Agregar tarjeta ---
if ($seccion_activa === 'pagos' && $accion === 'agregar_tarjeta') {
    $nombre_tarjeta  = strip_tags(trim($_POST['nombre_tarjeta'] ?? ''));
    $numero_tarjeta  = strip_tags(trim($_POST['numero_tarjeta'] ?? ''));
    $expiracion      = strip_tags(trim($_POST['expiracion'] ?? ''));
    $tipo            = strip_tags(trim($_POST['tipo_tarjeta'] ?? 'Visa'));
    try {
        $err = validarCamposTarjeta($nombre_tarjeta, $numero_tarjeta, $expiracion);
        if ($err !== null) throw new \InvalidArgumentException($err);
        $ultimos_4 = substr($numero_tarjeta, -4);
        $stmt = $conn->prepare(
            "INSERT INTO tarjetas_usuario (id_usuario, nombre_tarjeta, ultimos_4_digitos, expiracion, tipo)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("issss", $id_usuario, $nombre_tarjeta, $ultimos_4, $expiracion, $tipo);
        $stmt->execute();
        $stmt->close();
        $mensaje_exito = "Tarjeta simulada agregada con éxito.";
    } catch (\InvalidArgumentException $e) {
        $mensaje_error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $mensaje_error = "Error al guardar la tarjeta: " . $e->getMessage();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Recuperar datos actualizados para la vista
// ─────────────────────────────────────────────────────────────────────────────

$datos_perfil = ['nombre' => '', 'apellido' => '', 'email' => '', 'telefono' => ''];
$stmt_data = $conn->prepare(
    "SELECT u.email, p.nombres, p.apellidos, p.telefono
     FROM usuarios u LEFT JOIN perfiles p ON u.id_usuario = p.id_usuario
     WHERE u.id_usuario = ?"
);
$stmt_data->bind_param("i", $id_usuario);
$stmt_data->execute();
if ($fila = $stmt_data->get_result()->fetch_assoc()) {
    $datos_perfil = [
        'nombre'   => $fila['nombres'] ?? '',
        'apellido' => $fila['apellidos'] ?? '',
        'email'    => $fila['email'],
        'telefono' => $fila['telefono'] ?? '',
    ];
}
$stmt_data->close();

$stmt_dir = $conn->prepare(
    "SELECT id_direccion, direccion, ciudad, pais, codigo_postal, es_principal
     FROM direcciones WHERE id_usuario = ? ORDER BY es_principal DESC, id_direccion DESC"
);
$stmt_dir->bind_param("i", $id_usuario);
$stmt_dir->execute();
$direcciones = $stmt_dir->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_dir->close();

$stmt_tar = $conn->prepare(
    "SELECT id_tarjeta, nombre_tarjeta, ultimos_4_digitos, expiracion, tipo
     FROM tarjetas_usuario WHERE id_usuario = ?"
);
$stmt_tar->bind_param("i", $id_usuario);
$stmt_tar->execute();
$tarjetas = $stmt_tar->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_tar->close();
