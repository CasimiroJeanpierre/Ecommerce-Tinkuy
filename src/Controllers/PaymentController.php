<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class PaymentController {
    private $conn;

    public function __construct($conn) {
        if (!$conn) {
            throw new Exception("Se requiere una conexión a la base de datos válida");
        }
        $this->conn = $conn;
    }

    /**
     * Obtiene los detalles del carrito y calcula totales
     */
    public function getDetallesCarrito($carrito) {
        if (empty($carrito)) {
            return ['items' => [], 'total' => 0];
        }

        $ids_variantes = array_keys($carrito);
        $placeholders = implode(',', array_fill(0, count($ids_variantes), '?'));
        $tipos = str_repeat('i', count($ids_variantes));

        $query = "
            SELECT v.id_variante, v.talla, v.color, v.precio,
                   p.nombre_producto, p.imagen_principal
            FROM variantes_producto AS v 
            JOIN productos AS p ON v.id_producto = p.id_producto
            WHERE v.id_variante IN ($placeholders)
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($tipos, ...$ids_variantes);
        $stmt->execute();
        $resultado = $stmt->get_result();
        
        $items = [];
        $total = 0;
        
        while ($producto = $resultado->fetch_assoc()) {
            $id_variante = $producto['id_variante'];
            $cantidad = $carrito[$id_variante]['cantidad'];
            $precio = $producto['precio'];
            $subtotal = $precio * $cantidad;
            $total += $subtotal;
            
            $items[] = [
                'id_variante' => $id_variante,
                'nombre' => $producto['nombre_producto'],
                'imagen' => $producto['imagen_principal'],
                'talla' => $producto['talla'],
                'color' => $producto['color'],
                'cantidad' => $cantidad,
                'precio' => $precio,
                'subtotal' => $subtotal
            ];
        }
        
        return [
            'items' => $items,
            'total' => $total
        ];
    }

    /**
     * Obtiene las direcciones de envío del usuario
     */
    public function getDireccionesEnvio($id_usuario) {
        $stmt = $this->conn->prepare("
            SELECT * FROM direcciones WHERE id_usuario = ? ORDER BY id_direccion DESC
        ");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Obtiene tarjetas guardadas (simuladas) del usuario
     */
    public function getTarjetasUsuario($id_usuario) {
        $stmt = $this->conn->prepare("
            SELECT id_tarjeta, nombre_tarjeta, ultimos_4_digitos, expiracion, tipo
            FROM tarjetas_usuario
            WHERE id_usuario = ?
            ORDER BY id_tarjeta DESC
        ");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Procesa el pago de un pedido (ISO 25010)
     */
    public function procesarPago($id_usuario, $id_direccion, $carrito) {
        if (empty($carrito)) {
            throw new Exception("El carrito está vacío");
        }

        if (!is_numeric($id_usuario) || !is_numeric($id_direccion)) {
            throw new Exception("ID de usuario o dirección inválidos");
        }

        if (!$this->validarDireccion($id_direccion, $id_usuario)) {
            throw new Exception("La dirección seleccionada no es válida");
        }

        // 🔒 Inicia transacción segura
        $this->conn->begin_transaction();

        try {
            // 1️⃣ Recalcular precios y validar stock
            $resultado_validacion = $this->validarYCalcularPrecios($carrito);
            if (!$resultado_validacion['valid']) {
                throw new Exception($resultado_validacion['message']);
            }

            $total_seguro = $resultado_validacion['total'];
            $precios_reales = $resultado_validacion['prices'];

            // 2️⃣ Crear el pedido
            $id_pedido = $this->crearPedido($id_usuario, $id_direccion, $total_seguro);

            // 3️⃣ Insertar detalles y actualizar stock
            $this->procesarDetallesPedido($id_pedido, $carrito, $precios_reales);

            // 4️⃣ Confirmar todo
            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Pedido procesado correctamente',
                'order_id' => $id_pedido
            ];

        } catch (Exception $e) {
            $this->conn->rollback();
            throw new Exception("Error al procesar el pago: " . $e->getMessage());
        }
    }

    /**
     * Valida que la dirección pertenezca al usuario
     */
    private function validarDireccion($id_direccion, $id_usuario) {
        $stmt = $this->conn->prepare("
            SELECT 1 FROM direcciones 
            WHERE id_direccion = ? AND id_usuario = ?
        ");
        $stmt->bind_param("ii", $id_direccion, $id_usuario);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    /**
     * Valida precios del carrito y calcula el total
     */
    private function validarYCalcularPrecios($carrito) {
        $ids_variantes = array_keys($carrito);
        $placeholders = implode(',', array_fill(0, count($ids_variantes), '?'));
        $tipos = str_repeat('i', count($ids_variantes));

        $stmt = $this->conn->prepare("
            SELECT id_variante, precio, stock 
            FROM variantes_producto 
            WHERE id_variante IN ($placeholders)
        ");
        $stmt->bind_param($tipos, ...$ids_variantes);
        $stmt->execute();
        $resultado = $stmt->get_result();

        $precios_reales = [];
        $total = 0;

        while ($fila = $resultado->fetch_assoc()) {
            $id_variante = $fila['id_variante'];

            if ($fila['stock'] < $carrito[$id_variante]['cantidad']) {
                return [
                    'valid' => false,
                    'message' => "Stock insuficiente para el producto ID $id_variante"
                ];
            }

            $precios_reales[$id_variante] = (float)$fila['precio'];
            $total += $precios_reales[$id_variante] * $carrito[$id_variante]['cantidad'];
        }

        if (count($precios_reales) !== count($carrito)) {
            return [
                'valid' => false,
                'message' => "Uno o más productos ya no están disponibles"
            ];
        }

        return [
            'valid' => true,
            'total' => $total,
            'prices' => $precios_reales
        ];
    }

    /**
     * Crea un nuevo pedido
     */
    private function crearPedido($id_usuario, $id_direccion, $total) {
        $stmt = $this->conn->prepare("
            INSERT INTO pedidos (
                id_usuario, 
                id_direccion_envio, 
                id_estado_pedido, 
                total_pedido, 
                fecha_pedido
            ) VALUES (?, ?, 2, ?, NOW())
        ");
        $stmt->bind_param("iid", $id_usuario, $id_direccion, $total);
        $stmt->execute();
        return $this->conn->insert_id;
    }

    /**
     * Procesa los detalles del pedido y actualiza el stock
     */
    private function procesarDetallesPedido($id_pedido, $carrito, $precios_reales) {
        $stmt_detalle = $this->conn->prepare("
            INSERT INTO detalle_pedido (
                id_pedido, id_variante, cantidad, precio_historico
            ) VALUES (?, ?, ?, ?)
        ");
        
        $stmt_stock = $this->conn->prepare("
            UPDATE variantes_producto 
            SET stock = stock - ? 
            WHERE id_variante = ? AND stock >= ?
        ");

        foreach ($carrito as $id_variante => $item) {
            $cantidad = $item['cantidad'];
            $precio = $precios_reales[$id_variante];

            // Insertar detalle
            $stmt_detalle->bind_param("iiid", $id_pedido, $id_variante, $cantidad, $precio);
            $stmt_detalle->execute();

            // Actualizar stock
            $stmt_stock->bind_param("iii", $cantidad, $id_variante, $cantidad);
            $stmt_stock->execute();

            if ($stmt_stock->affected_rows === 0) {
                throw new Exception("Error al actualizar el stock del producto $id_variante");
            }
        }
    }
}
