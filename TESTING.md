# Plan de Pruebas - Ecommerce Tinkuy

## 8.4 Estrategia de Ejecución Automática

### 8.4.1 Herramientas de Testing
- **Framework**: PHPUnit 10.5
- **Automatización**: Composer scripts
- **Entorno**: PHP 8.0+

### 8.4.2 Comandos de Ejecución

#### Ejecutar TODOS los tests
```bash
vendor\bin\phpunit
```
o
```bash
php vendor\bin\phpunit
```

#### Ejecutar una suite específica
```bash
# Solo tests de validaciones
vendor\bin\phpunit --testsuite Validaciones

# Solo tests de modelos
vendor\bin\phpunit --testsuite Modelos

# Solo tests de controladores
vendor\bin\phpunit --testsuite Controladores
```

#### Ejecutar un archivo de test específico
```bash
vendor\bin\phpunit test/ProductoTest.php
```

#### Ejecutar con cobertura de código (requiere Xdebug)
```bash
vendor\bin\phpunit --coverage-html coverage
```

#### Ejecutar en modo verbose (detallado)
```bash
vendor\bin\phpunit --verbose
```

### 8.4.3 Integración Continua (CI/CD)
Se recomienda configurar GitHub Actions o similar para ejecutar automáticamente los tests en cada commit:

```yaml
# .github/workflows/tests.yml
name: Run Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.0'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: vendor/bin/phpunit
```

### 8.4.4 Scripts de Composer (Opcional)
Agregar al `composer.json`:
```json
"scripts": {
    "test": "phpunit",
    "test:coverage": "phpunit --coverage-html coverage",
    "test:unit": "phpunit --testsuite Modelos",
    "test:validation": "phpunit --testsuite Validaciones"
}
```

Luego ejecutar con:
```bash
composer test
composer test:coverage
```

---

## 8.5 Datos de Prueba / Fixtures / Mocks

### 8.5.1 Fixtures (Datos de Prueba)

#### Datos de Usuario de Prueba
```php
// test/fixtures/usuarios.php
return [
    'usuario_valido' => [
        'usuario' => 'testuser',
        'clave' => 'password123',
        'email' => 'test@tinkuy.com',
        'rol' => 'cliente'
    ],
    'usuario_admin' => [
        'usuario' => 'admintest',
        'clave' => 'admin12345',
        'email' => 'admin@tinkuy.com',
        'rol' => 'admin'
    ],
    'usuario_vendedor' => [
        'usuario' => 'vendortest',
        'clave' => 'vendor123',
        'email' => 'vendor@tinkuy.com',
        'rol' => 'vendedor'
    ]
];
```

#### Datos de Producto de Prueba
```php
// test/fixtures/productos.php
return [
    'producto_valido' => [
        'id_producto' => 1,
        'nombre_producto' => 'Chompa de Alpaca',
        'descripcion' => 'Chompa artesanal de alpaca 100%',
        'precio' => 150.00,
        'stock' => 20,
        'estado' => 'activo',
        'id_categoria' => 1
    ],
    'producto_sin_stock' => [
        'id_producto' => 2,
        'nombre_producto' => 'Gorro Artesanal',
        'precio' => 45.00,
        'stock' => 0,
        'estado' => 'activo'
    ]
];
```

#### Datos de Categoría de Prueba
```php
// test/fixtures/categorias.php
return [
    'categoria_textiles' => [
        'id_categoria' => 1,
        'nombre_categoria' => 'Textiles',
        'descripcion' => 'Productos textiles artesanales'
    ],
    'categoria_ceramica' => [
        'id_categoria' => 2,
        'nombre_categoria' => 'Cerámica',
        'descripcion' => 'Productos de cerámica tradicional'
    ]
];
```

### 8.5.2 Mocks (Simulaciones)

#### Mock de Conexión a Base de Datos
```php
// En los tests
protected function setUp(): void
{
    // Mock de mysqli
    $this->connMock = $this->createMock(mysqli::class);
    
    // Configurar comportamiento esperado
    $stmtMock = $this->createMock(mysqli_stmt::class);
    $stmtMock->method('bind_param')->willReturn(true);
    $stmtMock->method('execute')->willReturn(true);
    
    $this->connMock->method('prepare')->willReturn($stmtMock);
}
```

#### Mock de Sesión
```php
// En los tests
protected function mockSession($userData)
{
    $_SESSION = [];
    $_SESSION['usuario_id'] = $userData['id'];
    $_SESSION['usuario'] = $userData['usuario'];
    $_SESSION['rol'] = $userData['rol'];
}
```

### 8.5.3 Uso de Fixtures en Tests
```php
public function testCrearProductoConFixture()
{
    $fixtures = require __DIR__ . '/fixtures/productos.php';
    $producto = $fixtures['producto_valido'];
    
    $this->assertEquals('Chompa de Alpaca', $producto['nombre_producto']);
    $this->assertEquals(150.00, $producto['precio']);
}
```

