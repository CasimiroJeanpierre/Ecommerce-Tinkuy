<?php
// src/Models/Producto.php

/**
 * Modelo de productos del catálogo Ecommerce-Tinkuy.
 * Centraliza todas las consultas SQL relacionadas con productos, variantes, imágenes y carrito.
 * No gestiona sesiones ni lógica de negocio de controladores — solo acceso a datos.
 *
 * Métodos disponibles:
 *   getProductosDestacados()   — hasta 3 productos activos con stock para el home
 *   getProductoActivoPorId()   — detalle de un producto + galería de imágenes adicionales
 *   getVariantesActivasPorId() — variantes activas con stock para la página de producto
 *   getProductosFiltrados()    — catálogo con filtros de categoría, búsqueda y orden
 *   getProductosDelCarrito()   — datos de variantes por IDs para la vista del carrito
 */
class Producto
{
    /**
     * Devuelve hasta 3 productos activos con stock para la vitrina de la página principal.
     * La subquery en SELECT calcula el precio mínimo de variantes activas con stock > 0
     * para cada producto como precio_minimo. El filtro EXISTS garantiza que solo aparecen
     * productos con al menos una variante disponible en el momento de la consulta.
     * Los productos se seleccionan en orden aleatorio (ORDER BY RAND()) para mostrar
     * variedad en cada carga — nota que RAND() puede ser lento en catálogos muy grandes.
     *
     * @param mysqli $conn Conexión activa a la base de datos
     * @return array<int, array{id_producto: int, nombre_producto: string, descripcion: string,
     *                          imagen_principal: string, precio_minimo: float}>
     *         Hasta 3 productos con precio_minimo calculado; array vacío si falla la consulta
     */
    public function getProductosDestacados($conn)
    {
        // --- ¡ESTE CÓDIGO FALTABA! ---
        // Esta es la misma consulta que tenías en tu index.php original
        $query = "
            SELECT
                p.id_producto,
                p.nombre_producto,
                p.descripcion,
                p.imagen_principal,
                (SELECT MIN(vp.precio)
                 FROM variantes_producto vp
                 WHERE vp.id_producto = p.id_producto
                   AND vp.estado = 'activo'
                   AND vp.stock > 0
                ) AS precio_minimo
            FROM
                productos AS p
            WHERE
                p.estado = 'activo'
                AND EXISTS (
                    SELECT 1
                    FROM variantes_producto vp
                    WHERE vp.id_producto = p.id_producto
                      AND vp.estado = 'activo'
                      AND vp.stock > 0
                )
            ORDER BY
                RAND()
            LIMIT 3
        ";

        $resultado = $conn->query($query);
        $productos_destacados = [];

        if ($resultado) {
            $productos_destacados = $resultado->fetch_all(MYSQLI_ASSOC);
        } else {
            error_log("Error en consulta de productos destacados: " . $conn->error);
        }

        return $productos_destacados;
        // --- FIN DEL CÓDIGO QUE FALTABA ---
    }

