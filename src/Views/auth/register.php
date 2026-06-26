<?php
/**
 * Vista del formulario de registro de nuevos clientes.
 * Incluye validación en dos pasos: primero RegisterController valida CSRF y reCAPTCHA
 * (validarGuardiasRegistro), luego valida todos los campos del formulario y realiza
 * el INSERT en BD. En error repobla el formulario con $post_data.
 *
 * Variables definidas por RegisterController antes de renderizar:
 *   $mensaje_error (string) - Error de validación (formato, longitud) o email/usuario duplicado
 *   $mensaje_exito (string) - Confirmación de registro exitoso (antes de redirigir)
 *   $csrf_token    (string) - Token CSRF para proteger el formulario POST
 *   $base_url      (string) - URL base del proyecto (para el enlace de volver al login)
 *   $post_data     (array)  - Datos previos del formulario para repoblar campos tras error:
 *                              usuario, email, nombres, apellidos
 */
// --- Controlador que maneja la lógica del registro ---
require_once __DIR__ . '/../../Controllers/RegisterController.php';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registro | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        body {
            background: linear-gradient(to bottom right, #f5f7fa, #c3cfe2);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .register-container {
            max-width: 500px;
            margin: 3rem auto;
        }

        .card {
            border-radius: 15px;
        }
    </style>
</head>

<body>
    <?php
    // Ruta Navbar Corregida
    include BASE_PATH . '/src/Views/components/navbar.php';
    ?>

    <main class="flex-grow-1 d-flex align-items-center justify-content-center">
        <div class="register-container">
            <div class="card shadow-lg">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-person-plus-fill" style="font-size: 3rem; color: #0d6efd;"></i>
                        <h3 class="mt-2">Crear Cuenta</h3>
                        <p class="text-muted">Regístrate para empezar a comprar.</p>
                    </div>

                    <?php if (!empty($mensaje_error)): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <!-- Token CSRF: Protección obligatoria para poder registrarse -->
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombres" class="form-label">Nombres</label>
                                <input type="text" class="form-control" id="nombres" name="nombres"
                                    value="<?= htmlspecialchars($_POST['nombres'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="apellidos" class="form-label">Apellidos</label>
                                <input type="text" class="form-control" id="apellidos" name="apellidos"
                                    value="<?= htmlspecialchars($_POST['apellidos'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="usuario" class="form-label">Nombre de usuario</label>
                            <input type="text" class="form-control" id="usuario" name="usuario"
                                value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono (Opcional)</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono"
                                value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="clave" class="form-label">Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="clave" name="clave">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#clave"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="clave_repetida" class="form-label">Repetir Contraseña</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="clave_repetida" name="clave_repetida">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#clave_repetida"><i class="bi bi-eye"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- reCAPTCHA v2 Widget -->
                        <div class="mb-3 d-flex justify-content-center">
                            <!-- Clave de sitio (TEST) - ¡Cambiar en producción! -->
                            <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"></div>
                        </div>

                        <!-- Checkbox de Políticas de Privacidad y Términos (Ley 29733) -->
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="politicas_privacidad"
                                name="politicas_privacidad" required>
                            <label class="form-check-label" for="politicas_privacidad" style="font-size: 0.9rem;">
                                He leído y acepto los <a href="#" target="_blank">Términos y Condiciones</a> y la 
                                <a href="<?= $base_url ?>?page=about" target="_blank">Política de Privacidad y Tratamiento de Datos Personales (Ley N° 29733)</a>.
                            </label>
                            <div class="invalid-feedback">
                                Debes aceptar los términos y políticas para poder registrarte.
                            </div>
                        </div>

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-lg"></i> Registrarme
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">
                    <p class="text-center mb-0">
                        ¿Ya tienes cuenta? <a href="<?= $base_url ?>?page=login">Inicia sesión</a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <?php
    // Ruta Footer Corregida
    include BASE_PATH . '/src/Views/components/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script>
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.querySelector(targetId);
                if (input) {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
                }
            });
        });
    </script>
    <?php /* Validaciones de entrada desactivadas */ ?>
</body>

</html>