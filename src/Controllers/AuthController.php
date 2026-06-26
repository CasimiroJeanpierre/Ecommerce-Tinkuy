<?php
/**
 * Controlador de inicio de sesión (procedural).
 * Implementa el flujo completo de autenticación en dos fases (2FA por correo).
 *
 * Flujo:
 *   1. validarGuardiasLogin()  — verifica CSRF, reCAPTCHA y aceptación de políticas
 *   2. Security::estaRateLimited() — bloquea IPs/usuarios con demasiados intentos
 *   3. validarDatosLogin()     — comprueba formato de credenciales
 *   4. buscarUsuario()         — consulta la BD y recupera datos del usuario
 *   5. verificarCredenciales() — valida password y dispara el flujo 2FA
 *   6. iniciar2FA()            — genera y envía código OTP, redirige a verify_2fa
 *
 * Variables que expone al scope de la vista:
 *   $mensaje_error (string) - Error de autenticación o bloqueo por rate-limiting
 *   $mensaje_exito (string) - Mensaje de confirmación (ej. tras reset de contraseña)
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

// Redirigir si ya hay sesión activa
if (isset($_SESSION['usuario_id'])) {
    header("Location: {$base_url}?page=index");
    exit;
}

// Mensajes de sesión flash
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}
if (isset($_SESSION['mensaje_error'])) {
    $mensaje_error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

// ─────────────────────────────────────────────────────────────────────────────
// Funciones auxiliares del flujo de login
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Valida las protecciones previas al procesamiento de credenciales.
 * Comprueba token CSRF, reCAPTCHA v2 y aceptación de políticas de privacidad (Ley N° 29733).
 * Debe ejecutarse antes de cualquier consulta a la BD para evitar consumo innecesario de recursos.
 *
 * @param array $post Datos de $_POST con claves 'csrf_token', 'g-recaptcha-response', 'politicas_privacidad'
 * @return string|null Mensaje de error (string) si falla alguna validación; null si todo es correcto
 */
function validarGuardiasLogin(array $post): ?string
{
    if (!Security::verificarCSRF($post['csrf_token'] ?? '')) {
        return "Token de seguridad inválido. Recarga la página e intenta de nuevo.";
    }
    if (empty($post['g-recaptcha-response'])) {
        return "Por favor, verifica que no eres un robot (CAPTCHA).";
    }
    $recaptcha_secret = getenv('RECAPTCHA_SECRET_KEY') ?: '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
    if (!Security::verificarRecaptcha($post['g-recaptcha-response'], $recaptcha_secret)) {
        return "Error en la validación del CAPTCHA. Intenta de nuevo.";
    }
    if (!isset($post['politicas_privacidad'])) {
        return "Para iniciar sesión, debes aceptar la Política de Privacidad y el Tratamiento de Datos (Ley N° 29733).";
    }
    return null;
}

/**
 * Consulta la base de datos y devuelve los datos del usuario si existe.
 * Realiza un JOIN con la tabla roles para obtener el nombre del rol en la misma consulta.
 * Usa sentencia preparada con store_result() para verificar exactamente 1 resultado.
 *
 * @param string $usuario Nombre de usuario tal como fue ingresado en el formulario
 * @param mysqli $conn    Conexión activa a la base de datos
 * @return array{id: int, uname: string, email: string, hash: string, estado: string, rol: string}|array{}
 *         Array con datos del usuario si existe, o array vacío si no se encontró
 */
function buscarUsuario(string $usuario, $conn): array
{
    $stmt = $conn->prepare(
        "SELECT u.id_usuario, u.usuario, u.email, u.clave_hash, u.estado, r.nombre_rol
         FROM usuarios AS u
         JOIN roles AS r ON u.id_rol = r.id_rol
         WHERE u.usuario = ?"
    );
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows !== 1) {
        $stmt->close();
        return [];
    }

    $id = $uname = $email = $hash = $estado = $rol = null;
    $stmt->bind_result($id, $uname, $email, $hash, $estado, $rol);
    $stmt->fetch();
    $stmt->close();

    return compact('id', 'uname', 'email', 'hash', 'estado', 'rol');
}

