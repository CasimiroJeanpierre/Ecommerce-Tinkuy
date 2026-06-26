<?php
/**
 * Vista del formulario de inicio de sesión con autenticación en dos fases (2FA).
 * Al enviar el formulario, AuthController valida CSRF, reCAPTCHA, políticas de privacidad,
 * rate limiting, credenciales y redirige a verify_2fa si todo es correcto.
 *
 * Variables definidas por AuthController antes de renderizar:
 *   $mensaje_error (string) - Error de credenciales, cuenta inactiva o bloqueo por rate limiting
 *   $mensaje_exito (string) - Mensaje de confirmación (ej. "Registro exitoso, inicia sesión")
 *   $csrf_token    (string) - Token CSRF para proteger el formulario POST
 *   $base_url      (string) - URL base del proyecto (para los enlaces de registro y recuperación)
 */
// Controlador ejecuta la lógica de login y define: $mensaje_error, $mensaje_exito, $csrf_token
require_once __DIR__ . '/../../Controllers/AuthController.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Iniciar Sesión | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        body {
            background: linear-gradient(to bottom right, #f5f7fa, #c3cfe2);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .login-container {
            max-width: 400px;
            margin: auto;
            animation: fadeIn 0.6s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            border-radius: 15px;
        }

        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
        }

        .login-icon {
            font-size: 3rem;
            color: #0d6efd;
        }
    </style>
</head>

<body>
    <?php include BASE_PATH . '/src/Views/components/navbar.php'; ?>

    <main class="flex-grow-1 d-flex align-items-center justify-content-center">
        <div class="login-container">
            <div class="card shadow-lg">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-circle login-icon"></i>
                        <h3 class="mt-2">Iniciar Sesión</h3>
                        <p class="text-muted">Bienvenido de nuevo.</p>
                    </div>

                    <?php if ($mensaje_error !== ''): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($mensaje_exito !== ''): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?= htmlspecialchars($mensaje_exito, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off" novalidate>
                        <!-- Token CSRF: protección contra ataques Cross-Site Request Forgery -->
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="usuario" class="form-label">Nombre de usuario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="usuario" name="usuario"
                                    placeholder="Tu usuario" autocomplete="username" maxlength="20">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="clave" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="clave" name="clave"
                                    placeholder="••••••••" autocomplete="current-password" maxlength="20">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- reCAPTCHA v2 Widget -->
                        <div class="mb-3 d-flex justify-content-center">
                            <!-- Clave de sitio (TEST) - ¡Cambiar en producción! -->
                            <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"></div>
                        </div>

                    <!-- Checkbox de Políticas de Privacidad (RNF-09) -->
                    <div class="mb-3 form-check text-start" style="font-size: 0.85rem;">
                        <input type="checkbox" class="form-check-input" id="politicas_privacidad" name="politicas_privacidad" required>
                        <label class="form-check-label text-muted" for="politicas_privacidad">
                            Al iniciar sesión, acepto los <a href="#" target="_blank" class="text-decoration-none">Términos y Condiciones</a> y la
                            <a href="<?= $base_url ?>?page=about" target="_blank" class="text-decoration-none">Política de Privacidad (Ley N° 29733)</a>.
                        </label>
                        <div class="invalid-feedback">Debes aceptar las políticas para ingresar.</div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> Ingresar
                            </button>
                        </div>

                        <div class="text-center mt-3">
                            <a href="<?= $base_url ?>?page=forgot_password" class="text-decoration-none">
                                ¿Olvidaste tu contraseña?
                            </a>
                        </div>
                    </form>

                    <hr class="my-4">
                    <p class="text-center mb-0">
                        ¿No tienes cuenta? <a href="<?= $base_url ?>?page=register">Regístrate aquí</a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <?php include BASE_PATH . '/src/Views/components/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#clave');
        if (togglePassword && password) {
            togglePassword.addEventListener('click', function (e) {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
            });
        }
    </script>
</body>

</html>