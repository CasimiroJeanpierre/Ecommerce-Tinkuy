<?php

class EnviosController {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function listarEnviosPendientes($id_vendedor) {
        $query = "
            SELECT 
                dp.id_detalle,
                dp.cantidad,
                dp.precio_historico,
                p.nombre_producto,
                vp.talla,
                vp.color,
                pe.id_pedido,
                pe.fecha_pedido,
                d.direccion,
                d.ciudad,
                d.pais,
                d.codigo_postal,
                comprador_perfil.nombres AS cliente_nombres,
                comprador_perfil.apellidos AS cliente_apellidos
            FROM 
                detalle_pedido AS dp
            JOIN 
                variantes_producto AS vp ON dp.id_variante = vp.id_variante
            JOIN 
                productos AS p ON vp.id_producto = p.id_producto
            JOIN 
                pedidos AS pe ON dp.id_pedido = pe.id_pedido
            JOIN 
                direcciones AS d ON pe.id_direccion_envio = d.id_direccion
            JOIN 
                usuarios AS comprador ON pe.id_usuario = comprador.id_usuario
            JOIN 
                perfiles AS comprador_perfil ON comprador.id_usuario = comprador_perfil.id_usuario
            WHERE 
                p.id_vendedor = ? 
                AND dp.id_estado_detalle = 2
            ORDER BY
                pe.fecha_pedido ASC
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $id_vendedor);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function listarEmpresasEnvio() {
        $resultado = $this->conn->query("SELECT * FROM empresas_envio ORDER BY nombre_empresa ASC");
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }

    public function registrarEnvio($id_detalle, $id_empresa_envio, $numero_seguimiento, $id_vendedor) {
        try {
            // Validar datos
            if ($id_detalle === 0 || $id_empresa_envio === 0 || empty($numero_seguimiento)) {
                throw new Exception("Debe seleccionar una empresa y completar el N° de seguimiento.");
            }

            // Verificar propiedad del envío
            $stmt_check = $this->conn->prepare("
                SELECT p.id_vendedor
                FROM detalle_pedido dp
                JOIN variantes_producto vp ON dp.id_variante = vp.id_variante
                JOIN productos p ON vp.id_producto = p.id_producto
                WHERE dp.id_detalle = ?
            ");
            $stmt_check->bind_param("i", $id_detalle);
            $stmt_check->execute();
            $fila_check = $stmt_check->get_result()->fetch_assoc();

            if (!$fila_check || $fila_check['id_vendedor'] != $id_vendedor) {
                throw new Exception("Error de permisos: Usted no puede modificar este envío.");
            }

            // Actualizar envío
            $id_estado_enviado = 3;
            $stmt_envio = $this->conn->prepare(
                "UPDATE detalle_pedido SET id_empresa_envio = ?, numero_seguimiento = ?, id_estado_detalle = ? WHERE id_detalle = ?"
            );
            $stmt_envio->bind_param("isii", $id_empresa_envio, $numero_seguimiento, $id_estado_enviado, $id_detalle);
            $stmt_envio->execute();

            if ($stmt_envio->affected_rows > 0) {
                return ["success" => true, "message" => "Envío para el ítem #$id_detalle registrado correctamente."];
            } else {
                throw new Exception("No se pudo actualizar la información de envío.");
            }
        } catch (Exception $e) {
            return ["success" => false, "message" => "Error al registrar envío: " . $e->getMessage()];
        }
    }
}