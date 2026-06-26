<?php
require_once __DIR__ . '/../Models/Producto.php';

/**
 * Controlador de productos para el panel del vendedor.
 * Gestiona el listado, alta y edición de los productos propios del vendedor autenticado.
 * Delega la lógica de persistencia al modelo Producto y valida propiedad de cada producto
 * antes de permitir edición (un vendedor no puede editar productos de otro vendedor).
 *
 * Métodos disponibles:
 *   listar()               — Lista los productos del vendedor con variantes_json
 *   agregarProducto()      — Procesa el formulario POST de alta de nuevo producto
 *   editarProducto($id)    — Carga datos del producto y procesa actualización POST
 *   cambiarEstado($id)     — Activa o desactiva una variante sin eliminarla
 *
 * Seguridad:
 *   El constructor verifica sesión y rol 'vendedor'; redirige al login si no está autenticado.
 *   Todos los métodos de escritura verifican que id_vendedor coincida con $_SESSION['usuario_id'].
 */
class VendedorProductosController
{
    private $conn;
    private $producto;
    private $base_url;

    public function __construct()
    {
        global $conn;
        $this->conn = $conn;
        $this->producto = new Producto($conn);
        $this->base_url = defined('BASE_URL') ? BASE_URL : '/Ecommerce-Tinkuy/public/index.php';

        // Validar sesión y rol
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
     * Devuelve el listado de productos del vendedor con sus variantes y categorías.
     *
     * @return array{productos: array, categorias: array, nombre_vendedor: string, id_vendedor: int, base_url: string}
     */
    public function index()
    {
        $id_vendedor = $_SESSION['usuario_id'];
        $nombre_vendedor = $_SESSION['usuario'];

        // Obtener productos con sus variantes
        $productos = $this->producto->getProductosVendedor($id_vendedor);

        // Obtener categorías para el filtro
        $categorias = $this->producto->getCategorias();

        // Preparar datos para la vista
        return [
            'productos' => $productos,
            'categorias' => $categorias,
            'nombre_vendedor' => $nombre_vendedor,
            'id_vendedor' => $id_vendedor,
            'base_url' => $this->base_url
        ];
    }

    /**
     * Muestra el formulario de alta y procesa la creación del producto en POST.
     * Delega persistencia al modelo Producto (crearProducto + guardarImagen).
     *
     * @return array{categorias: array, mensaje_error: string, mensaje_exito: string, base_url: string}
     */
    public function agregarProducto()
    {
        $mensaje_error = "";
        $mensaje_exito = "";

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $nombre       = strip_tags(trim($_POST['nombre'] ?? ''));
                $descripcion  = strip_tags(trim($_POST['descripcion'] ?? ''));
                $id_categoria = filter_var($_POST['id_categoria'] ?? 0, FILTER_VALIDATE_INT);
                $variantes    = json_decode($_POST['variantes'] ?? '[]', true);

                if (empty($nombre) || empty($descripcion) || !$id_categoria || empty($variantes)) {
                    throw new \InvalidArgumentException("Todos los campos son obligatorios");
                }

                $id_producto = $this->producto->crearProducto([
                    'nombre'       => $nombre,
                    'descripcion'  => $descripcion,
                    'id_categoria' => $id_categoria,
                    'id_vendedor'  => $_SESSION['usuario_id'],
                    'variantes'    => $variantes
                ]);

                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                    $this->producto->guardarImagen($id_producto, $_FILES['imagen']);
                }

                $mensaje_exito = "Producto agregado exitosamente";

            } catch (\InvalidArgumentException $e) {
                $mensaje_error = $e->getMessage();
            } catch (Exception $e) {
                $mensaje_error = "Error al agregar el producto: " . $e->getMessage();
            }
        }

        // Obtener categorías para el formulario
        $categorias = $this->producto->getCategorias();

        return [
            'categorias' => $categorias,
            'mensaje_error' => $mensaje_error,
            'mensaje_exito' => $mensaje_exito,
            'base_url' => $this->base_url
        ];
    }

    /**
     * Muestra y procesa el formulario de edición de un producto existente.
     * Verifica propiedad antes de procesar (anti-IDOR): redirige si el producto
     * no existe o no pertenece al vendedor autenticado.
     *
     * @param int $id_producto ID del producto a editar
     * @return array{producto: array, categorias: array, mensaje_error: string, mensaje_exito: string, base_url: string}
     */
    public function editarProducto($id_producto)
    {
        $mensaje_error = "";
        $mensaje_exito = "";

        // Verificar propiedad del producto
        $producto = $this->producto->getProducto($id_producto, $_SESSION['usuario_id']);
        if (!$producto) {
            header("Location: " . $this->base_url . "?page=vendedor_productos&error=no_encontrado");
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $nombre       = trim($_POST['nombre'] ?? '');
                $descripcion  = trim($_POST['descripcion'] ?? '');
                $id_categoria = filter_var($_POST['id_categoria'] ?? 0, FILTER_VALIDATE_INT);
                $variantes    = json_decode($_POST['variantes'] ?? '[]', true);

                if (empty($nombre) || empty($descripcion) || !$id_categoria || empty($variantes)) {
                    throw new \InvalidArgumentException("Todos los campos son obligatorios");
                }

                $this->producto->actualizarProducto($id_producto, [
                    'nombre'       => $nombre,
                    'descripcion'  => $descripcion,
                    'id_categoria' => $id_categoria,
                    'variantes'    => $variantes
                ]);

                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                    $this->producto->actualizarImagen($id_producto, $_FILES['imagen']);
                }

                $mensaje_exito = "Producto actualizado exitosamente";
                $producto = $this->producto->getProducto($id_producto, $_SESSION['usuario_id']);

            } catch (\InvalidArgumentException $e) {
                $mensaje_error = $e->getMessage();
            } catch (Exception $e) {
                $mensaje_error = "Error al actualizar el producto: " . $e->getMessage();
            }
        }

        $categorias = $this->producto->getCategorias();

        return [
            'producto' => $producto,
            'categorias' => $categorias,
            'mensaje_error' => $mensaje_error,
            'mensaje_exito' => $mensaje_exito,
            'base_url' => $this->base_url
        ];
    }

    /**
     * Cambia el estado (activo/inactivo) del producto, verificando propiedad del vendedor.
     *
     * @param int    $id_producto  ID del producto a modificar
     * @param string $nuevo_estado 'activo' o 'inactivo'
     * @return array{success: bool, message: string}
     */
    public function cambiarEstado($id_producto, $nuevo_estado)
    {
        try {
            if ($this->producto->cambiarEstado($id_producto, $_SESSION['usuario_id'], $nuevo_estado)) {
                return ['success' => true, 'message' => 'Estado actualizado correctamente'];
            }
            return ['success' => false, 'message' => 'No se pudo actualizar el estado'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}