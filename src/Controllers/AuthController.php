<?php
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
    header("Location: " . $base_url . "?page=index");
    exit;
}

// Mensaje de registro exitoso
if (isset($_SESSION['mensaje_exito'])) {
    $mensaje_exito = $_SESSION['mensaje_exito'];
    unset($_SESSION['mensaje_exito']);
}

// Mensaje de error desde otras páginas (como expiración de sesión)
if (isset($_SESSION['mensaje_error'])) {
    $mensaje_error = $_SESSION['mensaje_error'];
    unset($_SESSION['mensaje_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verificar token CSRF
    if (!Security::verificarCSRF($_POST['csrf_token'] ?? '')) {
        $mensaje_error = "Token de seguridad inválido. Recarga la página e intenta de nuevo.";

        // 2. Verificar reCAPTCHA (Google reCAPTCHA v2)
    } elseif (empty($_POST['g-recaptcha-response'])) {
        $mensaje_error = "Por favor, verifica que no eres un robot (CAPTCHA).";
    } elseif (!Security::verificarRecaptcha($_POST['g-recaptcha-response'], '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe')) {
        $mensaje_error = "Error en la validación del CAPTCHA. Intenta de nuevo.";
    } elseif (!isset($_POST['politicas_privacidad'])) {
        $mensaje_error = "Para iniciar sesión, debes aceptar la Política de Privacidad y el Tratamiento de Datos (Ley N° 29733).";
    } else {
        // reCAPTCHA correcto
        $usuario = strip_tags(trim($_POST['usuario'] ?? ''));
        $clave = $_POST['clave'] ?? '';
        $ip = Security::obtenerIP();

        // 3. Verificar bloqueo en Base de Datos (Seguridad Real)
        if (Security::estaRateLimited($ip, $usuario, $conn)) {
            $segundos_restantes = Security::obtenerSegundosBloqueo($ip, $usuario, $conn);
            $minutos = ceil($segundos_restantes / 60);
            $mensaje_error = "Demasiados intentos fallidos. Por seguridad, tu acceso ha sido bloqueado. Espera {$minutos} minuto(s) para intentar de nuevo.";
        } else {
            // 4. Validar formato
            $mensaje_error = validarDatosLogin($usuario, $clave);

            // 5. Consultar usuario con JOIN de roles
            $stmt = $conn->prepare(
                "SELECT u.id_usuario, u.usuario, u.email, u.clave_hash, u.estado, r.nombre_rol
                 FROM usuarios AS u
                 JOIN roles AS r ON u.id_rol = r.id_rol
                 WHERE u.usuario = ?"
            );
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $stmt->store_result();

            $usuario_encontrado = ($stmt->num_rows === 1);
            $id_db = $usuario_db = $email_db = $clave_hash_db = $estado_db = $nombre_rol_db = null;
            if ($usuario_encontrado) {
                $stmt->bind_result($id_db, $usuario_db, $email_db, $clave_hash_db, $estado_db, $nombre_rol_db);
                $stmt->fetch();
            }
            $stmt->close();

            // Si hubo error de formato previo (ej. clave corta), lo castigamos como intento fallido
            if (!is_null($mensaje_error)) {
                Security::registrarIntento($ip, $usuario, false, $conn);
                $intentos_restantes = Security::intentosRestantes($ip, $usuario, $conn);
                if ($intentos_restantes > 0) {
                    $mensaje_error .= " Intentos restantes: {$intentos_restantes}.";
                } else {
                    $segundos_restantes = Security::obtenerSegundosBloqueo($ip, $usuario, $conn);
                    $minutos = ceil($segundos_restantes / 60);
                    $mensaje_error = "Demasiados intentos fallidos. Espera {$minutos} minuto(s).";
                }
            } elseif ($usuario_encontrado && $estado_db !== 'activo') {
                $mensaje_error = "Tu cuenta está desactivada. Contacta al administrador.";

            } elseif ($usuario_encontrado && password_verify($clave, $clave_hash_db)) {
                // Login exitoso (Fase 1: Credenciales correctas)
                Security::resetearIntentos($ip, $usuario, $conn);
                Security::registrarIntento($ip, $usuario, true, $conn);
                Security::rotarCSRF();
                session_regenerate_id(true);

                $perfil_stmt = $conn->prepare(
                    "SELECT nombres, apellidos FROM perfiles WHERE id_usuario = ?"
                );
                $perfil_stmt->bind_param("i", $id_db);
                $perfil_stmt->execute();
                $perfil_stmt->store_result();
                $nombres = $apellidos = '';
                $perfil_stmt->bind_result($nombres, $apellidos);
                $perfil_stmt->fetch();
                $perfil_stmt->close();

                // Guardar los datos temporalmente hasta verificar el 2FA
                $_SESSION['pending_2fa'] = [
                    'usuario_id' => $id_db,
                    'usuario' => $usuario_db,
                    'rol' => strtolower($nombre_rol_db),
                    'nombre_usuario' => htmlspecialchars($nombres ?: $usuario_db, ENT_QUOTES, 'UTF-8'),
                    'apellido_usuario' => htmlspecialchars($apellidos, ENT_QUOTES, 'UTF-8')
                ];

                // Generar código de 6 dígitos aleatorio
                $codigo_2fa = sprintf("%06d", mt_rand(1, 999999));
                $_SESSION['2fa_codigo'] = $codigo_2fa;
                $_SESSION['2fa_expiracion'] = time() + (5 * 60); // 5 minutos de validez

                // Enviar el correo usando tu configuración existente
                require_once BASE_PATH . '/src/Views/admin/mailer_config.php';
                $asunto = "Código de Seguridad 2FA | Tinkuy";
                $body_html = "
                <div style='font-family: Arial, sans-serif; padding: 20px; text-align: center; max-width: 500px; margin: auto; background-color: #f9f9f9; border-radius: 10px;'>
                    <h2 style='color: #0d6efd;'>Verificación de Seguridad</h2>
                    <p>Hola <strong>{$usuario_db}</strong>,</p>
                    <p>Tu código de verificación para iniciar sesión es:</p>
                    <h1 style='font-size: 36px; letter-spacing: 5px; color: #333; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #ddd;'>{$codigo_2fa}</h1>
                    <p style='color: #888; font-size: 12px;'>Este código expirará en 5 minutos. No lo compartas con nadie.</p>
                </div>";

                send_mail($email_db, $asunto, $body_html);

                // Redirigir a la vista de confirmación del código
                header("Location: " . $base_url . "?page=verify_2fa");
                exit;

            } else {
                Security::registrarIntento($ip, $usuario, false, $conn);
                $intentos_restantes = Security::intentosRestantes($ip, $usuario, $conn);

                $mensaje_error = "Usuario o contraseña incorrectos.";
                if ($intentos_restantes > 0) {
                    $mensaje_error .= " Intentos restantes: {$intentos_restantes}.";
                } else {
                    $segundos_restantes = Security::obtenerSegundosBloqueo($ip, $usuario, $conn);
                    $minutos = ceil($segundos_restantes / 60);
                    $mensaje_error = "Demasiados intentos fallidos. Espera {$minutos} minuto(s).";
                }
            }
        }
    }
}

$csrf_token = Security::generarCSRF();
