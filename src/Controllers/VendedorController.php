<?php
// src/Controllers/VendedorController.php
// Convertimos el controlador procedimental en una clase para usar en MVC
if (session_status() === PHP_SESSION_NONE)
    session_start();

class VendedorController
{
    private $conn;
    private $base_url;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
        $this->base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';

        // Validaciones de sesión y rol
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
     * Listar productos del vendedor (migrado desde la vista procedural)
     * @return array Datos para la vista
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
     * Cambiar estado de un producto (activar/inactivar)
     * @param int $id_producto
     * @param string $nuevo_estado
     * @return array
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

    // Métodos stub para futuras implementaciones (agregar/editar/eliminar/listarVentas/listarEnvios)
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

                // Insertar Múltiples Imágenes Adicionales (Respetando Límite de 9)
                if (isset($_FILES['imagenes_adicionales'])) {
                    $stmt_img = $this->conn->prepare("INSERT INTO producto_imagenes (id_producto, ruta_imagen) VALUES (?, ?)");
                    $total_files = count($_FILES['imagenes_adicionales']['name']);
                    $agregadas = 0;
                    for ($i = 0; $i < $total_files; $i++) {
                        if ($agregadas >= 9)
                            break; // Límite estricto
                        if ($_FILES['imagenes_adicionales']['error'][$i] == UPLOAD_ERR_OK) {
                            $fileName = $_FILES['imagenes_adicionales']['name'][$i];
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            if (!in_array($fileExt, $allowedfileExtensions)) {
                                throw new Exception("Tipo de archivo de imagen adicional no permitido.");
                            }
                            if ($_FILES['imagenes_adicionales']['size'][$i] <= 2 * 1024 * 1024) {
                                $nom_limpio = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($fileName));
                                $dest = BASE_PATH . '/public/img/productos/' . $nom_limpio;
                                if (move_uploaded_file($_FILES['imagenes_adicionales']['tmp_name'][$i], $dest)) {
                                    $stmt_img->bind_param("is", $id_producto, $nom_limpio);
                                    $stmt_img->execute();
                                    $agregadas++;
                                }
                            }
                        }
                    }
                    $stmt_img->close();
                }

                // Procesar variantes iniciales
                if (isset($_POST['variantes']) && is_array($_POST['variantes'])) {
                    $stmt_variante = $this->conn->prepare("INSERT INTO variantes_producto (id_producto, talla, color, sku, precio, stock) VALUES (?, ?, ?, ?, ?, ?)");
                    $variantes_agregadas = [];

                    foreach ($_POST['variantes'] as $v) {
                        // Validar solo precio y stock (talla/color pueden estar vacíos)
                        if (empty($v['precio']) || !isset($v['stock'])) {
                            continue; // Saltamos variantes incompletas
                        }

                        $talla = isset($v['talla']) ? strip_tags(trim($v['talla'])) : '';
                        $color = isset($v['color']) ? strip_tags(trim($v['color'])) : '';

                        if (!empty($talla) && !preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $talla)) {
                            throw new Exception("La talla solo puede contener letras, números y algunos signos básicos.");
                        }
                        if (!empty($color) && !preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $color)) {
                            throw new Exception("El color solo puede contener letras, números y algunos signos básicos.");
                        }

                        $tallaFinal = ($talla === '') ? 'Única' : $talla;
                        $colorFinal = ($color === '') ? 'Estándar' : $color;
                        $precio = filter_var($v['precio'], FILTER_VALIDATE_FLOAT);
                        $stock = filter_var($v['stock'], FILTER_VALIDATE_INT);

                        if ($precio === false || $stock === false || $precio <= 0 || $stock < 0) {
                            continue; // Saltamos variantes con datos inválidos
                        }

                        $key_var = $tallaFinal . '-' . $colorFinal;
                        if (in_array($key_var, $variantes_agregadas)) {
                            throw new Exception("No puedes crear variantes duplicadas con la misma talla ($tallaFinal) y color ($colorFinal).");
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
     * Cambia el estado de una variante de producto (activo/inactivo)
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
                    $nombre = strip_tags(trim($_POST['nombre_producto']));
                    $descripcion = strip_tags(trim($_POST['descripcion']));
                    $id_categoria = (int) $_POST['id_categoria'];
                    if (empty($nombre) || $id_categoria === 0) {
                        throw new Exception("Nombre y categoría obligatorios.");
                    }
                    if (!preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $nombre)) {
                        throw new Exception("El nombre del producto solo puede contener letras, números, espacios, puntos, comas y guiones.");
                    }

                    $imagen_query = "";
                    $params = [$nombre, $descripcion, $id_categoria];
                    $types = "ssi";

                    // Lógica de Subida de Imagen Principal
                    if (isset($_FILES['imagen_principal']) && $_FILES['imagen_principal']['error'] == UPLOAD_ERR_OK) {
                        $fileName = $_FILES['imagen_principal']['name'];
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                        if (!in_array($fileExtension, $allowedfileExtensions)) {
                            throw new Exception("El formato de la imagen principal no está permitido (solo JPG, PNG, WEBP).");
                        }

                        $nombre_limpio = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($fileName));
                        $dest_path = BASE_PATH . '/public/img/productos/' . $nombre_limpio;
                        if (!move_uploaded_file($_FILES['imagen_principal']['tmp_name'], $dest_path)) {
                            throw new Exception('Error al mover la imagen subida.');
                        }

                        // Eliminar imagen anterior físicamente del servidor
                        $stmt_old = $this->conn->prepare("SELECT imagen_principal FROM productos WHERE id_producto = ?");
                        $stmt_old->bind_param("i", $id_producto);
                        $stmt_old->execute();
                        $res_old = $stmt_old->get_result();
                        if ($row_old = $res_old->fetch_assoc()) {
                            $old_img = $row_old['imagen_principal'];
                            if (!empty($old_img) && $old_img !== $nombre_limpio) {
                                $old_path = BASE_PATH . '/public/img/productos/' . $old_img;
                                if (file_exists($old_path) && is_file($old_path)) {
                                    @unlink($old_path);
                                }
                            }
                        }
                        $stmt_old->close();

                        $imagen_query .= ", imagen_principal = ?";
                        $params[] = $nombre_limpio;
                        $types .= "s";
                    }

                    $params[] = $id_producto;
                    $params[] = $id_vendedor;
                    $types .= "ii";

                    $stmt = $this->conn->prepare("UPDATE productos SET nombre_producto = ?, descripcion = ?, id_categoria = ? {$imagen_query} WHERE id_producto = ? AND id_vendedor = ?");
                    $stmt->bind_param($types, ...$params);
                    if (!$stmt->execute()) {
                        throw new Exception("Error al actualizar producto.");
                    }
                    $stmt->close();

                    // Agregar nuevas imágenes adicionales calculando espacios disponibles
                    if (isset($_FILES['imagenes_adicionales'])) {
                        $stmt_count = $this->conn->prepare("SELECT COUNT(*) as total FROM producto_imagenes WHERE id_producto = ?");
                        $stmt_count->bind_param("i", $id_producto);
                        $stmt_count->execute();
                        $current_images = $stmt_count->get_result()->fetch_assoc()['total'];
                        $stmt_count->close();

                        $espacios_disponibles = 9 - $current_images;

                        if ($espacios_disponibles > 0) {
                            $stmt_img = $this->conn->prepare("INSERT INTO producto_imagenes (id_producto, ruta_imagen) VALUES (?, ?)");
                            $total_files = count($_FILES['imagenes_adicionales']['name']);
                            $agregadas = 0;

                            for ($i = 0; $i < $total_files; $i++) {
                                if ($agregadas >= $espacios_disponibles)
                                    break;
                                if ($_FILES['imagenes_adicionales']['error'][$i] == UPLOAD_ERR_OK) {
                                    $fileName = $_FILES['imagenes_adicionales']['name'][$i];
                                    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                                    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                                    if (!in_array($fileExt, $allowedExt)) {
                                        throw new Exception("El formato de una de las imágenes adicionales no está permitido.");
                                    }
                                    if ($_FILES['imagenes_adicionales']['size'][$i] <= 2 * 1024 * 1024) {
                                        $nom_limpio = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', basename($fileName));
                                        $nom_limpio = time() . '_' . $i . '_' . $nom_limpio;
                                        $dest = BASE_PATH . '/public/img/productos/' . $nom_limpio;
                                        if (move_uploaded_file($_FILES['imagenes_adicionales']['tmp_name'][$i], $dest)) {
                                            $stmt_img->bind_param("is", $id_producto, $nom_limpio);
                                            $stmt_img->execute();
                                            $agregadas++;
                                        }
                                    }
                                }
                            }
                            $stmt_img->close();
                        }
                    }

                    $mensaje_exito = "Producto actualizado.";
                }

                // --- ACCIÓN: ELIMINAR IMAGEN DE LA GALERÍA ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_imagen') {
                    $id_imagen = (int) $_POST['id_imagen'];

                    // Eliminar el archivo físico de la galería
                    $stmt_get = $this->conn->prepare("SELECT ruta_imagen FROM producto_imagenes WHERE id_imagen = ? AND id_producto = ?");
                    $stmt_get->bind_param("ii", $id_imagen, $id_producto);
                    $stmt_get->execute();
                    $res_get = $stmt_get->get_result();
                    if ($row_img = $res_get->fetch_assoc()) {
                        $old_path = BASE_PATH . '/public/img/productos/' . $row_img['ruta_imagen'];
                        if (file_exists($old_path) && is_file($old_path)) {
                            @unlink($old_path);
                        }
                    }
                    $stmt_get->close();

                    $stmt_del = $this->conn->prepare("DELETE FROM producto_imagenes WHERE id_imagen = ? AND id_producto = ?");
                    $stmt_del->bind_param("ii", $id_imagen, $id_producto);
                    if ($stmt_del->execute()) {
                        $mensaje_exito = "Imagen eliminada de la galería.";
                    }
                    $stmt_del->close();
                }

                // --- ACCIÓN: AGREGAR NUEVA VARIANTE (CON IMAGEN) ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'agregar_variante') {
                    $talla = strip_tags(trim($_POST['talla']));
                    $color = strip_tags(trim($_POST['color']));
                    $precio = filter_var(trim($_POST['precio']), FILTER_VALIDATE_FLOAT);
                    $stock = filter_var(trim($_POST['stock']), FILTER_VALIDATE_INT);

                    if (!empty($talla) && !preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $talla)) {
                        throw new Exception("La talla solo puede contener letras, números y signos básicos.");
                    }
                    if (!empty($color) && !preg_match('/^[a-zA-Z0-9\sñáéíóúÁÉÍÓÚ.,\-_]+$/u', $color)) {
                        throw new Exception("El color solo puede contener letras, números y signos básicos.");
                    }

                    if ($precio === false || $stock === false || $precio <= 0 || $stock < 0) {
                        throw new Exception("Precio/Stock inválidos.");
                    }

                    // Verificar propiedad
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

                    // Verificar que la variante no exista ya en este producto
                    $stmt_check_var = $this->conn->prepare("SELECT id_variante FROM variantes_producto WHERE id_producto = ? AND talla = ? AND color = ?");
                    $stmt_check_var->bind_param("iss", $id_producto, $tallaFinal, $colorFinal);
                    $stmt_check_var->execute();
                    if ($stmt_check_var->get_result()->num_rows > 0) {
                        $stmt_check_var->close();
                        throw new Exception("Ya existe una variante con la talla '$tallaFinal' y color '$colorFinal'.");
                    }
                    $stmt_check_var->close();

                    $talla_sku = preg_replace('/[^A-Za-z0-9]/', '', $tallaFinal);
                    $color_sku = preg_replace('/[^A-Za-z0-9]/', '', $colorFinal);
                    $sku_simulado = strtoupper(substr($nombre_prod_temp, 0, 3)) . '-' . $id_producto . '-' . $talla_sku . '-' . $color_sku . '-' . rand(1000, 9999);

                    $stmt = $this->conn->prepare("INSERT INTO variantes_producto (id_producto, talla, color, sku, precio, stock) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssdi", $id_producto, $tallaFinal, $colorFinal, $sku_simulado, $precio, $stock);
                    if ($stmt->execute()) {
                        $mensaje_exito = "Nueva variante agregada.";
                    } else {
                        throw new Exception("Error al agregar variante: " . $this->conn->error);
                    }
                    $stmt->close();
                }

                // --- ACCIÓN: ACTUALIZAR / DESACTIVAR VARIANTES EXISTENTES ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_variantes') {
                    $stmt_update = $this->conn->prepare("UPDATE variantes_producto SET precio = ?, stock = ? WHERE id_variante = ? AND id_producto = ?");
                    $stmt_desactivar = $this->conn->prepare("UPDATE variantes_producto SET estado = 'inactivo' WHERE id_variante = ? AND id_producto = ?");

                    if (isset($_POST['variantes']) && is_array($_POST['variantes'])) {
                        foreach ($_POST['variantes'] as $id_variante => $datos) {
                            $id_variante = (int) $id_variante;
                            // Verificar propiedad de la variante
                            $stmt_check_var = $this->conn->prepare("SELECT 1 FROM variantes_producto vp JOIN productos p ON vp.id_producto = p.id_producto WHERE vp.id_variante = ? AND p.id_vendedor = ?");
                            $stmt_check_var->bind_param("ii", $id_variante, $id_vendedor);
                            $stmt_check_var->execute();
                            if ($stmt_check_var->get_result()->num_rows === 0) {
                                $stmt_check_var->close();
                                throw new Exception("Permiso denegado variante $id_variante.");
                            }
                            $stmt_check_var->close();

                            if (isset($datos['desactivar'])) {
                                $stmt_desactivar->bind_param("ii", $id_variante, $id_producto);
                                $stmt_desactivar->execute();
                            } else {
                                $precio = filter_var($datos['precio'], FILTER_VALIDATE_FLOAT);
                                $stock = filter_var($datos['stock'], FILTER_VALIDATE_INT);
                                if ($precio === false || $stock === false || $precio <= 0 || $stock < 0) {
                                    throw new Exception("Datos inválidos variante $id_variante.");
                                }
                                $stmt_update->bind_param("diii", $precio, $stock, $id_variante, $id_producto);
                                $stmt_update->execute();
                            }
                        }
                    }
                    $mensaje_exito = "Lista de variantes actualizada.";
                    $stmt_update->close();
                    $stmt_desactivar->close();
                }

                // --- REACTIVAR VARIANTE ---
                if (isset($_GET['reactivar_variante_id'])) {
                    $id_variante_reactivar = (int) $_GET['reactivar_variante_id'];
                    $resultado = $this->cambiarEstadoVariante($id_producto, $id_variante_reactivar, 'activo');
                    if ($resultado['success']) {
                        $mensaje_exito = $resultado['mensaje'];
                    } else {
                        throw new Exception($resultado['mensaje']);
                    }
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

    public function listarVentas()
    {
        // De momento devolvemos estructura vacía para la vista
        return [
            'ventas' => [],
            'base_url' => $this->base_url,
            'nombre_vendedor' => $_SESSION['usuario'] ?? ''
        ];
    }

    public function listarEnvios()
    {
        return [
            'envios' => [],
            'base_url' => $this->base_url,
            'nombre_vendedor' => $_SESSION['usuario'] ?? ''
        ];
    }
}

?>