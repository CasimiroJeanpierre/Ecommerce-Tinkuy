<?php
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

    if ($stmt) {
        // --- PASO 5: Vincular los datos (parámetros) ---
        // "sss" significa que las 3 variables son Strings (texto)
        $stmt->bind_param("sss", $nombre, $correo, $mensaje);

        // --- PASO 6: Ejecutar la consulta ---
        if ($stmt->execute()) {
            // ¡Éxito! El mensaje se guardó.
            // Redirigimos al usuario de vuelta a la página de contacto
            // con un mensaje de éxito.
            header("Location: contacto.php?status=success");
            exit;
        } else {
            // Error al ejecutar.
            // Redirigimos con un mensaje de error.
            header("Location: contacto.php?status=error_ejecucion");
            exit;
        }
        
        // Cerramos el statement
        $stmt->close();

    } else {
        // Error al preparar la consulta (ej. error de sintaxis SQL).
        header("Location: contacto.php?status=error_sql");
        exit;
    }

    // Cerramos la conexión
    $conexion->close();

} else {
    // Si alguien intenta acceder a este archivo escribiendo la URL
    // directamente, lo botamos a la página de inicio.
    header("Location: index.php");
    exit;
}
?>