/**
 * Registra un intento fallido de login y construye el mensaje de error con contexto.
 * Si quedan intentos, añade el contador; si se agotaron, calcula y muestra el tiempo de bloqueo.
 *
 * @param string $ip         IP del cliente (obtenida con Security::obtenerIP())
 * @param string $usuario    Nombre de usuario ingresado en el formulario
 * @param mysqli $conn       Conexión activa a la base de datos
 * @param string $base_error Mensaje base de error (ej. "Usuario o contraseña incorrectos.")
 * @return string Mensaje de error enriquecido con intentos restantes o tiempo de bloqueo
 */
function manejarIntentoFallido(string $ip, string $usuario, $conn, string $base_error): string
{
    Security::registrarIntento($ip, $usuario, false, $conn);
    $restantes = Security::intentosRestantes($ip, $usuario, $conn);

    if ($restantes > 0) {
        return "{$base_error} Intentos restantes: {$restantes}.";
    }

    $segundos   = Security::obtenerSegundosBloqueo($ip, $usuario, $conn);
    $minutos    = ceil($segundos / 60);
    return "Demasiados intentos fallidos. Espera {$minutos} minuto(s).";
}

/**
 * Inicia la fase de verificación en dos pasos (2FA por correo electrónico).
 * Resetea rate-limiting, rota el token CSRF, regenera el ID de sesión,
 * genera un OTP de 6 dígitos con expiración de 5 minutos, envía el correo
 * con el código y almacena los datos pendientes en sesión antes de redirigir.
 *
 * @param array  $usuario_data Array con claves: id, uname, email, hash, estado, rol
 * @param string $email        Correo del usuario al que se enviará el código OTP
 * @param string $base_url     URL base del proyecto (para construir la redirección)
 * @return void                No retorna; siempre termina con header()+exit
 */
function iniciar2FA(array $usuario_data, string $email, string $base_url): void
{
    Security::resetearIntentos($_SERVER['REMOTE_ADDR'] ?? '', $usuario_data['uname'], $GLOBALS['conn']);
    Security::registrarIntento($_SERVER['REMOTE_ADDR'] ?? '', $usuario_data['uname'], true, $GLOBALS['conn']);
    Security::rotarCSRF();
    session_regenerate_id(true);

    $perfil_stmt = $GLOBALS['conn']->prepare(
        "SELECT nombres, apellidos FROM perfiles WHERE id_usuario = ?"
    );
    $perfil_stmt->bind_param("i", $usuario_data['id']);
    $perfil_stmt->execute();
    $perfil_stmt->store_result();
    $nombres = $apellidos = '';
    $perfil_stmt->bind_result($nombres, $apellidos);
    $perfil_stmt->fetch();
    $perfil_stmt->close();

    $_SESSION['pending_2fa'] = [
        'usuario_id'      => $usuario_data['id'],
        'usuario'         => $usuario_data['uname'],
        'rol'             => strtolower($usuario_data['rol']),
        'nombre_usuario'  => htmlspecialchars($nombres ?: $usuario_data['uname'], ENT_QUOTES, 'UTF-8'),
        'apellido_usuario'=> htmlspecialchars($apellidos, ENT_QUOTES, 'UTF-8'),
    ];

    $codigo_2fa = sprintf("%06d", mt_rand(1, 999999));
    $_SESSION['2fa_codigo']      = $codigo_2fa;
    $_SESSION['2fa_expiracion']  = time() + 300; // 5 minutos de validez

    // Commit session before the SMTP call so data is not lost on timeout
    session_write_close();

    require_once BASE_PATH . '/src/Views/admin/mailer_config.php';
    $asunto   = "Código de Seguridad 2FA | Tinkuy";
    $body_html = "
    <div style='font-family: Arial, sans-serif; padding: 20px; text-align: center; max-width: 500px; margin: auto; background-color: #f9f9f9; border-radius: 10px;'>
        <h2 style='color: #0d6efd;'>Verificación de Seguridad</h2>
        <p>Hola <strong>{$usuario_data['uname']}</strong>,</p>
        <p>Tu código de verificación para iniciar sesión es:</p>
        <h1 style='font-size: 36px; letter-spacing: 5px; color: #333; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #ddd;'>{$codigo_2fa}</h1>
        <p style='color: #888; font-size: 12px;'>Este código expirará en 5 minutos. No lo compartas con nadie.</p>
    </div>";

    send_mail($email, $asunto, $body_html);

    header("Location: {$base_url}?page=verify_2fa");
    exit;
}

