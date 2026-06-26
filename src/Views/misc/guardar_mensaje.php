<?php
/**
 * Controlador procedural de guardado de mensajes de contacto (ruta legacy).
 * Recibe los datos del formulario de contacto por POST e inserta el mensaje
 * en la tabla 'mensajes_contacto' de la base de datos 'tinkuy_db'.
 * Usa prepared statements para prevenir inyección SQL.
 *
 * Flujo:
 *   1. Verifica que la petición sea POST; si no, redirige a index.php
 *   2. Recibe y sanitiza nombre, correo y mensaje de $_POST
 *   3. Prepara e inserta el mensaje en mensajes_contacto
 *   4. Redirige a contacto.php con parámetro status=success o status=error_*
 *
 * @deprecated Usar la ruta ?page=contact del router MVC (public/index.php)
 *             con la clase Mensaje::guardarMensaje() y validación completa vía validarContacto().
 *             Este archivo usa una conexión directa legacy y no valida los datos del formulario.
 */
/*
 * ========================================
 * GUARDAR_MENSAJE.PHP
 * ========================================
 * Este archivo recibe los datos del formulario de contacto
 * y los inserta en la base de datos 'tinkuy_db',
 * en la tabla 'mensajes_contacto'.
 */

// --- PASO 1: Incluir tu archivo de conexión ---
/* * Reemplaza 'conexion.php' con el nombre real de tu
 * archivo que contiene la conexión a la base de datos (mysqli).
 */
include 'conexion.php';

// --- PASO 2: Verificar que los datos lleguen por POST ---
/*
 * Esto es una medida de seguridad. Solo ejecutamos el código
 * si el formulario fue enviado usando el método POST.
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- PASO 3: Recibir y limpiar los datos del formulario ---
    /*
     * Usamos los nombres (name="") de tu formulario HTML.
     * Voy a asumir que se llaman 'nombre', 'correo' y 'mensaje'.
     * Si en tu HTML se llaman diferente, ajústalos aquí.
     */
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $mensaje = $_POST['mensaje'];

    // --- PASO 4: Preparar la consulta SQL para insertar ---
    /*
     * Usamos la tabla 'mensajes_contacto'.
     * Vamos a insertar en las columnas 'nombre', 'correo' y 'mensaje'.
     * (Asumo que 'id_mensaje' es AUTO_INCREMENT y 'leido' tiene un valor por defecto 0).
     */
    $sql = "INSERT INTO mensajes_contacto (nombre, correo, mensaje) VALUES (?, ?, ?)";

    // Preparamos la consulta para evitar inyección SQL
    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        header("Location: contacto.php?status=error_sql");
        exit;
    }
    $stmt->bind_param("sss", $nombre, $correo, $mensaje);
    if (!$stmt->execute()) {
        header("Location: contacto.php?status=error_ejecucion");
        exit;
    }
    header("Location: contacto.php?status=success");
    exit;

} else {
    // Si alguien intenta acceder a este archivo escribiendo la URL
    // directamente, lo botamos a la página de inicio.
    header("Location: index.php");
    exit;
}
?>