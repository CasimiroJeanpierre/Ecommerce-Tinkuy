<?php
// src/Controllers/VendedorController.php
if (session_status() === PHP_SESSION_NONE)
    session_start();

/**
 * Controlador de operaciones del vendedor autenticado.
 * Cubre el ciclo de vida completo de un producto: alta, edición, cambio de estado,
 * gestión de imágenes adicionales, variantes y perfil del vendedor.
 *
 * Patrón de uso desde el router (public/index.php):
 *   $controller = new VendedorController();   // verifica sesión y rol en __construct
 *   $datos      = $controller->metodo(...);   // devuelve array para la vista
 *   extract($datos);
 *   require BASE_PATH . '/src/Views/vendedor/...';
 *
 * Seguridad:
 *   - __construct() redirige si no hay sesión o el rol no es 'vendedor'
 *   - Todos los métodos que modifican datos verifican propiedad del producto (anti-IDOR)
 *   - Las subidas de imagen filtran extensiones por lista blanca y tamaño máximo 2 MB
 *   - Las operaciones con múltiples escrituras usan transacciones de BD
 */
class VendedorController
{
    private $conn;
    private $base_url;

    /** Extensiones de imagen permitidas */
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Tamaño máximo por imagen adicional en bytes (2 MB) */
    private const MAX_IMAGE_SIZE = 2097152;

