<?php
/**
 * Configuración centralizada del servicio de correo electrónico (PHPMailer).
 * Define las constantes de conexión SMTP para el proveedor de correo seleccionado
 * y construye la función crearMailer() que devuelve una instancia configurada.
 *
 * Proveedor activo (constante MAIL_PROVIDER):
 *   'gmail'            — SMTP smtp.gmail.com:587 con TLS (requiere App Password)
 *   'office365'        — SMTP smtp.office365.com:587 con STARTTLS
 *   'outlook'          — Alias de office365
 *   'sendgrid'         — API SMTP smtp.sendgrid.net:587
 *   'mailtrap_live'    — Para staging con Mailtrap Live
 *   'mailtrap_sandbox' — Para desarrollo con Mailtrap Sandbox (sin entregar)
 *
 * Constantes definidas:
 *   MAIL_PROVIDER    — Selector de proveedor activo
 *   MAIL_FROM_NAME   — Nombre del remitente mostrado al destinatario
 *   MAIL_DEBUG       — Nivel de debug PHPMailer (0=off, 2=verbose)
 *   BASE_URL         — URL base del proyecto (fallback si no está definida)
 *
 * Uso:
 *   require BASE_PATH . '/src/Views/admin/mailer_config.php';
 *   $mail = crearMailer();
 *   $mail->addAddress($email); $mail->Subject = '...'; $mail->Body = '...';
 *   $mail->send();
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require BASE_PATH . '/vendor/autoload.php';

// Proveedor de envío: office365 | outlook | sendgrid | gmail | mailtrap_live | mailtrap_sandbox
define('MAIL_PROVIDER', 'mailtrap_sandbox');

// Comunes
define('MAIL_FROM_NAME', 'Tinkuy E-commerce');
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost:8086/Ecommerce-Tinkuy');
}
define('MAIL_DEBUG', 0); // 0 (off) | 2 (verbose)

// Credenciales/variables por proveedor (rellenar según el elegido)
// Office 365 / Outlook corporativo
define('O365_USERNAME', 'n00334763@upn.pe');
define('O365_PASSWORD', 'TU_PASSWORD_O_APP_PASSWORD');
// Outlook.com/Hotmail personal (si usas cuenta @outlook.com/@hotmail.com)
define('OUTLOOK_USERNAME', 'tu_cuenta_outlook@outlook.com');
define('OUTLOOK_PASSWORD', 'TU_PASSWORD_O_APP_PASSWORD');
// SendGrid (recomendado en producción)
define('SENDGRID_API_KEY', 'SG.xxxxxx');
define('SENDGRID_FROM', 'notificaciones@tudominio.com');
// Gmail (requiere App Password con 2FA)
define('GMAIL_USERNAME', 'casimirom543@gmail.com'); // Coloca tu correo Gmail aquí
define('GMAIL_APP_PASSWORD', 'cgbj lscf pfwn tvjg'); // Coloca la contraseña de aplicación aquí (16 letras sin espacios)
// Mailtrap Email Sending (no sandbox)
define('MAILTRAP_LIVE_USERNAME', '');
define('MAILTRAP_LIVE_PASSWORD', '');
define('MAILTRAP_LIVE_FROM', 'notificaciones@tudominio.com');

// Mailtrap Sandbox (para pruebas: los correos van al inbox de Mailtrap)
define('MAILTRAP_SANDBOX_HOST', 'sandbox.smtp.mailtrap.io');
define('MAILTRAP_SANDBOX_PORT', 2525);
define('MAILTRAP_SANDBOX_USERNAME', '347476dcabe2f0');
define('MAILTRAP_SANDBOX_PASSWORD', 'cbc95dd346b27c');
define('MAILTRAP_SANDBOX_FROM', 'no-reply@tinkuy.com');

function get_smtp_config()
{
    $cfg = [
        'host' => '',
        'port' => 587,
        'secure' => PHPMailer::ENCRYPTION_STARTTLS,
        'auth' => true,
        'username' => '',
        'password' => '',
        'from' => '',
        'from_name' => MAIL_FROM_NAME,
        'alt_host' => null
    ];

    switch (MAIL_PROVIDER) {
        case 'office365':
            $cfg['host'] = 'smtp.office365.com';
            $cfg['alt_host'] = 'smtp-mail.outlook.com';
            $cfg['username'] = O365_USERNAME;
            $cfg['password'] = O365_PASSWORD;
            $cfg['from'] = O365_USERNAME;
            break;
        case 'outlook':
            $cfg['host'] = 'smtp-mail.outlook.com';
            $cfg['username'] = OUTLOOK_USERNAME;
            $cfg['password'] = OUTLOOK_PASSWORD;
            $cfg['from'] = OUTLOOK_USERNAME;
            break;
        case 'sendgrid':
            $cfg['host'] = 'smtp.sendgrid.net';
            $cfg['username'] = 'apikey'; // fijo
            $cfg['password'] = SENDGRID_API_KEY;
            $cfg['from'] = SENDGRID_FROM;
            break;
        case 'gmail':
            $cfg['host'] = 'smtp.gmail.com';
            $cfg['username'] = GMAIL_USERNAME;
            $cfg['password'] = GMAIL_APP_PASSWORD;
            $cfg['from'] = GMAIL_USERNAME;
            break;
        case 'mailtrap_live':
            $cfg['host'] = 'live.smtp.mailtrap.io';
            $cfg['username'] = MAILTRAP_LIVE_USERNAME;
            $cfg['password'] = MAILTRAP_LIVE_PASSWORD;
            $cfg['from'] = MAILTRAP_LIVE_FROM;
            break;
        case 'mailtrap_sandbox':
            $cfg['host'] = MAILTRAP_SANDBOX_HOST;
            $cfg['port'] = MAILTRAP_SANDBOX_PORT;
            $cfg['username'] = MAILTRAP_SANDBOX_USERNAME;
            $cfg['password'] = MAILTRAP_SANDBOX_PASSWORD;
            $cfg['from'] = MAILTRAP_SANDBOX_FROM;
            $cfg['alt_host'] = null;
            break;
        default:
            throw new Exception('MAIL_PROVIDER inválido');
    }
    return $cfg;
}
// BASE_URL ya lo define el router; mantenemos esta constante fuera de este archivo cuando sea posible.

// Mensaje de ayuda para autenticación fallida en proveedores comunes
function build_authentication_hint($provider)
{
    $hint = "\n\n-- Sugerencia de autenticación --\n";
    switch ($provider) {
        case 'office365':
        case 'outlook':
            $hint .= "Si usas MFA (muy común en cuentas institucionales):\n";
            $hint .= "- No puedes usar tu contraseña normal.\n";
            $hint .= "- Genera una 'Contraseña de aplicación' en tu cuenta Microsoft.\n";
            $hint .= "  Pasos: Cuenta Microsoft > Seguridad > Verificación en dos pasos > Contraseñas de aplicación.\n";
            $hint .= "- Usa esa contraseña larga como O365_PASSWORD/OUTLOOK_PASSWORD.\n";
            $hint .= "- Además, pide habilitar 'Authenticated SMTP' para tu buzón en Exchange (si está bloqueado).\n";
            $hint .= "- Host corporativo: smtp.office365.com | Outlook personal: smtp-mail.outlook.com\n";
            break;
        case 'gmail':
            $hint .= "Gmail requiere 'App Password' si tienes 2FA:\n";
            $hint .= "- Activa 2FA y crea una contraseña de aplicación para 'Correo'.\n";
            $hint .= "- Usa esa contraseña como GMAIL_APP_PASSWORD.\n";
            break;
        case 'sendgrid':
            $hint .= "SendGrid: usa API Key como contraseña y usuario 'apikey'.\n";
            break;
        case 'mailtrap_live':
            $hint .= "Mailtrap Email Sending: verifica credenciales 'live' (no sandbox).\n";
            break;
        default:
            $hint .= "Verifica usuario/contraseña, TLS (587) y que el remitente coincide.\n";
    }
    return $hint;
}

/** Activa el debug de PHPMailer si MAIL_DEBUG está habilitado. */
function configurarDebugMailer(PHPMailer $mail, string $tag = ''): void
{
    if (!MAIL_DEBUG) return;
    $mail->SMTPDebug = MAIL_DEBUG;
    $mail->Debugoutput = function ($str, $level) use ($tag) {
        error_log("[PHPMailer]{$tag}[level $level] $str");
    };
}

