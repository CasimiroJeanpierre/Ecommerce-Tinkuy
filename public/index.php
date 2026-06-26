<?php
/**
 * public/index.php — Punto de entrada único (Front Controller) del sistema Ecommerce-Tinkuy.
 *
 * Este archivo:
 *   1. Configura el hardening de sesión (strict mode, httponly, samesite) antes de session_start()
 *   2. Define las constantes BASE_PATH, PROJECT_ROOT, PUBLIC_URL, BASE_URL de forma dinámica
 *   3. Carga el núcleo: db.php, validaciones.php, Security.php, modelos y controladores base
 *   4. Ejecuta Security::validarTimeoutSesion() para cerrar sesiones inactivas (OWASP A07)
 *   5. Enruta todas las peticiones mediante el parámetro GET `page`
 *
 * Rutas disponibles (parámetro `?page=`):
 *   Públicas:          index, products, products.php, producto, about, contact
 *   Autenticación:     login, register, logout, verify_2fa, forgot_password, reset_password
 *   Carrito/Pedidos:   cart, agregar_carrito, eliminar_carrito, vaciar_carrito,
 *                      aplicar_cupon, actualizar_carrito, pago, pedidos, gracias, ver_pedido
 *   Usuario:           mi_perfil
 *   Vendedor:          vendedor_dashboard, vendedor_productos, vendedor_agregar_producto,
 *                      vendedor_editar_producto, vendedor_cambiar_estado,
 *                      vendedor_cambiar_estado_variante, vendedor_ventas, vendedor_envios,
 *                      mi_perfil_vendedor
 *   Admin:             admin_dashboard, admin_pedidos, admin_ver_pedido, admin_productos,
 *                      admin_agregar_producto, admin_editar_producto, admin_usuarios,
 *                      admin_crear_usuario, admin_cupones, admin_eliminar_cupon,
 *                      admin_estado_cupon, admin_reportes, admin_reportes_generar,
 *                      admin_mensajes, admin_ver_mensaje
 *
 * Helpers definidos en este archivo:
 *   procesarItemsCarrito()     — enriquece el carrito de sesión con datos de BD
 *   procesarAgregarCarrito()   — valida y agrega una variante al carrito en sesión
 *   procesarActualizarCarrito()— sincroniza cantidades del carrito con el stock actual
 *   validarContacto()          — valida los campos del formulario de contacto
 */
// public/index.php
// Punto de entrada principal del sistema Ecommerce-Tinkuy

// --- Hardening de sesión: debe ir ANTES de session_start() ---
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_start();
define('BASE_PATH', dirname(__DIR__));

// Rutas base dinámicas (evitan hardcodear el nombre de carpeta)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$public_path = $scriptName ? rtrim(str_replace('\\', '/', dirname($scriptName)), '/') : '/public';
$project_root = $public_path !== '/' ? rtrim(dirname($public_path), '/') : '';
if ($project_root === '.') {
    $project_root = '';
}
if (!defined('PROJECT_ROOT'))
    define('PROJECT_ROOT', $project_root);
if (!defined('PUBLIC_URL'))
    define('PUBLIC_URL', $public_path);
if (!defined('BASE_URL'))
    define('BASE_URL', $public_path . '/index.php');

// 🧠 CORE: conexión, validaciones y seguridad
require_once BASE_PATH . '/src/Core/db.php';
require_once BASE_PATH . '/src/Core/validaciones.php';
require_once BASE_PATH . '/src/Core/Security.php';

// Validar timeout de inactividad (Prevención OWASP A07 - Broken Authentication)
Security::validarTimeoutSesion();

// 🧩 MODELOS
require_once BASE_PATH . '/src/Models/Producto.php';
require_once BASE_PATH . '/src/Models/Categoria.php';
require_once BASE_PATH . '/src/Models/Mensaje.php';

// 🎮 CONTROLADORES
// Controladores de vendedor se requieren solo en sus rutas para evitar ejecución
// inmediata al incluir el archivo (los controladores actuales ejecutan lógica al require).
// Controlador de Mensajes (solo definición de clase, sin efectos secundarios)
require_once BASE_PATH . '/src/Controllers/MensajesController.php';

$base_url = BASE_URL;
$page = $_GET['page'] ?? 'index';

// --- Funciones auxiliares para reducir anidamiento en el router ---

/**
 * Enriquece el carrito de sesión con los datos de BD (nombre, imagen, talla, color, precio, stock).
 * Aplica ajuste silencioso de cantidad cuando supera el stock disponible.
 * Elimina del carrito los ítems cuya variante ya no existe en BD (producto eliminado).
 *
 * @param array<int, array{cantidad: int}>            $carrito           Mapa id_variante → {cantidad} de $_SESSION['carrito']
 * @param array<int, array<string, mixed>>            $detalles_productos Mapa id_variante → datos BD (nombre, precio, stock, etc.)
 * @return array<int, array{id_variante, nombre, imagen_final, talla, color, cantidad, precio, subtotal, stock}>
 *         Array de ítems listos para renderizar en la vista del carrito
 */