    /** Máximo de imágenes adicionales por producto */
    private const MAX_ADDITIONAL_IMAGES = 9;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
        $this->base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';

        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ' . $this->base_url . '?page=login');
            exit;
        }
        if ($_SESSION['rol'] !== 'vendedor') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Esta sección es exclusiva para vendedores.";
            header('Location: ' . $this->base_url . '?page=index');
            exit;
        }
    }

    /**
     * Normaliza el superglobal $_FILES para un input múltiple o único.
     *
     * @param array $files Entrada de $_FILES['campo']
     * @return array<int, array{name:string, error:int, size:int, tmp_name:string}>
     */
    private function normalizarFiles(array $files): array
    {
        if (!is_array($files['name'])) {
            return [[
                'name'     => $files['name'],
                'error'    => $files['error'],
                'size'     => $files['size'],
                'tmp_name' => $files['tmp_name'],
            ]];
        }
        $resultado = [];
        foreach ($files['name'] as $i => $nombre) {
            $resultado[] = [
                'name'     => $nombre,
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
                'tmp_name' => $files['tmp_name'][$i],
            ];
        }
        return $resultado;
    }

    /**
     * Inserta las variantes iniciales de un producto recién creado desde $_POST['variantes'].
     * Omite variantes incompletas o con datos inválidos; lanza Exception si hay duplicados.
     *
     * @param int    $id_producto ID del producto al que pertenecen las variantes
     * @param string $nombre      Nombre del producto (usado para generar el SKU)
     * @throws Exception Si se detectan variantes duplicadas (misma talla+color)
     */
    private function procesarVariantesIniciales(int $id_producto, string $nombre): void
    {
        if (!isset($_POST['variantes']) || !is_array($_POST['variantes'])) {
            return;
        }
        $stmt_variante = $this->conn->prepare(
            "INSERT INTO variantes_producto (id_producto, talla, color, sku, precio, stock) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $variantes_agregadas = [];

        foreach ($_POST['variantes'] as $v) {
            if (empty($v['precio']) || !isset($v['stock'])) {
                continue;
            }
            $talla = isset($v['talla']) ? strip_tags(trim($v['talla'])) : '';
            $color = isset($v['color']) ? strip_tags(trim($v['color'])) : '';

            if (!empty($talla) && !preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $talla)) {
                throw new \Exception("La talla solo puede contener letras, números y algunos signos básicos.");
            }
            if (!empty($color) && !preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $color)) {
                throw new \Exception("El color solo puede contener letras, números y algunos signos básicos.");
            }

            $tallaFinal = ($talla === '') ? 'Única' : $talla;
            $colorFinal = ($color === '') ? 'Estándar' : $color;
            $precio     = filter_var($v['precio'], FILTER_VALIDATE_FLOAT);
            $stock      = filter_var($v['stock'],  FILTER_VALIDATE_INT);

            if ($precio === false || $stock === false || $precio <= 0 || $stock < 0) {
                continue;
            }
            $key_var = $tallaFinal . '-' . $colorFinal;
            if (in_array($key_var, $variantes_agregadas)) {
                throw new \Exception("No puedes crear variantes duplicadas con la misma talla ($tallaFinal) y color ($colorFinal).");
            }
            $variantes_agregadas[] = $key_var;

            $talla_sku = preg_replace('/[^A-Za-z0-9]/', '', $tallaFinal);
            $color_sku = preg_replace('/[^A-Za-z0-9]/', '', $colorFinal);
            $sku = strtoupper(substr($nombre, 0, 3)) . '-' . $id_producto . '-' . $talla_sku . '-' . $color_sku . '-' . rand(1000, 9999);
            $stmt_variante->bind_param("isssdi", $id_producto, $tallaFinal, $colorFinal, $sku, $precio, $stock);
            $stmt_variante->execute();
        }
        $stmt_variante->close();
    }

    /**
     * Actualiza precio/stock de una variante o la desactiva, previa verificación de propiedad.
     * Si los datos incluyen clave 'desactivar', ejecuta el statement de desactivación sin validar precio/stock.
     * Si los datos son de actualización, valida que precio > 0 y stock >= 0 antes de ejecutar.
     *
     * @param int           $id_variante    ID de la variante a modificar
     * @param int           $id_producto    ID del producto al que pertenece la variante
     * @param int           $id_vendedor    ID del vendedor autenticado (verificación anti-IDOR)
     * @param array         $datos          Array con claves 'precio', 'stock' y opcionalmente 'desactivar'
     * @param \mysqli_stmt  $stmt_update    Statement preparado para UPDATE precio/stock
     * @param \mysqli_stmt  $stmt_desactivar Statement preparado para marcar estado='inactivo'
     * @return void
     * @throws Exception Si la variante no pertenece al vendedor o si precio/stock son inválidos
     */
    private function actualizarODesactivarVariante(
        int $id_variante,
        int $id_producto,
        int $id_vendedor,
        array $datos,
        \mysqli_stmt $stmt_update,
        \mysqli_stmt $stmt_desactivar
    ): void {
        $stmt_check = $this->conn->prepare(
            "SELECT 1 FROM variantes_producto vp
             JOIN productos p ON vp.id_producto = p.id_producto
             WHERE vp.id_variante = ? AND p.id_vendedor = ?"
        );
        $stmt_check->bind_param("ii", $id_variante, $id_vendedor);
        $stmt_check->execute();
        $ok = $stmt_check->get_result()->num_rows > 0;
        $stmt_check->close();

        if (!$ok) {
            throw new \Exception("Permiso denegado variante $id_variante.");
        }
        if (isset($datos['desactivar'])) {
            $stmt_desactivar->bind_param("ii", $id_variante, $id_producto);
            $stmt_desactivar->execute();
            return;
        }
        $precio = filter_var($datos['precio'], FILTER_VALIDATE_FLOAT);
        $stock  = filter_var($datos['stock'],  FILTER_VALIDATE_INT);
        if ($precio === false || $stock === false || $precio <= 0 || $stock < 0) {
            throw new \Exception("Datos inválidos variante $id_variante.");
        }
        $stmt_update->bind_param("diii", $precio, $stock, $id_variante, $id_producto);
        $stmt_update->execute();
    }

    /**
     * Elimina físicamente la imagen principal anterior si el nombre cambió.
     *
     * @param int    $id_producto  ID del producto cuya imagen principal se reemplazó
     * @param string $nombre_nuevo Nombre del nuevo archivo (para no borrar si coincide)
     */
    private function eliminarImagenPrincipalAnterior(int $id_producto, string $nombre_nuevo): void
    {
        $stmt = $this->conn->prepare("SELECT imagen_principal FROM productos WHERE id_producto = ?");
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return;
        }
        $old_img = $row['imagen_principal'];
        if (empty($old_img) || $old_img === $nombre_nuevo) {
            return;
        }
        $old_path = BASE_PATH . '/public/img/productos/' . $old_img;
        if (file_exists($old_path) && is_file($old_path)) {
            @unlink($old_path);
        }
    }

    /**
     * Valida, mueve y registra una nueva imagen principal subida en $_FILES['imagen_principal'].
     * Elimina la imagen anterior del disco si el nombre cambió.
     * Devuelve el nombre de archivo limpio, o null si no se subió ninguna imagen.
     *
     * @param int $id_producto ID del producto al que pertenece la imagen
     * @return string|null
     * @throws Exception Si la extensión no está permitida o la copia falla
     */
    private function procesarNuevaImagenPrincipal(int $id_producto): ?string
    {
        if (!isset($_FILES['imagen_principal']) || $_FILES['imagen_principal']['error'] != UPLOAD_ERR_OK) {
            return null;
        }
        $fileExtension = strtolower(pathinfo($_FILES['imagen_principal']['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, self::ALLOWED_IMAGE_EXTENSIONS)) {
            throw new \Exception("El formato de la imagen principal no está permitido (solo JPG, PNG, WEBP).");
        }
        $nombre_limpio = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($_FILES['imagen_principal']['name']));
        $dest_path     = BASE_PATH . '/public/img/productos/' . $nombre_limpio;
        if (!move_uploaded_file($_FILES['imagen_principal']['tmp_name'], $dest_path)) {
            throw new \Exception('Error al mover la imagen subida.');
        }
        $this->eliminarImagenPrincipalAnterior($id_producto, $nombre_limpio);
        return $nombre_limpio;
    }

    /**
     * Procesa y persiste las imágenes adicionales de un producto.
     * Lanza Exception ante errores de formato, tamaño o escritura.
     *
     * @param int $id_producto          ID del producto destino
     * @param int $espacios_disponibles Cuántas imágenes más se pueden agregar
     * @throws Exception Si el formato, tamaño o escritura de archivo falla
     */
    private function procesarImagenesAdicionales(int $id_producto, int $espacios_disponibles): void
    {
        if (!isset($_FILES['imagenes_adicionales']) || $espacios_disponibles <= 0) {
            return;
        }

        $stmt_img = $this->conn->prepare(
            "INSERT INTO producto_imagenes (id_producto, ruta_imagen) VALUES (?, ?)"
        );
        $archivos  = $this->normalizarFiles($_FILES['imagenes_adicionales']);
        $agregadas = 0;

        foreach ($archivos as $idx => $file) {
            if ($agregadas >= $espacios_disponibles) {
                break;
            }
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error al subir imagen (Código: {$file['error']}).");
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_IMAGE_EXTENSIONS)) {
                throw new Exception("Tipo de archivo de imagen adicional no permitido: {$file['name']}.");
            }
            if ($file['size'] > self::MAX_IMAGE_SIZE) {
                throw new Exception("La imagen '{$file['name']}' supera el límite de 2MB.");
            }

            $nombre_limpio = time() . '_' . $idx . '_'
                . preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($file['name']));
            $dest = BASE_PATH . '/public/img/productos/' . $nombre_limpio;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                throw new Exception("Error al guardar la imagen: {$file['name']}.");
            }

            $stmt_img->bind_param("is", $id_producto, $nombre_limpio);
            $stmt_img->execute();
            $agregadas++;
        }

        $stmt_img->close();
    }

    /**
     * Elimina físicamente una imagen adicional del disco y su registro en producto_imagenes.
     * La condición WHERE incluye id_producto para evitar que un vendedor elimine imágenes ajenas.
     *
     * @param int $id_imagen   ID del registro en producto_imagenes a eliminar
     * @param int $id_producto ID del producto propietario (verificación de pertenencia)
     * @return string Mensaje de confirmación "Imagen eliminada de la galería." o '' si execute falla
     */
    private function procesarEliminacionImagen(int $id_imagen, int $id_producto): string
    {
        $stmt_get = $this->conn->prepare("SELECT ruta_imagen FROM producto_imagenes WHERE id_imagen = ? AND id_producto = ?");
        $stmt_get->bind_param("ii", $id_imagen, $id_producto);
        $stmt_get->execute();
        $row_img  = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();
        $img_path = $row_img ? BASE_PATH . '/public/img/productos/' . $row_img['ruta_imagen'] : '';
        if ($img_path !== '' && file_exists($img_path) && is_file($img_path)) {
            @unlink($img_path);
        }

        $stmt_del = $this->conn->prepare("DELETE FROM producto_imagenes WHERE id_imagen = ? AND id_producto = ?");
        $stmt_del->bind_param("ii", $id_imagen, $id_producto);
        $ok = $stmt_del->execute();
        $stmt_del->close();
        return $ok ? "Imagen eliminada de la galería." : '';
    }

    /**
     * Actualiza nombre, descripción, categoría e imagen principal del producto desde $_POST.
     * Si se sube una nueva imagen principal, delega en procesarNuevaImagenPrincipal() que
     * también elimina la imagen anterior del disco. Agrega imágenes adicionales si vienen en $_FILES.
     *
     * @param int $id_producto ID del producto a actualizar
     * @param int $id_vendedor ID del vendedor autenticado (incluido en el WHERE del UPDATE para anti-IDOR)
     * @return string Mensaje de confirmación "Producto actualizado."
     * @throws Exception Si nombre/categoría están vacíos, el nombre tiene caracteres inválidos,
     *                   el UPDATE falla, o la subida de imágenes falla
     */
    private function procesarActualizacionProducto(int $id_producto, int $id_vendedor): string
    {
        $nombre       = strip_tags(trim($_POST['nombre_producto']));
        $descripcion  = strip_tags(trim($_POST['descripcion']));
        $id_categoria = (int) $_POST['id_categoria'];
        if (empty($nombre) || $id_categoria === 0) {
            throw new Exception("Nombre y categoría obligatorios.");
        }
        if (!preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $nombre)) {
            throw new Exception("El nombre del producto solo puede contener letras, números, espacios, puntos, comas y guiones.");
        }

        $imagen_query = "";
        $params = [$nombre, $descripcion, $id_categoria];
        $types  = "ssi";

        $nombre_limpio = $this->procesarNuevaImagenPrincipal($id_producto);
        if ($nombre_limpio !== null) {
            $imagen_query .= ", imagen_principal = ?";
            $params[]      = $nombre_limpio;
            $types        .= "s";
        }

        $params[] = $id_producto;
        $params[] = $id_vendedor;
        $types   .= "ii";

        $stmt = $this->conn->prepare("UPDATE productos SET nombre_producto = ?, descripcion = ?, id_categoria = ? {$imagen_query} WHERE id_producto = ? AND id_vendedor = ?");
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar producto.");
        }
        $stmt->close();

        if (isset($_FILES['imagenes_adicionales'])) {
            $stmt_count = $this->conn->prepare("SELECT COUNT(*) as total FROM producto_imagenes WHERE id_producto = ?");
            $stmt_count->bind_param("i", $id_producto);
            $stmt_count->execute();
            $current_images = $stmt_count->get_result()->fetch_assoc()['total'];
            $stmt_count->close();

            $this->procesarImagenesAdicionales(
                $id_producto,
                self::MAX_ADDITIONAL_IMAGES - $current_images
            );
        }

        return "Producto actualizado.";
    }

    /**
     * Agrega una nueva variante a un producto del vendedor autenticado.
     * Verifica propiedad del producto, valida duplicados y genera un SKU único.
     *
     * @param int $id_producto ID del producto al que se agrega la variante
     * @param int $id_vendedor ID del vendedor autenticado (para verificar propiedad)
     * @return string Mensaje de confirmación tras alta exitosa
     * @throws Exception Si los datos son inválidos, el producto no pertenece al vendedor
     *                   o ya existe una variante con la misma combinación talla+color
     */
    private function procesarAgregadoVariante(int $id_producto, int $id_vendedor): string
    {
        $talla  = strip_tags(trim($_POST['talla']));
        $color  = strip_tags(trim($_POST['color']));
        $precio = filter_var(trim($_POST['precio']), FILTER_VALIDATE_FLOAT);
        $stock  = filter_var(trim($_POST['stock']),  FILTER_VALIDATE_INT);

        if (!empty($talla) && !preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $talla)) {
            throw new Exception("La talla solo puede contener letras, números y signos básicos.");
        }
        if (!empty($color) && !preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $color)) {
            throw new Exception("El color solo puede contener letras, números y signos básicos.");
        }
        if ($precio === false || $stock === false || $precio <= 0 || $stock < 0) {
            throw new Exception("Precio/Stock inválidos.");
        }

        $stmt_check_prop = $this->conn->prepare("SELECT nombre_producto FROM productos WHERE id_producto = ? AND id_vendedor = ?");
        $stmt_check_prop->bind_param("ii", $id_producto, $id_vendedor);
        $stmt_check_prop->execute();
        $res_check = $stmt_check_prop->get_result();
        if ($res_check->num_rows === 0) {
            throw new Exception("Permiso denegado.");
        }
        $nombre_prod_temp = $res_check->fetch_assoc()['nombre_producto'];
        $stmt_check_prop->close();

        $tallaFinal = ($talla === '' ? 'Única' : $talla);
        $colorFinal = ($color === '' ? 'Estándar' : $color);

        $stmt_check_var = $this->conn->prepare("SELECT id_variante FROM variantes_producto WHERE id_producto = ? AND talla = ? AND color = ?");
        $stmt_check_var->bind_param("iss", $id_producto, $tallaFinal, $colorFinal);
        $stmt_check_var->execute();
        if ($stmt_check_var->get_result()->num_rows > 0) {
            $stmt_check_var->close();
            throw new Exception("Ya existe una variante con la talla '$tallaFinal' y color '$colorFinal'.");
        }
        $stmt_check_var->close();

        $talla_sku    = preg_replace('/[^A-Za-z0-9]/', '', $tallaFinal);
        $color_sku    = preg_replace('/[^A-Za-z0-9]/', '', $colorFinal);
        $sku_simulado = strtoupper(substr($nombre_prod_temp, 0, 3)) . '-' . $id_producto . '-' . $talla_sku . '-' . $color_sku . '-' . rand(1000, 9999);

        $stmt = $this->conn->prepare("INSERT INTO variantes_producto (id_producto, talla, color, sku, precio, stock) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdi", $id_producto, $tallaFinal, $colorFinal, $sku_simulado, $precio, $stock);
        if (!$stmt->execute()) {
            throw new Exception("Error al agregar variante: " . $this->conn->error);
        }
        $stmt->close();
        return "Nueva variante agregada.";
    }

    /**
     * Itera sobre $_POST['variantes'] y actualiza o desactiva cada variante del vendedor.
     * Verifica que el producto pertenezca al vendedor antes de procesar.
     *
     * @param int $id_producto ID del producto cuyas variantes se actualizan
     * @param int $id_vendedor ID del vendedor autenticado (para verificar propiedad)
     * @return void
     * @throws Exception Si alguna variante tiene precio/stock inválidos
     */
    private function procesarActualizacionVariantesVendedor(int $id_producto, int $id_vendedor): void
    {
        $stmt_update     = $this->conn->prepare("UPDATE variantes_producto SET precio = ?, stock = ? WHERE id_variante = ? AND id_producto = ?");
        $stmt_desactivar = $this->conn->prepare("UPDATE variantes_producto SET estado = 'inactivo' WHERE id_variante = ? AND id_producto = ?");
        $variantes_post  = is_array($_POST['variantes'] ?? null) ? $_POST['variantes'] : [];
        foreach ($variantes_post as $id_variante => $datos) {
            $this->actualizarODesactivarVariante((int) $id_variante, $id_producto, $id_vendedor, $datos, $stmt_update, $stmt_desactivar);
        }
        $stmt_update->close();
        $stmt_desactivar->close();
    }

    /**
     * Lista todos los productos del vendedor autenticado con sus variantes serializadas en JSON.
     * La consulta usa GROUP_CONCAT + JSON_OBJECT para condensar todas las variantes de cada producto
     * en un único campo 'variantes_json'; el router o la vista deben hacer json_decode() sobre él.
     * Los campos JSON por variante incluyen: id_variante, talla, color, precio, stock, estado_variante.
     * El resultado se ordena: primero los activos (ASC por estado) y luego alfabéticamente por nombre.
     *
     * Restricción de propiedad:
     *   La cláusula WHERE p.id_vendedor = ? limita los resultados al vendedor en sesión,
     *   garantizando que un vendedor nunca vea productos ajenos en este listado.
     *
     * @return array{productos: array, nombre_vendedor: string, id_vendedor: int, base_url: string}
     */
    public function listarProductos()
    {
        $id_vendedor = $_SESSION['usuario_id'];
        $nombre_vendedor = $_SESSION['usuario'];

        $query = "
            SELECT
                p.id_producto,
                p.nombre_producto,
                p.imagen_principal,
                p.estado,
                c.nombre_categoria,
                CONCAT(
                    '[',
                    IFNULL(GROUP_CONCAT(
                        JSON_OBJECT(
                            'id_variante', vp.id_variante,
                            'talla', vp.talla,
                            'color', vp.color,
                            'precio', vp.precio,
                            'stock', vp.stock,
                            'estado_variante', vp.estado
                        ) ORDER BY vp.id_variante
                    ), '') ,
                    ']' 
                ) AS variantes_json
            FROM productos AS p
            JOIN categorias AS c ON p.id_categoria = c.id_categoria
            LEFT JOIN variantes_producto AS vp ON p.id_producto = vp.id_producto
            WHERE p.id_vendedor = ?
            GROUP BY p.id_producto
            ORDER BY p.estado ASC, p.nombre_producto ASC
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id_vendedor);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $productos = $resultado->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            'productos' => $productos,
            'nombre_vendedor' => $nombre_vendedor,
            'id_vendedor' => $id_vendedor,
            'base_url' => $this->base_url
        ];
    }

    /**
     * Activa o desactiva un producto del vendedor autenticado.
     * Antes de actualizar verifica propiedad: si el par (id_producto, id_vendedor) no existe
     * en la BD, retorna éxito=false sin ejecutar el UPDATE (anti-IDOR).
     * El nuevo_estado se normaliza: cualquier valor distinto de 'activo' se trata como 'inactivo'.
     *
     * @param int    $id_producto  ID del producto a modificar
     * @param string $nuevo_estado Estado deseado: 'activo' o 'inactivo'
     * @return array{success: bool, mensaje: string} Éxito y mensaje descriptivo para la sesión flash
     */
    public function cambiarEstado($id_producto, $nuevo_estado)
    {
        $id_vendedor = $_SESSION['usuario_id'];
        // Validar entrada
        $nuevo_estado = ($nuevo_estado === 'activo') ? 'activo' : 'inactivo';

        // Verificar propiedad
        $stmt_check = $this->conn->prepare("SELECT id_producto FROM productos WHERE id_producto = ? AND id_vendedor = ?");
        $stmt_check->bind_param('ii', $id_producto, $id_vendedor);
        $stmt_check->execute();
        $res = $stmt_check->get_result();
        if ($res->num_rows === 0) {
            $stmt_check->close();
            return ['success' => false, 'mensaje' => 'No tienes permiso para modificar este producto.'];
        }
        $stmt_check->close();

        $stmt_update = $this->conn->prepare("UPDATE productos SET estado = ? WHERE id_producto = ?");
        $stmt_update->bind_param('si', $nuevo_estado, $id_producto);
        $ok = $stmt_update->execute();
        $stmt_update->close();

        if ($ok) {
            return ['success' => true, 'mensaje' => "Estado del producto #$id_producto actualizado a '$nuevo_estado'."];
        }
        return ['success' => false, 'mensaje' => 'Error al actualizar el estado del producto.'];
    }

    /**
     * Muestra y procesa el formulario de alta de un nuevo producto del vendedor.
     * En POST: abre una transacción para garantizar consistencia entre el INSERT del producto,
     * la subida de imagen principal (opcional), las imágenes adicionales y las variantes iniciales.
     * Si cualquier paso falla se hace rollback completo.
     *
     * @return array{nombre_vendedor: string, categorias_jerarquia: array,
     *               mensaje_error: string|null, mensaje_exito: string|null, base_url: string}
     */
    public function agregarProducto()
    {
        $id_vendedor = $_SESSION['usuario_id'];
        $mensaje_error = null;
        $mensaje_exito = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->conn->begin_transaction();

                // Validar datos básicos del producto
                $nombre = strip_tags(trim($_POST['nombre_producto'] ?? ''));
                $descripcion = strip_tags(trim($_POST['descripcion'] ?? ''));
                $id_categoria = filter_var($_POST['id_categoria'] ?? 0, FILTER_VALIDATE_INT);

                if (empty($nombre) || !$id_categoria) {
                    throw new Exception("Nombre y categoría son obligatorios");
                }
                if (!preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $nombre)) {
                    throw new Exception("El nombre del producto solo puede contener letras, números, espacios, puntos, comas y guiones.");
                }

                // Validar imagen principal
                if (!isset($_FILES['imagen_principal']) || $_FILES['imagen_principal']['error'] != UPLOAD_ERR_OK) {
                    throw new Exception("La imagen principal es obligatoria");
                }

                $fileName = $_FILES['imagen_principal']['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($fileExtension, $allowedfileExtensions)) {
                    throw new Exception("Tipo de archivo de imagen principal no permitido.");
                }

                $nombre_limpio_principal = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($fileName));
                $dest_path_principal = BASE_PATH . '/public/img/productos/' . $nombre_limpio_principal;

                if (!move_uploaded_file($_FILES['imagen_principal']['tmp_name'], $dest_path_principal)) {
                    throw new Exception("Error al guardar la imagen principal.");
                }

                $stmt = $this->conn->prepare("INSERT INTO productos (nombre_producto, descripcion, imagen_principal, id_categoria, id_vendedor, estado) VALUES (?, ?, ?, ?, ?, 'activo')");
                $stmt->bind_param("sssii", $nombre, $descripcion, $nombre_limpio_principal, $id_categoria, $id_vendedor);

                if (!$stmt->execute()) {
                    throw new Exception("Error al crear el producto: " . $this->conn->error);
                }

                $id_producto = $this->conn->insert_id;
                $stmt->close();

                // Insertar imágenes adicionales respetando el límite máximo
                $this->procesarImagenesAdicionales($id_producto, self::MAX_ADDITIONAL_IMAGES);

                // Procesar variantes iniciales
                $this->procesarVariantesIniciales($id_producto, $nombre);

                $this->conn->commit();
                $_SESSION['mensaje_exito'] = "Producto creado exitosamente.";
                header("Location: " . $this->base_url . "?page=vendedor_productos");
                exit;

            } catch (Exception $e) {
                $this->conn->rollback();
                $mensaje_exito = ""; // Limpia cualquier falso éxito si ocurre un error
                $mensaje_error = $e->getMessage();
            }
        }

        // Obtener categorías para el formulario
        $query_categorias = "SELECT c.id_categoria, c.nombre_categoria, c.id_categoria_padre, cp.nombre_categoria AS nombre_padre 
                           FROM categorias c 
                           LEFT JOIN categorias cp ON c.id_categoria_padre = cp.id_categoria 
                           ORDER BY COALESCE(cp.nombre_categoria, c.nombre_categoria), c.id_categoria_padre, c.nombre_categoria";

        $resultado_categorias = $this->conn->query($query_categorias);
        $categorias = [];
        while ($row = $resultado_categorias->fetch_assoc()) {
            $categorias[] = $row;
        }

        return [
            'categorias' => $categorias,
            'mensaje_error' => $mensaje_error,
            'mensaje_exito' => $mensaje_exito,
            'base_url' => $this->base_url
        ];
    }

    /**
     * Activa o desactiva una variante específica de un producto del vendedor.
     * Realiza una verificación triple antes de actualizar: id_variante debe pertenecer
     * al id_producto, y ese id_producto debe pertenecer al id_vendedor de sesión.
     * Si la verificación falla retorna éxito=false sin ejecutar el UPDATE (anti-IDOR).
     * Envuelve toda la lógica en try/catch para capturar errores de BD.
     *
     * @param int    $id_producto  ID del producto propietario de la variante
     * @param int    $id_variante  ID de la variante a modificar
     * @param string $nuevo_estado Nuevo estado: 'activo' o 'inactivo'
     * @return array{success: bool, mensaje: string} Éxito y mensaje descriptivo de la operación
     */
    public function cambiarEstadoVariante($id_producto, $id_variante, $nuevo_estado)
    {
        $id_vendedor = $_SESSION['usuario_id'];

        try {
            // Verificar propiedad del producto y la variante
            $stmt = $this->conn->prepare("
                SELECT v.id_variante 
                FROM variantes_producto v 
                JOIN productos p ON v.id_producto = p.id_producto 
                WHERE v.id_variante = ? AND v.id_producto = ? AND p.id_vendedor = ?
            ");
            $stmt->bind_param("iii", $id_variante, $id_producto, $id_vendedor);
            $stmt->execute();

            if ($stmt->get_result()->num_rows === 0) {
                return [
                    'success' => false,
                    'mensaje' => 'No tienes permiso para modificar esta variante.'
                ];
            }
            $stmt->close();

            // Actualizar estado
            $stmt = $this->conn->prepare("
                UPDATE variantes_producto 
                SET estado = ? 
                WHERE id_variante = ? AND id_producto = ?
            ");
            $stmt->bind_param("sii", $nuevo_estado, $id_variante, $id_producto);

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'mensaje' => $nuevo_estado === 'activo' ?
                        "Variante reactivada correctamente." :
                        "Variante desactivada correctamente."
                ];
            } else {
                throw new Exception("Error al actualizar el estado de la variante.");
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'mensaje' => "Error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Muestra y procesa la edición de un producto existente del vendedor.
     * Verifica propiedad del producto antes de cualquier operación (anti-IDOR); redirige
     * a la lista si el producto no existe o no pertenece al vendedor autenticado.
     * En POST despacha la acción indicada en $_POST['accion']:
     *   actualizar_producto  — Actualiza nombre, descripción, categoría e imágenes
     *   actualizar_variantes — Actualiza precios/stocks o desactiva variantes individualmente
     *   agregar_variante     — Agrega una nueva variante con validación de duplicados
     *   eliminar_imagen      — Elimina una imagen adicional del disco y la BD
     *
     * @param int $id_producto ID del producto a editar (validado en el router antes de llamar)
     * @return array{nombre_vendedor: string, producto: array, variantes: array, imagenes: array,
     *               categorias_jerarquia: array, mensaje_error: string, mensaje_exito: string, base_url: string}
     */
    public function editarProducto($id_producto)
    {
        $id_vendedor = $_SESSION['usuario_id'];
        $mensaje_error = "";
        $mensaje_exito = "";

        // Limpiar cualquier mensaje fantasma persistente en la sesión
        unset($_SESSION['mensaje_exito']);
        unset($_SESSION['mensaje_error']);

        // --- 🛡️ CONTROL DE ACCESO (A01: Broken Access Control / IDOR) ---
        // Validar que el producto le pertenece al vendedor ANTES de procesar cualquier petición
        $stmt_auth = $this->conn->prepare("SELECT id_producto FROM productos WHERE id_producto = ? AND id_vendedor = ?");
        $stmt_auth->bind_param("ii", $id_producto, $id_vendedor);
        $stmt_auth->execute();
        if ($stmt_auth->get_result()->num_rows === 0) {
            $_SESSION['mensaje_error'] = "Acceso denegado: Producto no encontrado o no te pertenece.";
            header('Location: ' . $this->base_url . '?page=vendedor_productos');
            exit;
        }
        $stmt_auth->close();

        // --- Lógica POST ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->conn->begin_transaction();

                // --- ACCIÓN: ACTUALIZAR PRODUCTO GENERAL ---
                if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_producto') {
                    $mensaje_exito = $this->procesarActualizacionProducto($id_producto, $id_vendedor);
                }

                // --- ACCIÓN: ELIMINAR IMAGEN DE LA GALERÍA ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_imagen') {
                    $id_imagen     = (int) $_POST['id_imagen'];
                    $mensaje_exito = $this->procesarEliminacionImagen($id_imagen, $id_producto);
                }

                // --- ACCIÓN: AGREGAR NUEVA VARIANTE (CON IMAGEN) ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'agregar_variante') {
                    $mensaje_exito = $this->procesarAgregadoVariante($id_producto, $id_vendedor);
                }
                // --- ACCIÓN: ACTUALIZAR / DESACTIVAR VARIANTES EXISTENTES ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_variantes') {
                    $this->procesarActualizacionVariantesVendedor($id_producto, $id_vendedor);
                    $mensaje_exito = "Lista de variantes actualizada.";
                }

                // --- REACTIVAR VARIANTE ---
                if (isset($_GET['reactivar_variante_id'])) {
                    $id_variante_reactivar = (int) $_GET['reactivar_variante_id'];
                    $resultado = $this->cambiarEstadoVariante($id_producto, $id_variante_reactivar, 'activo');
                    if (!$resultado['success']) throw new Exception($resultado['mensaje']);
                    $mensaje_exito = $resultado['mensaje'];
                }

                $this->conn->commit();

            } catch (Exception $e) {
                $this->conn->rollback();
                $mensaje_error = "Error: " . $e->getMessage();
            }
        }

        // --- Lógica GET (Visualización) ---
        // Verificamos Propiedad y Obtenemos datos del Producto
        $sql_producto = "SELECT p.*, c.nombre_categoria FROM productos p JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.id_producto = ? AND p.id_vendedor = ?";
        $stmt = $this->conn->prepare($sql_producto);
        $stmt->bind_param("ii", $id_producto, $id_vendedor);
        $stmt->execute();
        $resultado_producto = $stmt->get_result();
        if ($resultado_producto->num_rows === 0) {
            $_SESSION['mensaje_error'] = "Producto no encontrado o permiso denegado.";
            header('Location: ' . $this->base_url . '?page=vendedor_productos');
            exit;
        }
        $producto = $resultado_producto->fetch_assoc();
        $stmt->close();

        // Obtenemos categorías con jerarquía
        $query_categorias_jerarquia = "SELECT c.id_categoria, c.nombre_categoria, c.id_categoria_padre, cp.nombre_categoria AS nombre_padre FROM categorias c LEFT JOIN categorias cp ON c.id_categoria_padre = cp.id_categoria ORDER BY COALESCE(cp.nombre_categoria, c.nombre_categoria), c.id_categoria_padre, c.nombre_categoria";
        $resultado_categorias = $this->conn->query($query_categorias_jerarquia);
        $categorias_jerarquia = [];
        while ($row = $resultado_categorias->fetch_assoc()) {
            $categorias_jerarquia[] = $row;
        }

        // Obtenemos TODAS las variantes (incluyendo estado e imagen)
        $stmt_variantes = $this->conn->prepare("SELECT *, estado, imagen_variante FROM variantes_producto WHERE id_producto = ? ORDER BY talla, color");
        $stmt_variantes->bind_param("i", $id_producto);
        $stmt_variantes->execute();
        $resultado_variantes = $stmt_variantes->get_result();
        $variantes = $resultado_variantes->fetch_all(MYSQLI_ASSOC);
        $stmt_variantes->close();

        // Obtener la galería de imágenes actuales
        $stmt_img = $this->conn->prepare("SELECT id_imagen, ruta_imagen FROM producto_imagenes WHERE id_producto = ?");
        $stmt_img->bind_param("i", $id_producto);
        $stmt_img->execute();
        $imagenes_adicionales = $stmt_img->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_img->close();

        return [
            'producto' => $producto,
            'categorias_jerarquia' => $categorias_jerarquia,
            'variantes' => $variantes,
            'imagenes_adicionales' => $imagenes_adicionales,
            'mensaje_error' => $mensaje_error,
            'mensaje_exito' => $mensaje_exito,
            'base_url' => $this->base_url
        ];
    }

    /**
     * Recupera los datos de perfil del vendedor autenticado para la vista mi_perfil_vendedor.
     * Hace JOIN entre usuarios (email) y perfiles (nombres, apellidos, teléfono).
     * Filtra por id_rol = 2 (vendedor) para evitar que un admin con id en sesión acceda.
     * Si el fetch devuelve vacío (cuenta eliminada con sesión activa), destruye la sesión
     * y redirige al login antes de retornar.
     *
     * Nota: El nombre del método es 'actualizarPerfil' aunque actualmente solo hace GET.
     * El POST de actualización de datos del perfil está pendiente de implementación.
     *
     * @return array{datos_perfil: array{nombre: string, apellido: string, email: string, telefono: string},
     *               mensaje_error: string, mensaje_exito: string, base_url: string}
     */
    public function actualizarPerfil()
    {
        $id_vendedor = $_SESSION['usuario_id'];
        $mensaje_error = '';
        $mensaje_exito = '';

        // Obtener datos de perfil y usuario
        $stmt = $this->conn->prepare(
            "SELECT u.email, p.nombres, p.apellidos, p.telefono
             FROM usuarios u
             LEFT JOIN perfiles p ON u.id_usuario = p.id_usuario
             WHERE u.id_usuario = ? AND u.id_rol = 2"
        );
        $stmt->bind_param('i', $id_vendedor);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($fila = $res->fetch_assoc()) {
            $datos_perfil = [
                'nombre' => $fila['nombres'] ?? '',
                'apellido' => $fila['apellidos'] ?? '',
                'email' => $fila['email'] ?? '',
                'telefono' => $fila['telefono'] ?? ''
            ];
        } else {
            session_destroy();
            header('Location: ' . $this->base_url . '?page=login');
            exit;
        }
        $stmt->close();

        return [
            'datos_perfil' => $datos_perfil,
            'mensaje_error' => $mensaje_error,
            'mensaje_exito' => $mensaje_exito,
            'base_url' => $this->base_url
        ];
    }

    /**
     * Método de compatibilidad para la ruta vendedor_ventas.
     * Este método solo provee las variables de sesión y URL base; la consulta real
     * de ítems vendidos la realiza VentasController::listarVentasCompletadas() llamado
     * directamente desde el router. Se mantiene para el patrón uniform de la arquitectura.
     *
     * @return array{ventas: array, base_url: string, nombre_vendedor: string}
     */
    public function listarVentas()
    {
        return [
            'ventas'          => [],
            'base_url'        => $this->base_url,
            'nombre_vendedor' => $_SESSION['usuario'] ?? '',
        ];
    }

    /**
     * Método de compatibilidad para la ruta vendedor_envios.
     * Este método solo provee las variables de sesión y URL base; las consultas reales
     * de envíos pendientes y empresas las realiza EnviosController llamado desde el router.
     * Se mantiene para el patrón uniforme de la arquitectura del panel de vendedor.
     *
     * @return array{envios: array, base_url: string, nombre_vendedor: string}
     */
    public function listarEnvios()
    {
        return [
            'envios'          => [],
            'base_url'        => $this->base_url,
            'nombre_vendedor' => $_SESSION['usuario'] ?? '',
        ];
    }
}

?>