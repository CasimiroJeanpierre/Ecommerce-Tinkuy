<?php
require_once BASE_PATH . '/src/Core/db.php';
require_once BASE_PATH . '/src/Views/admin/mailer_config.php';

$mensaje = '';
$tipo_mensaje = 'info'; // Para cambiar el color del alert

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strip_tags(trim($_POST['email']));

    // --- CORRECCIÓN: Añadir validación de campo vacío (ID 32) ---
    if (empty($email)) {
        $mensaje = "Error (ID 32): El campo correo es requerido.";
        $tipo_mensaje = 'danger';
        // --- FIN CORRECCIÓN ---

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Error (ID 28): Por favor, ingresa un formato de correo válido.";
        $tipo_mensaje = 'danger';
    } else {
        // (El resto de tu lógica PHP es excelente y se mantiene igual)
        $query = "SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1"; // No necesitas traer el email de nuevo
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            // Generar token seguro
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour')); // Expiración en 1 hora

            try {
                $conn->begin_transaction();

                // Borrar tokens anteriores para ese email
                $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $del->bind_param("s", $email);
                $del->execute();

                // Guardar nuevo token hash en BD
                $insert = $conn->prepare("INSERT INTO password_resets (email, token_hash, expiracion) VALUES (?, ?, ?)");
                $insert->bind_param("sss", $email, $token_hash, $expiracion);
                $insert->execute();

                $conn->commit(); // Confirmar transacción solo si todo va bien

                // Enlace de recuperación (token original, no hash)
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $domain = $_SERVER['HTTP_HOST'];
                $reset_link = $protocol . $domain . BASE_URL . "?page=reset_password&token=" . urlencode($token);
                $asunto = "Restablece tu contraseña | Tinkuy";

                $body_html = '
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Restablecer Contraseña</title></head>
<body style="font-family:Arial,sans-serif; background-color:#f4f4f4; padding:20px;">
<div style="max-width:600px; margin:auto; background:#fff; padding:30px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
<h2 style="color:#0d6efd; text-align:center;">🔐 Restablecer Contraseña</h2>
<p>Hola,</p>
<p>Hemos recibido una solicitud para restablecer tu contraseña en <strong>Tinkuy</strong>.</p>
<p>Haz clic en el botón de abajo para continuar:</p>
<div style="text-align:center; margin:30px 0;">
<a href="' . htmlspecialchars($reset_link) . '" style="display:inline-block; padding:12px 30px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold;">Restablecer mi contraseña</a>
</div>
<p><small><strong>Nota:</strong> Este enlace expirará en <strong>1 hora</strong>.</small></p>
<p>Si no solicitaste este cambio, ignora este correo.</p>
<hr style="border:none; border-top:1px solid #ddd; margin:20px 0;">
<p style="text-align:center; color:#888; font-size:12px;">© 2025 Tinkuy | Artesanías Peruanas</p>
</div>
</body>
</html>';

                // Intentar enviar el correo
                if (send_mail($email, $asunto, $body_html)) {
                    $mensaje = "Se ha enviado un enlace de recuperación a tu correo (si está registrado).";
                    $tipo_mensaje = 'success'; // Cambiamos a success para el mensaje principal
                } else {
                    // Error de envío (puede ser configuración SMTP, etc.)
                    $mensaje = "Hubo un problema al enviar el correo. Inténtalo más tarde.";
                    // Aquí podrías loggear el error real para ti: error_log("Mailer Error: " . $mail->ErrorInfo);
                    $tipo_mensaje = 'danger';
                }

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $mensaje = "Ocurrió un error al procesar tu solicitud. Inténtalo de nuevo.";
                // Loggear el error real: error_log("DB Error en forgot_password: " . $e->getMessage());
                $tipo_mensaje = 'danger';
            }

        } else {
            // Email no encontrado - Mensaje genérico por seguridad (ID 89)
            $mensaje = "Si existe una cuenta asociada a ese correo, recibirás un enlace.";
            $tipo_mensaje = 'info';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="card p-4 shadow-sm" style="max-width: 400px; width: 90%;">
        <div class="text-center mb-4">
            <i class="bi bi-key-fill" style="font-size: 3rem; color: #0d6efd;"></i>
            <h3 class="mt-2">¿Olvidaste tu contraseña?</h3>
            <p class="text-muted">Ingresa tu correo y te enviaremos un enlace para restablecerla.</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?= htmlspecialchars($tipo_mensaje) ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" name="email" id="email" placeholder="tu.correo@ejemplo.com"
                        required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-send"></i> Enviar enlace de recuperación
            </button>
        </form>
        <hr>
        <div class="text-center">
            <a href="<?= $base_url ?>?page=login" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Volver
                al inicio de sesión</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>