---

## 8.6 Criterios de Aceptación

### 8.6.1 Cobertura de Código
- **Mínimo aceptable**: 70% de cobertura de código
- **Objetivo**: 85% de cobertura
- **Crítico**: 100% de cobertura en funciones de validación y autenticación

### 8.6.2 Criterios por Módulo

#### Validaciones (CRÍTICO - 100%)
- ✅ Todas las validaciones de login deben pasar
- ✅ Validaciones de formato de datos
- ✅ Validaciones de longitud (min/max)
- ✅ Validaciones de caracteres permitidos
- ✅ Manejo correcto de campos vacíos

#### Modelos (Objetivo: 85%)
- ✅ CRUD básico funcional
- ✅ Validación de estructura de datos
- ✅ Validación de tipos de datos
- ✅ Manejo de valores nulos
- ✅ Valores por defecto correctos

#### Controladores (Objetivo: 75%)
- ✅ Flujo de autenticación correcto
- ✅ Validación de permisos por rol
- ✅ Manejo correcto de sesiones
- ✅ Redirecciones apropiadas
- ✅ Mensajes de error descriptivos

### 8.6.3 Criterios de Calidad

#### Éxito del Test
Un test se considera exitoso cuando:
1. ✅ Todos los asserts pasan
2. ✅ No hay errores fatales
3. ✅ No hay warnings críticos
4. ✅ Tiempo de ejecución < 5 segundos por suite
5. ✅ No hay memory leaks

#### Fallo del Test
Un test falla cuando:
1. ❌ Cualquier assert falla
2. ❌ Se lanza una excepción no capturada
3. ❌ Timeout (> 30 segundos)
4. ❌ Error de conexión a base de datos (en tests de integración)

### 8.6.4 Criterios de Regresión
Antes de cada release:
- ✅ Todos los tests existentes deben pasar
- ✅ Nuevas features deben tener tests
- ✅ Coverage no debe disminuir
- ✅ No debe haber tests marcados como "skipped"

### 8.6.5 Métricas de Calidad

#### Por Test Individual
- **Tiempo máximo**: 1 segundo
- **Assertions mínimas**: 1 por test
- **Assertions recomendadas**: 3-5 por test

#### Por Suite
- **Tests mínimos**: 5 por módulo
- **Tiempo máximo suite**: 30 segundos
- **Tasa de éxito**: 100%

### 8.6.6 Criterios de Aceptación Final

Para considerar el módulo de testing completo:

1. **Cobertura Global**
   - ✅ >= 70% de cobertura total
   - ✅ >= 90% en módulos críticos (auth, validaciones)
   - ✅ >= 80% en modelos principales

2. **Cantidad de Tests**
   - ✅ Mínimo 30 tests unitarios
   - ✅ Al menos 5 tests por módulo principal
   - ✅ Tests para todos los casos edge

3. **Calidad de Tests**
   - ✅ Todos los tests pasan
   - ✅ Tests son independientes (no dependen de orden)
   - ✅ Tests son repetibles (mismo resultado cada vez)
   - ✅ Tests son rápidos (< 1 seg cada uno)

4. **Documentación**
   - ✅ Cada test tiene descripción clara
   - ✅ README con instrucciones de ejecución
   - ✅ Fixtures documentados
   - ✅ Casos de uso documentados

### 8.6.7 Checklist de Validación

Antes de considerar completo el testing:

```
☐ PHPUnit instalado y configurado
☐ phpunit.xml creado y configurado
☐ Bootstrap configurado correctamente
☐ Al menos 30 tests implementados
☐ Todas las suites ejecutan correctamente
☐ Cobertura >= 70%
☐ Fixtures creados y documentados
☐ Mocks implementados donde necesario
☐ Documentación completa
☐ CI/CD configurado (opcional pero recomendado)
☐ Todos los tests pasan en entorno local
☐ Todos los tests pasan en entorno CI
☐ Sin warnings ni errores fatales
☐ Tiempo total de ejecución < 2 minutos
```

---

## Resumen de Comandos Útiles

```bash
# Ejecutar todos los tests
vendor\bin\phpunit

# Ejecutar con detalles
vendor\bin\phpunit --verbose

# Ejecutar suite específica
vendor\bin\phpunit --testsuite Validaciones

# Ejecutar test específico
vendor\bin\phpunit test/ProductoTest.php

# Ver cobertura en HTML
vendor\bin\phpunit --coverage-html coverage

# Generar reporte de cobertura en texto
vendor\bin\phpunit --coverage-text

# Modo watch (requiere extensión)
vendor\bin\phpunit --watch
```
