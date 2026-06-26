<?php
/**
 * Vista del formulario de contacto público de Tinkuy.
 * Permite a cualquier usuario (autenticado o no) enviar un mensaje al equipo.
 * En POST, public/index.php llama a validarContacto() y si es válido inserta
 * el mensaje en la tabla mensajes con estado 'pendiente'. El admin lo ve en admin_mensajes.
 *
 * Variables esperadas (preparadas por public/index.php case 'contact'):
 *   $mensaje_error (string) - Error de validación: campo vacío, email inválido, etc.
 *   $mensaje_exito (string) - Confirmación de mensaje guardado y enviado al admin
 *   $nombre        (string) - Valor previo del campo nombre (para repoblar tras error)
 *   $email         (string) - Valor previo del campo email
 *   $asunto        (string) - Valor previo del campo asunto
 *   $mensaje_txt   (string) - Valor previo del campo mensaje (texto libre)
 *   $base_url      (string) - URL base del proyecto
 */
// src/Views/contact.php
// Esta Vista espera que $mensaje_error, $mensaje_exito, $nombre, $email, $asunto, y $mensaje
// ya existan (porque el Controlador 'public/index.php' ya los definió).

// --- DEFINICIÓN DE RUTAS ---
$project_root = defined('PROJECT_ROOT') ? PROJECT_ROOT : "/Ecommerce-Tinkuy";
$base_url = defined('PUBLIC_URL') ? PUBLIC_URL : ($project_root . "/public"); // Para rutas HTML
$controller_url = defined('BASE_URL') ? BASE_URL : ($base_url . "/index.php"); // El "Cerebro"
$pagina_actual = 'contacto'; // Para el navbar
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Contacto | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= $base_url ?>/css/style.css"> 
    <style>
        @keyframes shake-horizontal { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); } 20%, 40%, 60%, 80% { transform: translateX(5px); } }
        .alert-error-animated { animation: shake-horizontal 0.5s ease-in-out; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100"> 
    
    <?php 
    // RUTA NAVBAR CORREGIDA
    include BASE_PATH . '/src/Views/components/navbar.php'; 
    ?>

    <div class="container my-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <i class="bi bi-envelope-heart-fill" style="font-size: 4rem; color: #0d6efd;"></i>
                    <h1 class="mt-2">Contáctanos</h1>
                    <p class="lead text-muted">¿Tienes preguntas? Estamos aquí para ayudarte.</p>
                </div>

                <div class="card shadow-lg border-0">
                    <div class="card-body p-4 p-md-5">

                        <?php if (!empty($mensaje_error)): ?>
                            <div class="alert alert-danger alert-error-animated"><?= htmlspecialchars($mensaje_error) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($mensaje_exito)): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($mensaje_exito) ?></div>
                        <?php endif; ?>

                        <form action="<?= $controller_url ?>?page=contact" method="POST" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label">Tu Nombre</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($nombre) ?>" placeholder="Ej: Juan Pérez" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Tu Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="correo@ejemplo.com" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="asunto" class="form-label">Asunto</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lightbulb-fill"></i></span>
                                    <input type="text" class="form-control" id="asunto" name="asunto" value="<?= htmlspecialchars($asunto) ?>" placeholder="Duda sobre envíos, Devolución, etc." required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="mensaje" class="form-label">Mensaje</label>
                                <textarea class="form-control" id="mensaje" name="mensaje" rows="6" placeholder="Escribe aquí tu consulta detallada..." required><?= htmlspecialchars($mensaje) ?></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send-fill"></i> Enviar Mensaje
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php 
    // RUTA FOOTER CORREGIDA
    include BASE_PATH . '/src/Views/components/footer.php'; 
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>