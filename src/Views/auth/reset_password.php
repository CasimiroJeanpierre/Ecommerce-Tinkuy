<?php
/**
 * Vista y mini-controlador de restablecimiento de contraseña por token.
 * Se accede con ?page=reset_password&token=UUID desde el enlace del correo.
 * Valida que el token exista, no haya expirado y no haya sido usado antes.
 * Si el token es válido muestra el formulario de nueva contraseña.
 *
 * Flujo:
 *   1. Lee el token de GET y lo busca en password_resets (usado=0, expiracion>now).
 *   2. En GET válido: muestra formulario de nueva contraseña con $show_form=true.
 *   3. En POST: valida nueva contraseña (7-30 chars, mayúscula, especial), hace UPDATE
 *      en usuarios y marca el token como usado=1 en password_resets.
 *   4. En éxito: redirige al login con mensaje flash.
 *
 * Variables disponibles:
 *   $conn          (mysqli) - Conexión a la BD (provista por db.php)
 *   $base_url      (string) - URL base del proyecto
 *   $mensaje       (string) - Resultado de la operación: éxito, error o token inválido
 *   $tipo_mensaje  (string) - Tipo Bootstrap: 'success'|'danger'|'warning'|'info'
 *   $token_valido  (bool)   - true si el token GET es válido y no ha expirado
 *   $show_form     (bool)   - true para mostrar el formulario de nueva contraseña
 */
// reset_password.php - Restablecer contraseña usando token del email
// Este archivo debe ser cargado vía router: ?page=reset_password&token=...
// Variables disponibles: $conn (db.php), $base_url (index.php)

$mensaje = '';
$tipo_mensaje = 'info';
$show_form = false;
$token_raw = $_GET['token'] ?? '';
$email_asociado = null;

/**
 * Actualiza la contraseña del usuario asociado al token y elimina el token usado.
 * Devuelve array con claves: ok (bool), mensaje (string), show_form (bool), token (string|null).
 */
function aplicarResetContrasena(string $token_post, string $clave_nueva, $conn): array
{
    $token_hash = hash('sha256', $token_post);
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token_hash = ? AND expiracion > NOW() LIMIT 1");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $res  = $stmt->get_result();
    $stmt->close();

    if ($res->num_rows !== 1) {
        return ['ok' => false, 'mensaje' => "Error: El enlace de restablecimiento es inválido o ha expirado.", 'show_form' => false, 'token' => null];
    }

    $email    = $res->fetch_assoc()['email'];
    $new_hash = password_hash($clave_nueva, PASSWORD_DEFAULT);

    try {
        $conn->begin_transaction();

        $upd = $conn->prepare("UPDATE usuarios SET clave_hash = ? WHERE email = ?");
        $upd->bind_param("ss", $new_hash, $email);
        $upd->execute();

        $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->bind_param("s", $email);
        $del->execute();

        $conn->commit();

        // Invalidate any active session for this user after password change
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);

        return ['ok' => true, 'mensaje' => "Contraseña actualizada correctamente. Ya puedes iniciar sesión.", 'show_form' => false, 'token' => null];
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log("Error DB en reset_password: " . $e->getMessage());
        return ['ok' => false, 'mensaje' => "Ocurrió un error al actualizar la contraseña. Inténtalo de nuevo.", 'show_form' => true, 'token' => $token_post];
    }
}

// --- LÓGICA GET: Validar el token de la URL ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($token_raw)) {
    $token_hash = hash('sha256', $token_raw);
    
    // Validar token y que no haya expirado
    $stmt = $conn->prepare("SELECT email, expiracion FROM password_resets WHERE token_hash = ? AND expiracion > NOW() LIMIT 1");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        // Token válido y no expirado, mostrar formulario
        $row = $res->fetch_assoc();
        $email_asociado = $row['email']; // Guardamos el email para usarlo después
        $show_form = true; 
    } else {
        // Token inválido o expirado. Verificar por qué.
        $stmt_check_exist = $conn->prepare("SELECT expiracion FROM password_resets WHERE token_hash = ? LIMIT 1");
        $stmt_check_exist->bind_param("s", $token_hash);
        $stmt_check_exist->execute();
        $res_exist = $stmt_check_exist->get_result();
        // El token nunca fue válido o ya se usó (INT-REST-3)
        $mensaje = "Error (INT-REST-3): El enlace de restablecimiento no es válido o ya fue utilizado.";
        if ($res_exist->num_rows === 1) {
            // El token existió pero expiró (INT-REST-2)
            $mensaje = "Error (INT-REST-2): El enlace de restablecimiento ha expirado. Solicita uno nuevo.";
        }
        $tipo_mensaje = 'danger';
    }
    $stmt->close();
    