function procesarItemsCarrito(array $carrito, array $detalles_productos): array {
    $items = [];
    foreach ($carrito as $id_variante => $item_sesion) {
        if (!isset($detalles_productos[$id_variante])) {
            unset($_SESSION['carrito'][$id_variante]);
            continue;
        }
        $bd = $detalles_productos[$id_variante];
        $stock = (int) $bd['stock'];
        $cantidad = (int) $item_sesion['cantidad'];
        if ($cantidad > $stock) {
            $cantidad = $stock;
            $_SESSION['carrito'][$id_variante]['cantidad'] = $cantidad;
        }
        $subtotal = $bd['precio'] * $cantidad;
        $items[] = [
            'id_variante'  => $id_variante,
            'nombre'       => $bd['nombre_producto'],
            'imagen_final' => trim($bd['imagen_principal'] ?? '') ?: 'default.png',
            'talla'        => $bd['talla'],
            'color'        => $bd['color'],
            'cantidad'     => $cantidad,
            'precio'       => $bd['precio'],
            'subtotal'     => $subtotal,
            'stock'        => $stock,
        ];
    }
    return $items;
}

/**
 * Valida y agrega una variante al carrito almacenado en $_SESSION['carrito'].
 * Solo acepta peticiones POST. Valida que la variante exista en BD y que la cantidad
 * solicitada no supere el stock. Si la variante ya estaba en el carrito, acumula la cantidad.
 * Siempre termina con header()+exit (redirige al carrito o a productos).
 *
 * @param mysqli $conn     Conexión activa a la base de datos
 * @param string $base_url URL base del proyecto para construir las redirecciones
 * @return void            No retorna; siempre termina con header(Location)+exit
 */
function procesarAgregarCarrito($conn, string $base_url): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: $base_url?page=index");
        exit;
    }
    if (!isset($_POST['csrf_token']) || !Security::validarCSRF($_POST['csrf_token'])) {
        $_SESSION['mensaje_error'] = "Token de seguridad inválido. Por favor, recarga la página.";
        header("Location: $base_url?page=products");
        exit;
    }
    $id_variante = filter_var($_POST['id_variante'] ?? '', FILTER_VALIDATE_INT);
    $cantidad    = filter_var($_POST['cantidad']    ?? '', FILTER_VALIDATE_INT);
    if (!$id_variante || !$cantidad || $cantidad <= 0) {
        $_SESSION['mensaje_error'] = "Datos inválidos para agregar al carrito.";
        header("Location: $base_url?page=products");
        exit;
    }
    $stmt = $conn->prepare("SELECT precio, stock, id_producto FROM variantes_producto WHERE id_variante = ?");
    $stmt->bind_param("i", $id_variante);
    $stmt->execute();
    $variante = $stmt->get_result()->fetch_assoc();
    if (!$variante) {
        $_SESSION['mensaje_error'] = "El producto no existe.";
        header("Location: $base_url?page=products");
        exit;
    }
    if ($cantidad > $variante['stock']) {
        $_SESSION['mensaje_error'] = "No hay suficiente stock.";
        header("Location: $base_url?page=producto&id=" . $variante['id_producto']);
        exit;
    }
    if (isset($_SESSION['carrito'][$id_variante])) {
        $nueva = $_SESSION['carrito'][$id_variante]['cantidad'] + $cantidad;
        if ($nueva > $variante['stock']) {
            $max_stock = $variante['stock'];
        $_SESSION['mensaje_error'] = "No puedes agregar más de $max_stock unidades.";
            header("Location: $base_url?page=cart");
            exit;
        }
        $_SESSION['carrito'][$id_variante]['cantidad'] = $nueva;
    } else {
        $_SESSION['carrito'][$id_variante] = ['cantidad' => $cantidad];
    }
    $_SESSION['mensaje_exito'] = "Producto agregado al carrito.";
    header("Location: $base_url?page=cart");
    exit;
}

/**
 * Sincroniza las cantidades del carrito en sesión con los valores enviados en $_POST['cantidades'].
 * Para cada variante: si la cantidad es <= 0 la elimina; si supera el stock disponible en BD,
 * la ajusta al máximo y activa el flag de error de stock. Solo procesa peticiones POST.
 *
 * @param mysqli $conn Conexión activa a la base de datos (para verificar stock de cada variante)
 * @return void        No retorna; establece mensaje en sesión para la vista del carrito
 */
function procesarActualizarCarrito($conn): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!isset($_POST['cantidades']) || !is_array($_POST['cantidades'])) return;
    $hubo_error_stock = false;
    foreach ($_POST['cantidades'] as $id_variante => $cantidad) {
        $id_variante = (int) $id_variante;
        $cantidad    = (int) $cantidad;
        if ($cantidad <= 0) {
            unset($_SESSION['carrito'][$id_variante]);
            continue;
        }
        if (!isset($_SESSION['carrito'][$id_variante])) continue;
        $stmt = $conn->prepare("SELECT stock FROM variantes_producto WHERE id_variante = ?");
        $stmt->bind_param("i", $id_variante);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) continue;
        if ($cantidad > $row['stock']) {
            $cantidad = $row['stock'];
            $hubo_error_stock = true;
        }
        $_SESSION['carrito'][$id_variante]['cantidad'] = $cantidad;
    }
    $_SESSION[$hubo_error_stock ? 'mensaje_error' : 'mensaje_exito'] = $hubo_error_stock
        ? "Stock insuficiente para algunos productos. Las cantidades se ajustaron al máximo disponible."
        : "Carrito actualizado correctamente.";
}

/**
 * Valida todos los campos del formulario de contacto y retorna el primer error encontrado.
 * Reglas: todos los campos obligatorios; email con FILTER_VALIDATE_EMAIL; nombre solo letras
 * y espacios (incluye ñ y acentos); asunto alfanumérico con algunos símbolos; mensaje >= 10 chars.
 *
 * @param array $post Datos de $_POST con claves: nombre, email, asunto, mensaje
 * @return string Mensaje de error (string no vacío) si hay validación fallida; '' si todo es correcto
 */
