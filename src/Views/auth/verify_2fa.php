<?php
// src/Views/auth/verify_2fa.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';

// Si no hay 2FA pendiente, redirigir al login
if (!isset($_SESSION['pending_2fa'])) {
    header("Location: " . $base_url . "?page=login");
    exit;
}

$mensaje_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_ingresado = trim($_POST['codigo_2fa'] ?? '');

    if (time() > $_SESSION['2fa_expiracion']) {
        $mensaje_error = "El código ha expirado. Por favor, inicia sesión nuevamente.";
        unset($_SESSION['pending_2fa'], $_SESSION['2fa_codigo'], $_SESSION['2fa_expiracion']);
    } elseif ($codigo_ingresado === $_SESSION['2fa_codigo']) {
        // Código correcto: Pasar datos pendientes a la sesión principal (Login Real)
        session_regenerate_id(true); // Prevenir Session Fixation al completar el 2FA
        $_SESSION['usuario_id'] = $_SESSION['pending_2fa']['usuario_id'];
        $_SESSION['usuario'] = $_SESSION['pending_2fa']['usuario'];
        $_SESSION['rol'] = $_SESSION['pending_2fa']['rol'];
        $_SESSION['nombre_usuario'] = $_SESSION['pending_2fa']['nombre_usuario'];
        $_SESSION['apellido_usuario'] = $_SESSION['pending_2fa']['apellido_usuario'];

        $rol = $_SESSION['rol'];

        // Limpiar datos temporales
        unset($_SESSION['pending_2fa'], $_SESSION['2fa_codigo'], $_SESSION['2fa_expiracion']);

        // Restaurar las redirecciones por rol que teníamos implementadas antes
        if ($rol === 'admin') {
            header("Location: " . $base_url . "?page=admin_dashboard");
        } elseif ($rol === 'vendedor') {
            header("Location: " . $base_url . "?page=vendedor_dashboard");
        } else {
            if (isset($_SESSION['redirect_url'])) {
                $url_pendiente = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                header("Location: " . $base_url . $url_pendiente);
            } else {
                header("Location: " . $base_url . "?page=index");
            }
        }
        exit;
    } else {
        $mensaje_error = "Código incorrecto. Intenta de nuevo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Verificación 2FA | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="card p-4 shadow-sm" style="max-width: 400px; width: 90%;">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock-fill" style="font-size: 3rem; color: #0d6efd;"></i>
            <h3 class="mt-2">Verificación de Seguridad</h3>
            <p class="text-muted">Hemos enviado un código de 6 dígitos a tu correo electrónico.</p>
        </div>
        <?php if ($mensaje_error !== ''): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($mensaje_error) ?>
            </div>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="codigo_2fa" class="form-label">Código de Verificación</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-123"></i></span>
                    <input type="text" inputmode="numeric" class="form-control text-center fs-4" id="codigo_2fa"
                        name="codigo_2fa" placeholder="000000" maxlength="6" pattern="\d{6}" required autofocus
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')" style="letter-spacing: 0.5rem;">
                </div>
            </div>
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Verificar y Entrar</button>
            </div>
        </form>
        <hr class="my-4">
        <div class="text-center">
            <a href="<?= $base_url ?>?page=login" class="text-decoration-none text-muted"><i
                    class="bi bi-arrow-left"></i> Volver al Login</a>
        </div>
    </div>
</body>

</html>