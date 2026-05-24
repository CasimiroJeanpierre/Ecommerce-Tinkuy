<?php

/**
 * Modelo Reporte
 * Queries para generación de reportes administrativos
 * Tipos: Ventas, Productos, Vendedores
 */
class Reporte {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * REPORTE DE VENTAS
     * Resumen de ventas por período con detalles de pedidos
     */
    public function generarReporteVentas($fecha_inicio, $fecha_fin) {
        $query = "
            SELECT 
                DATE(pe.fecha_pedido) as fecha,
                pe.id_pedido,
                CONCAT(pf.nombres, ' ', pf.apellidos) as cliente,
                u.email as email_cliente,
                COUNT(DISTINCT dp.id_detalle) as items_pedido,
                SUM(dp.cantidad) as total_unidades,
                SUM(dp.cantidad * dp.precio_historico) as monto_total,
                COALESCE(t.metodo_pago, 'No registrado') as metodo_pago,
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM detalle_pedido 
                        WHERE id_pedido = pe.id_pedido 
                        AND id_estado_detalle = 4
                    ) THEN 'Completado'
                    WHEN EXISTS (
                        SELECT 1 FROM detalle_pedido 
                        WHERE id_pedido = pe.id_pedido 
                        AND id_estado_detalle = 3
                    ) THEN 'Enviado'
                    WHEN EXISTS (
                        SELECT 1 FROM detalle_pedido 
                        WHERE id_pedido = pe.id_pedido 
                        AND id_estado_detalle = 2
                    ) THEN 'Procesando'
                    ELSE 'Pendiente'
                END as estado_general
            FROM 
                pedidos pe
            INNER JOIN 
                usuarios u ON pe.id_usuario = u.id_usuario
            INNER JOIN 
                perfiles pf ON u.id_usuario = pf.id_usuario
            INNER JOIN 
                detalle_pedido dp ON pe.id_pedido = dp.id_pedido
            LEFT JOIN 
                transacciones t ON pe.id_pedido = t.id_pedido
            WHERE 
                DATE(pe.fecha_pedido) BETWEEN ? AND ?
            GROUP BY 
                pe.id_pedido, fecha, cliente, email_cliente, metodo_pago
            ORDER BY 
                pe.fecha_pedido DESC, pe.id_pedido DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calcular estadísticas agregadas
        $stats = $this->calcularEstadisticasVentas($resultado);
        
