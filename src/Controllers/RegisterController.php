<?php
/**
 * Controlador de registro de nuevos clientes (procedural).
 * Implementa el flujo de alta de usuario en tres pasos:
 *   1. validarGuardiasRegistro() — CSRF, reCAPTCHA y aceptación de políticas
 *   2. validarCamposRegistro()   — formato de todos los campos del formulario
 *   3. registrarUsuarioEnBD()    — transacción que inserta en usuarios + perfiles
 *
 * Variables que expone al scope de la vista:
 *   $mensaje_error (string) - Error de validación o de BD (ej. usuario duplicado)
 *   $mensaje_exito (string) - No se usa en registro exitoso (redirige directamente)
 *   $csrf_token    (string) - Token CSRF para proteger el formulario POST
 *   $base_url      (string) - URL base del proyecto
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Core/db.php';
require_once __DIR__ . '/../Core/validaciones.php';
require_once __DIR__ . '/../Core/Security.php';

$mensaje_error = "";
$mensaje_exito = "";
$base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';

const ID_ROL_CLIENTE = 3;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers del formulario de registro
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Valida las protecciones previas al procesamiento del formulario de registro.
 * Comprueba token CSRF, reCAPTCHA v2 y aceptación de políticas de privacidad.
 * Debe ejecutarse antes de cualquier validación de datos o consulta a la BD.
 *
 * @param array $post Datos de $_POST con claves 'csrf_token', 'g-recaptcha-response', 'politicas_privacidad'
 * @return string|null Mensaje de error si falla alguna verificación; null si todas pasan
 */
function validarGuardiasRegistro(array $post): ?string
{
    if (!Security::verificarCSRF($post['csrf_token'] ?? '')) {
        return "Token de seguridad inválido. Recarga la página e intenta de nuevo.";
    }
    if (empty($post['g-recaptcha-response'])) {
        return "Por favor, verifica que no eres un robot (CAPTCHA).";
    }
    if (!Security::verificarRecaptcha($post['g-recaptcha-response'], '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe')) {
        return "Error en la validación del CAPTCHA. Intenta de nuevo.";
    }
    if (!isset($post['politicas_privacidad'])) {
        return "Para registrarte, debes aceptar la Política de Privacidad y el Tratamiento de Datos Personales (Ley N° 29733).";
    }
    return null;
}

/**
 * Valida el formato de todos los campos del formulario de registro.
 * Primero verifica que las contraseñas coincidan, luego delega en las funciones
 * de validaciones.php (validarUsuario, validarEmail, validarClave, validarNombre, validarTelefono).
 * Usa el operador ?? (null-coalescing) para retornar el primer error encontrado.
 *
 * @param array $post Datos de $_POST con claves: usuario, email, clave, clave_repetida, nombres, apellidos, telefono
 * @return string|null Primer mensaje de error encontrado; null si todos los campos son válidos
 */
function validarCamposRegistro(array $post): ?string
{
    $clave          = $post['clave'] ?? '';
    $clave_repetida = $post['clave_repetida'] ?? '';

    if ($clave !== $clave_repetida) {
        return "Las contraseñas no coinciden.";
    }

    return validarUsuario($post['usuario'] ?? '')
        ?? validarEmail($post['email'] ?? '')
        ?? validarClave($clave)
        ?? validarNombre($post['nombres'] ?? '', 'El nombre')
        ?? validarNombre($post['apellidos'] ?? '', 'El apellido')
        ?? validarTelefono($post['telefono'] ?? '');
}

/**
 * Inserta el nuevo usuario y su perfil en la BD dentro de una transacción atómica.
 * En caso de éxito: inicia sesión automáticamente, establece variables de sesión
 * (usuario_id, usuario, rol, nombre_usuario, apellido_usuario) y redirige al index.
 * Captura mysqli_sql_exception código 1062 para detectar duplicados de usuario/email.
 *
 * @param string $usuario   Nombre de usuario validado (5-20 chars, solo alfanumérico + _)
 * @param string $email     Email validado con formato correcto
 * @param string $clave     Contraseña en texto plano (se hashea internamente con PASSWORD_DEFAULT)
 * @param string $nombres   Nombres del nuevo usuario
 * @param string $apellidos Apellidos del nuevo usuario
 * @param string $telefono  Teléfono del usuario (puede ser vacío — se guarda como NULL)
 * @param mysqli $conn      Conexión activa a la base de datos
 * @param string $base_url  URL base del proyecto para la redirección tras registro exitoso
 * @return string|null Mensaje de error si falla la BD; null si el registro fue exitoso (no retorna, hace exit)
 */
function registrarUsuarioEnBD(string $usuario, string $email, string $clave, string $nombres, string $apellidos, string $telefono, $conn, string $base_url): ?string
{
    $clave_hash = password_hash($clave, PASSWORD_DEFAULT);
    $conn->begin_transaction();

    try {
        $id_rol_var = ID_ROL_CLIENTE;

        $stmt_usuario = $conn->prepare(
            "INSERT INTO usuarios (id_rol, usuario, email, clave_hash) VALUES (?, ?, ?, ?)"
        );
        $stmt_usuario->bind_param("isss", $id_rol_var, $usuario, $email, $clave_hash);
        $stmt_usuario->execute();

        $nuevo_usuario_id = $conn->insert_id;

        $stmt_perfil = $conn->prepare(
            "INSERT INTO perfiles (id_usuario, nombres, apellidos, telefono) VALUES (?, ?, ?, ?)"
        );
        $telefono_a_insertar = ($telefono !== '') ? $telefono : null;
        $stmt_perfil->bind_param("isss", $nuevo_usuario_id, $nombres, $apellidos, $telefono_a_insertar);
        $stmt_perfil->execute();

        $conn->commit();

        session_regenerate_id(true);
        $_SESSION['usuario_id']       = $nuevo_usuario_id;
        $_SESSION['usuario']          = htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8');
        $_SESSION['rol']              = 'cliente';
        $_SESSION['nombre_usuario']   = htmlspecialchars($nombres, ENT_QUOTES, 'UTF-8');
        $_SESSION['apellido_usuario'] = htmlspecialchars($apellidos, ENT_QUOTES, 'UTF-8');

        header("Location: {$base_url}?page=index");
        exit;

    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        return ($e->getCode() === 1062)
            ? "El usuario o email ya está registrado."
            : "Error al registrar. Intenta de nuevo.";
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Procesamiento del formulario de registro
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario   = strip_tags(trim($_POST['usuario'] ?? ''));
    $email     = strip_tags(trim($_POST['email'] ?? ''));
    $clave     = $_POST['clave'] ?? '';
    $nombres   = strip_tags(trim($_POST['nombres'] ?? ''));
    $apellidos = strip_tags(trim($_POST['apellidos'] ?? ''));
    $telefono  = strip_tags(trim($_POST['telefono'] ?? ''));

    // 1. Guardia de validaciones previas (CSRF, CAPTCHA, políticas)
    $mensaje_error = validarGuardiasRegistro($_POST);

    // 2. Validar campos del formulario solo si las guardias pasaron
    if ($mensaje_error === null) {
        $mensaje_error = validarCamposRegistro($_POST);
    }

    // 3. Crear usuario si no hay errores de validación
    if ($mensaje_error === null || $mensaje_error === '') {
        $mensaje_error = registrarUsuarioEnBD($usuario, $email, $clave, $nombres, $apellidos, $telefono, $conn, $base_url);
    }
}

$csrf_token = Security::generarCSRF();
