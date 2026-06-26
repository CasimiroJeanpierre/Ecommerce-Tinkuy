<?php

/**
 * Modelo de reportes administrativos.
 * Encapsula las consultas SQL de análisis para los tres tipos de reportes
 * disponibles en el panel de administración.
 *
 * Métodos disponibles:
 *   generarReporteVentas($fecha_inicio, $fecha_fin)    — Pedidos y totales en el rango de fechas
 *   generarReporteProductos($fecha_inicio, $fecha_fin) — Productos más vendidos con ingresos
 *   generarReporteVendedores($fecha_inicio, $fecha_fin) — Rendimiento por vendedor
 *
 * Los reportes filtran por fecha_pedido en la tabla 'pedidos' y solo incluyen
 * pedidos con estado diferente a 'Cancelado' para reflejar ingresos reales.
 *
 * Uso desde ReportesController::generar():
 *   $reporte = new Reporte($conn);
 *   $datos = $reporte->generarReporteVentas('2026-01-01', '2026-06-30');
 */
class Reporte {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Genera el reporte de ventas para un período dado.
     * Incluye cabecera de pedido, cliente, método de pago y estado derivado
     * de los ítems de detalle_pedido dentro del rango de fechas.
     *
     * @param string $fecha_inicio Fecha de inicio en formato YYYY-MM-DD
     * @param string $fecha_fin    Fecha de fin en formato YYYY-MM-DD
     * @return array{datos: array<int, array<string, mixed>>, estadisticas: array<string, mixed>}
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
     * Genera el reporte de productos con métricas de stock, ventas e ingresos para el período dado.
     * Usa LEFT JOIN a detalle_pedido y pedidos con filtro de fecha para contar solo ventas del período,
     * sin excluir productos con 0 ventas (LEFT JOIN vs INNER JOIN).
     * La clasificación de stock se calcula en SQL con CASE: Sin Stock (0), Bajo (<10), Normal (<50), Alto (≥50).
     * Los ingresos_generados excluyen devoluciones (no hay filtro de estado de pedido actualmente).
     *
     * Llama a calcularEstadisticasProductos() para agregar totales y top-5.
     *
     * @param string $fecha_inicio Fecha de inicio en formato YYYY-MM-DD (inclusive)
     * @param string $fecha_fin    Fecha de fin en formato YYYY-MM-DD (inclusive)
     * @return array{datos: array<int, array<string, mixed>>, estadisticas: array<string, mixed>}
     *         estadisticas incluye: total_productos, stock_total, unidades_vendidas, ingresos_totales,
     *         por_estado_stock (array), top_5_productos (array)
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
     * Genera el reporte de rendimiento de vendedores para el período indicado.
     * Incluye todos los usuarios con id_rol = 2 (vendedores), tengan ventas o no en el período.
     * La tasa_entrega_pct = (ítems con estado 4 / total ítems en el período) × 100.
     * productos_activos y productos_inactivos se cuentan sin filtro de fecha.
     * ingresos_totales y unidades_vendidas se filtran por fecha_pedido del período.
     * El resultado se ordena: mayor ingreso total primero, luego mayor cantidad vendida.
     *
     * Llama a calcularEstadisticasVendedores() para agregar totales globales del ranking.
     *
     * @param string $fecha_inicio Fecha de inicio en formato YYYY-MM-DD (inclusive)
     * @param string $fecha_fin    Fecha de fin en formato YYYY-MM-DD (inclusive)
     * @return array{datos: array<int, array<string, mixed>>, estadisticas: array<string, mixed>}
     *         estadisticas incluye: total_vendedores, total_ingresos, promedio_ingresos,
     *         top_vendedor (array), promedio_tasa_entrega (float)
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
     * Calcula métricas consolidadas de ventas a partir del array de datos ya obtenido
     * de generarReporteVentas(): total de pedidos, ingresos totales, unidades vendidas,
     * ticket promedio y distribuciones de conteo por método de pago y estado de pedido.
     * El ticket_promedio se calcula como total_ingresos / total_pedidos; si total_pedidos
     * es 0 (sin datos en el período) devuelve 0 para evitar división por cero.
     * Las distribuciones metodos_pago y estados son arrays asociativos [valor => conteo].
     * Los valores null en metodo_pago se normalizan a la cadena 'No especificado'.
     *
     * @param array<int, array<string, mixed>> $datos Filas del reporte de ventas (generarReporteVentas)
     * @return array{total_pedidos: int, total_ingresos: float, total_unidades: int,
     *               ticket_promedio: float, metodos_pago: array<string, int>, estados: array<string, int>}
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
     * Calcula métricas consolidadas de productos a partir del array de datos ya obtenido
     * de generarReporteProductos(): total de productos, stock total, unidades vendidas,
     * ingresos totales y distribución de conteo por estado de stock (Sin Stock / Bajo / Normal / Alto).
     * Los ingresos se redondean a 2 decimales para presentación en el panel de reportes.
     * La distribución por_estado_stock usa las 4 claves fijas del CASE SQL de generarReporteProductos;
     * las claves siempre están presentes (con valor 0 si no hay productos en ese estado).
     * top_5_productos son las primeras 5 filas del resultado original, que ya viene ordenado
     * por unidades_vendidas DESC desde la consulta SQL.
     *
     * @param array<int, array<string, mixed>> $datos Filas del reporte de productos (generarReporteProductos)
     * @return array{total_productos: int, stock_total: int, unidades_vendidas: int,
     *               ingresos_totales: float, por_estado_stock: array<string, int>, top_5_productos: array}
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
     * Calcula totales de ingresos, vendedores activos y top 3 por ingresos
     * a partir del array ya obtenido de generarReporteVendedores.
     *
     * @param array<int, array<string, mixed>> $datos Filas de reporte de vendedores
     * @return array{total_vendedores: int, vendedores_activos: int, ingresos_totales: float, ingreso_promedio_vendedor: float, productos_totales: int, top_3_vendedores: array}
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