function validarContacto(array $post): string {
    $nombre  = strip_tags(trim($post['nombre']  ?? ''));
    $email   = strip_tags(trim($post['email']   ?? ''));
    $asunto  = strip_tags(trim($post['asunto']  ?? ''));
    $mensaje = strip_tags(trim($post['mensaje'] ?? ''));
    if (empty($nombre) || empty($email) || empty($asunto) || empty($mensaje))
        return "Por favor, completa todos los campos.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        return "Formato de email no válido.";
    if (!preg_match('/^[a-zA-Z\sñáéíóúÁÉÍÓÚ]+$/u', $nombre))
        return "El nombre solo puede contener letras y espacios.";
    if (!preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_¿?¡!]+$/u', $asunto))
        return "El asunto contiene caracteres no permitidos.";
    if (strlen($mensaje) < 10)
        return "El mensaje es demasiado corto.";
    return '';
}

// 🧭 RUTEO PRINCIPAL
switch ($page) {

    /* =======================
     * 🏠 PÁGINA DE INICIO
     * ======================= */
    case 'index':
        // Lee mensajes flash de sesión (producidos por otras rutas), luego los limpia.
        // Instancia Producto para traer hasta 3 productos activos con stock para la vitrina home.
        $mensaje_error = $_SESSION['mensaje_error'] ?? null;
        $mensaje_exito = $_SESSION['mensaje_exito'] ?? null;
        unset($_SESSION['mensaje_error'], $_SESSION['mensaje_exito']);

        $modeloProducto = new Producto();
        $productos_destacados = $modeloProducto->getProductosDestacados($conn);
        require BASE_PATH . '/src/Views/index.php';
        break;

    /* =======================
     * 🔑 AUTENTICACIÓN
     * ======================= */
    case 'login':
        // Controlador procedural AuthController: CSRF + reCAPTCHA + flujo 2FA por email.
        // En GET renderiza el formulario. En POST valida credenciales y llama a iniciar2FA().
        require BASE_PATH . '/src/Views/auth/login.php';
        break;

    case 'verify_2fa':
        // Verifica el código OTP de 6 dígitos generado en iniciar2FA() (validez: 5 min).
        // Máximo 3 intentos fallidos antes de destruir la sesión pendiente y redirigir al login.
        require BASE_PATH . '/src/Views/auth/verify_2fa.php';
        break;

    case 'register':
        // Controlador procedural RegisterController: CSRF + reCAPTCHA + registro + login inmediato.
        // En POST válido: inserta usuarios + perfiles en transacción y redirige al índice.
        require BASE_PATH . '/src/Views/auth/register.php';
        break;

    case 'logout':
        // Destrucción limpia de sesión: limpiar datos + expirar cookie + destruir
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/', '', false, true);
        }
        session_destroy();
        header("Location: " . $base_url . "?page=index");
        exit;
        break;

    case 'forgot_password':
        // Envía un token de restablecimiento por email si el correo existe en BD.
        // El token se almacena hasheado en password_resets con expiración de 1 hora.
        require BASE_PATH . '/src/Views/auth/forgot_password.php';
        break;

    case 'reset_password':
        // Valida el token GET, verifica que no haya expirado (1h) y actualiza el hash.
        // En éxito: elimina el token de BD, establece mensaje flash y redirige al login.
        require BASE_PATH . '/src/Views/auth/reset_password.php';
        break;

    /* =======================
     * 🛍️ PRODUCTOS
     * ======================= */
    case 'products':
    case 'products.php':
        // Catálogo de productos con filtros de categoría, búsqueda y orden.
        // Los filtros se leen de GET y se validan con FILTER_VALIDATE_INT y FILTER_SANITIZE_SPECIAL_CHARS.
        // Producto::getProductosFiltrados() construye la consulta dinámicamente según los filtros activos.
        // Categoria::getTodasCategorias() puebla el selector de categorías en la barra de filtros.
        $modeloProducto = new Producto();
        $modeloCategoria = new Categoria();

        $ordenes_validos = ['nombre_asc', 'precio_asc', 'precio_desc'];
        $orden_raw = $_GET['orden'] ?? 'nombre_asc';
        $filtros = [
            'categoria' => filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT) ?: null,
            'buscar' => trim(filter_input(INPUT_GET, 'buscar', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''),
            'orden' => in_array($orden_raw, $ordenes_validos, true) ? $orden_raw : 'nombre_asc'
        ];
        if ($filtros['categoria'] === 0)
            $filtros['categoria'] = null;

        $productos_listados = $modeloProducto->getProductosFiltrados($conn, $filtros);
        $categorias = $modeloCategoria->getTodasCategorias($conn);
        $total_productos = count($productos_listados);

        $id_categoria_filtro = $filtros['categoria'];
        $termino_busqueda = $filtros['buscar'];
        $orden = $filtros['orden'];
        $filtros_activos = ($id_categoria_filtro !== null || !empty($termino_busqueda));

        require BASE_PATH . '/src/Views/producto/products.php';
        break;

    case 'producto':
        // Página de detalle de un producto. Valida el ID GET antes de consultar la BD.
        // Producto::getProductoActivoPorId() retorna null si el producto no existe o está inactivo → redirige.
        // Producto::getVariantesActivasPorId() obtiene solo variantes activas con stock > 0.
        $mensaje_error = $_SESSION['mensaje_error'] ?? null;
        $mensaje_exito = $_SESSION['mensaje_exito'] ?? null;
        unset($_SESSION['mensaje_error'], $_SESSION['mensaje_exito']);

        if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
            header("Location: index.php?page=products");
            exit;
        }

        $id_producto = $_GET['id'];
        $modeloProducto = new Producto();
        $producto = $modeloProducto->getProductoActivoPorId($conn, $id_producto);

        if (is_null($producto)) {
            header("Location: index.php?page=products&error=notfound");
            exit;
        }

        $variantes = $modeloProducto->getVariantesActivasPorId($conn, $id_producto);
        $variantes_json = json_encode($variantes);

        $ruta_base_principal = PROJECT_ROOT . "/public/img/productos/";
        $ruta_base_variantes = PROJECT_ROOT . "/public/img/productos/variantes/";
        $imagen_mostrada_inicial = htmlspecialchars(trim($producto['imagen_principal'] ?? 'default.png'));

        require BASE_PATH . '/src/Views/producto/producto.php';
        break;

    /* =======================
     * 🛒 CARRITO Y PEDIDOS
     * ======================= */
    case 'cart':
        // Vista del carrito de compras. Requiere sesión activa; si no hay sesión redirige al login.
        // Si el carrito de sesión no está vacío, enriquece cada ítem con datos de BD
        // (nombre, precio actual, stock) y calcula subtotales y total general.
        // Si hay cupón en sesión, aplica el descuento y calcula el total final con descuento.
        $mensaje_error = $_SESSION['mensaje_error'] ?? null;
        $mensaje_exito = $_SESSION['mensaje_exito'] ?? null;
        unset($_SESSION['mensaje_error'], $_SESSION['mensaje_exito']);

        if (!isset($_SESSION['usuario_id'])) {
            header("Location: $base_url?page=login");
            exit;
        }
        $carrito_items      = [];
        $total_general      = 0;
        $descuento_aplicado = 0;
        $total_con_descuento = 0;

        if (!empty($_SESSION['carrito'])) {
            // Consultar BD por los ítems del carrito y enriquecer con nombre, precio y stock.
            $modeloProducto     = new Producto();
            $detalles_productos = $modeloProducto->getProductosDelCarrito($conn, array_keys($_SESSION['carrito']));
            $carrito_items      = procesarItemsCarrito($_SESSION['carrito'], $detalles_productos);
            $total_general      = array_sum(array_column($carrito_items, 'subtotal'));
            // Aplicar el porcentaje de descuento del cupón activo si existe en la sesión.
            if (isset($_SESSION['cupon'])) {
                $descuento_aplicado = calcularDescuentoAplicado($total_general, (float) $_SESSION['cupon']['descuento']);
            }
            $total_con_descuento = calcularTotalFinal($total_general, $descuento_aplicado);
        }

        require BASE_PATH . '/src/Views/pedido/cart.php';
        break;

    case 'agregar_carrito':
        // Requiere sesión activa. Toda la lógica de validación y persistencia en sesión
        // está en procesarAgregarCarrito(); siempre termina con redirect (nunca renderiza vista).
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: $base_url?page=login");
            exit;
        }
        procesarAgregarCarrito($conn, $base_url);
        break;

    case 'eliminar_carrito':
        // Elimina una variante del carrito de sesión por ID GET. Valida el ID antes de procesar.
        // Si el ítem no está en el carrito (ya eliminado o ID incorrecto) devuelve mensaje de error.
        if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
            $_SESSION['mensaje_error'] = "ID de producto no válido.";
            header("Location: $base_url?page=cart");
            exit;
        }
        $id_variante = (int) $_GET['id'];
        $en_carrito = isset($_SESSION['carrito'][$id_variante]);
        if ($en_carrito) {
            unset($_SESSION['carrito'][$id_variante]);
            $_SESSION['mensaje_exito'] = "Producto eliminado del carrito.";
        }
        if (!$en_carrito) {
            $_SESSION['mensaje_error'] = "El producto no se pudo encontrar.";
        }
        header("Location: $base_url?page=cart");
        exit;
        break;

    case 'vaciar_carrito':
        // Vacía completamente el carrito de sesión y elimina el cupón aplicado si lo hubiera.
        // No requiere confirmación adicional en el router; el botón de la vista ya pide confirmación JS.
        if (isset($_SESSION['carrito'])) {
            $_SESSION['carrito'] = [];
            unset($_SESSION['cupon']); // Limpiamos también si había un cupón
            $_SESSION['mensaje_exito'] = "El carrito ha sido vaciado completamente.";
        }
        header("Location: $base_url?page=cart");
        exit;
        break;

    case 'aplicar_cupon':
        // Valida y aplica un código de cupón al total del carrito almacenándolo en sesión.
        // El descuento se guarda como decimal (ej: 0.10 para 10%) en $_SESSION['cupon']['descuento'].
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $codigo = strtoupper(trim($_POST['cupon'] ?? ''));
            // Consultamos a la base de datos por el cupón (verificando que esté activo y vigente)
            $stmt = $conn->prepare("SELECT porcentaje_descuento FROM cupones WHERE codigo = ? AND estado = 'activo' AND (fecha_expiracion IS NULL OR fecha_expiracion > NOW())");
            $stmt->bind_param("s", $codigo);
            $stmt->execute();
            $res = $stmt->get_result();

            $row = ($res->num_rows === 1) ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row !== null) {
                $descuento_decimal = $row['porcentaje_descuento'] / 100;
                $_SESSION['cupon'] = ['codigo' => $codigo, 'descuento' => $descuento_decimal];
                $_SESSION['mensaje_exito'] = "Cupón '$codigo' aplicado correctamente.";
            }
            if ($row === null) {
                $_SESSION['mensaje_error'] = "El código de cupón '$codigo' no es válido o ha expirado.";
            }
        }
        header("Location: $base_url?page=cart");
        exit;
        break;

    case 'actualizar_carrito':
        // Sincroniza las cantidades del carrito con los valores del formulario POST del carrito.
        // procesarActualizarCarrito() verifica stock en BD y ajusta cantidades si hay discrepancia.
        procesarActualizarCarrito($conn);
        header("Location: $base_url?page=cart");
        exit;
        break;

    /* =======================
     * 🧾 PEDIDOS
     * ======================= */
    case 'pedidos':
        // Guard: solo usuarios autenticados pueden ver su historial de pedidos.
        // OrderController se instancia dentro de la vista para consultar getUserOrders().
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: $base_url?page=login");
            exit;
        }
        require BASE_PATH . '/src/Views/pedido/pedidos.php';
        break;

    case 'pago':
        // Guard: solo usuarios autenticados pueden acceder al checkout.
        // PaymentController (instanciado en la vista) recalcula precios y procesa el pedido.
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: $base_url?page=login");
            exit;
        }
        require BASE_PATH . '/src/Views/pedido/pago.php';
        break;

    case 'gracias':
        // Guard: esta página se muestra tras un pago exitoso; requiere sesión activa.
        // La vista lee $_SESSION['ultimo_pedido'] establecido por PaymentController.
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: $base_url?page=login");
            exit;
        }
        require BASE_PATH . '/src/Views/pedido/gracias.php';
        break;

    /* =======================
     * 👤 USUARIO
     * ======================= */
    case 'ver_pedido':
        // Guard: solo usuarios autenticados pueden ver detalle de pedidos.
        // OrderController::getOrderDetails() incluye id_usuario en el WHERE (anti-IDOR).
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: $base_url?page=login");
            exit;
        }
        require BASE_PATH . '/src/Views/pedido/ver_pedido.php';
        break;

    case 'mi_perfil':
        // Guard: solo usuarios autenticados acceden al panel de perfil.
        // UserController (procedural) despacha acciones POST: perfil, direcciones, pagos, contraseña.
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: $base_url?page=login");
            exit;
        }
        require BASE_PATH . '/src/Views/user/mi_perfil.php';
        break;

    case 'mi_perfil_vendedor':
        // Carga los datos de perfil del vendedor autenticado (nombres, apellido, email, teléfono).
        // VendedorController::actualizarPerfil() actualmente solo hace GET; el POST de actualización
        // está pendiente de implementación (el método detecta sesión y rol vendedor internamente).
        require_once BASE_PATH . '/src/Controllers/VendedorController.php';
        $vendedorController = new VendedorController();
        $datos = $vendedorController->actualizarPerfil();
        extract($datos);
        require BASE_PATH . '/src/Views/vendedor/perfil/mi_perfil_vendedor.php';
        break;

    case 'vendedor_dashboard':
        // VendedorDashboardController (procedural) ejecuta 6 consultas métricas:
        // envíos pendientes, total productos, stock total, ventas totales, gráfico semanal y top-5.
        // La verificación de sesión y rol 'vendedor' se realiza dentro del controlador.
        require_once BASE_PATH . '/src/Controllers/VendedorDashboardController.php';
        require BASE_PATH . '/src/Views/vendedor/dashboard.php';
        break;

    /* =======================
     * 👨‍💼 VENDEDOR - rutas MVC
     * ======================= */
    case 'vendedor_productos':
        // Lista los productos del vendedor autenticado con variantes en JSON.
        // VendedorController::listarProductos() restringe la consulta al id_vendedor de sesión.
        require_once BASE_PATH . '/src/Controllers/VendedorController.php';
        $vendedorController = new VendedorController();
        $datos = $vendedorController->listarProductos();
        extract($datos);
        require BASE_PATH . '/src/Views/vendedor/productos/productos.php';
        break;

    case 'vendedor_agregar_producto':
        // Muestra el formulario de alta de producto y procesa el POST (imagen, variantes, categoría).
        // VendedorController::agregarProducto() usa transacción: si falla algún paso hace rollback.
        require_once BASE_PATH . '/src/Controllers/VendedorController.php';
        $vendedorController = new VendedorController();
        $datos = $vendedorController->agregarProducto();
        extract($datos);
        require BASE_PATH . '/src/Views/vendedor/productos/agregar_producto.php';
        break;

    case 'vendedor_editar_producto':
        // Valida el ID GET antes de instanciar el controlador para evitar consultas con ID inválido.
        // VendedorController::editarProducto() verifica propiedad (anti-IDOR) y procesa POST actions.
        $id_producto = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$id_producto) {
            $_SESSION['mensaje_error'] = "ID de producto inválido";
            header("Location: $base_url?page=vendedor_productos");
            exit;
        }
        require_once BASE_PATH . '/src/Controllers/VendedorController.php';
        $vendedorController = new VendedorController();
        $datos = $vendedorController->editarProducto($id_producto);
        extract($datos);
        require BASE_PATH . '/src/Views/vendedor/productos/editar_producto.php';
        break;

    case 'vendedor_cambiar_estado':
        // Valida ID y estado en lista blanca antes de llamar al controlador (IDOR + input sanitization).
        // VendedorController::cambiarEstado() realiza verificación adicional de propiedad en BD.
        $id_producto = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
        $nuevo_estado = $_GET['estado'] ?? '';
        if (!$id_producto || !in_array($nuevo_estado, ['activo', 'inactivo'])) {
            $_SESSION['mensaje_error'] = "Parámetros inválidos";
            header("Location: $base_url?page=vendedor_productos");
            exit;
        }
        require_once BASE_PATH . '/src/Controllers/VendedorController.php';
        $vendedorController = new VendedorController();
        $res = $vendedorController->cambiarEstado($id_producto, $nuevo_estado);
        if ($res['success'])
            $_SESSION['mensaje_exito'] = $res['mensaje'];
        else
            $_SESSION['mensaje_error'] = $res['mensaje'];
        header("Location: $base_url?page=vendedor_productos");
        break;

    case 'vendedor_cambiar_estado_variante':
        // Triple validación en el router: id_producto, id_variante y estado en lista blanca.
        // VendedorController::cambiarEstadoVariante() hace verificación adicional de propiedad en BD.
        $id_producto = filter_var($_GET['id_producto'] ?? 0, FILTER_VALIDATE_INT);
        $id_variante = filter_var($_GET['id_variante'] ?? 0, FILTER_VALIDATE_INT);
        $nuevo_estado = $_GET['estado'] ?? '';

        if (!$id_producto || !$id_variante || !in_array($nuevo_estado, ['activo', 'inactivo'])) {
            $_SESSION['mensaje_error'] = "Parámetros inválidos para la variante";
            header("Location: $base_url?page=vendedor_editar_producto&id=$id_producto");
            exit;
        }

        require_once BASE_PATH . '/src/Controllers/VendedorController.php';
        $vendedorController = new VendedorController();
        $res = $vendedorController->cambiarEstadoVariante($id_producto, $id_variante, $nuevo_estado);

        if ($res['success']) {
            $_SESSION['mensaje_exito'] = $res['mensaje'];
        } else {
            $_SESSION['mensaje_error'] = $res['mensaje'];
        }

        header("Location: $base_url?page=vendedor_editar_producto&id=$id_producto");
        break;

    case 'vendedor_ventas':
        // Dos controladores trabajan en conjunto: VendedorController aporta datos de sesión/URL
        // y VentasController::listarVentasCompletadas() ejecuta la consulta de ítems con estado 3 o 4.
        require_once BASE_PATH . '/src/Controllers/VendedorController.php';
        $vendedorController = new VendedorController();
        $datos = $vendedorController->listarVentas();
        extract($datos);
        require_once BASE_PATH . '/src/Controllers/VentasController.php';

        $ventasController = new VentasController($conn);
        $items_vendidos = $ventasController->listarVentasCompletadas($_SESSION['usuario_id']);
        $total_ingresos = $ventasController->calcularTotalIngresos($items_vendidos);

        require BASE_PATH . '/src/Views/vendedor/ventas/ventas.php';
        break;

    case 'vendedor_envios':
        // VendedorController aporta datos de sesión; EnviosController gestiona la lista de pendientes
        // y procesa el POST 'registrar_envio' (empresa + número de seguimiento + estado → Enviado).
        require_once BASE_PATH . '/src/Controllers/VendedorController.php';
        $vendedorController = new VendedorController();
        $datos = $vendedorController->listarEnvios();
        extract($datos);
        require_once BASE_PATH . '/src/Controllers/EnviosController.php';

        $enviosController = new EnviosController($conn);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'registrar_envio') {
            $resultado = $enviosController->registrarEnvio(
                (int) $_POST['id_detalle_envio'],
                (int) $_POST['id_empresa_envio'],
                strip_tags(trim($_POST['numero_seguimiento'])),
                $_SESSION['usuario_id']
            );

            $key = $resultado['success'] ? 'mensaje_exito' : 'mensaje_error';
            $_SESSION[$key] = $resultado['message'];
            header('Location: ' . $base_url . '?page=vendedor_envios');
            exit;
        }

        $items_pendientes = $enviosController->listarEnviosPendientes($_SESSION['usuario_id']);
        $empresas_envio = $enviosController->listarEmpresasEnvio();

        require BASE_PATH . '/src/Views/vendedor/envios/envios.php';
        break;

    /* =======================
     * 🛡 ADMIN - rutas MVC (placeholders para vistas en src/Views/admin)
     * ======================= */

    case 'admin_dashboard':
        // AdminController::dashboard() ejecuta 4 consultas independientes: pedidos pendientes,
        // total usuarios, total productos e ingresos totales de pedidos en estados activos (2,3,4).
        require_once BASE_PATH . '/src/Controllers/AdminController.php';
        $adminController = new AdminController($conn);
        $datos = $adminController->dashboard();
        extract($datos);
        require BASE_PATH . '/src/Views/admin/dashboard.php';
        break;
    case 'admin_pedidos':
        // Lista todos los pedidos del sistema con cabecera de cliente, total y estado. Solo admin.
        // AdminController::pedidos() agrega datos del cliente y dirección de envío a cada pedido.
        require_once BASE_PATH . '/src/Controllers/AdminController.php'; // 1. Carga el controlador
        $adminController = new AdminController($conn);                   // 2. Pasa la BBDD
        $datos = $adminController->pedidos();                        // 3. Obtiene los datos
        extract($datos);
        require BASE_PATH . '/src/Views/admin/pedidos/pedidos.php';
        break;
    case 'admin_ver_pedido':
        // Detalle completo de un pedido para el admin: ítems, cliente, dirección y estado.
        // AdminController::verPedido() no restringe por id_usuario — el admin ve todos los pedidos.
        // --- INICIO DE CALIDAD (SEGURIDAD) ---
        // 1. Validamos el ID del pedido aquí, antes de llamar al controlador
        if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
            $_SESSION['mensaje_error'] = "ID de pedido no válido.";
            header('Location: ?page=admin_pedidos'); // Redirigimos a la lista
            exit;
        }
        $id_pedido_actual = (int) $_GET['id'];
        // --- FIN DE CALIDAD (SEGURIDAD) ---

        require_once BASE_PATH . '/src/Controllers/AdminController.php'; // 1. Carga el controlador
        $adminController = new AdminController($conn);                   // 2. Pasa la BBDD
        $datos = $adminController->verPedido($id_pedido_actual);       // 3. Obtiene los datos (pasando el ID)
        extract($datos);                                               // 4. Prepara las variables
        require BASE_PATH . '/src/Views/admin/pedidos/ver_pedido.php';   // 5. Carga la vista LIMPIA
        break;

    case 'admin_productos':
        // Lista todos los productos del sistema (sin filtro de vendedor) con variantes en JSON.
        // Maneja también el toggle de estado via GET (cambiar_estado_id=N) con redirect tras el cambio.
        require_once BASE_PATH . '/src/Controllers/AdminProductosController.php'; // 1. Carga el NUEVO controlador
        $controller = new AdminProductosController($conn);                     // 2. Pasa la BBDD
        $datos = $controller->listarProductos();                           // 3. Obtiene los datos (maneja POST y GET)
        extract($datos);                                                     // 4. Prepara las variables
        require BASE_PATH . '/src/Views/admin/productos/productos_admin.php';  // 5. Carga la vista LIMPIA
        break;
    case 'admin_agregar_producto':
        // Guard manual de sesión y rol antes de renderizar; solo el admin puede agregar productos.
        // A diferencia de otras rutas admin, esta carga la vista directamente sin controlador MVC.
        if (!isset($_SESSION['usuario_id'])) {
            header("Location: $base_url?page=login");
            exit;
        }
        if (strtolower($_SESSION['rol'] ?? '') !== 'admin') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Requiere privilegios de administrador.";
            header("Location: $base_url?page=index");
            exit;
        }
        require BASE_PATH . '/src/Views/admin/productos/agregar_producto.php';
        break;

    case 'admin_editar_producto':
        // Panel de edición admin: despacha acciones POST (actualizar, agregar variante, cambiar estado,
        // actualizar variantes, eliminar imagen) y acción GET (reactivar_variante_id) con redirect.
        // 1. Validamos el ID del producto
        if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
            $_SESSION['mensaje_error'] = "ID de producto no válido.";
            header('Location: ?page=admin_productos');
            exit;
        }
        $id_producto_actual = (int) $_GET['id'];

        // 2. Cargamos el controlador
        require_once BASE_PATH . '/src/Controllers/AdminProductosController.php';
        $controller = new AdminProductosController($conn);

        // 3. Llamamos al método (maneja GET, POST y acciones GET como 'reactivar')
        $datos = $controller->editarProducto($id_producto_actual);

        // 4. Preparamos datos y mostramos la vista
        extract($datos);
        require BASE_PATH . '/src/Views/admin/productos/editar_producto_admin.php';
        break;

    case 'admin_usuarios';
        // Lista todos los usuarios con filtro de rol; maneja acciones GET (cambiar estado, cambiar rol).
        // AdminUsuariosController::listarUsuarios() incluye paginación y redirige tras cada acción GET.
        require_once BASE_PATH . '/src/Controllers/AdminUsuariosController.php'; // 1. Carga el NUEVO controlado
        $controller = new AdminUsuariosController($conn);                     // 2. Pasa la BBDD
        $datos = $controller->listarUsuarios();                            // 3. Obtiene los datos (maneja GET y acciones)
        extract($datos);                                                     // 4. Prepara las variables
        require BASE_PATH . '/src/Views/admin/usuarios/usuarios.php';          // 5. Carga la vista LIMPIA
        break;
    case 'admin_crear_usuario':
        // Formulario de alta de usuario por el admin. En POST valida campos y crea usuario + perfil
        // en una transacción (AdminUsuariosController::crearUsuario()). Redirige tras éxito/error.
        require_once BASE_PATH . '/src/Controllers/AdminUsuariosController.php'; // 1. Carga el NUEVO controlador
        $controller = new AdminUsuariosController($conn);                     // 2. Pasa la BBDD
        $datos = $controller->crearUsuario();                              // 3. Obtiene los datos (maneja POST y GET)e
        extract($datos);                                                     // 4. Prepara las variables
        require BASE_PATH . '/src/Views/admin/usuarios/crear_usuario.php';   // 5. Carga la vista LIMPIA
        break;

    case 'admin_cupones':
        // Lista todos los cupones y procesa la creación vía POST (AdminCuponesController::listar).
        // La verificación de rol 'admin' se realiza en el constructor del controlador.
        require_once BASE_PATH . '/src/Controllers/AdminCuponesController.php';
        $controller = new AdminCuponesController($conn);
        $datos = $controller->listar();
        extract($datos);
        require BASE_PATH . '/src/Views/admin/cupones/cupones.php';
        break;

    case 'admin_eliminar_cupon':
        // Elimina permanentemente un cupón; valida el ID GET antes de instanciar el controlador.
        // AdminCuponesController::eliminar() siempre redirige a admin_cupones tras ejecutar.
        $id_cupon = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if ($id_cupon) {
            require_once BASE_PATH . '/src/Controllers/AdminCuponesController.php';
            $controller = new AdminCuponesController($conn);
            $controller->eliminar($id_cupon);
        } else {
            header("Location: $base_url?page=admin_cupones");
        }
        break;

    case 'admin_estado_cupon':
        // Alterna el estado de un cupón entre 'activo' e 'inactivo' sin eliminarlo.
        // Solo ejecuta si el ID es válido y el estado es uno de los dos valores permitidos.
        $id_cupon = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $nuevo_estado = $_GET['estado'] ?? '';
        if ($id_cupon && in_array($nuevo_estado, ['activo', 'inactivo'])) {
            require_once BASE_PATH . '/src/Controllers/AdminCuponesController.php';
            $controller = new AdminCuponesController($conn);
            $controller->cambiarEstado($id_cupon, $nuevo_estado);
        }
        break;

    /* =======================
     * 📊 REPORTES ADMIN
     * ======================= */
    case 'admin_reportes':
        // Muestra el formulario de selección de tipo y rango de fechas del reporte.
        // ReportesController::index() no genera datos; solo renderiza la vista de filtros.
        require_once BASE_PATH . '/src/Controllers/ReportesController.php';
        $reportesController = new ReportesController($conn);
        $reportesController->index();
        break;

    case 'admin_reportes_generar':
        // Genera el reporte solicitado (ventas/productos/vendedores) en HTML, CSV o PDF.
        // ReportesController::generar() despacha a Reporte->generarReporte{Tipo}() según GET 'tipo'.
        require_once BASE_PATH . '/src/Controllers/ReportesController.php';
        $reportesController = new ReportesController($conn);
        $reportesController->generar();
        break;

    /* =======================
     * 🧩 MISCELÁNEO
     * ======================= */
    case 'about':
        // Página estática "Sobre Nosotros" — no requiere controlador ni sesión.
        require BASE_PATH . '/src/Views/misc/about.php';
        break;

    case 'contact':
    case '/contact.php':
        // Formulario de contacto público (no requiere sesión). En POST valida con validarContacto()
        // y persiste el mensaje con Mensaje::guardarMensaje() si la validación pasa.
        $mensaje_error = "";
        $mensaje_exito = "";
        $nombre = $email = $asunto = $mensaje = "";

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !Security::validarCSRF($_POST['csrf_token'])) {
                $mensaje_error = "Token de seguridad inválido. Por favor, recarga la página e inténtalo de nuevo.";
            } else {
            // Sanitizar campos con strip_tags para prevenir XSS; la validación de formato la hace validarContacto().
            $nombre  = strip_tags(trim($_POST['nombre']  ?? ''));
            $email   = strip_tags(trim($_POST['email']   ?? ''));
            $asunto  = strip_tags(trim($_POST['asunto']  ?? ''));
            $mensaje = strip_tags(trim($_POST['mensaje'] ?? ''));

            $error = validarContacto($_POST);
            if ($error) {
                $mensaje_error = $error;
            }
            // Solo persistir en BD si la validación pasó; usar Mensaje::guardarMensaje() que usa prepared stmt.
            if (!$error) {
                $ok = (new Mensaje())->guardarMensaje($conn, $nombre, $email, $asunto, $mensaje);
                $mensaje_exito = $ok ? "¡Gracias por tu mensaje! Te responderemos pronto." : "";
                $mensaje_error = $ok ? "" : "Error al enviar el mensaje. Intenta de nuevo.";
                if ($ok) $nombre = $email = $asunto = $mensaje = "";
            }
            } // end else csrf válido
        }

        require BASE_PATH . '/src/Views/misc/contact.php';
        break;

    /* =======================
     * 📧 MENSAJES - ADMIN
     * ======================= */
    case 'admin_mensajes':
        // Lista mensajes de contacto con filtro GET 'estado' y despacha acciones de gestión.
        // MensajesController::listar() es la única ruta que no requiere require_once previo
        // porque la clase se carga en el bootstrap (línea ~80 de este archivo).
        $controlador = new MensajesController($conn);
        $datos = $controlador->listar();
        extract($datos);
        require BASE_PATH . '/src/Views/admin/mensajes/mensajes.php';
        break;

    case 'admin_ver_mensaje':
        // Muestra el detalle de un mensaje y lo marca como leído automáticamente al visualizarlo.
        // Valida el ID GET antes de llamar al controlador para evitar consultas innecesarias.
        $id_mensaje = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id_mensaje) {
            $_SESSION['mensaje_error'] = "ID de mensaje no válido.";
            header('Location: ?page=admin_mensajes');
            exit;
        }
        $controlador = new MensajesController($conn);
        $datos = $controlador->ver($id_mensaje);
        extract($datos);
        require BASE_PATH . '/src/Views/admin/mensajes/ver.php';
        break;

    /* =======================
     * 🚫 DEFAULT / ERROR 404
     * ======================= */
    default:
        http_response_code(404);
        echo "<h1>Error 404: Página no encontrada</h1>";
        echo "<p>La página que buscas no existe. <a href='" . htmlspecialchars($base_url) . "?page=index'>Volver al inicio</a></p>";
        break;
}

// 🔚 Cierre de conexión
if (isset($conn) && $conn instanceof mysqli) {
    try {
        @$conn->close();
    } catch (Error $e) {
        // Conexión ya cerrada, ignorar
    }
}
?>