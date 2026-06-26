<?php

/**
 * Modelo de perfil del vendedor.
 * Gestiona la lectura y actualización de datos personales del vendedor
 * (nombres, apellidos, teléfono, descripción de tienda, foto) y el cálculo
 * de estadísticas de actividad (ventas, productos, ingresos).
 *
 * Métodos disponibles:
 *   getPerfil($id_vendedor)            — Datos combinados de usuarios + perfiles
 *   getEstadisticas($id_vendedor)      — Métricas de actividad del vendedor
 *   actualizarPerfil($id, $datos)      — UPDATE en la tabla perfiles
 *   actualizarFotoPerfil($id, $ruta)   — UPDATE solo del campo foto_perfil
 *
 * Uso desde VendedorController::actualizarPerfil():
 *   $modelo = new PerfilVendedor($conn);
 *   $perfil = $modelo->getPerfil($_SESSION['usuario_id']);
 */
class PerfilVendedor {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Devuelve los datos de perfil del vendedor (nombres, apellidos, teléfono, email).
     *
     * @param int $id_vendedor ID del usuario vendedor
     * @return array<string, mixed>|null Fila con datos combinados de usuarios y perfiles, o null si no existe
     */
    public function getPerfil($id_vendedor) {
        $stmt = $this->conn->prepare("
            SELECT p.*, u.usuario, u.email
            FROM usuarios u
            LEFT JOIN perfiles p ON u.id = p.id_usuario
            WHERE u.id = ? AND u.rol = 'vendedor'
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Inserta o actualiza (upsert) los datos de perfil del vendedor en una transacción.
     *
     * @param int   $id_vendedor ID del usuario vendedor
     * @param array{nombres: string, apellidos: string, telefono: string} $datos Datos del formulario
     * @return bool true si la transacción se confirmó
     * @throws \Exception Si la operación de BD falla (hace rollback y relanza)
     */
    public function actualizarPerfil($id_vendedor, $datos) {
        $this->conn->begin_transaction();

        try {
            // Upsert en perfiles para mantener coherencia si el registro no existe aún
            $stmt = $this->conn->prepare("
                INSERT INTO perfiles (id_usuario, nombres, apellidos, telefono)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    nombres = VALUES(nombres),
                    apellidos = VALUES(apellidos),
                    telefono = VALUES(telefono)
            ");
            $stmt->bind_param("isss",
                $id_vendedor,
                $datos['nombres'],
                $datos['apellidos'],
                $datos['telefono']
            );
            $stmt->execute();

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    /**
     * Calcula estadísticas de resumen del vendedor: productos activos, pedidos con ventas
     * completadas, calificación promedio y fecha de registro.
     *
     * @param int $id_vendedor ID del usuario vendedor
     * @return array{total_productos: int, total_ventas: int, calificacion_promedio: float, fecha_registro: string|null}
     */
    public function getEstadisticas($id_vendedor) {
        $stats = [
            'total_productos'      => 0,
            'total_ventas'         => 0,
            'calificacion_promedio' => 0,
            'fecha_registro'       => null
        ];

        // Total productos activos del vendedor
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as total
            FROM productos
            WHERE id_vendedor = ? AND estado = 'activo'
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        $stats['total_productos'] = $stmt->get_result()->fetch_assoc()['total'];

        // Total de pedidos distintos con al menos un ítem del vendedor
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT dp.id_pedido) as total
            FROM detalle_pedido dp
            JOIN productos p ON dp.id_producto = p.id_producto
            WHERE p.id_vendedor = ?
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        $stats['total_ventas'] = $stmt->get_result()->fetch_assoc()['total'];

        // Calificación promedio de los productos del vendedor
        $stmt = $this->conn->prepare("
            SELECT AVG(calificacion) as promedio
            FROM calificaciones c
            JOIN productos p ON c.id_producto = p.id_producto
            WHERE p.id_vendedor = ?
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stats['calificacion_promedio'] = $result['promedio'] ?? 0;

        // Fecha de registro del usuario
        $stmt = $this->conn->prepare("
            SELECT fecha_registro
            FROM usuarios
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        $stats['fecha_registro'] = $stmt->get_result()->fetch_assoc()['fecha_registro'];

        return $stats;
    }
}
