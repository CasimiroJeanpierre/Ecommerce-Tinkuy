<?php

/**
 * Modelo de mensajes de contacto.
 * Gestiona la persistencia de los mensajes enviados desde el formulario público
 * de contacto en la tabla 'mensajes_contacto'.
 *
 * Métodos disponibles:
 *   guardarMensaje($conn, $nombre, $email, $asunto, $mensaje) — INSERT en mensajes_contacto
 *   getMensajes($conn, $filtro)   — Lista mensajes con filtro opcional por estado
 *   getMensaje($conn, $id)        — Detalle de un mensaje específico
 *   cambiarEstado($conn, $id, $estado) — Actualiza el campo 'estado' del mensaje
 *   marcarLeido($conn, $id)       — Actualiza leido=1 del mensaje
 *   eliminar($conn, $id)          — DELETE permanente del mensaje
 *
 * Nota: tieneColumnaEstado() verifica dinámicamente la existencia del campo
 * para compatibilidad con instalaciones antiguas sin ese campo en la tabla.
 */
class Mensaje
{
    /**
     * Verifica dinámicamente si la tabla mensajes_contacto tiene la columna 'estado'.
     * Necesario para retrocompatibilidad con instalaciones anteriores a la migración que
     * añadió el campo de estado al esquema. Usa SHOW COLUMNS FROM para inspeccionar la BD.
     * Si la consulta falla por cualquier motivo (permisos insuficientes, tabla inexistente),
     * captura la excepción y retorna false para activar la ruta de fallback.
     *
     * @param mysqli $conn Conexión activa a la base de datos
     * @return bool true si la columna 'estado' existe en mensajes_contacto; false en caso contrario
     */
    private function tieneColumnaEstado($conn)
    {
        try {
            $res = $conn->query("SHOW COLUMNS FROM mensajes_contacto LIKE 'estado'");
            return $res && $res->num_rows > 0;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }
    /**
     * Inserta un nuevo mensaje de contacto en la tabla mensajes_contacto.
     * Todos los campos se insertan con sentencia preparada para prevenir inyección SQL.
     * La fecha de envío la asigna MySQL automáticamente via DEFAULT CURRENT_TIMESTAMP
     * — no se pasa como parámetro para evitar discrepancias con el reloj del servidor PHP.
     * Si la inserción falla (ej. tabla inexistente, constraint violation), captura la excepción
     * y retorna false sin relanzarla para no exponer detalles de BD al usuario final.
     *
     * @param mysqli $conn    Conexión activa a la base de datos
     * @param string $nombre  Nombre del remitente (validado con validarContacto() en el router)
     * @param string $email   Email del remitente (validado con FILTER_VALIDATE_EMAIL previamente)
     * @param string $asunto  Asunto del mensaje (alfanumérico, sanitizado con strip_tags)
     * @param string $mensaje Cuerpo del mensaje (mínimo 10 caracteres, validado previamente)
     * @return bool true si se insertó correctamente; false si ocurrió un error de BD
     */
    public function guardarMensaje($conn, $nombre, $email, $asunto, $mensaje)
    {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $nombre, $email, $asunto, $mensaje);
            $stmt->execute();
            $stmt->close();
            return true; // Éxito

        } catch (mysqli_sql_exception $e) {
            // Opcional: registrar el error $e->getMessage()
            return false; // Error
        }
    }

    /**
     * Devuelve todos los mensajes de contacto, opcionalmente filtrados por estado.
     * Si la columna 'estado' existe en la tabla, aplica el filtro directamente en SQL.
     * Si no existe (instalación anterior a la migración), devuelve todos los mensajes
     * simulando estado='pendiente' y retorna array vacío si se pide un estado distinto
     * de 'todos' o 'pendiente' (ya que esos estados no existen sin la columna).
     * Los resultados se ordenan por fecha_envio DESC (más recientes primero).
     *
     * @param mysqli $conn          Conexión activa a la base de datos
     * @param string $filtro_estado Estado a filtrar: 'todos', 'pendiente', 'respondido', 'archivado'
     * @return array<int, array<string, mixed>> Filas de mensajes_contacto con todos sus campos
     */
    public function obtenerTodosMensajes($conn, $filtro_estado = 'todos')
    {
        $tieneEstado = $this->tieneColumnaEstado($conn);
        if ($tieneEstado) {
            $query = "SELECT * FROM mensajes_contacto";
            if ($filtro_estado !== 'todos') {
                $query .= " WHERE estado = ?";
            }
            $query .= " ORDER BY fecha_envio DESC";
            $stmt = $conn->prepare($query);
            if ($filtro_estado !== 'todos') {
                $stmt->bind_param("s", $filtro_estado);
            }
            $stmt->execute();
            $resultado = $stmt->get_result();
            return $resultado->fetch_all(MYSQLI_ASSOC);
        } else {
            // Fallback: no existe columna estado, devolver todos y simular estado 'pendiente'.
            $stmt = $conn->prepare("SELECT * FROM mensajes_contacto ORDER BY fecha_envio DESC");
            $stmt->execute();
            $resultado = $stmt->get_result();
            $mensajes = $resultado->fetch_all(MYSQLI_ASSOC);
            foreach ($mensajes as &$m) {
                $m['estado'] = 'pendiente';
            }
            // Si se pidió un filtro distinto a 'todos', con fallback no hay respondidos/archivados.
            if ($filtro_estado !== 'todos' && $filtro_estado !== 'pendiente') {
                return []; // No existen esos estados todavía.
            }
            return $mensajes;
        }
    }

    /**
     * Marca un mensaje de contacto como leído estableciendo leido=1 en la BD.
     * Se invoca automáticamente en MensajesController::ver() al renderizar el detalle
     * para que el administrador no tenga que marcar mensajes manualmente.
     * Usa sentencia preparada con parámetro enlazado para prevenir inyección SQL.
     * Si el UPDATE falla por excepción de BD, captura el error y devuelve false.
     *
     * @param mysqli $conn       Conexión activa a la base de datos
     * @param int    $id_mensaje ID del mensaje a marcar como leído
     * @return bool true si el UPDATE se ejecutó sin errores; false si ocurrió una excepción
     */
    public function marcarComoLeido($conn, $id_mensaje)
    {
        try {
            $stmt = $conn->prepare("UPDATE mensajes_contacto SET leido = 1 WHERE id_mensaje = ?");
            $stmt->bind_param("i", $id_mensaje);
            $stmt->execute();
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Actualiza el campo 'estado' de un mensaje de contacto en la BD.
     * Primero verifica con tieneColumnaEstado() si la columna existe; si no existe,
     * devuelve false sin ejecutar el UPDATE para evitar errores de esquema en instalaciones antiguas.
     * La validación del estado permitido ('pendiente', 'respondido', 'archivado') se delega
     * al controlador que llama a este método — no se valida aquí para mantener el modelo simple.
     *
     * @param mysqli $conn       Conexión activa a la base de datos
     * @param int    $id_mensaje ID del mensaje cuyo estado se actualiza
     * @param string $estado     Nuevo estado: 'pendiente', 'respondido' o 'archivado'
     * @return bool true si el UPDATE se ejecutó; false si falta la columna o hay error de BD
     */
    public function cambiarEstado($conn, $id_mensaje, $estado)
    {
        if (!$this->tieneColumnaEstado($conn)) {
            // No hay columna: no se puede cambiar hasta migrar.
            return false;
        }
        try {
            $stmt = $conn->prepare("UPDATE mensajes_contacto SET estado = ? WHERE id_mensaje = ?");
            $stmt->bind_param("si", $estado, $id_mensaje);
            $stmt->execute();
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Elimina permanentemente un mensaje de contacto de la BD (DELETE físico).
     * No hay papelera ni baja lógica: la fila se elimina de mensajes_contacto de forma irreversible.
     * Usa sentencia preparada para prevenir inyección SQL en el parámetro $id_mensaje.
     * Captura errores de BD y devuelve false en lugar de relanzar la excepción para evitar
     * exponer detalles del esquema de BD al panel de administración.
     *
     * @param mysqli $conn       Conexión activa a la base de datos
     * @param int    $id_mensaje ID del mensaje a eliminar
     * @return bool true si el DELETE se ejecutó sin errores; false si ocurrió una excepción de BD
     */
    public function eliminarMensaje($conn, $id_mensaje)
    {
        try {
            $stmt = $conn->prepare("DELETE FROM mensajes_contacto WHERE id_mensaje = ?");
            $stmt->bind_param("i", $id_mensaje);
            $stmt->execute();
            return true;
        } catch (mysqli_sql_exception $e) {
            return false;
        }
    }

    /**
     * Devuelve un resumen estadístico de los mensajes: total, pendientes, respondidos y no leídos.
     * Usa SUM(CASE WHEN ...) en una sola consulta para calcular todos los contadores a la vez.
     * Si la columna 'estado' no existe (instalación antigua), ejecuta una consulta de fallback
     * que calcula solo total y no_leidos, asumiendo pendientes=total y respondidos=0.
     * Usado por MensajesController::listar() para renderizar las estadísticas del panel de mensajes.
     *
     * @param mysqli $conn Conexión activa a la base de datos
     * @return array{total: int, pendientes: int, respondidos: int, no_leidos: int}
     *         Contadores de mensajes; 'respondidos' es 0 en el modo fallback sin columna estado
     */
    public function contarMensajes($conn)
    {
        if ($this->tieneColumnaEstado($conn)) {
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                        SUM(CASE WHEN estado = 'respondido' THEN 1 ELSE 0 END) as respondidos,
                        SUM(CASE WHEN leido = 0 THEN 1 ELSE 0 END) as no_leidos
                      FROM mensajes_contacto";
            $resultado = $conn->query($query);
            return $resultado->fetch_assoc();
        } else {
            // Fallback: todo es pendiente, respondidos=0
            $resultado = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN leido = 0 THEN 1 ELSE 0 END) as no_leidos FROM mensajes_contacto");
            $row = $resultado->fetch_assoc();
            return [
                'total' => (int)$row['total'],
                'pendientes' => (int)$row['total'],
                'respondidos' => 0,
                'no_leidos' => (int)$row['no_leidos']
            ];
        }
    }
}
