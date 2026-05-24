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

const ID_ROL_CLIENTE = 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = strip_tags(trim($_POST['usuario'] ?? ''));
    $email = strip_tags(trim($_POST['email'] ?? ''));
    $clave = $_POST['clave'] ?? '';
    $nombres = strip_tags(trim($_POST['nombres'] ?? ''));
    $apellidos = strip_tags(trim($_POST['apellidos'] ?? ''));
    $telefono = strip_tags(trim($_POST['telefono'] ?? ''));
    $clave_repetida = $_POST['clave_repetida'] ?? '';

    // 1. Verificar token CSRF
    if (!Security::verificarCSRF($_POST['csrf_token'] ?? '')) {
        $mensaje_error = "Token de seguridad inválido. Recarga la página e intenta de nuevo.";
    } elseif (empty($_POST['g-recaptcha-response'])) {
        $mensaje_error = "Por favor, verifica que no eres un robot (CAPTCHA).";
    } elseif (!Security::verificarRecaptcha($_POST['g-recaptcha-response'], '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe')) {
        $mensaje_error = "Error en la validación del CAPTCHA. Intenta de nuevo.";
    } elseif (!isset($_POST['politicas_privacidad'])) {
        $mensaje_error = "Para registrarte, debes aceptar la Política de Privacidad y el Tratamiento de Datos Personales (Ley N° 29733).";
    } else {
        // 2. Si el CAPTCHA es correcto, validar el resto de los campos
        if ($clave !== $clave_repetida) {
            $mensaje_error = "Las contraseñas no coinciden.";
        } else {
            $mensaje_error =
                validarUsuario($usuario) ??
                validarEmail($email) ??
                validarClave($clave) ??
                validarNombre($nombres, 'El nombre') ??
                validarNombre($apellidos, 'El apellido') ??
                validarTelefono($telefono);
        }
    }

    if ($mensaje_error === null || $mensaje_error === "") {
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
            $_SESSION['usuario_id'] = $nuevo_usuario_id;
            $_SESSION['usuario'] = htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8');
            $_SESSION['rol'] = 'cliente';
            $_SESSION['nombre_usuario'] = htmlspecialchars($nombres, ENT_QUOTES, 'UTF-8');
            $_SESSION['apellido_usuario'] = htmlspecialchars($apellidos, ENT_QUOTES, 'UTF-8');

            header("Location: " . $base_url . "?page=index");
            exit;

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if ($e->getCode() == 1062) {
                $mensaje_error = "El usuario o email ya está registrado.";
            } else {
                $mensaje_error = "Error al registrar. Intenta de nuevo.";
            }
        }
    }
}

$csrf_token = Security::generarCSRF();
