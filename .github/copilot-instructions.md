## Instrucciones rápidas para agentes IA (Ecommerce-Tinkuy)

Breve y directo: estas notas ayudan a un agente a ser productivo al contribuir código aquí.

- ARQUITECTURA / FLUJO
  - Punto de entrada: `public/index.php`. El routing es por query param `?page=...` (switch-case). Añadir nuevas rutas editando ese `switch`.
  - Constante clave: `BASE_PATH` (definida en `public/index.php`) — úsala para `include/require` (p. ej. `include BASE_PATH . '/src/Views/components/footer.php'`).
  - Modelo MVC ligero: modelos en `src/Models/`, controladores en `src/Controllers/` y vistas en `src/Views/`. Las vistas se incluyen directamente; no hay motor de plantillas.
  - Persistencia: `src/Core/db.php` crea la conexión mysqli en `$conn` (host `localhost`, bd `tinkuy_db` por defecto). Cambia ahí los credenciales para dev.
  - Validaciones y utilidades: `src/Core/validaciones.php` contiene helpers de validación usados por controladores y vistas.

- CONVENCIONES PROYECTO
  - Ruteo: modificadores y lógica de página se implementan en `public/index.php`. Para páginas nuevas, crear vista en `src/Views/...` y añadir case en `switch ($page)`.
  - Mensajes al usuario: se usan tanto `$_SESSION['mensaje_error'] / mensaje_exito` como variables locales `$mensaje_error` en vistas. Mantén compatibilidad con ambos patrones.
  - Carrito: el carrito vive en `$_SESSION['carrito']` (clave = `id_variante`). Varios controladores esperan ese formato.
  - Acceso a archivos de imagen: rutas relativas usadas en vistas, p. ej. `/Ecommerce-Tinkuy/public/img/productos/`.
  - Seguridad básica: verás uso de `filter_input`, `filter_var`, y consultas preparadas (`$conn->prepare`). Sigue estas mismas prácticas.

- DEPENDENCIAS E INTEGRACIONES
  - Composer: hay `composer.json` (PHPMailer + phpunit). Ejecuta `composer install` antes de usar dependencias.
  - Tests: phpunit está en `vendor/bin` (en Windows: `vendor\bin\phpunit.bat`). También funciona `php vendor\bin\phpunit`.
  - Correo: usar `PHPMailer` (configurar en `assets/admin/mailer_config.php` si es necesario).

- WORKFLOWS Y COMANDOS ÚTILES
  - Levantar localmente (XAMPP): colocar el proyecto en `htdocs` y abrir `http://localhost/Ecommerce-Tinkuy/public/index.php` o configurar VirtualHost apuntando a `public/`.
  - Instalar dependencias: `composer install`.
  - Ejecutar tests: `vendor\bin\phpunit.bat` (desde la raíz del proyecto) o `php vendor\bin\phpunit`.
  - Linter/estático: no hay linter configurado; seguir estilo existente (PHP procedural + OOP mixto).

- DÓNDE HACER CAMBIOS COMUNES (ejemplos)
  - Nueva página pública: crear `src/Views/misc/nueva.php` y añadir `case 'nueva': require BASE_PATH . '/src/Views/misc/nueva.php'; break;` en `public/index.php`.
  - Nueva API/acción POST: manejar en `public/index.php` con `if ($_SERVER['REQUEST_METHOD'] === 'POST')` en el case correspondiente o crear controlador en `src/Controllers/` y `require`arlo.
  - Cambiar conexión DB: editar `src/Core/db.php` (host/port/usuario/password/database).

- COSAS PARA NO HACER / ATENCIÓN
  - No asumir autoload PSR-4: actualmente el proyecto incluye manualmente archivos en `public/index.php`; aunque Composer está disponible, muchas clases/archivos se cargan manualmente.
  - Evitar romper la semántica de `$_SESSION['carrito']` o el formato de mensajes al usuario; son esperados por múltiples vistas/controladores.

- PREGUNTAS ABIERTAS / SUGERENCIAS
  - Considerar añadir `vendor/autoload.php` y configurar autoloading PSR-4 para simplificar includes.
  - Documentar variables de sesión y formato del carrito en `README.md` o un archivo de arquitectura ligera.

Si algo no está claro o quieres que integre una sección extra (p. ej. lista de endpoints, mapeo de modelos -> tablas), dime cuál y lo añado.
