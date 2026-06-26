<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Controlador de pagos y checkout.
 * Encapsula la lógica de validación del carrito, recalculo de precios en servidor,
 * verificación de stock y creación atómica del pedido con sus detalles.
 * Todas las escrituras se realizan dentro de una transacción para garantizar
 * consistencia ante fallos parciales (INSERT pedidos + INSERT detalle_pedido).
 *
 * Métodos disponibles:
 *   getDetallesCarrito($carrito)       — Enriquece el carrito con nombre, precio e imagen
 *   aplicarCupon($codigo, $total)      — Valida y aplica el descuento del cupón
 *   procesarPago($carrito, $datos)     — Crea el pedido completo en transacción
 *   getDireccionesUsuario($id_usuario) — Devuelve las direcciones guardadas del usuario
 *
 * Seguridad:
 *   Los precios se recalculan en servidor desde BD; el cliente no puede manipular totales.
 *   El stock se verifica y descuenta dentro de la misma transacción que crea el pedido.
 */
class PaymentController {
    private $conn;

    public function __construct($conn) {
        if (!$conn) {
            throw new Exception("Se requiere una conexión a la base de datos válida");
        }
        $this->conn = $conn;
    }

    /**
     * Enriquece el carrito de sesión con datos reales de BD (nombre, imagen, talla, color,
     * precio) y calcula subtotal por ítem y total general.
     * La consulta usa un IN() preparado con placeholders dinámicos para obtener todos los
     * ítems en una sola consulta, independientemente del tamaño del carrito.
     * Los precios provienen de la BD en este momento — pueden diferir del precio que tenían
     * cuando el usuario los agregó al carrito (no hay snapshot hasta el checkout).
     * El total retornado es solo referencial en la vista; el total definitivo lo calcula
     * validarYCalcularPrecios() dentro de la transacción de procesarPago().
     *
     * @param array<int, array{cantidad: int}> $carrito Mapa id_variante → ['cantidad' => int]
     * @return array{items: array<int, array<string, mixed>>, total: float}
     *         items: array con nombre, imagen, talla, color, cantidad, precio, subtotal por ítem
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
     * Devuelve todas las direcciones de envío guardadas del usuario, ordenadas por ID descendente
     * (la más reciente primero) para pre-seleccionar la última dirección en el checkout.
     * No filtra por estado activo/inactivo — todas las direcciones del usuario se incluyen.
     * Usado por el router del checkout para poblar el selector de dirección de envío antes
     * de mostrar la vista de confirmación de pago.
     *
     * @param int $id_usuario ID del usuario autenticado (FK → usuarios.id_usuario)
     * @return array<int, array<string, mixed>> Filas de la tabla direcciones, más reciente primero
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
     * Devuelve las tarjetas de pago de referencia del usuario (nunca datos sensibles completos).
     * Solo se almacena el nombre del titular, los últimos 4 dígitos, la fecha de expiración y el tipo
     * (Visa, Mastercard, etc.) — nunca el PAN completo ni el CVV, para cumplir con PCI-DSS.
     * Ordenado por id_tarjeta DESC para mostrar la tarjeta más recientemente agregada primero.
     * Usado en la vista de checkout para que el usuario seleccione un método de pago guardado.
     *
     * @param int $id_usuario ID del usuario autenticado
     * @return array<int, array<string, mixed>> Filas con id_tarjeta, nombre_tarjeta, ultimos_4_digitos,
     *         expiracion y tipo; vacío si el usuario no tiene tarjetas guardadas
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
     * Orquesta el flujo completo de creación de pedido en cuatro pasos atómicos.
     * Paso 1: validarDireccion() — verifica que la dirección exista y pertenezca al usuario (anti-IDOR).
     * Paso 2: validarYCalcularPrecios() — recalcula precios desde BD y verifica stock (anti-manipulación).
     * Paso 3: crearPedido() — inserta la cabecera del pedido con estado 2 (Pagado).
     * Paso 4: procesarDetallesPedido() — inserta detalles y descuenta stock con condición de carrera.
     * Todos los pasos 2-4 ocurren dentro de una transacción mysqli; cualquier Exception hace rollback.
     * El resultado exitoso incluye order_id para que la vista de confirmación muestre el número de pedido.
     *
     * @param int   $id_usuario   ID del comprador (de $_SESSION['usuario_id'])
     * @param int   $id_direccion ID de la dirección de envío seleccionada en el formulario de checkout
     * @param array<int, array{cantidad: int}> $carrito Mapa id_variante → ['cantidad' => int]
     * @return array{success: bool, message: string, order_id: int} Resultado con ID del pedido creado
     * @throws \Exception Si el carrito está vacío, la dirección es inválida, o hay fallo en la BD
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
     * Verifica que la dirección de envío exista y pertenezca al usuario autenticado (anti-IDOR).
     * Usa SELECT 1 con WHERE id_direccion = ? AND id_usuario = ? para que un usuario no pueda
     * enviar un pedido a la dirección de otro usuario manipulando el parámetro id_direccion.
     * Si la dirección no existe o no pertenece al usuario, procesarPago() lanza excepción
     * y hace rollback antes de crear el pedido.
     * Diseño intencional: no se informa si la dirección existe pero pertenece a otro usuario,
     * solo se retorna bool para no revelar información sobre direcciones ajenas.
     *
     * @param int $id_direccion ID de la dirección de envío enviada por el formulario de checkout
     * @param int $id_usuario   ID del usuario autenticado en sesión
     * @return bool true si la dirección existe y pertenece al usuario; false en caso contrario
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
     * Recalcula precios directamente desde la BD para prevenir manipulación de totales por el cliente
     * y verifica que el stock sea suficiente para cada ítem antes de crear el pedido.
     * La consulta usa IN() para obtener precio y stock de todas las variantes en una sola query.
     * Si alguna variante tiene stock < cantidad solicitada, retorna ['valid' => false] inmediatamente.
     * Si el conteo de variantes encontradas en BD difiere del carrito, significa que alguna variante
     * ya no existe o fue eliminada; también retorna ['valid' => false] para abortar el pedido.
     * Los precios resultantes (prices) se pasan a procesarDetallesPedido() como precio_historico
     * para crear un snapshot inmutable del precio en el momento de compra.
     *
     * @param array<int, array{cantidad: int}> $carrito Mapa id_variante → ['cantidad' => int]
     * @return array{valid: bool, message?: string, total?: float, prices?: array<int, float>}
     *         Si valid=false incluye 'message'; si valid=true incluye 'total' y 'prices'
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
     * Inserta la fila de cabecera en la tabla 'pedidos' dentro de la transacción abierta
     * por procesarPago() y devuelve el ID auto-incremental generado por MySQL.
     * El pedido se crea directamente con id_estado_pedido = 2 (Pagado / Confirmado)
     * porque este método solo se alcanza después de que validarYCalcularPrecios() validó
     * precios y stock — no existe un estado intermedio "pendiente de validación".
     * La fecha_pedido se asigna con NOW() del servidor MySQL para coherencia de timezone con BD.
     * Este método debe llamarse dentro de una transacción activa; no gestiona begin/commit propios.
     *
     * @param int   $id_usuario   ID del comprador (FK → usuarios.id_usuario)
     * @param int   $id_direccion ID de la dirección de envío ya validada por validarDireccion()
     * @param float $total        Total calculado y validado por validarYCalcularPrecios()
     * @return int ID del pedido recién insertado (insert_id del mysqli)
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
     * Itera sobre los ítems del carrito e inserta una fila en 'detalle_pedido' y actualiza
     * el stock de 'variantes_producto' por cada ítem, dentro de la transacción de procesarPago().
     * El UPDATE de stock usa la cláusula AND stock >= ? para garantizar atomicidad: si entre
     * la validación y el INSERT otro comprador agotó el stock, affected_rows será 0 y se lanza
     * excepción para hacer rollback del pedido completo (ningún detalle queda a medias).
     * precio_historico almacena el precio en el momento de la compra (snapshot) para proteger
     * el historial de ventas ante futuros cambios de precio en la tabla variantes_producto.
     * Los dos statements preparados (stmt_detalle y stmt_stock) se reutilizan en el bucle
     * para evitar re-parsear la consulta SQL en cada iteración.
     *
     * @param int                              $id_pedido      ID del pedido creado por crearPedido()
     * @param array<int, array{cantidad: int}> $carrito        Mapa id_variante → cantidad del carrito
     * @param array<int, float>                $precios_reales Mapa id_variante → precio validado en BD
     * @throws \Exception Si affected_rows === 0 al actualizar stock (posible condición de carrera)
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