/**
 * Intenta enviar el correo usando el host SMTP alternativo.
 * Devuelve ['success' => bool, 'error' => string|null].
 */
function enviarViaFallback(array $cfg, string $to, string $subject, string $body_html, string $body_text): array
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['alt_host'];
        $mail->SMTPAuth   = $cfg['auth'];
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = $cfg['secure'];
        $mail->Port       = $cfg['port'];
        configurarDebugMailer($mail, '[ALT]');
        $mail->setFrom($cfg['from'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = $body_text ?: strip_tags($body_html);
        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e2) {
        return ['success' => false, 'error' => $e2->getMessage() ?: 'unknown'];
    }
}

function send_mail($to, $subject, $body_html, $body_text = '')
{
    $cfg  = get_smtp_config();
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = $cfg['auth'];
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = $cfg['secure'];
        $mail->Port       = $cfg['port'];
        $mail->Timeout    = 10;

        configurarDebugMailer($mail);

        $mail->setFrom($cfg['from'], $cfg['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = $body_text ?: strip_tags($body_html);

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        // Fallback automático si falla autenticación y hay host alternativo
        $error        = $mail->ErrorInfo ?? $e->getMessage();
        $triedFallback = false;
        if (stripos($error, 'Could not authenticate') !== false && !empty($cfg['alt_host'])) {
            $triedFallback = true;
            $resultado     = enviarViaFallback($cfg, $to, $subject, $body_html, $body_text);
            if ($resultado['success']) {
                return true;
            }
            $error .= " | Fallback error: " . $resultado['error'];
        }
        // Agregar guía si es error típico de autenticación
        if (stripos($error, 'Could not authenticate') !== false) {
            $error .= build_authentication_hint(MAIL_PROVIDER);
        }
        error_log("send_mail error: $error" . ($triedFallback ? " (fallback tried)" : ""));
        return false;
    }
}