/**
 * Verifica las credenciales del usuario encontrado en BD y despacha el flujo 2FA.
 * Primero comprueba que la cuenta esté activa, luego verifica el password con
 * password_verify(). Si las credenciales son correctas, llama a iniciar2FA()
 * (que hace exit internamente). Si son incorrectas, registra el intento fallido.
 *
 * @param array  $usuario_data Array retornado por buscarUsuario() — puede ser vacío
 * @param string $clave        Contraseña en texto plano recibida del formulario
 * @param string $ip           IP del cliente para registrar en login_intentos
 * @param string $usuario      Nombre de usuario para registrar en login_intentos
 * @param mysqli $conn         Conexión activa a la base de datos
 * @param string $base_url     URL base del proyecto para la redirección 2FA
 * @return string|null Mensaje de error si las credenciales fallan; null si 2FA inició (exit)
 */
function verificarCredenciales(array $usuario_data, string $clave, string $ip, string $usuario, $conn, string $base_url): ?string
{
    if (!empty($usuario_data) && $usuario_data['estado'] !== 'activo') {
        return "Tu cuenta está desactivada. Contacta al administrador.";
    }
    if (!empty($usuario_data) && password_verify($clave, $usuario_data['hash'])) {
        iniciar2FA($usuario_data, $usuario_data['email'], $base_url); // redirige y hace exit
        return null;
    }
    return manejarIntentoFallido($ip, $usuario, $conn, "Usuario o contraseña incorrectos.");
}

// ─────────────────────────────────────────────────────────────────────────────
// Procesamiento del formulario de login
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Guardia de validaciones previas (CSRF, CAPTCHA, políticas)
    $mensaje_error = validarGuardiasLogin($_POST);

    if ($mensaje_error === null) {
        $usuario = strip_tags(trim($_POST['usuario'] ?? ''));
        $clave   = $_POST['clave'] ?? '';
        $ip      = Security::obtenerIP();

        // 2. Verificar bloqueo por rate-limiting
        if (Security::estaRateLimited($ip, $usuario, $conn)) {
            $segundos      = Security::obtenerSegundosBloqueo($ip, $usuario, $conn);
            $minutos       = ceil($segundos / 60);
            $mensaje_error = "Demasiados intentos fallidos. Por seguridad, tu acceso ha sido bloqueado. Espera {$minutos} minuto(s) para intentar de nuevo.";
        }

        // 3. Validar formato de credenciales
        if ($mensaje_error === null) {
            $error_formato = validarDatosLogin($usuario, $clave);
            $mensaje_error = ($error_formato !== null)
                ? manejarIntentoFallido($ip, $usuario, $conn, $error_formato)
                : null;
        }

        // 4. Consultar usuario en BD y verificar credenciales
        if ($mensaje_error === null) {
            $usuario_data  = buscarUsuario($usuario, $conn);
            $mensaje_error = verificarCredenciales($usuario_data, $clave, $ip, $usuario, $conn, $base_url);
        }
    }
}

$csrf_token = Security::generarCSRF();
