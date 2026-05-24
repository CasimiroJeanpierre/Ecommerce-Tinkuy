<?php
// src/Models/Categoria.php

class Categoria
{
    /**
     * Obtiene todas las categorías para los filtros.
     * @param mysqli $conn La conexión a la base de datos.
     * @return array
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