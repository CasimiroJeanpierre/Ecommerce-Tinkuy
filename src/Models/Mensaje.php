<?php
// src/Models/Mensaje.php

class Mensaje
{
    /**
     * Verifica si la tabla mensajes_contacto tiene la columna 'estado'.
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
     * Guarda un nuevo mensaje de contacto en la base de datos.
     *
     * @param mysqli $conn La conexión a la BD.
     * @param string $nombre
     * @param string $email
     * @param string $asunto
     * @param string $mensaje
     * @return bool True si se guardó, False si hubo un error.
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
     * Obtener todos los mensajes (para admin)
     *
     * @param mysqli $conn
     * @param string $filtro_estado 'todos', 'pendiente', 'respondido'
     * @return array
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
     * Marcar mensaje como leído
     *
     * @param mysqli $conn
     * @param int $id_mensaje
     * @return bool
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
     * Cambiar estado del mensaje
     *
     * @param mysqli $conn
     * @param int $id_mensaje
     * @param string $estado 'pendiente', 'respondido', 'archivado'
     * @return bool
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
     * Eliminar mensaje
     *
     * @param mysqli $conn
     * @param int $id_mensaje
     * @return bool
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
     * Contar mensajes por estado
     *
     * @param mysqli $conn
     * @return array
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
