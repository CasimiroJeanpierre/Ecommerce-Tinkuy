<?php
// src/Controllers/AdminProductosController.php

/**
 * Controlador de administración de productos (panel de admin).
 * Permite al administrador listar, crear, editar y eliminar cualquier producto
 * del sistema sin restricción de vendedor propietario.
 * Gestiona subida de imagen principal e imágenes adicionales con validación
 * de tipo (jpg/jpeg/png/gif/webp) y tamaño (máx 2 MB cada una, máx 9 adicionales).
 *
 * Métodos disponibles:
 *   listar()               — Lista todos los productos con filtros de categoría y estado
 *   agregar()              — Procesa el formulario POST de alta de producto por el admin
 *   editar($id)            — Carga datos del producto y procesa actualización POST
 *   cambiarEstado($id)     — Activa/desactiva una variante específica
 *   eliminarImagen($id)    — Borra una imagen adicional del disco y de la BD
 */
class AdminProductosController
{

    private $conn;

    /** Extensiones de imagen permitidas para subida */
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Tamaño máximo de imagen adicional en bytes (2 MB) */
    private const MAX_IMAGE_SIZE = 2097152;

    /** Máximo de imágenes adicionales por producto */
    private const MAX_ADDITIONAL_IMAGES = 9;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    /**
     * Normaliza la estructura de $_FILES para un campo de subida simple o múltiple.
     * PHP representa de forma diferente un <input type="file"> simple y uno con multiple:
     * el simple da $_FILES['campo'] = ['name'=>'x','error'=>0,...], mientras que el múltiple
     * da $_FILES['campo'] = ['name'=>['a','b'],'error'=>[0,0],...]. Este método normaliza
     * ambas formas a un array plano de entradas uniformes para procesarlas con el mismo bucle.
     *
     * @param array $files Entrada de $_FILES['campo'] (simple o múltiple)
     * @return array<int, array{name:string, error:int, size:int, tmp_name:string}>
     *         Array plano de archivos; cada entrada tiene las 4 claves estándar de $_FILES
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
     * Actualiza precio/stock de una variante o la desactiva, según los datos del subarray POST.
     * Si el subarray tiene la clave 'desactivar' (checkbox marcado), ejecuta stmt_desactivar
     * (SET estado='inactivo') sin modificar precio ni stock.
     * Si no tiene 'desactivar', valida precio (> 0, tipo float) y stock (>= 0, tipo int)
     * con FILTER_VALIDATE_FLOAT/INT antes de ejecutar stmt_update.
     * Ambos prepared statements se crean en procesarActualizacionVariantesAdmin() y se reutilizan
     * en el bucle para evitar re-parsear la query SQL en cada iteración de variante.
     *
     * @param int          $id_variante     ID de la variante a procesar (clave del array POST)
     * @param int          $id_producto     ID del producto propietario (guard de integridad)
     * @param array        $datos           Subarray $_POST['variantes'][$id_variante]
     * @param \mysqli_stmt $stmt_update     Statement preparado para UPDATE precio/stock
     * @param \mysqli_stmt $stmt_desactivar Statement preparado para SET estado='inactivo'
     * @throws Exception Si precio o stock no superan la validación de filtro
     */
    private function actualizarODesactivarVariante(
        int $id_variante,
        int $id_producto,
        array $datos,
        \mysqli_stmt $stmt_update,
        \mysqli_stmt $stmt_desactivar
    ): void {
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
     * Elimina físicamente la imagen principal anterior del disco si fue reemplazada por una nueva.
     * Consulta el nombre de archivo actual en BD antes de borrarlo; si es el mismo que el nuevo
     * (p.ej. misma imagen re-subida con el mismo nombre), no elimina nada para evitar pérdida.
     * Usa @unlink para suprimir advertencias si el archivo ya fue eliminado manualmente.
     * Solo borra si file_exists() && is_file() para evitar eliminar directorios accidentalmente.
     *
     * @param int    $id_producto  ID del producto cuya imagen_principal se reemplazó
     * @param string $nombre_nuevo Nombre del nuevo archivo subido (comparado con el existente en BD)
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
     * Procesa y persiste las imágenes adicionales de un producto desde $_FILES['imagenes_adicionales'].
     * Itera sobre los archivos normalizados con normalizarFiles() y para cada uno:
     *   1. Valida la extensión contra ALLOWED_IMAGE_EXTENSIONS
     *   2. Valida que el tamaño no supere MAX_IMAGE_SIZE (2 MB)
     *   3. Mueve el archivo al directorio /public/img/productos/ con prefijo timestamp
     *   4. Registra la ruta en la tabla producto_imagenes
     * Respeta espacios_disponibles para no superar MAX_ADDITIONAL_IMAGES por producto.
     * Si no se subieron archivos (UPLOAD_ERR_NO_FILE) los omite silenciosamente.
     *
     * @param int $id_producto          ID del producto destino (FK → productos.id_producto)
     * @param int $espacios_disponibles Cuántas imágenes más caben (MAX_ADDITIONAL_IMAGES − actuales)
     * @throws Exception Si el formato, tamaño o escritura de algún archivo falla
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
                throw new Exception("Formato no permitido: {$file['name']}.");
            }
            if ($file['size'] > self::MAX_IMAGE_SIZE) {
                throw new Exception("'{$file['name']}' supera el límite de 2MB.");
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
     * Alterna el estado de un producto entre 'activo' e 'inactivo' según $_POST['estado'].
     * El toggle es unidireccional desde el POST: si 'activo' se envía → queda 'activo',
     * si cualquier otro valor → queda 'inactivo'. Esto evita valores inesperados en la BD.
     * El retorno es el mensaje de confirmación que se almacena en $_SESSION['mensaje_exito']
     * en editar() para mostrarlo después del redirect.
     *
     * @param int $id_producto ID del producto a cambiar de estado (validado en el router)
     * @return string Mensaje de confirmación con el nuevo estado aplicado
     * @throws \Exception Si el UPDATE falla (affected_rows = 0 no lanza — solo execute() false)
     */
    private function procesarCambioEstadoProductoAdmin(int $id_producto): string
    {
        $nuevo_estado = $_POST['estado'] === 'activo' ? 'activo' : 'inactivo';
        $stmt_estado = $this->conn->prepare("UPDATE productos SET estado = ? WHERE id_producto = ?");
        $stmt_estado->bind_param("si", $nuevo_estado, $id_producto);
        if (!$stmt_estado->execute()) {
            throw new \Exception("Error al cambiar estado del producto.");
        }
        $stmt_estado->close();
        return "Estado del producto actualizado a '" . $nuevo_estado . "'";
    }

    /**
     * Actualiza los campos principales del producto y su galería de imágenes desde datos POST.
     * Valida nombre con preg_match (alfanumérico + caracteres especiales básicos del español);
     * si es inválido o falta la categoría lanza Exception antes de tocar la BD.
     * La imagen principal solo se actualiza si el usuario subió un nuevo archivo (procesarNuevaImagenPrincipal);
     * si no hay nueva imagen, la consulta UPDATE omite el campo imagen_principal.
     * Cuenta las imágenes adicionales actuales para calcular espacios_disponibles antes de procesar
     * las nuevas subidas y respetar MAX_ADDITIONAL_IMAGES.
     *
     * @param int $id_producto ID del producto a actualizar (sin restricción de vendedor: admin)
     * @return string Mensaje de confirmación tras la actualización exitosa
     * @throws \Exception Si la validación de nombre/categoría falla, o si BD/imagen falla
     */
    private function procesarActualizacionProductoAdmin(int $id_producto): string
    {
        $nombre = strip_tags(trim($_POST['nombre_producto']));
        $descripcion = strip_tags(trim($_POST['descripcion']));
        $id_categoria = (int) $_POST['id_categoria'];
        if (empty($nombre) || $id_categoria === 0) {
            throw new \Exception("Nombre y categoría obligatorios.");
        }
        if (!preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $nombre)) {
            throw new \Exception("El nombre del producto solo puede contener letras, números, espacios, puntos, comas y guiones.");
        }

        $imagen_query = "";
        $params = [$nombre, $descripcion, $id_categoria];
        $types = "ssi";

        $nombre_limpio = $this->procesarNuevaImagenPrincipal($id_producto);
        if ($nombre_limpio !== null) {
            $imagen_query .= ", imagen_principal = ?";
            $params[]     = $nombre_limpio;
            $types        .= "s";
        }

        $params[] = $id_producto;
        $types .= "i";

        $stmt = $this->conn->prepare("UPDATE productos SET nombre_producto = ?, descripcion = ?, id_categoria = ? {$imagen_query} WHERE id_producto = ?");
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new \Exception("Error al actualizar producto.");
        }
        $stmt->close();

