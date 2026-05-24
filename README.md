🧩 Cómo instalar dependencias (vendor/) en tu proyecto Ecommerce-Tinkuy
🧠 Requisitos previos

Antes de empezar, asegúrate de tener instalado:

PHP (versión 8.1 o superior)
Puedes usar XAMPP, Laragon o WAMP.
👉 Descargar XAMPP

Composer (gestor de dependencias PHP)
👉 Descargar Composer

Para comprobar si ya lo tienes instalado, abre la terminal y ejecuta:

composer -V


Deberías ver algo como:

Composer version 2.x.x

🧩 Paso 1 — Clonar el proyecto

Abre tu terminal (CMD o PowerShell) y ejecuta:

git clone https://github.com/JEAS-14/Ecommerce-Tinkuy.git


Luego entra al directorio:

cd Ecommerce-Tinkuy

🧩 Paso 2 — Instalar las dependencias

Ejecuta este comando dentro de la carpeta del proyecto:

composer install


Esto descargará automáticamente todas las librerías en la carpeta /vendor/, incluyendo:

PHPMailer (para envío de correos)

El autoloader de Composer

📦 Verás aparecer la carpeta vendor/ al finalizar el proceso.

🧩 Paso 3 — Configurar tu entorno local

Crea una base de datos en phpMyAdmin llamada:

tinkuy_db


Importa el archivo SQL incluido en el proyecto (tinkuy_db.sql).

Ajusta los datos de conexión en:

assets/admin/db.php
Paso 4 — Probar el proyecto

Ejecuta tu servidor local (XAMPP o similar) y abre en el navegador:

http://localhost/Ecommerce-Tinkuy/


Ya deberías ver la tienda funcionando, con:

Login de usuarios y administradores

Recuperación de contraseña (vía Mailtrap)

Simulación de pasarela de pagos

Gestión de pedidos y perfiles

🧩 Paso 5 — (Opcional) Configurar Mailtrap

Si deseas probar el envío de correos (simulado), crea una cuenta en:

👉 https://mailtrap.io

Luego copia tus credenciales SMTP y colócalas en:

assets/admin/mailer_config.php
\n+🧪 Tests Automatizados (PHPUnit)
\n+Esta sección explica cómo ejecutar y demostrar la batería de tests del proyecto. Se usa **PHPUnit 10.5** configurado en `phpunit.xml` y una base de datos de pruebas que se crea automáticamente al correr los tests (script `test/db_setup.php`).\n\n+### Requisitos
1. Composer instalado (ya usado para `composer install`).\n+2. PHP >= 8.1.\n+3. (Opcional para cobertura) Extensión **Xdebug** habilitada. Verifica con: `php -m | find "xdebug"` (Windows) o `php -m | grep xdebug` (Linux/macOS).\n\n+### Comandos Básicos (Windows CMD desde raíz del proyecto)
```cmd
vendor\bin\phpunit              REM Ejecuta todos los tests
vendor\bin\phpunit --testdox     REM Salida legible tipo documentación
vendor\bin\phpunit --coverage-html coverage  REM Genera reporte HTML (requiere Xdebug)
vendor\bin\phpunit --coverage-text           REM Cobertura directa en consola
```
Los reportes HTML quedan en `coverage/` y el resumen TestDox en `test-reports/testdox.html` si se usa la configuración de logging.
\n+### Por Suite (definidas en phpunit.xml)
```cmd
vendor\bin\phpunit --testsuite Validaciones
vendor\bin\phpunit --testsuite Modelos
vendor\bin\phpunit --testsuite Controladores
```
### Archivo Específico
```cmd
vendor\bin\phpunit test\ValidacionesTest.php
vendor\bin\phpunit test\PaymentControllerTestExtended.php
```
### Ejecución Continua para Demostración
Se incluye el script `run_tests_live.bat` que re-ejecuta los tests cada 10 segundos mostrando fecha y hora:
```cmd
run_tests_live.bat          REM modo normal
run_tests_live.bat testdox  REM modo documentación
run_tests_live.bat coverage REM muestra cobertura en texto
```
Cancelar con `CTRL + C`.
\n+### Base de Datos de Pruebas
Los tests crean automáticamente una BD `tinkuy_db_test` y datos mínimos (productos, variantes, direcciones). No necesitas importar nada extra para pruebas. El script también restaura el stock para mantener tests idempotentes.
\n+### Estructura Relacionada a Testing
```
phpunit.xml                  # Configuración de PHPUnit
test/                        # Carpeta principal de tests
	bootstrap.php              # Inicializa entorno y BD de prueba
	db_setup.php               # Crea tablas y seed
	fixtures/                  # Datos reutilizables (usuarios, productos, categorías)
	ValidacionesTest.php       # Casos de validación login
	PaymentControllerTest.php  # Casos básicos de pago
	PaymentControllerTestExtended.php # Casos avanzados (rollback, variante inexistente)
```
### Commit de Referencia
Última integración de testing: mensaje tipo `feat(testing): configurar PHPUnit 10.5, agregar fixtures y pruebas ampliadas`.
\n+### Buenas Prácticas
- No subir `vendor/` (ya ignorado en `.gitignore`).\n+- Ejecutar `composer install` tras clonar antes de correr tests.\n+- Usar `--testdox` para presentación a docentes.\n+- Generar cobertura sólo cuando Xdebug esté disponible (evita sobrecoste en cada ciclo).\n+\n+### Problemas Frecuentes
| Problema | Causa | Solución |
|----------|-------|----------|
| Unknown database 'tinkuy_db_test' | Falló creación automática | Verificar permisos MySQL y que `test/db_setup.php` se ejecuta (revisar `bootstrap.php`) |
| Stock insuficiente inesperado | Tests previos consumieron stock | Confirmar restauración (línea de UPDATE en `db_setup.php`) |
| Warning configuración XML | Atributos no soportados (ej. verbose) | Usar esquema correcto 10.5 y quitar atributos obsoletos |
| Cobertura vacía | Falta Xdebug | Instalar/habilitar extensión Xdebug |
\n+### Ejemplo Flujo Demostración Rápida
```cmd
vendor\bin\phpunit --testdox
vendor\bin\phpunit --testsuite Controladores
vendor\bin\phpunit --coverage-text
run_tests_live.bat testdox
```

---

## 📚 Documentación Adicional

Toda la documentación técnica está organizada en la carpeta **`docs/`**:

- **[LEEME_PRIMERO.md](LEEME_PRIMERO.md)** - Guía rápida del Asistente IA 🤖
- **[docs/README.md](docs/README.md)** - Índice completo de documentación
- **[docs/ASISTENTE_IA.md](docs/ASISTENTE_IA.md)** - Documentación técnica del asistente de búsqueda
- **[docs/DIAGRAMA_FLUJO_IA.md](docs/DIAGRAMA_FLUJO_IA.md)** - Diagramas de arquitectura
- **[docs/TESTING.md](docs/TESTING.md)** - Guía de pruebas unitarias

---

Si deseas ampliar con tests de roles, concurrencia o autenticación real, crea nuevos archivos dentro de `test/` y agrégalos a la suite adecuada en `phpunit.xml`.