        return [
            'datos' => $resultado,
            'estadisticas' => $stats
        ];
    }
    
    /**
     * REPORTE DE PRODUCTOS
     * Análisis de productos: stock, ventas, rendimiento
     */
    public function generarReporteProductos($fecha_inicio, $fecha_fin) {
        $query = "
            SELECT 
                p.id_producto,
                p.nombre_producto,
                c.nombre_categoria,
                CONCAT(ven.usuario) as vendedor,
                COUNT(DISTINCT vp.id_variante) as total_variantes,
                SUM(vp.stock) as stock_total,
                COALESCE(SUM(dp.cantidad), 0) as unidades_vendidas,
                COALESCE(SUM(dp.cantidad * dp.precio_historico), 0) as ingresos_generados,
                CASE 
                    WHEN SUM(vp.stock) = 0 THEN 'Sin Stock'
                    WHEN SUM(vp.stock) < 10 THEN 'Stock Bajo'
                    WHEN SUM(vp.stock) < 50 THEN 'Stock Normal'
                    ELSE 'Stock Alto'
                END as estado_stock,
                p.estado as estado_producto,
                p.fecha_creacion
            FROM 
                productos p
            INNER JOIN 
                categorias c ON p.id_categoria = c.id_categoria
            INNER JOIN 
                usuarios ven ON p.id_vendedor = ven.id_usuario
            LEFT JOIN 
                variantes_producto vp ON p.id_producto = vp.id_producto
            LEFT JOIN 
                detalle_pedido dp ON vp.id_variante = dp.id_variante
            LEFT JOIN
                pedidos pe ON dp.id_pedido = pe.id_pedido 
                AND DATE(pe.fecha_pedido) BETWEEN ? AND ?
            GROUP BY 
                p.id_producto, p.nombre_producto, c.nombre_categoria, 
                vendedor, p.estado, p.fecha_creacion
            ORDER BY 
                unidades_vendidas DESC, p.nombre_producto ASC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calcular estadísticas
        $stats = $this->calcularEstadisticasProductos($resultado);
        
        return [
            'datos' => $resultado,
            'estadisticas' => $stats
        ];
    }
    
    /**
     * REPORTE DE VENDEDORES
     * Ranking y desempeño de vendedores
     */
    public function generarReporteVendedores($fecha_inicio, $fecha_fin) {
        $query = "
            SELECT 
                u.id_usuario as id_vendedor,
                u.usuario as nombre_usuario,
                CONCAT(pf.nombres, ' ', pf.apellidos) as nombre_completo,
                u.email,
                pf.telefono,
                COUNT(DISTINCT p.id_producto) as total_productos,
                COUNT(DISTINCT CASE WHEN p.estado = 'activo' THEN p.id_producto END) as productos_activos,
                COUNT(DISTINCT CASE WHEN p.estado = 'inactivo' THEN p.id_producto END) as productos_inactivos,
                COALESCE(SUM(dp.cantidad), 0) as unidades_vendidas,
                COALESCE(SUM(dp.cantidad * dp.precio_historico), 0) as ingresos_totales,
                COALESCE(AVG(dp.precio_historico), 0) as precio_promedio,
                COUNT(DISTINCT dp.id_pedido) as pedidos_procesados,
                COUNT(DISTINCT CASE 
                    WHEN dp.id_estado_detalle = 4 THEN dp.id_detalle 
                END) as entregas_completadas,
                CASE 
                    WHEN COUNT(dp.id_detalle) > 0 
                    THEN ROUND((COUNT(CASE WHEN dp.id_estado_detalle = 4 THEN 1 END) * 100.0 / COUNT(dp.id_detalle)), 2)
                    ELSE 0 
                END as tasa_entrega_pct,
                u.fecha_registro
            FROM 
                usuarios u
            INNER JOIN 
                perfiles pf ON u.id_usuario = pf.id_usuario
            LEFT JOIN 
                productos p ON u.id_usuario = p.id_vendedor
            LEFT JOIN 
                variantes_producto vp ON p.id_producto = vp.id_producto
            LEFT JOIN 
                detalle_pedido dp ON vp.id_variante = dp.id_variante
            LEFT JOIN 
                pedidos pe ON dp.id_pedido = pe.id_pedido 
                AND DATE(pe.fecha_pedido) BETWEEN ? AND ?
            WHERE 
                u.id_rol = 2
            GROUP BY 
                u.id_usuario, u.usuario, nombre_completo, u.email, 
                pf.telefono, u.fecha_registro
            ORDER BY 
                ingresos_totales DESC, unidades_vendidas DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
        $stmt->execute();
        $resultado = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calcular estadísticas
        $stats = $this->calcularEstadisticasVendedores($resultado);
        
        return [
            'datos' => $resultado,
            'estadisticas' => $stats
        ];
    }
    
    /**
     * Calcular estadísticas agregadas de ventas
     */
    private function calcularEstadisticasVentas($datos) {
        $total_pedidos = count($datos);
        $total_ingresos = array_sum(array_column($datos, 'monto_total'));
        $total_unidades = array_sum(array_column($datos, 'total_unidades'));
        $ticket_promedio = $total_pedidos > 0 ? $total_ingresos / $total_pedidos : 0;
        
        // Contar por método de pago
        $metodos_pago = [];
        foreach ($datos as $venta) {
            $metodo = $venta['metodo_pago'] ?? 'No especificado';
            if (!isset($metodos_pago[$metodo])) {
                $metodos_pago[$metodo] = 0;
            }
            $metodos_pago[$metodo]++;
        }
        
        // Contar por estado
        $estados = [];
        foreach ($datos as $venta) {
            $estado = $venta['estado_general'];
            if (!isset($estados[$estado])) {
                $estados[$estado] = 0;
            }
            $estados[$estado]++;
        }
        
        return [
            'total_pedidos' => $total_pedidos,
            'total_ingresos' => round($total_ingresos, 2),
            'total_unidades' => $total_unidades,
            'ticket_promedio' => round($ticket_promedio, 2),
            'metodos_pago' => $metodos_pago,
            'estados' => $estados
        ];
    }
    
    /**
     * Calcular estadísticas agregadas de productos
     */
    private function calcularEstadisticasProductos($datos) {
        $total_productos = count($datos);
        $stock_total = array_sum(array_column($datos, 'stock_total'));
        $unidades_vendidas = array_sum(array_column($datos, 'unidades_vendidas'));
        $ingresos_totales = array_sum(array_column($datos, 'ingresos_generados'));
        
        // Productos por estado de stock
        $por_stock = [
            'Sin Stock' => 0,
            'Stock Bajo' => 0,
            'Stock Normal' => 0,
            'Stock Alto' => 0
        ];
        
        foreach ($datos as $prod) {
            $estado = $prod['estado_stock'];
            if (isset($por_stock[$estado])) {
                $por_stock[$estado]++;
            }
        }
        
        // Top 5 productos
        $top_productos = array_slice($datos, 0, 5);
        
        return [
            'total_productos' => $total_productos,
            'stock_total' => $stock_total,
            'unidades_vendidas' => $unidades_vendidas,
            'ingresos_totales' => round($ingresos_totales, 2),
            'por_estado_stock' => $por_stock,
            'top_5_productos' => $top_productos
        ];
    }
    
    /**
     * Calcular estadísticas agregadas de vendedores
     */
    private function calcularEstadisticasVendedores($datos) {
        $total_vendedores = count($datos);
        $vendedores_activos = 0;
        $ingresos_totales = 0;
        $productos_totales = 0;
        
        foreach ($datos as $vendedor) {
            if ($vendedor['productos_activos'] > 0) {
                $vendedores_activos++;
            }
            $ingresos_totales += $vendedor['ingresos_totales'];
            $productos_totales += $vendedor['total_productos'];
        }
        
        $ingreso_promedio = $total_vendedores > 0 ? $ingresos_totales / $total_vendedores : 0;
        
        // Top 3 vendedores
        $top_vendedores = array_slice($datos, 0, 3);
        
        return [
            'total_vendedores' => $total_vendedores,
            'vendedores_activos' => $vendedores_activos,
            'ingresos_totales' => round($ingresos_totales, 2),
            'ingreso_promedio_vendedor' => round($ingreso_promedio, 2),
            'productos_totales' => $productos_totales,
            'top_3_vendedores' => $top_vendedores
        ];
    }
}