        $stmt_count = $this->conn->prepare("SELECT COUNT(*) as total FROM producto_imagenes WHERE id_producto = ?");
        $stmt_count->bind_param("i", $id_producto);
        $stmt_count->execute();
        $current_images = $stmt_count->get_result()->fetch_assoc()['total'];
        $stmt_count->close();

        $this->procesarImagenesAdicionales($id_producto, self::MAX_ADDITIONAL_IMAGES - $current_images);
        return "Producto actualizado.";
    }

    /**
     * Elimina una imagen de la galería: primero borra el archivo físico del disco y luego
     * elimina el registro en la tabla producto_imagenes.
     * El SELECT previo al DELETE valida que el par (id_imagen, id_producto) exista juntos
     * para prevenir borrado de imágenes de otros productos (anti-IDOR básico).
     * Si la ruta del archivo existe en disco, usa @unlink para supresión silenciosa de errores.
     * El DELETE solo se ejecuta si el SELECT devuelve resultado; si la imagen ya fue borrada
     * del disco manualmente, el registro en BD se elimina igualmente para mantener consistencia.
     *
     * @param int $id_imagen   ID del registro en producto_imagenes a eliminar
     * @param int $id_producto ID del producto propietario (validación anti-IDOR del par)
     * @return string Mensaje de confirmación si execute() tuvo éxito; cadena vacía si falló
     */
    private function procesarEliminacionImagenAdmin(int $id_imagen, int $id_producto): string
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
     * Agrega una nueva variante a un producto existente procesando los datos de $_POST.
     * Valida talla y color con preg_match (alfanumérico + caracteres especiales del español).
     * Si talla o color están vacíos, usa los valores por defecto 'Única' y 'Estándar'.
     * Genera un SKU simulado compuesto de 3 letras del nombre + id_producto + talla + color
     * en mayúsculas; este SKU no garantiza unicidad global pero es informativo para el admin.
     * El INSERT falla con mysqli error si el SKU generado ya existe (campo SKU UNIQUE en BD).
     *
     * @param int $id_producto ID del producto al que se agrega la variante
     * @return string Mensaje de confirmación tras alta exitosa
     * @throws \Exception Si los datos de talla/color/precio/stock no pasan validación, o INSERT falla
     */
    private function procesarAgregadoVarianteAdmin(int $id_producto): string
    {
        $talla  = strip_tags(trim($_POST['talla']));
        $color  = strip_tags(trim($_POST['color']));
        $precio = filter_var(trim($_POST['precio']), FILTER_VALIDATE_FLOAT);
        $stock  = filter_var(trim($_POST['stock']),  FILTER_VALIDATE_INT);

        if (!empty($talla) && !preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $talla)) {
            throw new \Exception("La talla solo puede contener letras, números y signos básicos.");
        }
        if (!empty($color) && !preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $color)) {
            throw new \Exception("El color solo puede contener letras, números y signos básicos.");
        }
        if ($precio === false || $stock === false || $precio <= 0 || $stock < 0) {
            throw new \Exception("Precio/Stock inválidos.");
        }

        $tallaFinal = ($talla === '' ? 'Única' : $talla);
        $colorFinal = ($color === '' ? 'Estándar' : $color);

        $stmt_check_prop = $this->conn->prepare("SELECT nombre_producto FROM productos WHERE id_producto = ?");
        $stmt_check_prop->bind_param("i", $id_producto);
        $stmt_check_prop->execute();
        $nombre_prod_temp = $stmt_check_prop->get_result()->fetch_assoc()['nombre_producto'];
        $stmt_check_prop->close();
        $sku_simulado = strtoupper(substr($nombre_prod_temp, 0, 3)) . '-' . $id_producto . '-' . $tallaFinal . '-' . $colorFinal;

        $stmt = $this->conn->prepare("INSERT INTO variantes_producto (id_producto, talla, color, sku, precio, stock) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdi", $id_producto, $tallaFinal, $colorFinal, $sku_simulado, $precio, $stock);
        if (!$stmt->execute()) {
            throw new \Exception("Error al agregar variante: " . $this->conn->error);
        }
        $stmt->close();
        return "Nueva variante agregada.";
    }

    /**
     * Orquesta la actualización masiva de variantes de un producto iterando sobre $_POST['variantes'].
     * Crea dos prepared statements reutilizables (update + desactivar) antes del bucle para
     * evitar parsear la misma query SQL una vez por variante, lo que mejora el rendimiento
     * cuando el producto tiene muchas variantes.
     * Delega la lógica de cada variante a actualizarODesactivarVariante(), que decide entre
     * actualizar precio/stock o marcar como inactiva según la presencia de la clave 'desactivar'.
     * Si $_POST['variantes'] no está definido o no es array, usa array vacío (no-op silencioso).
     *
     * @param int $id_producto ID del producto cuyas variantes se actualizan
     * @return void
     * @throws \Exception Propagada desde actualizarODesactivarVariante() si precio/stock son inválidos
     */
    private function procesarActualizacionVariantesAdmin(int $id_producto): void
    {
        $stmt_update     = $this->conn->prepare("UPDATE variantes_producto SET precio = ?, stock = ? WHERE id_variante = ? AND id_producto = ?");
        $stmt_desactivar = $this->conn->prepare("UPDATE variantes_producto SET estado = 'inactivo' WHERE id_variante = ? AND id_producto = ?");
        $variantes_post  = is_array($_POST['variantes'] ?? null) ? $_POST['variantes'] : [];
        foreach ($variantes_post as $id_variante => $datos) {
            $this->actualizarODesactivarVariante((int) $id_variante, $id_producto, $datos, $stmt_update, $stmt_desactivar);
        }
        $stmt_update->close();
        $stmt_desactivar->close();
    }

    /**
     * Lista todos los productos del sistema (de todos los vendedores) con sus variantes en JSON.
     * Sin restricción de id_vendedor — el admin tiene visibilidad global.
     * La consulta usa GROUP_CONCAT + JSON_OBJECT para traer variantes en una sola fila por producto,
     * ordenadas por id_variante, incluyendo id_variante, talla, color, precio, stock y estado.
     *
     * Acción GET soportada:
     *   cambiar_estado_id=N & estado='activo'|'inactivo' — Alterna el estado del producto N y redirige.
     *
     * Variables flash de sesión leídas y limpiadas en este método:
     *   $_SESSION['mensaje_error'] — Error de la última acción ejecutada
     *   $_SESSION['mensaje_exito'] — Confirmación de la última acción exitosa
     *
     * @return array{nombre_admin: string, productos: array, mensaje_error: string|null, mensaje_exito: string|null}
     */
    public function listarProductos()
    {

        // --- 1. SEGURIDAD ---
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if ($_SESSION['rol'] !== 'admin') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Requiere privilegios de administrador.";
            header('Location: ?page=index');
            exit;
        }

        $nombre_admin = $_SESSION['usuario'];

        // --- 2. MANEJO DE MENSAJES ---
        $mensaje_error = $_SESSION['mensaje_error'] ?? null;
        $mensaje_exito = $_SESSION['mensaje_exito'] ?? null;
        unset($_SESSION['mensaje_error'], $_SESSION['mensaje_exito']);

        // --- 3. LÓGICA DE ACCIONES (POST/GET) ---
        // (Movemos tu lógica de 'cambiar_estado_id')
        if (isset($_GET['cambiar_estado_id'])) {
            $id_producto_cambiar = (int) $_GET['cambiar_estado_id'];
            $estado_actual = $_GET['estado'];

            $nuevo_estado = ($estado_actual === 'activo') ? 'inactivo' : 'activo';
            $mensaje = ($nuevo_estado === 'activo') ? 'Producto reactivado y visible en la tienda.' : 'Producto desactivado temporalmente.';

            try {
                $stmt_estado = $this->conn->prepare("UPDATE productos SET estado = ? WHERE id_producto = ?");
                $stmt_estado->bind_param("si", $nuevo_estado, $id_producto_cambiar);

                $ok = $stmt_estado->execute();
                if ($ok) {
                    $_SESSION['mensaje_exito'] = $mensaje;
                }
                if (!$ok) {
                    $_SESSION['mensaje_error'] = "Error al cambiar el estado del producto.";
                }
                $stmt_estado->close();
            } catch (Exception $e) {
                $_SESSION['mensaje_error'] = "Error de BD: " . $e->getMessage();
            }

            // Redirigimos al router MVC, no al archivo .php
            header('Location: ?page=admin_productos');
            exit;
        }
        // --- FIN LÓGICA DE ACCIONES ---

        // --- 4. LÓGICA DE VISUALIZACIÓN (GET) ---
        // (Movemos tu consulta principal)
        $query = "
            SELECT 
                p.id_producto, p.nombre_producto, p.imagen_principal,
                c.nombre_categoria,
                u.usuario AS nombre_vendedor, 
                p.estado AS estado_producto, 
                CONCAT(
                    '[', 
                    IFNULL(GROUP_CONCAT(
                        JSON_OBJECT(
                            'id_variante', vp.id_variante,
                            'talla', vp.talla, 'color', vp.color,
                            'precio', vp.precio, 'stock', vp.stock,
                            'estado', vp.estado
                        ) ORDER BY vp.id_variante
                    ), '') ,
                    ']'
                ) AS variantes_json
            FROM productos AS p
            JOIN categorias AS c ON p.id_categoria = c.id_categoria
            JOIN usuarios AS u ON p.id_vendedor = u.id_usuario 
            LEFT JOIN variantes_producto AS vp ON p.id_producto = vp.id_producto
            GROUP BY p.id_producto
            ORDER BY p.id_producto DESC
        ";

        $resultado = $this->conn->query($query);
        $productos = $resultado->fetch_all(MYSQLI_ASSOC);
        // NO cerramos la conexión aquí

        // --- 5. DEVOLVEMOS LOS DATOS ---
        return [
            'nombre_admin' => $nombre_admin,
            'productos' => $productos,
            'mensaje_error' => $mensaje_error,
            'mensaje_exito' => $mensaje_exito
        ];
    }

    /**
     * Carga y procesa la edición completa de un producto desde el panel de admin.
     * Sin restricción de id_vendedor — el admin puede editar cualquier producto del sistema.
     * En GET: carga datos del producto, categorías jerárquicas, todas las variantes e imágenes adicionales.
     * En POST: despacha la acción en $_POST['accion'] dentro de una transacción atómica.
     *
     * Acciones POST soportadas:
     *   accion='actualizar_producto'     — Actualiza nombre, descripción, categoría e imágenes
     *   accion='cambiar_estado_producto' — Alterna estado activo/inactivo según $_POST['estado']
     *   accion='agregar_variante'        — Inserta una variante nueva con SKU simulado
     *   accion='actualizar_variantes'    — Actualiza precio/stock o desactiva variantes existentes
     *   accion='eliminar_imagen'         — Borra imagen adicional del disco y de producto_imagenes
     *
     * Acción GET soportada:
     *   reactivar_variante_id=N          — Pone estado='activo' a una variante previamente desactivada
     *
     * @param int $id_producto ID del producto a editar (validado en el router antes de llamar)
     * @return array{nombre_admin: string, producto: array|null, categorias_jerarquia: array,
     *               variantes: array, imagenes_adicionales: array,
     *               mensaje_error: string|null, mensaje_exito: string|null, id_producto: int}
     */
    public function editarProducto($id_producto)
    {

        // --- 1. SEGURIDAD Y DATOS INICIALES ---
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ?page=login');
            exit;
        }
        if ($_SESSION['rol'] !== 'admin') {
            $_SESSION['mensaje_error'] = "Acceso denegado: Requiere privilegios de administrador.";
            header('Location: ?page=index');
            exit;
        }
        $nombre_admin = $_SESSION['usuario'];
        $mensaje_error = null;
        $mensaje_exito = null;

        // --- 2. LÓGICA DE ACCIONES (GET Y POST) ---
        // (Movemos toda tu lógica de PHP aquí)

        // --- Lógica de Reactivación (GET) ---
        if (isset($_GET['reactivar_variante_id'])) {
            try {
                $id_variante_reactivar = (int) $_GET['reactivar_variante_id'];

                // (Admin no necesita chequear propiedad, solo que exista)
                $stmt_check_var = $this->conn->prepare("SELECT 1 FROM variantes_producto WHERE id_variante = ?");
                $stmt_check_var->bind_param("i", $id_variante_reactivar);
                $stmt_check_var->execute();
                if ($stmt_check_var->get_result()->num_rows === 0) {
                    throw new Exception("Variante no encontrada.");
                }
                $stmt_check_var->close();

                $stmt_reactivar = $this->conn->prepare("UPDATE variantes_producto SET estado = 'activo' WHERE id_variante = ? AND id_producto = ?");
                $stmt_reactivar->bind_param("ii", $id_variante_reactivar, $id_producto);
                if (!$stmt_reactivar->execute()) {
                    throw new Exception("Error al reactivar.");
                }
                $_SESSION['mensaje_exito_temp'] = "Variante reactivada.";
                $stmt_reactivar->close();
            } catch (Exception $e) {
                $_SESSION['mensaje_error_temp'] = "Error: " . $e->getMessage();
            }

            header("Location: ?page=admin_editar_producto&id=$id_producto"); // Redirige al router
            exit;
        }
        if (isset($_SESSION['mensaje_exito_temp'])) {
            $mensaje_exito = $_SESSION['mensaje_exito_temp'];
            unset($_SESSION['mensaje_exito_temp']);
        }
        if (isset($_SESSION['mensaje_error_temp'])) {
            $mensaje_error = $_SESSION['mensaje_error_temp'];
            unset($_SESSION['mensaje_error_temp']);
        }

        // Limpiar cualquier mensaje fantasma persistente en la sesión
        unset($_SESSION['mensaje_exito']);
        unset($_SESSION['mensaje_error']);

        // --- Lógica POST ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->conn->begin_transaction();
                // --- ACCIÓN: CAMBIAR ESTADO DEL PRODUCTO (Activar/Desactivar) ---
                if (isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado_producto') {
                    $mensaje_exito = $this->procesarCambioEstadoProductoAdmin($id_producto);
                }
                // --- ACCIÓN: ACTUALIZAR PRODUCTO GENERAL ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_producto') {
                    $mensaje_exito = $this->procesarActualizacionProductoAdmin($id_producto);
                }
                // --- ACCIÓN: ELIMINAR IMAGEN DE LA GALERÍA ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_imagen') {
                    $id_imagen = (int) $_POST['id_imagen'];
                    $mensaje_exito = $this->procesarEliminacionImagenAdmin($id_imagen, $id_producto);
                }
                // --- ACCIÓN: AGREGAR NUEVA VARIANTE ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'agregar_variante') {
                    $mensaje_exito = $this->procesarAgregadoVarianteAdmin($id_producto);
                }
                // --- ACCIÓN: ACTUALIZAR / DESACTIVAR VARIANTES EXISTENTES ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_variantes') {
                    $this->procesarActualizacionVariantesAdmin($id_producto);
                    $mensaje_exito = "Lista de variantes actualizada.";
                }

                // Confirmar todas las transacciones realizadas (imágenes, variantes o producto general)
                $this->conn->commit();

            } catch (Exception $e) {
                $this->conn->rollback();
                $mensaje_error = "Error: " . $e->getMessage();
            }
        }
        // --- FIN Lógica POST ---

        // --- 3. LÓGICA GET (Visualización) ---
        $sql_producto = "SELECT p.*, c.nombre_categoria FROM productos p JOIN categorias c ON p.id_categoria = c.id_categoria WHERE p.id_producto = ?";
        $stmt = $this->conn->prepare($sql_producto);
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        $resultado_producto = $stmt->get_result();
        if ($resultado_producto->num_rows === 0) {
            $_SESSION['mensaje_error'] = "Producto no encontrado.";
            header('Location: ?page=admin_productos'); // Redirige al router
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

        // Obtenemos TODAS las variantes
        $stmt_variantes = $this->conn->prepare("SELECT *, estado, imagen_variante FROM variantes_producto WHERE id_producto = ? ORDER BY talla, color");
        $stmt_variantes->bind_param("i", $id_producto);
        $stmt_variantes->execute();
        $resultado_variantes = $stmt_variantes->get_result();
        $variantes = $resultado_variantes->fetch_all(MYSQLI_ASSOC);
        $stmt_variantes->close();

        // Obtener la galería de imágenes actuales desde la nueva tabla
        $stmt_img = $this->conn->prepare("SELECT id_imagen, ruta_imagen FROM producto_imagenes WHERE id_producto = ?");
        $stmt_img->bind_param("i", $id_producto);
        $stmt_img->execute();
        $imagenes_adicionales = $stmt_img->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_img->close();

        // --- 4. DEVOLVEMOS LOS DATOS A LA VISTA ---
        return [
            'nombre_admin' => $nombre_admin,
            'producto' => $producto,
            'categorias_jerarquia' => $categorias_jerarquia,
            'variantes' => $variantes,
            'imagenes_adicionales' => $imagenes_adicionales,
            'mensaje_error' => $mensaje_error,
            'mensaje_exito' => $mensaje_exito,
            'id_producto' => $id_producto
        ];
    }

}
?>