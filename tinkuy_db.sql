-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3307
-- Tiempo de generación: 23-05-2026 a las 05:35:28
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `tinkuy_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones`
--

CREATE TABLE `calificaciones` (
  `id_calificacion` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `puntuacion` int(11) NOT NULL CHECK (`puntuacion` >= 1 and `puntuacion` <= 5),
  `comentario` text DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `calificaciones`
--

INSERT INTO `calificaciones` (`id_calificacion`, `id_producto`, `id_usuario`, `puntuacion`, `comentario`, `fecha`) VALUES
(1, 1, 3, 5, '¡Me encantó la chompa! Súper suave y el color rojo es muy vivo.', '2025-10-22 20:53:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_categoria_padre` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre_categoria`, `descripcion`, `id_categoria_padre`) VALUES
(4, 'Ropa y Vestimenta', 'Prendas de vestir artesanales para hombres, mujeres y niños', NULL),
(5, 'Calzado', 'Zapatos, zapatillas y sandalias hechas a mano', NULL),
(6, 'Accesorios de Moda', 'Complementos para tu atuendo diario', NULL),
(7, 'Hogar y Decoración', 'Artículos para decorar los espacios de tu casa', NULL),
(8, 'Arte y Artesanía', 'Piezas de arte, coleccionables y tradición', NULL),
(9, 'Chompas y Suéteres', 'Tejidos de alpaca, lana de oveja y otros', 4),
(10, 'Ponchos y Ruanas', 'Abrigos tradicionales andinos', 4),
(11, 'Vestidos y Blusas', 'Prendas bordadas, teñidas y tejidas a mano', 4),
(12, 'Pantalones y Polleras', 'Partes bajas con diseño artesanal', 4),
(13, 'Ropa para Niños', 'Prendas en tallas infantiles', 4),
(14, 'Zapatillas', 'Zapatillas con bordados o diseños únicos', 5),
(15, 'Zapatos y Botines', 'Calzado de cuero y otros materiales resistentes', 5),
(16, 'Sandalias y Ojotas', 'Calzado abierto artesanal', 5),
(17, 'Gorros, Chullos y Sombreros', 'Para cubrir la cabeza con estilo tradicional', 6),
(18, 'Bolsos y Carteras', 'Bolsos, mochilas y carteras de tela, cuero o paja', 6),
(19, 'Joyas y Bisutería', 'Aretes, collares, pulseras de materiales locales', 6),
(20, 'Bufandas y Chalinas', 'Tejidos para el cuello', 6),
(21, 'Cinturones y Correas', 'Hechos en cuero o telar', 6),
(22, 'Telas y Textiles', 'Telas teñidas a mano, tapices y diseños por metro', 7),
(23, 'Mantas y Frazadas', 'Para cama, sofá o decoración', 7),
(24, 'Alfombras y Tapices', 'Tejidos para el suelo o pared', 7),
(25, 'Cerámica y Alfarería', 'Platos, tazas, vasijas y adornos de barro', 7),
(26, 'Cojines y Fundas', 'Fundas bordadas y tejidas', 7),
(27, 'Pinturas y Retablos', 'Arte visual y retablos ayacuchanos', 8),
(28, 'Esculturas y Tallas', 'Trabajos en madera, piedra o arcilla', 8),
(29, 'Instrumentos Musicales', 'Zampoñas, quenas, charangos y más', 8),
(30, 'Guantes y Mitones', 'Guantes y mitones tejidos de lana, alpaca y más', 6),
(31, 'Chompas', 'Chompas artesanales de alpaca', NULL),
(32, 'Accesorios', 'Accesorios tejidos a mano', NULL),
(33, 'Textiles', 'Textiles y mantas tradicionales', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_pedido`
--

CREATE TABLE `detalle_pedido` (
  `id_detalle` int(11) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `id_variante` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_historico` decimal(10,2) NOT NULL,
  `id_empresa_envio` int(11) DEFAULT NULL,
  `numero_seguimiento` varchar(100) DEFAULT NULL,
  `id_estado_detalle` int(11) NOT NULL DEFAULT 2 COMMENT 'Refleja el estado del item, no del pedido. (2=Pagado, 3=Enviado, 4=Entregado)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_pedido`
--

INSERT INTO `detalle_pedido` (`id_detalle`, `id_pedido`, `id_variante`, `cantidad`, `precio_historico`, `id_empresa_envio`, `numero_seguimiento`, `id_estado_detalle`) VALUES
(1, 1, 1, 1, 150.00, 3, '1', 3),
(2, 1, 3, 1, 80.00, 1, '1', 3),
(3, 2, 2, 1, 150.00, 3, '1', 3),
(4, 3, 2, 1, 150.00, NULL, NULL, 2),
(5, 4, 2, 1, 150.00, NULL, NULL, 2),
(6, 5, 4, 1, 45.00, NULL, NULL, 2),
(7, 5, 5, 1, 20.00, NULL, NULL, 2),
(8, 6, 6, 1, 10.00, NULL, NULL, 2),
(9, 7, 1, 1, 150.00, NULL, NULL, 2),
(10, 8, 4, 1, 45.00, NULL, NULL, 2),
(11, 9, 2, 1, 150.00, NULL, NULL, 2),
(12, 10, 7, 1, 150.00, NULL, NULL, 2),
(13, 10, 10, 1, 35.00, NULL, NULL, 2),
(14, 11, 4, 1, 45.00, NULL, NULL, 2),
(15, 12, 5, 1, 20.00, NULL, NULL, 2),
(16, 13, 2, 1, 150.00, NULL, NULL, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `direcciones`
--

CREATE TABLE `direcciones` (
  `id_direccion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `ciudad` varchar(100) NOT NULL,
  `pais` varchar(100) NOT NULL,
  `codigo_postal` varchar(20) DEFAULT NULL,
  `es_principal` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `direcciones`
--

INSERT INTO `direcciones` (`id_direccion`, `id_usuario`, `direccion`, `ciudad`, `pais`, `codigo_postal`, `es_principal`) VALUES
(1, 3, 'Av. Arequipa 1234, Miraflores', 'Lima', 'Perú', '1500', 1),
(2, 3, 'Jr. Cusco 567, Centro', 'Cusco', 'Perú', '0800', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas_envio`
--

CREATE TABLE `empresas_envio` (
  `id_empresa_envio` int(11) NOT NULL,
  `nombre_empresa` varchar(100) NOT NULL,
  `sitio_web` varchar(255) DEFAULT NULL,
  `tracking_url_base` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `empresas_envio`
--

INSERT INTO `empresas_envio` (`id_empresa_envio`, `nombre_empresa`, `sitio_web`, `tracking_url_base`) VALUES
(1, 'Olva Courier', 'https://www.olvacourier.com/', 'https://www.olvacourier.com/seguimiento-envio/?numero_envio='),
(2, 'Urbano', 'https://www.urbano.com.pe/', NULL),
(3, 'Shalom', 'https://shalom.com.pe/', 'https://rastrea.shalom.pe/'),
(4, 'Olva Courier Test', NULL, NULL),
(5, 'Shalom Test', NULL, NULL),
(6, 'Serpost Test', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_pedido`
--

CREATE TABLE `estados_pedido` (
  `id_estado` int(11) NOT NULL,
  `nombre_estado` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estados_pedido`
--

INSERT INTO `estados_pedido` (`id_estado`, `nombre_estado`) VALUES
(5, 'Cancelado'),
(4, 'Entregado'),
(3, 'Enviado'),
(2, 'Pagado'),
(1, 'Pendiente de Pago');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `login_intentos`
--

CREATE TABLE `login_intentos` (
  `id` int(11) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `exitoso` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_intento` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Registro de intentos de login para protección anti fuerza bruta';

--
-- Volcado de datos para la tabla `login_intentos`
--

INSERT INTO `login_intentos` (`id`, `ip`, `usuario`, `exitoso`, `fecha_intento`) VALUES
(1, '::1', 'Jeanpierre', 1, '2026-05-11 20:10:53'),
(2, '::1', 'Jeanpierre', 1, '2026-05-11 20:14:22'),
(3, '::1', 'diego', 1, '2026-05-11 21:23:52'),
(4, '::1', 'crisdel', 1, '2026-05-11 21:27:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes_contacto`
--

CREATE TABLE `mensajes_contacto` (
  `id_mensaje` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `asunto` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `fecha_envio` datetime DEFAULT current_timestamp(),
  `leido` tinyint(1) DEFAULT 0,
  `estado` enum('pendiente','respondido','archivado') NOT NULL DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mensajes_contacto`
--

INSERT INTO `mensajes_contacto` (`id_mensaje`, `nombre`, `email`, `asunto`, `mensaje`, `fecha_envio`, `leido`, `estado`) VALUES
(1, 'elias j', 'user@gmail.com', 'hola', 'hola aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '2025-11-05 06:33:51', 1, 'respondido'),
(2, 'Juan', 'n00334240@upn.pe', 'Duda', 'Prueba de mensajes', '2025-12-05 12:06:11', 0, 'pendiente'),
(3, 'Juan Perez', 'test@example.com', 'Consulta producto', 'Hola, quisiera más información sobre este producto.', '2025-12-05 15:57:48', 0, 'pendiente'),
(4, 'Juan Perez', 'test@example.com', 'Consulta producto', 'Hola, quisiera más información sobre este producto.', '2025-12-06 09:32:17', 0, 'pendiente'),
(5, 'Juan Perez', 'test@example.com', 'Consulta producto', 'Hola, quisiera más información sobre este producto.', '2025-12-06 09:34:44', 0, 'pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expiracion` datetime NOT NULL,
  `creado_en` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `password_resets`
--


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id_pedido` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_direccion_envio` int(11) NOT NULL,
  `id_estado_pedido` int(11) NOT NULL,
  `fecha_pedido` datetime DEFAULT current_timestamp(),
  `total_pedido` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id_pedido`, `id_usuario`, `id_direccion_envio`, `id_estado_pedido`, `fecha_pedido`, `total_pedido`) VALUES
(1, 3, 1, 2, '2025-10-22 20:53:10', 230.00),
(2, 3, 1, 2, '2025-10-23 16:13:08', 150.00),
(3, 3, 1, 2, '2025-10-23 16:25:22', 150.00),
(4, 3, 1, 2, '2025-10-23 16:35:37', 150.00),
(5, 3, 1, 2, '2025-11-05 09:29:19', 65.00),
(6, 3, 1, 2, '2025-11-05 09:30:53', 10.00),
(7, 3, 1, 2, '2025-11-05 09:35:06', 150.00),
(8, 3, 1, 2, '2025-11-05 09:35:48', 45.00),
(9, 3, 1, 2, '2025-11-05 09:40:04', 150.00),
(10, 3, 1, 2, '2025-11-15 03:43:02', 185.00),
(11, 3, 1, 2, '2025-11-15 05:28:20', 45.00),
(12, 3, 1, 2, '2025-11-15 05:36:55', 20.00),
(13, 3, 1, 2, '2025-11-15 07:28:03', 150.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `perfiles`
--

CREATE TABLE `perfiles` (
  `id_perfil` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombres` varchar(100) DEFAULT NULL,
  `apellidos` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `perfiles`
--

INSERT INTO `perfiles` (`id_perfil`, `id_usuario`, `nombres`, `apellidos`, `telefono`) VALUES
(1, 1, 'Admin', 'Test', '987654321'),
(2, 2, 'Vendedor', 'Test', '987654322'),
(3, 3, 'Cliente', 'Test', '987654323'),
(4, 4, 'Jeanpierre Amilcar', 'Casimiro Guerra', '994220530'),
(5, 5, 'Crisdel Aldemir', 'Gonzales Canales', NULL),
(6, 6, 'Diego Anderson', 'Becerra Burga', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `id_vendedor` int(11) NOT NULL,
  `nombre_producto` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen_principal` varchar(255) DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `id_categoria`, `id_vendedor`, `nombre_producto`, `descripcion`, `imagen_principal`, `estado`, `fecha_creacion`) VALUES
(1, 9, 2, 'Chompa de Alpaca Clásica', 'Chompa suave de 100% alpaca, perfecta para el invierno.', 'chompa-artesanal1.png', 'activo', '2025-10-22 20:53:09'),
(2, 22, 2, 'Tela Andina \"Inti\"', 'Tela de 1.5m x 1m con diseños solares, teñido natural.', 'tela.jpeg\r\n', 'inactivo', '2025-10-22 20:53:09'),
(3, 17, 2, 'Gorro Chullo Tradicional', 'Gorro chullo con orejeras, tejido a mano.', 'gorro-artesanal-unixes.png', 'activo', '2025-10-22 20:53:09'),
(4, 30, 2, 'Guantes de lana', 'Guantes de lana de oveja', 'prod_68fadded17d71.png', 'activo', '2025-10-23 21:01:17'),
(5, 31, 2, 'Chompa de Alpaca Premium', 'Chompa tejida a mano con lana de alpaca 100% natural. Diseño tradicional andino.', 'chompa_alpaca_1.jpg', 'inactivo', '2025-11-15 03:43:02'),
(6, 32, 2, 'Gorro Andino', 'Gorro tejido con diseños geométricos tradicionales. Protección contra el frío.', 'gorro_andino_1.jpg', 'inactivo', '2025-11-15 03:43:02'),
(7, 33, 2, 'Manta Cusqueña', 'Manta artesanal de Cusco con tintes naturales. Ideal para decoración.', 'manta_cusco_1.jpg', 'inactivo', '2025-11-15 03:43:02'),
(8, 16, 2, 'Ojotas', 'suela gruesa de neumático o llanta reciclado, con tiras del mismo material para sujetar el pie.', 'producto_1764429190.png', 'activo', '2025-11-29 10:13:10'),
(9, 10, 2, 'Poncho Artesanal Corto', 'Poncho en colores surtidos, elaborado 100% en algodón, tamaño 120*72 cm, 240 gr', 'producto_1764430653.jpg', 'activo', '2025-11-29 10:37:33'),
(10, 29, 2, 'Quena', '', 'producto_1764437053.png', 'activo', '2025-11-29 12:24:13'),
(11, 21, 2, 'Cinturón Mujer, Bordado a Mano en Perú, Lana Artesanal', '', 'producto_1764437719.png', 'activo', '2025-11-29 12:35:19'),
(12, 29, 2, 'Zampolla', '', 'producto_1764439324.png', 'activo', '2025-11-29 13:02:04'),
(13, 20, 2, 'Chalina', '', 'producto_1764464104.png', 'activo', '2025-11-29 19:55:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_imagenes`
--

CREATE TABLE `producto_imagenes` (
  `id_imagen` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `ruta_imagen` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(1, 'admin'),
(3, 'cliente'),
(2, 'vendedor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarjetas_usuario`
--

CREATE TABLE `tarjetas_usuario` (
  `id_tarjeta` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombre_tarjeta` varchar(100) NOT NULL,
  `ultimos_4_digitos` varchar(4) NOT NULL,
  `expiracion` varchar(5) NOT NULL,
  `tipo` varchar(20) DEFAULT 'Visa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tarjetas_usuario`
--

INSERT INTO `tarjetas_usuario` (`id_tarjeta`, `id_usuario`, `nombre_tarjeta`, `ultimos_4_digitos`, `expiracion`, `tipo`) VALUES
(1, 3, 'Cliente Comprador', '4444', '12/28', 'Visa'),
(2, 3, 'Cliente Comprador', '5555', '06/27', 'Mastercard');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transacciones`
--

CREATE TABLE `transacciones` (
  `id_transaccion` int(11) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `metodo_pago` varchar(50) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `id_externo_gateway` varchar(255) DEFAULT NULL,
  `estado_pago` enum('exitoso','fallido','pendiente') NOT NULL,
  `fecha_transaccion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `transacciones`
--

INSERT INTO `transacciones` (`id_transaccion`, `id_pedido`, `metodo_pago`, `monto`, `id_externo_gateway`, `estado_pago`, `fecha_transaccion`) VALUES
(1, 1, 'Tarjeta de Crédito', 230.00, 'txn_12345ABCDE', 'exitoso', '2025-10-22 20:53:10'),
(2, 2, 'Tarjeta (Simulada)', 150.00, 'txn_simulado_68fa9a649211c', 'exitoso', '2025-10-23 16:13:08'),
(3, 3, 'Tarjeta (Simulada)', 150.00, 'txn_simulado_68fa9d42a1249', 'exitoso', '2025-10-23 16:25:22'),
(4, 4, 'Tarjeta (Simulada)', 150.00, 'txn_simulado_68fa9fa9e226a', 'exitoso', '2025-10-23 16:35:37'),
(5, 5, 'Tarjeta (Simulada)', 65.00, 'txn_84b2357701ca57c2c8df139c2ace1ee2', 'exitoso', '2025-11-05 09:29:19'),
(6, 6, 'Tarjeta (Simulada)', 10.00, 'txn_f50b155df5c6b7a697a4dee9ccd2d26a', 'exitoso', '2025-11-05 09:30:53'),
(7, 7, 'Tarjeta (Simulada)', 150.00, 'txn_4f121973bb88d09e4301ee92d985d4ef', 'exitoso', '2025-11-05 09:35:06'),
(8, 8, 'Tarjeta (Simulada)', 45.00, 'txn_5767c54a6d1ffa117b4fe9d34622425e', 'exitoso', '2025-11-05 09:35:48'),
(9, 9, 'Tarjeta (Simulada)', 150.00, 'txn_489ee7bf288f5836900c3230f053a657', 'exitoso', '2025-11-05 09:40:04'),
(10, 10, 'Tarjeta (Simulada)', 185.00, 'txn_test_abc123def456', 'exitoso', '2025-11-15 03:43:02'),
(11, 11, 'Tarjeta (Simulada)', 45.00, 'txn_335cbd6ea5fa8faada906d2ff4c925dc', 'exitoso', '2025-11-15 05:28:20'),
(12, 12, 'Tarjeta (Simulada)', 20.00, 'txn_be9736911f9219307d7c5df29a1ec6c9', 'exitoso', '2025-11-15 05:36:55'),
(13, 13, 'Tarjeta (Simulada)', 150.00, 'txn_4ba3cdc74f27ada5d795cd06d6e27f3d', 'exitoso', '2025-11-15 07:28:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `estado` varchar(10) NOT NULL DEFAULT 'activo',
  `usuario` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `clave_hash` varchar(255) NOT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `id_rol`, `estado`, `usuario`, `email`, `clave_hash`, `fecha_registro`) VALUES
(1, 1, 'activo', 'admin_test', 'admin.test@tinkuy.com', '$2y$10$vZL5gP8Z5qX5Z5qX5qX5qO.K5qX5qX5qX5qX5qX5qX5qX5qX5qX5q', '2025-11-15 03:43:02'),
(2, 2, 'activo', 'vendedor_test', 'vendedor.test@tinkuy.com', '$2y$10$vZL5gP8Z5qX5Z5qX5qX5qO.K5qX5qX5qX5qX5qX5qX5qX5qX5qX5q', '2025-11-15 03:43:02'),
(3, 3, 'activo', 'cliente_test', 'cliente.test@tinkuy.com', '$2y$10$vZL5gP8Z5qX5Z5qX5qX5qO.K5qX5qX5qX5qX5qX5qX5qX5qX5qX5q', '2025-11-15 03:43:02'),
(4, 1, 'activo', 'Jeanpierre', 'casimirom543@gmail.com', '$2y$10$TVLxltApZjOFQaf4gQwONOxhVFqxLqPAIqin5kEH420AexaMPg1.u', '2026-05-11 19:49:31'),
(5, 2, 'activo', 'crisdel', 'crisdel@gmail.com', '$2y$10$R8EO.gknoiZnhHlI8hbt9.P8ucpTo1MQqlHBRoxs71pVvNrqxth.e', '2026-05-11 19:52:21'),
(6, 3, 'activo', 'diego', 'diego@gmail.com', '$2y$10$G.jnjQvFyCySMbQOy6bo3.UxcT9mWoohpcAeJ/bHe.lkprg5O/nPS', '2026-05-11 19:53:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `variantes_producto`
--

CREATE TABLE `variantes_producto` (
  `id_variante` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `talla` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `imagen_variante` varchar(255) DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `variantes_producto`
--

INSERT INTO `variantes_producto` (`id_variante`, `id_producto`, `talla`, `color`, `sku`, `precio`, `stock`, `imagen_variante`, `estado`) VALUES
(1, 1, 'M', 'Rojo', 'CHOMPA-M-ROJO', 150.00, 10, 'chompa-artesanal2.png', 'activo'),
(2, 1, 'L', 'Azul', 'CHOMPA-L-AZUL', 150.00, 9, 'chompa-artesanal3.png', 'activo'),
(3, 2, 'Único', 'Multicolor', 'TELA-INTI', 80.00, 20, 'tela.jpeg', 'activo'),
(4, 3, 'Único', 'Gris', 'GORRO-GRIS', 45.00, 12, 'gorro-artesanal-unixes.png', 'activo'),
(5, 4, 's', 'gris', 'GUA-4-s-gris', 20.00, 9, NULL, 'activo'),
(6, 4, 's', 'rojo', 'GUA-4-s-rojo', 10.00, 10, 'variante_4_1761278451.png', 'activo'),
(7, 5, 'S', 'Rojo', NULL, 150.00, 5, 'chompa_alpaca_rojo_s.jpg', 'activo'),
(8, 5, 'M', 'Rojo', NULL, 150.00, 8, 'chompa_alpaca_rojo_m.jpg', 'activo'),
(9, 5, 'L', 'Azul', NULL, 150.00, 7, 'chompa_alpaca_azul_l.jpg', 'activo'),
(10, 6, 'Única', 'Multicolor', NULL, 35.00, 25, 'gorro_multicolor.jpg', 'activo'),
(11, 6, 'Única', 'Verde', NULL, 35.00, 25, 'gorro_verde.jpg', 'activo'),
(12, 7, 'Grande', 'Natural', NULL, 80.00, 8, 'manta_natural_g.jpg', 'activo'),
(13, 7, 'Pequeña', 'Natural', NULL, 60.00, 7, 'manta_natural_p.jpg', 'activo'),
(14, 8, '35', 'negro', 'OJO-103-35-negro', 15.00, 20, NULL, 'activo'),
(15, 8, '36', 'negro', 'OJO-103-36-negro', 15.00, 20, NULL, 'activo'),
(16, 8, '40', 'negro', 'OJO-103-40-negro', 16.00, 20, NULL, 'activo'),
(17, 9, 's', 'blanco con rayas', 'PON-104-s-blanco con rayas', 50.00, 10, NULL, 'activo'),
(18, 9, 'm', 'blanco con rayas', 'PON-104-m-blanco con rayas', 50.00, 10, NULL, 'activo'),
(19, 10, 'Unica', 'Estandar', 'QUE-105-Unica-Estandar', 25.00, 5, NULL, 'activo'),
(20, 11, 'M', 'Marron', 'CIN-106-M-Marron', 50.00, 10, NULL, 'activo'),
(21, 11, 'S', 'Marron', 'CIN-106-S-Marron', 20.00, 10, 'variante_106_1764437748.png', 'activo'),
(22, 12, 'Única', 'Estándar', 'ZAM-107-Única-Estándar', 25.00, 12, NULL, 'activo'),
(23, 13, 'Única', 'Estándar', 'CHA-108-Única-Estándar', 50.00, 12, NULL, 'activo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  ADD PRIMARY KEY (`id_calificacion`),
  ADD KEY `idx_id_producto` (`id_producto`),
  ADD KEY `idx_id_usuario` (`id_usuario`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nombre_categoria` (`nombre_categoria`),
  ADD KEY `id_categoria_padre` (`id_categoria_padre`);

--
-- Indices de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `idx_id_pedido` (`id_pedido`),
  ADD KEY `idx_id_variante` (`id_variante`),
  ADD KEY `fk_detalle_empresa_envio` (`id_empresa_envio`);

--
-- Indices de la tabla `direcciones`
--
ALTER TABLE `direcciones`
  ADD PRIMARY KEY (`id_direccion`),
  ADD KEY `idx_id_usuario` (`id_usuario`);

--
-- Indices de la tabla `empresas_envio`
--
ALTER TABLE `empresas_envio`
  ADD PRIMARY KEY (`id_empresa_envio`),
  ADD UNIQUE KEY `nombre_empresa` (`nombre_empresa`);

--
-- Indices de la tabla `estados_pedido`
--
ALTER TABLE `estados_pedido`
  ADD PRIMARY KEY (`id_estado`),
  ADD UNIQUE KEY `nombre_estado` (`nombre_estado`);

--
-- Indices de la tabla `login_intentos`
--
ALTER TABLE `login_intentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_fecha` (`ip`,`fecha_intento`),
  ADD KEY `idx_usuario_fecha` (`usuario`,`fecha_intento`);

--
-- Indices de la tabla `mensajes_contacto`
--
ALTER TABLE `mensajes_contacto`
  ADD PRIMARY KEY (`id_mensaje`);

--
-- Indices de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `id_direccion_envio` (`id_direccion_envio`),
  ADD KEY `idx_id_usuario` (`id_usuario`),
  ADD KEY `idx_id_estado_pedido` (`id_estado_pedido`);

--
-- Indices de la tabla `perfiles`
--
ALTER TABLE `perfiles`
  ADD PRIMARY KEY (`id_perfil`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD KEY `idx_id_categoria` (`id_categoria`),
  ADD KEY `idx_id_vendedor` (`id_vendedor`);

--
-- Indices de la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  ADD PRIMARY KEY (`id_imagen`),
  ADD KEY `idx_id_producto` (`id_producto`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `tarjetas_usuario`
--
ALTER TABLE `tarjetas_usuario`
  ADD PRIMARY KEY (`id_tarjeta`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD PRIMARY KEY (`id_transaccion`),
  ADD KEY `idx_id_pedido` (`id_pedido`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_id_rol` (`id_rol`),
  ADD KEY `idx_email` (`email`);

--
-- Indices de la tabla `variantes_producto`
--
ALTER TABLE `variantes_producto`
  ADD PRIMARY KEY (`id_variante`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_id_producto` (`id_producto`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  MODIFY `id_calificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT de la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT de la tabla `direcciones`
--
ALTER TABLE `direcciones`
  MODIFY `id_direccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT de la tabla `empresas_envio`
--
ALTER TABLE `empresas_envio`
  MODIFY `id_empresa_envio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT de la tabla `estados_pedido`
--
ALTER TABLE `estados_pedido`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `login_intentos`
--
ALTER TABLE `login_intentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de la tabla `mensajes_contacto`
--
ALTER TABLE `mensajes_contacto`
  MODIFY `id_mensaje` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id_pedido` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT de la tabla `perfiles`
--
ALTER TABLE `perfiles`
  MODIFY `id_perfil` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT de la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  MODIFY `id_imagen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tarjetas_usuario`
--
ALTER TABLE `tarjetas_usuario`
  MODIFY `id_tarjeta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT de la tabla `transacciones`
--
ALTER TABLE `transacciones`
  MODIFY `id_transaccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=114;

--
-- AUTO_INCREMENT de la tabla `variantes_producto`
--
ALTER TABLE `variantes_producto`
  MODIFY `id_variante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `calificaciones`
--
ALTER TABLE `calificaciones`
  ADD CONSTRAINT `calificaciones_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `calificaciones_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD CONSTRAINT `categorias_ibfk_1` FOREIGN KEY (`id_categoria_padre`) REFERENCES `categorias` (`id_categoria`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_pedido`
--
ALTER TABLE `detalle_pedido`
  ADD CONSTRAINT `detalle_pedido_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `detalle_pedido_ibfk_2` FOREIGN KEY (`id_variante`) REFERENCES `variantes_producto` (`id_variante`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_empresa_envio` FOREIGN KEY (`id_empresa_envio`) REFERENCES `empresas_envio` (`id_empresa_envio`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `direcciones`
--
ALTER TABLE `direcciones`
  ADD CONSTRAINT `direcciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_pr_email` FOREIGN KEY (`email`) REFERENCES `usuarios` (`email`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`id_direccion_envio`) REFERENCES `direcciones` (`id_direccion`) ON UPDATE CASCADE,
  ADD CONSTRAINT `pedidos_ibfk_3` FOREIGN KEY (`id_estado_pedido`) REFERENCES `estados_pedido` (`id_estado`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `perfiles`
--
ALTER TABLE `perfiles`
  ADD CONSTRAINT `perfiles_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE,
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`id_vendedor`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  ADD CONSTRAINT `fk_prod_img` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `tarjetas_usuario`
--
ALTER TABLE `tarjetas_usuario`
  ADD CONSTRAINT `tarjetas_usuario_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `transacciones`
--
ALTER TABLE `transacciones`
  ADD CONSTRAINT `transacciones_ibfk_1` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `variantes_producto`
--
ALTER TABLE `variantes_producto`
  ADD CONSTRAINT `variantes_producto_ibfk_1` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON DELETE CASCADE ON UPDATE CASCADE;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cupones`
--

CREATE TABLE `cupones` (
  `id_cupon` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `porcentaje_descuento` decimal(5,2) NOT NULL COMMENT 'Ej: 10.00 para 10%',
  `fecha_expiracion` datetime DEFAULT NULL,
  `estado` enum('activo','inactivo') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id_cupon`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cupones`
--

INSERT INTO `cupones` (`id_cupon`, `codigo`, `porcentaje_descuento`, `fecha_expiracion`, `estado`) VALUES
(1, 'TINKUY10', 10.00, '2026-12-31 23:59:59', 'activo'),
(2, 'ARTESANIA20', 20.00, '2026-12-31 23:59:59', 'activo');

-- --------------------------------------------------------

--
-- Eventos para limpieza automática de la Base de Datos
--

DELIMITER $$
CREATE EVENT IF NOT EXISTS `limpiar_login_intentos`
 ON SCHEDULE EVERY 1 HOUR
 DO
  DELETE FROM `login_intentos` WHERE `fecha_intento` < NOW() - INTERVAL 24 HOUR$$
DELIMITER ;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
