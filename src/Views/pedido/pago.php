<?php
require_once BASE_PATH . '/src/Core/db.php';
require_once BASE_PATH . '/src/Controllers/PaymentController.php';

// Control de acceso (Seguridad)
$base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['redirect_url'] = '?page=pago';
    header('Location: ' . $base_url . '?page=login');
    exit;
}

// Validar carrito
if (empty($_SESSION['carrito'])) {
    header('Location: ' . $base_url . '?page=cart');
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$mensaje_error = "";
$mensaje_exito = "";
$carrito_items = [];
$total_general = 0;

// Inicializar el controlador
$paymentController = new PaymentController($conn);

// Obtener direcciones y detalles del carrito
try {
    $direcciones = $paymentController->getDireccionesEnvio($id_usuario);
    $resultado_carrito = $paymentController->getDetallesCarrito($_SESSION['carrito']);
    $carrito_items = $resultado_carrito['items'];
    $total_general = $resultado_carrito['total'];

    $descuento_aplicado = 0;
    $total_con_descuento = $total_general;
    if (isset($_SESSION['cupon'])) {
        $descuento_aplicado = calcularDescuentoAplicado($total_general, (float) $_SESSION['cupon']['descuento']);
        $total_con_descuento = calcularTotalFinal($total_general, $descuento_aplicado);
    }

    // Tarjetas guardadas del usuario (simuladas)
    $tarjetas_guardadas = $paymentController->getTarjetasUsuario($id_usuario);
} catch (Exception $e) {
    $mensaje_error = "Error al cargar los datos: " . $e->getMessage();
}

// Procesar el pago si es un POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validación de CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje_error = "Error de seguridad: token inválido.";
    }
    // Validar dirección seleccionada
    elseif (empty($_POST['id_direccion']) || !filter_var($_POST['id_direccion'], FILTER_VALIDATE_INT)) {
        $mensaje_error = "Por favor, selecciona una dirección de envío válida.";
    } else {
        $id_direccion_seleccionada = (int) $_POST['id_direccion'];

        // Iniciar transacción
        $conn->begin_transaction();

        try {
            // Validar que la dirección pertenezca al usuario
            $stmt_validar = $conn->prepare("SELECT id_direccion FROM direcciones WHERE id_direccion = ? AND id_usuario = ?");
            $stmt_validar->bind_param("ii", $id_direccion_seleccionada, $id_usuario);
            $stmt_validar->execute();
            $resultado_validar = $stmt_validar->get_result();

            if ($resultado_validar->num_rows === 0) {
                throw new Exception("Dirección de envío inválida.");
            }

            // Recalcular el total en el servidor (seguridad)
            $ids_variantes = array_keys($_SESSION['carrito']);
            $placeholders = implode(',', array_fill(0, count($ids_variantes), '?'));
            $tipos = str_repeat('i', count($ids_variantes));

            $stmt_precios = $conn->prepare("
                SELECT v.id_variante, v.precio, v.stock 
                FROM variantes_producto AS v 
                WHERE v.id_variante IN ($placeholders)
            ");
            $stmt_precios->bind_param($tipos, ...$ids_variantes);
            $stmt_precios->execute();
            $resultado_precios = $stmt_precios->get_result();

            $precios_reales = [];
            $stock_actual = [];
            while ($fila = $resultado_precios->fetch_assoc()) {
                $precios_reales[$fila['id_variante']] = (float) $fila['precio'];
                $stock_actual[$fila['id_variante']] = (int) $fila['stock'];
            }

            $total_seguro = 0;
            foreach ($_SESSION['carrito'] as $id_variante => $item) {
                if (!isset($precios_reales[$id_variante])) {
                    throw new Exception("El producto ID $id_variante ya no está disponible.");
                }
                if ($stock_actual[$id_variante] < $item['cantidad']) {
                    throw new Exception("Stock insuficiente para el producto ID $id_variante.");
                }
                $total_seguro += $precios_reales[$id_variante] * $item['cantidad'];
            }

            // Aplicar descuento al total seguro antes de guardar
            if (isset($_SESSION['cupon'])) {
                $descuento_seguro = calcularDescuentoAplicado($total_seguro, (float) $_SESSION['cupon']['descuento']);
                $total_seguro = calcularTotalFinal($total_seguro, $descuento_seguro);
            }

            // Crear el Pedido
            $stmt_pedido = $conn->prepare("
                INSERT INTO pedidos (id_usuario, id_direccion_envio, id_estado_pedido, total_pedido, fecha_pedido) 
                VALUES (?, ?, 2, ?, NOW())
            ");
            $stmt_pedido->bind_param("iid", $id_usuario, $id_direccion_seleccionada, $total_seguro);
            $stmt_pedido->execute();
            $nuevo_pedido_id = $conn->insert_id;

            // Guardar los Detalles del Pedido y actualizar stock
            $stmt_detalle = $conn->prepare("
                INSERT INTO detalle_pedido (id_pedido, id_variante, cantidad, precio_historico) 
                VALUES (?, ?, ?, ?)
            ");

            $stmt_stock = $conn->prepare("
                UPDATE variantes_producto 
                SET stock = stock - ? 
                WHERE id_variante = ? AND stock >= ?
            ");

            foreach ($_SESSION['carrito'] as $id_variante => $item) {
                $cantidad = $item['cantidad'];
                $precio_historico = $precios_reales[$id_variante];

                // Insertar detalle
                $stmt_detalle->bind_param("iiid", $nuevo_pedido_id, $id_variante, $cantidad, $precio_historico);
                $stmt_detalle->execute();

                // Actualizar stock con verificación de concurrencia
                $stmt_stock->bind_param("iii", $cantidad, $id_variante, $cantidad);
                $stmt_stock->execute();

                if ($stmt_stock->affected_rows === 0) {
                    throw new Exception("Error al actualizar el stock del producto ID $id_variante.");
                }
            }

            // Registrar la Transacción
            $stmt_transaccion = $conn->prepare("
                INSERT INTO transacciones (id_pedido, metodo_pago, monto, estado_pago, id_externo_gateway, fecha_transaccion) 
                VALUES (?, 'Tarjeta (Simulada)', ?, 'exitoso', ?, NOW())
            ");
            $id_gateway_simulado = "txn_" . bin2hex(random_bytes(16));
            $stmt_transaccion->bind_param("ids", $nuevo_pedido_id, $total_seguro, $id_gateway_simulado);
            $stmt_transaccion->execute();

            // Confirmar transacción
            $conn->commit();

            // Limpiar carrito y redirigir
            $_SESSION['carrito'] = [];
            unset($_SESSION['cupon']); // Limpiar cupón tras compra exitosa
            $_SESSION['pedido_exitoso_id'] = $nuevo_pedido_id;
            header("Location: " . $base_url . "?page=gracias&order_id=" . $nuevo_pedido_id);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $mensaje_error = "Error al procesar el pedido: " . $e->getMessage();
            error_log("Error en el proceso de pago: " . $e->getMessage());
        }
    }
}

// Generar nuevo token CSRF
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Finalizar Pago | Tinkuy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        .alert-error-animated {
            animation: shake 0.82s cubic-bezier(.36, .07, .19, .97) both;
        }

        @keyframes shake {

            10%,
            90% {
                transform: translate3d(-1px, 0, 0);
            }

            20%,
            80% {
                transform: translate3d(2px, 0, 0);
            }

            30%,
            50%,
            70% {
                transform: translate3d(-4px, 0, 0);
            }

            40%,
            60% {
                transform: translate3d(4px, 0, 0);
            }
        }
    </style>
</head>

<body>
    <?php include BASE_PATH . '/src/Views/components/navbar.php'; ?>

    <div class="container my-5">
        <h1 class="text-center mb-4">Finalizar Compra</h1>

        <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-danger alert-error-animated"><?= htmlspecialchars($mensaje_error) ?></div>
        <?php endif; ?>

        <form method="POST" id="checkoutForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="secure_token" id="secure_token" value="">

            <div class="row g-5">
                <div class="col-lg-7">
                    <h4 class="mb-3">Dirección de Envío</h4>
                    <?php if (empty($direcciones)): ?>
                        <div class="alert alert-warning">
                            No tienes direcciones guardadas.
                            <a href="?page=mi_perfil&seccion=direcciones" class="alert-link">
                                Agrega una dirección</a> antes de continuar.
                        </div>
                    <?php else: ?>
                        <div class="list-group mb-3">
                            <?php foreach ($direcciones as $dir): ?>
                                <label class="list-group-item list-group-item-action">
                                    <input class="form-check-input" type="radio" name="id_direccion"
                                        value="<?= htmlspecialchars($dir['id_direccion']) ?>" required>
                                    <strong><?= htmlspecialchars($dir['direccion']) ?></strong>
                                    <small class="d-block text-muted">
                                        <?= htmlspecialchars($dir['ciudad']) ?>, <?= htmlspecialchars($dir['pais']) ?>
                                    </small>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <hr class="my-4">

                    <h4 class="mb-3">Método de Pago</h4>
                    <?php if (!empty($tarjetas_guardadas)): ?>
                        <div class="mb-2"><small class="text-muted">Usar tarjeta guardada</small></div>
                        <div class="list-group mb-3">
                            <?php foreach ($tarjetas_guardadas as $t): ?>
                                <?php $value = 'tarjeta_guardada_' . (int) $t['id_tarjeta']; ?>
                                <label class="list-group-item d-flex align-items-center">
                                    <input class="form-check-input me-2" type="radio" name="metodo_pago"
                                        value="<?= htmlspecialchars($value) ?>">
                                    <span><i class="bi bi-credit-card-fill"></i> <?= htmlspecialchars($t['tipo']) ?> terminada
                                        en ****<?= htmlspecialchars($t['ultimos_4_digitos']) ?> (exp.
                                        <?= htmlspecialchars($t['expiracion']) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center text-muted my-2">— o —</div>
                    <?php endif; ?>

                    <div class="my-3">
                        <div class="form-check">
                            <input id="tarjeta" name="metodo_pago" type="radio" class="form-check-input" value="tarjeta"
                                <?= empty($tarjetas_guardadas) ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="tarjeta">
                                Pagar con nueva tarjeta (Simulado)
                            </label>
                        </div>
                    </div>

                    <div class="row gy-3">
                        <div class="col-md-6">
                            <label for="cc-name" class="form-label">Nombre en la tarjeta</label>
                            <input type="text" class="form-control" id="cc-name" placeholder="Juan Perez" required>
                        </div>
                        <div class="col-md-6">
                            <label for="cc-number" class="form-label">Número de tarjeta</label>
                            <input type="text" class="form-control" id="cc-number" placeholder="4444 4444 4444 4444"
                                required>
                            <div class="form-text mt-1"></div>
                        </div>
                        <div class="col-md-3">
                            <label for="cc-expiration" class="form-label">Expiración</label>
                            <input type="text" class="form-control" id="cc-expiration" placeholder="MM/YY" required>
                        </div>
                        <div class="col-md-3">
                            <label for="cc-cvv" class="form-label">CVV</label>
                            <input type="text" class="form-control" id="cc-cvv" placeholder="123" required>
                        </div>
                    </div>

                    <hr class="my-4">

                    <button class="w-100 btn btn-success btn-lg" type="submit" <?php if (empty($direcciones))
                        echo 'disabled'; ?>>
                        <i class="bi bi-lock-fill"></i>
                        Pagar S/ <?= number_format($total_con_descuento, 2) ?>
                    </button>
                </div>

                <div class="col-lg-5">
                    <h4 class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-primary">Tu Carrito</span>
                        <span class="badge bg-primary rounded-pill"><?= count($carrito_items) ?></span>
                    </h4>
                    <ul class="list-group mb-3">
                        <?php foreach ($carrito_items as $item): ?>
                            <li class="list-group-item d-flex justify-content-between lh-sm">
                                <div>
                                    <h6 class="my-0"><?= htmlspecialchars($item['nombre']) ?></h6>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($item['talla']) ?> /
                                        <?= htmlspecialchars($item['color']) ?>
                                        (Cant: <?= $item['cantidad'] ?>)
                                    </small>
                                </div>
                                <span class="text-muted">S/ <?= number_format($item['subtotal'], 2) ?></span>
                            </li>
                        <?php endforeach; ?>

                        <?php if (isset($_SESSION['cupon'])): ?>
                            <li class="list-group-item d-flex justify-content-between bg-light">
                                <div class="text-success">
                                    <h6 class="my-0">Cupón aplicado</h6>
                                    <small><?= htmlspecialchars($_SESSION['cupon']['codigo']) ?></small>
                                </div>
                                <span class="text-success">−S/ <?= number_format($descuento_aplicado, 2) ?></span>
                            </li>
                        <?php endif; ?>

                        <li class="list-group-item d-flex justify-content-between">
                            <span>Total (S/)</span>
                            <strong>S/ <?= number_format($total_con_descuento, 2) ?></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </form>
    </div>

    <?php
    // Ruta Footer Corregida
    include BASE_PATH . '/src/Views/components/footer.php';
    ?>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const inputNombre = document.getElementById('cc-name');
            const inputNumero = document.getElementById('cc-number');
            const inputCVV = document.getElementById('cc-cvv');
            const inputExp = document.getElementById('cc-expiration');
            const botonPagar = document.querySelector('button[type="submit"]');
            const radiosMetodo = document.querySelectorAll('input[name="metodo_pago"]');

            // Crear/insertar icono y feedback
            const labelNumero = document.querySelector('label[for="cc-number"]');
            const icono = document.createElement('i');
            icono.className = 'bi bi-credit-card text-secondary ms-2';
            labelNumero.appendChild(icono);

            const feedback = document.createElement('div');
            feedback.className = 'form-text mt-1';
            inputNumero.parentNode.appendChild(feedback);

            function updatePaymentMethodState() {
                const sel = document.querySelector('input[name="metodo_pago"]:checked');
                const usarNueva = !!sel && sel.value === 'tarjeta';
                [inputNombre, inputNumero, inputExp, inputCVV].forEach(el => {
                    if (!el) return;
                    el.disabled = !usarNueva;
                    el.required = usarNueva;
                    if (!usarNueva) {
                        el.classList.remove('is-invalid', 'is-valid');
                    }
                });
            }

            radiosMetodo.forEach(r => r.addEventListener('change', updatePaymentMethodState));
            updatePaymentMethodState();

            // Validación de número de tarjeta
            inputNumero.addEventListener('input', () => {
                let raw = inputNumero.value.replace(/\D/g, '');
                let formatted = raw.match(/.{1,4}/g);
                inputNumero.value = formatted ? formatted.join(' ') : '';

                const tipo = detectarTipoTarjeta(raw);
                const validoLongitud = validarLongitudPorTipo(raw, tipo);
                const pasaLuhn = raw.length >= 12 ? luhnCheck(raw) : false;
                actualizarIcono(tipo);

                if (raw.length === 0) {
                    feedback.textContent = '';
                    feedback.className = 'form-text mt-1 text-muted';
                    inputNumero.classList.remove('is-invalid', 'is-valid');
                } else if (!tipo) {
                    feedback.textContent = 'Tipo de tarjeta no reconocido todavía.';
                    feedback.className = 'form-text mt-1 text-warning';
                    inputNumero.classList.remove('is-valid');
                    inputNumero.classList.add('is-invalid');
                } else if (!validoLongitud) {
                    const expect = longitudEsperadaTexto(tipo);
                    feedback.textContent = `Formato incorrecto para ${tipo}. ${expect}.`;
                    feedback.className = 'form-text mt-1 text-danger';
                    inputNumero.classList.remove('is-valid');
                    inputNumero.classList.add('is-invalid');
                } else if (!pasaLuhn) {
                    feedback.textContent = `El número parece inválido (falló la comprobación Luhn).`;
                    feedback.className = 'form-text mt-1 text-danger';
                    inputNumero.classList.remove('is-valid');
                    inputNumero.classList.add('is-invalid');
                } else {
                    feedback.textContent = `Tarjeta detectada: ${tipo}. Número válido.`;
                    feedback.className = 'form-text mt-1 text-success';
                    inputNumero.classList.remove('is-invalid');
                    inputNumero.classList.add('is-valid');
                }
            });

            // Validación de CVV
            inputCVV.addEventListener('input', () => {
                inputCVV.value = inputCVV.value.replace(/\D/g, '');
                const raw = document.getElementById('cc-number').value.replace(/\D/g, '');
                const tipo = detectarTipoTarjeta(raw);
                const max = (tipo === 'Amex') ? 4 : 3;
                inputCVV.value = inputCVV.value.slice(0, max);
            });

            // Validación de fecha de expiración
            inputExp.addEventListener('input', () => {
                let v = inputExp.value.replace(/\D/g, '').slice(0, 4);
                if (v.length >= 3) {
                    inputExp.value = v.slice(0, 2) + '/' + v.slice(2);
                } else {
                    inputExp.value = v;
                }
            });

            // Validación del formulario
            document.getElementById('checkoutForm').addEventListener('submit', (e) => {
                const sel = document.querySelector('input[name="metodo_pago"]:checked');
                const usarNueva = !!sel && sel.value === 'tarjeta';

                if (usarNueva) {
                    const raw = inputNumero.value.replace(/\D/g, '');
                    const tipo = detectarTipoTarjeta(raw);
                    const validoLongitud = validarLongitudPorTipo(raw, tipo);
                    const pasaLuhn = luhnCheck(raw);

                    if (!tipo || !validoLongitud || !pasaLuhn) {
                        e.preventDefault();
                        inputNumero.focus();
                        feedback.textContent = 'Revisa el número de tarjeta. No se puede procesar hasta que sea válido.';
                        feedback.className = 'form-text mt-1 text-danger';
                        return false;
                    }
                }

                botonPagar.disabled = true;
                botonPagar.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';
            });

            // Funciones auxiliares
            function detectarTipoTarjeta(num) {
                if (!num) return null;
                const reMaster1 = /^(5[1-5][0-9]{0,})$/;
                const reMaster2 = /^(22[2-9][0-9]{0,}|2[3-6][0-9]{0,}|27[01][0-9]{0,}|2720[0-9]{0,})$/;
                if (/^4[0-9]{0,}$/.test(num)) return 'Visa';
                if (reMaster1.test(num) || reMaster2.test(num)) return 'MasterCard';
                if (/^3[47][0-9]{0,}$/.test(num)) return 'Amex';
                if (/^6(?:011|5[0-9]{2}|4[4-9][0-9]|22[1-9])[0-9]{0,}$/.test(num)) return 'Discover';
                return null;
            }

            function validarLongitudPorTipo(num, tipo) {
                const len = num.length;
                if (!tipo) return false;
                switch (tipo) {
                    case 'Visa': return (len === 13 || len === 16 || len === 19);
                    case 'MasterCard': return (len === 16);
                    case 'Amex': return (len === 15);
                    case 'Discover': return (len === 16);
                    default: return false;
                }
            }

            function longitudEsperadaTexto(tipo) {
                switch (tipo) {
                    case 'Visa': return 'Visa puede tener 13, 16 o 19 dígitos.';
                    case 'MasterCard': return 'MasterCard debe tener 16 dígitos.';
                    case 'Amex': return 'American Express debe tener 15 dígitos.';
                    case 'Discover': return 'Discover debe tener 16 dígitos.';
                    default: return '';
                }
            }

            function actualizarIcono(tipo) {
                icono.className = 'bi ms-2';
                switch (tipo) {
                    case 'Visa':
                        icono.classList.add('bi-credit-card-2-front-fill', 'text-primary');
                        break;
                    case 'MasterCard':
                        icono.classList.add('bi-credit-card-2-back-fill', 'text-warning');
                        break;
                    case 'Amex':
                        icono.classList.add('bi-credit-card', 'text-info');
                        break;
                    case 'Discover':
                        icono.classList.add('bi-credit-card', 'text-success');
                        break;
                    default:
                        icono.classList.add('bi-credit-card', 'text-secondary');
                }
            }

            function luhnCheck(val) {
                if (!val || val.length < 12) return false;
                let sum = 0;
                let shouldDouble = false;
                for (let i = val.length - 1; i >= 0; i--) {
                    let digit = parseInt(val.charAt(i), 10);
                    if (shouldDouble) {
                        digit *= 2;
                        if (digit > 9) digit -= 9;
                    }
                    sum += digit;
                    shouldDouble = !shouldDouble;
                }
                return (sum % 10) === 0;
            }
        });
    </script>
</body>

</html>