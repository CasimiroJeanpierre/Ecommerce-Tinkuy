<?php

/**
 * Valida los datos del formulario de login.
 * - Usuario: 2–20 caracteres, solo letras, números, guion bajo, punto y guion.
 * - Contraseña: 6–20 caracteres, no vacía.
 */
function validarDatosLogin($usuario, $clave): ?string
{
    $usuario = trim((string) $usuario);

    if ($usuario === '')
        return "El nombre de usuario es obligatorio.";
    if (strlen($usuario) < 3 || strlen($usuario) > 50)
        return "El usuario debe tener entre 3 y 50 caracteres.";
    if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $usuario))
        return "El usuario contiene caracteres no permitidos.";

    $clave = (string) $clave;
    if ($clave === '')
        return "La contraseña es obligatoria.";
    if (strlen($clave) < 6 || strlen($clave) > 30)
        return "La contraseña debe tener entre 6 y 30 caracteres.";

    return null;
}

/**
 * Valida el nombre de usuario para registro/creación.
 */
function validarUsuario(string $usuario): ?string
{
    $usuario = trim($usuario);
    if ($usuario === '')
        return "El nombre de usuario es obligatorio.";
    if (strlen($usuario) < 3 || strlen($usuario) > 50)
        return "El usuario debe tener entre 3 y 50 caracteres.";
    if (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $usuario))
        return "El usuario solo puede contener letras, números, punto, guion y guion bajo.";
    return null;
}

/**
 * Valida formato de email.
 */
function validarEmail(string $email): ?string
{
    $email = trim($email);
    if ($email === '')
        return "El email es obligatorio.";
    if (strlen($email) > 100)
        return "El email no debe exceder 100 caracteres.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        return "El formato del email no es válido.";
    return null;
}

/**
 * Valida una contraseña nueva (registro o creación de usuario).
 */
function validarClave(string $clave): ?string
{
    if ($clave === '')
        return "La contraseña es obligatoria.";
    if (strlen($clave) < 7 || strlen($clave) > 30)
        return "La contraseña debe tener entre 7 y 30 caracteres.";
    if (!preg_match('/[A-Z]/', $clave))
        return "La contraseña debe contener al menos una mayúscula.";
    if (!preg_match('/[^a-zA-Z0-9]/', $clave))
        return "La contraseña debe contener al menos un carácter especial.";
    return null;
}

/**
 * Valida un nombre o apellido de persona.
 * @param string $campo Nombre del campo para el mensaje de error.
 */
function validarNombre(string $valor, string $campo = 'El campo'): ?string
{
    $valor = trim($valor);
    if ($valor === '')
        return "{$campo} es obligatorio.";
    if (strlen($valor) < 2 || strlen($valor) > 100)
        return "{$campo} debe tener entre 2 y 100 caracteres.";
    if (!preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $valor))
        return "{$campo} solo puede contener letras y espacios.";
    return null;
}

/**
 * Valida teléfono (opcional). Si está vacío, pasa sin error.
 */
function validarTelefono(string $telefono): ?string
{
    if ($telefono === '')
        return null;
    if (!preg_match('/^[0-9]{9}$/', $telefono))
        return "El teléfono debe tener exactamente 9 dígitos numéricos.";
    return null;
}

/**
 * Calcula el monto de descuento a aplicar basado en un porcentaje decimal.
 */
function calcularDescuentoAplicado(float $total, float $porcentaje_decimal): float
{
    if ($total < 0 || $porcentaje_decimal < 0 || $porcentaje_decimal > 1)
        return 0.0;
    return $total * $porcentaje_decimal;
}

/**
 * Calcula el total final a pagar asegurando que nunca sea negativo.
 */
function calcularTotalFinal(float $total, float $descuento): float
{
    $final = $total - $descuento;
    return $final < 0 ? 0.0 : $final;
}

/**
 * Valida los datos básicos al crear o editar un producto y sus variantes.
 */
function validarDatosProducto(string $nombre, float $precio, int $stock): ?string
{
    $nombre = trim($nombre);
    if ($nombre === '')
        return "El nombre del producto es obligatorio.";
    if (strlen($nombre) < 3 || strlen($nombre) > 100)
        return "El nombre debe tener entre 3 y 100 caracteres.";
    if ($precio <= 0)
        return "El precio debe ser mayor a 0.";
    if ($stock < 0)
        return "El stock no puede ser negativo.";
    return null;
}

/**
 * Valida el formato de una tarjeta de crédito/débito para la pasarela simulada.
 */
function validarTarjetaSimulada(string $numero, string $expiracion): ?string
{
    $numero = preg_replace('/\s+/', '', $numero); // Permite espacios y luego los limpia
    if (!preg_match('/^[0-9]{13,16}$/', $numero))
        return "El número de tarjeta debe tener entre 13 y 16 dígitos numéricos.";
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiracion))
        return "El formato de expiración debe ser MM/AA.";
    return null;
}
