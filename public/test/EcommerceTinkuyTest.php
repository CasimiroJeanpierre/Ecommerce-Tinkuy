<?php
use PHPUnit\Framework\TestCase;

// Importamos las clases y funciones core que vamos a testear
require_once __DIR__ . '/../../src/Core/validaciones.php';
require_once __DIR__ . '/../../src/Core/Security.php';
require_once __DIR__ . '/../../src/Controllers/VentasController.php';
require_once __DIR__ . '/../../src/Controllers/OrderController.php';

class EcommerceTinkuyTest extends TestCase
{
    protected function setUp(): void
    {
        // Iniciamos la sesión de PHP en memoria requerida por las clases de Seguridad
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // --- 🛡️ PRUEBAS DE VALIDACIÓN Y SANITIZACIÓN ---

    // 1. Validar el Login (Casos correcto, vacío y caracteres inválidos)
    public function testValidarDatosLogin()
    {
        echo "\n-> [Test 1] Validando Login (Datos válidos, campos vacíos y caracteres no permitidos)...";

        $resCorrecto = validarDatosLogin('admin_test', 'clave1234');
        $this->assertNull(
            $resCorrecto,
            "❌ Error: El login válido fue rechazado."
        );
        echo "\n   ✅ Éxito: Login aceptado (Sin errores).";
        $this->assertNull($resCorrecto, "❌ Error: El login válido fue rechazado.");

        $resVacio = validarDatosLogin('', 'clave1234');
        $this->assertEquals(
            "El nombre de usuario es obligatorio.",
            $resVacio,
            "❌ Error: No se bloqueó el login sin usuario."
        );
        echo "\n   ✅ Éxito: " . $resVacio;
        $this->assertEquals("El nombre de usuario es obligatorio.", $resVacio);

        $resUsuarioCorto = validarDatosLogin('aa', 'clave1234');
        $this->assertEquals(
            "El usuario debe tener entre 3 y 50 caracteres.",
            $resUsuarioCorto,
            "❌ Error: No se bloqueó el usuario demasiado corto."
        );
        echo "\n   ✅ Éxito: " . $resUsuarioCorto;
        $this->assertEquals("El usuario debe tener entre 3 y 50 caracteres.", $resUsuarioCorto);

        $resUsuarioLargo = validarDatosLogin(str_repeat('a', 51), 'clave1234');
        $this->assertEquals(
            "El usuario debe tener entre 3 y 50 caracteres.",
            $resUsuarioLargo,
            "❌ Error: No se bloqueó el usuario demasiado largo."
        );
        echo "\n   ✅ Éxito: " . $resUsuarioLargo;
        $this->assertEquals("El usuario debe tener entre 3 y 50 caracteres.", $resUsuarioLargo);

        $resInvalido = validarDatosLogin('admin@!', 'clave1234');
        $this->assertEquals(
            "El usuario contiene caracteres no permitidos.",
            $resInvalido,
            "❌ Error: No se bloquearon caracteres inválidos."
        );
        echo "\n   ✅ Éxito: " . $resInvalido;
        $this->assertEquals("El usuario contiene caracteres no permitidos.", $resInvalido);

        $resClaveVacia = validarDatosLogin('admin_test', '');
        $this->assertEquals(
            "La contraseña es obligatoria.",
            $resClaveVacia,
            "❌ Error: No se bloqueó el login sin contraseña."
        );
        echo "\n   ✅ Éxito: " . $resClaveVacia;
        $this->assertEquals("La contraseña es obligatoria.", $resClaveVacia);

        $resClaveCorta = validarDatosLogin('admin_test', '123');
        $this->assertEquals(
            "La contraseña debe tener entre 6 y 30 caracteres.",
            $resClaveCorta,
            "❌ Error: No se bloqueó la contraseña demasiado corta."
        );
        echo "\n   ✅ Éxito: " . $resClaveCorta;
        $this->assertEquals("La contraseña debe tener entre 6 y 30 caracteres.", $resClaveCorta);

        $resClaveLarga = validarDatosLogin('admin_test', str_repeat('a', 31));
        $this->assertEquals(
            "La contraseña debe tener entre 6 y 30 caracteres.",
            $resClaveLarga,
            "❌ Error: No se bloqueó la contraseña demasiado larga."
        );
        echo "\n   ✅ Éxito: " . $resClaveLarga;
        $this->assertEquals("La contraseña debe tener entre 6 y 30 caracteres.", $resClaveLarga);
    }

    // 2. Validar Registro de Usuario (Casos correcto y longitud inválida)
    public function testValidarRegistroUsuario()
    {
        echo "\n-> [Test 2] Validando reglas para nombres de usuario nuevos...";

        $resCorrecto = validarUsuario('nuevo_cliente.123');
        $this->assertNull(
            $resCorrecto,
            "❌ Error: El usuario válido fue rechazado."
        );
        echo "\n   ✅ Éxito: Usuario aceptado (Sin errores).";
        $this->assertNull($resCorrecto, "❌ Error: El usuario válido fue rechazado.");

        $resVacio = validarUsuario('');
        $this->assertEquals(
            "El nombre de usuario es obligatorio.",
            $resVacio,
            "❌ Error: No se bloqueó el registro sin usuario."
        );
        echo "\n   ✅ Éxito: " . $resVacio;
        $this->assertEquals("El nombre de usuario es obligatorio.", $resVacio);


        $resCorto = validarUsuario('ab');
        $this->assertEquals(
            "El usuario debe tener entre 3 y 50 caracteres.",
            $resCorto,
            "❌ Error: No se bloqueó el usuario demasiado corto."
        );
        echo "\n   ✅ Éxito: " . $resCorto;
        $this->assertEquals("El usuario debe tener entre 3 y 50 caracteres.", $resCorto);

        $resLargo = validarUsuario(str_repeat('a', 51));
        $this->assertEquals(
            "El usuario debe tener entre 3 y 50 caracteres.",
            $resLargo,
            "❌ Error: No se bloqueó el usuario demasiado largo."
        );
        echo "\n   ✅ Éxito: " . $resLargo;
        $this->assertEquals("El usuario debe tener entre 3 y 50 caracteres.", $resLargo);

        $resInvalido = validarUsuario('usuario@!');
        $this->assertEquals(
            "El usuario solo puede contener letras, números, punto, guion y guion bajo.",
            $resInvalido,
            "❌ Error: No se bloquearon caracteres inválidos en el registro."
        );
        echo "\n   ✅ Éxito: " . $resInvalido;
        $this->assertEquals("El usuario solo puede contener letras, números, punto, guion y guion bajo.", $resInvalido);
    }

    // 3. Validar el filtro de Email (Casos correcto e incorrecto)
    public function testValidarEmail()
    {
        echo "\n-> [Test 3] Validando formatos de correo electrónico permitidos y bloqueados...";

        $resCorrecto = validarEmail('cliente@tinkuy.com');
        $this->assertNull(
            $resCorrecto,
            "❌ Error: El email válido fue rechazado."
        );
        echo "\n   ✅ Éxito: Email aceptado (Sin errores).";
        $this->assertNull($resCorrecto, "❌ Error: El email válido fue rechazado.");

        $resVacio = validarEmail('');
        $this->assertEquals(
            "El email es obligatorio.",
            $resVacio,
            "❌ Error: No se bloqueó el email vacío."
        );
        echo "\n   ✅ Éxito: " . $resVacio;
        $this->assertEquals("El email es obligatorio.", $resVacio);

        $resLargo = validarEmail(str_repeat('a', 90) . '@tinkuy.com');
        $this->assertEquals(
            "El email no debe exceder 100 caracteres.",
            $resLargo,
            "❌ Error: No se bloqueó el email demasiado largo."
        );
        echo "\n   ✅ Éxito: " . $resLargo;
        $this->assertEquals("El email no debe exceder 100 caracteres.", $resLargo);

        $resInvalido = validarEmail('correo-invalido.com');
        $this->assertEquals(
            "El formato del email no es válido.",
            $resInvalido,
            "❌ Error: No se bloqueó el email inválido."
        );
        echo "\n   ✅ Éxito: " . $resInvalido;
        $this->assertEquals("El formato del email no es válido.", $resInvalido);
    }

    // 4. Validar seguridad de contraseña (Casos correcto y corto)
    public function testValidarClave()
    {
        echo "\n-> [Test 4] Validando longitud y seguridad de contraseñas...";

        $resCorrecto = validarClave('ClaveSegura123!');
        $this->assertNull(
            $resCorrecto,
            "❌ Error: La contraseña válida fue rechazada."
        );
        echo "\n   ✅ Éxito: Contraseña aceptada (Sin errores).";
        $this->assertNull($resCorrecto, "❌ Error: La contraseña válida fue rechazada.");

        $resVacia = validarClave('');
        $this->assertEquals(
            "La contraseña es obligatoria.",
            $resVacia,
            "❌ Error: No se bloqueó la contraseña vacía."
        );
        echo "\n   ✅ Éxito: " . $resVacia;
        $this->assertEquals("La contraseña es obligatoria.", $resVacia);

        $resCorta = validarClave('12345');
        $this->assertEquals(
            "La contraseña debe tener entre 7 y 30 caracteres.",
            $resCorta,
            "❌ Error: No se bloqueó la contraseña muy corta."
        );
        echo "\n   ✅ Éxito: " . $resCorta;
        $this->assertEquals("La contraseña debe tener entre 7 y 30 caracteres.", $resCorta);

        $resLarga = validarClave(str_repeat('a', 31));
        $this->assertEquals(
            "La contraseña debe tener entre 7 y 30 caracteres.",
            $resLarga,
            "❌ Error: No se bloqueó la contraseña muy larga."
        );
        echo "\n   ✅ Éxito: " . $resLarga;
        $this->assertEquals("La contraseña debe tener entre 7 y 30 caracteres.", $resLarga);

        $resSinMayuscula = validarClave('clavesegura123!');
        $this->assertEquals(
            "La contraseña debe contener al menos una mayúscula.",
            $resSinMayuscula,
            "❌ Error: No se bloqueó la contraseña sin mayúsculas."
        );
        echo "\n   ✅ Éxito: " . $resSinMayuscula;
        $this->assertEquals("La contraseña debe contener al menos una mayúscula.", $resSinMayuscula);

        $resSinEspecial = validarClave('ClaveSegura1234');
        $this->assertEquals(
            "La contraseña debe contener al menos un carácter especial.",
            $resSinEspecial,
            "❌ Error: No se bloqueó la contraseña sin carácter especial."
        );
        echo "\n   ✅ Éxito: " . $resSinEspecial;
        $this->assertEquals("La contraseña debe contener al menos un carácter especial.", $resSinEspecial);
    }

    // 5. Validar nombres de personas (Casos correcto y con números)
    public function testValidarNombre()
    {
        echo "\n-> [Test 5] Validando formato de Nombres y Apellidos (Solo letras y tildes)...";

        $resCorrecto = validarNombre('Juan Pérez', 'El nombre');
        $this->assertNull(
            $resCorrecto,
            "❌ Error: El nombre válido fue rechazado."
        );
        echo "\n   ✅ Éxito: Nombre aceptado (Sin errores).";
        $this->assertNull($resCorrecto, "❌ Error: El nombre válido fue rechazado.");

        $resVacio = validarNombre('', 'El nombre');
        $this->assertEquals(
            "El nombre es obligatorio.",
            $resVacio,
            "❌ Error: No se bloqueó el nombre vacío."
        );
        echo "\n   ✅ Éxito: " . $resVacio;
        $this->assertEquals("El nombre es obligatorio.", $resVacio);

        $resCorto = validarNombre('A', 'El nombre');
        $this->assertEquals(
            "El nombre debe tener entre 2 y 100 caracteres.",
            $resCorto,
            "❌ Error: No se bloqueó el nombre muy corto."
        );
        echo "\n   ✅ Éxito: " . $resCorto;
        $this->assertEquals("El nombre debe tener entre 2 y 100 caracteres.", $resCorto);

        $resLargo = validarNombre(str_repeat('a', 101), 'El nombre');
        $this->assertEquals(
            "El nombre debe tener entre 2 y 100 caracteres.",
            $resLargo,
            "❌ Error: No se bloqueó el nombre muy largo."
        );
        echo "\n   ✅ Éxito: " . $resLargo;
        $this->assertEquals("El nombre debe tener entre 2 y 100 caracteres.", $resLargo);

        $resNumeros = validarNombre('Juan123', 'El nombre');
        $this->assertEquals(
            "El nombre solo puede contener letras y espacios.",
            $resNumeros,
            "❌ Error: No se bloqueó el nombre con números."
        );
        echo "\n   ✅ Éxito: " . $resNumeros;
        $this->assertEquals("El nombre solo puede contener letras y espacios.", $resNumeros);
    }

    // 6. Validar teléfonos peruanos (Casos correcto, opcional vacío y longitud inválida)
    public function testValidarTelefono()
    {
        echo "\n-> [Test 6] Validando formato de Teléfonos peruanos (Exclusivamente 9 dígitos)...";

        $resCorrecto = validarTelefono('999888777');
        $this->assertNull(
            $resCorrecto,
            "❌ Error: El teléfono válido fue rechazado."
        );
        echo "\n   ✅ Éxito: Teléfono aceptado (Sin errores).";
        $this->assertNull($resCorrecto, "❌ Error: El teléfono válido fue rechazado.");

        $resVacio = validarTelefono('');
        $this->assertNull(
            $resVacio,
            "❌ Error: El teléfono vacío (opcional) fue rechazado."
        );
        echo "\n   ✅ Éxito: Teléfono vacío permitido (Sin errores).";
        $this->assertNull($resVacio, "❌ Error: El teléfono vacío (opcional) fue rechazado.");

        $resInvalido = validarTelefono('999888');
        $this->assertEquals(
            "El teléfono debe tener exactamente 9 dígitos numéricos.",
            $resInvalido,
            "❌ Error: No se bloqueó la longitud incorrecta."
        );
        echo "\n   ✅ Éxito: " . $resInvalido;
        $this->assertEquals("El teléfono debe tener exactamente 9 dígitos numéricos.", $resInvalido);
    }

    // --- 🏷️ PRUEBAS DE DESCUENTOS Y CUPONES ---

    // 7. Lógica Financiera: Cálculo de cupones de descuento en el carrito
    public function testAplicarCuponDescuento()
    {
        echo "\n-> [Test 7] Evaluando lógica matemática de aplicación de cupones de descuento...";

        // Simulamos un carrito con S/ 250.00 y un cupón válido del 20% (0.20)
        $total_general = 250.00;
        $cupon_simulado = ['codigo' => 'ARTESANIA20', 'descuento' => 0.20];

        // Usamos las funciones reales del sistema
        $descuento_calculado = calcularDescuentoAplicado($total_general, $cupon_simulado['descuento']);
        $total_con_descuento = calcularTotalFinal($total_general, $descuento_calculado);

        $this->assertEquals(50.00, $descuento_calculado, "❌ Error: El monto descontado es incorrecto.");
        echo "\n   ✅ Éxito: Monto descontado con precisión usando calcularDescuentoAplicado() (S/ 50.00).";

        $this->assertEquals(200.00, $total_con_descuento, "❌ Error: El total final a pagar es incorrecto.");
        echo "\n   ✅ Éxito: Total final verificado usando calcularTotalFinal() (S/ 200.00).";
    }

    // --- 🛒 PRUEBAS DE LÓGICA DE NEGOCIO Y CONTROLADORES ---

    // 8. Lógica Financiera: Cálculo matemático correcto del total de ingresos
    public function testCalcularTotalIngresos()
    {
        echo "\n-> [Test 8] Evaluando cálculo matemático del total de ingresos en reportes de ventas...";
        $controller = new VentasController(null); // Instanciado sin BD para aislar la prueba
        $ventas_simuladas = [
            ['subtotal' => 150.50],
            ['subtotal' => 50.00],
            ['subtotal' => 99.50]
        ];
        $total = $controller->calcularTotalIngresos($ventas_simuladas);
        $this->assertEquals(300.00, $total, "❌ Error: El cálculo matemático sumó incorrectamente.");
        echo "\n   ✅ Éxito: Cálculo matemático exacto (300.00).";
    }

    // 9. Lógica de Catálogo: Validación estricta al crear productos y variantes
    public function testValidarCreacionProducto()
    {
        echo "\n-> [Test 9] Evaluando reglas de negocio al crear productos y variantes...";

        $resCorrecto = validarDatosProducto('Chompa de Alpaca', 150.50, 10);
        $this->assertNull(
            $resCorrecto,
            "❌ Error: Un producto válido fue rechazado."
        );
        echo "\n   ✅ Éxito: Producto con datos válidos aceptado.";
        $this->assertNull($resCorrecto, "❌ Error: Un producto válido fue rechazado.");

        $resPrecio = validarDatosProducto('Gorro Andino', 0.00, 5);
        $this->assertEquals(
            "El precio debe ser mayor a 0.",
            $resPrecio,
            "❌ Error: No se bloqueó el precio cero/negativo."
        );
        echo "\n   ✅ Éxito: " . $resPrecio;
        $this->assertEquals("El precio debe ser mayor a 0.", $resPrecio);

        $resStock = validarDatosProducto('Manta Artesanal', 85.00, -2);
        $this->assertEquals(
            "El stock no puede ser negativo.",
            $resStock,
            "❌ Error: No se bloqueó el stock negativo."
        );
        echo "\n   ✅ Éxito: " . $resStock;
        $this->assertEquals("El stock no puede ser negativo.", $resStock);
    }

    // 10. Lógica de Pagos: Validación de formato de Tarjetas de Crédito/Débito
    public function testValidarTarjetaPago()
    {
        echo "\n-> [Test 10] Evaluando validación de pasarela de pago (Tarjetas)...";

        $resCorrecto = validarTarjetaSimulada('4555666677778888', '12/26');
        $this->assertNull($resCorrecto, "❌ Error: Tarjeta válida rechazada.");
        echo "\n   ✅ Éxito: Tarjeta de crédito con formato válido aceptada.";

        $resNumero = validarTarjetaSimulada('4555666', '12/26');
        $this->assertEquals(
            "El número de tarjeta debe tener entre 13 y 16 dígitos numéricos.",
            $resNumero,
            "❌ Error: No se bloqueó un número incompleto."
        );
        echo "\n   ✅ Éxito: " . $resNumero;
        $this->assertEquals("El número de tarjeta debe tener entre 13 y 16 dígitos numéricos.", $resNumero);

        $resExpiracion = validarTarjetaSimulada('4555666677778888', '13/26'); // Mes 13 no existe
        $this->assertEquals(
            "El formato de expiración debe ser MM/AA.",
            $resExpiracion,
            "❌ Error: No se bloqueó un mes inválido."
        );
        echo "\n   ✅ Éxito: " . $resExpiracion;
        $this->assertEquals("El formato de expiración debe ser MM/AA.", $resExpiracion);
    }
}