    /**
     * Obtiene un producto activo por su ID junto con su galería de imágenes adicionales.
     * Solo devuelve productos con estado='activo'. Si el producto no existe o está inactivo, retorna null.
     * Las imágenes adicionales se adjuntan como $producto['imagenes_adicionales'] (array de rutas).
     *
     * @param mysqli $conn       Conexión activa a la base de datos
     * @param int    $id_producto ID del producto a consultar
     * @return array{nombre_producto, descripcion, imagen_principal, estado, nombre_categoria, imagenes_adicionales: array}|null
     *         null si el producto no existe o no está activo
     */
    public function getProductoActivoPorId($conn, $id_producto)
    {
        // Esta es la consulta de tu producto.php
        $stmt_producto = $conn->prepare("
            SELECT p.nombre_producto, p.descripcion, p.imagen_principal, p.estado, c.nombre_categoria
            FROM productos AS p
            JOIN categorias AS c ON p.id_categoria = c.id_categoria
            WHERE p.id_producto = ? AND p.estado = 'activo'
        ");
        $stmt_producto->bind_param("i", $id_producto);
        $stmt_producto->execute();
        $resultado_producto = $stmt_producto->get_result();

        if ($resultado_producto->num_rows === 0) {
            return null; // No encontrado o inactivo
        }

        $producto = $resultado_producto->fetch_assoc();
        $stmt_producto->close();

        // Obtener la galería de imágenes adicionales desde la nueva tabla
        $producto['imagenes_adicionales'] = [];
        $stmt_img = $conn->prepare("SELECT ruta_imagen FROM producto_imagenes WHERE id_producto = ?");
        $stmt_img->bind_param("i", $id_producto);
        $stmt_img->execute();
        $res_img = $stmt_img->get_result();
        while ($row = $res_img->fetch_assoc()) {
            $producto['imagenes_adicionales'][] = $row['ruta_imagen'];
        }
        $stmt_img->close();

        return $producto;
    }

    /**
     * Devuelve las variantes activas con stock positivo de un producto específico.
     * Solo incluye variantes con estado='activo' AND stock > 0 para evitar mostrar
     * opciones agotadas en el selector talla/color de la página de detalle de producto.
     * El resultado se ordena por talla y luego por color para una presentación consistente
     * sin depender del orden de inserción en la BD.
     * Si un producto no tiene variantes activas con stock, retorna un array vacío (no null).
     *
     * @param mysqli $conn       Conexión activa a la base de datos
     * @param int    $id_producto ID del producto cuyas variantes activas se consultan
     * @return array<int, array{id_variante: int, talla: string, color: string, precio: float, stock: int}>
     *         Array de variantes disponibles ordenado por talla, color; vacío si no hay stock
     */
    public function getVariantesActivasPorId($conn, $id_producto)
    {
        // Esta es tu consulta de variantes
        $stmt_variantes = $conn->prepare("
            SELECT id_variante, talla, color, precio, stock
            FROM variantes_producto
            WHERE id_producto = ?
              AND estado = 'activo'
              AND stock > 0
            ORDER BY talla, color
        ");
        $stmt_variantes->bind_param("i", $id_producto);
        $stmt_variantes->execute();
        $resultado_variantes = $stmt_variantes->get_result();

        $variantes = [];
        while ($fila = $resultado_variantes->fetch_assoc()) {
            $variantes[] = $fila;
        }
        $stmt_variantes->close();
        return $variantes;
    }
    // ... (después de la función getVariantesActivasPorId) ...

    /**
     * Obtiene el catálogo de productos activos con soporte de filtros dinámicos.
     * La subconsulta interna de variantes (solo activas con stock > 0) calcula min_precio,
     * max_precio y total_stock; el INNER JOIN garantiza que solo aparecen productos
     * con al menos una variante disponible al momento de la consulta.
     * Filtro por categoría: usa OR (id_categoria = ? OR id_categoria_padre = ?) para
     * incluir productos de subcategorías cuando se selecciona una categoría padre.
     * Filtro de búsqueda: usa LIKE %término% sobre nombre_producto (búsqueda parcial).
     * Orden soportado: 'precio_asc' → min_precio ASC, 'precio_desc' → min_precio DESC,
     * cualquier otro valor → nombre_producto ASC (comportamiento por defecto).
     *
     * @param mysqli $conn    Conexión activa a la base de datos
     * @param array{categoria: int|null, buscar: string, orden: string} $filtros Criterios de filtrado y orden
     * @return array<int, array{id_producto: int, nombre_producto: string, imagen_principal: string,
     *                          nombre_categoria: string, min_precio: float, max_precio: float}>
     */
    public function getProductosFiltrados($conn, $filtros)
    {
        // Valores por defecto
        $id_categoria = $filtros['categoria'] ?? null;
        $termino_busqueda = $filtros['buscar'] ?? '';
        $orden = $filtros['orden'] ?? 'nombre_asc';

        // --- Esta es toda la lógica SQL de tu archivo 'products.php' antiguo ---
        $sql_base = "
            FROM productos p
            JOIN categorias c ON p.id_categoria = c.id_categoria
            INNER JOIN (
                SELECT id_producto, MIN(precio) as min_precio, MAX(precio) as max_precio, SUM(stock) as total_stock
                FROM variantes_producto
                WHERE estado = 'activo' AND stock > 0
                GROUP BY id_producto
                HAVING SUM(stock) > 0
            ) vp ON p.id_producto = vp.id_producto
            WHERE p.estado = 'activo'
        ";

        $params = [];
        $types = "";

        if ($id_categoria !== null) {
            // (Esta es una lógica simplificada para el filtro de categoría)
            $sql_base .= " AND (p.id_categoria = ? OR c.id_categoria_padre = ?)";
            $params[] = $id_categoria;
            $params[] = $id_categoria;
            $types .= "ii";
        }

        if (!empty($termino_busqueda)) {
            $sql_base .= " AND p.nombre_producto LIKE ?";
            $params[] = "%" . $termino_busqueda . "%";
            $types .= "s";
        }

        // --- Contar total (para paginación, aunque no la implementamos aquí aún) ---
        // $sql_count = "SELECT COUNT(DISTINCT p.id_producto) as total " . $sql_base;
        // ... (lógica de conteo iría aquí) ...

        // --- Consulta principal ---
        $sql_select = "SELECT DISTINCT p.id_producto, p.nombre_producto, p.imagen_principal, c.nombre_categoria, vp.min_precio, vp.max_precio";
        $sql_order = "";

        switch ($orden) {
            case 'precio_asc':
                $sql_order .= " ORDER BY vp.min_precio ASC";
                break;
            case 'precio_desc':
                $sql_order .= " ORDER BY vp.min_precio DESC";
                break;
            default:
                $sql_order .= " ORDER BY p.nombre_producto ASC";
        }

        $sql_final = $sql_select . $sql_base . $sql_order; // (Sin paginación por ahora)
        $stmt_productos = $conn->prepare($sql_final);

        if (!empty($types)) {
            $stmt_productos->bind_param($types, ...$params);
        }

        $stmt_productos->execute();
        $resultado_productos = $stmt_productos->get_result();
        $productos = $resultado_productos->fetch_all(MYSQLI_ASSOC);
        $stmt_productos->close();

        return $productos;
    }
    // ... (después de la función getProductosFiltrados) ...

    /**
     * Obtiene los datos necesarios para renderizar los ítems del carrito a partir de
     * un array de IDs de variantes (las claves de $_SESSION['carrito']).
     * Hace JOIN entre variantes_producto y productos para obtener nombre, imagen, talla,
     * color, precio y stock de cada variante solicitada en una sola consulta con IN().
     * Devuelve los resultados indexados por id_variante para acceso O(1) en el router.
     * Si el array de IDs está vacío, retorna un array vacío sin consultar la BD.
     * Los ítems cuya variante ya no existe en BD simplemente no aparecen en el resultado;
     * el router (procesarItemsCarrito) limpia esos ítems de $_SESSION['carrito'] a posteriori.
     *
     * @param mysqli     $conn          Conexión activa a la base de datos
     * @param array<int> $ids_variantes IDs de variantes a consultar (claves de $_SESSION['carrito'])
     * @return array<int, array{id_variante: int, nombre_producto: string, imagen_principal: string,
     *                          talla: string, color: string, precio: float, stock: int}>
     *         Mapa id_variante → datos de BD; vacío si no se encontró ninguna variante
     */
    public function getProductosDelCarrito($conn, $ids_variantes)
    {
        if (empty($ids_variantes)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids_variantes), '?'));
        $tipos = str_repeat('i', count($ids_variantes));

        $query = "
            SELECT
                v.id_variante, v.talla, v.color, v.precio, v.stock,
                p.nombre_producto, p.imagen_principal
            FROM
                variantes_producto AS v
            JOIN
                productos AS p ON v.id_producto = p.id_producto
            WHERE
                v.id_variante IN ($placeholders)
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($tipos, ...$ids_variantes);
        $stmt->execute();
        $resultado = $stmt->get_result();

        $detalles_productos = [];
        while ($fila = $resultado->fetch_assoc()) {
            // Usamos el id_variante como clave para fácil acceso
            $detalles_productos[$fila['id_variante']] = $fila;
        }
        $stmt->close();

        return $detalles_productos;
    }
}