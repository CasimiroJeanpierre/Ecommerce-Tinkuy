<?php
// src/Controllers/ProductoController.php

/**
 * Controlador del catálogo público de productos.
 * Gestiona el listado de productos con filtros por categoría, búsqueda por texto y orden.
 * Delega la consulta al modelo Producto::getProductosFiltrados().
 *
 * Métodos disponibles:
 *   listar() — Aplica filtros de GET (categoria, busqueda, orden) y retorna datos para la vista
 *
 * Uso desde el router (public/index.php):
 *   $ctrl = new ProductoController($conn);
 *   extract($ctrl->listar());
 *   include BASE_PATH . '/src/Views/producto/products.php';
 *
 * Parámetros GET aceptados:
 *   categoria (int)    — Filtra por ID de categoría
 *   busqueda  (string) — Busca en nombre y descripción del producto
 *   orden     (string) — 'nombre_asc'|'precio_asc'|'precio_desc'
 */
class ProductoController {

    private $conn;

    /**
     * @param mysqli $db_connection Conexión activa a la base de datos
     */
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    /**
     * Devuelve los datos del catálogo aplicando filtros de categoría, búsqueda y orden.
     *
     * @return array{
     *   productos_listados: array,
     *   categorias: array,
     *   total_productos: int,
     *   id_categoria_filtro: int|null,
     *   termino_busqueda: string,
     *   orden: string,
     *   filtros_activos: bool
     * }
     */
    public function listarProductos() {
        
        // --- INICIO DE TU LÓGICA (Movida desde index.php) ---
        
        $modeloProducto = new Producto();
        $modeloCategoria = new Categoria();

        $filtros = [
            'categoria' => filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT) ?: null,
            'buscar' => trim(filter_input(INPUT_GET, 'buscar', FILTER_SANITIZE_SPECIAL_CHARS) ?? ''),
            'orden' => $_GET['orden'] ?? 'nombre_asc'
        ];
        if ($filtros['categoria'] === 0) $filtros['categoria'] = null;

        $productos_listados = $modeloProducto->getProductosFiltrados($this->conn, $filtros);
        $categorias = $modeloCategoria->getTodasCategorias($this->conn);
        $total_productos = count($productos_listados);

        $id_categoria_filtro = $filtros['categoria'];
        $termino_busqueda = $filtros['buscar'];
        $orden = $filtros['orden'];
        $filtros_activos = ($id_categoria_filtro !== null || !empty($termino_busqueda));
        
        // --- FIN DE TU LÓGICA ---

        // Devolvemos todas las variables que la Vista necesita
        return [
            'productos_listados' => $productos_listados,
            'categorias' => $categorias,
            'total_productos' => $total_productos,
            'id_categoria_filtro' => $id_categoria_filtro,
            'termino_busqueda' => $termino_busqueda,
            'orden' => $orden,
            'filtros_activos' => $filtros_activos
        ];
    }
}
?>