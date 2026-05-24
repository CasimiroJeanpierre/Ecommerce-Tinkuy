<?php
require_once __DIR__ . '/../Models/Producto.php';

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

    public function agregarProducto()
    {
        $mensaje_error = "";
        $mensaje_exito = "";

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = strip_tags(trim($_POST['nombre'] ?? ''));
            $descripcion = strip_tags(trim($_POST['descripcion'] ?? ''));
            $nombre = strip_tags(trim($_POST['nombre'] ?? ''));
            $descripcion = strip_tags(trim($_POST['descripcion'] ?? ''));
            $id_categoria = filter_var($_POST['id_categoria'] ?? 0, FILTER_VALIDATE_INT);
            $variantes = json_decode($_POST['variantes'] ?? '[]', true);

            // Validar datos
            if (empty($nombre) || empty($descripcion) || !$id_categoria || empty($variantes)) {
                $mensaje_error = "Todos los campos son obligatorios";
            } else {
                try {
                    $id_producto = $this->producto->crearProducto([
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'id_categoria' => $id_categoria,
                        'id_vendedor' => $_SESSION['usuario_id'],
                        'variantes' => $variantes
                    ]);

                    // Procesar imagen
                    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                        $this->producto->guardarImagen($id_producto, $_FILES['imagen']);
                    }

                    $mensaje_exito = "Producto agregado exitosamente";

                } catch (Exception $e) {
                    $mensaje_error = "Error al agregar el producto: " . $e->getMessage();
                }
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
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $id_categoria = filter_var($_POST['id_categoria'] ?? 0, FILTER_VALIDATE_INT);
            $variantes = json_decode($_POST['variantes'] ?? '[]', true);

            if (empty($nombre) || empty($descripcion) || !$id_categoria || empty($variantes)) {
                $mensaje_error = "Todos los campos son obligatorios";
            } else {
                try {
                    $this->producto->actualizarProducto($id_producto, [
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'id_categoria' => $id_categoria,
                        'variantes' => $variantes
                    ]);

                    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
                        $this->producto->actualizarImagen($id_producto, $_FILES['imagen']);
                    }

                    $mensaje_exito = "Producto actualizado exitosamente";
                    $producto = $this->producto->getProducto($id_producto, $_SESSION['usuario_id']);

                } catch (Exception $e) {
                    $mensaje_error = "Error al actualizar el producto: " . $e->getMessage();
                }
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