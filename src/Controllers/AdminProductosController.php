<?php
// src/Controllers/AdminProductosController.php

class AdminProductosController
{

    private $conn;

    public function __construct($db_connection)
    {
        $this->conn = $db_connection;
    }

    /**
     * Muestra y procesa la lista de todos los productos
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

                if ($stmt_estado->execute()) {
                    $_SESSION['mensaje_exito'] = $mensaje;
                } else {
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
     * Muestra y procesa la página de "Editar Producto"
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
                if ($stmt_reactivar->execute()) {
                    $_SESSION['mensaje_exito_temp'] = "Variante reactivada.";
                } else {
                    throw new Exception("Error al reactivar.");
                }
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
                    $nuevo_estado = $_POST['estado'] === 'activo' ? 'activo' : 'inactivo';
                    $stmt_estado = $this->conn->prepare("UPDATE productos SET estado = ? WHERE id_producto = ?");
                    $stmt_estado->bind_param("si", $nuevo_estado, $id_producto);
                    if ($stmt_estado->execute()) {
                        $mensaje_exito = "Estado del producto actualizado a '" . $nuevo_estado . "'.";
                    } else {
                        throw new Exception("Error al cambiar estado del producto.");
                    }
                    $stmt_estado->close();
                }

                // --- ACCIÓN: ACTUALIZAR PRODUCTO GENERAL ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_producto') {
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
                    $types .= "i";

                    $stmt = $this->conn->prepare("UPDATE productos SET nombre_producto = ?, descripcion = ?, id_categoria = ? {$imagen_query} WHERE id_producto = ?");
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
                    // Permitir vacío: aplicar valores por defecto
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
                    if ($stmt->execute()) {
                        $mensaje_exito = "Nueva variante agregada.";
                    } else {
                        throw new Exception("Error al agregar variante: " . $this->conn->error);
                    }
                    $stmt->close();
                }

                // --- ACCIÓN: ACTUALIZAR / DESACTIVAR VARIANTES EXISTENTES ---
                elseif (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_variantes') {
                    $this->conn->begin_transaction();
                    $stmt_update = $this->conn->prepare("UPDATE variantes_producto SET precio = ?, stock = ? WHERE id_variante = ? AND id_producto = ?");
                    $stmt_desactivar = $this->conn->prepare("UPDATE variantes_producto SET estado = 'inactivo' WHERE id_variante = ? AND id_producto = ?");

                    if (isset($_POST['variantes']) && is_array($_POST['variantes'])) {
                        foreach ($_POST['variantes'] as $id_variante => $datos) {
                            $id_variante = (int) $id_variante;
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
                    $this->conn->commit();
                    $stmt_update->close();
                    $stmt_desactivar->close();
                    $mensaje_exito = "Lista de variantes actualizada.";
                }

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