// --- LÓGICA POST: Procesar el formulario de nueva contraseña ---
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token_post = $_POST['token'] ?? ''; // Token del campo hidden
    $clave_nueva = $_POST['clave_nueva'] ?? '';
    $clave_confirm = $_POST['clave_confirm'] ?? '';

    // --- INICIO VALIDACIÓN DE CALIDAD (IDs 19, 21-26, 85) ---
    if (empty($clave_nueva) || empty($clave_confirm) || empty($token_post)) {
        $mensaje = "Error (ID 25): Todos los campos son obligatorios.";
        $tipo_mensaje = 'danger';
        $show_form = true; // Mostrar formulario de nuevo si falla
        $token_raw = $token_post; // Mantener el token en el form
    } elseif ($clave_nueva !== $clave_confirm) {
        $mensaje = "Error (ID 85): Las contraseñas no coinciden.";
        $tipo_mensaje = 'danger';
        $show_form = true; 
        $token_raw = $token_post; 
        
    // --- VALIDACIÓN DE CALIDAD DE LA CONTRASEÑA NUEVA ---
    } elseif (strlen($clave_nueva) < 7) { // CORREGIDO: Mínimo 7
        $mensaje = "Error (ID 21/99): La contraseña debe tener mínimo 7 caracteres.";
        $tipo_mensaje = 'danger';
        $show_form = true; 
        $token_raw = $token_post; 
    } elseif (strlen($clave_nueva) > 30) { // AÑADIDO: Máximo 30
        $mensaje = "Error (ID 22/100): La contraseña debe tener máximo 30 caracteres.";
        $tipo_mensaje = 'danger';
        $show_form = true; 
        $token_raw = $token_post; 
    } elseif (!preg_match('/[A-Z]/', $clave_nueva)) { // AÑADIDO: Mayúscula
        $mensaje = "Error (ID 23): La contraseña debe contener al menos una mayúscula.";
        $tipo_mensaje = 'danger';
        $show_form = true; 
        $token_raw = $token_post; 
    } elseif (!preg_match('/[^a-zA-Z0-9]/', $clave_nueva)) { // AÑADIDO: Especial
        $mensaje = "Error (ID 24): La contraseña debe contener al menos un carácter especial.";
        $tipo_mensaje = 'danger';
        $show_form = true; 
        $token_raw = $token_post; 
    }
    // --- FIN VALIDACIÓN ---
    else {
        $resultado  = aplicarResetContrasena($token_post, $clave_nueva, $conn);
        $mensaje    = ($resultado['ok'] ? "✅ " : "") . $resultado['mensaje'];
        $tipo_mensaje = $resultado['ok'] ? 'success' : 'danger';
        $show_form  = $resultado['show_form'];
        if ($resultado['token'] !== null) {
            $token_raw = $resultado['token'];
        }
    }
} elseif (empty($token_raw) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
     // Si se accede sin token GET y no es un POST fallido (INT-REST-4)
     $mensaje = "Error (INT-REST-4): Falta el token de restablecimiento. Solicita un nuevo enlace.";
     $tipo_mensaje = 'warning';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Restablecer Contraseña | Tinkuy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
<div class="card p-4 shadow-sm" style="max-width: 400px; width: 90%;">
    <div class="text-center mb-4">
        <i class="bi bi-shield-lock-fill" style="font-size: 3rem; color: #0d6efd;"></i>
        <h3 class="mt-2">Restablecer Contraseña</h3>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= htmlspecialchars($tipo_mensaje) ?>"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($show_form): ?>
        <p class="text-muted text-center">Ingresa tu nueva contraseña para la cuenta <?= htmlspecialchars($email_asociado ?? '') ?>.</p>
        <form method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token_raw) ?>">
            
            <div class="mb-3">
                <label for="clave_nueva" class="form-label">Nueva contraseña</label>
                 <div class="input-group">
                     <span class="input-group-text"><i class="bi bi-lock"></i></span>
                     <input type="password" name="clave_nueva" id="clave_nueva" class="form-control" 
                            required 
                            minlength="7" 
                            maxlength="30"
                            aria-describedby="passwordHelpBlockReset">
                 </div>
                 <div id="passwordHelpBlockReset" class="form-text">
                     (IDs 19, 21-24) Debe tener 7-30 caracteres, 1 mayúscula y 1 carácter especial (ej. #, $, !).
                 </div>
            </div>
            
            <div class="mb-3">
                <label for="clave_confirm" class="form-label">Confirmar nueva contraseña</label>
                 <div class="input-group">
                     <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                     <input type="password" name="clave_confirm" id="clave_confirm" class="form-control" 
                            required 
                            minlength="7" 
                            maxlength="30"
                            title="Repite la nueva contraseña. (ID 85)">
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-save"></i> Guardar nueva contraseña
            </button>
        </form>
    <?php elseif ($tipo_mensaje === 'success'): ?>
        <div class="text-center mt-3">
            <a href="<?= $base_url ?>?page=login" class="btn btn-success w-100"><i class="bi bi-box-arrow-in-right"></i> Ir a Iniciar Sesión</a>
        </div>
    <?php else: ?>
         <div class="text-center mt-3">
            <a href="<?= $base_url ?>?page=forgot_password" class="btn btn-warning w-100"><i class="bi bi-arrow-clockwise"></i> Solicitar un nuevo enlace</a>
        </div>
    <?php endif; ?>
    
    <hr>
    <div class="text-center">
         <a href="<?= $base_url ?>?page=login" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Volver al inicio de sesión</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>