<?php
/**
 * Funciones de validación reutilizables del sistema Ecommerce-Tinkuy.
 * Contiene las validaciones de formato para los formularios de login y registro.
 * Se incluye desde public/index.php en el bootstrap de cada petición.
 *
 * Funciones disponibles:
 *   validarDatosLogin($usuario, $clave)     — Valida formato de credenciales de login
 *   validarDatosRegistro($usuario, $email,
 *     $clave, $clave2, $nombres, $apellidos) — Valida todos los campos del formulario de registro
 *
 * Todas las funciones devuelven string con el primer error encontrado, o null si es válido.
 * Los mensajes de error están en español y son seguros para mostrar directamente al usuario.
 */

/**
 * Valida los datos del formulario de login.
 * - Usuario: 3-50 caracteres, solo letras, números, guion bajo, punto y guion.
 * - Contraseña: 6-30 caracteres, no vacía.
 *
 * @param string|mixed $usuario Nombre de usuario recibido del formulario
 * @param string|mixed $clave   Contraseña recibida del formulario
 * @return string|null Mensaje de error o null si los datos son válidos
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
 * Valida el nombre de usuario para registro o creación de cuenta.
 * Reglas: 3-50 caracteres, solo letras, números, punto, guion y guion bajo.
 *
 * @param string $usuario Nombre de usuario a validar
 * @return string|null Mensaje de error o null si es válido
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
 * Valida el formato de una dirección de correo electrónico.
 * Máximo 100 caracteres; pasa por FILTER_VALIDATE_EMAIL.
 *
 * @param string $email Dirección de email a validar
 * @return string|null Mensaje de error o null si el formato es correcto
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
 * Valida una contraseña nueva para registro o creación de usuario.
 * Reglas: 7-30 caracteres, al menos una mayúscula y un carácter especial.
 *
 * @param string $clave Contraseña en texto plano a validar
 * @return string|null Mensaje de error o null si cumple todos los requisitos
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
 * Reglas: 2-100 caracteres, solo letras y espacios (incluye acentos y ñ).
 *
 * @param string $valor Valor del campo a validar
 * @param string $campo Etiqueta del campo para personalizar el mensaje de error
 * @return string|null Mensaje de error o null si el valor es válido
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
 * Valida un número de teléfono. Es opcional: cadena vacía pasa sin error.
 * Formato requerido: exactamente 9 dígitos numéricos.
 *
 * @param string $telefono Número de teléfono a validar (puede ser vacío)
 * @return string|null Mensaje de error o null si es válido o vacío
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
 * Calcula el monto de descuento a aplicar sobre un total dado.
 * Devuelve 0 si los parámetros están fuera de rango (negativos o > 1).
 *
 * @param float $total              Total del carrito antes del descuento
 * @param float $porcentaje_decimal Porcentaje en decimal (ej. 0.15 = 15%)
 * @return float Monto a descontar (nunca negativo)
 */
function calcularDescuentoAplicado(float $total, float $porcentaje_decimal): float
{
    if ($total < 0 || $porcentaje_decimal < 0 || $porcentaje_decimal > 1)
        return 0.0;
    return $total * $porcentaje_decimal;
}

/**
 * Calcula el total final a pagar después de aplicar el descuento.
 * Garantiza que el resultado nunca sea negativo.
 *
 * @param float $total     Total del carrito antes del descuento
 * @param float $descuento Monto del descuento a restar (calculado con calcularDescuentoAplicado)
 * @return float Total final a pagar (mínimo 0.00)
 */
function calcularTotalFinal(float $total, float $descuento): float
{
    $final = $total - $descuento;
    return $final < 0 ? 0.0 : $final;
}

/**
 * Valida los datos básicos de un producto al crearlo o editarlo.
 * Reglas: nombre 3-100 caracteres, precio > 0, stock >= 0.
 *
 * @param string $nombre Nombre del producto
 * @param float  $precio Precio unitario (debe ser mayor a 0)
 * @param int    $stock  Cantidad en stock (puede ser 0, no negativo)
 * @return string|null Mensaje de error o null si todos los datos son válidos
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
 * Valida el formato de una tarjeta de crédito/débito para la pasarela de pago simulada.
 * Número: 13-16 dígitos; expiración: formato MM/AA.
 *
 * @param string $numero     Número de tarjeta (puede incluir espacios, se limpian)
 * @param string $expiracion Fecha de expiración en formato MM/AA
 * @return string|null Mensaje de error o null si el formato es válido
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
