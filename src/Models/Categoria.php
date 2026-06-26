<?php

/**
 * Modelo de categorías de productos.
 * Proporciona métodos de consulta a la tabla 'categorias' para poblar
 * filtros, selectores de formulario y el árbol jerárquico de categorías.
 *
 * Métodos disponibles:
 *   getTodasCategorias($conn)     — Lista todas las categorías con id, nombre y padre
 *   getCategoriaJerarquia($conn)  — Devuelve el árbol (padre → hijos) para selectores anidados
 */
class Categoria
{
    /**
     * Obtiene todas las categorías de la tabla 'categorias' ordenadas por jerarquía y nombre.
     * Incluye categorías raíz (id_categoria_padre IS NULL) y subcategorías en el mismo resultado.
     * El ORDER BY id_categoria_padre ASC, nombre_categoria ASC pone las categorías padre primero
     * (NULL < número) y dentro de cada nivel las ordena alfabéticamente.
     * Usado por los formularios de alta/edición de producto para poblar el <select> de categorías
     * y por el catálogo de productos para construir el árbol de filtros de navegación.
     * Si la consulta falla por error de BD, registra el error con error_log y retorna array vacío
     * para que la vista pueda degradarse (selector vacío) sin lanzar excepción al usuario.
     *
     * @param mysqli $conn Conexión activa a la base de datos
     * @return array<int, array{id_categoria: int, nombre_categoria: string, id_categoria_padre: int|null}>
     *         Array ordenado de categorías; vacío si la consulta falla
     */
    public function getTodasCategorias($conn)
    {
        $categorias = [];
        // Esta consulta obtiene padres e hijas ordenados
        $sql = "SELECT id_categoria, nombre_categoria, id_categoria_padre 
                FROM categorias 
                ORDER BY id_categoria_padre ASC, nombre_categoria ASC";
        
        $resultado = $conn->query($sql);
        
        if ($resultado) {
            while ($fila = $resultado->fetch_assoc()) {
                $categorias[] = $fila;
            }
        } else {
            error_log("Error al consultar categorías: ". $conn->error);
        }
        
        return $categorias;
    }
}