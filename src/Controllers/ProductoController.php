<?php
// src/Controllers/ProductoController.php

class ProductoController {

    private $conn;

    // El constructor recibe la conexión a la BBDD
    public function __construct($db_connection) {
        $this->conn = $db_connection;
    }

    /**
     * Muestra la página del catálogo (lista de productos)
     * y maneja la lógica de filtros.
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